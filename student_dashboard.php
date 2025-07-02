<?php
// Start session securely
require_once 'session_handler.php';
initializeSessionHandler();

// Redirect unauthorized users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.php");
    exit();
}

// Database connection with error handling
$conn = new mysqli("p:localhost", "root", "0000", "kasms_db");
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("System maintenance in progress. Please try again later.");
}

$student_id = $_SESSION['student_id'];

// Fetch student details, using course as program_name
$stmt = $conn->prepare("SELECT s.student_id, s.name, s.id_number, s.gender, s.date_of_birth, s.phone_number, s.email_address, s.postal_address, s.total_billed, s.total_paid, s.year_of_study, s.course FROM students s WHERE s.student_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("s", $student_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("An error occurred. Please try again later.");
}
$student = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// Fetch department based on course
$department = '';
if ($student['course']) {
    $stmt = $conn->prepare("SELECT department FROM courses WHERE course_name = ?");
    $stmt->bind_param("s", $student['course']);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $department = $course['department'] ?? 'Not Assigned';
    $stmt->close();
}

// Fallback for year_of_study and course if not in database
$year_of_study = $student['year_of_study'] ?? 1;
$course = $student['course'] ?? 'Program Not Assigned';

// Get current semester/year
$current_semester = '2025-S1';
$current_year = date('Y', strtotime('01:52 PM EAT')); // Set to July 02, 2025

// Fetch registered courses
$stmt = $conn->prepare("SELECT r.course AS program_name, r.semester, r.stage, r.registration_date, GROUP_CONCAT(r.unit_code) as unit_codes, GROUP_CONCAT(r.unit_name) as unit_names FROM registrations r WHERE r.student_id = ? AND r.course = ? GROUP BY r.course, r.semester, r.stage, r.registration_date ORDER BY r.registration_date DESC");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("ss", $student_id, $course);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("An error occurred. Please try again later.");
}
$result = $stmt->get_result();
$registrations = $result->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();

// Fetch available units for registration
$stmt = $conn->prepare("SELECT u.unit_code, u.unit_name FROM units u WHERE u.course = ? AND NOT EXISTS (SELECT 1 FROM registrations r WHERE r.student_id = ? AND r.semester = ? AND r.unit_code = u.unit_code)");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("sss", $course, $student_id, $current_semester);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("An error occurred. Please try again later.");
}
$available_units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();

// Handle registration submission
$registration_success = $registration_error = '';
$registration_disabled = false;
$registration_open = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_units'])) {
    if (!$registration_open) {
        $registration_error = "Registration is currently closed for this semester.";
    } else {
        $selected_units = $_POST['selected_units'] ?? '';
        $stage = $_POST['stage'] ?? "Year {$year_of_study} Semester 1";

        $unit_codes = !empty($selected_units) ? explode(',', $selected_units) : [];

        if (empty($unit_codes)) {
            $registration_error = "Please select at least one unit to register.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO registrations (student_id, course, semester, stage, unit_code, unit_name, registration_date) VALUES (?, ?, ?, ?, ?, (SELECT unit_name FROM units WHERE unit_code = ?), CURDATE())");
                if (!$stmt) {
                    throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                }
                foreach ($unit_codes as $unit_code) {
                    $stmt->bind_param("sssss", $student_id, $course, $current_semester, $stage, $unit_code, $unit_code);
                    if (!$stmt->execute()) {
                        throw new mysqli_sql_exception("Execute failed: " . $stmt->error);
                    }
                }
                $conn->commit();
                $registration_success = "Units registered successfully!";
                $registration_disabled = true;

                $stmt = $conn->prepare("SELECT u.unit_code, u.unit_name FROM units u WHERE u.course = ? AND NOT EXISTS (SELECT 1 FROM registrations r WHERE r.student_id = ? AND r.semester = ? AND r.unit_code = u.unit_code)");
                if (!$stmt) {
                    throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sss", $course, $student_id, $current_semester);
                if (!$stmt->execute()) {
                    throw new mysqli_sql_exception("Execute failed: " . $stmt->error);
                }
                $available_units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                $stmt->close();

                header("Refresh:0");
                exit();
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $registration_error = "Registration failed: " . $e->getMessage();
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }
}

// Fetch coursework
$stmt = $conn->prepare("SELECT c.unit_code, u.unit_name, c.semester, c.cat1 FROM coursework c JOIN units u ON c.unit_code = u.unit_code WHERE c.student_id = ? ORDER BY c.semester DESC");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("s", $student_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("An error occurred. Please try again later.");
}
$coursework = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();

// Fetch exam results
$stmt = $conn->prepare("SELECT e.unit_code, u.unit_name, e.semester, e.final_grade FROM exam_results e JOIN units u ON e.unit_code = u.unit_code WHERE e.student_id = ? ORDER BY e.semester DESC");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("s", $student_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("An error occurred. Please try again later.");
}
$exam_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();

// Fetch payment receipts
$stmt = $conn->prepare("SELECT payment_id, amount, payment_date, receipt_number, service_type FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("s", $student_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("An error occurred. Please try again later.");
}
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();

// Handle password reset
$password_error = $password_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 8 || !preg_match("/[A-Z]/", $new_password) || !preg_match("/[a-z]/", $new_password) || !preg_match("/[0-9]/", $new_password) || $new_password !== $confirm_password) {
        $password_error = "Password must be at least 8 characters with uppercase, lowercase, and numbers, and match confirmation.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND student_id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            die("An error occurred. Please try again later.");
        }
        $stmt->bind_param("ss", $_SESSION['user_id'], $student_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            die("An error occurred. Please try again later.");
        }
        $user = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        if ($user && password_verify($old_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND student_id = ?");
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                die("An error occurred. Please try again later.");
            }
            $stmt->bind_param("sss", $hashed_password, $_SESSION['user_id'], $student_id);
            if ($stmt->execute()) {
                $password_success = "Password reset successfully!";
            } else {
                $password_error = "Failed to update password";
            }
            $stmt->close();
        } else {
            $password_error = "Old password is incorrect";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KASMS Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --accent: #8e44ad;
            --text: #333;
            --background: #f5f7fa;
            --card-bg: #ffffff;
            --border: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--background);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            list-style: none;
            margin-top: 20px;
        }

        .menu-item {
            padding: 5px 0;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .menu-link:hover, .menu-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left: 4px solid var(--secondary);
        }

        .submenu {
            list-style: none;
            padding-left: 30px;
            display: none;
        }

        .submenu.show {
            display: block;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
            transition: all 0.3s ease;
        }

        .header {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            font-weight: bold;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: var(--dark);
        }

        .section {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: none;
        }

        .section.active {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }

        .section-title {
            color: var(--primary);
            font-size: 1.5rem;
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .registration-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .registration-table th {
            background: var(--primary);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .registration-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .registration-table tbody tr:hover {
            background: var(--light);
            transition: background 0.3s ease;
        }

        .new-registration-btn {
            background: var(--success);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-weight: 600;
        }

        .new-registration-btn:hover {
            background: #219653;
        }

        .new-registration-btn[disabled] {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .registration-dropdown {
            display: none;
            position: absolute;
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            width: 600px;
        }

        .registration-dropdown.show {
            display: block;
        }

        .unit-selector {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
        }

        .unit-box {
            background: var(--light);
            padding: 15px;
            border-radius: 6px;
        }

        .unit-select {
            width: 100%;
            height: 150px;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 5px;
        }

        .action-btn {
            background: var(--secondary);
            color: white;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            display: block;
            margin: 10px auto;
            width: 80px;
        }

        .action-btn:hover {
            background: var(--accent);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background: var(--card-bg);
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            z-index: 1000;
        }

        .dropdown-content a {
            color: var(--text);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            transition: background 0.3s ease;
        }

        .dropdown-content a:hover {
            background: var(--light);
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary);
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input[readonly], .form-textarea[readonly] {
            background-color: #f0f0f0;
            cursor: not-allowed;
        }

        .form-input:focus, .form-textarea:focus {
            border-color: var(--secondary);
            outline: none;
        }

        .submit-btn {
            background: var(--success);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .submit-btn:hover {
            background: var(--dark);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.3);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 992px) {
            .menu-toggle {
                display: block;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .registration-dropdown {
                width: 90%;
                left: 5%;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            .section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> KASMS</h2>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="#" class="menu-link active" onclick="showSection('profileSection')">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="toggleSubmenu('academicsSubmenu')">
                        <i class="fas fa-book"></i> Academics
                    </a>
                    <ul id="academicsSubmenu" class="submenu">
                        <li><a href="#" class="menu-link" onclick="showSection('courseRegistrationSection')">Course Registration</a></li>
                        <li><a href="#" class="menu-link" onclick="showSection('courseworkSection')">Coursework</a></li>
                        <li><a href="#" class="menu-link" onclick="showSection('examResultsSection')">Exam Results</a></li>
                        <li><a href="#" class="menu-link" onclick="showSection('academicLeaveSection')">Academic Leave</a></li>
                        <li><a href="#" class="menu-link" onclick="showSection('clearanceSection')">Clearance</a></li>
                        <li><a href="#" class="menu-link" onclick="showSection('surveySection')">Student Survey</a></li>
                        <li><a href="#" class="menu-link" onclick="showSection('examAuditSection')">Exam Audit</a></li>
                    </ul>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="toggleSubmenu('financeSubmenu')">
                        <i class="fas fa-wallet"></i> Finance
                    </a>
                    <ul id="financeSubmenu" class="submenu">
                        <li><a href="#" class="menu-link" onclick="showSection('receiptsSection')">Receipts</a></li>
                        <li><a href="#" class="menu-link" onclick="showSection('feeBalanceSection')">Fee Balance</a></li>
                    </ul>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="showSection('selfServiceSection')">
                        <i class="fas fa-cog"></i> Self Service
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-info">
                    <div class="user-avatar"><?php echo substr($student['name'] ?? 'S', 0, 1); ?></div>
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($student['name'] ?? 'Student'); ?></h3>
                        <p>Student ID: <?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <button class="logout-btn" onclick="location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Log Out
                </button>
            </div>

            <section id="profileSection" class="section active">
                <h2 class="section-title"><i class="fas fa-user"></i> Student Profile</h2>
                <div class="profile-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="profile-card" style="background: var(--card-bg); padding: 20px; border-radius: 10px;">
                        <div class="profile-header" style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                            <div class="profile-img" style="width: 80px; height: 80px; background: linear-gradient(45deg, <?php echo isset($student['gender']) && $student['gender'] === 'Male' ? '#3498db' : '#e74c3c'; ?>, #9b59b6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 2.5rem;">
                                <?php echo substr($student['name'] ?? 'S', 0, 1); ?>
                            </div>
                            <div class="profile-info">
                                <h2><?php echo htmlspecialchars($student['name'] ?? 'Student Name'); ?></h2>
                                <p><?php echo htmlspecialchars($student['id_number'] ?? 'ID Number'); ?></p>
                            </div>
                        </div>
                        <div class="profile-details">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr><td style="padding: 10px; border-bottom: 1px solid var(--border);">Admission No</td><td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></td></tr>
                                <tr><td style="padding: 10px; border-bottom: 1px solid var(--border);">Gender</td><td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></td></tr>
                                <tr><td style="padding: 10px; border-bottom: 1px solid var(--border);">Date of Birth</td><td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo !empty($student['date_of_birth']) ? date('d/m/Y', strtotime($student['date_of_birth'])) : 'N/A'; ?></td></tr>
                                <tr><td style="padding: 10px; border-bottom: 1px solid var(--border);">Email</td><td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($student['email_address'] ?? 'N/A'); ?></td></tr>
                                <tr><td style="padding: 10px; border-bottom: 1px solid var(--border);">Phone</td><td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($student['phone_number'] ?? 'N/A'); ?></td></tr>
                                <tr><td style="padding: 10px; border-bottom: 1px solid var(--border);">Postal Address</td><td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($student['postal_address'] ?? 'N/A'); ?></td></tr>
                                <tr><td style="padding: 10px; border-bottom: 1px solid var(--border);">Program</td><td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($course); ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="profile-card" style="background: var(--card-bg); padding: 20px; border-radius: 10px;">
                        <h3 class="section-title"><i class="fas fa-wallet"></i> Financial Summary</h3>
                        <div class="finance-summary" style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            <div class="finance-card" style="background: var(--light); padding: 15px; border-radius: 8px; text-align: center;">
                                <h3><i class="fas fa-file-invoice"></i> Total Billed</h3>
                                <div class="amount" style="font-size: 1.5rem; font-weight: bold; color: var(--primary);">Ksh <?php echo number_format($student['total_billed'] ?? 0, 2); ?></div>
                                <div class="label">All time charges</div>
                            </div>
                            <div class="finance-card" style="background: var(--light); padding: 15px; border-radius: 8px; text-align: center;">
                                <h3><i class="fas fa-receipt"></i> Total Paid</h3>
                                <div class="amount" style="font-size: 1.5rem; font-weight: bold; color: var(--primary);">Ksh <?php echo number_format($student['total_paid'] ?? 0, 2); ?></div>
                                <div class="label">Payment history</div>
                            </div>
                            <div class="finance-card" style="background: var(--light); padding: 15px; border-radius: 8px; text-align: center;">
                                <h3><i class="fas fa-scale-balanced"></i> Balance</h3>
                                <div class="amount" style="font-size: 1.5rem; font-weight: bold; color: var(--danger);">Ksh <?php echo ($student['total_billed'] - $student['total_paid'] < 0 ? '-' : '') . number_format(abs($student['total_billed'] - $student['total_paid'] ?? 0), 2); ?></div>
                                <div class="label">Current amount due</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="courseRegistrationSection" class="section">
                <div class="registration-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div class="registration-title">
                        <i class="fas fa-book"></i>
                        <h2>Course Registration</h2>
                    </div>
                </div>
                <?php if ($registration_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $registration_success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($registration_error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $registration_error; ?>
                    </div>
                <?php endif; ?>
                <h3><i class="fas fa-list"></i> Course Registration List</h3>
                <table class="registration-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Programme Name</th>
                            <th>Semester</th>
                            <th>Nominal Roll</th>
                            <th>
                                <div class="dropdown">
                                    <button class="new-registration-btn" onclick="toggleRegistrationDropdown()" <?php echo $registration_disabled ? 'disabled' : ''; ?>>
                                        + New Registration
                                    </button>
                                    <div class="registration-dropdown" id="registrationDropdown">
                                        <form method="post" action="" id="registrationForm">
                                            <div class="form-group">
                                                <label class="form-label">Stage</label>
                                                <input type="text" class="form-input" name="stage" value="Year <?php echo $year_of_study; ?> Semester 1" required>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Program</label>
                                                <input type="text" class="form-input" name="course" value="<?php echo htmlspecialchars($course); ?>" readonly>
                                            </div>
                                            <div class="unit-selector">
                                                <div class="unit-box">
                                                    <h4><i class="fas fa-list"></i> Available Units</h4>
                                                    <p class="unit-count">Units: <span id="availableCount"><?php echo count($available_units); ?></span></p>
                                                    <select id="availableUnitsSelect" class="unit-select" multiple size="8">
                                                        <?php foreach ($available_units as $unit): ?>
                                                            <option value="<?php echo htmlspecialchars($unit['unit_code']); ?>">
                                                                <?php echo htmlspecialchars($unit['unit_code'] . ' - ' . $unit['unit_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="unit-actions">
                                                    <button class="action-btn" onclick="addUnit(event)" id="addUnit">
                                                        <i class="fas fa-arrow-right"></i> Add
                                                    </button>
                                                    <button class="action-btn" onclick="removeUnit(event)" id="removeUnit">
                                                        <i class="fas fa-arrow-left"></i> Remove
                                                    </button>
                                                    <button class="action-btn" onclick="clearSelection(event)" id="clearSelection">
                                                        <i class="fas fa-times"></i> Clear
                                                    </button>
                                                </div>
                                                <div class="unit-box">
                                                    <h4><i class="fas fa-check-circle"></i> Chosen Units</h4>
                                                    <p class="unit-count">Units: <span id="selectedCount">0</span> <span id="minUnitsWarning" style="color: var(--danger); display: none;">(Min 6 required)</span></p>
                                                    <select id="selectedUnitsSelect" class="unit-select" multiple size="8"></select>
                                                </div>
                                            </div>
                                            <div class="submit-container">
                                                <input type="hidden" name="selected_units" id="selectedUnitsHidden">
                                                <button type="submit" name="register_units" class="submit-btn" id="registerSubmit" disabled>
                                                    <i class="fas fa-paper-plane"></i> Submit Registration
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($registrations) && !empty($registrations)): ?>
                            <?php foreach ($registrations as $index => $reg): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($department); ?></td>
                                    <td><?php echo htmlspecialchars($reg['semester'] ?? 'N/A'); ?></td>
                                    <td><?php echo !empty($reg['registration_date']) ? date('d/m/Y', strtotime($reg['registration_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="dropdown-btn">Actions</button>
                                            <div class="dropdown-content">
                                                <?php
                                                $payment_percentage = ($student['total_billed'] > 0) ? ($student['total_paid'] / $student['total_billed']) * 100 : 0;
                                                if ($payment_percentage >= 98):
                                                ?>
                                                    <a href="generate_exam_card.php?semester=<?php echo urlencode($reg['semester'] ?? ''); ?>">Exam Card</a>
                                                <?php else: ?>
                                                    <a href="#" onclick="alert('You must pay at least 98% of fees to download the exam card.');">Exam Card</a>
                                                <?php endif; ?>
                                                <a href="#" onclick="showRegistrationDetails(<?php echo $index; ?>)">View Registration</a>
                                                <a href="#" onclick="showUnits(<?php echo $index; ?>)">View Units</a>
                                                <a href="#" onclick="alert('Please visit the finance office to delete this registration.');">Delete Registration</a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="regDetails<?php echo $index; ?>" style="display:none;">
                                    <td colspan="5">
                                        <div class="profile-card" style="background: var(--card-bg); padding: 15px; border-radius: 8px;">
                                            <h4>Registration Details</h4>
                                            <p><strong>Program:</strong> <?php echo htmlspecialchars($reg['program_name'] ?? 'N/A'); ?></p>
                                            <p><strong>Semester:</strong> <?php echo htmlspecialchars($reg['semester'] ?? 'N/A'); ?></p>
                                            <p><strong>Stage:</strong> <?php echo htmlspecialchars($reg['stage'] ?? 'N/A'); ?></p>
                                            <p><strong>Units Taken:</strong> <?php echo !empty($reg['unit_codes']) ? count(explode(',', $reg['unit_codes'])) : 0; ?></p>
                                            <p><strong>Registration Date:</strong> <?php echo !empty($reg['registration_date']) ? date('d/m/Y', strtotime($reg['registration_date'])) : 'N/A'; ?></p>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="units<?php echo $index; ?>" style="display:none;">
                                    <td colspan="5">
                                        <div class="profile-card" style="background: var(--card-bg); padding: 15px; border-radius: 8px;">
                                            <h4>Registered Units</h4>
                                            <ul style="list-style: none;">
                                                <?php
                                                $unit_codes = !empty($reg['unit_codes']) ? explode(',', $reg['unit_codes']) : [];
                                                $unit_names = !empty($reg['unit_names']) ? explode(',', $reg['unit_names']) : [];
                                                foreach ($unit_codes as $i => $code):
                                                ?>
                                                    <li style="margin-bottom: 10px;"><?php echo htmlspecialchars($code . ' - ' . ($unit_names[$i] ?? 'N/A')); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No registrations found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section id="courseworkSection" class="section">
                <h2 class="section-title"><i class="fas fa-book"></i> Coursework</h2>
                <table class="registration-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Unit Code</th>
                            <th>Unit Name</th>
                            <th>Semester</th>
                            <th>CAT 1 Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($coursework) && !empty($coursework)): ?>
                            <?php foreach ($coursework as $index => $cw): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($cw['unit_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cw['unit_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cw['semester'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cw['cat1'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No coursework records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section id="examResultsSection" class="section">
                <h2 class="section-title"><i class="fas fa-graduation-cap"></i> Exam Results</h2>
                <table class="registration-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Programme Name</th>
                            <th>Semester</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($registrations) && !empty($registrations)): ?>
                            <?php foreach ($registrations as $index => $reg): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($reg['program_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['semester'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="generate_exam_results.php?semester=<?php echo urlencode($reg['semester'] ?? ''); ?>" class="submit-btn">Download Results</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No registrations found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section id="academicLeaveSection" class="section">
                <h2 class="section-title"><i class="fas fa-calendar-times"></i> Academic Leave</h2>
                <form method="post" action="submit_academic_leave.php">
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <input type="text" class="form-input" name="semester" value="<?php echo $current_semester; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason for Leave</label>
                        <textarea class="form-textarea" name="reason" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-input" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-input" name="end_date" required>
                    </div>
                    <div class="submit-container" style="text-align: center;">
                        <button type="submit" class="submit-btn">Submit Leave Request</button>
                    </div>
                </form>
            </section>

            <section id="clearanceSection" class="section">
                <h2 class="section-title"><i class="fas fa-check-circle"></i> Clearance</h2>
                <form method="post" action="submit_clearance.php">
                    <div class="form-group">
                        <label class="form-label">Reason for Clearance</label>
                        <textarea class="form-textarea" name="reason" required></textarea>
                    </div>
                    <div class="submit-container" style="text-align: center;">
                        <button type="submit" class="submit-btn">Submit Clearance Request</button>
                    </div>
                </form>
            </section>

            <section id="surveySection" class="section">
                <h2 class="section-title"><i class="fas fa-poll"></i> Student Survey</h2>
                <p>Complete the survey for <?php echo $current_semester; ?>.</p>
                <a href="submit_survey.php?semester=<?php echo urlencode($current_semester); ?>" class="submit-btn">Take Survey</a>
            </section>

            <section id="examAuditSection" class="section">
                <h2 class="section-title"><i class="fas fa-file-alt"></i> Exam Audit</h2>
                <p>Exam audit functionality will be implemented soon.</p>
            </section>

            <section id="receiptsSection" class="section">
                <h2 class="section-title"><i class="fas fa-receipt"></i> Receipts</h2>
                <?php if (empty($payments)): ?>
                    <div class="alert">
                        <i class="fas fa-info-circle"></i> No payment records found.
                    </div>
                <?php else: ?>
                    <table class="registration-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Receipt #</th>
                                <th>Amount</th>
                                <th>Service Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['receipt_number'] ?? 'N/A'); ?></td>
                                    <td>Ksh <?php echo number_format($payment['amount'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['service_type'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section id="feeBalanceSection" class="section">
                <h2 class="section-title"><i class="fas fa-wallet"></i> Fee Balance</h2>
                <a href="generate_fee_statement.php" class="submit-btn">Download Fee Statement</a>
            </section>

            <section id="selfServiceSection" class="section">
                <h2 class="section-title"><i class="fas fa-cog"></i> Account Settings</h2>
                <?php if ($password_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $password_success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($password_error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $password_error; ?>
                    </div>
                <?php endif; ?>
                <div class="profile-card" style="background: var(--card-bg); padding: 20px; border-radius: 10px;">
                    <h3 class="section-title"><i class="fas fa-key"></i> Change Password</h3>
                    <form method="post" action="" class="password-form">
                        <div class="form-group">
                            <label class="form-label" for="old_password">Current Password</label>
                            <input type="password" class="form-input" id="old_password" name="old_password" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="new_password">New Password</label>
                            <input type="password" class="form-input" id="new_password" name="new_password" required>
                            <small style="color: var(--text); font-size: 0.9rem;">Must be at least 8 characters with uppercase, lowercase, and numbers</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-input" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="submit-container" style="text-align: center;">
                            <button type="submit" name="reset_password" class="submit-btn">
                                <i class="fas fa-sync-alt"></i> Reset Password
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.getElementById('availableUnitsSelect').addEventListener('dblclick', addUnit);
        document.getElementById('selectedUnitsSelect').addEventListener('dblclick', removeUnit);

        function updateUnitCount() {
            const selectedCount = document.getElementById('selectedUnitsSelect').options.length;
            document.getElementById('selectedCount').textContent = selectedCount;
            
            const minWarning = document.getElementById('minUnitsWarning');
            const submitBtn = document.getElementById('registerSubmit');
            
            if (selectedCount < 6) {
                minWarning.style.display = 'inline';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                minWarning.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }

        function clearSelection(event) {
            event.preventDefault();
            const selected = document.getElementById('selectedUnitsSelect');
            const available = document.getElementById('availableUnitsSelect');
            
            while (selected.options.length > 0) {
                available.appendChild(selected.options[0]);
            }
            
            updateUnitCount();
            updateAvailableCount();
        }

        function updateAvailableCount() {
            const availableCount = document.getElementById('availableUnitsSelect').options.length;
            document.getElementById('availableCount').textContent = availableCount;
        }

        function addUnit(event) {
            event.preventDefault();
            const from = document.getElementById('availableUnitsSelect');
            const to = document.getElementById('selectedUnitsSelect');
            
            const selectedOptions = event.type === 'dblclick' 
                ? [from.options[from.selectedIndex]]
                : Array.from(from.selectedOptions);
            
            selectedOptions.forEach(option => {
                to.appendChild(option);
            });
            
            updateUnitCount();
            updateAvailableCount();
        }

        function removeUnit(event) {
            event.preventDefault();
            const from = document.getElementById('selectedUnitsSelect');
            const to = document.getElementById('availableUnitsSelect');
            
            const selectedOptions = event.type === 'dblclick' 
                ? [from.options[from.selectedIndex]]
                : Array.from(from.selectedOptions);
            
            selectedOptions.forEach(option => {
                to.appendChild(option);
            });
            
            updateUnitCount();
            updateAvailableCount();
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateUnitCount();
            updateAvailableCount();
        });

        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (e.target.querySelector('[name="register_units"]')) {
                const selected = document.getElementById('selectedUnitsSelect');
                const hidden = document.getElementById('selectedUnitsHidden');
                const units = Array.from(selected.options).map(option => option.value);
                hidden.value = units.join(',');
                if (units.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one unit to register');
                }
            }
        });

        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));
            document.getElementById(sectionId).classList.add('active');
            document.querySelectorAll('.menu-link').forEach(link => link.classList.remove('active'));
            document.querySelector(`[onclick*="showSection('${sectionId}')"]`)?.classList.add('active');
        }

        function toggleSubmenu(submenuId) {
            const submenu = document.getElementById(submenuId);
            submenu.classList.toggle('show');
        }

        function toggleRegistrationDropdown() {
            const dropdown = document.getElementById('registrationDropdown');
            if (!<?php echo $registration_disabled ? 'true' : 'false'; ?>) {
                dropdown.classList.toggle('show');
            }
        }

        function showRegistrationDetails(index) {
            const details = document.getElementById(`regDetails${index}`);
            details.style.display = details.style.display === 'none' ? 'table-row' : 'none';
        }

        function showUnits(index) {
            const units = document.getElementById(`units${index}`);
            units.style.display = units.style.display === 'none' ? 'table-row' : 'none';
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('registrationDropdown');
            const button = document.querySelector('.new-registration-btn');
            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>