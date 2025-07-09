<?php
session_start();
require_once 'session_handler.php';
requireLogin('admin');

$conn = new mysqli("localhost", "root", "", "kasms_db");
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode(['error' => 'Database connection failed']));
}

$table = $_POST['table'] ?? '';

$valid_tables = ['students', 'courses', 'units', 'fees', 'registrations'];
if (!in_array($table, $valid_tables)) {
    die(json_encode(['error' => 'Invalid table']));
}

// Define field types for each table
$field_types = [
    'students' => [
        'student_id' => 's',
        'name' => 's',
        'year_of_study' => 'i',
        'id_number' => 's',
        'gender' => 's',
        'date_of_birth' => 's',
        'phone_number' => 's',
        'email_address' => 's',
        'postal_address' => 's',
        'course' => 's',
        'enrollment_year' => 'i',
        'disability' => 's'
    ],
    'courses' => [
        'course' => 's',
        'department' => 's'
    ],
    'units' => [
        'unit_code' => 's',
        'unit_name' => 's',
        'course' => 's',
        'credits' => 'i'
    ],
    'fees' => [
        'student_id' => 's',
        'semester' => 's',
        'amount_owed' => 'd',
        'amount_paid' => 'd'
    ],
    'registrations' => [
        'student_id' => 's',
        'semester' => 's',
        'stage' => 's',
        'unit_code' => 's',
        'unit_name' => 's',
        'course' => 's',
        'registration_date' => 's'
    ]
];

unset($_POST['table'], $_POST['id']);
$columns = [];
$placeholders = [];
$params = [];
$types = '';

foreach ($_POST as $key => $value) {
    if ($key !== 'created_at' && $key !== 'updated_at' && $key !== 'balance') {
        $columns[] = $key;
        $placeholders[] = '?';
        $params[] = $value;
        $types .= $field_types[$table][$key] ?? 's';
    }
}

$query = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die(json_encode(['error' => 'Prepare failed: ' . $conn->error]));
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    error_log("Record added successfully: table=$table");
    echo json_encode(['success' => true]);
} else {
    error_log("Insert failed: " . $stmt->error);
    echo json_encode(['error' => 'Insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>