<?php
// api/recent_items.php
header('Content-Type: application/json');

// Include required files
require_once '../bootstrap.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

try {
    // Get database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get recent items (last 10)
    $sql = "SELECT id, item_name, serial_number, category, status, 
                   created_at, stock_location, quantity
            FROM items 
            ORDER BY created_at DESC 
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Format the data for frontend
        $items[] = [
            'id' => $row['id'],
            'item_name' => $row['item_name'],
            'serial_number' => $row['serial_number'],
            'category' => getCategoryBadge($row['category']), // Returns HTML badge
            'status' => getStatusBadge($row['status']), // Returns HTML badge
            'created_at' => formatDate($row['created_at'], 'Y-m-d H:i:s'),
            'location' => $row['stock_location'],
            'quantity' => $row['quantity']
        ];
    }
    
    echo json_encode($items);
    
    $stmt->close();
    $db->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>