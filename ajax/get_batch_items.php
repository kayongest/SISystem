<?php
// ajax/get_batch_items.php
error_reporting(0);
header('Content-Type: application/json');

// Start session and check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');

// Check connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get batch_id from GET parameter
$batch_id = isset($_GET['batch_id']) ? $conn->real_escape_string($_GET['batch_id']) : '';

if (empty($batch_id)) {
    echo json_encode(['error' => 'Batch ID is required']);
    exit();
}

// First, check if there's a batch_items table
$table_check = $conn->query("SHOW TABLES LIKE 'batch_items'");
if ($table_check->num_rows == 0) {
    // Return empty array if table doesn't exist
    echo json_encode([]);
    exit();
}

// Query to get items for this batch - use batch_id string, not numeric ID
$result = $conn->query("
    SELECT 
        bi.item_name,
        bi.serial_number,
        bi.category,
        bi.new_status as status,
        bi.new_location as destination,
        bi.quantity
    FROM batch_items bi 
    WHERE bi.batch_id = '$batch_id'
    ORDER BY bi.id
");

$items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'item_name' => $row['item_name'] ?? 'Unknown Item',
            'serial_number' => $row['serial_number'] ?? 'N/A',
            'category' => $row['category'] ?? 'N/A',
            'status' => $row['status'] ?? 'available',
            'destination' => $row['destination'] ?? 'KCC',
            'quantity' => $row['quantity'] ?? 1
        ];
    }
}

$conn->close();

// Return items (empty array if none found)
echo json_encode($items);
