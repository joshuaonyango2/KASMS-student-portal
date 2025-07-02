<?php
$stored_hash = "$2y$10$12jK/9xO8zPqR5tYvL3mLu8e7w9xV6yB1cD2eF3gH4iJ5kL6mN7oP";
$test_password = "password123";
if (password_verify($test_password, $stored_hash)) {
    echo "The password 'password123' matches the hash!";
} else {
    echo "The password 'password123' does not match the hash. You may need to reset it.";
}
?>