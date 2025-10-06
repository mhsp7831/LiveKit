<?php
session_start();

// --- CONSTANTS & CONFIGS ---
// FIX 5: Assuming functions.php is inside a /config directory.
define('CONFIG_DIR', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__)); // Goes one level up from /config to the root
define('EVENTS_DIR', CONFIG_DIR . '/events/');
define('EVENTS_FILE', CONFIG_DIR . '/events.json');
define('UPLOADS_DIR', CONFIG_DIR . '/uploads/'); // Physical path remains /config/uploads/
define('LOG_FILE', CONFIG_DIR . '/app.log');
define('DB_FILE', CONFIG_DIR . '/live_database.sqlite');


// Ensure base directories exist
if (!is_dir(EVENTS_DIR)) mkdir(EVENTS_DIR, 0755, true);
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);

// Create .htaccess in uploads to prevent script execution
$htaccess_path = UPLOADS_DIR . '.htaccess';
if (!file_exists($htaccess_path)) {
    $htaccess_content = "Options -Indexes\n<FilesMatch \"\.(php|phtml|php3|php4|php5|php7|pl|py|cgi|asp|js)\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>";
    @file_put_contents($htaccess_path, $htaccess_content);
}

// --- DATABASE MANAGEMENT ---
function get_db_connection()
{
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'admin'
        )");

        $stmt = $db->query("SELECT COUNT(id) as count FROM users");
        $user_count = $stmt->fetchColumn();

        if ($user_count == 0) {
            $default_username = 'owner';
            $default_password = password_hash('123456', PASSWORD_DEFAULT);
            $default_role = 'owner';
            $insert_stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
            $insert_stmt->execute(['username' => $default_username, 'password' => $default_password, 'role' => $default_role]);
            write_log('INFO', 'Database created and default owner user was added.');
        }

        return $db;
    } catch (PDOException $e) {
        write_log('CRITICAL', 'Database connection failed: ' . $e->getMessage());
        die("Fatal Error: Could not connect to the database. Please check file permissions.");
    }
}

// --- USER MANAGEMENT & PERMISSIONS ---
function is_owner()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'owner';
}
function get_all_users()
{
    $db = get_db_connection();
    $stmt = $db->query("SELECT id, username, role FROM users ORDER BY role DESC, username ASC");
    return $stmt->fetchAll();
}
function add_user($username, $password)
{
    $db = get_db_connection();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
    return $stmt->execute(['username' => $username, 'password' => $hashed_password]);
}
function update_user($id, $username, $password = null)
{
    $db = get_db_connection();
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET username = :username, password = :password WHERE id = :id");
        return $stmt->execute(['id' => $id, 'username' => $username, 'password' => $hashed_password]);
    } else {
        $stmt = $db->prepare("UPDATE users SET username = :username WHERE id = :id");
        return $stmt->execute(['id' => $id, 'username' => $username]);
    }
}
function delete_user($id)
{
    $db = get_db_connection();
    $user_to_delete_stmt = $db->prepare("SELECT role FROM users WHERE id = :id");
    $user_to_delete_stmt->execute(['id' => $id]);
    $user_to_delete = $user_to_delete_stmt->fetch();
    if ($user_to_delete && $user_to_delete['role'] === 'owner') {
        $owner_count_stmt = $db->query("SELECT COUNT(id) FROM users WHERE role = 'owner'");
        if ($owner_count_stmt->fetchColumn() <= 1) {
            throw new Exception("شما نمی‌توانید آخرین کاربر Owner را حذف کنید.");
        }
    }
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    return $stmt->execute(['id' => $id]);
}
function get_user_by_username($username)
{
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    return $stmt->fetch();
}

// --- LOGGING ---
function write_log($level, $message)
{
    $message = trim(preg_replace('/\s+/', ' ', $message));
    $log_entry = sprintf(
        "[%s] [%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        $message
    );
    @file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

// --- DEFAULT CONFIGS (IMPROVED for dynamic lists) ---
$defaultConfigs = [
    "title" => "پخش زنده جدید",
    "homePage" => "",
    "iframe" => "",
    "liveStart" => "",
    "liveEnd" => "",
    "playerRevealOffset" => 0,
    "fetchInterval" => 8000,
    "scrollSpeed" => 50,
    "copyright" => "",
    "buttons" => [],
    "socials" => [],
    "logo" => "",
    "preBanner" => "",
    "endBanner" => "",
    "banner" => "",
    "bannerLink" => "",
    "colors" => ["bg" => "#ffffff", "title" => "#000000", "primary" => "#4caf50", "primary-hover" => "#45a049", "card-bg" => "#f8f9fa", "placeholder" => "#e9ecef", "placeholder-border" => "#ced4da", "text" => "#212529"],
];

// --- EVENT MANAGEMENT ---
function get_events()
{
    if (!file_exists(EVENTS_FILE)) return [];
    $json = file_get_contents(EVENTS_FILE);
    return json_decode($json, true) ?: [];
}
function is_valid_event_id($event_id)
{
    if (empty($event_id) || !preg_match('/^[a-zA-Z0-9_]+$/', $event_id)) {
        return false;
    }
    $events = get_events();
    foreach ($events as $event) {
        if ($event['id'] === $event_id) {
            return true;
        }
    }
    return false;
}
function safe_file_put_contents($filename, $data)
{
    return file_put_contents($filename, $data, LOCK_EX);
}
function create_event_files($event_id, $default_configs)
{
    $event_path = EVENTS_DIR . $event_id;
    if (!is_dir($event_path)) mkdir($event_path, 0755, true);
    safe_file_put_contents($event_path . '/configs.json', json_encode($default_configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    safe_file_put_contents($event_path . '/subtitles.json', json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// --- GLOBAL DATA LOADING FOR EVENTS ---
$all_events = get_events();
$current_event_id = $_SESSION['current_event_id'] ?? ($all_events[0]['id'] ?? null);
$event_exists = false;
if ($current_event_id) {
    foreach ($all_events as $event) {
        if ($event['id'] === $current_event_id) {
            $event_exists = true;
            break;
        }
    }
}
if (!$event_exists && !empty($all_events)) {
    $current_event_id = $all_events[0]['id'];
    $_SESSION['current_event_id'] = $current_event_id;
}
$configsFile = $current_event_id ? EVENTS_DIR . $current_event_id . '/configs.json' : null;
$subtitlesFile = $current_event_id ? EVENTS_DIR . $current_event_id . '/subtitles.json' : null;
$configs = ($configsFile && file_exists($configsFile)) ? json_decode(file_get_contents($configsFile), true) : $defaultConfigs;
$subtitles = ($subtitlesFile && file_exists($subtitlesFile)) ? json_decode(file_get_contents($subtitlesFile), true) : [];

// --- SECURITY & HELPERS ---
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validate_csrf_token()
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        return false;
    }
    return true;
}
if (basename($_SERVER['PHP_SELF']) === 'dashboard.php') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['username'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Handles file uploads with enhanced security.
 * - Sanitizes filenames, allowing Persian characters.
 * - Correctly deletes old files upon replacement.
 */
function handle_upload($file_input, $url_input, $old_file_path)
{
    global $current_event_id;
    $new_file_path = $url_input;
    $error_message = null;

    if (isset($file_input) && $file_input['error'] === UPLOAD_ERR_OK) {
        $file = $file_input;
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $max_size = 5 * 1024 * 1024;

        $original_name = $file['name'];
        $path_info = pathinfo($original_name);
        // FIX 2.1: Allow Persian characters in filename
        $base_name = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $path_info['filename']);
        $extension = strtolower($path_info['extension'] ?? '');

        if (preg_match('/\.(php|phtml|phar|pl|py|cgi|asp|js)\b/i', $original_name)) {
            return ['path' => $url_input, 'error' => 'پسوند فایل آپلودی غیرمجاز است.'];
        }
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            return ['path' => $url_input, 'error' => 'فرمت فایل آپلود شده مجاز نیست.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mimes)) {
            $error_message = 'نوع فایل شناسایی شده مجاز نیست.';
        } elseif ($file['size'] > $max_size) {
            $error_message = 'حجم فایل بیشتر از ۵ مگابایت است.';
        } else {
            $event_upload_dir_abs = UPLOADS_DIR . $current_event_id . '/';
            if (!is_dir($event_upload_dir_abs)) mkdir($event_upload_dir_abs, 0755, true);

            $sanitized_name = $base_name . '_' . uniqid() . '.' . $extension;
            $target_file_abs = $event_upload_dir_abs . $sanitized_name;

            if (move_uploaded_file($file['tmp_name'], $target_file_abs)) {
                // FIX 5: Use correct web-accessible path
                $new_file_path = 'config/uploads/' . $current_event_id . '/' . $sanitized_name;
            } else {
                $error_message = 'خطا در آپلود فایل.';
                $new_file_path = $url_input;
            }
        }
    }

    // Compare final path with old path for deletion
    if ($new_file_path !== $old_file_path && !empty($old_file_path) && strpos($old_file_path, 'config/uploads/') === 0) {
        // Construct physical path from web path
        $old_file_on_disk = PROJECT_ROOT . '/' . $old_file_path;
        if (file_exists($old_file_on_disk)) {
            @unlink($old_file_on_disk);
        }
    }

    return ['path' => $new_file_path, 'error' => $error_message];
}

// Download handler
if (isset($_GET['download']) && isset($_GET['event_id'])) {
    if (!is_valid_event_id($_GET['event_id'])) {
        header("HTTP/1.0 404 Not Found");
        exit('رویداد نامعتبر است.');
    }

    $event_id = $_GET['event_id'];
    $file_type = $_GET['download'];
    if ($file_type === 'configs') {
        $file_path = EVENTS_DIR . $event_id . '/configs.json';
        $file_name = $event_id . '_configs_backup.json';
    } elseif ($file_type === 'subtitles') {
        $file_path = EVENTS_DIR . $event_id . '/subtitles.json';
        $file_name = $event_id . '_subtitles_backup.json';
    } elseif ($file_type === 'uploads') {
        $event_uploads_dir = UPLOADS_DIR . $event_id;

        if (!is_dir($event_uploads_dir) || count(scandir($event_uploads_dir)) <= 2) {
            header("HTTP/1.0 404 Not Found");
            exit('
                <!DOCTYPE html>
                <html lang="fa" dir="rtl">
                  <head>
                    <meta charset="UTF-8">
                    <title>خطا</title>
                    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
                    <style>
                    body{font-family: "Vazirmatn", sans-serif;text-align:center;padding:40px;background:#fefefe;color:#333}
                    h1{color:#d9534f;}
                    </style>
                    </head>
                    <body>
                        <h1>خطا در دانلود رویداد</h1>
                      <p>هیچ فایل آپلودی برای این رویداد یافت نشد.</p>
                    </body>
                </html>
            ');
        }

        $zip_file_path = tempnam(sys_get_temp_dir(), 'event_uploads_') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            exit("خطا در ساخت فایل فشرده.");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($event_uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($event_uploads_dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $event_id . '_uploads_backup.zip"');
        header('Content-Length: ' . filesize($zip_file_path));

        readfile($zip_file_path);

        // Clean up the temporary zip file
        unlink($zip_file_path);
        exit;
    }

    if (isset($file_path) && file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}

function is_valid_image_url($url)
{
    $url = trim($url);

    if (empty($url)) {
        return true; // Optional fields are valid if empty
    }

    $isStructurallyValid = false;

    // First, check if the URL structure is valid.
    if (preg_match('/^https?:\/\//i', $url)) {
        // For absolute URLs, use the full validator.
        $isStructurallyValid = (filter_var($url, FILTER_VALIDATE_URL) !== false);
    } else {
        // For relative URLs, we assume the structure is valid.
        $isStructurallyValid = true;
    }

    if (!$isStructurallyValid) {
        return false;
    }

    // If the structure is valid, now check for a valid image extension.
    return preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $url);
}

function validate_username($username)
{
    if (strlen($username) < 3 || strlen($username) > 20) {
        throw new Exception('نام کاربری باید بین ۳ تا ۲۰ کاراکتر باشد.');
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        throw new Exception('نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد، خط تیره و آندرلاین باشد.');
    }
    return true;
}

function is_valid_backup_content($data, $type) {
    if ($data === null) {
        return false;
    }

    if ($type === 'configs') {
        return isset($data['title']) &&
               isset($data['colors']) && is_array($data['colors']) &&
               isset($data['buttons']) && is_array($data['buttons']) &&
               isset($data['socials']) && is_array($data['socials']);
    }

    if ($type === 'subtitles') {
        // A valid subtitles file must be a list-style array.
        // This check ensures it's not an associative array (like a config file).
        if (!is_array($data) || (count($data) > 0 && array_keys($data) !== range(0, count($data) - 1))) {
            return false;
        }
        
        // If the array isn't empty, check the structure of the first item.
        if (!empty($data) && (!is_array($data[0]) || !isset($data[0]['text']))) {
            return false;
        }
        
        return true;
    }

    return false;
}
 