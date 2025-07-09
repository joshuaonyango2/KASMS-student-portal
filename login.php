<?php
require_once 'session_handler.php';
initializeSessionHandler();

$conn = mysqli_connect("p:localhost", "root", "", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $conn->prepare("SELECT id, username, password, role, student_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            error_log("User found: $username, Hash: " . $user['password']);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['student_id'] = $user['student_id'];

                switch ($user['role']) {
                    case 'student':
                        $stmt = $conn->prepare("SELECT confirmed FROM students WHERE student_id = ?");
                        $stmt->bind_param("s", $user['student_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $student = $result->fetch_assoc();
                        $stmt->close();

                        if ($student['confirmed']) {
                            header("Location: student_dashboard.php");
                        } else {
                            header("Location: student_confirm.php");
                        }
                        break;
                    case 'instructor':
                        header("Location: instructor.php");
                        break;
                    case 'hod':
                        header("Location: hod_dashboard.php");
                        break;
                    case 'finance':
                        header("Location: finance_dashboard.php");
                        break;
                    case 'registrar':
                        header("Location: registrar_dashboard.php");
                        break;
                    case 'admin':
                        header("Location: dashboard_admin.php");
                        break;
                    default:
                        error_log("Unknown role for $username: " . $user['role']);
                        echo "Error: Unknown role!";
                        exit();
                }
                exit();
            } else {
                error_log("Password verification failed for $username");
            }
        } else {
            error_log("No user found for username: $username");
        }
    } catch (Exception $e) {
        error_log("Login error for $username: " . $e->getMessage());
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Error</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .error-container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); text-align: center; }
        .error-container a { color: #28a745; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-container">
        <h2>Login Failed</h2>
        <p>Invalid username or password. Please try again.</p>
        <a href="index.php">Back to Login</a>
    </div>
</body>
</html>