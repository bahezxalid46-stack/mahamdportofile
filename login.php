<?php
define('APP_SECURE', true);
require_once 'config/security.php';
start_secure_session();

$just_logged_out = false;

// ئەگەر داوای دەرچوون (Logout) کرابێت
if (isset($_GET['logout'])) {
    destroy_secure_session();
    start_secure_session();
    $just_logged_out = true;
}

// ئەگەر پێشتر داخڵ ببوو و داوای دەرچوونی نەکردبوو، ڕاستەوخۆ بیگوازەرەوە بۆ لاپەڕەی سەرەکی
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/db.php';

$error_msg = '';
$success_msg = '';
$active_tab = 'login';
$csrf_token = get_csrf_token();

// فەنکشنی هاشکردن کە لەگەڵ هەموو وەشانێکی کۆن و نوێی PHP کاردەکات
function safe_hash_password($password) {
    if (function_exists('password_hash')) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    return 'sha256$' . hash('sha256', 'smart_meter_salt_#99' . $password);
}

function safe_verify_password($password, $hashedPassword) {
    if (function_exists('password_verify') && strpos($hashedPassword, 'sha256$') !== 0) {
        return password_verify($password, $hashedPassword);
    }
    $expected = 'sha256$' . hash('sha256', 'smart_meter_salt_#99' . $password);
    if (function_exists('hash_equals')) {
        return hash_equals($expected, $hashedPassword);
    }
    return ($expected === $hashedPassword);
}

// پشکنینی ژمارەی هەوڵە هەڵەکانی چوونەژوورەوە لە ماوەی ١٠ خولەکی ڕابردوودا (Brute-Force Protection)
function is_login_rate_limited($pdo, $ip) {
    try {
        // سڕینەوەی تۆمارە کۆنەکانی پێش ١ کاتژمێر
        $pdo->exec("DELETE FROM `login_attempts` WHERE `attempted_at` < (NOW() - INTERVAL 1 HOUR)");
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `login_attempts` WHERE `ip_address` = ? AND `attempted_at` >= (NOW() - INTERVAL 10 MINUTE)");
        $stmt->execute(array($ip));
        $attempts = (int)$stmt->fetchColumn();
        return ($attempts >= 5);
    } catch (Exception $e) {
        return false;
    }
}

function record_failed_login($pdo, $ip, $username) {
    try {
        $stmt = $pdo->prepare("INSERT INTO `login_attempts` (`ip_address`, `username`) VALUES (?, ?)");
        $stmt->execute(array($ip, substr($username, 0, 100)));
    } catch (Exception $e) {
        // تۆمارکردن لە لۆگ بە نهێنی
    }
}

function clear_failed_logins($pdo, $ip) {
    try {
        $stmt = $pdo->prepare("DELETE FROM `login_attempts` WHERE `ip_address` = ?");
        $stmt->execute(array($ip));
    } catch (Exception $e) {
    }
}

// کاتێک فۆرمێک Submit دەکرێت
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $posted_csrf = get_request_csrf_token();

    // ١. پشکنینی تۆکنی پاراستن (CSRF Token)
    if (!verify_csrf_token($posted_csrf)) {
        $error_msg = 'دانیشتنەکە بەسەرچووە یان داواکارییەکە ڕێگەپێنەدراوە، تکایە دووبارە هەوڵ بدەرەوە!';
    }
    // ٢. دروستکردنی هەژماری نوێ
    elseif ($action === 'register') {
        $active_tab = 'register';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($username) || empty($email) || empty($password)) {
            $error_msg = 'تکایە هەموو خانەکان پڕبکەرەوە!';
        } elseif (mb_strlen($username, 'UTF-8') < 3 || mb_strlen($username, 'UTF-8') > 50) {
            $error_msg = 'ناوی بەکارهێنەر دەبێت لە نێوان ٣ بۆ ٥٠ پیت بێت!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $error_msg = 'ئیمەیڵەکە نادروستە!';
        } elseif (strlen($password) < 6 || strlen($password) > 128) {
            $error_msg = 'وشەی نهێنی نابێت کەمتر بێت لە ٦ پیت!';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute(array($username, $email));
                if ($stmt->fetch()) {
                    $error_msg = 'ئەم ناوی بەکارهێنەرە یان ئیمەیڵە پێشتر بەکارهاتووە!';
                } else {
                    $hashedPassword = safe_hash_password($password);
                    $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    if ($insertStmt->execute(array($username, $email, $hashedPassword))) {
                        $success_msg = 'هەژمارەکەت بە سەرکەوتوویی دروستکرا! ئێستا دەتوانیت لۆگین بکەیت.';
                        $active_tab = 'login';
                    } else {
                        $error_msg = 'کێشەیەک ڕوویدا لە دروستکردنی هەژمارەکەدا.';
                    }
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error_msg = 'کێشەیەک لە سێرڤەر ڕوویدا، تکایە دواتر هەوڵ بدەرەوە.';
            }
        }
    }

    // ٣. چوونەژوورەوە
    elseif ($action === 'login') {
        $active_tab = 'login';
        $usernameOrEmail = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $client_ip = get_client_ip();

        if (is_login_rate_limited($pdo, $client_ip)) {
            $error_msg = 'ژمارەی هەوڵە هەڵەکانت زۆر بووە! تکایە دوای ١٠ خولەکی تر هەوڵ بدەرەوە.';
        } elseif (empty($usernameOrEmail) || empty($password)) {
            $error_msg = 'تکایە هەردوو خانەکە پڕبکەرەوە!';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute(array($usernameOrEmail, $usernameOrEmail));
                $user = $stmt->fetch();

                if ($user && safe_verify_password($password, $user['password'])) {
                    clear_failed_logins($pdo, $client_ip);

                    // گۆڕینی ناسنامەی سێشن دوای لۆگین بۆ ڕێگری لە دزینی دانیشتن (Session Fixation Protection)
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['ua_fingerprint'] = isset($_SERVER['HTTP_USER_AGENT']) ? hash('sha256', $_SERVER['HTTP_USER_AGENT']) : 'unknown';
                    $_SESSION['last_regen'] = time();

                    header("Location: index.php");
                    exit;
                } else {
                    record_failed_login($pdo, $client_ip, $usernameOrEmail);
                    $error_msg = 'ناوی بەکارهێنەر یان وشەی نهێنی هەڵەیە!';
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error_msg = 'کێشەیەک لە سێرڤەر ڕوویدا، تکایە دواتر هەوڵ بدەرەوە.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چوونەژوورەوە | Smart Meter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.85);
            --border: rgba(255, 255, 255, 0.12);
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a 65%);
            color: var(--text);
            padding: 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 36px 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }

        .brand {
            text-align: center;
            margin-bottom: 25px;
        }

        .brand .logo-icon {
            font-size: 2.8rem;
            color: #fbbf24;
            margin-bottom: 8px;
            display: inline-block;
        }

        .brand h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .brand p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .tabs {
            display: flex;
            background: rgba(15, 23, 42, 0.8);
            padding: 5px;
            border-radius: 14px;
            margin-bottom: 22px;
            border: 1px solid var(--border);
        }

        .tab-btn {
            flex: 1;
            padding: 11px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: var(--primary);
            color: #fff;
        }

        .field-group {
            margin-bottom: 18px;
        }

        .field-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.88rem;
            color: #cbd5e1;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i.icon {
            position: absolute;
            right: 15px;
            color: var(--text-muted);
            font-size: 1rem;
        }

        .input-wrapper input {
            width: 100%;
            padding: 13px 42px 13px 15px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }

        .submit-btn {
            width: 100%;
            padding: 13px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .submit-btn:hover {
            background: var(--primary-hover);
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.4;
        }

        .alert-box.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }

        .alert-box.success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="brand">
        <div class="logo-icon"><i class="fas fa-bolt"></i></div>
        <h1>سیستمی کارەبای کوردستان</h1>
        <p>چوونەژوورەوە بۆ ژمێرەری زیرەک</p>
    </div>

    <div class="tabs">
        <button type="button" class="tab-btn <?php echo ($active_tab === 'login') ? 'active' : ''; ?>" id="tab-login" onclick="switchTab('login')">چوونەژوورەوە</button>
        <button type="button" class="tab-btn <?php echo ($active_tab === 'register') ? 'active' : ''; ?>" id="tab-register" onclick="switchTab('register')">تۆمارکردنی نوێ</button>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert-box error"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert-box success"><?php echo htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <!-- فۆرمی چوونەژوورەوە (Login) -->
    <form id="loginForm" method="POST" action="login.php" class="<?php echo ($active_tab === 'login') ? '' : 'hidden'; ?>">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        
        <div class="field-group">
            <label for="login-username">ناوی بەکارهێنەر یان ئیمەیڵ</label>
            <div class="input-wrapper">
                <i class="fas fa-user icon"></i>
                <input type="text" id="login-username" name="username" maxlength="100" autocomplete="username" required placeholder="ناوی بەکارهێنەر یان ئیمەیڵ">
            </div>
        </div>

        <div class="field-group">
            <label for="login-password">وشەی نهێنی (Password)</label>
            <div class="input-wrapper">
                <i class="fas fa-lock icon"></i>
                <input type="password" id="login-password" name="password" maxlength="128" autocomplete="current-password" required placeholder="••••••••">
            </div>
        </div>

        <button type="submit" class="submit-btn" id="loginBtn">
            <i class="fas fa-sign-in-alt"></i> چوونەژوورەوە
        </button>
    </form>

    <!-- فۆرمی دروستکردنی هەژمار (Register) -->
    <form id="registerForm" method="POST" action="login.php" class="<?php echo ($active_tab === 'register') ? '' : 'hidden'; ?>">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="field-group">
            <label for="reg-username">ناوی بەکارهێنەر (Username)</label>
            <div class="input-wrapper">
                <i class="fas fa-user-tag icon"></i>
                <input type="text" id="reg-username" name="username" minlength="3" maxlength="50" autocomplete="username" required placeholder="ناوی بەکارهێنەر">
            </div>
        </div>

        <div class="field-group">
            <label for="reg-email">ئیمەیڵ (Email)</label>
            <div class="input-wrapper">
                <i class="fas fa-envelope icon"></i>
                <input type="email" id="reg-email" name="email" maxlength="100" autocomplete="email" required placeholder="user@example.com">
            </div>
        </div>

        <div class="field-group">
            <label for="reg-password">وشەی نهێنی (Password)</label>
            <div class="input-wrapper">
                <i class="fas fa-lock icon"></i>
                <input type="password" id="reg-password" name="password" minlength="6" maxlength="128" autocomplete="new-password" required placeholder="بە لایەنی کەم ٦ پیت">
            </div>
        </div>

        <button type="submit" class="submit-btn" id="regBtn">
            <i class="fas fa-user-plus"></i> دروستکردنی هەژمار
        </button>
    </form>
</div>

<script>
    <?php if ($just_logged_out): ?>
    // پاککردنەوەی هەموو داتای هەستیاری بەکارهێنەر لە براوسەر دوای چوونەدەرەوە (Logout)
    // تا کەسی تر لەسەر هەمان ئامێر نەتوانێت مێژوو، ئامێرەکان، پسوولەکان یان کلیلی AI ببینێت
    (function() {
        try {
            var keysToRemove = [];
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                if (k && (
                    k.indexOf('homeDevices_') === 0 ||
                    k.indexOf('calcHistory_') === 0 ||
                    k.indexOf('smart_meter_bills_') === 0 ||
                    k === 'calcHistory' ||
                    k === 'gemini_api_key'
                )) {
                    keysToRemove.push(k);
                }
            }
            keysToRemove.forEach(function(k) { localStorage.removeItem(k); });
            sessionStorage.clear();
        } catch (e) {}
    })();
    <?php endif; ?>

    function switchTab(tab) {
        var loginForm = document.getElementById('loginForm');
        var regForm = document.getElementById('registerForm');
        var tabLogin = document.getElementById('tab-login');
        var tabRegister = document.getElementById('tab-register');

        if (tab === 'login') {
            loginForm.classList.remove('hidden');
            regForm.classList.add('hidden');
            tabLogin.classList.add('active');
            tabRegister.classList.remove('active');
        } else {
            loginForm.classList.add('hidden');
            regForm.classList.remove('hidden');
            tabRegister.classList.add('active');
            tabLogin.classList.remove('active');
        }
    }
</script>

</body>
</html>
