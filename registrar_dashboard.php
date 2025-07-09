<?php
require_once 'session_handler.php';
// Initialize session handler to manage user session
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'registrar') {
    header("Location: index.php");
    exit();
}

// Secure database connection
$conn = mysqli_connect("p:localhost", "root", "", "kasms_db");
if (!$conn) {
    error_log("Connection failed at " . date('Y-m-d H:i:s') . ": " . mysqli_connect_error());
    die("An error occurred. Please try again or contact support.");
}

$success_message = '';
$error_message = '';
$students = [];

// Fetch courses (only names since course_id is removed)
$course_query = "SELECT course FROM courses";
$course_result = $conn->query($course_query);
$course_options = [];
if ($course_result) {
    while ($row = $course_result->fetch_assoc()) {
        $course_options[] = $row['course'];
    }
} else {
    error_log("Failed to fetch courses at " . date('Y-m-d H:i:s') . ": " . $conn->error);
    echo "<script>showPopup('Error fetching courses. Please contact support.', true);</script>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enroll_student'])) {
        $name = trim($_POST['name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $year_of_study = (int)($_POST['year_of_study'] ?? 0);
        $id_number = trim($_POST['id_number'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $phone_number = trim($_POST['phone_number'] ?? '');
        $email_address = trim($_POST['email_address'] ?? '');
        $postal_address = trim($_POST['postal_address'] ?? '');
        $disability = trim($_POST['disability'] ?? 'No');

        if (empty($name) || empty($course) || $year_of_study <= 0 || empty($id_number) || empty($gender) || empty($date_of_birth) || empty($phone_number) || empty($email_address) || empty($postal_address) || empty($disability)) {
            echo "<script>showPopup('All fields are required, and Year of Study must be a positive number.', true);</script>";
        } elseif (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            echo "<script>showPopup('Invalid email address.', true);</script>";
        } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
            echo "<script>showPopup('Gender must be \\'Male\\', \\'Female\\', or \\'Other\\'.', true);</script>";
        } elseif (!in_array($disability, ['Yes', 'No'])) {
            echo "<script>showPopup('Disability must be \\'Yes\\' or \\'No\\'.', true);</script>";
        } elseif (!in_array($course, $course_options)) {
            echo "<script>showPopup('Invalid course selected.', true);</script>";
        } else {
            $max_stmt = $conn->prepare("SELECT MAX(student_id) as max_id FROM students");
            $max_stmt->execute();
            $max_result = $max_stmt->get_result()->fetch_assoc();
            $max_stmt->close();

            $base_number = 1000;
            if ($max_result['max_id']) {
                $current_max = (int)substr($max_result['max_id'], 3);
                $base_number = max($base_number, $current_max + 1);
            }

            $max_attempts = 1000;
            $attempt = 0;
            $student_id = "ADM" . str_pad($base_number, 4, "0", STR_PAD_LEFT);
            do {
                $check_stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
                $check_stmt->bind_param("s", $student_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($result->num_rows > 0) {
                    $base_number++;
                    $student_id = "ADM" . str_pad($base_number, 4, "0", STR_PAD_LEFT);
                }
                $attempt++;
                if ($attempt >= $max_attempts) {
                    echo "<script>showPopup('Unable to generate a unique admission number after multiple attempts.', true);</script>";
                    break;
                }
            } while ($result->num_rows > 0);
            $check_stmt->close();

            if (empty($error_message)) {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO students (student_id, name, year_of_study, id_number, gender, date_of_birth, phone_number, email_address, postal_address, disability, enrollment_year, course) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, YEAR(CURDATE()), ?)");
                    if ($stmt === false) {
                        throw new Exception("Prepare failed (students): " . $conn->error);
                    }
                    $stmt->bind_param("ssisssissss", $student_id, $name, $year_of_study, $id_number, $gender, $date_of_birth, $phone_number, $email_address, $postal_address, $disability, $course);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to enroll student: " . $stmt->error);
                    }
                    $stmt->close();

                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, student_id) VALUES (?, ?, 'student', ?)");
                    if ($stmt === false) {
                        throw new Exception("Prepare failed (users): " . $conn->error);
                    }
                    $hashed_password = password_hash($id_number, PASSWORD_DEFAULT);
                    $stmt->bind_param("sss", $student_id, $hashed_password, $student_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create user: " . $stmt->error);
                    }
                    $stmt->close();

                    $conn->commit();
                    echo "<script>showPopup('Student enrolled successfully! Admission Number: $student_id, Password: $id_number');</script>";
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error at " . date('Y-m-d H:i:s') . ": " . $e->getMessage());
                    echo "<script>showPopup('An error occurred. Please try again or contact support.', true);</script>";
                }
            }
        }
    } elseif (isset($_POST['update_student'])) {
        $student_id = $_POST['student_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $year_of_study = (int)($_POST['year_of_study'] ?? 0);
        $id_number = trim($_POST['id_number'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $phone_number = trim($_POST['phone_number'] ?? '');
        $email_address = trim($_POST['email_address'] ?? '');
        $postal_address = trim($_POST['postal_address'] ?? '');
        $disability = trim($_POST['disability'] ?? 'No');

        if (empty($name) || empty($course) || $year_of_study <= 0 || empty($id_number) || empty($gender) || empty($date_of_birth) || empty($phone_number) || empty($email_address) || empty($postal_address) || empty($disability)) {
            echo "<script>showPopup('All fields are required, and Year of Study must be a positive number.', true);</script>";
        } elseif (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            echo "<script>showPopup('Invalid email address.', true);</script>";
        } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
            echo "<script>showPopup('Gender must be \\'Male\\', \\'Female\\', or \\'Other\\'.', true);</script>";
        } elseif (!in_array($disability, ['Yes', 'No'])) {
            echo "<script>showPopup('Disability must be \\'Yes\\' or \\'No\\'.', true);</script>";
        } elseif (!in_array($course, $course_options)) {
            echo "<script>showPopup('Invalid course selected.', true);</script>";
        } else {
            $stmt = $conn->prepare("UPDATE students SET name = ?, year_of_study = ?, id_number = ?, gender = ?, date_of_birth = ?, phone_number = ?, email_address = ?, postal_address = ?, disability = ?, course = ? WHERE student_id = ?");
            if ($stmt === false) {
                echo "<script>showPopup('An error occurred. Please try again or contact support.', true);</script>";
            } else {
                $stmt->bind_param("sisssisssss", $name, $year_of_study, $id_number, $gender, $date_of_birth, $phone_number, $email_address, $postal_address, $disability, $course, $student_id);
                if ($stmt->execute()) {
                    echo "<script>showPopup('Student details updated successfully!');</script>";
                } else {
                    error_log("Update failed at " . date('Y-m-d H:i:s') . ": " . $stmt->error);
                    echo "<script>showPopup('An error occurred. Please try again or contact support.', true);</script>";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch students with filters and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$filter_option = isset($_GET['filter_option']) ? $_GET['filter_option'] : 'All';
$filter_value = isset($_GET['filter_value']) ? trim($_GET['filter_value']) : '';

$query = "SELECT s.* FROM students s";
$where = [];
$params = [];
$types = '';

if ($filter_option === 'D.Clinical Medicine') {
    $where[] = "s.course = ?";
    $params[] = 'D.Clinical Medicine';
    $types .= 's';
} elseif ($filter_option === 'D.Nursing') {
    $where[] = "s.course = ?";
    $params[] = 'D.Nursing';
    $types .= 's';
} elseif ($filter_option === 'Admission Number' && !empty($filter_value)) {
    $where[] = "s.student_id = ?";
    $params[] = $filter_value;
    $types .= 's';
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count total students for pagination
$total_query = "SELECT COUNT(*) as total FROM students s";
if (!empty($where)) {
    $total_query .= " WHERE " . implode(" AND ", $where);
}
$total_stmt = $conn->prepare($total_query);
if (!empty($params) && !empty($where)) {
    $total_stmt->bind_param(substr($types, 0, strlen($types) - 2), ...array_slice($params, 0, count($where)));
}
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_students = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_students / $limit);
$total_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KASMS Registrar Dashboard</title>
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
            cursor: pointer;
        }

        .menu-link:hover, .menu-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left: 4px solid var(--secondary);
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

        .user-info {
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary);
        }

        .form-input, .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
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

        .student-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .student-table th,
        .student-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .student-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        .student-table td input,
        .student-table td select {
            width: 100%;
            box-sizing: border-box;
        }

        .filter-section {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-section select,
        .filter-section input,
        .filter-section button {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
        }

        .search-bar {
            display: none;
        }

        .search-bar.active {
            display: inline-block;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .pagination a {
            padding: 10px 15px;
            text-decoration: none;
            color: var(--primary);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
        }

        .pagination a:hover {
            background: var(--light);
        }

        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--success);
            color: white;
            padding: 15px 25px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            font-size: 1rem;
            text-align: center;
            max-width: 90%;
            word-wrap: break-word;
        }

        .popup-error {
            background: var(--danger);
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
                z-index: 2000;
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
            .student-table th,
            .student-table td {
                padding: 8px;
            }
            .filter-section {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
    <script>
        // Popup handling
        let popupInitialized = false;
        window.showPopupQueue = [];

        function showPopup(message, isError = false) {
            if (!popupInitialized) {
                window.showPopupQueue.push({ message, isError });
                return;
            }
            const popup = document.getElementById('popup');
            if (!popup) {
                console.error('Popup element not found');
                return;
            }
            popup.textContent = message;
            popup.classList.toggle('popup-error', isError);
            popup.style.display = 'block';
            setTimeout(() => {
                popup.style.display = 'none';
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const popup = document.getElementById('popup');
            if (popup) {
                popupInitialized = true;
                window.showPopupQueue.forEach(({ message, isError }) => {
                    showPopup(message, isError);
                });
                window.showPopupQueue = [];
            } else {
                console.error('Popup element not found on DOM load');
            }
        });

        // Section toggling
        let homeToggle = false;
        let currentSection = 'homeSection';

        function toggleSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });

            if (sectionId === 'homeSection') {
                homeToggle = !homeToggle;
                document.getElementById('profileSection').style.display = homeToggle ? 'block' : 'none';
                if (homeToggle) document.getElementById(sectionId).classList.add('active');
            } else {
                homeToggle = false;
                document.getElementById('profileSection').style.display = 'none';
                document.getElementById(sectionId).classList.add('active');
            }

            currentSection = sectionId;
            document.querySelectorAll('.menu-link').forEach(link => link.classList.remove('active'));
            document.querySelector(`[onclick*="toggleSection('${sectionId}')"]`)?.classList.add('active');
        }

        // Search bar toggle
        function toggleSearchBar() {
            const filterOption = document.getElementsByName('filter_option')[0].value;
            const searchBar = document.getElementById('search-bar');
            searchBar.classList.toggle('active', filterOption === 'Admission Number');
            if (filterOption !== 'Admission Number') {
                searchBar.value = '';
            }
        }

        // Sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Handle clicks outside sidebar on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 992 && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Initialize
        window.onload = function() {
            toggleSection('homeSection');
            toggleSearchBar();
            document.querySelectorAll('.submit-btn, .logout-btn, .menu-link, .pagination a').forEach(button => {
                button.style.pointerEvents = 'auto';
                button.style.cursor = 'pointer';
            });
        };
    </script>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-user-shield"></i> KASMS Registrar</h2>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="#" class="menu-link active" onclick="toggleSection('homeSection')">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="toggleSection('enrollSection')">
                        <i class="fas fa-user-plus"></i> Enroll Students
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="toggleSection('reportsSection')">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="user-info">
                    <div class="user-avatar">R</div>
                    <div>
                        <h3>Registrar</h3>
                        <p>User ID: <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
            </div>

            <section id="homeSection" class="section active">
                <div id="profileSection" class="profile-section">
                    <h2 class="section-title"><i class="fas fa-user"></i> Registrar Profile</h2>
                    <div class="profile-card" style="background: var(--card-bg); padding: 20px; border-radius: 10px;">
                        <div class="profile-header" style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                            <div class="profile-img" style="width: 80px; height: 80px; background: linear-gradient(45deg, #3498db, #9b59b6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 2.5rem;">
                                R
                            </div>
                            <div class="profile-info">
                                <h3>Registrar</h3>
                                <p>User ID: <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <p style="margin-top: 15px;">Welcome to the Registrar Dashboard. Manage student enrollments and generate reports efficiently.</p>
                    </div>
                </div>
            </section>

            <section id="enrollSection" class="section">
                <h2 class="section-title"><i class="fas fa-user-plus"></i> Enroll New Student</h2>
                <form id="enrollForm" method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="name">Full Name</label>
                        <input type="text" class="form-input" name="name" id="name" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="course">Course</label>
                        <select class="form-select" name="course" id="course" required>
                            <option value="">Select Course</option>
                            <?php foreach ($course_options as $course): ?>
                                <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="year_of_study">Year of Study</label>
                        <input type="number" class="form-input" name="year_of_study" id="year_of_study" placeholder="Year of Study" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="id_number">ID Number</label>
                        <input type="text" class="form-input" name="id_number" id="id_number" placeholder="ID Number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender">Gender</label>
                        <select class="form-select" name="gender" id="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input type="date" class="form-input" name="date_of_birth" id="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone_number">Phone Number</label>
                        <input type="text" class="form-input" name="phone_number" id="phone_number" placeholder="Phone Number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email_address">Email Address</label>
                        <input type="email" class="form-input" name="email_address" id="email_address" placeholder="Email Address" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="postal_address">Postal Address</label>
                        <input type="text" class="form-input" name="postal_address" id="postal_address" placeholder="Postal Address" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="disability">Disability Status</label>
                        <select class="form-select" name="disability" id="disability" required>
                            <option value="">Select Disability Status</option>
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                    <div class="form-group" style="text-align: center;">
                        <button type="submit" name="enroll_student" class="submit-btn">Enroll Student</button>
                    </div>
                </form>
            </section>

            <section id="reportsSection" class="section">
                <h2 class="section-title"><i class="fas fa-chart-bar"></i> Reports</h2>
                <div class="filter-section">
                    <form method="GET" onsubmit="if(document.getElementsByName('filter_option')[0].value === 'Admission Number' && !document.getElementById('search-bar').value) { alert('Please enter an admission number.'); return false; }">
                        <select class="form-select" name="filter_option" onchange="toggleSearchBar(); this.form.submit()">
                            <option value="All" <?php echo $filter_option === 'All' ? 'selected' : ''; ?>>All Courses</option>
                            <option value="D.Clinical Medicine" <?php echo $filter_option === 'D.Clinical Medicine' ? 'selected' : ''; ?>>D.Clinical Medicine</option>
                            <option value="D.Nursing" <?php echo $filter_option === 'D.Nursing' ? 'selected' : ''; ?>>D.Nursing</option>
                            <option value="Admission Number" <?php echo $filter_option === 'Admission Number' ? 'selected' : ''; ?>>Admission Number</option>
                        </select>
                        <input type="text" class="form-input search-bar" id="search-bar" name="filter_value" placeholder="Enter Admission Number" value="<?php echo htmlspecialchars($filter_value); ?>" class="<?php echo $filter_option === 'Admission Number' ? 'active' : ''; ?>">
                        <button type="submit" class="submit-btn">Filter</button>
                    </form>
                </div>
                <table class="student-table" id="studentTable">
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr data-student-id="<?php echo htmlspecialchars($student['student_id']); ?>">
                                <form id="updateForm_<?php echo htmlspecialchars($student['student_id']); ?>" method="POST" action="">
                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><input type="text" class="form-input" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required></td>
                                    <td>
                                        <select class="form-select" name="course" required>
                                            <?php foreach ($course_options as $course): ?>
                                                <option value="<?php echo htmlspecialchars($course); ?>" <?php echo $student['course'] === $course ? 'selected' : ''; ?>><?php echo htmlspecialchars($course); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" class="form-input" name="year_of_study" value="<?php echo htmlspecialchars($student['year_of_study']); ?>" min="1" required></td>
                                    <td><input type="text" class="form-input" name="id_number" value="<?php echo htmlspecialchars($student['id_number']); ?>" required></td>
                                    <td>
                                        <select class="form-select" name="gender" required>
                                            <option value="Male" <?php echo $student['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $student['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo $student['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </td>
                                    <td><input type="date" class="form-input" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth']); ?>" required></td>
                                    <td><input type="text" class="form-input" name="phone_number" value="<?php echo htmlspecialchars($student['phone_number']); ?>" required></td>
                                    <td><input type="email" class="form-input" name="email_address" value="<?php echo htmlspecialchars($student['email_address']); ?>" required></td>
                                    <td><input type="text" class="form-input" name="postal_address" value="<?php echo htmlspecialchars($student['postal_address']); ?>" required></td>
                                    <td>
                                        <select class="form-select" name="disability" required>
                                            <option value="No" <?php echo $student['disability'] == 'No' ? 'selected' : ''; ?>>No</option>
                                            <option value="Yes" <?php echo $student['disability'] == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                        </select>
                                    </td>
                                    <td><button type="submit" name="update_student" class="submit-btn">Update</button></td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="12">No students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&filter_option=<?php echo htmlspecialchars($filter_option); ?>&filter_value=<?php echo htmlspecialchars($filter_value); ?>" <?php echo $i === $page ? 'style="background: var(--light);"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <div id="popup" class="popup"></div>
</body>
</html>