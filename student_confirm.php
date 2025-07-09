<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.php");
    exit();
}

$conn = mysqli_connect("p:localhost", "root", "", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT student_id, name, id_number, confirmed FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_details'])) {
    try {
        $stmt = $conn->prepare("UPDATE students SET confirmed = TRUE WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $stmt->close();

        header("Location: student_dashboard.php");
        exit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7fa; }
        .content { padding: 20px; }
        .header { background-color: #ecf0f1; padding: 10px 20px; display: flex; justify-content: flex-end; align-items: center; }
        .header span { margin-right: 20px; color: #e74c3c; }
        .confirm-section { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 500px; margin: 20px auto; }
        .confirm-section button { padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .confirm-section button:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="content">
        <div class="header">
            <span><?php echo $student['name']; ?> -</span>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none;">Log Out</a>
        </div>
        <div class="confirm-section">
            <h2>Confirm Your Details</h2>
            <p><strong>Registration Number:</strong> <?php echo $student['student_id']; ?></p>
            <p><strong>Name:</strong> <?php echo $student['name']; ?></p>
            <p><strong>ID Number:</strong> <?php echo $student['id_number']; ?></p>
            <?php if (!$student['confirmed']) { ?>
                <form method="POST">
                    <p>Please confirm that the details above are correct.</p>
                    <button type="submit" name="confirm_details">Confirm</button>
                </form>
            <?php } else { ?>
                <p>Details already confirmed. Proceed to your dashboard.</p>
                <a href="student_dashboard.php" style="color: #28a745; text-decoration: none;">Go to Dashboard</a>
            <?php } ?>
        </div>
    </div>
</body>
</html>