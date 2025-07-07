<?php
// Set session options before starting the session
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookies
ini_set('session.use_only_cookies', 1); // Use cookies only for sessions
ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // Use secure cookies if HTTPS

// Start the session
session_start();

// Regenerate session ID on login to prevent session fixation
function regenerateSession() {
    if (session_id() !== '') {
        session_regenerate_id(true);
    }
}

// Check if user is logged in and has the correct role
function isLoggedIn($required_role = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    if ($required_role && $_SESSION['role'] !== $required_role) {
        return false;
    }
    return true;
}

// Redirect to login if not authenticated
function requireLogin($required_role = null) {
    if (!isLoggedIn($required_role)) {
        header("Location: login.php");
        exit();
    }
}

// Destroy session on logout
function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>