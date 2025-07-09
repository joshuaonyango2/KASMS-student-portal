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

$feedback = '';
$page = isset($_GET['page']) ? $_GET['page'] : 'update';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_finance']) && $page === 'update') {
    $student_id = $_POST['update_finance']; // Get student_id from the button value
    $total_billed = filter_var($_POST['total_billed'][$student_id] ?? null, FILTER_VALIDATE_FLOAT);
    $total_paid = filter_var($_POST['total_paid'][$student_id] ?? null, FILTER_VALIDATE_FLOAT);
    $semester = '2025-S1'; // Assume current semester; adjust as needed

    if ($total_billed === false || $total_paid === false || $total_billed < 0 || $total_paid < 0) {
        $feedback = "Invalid input for total billed or total paid. Please enter positive numbers.";
    } else {
        $conn->begin_transaction();
        try {
            // Get current totals to calculate differences
            $stmt = $conn->prepare("SELECT total_billed, total_paid FROM students WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current = $result->fetch_assoc();
            $stmt->close();

            $billed_diff = $total_billed - ($current['total_billed'] ?? 0);
            $paid_diff = $total_paid - ($current['total_paid'] ?? 0);

            // Update students table
            $stmt = $conn->prepare("UPDATE students SET total_billed = ?, total_paid = ? WHERE student_id = ?");
            $stmt->bind_param("dds", $total_billed, $total_paid, $student_id);
            $stmt->execute();

            // Update fees table for the semester
            $stmt = $conn->prepare("UPDATE fees SET amount_owed = amount_owed + ?, amount_paid = amount_paid + ? WHERE student_id = ? AND semester = ?");
            $stmt->bind_param("ddss", $billed_diff, $paid_diff, $student_id, $semester);
            $stmt->execute();

            // Record payment if there’s a change in paid amount
            if ($paid_diff > 0) {
                $receipt_number = 'REC' . time() . rand(1000, 9999); // Unique receipt number
                $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, payment_date, receipt_number, service_type, semester) VALUES (?, ?, NOW(), ?, 'Fee Update', ?)");
                $stmt->bind_param("sdss", $student_id, $paid_diff, $receipt_number, $semester);
                $stmt->execute();
            }

            $conn->commit();
            $feedback = "Financial details updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $feedback = "Error: " . $e->getMessage();
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT student_id, name, course, year_of_study, id_number, gender, date_of_birth, phone_number, email_address, postal_address, disability, total_billed, total_paid, balance FROM students");
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
        .feedback { color: #e74c3c; margin-bottom: 10px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #2c3e50;
            color: white;
        }
        .update-table td input {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
        }
        .update-table td button {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }
        .update-table td button:hover {
            background-color: #27ae60;
        }
        .report-table td {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="?page=update">Home</a>
        <a href="?page=update">Update Finances</a>
        <a href="?page=report">Reports</a>
    </div>
    <div class="content">
        <div class="header">
            <span>Finance -</span>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none;">Log Out</a>
        </div>
        <div class="finance-section">
            <h2><?php echo ($page === 'update') ? 'Update Student Finances' : 'Financial Reports'; ?></h2>
            <?php if ($feedback && $page === 'update'): ?>
                <div class="feedback"><?php echo htmlspecialchars($feedback); ?></div>
            <?php endif; ?>
            <?php if ($page === 'update'): ?>
                <form method="POST" class="update-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Admission No</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Year of Study</th>
                                <th>ID Number</th>
                                <th>Gender</th>
                                <th>Date of Birth</th>
                                <th>Phone Number</th>
                                <th>Email Address</th>
                                <th>Postal Address</th>
                                <th>Disability</th>
                                <th>Total Billed</th>
                                <th>Total Paid</th>
                                <th>Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['course']); ?></td>
                                    <td><?php echo htmlspecialchars($student['year_of_study']); ?></td>
                                    <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($student['date_of_birth']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email_address']); ?></td>
                                    <td><?php echo htmlspecialchars($student['postal_address']); ?></td>
                                    <td><?php echo htmlspecialchars($student['disability']); ?></td>
                                    <td><input type="number" step="0.01" name="total_billed[<?php echo $student['student_id']; ?>]" value="<?php echo htmlspecialchars($student['total_billed']); ?>" required></td>
                                    <td><input type="number" step="0.01" name="total_paid[<?php echo $student['student_id']; ?>]" value="<?php echo htmlspecialchars($student['total_paid']); ?>" required></td>
                                    <td><?php echo htmlspecialchars($student['balance']); ?></td>
                                    <td>
                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
                                        <button type="submit" name="update_finance" value="<?php echo htmlspecialchars($student['student_id']); ?>">Update</button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </form>
            <?php elseif ($page === 'report'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Admission No</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Year of Study</th>
                            <th>ID Number</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Phone Number</th>
                            <th>Email Address</th>
                            <th>Postal Address</th>
                            <th>Disability</th>
                            <th>Total Billed</th>
                            <th>Total Paid</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['course']); ?></td>
                                <td><?php echo htmlspecialchars($student['year_of_study']); ?></td>
                                <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                <td><?php echo htmlspecialchars($student['date_of_birth']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['email_address']); ?></td>
                                <td><?php echo htmlspecialchars($student['postal_address']); ?></td>
                                <td><?php echo htmlspecialchars($student['disability']); ?></td>
                                <td><?php echo htmlspecialchars($student['total_billed']); ?></td>
                                <td><?php echo htmlspecialchars($student['total_paid']); ?></td>
                                <td><?php echo htmlspecialchars($student['balance']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>