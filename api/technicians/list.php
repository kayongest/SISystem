<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ability_db';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit();
}

$sql = "SELECT id, full_name, id as technician_id, department 
        FROM users 
        WHERE is_active = 1 AND (role = 'technician' OR role = 'tech_lead' OR role = 'senior_tech' OR role = 'audio_specialist' OR role = 'video_specialist' OR role = 'lighting_specialist' OR role = 'rigging_specialist')
        ORDER BY full_name";

$result = $conn->query($sql);
$technicians = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'technicians' => $technicians
]);

$conn->close();
?>