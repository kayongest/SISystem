<?php
// api/items/get.php
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Item ID required']);
    exit();
}

$item_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            c.name as category_name,
            u.username as created_by_username
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN users u ON i.created_by = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        echo json_encode([
            'success' => true,
            'item' => $item
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Item not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>