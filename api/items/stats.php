<?php
// api/items/stats.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Define root directory
$rootDir = realpath(__DIR__ . '/../..');

$response = [
    'success' => false,
    'message' => '',
    'stats' => []
];

try {
    require_once $rootDir . '/includes/db_connect.php';
    require_once $rootDir . '/includes/functions.php';
    
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in', 401);
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Could not establish database connection');
    }
    
    // Get total count
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM items");
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    
    // Get status counts
    $statusStmt = $conn->prepare("SELECT status, COUNT(*) as count FROM items GROUP BY status");
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    $statusCounts = [];
    
    while ($row = $statusResult->fetch_assoc()) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
    // Get category breakdown
    $categoryStmt = $conn->prepare("SELECT category, COUNT(*) as count FROM items WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY count DESC");
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
    $categories = [];
    
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = [
            'category' => $row['category'],
            'count' => $row['count']
        ];
    }
    
    $totalStmt->close();
    $statusStmt->close();
    $categoryStmt->close();
    $db->close();
    
    $response['success'] = true;
    $response['message'] = 'Statistics loaded successfully';
    $response['stats'] = [
        'total' => $totalRow['total'] ?? 0,
        'available' => $statusCounts['available'] ?? 0,
        'in_use' => $statusCounts['in_use'] ?? 0,
        'maintenance' => $statusCounts['maintenance'] ?? 0,
        'reserved' => $statusCounts['reserved'] ?? 0,
        'lost' => $statusCounts['lost'] ?? 0,
        'damaged' => $statusCounts['damaged'] ?? 0,
        'categories' => $categories
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_PRETTY_PRINT);
exit();