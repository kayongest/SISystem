<?php
// api/items/get_by_id.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get item ID
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Query the items table
$stmt = $conn->prepare("
    SELECT 
        id,
        item_name as name,
        item_name,
        serial_number,
        serial,
        category,
        category_name,
        status,
        stock_location,
        location,
        quantity,
        brand,
        model,
        description
    FROM items 
    WHERE id = ? OR item_id = ?
");

$stmt->bind_param("ii", $itemId, $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Format the response
    $response = [
        'success' => true,
        'item' => [
            'id' => $row['id'],
            'item_id' => $row['id'],
            'name' => $row['item_name'] ?? $row['name'] ?? 'Unknown Item',
            'item_name' => $row['item_name'] ?? $row['name'] ?? 'Unknown Item',
            'serial_number' => $row['serial_number'] ?? $row['serial'] ?? '',
            'serial' => $row['serial_number'] ?? $row['serial'] ?? '',
            'category' => $row['category'] ?? $row['category_name'] ?? 'Uncategorized',
            'category_name' => $row['category'] ?? $row['category_name'] ?? 'Uncategorized',
            'status' => $row['status'] ?? 'available',
            'stock_location' => $row['stock_location'] ?? $row['location'] ?? 'Ndera Stock',
            'location' => $row['stock_location'] ?? $row['location'] ?? 'Ndera Stock',
            'quantity' => $row['quantity'] ?? 1,
            'brand' => $row['brand'] ?? '',
            'model' => $row['model'] ?? '',
            'description' => $row['description'] ?? ''
        ]
    ];
    
    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Item not found with ID: ' . $itemId
    ]);
}

$conn->close();
?>