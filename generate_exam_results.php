```php
<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.php");
    exit();
}

require_once 'vendor/autoload.php';
use TCPDF;

$conn = new mysqli("p:localhost", "root", "", "kasms_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'];
$semester = $_GET['semester'] ?? '';
if (empty($semester)) {
    die("Invalid semester.");
}

// Fetch exam results
$stmt = $conn->prepare("SELECT e.unit_code, u.unit_name, e.final_grade FROM exam_results e JOIN units u ON e.unit_code = u.unit_code WHERE e.student_id = ? AND e.semester = ?");
$stmt->bind_param("ss", $student_id, $semester);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

if (empty($results)) {
    die("No exam results found for the selected semester.");
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('KASMS');
$pdf->SetTitle('Exam Results');
$pdf->SetHeaderData('', 0, 'KASMS Exam Results', '');
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// Generate PDF content
$html = '
<h1 style="text-align: center;">KASMS Exam Results</h1>
<p><strong>Student ID:</strong> ' . htmlspecialchars($student_id) . '</p>
<p><strong>Semester:</strong> ' . htmlspecialchars($semester) . '</p>
<table border="1" cellpadding="5">
    <tr>
        <th>Unit Code</th>
        <th>Unit Name</th>
        <th>Final Grade</th>
    </tr>';
foreach ($results as $result) {
    $html .= '
    <tr>
        <td>' . htmlspecialchars($result['unit_code']) . '</td>
        <td>' . htmlspecialchars($result['unit_name']) . '</td>
        <td>' . htmlspecialchars($result['final_grade']) . '</td>
    </tr>';
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('exam_results_' . $semester . '.pdf', 'D');
?>
```
<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "kasms_db";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle selection
$selectedStudent = $_POST['student'] ?? '';
$selectedCourse = $_POST['course'] ?? '';
$selectedSemester = $_POST['semester'] ?? '';

// Fetch dropdown data
$students = $conn->query("SELECT DISTINCT student_id, full_name FROM students");
$courses = $conn->query("SELECT DISTINCT course FROM students");
$semesters = $conn->query("SELECT DISTINCT semester FROM exam_results");

// Display filter form
echo "<form method='POST'>
    <label>Student:</label>
    <select name='student'>
        <option value=''>-- All Students --</option>";
while ($row = $students->fetch_assoc()) {
    $selected = ($selectedStudent == $row['student_id']) ? "selected" : "";
    echo "<option value='{$row['student_id']}' $selected>{$row['full_name']}</option>";
}
echo "</select>";

echo "<label>Course:</label>
    <select name='course'>
        <option value=''>-- All Courses --</option>";
while ($row = $courses->fetch_assoc()) {
    $selected = ($selectedCourse == $row['course']) ? "selected" : "";
    echo "<option value='{$row['course']}' $selected>{$row['course']}</option>";
}
echo "</select>";

echo "<label>Semester:</label>
    <select name='semester'>
        <option value=''>-- All Semesters --</option>";
while ($row = $semesters->fetch_assoc()) {
    $selected = ($selectedSemester == $row['semester']) ? "selected" : "";
    echo "<option value='{$row['semester']}' $selected>{$row['semester']}</option>";
}
echo "</select>";

echo "<input type='submit' value='Filter'>
</form><br>";

// Build dynamic query
$where = "WHERE 1=1";
if ($selectedStudent) $where .= " AND s.student_id = " . intval($selectedStudent);
if ($selectedCourse) $where .= " AND s.course = '" . $conn->real_escape_string($selectedCourse) . "'";
if ($selectedSemester) $where .= " AND r.semester = '" . $conn->real_escape_string($selectedSemester) . "'";

// Fetch filtered results
$sql = "SELECT s.student_id, s.reg_no, s.full_name, s.course, r.unit_name, r.marks, r.semester 
        FROM students s 
        JOIN exam_results r ON s.student_id = r.student_id 
        $where
        ORDER BY s.student_id, r.unit_name";

$result = $conn->query($sql);

$studentData = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id = $row['student_id'];
        $studentData[$id]['info'] = [
            'reg_no' => $row['reg_no'],
            'name' => $row['full_name'],
            'course' => $row['course']
        ];
        $studentData[$id]['results'][] = [
            'unit' => $row['unit_name'],
            'marks' => $row['marks'],
            'semester' => $row['semester']
        ];
    }
} else {
    echo "<p>No results found based on the filters.</p>";
}

function getGrade($marks) {
    if ($marks >= 70) return 'A';
    elseif ($marks >= 60) return 'B';
    elseif ($marks >= 50) return 'C';
    elseif ($marks >= 40) return 'D';
    else return 'E';
}

// Display results
foreach ($studentData as $data) {
    $info = $data['info'];
    $results = $data['results'];
    $total = 0;
    $count = count($results);

    echo "<h3>Reg No: {$info['reg_no']} | Name: {$info['name']} | Course: {$info['course']}</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Unit</th><th>Marks</th><th>Grade</th><th>Semester</th></tr>";

    foreach ($results as $res) {
        $grade = getGrade($res['marks']);
        $total += $res['marks'];
        echo "<tr>
            <td>{$res['unit']}</td>
            <td>{$res['marks']}</td>
            <td>$grade</td>
            <td>{$res['semester']}</td>
        </tr>";
    }

    $average = $total / $count;
    $finalGrade = getGrade($average);

    echo "<tr><td><strong>Total</strong></td><td colspan='3'>$total</td></tr>";
    echo "<tr><td><strong>Average</strong></td><td colspan='3'>" . number_format($average, 2) . "</td></tr>";
    echo "<tr><td><strong>Overall Grade</strong></td><td colspan='3'>$finalGrade</td></tr>";
    echo "</table><br>";
}

$conn->close();
?>
  