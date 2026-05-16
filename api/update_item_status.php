<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$item_id = $data['item_id'] ?? '';
$status = $data['status'] ?? '';
$technician_id = $data['technician_id'] ?? '';

if (empty($item_id) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Update item status
    $stmt = $pdo->prepare("UPDATE items SET status = ? WHERE id = ?");
    $stmt->execute([$status, $item_id]);

    // Log the action
    $logStmt = $pdo->prepare("
        INSERT INTO item_logs 
        (item_id, action, performed_by, details) 
        VALUES (?, ?, ?, ?)
    ");

    $action = "Status changed to " . $status;
    $details = json_encode([
        'old_status' => 'unknown',
        'new_status' => $status,
        'technician_id' => $technician_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    $logStmt->execute([$item_id, $action, 'technician', $details]);

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
