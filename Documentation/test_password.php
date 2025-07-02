<?php
$password = "password123";
$stored_hash = "$2y$10$12jK/9xO8zPqR5tYvL3mLu8e7w9xV6yB1cD2eF3gH4iJ5kL6mN7oP";
if (password_verify($password, $stored_hash)) {
    echo "Password matches!";
} else {
    echo "Password does not match.";
}
?>