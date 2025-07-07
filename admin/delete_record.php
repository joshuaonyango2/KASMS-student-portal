<?php
session_start();
$conn = new mysqli("localhost", "root", "0000", "kasms_db");
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['error' => 'Unauthorized']));
}

$table = $_POST['table'] ?? '';
$idField = $_POST['idField'] ?? '';
$id = $_POST['id'] ?? '';

$valid_tables = ['students', 'courses', 'units', 'fees', 'registrations'];
if (!in_array($table, $valid_tables)) {
    die(json_encode(['error' => 'Invalid table']));
}

$query = "DELETE FROM $table WHERE $idField = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Delete failed']);
}

$stmt->close();
$conn->close();
?>