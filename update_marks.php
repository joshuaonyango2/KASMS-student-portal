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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $unit_code = $_POST['unit_code'] ?? '';
    $unit_title = $_POST['unit_title'] ??'';
    $cat1 = $_POST['cat1'] ?? 0;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT version FROM marks WHERE student_id = ?,unit_title AND unit_code = ? FOR UPDATE");
        $stmt->bind_param("ss", $student_id, $unit_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $mark = $result->fetch_assoc();
        $stmt->close();

        $version = $mark ? $mark['version'] : -1;
        if ($mark) {
            $stmt = $conn->prepare("UPDATE marks SET cat1 = ?, version = version + 1 WHERE student_id = ? unit_title= ? AND unit_code = ? AND version = ?");
            $stmt->bind_param("isii", $cat1, $student_id, $unit_title, $unit_code, $version);
            $success = $stmt->execute();
            if (!$success) throw new Exception("Version conflict, please try again.");
        } else {
            $stmt = $conn->prepare("INSERT INTO marks (student_id, unit_title, unit_code, cat1, version) VALUES (?, ?, ?, 0)");
            $stmt->bind_param("ssi", $student_id,$unit_title, $unit_code, $cat1);
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();
        echo "Marks updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}

$conn->close();
?>
     <?php
     -- Determine grade
    if (condition) {
        # code...
    } total < 40 then
        SET grade = 'E';
    elseif total < 50 then
        SET grade = 'D';
    elseif total < 60 then
        SET grade = 'C';
    elseif total < 70 then
        SET grade = 'B';
    else {
        # code...
    }
        SET grade = 'A';
    end if;

    -- Update final_grade
    UPDATE marks
    SET final_grade = $grade;
    WHERE id = $mark_id;
END$$

DELIMITER ;

?>

        
<!DOCTYPE html>
<html>
<head>
    <title>Update Marks</title>
</head>
<body>
    <h2>Update Marks</h2>
    <form method="POST">
        <input type="text" name="student_id" placeholder="Student ID" required>
        <input type="text" name="unnit_title" placeholder="Unit Title" required>
        <input type="text" name="unit_code" placeholder="Unit Code" required>
        <input type="number" name="cat1" placeholder="CAT 1 Score" required>
        <button type="submit">Update</button>
    </form>
</body>
</html>


     php
     <!DOCTYPE html>
     <html lang="en">
     <head>

        <title>Students Marks and Grade</title>
     </head>
     <body>
        <h2>Enter Students Marks</h2>
        <form method="POST">
            <label>Student Name:</label><br>
            <input type="text" name="student_name" required>
            <input type="number" name="cat1" min="0" max="100"><br><br>

    <label>CAT 2:</label><br>
    <input type="number" name="cat2" min="0" max="100"><br><br>

    <label>Assignment:</label><br>
    <input type="number" name="assignment" min="0" max="100"><br><br>

    <label>Main Exam:</label><br>
    <input type="number" name="main_exam" min="0" max="100"><br><br>

    <input type="submit" name="submit" value="Calculate Grade">
</form>

</body>
</html>

    