<?php
// api/scan/get_details.php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid scan ID']);
    exit();
}

$scanId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            i.name as item_name,
            i.serial_number,
            i.category,
            i.status as item_status,
            i.stock_location,
            i.department,
            u.full_name,
            u.username,
            u.email
        FROM scans s
        LEFT JOIN items i ON s.item_id = i.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    
    $stmt->execute([$scanId]);
    $scan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scan) {
        echo json_encode([
            'success' => false, 
            'message' => 'Scan not found'
        ]);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $scan
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}