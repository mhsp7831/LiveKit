<?php
require_once 'functions.php';

// If user is already logged in, redirect them to the dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic validation for empty fields
    if (empty($username) || empty($password)) {
        $error = "نام کاربری و رمز عبور نباید خالی باشند.";
    } else {
        // Fetch user from the database
        $user = get_user_by_username($username);

        // Verify user existence and password correctness
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables upon successful login
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role']; // Store user role for permission checks
            
            write_log('INFO', "User '{$username}' logged in successfully.");
            header("Location: dashboard.php");
            exit;
        } else {
            // Log failed attempt and show a generic error message
            write_log('WARNING', "Failed login attempt for username '{$username}'.");
            $error = "نام کاربری یا رمز عبور اشتباه است.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>ورود</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-box">
        <h2>صفحه ورود</h2>
        <?php if (!empty($error)) : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <label>نام کاربری:</label>
            <input type="text" name="username" required>
            <label>رمز عبور:</label>
            <input type="password" name="password" required>
            <button type="submit">ورود</button>
        </form>
    </div>
</body>
</html>

