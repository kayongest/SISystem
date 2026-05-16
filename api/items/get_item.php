<?php
// api/get_item.php - Get single item details by ID
require_once '../bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get item ID from request
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

require_once '../includes/db_connect.php';
$conn = getConnection();

// Get item details
$stmt = $conn->prepare("
    SELECT 
        i.id,
        i.item_name,
        i.serial_number,
        i.quantity,
        i.status,
        i.condition,
        i.stock_location,
        i.description,
        i.brand,
        i.model,
        i.department,
        i.created_at,
        i.updated_at,
        c.name as category,
        d.name as department_name
    FROM items i
    LEFT JOIN categories c ON i.category = c.id
    LEFT JOIN departments d ON i.department = d.id
    WHERE i.id = ?
");

$stmt->bind_param('i', $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit();
}

$item = $result->fetch_assoc();

// Get accessories for this item
$accessories = [];
$acc_stmt = $conn->prepare("
    SELECT a.name 
    FROM accessories a
    JOIN item_accessories ia ON a.id = ia.accessory_id
    WHERE ia.item_id = ?
");

$acc_stmt->bind_param('i', $item_id);
$acc_stmt->execute();
$acc_result = $acc_stmt->get_result();

while ($acc = $acc_result->fetch_assoc()) {
    $accessories[] = $acc['name'];
}

$item['accessories'] = $accessories;

// Clean up null/undefined values
foreach ($item as $key => $value) {
    if ($value === null) {
        $item[$key] = '';
    }
}

echo json_encode([
    'success' => true,
    'data' => $item
]);

$stmt->close();
$acc_stmt->close();
$conn->close();
?>