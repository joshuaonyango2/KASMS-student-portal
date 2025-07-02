<?php
$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
$username = "instructor1";
$password = "password123"; // Set desired password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hashed_password, $username);
$stmt->execute();
$stmt->close();
$conn->close();
echo "Password reset for $username";
?>