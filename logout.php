<?php
// File: logout.php
require_once 'session_handler.php';
initializeSessionHandler();

$conn = mysqli_connect("p:localhost", "root", "", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $session_id = session_id();
    error_log("Logout: Attempting to delete session ID: $session_id");
    
    // Delete session from database
    if (!empty($session_id)) {
        $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            error_log("No session found in database for ID: $session_id");
        }
        $stmt->close();
    } else {
        error_log("No session ID available during logout");
    }

    // Clear session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Prevent caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

    header("Location: index.php");
    exit();
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    die("Error during logout: " . $e->getMessage());
} finally {
    $conn->close();
}
?>