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
<?php
require_once 'session_handler.php';
requireLogin('admin');

$conn = new mysqli("localhost", "root", "0000", "kasms_db");
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM user_details WHERE user_id = $user_id");
$user_details = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $id_number = $conn->real_escape_string(trim($_POST['id_number']));
    $gender = $conn->real_escape_string(trim($_POST['gender']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $designation = $conn->real_escape_string(trim($_POST['designation']));

    $query = "UPDATE user_details SET name='$name', id_number='$id_number', gender='$gender', phone='$phone', email='$email', designation='$designation' WHERE user_id=$user_id";
    if ($conn->query($query)) {
        $result = $conn->query("SELECT * FROM user_details WHERE user_id = $user_id");
        $user_details = $result->fetch_assoc();
    }
}

// Handle adding a new user with details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
    $role = $conn->real_escape_string(trim($_POST['role']));
    $name = $conn->real_escape_string(trim($_POST['name']));
    $id_number = $conn->real_escape_string(trim($_POST['id_number']));
    $gender = $conn->real_escape_string(trim($_POST['gender']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $designation = $conn->real_escape_string(trim($_POST['designation']));

    $conn->begin_transaction();
    try {
        // Insert into users table
        $query = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $username, $password, $role);
        $stmt->execute();
        $new_user_id = $conn->insert_id;
        error_log("Inserted user ID: $new_user_id");

        // Insert into user_details table
        $query = "INSERT INTO user_details (user_id, name, id_number, gender, phone, email, designation) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issssss", $new_user_id, $name, $id_number, $gender, $phone, $email, $designation);
        if (!$stmt->execute()) {
            error_log("User details insert failed: " . $stmt->error . " Query: " . $query);
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $conn->commit();
        error_log("New user added successfully: $username");
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to add user: " . $e->getMessage());
    }
    $stmt->close();
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = $conn->real_escape_string($_POST['id']);
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = !empty(trim($_POST['password'])) ? password_hash(trim($_POST['password']), PASSWORD_BCRYPT) : $_POST['current_password'];
    $role = $conn->real_escape_string(trim($_POST['role']));
    $query = "UPDATE users SET username='$username', password='$password', role='$role' WHERE id=$id";
    $conn->query($query);

    // Check if user_details exists, update or insert accordingly
    $result = $conn->query("SELECT * FROM user_details WHERE user_id = $id");
    if (!empty($_POST['name']) || $result->num_rows == 0) {
        $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
        $id_number = $conn->real_escape_string(trim($_POST['id_number'] ?? ''));
        $gender = $conn->real_escape_string(trim($_POST['gender'] ?? ''));
        $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $designation = $conn->real_escape_string(trim($_POST['designation'] ?? ''));

        if ($result->num_rows == 0) {
            $query = "INSERT INTO user_details (user_id, name, id_number, gender, phone, email, designation) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issssss", $id, $name, $id_number, $gender, $phone, $email, $designation);
        } else {
            $query = "UPDATE user_details SET name=?, id_number=?, gender=?, phone=?, email=?, designation=? WHERE user_id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssi", $name, $id_number, $gender, $phone, $email, $designation, $id);
        }
        if (!$stmt->execute()) {
            error_log("User details update/insert failed: " . $stmt->error . " Query: " . $query);
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $id = $conn->real_escape_string($_POST['id']);
    $conn->query("DELETE FROM users WHERE id=$id");
    $conn->query("DELETE FROM user_details WHERE user_id=$id");
}

// Handle course management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
    $course_id = $conn->real_escape_string(trim($_POST['course_id']));
    $course_name = $conn->real_escape_string(trim($_POST['course_name']));
    $description = $conn->real_escape_string(trim($_POST['description']));

    $query = "INSERT INTO courses (course_id, course_name, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE course_name=?, description=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssi", $course_id, $course_name, $description, $course_name, $description);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $course_id = $conn->real_escape_string($_POST['course_id']);
    $conn->query("DELETE FROM courses WHERE course_id='$course_id'");
}

// Handle unit management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_unit'])) {
    $unit_id = $conn->real_escape_string(trim($_POST['unit_id']));
    $unit_name = $conn->real_escape_string(trim($_POST['unit_name']));
    $course_id = $conn->real_escape_string(trim($_POST['course_id']));
    $description = $conn->real_escape_string(trim($_POST['description']));

    $query = "INSERT INTO units (unit_id, unit_name, course_id, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE unit_name=?, course_id=?, description=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sisssi", $unit_id, $unit_name, $course_id, $description, $unit_name, $course_id, $description);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_unit'])) {
    $unit_id = $conn->real_escape_string($_POST['unit_id']);
    $conn->query("DELETE FROM units WHERE unit_id='$unit_id'");
}
?>

<div class="container">
    <div class="sidebar">
        <h2>Admin Dashboard</h2>
        <ul>
            <li><a href="#profile" onclick="showSection('profile')">Profile</a></li>
            <li><a href="#users" onclick="showSection('users')">Manage Users</a></li>
            <li><a href="#students" onclick="showSection('students')">Manage Students</a></li>
            <li><a href="#courses" onclick="showSection('courses')">Manage Courses</a></li>
            <li><a href="#units" onclick="showSection('units')">Manage Units</a></li>
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
                        <th>Password Hash</th>
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
                            <td>" . htmlspecialchars($row['password']) . "</td>
                            <td>" . htmlspecialchars($row['role']) . "</td>
                            <td>" . htmlspecialchars($row['name'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['id_number'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['gender'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['phone'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['email'] ?? '') . "</td>
                            <td>" . htmlspecialchars($row['designation'] ?? '') . "</td>
                            <td>
                                <button class='btn' onclick='showEditForm(\"users\", \"id\", \"" . htmlspecialchars($row['id']) . "\")'>Edit</button>
                                <button class='btn' onclick='deleteRecord(\"users\", \"id\", \"" . htmlspecialchars($row['id']) . "\")'>Delete</button>
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
                        <th>Course Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM courses");
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['course_id']) . "</td>
                            <td>" . htmlspecialchars($row['course_name']) . "</td>
                            <td>" . htmlspecialchars($row['description']) . "</td>
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
            <button class="btn" onclick="showAddForm('units', 'unit_id')">Add Unit</button>
            <table>
                <thead>
                    <tr>
                        <th>Unit ID</th>
                        <th>Unit Name</th>
                        <th>Course ID</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM units");
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['unit_id']) . "</td>
                            <td>" . htmlspecialchars($row['unit_name']) . "</td>
                            <td>" . htmlspecialchars($row['course_id']) . "</td>
                            <td>" . htmlspecialchars($row['description']) . "</td>
                            <td>
                                <button class='btn' onclick='showEditForm(\"units\", \"unit_id\", \"" . htmlspecialchars($row['unit_id']) . "\")'>Edit</button>
                                <button class='btn' onclick='deleteRecord(\"units\", \"unit_id\", \"" . htmlspecialchars($row['unit_id']) . "\")'>Delete</button>
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
                    <input type="hidden" id="edit_table" name="table">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username">
                    </div>
                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password">
                        <input type="hidden" id="current_password" name="current_password">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="admin">Admin</option>
                            <option value="student">Student</option>
                            <option value="instructor">Instructor</option>
                            <option value="hod">HOD</option>
                            <option value="finance">Finance</option>
                            <option value="registrar">Registrar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name">
                    </div>
                    <div class="form-group">
                        <label for="id_number">ID Number</label>
                        <input type="text" id="id_number" name="id_number">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <input type="text" id="designation" name="designation">
                    </div>
                    <div class="form-group">
                        <label for="course_id">Course ID</label>
                        <input type="text" id="course_id" name="course_id">
                    </div>
                    <div class="form-group">
                        <label for="course_name">Course Name</label>
                        <input type="text" id="course_name" name="course_name">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="unit_id">Unit ID</label>
                        <input type="text" id="unit_id" name="unit_id">
                    </div>
                    <div class="form-group">
                        <label for="unit_name">Unit Name</label>
                        <input type="text" id="unit_name" name="unit_name">
                    </div>
                    <div class="form-group">
                        <label for="unit_course_id">Course ID</label>
                        <input type="text" id="unit_course_id" name="course_id">
                    </div>
                    <button type="submit" name="update_user" class="btn">Save Changes</button>
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
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
    if (table === 'users') {
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
                    <option value="instructor">Instructor</option>
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
        form.innerHTML += `<button type="submit" name="save_user" class="btn">Save Changes</button>`;
    } else if (table === 'students') {
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
                <label for="email_address">Email</label>
                <input type="email" id="email_address" name="email_address" required>
            </div>
        `;
        form.innerHTML += `<button type="submit" name="save_user" class="btn">Save Changes</button>`;
    } else if (table === 'courses') {
        form.innerHTML += `
            <div class="form-group">
                <label for="course_id">Course ID</label>
                <input type="text" id="course_id" name="course_id" required>
            </div>
            <div class="form-group">
                <label for="course_name">Course Name</label>
                <input type="text" id="course_name" name="course_name" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" required></textarea>
            </div>
        `;
        form.innerHTML += `<button type="submit" name="save_course" class="btn">Save Changes</button>`;
    } else if (table === 'units') {
        form.innerHTML += `
            <div class="form-group">
                <label for="unit_id">Unit ID</label>
                <input type="text" id="unit_id" name="unit_id" required>
            </div>
            <div class="form-group">
                <label for="unit_name">Unit Name</label>
                <input type="text" id="unit_name" name="unit_name" required>
            </div>
            <div class="form-group">
                <label for="unit_course_id">Course ID</label>
                <input type="text" id="unit_course_id" name="course_id" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" required></textarea>
            </div>
        `;
        form.innerHTML += `<button type="submit" name="save_unit" class="btn">Save Changes</button>`;
    }
    form.innerHTML += `<button type="button" class="btn" onclick="closeModal()">Cancel</button>`;
    document.getElementById('editModal').style.display = 'block';
}

function showEditForm(table, idField, id) {
    fetch(`get_record.php?table=${table}&id=${id}`)
        .then(response => response.json())
        .then(data => {
            const form = document.getElementById('editForm');
            form.innerHTML = `<input type="hidden" id="edit_table" name="table" value="${table}"><input type="hidden" id="edit_id" name="id" value="${id}">`;
            if (table === 'users') {
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
                form.innerHTML += `<button type="submit" name="update_user" class="btn">Save Changes</button>`;
            } else if (table === 'students') {
                form.innerHTML += `
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" value="${data.student_id || ''}" required>
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
                        <label for="email_address">Email</label>
                        <input type="email" id="email_address" name="email_address" value="${data.email_address || ''}" required>
                    </div>
                `;
                form.innerHTML += `<button type="submit" name="update_user" class="btn">Save Changes</button>`;
            } else if (table === 'courses') {
                form.innerHTML += `
                    <div class="form-group">
                        <label for="course_id">Course ID</label>
                        <input type="text" id="course_id" name="course_id" value="${data.course_id || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="course_name">Course Name</label>
                        <input type="text" id="course_name" name="course_name" value="${data.course_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required>${data.description || ''}</textarea>
                    </div>
                `;
                form.innerHTML += `<button type="submit" name="save_course" class="btn">Save Changes</button>`;
            } else if (table === 'units') {
                form.innerHTML += `
                    <div class="form-group">
                        <label for="unit_id">Unit ID</label>
                        <input type="text" id="unit_id" name="unit_id" value="${data.unit_id || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="unit_name">Unit Name</label>
                        <input type="text" id="unit_name" name="unit_name" value="${data.unit_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="unit_course_id">Course ID</label>
                        <input type="text" id="unit_course_id" name="course_id" value="${data.course_id || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required>${data.description || ''}</textarea>
                    </div>
                `;
                form.innerHTML += `<button type="submit" name="save_unit" class="btn">Save Changes</button>`;
            }
            form.innerHTML += `<button type="button" class="btn" onclick="closeModal()">Cancel</button>`;
            document.getElementById('editModal').style.display = 'block';
        });
}

function deleteRecord(table, idField, id) {
    if (confirm('Are you sure you want to delete this record?')) {
        const formData = new FormData();
        formData.append('table', table);
        formData.append('idField', idField);
        formData.append('id', id);
        fetch('delete_record.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting record: ' + data.error);
            }
        });
    }
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating record: ' + data.error);
        }
    });
});
</script>

<?php $conn->close(); ?>
</html>