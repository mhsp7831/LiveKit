<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once 'functions.php';

$response = ['success' => false, 'message' => 'درخواست نامعتبر است.'];
$action = $_POST['action'] ?? '';

// CSRF Token Validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $response['message'] = 'خطای امنیتی: درخواست نامعتبر است.';
        write_log('ERROR', 'CSRF token validation failed.');
        echo json_encode($response);
        exit;
    }
}

try {
    // --- User/Event management actions that don't need an active event context ---
    switch ($action) {
        case 'add_user':
            if (!is_owner()) throw new Exception('شما مجوز افزودن کاربر جدید را ندارید.');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if (empty($username) || empty($password)) throw new Exception('نام کاربری و رمز عبور نمی‌توانند خالی باشند.');
            if (strlen($password) < 6) throw new Exception('رمز عبور باید حداقل ۶ کاراکتر باشد.');
            if (get_user_by_username($username)) throw new Exception('این نام کاربری قبلا ثبت شده است.');

            if (add_user($username, $password)) {
                write_log('INFO', "Admin user '{$username}' created by '{$_SESSION['username']}'.");
                $response = ['success' => true, 'message' => 'کاربر با موفقیت اضافه شد.'];
            } else throw new Exception('خطا در افزودن کاربر.');
            break;

        case 'update_user':
            $userId = $_POST['user_id'] ?? 0;
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($userId) || empty($username)) throw new Exception('اطلاعات کاربر ناقص است.');
            if (!is_owner() && $userId != $_SESSION['user_id']) throw new Exception('شما فقط می‌توانید اطلاعات حساب کاربری خود را ویرایش کنید.');
            if (!empty($password) && strlen($password) < 6) throw new Exception('رمز عبور جدید باید حداقل ۶ کاراکتر باشد.');
            
            $existingUser = get_user_by_username($username);
            if ($existingUser && $existingUser['id'] != $userId) throw new Exception('این نام کاربری قبلا ثبت شده است.');

            if (update_user($userId, $username, $password)) {
                 write_log('INFO', "User ID {$userId} ('{$username}') updated by '{$_SESSION['username']}'.");
                 $response = ['success' => true, 'message' => 'کاربر با موفقیت ویرایش شد.'];
            } else throw new Exception('خطا در ویرایش کاربر.');
            break;
            
        case 'delete_user':
            $userId = $_POST['user_id'] ?? 0;
            if (empty($userId)) throw new Exception('شناسه کاربر ارسال نشده است.');
            
            $is_self_delete = ($userId == $_SESSION['user_id']);

            if ($is_self_delete && is_owner()) {
                throw new Exception('کاربر Owner نمی‌تواند حساب کاربری خود را حذف کند.');
            }
            
            if (!is_owner() && !$is_self_delete) {
                throw new Exception('شما مجوز حذف سایر کاربران را ندارید.');
            }

            if (delete_user($userId)) {
                 write_log('INFO', "User ID {$userId} deleted by '{$_SESSION['username']}'.");
                 $response = ['success' => true, 'message' => 'کاربر با موفقیت حذف شد.'];
                 if ($is_self_delete) {
                    $response['self_delete'] = true;
                    session_unset();
                    session_destroy();
                 }
            } else {
                throw new Exception('خطا در حذف کاربر.');
            }
            break;

        case 'create_event':
            $eventName = trim($_POST['event_name'] ?? '');
            if (empty($eventName)) throw new Exception('نام رویداد نمی‌تواند خالی باشد.');
            
            $events = get_events();
            $newEventId = 'event_' . uniqid();
            $events[] = ['id' => $newEventId, 'name' => $eventName];
            
            create_event_files($newEventId, $defaultConfigs);
            
            safe_file_put_contents(EVENTS_FILE, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['current_event_id'] = $newEventId;
            write_log('INFO', "Event created. ID: {$newEventId}, Name: {$eventName}");
            $response = ['success' => true, 'message' => 'رویداد جدید با موفقیت ساخته شد.'];
            break;

        case 'rename_event':
            $eventId = $_POST['event_id'] ?? '';
            $newName = trim($_POST['event_name'] ?? '');
            if (empty($newName)) throw new Exception('نام رویداد نمی‌تواند خالی باشد.');
            if (!is_valid_event_id($eventId)) throw new Exception('رویداد نامعتبر است.');

            $events = get_events();
            foreach ($events as &$event) {
                if ($event['id'] === $eventId) {
                    $event['name'] = $newName;
                    break;
                }
            }

            safe_file_put_contents(EVENTS_FILE, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            write_log('INFO', "Event renamed. ID: {$eventId}, New Name: {$newName}");
            $response = ['success' => true, 'message' => 'نام رویداد با موفقیت تغییر کرد.'];
            break;
            
        case 'edit_event_id':
            $currentId = $_POST['current_event_id'] ?? '';
            $newId = trim($_POST['new_event_id'] ?? '');

            if (empty($newId)) throw new Exception('شناسه جدید ارسال نشده است.');
            if (!is_valid_event_id($currentId)) throw new Exception('رویداد فعلی نامعتبر است.');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $newId)) throw new Exception('شناسه جدید فقط می‌تواند شامل حروف انگلیسی، اعداد و آندرلاین (_) باشد.');

            $events = get_events();
            foreach ($events as $event) {
                if ($event['id'] === $newId && $currentId !== $newId) {
                     throw new Exception('این شناسه قبلاً برای رویداد دیگری استفاده شده است.');
                }
            }

            if ($currentId !== $newId) {
                $oldPath = EVENTS_DIR . $currentId;
                $newPath = EVENTS_DIR . $newId;
                if (!is_dir($oldPath)) throw new Exception('پوشه رویداد فعلی یافت نشد.');
                if (is_dir($newPath)) throw new Exception('یک پوشه با شناسه جدید از قبل وجود دارد.');
                if (!rename($oldPath, $newPath)) throw new Exception('خطا در تغییر نام پوشه رویداد.');

                $oldUploadsPath = UPLOADS_DIR . $currentId;
                $newUploadsPath = UPLOADS_DIR . $newId;
                if (is_dir($oldUploadsPath)) {
                    if (!rename($oldUploadsPath, $newUploadsPath)) {
                        rename($newPath, $oldPath);
                        throw new Exception('خطا در تغییر نام پوشه آپلودها.');
                    }
                }

                $configsFileToUpdate = $newPath . '/configs.json';
                if (file_exists($configsFileToUpdate)) {
                    $eventConfigs = json_decode(file_get_contents($configsFileToUpdate), true);
                    $oldUploadsRelativePath = 'uploads/' . $currentId . '/';
                    $newUploadsRelativePath = 'uploads/' . $newId . '/';

                    // Update main image fields
                    $fileFields = ['logo', 'preBanner', 'endBanner', 'banner'];
                    foreach ($fileFields as $field) {
                        if (isset($eventConfigs[$field]) && strpos($eventConfigs[$field], $oldUploadsRelativePath) === 0) {
                            $eventConfigs[$field] = str_replace($oldUploadsRelativePath, $newUploadsRelativePath, $eventConfigs[$field]);
                        }
                    }
                    
                    // **FIX 3: Also update social icon paths**
                    if (isset($eventConfigs['socials']) && is_array($eventConfigs['socials'])) {
                        foreach ($eventConfigs['socials'] as &$social) { // Use reference to modify
                            if (isset($social['icon']) && strpos($social['icon'], $oldUploadsRelativePath) === 0) {
                                $social['icon'] = str_replace($oldUploadsRelativePath, $newUploadsRelativePath, $social['icon']);
                            }
                        }
                    }

                    safe_file_put_contents($configsFileToUpdate, json_encode($eventConfigs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }

            foreach ($events as &$event) {
                if ($event['id'] === $currentId) $event['id'] = $newId;
            }

            safe_file_put_contents(EVENTS_FILE, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['current_event_id'] = $newId;
            write_log('INFO', "Event ID changed from '{$currentId}' to '{$newId}'.");
            $response = ['success' => true, 'message' => 'شناسه رویداد با موفقیت تغییر کرد.'];
            break;

        case 'delete_event':
            $eventId = $_POST['event_id'] ?? '';
            if (!is_valid_event_id($eventId)) throw new Exception('رویداد نامعتبر است.');
            if (count(get_events()) <= 1) throw new Exception('نمی‌توانید آخرین رویداد را حذف کنید.');

            $events = array_values(array_filter(get_events(), fn($e) => $e['id'] !== $eventId));
            
            $dirPath = EVENTS_DIR . $eventId;
            $uploadsPath = UPLOADS_DIR . $eventId;
            foreach([$dirPath, $uploadsPath] as $pathToDelete) {
                if (is_dir($pathToDelete)) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pathToDelete, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($files as $fileinfo) ($fileinfo->isDir() ? 'rmdir' : 'unlink')($fileinfo->getRealPath());
                    rmdir($pathToDelete);
                }
            }
            
            safe_file_put_contents(EVENTS_FILE, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['current_event_id'] = $events[0]['id'];
            write_log('INFO', "Event deleted. ID: {$eventId}");
            $response = ['success' => true, 'message' => 'رویداد با موفقیت حذف شد.'];
            break;

        case 'switch_event':
            $eventId = $_POST['event_id'] ?? '';
            if (!is_valid_event_id($eventId)) throw new Exception('رویداد نامعتبر است.');
            $_SESSION['current_event_id'] = $eventId;
            $response = ['success' => true, 'message' => 'رویداد تغییر کرد.'];
            break;

        // Default case for actions requiring an active event context
        default:
            if (empty($current_event_id) || !is_valid_event_id($current_event_id)) throw new Exception("هیچ رویداد معتبری انتخاب نشده است.");

            switch ($action) {
                case 'save_settings':
                    $upload_errors = [];
                    $updated_images = [];
                    
                    // Handle main image uploads
                    $file_fields = ['logo', 'preBanner', 'endBanner', 'banner'];
                    foreach ($file_fields as $field) {
                        $result = handle_upload($field . '_file', $field . '_url', $_POST[$field . '_old'] ?? '');
                        if ($result['path'] !== ($_POST[$field . '_old'] ?? '')) {
                            $updated_images[$field.'_url'] = $result['path'];
                        }
                        $configs[$field] = $result['path']; 
                        if ($result['error']) $upload_errors[] = $result['error'];
                    }
                    if (!empty($upload_errors)) throw new Exception(implode('<br>', $upload_errors));
                    
                    // Handle social icon uploads
                    for ($i = 1; $i <= 4; $i++) {
                        $result = handle_upload('social'.$i.'_icon_file', 'social'.$i.'_icon_url', $_POST['social'.$i.'_icon_old'] ?? '');
                        if ($result['path'] !== ($_POST['social'.$i.'_icon_old'] ?? '')) {
                            $updated_images['social'.$i.'_icon_url'] = $result['path'];
                        }
                        $configs['socials']['social'.$i]['icon'] = $result['path'];
                        if ($result['error']) $upload_errors[] = $result['error'];
                    }
                    if (!empty($upload_errors)) throw new Exception(implode('<br>', $upload_errors));


                    // Handle text and other settings
                    $configs['homePage'] = $_POST['homePage'];
                    $configs['title'] = $_POST['title'];
                    $configs['iframe'] = $_POST['iframe'];
                    $configs['liveStart'] = $_POST['liveStart'];
                    $configs['liveEnd'] = $_POST['liveEnd'];
                    $configs['fetchInterval'] = intval($_POST['fetchInterval']);
                    $configs['subtitleDelay'] = intval($_POST['subtitleDelay']);
                    for ($i = 1; $i <= 3; $i++) {
                        $configs['buttons']['btn'.$i]['title'] = $_POST['btn'.$i.'-title'];
                        $configs['buttons']['btn'.$i]['link'] = $_POST['btn'.$i.'-link'];
                    }
                    for ($i = 1; $i <= 4; $i++) {
                        $configs['socials']['social'.$i]['title'] = $_POST['social'.$i.'-title'];
                        $configs['socials']['social'.$i]['link'] = $_POST['social'.$i.'-link'];
                    }

                    if (safe_file_put_contents($configsFile, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $response = ['success' => true, 'message' => 'تنظیمات ذخیره شد.', 'updated_images' => $updated_images];
                    } else throw new Exception('خطا در ذخیره فایل تنظیمات.');
                    break;
                
                case 'save_subtitles':
                    $newSubtitles = [];
                    if (isset($_POST['subtitle_text']) && is_array($_POST['subtitle_text'])) {
                        foreach ($_POST['subtitle_text'] as $index => $text) {
                            if (!empty(trim($text))) {
                                $newSubtitles[] = ['text' => trim($text), 'link' => trim($_POST['subtitle_link'][$index] ?? '')];
                            }
                        }
                    }
                    if (safe_file_put_contents($subtitlesFile, json_encode($newSubtitles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $response = ['success' => true, 'message' => 'زیرنویس‌ها ذخیره شدند.'];
                    } else throw new Exception('خطا در ذخیره فایل زیرنویس‌ها.');
                    break;

                case 'save_colors':
                    foreach($configs['colors'] as $key => $value) {
                        $post_key = str_replace('_', '-', $key) === 'title' ? 'title-color' : str_replace('_', '-', $key);
                        $configs['colors'][$key] = $_POST[$post_key] ?? $value;
                    }
                    if (safe_file_put_contents($configsFile, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $response = ['success' => true, 'message' => 'رنگ‌ها ذخیره شدند.'];
                    } else throw new Exception('خطا در ذخیره فایل تنظیمات رنگ.');
                    break;

                case 'restore_backup':
                    $target = $_POST['restore_target'] ?? '';
                    $file = $_FILES['backup_file'] ?? null;
                    $targetFile = ($target === 'configs') ? $configsFile : (($target === 'subtitles') ? $subtitlesFile : '');
                    if (empty($targetFile)) throw new Exception('هدف بازیابی مشخص نشده است.');

                    if ($file && $file['error'] === UPLOAD_ERR_OK) {
                        if ($file['type'] === 'application/json' && pathinfo($file['name'], PATHINFO_EXTENSION) === 'json') {
                            $content = file_get_contents($file['tmp_name']);
                            if (json_decode($content) !== null) {
                                if (safe_file_put_contents($targetFile, $content)) {
                                    $response = ['success' => true, 'message' => 'بازیابی موفق بود. صفحه رفرش می‌شود.'];
                                } else throw new Exception('خطا در ذخیره فایل بازیابی شده.');
                            } else throw new Exception('فایل JSON معتبر نیست.');
                        } else throw new Exception('فرمت فایل باید JSON باشد.');
                    } else throw new Exception('خطا در آپلود فایل پشتیبان.');
                    break;
                 
                default:
                     $response = ['success' => false, 'message' => 'عملیات ناشناخته است.'];
                     break;
            }
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    write_log('ERROR', "Action '{$action}': " . $e->getMessage());
}

echo json_encode($response);
exit;

