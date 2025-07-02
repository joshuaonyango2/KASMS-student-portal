```php
<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.php");
    exit();
}

require_once 'vendor/autoload.php'; // Assuming TCPDF is installed via Composer
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

// Check payment status
$stmt = $conn->prepare("SELECT total_billed, total_paid FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$payment_percentage = ($student['total_billed'] > 0) ? ($student['total_paid'] / $student['total_billed']) * 100 : 0;
if ($payment_percentage < 98) {
    die("You must pay at least 98% of fees to download the exam card.");
}

// Fetch registration details
$stmt = $conn->prepare("SELECT program_name, semester, stage, unit_code, unit_name FROM registrations WHERE student_id = ? AND semester = ?");
$stmt->bind_param("ss", $student_id, $semester);
$stmt->execute();
$registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

if (empty($registrations)) {
    die("No registration found for the selected semester.");
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('KASMS');
$pdf->SetTitle('Exam Card');
$pdf->SetHeaderData('', 0, 'KASMS Exam Card', '');
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->AddPage();

// Generate PDF content
$html = '
<h1 style="text-align: center;">KASMS Exam Card</h1>
<p><strong>Student ID:</strong> ' . htmlspecialchars($student_id) . '</p>
<p><strong>Program:</strong> ' . htmlspecialchars($registrations[0]['program_name']) . '</p>
<p><strong>Semester:</strong> ' . htmlspecialchars($semester) . '</p>
<p><strong>Stage:</strong> ' . htmlspecialchars($registrations[0]['stage']) . '</p>
<h3>Registered Units</h3>
<table border="1" cellpadding="5">
    <tr>
        <th>Unit Code</th>
        <th>Unit Name</th>
    </tr>';
foreach ($registrations as $reg) {
    $html .= '
    <tr>
        <td>' . htmlspecialchars($reg['unit_code']) . '</td>
        <td>' . htmlspecialchars($reg['unit_name']) . '</td>
    </tr>';
}
$html .= '
</table>
<p style="margin-top: 50px;"><strong>Finance Signature:</strong> _________________________</p>
<p><strong>Official Stamp:</strong></p>
<img src="path/to/stamp.png" width="100" height="100">'; // Replace with actual stamp path

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('exam_card_' . $semester . '.pdf', 'D');
?>
```