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

// Fetch student financial details
$stmt = $conn->prepare("SELECT name, total_billed, total_paid, balance FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch payment history
$stmt = $conn->prepare("SELECT amount, payment_date, receipt_number, service_type FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('KASMS');
$pdf->SetTitle('Fee Statement');
$pdf->SetHeaderData('', 0, 'KASMS Fee Statement', '');
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// Generate PDF content
$html = '
<h1 style="text-align: center;">KASMS Fee Statement</h1>
<p><strong>Student Name:</strong> ' . htmlspecialchars($student['name']) . '</p>
<p><strong>Student ID:</strong> ' . htmlspecialchars($student_id) . '</p>
<h3>Financial Summary</h3>
<table border="1" cellpadding="5">
    <tr>
        <th>Total Billed</th>
        <th>Total Paid</th>
        <th>Balance</th>
    </tr>
    <tr>
        <td>Ksh ' . number_format($student['total_billed'], 2) . '</td>
        <td>Ksh ' . number_format($student['total_paid'], 2) . '</td>
        <td>Ksh ' . number_format($student['balance'], 2) . '</td>
    </tr>
</table>
<h3>Payment History</h3>
<table border="1" cellpadding="5">
    <tr>
        <th>Date</th>
        <th>Receipt #</th>
        <th>Amount</th>
        <th>Service Type</th>
    </tr>';
foreach ($payments as $payment) {
    $html .= '
    <tr>
        <td>' . date('d/m/Y', strtotime($payment['payment_date'])) . '</td>
        <td>' . htmlspecialchars($payment['receipt_number']) . '</td>
        <td>Ksh ' . number_format($payment['amount'], 2) . '</td>
        <td>' . htmlspecialchars($payment['service_type']) . '</td>
    </tr>';
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('fee_statement.pdf', 'D');
?>
```