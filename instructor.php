<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: index.php");
    exit();
}

$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$instructor_id = $_SESSION['user_id'];
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT s.student_id, s.name, u.unit_code, u.unit_name 
                           FROM students s 
                           JOIN enrollments e ON s.student_id = e.student_id 
                           JOIN units u ON e.unit_code = u.unit_code 
                           WHERE u.instructor_id = ? AND e.semester = ?");
    $semester = '2025-S1';
    $stmt->bind_param("is", $instructor_id, $semester);
    $stmt->execute();
    $result_students = $stmt->get_result();
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
    <title>KASMS Instructor Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7fa; }
        .sidebar { width: 250px; height: 100vh; background-color: #2c3e50; color: white; position: fixed; padding-top: 20px; }
        .sidebar a { padding: 15px 20px; text-decoration: none; color: white; display: block; }
        .sidebar a:hover { background-color: #34495e; }
        .content { margin-left: 250px; padding: 20px; }
        .header { background-color: #ecf0f1; padding: 10px 20px; display: flex; justify-content: flex-end; align-items: center; }
        .header span { margin-right: 20px; color: #e74c3c; }
        .students-section { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .students-section table { width: 100%; border-collapse: collapse; }
        .students-section td { padding: 10px; border-bottom: 1px solid #ddd; }
        .students-section td:first-child { font-weight: bold; color: #2c3e50; }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="#">Home</a>
        <a href="update_marks.php">Update Marks</a>
        <a href="#">Reports</a>
    </div>
    <div class="content">
        <div class="header">
            <span>Instructor -</span>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none;">Log Out</a>
        </div>
        <div class="students-section">
            <h2>Enrolled Students</h2>
            <table>
                <tr><th>Student ID</th><th>Name</th><th>Unit Code</th><th>Unit Name</th></tr>
                <?php while ($student = $result_students->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $student['student_id'] . "</td>";
                    echo "<td>" . $student['name'] . "</td>";
                    echo "<td>" . $student['unit_code'] . "</td>";
                    echo "<td>" . $student['unit_name'] . "</td>";
                    echo "</tr>";
                } ?>
            </table>
        </div>
    </div>
    <?php $conn->close(); ?>
</body>
</html>