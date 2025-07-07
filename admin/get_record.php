<?php
session_start();
$conn = new mysqli("localhost", "root", "0000", "kasms_db");
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['error' => 'Unauthorized']));
}

$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? '';

$valid_tables = ['students', 'courses', 'units', 'fees', 'registrations'];
if (!in_array($table, $valid_tables)) {
    die(json_encode(['error' => 'Invalid table']));
}

$id_field = ($table === 'students') ? 'student_id' : 'id';
$query = "SELECT * FROM $table WHERE $id_field = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode($data);
$stmt->close();
$conn->close();
?>