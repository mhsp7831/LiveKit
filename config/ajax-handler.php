<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// FIX 4: Correct require_once path
require_once __DIR__ . '/functions.php';

$response = ['success' => false, 'message' => 'درخواست نامعتبر است.'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $response['message'] = 'خطای امنیتی: درخواست نامعتبر است.';
        write_log('ERROR', 'CSRF token validation failed.');
        echo json_encode($response);
        exit;
    }
}

try {
    switch ($action) {
        case 'add_user':
            if (!is_owner()) throw new Exception('شما مجوز افزودن کاربر جدید را ندارید.');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            validate_username($username);
            if (empty($username) || empty($password)) throw new Exception('نام کاربری و رمز عبور نمی‌توانند خالی باشند.');
            if (strlen($password) < 6) throw new Exception('رمز عبور باید حداقل ۶ کاراکتر باشد.');
            if (get_user_by_username($username)) throw new Exception('این نام کاربری قبلا ثبت شده است.');

            if (add_user($username, $password)) {
                write_log('INFO', "Admin user '{$username}' created by '{$_SESSION['username']}'.");
                $response = ['success' => true, 'message' => 'کاربر با موفقیت اضافه شد.'];
            } else throw new Exception('خطا در افزودن کاربر.');
            break;

        case 'update_user':
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            validate_username($username);
            if (empty($userId) || empty($username)) throw new Exception('اطلاعات کاربر ناقص است.');
            if (!is_owner() && $userId != $_SESSION['user_id']) throw new Exception('شما فقط می‌توانید اطلاعات حساب کاربری خود را ویرایش کنید.');
            if (!empty($password) && strlen($password) < 6) throw new Exception('رمز عبور جدید باید حداقل ۶ کاراکتر باشد.');

            $existingUser = get_user_by_username($username);
            if ($existingUser && $existingUser['id'] != $userId) throw new Exception('این نام کاربری قبلا ثبت شده است.');

            if (update_user($userId, $username, $password)) {
                // Check if the user being updated is the same as the logged-in user
                if ($userId == $_SESSION['user_id']) {
                    // If so, update the username in the session immediately
                    $_SESSION['username'] = $username;
                }
                write_log('INFO', "User ID {$userId} ('{$username}') updated by '{$_SESSION['username']}'.");
                $response = ['success' => true, 'message' => 'کاربر با موفقیت ویرایش شد.'];
            } else throw new Exception('خطا در ویرایش کاربر.');
            break;

        case 'delete_user':
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
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
            } else throw new Exception('خطا در حذف کاربر.');
            break;

        case 'create_event':
            // FIX 1: Replace deprecated FILTER_SANITIZE_STRING
            $eventName = trim($_POST['event_name'] ?? '');
            if (empty($eventName)) throw new Exception('نام رویداد نمی‌تواند خالی باشد.');

            $events = get_events();
            $newEventId = 'event_' . uniqid();
            $events[] = ['id' => $newEventId, 'name' => $eventName];

            create_event_files($newEventId, $defaultConfigs);
            safe_file_put_contents(EVENTS_FILE, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['current_event_id'] = $newEventId;
            write_log('INFO', "Event created. ID: {$newEventId}, Name: {$eventName}");
            $response = ['success' => true, 'message' => 'رویداد جدید ساخته شد.', 'new_event' => ['id' => $newEventId, 'name' => $eventName]];
            break;

        case 'rename_event':
            $eventId = $_POST['event_id'] ?? '';
            // FIX 1: Replace deprecated FILTER_SANITIZE_STRING
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
            $response = ['success' => true, 'message' => 'نام رویداد تغییر کرد.', 'renamed_event' => ['id' => $eventId, 'name' => $newName]];
            break;

        // FIX 2: Implement edit_event_id logic
        case 'edit_event_id':
            $currentId = $_POST['current_event_id'] ?? '';
            $newId = trim($_POST['new_event_id'] ?? '');

            if (empty($newId)) throw new Exception('شناسه جدید ارسال نشده است.');
            if (!is_valid_event_id($currentId)) throw new Exception('رویداد فعلی نامعتبر است.');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $newId)) throw new Exception('شناسه جدید فقط می‌تواند شامل حروف انگلیسی، اعداد و آندرلاین (_) باشد.');
            if ($currentId === $newId) throw new Exception('شناسه جدید باید متفاوت از شناسه فعلی باشد.');

            $events = get_events();
            foreach ($events as $event) {
                if ($event['id'] === $newId) {
                    throw new Exception('این شناسه قبلاً برای رویداد دیگری استفاده شده است.');
                }
            }

            $oldPath = EVENTS_DIR . $currentId;
            $newPath = EVENTS_DIR . $newId;
            if (!is_dir($oldPath)) throw new Exception('پوشه رویداد فعلی یافت نشد.');
            if (is_dir($newPath)) throw new Exception('یک پوشه با شناسه جدید از قبل وجود دارد.');
            if (!rename($oldPath, $newPath)) throw new Exception('خطا در تغییر نام پوشه رویداد.');

            $oldUploadsPath = UPLOADS_DIR . $currentId;
            $newUploadsPath = UPLOADS_DIR . $newId;
            if (is_dir($oldUploadsPath)) {
                if (!rename($oldUploadsPath, $newUploadsPath)) {
                    rename($newPath, $oldPath); // Revert previous rename on failure
                    throw new Exception('خطا در تغییر نام پوشه آپلودها.');
                }
            }

            // Update paths inside configs.json
            $configsFile = $newPath . '/configs.json';
            if (file_exists($configsFile)) {
                $eventConfigs = json_decode(file_get_contents($configsFile), true);
                $oldUploadsRelativePath = 'config/uploads/' . $currentId . '/';
                $newUploadsRelativePath = 'config/uploads/' . $newId . '/';

                // Update main images
                $fileFields = ['logo', 'preBanner', 'endBanner', 'banner'];
                foreach ($fileFields as $field) {
                    if (isset($eventConfigs[$field]) && strpos($eventConfigs[$field], $oldUploadsRelativePath) === 0) {
                        $eventConfigs[$field] = str_replace($oldUploadsRelativePath, $newUploadsRelativePath, $eventConfigs[$field]);
                    }
                }
                // Update social icons
                if (isset($eventConfigs['socials']) && is_array($eventConfigs['socials'])) {
                    foreach ($eventConfigs['socials'] as &$social) {
                        if (isset($social['icon']) && strpos($social['icon'], $oldUploadsRelativePath) === 0) {
                            $social['icon'] = str_replace($oldUploadsRelativePath, $newUploadsRelativePath, $social['icon']);
                        }
                    }
                }
                safe_file_put_contents($configsFile, json_encode($eventConfigs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Update events.json
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
            foreach ([$dirPath, $uploadsPath] as $pathToDelete) {
                if (is_dir($pathToDelete)) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pathToDelete, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($files as $fileinfo) ($fileinfo->isDir() ? 'rmdir' : 'unlink')($fileinfo->getRealPath());
                    rmdir($pathToDelete);
                }
            }

            safe_file_put_contents(EVENTS_FILE, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $newCurrentEventId = $events[0]['id'] ?? null;
            $_SESSION['current_event_id'] = $newCurrentEventId;
            write_log('INFO', "Event deleted. ID: {$eventId}");
            $response = ['success' => true, 'message' => 'رویداد حذف شد.', 'deleted_event_id' => $eventId, 'new_current_event_id' => $newCurrentEventId];
            break;

        case 'switch_event':
            $eventId = $_POST['event_id'] ?? '';
            if (!is_valid_event_id($eventId)) throw new Exception('رویداد نامعتبر است.');
            $_SESSION['current_event_id'] = $eventId;
            $response = ['success' => true];
            break;
        
        // Add new cases for version management
        case 'get_versions':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است.');

            $versions = get_config_versions($current_event_id);
            $current_version = get_current_version($current_event_id);

            $response = [
                'success' => true,
                'versions' => $versions,
                'current_version' => $current_version
            ];
            break;

        case 'get_version_data':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است.');

            $version_number = filter_input(INPUT_POST, 'version_number', FILTER_VALIDATE_INT);
            if (!$version_number)
                throw new Exception('شماره نسخه نامعتبر است.');

            $version = get_config_version($current_event_id, $version_number);
            if (!$version)
                throw new Exception('نسخه یافت نشد.');

            $response = [
                'success' => true,
                'version' => [
                    'version_number' => $version['version_number'],
                    'changed_by' => $version['changed_by'],
                    'created_at' => $version['created_at'],
                    'description' => $version['description'],
                    'configs' => json_decode($version['configs_data'], true),
                    'subtitles' => json_decode($version['subtitles_data'], true),
                    'custom_css' => $version['custom_css']
                ]
            ];
            break;

        case 'restore_version':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است.');

            $version_number = filter_input(INPUT_POST, 'version_number', FILTER_VALIDATE_INT);
            if (!$version_number)
                throw new Exception('شماره نسخه نامعتبر است.');

            restore_config_version($current_event_id, $version_number);

            write_log('INFO', "Version {$version_number} restored for event {$current_event_id}");

            $response = [
                'success' => true,
                'message' => "نسخه #{$version_number} با موفقیت بازگردانی شد. صفحه رفرش می‌شود."
            ];
            break;

        case 'compare_versions':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است.');

            $version1 = filter_input(INPUT_POST, 'version1', FILTER_VALIDATE_INT);
            $version2 = filter_input(INPUT_POST, 'version2', FILTER_VALIDATE_INT);

            if (!$version1 || !$version2)
                throw new Exception('شماره نسخه‌ها نامعتبر است.');

            $v1 = get_config_version($current_event_id, $version1);
            $v2 = get_config_version($current_event_id, $version2);

            if (!$v1 || !$v2)
                throw new Exception('یکی از نسخه‌ها یافت نشد.');

            $response = [
                'success' => true,
                'version1' => [
                    'version_number' => $v1['version_number'],
                    'changed_by' => $v1['changed_by'],
                    'created_at' => $v1['created_at'],
                    'configs' => json_decode($v1['configs_data'], true),
                    'subtitles' => json_decode($v1['subtitles_data'], true),
                    'custom_css' => $v1['custom_css']
                ],
                'version2' => [
                    'version_number' => $v2['version_number'],
                    'changed_by' => $v2['changed_by'],
                    'created_at' => $v2['created_at'],
                    'configs' => json_decode($v2['configs_data'], true),
                    'subtitles' => json_decode($v2['subtitles_data'], true),
                    'custom_css' => $v2['custom_css']
                ]
            ];
            break;

        case 'upload_media':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است');

            if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('فایلی انتخاب نشده است');
            }

            $description = $_POST['description'] ?? '';
            $tags = $_POST['tags'] ?? '';

            $result = upload_to_media_library($_FILES['media_file'], $current_event_id, $description, $tags);

            write_log('INFO', "Media uploaded: {$result['filename']} to event {$current_event_id}");

            $response = [
                'success' => true,
                'message' => 'فایل با موفقیت آپلود شد',
                'media' => $result
            ];
            break;

        case 'get_media_library':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است');

            $filters = [
                'search' => $_POST['search'] ?? '',
                'mime_type' => $_POST['mime_type'] ?? '',
                'limit' => $_POST['limit'] ?? null
            ];

            $media = get_media_library($current_event_id, $filters);
            $stats = get_media_stats($current_event_id);

            $response = [
                'success' => true,
                'media' => $media,
                'stats' => $stats
            ];
            break;

        case 'delete_media':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است');

            $media_id = filter_input(INPUT_POST, 'media_id', FILTER_VALIDATE_INT);
            if (!$media_id)
                throw new Exception('شناسه فایل نامعتبر است');

            delete_media_file($media_id, $current_event_id);

            $response = [
                'success' => true,
                'message' => 'فایل با موفقیت حذف شد'
            ];
            break;

        case 'check_media_usage':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است');

            $filepath = $_POST['filepath'] ?? '';
            if (empty($filepath))
                throw new Exception('مسیر فایل ارسال نشده است');

            $usage = check_media_usage($filepath, $current_event_id);

            $response = [
                'success' => true,
                'usage' => $usage
            ];
            break;

        case 'cleanup_unused_media':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است');

            $deleted_count = cleanup_unused_media($current_event_id);

            $response = [
                'success' => true,
                'message' => "{$deleted_count} فایل استفاده نشده حذف شد",
                'deleted_count' => $deleted_count
            ];
            break;

        case 'update_media_info':
            if (empty($current_event_id))
                throw new Exception('رویداد انتخاب نشده است');

            $media_id = filter_input(INPUT_POST, 'media_id', FILTER_VALIDATE_INT);
            $description = $_POST['description'] ?? '';
            $tags = $_POST['tags'] ?? '';

            if (!$media_id)
                throw new Exception('شناسه فایل نامعتبر است');

            $db = get_db_connection();
            $stmt = $db->prepare("UPDATE media_library 
        SET description = :description, tags = :tags 
        WHERE id = :id AND event_id = :event_id");

            $stmt->execute([
                'id' => $media_id,
                'description' => $description,
                'tags' => $tags,
                'event_id' => $current_event_id
            ]);

            $response = [
                'success' => true,
                'message' => 'اطلاعات فایل به‌روزرسانی شد'
            ];
            break;
        default:
            if (empty($current_event_id) || !is_valid_event_id($current_event_id)) throw new Exception("هیچ رویداد معتبری انتخاب نشده است.");
            switch ($action) {
                case 'save_settings':
                    $upload_errors = [];

                    $file_fields = ['logo', 'preBanner', 'endBanner', 'banner'];
                    foreach ($file_fields as $field) {
                        $url_input = $_POST[$field . '_url'] ?? '';
                        if (!empty($url_input) && strpos($url_input, 'uploads/') === 0 && strpos($url_input, 'config/') !== 0) {
                            $url_input = 'config/' . $url_input;
                        }
                        if (!is_valid_image_url($url_input)) {
                            throw new Exception("آدرس تصویر برای '$field' نامعتبر است. آدرس باید به یک فایل تصویر ختم شود.");
                        }
                        $result = handle_upload($_FILES[$field . '_file'] ?? null, trim($url_input) ?? '', $_POST[$field . '_old'] ?? '');
                        $configs[$field] = $result['path'];
                        if ($result['error']) {
                            $upload_errors[] = $result['error'];
                        }
                    }
                    if (!empty($upload_errors)) {
                        throw new Exception(implode('<br>', $upload_errors));
                    }

                    $customCSS = $_POST['custom_css'] ?? '';

                    // Validate custom CSS
                    $dangerous_patterns = [
                        '/<script/i',
                        '/<\/script/i',
                        '/javascript:/i',
                        '/expression\(/i',
                        '/-moz-binding/i',
                        '/behavior:/i',
                        '/<iframe/i',
                        '/on(click|error|load|mouse|key)/i'
                    ];

                    foreach ($dangerous_patterns as $pattern) {
                        if (preg_match($pattern, $customCSS)) {
                            throw new Exception('کد CSS حاوی محتوای غیرمجاز است.');
                        }
                    }

                    // Save custom CSS
                    $customCSSFile = EVENTS_DIR . $current_event_id . '/custom.css';
                    safe_file_put_contents($customCSSFile, $customCSS);

                    $newButtons = [];
                    if (isset($_POST['button_title']) && is_array($_POST['button_title'])) {
                        foreach ($_POST['button_title'] as $index => $title) {
                            $link = trim($_POST['button_link'][$index]) ?? '';
                            if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
                                throw new Exception("لینک دکمه نامعتبر است: " . htmlspecialchars($link));
                            }
                            $newButtons[] = ['title' => trim($title), 'link' => $link];
                        }
                    }
                    $configs['buttons'] = $newButtons;

                    $old_social_icons = array_filter(array_column($configs['socials'] ?? [], 'icon'));

                    $newSocials = [];
                    if (isset($_POST['social_title']) && is_array($_POST['social_title'])) {
                        foreach ($_POST['social_title'] as $index => $title) {
                            $link = $_POST['social_link'][$index] ?? '';
                            if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
                                throw new Exception("لینک آیتم اجتماعی نامعتبر است: " . htmlspecialchars($link));
                            }

                            $icon_url_input = $_POST['social_icon_url'][$index] ?? '';
                            if (!empty($icon_url_input) && strpos($icon_url_input, 'uploads/') === 0 && strpos($icon_url_input, 'config/') !== 0) {
                                $icon_url_input = 'config/' . $icon_url_input;
                            }
                            if (!is_valid_image_url($icon_url_input)) {
                                throw new Exception("آدرس آیکن برای '$title' نامعتبر است. آدرس باید به یک فایل تصویر ختم شود.");
                            }

                            $icon_result = handle_upload(
                                !empty($_FILES['social_icon_file']['name'][$index]) ? [
                                    'name' => $_FILES['social_icon_file']['name'][$index],
                                    'type' => $_FILES['social_icon_file']['type'][$index],
                                    'tmp_name' => $_FILES['social_icon_file']['tmp_name'][$index],
                                    'error' => $_FILES['social_icon_file']['error'][$index],
                                    'size' => $_FILES['social_icon_file']['size'][$index],
                                ] : null,
                                $icon_url_input,
                                $_POST['social_icon_old'][$index] ?? ''
                            );
                            if ($icon_result['error']) {
                                $upload_errors[] = $icon_result['error'];
                            }

                            $newSocials[] = [
                                'title' => trim($title),
                                'link' => trim($link),
                                'icon' => $icon_result['path']
                            ];
                        }
                    }

                    // 2. Get a list of the icons that are still in use.
                    $kept_social_icons = array_filter(array_column($newSocials, 'icon'));

                    // 3. Find which icons were removed by comparing the old list to the new one.
                    $icons_to_delete = array_diff($old_social_icons, $kept_social_icons);

                    // 4. Securely delete the orphaned files.
                    foreach ($icons_to_delete as $icon_path) {
                        if (strpos($icon_path, 'config/uploads/') === 0) {
                            $physical_path = PROJECT_ROOT . '/' . $icon_path;
                            if (file_exists($physical_path)) {
                                @unlink($physical_path);
                            }
                        }
                    }

                    $configs['socials'] = $newSocials;


                    // FIX: Replace the existing 'homePage' line with this block for better validation
                    $homePageUrl = trim($_POST['homePage'] ?? '');
                    if (!empty($homePageUrl) && filter_var($homePageUrl, FILTER_VALIDATE_URL) === false) {
                        throw new Exception('لینک صفحه اصلی نامعتبر است.');
                    }
                    $configs['homePage'] = $homePageUrl;
                    $bannerLink = trim($_POST['bannerLink'] ?? '');
                    if (!empty($bannerLink) && filter_var($bannerLink, FILTER_VALIDATE_URL) === false) {
                        throw new Exception('لینک بنر نامعتبر است.');
                    }
                    $configs['bannerLink'] = $bannerLink;
                    $configs['title'] = trim($_POST['title'] ?? '');
                    $configs['iframe'] = trim($_POST['iframe'] ?? '');
                    $liveStart = trim($_POST['liveStart'] ?? '');
                    $liveEnd = trim($_POST['liveEnd'] ?? '');

                    // Only validate if both values have been provided
                    if (!empty($liveStart) && !empty($liveEnd)) {
                        if (strtotime($liveStart) >= strtotime($liveEnd)) {
                            throw new Exception('زمان شروع باید قبل از زمان پایان باشد.');
                        }
                    }

                    $configs['liveStart'] = $liveStart;
                    $configs['liveEnd'] = $liveEnd;
                    $configs['playerRevealOffset'] = filter_input(INPUT_POST, 'playerRevealOffset', FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) ?: 0;
                    $configs['fetchInterval'] = filter_input(INPUT_POST, 'fetchInterval', FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) ?: 8000;
                    $configs['scrollSpeed'] = filter_input(INPUT_POST, 'scrollSpeed', FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) ?: 50;
                    $configs['copyright'] = trim($_POST['copyright'] ?? '');

                    foreach ($configs['colors'] as $key => &$value) {
                        $post_key = str_replace('_', '-', $key) === 'title' ? 'title-color' : str_replace('_', '-', $key);
                        $color_val = $_POST[$post_key] ?? '#000000';
                        if (preg_match('/^#[a-f0-9]{6}$/i', $color_val)) {
                            $value = $color_val;
                        }
                    }

                    $newSubtitles = [];
                    if (isset($_POST['subtitle_text']) && is_array($_POST['subtitle_text'])) {
                        foreach ($_POST['subtitle_text'] as $index => $text) {
                            if (!empty(trim($text))) {
                                $link = trim($_POST['subtitle_link'][$index] ?? '');
                                if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
                                    throw new Exception("لینک زیرنویس نامعتبر است: " . htmlspecialchars($link));
                                }
                                $newSubtitles[] = ['text' => trim($text), 'link' => $link];
                            }
                        }
                    }

                    if (
                        safe_file_put_contents($configsFile, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) &&
                        safe_file_put_contents($subtitlesFile, json_encode($newSubtitles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                    ) {
                        $version_number = save_config_version($current_event_id, $configs, $newSubtitles, $customCSS, 'ذخیره تنظیمات');
                        $response = ['success' => true, 'message' => 'تنظیمات ذخیره شد.', 'updated_data' => ['configs' => $configs, 'subtitles' => $newSubtitles], 'version_number' => $version_number];
                    } else throw new Exception('خطا در ذخیره فایل‌ها.');
                    break;


                case 'restore_backup':
                    $target = $_POST['restore_target'] ?? '';
                    $file = $_FILES['backup_file'] ?? null;
                    $targetFile = ($target === 'configs') ? $configsFile : (($target === 'subtitles') ? $subtitlesFile : '');

                    if (empty($targetFile)) throw new Exception('هدف بازیابی مشخص نشده است.');

                    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('خطا در آپلود فایل پشتیبان.');
                    }

                    if ($file['type'] !== 'application/json' || pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
                        throw new Exception('فرمت فایل باید JSON باشد.');
                    }

                    $content = file_get_contents($file['tmp_name']);
                    $decoded_data = json_decode($content, true);

                    // FIX: Use the new, stronger validation function
                    if (is_valid_backup_content($decoded_data, $target)) {
                        $content_to_save = $content;

                        // For configs, merge with defaults to ensure all keys exist
                        if ($target === 'configs') {
                            global $defaultConfigs;
                            $merged_config = array_replace_recursive($defaultConfigs, $decoded_data);
                            $content_to_save = json_encode($merged_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        }

                        if (safe_file_put_contents($targetFile, $content_to_save)) {
                            $response = ['success' => true, 'message' => 'بازیابی موفق بود. صفحه رفرش می‌شود.'];
                        } else {
                            throw new Exception('خطا در ذخیره فایل بازیابی شده.');
                        }
                    } else {
                        throw new Exception('محتوای فایل پشتیبان نامعتبر است یا با نوع انتخابی مطابقت ندارد.');
                    }
                    break;
            }
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    write_log('ERROR', "Action '{$action}': " . $e->getMessage());
}

echo json_encode($response);
exit;
