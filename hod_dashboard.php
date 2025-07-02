<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hod') {
    header("Location: index.php");
    exit();
}

$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT s.department, COUNT(s.student_id) as student_count, AVG(m.final_grade) as avg_grade 
                           FROM students s 
                           LEFT JOIN marks m ON s.student_id = m.student_id 
                           LEFT JOIN enrollments e ON m.student_id = e.student_id AND m.unit_code = e.unit_code 
                           WHERE s.department = 'Computer Science' AND e.semester = ? 
                           GROUP BY s.department");
    $semester = '2025-S1';
    $stmt->bind_param("s", $semester);
    $stmt->execute();
    $result_summary = $stmt->get_result();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KASMS HOD Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7fa; }
        .sidebar { width: 250px; height: 100vh; background-color: #2c3e50; color: white; position: fixed; padding-top: 20px; }
        .sidebar a { padding: 15px 20px; text-decoration: none; color: white; display: block; }
        .sidebar a:hover { background-color: #34495e; }
        .content { margin-left: 250px; padding: 20px; }
        .header { background-color: #ecf0f1; padding: 10px 20px; display: flex; justify-content: flex-end; align-items: center; }
        .header span { margin-right: 20px; color: #e74c3c; }
        .summary-section { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="#">Home</a>
        <a href="#">Approve Marks</a>
        <a href="#">Reports</a>
    </div>
    <div class="content">
        <div class="header">
            <span>HOD -</span>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none;">Log Out</a>
        </div>
        <div class="summary-section">
            <h2>Department Summary</h2>
            <p>Department: Computer Science</p>
            <?php if ($row = $result_summary->fetch_assoc()) {
                echo "<p>Student Count: " . $row['student_count'] . "</p>";
                echo "<p>Average Grade: " . ($row['avg_grade'] ?? 'N/A') . "</p>";
            } ?>
        </div>
    </div>
    <?php $conn->close(); ?>
</body>
</html>