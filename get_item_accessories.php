<?php
// api/get_item_accessories.php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
        throw new Exception('Invalid item ID');
    }
    
    $itemId = (int)$_GET['item_id'];
    $db = getConnection();
    
    // Get accessories for this item
    $stmt = $db->prepare("
        SELECT a.id, a.name, a.description 
        FROM accessories a
        INNER JOIN item_accessories ia ON a.id = ia.accessory_id
        WHERE ia.item_id = ?
        ORDER BY a.name
    ");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accessories = [];
    while ($row = $result->fetch_assoc()) {
        $accessories[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'accessories' => $accessories,
        'count' => count($accessories),
        'item_id' => $itemId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>