<?php
// api/get_recent_items.php

session_start();
require_once __DIR__ . '/../includes/database_fix.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

try {
    // Get database connection
    $db = new DatabaseFix();
    $conn = $db->getConnection();
    
    // Get recent items (you can reuse your existing function)
    $limit = 100;
    $items = [];
    
    $sql = "SELECT 
                i.id, 
                i.item_name, 
                i.serial_number, 
                i.category, 
                i.brand, 
                i.model, 
                i.department, 
                i.description,
                i.condition,
                i.stock_location,
                i.quantity,
                i.status,
                i.qr_code,
                i.created_at,
                i.updated_at
            FROM items i
            ORDER BY i.created_at DESC 
            LIMIT " . (int)$limit;

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get accessories for each item
            $accessories = [];
            $accSql = "SELECT a.id, a.name 
                      FROM accessories a
                      INNER JOIN item_accessories ia ON a.id = ia.accessory_id
                      WHERE ia.item_id = ? AND a.is_active = 1";
                      
            $accStmt = $conn->prepare($accSql);
            if ($accStmt) {
                $accStmt->bind_param("i", $row['id']);
                $accStmt->execute();
                $accResult = $accStmt->get_result();
                while ($acc = $accResult->fetch_assoc()) {
                    $accessories[] = $acc;
                }
                $accStmt->close();
            }
            
            $row['accessories'] = $accessories;
            $items[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>