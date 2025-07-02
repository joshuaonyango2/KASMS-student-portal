```php
<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("p:localhost", "root", "0000", "kasms_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'];
$success_message = $error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semester = $_POST['semester'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    if (empty($semester) || empty($reason) || empty($start_date) || empty($end_date)) {
        $error_message = "All fields are required.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO academic_leave (student_id, semester, reason, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $student_id, $semester, $reason, $start_date, $end_date);
            $stmt->execute();
            $conn->commit();
            $success_message = "Academic leave request submitted successfully!";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Leave Submission</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); text-align: center; }
        .success { color: green; }
        .error { color: red; }
        a { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Academic Leave Submission</h2>
        <?php if ($success_message): ?>
            <p class="success"><?php echo $success_message; ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <a href="student_dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>
```