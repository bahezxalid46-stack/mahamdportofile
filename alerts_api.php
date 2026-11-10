<?php
define('APP_SECURE', true);
require_once 'config/security.php';
start_secure_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'تکایە سەرەتا لۆگین بکە'));
    exit;
}

require_once 'config/db.php';
$user_id = (int)$_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// پشکنینی ڕێگەپێدان و CSRF بۆ هەموو ئەو کردارانەی کە داتا دەگۆڕن یان دەسڕنەوە
$write_actions = array(
    'save_settings',
    'trigger_alert',
    'mark_all_read',
    'clear_all',
    'save_history',
    'delete_history_item',
    'clear_history',
    'save_all_devices',
    'save_all_bills'
);

if (in_array($action, $write_actions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(array('success' => false, 'error' => 'ئەم کردارە تەنها بە شێوازی POST ڕێگەپێدراوە'));
        exit;
    }
    $csrf_token = get_request_csrf_token();
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'تۆکنی پاراستن (CSRF) نادروستە، تکایە پەڕەکە نوێ بکەرەوە'));
        exit;
    }
}

// هەڵگرتنی ڕێکخستنە بنەڕەتییەکان ئەگەر لە داتابەیس نەبوو
function get_or_create_alert_settings($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM `alert_settings` WHERE `user_id` = ? LIMIT 1");
    $stmt->execute(array($user_id));
    $settings = $stmt->fetch();

    if (!$settings) {
        $stmt_ins = $pdo->prepare("INSERT INTO `alert_settings` (`user_id`, `kwh_alert_enabled`, `kwh_threshold`, `amp_alert_enabled`, `amp_threshold`) VALUES (?, 1, 400, 1, 40.0)");
        $stmt_ins->execute(array($user_id));
        return array(
            'user_id' => $user_id,
            'kwh_alert_enabled' => 1,
            'kwh_threshold' => 400,
            'amp_alert_enabled' => 1,
            'amp_threshold' => 40.0
        );
    }
    return $settings;
}

try {
    switch ($action) {
        // وەرگرتنی ڕێکخستنەکانی ئاگادارکردنەوە
        case 'get_settings':
            $settings = get_or_create_alert_settings($pdo, $user_id);
            echo json_encode(array('success' => true, 'settings' => $settings));
            break;

        // نوێکردنەوەی ڕێکخستنەکانی ئاگادارکردنەوە
        case 'save_settings':
            $kwh_enabled = (isset($_POST['kwh_alert_enabled']) && (int)$_POST['kwh_alert_enabled'] === 1) ? 1 : 0;
            $kwh_threshold = isset($_POST['kwh_threshold']) ? max(100, min(5000, (int)$_POST['kwh_threshold'])) : 400;
            $amp_enabled = (isset($_POST['amp_alert_enabled']) && (int)$_POST['amp_alert_enabled'] === 1) ? 1 : 0;
            $amp_threshold = isset($_POST['amp_threshold']) ? max(5.0, min(200.0, (float)$_POST['amp_threshold'])) : 40.0;

            get_or_create_alert_settings($pdo, $user_id);

            $stmt = $pdo->prepare("UPDATE `alert_settings` SET `kwh_alert_enabled` = ?, `kwh_threshold` = ?, `amp_alert_enabled` = ?, `amp_threshold` = ? WHERE `user_id` = ?");
            $stmt->execute(array($kwh_enabled, $kwh_threshold, $amp_enabled, $amp_threshold, $user_id));

            echo json_encode(array(
                'success' => true,
                'message' => 'ڕێکخستنەکان بە سەرکەوتوویی پاشەکەوت کران',
                'settings' => array(
                    'kwh_alert_enabled' => $kwh_enabled,
                    'kwh_threshold' => $kwh_threshold,
                    'amp_alert_enabled' => $amp_enabled,
                    'amp_threshold' => $amp_threshold
                )
            ));
            break;

        // وەرگرتنی لیستی هەموو نۆتیفیکەیشنەکان لە داتابەیس
        case 'get_notifications':
            $stmt = $pdo->prepare("SELECT * FROM `notifications` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 50");
            $stmt->execute(array($user_id));
            $notifications = $stmt->fetchAll();

            $unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = ? AND `is_read` = 0");
            $unread_stmt->execute(array($user_id));
            $unread_count = (int)$unread_stmt->fetchColumn();

            $settings = get_or_create_alert_settings($pdo, $user_id);

            echo json_encode(array(
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count,
                'settings' => $settings
            ));
            break;

        // پشکنین و تۆمارکردنی ئاگادارکردنەوە نوێیەکان لە داتابەیس
        case 'trigger_alert':
            $type = isset($_POST['type']) ? substr(trim($_POST['type']), 0, 20) : '';
            $value = isset($_POST['value']) ? max(0, (float)$_POST['value']) : 0;
            $title = isset($_POST['title']) ? mb_substr(trim($_POST['title']), 0, 255, 'UTF-8') : '';
            $message = isset($_POST['message']) ? mb_substr(trim($_POST['message']), 0, 1000, 'UTF-8') : '';
            $threshold = isset($_POST['threshold']) ? max(0, (float)$_POST['threshold']) : 0;

            if (empty($type) || empty($message) || !in_array($type, array('kwh', 'amp'), true)) {
                echo json_encode(array('success' => false, 'error' => 'زانیاری ناتەواوە'));
                exit;
            }

            // پشکنینی دووبارە نەبوونەوەی دەستبەجێ (لە ماوەی ١ کاتژمێردا بۆ هەمان بڕ و جۆر)
            $check_stmt = $pdo->prepare("SELECT id FROM `notifications` WHERE `user_id` = ? AND `type` = ? AND `threshold` = ? AND `created_at` >= (NOW() - INTERVAL 1 HOUR) ORDER BY id DESC LIMIT 1");
            $check_stmt->execute(array($user_id, $type, $threshold));
            $already_notified = $check_stmt->fetch();

            $inserted = false;
            if (!$already_notified) {
                $ins_stmt = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`, `value`, `threshold`, `is_read`) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $ins_stmt->execute(array($user_id, $type, $title, $message, $value, $threshold));
                $inserted = true;
            }

            $unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = ? AND `is_read` = 0");
            $unread_stmt->execute(array($user_id));
            $unread_count = (int)$unread_stmt->fetchColumn();

            echo json_encode(array(
                'success' => true,
                'inserted' => $inserted,
                'unread_count' => $unread_count
            ));
            break;

        // نیشانکردنی هەموو ئاگادارییەکان وەک خوێندراوە
        case 'mark_all_read':
            $stmt = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `user_id` = ?");
            $stmt->execute(array($user_id));
            echo json_encode(array('success' => true));
            break;

        // سڕینەوەی هەموو ئاگادارییەکان
        case 'clear_all':
            $stmt = $pdo->prepare("DELETE FROM `notifications` WHERE `user_id` = ?");
            $stmt->execute(array($user_id));
            echo json_encode(array('success' => true));
            break;

        // ==================== بەشی مێژوو (History) ====================
        case 'get_history':
            $stmt = $pdo->prepare("SELECT * FROM `user_history` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 50");
            $stmt->execute(array($user_id));
            $rows = $stmt->fetchAll();
            $formatted = array_map(function($r) {
                return array(
                    'id' => (int)$r['id'],
                    'watt' => (float)$r['watt'],
                    'hours' => (float)$r['hours'],
                    'costMonth' => (float)$r['cost_month'],
                    'domType' => $r['dom_type'],
                    'date' => date('Y-m-d H:i', strtotime($r['created_at']))
                );
            }, $rows);
            echo json_encode(array('success' => true, 'history' => $formatted));
            break;

        case 'save_history':
            $watt = isset($_POST['watt']) ? max(0, min(1000000, (float)$_POST['watt'])) : 0;
            $hours = isset($_POST['hours']) ? max(0, min(24, (float)$_POST['hours'])) : 0;
            $costMonth = isset($_POST['costMonth']) ? max(0, (float)$_POST['costMonth']) : 0;
            $domType = isset($_POST['domType']) ? substr(trim($_POST['domType']), 0, 50) : '';

            $stmt = $pdo->prepare("INSERT INTO `user_history` (`user_id`, `watt`, `hours`, `cost_month`, `dom_type`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(array($user_id, $watt, $hours, $costMonth, $domType));
            $insertedId = (int)$pdo->lastInsertId();

            echo json_encode(array('success' => true, 'id' => $insertedId));
            break;

        case 'delete_history_item':
            $item_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $stmt = $pdo->prepare("DELETE FROM `user_history` WHERE `id` = ? AND `user_id` = ?");
            $stmt->execute(array($item_id, $user_id));
            echo json_encode(array('success' => true));
            break;

        case 'clear_history':
            $stmt = $pdo->prepare("DELETE FROM `user_history` WHERE `user_id` = ?");
            $stmt->execute(array($user_id));
            echo json_encode(array('success' => true));
            break;

        // ==================== بەشی ماڵەکەم (Home Devices) ====================
        case 'get_devices':
            $stmt = $pdo->prepare("SELECT * FROM `user_devices` WHERE `user_id` = ? ORDER BY `id` ASC");
            $stmt->execute(array($user_id));
            $rows = $stmt->fetchAll();
            $devices = array_map(function($r) {
                return array(
                    'id' => (float)$r['device_client_id'],
                    'db_id' => (int)$r['id'],
                    'name' => $r['name'],
                    'watt' => (float)$r['watt'],
                    'hours' => (float)$r['hours'],
                    'qty' => (int)$r['qty'],
                    'icon' => $r['icon']
                );
            }, $rows);
            echo json_encode(array('success' => true, 'devices' => $devices));
            break;

        case 'save_all_devices':
            $devicesJson = isset($_POST['devices']) ? $_POST['devices'] : '[]';
            $devices = json_decode($devicesJson, true);
            if (!is_array($devices)) $devices = array();
            // دانانی سنووری زۆرترین ژمارەی ئامێر بۆ پاراستنی بنکەدراوە
            $devices = array_slice($devices, 0, 200);

            $pdo->beginTransaction();
            $delStmt = $pdo->prepare("DELETE FROM `user_devices` WHERE `user_id` = ?");
            $delStmt->execute(array($user_id));

            $insStmt = $pdo->prepare("INSERT INTO `user_devices` (`user_id`, `device_client_id`, `name`, `watt`, `hours`, `qty`, `icon`) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($devices as $d) {
                $cId = isset($d['id']) ? (float)$d['id'] : time();
                $name = isset($d['name']) ? mb_substr(trim($d['name']), 0, 100, 'UTF-8') : 'ئامێر';
                $watt = isset($d['watt']) ? max(0, min(100000, (float)$d['watt'])) : 0;
                $hours = isset($d['hours']) ? max(0, min(24, (float)$d['hours'])) : 0;
                $qty = isset($d['qty']) ? max(1, min(1000, (int)$d['qty'])) : 1;
                $icon = isset($d['icon']) ? mb_substr(trim($d['icon']), 0, 20, 'UTF-8') : '🔌';

                $insStmt->execute(array($user_id, $cId, $name, $watt, $hours, $qty, $icon));
            }
            $pdo->commit();

            echo json_encode(array('success' => true));
            break;

        // ==================== بەشی پسوولەکان و چارت (User Bills) ====================
        case 'get_bills':
            $stmt = $pdo->prepare("SELECT * FROM `user_bills` WHERE `user_id` = ? ORDER BY `id` ASC");
            $stmt->execute(array($user_id));
            $rows = $stmt->fetchAll();
            $bills = array_map(function($r) {
                return array(
                    'id' => !empty($r['bill_client_id']) ? $r['bill_client_id'] : ('bill_' . $r['id']),
                    'db_id' => (int)$r['id'],
                    'month' => $r['month_name'],
                    'cost' => (float)$r['cost'],
                    'kwh' => (float)$r['kwh'],
                    'notes' => $r['notes'] !== null ? $r['notes'] : '',
                    'created_at' => $r['created_at']
                );
            }, $rows);
            echo json_encode(array('success' => true, 'bills' => $bills));
            break;

        case 'save_all_bills':
            $billsJson = isset($_POST['bills']) ? $_POST['bills'] : '[]';
            $bills = json_decode($billsJson, true);
            if (!is_array($bills)) $bills = array();
            $bills = array_slice($bills, 0, 200);

            $pdo->beginTransaction();
            $delStmt = $pdo->prepare("DELETE FROM `user_bills` WHERE `user_id` = ?");
            $delStmt->execute(array($user_id));

            $insStmt = $pdo->prepare("INSERT INTO `user_bills` (`user_id`, `bill_client_id`, `month_name`, `cost`, `kwh`, `notes`) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($bills as $b) {
                $cId = isset($b['id']) ? substr(trim((string)$b['id']), 0, 64) : ('bill_' . time());
                $month = isset($b['month']) ? mb_substr(trim((string)$b['month']), 0, 100, 'UTF-8') : '';
                $cost = isset($b['cost']) ? max(0, (float)$b['cost']) : 0;
                $kwh = isset($b['kwh']) ? max(0, (float)$b['kwh']) : 0;
                $notes = isset($b['notes']) ? mb_substr(trim((string)$b['notes']), 0, 500, 'UTF-8') : '';

                if ($month !== '') {
                    $insStmt->execute(array($user_id, $cId, $month, $cost, $kwh, $notes));
                }
            }
            $pdo->commit();

            echo json_encode(array('success' => true));
            break;

        default:
            echo json_encode(array('success' => false, 'error' => 'کرداری نەناسراو'));
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("API error in alerts_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'کێشەیەک لە سێرڤەر ڕوویدا'));
}
