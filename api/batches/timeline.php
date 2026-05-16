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

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$sql = "SELECT 
            b.id,
            b.batch_number as batch_id,
            b.created_at as date,
            b.status,
            b.approval_status,
            b.event_name,
            (SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = b.id) as item_count,
            (SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = b.id) as total_quantity,
            COALESCE(NULLIF(b.destination_room, ''), b.destination_name, 'N/A') as location,
            COALESCE(t.full_name, 'Unknown') as technician
        FROM stock_movements b
        LEFT JOIN users t ON b.technician_id = t.id
        WHERE DATE(b.created_at) BETWEEN ? AND ?";

// Role-Based Filtering
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$params = [$date_from, $date_to];
$types = "ss";

if ($userRole === 'stock_controller') {
    $sql .= " AND b.stock_controller_id = ?";
    $params[] = $userId;
    $types .= "i";
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$timeline = [];
while ($row = $result->fetch_assoc()) {
    $timeline[] = $row;
}

echo json_encode([
    'success' => true,
    'timeline' => $timeline
]);

$stmt->close();
$conn->close();
?>