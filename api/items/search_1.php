<?php
// api/items/search.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Empty search query']);
    exit;
}

try {
    $search_term = "%$query%";
    
    $sql = "SELECT * FROM items 
            WHERE item_name LIKE ? 
               OR serial_number LIKE ? 
               OR id = ? 
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    
    // Try to parse query as ID
    $id = is_numeric($query) ? (int)$query : 0;
    
    $stmt->execute([$search_term, $search_term, $id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Search error: ' . $e->getMessage()]);
}