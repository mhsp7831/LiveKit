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

        create_version_history_table($db);
        create_phone_validation_table($db);

        return $db;
    } catch (PDOException $e) {
        write_log('CRITICAL', 'Database connection failed: ' . $e->getMessage());
        die("Fatal Error: Could not connect to the database. Please check file permissions.");
    }
}

// Create version history table
function create_version_history_table($db) {
    
    $db->exec("CREATE TABLE IF NOT EXISTS config_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id TEXT NOT NULL,
        version_number INTEGER NOT NULL,
        configs_data TEXT NOT NULL,
        subtitles_data TEXT NOT NULL,
        custom_css TEXT,
        changed_by TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        description TEXT,
        UNIQUE(event_id, version_number)
    )");
    
    // Create index for faster queries
    $db->exec("CREATE INDEX IF NOT EXISTS idx_event_versions 
               ON config_versions(event_id, version_number DESC)");
}


function create_media_library_table() {
    $db = get_db_connection();
    
    $db->exec("CREATE TABLE IF NOT EXISTS media_library (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id TEXT NOT NULL,
        filename TEXT NOT NULL,
        original_name TEXT NOT NULL,
        filepath TEXT NOT NULL,
        filesize INTEGER NOT NULL,
        mime_type TEXT NOT NULL,
        width INTEGER,
        height INTEGER,
        uploaded_by TEXT NOT NULL,
        uploaded_at INTEGER NOT NULL,
        last_used_at INTEGER,
        usage_count INTEGER DEFAULT 0,
        tags TEXT,
        description TEXT
    )");
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_media_event ON media_library(event_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_media_filename ON media_library(filename)");
}

// Call in get_db_connection() after other tables
create_media_library_table();

/**
 * Get the base URL of the application (e.g., http://localhost/php/livekit)
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    
    // Assumes the project is directly inside the web root or a subdirectory.
    // This logic calculates the path from the document root to the project root.
    $project_root_path = str_replace('\\', '/', PROJECT_ROOT);
    $document_root_path = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    
    // Ensure document_root_path ends with a slash if it's not empty
    if ($document_root_path !== '' && substr($document_root_path, -1) !== '/') {
        $document_root_path .= '/';
    }

    // Calculate the base path by removing the document root from the project root
    if (strpos($project_root_path, $document_root_path) === 0) {
        $base_path = substr($project_root_path, strlen($document_root_path));
    } else {
        // Fallback if the project is not under the document root (e.g., symlink)
        // This might need manual configuration in a real-world scenario
        $base_path = ''; 
    }

    // Prepend a slash if base_path is not empty and doesn't start with one
    if ($base_path !== '' && $base_path[0] !== '/') {
        $base_path = '/' . $base_path;
    }

    return rtrim($protocol . $domain . $base_path, '/');
}

/**
 * Converts a full absolute URL into a project-relative path.
 * It removes the base URL from the beginning of the given link.
 *
 * @param string $full_url The full URL to convert (e.g., "https://example.com/myapp/css/style.css").
 * @return string The project-relative path (e.g., "/css/style.css"), or the original URL if it's not part of the project.
 */
function url_to_relative_path($full_url) {
    // Get the base URL of the current project.
    $base_url = get_base_url() . "/config";

    // Check if the provided URL starts with the base URL.
    // We use strpos() === 0 to ensure it's at the beginning.
    if (strpos($full_url, $base_url) === 0) {
        // If it matches, extract the part of the string after the base URL.
        $relative_path = substr($full_url, strlen($base_url));

        // Ensure the path always starts with a '/' for consistency.
        if (empty($relative_path) || $relative_path[0] === '/') {
            $relative_path = substr($relative_path, 1);
        }

        return $relative_path;
    }

    // If the URL is not part of this project, return it unchanged.
    return $full_url;
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
                // Use absolute URL
                $new_file_path = get_base_url() . '/config/uploads/' . $current_event_id . '/' . $sanitized_name;
            } else {
                $error_message = 'خطا در آپلود فایل.';
                $new_file_path = $url_input;
            }
        }
    }

    // Compare final path with old path for deletion
    if ($new_file_path !== $old_file_path && !empty($old_file_path)) {
        $base_url = get_base_url();
        // Check if it's a local file managed by the app
        if (strpos($old_file_path, $base_url . '/config/uploads/') === 0) {
            // Construct physical path from web path
            $relative_path = substr($old_file_path, strlen($base_url) + 1); // +1 for the leading slash
            $old_file_on_disk = PROJECT_ROOT . '/' . $relative_path;
            if (file_exists($old_file_on_disk)) {
                @unlink($old_file_on_disk);
            }
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
    } elseif ($file_type === 'phones') {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT phone_number FROM authorized_phones WHERE event_id = :event_id ORDER BY phone_number ASC");
        $stmt->execute(['event_id' => $event_id]);

        $filename = $event_id . '_phones_backup.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add header row
        fputcsv($output, ['phone_number']);

        // Add data rows
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['phone_number']]);
        }

        fclose($output);
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
        // For absolute URLs, we need to handle non-ASCII characters in the path.
        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $path = $parts['path'] ?? '';
        // URL-encode each part of the path individually, but don't encode slashes
        $path_parts = explode('/', $path);
        $encoded_path_parts = array_map('rawurlencode', $path_parts);
        $encoded_path = implode('/', $encoded_path_parts);
        
        $rebuilt_url = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $rebuilt_url .= ':' . $parts['port'];
        }
        $rebuilt_url .= $encoded_path;
        if (isset($parts['query'])) {
            $rebuilt_url .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $rebuilt_url .= '#' . $parts['fragment'];
        }

        $isStructurallyValid = (filter_var($rebuilt_url, FILTER_VALIDATE_URL) !== false);
    } else {
        // For relative URLs, we assume the structure is valid.
        $isStructurallyValid = true;
    }

    if (!$isStructurallyValid) {
        return false;
    }

    // If the structure is valid, now check for a valid image extension on the original URL.
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


/**
 * Save a new version of event configuration
 */
function save_config_version($event_id, $configs, $subtitles, $custom_css = '', $description = '') {
    $db = get_db_connection();
    
    // Get current max version number
    $stmt = $db->prepare("SELECT MAX(version_number) as max_ver FROM config_versions WHERE event_id = :event_id");
    $stmt->execute(['event_id' => $event_id]);
    $result = $stmt->fetch();
    $next_version = ($result['max_ver'] ?? 0) + 1;
    
    // Insert new version
    $stmt = $db->prepare("INSERT INTO config_versions 
        (event_id, version_number, configs_data, subtitles_data, custom_css, changed_by, created_at, description) 
        VALUES (:event_id, :version_number, :configs_data, :subtitles_data, :custom_css, :changed_by, :created_at, :description)");
    
    $stmt->execute([
        'event_id' => $event_id,
        'version_number' => $next_version,
        'configs_data' => json_encode($configs, JSON_UNESCAPED_UNICODE),
        'subtitles_data' => json_encode($subtitles, JSON_UNESCAPED_UNICODE),
        'custom_css' => $custom_css,
        'changed_by' => $_SESSION['username'] ?? 'system',
        'created_at' => time(),
        'description' => $description
    ]);
    
    // Keep only last 10 versions
    cleanup_old_versions($event_id);
    
    return $next_version;
}

/**
 * Keep only the last 10 versions per event
 */
function cleanup_old_versions($event_id, $keep_count = 10) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("DELETE FROM config_versions 
        WHERE event_id = :event_id 
        AND version_number NOT IN (
            SELECT version_number FROM config_versions 
            WHERE event_id = :event_id 
            ORDER BY version_number DESC 
            LIMIT :keep_count
        )");
    
    $stmt->execute([
        'event_id' => $event_id,
        'keep_count' => $keep_count
    ]);
}

/**
 * Get all versions for an event
 */
function get_config_versions($event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT id, version_number, changed_by, created_at, description 
        FROM config_versions 
        WHERE event_id = :event_id 
        ORDER BY version_number DESC");
    
    $stmt->execute(['event_id' => $event_id]);
    return $stmt->fetchAll();
}

/**
 * Get a specific version
 */
function get_config_version($event_id, $version_number) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT * FROM config_versions 
        WHERE event_id = :event_id AND version_number = :version_number");
    
    $stmt->execute([
        'event_id' => $event_id,
        'version_number' => $version_number
    ]);
    
    return $stmt->fetch();
}

/**
 * Restore a specific version
 */
function restore_config_version($event_id, $version_number) {
    $version = get_config_version($event_id, $version_number);
    
    if (!$version) {
        throw new Exception('نسخه مورد نظر یافت نشد.');
    }
    
    // Decode data
    $configs = json_decode($version['configs_data'], true);
    $subtitles = json_decode($version['subtitles_data'], true);
    $custom_css = $version['custom_css'];
    
    // Save to files
    $event_path = EVENTS_DIR . $event_id;
    safe_file_put_contents($event_path . '/configs.json', json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    safe_file_put_contents($event_path . '/subtitles.json', json_encode($subtitles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    safe_file_put_contents($event_path . '/custom.css', $custom_css);
    
    // Save as new version with description
    save_config_version($event_id, $configs, $subtitles, $custom_css, "بازگردانی از نسخه #{$version_number}");
    
    return true;
}

/**
 * Get current version number
 */
function get_current_version($event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT MAX(version_number) as current_ver FROM config_versions WHERE event_id = :event_id");
    $stmt->execute(['event_id' => $event_id]);
    $result = $stmt->fetch();
    
    return $result['current_ver'] ?? 0;
}

/**
 * Upload file to media library
 */
function upload_to_media_library($file, $event_id, $description = '', $tags = '') {
    // Validate file
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در آپلود فایل');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_mimes)) {
        throw new Exception('فرمت فایل مجاز نیست');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('حجم فایل بیش از 5 مگابایت است');
    }
    
    // Get image dimensions
    $dimensions = @getimagesize($file['tmp_name']);
    $width = $dimensions[0] ?? null;
    $height = $dimensions[1] ?? null;
    
    // Generate unique filename
    $path_info = pathinfo($file['name']);
    $extension = strtolower($path_info['extension']);
    $unique_name = uniqid() . '_' . preg_replace('/[^\p{L}\p{N}_-]/u', '_', $path_info['filename']) . '.' . $extension;
    
    // Create event-specific media directory
    $media_dir = UPLOADS_DIR . $event_id . '/media/';
    if (!is_dir($media_dir)) {
        mkdir($media_dir, 0755, true);
    }
    
    $target_path = $media_dir . $unique_name;
    $relative_web_path = 'config/uploads/' . $event_id . '/media/' . $unique_name;
    $web_path = get_base_url() . '/' . $relative_web_path;
    
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    
    // Save to database
    $db = get_db_connection();
    $stmt = $db->prepare("INSERT INTO media_library 
        (event_id, filename, original_name, filepath, filesize, mime_type, width, height, uploaded_by, uploaded_at, tags, description) 
        VALUES (:event_id, :filename, :original_name, :filepath, :filesize, :mime_type, :width, :height, :uploaded_by, :uploaded_at, :tags, :description)");
    
    $stmt->execute([
        'event_id' => $event_id,
        'filename' => $unique_name,
        'original_name' => $file['name'],
        'filepath' => $web_path, // Store the full URL
        'filesize' => $file['size'],
        'mime_type' => $mime_type,
        'width' => $width,
        'height' => $height,
        'uploaded_by' => $_SESSION['username'] ?? 'unknown',
        'uploaded_at' => time(),
        'tags' => $tags,
        'description' => $description
    ]);
    
    return [
        'id' => $db->lastInsertId(),
        'filepath' => $web_path,
        'filename' => $unique_name,
        'filesize' => $file['size'],
        'width' => $width,
        'height' => $height
    ];
}

/**
 * Verify file exists before loading media library
 */
function get_media_library($event_id, $filters = []) {
    // First sync to remove orphaned entries
    sync_media_library($event_id);
    
    $db = get_db_connection();
    
    $sql = "SELECT * FROM media_library WHERE event_id = :event_id";
    $params = ['event_id' => $event_id];
    
    // Add filters
    if (!empty($filters['search'])) {
        $sql .= " AND (original_name LIKE :search OR description LIKE :search OR tags LIKE :search)";
        $params['search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['mime_type'])) {
        $sql .= " AND mime_type = :mime_type";
        $params['mime_type'] = $filters['mime_type'];
    }
    
    $sql .= " ORDER BY uploaded_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT :limit";
    }
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    if (!empty($filters['limit'])) {
        $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
    }
    
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Delete media file
 */
function delete_media_file($media_id, $event_id) {
    $db = get_db_connection();
    
    // Get media info
    $stmt = $db->prepare("SELECT * FROM media_library WHERE id = :id AND event_id = :event_id");
    $stmt->execute(['id' => $media_id, 'event_id' => $event_id]);
    $media = $stmt->fetch();
    
    if (!$media) {
        throw new Exception('فایل یافت نشد');
    }
    
    // Check if file is in use
    $usage = check_media_usage($media['filepath'], $event_id);
    if ($usage['in_use']) {
        throw new Exception('این فایل در حال استفاده است و نمی‌تواند حذف شود: ' . implode(', ', $usage['locations']));
    }
    
    // Delete physical file
    $base_url = get_base_url();
    $physical_path = '';

    // Convert URL to physical path
    if (strpos($media['filepath'], $base_url) === 0) {
        $relative_path = substr($media['filepath'], strlen($base_url) + 1);
        $physical_path = PROJECT_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    }

    if (!empty($physical_path) && file_exists($physical_path)) {
        @unlink($physical_path);
    } else {
        // Log if file not found, but proceed to delete DB record
        write_log('WARNING', "Media file not found on disk for deletion: {$physical_path}");
    }
    
    // Delete from database
    $stmt = $db->prepare("DELETE FROM media_library WHERE id = :id");
    $stmt->execute(['id' => $media_id]);
    
    write_log('INFO', "Media file deleted: {$media['filename']} from event {$event_id}");
    
    return true;
}

/**
 * Check if media file is currently in use
 */
function check_media_usage($filepath, $event_id) {
    $configsFile = EVENTS_DIR . $event_id . '/configs.json';
    
    if (!file_exists($configsFile)) {
        return ['in_use' => false, 'locations' => []];
    }
    
    $configs = json_decode(file_get_contents($configsFile), true);
    $locations = [];
    
    // Check main images
    $fields = ['logo' => 'لوگو', 'preBanner' => 'بنر قبل از پخش‌زنده', 'endBanner' => 'بنر بعد از پخش‌زنده', 'banner' => 'بنر زیر پخش‌زنده'];
    foreach ($fields as $key => $label) {
        if (isset($configs[$key]) && $configs[$key] === $filepath) {
            $locations[] = $label;
        }
    }
    
    // Check social icons
    if (isset($configs['socials']) && is_array($configs['socials'])) {
        foreach ($configs['socials'] as $index => $social) {
            if (isset($social['icon']) && $social['icon'] === $filepath) {
                $locations[] = "آیکون شبکه اجتماعی: {$social['title']}";
            }
        }
    }
    
    return [
        'in_use' => !empty($locations),
        'locations' => $locations
    ];
}

/**
 * Update media usage stats
 */
function update_media_usage($filepath, $event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE media_library 
        SET last_used_at = :time, usage_count = usage_count + 1 
        WHERE filepath = :filepath AND event_id = :event_id");
    
    $stmt->execute([
        'time' => time(),
        'filepath' => $filepath,
        'event_id' => $event_id
    ]);
}

/**
 * Get media library statistics
 */
function get_media_stats($event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_files,
        SUM(filesize) as total_size,
        COUNT(CASE WHEN mime_type LIKE 'image/jpeg%' THEN 1 END) as jpeg_count,
        COUNT(CASE WHEN mime_type LIKE 'image/png%' THEN 1 END) as png_count,
        COUNT(CASE WHEN mime_type LIKE 'image/gif%' THEN 1 END) as gif_count,
        COUNT(CASE WHEN mime_type LIKE 'image/webp%' THEN 1 END) as webp_count,
        COUNT(CASE WHEN mime_type LIKE 'image/svg%' THEN 1 END) as svg_count
        FROM media_library WHERE event_id = :event_id");
    
    $stmt->execute(['event_id' => $event_id]);
    
    return $stmt->fetch();
}

/**
 * Bulk delete unused media
 */
function cleanup_unused_media($event_id) {
    $media_files = get_media_library($event_id);
    $deleted_count = 0;
    
    foreach ($media_files as $media) {
        $usage = check_media_usage($media['filepath'], $event_id);
        if (!$usage['in_use']) {
            try {
                delete_media_file($media['id'], $event_id);
                $deleted_count++;
            } catch (Exception $e) {
                // Continue with other files
            }
        }
    }
    
    return $deleted_count;
}

/**
 * Sync media library with actual files - remove orphaned database entries
 */
function sync_media_library($event_id) {
    $db = get_db_connection();
    
    // Get all media from database
    $stmt = $db->prepare("SELECT id, filepath FROM media_library WHERE event_id = :event_id");
    $stmt->execute(['event_id' => $event_id]);
    $media_files = $stmt->fetchAll();
    
    $removed_count = 0;
    
    foreach ($media_files as $media) {
        $physical_path = url_to_relative_path($media['filepath']);
        
        
        // If file doesn't exist on disk, remove from database
        if (!file_exists($physical_path)) {
            $delete_stmt = $db->prepare("DELETE FROM media_library WHERE id = :id");
            $delete_stmt->execute(['id' => $media['id']]);
            $removed_count++;
            
            write_log('WARNING', "Orphaned media entry removed: {$media['filepath']}");
        }
    }
    
    return $removed_count;
}

// Add to functions.php - call this when loading dashboard

/**
 * Perform database maintenance tasks
 */
function perform_database_maintenance() {
    $db = get_db_connection();
    
    // Get all events
    $events = get_events();
    $event_ids = array_column($events, 'id');
    
    if (empty($event_ids)) {
        return;
    }
    
    // Clean up media for non-existent events
    $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
    $stmt = $db->prepare("DELETE FROM media_library WHERE event_id NOT IN ($placeholders)");
    $stmt->execute($event_ids);
    
    // Clean up versions for non-existent events
    $stmt = $db->prepare("DELETE FROM config_versions WHERE event_id NOT IN ($placeholders)");
    $stmt->execute($event_ids);
    
    // Sync each event's media library
    foreach ($event_ids as $event_id) {
        sync_media_library($event_id);
    }
    
    write_log('INFO', 'Database maintenance completed');
}

// Update functions.php - remove csv_filename from schema
function create_phone_validation_table($db) {
    
    $db->exec("CREATE TABLE IF NOT EXISTS phone_validation (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id TEXT NOT NULL UNIQUE,
        enabled INTEGER DEFAULT 0,
        csv_uploaded_at INTEGER,
        total_numbers INTEGER DEFAULT 0,
        last_updated_by TEXT
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS authorized_phones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id TEXT NOT NULL,
        phone_number TEXT NOT NULL,
        added_at INTEGER NOT NULL,
        UNIQUE(event_id, phone_number)
    )");
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_phone_event ON authorized_phones(event_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_phone_number ON authorized_phones(event_id, phone_number)");

    try {
        // Check if the 'source_type' column exists. If not, alter the table.
        $db->query("SELECT source_type FROM phone_validation LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, so add it and the other WP fields
        $db->exec("ALTER TABLE phone_validation ADD COLUMN source_type TEXT DEFAULT 'csv'");
        $db->exec("ALTER TABLE phone_validation ADD COLUMN wp_api_url TEXT");
        $db->exec("ALTER TABLE phone_validation ADD COLUMN wp_api_key TEXT");
        $db->exec("ALTER TABLE phone_validation ADD COLUMN wp_form_id TEXT");
        $db->exec("ALTER TABLE phone_validation ADD COLUMN wp_field_id TEXT");
        $db->exec("ALTER TABLE phone_validation ADD COLUMN last_test_at INTEGER");
        $db->exec("ALTER TABLE phone_validation ADD COLUMN last_test_status TEXT");
        write_log('INFO', 'Database table "phone_validation" updated for WordPress integration.');
    }
}


/**
 * Get phone validation settings for an event
 */
function get_phone_validation_settings($event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT * FROM phone_validation WHERE event_id = :event_id");
    $stmt->execute(['event_id' => $event_id]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Create default settings
        $stmt = $db->prepare("INSERT INTO phone_validation (event_id, enabled, source_type) VALUES (:event_id, 0, 'csv')");
        $stmt->execute(['event_id' => $event_id]);
        
        return [
            'event_id' => $event_id,
            'enabled' => 0,
            'csv_uploaded_at' => null,
            'total_numbers' => 0,
            'last_updated_by' => null,
            'source_type' => 'csv' // Add default here too
        ];
    }
    
    // FIX: Ensure source_type is not null for existing entries
    if (empty($settings['source_type'])) {
        $settings['source_type'] = 'csv';
    }
    
    return $settings;
}

/**
 * Update phone validation settings
 */
function update_phone_validation_settings($event_id, $enabled) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE phone_validation 
        SET enabled = :enabled, last_updated_by = :updated_by 
        WHERE event_id = :event_id");
    
    $stmt->execute([
        'enabled' => $enabled ? 1 : 0,
        'updated_by' => $_SESSION['username'] ?? 'unknown',
        'event_id' => $event_id
    ]);
    
    write_log('INFO', "Phone validation " . ($enabled ? 'enabled' : 'disabled') . " for event {$event_id}");
}

// Update in functions.php

/**
 * Process and import CSV phone numbers
 */
/**
 * Process and import CSV phone numbers
 */
function import_phone_numbers_csv($file, $event_id) {
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در آپلود فایل');
    }
    
    $allowed_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Also check extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($mime_type, $allowed_types) && $extension !== 'csv') {
        throw new Exception('فرمت فایل باید CSV باشد');
    }
    
    // Max file size: 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('حجم فایل نباید بیشتر از 5 مگابایت باشد');
    }
    
    // Read and parse CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('خطا در خواندن فایل');
    }
    
    $phone_numbers = [];
    $line_number = 0;
    $invalid_count = 0;
    
    while (($line = fgets($handle)) !== false) {
        $line_number++;
        $phone = trim($line);
        
        // Skip empty lines
        if (empty($phone)) {
            continue;
        }
        
        // Normalize phone number (remove spaces, dashes, etc.)
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Validate phone number format
        if (!preg_match('/^(\+98|0)?9\d{9}$/', $phone)) {
            write_log('WARNING', "Invalid phone number on line {$line_number}: {$phone}");
            $invalid_count++;
            continue; // Skip invalid numbers
        }
        
        // Normalize to standard format (09xxxxxxxxx)
        if (preg_match('/^\+989(\d{9})$/', $phone, $matches)) {
            $phone = '09' . $matches[1];
        } elseif (preg_match('/^9(\d{9})$/', $phone, $matches)) {
            $phone = '09' . $matches[1];
        }
        
        $phone_numbers[] = $phone;
    }
    
    fclose($handle);
    
    if (empty($phone_numbers)) {
        throw new Exception('هیچ شماره تلفن معتبری در فایل یافت نشد');
    }
    
    // Remove duplicates
    $phone_numbers = array_unique($phone_numbers);
    
    // Update database
    $db = get_db_connection();
    
    // Start transaction for atomicity
    $db->beginTransaction();
    
    try {
        // Clear existing phone numbers for this event
        $stmt = $db->prepare("DELETE FROM authorized_phones WHERE event_id = :event_id");
        $stmt->execute(['event_id' => $event_id]);
        
        // Insert new phone numbers
        $stmt = $db->prepare("INSERT INTO authorized_phones (event_id, phone_number, added_at) 
            VALUES (:event_id, :phone_number, :added_at)");
        
        foreach ($phone_numbers as $phone) {
            $stmt->execute([
                'event_id' => $event_id,
                'phone_number' => $phone,
                'added_at' => time()
            ]);
        }
        
        // Update validation settings
        $stmt = $db->prepare("UPDATE phone_validation 
            SET csv_uploaded_at = :uploaded_at, total_numbers = :total, last_updated_by = :updated_by 
            WHERE event_id = :event_id");
        
        $stmt->execute([
            'uploaded_at' => time(),
            'total' => count($phone_numbers),
            'updated_by' => $_SESSION['username'] ?? 'unknown',
            'event_id' => $event_id
        ]);
        
        $db->commit();
        
        write_log('INFO', "Imported " . count($phone_numbers) . " phone numbers for event {$event_id}" . 
                  ($invalid_count > 0 ? " ({$invalid_count} invalid numbers skipped)" : ""));
        
        return [
            'total' => count($phone_numbers),
            'invalid_count' => $invalid_count
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('خطا در ذخیره اطلاعات: ' . $e->getMessage());
    }
}

/**
 * Check if phone number is authorized
 */
function is_phone_authorized($event_id, $phone_number) {
    // Normalize phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone_number);
    
    // Convert to standard format
    if (preg_match('/^\+989(\d{9})$/', $phone, $matches)) {
        $phone = '09' . $matches[1];
    } elseif (preg_match('/^9(\d{9})$/', $phone, $matches)) {
        $phone = '09' . $matches[1];
    }
    
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM authorized_phones 
        WHERE event_id = :event_id AND phone_number = :phone_number");
    
    $stmt->execute([
        'event_id' => $event_id,
        'phone_number' => $phone
    ]);
    
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Saves the WordPress integration settings for an event.
 */
function save_wp_validation_settings($event_id, $settings) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE phone_validation 
        SET 
            source_type = 'wordpress',
            wp_api_url = :wp_api_url,
            wp_api_key = :wp_api_key,
            wp_form_id = :wp_form_id,
            wp_field_id = :wp_field_id,
            last_updated_by = :last_updated_by
        WHERE event_id = :event_id");
        
    $stmt->execute([
        ':wp_api_url' => $settings['wp_api_url'],
        ':wp_api_key' => $settings['wp_api_key'],
        ':wp_form_id' => $settings['wp_form_id'],
        ':wp_field_id' => $settings['wp_field_id'],
        ':last_updated_by' => $_SESSION['username'] ?? 'unknown',
        ':event_id' => $event_id
    ]);
    
    write_log('INFO', "WordPress validation settings saved for event {$event_id}");
    return true;
}

/**
 * Updates the source type (e.g., 'csv' or 'wordpress') for an event.
 */
function update_phone_validation_source($event_id, $source_type) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE phone_validation 
        SET 
            source_type = :source_type,
            last_updated_by = :last_updated_by
        WHERE event_id = :event_id");
        
    $stmt->execute([
        ':source_type' => $source_type,
        ':last_updated_by' => $_SESSION['username'] ?? 'unknown',
        ':event_id' => $event_id
    ]);
    
    write_log('INFO', "Phone validation source changed to '{$source_type}' for event {$event_id}");
    return true;
}


/**
 * Helper function to perform cURL requests to WordPress.
 */
function call_wordpress_api($event_id, $endpoint, $method = 'GET', $data = []) {
    $settings = get_phone_validation_settings($event_id);
    if (empty($settings['wp_api_url']) || empty($settings['wp_api_key'])) {
        throw new Exception('اطلاعات اتصال WordPress API (URL or Key) ثبت نشده است.');
    }
    
    $api_url = rtrim($settings['wp_api_url'], '/');
    
    // FIX: Automatically append /wp-json if it's missing from the base URL
    if (strpos($api_url, '/wp-json') === false) {
        $api_url .= '/wp-json';
    }
    
    $api_url .= $endpoint;

    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'X-API-Key: ' . $settings['wp_api_key']
    ];

    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enforce SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('خطای cURL در اتصال به WordPress: ' . $error);
    }
    
    curl_close($ch);
    
    $body = json_decode($response, true);
    
    if ($http_code >= 400) {
        // Log the full response for debugging
        write_log('ERROR', "WP API Error ($http_code) at $api_url: " . $response);
        throw new Exception('WordPress API Error (' . $http_code . '): ' . ($body['message'] ?? $response));
    }
    
    return $body;
}

/**
 * Tests the connection to the WordPress API.
 */
function test_wordpress_connection($event_id) {
    $db = get_db_connection();
    $status_text = 'خطا';
    $message = '';
    
    try {
        $response = call_wordpress_api($event_id, '/test-connection', 'GET');
        
        if (isset($response['success']) && $response['success'] === true) {
            $status_text = 'موفق';
            $message = $response['message'] ?? 'اتصال با موفقیت برقرار شد.';
        } else {
            $message = 'پاسخ نامعتبر از سرور WordPress دریافت شد.';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
    
    // Save test result to database
    $stmt = $db->prepare("UPDATE phone_validation 
        SET 
            last_test_at = :time,
            last_test_status = :status_message
        WHERE event_id = :event_id");
    
    $stmt->execute([
        ':time' => time(),
        ':status_message' => $status_text . ': ' . $message,
        ':event_id' => $event_id
    ]);
    
    if ($status_text === 'موفق') {
        return ['success' => true, 'message' => $message];
    } else {
        throw new Exception($message);
    }
}

/**
 * Verifies a phone number against the WordPress API.
 */
function verify_phone_wordpress($event_id, $phone_number) {
    try {
        $settings = get_phone_validation_settings($event_id);
        
        $data = [
            'phone' => $phone_number,
            'form_id' => $settings['wp_form_id'],
            'field_id' => $settings['wp_field_id']
        ];
        
        $response = call_wordpress_api($event_id, '/verify-phone', 'POST', $data);
        
        if (isset($response['authorized']) && $response['authorized'] === true) {
            return true;
        } else {
            write_log('INFO', "WP API Denied. Phone: {$phone_number}, Event: {$event_id}, Reason: " . ($response['message'] ?? 'Not found'));
            return false;
        }
    } catch (Exception $e) {
        write_log('ERROR', 'WP API Verification Failed. Event: {$event_id}, Error: ' . $e->getMessage());
        return false; // Fail-closed. If API fails, deny access.
    }
}

/**
 * Get phone validation statistics
 */
function get_phone_validation_stats($event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM authorized_phones WHERE event_id = :event_id");
    $stmt->execute(['event_id' => $event_id]);
    $result = $stmt->fetch();
    
    return [
        'total_numbers' => $result['total']
    ];
}

/**
 * Delete phone validation data when event is deleted
 */
function delete_phone_validation_data($event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("DELETE FROM phone_validation WHERE event_id = :event_id");
    $stmt->execute(['event_id' => $event_id]);
    
    $stmt = $db->prepare("DELETE FROM authorized_phones WHERE event_id = :event_id");
    $stmt->execute(['event_id' => $event_id]);
    
    // REMOVED: CSV file deletion since we don't save files anymore
}

/**
 * Update event_id in phone validation when event ID changes
 */
function update_phone_validation_event_id($old_event_id, $new_event_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE phone_validation SET event_id = :new_id WHERE event_id = :old_id");
    $stmt->execute(['new_id' => $new_event_id, 'old_id' => $old_event_id]);
    
    $stmt = $db->prepare("UPDATE authorized_phones SET event_id = :new_id WHERE event_id = :old_id");
    $stmt->execute(['new_id' => $new_event_id, 'old_id' => $old_event_id]);
}