<?php
define('APP_SECURE', true);
require_once 'config/security.php';
start_secure_session();

// ئەگەر بەکارهێنەر لۆگینی نەکردبێت، ڕاستەوخۆ بڕۆ بۆ لاپەڕەی لۆگین
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ژمێرەری زیرەکی کارەبای کوردستان - تێچووی کارەباکەت بە ئاسانی بزانە">
    <title>کارەبای کوردستان - ژمێرەر و ڕێنمایی</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="favicon.svg">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <link rel="stylesheet" href="style.css?v=2.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        window.CURRENT_USER_ID = <?php echo json_encode((int)$_SESSION['user_id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.CURRENT_USERNAME = <?php echo json_encode(isset($_SESSION['username']) ? $_SESSION['username'] : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.CSRF_TOKEN = <?php echo json_encode($csrf_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- کتێبخانەی چارت و سکانەری پسوولە (OCR) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

</head>

<body class="rtl">

    <header>
        <div class="header-content">
            <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle Menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
            <div class="logo">⚡ Smart Meter</div>

            <nav id="horizontal-nav">
                <a onclick="showPage('calculator')" id="nav-calc" class="active"><i class="fas fa-calculator"></i> ژمێرەر</a>
                <a onclick="showPage('converter')" id="nav-conv"><i class="fas fa-exchange-alt"></i> گۆڕین</a>
                <a onclick="showPage('myhome')" id="nav-home"><i class="fas fa-home"></i> ماڵەکەم</a>
                <a onclick="showPage('chart')" id="nav-chart"><i class="fas fa-chart-line"></i> چارت و پسوولە</a>
                <a onclick="showPage('tips')" id="nav-tips"><i class="fas fa-lightbulb"></i> ڕێنمایی</a>
                <a onclick="showPage('history')" id="nav-history"><i class="fas fa-history"></i> مێژوو</a>
                <a onclick="showPage('faq')" id="nav-faq"><i class="fas fa-question-circle"></i> پرسیار و وەڵام</a>
                <a onclick="showPage('about')" id="nav-about"><i class="fas fa-user"></i> دەربارە</a>
                <a onclick="showPage('settings')" id="nav-settings"><i class="fas fa-cog"></i> ڕێکخستن</a>
            </nav>

            <div class="controls">
                <!-- دوگمە و درۆپداونی ئاگادارییەکان (Notifications) -->
                <div class="notification-wrapper">
                    <button type="button" class="btn-notif" id="btnNotifToggle" onclick="toggleNotificationDropdown()" aria-label="Notifications" title="ئاگادارکردنەوەکان">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notif-drop-header">
                            <div class="notif-drop-title">
                                <i class="fas fa-bell"></i>
                                <span id="notif-drop-heading">ئاگادارکردنەوەکان</span>
                            </div>
                            <div class="notif-actions">
                                <button type="button" class="btn-notif-action" onclick="markAllNotificationsRead()" title="خوێندنەوەی هەمووی">
                                    <i class="fas fa-check-double"></i>
                                </button>
                                <button type="button" class="btn-notif-action text-danger" onclick="clearAllNotifications()" title="سڕینەوەی هەمووی">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty" id="notifEmpty">
                                <i class="fas fa-bell-slash"></i>
                                <p id="notif-empty-text">هیچ ئاگادارکردنەوەیەک نییە</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="user-session-badge">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'بەکارهێنەر'); ?></span>
                </div>
                <a href="login.php?logout=1" class="btn-logout" title="دەرچوون">
                    <i class="fas fa-sign-out-alt"></i> <span>دەرچوون</span>
                </a>
            </div>
        </div>
    </header>

    <main class="container">

        <article id="calculator" class="page active">
            <h2 id="calc-title">⚡ ژمێرەری زیرەکی کارەبا</h2>

            <div class="field">
                <label for="userType">جۆری بەشداربوو:</label>
                <select id="userType" onchange="checkUserType()">
                    <option value="dom">ماڵان</option>
                    <option value="185">بازرگانی (185 دینار)</option>
                    <option value="125">پیشەسازی گەورە (125 دینار)</option>
                    <option value="160">پیشەسازی بچووک (160 دینار)</option>
                    <option value="160">میری (160 دینار)</option>
                    <option value="60">کشتوکاڵی (60 دینار)</option>
                </select>
            </div>

            <div class="field" id="domOptions" style="display: none; animation: fadeScale 0.3s ease-out;">
                <label for="domType">جۆری بەکارهێنانی ماڵان:</label>
                <select id="domType">
                    <option value="72">1-400kWh یەکە (72 دینار)</option>
                    <option value="108">401-800kWh یەکە (108 دینار)</option>
                    <option value="175">801-1200kWh یەکە (175 دینار)</option>
                    <option value="265">1201-1600kWh یەکە (265 دینار)</option>
                    <option value="350">سەرووی 1600kWh یەکە (350 دینار)</option>
                </select>
            </div>

            <div class="field">
                <div class="label-row">
                    <label for="wattInput" id="label-watt">بڕی کارەبای ئامێرەکە (Watt):</label>
                    <button type="button" class="btn-preset-trigger" onclick="togglePresets()" id="btn-presets">📋
                        هەڵبژاردنی ئامێر</button>
                </div>
                <div id="presetsGrid" class="presets-grid-hidden">

                </div>
                <input type="number" id="wattInput" min="1">
            </div>


            <div class="field">
                <label for="hourInput" id="label-hours">کاتژمێری کارکردن لە ڕۆژێکدا:</label>
                <input type="number" id="hourInput" placeholder="24" min="1" max="24">
            </div>

            <div class="field period-field">
                <label for="calcPeriodDays" class="period-title"><i class="fas fa-calendar-alt"></i> <span id="calc-period-label">ژمارەی ڕۆژەکان</span></label>
                <div class="period-wrap open" id="calcPeriodWrap">
                    <div class="period-range-box">
                        <input type="range" id="calcPeriodRange" min="1" max="61" value="30" oninput="syncPeriod('calc', this.value)">
                        <div class="period-ticks"><span>1</span><span>15</span><span>30</span><span>45</span><span>61</span></div>
                    </div>
                    <div class="period-num">
                        <input type="number" id="calcPeriodDays" min="1" max="61" value="30" oninput="syncPeriod('calc', this.value, true)">
                        <span id="calc-period-unit">ڕۆژ</span>
                    </div>
                </div>
            </div>


            <button onclick="calculate()" class="btn-calculate">
                <span>حیسابی بکە</span>
                <i class="fas fa-calculator"></i>
            </button>

            <section id="resultDisplay" class="result-box">
                <div class="result-header">
                    <span id="res-main-title">ئەنجامی حیسابکردن</span>
                </div>
                <div class="result-grid" style="display:none">
                    <div class="res-card">
                        <span class="res-card-label" id="res-day-label">ڕۆژانە</span>
                        <span class="res-card-value"><span id="priceDay">0</span> <small
                                id="curr-day">دینار</small></span>
                    </div>
                    <div class="res-card">
                        <span class="res-card-label" id="res-week-label">هەفتانە</span>
                        <span class="res-card-value"><span id="priceWeek">0</span> <small
                                id="curr-week">دینار</small></span>
                    </div>
                    <div class="res-card highlight">
                        <span class="res-card-label" id="res-month-label">مانگانە</span>
                        <span class="res-card-value"><span id="priceMonth">0</span> <small
                                id="curr-month">دینار</small></span>
                    </div>
                    <div class="res-card">
                        <span class="res-card-label" id="res-year-label">ساڵانە</span>
                        <span class="res-card-value"><span id="priceYear">0</span> <small
                                id="curr-year">دینار</small></span>
                    </div>
                </div>
                <div class="result-footer" style="display:none">
                    <span id="res-unit-label">سەرفیاتی مانگانە:</span>
                    <span id="unitOut">0</span> <span id="monthly-unit-text">یەکە (kWh)</span>
                </div>
                <div class="period-result" id="calcPeriodResult" style="display:none">
                    <span class="period-result-label" id="calcPeriodCostLabel"></span>
                    <span class="period-result-value"><span id="calcPeriodCost">0</span> <small id="calcPeriodCurr">دینار</small></span>
                    <span class="period-result-units" id="calcPeriodUnits"></span>
                    <small class="period-note" id="calcPeriodNote"></small>
                </div>
                    <button onclick="exportAsImage('resultDisplay')" class="btn-export" id="btn-export-img">
                    <i class="fas fa-camera"></i> داگرتن وەک وێنە
                </button>
            </section>


            </section>
        </article>


        <article id="converter" class="page">
            <h2 id="conv-title">🔌 گۆڕینی ئەمپێر بۆ وات</h2>

            <div class="converter-box">
                <div class="field">
                    <label id="label-amp">ئەمپێر (Ampere):</label>
                    <input type="number" id="ampInput" placeholder="10">
                </div>

                <div class="field">
                    <label id="label-voltage">ڤۆڵتییە (Voltage):</label>
                    <select id="voltageSelect">
                        <option value="220">220V (Standard)</option>
                        <option value="180">180V (Weak)</option>
                        <option value="110">110V (US)</option>
                    </select>
                </div>

                <button onclick="convertAmpToWatt()" class="btn-convert-action" id="btn-convert"
                    style="margin-bottom: 5px;">
                    <span>گۆڕین</span>
                    <i class="fas fa-arrow-down conv-icon"></i>
                </button>

                <div class="field">
                    <label id="label-conv-watt">وات (Watt):</label>
                    <input type="number" id="convWattInput" placeholder="2200" readonly>
                </div>

                <button onclick="useWattInCalc()" class="btn-use-watt" id="btn-use-watt">
                    <span>بەکارھێنان لە ژمێرەر</span>
                </button>
            </div>
        </article>




        <article id="myhome" class="page">
            <h2 id="home-title">🏠 ماڵەکەم</h2>
            <p class="home-subtitle" id="home-subtitle">ئامێرەکانی ماڵەکەت زیاد بکە و کۆی تێچووی مانگانەی کارەباکەت بزانە</p>

            <section class="home-card period-field no-export" id="homePeriodCard">
                <label for="homePeriodDays" class="period-title"><i class="fas fa-calendar-alt"></i> <span id="home-period-label">ژمارەی ڕۆژەکان</span></label>
                <div class="period-wrap open" id="homePeriodWrap">
                    <div class="period-range-box">
                        <input type="range" id="homePeriodRange" min="1" max="61" value="30" oninput="syncPeriod('home', this.value)">
                        <div class="period-ticks"><span>1</span><span>15</span><span>30</span><span>45</span><span>61</span></div>
                    </div>
                    <div class="period-num">
                        <input type="number" id="homePeriodDays" min="1" max="61" value="30" oninput="syncPeriod('home', this.value, true)">
                        <span id="home-period-unit">ڕۆژ</span>
                    </div>
                </div>
                <small class="period-note" id="homePeriodNote"></small>
            </section>

            <section id="homeSummary" class="home-summary">
                <div class="home-stat home-stat-main">
                    <div class="home-stat-icon"><i class="fas fa-wallet"></i></div>
                    <span class="home-stat-label" id="home-cost-label">کۆی تێچووی مانگانە</span>
                    <span class="home-stat-value"><span id="homeMonthCost">0</span> <small id="home-curr">دینار</small></span>
                    <span class="home-stat-sub"><span id="home-year-label">ساڵانە</span>: <b id="homeYearCost">0</b></span>
                </div>
                <div class="home-stat">
                    <div class="home-stat-icon"><i class="fas fa-bolt"></i></div>
                    <span class="home-stat-label" id="home-units-label">سەرفیاتی مانگانە</span>
                    <span class="home-stat-value"><span id="homeMonthUnits">0</span> <small>kWh</small></span>
                </div>
                <div class="home-stat">
                    <div class="home-stat-icon"><i class="fas fa-plug"></i></div>
                    <span class="home-stat-label" id="home-count-label">ژمارەی ئامێرەکان</span>
                    <span class="home-stat-value" id="homeDeviceCount">0</span>
                </div>
                <div class="home-stat home-stat-load">
                    <div class="home-stat-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <span class="home-stat-label" id="home-load-label">ئەمپێر (هەمووی پێکەوە)</span>
                    <span class="home-stat-value"><span id="homeAmps">0</span> <small>/ 40A</small></span>
                    <div class="home-load-bar"><div id="homeLoadFill" class="home-load-fill"></div></div>
                    <span class="home-load-status" id="homeLoadStatus"></span>
                </div>
            </section>

            <section class="home-card no-export" id="homeAddCard">
                <h3 class="home-card-title"><i class="fas fa-plus-circle"></i> <span id="home-add-title">زیادکردنی ئامێر</span></h3>
                <span class="home-mini-label" id="home-quick-label">هەڵبژاردنی خێرا:</span>
                <div id="homePresets" class="home-presets"></div>

                <div class="home-form-grid">
                    <div class="field home-field-name">
                        <label for="homeName" id="home-label-name">ناوی ئامێر:</label>
                        <input type="text" id="homeName" maxlength="40">
                    </div>
                    <div class="field">
                        <label for="homeWatt" id="home-label-watt">وات (Watt):</label>
                        <input type="number" id="homeWatt" min="1" placeholder="1000">
                    </div>
                    <div class="field">
                        <label for="homeHours" id="home-label-hours">کاتژمێر لە ڕۆژێکدا:</label>
                        <input type="number" id="homeHours" min="0.1" max="24" step="0.5" placeholder="8">
                    </div>
                    <div class="field">
                        <label for="homeQty" id="home-label-qty">ژمارە:</label>
                        <input type="number" id="homeQty" min="1" value="1">
                    </div>
                </div>

                <button onclick="addHomeDevice()" class="btn-calculate" id="btn-home-add">
                    <i class="fas fa-plus"></i> <span>زیادکردن بۆ ماڵەکەم</span>
                </button>
            </section>

            <section class="home-card">
                <div class="home-list-header">
                    <h3 class="home-card-title"><i class="fas fa-list-ul"></i> <span id="home-list-title">ئامێرەکانی ماڵەکەم</span></h3>
                    <button onclick="clearHomeDevices()" class="btn-clear-history no-export" id="btn-home-clear">
                        <i class="fas fa-trash-alt"></i> <span>سڕینەوەی هەموو</span>
                    </button>
                </div>
                <div id="homeList" class="home-list"></div>
                <div id="homeEmpty" class="home-empty">
                    <div class="home-empty-icon">🏠</div>
                    <p id="home-empty-msg">هێشتا هیچ ئامێرێکت زیاد نەکردووە.</p>
                </div>
            </section>

            <section class="home-card" id="homeTiers">
                <h3 class="home-card-title"><i class="fas fa-layer-group"></i> <span id="home-tier-title">دابەشبوونی نرخ بەپێی پلیکانە</span></h3>
                <div id="homeTierList" class="home-tier-list"></div>
            </section>

            <button onclick="exportAsImage('myhome')" class="btn-export no-export" id="btn-export-home">
                <i class="fas fa-camera"></i> <span>داگرتن وەک وێنە</span>
            </button>
        </article>

        <!-- بەشی نوێ: چارت و کێرڤی پسوولەی کارەبا و سکانەری زیرەک -->
        <article id="chart" class="page">
            <div class="chart-page-header">
                <div>
                    <h2 id="chart-page-title"><i class="fas fa-chart-line"></i> شیکاریی پسوولەی کارەبا و چارت</h2>
                    <p class="chart-page-subtitle">چاودێری سەرفیاتی مانگانەت بکە بە کێرڤی پێشکەوتوو؛ پسوولەکەت سکان بکە یان داتاکان تۆمار بکە.</p>
                </div>
            </div>

            <!-- کانتینەری سەرەکی چارت -->
            <section class="chart-main-card">
                <div class="chart-card-top">
                    <div class="chart-title-wrap">
                        <h3><i class="fas fa-bezier-curve"></i> کێرڤی بەراوردکاری مانگانە</h3>
                        <span class="chart-badge-live"><span class="pulse-dot"></span> دینامیکی</span>
                    </div>

                    <div class="chart-view-toggles">
                        <button type="button" class="chart-toggle-btn active" data-mode="both" onclick="setChartDisplayMode('both')">
                            <i class="fas fa-layer-group"></i> هەردووکی (پارە و kWh)
                        </button>
                        <button type="button" class="chart-toggle-btn" data-mode="cost" onclick="setChartDisplayMode('cost')">
                            <i class="fas fa-coins"></i> تەنها پارە (IQD)
                        </button>
                        <button type="button" class="chart-toggle-btn" data-mode="kwh" onclick="setChartDisplayMode('kwh')">
                            <i class="fas fa-bolt"></i> تەنها کیلۆوات (kWh)
                        </button>
                    </div>
                </div>

                <div class="chart-canvas-wrapper" id="chartExportTarget">
                    <canvas id="billCurveChart"></canvas>
                    <div id="chartEmptyOverlay" class="chart-empty-overlay">
                        <div class="empty-chart-icon"><i class="fas fa-chart-area"></i></div>
                        <h4>هیچ داتایەک لە چارتەکەدا نییە</h4>
                        <p>پسوولەیەک سکان بکە، یان لە فۆرمەکەی خوارەوە داتای مانگ داخڵ بکە یان کلیک لە «نموونەی تاقیکردنەوە» بکە.</p>
                    </div>
                </div>

                <div class="chart-legend-custom">
                    <div class="legend-item legend-cost">
                        <span class="legend-dot dot-cost"></span>
                        <span>هێڵی پارە (IQD)</span>
                    </div>
                    <div class="legend-item legend-kwh">
                        <span class="legend-dot dot-kwh"></span>
                        <span>هێڵی کارەبا (kWh)</span>
                    </div>
                </div>
            </section>

            <!-- بەشی سکانەر و داغڵکردنی داتا بە دوو ستوون -->
            <div class="chart-panels-grid">

                <!-- ستوونی دووەم: فۆرمی داغڵکردنی داتا بە دەستی یان پێداچوونەوە -->
                <section class="panel-card manual-form-panel">
                    <div class="panel-header">
                        <div class="panel-icon-badge form-badge"><i class="fas fa-pen-fancy"></i></div>
                        <div>
                            <h3 id="formPanelHeading">داغڵکردنی زانیاریی پسوولە</h3>
                            <p class="panel-sub">بڕی پارە و سەرفیاتی کیلۆوات بنووسە بەپێی ویستی خۆت</p>
                        </div>
                    </div>

                    <form id="billManualForm" onsubmit="handleBillFormSubmit(event)">
                        <input type="hidden" id="billEditId" value="">

                        <div class="form-grid-chart">
                            <!-- بەرواری خوێندنەوەی ئێستا و ناوی مانگ -->
                            <div class="field-chart">
                                <label for="billMonthCustom" style="font-size: 0.78rem; color: var(--text-muted);"><i class="fas fa-calendar-check"></i> مانگی پسوولە:</label>
                                <div class="input-with-select">
                                    <select id="billMonthSelect" onchange="syncMonthInput(this.value)">
                                        <option value="">-- هەڵبژاردنی مانگ --</option>
                                        <option value="کانوونی دووەم (1)">کانوونی دووەم (1)</option>
                                        <option value="شوبات (2)">شوبات (2)</option>
                                        <option value="ئازار (3)">ئازار (3)</option>
                                        <option value="نیسان (4)">نیسان (4)</option>
                                        <option value="ئایار (5)">ئایار (5)</option>
                                        <option value="حوزەیران (6)">حوزەیران (6)</option>
                                        <option value="تەمووز (7)">تەمووز (7)</option>
                                        <option value="ئاب (8)">ئاب (8)</option>
                                        <option value="ئەیلوول (9)">ئەیلوول (9)</option>
                                        <option value="تشرینی یەکەم (10)">تشرینی یەکەم (10)</option>
                                        <option value="تشرینی دووەم (11)">تشرینی دووەم (11)</option>
                                        <option value="کانوونی یەکەم (12)">کانوونی یەکەم (12)</option>
                                    </select>
                                    <input type="text" id="billMonthCustom" placeholder="مانگ..." required>
                                </div>
                            </div>

                            <!-- بڕی پارەی پسوولە بە دینار -->
                            <div class="field-chart">
                                <label for="billAmount"><i class="fas fa-coins"></i> بڕی پارەی پسوولە (دینار IQD):</label>
                                <div class="input-icon-group">
                                    <input type="number" id="billAmount" min="0" step="250" placeholder="نموونە: 65000" required>
                                    <span class="input-suffix">د.ع</span>
                                </div>
                            </div>

                            <!-- بڕی سەرفیاتی کارەبا بە کیلۆوات -->
                            <div class="field-chart">
                                <label for="billKwh"><i class="fas fa-bolt"></i> بڕی سەرفیات بە کیلۆوات (kWh):</label>
                                <div class="input-icon-group">
                                    <input type="number" id="billKwh" min="0" step="1" placeholder="نموونە: 700" required>
                                    <span class="input-suffix">یەکە</span>
                                </div>
                            </div>

                            <!-- تێبینی یان ژمارەی پێوەر -->
                            <div class="field-chart">
                                <label for="billNotes"><i class="fas fa-sticky-note"></i> تێبینی / سەرنج (ئارەزوومەندانە):</label>
                                <input type="text" id="billNotes" placeholder="نموونە: بەکارهێنانی سپلیت، ژمارەی پێوەر...">
                            </div>
                        </div>

                        <!-- دوگمەکانی فۆرم -->
                        <div class="form-btn-actions">
                            <button type="submit" class="btn-save-bill" id="btnSaveBill">
                                <i class="fas fa-plus-circle"></i> <span id="btnSaveBillText">زیادکردن بۆ ناو چارت</span>
                            </button>
                            <button type="button" class="btn-cancel-edit" id="btnCancelEdit" style="display: none;" onclick="cancelBillEdit()">
                                <i class="fas fa-times"></i> پاشگەزبوونەوە
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- خشتەی مێژووی پسوولە تۆمارکراوەکان -->
            <section class="records-table-card">
                <div class="records-header">
                    <div class="records-title">
                        <i class="fas fa-table-list"></i>
                        <div>
                            <h3>خشتەی پسوولە تۆمارکراوەکان</h3>
                            <span class="records-count" id="recordsCountText">0 پسوولە تۆمارکراوە</span>
                        </div>
                    </div>
                    <div class="records-top-actions">
                        <button type="button" class="btn-danger-outline" onclick="confirmClearAllBills()">
                            <i class="fas fa-trash-alt"></i> سڕینەوەی هەمووی
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="bills-table" id="billsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>مانگ / بەروار</th>
                                <th>بڕی پارە (دینار)</th>
                                <th>سەرفیات (kWh)</th>
                                <th>تێکڕای نرخی هەر یەکەیەک</th>
                                <th>گۆڕانکاری لەگەڵ مانگی پێشوو</th>
                                <th>تێبینی</th>
                                <th class="text-center">کردارەکان</th>
                            </tr>
                        </thead>
                        <tbody id="billsTableBody">
                            <!-- هێڵەکان بە داینامیکی زیاد دەبن -->
                        </tbody>
                    </table>
                </div>

                <div id="billsEmptyState" class="bills-empty-state">
                    <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                    <h4>هێشتا هیچ پسوولەیەک تۆمار نەکراوە</h4>
                    <p>پسوولەی نوێ زیاد بکە بۆ ئەوەی چارت و خشتەکە بە شێوەیەکی کێرڤی پێشکەوتوو دەربکەون.</p>
                </div>
            </section>
        </article>

        <!-- مۆداڵی کامێرا بۆ گرتنی ڕاستەوخۆی پسوولە -->
        <div id="cameraModal" class="camera-modal-overlay" style="display: none;">
            <div class="camera-modal-content">
                <div class="camera-modal-header">
                    <h4><i class="fas fa-camera"></i> گرتنی وێنەی پسوولە بە کامێرا</h4>
                    <button type="button" class="btn-close-modal" onclick="closeCameraModal()">&times;</button>
                </div>
                <div class="camera-video-box">
                    <video id="cameraVideo" autoplay playsinline></video>
                    <div class="camera-frame-guide">
                        <span>پسوولەکەت بخەرە ناو ئەم چوارچێوەیە</span>
                    </div>
                </div>
                <div class="camera-modal-actions">
                    <button type="button" class="btn-capture-photo" onclick="captureCameraPhoto()">
                        <i class="fas fa-circle-dot"></i> وێنە بگرە
                    </button>
                    <button type="button" class="btn-close-cam" onclick="closeCameraModal()">داخستن</button>
                </div>
            </div>
        </div>

        <article id="tips" class="page">
            <h2 id="tips-page-title">💡 ڕێنماییەکانی کەمکردنەوە</h2>
            <div class="tips-list">
                <div class="tips-item">
                    <strong id="tip1-title">❄️ پلەی گەرمی سپلیت:</strong>
                    <p id="tip1-desc">دانانی سپلیت لەسەر 24 پلە باشترین ڕێگەیە بۆ کەمکردنەوەی خەرجی و پاراستنی ئامێرەکە.
                    </p>
                </div>
                <div class="tips-item">
                    <strong id="tip2-title">💡 گڵۆپی LED:</strong>
                    <p id="tip2-desc">بەکارهێنانی گڵۆپی لید لە جیاتی گڵۆپی ئاسایی تێچووی ڕووناکی بە ڕێژەی 80%
                    کەمدەکاتەوە.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip3-title">🚿 ئامێری ئاو گەرمکەر (گیزەر):</strong>
                    <p id="tip3-desc">تەنها 30 خولەک پێش بەکارهێنان دایگیرسێنە و دواتر بیکوژێنەوە.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip4-title">🧹 خاوێنکردنەوەی فلتەر:</strong>
                    <p id="tip4-desc">خاوێنکردنەوەی فلتەری سپلیت مانگانە یەک جار، کارەبا سەرفکردن کەمدەکاتەوە.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip5-title">🔌 ئامێرە بێ کەڵکەکان:</strong>
                    <p id="tip5-desc">ئەو ئامێرانەی بەکاریان ناهێنیت لە کارەبا دەریان بکە، چونکە بڕێکی کەم کارەبا
                        ڕادەکێشن تەنانەت ئەگەر کوژاوەش بن.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip6-title">🧺 ئامێری جلشۆردن:</strong>
                    <p id="tip6-desc">تەنها کاتێک ئامێری جلشۆردن بەکاربهێنە کە جلەکانت پڕ دەبن بۆ ئەوەی بڕی ئاو و
                        کارەبای کەمتر بەفیڕۆ بدەیت.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip7-title">👔 ئوتووکردن:</strong>
                    <p id="tip7-desc">هەوڵبدە هەموو جلەکانت پێکەوە ئوتوو بکەیت، چونکە ئوتوو لە کاتی گەرمبووندا
                        کارەبایەکی زۆر دەبات.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip8-title">🧊 سەلاجە و بەفرگر:</strong>
                    <p id="tip8-desc">دڵنیابە لەوەی لاستیکی دەرگای سەلاجەکەت باشە و زۆر دەرگاکەی مەکەرەوە بۆ ئەوەی
                        ساردییەکەی پارێزراو بێت.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip9-title">🥘 داپۆشینی مەنجەڵ:</strong>
                    <p id="tip9-desc">لە کاتی لێنانی خواردن مەنجەڵەکان دابپۆشە بۆ ئەوەی زووتر بکوڵێن و وزەی کەمتر
                        بەکاربهێنن.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip10-title">☀️ ڕووناکی سروشتی:</strong>
                    <p id="tip10-desc">لە ڕۆژدا پەردەکان لابدە و سوود لە ڕووناکی خۆر وەربگرە بۆ کەمکردنەوەی بەکارهێنانی
                        گڵۆپ.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip11-title">🍱 مایکرۆوەیڤ:</strong>
                    <p id="tip11-desc">بۆ گەرمکردنەوەی خواردنی کەم، مایکرۆوەیڤ بەکاربهێنە لەجیاتی فڕنی کارەبایی چونکە
                        کارەبای زۆر کەمتر دەوێت.</p>
                </div>
                <div class="tips-item">
                    <strong id="tip12-title">🔋 ئامێری زیرەک:</strong>
                    <p id="tip12-desc">ئامێرە زیرەکەکان و مۆبایلەکان تەنها تا پڕبوون لە شەحن دابنێ، چونکە دواتر هەر
                        کارەبا ڕادەکێشن.</p>
                </div>
            </div>
        </article>

        <article id="history" class="page">
            <h2 id="history-title">📜 مێژووی حیسابات</h2>
            <div class="history-controls">
                <button onclick="clearHistory()" class="btn-clear-history" id="btn-clear-history"><i class="fas fa-trash-alt"></i> سڕینەوەی هەموو</button>
            </div>
            <div id="history-list" class="history-list">
            </div>
            <div id="history-empty" class="history-empty">
                <p id="history-empty-msg">هیچ مێژوویەک نییە.</p>
            </div>
        </article>

        <article id="faq" class="page">
            <h2 id="faq-title">❓ پرسیار و وەڵامە باوەکان</h2>
            <div id="faq-list" class="faq-list">
            </div>
        </article>

        <article id="about" class="page">
            <h2 id="about-title">👤 دەربارەی گەشەپێدەر</h2>
            <div class="profile-container">
                <div class="profile-header">
                    <div class="avatar">
                        <img src="bhm_team.jpg" alt="Profile" id="profile-img">
                    </div>

                    <h3 id="dev-name">BHM TEAM</h3>
                    <p id="dev-role">تیمی گەشەپێدان</p>
                </div>
                <div class="profile-bio">
                    <p id="dev-bio">سڵاو! ئێمە تیمێکی سێ کەسیین لە گەشەپێدەران و بەرنامە داڕێژەران، پێکەوە کار دەکەین لەسەر دروستکردن و پەرەپێدانی پرۆژە و وێبسایتی پێشکەوتوو. ئەم پڕۆژەیەشمان بۆ خزمەتکردنی هاوڵاتیانی خۆشەویست دروست کردووە بۆ ئەوەی بە ئاسانی و خێرایی تێچووی کارەباکەیان بزانن.</p>
                </div>

                <div class="profile-links">
                    <a href="https://www.facebook.com/share/18AC5YGz19/?mibextid=wwXIfr" target="_blank" class="social-btn facebook" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://www.instagram.com/ameer_.dev1?igsh=b3RwMHFhOGdmeDA0&utm_source=qr" target="_blank" class="social-btn instagram" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://t.me/ameeragha" target="_blank" class="social-btn telegram" title="Telegram">
                        <i class="fab fa-telegram-plane"></i>
                    </a>
                    <a href="https://wa.me/qr/V6C6TE24AOR2N1" target="_blank" class="social-btn whatsapp" title="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="mailto:bahezxalid46@gmail.com" class="social-btn envelope" title="Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </article>

        <article id="settings" class="page">
            <h2 id="settings-title">⚙️ ڕێکخستنەکان</h2>
            
            <div class="settings-container">
                <!-- ڕێکخستنی زمان -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="settings-icon-box">
                            <i class="fas fa-language"></i>
                        </div>
                        <div>
                            <h3 id="settings-lang-title">زمانی وێبسایت</h3>
                            <p id="settings-lang-desc" class="settings-desc">زمانی دڵخوازی خۆت هەڵبژێرە بۆ بەکارهێنانی سیستەمەکە</p>
                        </div>
                    </div>
                    <div class="settings-options-grid lang-grid">
                        <button type="button" class="settings-opt-btn" data-lang="ku" onclick="changeLanguage('ku')">
                            <span class="flag-icon">☀️</span>
                            <span class="opt-label">کوردی</span>
                            <i class="fas fa-check check-mark"></i>
                        </button>
                        <button type="button" class="settings-opt-btn" data-lang="ar" onclick="changeLanguage('ar')">
                            <span class="flag-icon">🌴</span>
                            <span class="opt-label">العربية</span>
                            <i class="fas fa-check check-mark"></i>
                        </button>
                        <button type="button" class="settings-opt-btn" data-lang="en" onclick="changeLanguage('en')">
                            <span class="flag-icon">🌐</span>
                            <span class="opt-label">English</span>
                            <i class="fas fa-check check-mark"></i>
                        </button>
                    </div>
                </div>

                <!-- ڕێکخستنی ڕووکار / دۆخی ڕووناک و تاریک -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="settings-icon-box">
                            <i class="fas fa-moon"></i>
                        </div>
                        <div>
                            <h3 id="settings-theme-title">شێوازی ڕووکار (دۆخی تاریک / ڕووناک)</h3>
                            <p id="settings-theme-desc" class="settings-desc">دۆخی ڕووناکی شاشە بگۆڕە بەپێی ئارەزووی چاوەکانت</p>
                        </div>
                    </div>
                    <div class="settings-options-grid theme-grid">
                        <button type="button" class="settings-opt-btn" data-theme="light" onclick="setAppTheme('light')">
                            <span class="mode-icon">☀️</span>
                            <span class="opt-label" id="settings-theme-light">دۆخی ڕووناک</span>
                            <i class="fas fa-check check-mark"></i>
                        </button>
                        <button type="button" class="settings-opt-btn" data-theme="dark" onclick="setAppTheme('dark')">
                            <span class="mode-icon">🌙</span>
                            <span class="opt-label" id="settings-theme-dark">دۆخی تاریک</span>
                            <i class="fas fa-check check-mark"></i>
                        </button>
                    </div>
                </div>

                <!-- ڕێکخستنی ڕەنگی سەرەکی -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <div class="settings-icon-box">
                            <i class="fas fa-palette"></i>
                        </div>
                        <div>
                            <h3 id="settings-color-title">ڕەنگی بنەڕەتی بەرنامە</h3>
                            <p id="settings-color-desc" class="settings-desc">ڕەنگی دوگمە و بەشە سەرەکییەکان دەستنیشان بکە</p>
                        </div>
                    </div>
                    
                    <div class="color-presets-wrapper">
                        <span class="color-presets-label" id="settings-presets-label">ڕەنگە پێشنیارکراوەکان:</span>
                        <div class="color-presets">
                            <button type="button" class="color-preset-circle" style="--c: #2563eb;" onclick="changePrimaryColor('#2563eb')" title="Blue"></button>
                            <button type="button" class="color-preset-circle" style="--c: #06b6d4;" onclick="changePrimaryColor('#06b6d4')" title="Cyan"></button>
                            <button type="button" class="color-preset-circle" style="--c: #10b981;" onclick="changePrimaryColor('#10b981')" title="Emerald"></button>
                            <button type="button" class="color-preset-circle" style="--c: #8b5cf6;" onclick="changePrimaryColor('#8b5cf6')" title="Purple"></button>
                            <button type="button" class="color-preset-circle" style="--c: #ec4899;" onclick="changePrimaryColor('#ec4899')" title="Pink"></button>
                            <button type="button" class="color-preset-circle" style="--c: #f59e0b;" onclick="changePrimaryColor('#f59e0b')" title="Amber"></button>
                            <button type="button" class="color-preset-circle" style="--c: #ef4444;" onclick="changePrimaryColor('#ef4444')" title="Red"></button>
                        </div>
                    </div>

                    <div class="custom-color-row">
                        <label for="settingsCustomColor" id="settings-custom-color-label">یان هەڵبژاردنی ڕەنگی دڵخواز بە تایبەتی:</label>
                        <div class="custom-color-input-wrap">
                            <input type="color" id="settingsCustomColor" onchange="changePrimaryColor(this.value)">
                            <span id="currentColorCode">#2563eb</span>
                        </div>
                    </div>
                </div>

                <!-- ڕێکخستنی ئاگادارکردنەوە و ئەلێرتەکان (Alerts & Notifications Settings) -->
                <div class="settings-card" id="card-alert-settings">
                    <div class="settings-card-header">
                        <div class="settings-icon-box" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <h3 id="settings-alert-title">ئاگادارکردنەوەی بەکارهێنانی کارەبا</h3>
                            <p id="settings-alert-desc" class="settings-desc">ئاگادارکردنەوە بەدەستبهێنە کاتێک بەکارهێنانی یەکەکان (kWh) یان بارگرانی ئەمپێر (Amps) زیاد دەکات</p>
                        </div>
                    </div>

                    <div class="alert-settings-body">
                        <!-- بەشی ئاگاداری کیلۆوات -->
                        <div class="alert-setting-row">
                            <div class="alert-setting-info">
                                <span class="alert-row-title" id="lbl-kwh-alert-title"><i class="fas fa-bolt"></i> ئاگاداری یەکەکان (سەرووی ٤٠٠ و ٨٠٠ kWh)</span>
                                <span class="alert-row-desc" id="lbl-kwh-alert-desc">ئاگادارت دەکاتەوە ئەگەر سەرفیاتی مانگانەت چووە سەر پلیکانەی ٤٠٠ یان ٨٠٠ یەکە و سەرووتر</span>
                            </div>
                            <div class="alert-setting-ctrls">
                                <select id="alertKwhThreshold" class="alert-select" onchange="saveAlertSettingsToServer()">
                                    <option value="400">سەروو ٤٠٠ kWh (پلیکانەی ٢)</option>
                                    <option value="800">سەروو ٨٠٠ kWh (پلیکانەی ٣)</option>
                                    <option value="1200">سەروو ١٢٠٠ kWh (پلیکانەی ٤)</option>
                                    <option value="1600">سەروو ١٦٠٠ kWh (پلیکانەی ٥)</option>
                                </select>
                                <label class="switch-toggle" title="چالاککردن / ناچالاککردن">
                                    <input type="checkbox" id="alertKwhToggle" checked onchange="saveAlertSettingsToServer()">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- بەشی ئاگاداری ئەمپێر -->
                        <div class="alert-setting-row">
                            <div class="alert-setting-info">
                                <span class="alert-row-title" id="lbl-amp-alert-title"><i class="fas fa-tachometer-alt"></i> ئاگاداری بارگرانی ئەمپێر (٤٠ ئەمپێر یان زیاتر)</span>
                                <span class="alert-row-desc" id="lbl-amp-alert-desc">ئاگادارت دەکاتەوە ئەگەر بڕی ئەمپێری ئامێرەکان گەیشتە ٤٠ ئەمپێر بۆ ڕێگری لە کوژانەوەی پێوەر</span>
                            </div>
                            <div class="alert-setting-ctrls">
                                <div class="amp-input-wrap">
                                    <input type="number" id="alertAmpThreshold" class="alert-num-input" value="40" min="10" max="100" step="1" onchange="saveAlertSettingsToServer()">
                                    <span>A</span>
                                </div>
                                <label class="switch-toggle" title="چالاککردن / ناچالاککردن">
                                    <input type="checkbox" id="alertAmpToggle" checked onchange="saveAlertSettingsToServer()">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div id="alertSaveStatus" class="alert-save-status"></div>
                    </div>
                </div>
            </div>
        </article>
    </main>

    <footer class="main-footer">
        <p class="copyright">
            <span id="rights-reserved">هەموو مافەکان پارێزراوە بۆ</span> &copy; <span id="current-year"></span>
            <strong id="dev-name-footer">Smart Meter</strong>
        </p>
        <div class="visitor-compact">
            <span class="visitor-dot"></span>
            <span class="label-text">کۆی سەردانیکەران:</span>
            <span id="visit-count" class="number-text">0</span>
        </div>
    </footer>

    <!-- هۆڵدەری ئاگادارییە کاتییەکان (Real-time Toast Notifications) -->
    <div id="toastNotificationContainer" class="toast-notification-container"></div>

    <script src="script.js?v=2.1"></script>
</body>
</html>
