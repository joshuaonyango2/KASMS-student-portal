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

// Handle all POST requests in a centralized way
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Profile update
    if (isset($_POST['save_profile'])) {
        $name = trim($_POST['name']);
        $id_number = trim($_POST['id_number']);
        $gender = trim($_POST['gender']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $designation = trim($_POST['designation']);

        $query = "UPDATE user_details SET name=?, id_number=?, gender=?, phone=?, email=?, designation=? WHERE user_id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssi", $name, $id_number, $gender, $phone, $email, $designation, $user_id);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    // Add new user
    if (isset($_POST['save_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $encrypted_password = password_hash($password, PASSWORD_BCRYPT);
        $role = trim($_POST['role']);
        $name = trim($_POST['name']);
        $id_number = trim($_POST['id_number']);
        $gender = trim($_POST['gender']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $designation = trim($_POST['designation']);

        $conn->begin_transaction();
        try {
            $query = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sss", $username, $encrypted_password, $role);
            $stmt->execute();
            $new_user_id = $conn->insert_id;

            $query = "INSERT INTO user_details (user_id, name, id_number, gender, phone, email, designation) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issssss", $new_user_id, $name, $id_number, $gender, $phone, $email, $designation);
            $stmt->execute();
            $conn->commit();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        $stmt->close();
        exit;
    }

    // Update user
    if (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $encrypted_password = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : $_POST['current_password'];
        $role = trim($_POST['role']);
        $name = trim($_POST['name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $designation = trim($_POST['designation'] ?? '');

        $conn->begin_transaction();
        try {
            $query = "UPDATE users SET username=?, password=?, role=? WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $username, $encrypted_password, $role, $id);
            $success = $stmt->execute();
            error_log("Users update query: $query, ID: $id, Success: $success");

            $result = $conn->query("SELECT * FROM user_details WHERE user_id = " . $conn->real_escape_string($id));
            if ($result->num_rows == 0 && (!empty($name) || !empty($id_number) || !empty($gender) || !empty($phone) || !empty($email) || !empty($designation))) {
                $query = "INSERT INTO user_details (user_id, name, id_number, gender, phone, email, designation) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("issssss", $id, $name, $id_number, $gender, $phone, $email, $designation);
            } elseif ($result->num_rows > 0) {
                $query = "UPDATE user_details SET name=?, id_number=?, gender=?, phone=?, email=?, designation=? WHERE user_id=?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssssi", $name, $id_number, $gender, $phone, $email, $designation, $id);
            }
            if (isset($stmt)) {
                $success = $success && $stmt->execute();
                error_log("User_details query: $query, User_ID: $id, Success: $success");
            }
            $conn->commit();
            
            echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        $stmt->close();
        exit;
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $id = $_POST['id'];
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        $query = "DELETE FROM user_details WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $success && $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    // Handle course, unit, student management (similarly ensure JSON response and exit)
    if (isset($_POST['save_course'])) {
        $course_id = trim($_POST['course_id']);
        $course = trim($_POST['course']);
        $department = trim($_POST['department']);

        $query = "INSERT INTO courses (course_id, course, department) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE course=?, department=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", $course_id, $course, $department, $course, $department);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    if (isset($_POST['delete_course'])) {
        $course_id = $_POST['course_id'];
        $query = "DELETE FROM courses WHERE course_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $course_id);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    if (isset($_POST['save_unit'])) {
        $unit_code = trim($_POST['unit_code']);
        $unit_name = trim($_POST['unit_name']);
        $course = trim($_POST['course']);
        $credits = trim($_POST['credits']);

        $query = "INSERT INTO units (unit_code, unit_name, course, credits) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE unit_name=?, course=?, credits=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssdsds", $unit_code, $unit_name, $course, $credits, $unit_name, $course, $credits);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    if (isset($_POST['delete_unit'])) {
        $unit_code = $_POST['unit_code'];
        $query = "DELETE FROM units WHERE unit_code = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $unit_code);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    if (isset($_POST['save_student'])) {
        $student_id = trim($_POST['student_id']);
        $name = trim($_POST['name']);
        $course = trim($_POST['course']);
        $email_address = trim($_POST['email_address']);

        $query = "INSERT INTO students (student_id, name, course, email_address) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=?, course=?, email_address=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssss", $student_id, $name, $course, $email_address, $name, $course, $email_address);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    if (isset($_POST['delete_student'])) {
        $student_id = $_POST['student_id'];
        $query = "DELETE FROM students WHERE student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $student_id);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : $stmt->error]);
        $stmt->close();
        exit;
    }

    // Default response for unhandled requests
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - KASMS Portal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
        }
        .sidebar h2 {
            margin-top: 0;
            font-size: 24px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            margin: 10px 0;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            border-radius: 4px;
        }
        .sidebar ul li a:hover {
            background-color: #34495e;
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
        .header {
            background-color: white;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #34495e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #2c3e50;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="sidebar">
        <h2>Admin Dashboard</h2>
        <ul>
            <li><a href="#profile" onclick="showSection('profile')">Profile</a></li>
            <li><a href="#users" onclick="showSection('users')">Manage Users</a></li>
            <li><a href="#students" onclick="showSection('students')">Manage Students</a></li>
            <li><a href="#courses" onclick="showSection('courses')">Manage Courses</a></li>
            <li><a href="#units" onclick="showSection('units')">Manage Units</a></li>
            <li><a href="#instructors" onclick="showSection('instructors')">Manage Instructors</a></li>
            <li><a href="#fees" onclick="showSection('fees')">Manage Fees</a></li>
            <li><a href="#registrations" onclick="showSection('registrations')">Manage Registrations</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($user_details['name']); ?></h1>
        </div>

        <div class="card" id="profile">
            <h2>Your Profile</h2>
            <div class="form-group">
                <label>Full Name:</label>
                <span><?php echo htmlspecialchars($user_details['name']); ?></span>
            </div>
            <div class="form-group">
                <label>ID Number:</label>
                <span><?php echo htmlspecialchars($user_details['id_number']); ?></span>
            </div>
            <div class="form-group">
                <label>Gender:</label>
                <span><?php echo htmlspecialchars($user_details['gender']); ?></span>
            </div>
            <div class="form-group">
                <label>Phone Number:</label>
                <span><?php echo htmlspecialchars($user_details['phone']); ?></span>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <span><?php echo htmlspecialchars($user_details['email']); ?></span>
            </div>
            <div class="form-group">
                <label>Designation:</label>
                <span><?php echo htmlspecialchars($user_details['designation']); ?></span>
            </div>
            <button class="btn" onclick="showEditProfile()">Edit Profile</button>
        </div>

        <div class="card" id="users" style="display: none;">
            <h2>Manage Users</h2>
            <button class="btn" onclick="showAddForm('users', 'id')">Add User</button>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Role</th>
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
                    $result = $conn->query("SELECT u.*, ud.* FROM users u LEFT JOIN user_details ud ON u.id = ud.user_id");
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['id']) . "</td>
                            <td>" . htmlspecialchars($row['username']) . "</td>
                            <td>********</td>
                            <td>" . htmlspecialchars($row['role']) . "</td>
                            <td>" . htmlspecialchars($row['name'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['id_number'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['gender'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['phone'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['email'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['designation'] ?? '') . "</td>
                            <td>
                                <button class='btn' onclick='showEditForm(\"users\", \"id\", " . intval($row['id']) . ")'>Edit</button>
                                <button class='btn' onclick='deleteRecord(\"users\", \"id\", " . intval($row['id']) . ")'>Delete</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="students" style="display: none;">
            <h2>Manage Students</h2>
            <button class="btn" onclick="showAddForm('students', 'student_id')">Add Student</button>
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
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['student_id']) . "</td>
                            <td>" . htmlspecialchars($row['name']) . "</td>
                            <td>" . htmlspecialchars($row['course']) . "</td>
                            <td>" . htmlspecialchars($row['email_address']) . "</td>
                            <td>
                                <button class='btn' onclick='showEditForm(\"students\", \"student_id\", \"" . htmlspecialchars($row['student_id']) . "\")'>Edit</button>
                                <button class='btn' onclick='deleteRecord(\"students\", \"student_id\", \"" . htmlspecialchars($row['student_id']) . "\")'>Delete</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="courses" style="display: none;">
            <h2>Manage Courses</h2>
            <button class="btn" onclick="showAddForm('courses', 'course_id')">Add Course</button>
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
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['course_id']) . "</td>
                            <td>" . htmlspecialchars($row['course']) . "</td>
                            <td>" . htmlspecialchars($row['department']) . "</td>
                            <td>
                                <button class='btn' onclick='showEditForm(\"courses\", \"course_id\", \"" . htmlspecialchars($row['course_id']) . "\")'>Edit</button>
                                <button class='btn' onclick='deleteRecord(\"courses\", \"course_id\", \"" . htmlspecialchars($row['course_id']) . "\")'>Delete</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="units" style="display: none;">
            <h2>Manage Units</h2>
            <button class="btn" onclick="showAddForm('units', 'unit_code')">Add Unit</button>
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
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['unit_code']) . "</td>
                            <td>" . htmlspecialchars($row['unit_name']) . "</td>
                            <td>" . htmlspecialchars($row['course']) . "</td>
                            <td>" . htmlspecialchars($row['credits']) . "</td>
                            <td>
                                <button class='btn' onclick='showEditForm(\"units\", \"unit_code\", \"" . htmlspecialchars($row['unit_code']) . "\")'>Edit</button>
                                <button class='btn' onclick='deleteRecord(\"units\", \"unit_code\", \"" . htmlspecialchars($row['unit_code']) . "\")'>Delete</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="instructors" style="display: none;">
            <h2>Manage Instructors</h2>
            <button class="btn" onclick="showAddForm('instructors', 'id')">Add Instructor</button>
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
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['user_id']) . "</td>
                            <td>" . htmlspecialchars($row['name']) . "</td>
                            <td>" . htmlspecialchars($row['id_number']) . "</td>
                            <td>" . htmlspecialchars($row['gender']) . "</td>
                            <td>" . htmlspecialchars($row['phone']) . "</td>
                            <td>" . htmlspecialchars($row['email']) . "</td>
                            <td>" . htmlspecialchars($row['designation']) . "</td>
                            <td>
                                <button class='btn' onclick='showEditForm(\"instructors\", \"id\", \"" . htmlspecialchars($row['user_id']) . "\")'>Edit</button>
                                <button class='btn' onclick='deleteRecord(\"instructors\", \"id\", \"" . htmlspecialchars($row['user_id']) . "\")'>Delete</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="fees" style="display: none;">
            <h2>Manage Fees</h2>
            <!-- Placeholder for future implementation -->
        </div>

        <div class="card" id="registrations" style="display: none;">
            <h2>Manage Registrations</h2>
            <!-- Placeholder for future implementation -->
        </div>

        <div id="editModal" class="modal">
            <div class="modal-content">
                <h2>Edit Record</h2>
                <form id="editForm" method="POST" action="">
                    <!-- Form fields populated by JavaScript -->
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showSection(sectionId) {
    document.querySelectorAll('.card').forEach(card => {
        card.style.display = card.id === sectionId ? 'block' : 'none';
    });
}

function showEditProfile() {
    const profileCard = document.getElementById('profile');
    profileCard.innerHTML = `
        <h2>Edit Profile</h2>
        <form method="POST">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_details['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="id_number">ID Number</label>
                <input type="text" id="id_number" name="id_number" value="<?php echo htmlspecialchars($user_details['id_number']); ?>" required>
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="Male" <?php echo $user_details['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $user_details['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $user_details['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user_details['phone']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_details['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="designation">Designation</label>
                <input type="text" id="designation" name="designation" value="<?php echo htmlspecialchars($user_details['designation']); ?>" required>
            </div>
            <button type="submit" name="save_profile" class="btn">Save Changes</button>
        </form>
    `;
}
function showAddForm(table, idField) {
    const form = document.getElementById('editForm');
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
    form.innerHTML += `<button type="submit" name="${action}" class="btn">Save Changes</button>`;
    form.innerHTML += `<button type="button" class="btn" onclick="closeModal()">Cancel</button>`;
    document.getElementById('editModal').style.display = 'block';
}

function showEditForm(table, idField, id) {
    console.log('Fetching record for table:', table, 'idField:', idField, 'id:', id);
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
            form.innerHTML += `<button type="submit" name="${action}" class="btn">Save Changes</button>`;
            form.innerHTML += `<button type="button" class="btn" onclick="closeModal()">Cancel</button>`;
            document.getElementById('editModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error fetching record: ' + error.message);
        });
}
</script>

<?php $conn->close(); ?>
</body>
</html>