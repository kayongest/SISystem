<?php
// api/scan/delete.php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid scan ID']);
    exit();
}

$scanId = (int)$_POST['id'];

try {
    // First get the scan details to update the item status if needed
    $stmt = $pdo->prepare("SELECT item_id, scan_type FROM scans WHERE id = ?");
    $stmt->execute([$scanId]);
    $scan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scan) {
        echo json_encode(['success' => false, 'message' => 'Scan not found']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete the scan
    $deleteStmt = $pdo->prepare("DELETE FROM scans WHERE id = ?");
    $deleteStmt->execute([$scanId]);
    
    // Note: In a real application, you might want to update the item status
    // based on the latest scan instead of leaving it as is
    // For simplicity, we're just deleting the scan record
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Scan deleted successfully'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}