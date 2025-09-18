<?php 
require_once 'functions.php'; 

// --- HELPER FUNCTION FOR IMAGE PATHS (Dashboard specific) ---
function get_dashboard_image_url($path) {
    if (empty($path) || preg_match('/^(https?:)?\/\//', $path)) {
        return $path;
    }
    // dashboard.php is in /config, uploads are in /config/uploads, so the path is relative from /config
    return $path;
}

// --- INITIALIZE FIRST EVENT (Dashboard only) ---
// This logic runs only when the dashboard is loaded for the first time.
if (empty($all_events)) {
    $first_event_id = 'event_' . uniqid();
    $all_events = [['id' => $first_event_id, 'name' => 'رویداد اول']];
    create_event_files($first_event_id, $defaultConfigs);
    
    $events_json = json_encode($all_events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    safe_file_put_contents(EVENTS_FILE, $events_json);
    
    // Reload variables after creation to ensure the page has the correct context
    $current_event_id = $first_event_id;
    $_SESSION['current_event_id'] = $current_event_id;
    $configsFile = EVENTS_DIR . $current_event_id . '/configs.json';
    $subtitlesFile = EVENTS_DIR . $current_event_id . '/subtitles.json';
    $configs = json_decode(file_get_contents($configsFile), true);
    $subtitles = json_decode(file_get_contents($subtitlesFile), true);
}

// Make sure socials key exists to prevent errors
if (!isset($configs['socials'])) {
    $configs['socials'] = $defaultConfigs['socials'];
}

// Fetch all users to display in the user management tab
$users = get_all_users();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

    <aside class="sidebar">
        <nav class="sidebar-nav">
            <h2>پنل مدیریت</h2>
            <ul>
                <li><a href="#" class="tab-button active" data-tab="settings">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066 2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>تنظیمات پخش</span>
                </a></li>
                <li><a href="#" class="tab-button" data-tab="subtitles">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v1h-14v-1zM19 9h-14v10a2 2 0 002 2h10a2 2 0 002-2v-10z" /></svg>
                    <span>مدیریت زیرنویس</span>
                </a></li>
                <li><a href="#" class="tab-button" data-tab="colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                    <span>تم رنگی</span>
                </a></li>
                <li><a href="#" class="tab-button" data-tab="users">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197m0 0A5 5 0 019 10a5 5 0 014 2.002m-4 0a4 4 0 100-5.292" /></svg>
                    <span>مدیریت کاربران</span>
                </a></li>
                <li><a href="#" class="tab-button" data-tab="backup">
                     <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    <span>پشتیبان‌گیری</span>
                </a></li>
            </ul>
        </nav>
    </aside>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <div class="event-manager">
                    <label for="event-selector">رویداد فعلی:</label>
                    <select id="event-selector">
                        <?php foreach ($all_events as $event): ?>
                            <option value="<?= htmlspecialchars($event['id']) ?>" <?= ($event['id'] === $current_event_id) ? 'selected' : '' ?> >
                                <?= htmlspecialchars($event['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="event-actions">
                         <button id="create-event-btn" clfass="btn btn-secondary btn-sm btn-icon" title="رویداد جدید">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                            <span>جدید</span>
                        </button>
                        <button id="rename-event-btn" class="btn btn-secondary btn-sm btn-icon" title="تغییر نام رویداد فعلی">
                             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L15.232 5.232z" /></svg>
                            <span>تغییر نام</span>
                        </button>
                        <button id="delete-event-btn" class="btn btn-danger btn-sm btn-icon" title="حذف رویداد فعلی">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            <span>حذف</span>
                        </button>
                    </div>
                </div>
                <div class="event-id-display">
                    شناسه: <code><?= htmlspecialchars($current_event_id) ?></code>
                    <button id="copy-event-id-btn" title="کپی شناسه"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zM-1 1.5A1.5 1.5 0 0 1 .5 0h3A1.5 1.5 0 0 1 5 1.5v1A1.5 1.5 0 0 1 3.5 4h-3A1.5 1.5 0 0 1-1 2.5v-1z"/></svg></button>
                    <button id="edit-event-id-btn" title="ویرایش شناسه"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg></button>
                </div>
            </div>
            <div class="header-right">
                <span class="current-user">کاربر: <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="../index.php?event=<?= htmlspecialchars($current_event_id) ?>" target="_blank" class="btn btn-view-live">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <span>مشاهده پخش زنده</span>
                </a>
                <a href="logout.php" class="logout-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    خروج
                </a>
            </div>
        </header>

        <main class="content-area">
            <div id="settings" class="tab-panel active">
                 <form id="settings-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <div class="card">
                        <h3>تنظیمات اصلی پخش زنده</h3>
                        <div class="form-grid">
                            <div class="form-group"><label for="title">عنوان پخش زنده:</label><input required type="text" name="title" id="title" value="<?= htmlspecialchars($configs['title']) ?>"></div>
                            <div class="form-group"><label for="homePage">صفحه اصلی:</label><input required type="url" name="homePage" id="homePage" value="<?= htmlspecialchars($configs['homePage']) ?>"></div>
                            <div class="form-group"><label for="iframe">ای‌فریم:</label><input required type="text" name="iframe" id="iframe" value="<?= htmlspecialchars($configs['iframe']) ?>"></div>
                            <div class="form-group"><label for="liveStart">زمان شروع:</label><input required type="datetime-local" name="liveStart" id="liveStart" value="<?= htmlspecialchars($configs['liveStart']) ?>"></div>
                            <div class="form-group"><label for="liveEnd">زمان پایان:</label><input required type="datetime-local" name="liveEnd" id="liveEnd" value="<?= htmlspecialchars($configs['liveEnd']) ?>"></div>
                            <div class="form-group"><label for="fetchInterval">فاصله زمانی دریافت زیرنویس (ms):</label><input required type="number" name="fetchInterval" id="fetchInterval" value="<?= htmlspecialchars($configs['fetchInterval']) ?>"></div>
                            <div class="form-group"><label for="subtitleDelay">زمان نمایش هر زیرنویس (ms):</label><input required type="number" name="subtitleDelay" id="subtitleDelay" value="<?= htmlspecialchars($configs['subtitleDelay']) ?>"></div>
                        </div>
                    </div>
                    <div class="card">
                        <h3>تصاویر و بنرها</h3>
                        <div class="form-grid image-upload-grid">
                            <div class="form-group image-group">
                                <label for="logo_file">لوگو:</label>
                                <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['logo'])) ?>" alt="پیش‌نمایش" class="image-preview" id="logo_preview"></div>
                                <input type="text" name="logo_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars($configs['logo']) ?>" data-preview-target="logo_preview">
                                <input type="file" name="logo_file" id="logo_file" accept="image/*" data-preview-target="logo_preview">
                                <input type="hidden" name="logo_old" value="<?= htmlspecialchars($configs['logo']) ?>">
                            </div>
                            <div class="form-group image-group">
                                <label for="preBanner_file">بنر قبل از پخش:</label>
                                <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['preBanner'])) ?>" alt="پیش‌نمایش" class="image-preview" id="preBanner_preview"></div>
                                <input type="text" name="preBanner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars($configs['preBanner']) ?>" data-preview-target="preBanner_preview">
                                <input type="file" name="preBanner_file" id="preBanner_file" accept="image/*" data-preview-target="preBanner_preview">
                                <input type="hidden" name="preBanner_old" value="<?= htmlspecialchars($configs['preBanner']) ?>">
                            </div>
                            <div class="form-group image-group">
                                <label for="endBanner_file">بنر بعد از پخش:</label>
                                <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['endBanner'])) ?>" alt="پیش‌نمایش" class="image-preview" id="endBanner_preview"></div>
                                <input type="text" name="endBanner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars($configs['endBanner']) ?>" data-preview-target="endBanner_preview">
                                <input type="file" name="endBanner_file" id="endBanner_file" accept="image/*" data-preview-target="endBanner_preview">
                                <input type="hidden" name="endBanner_old" value="<?= htmlspecialchars($configs['endBanner']) ?>">
                            </div>
                             <div class="form-group image-group">
                                <label for="banner_file">بنر:</label>
                                <div class="image-preview-container"><img src="<?= htmlspecialchars(get_dashboard_image_url($configs['banner'])) ?>" alt="پیش‌نمایش" class="image-preview" id="banner_preview"></div>
                                <input type="text" name="banner_url" placeholder="آدرس تصویر" value="<?= htmlspecialchars($configs['banner']) ?>" data-preview-target="banner_preview">
                                <input type="file" name="banner_file" id="banner_file" accept="image/*" data-preview-target="banner_preview">
                                <input type="hidden" name="banner_old" value="<?= htmlspecialchars($configs['banner']) ?>">
                            </div>
                        </div>
                    </div>
                     <div class="card">
                        <h3>دکمه‌ها</h3>
                        <div class="buttons-container">
                           <div class="button-group"><h4>دکمه ۱</h4><div class="form-group"><label for="btn1-title">عنوان:</label><input type="text" name="btn1-title" id="btn1-title" value="<?= htmlspecialchars($configs['buttons']['btn1']['title']) ?>"></div><div class="form-group"><label for="btn1-link">لینک:</label><input type="url" name="btn1-link" id="btn1-link" value="<?= htmlspecialchars($configs['buttons']['btn1']['link']) ?>"></div></div>
                           <div class="button-group"><h4>دکمه ۲</h4><div class="form-group"><label for="btn2-title">عنوان:</label><input type="text" name="btn2-title" id="btn2-title" value="<?= htmlspecialchars($configs['buttons']['btn2']['title']) ?>"></div><div class="form-group"><label for="btn2-link">لینک:</label><input type="url" name="btn2-link" id="btn2-link" value="<?= htmlspecialchars($configs['buttons']['btn2']['link']) ?>"></div></div>
                           <div class="button-group"><h4>دکمه ۳</h4><div class="form-group"><label for="btn3-title">عنوان:</label><input type="text" name="btn3-title" id="btn3-title" value="<?= htmlspecialchars($configs['buttons']['btn3']['title']) ?>"></div><div class="form-group"><label for="btn3-link">لینک:</label><input type="url" name="btn3-link" id="btn3-link" value="<?= htmlspecialchars($configs['buttons']['btn3']['link']) ?>"></div></div>
                        </div>
                    </div>
                    <div class="card">
                        <h3>صفحات اجتماعی</h3>
                        <div class="buttons-container">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="button-group">
                                    <h4>آیتم <?= $i ?></h4>
                                    <div class="form-group">
                                        <label for="social<?= $i ?>-title">عنوان:</label>
                                        <input type="text" name="social<?= $i ?>-title" id="social<?= $i ?>-title" value="<?= htmlspecialchars($configs['socials']['social'.$i]['title']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="social<?= $i ?>-link">لینک:</label>
                                        <input type="url" name="social<?= $i ?>-link" id="social<?= $i ?>-link" value="<?= htmlspecialchars($configs['socials']['social'.$i]['link']) ?>">
                                    </div>
                                    <div class="form-group image-group">
                                        <label for="social<?= $i ?>_icon_file">آیکن:</label>
                                        <div class="image-preview-container">
                                            <img src="<?= htmlspecialchars(get_dashboard_image_url($configs['socials']['social'.$i]['icon'])) ?>" alt="پیش‌نمایش" class="image-preview" id="social<?= $i ?>_icon_preview">
                                        </div>
                                        <input type="text" name="social<?= $i ?>_icon_url" placeholder="آدرس آیکن" value="<?= htmlspecialchars($configs['socials']['social'.$i]['icon']) ?>" data-preview-target="social<?= $i ?>_icon_preview">
                                        <input type="file" name="social<?= $i ?>_icon_file" id="social<?= $i ?>_icon_file" accept="image/*" data-preview-target="social<?= $i ?>_icon_preview">
                                        <input type="hidden" name="social<?= $i ?>_icon_old" value="<?= htmlspecialchars($configs['socials']['social'.$i]['icon']) ?>">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="save-button-container"><button type="submit"><span class="btn-text">ذخیره تمام تنظیمات پخش</span></button></div>
                </form>
            </div>

            <div id="subtitles" class="tab-panel">
                <div class="card">
                    <h3>مدیریت زیرنویس‌ها</h3>
                    <form id="subtitles-form">
                        <input type="hidden" name="action" value="save_subtitles">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <div id="subtitles-container">
                            <?php foreach ($subtitles as $subtitle): ?>
                                <div class="subtitle-item">
                                    <svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 2h10v10H5V5zm2 1a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 4a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 4a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" /></svg>
                                    <input type="text" name="subtitle_text[]" value="<?= htmlspecialchars($subtitle['text']) ?>" placeholder="متن زیرنویس">
                                    <input type="url" name="subtitle_link[]" value="<?= htmlspecialchars($subtitle['link']) ?>" placeholder="لینک (اختیاری)">
                                    <button type="button" class="remove-btn"><span class="btn-text">حذف</span></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-actions">
                            <button type="button" id="add-subtitle-btn" class="btn"><span class="btn-text">افزودن زیرنویس جدید</span></button>
                            <button type="button" id="remove-all-subtitles-btn" class="btn btn-danger"><span class="btn-text">حذف همه</span></button>
                            <button type="submit"><span class="btn-text">ذخیره زیرنویس‌ها</span></button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="colors" class="tab-panel">
                <div class="card">
                    <h3>شخصی‌سازی تم رنگی</h3>
                     <div class="color-preview-area" style="<?php foreach ($configs['colors'] as $name => $value) echo '--' . htmlspecialchars($name) . ':' . htmlspecialchars($value) . ';'; ?>">
                        <h4>این یک عنوان نمونه است</h4>
                        <p>این یک متن نمونه برای نمایش رنگ فونت اصلی صفحه شما است.</p>
                        <button type="button" class="preview-button"><span class="btn-text">دکمه اصلی</span></button>
                        <div class="preview-card"><p>این یک کارت نمونه است:</p><div class="placeholder"></div></div>
                    </div>
                    <form id="color-form">
                        <input type="hidden" name="action" value="save_colors">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <div class="form-grid">
                            <div class="form-group"><label for="bg">پس‌زمینه (bg):</label><input required type="color" name="bg" id="bg" value="<?= htmlspecialchars($configs['colors']['bg']) ?>"></div>
                            <div class="form-group"><label for="title-color">عنوان (title):</label><input required type="color" name="title-color" id="title-color" value="<?= htmlspecialchars($configs['colors']['title']) ?>"></div>
                            <div class="form-group"><label for="primary">رنگ اصلی (primary):</label><input required type="color" name="primary" id="primary" value="<?= htmlspecialchars($configs['colors']['primary']) ?>"></div>
                            <div class="form-group"><label for="primary-hover">هاور رنگ اصلی (primary-hover):</label><input required type="color" name="primary-hover" id="primary-hover" value="<?= htmlspecialchars($configs['colors']['primary-hover']) ?>"></div>
                            <div class="form-group"><label for="card-bg">پس‌زمینه کارت (card-bg):</label><input required type="color" name="card-bg" id="card-bg" value="<?= htmlspecialchars($configs['colors']['card-bg']) ?>"></div>
                            <div class="form-group"><label for="placeholder">جایگاه‌ها (placeholder):</label><input required type="color" name="placeholder" id="placeholder" value="<?= htmlspecialchars($configs['colors']['placeholder']) ?>"></div>
                            <div class="form-group"><label for="placeholder-border">کادر جایگاه‌ها (placeholder-border):</label><input type="color" name="placeholder-border" id="placeholder-border" value="<?= htmlspecialchars($configs['colors']['placeholder-border']) ?>"></div>
                            <div class="form-group"><label for="text">متن ساده (text):</label><input required type="color" name="text" id="text" value="<?= htmlspecialchars($configs['colors']['text']) ?>"></div>
                        </div>
                        <div class="form-actions">
                             <button type="button" id="reset-colors-btn" class="btn btn-secondary" data-defaults='<?= htmlspecialchars(json_encode($defaultConfigs['colors'])) ?>'><span class="btn-text">بازگشت به پیش‌فرض</span></button>
                            <button type="submit"><span class="btn-text">ذخیره رنگ‌ها</span></button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="users" class="tab-panel">
                <div class="card-grid">
                    <?php if (is_owner()): ?>
                    <div class="card">
                        <h3>افزودن کاربر جدید</h3>
                        <form id="add-user-form">
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
                                <button type="submit"><span class="btn-text">افزودن کاربر</span></button>
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
                                            echo '<button class="btn btn-secondary btn-sm edit-user-btn" data-id="'.$user['id'].'" data-username="'.htmlspecialchars($user['username']).'">ویرایش</button>';
                                        }
                                        if (is_owner() && !$is_current_user) {
                                            echo '<button class="btn btn-danger btn-sm delete-user-btn" data-id="'.$user['id'].'" data-username="'.htmlspecialchars($user['username']).'">حذف</button>';
                                        }
                                        if ($is_current_user && !is_owner()) {
                                            echo '<button class="btn btn-danger btn-sm delete-user-btn" data-id="'.$user['id'].'" data-username="'.htmlspecialchars($user['username']).'" data-self-delete="true">حذف حساب من</button>';
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
                            <a href="?download=configs&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn">دانلود تنظیمات</a>
                            <a href="?download=subtitles&event_id=<?= htmlspecialchars($current_event_id) ?>" class="btn">دانلود زیرنویس‌ها</a>
                        </div>
                    </div>
                    <div class="card">
                        <h3>بازیابی از نسخه پشتیبان (Import)</h3>
                        <p>فایل پشتیبان JSON را انتخاب کرده و مشخص کنید کدام بخش از رویداد فعلی را می‌خواهید جایگزین کنید.</p>
                        <form id="restore-form" enctype="multipart/form-data">
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
                                <button type="submit" id="restore-btn" disabled><span class="btn-text">تایید و بازیابی</span></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <div id="toast-container"></div>
    <div id="confirm-modal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modal-title">تایید عملیات</h3>
            <p id="modal-text">آیا از انجام این کار مطمئن هستید؟</p>
            <div class="modal-buttons">
                <button id="modal-confirm-btn" class="btn btn-danger"><span class="btn-text">تایید</span></button>
                <button id="modal-cancel-btn" class="btn"><span class="btn-text">انصراف</span></button>
            </div>
        </div>
    </div>
     <div id="prompt-modal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="prompt-title">عنوان</h3>
            <p id="prompt-text">لطفا یک مقدار وارد کنید:</p>
            <input type="text" id="prompt-input" class="prompt-input">
            <div class="modal-buttons">
                <button id="prompt-confirm-btn" class="btn"><span class="btn-text">تایید</span></button>
                <button id="prompt-cancel-btn" class="btn btn-secondary"><span class="btn-text">انصراف</span></button>
            </div>
        </div>
    </div>
    <div id="edit-user-modal" class="modal-overlay">
        <div class="modal-content">
            <h3>ویرایش کاربر</h3>
            <form id="edit-user-form">
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
                    <button type="submit" class="btn"><span class="btn-text">ذخیره تغییرات</span></button>
                    <button type="button" class="btn btn-secondary modal-cancel-btn"><span class="btn-text">انصراف</span></button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>

