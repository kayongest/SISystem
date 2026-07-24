<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$conn->set_charset("utf8mb4");

$sql = "SELECT id, full_name, phone_number, vehicle_type, vehicle_number 
        FROM drivers 
        WHERE is_active = 1 AND status = 'available'
        ORDER BY full_name ASC";

$result = $conn->query($sql);
$drivers = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
    }
}

echo json_encode(['success' => true, 'drivers' => $drivers]);
$conn->close();
