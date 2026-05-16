<?php
// api/debug_item.php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Get ID from query string
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        // Try to get from POST as fallback
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    }
    
    if (!$id) {
        throw new Exception('Item ID required - please provide an ID parameter');
    }
    
    $db = getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get ALL item details - no filters
    $stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Item with ID $id not found");
    }
    
    $item = $result->fetch_assoc();
    
    // Get accessories for this item
    $accStmt = $db->prepare("
        SELECT a.* 
        FROM accessories a
        JOIN item_accessories ia ON a.id = ia.accessory_id
        WHERE ia.item_id = ?
    ");
    $accStmt->bind_param("i", $id);
    $accStmt->execute();
    $accResult = $accStmt->get_result();
    
    $accessories = [];
    while ($acc = $accResult->fetch_assoc()) {
        $accessories[] = $acc;
    }
    
    // Get category details if category ID exists
    $category = null;
    if (!empty($item['category']) && is_numeric($item['category'])) {
        $catStmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $catStmt->bind_param("i", $item['category']);
        $catStmt->execute();
        $catResult = $catStmt->get_result();
        $category = $catResult->fetch_assoc();
    }
    
    // Get department details if department ID exists
    $department = null;
    if (!empty($item['department']) && is_numeric($item['department'])) {
        $deptStmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
        $deptStmt->bind_param("i", $item['department']);
        $deptStmt->execute();
        $deptResult = $deptStmt->get_result();
        $department = $deptResult->fetch_assoc();
    }
    
    // Return complete data
    echo json_encode([
        'success' => true,
        'item' => $item,
        'accessories' => $accessories,
        'category' => $category,
        'department' => $department,
        'debug' => [
            'query_time' => date('Y-m-d H:i:s'),
            'database' => 'ability_db',
            'table' => 'items',
            'id_requested' => $id
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'query_time' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage()
        ]
    ]);
}
?>