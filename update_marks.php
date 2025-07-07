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

<?php
<label for="year">Year of Study:</label>
<select name="year" id="year" required>
    <option value="">-- Select Year --</option>
    <option value="1">Year 1</option>
    <option value="2">Year 2</option>
    <option value="3">Year 3</option>
</select>

<label for="semester">Semester:</label>
<select name="semester" id="semester" required>
    <option value="">-- Select Semester --</option>
    <option value="1">Semester 1</option>
    <option value="2">Semester 2</option>
</select>
<?


</body>
</html>
<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "student_portal";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Form submission logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'];
    $year_semester = $_POST['year_semester'];

    // Split the "Year X - Semester Y" into parts
    $parts = explode(' - ', $year_semester);
    $year = (int) filter_var($parts[0], FILTER_SANITIZE_NUMBER_INT);
    $semester = (int) filter_var($parts[1], FILTER_SANITIZE_NUMBER_INT);

    // Insert into the database
    $sql = "INSERT INTO student_year_semester (student_id, year_of_study, semester)
            VALUES ('$student_id', '$year', '$semester')";

    if (mysqli_query($conn, $sql)) {
        echo "✅ Data inserted successfully!";
    } else {
        echo "❌ Error: " . mysqli_error($conn);
    }
}
?>

<!-- HTML Form -->
<form method="POST" action="">
    <label for="student_id">Student ID:</label>
    <input type="text" name="student_id" required><br><br>

    <label for="year_semester">Year and Semester:</label>
    <select name="year_semester" id="year_semester" required>
        <option value="">-- Select Year & Semester --</option>
        <?php
        for ($year = 1; $year <= 3; $year++) {
            for ($sem = 1; $sem <= 2; $sem++) {
                $option = "Year $year - Semester $sem";
                echo "<option value='$option'>$option</option>";
            }
        }
        ?>
    </select><br><br>

    <input type="submit" value="Submit">
</form>


    