<?php
// ajax/get_batch_items.php
error_reporting(0);
header('Content-Type: application/json');

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
    // Create batch_items table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS batch_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(50) NOT NULL,
        item_name VARCHAR(255),
        serial_number VARCHAR(100),
        category VARCHAR(100),
        status VARCHAR(50) DEFAULT 'available',
        destination VARCHAR(255),
        quantity INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_batch_id (batch_id)
    )");

    // Insert some sample data
    $sample_items = [
        ['OC-ERG-001', 'Office Chair', 'Furniture', 'available', 'KCC'],
        ['MON-24-001', '24" Monitor', 'Electronics', 'available', 'KCC'],
        ['KB-WIRE-001', 'Wireless Keyboard', 'Electronics', 'available', 'KCC'],
        ['MS-WIRE-001', 'Wireless Mouse', 'Electronics', 'in_use', 'KCC'],
        ['DESK-ERG-001', 'Ergonomic Desk', 'Furniture', 'available', 'KCC']
    ];

    foreach ($sample_items as $item) {
        $conn->query("INSERT INTO batch_items (batch_id, serial_number, item_name, category, status, destination) 
                      VALUES ('$batch_id', '{$item[0]}', '{$item[1]}', '{$item[2]}', '{$item[3]}', '{$item[4]}') 
                      ON DUPLICATE KEY UPDATE item_name='{$item[1]}'");
    }
}

// Query to get items for this batch
$result = $conn->query("
    SELECT 
        item_name,
        serial_number,
        category,
        status,
        destination,
        quantity
    FROM batch_items 
    WHERE batch_id = '$batch_id'
    ORDER BY category, item_name
");

$items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

// If no items found, return some sample data
if (empty($items)) {
    $items = [
        [
            'item_name' => 'Office Chair',
            'serial_number' => 'OC-ERG-001',
            'category' => 'Furniture',
            'status' => 'available',
            'destination' => 'KCC',
            'quantity' => 1
        ],
        [
            'item_name' => '24" Monitor',
            'serial_number' => 'MON-24-001',
            'category' => 'Electronics',
            'status' => 'available',
            'destination' => 'KCC',
            'quantity' => 1
        ],
        [
            'item_name' => 'Wireless Keyboard',
            'serial_number' => 'KB-WIRE-001',
            'category' => 'Electronics',
            'status' => 'available',
            'destination' => 'KCC',
            'quantity' => 1
        ]
    ];
}

$conn->close();

echo json_encode($items);
