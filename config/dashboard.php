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
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
                                <h3>تنظیمات اصلی</h3>
                                <div class="form-grid">
                                    <div class="form-group"><label for="title">عنوان پخش زنده:</label><input required type="text" name="title" id="title" value="<?= htmlspecialchars($configs['title']) ?>"></div>
                                    <div class="form-group"><label for="homePage">صفحه اصلی:</label><input type="url" name="homePage" id="homePage" value="<?= htmlspecialchars($configs['homePage']) ?>"></div>
                                    <div class="form-group"><label for="iframe">ای‌فریم:</label><input type="text" name="iframe" id="iframe" value="<?= htmlspecialchars($configs['iframe']) ?>"></div>
                                    <div class="form-group"><label for="liveStart">زمان شروع:</label><input type="datetime-local" name="liveStart" id="liveStart" value="<?= htmlspecialchars($configs['liveStart']) ?>"></div>
                                    <div class="form-group"><label for="liveEnd">زمان پایان:</label><input type="datetime-local" name="liveEnd" id="liveEnd" value="<?= htmlspecialchars($configs['liveEnd']) ?>"></div>
                                    <div class="form-group"><label for="fetchInterval">فاصله زمانی زیرنویس (ms):</label><input type="number" name="fetchInterval" id="fetchInterval" value="<?= htmlspecialchars($configs['fetchInterval']) ?>"></div>
                                    <div class="form-group"><label for="subtitleDelay">زمان نمایش زیرنویس (ms):</label><input type="number" name="subtitleDelay" id="subtitleDelay" value="<?= htmlspecialchars($configs['subtitleDelay']) ?>"></div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h3>دکمه‌ها</h3>
                                    <button type="button" id="add-button-btn" class="btn btn--primary btn--icon" title="دکمه جدید">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </button>
                                </div>    
                                <div id="buttons-container" class="sortable-list-grid">
                                    <?php foreach ($configs['buttons'] as $index => $button): ?>
                                        <div class="sortable-item">
                                            <div class="sortable-header">
                                                <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                                                </svg>
                                                <span class="item-title">دکمه #<span class="item-counter"><?= $index + 1 ?></span></span>
                                                <button type="button" class="btn btn--danger btn--sm remove-btn"><span class="btn-text">حذف</span></button>
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
                                    <button type="button" id="add-social-btn" class="btn btn--primary btn--icon" title="افزودن آیتم اجتماعی">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </button>
                                </div>    
                                <div id="socials-container" class="sortable-list-grid">
                                    <?php foreach ($configs['socials'] as $index => $social): ?>
                                        <div class="sortable-item">
                                            <div class="sortable-header">
                                                <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
                                                </svg>
                                                <span class="item-title">آیتم #<span class="item-counter"><?= $index + 1 ?></span></span>
                                                <button type="button" class="btn btn--danger btn--sm remove-btn"><span class="btn-text">حذف</span></button>
                                            </div>
                                            <div class="sortable-content">
                                                <div class="form-group"><label>عنوان:</label><input type="text" name="social_title[]" value="<?= htmlspecialchars($social['title']) ?>"></div>
                                                <div class="form-group"><label>لینک:</label><input type="url" name="social_link[]" value="<?= htmlspecialchars($social['link']) ?>"></div>
                                                <div class="form-group image-group">
                                                    <label>آیکن:</label>
                                                    <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($social['icon'])) ?>" class="image-preview"></div>
                                                    <input type="text" name="social_icon_url[]" placeholder="آدرس آیکن" value="<?= htmlspecialchars(get_dashboard_image_url($social['icon'])) ?>" class="preview-url-input">
                                                    <input type="file" name="social_icon_file[]" accept="image/*" class="preview-file-input">
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
                                        <button type="button" id="remove-all-subtitles-btn" class="btn btn--danger btn--sm"><span class="btn-text">حذف همه</span></button>
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
                                                <span class="item-title">زیرنویس</span>
                                                <button type="button" class="btn btn--danger btn--sm remove-btn"><span class="btn-text">حذف</span></button>
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
                                <h3>تصاویر و بنرها</h3>
                                <div class="image-upload-grid">
                                    <div class="form-group image-group">
                                        <label for="logo_file">لوگو:</label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['logo'])) ?>" class="image-preview" id="logo_preview"></div>
                                        <input type="text" name="logo_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['logo'])) ?>" class="preview-url-input">
                                        <input type="file" name="logo_file" id="logo_file" accept="image/*" class="preview-file-input">
                                        <input type="hidden" name="logo_old" value="<?= htmlspecialchars($configs['logo']) ?>">
                                    </div>
                                    <div class="form-group image-group">
                                        <label for="preBanner_file">بنر قبل از پخش:</label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['preBanner'])) ?>" class="image-preview" id="preBanner_preview"></div>
                                        <input type="text" name="preBanner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['preBanner'])) ?>" class="preview-url-input">
                                        <input type="file" name="preBanner_file" id="preBanner_file" accept="image/*" class="preview-file-input">
                                        <input type="hidden" name="preBanner_old" value="<?= htmlspecialchars($configs['preBanner']) ?>">
                                    </div>
                                    <div class="form-group image-group">
                                        <label for="endBanner_file">بنر بعد از پخش:</label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['endBanner'])) ?>" class="image-preview" id="endBanner_preview"></div>
                                        <input type="text" name="endBanner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['endBanner'])) ?>" class="preview-url-input">
                                        <input type="file" name="endBanner_file" id="endBanner_file" accept="image/*" class="preview-file-input">
                                        <input type="hidden" name="endBanner_old" value="<?= htmlspecialchars($configs['endBanner']) ?>">
                                    </div>
                                    <div class="form-group image-group">
                                        <label for="banner_file">بنر:</label>
                                        <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['banner'])) ?>" class="image-preview" id="banner_preview"></div>
                                        <input type="text" name="banner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars(get_dashboard_image_url($configs['banner'])) ?>" class="preview-url-input">
                                        <input type="file" name="banner_file" id="banner_file" accept="image/*" class="preview-file-input">
                                        <input type="hidden" name="banner_old" value="<?= htmlspecialchars($configs['banner']) ?>">

                                        <label for="bannerLink" style="margin-top: 1rem;">لینک بنر (اختیاری):</label>
                                        <input type="url" name="bannerLink" id="bannerLink" placeholder="https://example.com" value="<?= htmlspecialchars($configs['bannerLink'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <h3>شخصی‌سازی تم رنگی</h3>
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
                                    <button type="button" id="reset-colors-btn" class="btn btn--primary"><span class="btn-text">بازگشت به پیش‌فرض</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div id="users" class="tab-panel">
                    <div class="card-grid">
                        <?php if (is_owner()): ?>
                            <div class="card">
                                <h3>افزودن کاربر جدید</h3>
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
                            <h3>لیست کاربران</h3>
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
                                                echo '<button class="btn btn--primary btn--icon edit-user-btn" data-id="' . $user['id'] . '" data-username="' . htmlspecialchars($user['username']) . '"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L15.232 5.232z" /></svg></button>';
                                            }
                                            if (is_owner() && !$is_current_user) {
                                                echo '<button class="btn btn--danger btn--icon delete-user-btn" data-id="' . $user['id'] . '" data-username="' . htmlspecialchars($user['username']) . '"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>';
                                            }
                                            if ($is_current_user && !is_owner()) {
                                                echo '<button class="btn btn--danger btn--sm delete-user-btn" data-id="' . $user['id'] . '" data-username="' . htmlspecialchars($user['username']) . '">حذف حساب من</button>';
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
                            <h3>دریافت نسخه پشتیبان (Export)</h3>
                            <p>برای دریافت نسخه پشتیبان از تنظیمات رویداد فعلی، روی دکمه‌های زیر کلیک کنید.</p>
                            <div class="form-actions">
                                <a href="?download=configs&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn btn--primary btn--outline">دانلود تنظیمات</a>
                                <a href="?download=subtitles&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn btn--primary btn--outline">دانلود زیرنویس‌ها</a>
                                <a href="?download=uploads&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn btn--primary btn--outline">دانلود آپلودها</a>
                            </div>
                        </div>
                        <div class="card">
                            <h3>بازیابی از نسخه پشتیبان (Import)</h3>
                            <p>فایل پشتیبان JSON را انتخاب کرده و مشخص کنید کدام بخش از رویداد فعلی را می‌خواهید جایگزین کنید.</p>
                            <form id="restore-form" class="standalone-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="restore_backup">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <div class="restore-target">
                                    <label><input type="radio" name="restore_target" value="configs" checked> تنظیمات</label>
                                    <label><input type="radio" name="restore_target" value="subtitles"> زیرنویس‌ها</label>
                                </div>
                                <div class="form-group">
                                    <label for="backup_file">فایل پشتیبان (فقط JSON):</label>
                                    <input type="file" name="backup_file" id="backup_file" accept=".json,application/json">
                                </div>
                                <div id="json-preview-container" style="display: none;">
                                    <h4>پیش‌نمایش محتوای فایل:</h4>
                                    <pre id="json-preview"></pre>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn--primary" id="restore-btn" disabled><span class="btn-text">تایید و بازیابی</span></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
            <div class="save-button-container">
                <button type="submit" class="btn btn--primary" id="main-save-btn" form="settings-form" disabled><span class="btn-text">ذخیره تمام تغییرات</span></button>
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
                    <button type="button" class="btn btn--danger btn--sm remove-btn"><span class="btn-text">حذف</span></button>
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
                    <button type="button" class="btn btn--danger btn--sm remove-btn"><span class="btn-text">حذف</span></button>
                </div>
                <div class="sortable-content">
                    <div class="form-group"><label>عنوان:</label><input type="text" name="social_title[]" value=""></div>
                    <div class="form-group"><label>لینک:</label><input type="url" name="social_link[]" value=""></div>
                    <div class="form-group image-group">
                        <label>آیکن:</label>
                        <div class="image-preview-container"><img src="" class="image-preview"></div>
                        <input type="text" name="social_icon_url[]" placeholder="آدرس آیکن" value="" class="preview-url-input">
                        <input type="file" name="social_icon_file[]" accept="image/*" class="preview-file-input">
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
                    <span class="item-title">زیرنویس</span>
                    <button type="button" class="btn btn--danger btn--sm remove-btn"><span class="btn-text">حذف</span></button>
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
                    <button id="modal-cancel-btn" class="btn btn--primary btn--outline"><span class="btn-text">انصراف</span></button>
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
    </div>
    <script src="assets/js/dashboard.js"></script>
</body>

</html>