<?php
// File: index.php
require_once 'session_handler.php';
initializeSessionHandler();

// Debug: Log session data
error_log("index.php accessed. Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    error_log("Redirecting user with role: $role");
    switch ($role) {
        case 'student':
            header("Location: student_dashboard.php");
            exit();
        case 'instructor':
            header("Location: instructor.php");
            exit();
        case 'hod':
            header("Location: hod_dashboard.php");
            exit();
        case 'finance':
            header("Location: finance_dashboard.php");
            exit();
        case 'admin':
            header("Location: dashboard_admin.php");
            exit();
        case 'registrar':
            header("Location: registrar_dashboard.php");
            exit();
        default:
            error_log("Invalid role detected: $role. Clearing session.");
            // Clear session if role is invalid
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            // Optionally redirect to login page
            header("Location: index.php");
            exit();
    }
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KASMS Login</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .login-container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .login-container h2 { text-align: center; }
        .login-container input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; }
        .login-container button { width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .login-container button:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>KASMS Login</h2>
        <form action="login.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>