<?php
// api/quick_search.php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = getConnection();
    $searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    // Get search results
    $results = [];
    
    if (strlen($searchTerm) >= 2) {
        $likeTerm = '%' . $searchTerm . '%';
        
        // Search query
        $stmt = $db->prepare("
            SELECT id, item_name, serial_number, status, stock_location, image, description 
            FROM items 
            WHERE item_name LIKE ? 
               OR serial_number LIKE ? 
               OR brand LIKE ? 
               OR model LIKE ? 
               OR description LIKE ?
            LIMIT 20
        ");
        $stmt->bind_param("sssss", $likeTerm, $likeTerm, $likeTerm, $likeTerm, $likeTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    
    // Get statistics for dashboard
    $stats = [
        'total' => 0,
        'available' => 0,
        'in_use' => 0,
        'maintenance' => 0,
        'categories' => []
    ];
    
    // Get total items count
    $totalResult = $db->query("SELECT COUNT(*) as count FROM items");
    if ($totalResult) {
        $stats['total'] = (int)$totalResult->fetch_assoc()['count'];
    }
    
    // Get status counts
    $statusResult = $db->query("
        SELECT 
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as in_use,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
        FROM items
    ");
    if ($statusResult) {
        $row = $statusResult->fetch_assoc();
        $stats['available'] = (int)$row['available'];
        $stats['in_use'] = (int)$row['in_use'];
        $stats['maintenance'] = (int)$row['maintenance'];
    }
    
    // Get top categories
    $catResult = $db->query("
        SELECT category, COUNT(*) as count 
        FROM items 
        WHERE category IS NOT NULL AND category != ''
        GROUP BY category 
        ORDER BY count DESC 
        LIMIT 5
    ");
    if ($catResult) {
        while ($cat = $catResult->fetch_assoc()) {
            $stats['categories'][] = $cat;
        }
    }
    
    echo json_encode([
        'success' => true,
        'items' => $results,
        'stats' => $stats,  // This is the key part!
        'count' => count($results),
        'message' => strlen($searchTerm) < 2 ? 'Search term too short' : ''
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>