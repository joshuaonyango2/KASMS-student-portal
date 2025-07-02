<?php
// dashboard_admin.php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "0000", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$admin_details = null;

// Fetch admin details
$stmt = $conn->prepare("SELECT name, email, phone, address FROM admin_details WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $admin_details = $result->fetch_assoc();
}
$stmt->close();

// Handle admin details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_details'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if (empty($name) || empty($email) || empty($phone) || empty($address)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address.";
    } else {
        $stmt = $conn->prepare("INSERT INTO admin_details (user_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, email = ?, phone = ?, address = ?");
        $stmt->bind_param("issssssss", $user_id, $name, $email, $phone, $address, $name, $email, $phone, $address);
        if ($stmt->execute()) {
            $success_message = "Details saved successfully!";
            $admin_details = ['name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
        } else {
            $error_message = "Failed to save details: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    if (empty($username) || empty($password) || empty($role)) {
        $error_message = "All fields are required.";
    } elseif (!in_array($role, ['instructor', 'hod', 'finance', 'registrar'])) {
        $error_message = "Invalid role. Students can only be added by registrar.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, created_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);
        if ($stmt->execute()) {
            $success_message = "User added successfully! Username: $username, Role: $role";
        } else {
            $error_message = "Failed to add user: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KASMS Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7fa; }
        .sidebar { width: 250px; height: 100vh; background-color: #2c3e50; color: white; position: fixed; padding-top: 20px; }
        .sidebar a { padding: 15px 20px; text-decoration: none; color: white; display: block; position: relative; }
        .sidebar a:hover { background-color: #34495e; }
        .sidebar .active { background-color: #f4a261; color: #fff; }
        .content { margin-left: 250px; padding: 20px; }
        .header { background-color: #ecf0f1; padding: 10px 20px; display: flex; justify-content: flex-end; align-items: center; }
        .header span { margin-right: 20px; color: #e74c3c; }
        .home-section, .add-user-section { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 70%; margin: 0 auto; margin-top: 20px; display: none; }
        .home-section.active, .add-user-section.active { display: block; }
        .error { color: red; }
        .success { color: green; }
        input, select, button { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        button { background-color: #f4a261; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #e67e22; }
        .details-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .details-table th, .details-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .details-table th { background-color: #f4a261; color: white; }
    </style>
    <script>
        function showSection(sectionId) {
            var sections = document.getElementsByClassName('home-section, .add-user-section');
            for (var i = 0; i < sections.length; i++) {
                sections[i].classList.remove('active');
            }
            document.getElementById(sectionId).classList.add('active');
        }

        window.onload = function() {
            showSection('homeSection');
        };
    </script>
</head>
<body>
    <div class="sidebar">
        <a href="#" onclick="showSection('homeSection')" class="active">Home</a>
        <a href="#" onclick="showSection('addUserSection')">Add User</a>
    </div>
    <div class="content">
        <div class="header">
            <span>Admin -</span>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none;">Log Out</a>
        </div>
        <div id="homeSection" class="home-section active">
            <h2>Admin Dashboard</h2>
            <p>Welcome to the Admin Dashboard. Use the sidebar to navigate.</p>
            <?php if ($error_message) echo "<p class='error'>$error_message</p>"; ?>
            <?php if ($success_message) echo "<p class='success'>$success_message</p>"; ?>
            <?php if (!$admin_details): ?>
                <h3>Enter Your Personal Details</h3>
                <form method="POST">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="text" name="phone" placeholder="Phone Number" required>
                    <input type="text" name="address" placeholder="Address" required>
                    <button type="submit" name="submit_details">Save Details</button>
                </form>
            <?php else: ?>
                <h3>Your Details</h3>
                <table class="details-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($admin_details['name']); ?></td>
                            <td><?php echo htmlspecialchars($admin_details['email']); ?></td>
                            <td><?php echo htmlspecialchars($admin_details['phone']); ?></td>
                            <td><?php echo htmlspecialchars($admin_details['address']); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div id="addUserSection" class="add-user-section">
            <h2>Add New User</h2>
            <?php if ($error_message) echo "<p class='error'>$error_message</p>"; ?>
            <?php if ($success_message) echo "<p class='success'>$success_message</p>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="instructor">Instructor</option>
                    <option value="hod">HOD</option>
                    <option value="finance">Finance</option>
                    <option value="registrar">Registrar</option>
                </select>
                <button type="submit" name="add_user">Add User</button>
            </form>
        </div>
    </div>
</body>
</html>