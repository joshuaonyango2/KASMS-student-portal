<?php
$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$new_password = password_hash("password123", PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $new_password, $username);
$username = "student1";
$stmt->execute();
$stmt->close();
echo "Password reset for student1. New hash: $new_password";

$conn->close();
?>