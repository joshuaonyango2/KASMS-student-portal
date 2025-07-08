<?php
require_once 'session_handler.php';
'initializeSessionHandler'();
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
    $stmt = $conn->prepare("SELECT s.course, COUNT(s.student_id) as student_count, AVG(er.final_grade) as avg_grade 
                       FROM students s 
                       LEFT JOIN exam_results er ON s.student_id = er.student_id 
                       LEFT JOIN enrollments e ON er.student_id = e.student_id AND er.unit_code = e.unit_code 
                       WHERE s.course = 'Computer Science' AND e.semester = ? 
                       GROUP BY s.course");;
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
<?
<?php
// Connect to database
$conn = mysqli_connect("localhost", "root", "", "student_portal");

$hod_id = 1; // HOD ID (hardcoded for demo)

// Get HOD details
$hod_query = mysqli_query($conn, "SELECT * FROM hod WHERE id = $hod_id");
$hod = mysqli_fetch_assoc($hod_query);
$dept = $hod['department'];

// Count students in HOD's department
$student_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM students WHERE department = '$dept'");
$student_count = mysqli_fetch_assoc($student_query)['total'];

// Fetch units in the department
$units_query = mysqli_query($conn, "SELECT * FROM units WHERE department = '$dept'");

// Fetch instructors in the department
$instructors_query = mysqli_query($conn, "SELECT instructors.*, units.unit_name FROM instructors 
    JOIN units ON instructors.unit_id = units.id WHERE instructors.department = '$dept'");
?>

<h2>Welcome, <?php echo $hod['name']; ?> (<?php echo $hod['department']; ?> Department)</h2>

<h3> Personal Details</h3>
<p><strong>Email:</strong> <?php echo $hod['email']; ?></p>

<h3> Students Enrolled: <?php echo $student_count; ?></h3>

<h3> Units Under Your Department</h3>
<ul>
<?php while ($unit = mysqli_fetch_assoc($units_query)) { ?>
    <li><?php echo $unit['unit_name']; ?> 
        <a href="delete_unit.php?id=<?php echo $unit['id']; ?>">[Remove]</a>
    </li>
<?php } ?>
</ul>

<!-- Add a unit -->
<h4>Add a New Unit</h4>
<form method="POST" action="add_unit.php">
    <input type="text" name="unit_name" placeholder="Unit Name" required>
    <input type="hidden" name="department" value="<?php echo $dept; ?>">
    <input type="submit" value="Add Unit">
</form>

<h3> Instructors</h3>
<ul>
<?php while ($inst = mysqli_fetch_assoc($instructors_query)) { ?>
    <li><?php echo $inst['name'] . " - " . $inst['unit_name'] . " (" . $inst['email'] . ")"; ?></li>
<?php } ?>
</ul>

<!-- Add a new instructor -->
<h4>Add New Instructor</h4>
<form method="POST" action="add_instructor.php">
    <input type="text" name="name" placeholder="Instructor Name" required>
    <input type="email" name="email" placeholder="Instructor Email" required>
    <select name="unit_id" required>
        <option value="">-- Select Unit --</option>
        <?php
        $units_result = mysqli_query($conn, "SELECT * FROM units WHERE department = '$dept'");
        while ($unit = mysqli_fetch_assoc($units_result)) {
            echo "<option value='{$unit['id']}'>{$unit['unit_name']}</option>";
        }
        ?>
    </select>
    <input type="hidden" name="department" value="<?php echo $dept; ?>">
    <input type="submit" value="Add Instructor">
</form>

