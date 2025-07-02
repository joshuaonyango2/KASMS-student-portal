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

$conn = new mysqli("p:localhost", "root", "0000", "kasms_db");
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