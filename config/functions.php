<?php
session_start();

// --- CONSTANTS & CONFIGS ---
define('CONFIG_DIR', __DIR__);
define('EVENTS_DIR', CONFIG_DIR . '/events/');
define('EVENTS_FILE', CONFIG_DIR . '/events.json');
define('UPLOADS_DIR', CONFIG_DIR . '/uploads/');
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
/**
 * Establishes a connection to the SQLite database.
 * Creates the database and users table if they don't exist.
 * @return PDO The database connection object.
 */
function get_db_connection() {
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create the users table with a 'role' column if it doesn't exist
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'admin'
        )");

        // Check if any user exists, if not, create the default 'owner'
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
        // In a real application, you might show a user-friendly error page
        die("Fatal Error: Could not connect to the database. Please check file permissions.");
    }
}

// --- USER MANAGEMENT & PERMISSIONS ---

/**
 * Checks if the currently logged-in user has the 'owner' role.
 * @return bool True if the user is an owner, false otherwise.
 */
function is_owner() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'owner';
}

/**
 * Retrieves all users from the database.
 * @return array A list of all users.
 */
function get_all_users() {
    $db = get_db_connection();
    $stmt = $db->query("SELECT id, username, role FROM users ORDER BY role DESC, username ASC");
    return $stmt->fetchAll();
}

/**
 * Adds a new user to the database with the default 'admin' role.
 * @param string $username The username.
 * @param string $password The plain-text password.
 * @return bool True on success, false on failure.
 */
function add_user($username, $password) {
    $db = get_db_connection();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
    return $stmt->execute(['username' => $username, 'password' => $hashed_password]);
}

/**
 * Updates a user's information.
 * @param int $id The user's ID.
 * @param string $username The new username.
 * @param string|null $password The new plain-text password (optional).
 * @return bool True on success, false on failure.
 */
function update_user($id, $username, $password = null) {
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

/**
 * Deletes a user from the database.
 * @param int $id The ID of the user to delete.
 * @return bool True on success, false on failure.
 * @throws Exception If trying to delete the last owner.
 */
function delete_user($id) {
    $db = get_db_connection();

    // Check if the user being deleted is an owner
    $user_to_delete_stmt = $db->prepare("SELECT role FROM users WHERE id = :id");
    $user_to_delete_stmt->execute(['id' => $id]);
    $user_to_delete = $user_to_delete_stmt->fetch();

    // Prevent deletion of the last owner
    if ($user_to_delete && $user_to_delete['role'] === 'owner') {
        $owner_count_stmt = $db->query("SELECT COUNT(id) FROM users WHERE role = 'owner'");
        if ($owner_count_stmt->fetchColumn() <= 1) {
            throw new Exception("شما نمی‌توانید آخرین کاربر Owner را حذف کنید.");
        }
    }
    
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    return $stmt->execute(['id' => $id]);
}

/**
 * Retrieves a single user by their username.
 * @param string $username The username to look for.
 * @return array|false The user's data or false if not found.
 */
function get_user_by_username($username) {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    return $stmt->fetch();
}


// --- LOGGING ---
/**
 * Writes a message to the log file.
 * @param string $level The log level (e.g., INFO, WARNING, ERROR).
 * @param string $message The message to log.
 */
function write_log($level, $message) {
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


// --- DEFAULT CONFIGS ---
$defaultConfigs = [
    "logo" => "", "homePage" => "", "title" => "پخش زنده جدید", "iframe" => "",
    "preBanner" => "", "endBanner" => "", "liveStart" => "", "liveEnd" => "",
    "fetchInterval" => 60000, "subtitleDelay" => 4000, "banner" => "",
    "colors" => ["bg" => "#ffffff", "title" => "#000000", "primary" => "#4caf50", "primary-hover" => "#45a049", "card-bg" => "#f8f9fa", "placeholder" => "#e9ecef", "placeholder-border"=> "#ced4da", "text" => "#212529"],
    "buttons" => ["btn1" => ["title" => "", "link" => ""], "btn2" => ["title" => "", "link" => ""], "btn3" => ["title" => "", "link" => ""]],
    "socials" => [
        "social1" => ["title" => "", "link" => "", "icon" => ""],
        "social2" => ["title" => "", "link" => "", "icon" => ""],
        "social3" => ["title" => "", "link" => "", "icon" => ""],
        "social4" => ["title" => "", "link" => "", "icon" => ""],
    ]
];

// --- EVENT MANAGEMENT ---
function get_events() {
    if (!file_exists(EVENTS_FILE)) return [];
    $json = file_get_contents(EVENTS_FILE);
    return json_decode($json, true) ?: [];
}

function is_valid_event_id($event_id) {
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

function safe_file_put_contents($filename, $data) {
    return file_put_contents($filename, $data, LOCK_EX);
}

function create_event_files($event_id, $default_configs) {
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
// If session event is invalid, fallback to the first available event
if (!$event_exists && !empty($all_events)) {
    $current_event_id = $all_events[0]['id'];
    $_SESSION['current_event_id'] = $current_event_id;
}

$configsFile = $current_event_id ? EVENTS_DIR . $current_event_id . '/configs.json' : null;
$subtitlesFile = $current_event_id ? EVENTS_DIR . $current_event_id . '/subtitles.json' : null;

$configs = ($configsFile && file_exists($configsFile)) ? json_decode(file_get_contents($configsFile), true) : $defaultConfigs;
$subtitles = ($subtitlesFile && file_exists($subtitlesFile)) ? json_decode(file_get_contents($subtitlesFile), true) : [];

// --- SECURITY & HELPERS ---

// --- CSRF Protection ---
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        return false;
    }
    return true;
}

// Authentication check for dashboard page
if (basename($_SERVER['PHP_SELF']) === 'dashboard.php') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['username'])) {
        header("Location: login.php");
        exit;
    }
}

// **FIX 2: Improved file handling function to correctly delete old files**
function handle_upload($file_input_name, $url_input_name, $old_file_path) {
    global $current_event_id;
    
    // The new path is initially what's in the URL input field.
    $new_file_path = $_POST[$url_input_name] ?? '';
    $error_message = null;

    // Check if a new file was actually uploaded. If so, it takes precedence.
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            $error_message = 'فرمت فایل آپلود شده مجاز نیست.';
        } elseif ($file['size'] > $max_size) {
            $error_message = 'حجم فایل بیشتر از ۵ مگابایت است.';
        } else {
            $event_upload_dir_abs = UPLOADS_DIR . $current_event_id . '/';
            if (!is_dir($event_upload_dir_abs)) mkdir($event_upload_dir_abs, 0755, true);

            $event_upload_dir_rel = 'uploads/' . $current_event_id . '/';
            $file_name = uniqid('file_') . '.' . $file_extension;
            $target_file_abs = $event_upload_dir_abs . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $target_file_abs)) {
                // If upload is successful, the new path is the uploaded file's path.
                $new_file_path = $event_upload_dir_rel . $file_name;
            } else {
                $error_message = 'خطا در آپلود فایل.';
                // If upload fails, revert to the URL input path to be safe.
                $new_file_path = $_POST[$url_input_name] ?? '';
            }
        }
    }

    // After determining the final new_file_path (either from upload or URL input),
    // check if it's different from the old path and if the old path was a local file.
    if ($new_file_path !== $old_file_path && !empty($old_file_path) && strpos($old_file_path, 'uploads/') === 0) {
        $old_file_on_disk = CONFIG_DIR . '/' . $old_file_path;
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
    $file_path = '';
    $file_name = '';

    if ($file_type === 'configs') {
        $file_path = EVENTS_DIR . $event_id . '/configs.json';
        $file_name = $event_id . '_configs_backup.json';
    } elseif ($file_type === 'subtitles') {
        $file_path = EVENTS_DIR . $event_id . '/subtitles.json';
        $file_name = $event_id . '_subtitles_backup.json';
    }

    if (file_exists($file_path)) {
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

