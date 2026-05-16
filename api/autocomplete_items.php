<?php
// api/autocomplete_items.php
session_start();

require_once dirname(__DIR__) . '/includes/database_fix.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$term = $_GET['term'] ?? '';

if (empty($term) || strlen($term) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();
    
    // Search for items with autocomplete
    $sql = "
        SELECT 
            DISTINCT item_name,
            SUM(quantity) as total_count
        FROM items 
        WHERE item_name LIKE ? 
        AND status NOT IN ('disposed', 'lost')
        GROUP BY item_name
        ORDER BY 
            CASE 
                WHEN item_name LIKE ? THEN 1  -- Exact match
                WHEN item_name LIKE ? THEN 2  -- Starts with
                ELSE 3                         -- Contains
            END,
            total_count DESC,
            item_name ASC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $conn->error);
    }
    
    $exactTerm = $term . '%';
    $startsWith = $term . '%';
    $contains = '%' . $term . '%';
    
    $stmt->bind_param("sss", $contains, $exactTerm, $startsWith);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'label' => $row['item_name'] . " (" . $row['total_count'] . ")",
            'value' => $row['item_name'],
            'count' => $row['total_count']
        ];
    }
    
    echo json_encode($items);
    
    $stmt->close();
    $db->close();
    
} catch (Exception $e) {
    error_log("Autocomplete error: " . $e->getMessage());
    echo json_encode([]);
}
?>