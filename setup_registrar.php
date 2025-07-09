<?php
$conn = mysqli_connect("p:localhost", "root", "", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$username = "registrar1";
$password = "registrar123";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = "registrar";
$student_id = NULL;

$stmt = $conn->prepare("INSERT INTO users (username, password, role, student_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = ?, role = ?, student_id = ?");
$stmt->bind_param("sssssss", $username, $hashed_password, $role, $student_id, $hashed_password, $role, $student_id);
$stmt->execute();
$stmt->close();

$conn->close();
echo "Registrar user created/updated successfully!";
?>