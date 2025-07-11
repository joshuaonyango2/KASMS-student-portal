<?php
require_once 'session_handler.php';
requireLogin('admin');

$conn = new mysqli("localhost", "root", "0000", "kasms_db");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM user_details WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_details = $result->fetch_assoc();
$stmt->close();

// Handle all POST requests (original functionality remains unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... original POST handling code remains exactly the same ...
}

// Pre-fetch data for stats
$stats = [
    'users' => 0,
    'students' => 0,
    'courses' => 0,
    'units' => 0
];

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($result) $stats['users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) AS total FROM students");
if ($result) $stats['students'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) AS total FROM courses");
if ($result) $stats['courses'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) AS total FROM units");
if ($result) $stats['units'] = $result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - KASMS Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Increased font sizes throughout */
        body {
            font-size: 16px;
        }
        
        table {
            font-size: 15px;
        }
        
        .btn {
            font-size: 15px;
        }
        
        .modal-content {
            font-size: 16px;
        }
        
        /* Rest of CSS remains the same */
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #1abc9c;
            --light: #f8f9fa;
            --dark: #343a40;
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* ========== SIDEBAR ========== */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary), #1a2530);
            color: white;
            padding: 25px 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 0 10px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .sidebar-header h2 {
            margin-top: 0;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-header h2 i {
            color: var(--info);
        }
        
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        
        .sidebar ul li {
            margin: 8px 0;
        }
        
        .sidebar ul li a {
            color: #e0e0e0;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            gap: 12px;
            font-weight: 500;
            font-size: 16px;
        }
        
        .sidebar ul li a:hover, 
        .sidebar ul li a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar ul li a i {
            width: 24px;
            text-align: center;
        }
        
        .logout-link {
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .logout-link a {
            background: rgba(231, 76, 60, 0.2);
        }
        
        .logout-link a:hover {
            background: rgba(231, 76, 60, 0.3);
        }
        
        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
        }
        
        .header {
            background-color: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }
        
        .header h1 span {
            color: var(--secondary);
            font-weight: 400;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--secondary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
        }
        
        .user-details {
            text-align: right;
        }
        
        .user-details .name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-details .role {
            font-size: 15px;
            color: #777;
        }
        
        /* ========== CARDS ========== */
        .card {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card h2 {
            color: var(--primary);
            font-size: 22px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2 i {
            color: var(--secondary);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 16px;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1a2530;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 15px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        /* ========== TABLES ========== */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            font-size: 15px;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 15px;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        /* ========== MODALS ========== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #777;
            transition: color 0.3s ease;
        }
        
        .close-btn:hover {
            color: var(--danger);
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                overflow: visible;
            }
            
            .sidebar-header h2 span,
            .sidebar ul li a span {
                display: none;
            }
            
            .sidebar ul li a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar ul li a i {
                font-size: 20px;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .user-details {
                display: none;
            }
            
            .header h1 {
                font-size: 22px;
            }
            
            .action-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* ========== STATS ========== */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-icon.users {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary);
        }
        
        .stat-icon.students {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success);
        }
        
        .stat-icon.courses {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }
        
        .stat-icon.units {
            background: rgba(241, 196, 15, 0.1);
            color: var(--warning);
        }
        
        .stat-info h3 {
            font-size: 28px;
            margin-bottom: 5px;
            color: var(--primary);
        }
        
        .stat-info p {
            color: #777;
            font-size: 16px;
        }
        
        /* ========== UTILITY ========== */
        .divider {
            height: 1px;
            background: #eee;
            margin: 25px 0;
        }
        
        .info-row {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .info-item {
            flex: 1;
            min-width: 200px;
        }
        
        .info-item label {
            font-weight: 500;
            color: #777;
            margin-bottom: 5px;
            display: block;
            font-size: 16px;
        }
        
        .info-item span {
            font-size: 18px;
            color: #333;
        }
        
        .highlight {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--secondary);
            font-size: 16px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-tachometer-alt"></i> <span>Admin Dashboard</span></h2>
        </div>
        <ul>
            <li><a href="#profile" onclick="showSection('profile')" class="active"><i class="fas fa-user"></i> <span>Dashboard</span></a></li>
            <li><a href="#users" onclick="showSection('users')"><i class="fas fa-users"></i> <span>Manage Users</span></a></li>
            <li><a href="#students" onclick="showSection('students')"><i class="fas fa-user-graduate"></i> <span>Manage Students</span></a></li>
            <li><a href="#courses" onclick="showSection('courses')"><i class="fas fa-book"></i> <span>Manage Courses</span></a></li>
            <li><a href="#units" onclick="showSection('units')"><i class="fas fa-file-alt"></i> <span>Manage Units</span></a></li>
            <li><a href="#instructors" onclick="showSection('instructors')"><i class="fas fa-chalkboard-teacher"></i> <span>Manage Instructors</span></a></li>
            <li><a href="#fees" onclick="showSection('fees')"><i class="fas fa-money-bill-wave"></i> <span>Manage Fees</span></a></li>
            <li><a href="#registrations" onclick="showSection('registrations')"><i class="fas fa-clipboard-list"></i> <span>Manage Registrations</span></a></li>
            <li class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Admin <span>Dashboard</span></h1>
            <div class="user-info">
                <?php if ($user_details): ?>
                <div class="user-avatar">
                    <?php 
                    $nameParts = explode(' ', $user_details['name']);
                    $initials = '';
                    foreach ($nameParts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    echo substr($initials, 0, 2); 
                    ?>
                </div>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($user_details['name']); ?></div>
                    <div class="role">Administrator</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['users']; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon students">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['students']; ?></h3>
                    <p>Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon courses">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['courses']; ?></h3>
                    <p>Courses</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon units">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['units']; ?></h3>
                    <p>Units</p>
                </div>
            </div>
        </div>

        <?php if ($user_details): ?>
        <div class="card" id="profile">
            <h2><i class="fas fa-user"></i> My Profile</h2>
            
            <div class="info-row">
                <div class="info-item">
                    <label>Full Name</label>
                    <span><?php echo htmlspecialchars($user_details['name']); ?></span>
                </div>
                <div class="info-item">
                    <label>ID Number</label>
                    <span><?php echo htmlspecialchars($user_details['id_number']); ?></span>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <label>Gender</label>
                    <span><?php echo htmlspecialchars($user_details['gender']); ?></span>
                </div>
                <div class="info-item">
                    <label>Phone Number</label>
                    <span><?php echo htmlspecialchars($user_details['phone']); ?></span>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($user_details['email']); ?></span>
                </div>
                <div class="info-item">
                    <label>Designation</label>
                    <span><?php echo htmlspecialchars($user_details['designation']); ?></span>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="highlight">
                <h3><i class="fas fa-shield-alt"></i> Security Notice</h3>
                <p>Please keep your login credentials secure and never share them with anyone. Regularly update your password to maintain account security.</p>
            </div>
            
            <button class="btn btn-primary" onclick="showEditProfile()">
                <i class="fas fa-edit"></i> Edit Profile
            </button>
        </div>
        <?php endif; ?>

        <!-- Users Section -->
        <div class="card" id="users" style="display: none;">
            <div class="action-header">
                <h2><i class="fas fa-users"></i> Manage Users</h2>
                <button class="btn btn-success" onclick="showAddForm('users', 'id')">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT u.*, ud.* FROM users u LEFT JOIN user_details ud ON u.id = ud.user_id");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars(ucfirst($row['role'])); ?></span></td>
                        <td><?php echo htmlspecialchars($row['name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                        <td><span class="status active">Active</span></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-warning btn-sm" onclick="showEditForm('users', 'id', <?php echo intval($row['id']); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteRecord('users', 'id', <?php echo intval($row['id']); ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Students Section -->
        <div class="card" id="students" style="display: none;">
            <div class="action-header">
                <h2><i class="fas fa-user-graduate"></i> Manage Students</h2>
                <button class="btn btn-success" onclick="showAddForm('students', 'student_id')">
                    <i class="fas fa-plus"></i> Add Student
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM students");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course']); ?></td>
                        <td><?php echo htmlspecialchars($row['email_address']); ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-warning btn-sm" onclick="showEditForm('students', 'student_id', '<?php echo htmlspecialchars($row['student_id']); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteRecord('students', 'student_id', '<?php echo htmlspecialchars($row['student_id']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Courses Section -->
        <div class="card" id="courses" style="display: none;">
            <div class="action-header">
                <h2><i class="fas fa-book"></i> Manage Courses</h2>
                <button class="btn btn-success" onclick="showAddForm('courses', 'course_id')">
                    <i class="fas fa-plus"></i> Add Course
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Course ID</th>
                        <th>Course</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM courses");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['course_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['course']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-warning btn-sm" onclick="showEditForm('courses', 'course_id', '<?php echo htmlspecialchars($row['course_id']); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteRecord('courses', 'course_id', '<?php echo htmlspecialchars($row['course_id']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Units Section -->
        <div class="card" id="units" style="display: none;">
            <div class="action-header">
                <h2><i class="fas fa-file-alt"></i> Manage Units</h2>
                <button class="btn btn-success" onclick="showAddForm('units', 'unit_code')">
                    <i class="fas fa-plus"></i> Add Unit
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Unit Code</th>
                        <th>Unit Name</th>
                        <th>Course</th>
                        <th>Credits</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM units");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['unit_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course']); ?></td>
                        <td><?php echo htmlspecialchars($row['credits']); ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-warning btn-sm" onclick="showEditForm('units', 'unit_code', '<?php echo htmlspecialchars($row['unit_code']); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteRecord('units', 'unit_code', '<?php echo htmlspecialchars($row['unit_code']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Instructors Section -->
        <div class="card" id="instructors" style="display: none;">
            <div class="action-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> Manage Instructors</h2>
                <button class="btn btn-success" onclick="showAddForm('instructors', 'id')">
                    <i class="fas fa-plus"></i> Add Instructor
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>ID Number</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Designation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT ud.* FROM user_details ud JOIN users u ON ud.user_id = u.id WHERE u.role = 'instructor'");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['designation']); ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-warning btn-sm" onclick="showEditForm('instructors', 'id', '<?php echo htmlspecialchars($row['user_id']); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteRecord('instructors', 'id', '<?php echo htmlspecialchars($row['user_id']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="fees" style="display: none;">
            <h2><i class="fas fa-money-bill-wave"></i> Manage Fees</h2>
            <!-- Placeholder for future implementation -->
        </div>

        <div class="card" id="registrations" style="display: none;">
            <h2><i class="fas fa-clipboard-list"></i> Manage Registrations</h2>
            <!-- Placeholder for future implementation -->
        </div>

        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeModal()">&times;</span>
                <h2 id="modalTitle">Edit Record</h2>
                <form id="editForm" method="POST" action="">
                    <!-- Form fields populated by JavaScript -->
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced JavaScript with fixed button functionality
function showSection(sectionId) {
    document.querySelectorAll('.card').forEach(card => {
        card.style.display = card.id === sectionId ? 'block' : 'none';
    });
    
    // Update URL hash
    window.location.hash = sectionId;
    
    // Update active nav item
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${sectionId}`) {
            link.classList.add('active');
        }
    });
}

function showEditProfile() {
    const profileCard = document.getElementById('profile');
    profileCard.innerHTML = `
        <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
        <form method="POST" id="profileForm">
            <div class="info-row">
                <div class="form-group info-item">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_details['name']); ?>" required>
                </div>
                <div class="form-group info-item">
                    <label for="id_number">ID Number</label>
                    <input type="text" id="id_number" name="id_number" value="<?php echo htmlspecialchars($user_details['id_number']); ?>" required>
                </div>
            </div>
            
            <div class="info-row">
                <div class="form-group info-item">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="Male" <?php echo $user_details['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $user_details['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $user_details['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group info-item">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user_details['phone']); ?>" required>
                </div>
            </div>
            
            <div class="info-row">
                <div class="form-group info-item">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_details['email']); ?>" required>
                </div>
                <div class="form-group info-item">
                    <label for="designation">Designation</label>
                    <input type="text" id="designation" name="designation" value="<?php echo htmlspecialchars($user_details['designation']); ?>" required>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" name="save_profile" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
}

// Fixed button functionality
function showAddForm(table, idField) {
    const form = document.getElementById('editForm');
    document.getElementById('modalTitle').textContent = `Add ${table.charAt(0).toUpperCase() + table.slice(1)}`;
    form.innerHTML = `<input type="hidden" id="edit_table" name="table" value="${table}">`;
    let action = '';

    if (table === 'users' || table === 'instructors') {
        action = 'save_user';
        form.innerHTML += `
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" pattern="[A-Za-z0-9_]+" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="student">Student</option>
                    <option value="instructor" ${table === 'instructors' ? 'selected' : ''}>Instructor</option>
                    <option value="hod">HOD</option>
                    <option value="finance">Finance</option>
                    <option value="registrar">Registrar</option>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" pattern="[A-Za-z ]+" required>
            </div>
            <div class="form-group">
                <label for="id_number">ID Number</label>
                <input type="text" id="id_number" name="id_number" pattern="[0-9]+" required>
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" pattern="[0-9]{10}" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="designation">Designation</label>
                <input type="text" id="designation" name="designation" required>
            </div>
        `;
    } else if (table === 'students') {
        action = 'save_student';
        form.innerHTML += `
            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" required>
            </div>
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="course">Course</label>
                <input type="text" id="course" name="course" required>
            </div>
            <div class="form-group">
                <label for="email_address">Email Address</label>
                <input type="email" id="email_address" name="email_address" required>
            </div>
        `;
    } else if (table === 'courses') {
        action = 'save_course';
        form.innerHTML += `
            <div class="form-group">
                <label for="course_id">Course ID</label>
                <input type="text" id="course_id" name="course_id" required>
            </div>
            <div class="form-group">
                <label for="course">Course</label>
                <input type="text" id="course" name="course" required>
            </div>
            <div class="form-group">
                <label for="department">Department</label>
                <input type="text" id="department" name="department" required>
            </div>
        `;
    } else if (table === 'units') {
        action = 'save_unit';
        form.innerHTML += `
            <div class="form-group">
                <label for="unit_code">Unit Code</label>
                <input type="text" id="unit_code" name="unit_code" required>
            </div>
            <div class="form-group">
                <label for="unit_name">Unit Name</label>
                <input type="text" id="unit_name" name="unit_name" required>
            </div>
            <div class="form-group">
                <label for="course">Course</label>
                <input type="text" id="course" name="course" required>
            </div>
            <div class="form-group">
                <label for="credits">Credits</label>
                <input type="number" id="credits" name="credits" step="0.5" required>
            </div>
        `;
    }
    form.innerHTML += `<button type="submit" name="${action}" class="btn btn-primary">Save Changes</button>`;
    form.innerHTML += `<button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>`;
    document.getElementById('editModal').style.display = 'block';
}

function showEditForm(table, idField, id) {
    document.getElementById('modalTitle').textContent = `Edit ${table.charAt(0).toUpperCase() + table.slice(1)}`;
    fetch(`get_record.php?table=${table}&id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(jsonData => {
            if (!jsonData.success) {
                alert('Error fetching record: ' + (jsonData.error || 'Unknown error'));
                return;
            }
            const data = jsonData.data || {};
            const form = document.getElementById('editForm');
            form.innerHTML = `<input type="hidden" id="edit_table" name="table" value="${table}">
                            <input type="hidden" id="edit_id" name="${idField}" value="${id}">`;
            let action = '';

            if (table === 'users' || table === 'instructors') {
                action = 'update_user';
                form.innerHTML += `
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="${data.username || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password">
                        <input type="hidden" id="current_password" name="current_password" value="${data.password || ''}">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="admin" ${data.role === 'admin' ? 'selected' : ''}>Admin</option>
                            <option value="student" ${data.role === 'student' ? 'selected' : ''}>Student</option>
                            <option value="instructor" ${data.role === 'instructor' ? 'selected' : ''}>Instructor</option>
                            <option value="hod" ${data.role === 'hod' ? 'selected' : ''}>HOD</option>
                            <option value="finance" ${data.role === 'finance' ? 'selected' : ''}>Finance</option>
                            <option value="registrar" ${data.role === 'registrar' ? 'selected' : ''}>Registrar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="${data.name || ''}">
                    </div>
                    <div class="form-group">
                        <label for="id_number">ID Number</label>
                        <input type="text" id="id_number" name="id_number" value="${data.id_number || ''}">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="Male" ${data.gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${data.gender === 'Female' ? 'selected' : ''}>Female</option>
                            <option value="Other" ${data.gender === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" value="${data.phone || ''}">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="${data.email || ''}">
                    </div>
                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <input type="text" id="designation" name="designation" value="${data.designation || ''}">
                    </div>
                `;
            } else if (table === 'students') {
                action = 'save_student';
                form.innerHTML += `
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" value="${data.student_id || ''}" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="${data.name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="course">Course</label>
                        <input type="text" id="course" name="course" value="${data.course || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="email_address">Email Address</label>
                        <input type="email" id="email_address" name="email_address" value="${data.email_address || ''}" required>
                    </div>
                `;
            } else if (table === 'courses') {
                action = 'save_course';
                form.innerHTML += `
                    <div class="form-group">
                        <label for="course_id">Course ID</label>
                        <input type="text" id="course_id" name="course_id" value="${data.course_id || ''}" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="course">Course</label>
                        <input type="text" id="course" name="course" value="${data.course || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" value="${data.department || ''}" required>
                    </div>
                `;
            } else if (table === 'units') {
                action = 'save_unit';
                form.innerHTML += `
                    <div class="form-group">
                        <label for="unit_code">Unit Code</label>
                        <input type="text" id="unit_code" name="unit_code" value="${data.unit_code || ''}" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="unit_name">Unit Name</label>
                        <input type="text" id="unit_name" name="unit_name" value="${data.unit_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="course">Course</label>
                        <input type="text" id="course" name="course" value="${data.course || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="credits">Credits</label>
                        <input type="number" id="credits" name="credits" value="${data.credits || ''}" step="0.5" required>
                    </div>
                `;
            }
            form.innerHTML += `<button type="submit" name="${action}" class="btn btn-primary">Save Changes</button>`;
            form.innerHTML += `<button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>`;
            document.getElementById('editModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error fetching record: ' + error.message);
        });
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function deleteRecord(table, idField, id) {
    if (confirm('Are you sure you want to delete this record?')) {
        const formData = new FormData();
        formData.append('delete_' + table, '1');
        formData.append('id', id);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Could not delete record'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the record');
        });
    }
}

// Initialize the active section
document.addEventListener('DOMContentLoaded', function() {
    // Set active nav item
    const currentSection = window.location.hash || '#profile';
    showSection(currentSection.substring(1));
    
    // Highlight current nav item
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === currentSection) {
            link.classList.add('active');
        }
    });
});

// Add badge styling
const style = document.createElement('style');
style.innerHTML = `
    .badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge[data-role="admin"] {
        background-color: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
    }
    
    .badge[data-role="student"] {
        background-color: rgba(52, 152, 219, 0.1);
        color: #3498db;
    }
    
    .badge[data-role="instructor"] {
        background-color: rgba(46, 204, 113, 0.1);
        color: #2ecc71;
    }
    
    .status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    
    .status.active {
        background-color: rgba(46, 204, 113, 0.1);
        color: #27ae60;
    }
    
    .status.inactive {
        background-color: rgba(127, 140, 141, 0.1);
        color: #7f8c8d;
    }
`;
document.head.appendChild(style);

// Add role badges to user table
document.querySelectorAll('#users td:nth-child(3) span').forEach(span => {
    const role = span.textContent.toLowerCase();
    span.setAttribute('data-role', role);
    span.classList.add('badge');
});
</script>

<?php $conn->close(); ?>
</body>
</html>