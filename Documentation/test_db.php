<?php
$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
echo "Database connection successful!<br>";

$result = $conn->query("SELECT * FROM users WHERE username = 'student1'");
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "Found user: " . $user['username'] . "<br>";
    echo "Stored password hash: " . $user['password'] . "<br>";
} else {
    echo "User 'student1' not found in the database.";
}

$conn->close();
?>