<?php
header('Content-Type: application/json');
require_once '../includes/bootstrap.php';
require_once '../includes/database_fix.php';

session_start();

$db = new DatabaseFix();
$conn = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$itemId = $input['item_id'] ?? null;

if (!$itemId) {
    echo json_encode(['success' => false, 'message' => 'Item ID required']);
    exit();
}

$userId = $_SESSION['user_id'] ?? null;

$stmt = $conn->prepare("
    INSERT INTO scan_history (item_id, user_id, scan_type, notes)
    VALUES (?, ?, 'inventory', 'Inventory verification scan')
");
$stmt->bind_param("ii", $itemId, $userId);

if ($stmt->execute()) {
    // Update last_scanned timestamp
    $updateStmt = $conn->prepare("UPDATE items SET last_scanned = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $itemId);
    $updateStmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Inventory scan recorded']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record inventory scan']);
}
?>