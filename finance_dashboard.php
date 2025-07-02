<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: index.php");
    exit();
}

$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_finance'])) {
    $student_id = $_POST['student_id'];
    $total_billed = $_POST['total_billed'];
    $total_paid = $_POST['total_paid'];
    $balance = $total_billed - $total_paid;

    try {
        $stmt = $conn->prepare("UPDATE students SET total_billed = ?, total_paid = ?, balance = ? WHERE student_id = ?");
        $stmt->bind_param("ddds", $total_billed, $total_paid, $balance, $student_id); // Changed to "ddds"
        $stmt->execute();
        $stmt->close();
        echo "Financial details updated successfully!";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

$stmt = $conn->prepare("SELECT student_id, name, total_billed, total_paid, balance FROM students");
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KASMS Finance Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7fa; }
        .sidebar { width: 250px; height: 100vh; background-color: #2c3e50; color: white; position: fixed; padding-top: 20px; }
        .sidebar a { padding: 15px 20px; text-decoration: none; color: white; display: block; }
        .sidebar a:hover { background-color: #34495e; }
        .content { margin-left: 250px; padding: 20px; }
        .header { background-color: #ecf0f1; padding: 10px 20px; display: flex; justify-content: flex-end; align-items: center; }
        .header span { margin-right: 20px; color: #e74c3c; }
        .finance-section { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="#">Home</a>
        <a href="#">Update Finances</a>
        <a href="#">Reports</a>
    </div>
    <div class="content">
        <div class="header">
            <span>Finance -</span>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none;">Log Out</a>
        </div>
        <div class="finance-section">
            <h2>Update Student Finances</h2>
            <?php foreach ($students as $student) { ?>
                <form method="POST" style="margin-bottom: 20px;">
                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                    <p><strong>Student:</strong> <?php echo $student['name']; ?> (Reg No: <?php echo $student['student_id']; ?>)</p>
                    <label>Total Billed: <input type="number" step="0.01" name="total_billed" value="<?php echo $student['total_billed']; ?>" required></label><br>
                    <label>Total Paid: <input type="number" step="0.01" name="total_paid" value="<?php echo $student['total_paid']; ?>" required></label><br>
                    <button type="submit" name="update_finance">Update</button>
                    <p><strong>Balance:</strong> <?php echo $student['total_billed'] - $student['total_paid']; ?></p>
                </form>
            <?php } ?>
        </div>
    </div>
</body>
</html>