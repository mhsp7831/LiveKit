<?php
session_start();

// پاک کردن تمام متغیرهای سشن
$_SESSION = array();

// از بین بردن سشن
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// هدایت به صفحه ورود
header("Location: login.php");
exit;
?>
