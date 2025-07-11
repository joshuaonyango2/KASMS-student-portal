<?php
require_once 'session_handler.php';
initializeSessionHandler();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: index.php");
    exit();
}

// Database connection
$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
if (!$conn) {
    error_log("Connection failed: " . mysqli_connect_error());
    die("System error. Please try again later.");
}

$instructor_id = (int)$_SESSION['user_id'];
$semester = date('Y') . '-S1';
$instructor_name = htmlspecialchars($_SESSION['name'] ?? 'Instructor');

// Fetch instructor personal details
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT name, id_number, gender, phone, email, designation 
                           FROM user_details WHERE user_id = ?");
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $instructor_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error fetching instructor details: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Fetch instructor's registered units
try {
    $stmt = $conn->prepare("SELECT unit_code, unit_name, course FROM units WHERE instructor_id = ?");
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $registered_units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching registered units: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Handle unit registration
$unit_registration_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_units'])) {
    $selected_units = $_POST['selected_units'] ?? [];
    if (!is_array($selected_units)) {
        $unit_registration_message = "Error: Invalid unit selection.";
    } else {
        $conn->begin_transaction();
        try {
            // Clear existing unit assignments for this instructor
            $stmt = $conn->prepare("UPDATE units SET instructor_id = NULL WHERE instructor_id = ?");
            $stmt->bind_param("i", $instructor_id);
            $stmt->execute();
            $stmt->close();

            // Assign selected units to instructor
            $stmt = $conn->prepare("UPDATE units SET instructor_id = ? WHERE unit_code = ?");
            foreach ($selected_units as $unit_code) {
                $unit_code = htmlspecialchars($unit_code);
                $stmt->bind_param("is", $instructor_id, $unit_code);
                $stmt->execute();
            }
            $stmt->close();
            $conn->commit();
            $unit_registration_message = "Units registered successfully.";
            // Refresh registered units
            $stmt = $conn->prepare("SELECT unit_code, unit_name, course FROM units WHERE instructor_id = ?");
            $stmt->bind_param("i", $instructor_id);
            $stmt->execute();
            $registered_units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error registering units: " . $e->getMessage());
            $unit_registration_message = "Error registering units. Please try again.";
        }
    }
}

// Fetch available units for registration
try {
    $stmt = $conn->prepare("SELECT unit_code, unit_name, course FROM units WHERE instructor_id IS NULL OR instructor_id = ?");
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $available_units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching units: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Fetch distinct courses (departments) for filtering
try {
    $stmt = $conn->prepare("SELECT DISTINCT course FROM units WHERE instructor_id = ?");
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Fetch students for marks update with filtering
$marks_filter_unit = $_POST['filter_unit'] ?? '';
$marks_filter_course = $_POST['filter_course'] ?? '';
$marks_students = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_marks'])) {
    $query = "SELECT s.student_id, s.name, r.unit_code, r.unit_name, c.cat1, e.final_exam, u.course 
              FROM students s 
              JOIN registrations r ON s.student_id = r.student_id 
              JOIN units u ON r.unit_code = u.unit_code
              LEFT JOIN coursework c ON s.student_id = c.student_id AND r.unit_code = c.unit_code AND r.semester = c.semester 
              LEFT JOIN exam_results e ON s.student_id = e.student_id AND r.unit_code = e.unit_code AND r.semester = e.semester 
              WHERE r.unit_code IN (SELECT unit_code FROM units WHERE instructor_id = ?) 
              AND r.semester = ?";
    $params = [$instructor_id, $semester];
    $types = "is";

    if (!empty($marks_filter_unit)) {
        $query .= " AND r.unit_code = ?";
        $params[] = $marks_filter_unit;
        $types .= "s";
    }
    if (!empty($marks_filter_course) && count($courses) > 1) {
        $query .= " AND u.course = ?";
        $params[] = $marks_filter_course;
        $types .= "s";
    }

    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $marks_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching students for marks: " . $e->getMessage());
        die("System error. Please try again later.");
    }
} else {
    try {
        $stmt = $conn->prepare("SELECT s.student_id, s.name, r.unit_code, r.unit_name, c.cat1, e.final_exam, u.course 
                               FROM students s 
                               JOIN registrations r ON s.student_id = r.student_id 
                               JOIN units u ON r.unit_code = u.unit_code
                               LEFT JOIN coursework c ON s.student_id = c.student_id AND r.unit_code = c.unit_code AND r.semester = c.semester 
                               LEFT JOIN exam_results e ON s.student_id = e.student_id AND r.unit_code = e.unit_code AND r.semester = e.semester 
                               WHERE r.unit_code IN (SELECT unit_code FROM units WHERE instructor_id = ?) 
                               AND r.semester = ?");
        $stmt->bind_param("is", $instructor_id, $semester);
        $stmt->execute();
        $marks_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching students: " . $e->getMessage());
        die("System error. Please try again later.");
    }
}

// Handle coursework and exam marks submission
$marks_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_marks'])) {
    list($student_id, $unit_code) = explode('|', $_POST['student_unit'] ?? '');
    $cat1 = isset($_POST['cat1']) && is_numeric($_POST['cat1']) ? (int)$_POST['cat1'] : null;
    $final_exam = isset($_POST['final_exam']) && is_numeric($_POST['final_exam']) ? (int)$_POST['final_exam'] : null;

    if (empty($student_id) || empty($unit_code)) {
        $marks_message = "Error: Invalid student or unit selection.";
    } else {
        $conn->begin_transaction();
        try {
            if ($cat1 !== null) {
                $stmt = $conn->prepare("INSERT INTO coursework (student_id, unit_code, semester, cat1, version) 
                                       VALUES (?, ?, ?, ?, 0) 
                                       ON DUPLICATE KEY UPDATE cat1 = ?, version = version + 1");
                $stmt->bind_param("sssii", $student_id, $unit_code, $semester, $cat1, $cat1);
                $stmt->execute();
                $stmt->close();
            }

            if ($final_exam !== null) {
                $stmt = $conn->prepare("INSERT INTO exam_results (student_id, unit_code, semester, final_exam, version) 
                                       VALUES (?, ?, ?, ?, 0) 
                                       ON DUPLICATE KEY UPDATE final_exam = ?, version = version + 1");
                $stmt->bind_param("sssii", $student_id, $unit_code, $semester, $final_exam, $final_exam);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $marks_message = "Marks updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error updating marks: " . $e->getMessage());
            $marks_message = "Error updating marks. Please try again.";
        }
    }
}

// Fetch students for reports with filtering, including marks
$report_filter_unit = $_POST['report_filter_unit'] ?? '';
$report_filter_course = $_POST['report_filter_course'] ?? '';
$report_students = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_report'])) {
    $query = "SELECT s.student_id, s.name, r.unit_code, r.unit_name, u.course, c.cat1, e.final_exam,
                     COALESCE(c.cat1, 0) + COALESCE(e.final_exam, 0) AS total_score
              FROM students s 
              JOIN registrations r ON s.student_id = r.student_id 
              JOIN units u ON r.unit_code = u.unit_code 
              LEFT JOIN coursework c ON s.student_id = c.student_id AND r.unit_code = c.unit_code AND r.semester = c.semester 
              LEFT JOIN exam_results e ON s.student_id = e.student_id AND r.unit_code = e.unit_code AND r.semester = e.semester 
              WHERE r.unit_code IN (SELECT unit_code FROM units WHERE instructor_id = ?) 
              AND r.semester = ?";
    $params = [$instructor_id, $semester];
    $types = "is";

    if (!empty($report_filter_unit)) {
        $query .= " AND r.unit_code = ?";
        $params[] = $report_filter_unit;
        $types .= "s";
    }
    if (!empty($report_filter_course) && count($courses) > 1) {
        $query .= " AND u.course = ?";
        $params[] = $report_filter_course;
        $types .= "s";
    }

    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $report_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching report students: " . $e->getMessage());
        die("System error. Please try again later.");
    }
} else {
    try {
        $stmt = $conn->prepare("SELECT s.student_id, s.name, r.unit_code, r.unit_name, u.course, c.cat1, e.final_exam,
                               COALESCE(c.cat1, 0) + COALESCE(e.final_exam, 0) AS total_score
                               FROM students s 
                               JOIN registrations r ON s.student_id = r.student_id 
                               JOIN units u ON r.unit_code = u.unit_code 
                               LEFT JOIN coursework c ON s.student_id = c.student_id AND r.unit_code = c.unit_code AND r.semester = c.semester 
                               LEFT JOIN exam_results e ON s.student_id = e.student_id AND r.unit_code = e.unit_code AND r.semester = e.semester 
                               WHERE r.unit_code IN (SELECT unit_code FROM units WHERE instructor_id = ?) 
                               AND r.semester = ?");
        $stmt->bind_param("is", $instructor_id, $semester);
        $stmt->execute();
        $report_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching report students: " . $e->getMessage());
        die("System error. Please try again later.");
    }
}

// Handle export requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $format = $_POST['export_format'] ?? 'csv';
    $filename = "student_report_" . date('Ymd_His');

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Admission No.', 'Name', 'Unit Code', 'Unit Name', 'Department', 'CAT 1', 'Final Exam', 'Total Score']);
        foreach ($report_students as $student) {
            fputcsv($output, [
                $student['student_id'],
                $student['name'],
                $student['unit_code'],
                $student['unit_name'],
                $student['course'],
                $student['cat1'] ?? '-',
                $student['final_exam'] ?? '-',
                $student['total_score'] ?? '-'
            ]);
        }
        fclose($output);
        exit();
    } elseif ($format === 'pdf') {
        require_once 'tcpdf/tcpdf.php'; // Adjusted path to C:\wamp64\www\KASMS\tcpdf\tcpdf.php
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($instructor_name);
        $pdf->SetTitle('Student Report');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $html = '<h1>Student Report</h1><table border="1" cellpadding="4">
                <thead><tr>
                    <th>Admission No.</th>
                    <th>Name</th>
                    <th>Unit Code</th>
                    <th>Unit Name</th>
                    <th>Department</th>
                    <th>CAT 1</th>
                    <th>Final Exam</th>
                    <th>Total Score</th>
                </tr></thead><tbody>';
        foreach ($report_students as $student) {
            $html .= '<tr>
                <td>' . htmlspecialchars($student['student_id']) . '</td>
                <td>' . htmlspecialchars($student['name']) . '</td>
                <td>' . htmlspecialchars($student['unit_code']) . '</td>
                <td>' . htmlspecialchars($student['unit_name']) . '</td>
                <td>' . htmlspecialchars($student['course']) . '</td>
                <td>' . ($student['cat1'] ?? '-') . '</td>
                <td>' . ($student['final_exam'] ?? '-') . '</td>
                <td>' . ($student['total_score'] ?? '-') . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filename . '.pdf', 'D');
        exit();
    }elseif ($format === 'excel') {
    // Include all necessary PhpSpreadsheet files
    require_once 'PhpSpreadsheet-master/src/PhpSpreadsheet/Writer/BaseWriter.php';
    require_once 'PhpSpreadsheet-master/src/PhpSpreadsheet/Spreadsheet.php';
    require_once 'PhpSpreadsheet-master/src/PhpSpreadsheet/Writer/IWriter.php';
    require_once 'PhpSpreadsheet-master/src/PhpSpreadsheet/Writer/Xlsx.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Student Report');
    
    // Headers
    $sheet->setCellValue('A1', 'Admission No.');
    $sheet->setCellValue('B1', 'Name');
    $sheet->setCellValue('C1', 'Unit Code');
    $sheet->setCellValue('D1', 'Unit Name');
    $sheet->setCellValue('E1', 'Department');
    $sheet->setCellValue('F1', 'CAT 1');
    $sheet->setCellValue('G1', 'Final Exam');
    $sheet->setCellValue('H1', 'Total Score');

    // Data
    $row = 2;
    foreach ($report_students as $student) {
        $sheet->setCellValue('A' . $row, $student['student_id']);
        $sheet->setCellValue('B' . $row, $student['name']);
        $sheet->setCellValue('C' . $row, $student['unit_code']);
        $sheet->setCellValue('D' . $row, $student['unit_name']);
        $sheet->setCellValue('E' . $row, $student['course']);
        $sheet->setCellValue('F' . $row, $student['cat1'] ?? '-');
        $sheet->setCellValue('G' . $row, $student['final_exam'] ?? '-');
        $sheet->setCellValue('H' . $row, $student['total_score'] ?? '-');
        $row++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KASMS Instructor Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --danger: #f72585;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e7f1 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 0 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .sidebar:hover {
            box-shadow: 0 0 30px rgba(0,0,0,0.15);
        }
        
        .sidebar-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-item:hover {
            background: rgba(255,255,255,0.15);
            border-left: 3px solid white;
        }
        
        .sidebar-item.active {
            background: rgba(255,255,255,0.2);
            border-left: 3px solid white;
        }
        
        .card {
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            background: white;
            overflow: hidden;
            border: none;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .profile-card {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.3);
        }
        
        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #4895ef 0%, #4361ee 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.3);
        }
        
        .btn-primary {
            background: var(--primary);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            transition: all 0.3s ease;
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .section { 
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .section.visible { 
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-img {
            width: 120px;
            height: 120px;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }
        
        .header {
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 16px;
        }
        
        .table-container {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-container table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table-container thead {
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
        }
        
        .table-container th {
            padding: 15px 20px;
            font-weight: 500;
        }
        
        .table-container td {
            padding: 12px 20px;
            border-bottom: 1px solid #eef2f7;
        }
        
        .table-container tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-container tbody tr:hover {
            background-color: #f8f9ff;
        }
        
        .unit-chip {
            background: #e0e7ff;
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 1000;
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: #4ade80;
            color: white;
        }
        
        .notification.error {
            background: #f87171;
            color: white;
        }
        
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background: var(--primary);
            color: white;
        }
        
        .badge-secondary {
            background: var(--accent);
            color: white;
        }
        
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.4);
            z-index: 50;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .floating-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.5);
        }
        
        .semester-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .print-table {
                width: 100%;
                font-size: 10pt;
            }
            body {
                background: white;
                margin: 0;
            }
            .table-container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body class="flex min-h-screen">
    <!-- Sidebar -->
    <div class="w-64 sidebar text-white h-full fixed p-4 flex flex-col no-print">
        <div class="text-2xl font-bold mb-6 mt-2 flex items-center gap-2">
            <i class="fas fa-graduation-cap"></i>
            <span>KASMS Portal</span>
        </div>
        
        <div class="flex-1 flex flex-col gap-1">
            <a onclick="toggleSection('dashboard')" class="sidebar-item py-3 px-4 rounded-lg flex items-center gap-3 cursor-pointer active">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a onclick="toggleSection('unit-registration')" class="sidebar-item py-3 px-4 rounded-lg flex items-center gap-3 cursor-pointer">
                <i class="fas fa-book w-5 text-center"></i>
                <span>Register Units</span>
            </a>
            <a onclick="toggleSection('view-units')" class="sidebar-item py-3 px-4 rounded-lg flex items-center gap-3 cursor-pointer">
                <i class="fas fa-eye w-5 text-center"></i>
                <span>View Units</span>
            </a>
            <a onclick="toggleSection('marks')" class="sidebar-item py-3 px-4 rounded-lg flex items-center gap-3 cursor-pointer">
                <i class="fas fa-edit w-5 text-center"></i>
                <span>Update Marks</span>
            </a>
            <a onclick="toggleSection('reports')" class="sidebar-item py-3 px-4 rounded-lg flex items-center gap-3 cursor-pointer">
                <i class="fas fa-chart-bar w-5 text-center"></i>
                <span>Reports</span>
            </a>
        </div>
        
        <div class="mt-auto pt-4 border-t border-white/20">
            <a href="logout.php?token=<?php echo bin2hex(random_bytes(16)); ?>" class="block py-3 px-4 rounded-lg hover:bg-white/10 flex items-center gap-3 text-red-100 hover:text-white">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-64 p-6 w-full">
        <header class="header p-5 mb-6 flex justify-between items-center text-white rounded-xl no-print">
            <div>
                <h1 class="text-2xl font-bold">Instructor Dashboard</h1>
                <p class="opacity-90">Welcome back, <?php echo $instructor_name; ?></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">3</span>
                </div>
                <div class="h-10 w-10 rounded-full bg-white flex items-center justify-center text-primary font-bold">
                    <?php echo substr($instructor_name, 0, 1); ?>
                </div>
            </div>
        </header>

        <!-- Dashboard Section -->
        <div class="section visible" id="dashboard">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Dashboard Overview</h2>
                <p class="text-gray-600">Welcome to your personalized instructor dashboard. Here you can manage your courses and student interactions.</p>
            </div>
            
            <div class="dashboard-grid mb-8">
                <div class="stat-card flex flex-col">
                    <div class="text-3xl font-bold mb-2"><?php echo count($registered_units); ?></div>
                    <div class="text-sm opacity-90">Assigned Units</div>
                    <i class="fas fa-book mt-4 text-3xl opacity-20 self-end"></i>
                </div>
                
                <div class="stat-card flex flex-col">
                    <div class="text-3xl font-bold mb-2"><?php echo count($report_students); ?></div>
                    <div class="text-sm opacity-90">Students Enrolled</div>
                    <i class="fas fa-users mt-4 text-3xl opacity-20 self-end"></i>
                </div>
                
                <div class="stat-card flex flex-col">
                    <div class="text-3xl font-bold mb-2">12</div>
                    <div class="text-sm opacity-90">Pending Tasks</div>
                    <i class="fas fa-tasks mt-4 text-3xl opacity-20 self-end"></i>
                </div>
            </div>
            
            <div class="profile-card p-6 mb-8">
                <div class="flex flex-col items-center text-center">
                    <div class="bg-gray-200 border-2 border-dashed rounded-xl w-32 h-32 mb-4 flex items-center justify-center">
                        <i class="fas fa-user text-5xl text-white opacity-70"></i>
                    </div>
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($instructor_details['name']); ?></h3>
                    <p class="text-white/80 mb-4"><?php echo htmlspecialchars($instructor_details['designation']); ?></p>
                    <div class="flex gap-2">
                        <span class="badge badge-primary"><?php echo htmlspecialchars($instructor_details['id_number']); ?></span>
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($semester); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-info-circle text-primary"></i>
                        Personal Information
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-primary">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ID Number</p>
                                <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($instructor_details['id_number']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-primary">
                                <i class="fas fa-venus-mars"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Gender</p>
                                <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($instructor_details['gender']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-primary">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Email</p>
                                <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($instructor_details['email']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-primary">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Phone</p>
                                <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($instructor_details['phone']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-book text-primary"></i>
                        Current Units
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if ($registered_units): ?>
                            <?php foreach ($registered_units as $unit): ?>
                                <div class="detail-card">
                                    <div class="flex justify-between items-center">
                                        <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($unit['unit_name']); ?></h4>
                                        <span class="unit-chip">
                                            <i class="fas fa-bookmark"></i>
                                            <?php echo htmlspecialchars($unit['unit_code']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($unit['course']); ?></p>
                                    <div class="flex gap-2 mt-3">
                                        <span class="text-xs bg-green-100 text-green-800 py-1 px-2 rounded">32 Students</span>
                                        <span class="text-xs bg-purple-100 text-purple-800 py-1 px-2 rounded">Mon/Fri</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-book text-4xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500">No units registered for this semester</p>
                                <button onclick="toggleSection('unit-registration')" class="mt-3 btn-primary px-4 py-2 rounded-md text-sm">Register Units</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center gap-2">
                    <i class="fas fa-tasks text-primary"></i>
                    Recent Activities
                </h3>
                
                <div class="space-y-4">
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-primary">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Updated marks for CSC 401</p>
                            <p class="text-sm text-gray-600">You updated final exam marks for 15 students</p>
                            <p class="text-xs text-gray-500 mt-1">2 hours ago</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">New student registered</p>
                            <p class="text-sm text-gray-600">John Doe registered for your CSC 401 unit</p>
                            <p class="text-xs text-gray-500 mt-1">Yesterday</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-500">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Unit materials updated</p>
                            <p class="text-sm text-gray-600">You uploaded new materials for CSC 402</p>
                            <p class="text-xs text-gray-500 mt-1">2 days ago</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit Registration Section -->
        <div class="section card p-6" id="unit-registration">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Register Units</h2>
                <div class="relative">
                    <span class="semester-badge"><?php echo htmlspecialchars($semester); ?></span>
                    <div class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-sm font-medium">
                        <?php echo count($registered_units); ?> units registered
                    </div>
                </div>
            </div>
            
            <?php if ($unit_registration_message): ?>
                <div class="notification <?php echo strpos($unit_registration_message, 'Error') === false ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($unit_registration_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-blue-800 flex items-center gap-2">
                        <i class="fas fa-info-circle"></i>
                        Select units you'll be teaching this semester. Use the arrows to add or remove units, or double-click to move units.
                    </p>
                </div>
                
                <div class="flex flex-col md:flex-row gap-6">
                    <div class="md:w-1/2">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Available Units</label>
                            <span class="text-sm text-gray-500"><?php echo count($available_units); ?> units</span>
                        </div>
                        <select id="available_units" multiple class="w-full border border-gray-300 rounded-lg shadow-sm h-64 p-2 bg-white" ondblclick="moveUnit('available_units', 'selected_units')">
                            <?php foreach ($available_units as $unit): ?>
                                <option value="<?php echo htmlspecialchars($unit['unit_code']); ?>">
                                    <?php echo htmlspecialchars($unit['unit_name'] . ' (' . $unit['unit_code'] . ', ' . $unit['course'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-center justify-center md:flex-col md:justify-center md:gap-4">
                        <button type="button" onclick="moveUnit('available_units', 'selected_units')" class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primary-dark transition">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="button" onclick="moveUnit('selected_units', 'available_units')" class="w-10 h-10 rounded-full bg-gray-500 text-white flex items-center justify-center hover:bg-gray-600 transition">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>
                    
                    <div class="md:w-1/2">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Selected Units</label>
                            <span class="text-sm text-gray-500"><?php echo count($registered_units); ?> selected</span>
                        </div>
                        <select name="selected_units[]" id="selected_units" multiple class="w-full border border-gray-300 rounded-lg shadow-sm h-64 p-2 bg-white" ondblclick="moveUnit('selected_units', 'available_units')">
                            <?php foreach ($registered_units as $unit): ?>
                                <option value="<?php echo htmlspecialchars($unit['unit_code']); ?>">
                                    <?php echo htmlspecialchars($unit['unit_name'] . ' (' . $unit['unit_code'] . ', ' . $unit['course'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" name="register_units" class="btn-primary px-6 py-2 rounded-lg flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        Save Registration
                    </button>
                </div>
            </form>
        </div>

        <!-- View Units Section -->
        <div class="section card p-6" id="view-units">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Your Teaching Units</h2>
                <div class="relative">
                    <span class="semester-badge"><?php echo htmlspecialchars($semester); ?></span>
                    <div class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-sm font-medium">
                        <?php echo count($registered_units); ?> units
                    </div>
                </div>
            </div>
            
            <?php if ($registered_units): ?>
                <div class="table-container">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="text-left">Unit Code</th>
                                <th class="text-left">Unit Name</th>
                                <th class="text-left">Department</th>
                                <th class="text-left">Enrolled</th>
                                <th class="text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registered_units as $unit): ?>
                                <tr>
                                    <td class="font-medium text-gray-900"><?php echo htmlspecialchars($unit['unit_code']); ?></td>
                                    <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                    <td><?php echo htmlspecialchars($unit['course']); ?></td>
                                    <td>
                                        <span class="inline-flex items-center gap-1">
                                            <i class="fas fa-users text-blue-500"></i>
                                            32
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            <button class="text-blue-500 hover:text-blue-700">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="text-green-500 hover:text-green-700">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="text-red-500 hover:text-red-700">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 rounded-full bg-blue-50 flex items-center justify-center mb-4">
                        <i class="fas fa-book text-4xl text-blue-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Units Registered</h3>
                    <p class="text-gray-600 max-w-md mx-auto mb-6">You haven't registered for any teaching units this semester. Register now to start managing your courses.</p>
                    <button onclick="toggleSection('unit-registration')" class="btn-primary px-6 py-2 rounded-lg inline-flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        Register Units
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Update Marks Section -->
        <div class="section card p-6" id="marks">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Update Student Marks</h2>
                <div class="relative">
                    <span class="semester-badge"><?php echo htmlspecialchars($semester); ?></span>
                    <div class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-sm font-medium">
                        <?php echo count($marks_students); ?> records
                    </div>
                </div>
            </div>
            
            <?php if ($marks_message): ?>
                <div class="notification <?php echo strpos($marks_message, 'Error') === false ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($marks_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($marks_students): ?>
                <form method="POST" class="space-y-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="filter_unit" class="block text-sm font-medium text-gray-700 mb-1">Filter by Unit</label>
                            <select name="filter_unit" id="filter_unit" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                                <option value="">All Units</option>
                                <?php foreach ($registered_units as $unit): ?>
                                    <option value="<?php echo htmlspecialchars($unit['unit_code']); ?>" <?php echo $marks_filter_unit === $unit['unit_code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit['unit_name'] . ' (' . $unit['unit_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (count($courses) > 1): ?>
                            <div>
                                <label for="filter_course" class="block text-sm font-medium text-gray-700 mb-1">Filter by Department</label>
                                <select name="filter_course" id="filter_course" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                                    <option value="">All Departments</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course']); ?>" <?php echo $marks_filter_course === $course['course'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-end">
                            <button type="submit" name="filter_marks" class="btn-primary w-full py-2 rounded-lg flex items-center justify-center gap-2">
                                <i class="fas fa-filter"></i>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
                
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="student_unit" class="block text-sm font-medium text-gray-700 mb-1">Select Student and Unit</label>
                            <select name="student_unit" id="student_unit" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                                <?php foreach ($marks_students as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['student_id'] . '|' . $student['unit_code']); ?>">
                                        <?php echo htmlspecialchars($student['name'] . ' - ' . $student['unit_name'] . ' (' . $student['unit_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="cat1" class="block text-sm font-medium text-gray-700 mb-1">CAT 1 Mark</label>
                                <input type="number" name="cat1" id="cat1" min="0" max="100" placeholder="0-100" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="final_exam" class="block text-sm font-medium text-gray-700 mb-1">Final Exam</label>
                                <input type="number" name="final_exam" id="final_exam" min="0" max="100" placeholder="0-100" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="update_marks" class="btn-primary px-6 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            Update Marks
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 rounded-full bg-blue-50 flex items-center justify-center mb-4">
                        <i class="fas fa-user-graduate text-4xl text-blue-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Students Found</h3>
                    <p class="text-gray-600 max-w-md mx-auto">There are no students enrolled in your units for the current semester. Please check back later or contact the administration.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reports Section -->
        <div class="section card p-6" id="reports">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Student Reports</h2>
                <div class="relative">
                    <span class="semester-badge"><?php echo htmlspecialchars($semester); ?></span>
                    <div class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-sm font-medium">
                        <?php echo count($report_students); ?> students
                    </div>
                </div>
            </div>
            
            <?php if ($report_students): ?>
                <form method="POST" class="space-y-6 mb-6 no-print">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="report_filter_unit" class="block text-sm font-medium text-gray-700 mb-1">Filter by Unit</label>
                            <select name="report_filter_unit" id="report_filter_unit" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                                <option value="">All Units</option>
                                <?php foreach ($registered_units as $unit): ?>
                                    <option value="<?php echo htmlspecialchars($unit['unit_code']); ?>" <?php echo $report_filter_unit === $unit['unit_code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit['unit_name'] . ' (' . $unit['unit_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (count($courses) > 1): ?>
                            <div>
                                <label for="report_filter_course" class="block text-sm font-medium text-gray-700 mb-1">Filter by Department</label>
                                <select name="report_filter_course" id="report_filter_course" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                                    <option value="">All Departments</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course']); ?>" <?php echo $report_filter_course === $course['course'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-end">
                            <button type="submit" name="filter_report" class="btn-primary w-full py-2 rounded-lg flex items-center justify-center gap-2">
                                <i class="fas fa-filter"></i>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="table-container print-table">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="text-left">Admission No.</th>
                                <th class="text-left">Name</th>
                                <th class="text-left">Unit Code</th>
                                <th class="text-left">Unit Name</th>
                                <th class="text-left">Department</th>
                                <th class="text-left">CAT 1</th>
                                <th class="text-left">Final Exam</th>
                                <th class="text-left">Total Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_students as $student): ?>
                                <tr>
                                    <td class="font-medium text-gray-900"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['unit_code']); ?></td>
                                    <td><?php echo htmlspecialchars($student['unit_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['course']); ?></td>
                                    <td><?php echo htmlspecialchars($student['cat1'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($student['final_exam'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($student['total_score'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST" class="mt-6 flex justify-between items-center no-print">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo count($report_students); ?> of <?php echo count($report_students); ?> records
                    </div>
                    <div class="flex gap-2">
                        <select name="export_format" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary">
                            <option value="csv">Export as CSV</option>
                            <option value="pdf">Export as PDF</option>
                            <option value="excel">Export as Excel</option>
                        </select>
                        <button type="submit" name="export" class="btn-outline px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                        <button type="button" onclick="window.print()" class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-print"></i>
                            Print Report
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 rounded-full bg-blue-50 flex items-center justify-center mb-4">
                        <i class="fas fa-file-contract text-4xl text-blue-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Student Reports</h3>
                    <p class="text-gray-600 max-w-md mx-auto">There are no students enrolled in your units for the current semester. Reports will be available once students register for your units.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="floating-btn no-print" onclick="toggleSection('dashboard')">
        <i class="fas fa-home"></i>
    </div>

    <script>
        // Initially show dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Show notification if exists
            const notification = document.querySelector('.notification');
            if (notification) {
                setTimeout(() => {
                    notification.classList.add('show');
                }, 300);
                
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 5000);
            }
        });
        
        function toggleSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('visible');
            });
            
            // Show the selected section
            const section = document.getElementById(sectionId);
            if (section) {
                section.classList.add('visible');
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            
            // Update active state in sidebar
            document.querySelectorAll('.sidebar-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Set active state for the clicked item
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            const itemIndex = Array.from(sidebarItems).findIndex(item => 
                item.getAttribute('onclick').includes(sectionId)
            );
            
            if (itemIndex !== -1) {
                sidebarItems[itemIndex].classList.add('active');
            }
        }
        
        // Move units between select boxes
        function moveUnit(fromId, toId) {
            const fromSelect = document.getElementById(fromId);
            const toSelect = document.getElementById(toId);
            const selectedOptions = Array.from(fromSelect.selectedOptions);
            
            if (selectedOptions.length === 0 && fromId === 'available_units') {
                // If no options are selected for double-click, select the first one
                if (fromSelect.options.length > 0) {
                    fromSelect.options[0].selected = true;
                    selectedOptions.push(fromSelect.options[0]);
                }
            } else if (selectedOptions.length === 0 && fromId === 'selected_units') {
                // If no options are selected for double-click, select the first one
                if (fromSelect.options.length > 0) {
                    fromSelect.options[0].selected = true;
                    selectedOptions.push(fromSelect.options[0]);
                }
            }
            
            selectedOptions.forEach(option => {
                toSelect.appendChild(option);
            });
            
            // Update counters
            updateUnitCounters();
        }
        
        // Update unit counters
        function updateUnitCounters() {
            const availableCount = document.getElementById('available_units').options.length;
            const selectedCount = document.getElementById('selected_units').options.length;
            
            document.querySelector('[for="available_units"] + span').textContent = `${availableCount} units`;
            document.querySelector('[for="selected_units"] + span').textContent = `${selectedCount} selected`;
        }
    </script>
</body>
</html>