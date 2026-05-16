<?php
session_start();
require_once '../includes/database_fix.php';

header('Content-Type: application/json');

$db = new DatabaseFix();
$conn = $db->getConnection();

$search = $_GET['q'] ?? '';

if (strlen($search) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search term too short']);
    exit();
}

$searchTerm = "%{$search}%";

$stmt = $conn->prepare("
    SELECT id, item_name, serial_number, status, stock_location, image 
    FROM items 
    WHERE item_name LIKE ? OR serial_number LIKE ?
    ORDER BY item_name 
    LIMIT 20
");
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$items = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'items' => $items
]);
?>