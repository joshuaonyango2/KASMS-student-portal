<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: index.php");
    exit();
}

$conn = mysqli_connect("p:localhost", "root", "", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $semester = $_POST['semester'] ?? '2025-S1';
    $amount_paid = $_POST['amount_paid'] ?? 0;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT version FROM fees WHERE student_id = ? AND semester = ? FOR UPDATE");
        $stmt->bind_param("ss", $student_id, $semester);
        $stmt->execute();
        $result = $stmt->get_result();
        $fee = $result->fetch_assoc();
        $stmt->close();

        $version = $fee ? $fee['version'] : -1;
        if ($fee) {
            $new_amount_paid = $fee['amount_paid'] + $amount_paid;
            $stmt = $conn->prepare("UPDATE fees SET amount_paid = ?, version = version + 1 WHERE student_id = ? AND semester = ? AND version = ?");
            $stmt->bind_param("dsii", $new_amount_paid, $student_id, $semester, $version);
            $success = $stmt->execute();
            if (!$success) throw new Exception("Version conflict, please try again.");
        } else {
            $stmt = $conn->prepare("INSERT INTO fees (student_id, semester, amount_paid, version) VALUES (?, ?, ?, 0)");
            $stmt->bind_param("ssd", $student_id, $semester, $amount_paid);
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();
        echo "Fees updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Fees</title>
</head>
<body>
    <h2>Update Fees</h2>
    <form method="POST">
        <input type="text" name="student_id" placeholder="Student ID" required>
        <input type="text" name="semester" value="2025-S1" readonly>
        <input type="number" step="0.01" name="amount_paid" placeholder="Amount Paid" required>
        <button type="submit">Update</button>
    </form>
</body>
</html>