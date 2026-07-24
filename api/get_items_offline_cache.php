<?php
// api/get_items_offline_cache.php
header('Content-Type: application/json');
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ability_db';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$conn->set_charset("utf8mb4");

// Check if is_active column exists (default to checking column list if query fails)
$checkCol = $conn->query("SHOW COLUMNS FROM items LIKE 'is_active'");
$hasIsActive = ($checkCol && $checkCol->num_rows > 0);

$query = $hasIsActive 
    ? "SELECT id, item_name, serial_number, status, stock_location FROM items WHERE is_active = 1"
    : "SELECT id, item_name, serial_number, status, stock_location FROM items";

$result = $conn->query($query);

if ($result) {
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'name' => $row['item_name'],
            'serial' => $row['serial_number'],
            'status' => $row['status'],
            'location' => $row['stock_location']
        ];
    }
    echo json_encode([
        'success' => true,
        'count' => count($items),
        'items' => $items,
        'timestamp' => time()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
}

$conn->close();
?>
