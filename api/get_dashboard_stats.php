<?php
// api/get_dashboard_stats.php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
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
        $row = $totalResult->fetch_assoc();
        $stats['total'] = (int)$row['count'];
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
            $stats['categories'][] = [
                'category' => $cat['category'],
                'count' => (int)$cat['count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>