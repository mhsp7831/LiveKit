<?php
// FIX 4: Correct require_once path
require_once __DIR__ . '/functions.php';

// FIX 5: Image URL helper for dashboard expects paths like "config/uploads/..."
function get_dashboard_image_url($path)
{
    if (empty($path) || preg_match('/^(https?:)?\/\//', $path)) {
        return $path;
    }
    if (strpos($path, 'config/') === 0) {
        return substr($path, strlen('config/'));
    }
    return $path;
}

if (empty($all_events)) {
    $first_event_id = 'event_' . uniqid();
    $all_events = [['id' => $first_event_id, 'name' => 'رویداد اول']];
    create_event_files($first_event_id, $defaultConfigs);

    $events_json = json_encode($all_events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    safe_file_put_contents(EVENTS_FILE, $events_json);

    $current_event_id = $first_event_id;
    $_SESSION['current_event_id'] = $current_event_id;
    $configsFile = EVENTS_DIR . $current_event_id . '/configs.json';
    $subtitlesFile = EVENTS_DIR . $current_event_id . '/subtitles.json';
    $configs = json_decode(file_get_contents($configsFile), true);
    $subtitles = json_decode(file_get_contents($subtitlesFile), true);
}

if (!isset($configs['buttons'])) {
    $configs['buttons'] = [];
}
if (!isset($configs['socials'])) {
    $configs['socials'] = [];
}

if (!empty($all_events)) {
    // Perform database maintenance on dashboard load
    perform_database_maintenance();
}

$users = get_all_users();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت</title>
    <script>
        window.addEventListener('load', function() {
            const loader = document.getElementById('loader-wrapper');
            const pageContent = document.getElementById('page-content');

            if (loader) {
                // Fade out the loader
                loader.style.opacity = '0';
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 500); // This duration must match the CSS transition time
            }
            if (pageContent) {
                // Fade in the main content
                pageContent.style.visibility = 'visible';
                pageContent.style.opacity = '1';
            }
        });
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/prism.css">
</head>

<body>
    <div id="loader-wrapper">
        <div class="loader-spinner"></div>
    </div>
    <div id="page-content" style="visibility: hidden; opacity: 0; transition: opacity 0.5s ease; display: flex; flex-grow: 1; width: 100%;">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <h2>پنل مدیریت</h2>
                <ul>
                    <li><a href="#" class="tab-button active" data-tab="settings">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924-1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>تنظیمات</span>
                        </a></li>
                    <li><a href="#" class="tab-button" data-tab="subtitles">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v1h-14v-1zM19 9h-14v10a2 2 0 002 2h10a2 2 0 002-2v-10z" />
                            </svg>
                            <span>زیرنویس</span>
                        </a></li>
                    <li><a href="#" class="tab-button" data-tab="appearance">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            <span>ظاهر</span>
                        </a></li>
                    <li><a href="#" class="tab-button" data-tab="phone-validation">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>اعتبارسنجی تلفن</span>
                    </a></li>
                    <li><a href="#" class="tab-button" data-tab="media">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>مدیریت رسانه</span>
                    </a></li>
                    <li><a href="#" class="tab-button" data-tab="users">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197m0 0A5 5 0 019 10a5 5 0 014 2.002m-4 0a4 4 0 100-5.292" />
                            </svg>
                            <span>کاربران</span>
                        </a></li>
                    <li><a href="#" class="tab-button" data-tab="backup">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span>پشتیبان‌گیری</span>
                        </a></li>
                    <li><a href="#" class="tab-button" data-tab="versions">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>تاریخچه نسخه‌ها</span>
                    </a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <div class="event-manager">
                            <div class="event-display">
                                <div class="current-event">
                                    <label for="event-selector">رویداد فعلی:</label>
                                    <select id="event-selector">
                                        <?php foreach ($all_events as $event): ?>
                                            <option value="<?= htmlspecialchars($event['id']) ?>" <?= ($event['id'] === $current_event_id) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($event['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="event-id-display">
                                    شناسه: <code><?= htmlspecialchars($current_event_id) ?></code>
        
                                    <button id="copy-event-id-btn" title="کپی شناسه">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V2Zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H6ZM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1H2Z" />
                                        </svg>
                                    </button>
        
                                    <button id="edit-event-id-btn" title="ویرایش شناسه">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="event-actions">
                                <div>
                                    <button id="create-event-btn" class="btn btn--primary btn--icon" title="رویداد جدید">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </button>
                                    <button id="rename-event-btn" class="btn btn--primary btn--icon" title="تغییر نام رویداد فعلی">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L15.232 5.232z" />
                                        </svg>
                                    </button>
                                </div>
                                    <div>
                                    <a href="../index.php?event=<?= htmlspecialchars($current_event_id) ?>" title="مشاهده پخش زنده" target="_blank" class="btn btn--primary btn--icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <button id="delete-event-btn" class="btn btn--danger btn--icon" title="حذف رویداد فعلی">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <span class="current-user">کاربر: <?= htmlspecialchars($_SESSION['username']) ?></span>
                        <a href="logout.php" class="btn btn--danger btn--xs btn--outline logout-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            خروج
                        </a>
                    </div>
                </div>
            </header>

            <main class="content-area">
                <form id="settings-form" class="main-form">
                    <div class="form-content">
                        <div id="settings" class="tab-panel active">
                            <input type="hidden" name="action" value="save_settings">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                            <div class="card">
                                <div class="card-header">
                                    <h3>تنظیمات اصلی</h3>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group"><label for="title">عنوان پخش زنده:</label><input required type="text" name="title" id="title" value="<?= htmlspecialchars($configs['title']) ?>"></div>
                                    <div class="form-group"><label for="homePage">لینک صفحه اصلی:</label><input type="url" name="homePage" id="homePage" value="<?= htmlspecialchars($configs['homePage']) ?>"></div>
                                    <div class="form-group"><label for="iframe">کد ای‌فریم (iframe):</label><input type="text" name="iframe" id="iframe" value="<?= htmlspecialchars($configs['iframe']) ?>"></div>
                                    <div class="form-group"><label for="liveStart">زمان شروع:</label><input type="datetime-local" name="liveStart" id="liveStart" value="<?= htmlspecialchars($configs['liveStart']) ?>"></div>
                                    <div class="form-group"><label for="liveEnd">زمان پایان:</label><input type="datetime-local" name="liveEnd" id="liveEnd" value="<?= htmlspecialchars($configs['liveEnd']) ?>"></div>
                                    <div class="form-group">
                                        <label for="playerRevealOffset">
                                            نمایش پلیر قبل از شروع (ms):
                                            <div class="label-tooltip">
                                                <svg data-tippy-content="این عدد مشخص می‌کند که چند میلی‌ثانیه زودتر از زمان شروع، پلیر نمایش داده شود اما شمارنده به کار خود ادامه دهد. <br />(مقدار پیش‌فرض: 0)" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        </label>
                                        <input type="number" name="playerRevealOffset" id="playerRevealOffset" placeholder="0" value="<?= htmlspecialchars($configs['playerRevealOffset'] ?? 0) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="fetchInterval">
                                            فاصله زمانی به‌روزرسانی زیرنویس (ms):
                                            <div class="label-tooltip">
                                                <svg data-tippy-content="این عدد مشخص می‌کند که صفحه پخش زنده، هر چند میلی‌ثانیه یک‌بار برای دریافت زیرنویس‌های جدید، سرور را بررسی کند. عدد کمتر به معنی به‌روزرسانی سریع‌تر و بار بیشتر روی سرور است. <br />(مقدار پیش‌فرض: 8000ms)" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        </label>
                                        <input type="number" name="fetchInterval" id="fetchInterval" value="<?= htmlspecialchars($configs['fetchInterval']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="scrollSpeed">
                                            سرعت نمایش زیرنویس (px/s):
                                            <div class="label-tooltip">
                                                <svg data-tippy-content="این عدد مشخص می‌کند که هر زیرنویس با چه سرعتی (بر حسب پیکسل در ثانیه) روی صفحه حرکت کند تا زیرنویس بعدی نمایش داده شود.<br />(مقدار پیش‌فرض: 50px/s)" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        </label>
                                        <input type="number" name="scrollSpeed" id="scrollSpeed" value="<?= htmlspecialchars($configs['scrollSpeed']) ?>">
                                    </div>
                                    <div class="form-group"><label for="copyright">کپی‌رایت:</label><input type="text" name="copyright" id="copyright" value="<?= htmlspecialchars($configs['copyright']) ?>"></div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h3>دکمه‌ها</h3>
                                    <div>
                                        <button type="button" id="remove-all-buttons-btn" class="btn btn--danger btn--icon remove-all-btn" title="حذف همه دکمه ها">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                        <button type="button" id="add-button-btn" class="btn btn--primary btn--icon" title="دکمه جدید">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>    
                                <div id="buttons-container" class="sortable-list-grid">
                                    <?php foreach ($configs['buttons'] as $index => $button): ?>
                                        <div class="sortable-item">
                                            <div class="sortable-header">
                                                <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                                                </svg>
                                                <span class="item-title">دکمه #<span class="item-counter"><?= $index + 1 ?></span></span>
                                                <button type="button" class="btn btn--danger btn--icon remove-btn" title="حذف این آیتم">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="sortable-content">
                                                <div class="form-group"><label>عنوان:</label><input type="text" name="button_title[]" value="<?= htmlspecialchars($button['title']) ?>"></div>
                                                <div class="form-group"><label>لینک:</label><input type="url" name="button_link[]" value="<?= htmlspecialchars($button['link']) ?>"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h3>صفحات اجتماعی</h3>
                                    <div>
                                        <button type="button" id="remove-all-socials-btn" class="btn btn--danger btn--icon remove-all-btn" title="حذف همه صفحات اجتماعی">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                        <button type="button" id="add-social-btn" class="btn btn--primary btn--icon" title="افزودن آیتم اجتماعی">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>    
                                <div id="socials-container" class="sortable-list-grid">
                                    <?php foreach ($configs['socials'] as $index => $social): ?>
                                        <div class="sortable-item">
                                            <div class="sortable-header">
                                                <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                                                </svg>
                                                <span class="item-title">آیتم #<span class="item-counter"><?= $index + 1 ?></span></span>
                                                <button type="button" class="btn btn--danger btn--icon remove-btn" title="حذف این آیتم">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="sortable-content">
                                                <div class="form-group"><label>عنوان:</label><input type="text" name="social_title[]" value="<?= htmlspecialchars($social['title']) ?>"></div>
                                                <div class="form-group"><label>لینک:</label><input type="url" name="social_link[]" value="<?= htmlspecialchars($social['link']) ?>"></div>
                                                <div class="form-group image-group">
                                                    <label>آیکن:</label>
                                                    <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($social['icon'])) ?>" class="image-preview"></div>
                                                    <input type="text" name="social_icon_url[]" placeholder="آدرس آیکن" value="<?= htmlspecialchars(get_dashboard_image_url($social['icon'])) ?>" class="preview-url-input">
                                                    <div class="image-actions">
                                                        <button type="button" class="btn btn--primary btn--outline select-from-library-btn">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                            <span class="btn-text">انتخاب از کتابخانه</span>
                                                        </button>
                                                        <label class="btn btn--primary btn--outline upload-new-btn">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                            </svg>
                                                            <span class="btn-text">آپلود جدید</span>
                                                            <input type="file" name="social_icon_file[]" accept="image/*" class="preview-file-input" style="display: none;">
                                                        </label>
                                                    </div>
                                                    <input type="hidden" name="social_icon_old[]" value="<?= htmlspecialchars($social['icon']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
 
                        <div id="subtitles" class="tab-panel">
                            <div class="card">
                                <div class="card-header">
                                    <h3>مدیریت زیرنویس ها</h3>
                                    <div>
                                        <button type="button" id="remove-all-subtitles-btn" class="btn btn--danger btn--icon remove-all-btn" title="حذف همه زیرنویس ها">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                        <button type="button" id="add-subtitle-btn" class="btn btn--primary btn--icon" title="افزودن زیرنویس">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>    
                                <div id="subtitles-container" class="sortable-list"><?php foreach ($subtitles as $subtitle): ?>
                                        <div class="sortable-item">
                                            <div class="sortable-header">
                                                <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                                                </svg>
                                                <span class="item-title">زیرنویس #<span class="item-counter"></span></span>
                                                <button type="button" class="btn btn--danger btn--icon remove-btn" title="حذف این آیتم">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="sortable-content form-grid">
                                                <div class="form-group"><label>متن:</label><input type="text" name="subtitle_text[]" value="<?= htmlspecialchars($subtitle['text']) ?>"></div>
                                                <div class="form-group"><label>لینک (اختیاری):</label><input type="url" name="subtitle_link[]" value="<?= htmlspecialchars($subtitle['link'] ?? '') ?>"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?></div>
                            </div>
                        </div>

                        <div id="appearance" class="tab-panel">
                            <div class="card">
                                <div class="card-header"><h3>تصاویر و بنرها</h3></div>
                                <div class="image-upload-grid">
                                    <div class="form-group image-group">
                                        <label for="logo_file"> 
                                            لوگو:
                                            <div class="label-tooltip">
                                                <svg data-tippy-content="فرمت‌های مجاز: JPG, PNG, GIF, SVG, WEBP<br />ابعاد پیشنهادی: 150×150 پیکسل" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        </label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['logo'])) ?>" class="image-preview" id="logo_preview"></div>
                                        
                                        <input type="text" name="logo_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['logo'])) ?>" class="preview-url-input">
                                        
                                        <div class="image-actions">
                                            <button type="button" class="btn btn--primary btn--outline select-from-library-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span class="btn-text">انتخاب از کتابخانه</span>
                                            </button>
                                            <label class="btn btn--primary btn--outline upload-new-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                                <span class="btn-text">آپلود جدید</span>
                                                <input type="file" name="logo_file" id="logo_file" accept="image/*" class="preview-file-input" style="display: none;">
                                            </label>
                                        </div>
                                        
                                        <input type="hidden" name="logo_old" value="<?= htmlspecialchars($configs['logo']) ?>">
                                    </div>
                                    <div class="form-group image-group">
                                        <label for="preBanner_file">
                                            بنر قبل از پخش‌زنده:
                                            <div class="label-tooltip">
                                                <svg data-tippy-content="فرمت: JPG, PNG, GIF, SVG, WEBP<br />نسبت ابعاد: 16:9 (مثال: 1080×1920)" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        </label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['preBanner'])) ?>" class="image-preview" id="preBanner_preview"></div>
                                        <input type="text" name="preBanner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['preBanner'])) ?>" class="preview-url-input">
                                        <div class="image-actions">
                                            <button type="button" class="btn btn--primary btn--outline select-from-library-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span class="btn-text">انتخاب از کتابخانه</span>
                                            </button>
                                            <label class="btn btn--primary btn--outline upload-new-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                                <span class="btn-text">آپلود جدید</span>
                                                <input type="file" name="preBanner_file" id="preBanner_file" accept="image/*" class="preview-file-input" style="display: none;">
                                            </label>
                                        </div>
                                        <input type="hidden" name="preBanner_old" value="<?= htmlspecialchars($configs['preBanner']) ?>">
                                    </div>
                                    <div class="form-group image-group">
                                        <label for="endBanner_file">
                                            بنر بعد از پخش‌زنده:
                                            <div class="label-tooltip">
                                                <svg data-tippy-content="فرمت: JPG, PNG, GIF, SVG, WEBP<br />نسبت ابعاد: 16:9 (مثال: 1080×1920)" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        </label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['endBanner'])) ?>" class="image-preview" id="endBanner_preview"></div>
                                        <input type="text" name="endBanner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['endBanner'])) ?>" class="preview-url-input">
                                        <div class="image-actions">
                                            <button type="button" class="btn btn--primary btn--outline select-from-library-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span class="btn-text">انتخاب از کتابخانه</span>
                                            </button>
                                            <label class="btn btn--primary btn--outline upload-new-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                                <span class="btn-text">آپلود جدید</span>
                                                <input type="file" name="endBanner_file" id="endBanner_file" accept="image/*" class="preview-file-input" style="display: none;">
                                            </label>
                                        </div>
                                        <input type="hidden" name="endBanner_old" value="<?= htmlspecialchars($configs['endBanner']) ?>">
                                    </div>
                                    <div class="form-group image-group">
                                        <label for="banner_file">
                                            بنر زیر پخش‌زنده:
                                            <div class="label-tooltip">
                                                <svg data-tippy-content="فرمت: JPG, PNG, GIF, SVG, WEBP<br />نسبت ابعاد: 64:19 (مثال: 1280×380)" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        </label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['banner'])) ?>" class="image-preview" id="banner_preview"></div>
                                        <input type="text" name="banner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['banner'])) ?>" class="preview-url-input">
                                        <div class="image-actions">
                                            <button type="button" class="btn btn--primary btn--outline select-from-library-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span class="btn-text">انتخاب از کتابخانه</span>
                                            </button>
                                            <label class="btn btn--primary btn--outline upload-new-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                                <span class="btn-text">آپلود جدید</span>
                                                <input type="file" name="banner_file" id="banner_file" accept="image/*" class="preview-file-input" style="display: none;">
                                            </label>
                                        </div>
                                        <input type="hidden" name="banner_old" value="<?= htmlspecialchars($configs['banner']) ?>">

                                        <label for="bannerLink" style="margin-top: 1rem;">لینک بنر (اختیاری):</label>
                                        <input type="url" name="bannerLink" id="bannerLink" placeholder="https://example.com" value="<?= htmlspecialchars($configs['bannerLink'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header"><h3>شخصی‌سازی تم رنگی</h3></div>
                                <div class="color-preview-area" style="<?php foreach ($configs['colors'] as $name => $value) echo '--' . htmlspecialchars($name) . ':' . htmlspecialchars($value) . ';'; ?>">
                                    <h4>این یک عنوان نمونه است</h4>
                                    <p>این یک متن نمونه برای نمایش رنگ فونت اصلی صفحه شما است.</p>
                                    <button type="button" class="preview-button"><span class="btn-text">دکمه اصلی</span></button>
                                    <div class="preview-card">
                                        <p>این یک کارت نمونه است:</p>
                                        <div class="placeholder"></div>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group"><label for="bg">پس‌زمینه (bg):</label><input type="color" name="bg" id="bg" value="<?= htmlspecialchars($configs['colors']['bg']) ?>"></div>
                                    <div class="form-group"><label for="title-color">عنوان (title):</label><input type="color" name="title-color" id="title-color" value="<?= htmlspecialchars($configs['colors']['title']) ?>"></div>
                                    <div class="form-group"><label for="primary">رنگ اصلی (primary):</label><input type="color" name="primary" id="primary" value="<?= htmlspecialchars($configs['colors']['primary']) ?>"></div>
                                    <div class="form-group"><label for="primary-hover">هاور رنگ اصلی (primary-hover):</label><input type="color" name="primary-hover" id="primary-hover" value="<?= htmlspecialchars($configs['colors']['primary-hover']) ?>"></div>
                                    <div class="form-group"><label for="card-bg">پس‌زمینه کارت (card-bg):</label><input type="color" name="card-bg" id="card-bg" value="<?= htmlspecialchars($configs['colors']['card-bg']) ?>"></div>
                                    <div class="form-group"><label for="placeholder">جایگاه‌ها (placeholder):</label><input type="color" name="placeholder" id="placeholder" value="<?= htmlspecialchars($configs['colors']['placeholder']) ?>"></div>
                                    <div class="form-group"><label for="placeholder-border">کادر جایگاه‌ها (placeholder-border):</label><input type="color" name="placeholder-border" id="placeholder-border" value="<?= htmlspecialchars($configs['colors']['placeholder-border']) ?>"></div>
                                    <div class="form-group"><label for="text">متن ساده (text):</label><input type="color" name="text" id="text" value="<?= htmlspecialchars($configs['colors']['text']) ?>"></div>
                                </div>
                                <div class="form-actions">
                                    <button type="button" id="reset-colors-btn" class="btn btn--primary" data-defaults='<?= htmlspecialchars(json_encode($defaultConfigs['colors'])) ?>'><span class="btn-text">بازگشت به پیش‌فرض</span></button>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h3>CSS سفارشی</h3>
                                </div>
                                <div class="alert-warning">
                                    <p><strong>توجه:</strong> تغییرات CSS فقط روی صفحه پخش زنده تأثیر می‌گذارد. تغییرات با کلیک روی دکمه "ذخیره تمام تغییرات" اعمال می‌شود.</p>
                                </div>
                                <div class="form-group">
                                    <label>ویرایشگر CSS:</label>
                                    <textarea id="custom-css-editor" 
                                            name="custom_css" 
                                            style="display: none;"><?php
                                            $customCSSFile = EVENTS_DIR . $current_event_id . '/custom.css';
                                            echo file_exists($customCSSFile)
                                                ? htmlspecialchars(file_get_contents($customCSSFile))
                                                : " /* مثال‌های CSS سفارشی:\n\n.card {\n    border-radius: 20px;\n    box-shadow: 0 10px 30px rgba(0,0,0,0.2);\n}\n\n.video {\n    border: 3px solid var(--primary);\n}\n\nh1 {\n    font-size: 2.5rem;\n}\n\n*/";
                                            ?></textarea>
                                </div>
                                
                                <!-- CSS Reference -->
                                <div class="css-reference">
                                    <h4>متغیرهای رنگی قابل استفاده:</h4>
                                    <code class="language-css">var(--bg), var(--title), var(--primary), var(--primary-hover), var(--card-bg), var(--placeholder), var(--text)</code>
                                    
                                    <h4>کلاس‌های اصلی:</h4>
                                    <code class="language-css">.card {}, .video {}, .banner {}, .btn {}, .countdown {}, .social {}, header {}, main {}, footer {}</code>
                                    <h4>کلاس‌های صفحه اعتبارسنجی:</h4>

                                    <code class="language-css">.auth-container {}, .auth-icon {}, .error-message {}, .input-hint {}</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
                <div id="phone-validation" class="tab-panel">
                    <div class="card">
                        <div class="card-header">
                            <h3>اعتبارسنجی شماره تلفن</h3>
                            <div>
                                <a data-tippy-content="دانلود لیست شماره‌ها (CSV)" href="?download=phones&event_id=<?= htmlspecialchars($current_event_id) ?>"
                                    class="btn btn--primary btn--icon" title="دانلود لیست شماره‌ها (CSV)" id="download-phone-list-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18"
                                        height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                                <button type="button" id="refresh-phone-validation-btn" class="btn btn--primary btn--icon"
                                    title="بارگذاری مجدد">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert-warning" style="margin-bottom: 1.5rem;">
                            <p><strong>توضیحات:</strong></p>
                            <p>با فعال کردن این قابلیت، فقط کاربرانی که شماره تلفن آن‌ها در لیست (CSV یا WordPress) موجود است می‌توانند به پخش زنده دسترسی داشته باشند.</p>
                        </div>
                        
                        <div class="phone-validation-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" id="phone-validation-enabled">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">فعال‌سازی اعتبارسنجی شماره تلفن</span>
                            </label>
                        </div>
                        
                        <div id="phone-validation-stats" class="phone-validation-stats">
                            </div>
                        
                        <div class="card" style="margin-bottom: 1.5rem; background: var(--grey-x-light-color);">
                            <h4>انتخاب منبع اعتبارسنجی</h4>
                            <div class="restore-target" style="margin-top: 1rem;">
                                <label>
                                    <input type="radio" name="phone_source_type" value="csv" id="phone_source_csv" checked>
                                    <p>آپلود فایل CSV (دستی)</p>
                                </label>
                                <label>
                                    <input type="radio" name="phone_source_type" value="wordpress" id="phone_source_wordpress">
                                    <p>WordPress / Gravity Forms (API)</p>
                                </label>
                            </div>
                        </div>

                        <div id="phone-validation-csv-container">
                            <div class="phone-validation-upload">
                                <h4>آپلود لیست شماره تلفن‌ها (CSV)</h4>
                                <p>هر خط باید شامل یک شماره تلفن باشد. آپلود جدید، لیست قبلی را بازنویسی می‌کند.</p>
                                
                                <form id="phone-csv-upload-form" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_phone_numbers_csv">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                    
                                    <div class="upload-dropzone" id="phone-csv-dropzone">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 4 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <p>فایل CSV را اینجا رها کنید یا کلیک کنید</p>
                                        <small>فرمت قابل قبول: CSV - حداکثر 5MB</small>
                                        <input type="file" id="phone-csv-input" name="csv_file" accept=".csv,text/csv" style="display: none;">
                                    </div>
                                    <button type="submit" class="btn btn--primary" id="upload-csv-btn" style="margin-top: 1rem;">
                                        <span class="btn-text">آپلود فایل CSV</span>
                                    </button>
                                </form>
                            </div>
                            
                            <div id="current-csv-info" class="current-csv-info" style="display: none;">
                                <h4>آخرین آپلود CSV</h4>
                                <div class="csv-info-content">
                                    <p><strong>تاریخ آپلود:</strong> <span id="csv-upload-date"></span></p>
                                    <p><strong>تعداد شماره‌ها:</strong> <span id="csv-total-numbers"></span></p>
                                    <p><strong>آپلود شده توسط:</strong> <span id="csv-uploaded-by"></span></p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="phone-validation-wp-container" style="display: none;">
                            <form id="wp-validation-settings-form">
                                <input type="hidden" name="action" value="save_wp_validation_settings">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                
                                <div class="phone-validation-upload">
                                    <h4>تنظیمات اتصال به WordPress</h4>
                                    <p>اطلاعات اتصال به افزونه WordPress را وارد کنید.</p>
                                    
                                    <div class="alert-warning" style="margin-bottom: 1rem;">
                                        <p><strong>نکته:</strong> پس از وارد کردن URL و API Key، روی دکمه "بارگیری فرم‌ها" کلیک کنید.</p>
                                    </div>
                                    
                                    <div class="form-grid">
                                        <!-- Step 1: WordPress Connection -->
                                        <div class="form-group" style="grid-column: 1 / -1;">
                                            <label for="wp_api_url">
                                                WordPress Site URL:
                                                <div class="label-tooltip">
                                                    <svg data-tippy-content="آدرس پایه سایت وردپرس (مثال: https://yoursite.com)" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                    </svg>
                                                </div>
                                            </label>
                                            <input type="url" id="wp_api_url" name="wp_api_url" 
                                                placeholder="https://yourwordpress.com" required>
                                        </div>
                                        
                                        <div class="form-group" style="grid-column: 1 / -1;">
                                            <label for="wp_api_key">
                                                API Key:
                                                <div class="label-tooltip">
                                                    <svg data-tippy-content="کلید API را از تنظیمات افزونه در وردپرس کپی کنید" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                    </svg>
                                                </div>
                                            </label>
                                            <input type="text" id="wp_api_key" name="wp_api_key" 
                                                placeholder="ls_..." required>
                                        </div>
                                        
                                        <!-- Step 2: Form Selection (hidden until forms loaded) -->
                                        <div id="wp-forms-selection" style="grid-column: 1 / -1; display: none;">
                                            <div class="form-group">
                                                <label for="wp_form_select">
                                                    انتخاب فرم Gravity Forms:
                                                    <div class="label-tooltip">
                                                        <svg data-tippy-content="فرم مورد نظر خود را از لیست انتخاب کنید" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                        </svg>
                                                    </div>
                                                </label>
                                                <div style="display: flex; gap: var(--spacing-sm);">
                                                    <select id="wp_form_select" name="wp_form_id" required style="width: 100%; padding: 0.5rem; border-radius: 8px; border: 2px solid var(--placeholder-border);">
                                                        <option value="">-- انتخاب فرم --</option>
                                                    </select>
                                                    <div>
                                                        <button data-tippy-content="بارگیری فرم‌ها از WordPress" type="button" class="btn btn--primary btn--icon" id="load-wp-forms-btn" style="width: 100%;">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Field Selection (shown after form is selected) -->
                                            <div id="wp-fields-selection" style="display: none;">
                                                <div class="form-group">
                                                    <label for="wp_field_select">
                                                        انتخاب فیلد تلفن (اختیاری):
                                                        <div class="label-tooltip">
                                                            <svg data-tippy-content="اگر انتخاب نکنید، سیستم به صورت خودکار فیلد تلفن را پیدا می‌کند" class="tooltip-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                                                            </svg>
                                                        </div>
                                                    </label>
                                                    <select id="wp_field_select" name="wp_field_id" style="width: 100%; padding: 0.5rem; border-radius: 8px; border: 2px solid var(--placeholder-border);">
                                                        <option value="">-- شناسایی خودکار --</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions" style="margin-top: 1.5rem;">
                                        <button type="submit" class="btn btn--primary" id="save-wp-settings-btn">
                                            <span class="btn-text">ذخیره تنظیمات</span>
                                        </button>
                                    </div>
                                    
                                    <div id="wp-connection-status" style="margin-top: 1rem; padding: 1rem; background: var(--card-bg); border-radius: 8px; display: none;">
                                        <p><strong>وضعیت اتصال:</strong> <span id="wp-connection-status-text">---</span></p>
                                        <p><strong>تعداد فرم‌های یافت شده:</strong> <span id="wp-forms-count-text">---</span></p>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div id="users" class="tab-panel">
                    <div class="card-grid">
                        <?php if (is_owner()): ?>
                            <div class="card">
                                <div class="card-header"><h3>افزودن کاربر جدید</h3></div>
                                <form id="add-user-form" class="standalone-form">
                                    <input type="hidden" name="action" value="add_user">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <div class="form-group">
                                        <label for="new_username">نام کاربری:</label>
                                        <input type="text" id="new_username" name="username" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new_password">رمز عبور:</label>
                                        <input type="password" id="new_password" name="password" required>
                                    </div>
                                    <div class="form-actions">
                                        <button class="btn btn--primary" type="submit"><span class="btn-text">افزودن کاربر</span></button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        <div class="card" style="<?= !is_owner() ? 'grid-column: 1 / -1;' : '' ?>">
                            <div class="card-header"><h3>لیست کاربران</h3></div>    
                            <div id="users-list" class="users-list">
                                <?php foreach ($users as $user): ?>
                                    <div class="user-item">
                                        <span class="user-name">
                                            <?= htmlspecialchars($user['username']) ?>
                                            <?php if ($user['role'] === 'owner'): ?>
                                                <span class="user-role-badge">(Owner)</span>
                                            <?php endif; ?>
                                        </span>
                                        <div class="user-actions">
                                            <?php
                                            $is_current_user = ($user['id'] == $_SESSION['user_id']);
                                            if (is_owner() || $is_current_user) {
                                                echo '<button class="btn btn--primary btn--icon edit-user-btn" data-id="' . $user['id'] . '" data-username="' . htmlspecialchars($user['username']) . '" tite="ویرایش کاربر"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L15.232 5.232z" /></svg></button>';
                                            }
                                            if (is_owner() && !$is_current_user) {
                                                echo '<button class="btn btn--danger btn--icon delete-user-btn" data-id="' . $user['id'] . '" data-username="' . htmlspecialchars($user['username']) . '" title="حذف کاربر"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>';
                                            }
                                            if ($is_current_user && !is_owner()) {
                                                echo '<button class="btn btn--danger btn--icon delete-user-btn delete-my-account-btn" data-id="' . $user['id'] . '" data-username="' . htmlspecialchars($user['username']) . '" title="حذف حساب من"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="backup" class="tab-panel">
                    <div class="card-grid">
                        <div class="card">
                            <div class="card-header"><h3>دریافت نسخه پشتیبان (Export)</h3></div>
                            <p>برای دریافت نسخه پشتیبان از تنظیمات رویداد فعلی، روی دکمه‌های زیر کلیک کنید.</p>
                            <div class="form-actions">
                                <a href="?download=configs&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn btn--primary btn--outline">دانلود تنظیمات</a>
                                <a href="?download=subtitles&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn btn--primary btn--outline">دانلود زیرنویس‌ها</a>
                                <a href="?download=uploads&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn btn--primary btn--outline">دانلود آپلودها</a>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><h3>بازیابی از نسخه پشتیبان (Import)</h3></div>
                            <p>فایل پشتیبان JSON را انتخاب کرده و مشخص کنید کدام بخش از رویداد فعلی را می‌خواهید جایگزین کنید.</p>
                            <form id="restore-form" class="standalone-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="restore_backup">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <div class="restore-target">
                                    <label>
                                        <input type="radio" name="restore_target" value="configs" checked>
                                        <p>تنظیمات</p>
                                    </label>
                                    <label>
                                        <input type="radio" name="restore_target" value="subtitles">
                                        <p>زیرنویس‌ها</p>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <div class="alert-warning">
                                        <p><strong>نکته مهم:</strong> فرآیند بازیابی فقط تنظیمات را بازگردانی می‌کند و شامل فایل‌های آپلود شده (لوگو، بنرها و آیکون‌ها) نمی‌شود. پس از بازیابی، لطفاً تصاویر را به صورت دستی مجدداً آپلود نمایید.</p>
                                    </div>
                                    <label for="backup_file">فایل پشتیبان (فقط JSON):</label>
                                    <input type="file" name="backup_file" id="backup_file" accept=".json,application/json">
                                </div>
                                <div id="json-preview-container" style="display: none;">
                                    <h4>پیش‌نمایش محتوای فایل:</h4>
                                    <pre id="json-preview" class="language-json"><code></code></pre>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn--primary" id="restore-btn" disabled><span class="btn-text">تایید و بازیابی</span></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="versions" class="tab-panel">
                    <div class="card">
                        <div class="card-header">
                            <h3>تاریخچه نسخه‌های تنظیمات</h3>
                            <button type="button" id="refresh-versions-btn" class="btn btn--primary btn--icon" title="بارگذاری مجدد">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                        <p>آخرین ۱۰ نسخه از تنظیمات رویداد ذخیره می‌شود. می‌توانید به هر نسخه برگردید یا نسخه‌ها را مقایسه کنید.</p>

                        <div id="versions-list" class="versions-list">
                            <div class="loading-versions"><div class="loader-spinner"></div></div>
                        </div>
                    </div>
                </div>
                <div id="media" class="tab-panel">
                    <div class="card">
                        <div class="card-header">
                            <h3>کتابخانه رسانه</h3>
                            <div>
                                <button type="button" id="cleanup-media-btn" class="btn btn--danger btn--icon remove-all-btn" title="حذف فایل‌های استفاده نشده">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                                <button type="button" id="refresh-media-btn" class="btn btn--primary btn--icon" title="بارگذاری مجدد">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Upload Section -->
                        <div class="media-upload-section">
                            <form id="media-upload-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_media">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                
                                <div class="upload-dropzone" id="media-dropzone">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <p>فایل را اینجا رها کنید یا کلیک کنید</p>
                                    <small>فرمت‌های مجاز: JPG, PNG, GIF, WebP, SVG - حداکثر 5MB</small>
                                    <input type="file" id="media-file-input" name="media_file" accept="image/*" style="display: none;">
                                </div>
                                
                                <div class="form-grid" style="margin-top: 1rem;">
                                    <div class="form-group">
                                        <label>توضیحات (اختیاری):</label>
                                        <input type="text" name="description" placeholder="توضیحات فایل">
                                    </div>
                                    <div class="form-group">
                                        <label>برچسب‌ها (اختیاری):</label>
                                        <input type="text" name="tags" placeholder="بنر, لوگو, آیکون (با کاما جدا کنید)">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn--primary" id="upload-media-btn">
                                    <span class="btn-text">آپلود فایل</span>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Stats Section -->
                        <div id="media-stats" class="media-stats"></div>
                        
                        <!-- Search and Filter -->
                        <div class="media-filters">
                            <input type="text" id="media-search" class="search-box" placeholder="جستجو در نام، توضیحات یا برچسب‌ها...">
                            <select id="media-filter-type">
                                <option value="">همه فرمت‌ها</option>
                                <option value="image/jpeg">JPEG</option>
                                <option value="image/png">PNG</option>
                                <option value="image/gif">GIF</option>
                                <option value="image/webp">WebP</option>
                                <option value="image/svg+xml">SVG</option>
                            </select>
                        </div>
                        
                        <!-- Media Grid -->
                        <div id="media-grid" class="media-grid">
                            <div class="loading-media"><div class="image-loader"></div></div>
                        </div>
                    </div>
                </div>
            </main>
            <div class="save-button-container">
                <button type="button" id="preview-btn" class="btn btn--primary btn--outline">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span class="btn-text">پیش‌نمایش</span>
                </button>
                <button type="submit" class="btn btn--primary" id="main-save-btn" form="settings-form" disabled>
                    <span class="btn-text">ذخیره تمام تغییرات</span>
                </button>
            </div>
        </div>

        <!-- Templates for dynamic items -->
        <template id="button-template">
            <div class="sortable-item">
                <div class="sortable-header">
                    <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                    </svg>
                    <span class="item-title">دکمه #<span class="item-counter"></span></span>
                    <button type="button" class="btn btn--danger btn--icon remove-btn" title="حذف این آیتم">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
                <div class="sortable-content">
                    <div class="form-group"><label>عنوان:</label><input type="text" name="button_title[]" value=""></div>
                    <div class="form-group"><label>لینک:</label><input type="url" name="button_link[]" value=""></div>
                </div>
            </div>
        </template>
        <template id="social-template">
            <div class="sortable-item">
                <div class="sortable-header">
                    <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                    </svg>
                    <span class="item-title">آیتم #<span class="item-counter"></span></span>
                    <button type="button" class="btn btn--danger btn--icon remove-btn" title="حذف این آیتم">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
                    <div class="sortable-content">
                        <div class="form-group"><label>عنوان:</label><input type="text" name="social_title[]" value=""></div>
                        <div class="form-group"><label>لینک:</label><input type="url" name="social_link[]" value=""></div>
                        <div class="form-group image-group">
                            <label>آیکون:</label>
                            <div class="image-preview-container"><img src="" class="image-preview"></div>
                            <input type="text" name="social_icon_url[]" placeholder="آدرس آیکون" value="" class="preview-url-input">
                            <div class="image-actions">
                                <button type="button" class="btn btn--primary btn--outline select-from-library-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="btn-text">انتخاب از کتابخانه</span>
                                </button>
                                <label class="btn btn--primary btn--outline upload-new-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 4 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span class="btn-text">آپلود جدید</span>
                                    <input type="file" name="social_icon_file[]" accept="image/*" class="preview-file-input" style="display: none;">
                                </label>
                            </div>
                            <input type="hidden" name="social_icon_old[]" value="">
                        </div>
                    </div>
            </div>
        </template>
        <template id="subtitle-template">
            <div class="sortable-item">
                <div class="sortable-header">
                    <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                    </svg>
                    <span class="item-title">زیرنویس #<span class="item-counter"></span></span>
                    <button type="button" class="btn btn--danger btn--icon remove-btn" title="حذف این آیتم">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
                <div class="sortable-content form-grid">
                    <div class="form-group"><label>متن:</label><input type="text" name="subtitle_text[]" value=""></div>
                    <div class="form-group"><label>لینک (اختیاری):</label><input type="url" name="subtitle_link[]" value=""></div>
                </div>
            </div>
        </template>

        <div id="toast-container"></div>
        <div id="confirm-modal" class="modal-overlay">
            <div class="modal-content">
                <h3 id="modal-title">تایید عملیات</h3>
                <p id="modal-text">آیا از انجام این کار مطمئن هستید؟</p>
                <div class="modal-buttons">
                    <button id="modal-confirm-btn" class="btn btn--danger"><span class="btn-text">تایید</span></button>
                    <button id="modal-cancel-btn" class="btn btn--primary btn--outline modal-cancel-btn"><span class="btn-text">انصراف</span></button>
                </div>
            </div>
        </div>
        <div id="prompt-modal" class="modal-overlay">
            <div class="modal-content">
                <h3 id="prompt-title">عنوان</h3>
                <p id="prompt-text">لطفا یک مقدار وارد کنید:</p>
                <input type="text" id="prompt-input" class="prompt-input">
                <div class="modal-buttons">
                    <button id="prompt-confirm-btn" class="btn btn--primary"><span class="btn-text">تایید</span></button>
                    <button id="prompt-cancel-btn" class="btn btn--danger btn--outline"><span class="btn-text">انصراف</span></button>
                </div>
            </div>
        </div>
        <div id="edit-user-modal" class="modal-overlay">
            <div class="modal-content">
                <h3>ویرایش کاربر</h3>
                <form id="edit-user-form" class="standalone-form">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="form-group">
                        <label for="edit_username">نام کاربری:</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">رمز عبور جدید (اختیاری):</label>
                        <input type="password" id="edit_password" name="password">
                        <small class="form-hint">برای عدم تغییر، این فیلد را خالی بگذارید.</small>
                    </div>
                    <div class="modal-buttons">
                        <button type="submit" class="btn btn--primary"><span class="btn-text">ذخیره تغییرات</span></button>
                        <button type="button" class="btn btn--danger btn--outline modal-cancel-btn"><span class="btn-text">انصراف</span></button>
                    </div>
                </form>
            </div>
        </div>
        <div id="media-detail-modal" class="modal-overlay">
            <div class="modal-content media-detail-content">
                <div class="media-detail-header">
                    <h3>جزئیات فایل</h3>
                    <button type="button" class="btn btn--danger btn--icon modal-cancel-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="media-detail-body">
                    <div class="media-detail-preview">
                        <img id="media-detail-image" src="" alt="">
                    </div>
                    <div class="media-detail-info">
                        <div class="form-group">
                            <label>نام فایل:</label>
                            <input type="text" id="media-detail-filename" readonly>
                        </div>
                        <div class="form-group">
                            <label>آدرس فایل:</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="text" id="media-detail-filepath" readonly style="flex: 1;">
                                <button type="button" id="copy-filepath-btn" class="btn btn--primary btn--icon" title="کپی آدرس">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>ابعاد:</label>
                            <input type="text" id="media-detail-dimensions" readonly>
                        </div>
                        <div class="form-group">
                            <label>حجم:</label>
                            <input type="text" id="media-detail-size" readonly>
                        </div>
                        <div class="form-group">
                            <label>آپلود شده توسط:</label>
                            <input type="text" id="media-detail-uploader" readonly>
                        </div>
                        <div class="form-group">
                            <label>تاریخ آپلود:</label>
                            <input type="text" id="media-detail-date" readonly>
                        </div>
                        <div class="form-group">
                            <label>توضیحات:</label>
                            <textarea id="media-detail-description" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>برچسب‌ها:</label>
                            <input type="text" id="media-detail-tags">
                        </div>
                        <div id="media-usage-info" class="alert-warning" style="display: none; margin-top: 1rem;">
                            <p><strong>این فایل در حال استفاده است در:</strong></p>
                            <ul id="media-usage-list"></ul>
                        </div>
                        <div class="form-actions">
                            <button type="button" id="save-media-info-btn" class="btn btn--primary">
                                <span class="btn-text">ذخیره تغییرات</span>
                            </button>
                            <button type="button" id="delete-media-btn" class="btn btn--danger">
                                <span class="btn-text">حذف فایل</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="preview-modal" class="modal-overlay preview-modal">
        <div class="preview-modal-content">
            <div class="preview-modal-header">
                <h3>پیش‌نمایش صفحه پخش زنده</h3>
                <div class="preview-badge">حالت پیش‌نمایش</div>
                <div class="preview-controls">
                    <select id="preview-state">
                        <option value="pre">قبل از شروع</option>
                        <option value="live" selected>در حال پخش</option>
                        <option value="end">پایان یافته</option>
                    </select>
                    <button type="button" id="close-preview-btn" class="btn btn--danger btn--icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="preview-modal-body">
                <iframe id="preview-frame" sandbox="allow-same-origin allow-scripts"></iframe>
            </div>
        </div>
    </div>

    <div id="compare-modal" class="modal-overlay compare-modal">
        <div class="compare-modal-content">
            <div class="compare-modal-header">
                <h3>مقایسه نسخه‌ها</h3>
                <button type="button" class="btn btn--danger btn--icon modal-cancel-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="compare-modal-body">
                <div class="compare-column">
                    <h4 id="compare-v1-title">نسخه #1</h4>
                    <div id="compare-v1-content" class="compare-content"></div>
                </div>
                <div class="compare-column">
                    <h4 id="compare-v2-title">نسخه #2</h4>
                    <div id="compare-v2-content" class="compare-content"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay media-picker-modal" id="media-picker-modal" style="z-index: 2000;">
        <div class="modal-content">
            <div class="media-picker-header">
                <div>
                    <h3>انتخاب یا آپلود تصویر</h3>
                    <div class="media-picker-tabs">
                        <button type="button" class="media-picker-tab active" data-tab="library">کتابخانه رسانه</button>
                        <button type="button" class="media-picker-tab" data-tab="upload">آپلود جدید</button>
                    </div>
                </div>
                <button type="button" class="btn btn--danger btn--icon close-picker-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="media-picker-body">
                <!-- Library Tab -->
                <div id="picker-library-tab" class="media-picker-tab-content active">
                    <input type="text" id="picker-search" class="search-box" placeholder="جستجو..." style="width: 100%; margin-bottom: 1rem;">
                    <div id="temp-media-grid" class="media-grid"></div>
                </div>
                
                <!-- Upload Tab -->
                <div id="picker-upload-tab" class="media-picker-tab-content">
                    <form id="quick-upload-form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_media">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
    
                        <div class="quick-upload-zone" id="quick-upload-zone">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 4 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p>فایل را اینجا رها کنید یا کلیک کنید</p>
                            <small>فرمت‌های مجاز: JPG, PNG, GIF, WebP, SVG - حداکثر 5MB</small>
                            <input type="file" id="quick-file-input" name="media_file" accept="image/*"
                                style="display: none;">
                        </div>
    
                        <div class="upload-progress" id="upload-progress">
                            <p>در حال آپلود...</p>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progress-fill"></div>
                            </div>
                        </div>
                        
                        <div class="alert-warning">
                            <p>
                                <strong>توجه: </strong>بعد از انتخاب یا رها کردن فایل، آپلود آغاز می‌شود. ابتدا "توضیحات" و "برچسب‌ها" را وارد کنید.
                            </p>
                        </div>

                        <div class="form-grid" style="margin-top: 1rem;">
                            <div class="form-group">
                                <label>توضیحات (اختیاری):</label>
                                <input type="text" name="description" placeholder="توضیحات فایل">
                            </div>
                            <div class="form-group">
                                <label>برچسب‌ها (اختیاری):</label>
                                <input type="text" name="tags" placeholder="بنر, لوگو (با کاما جدا کنید)">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>

    <!-- Tippy.JS -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>

    <!-- Prism.JS -->
    <script src="assets/js/prism.js"></script>

    <!-- CodeMirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/css-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
</body>

</html>
