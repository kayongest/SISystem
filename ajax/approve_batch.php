<?php
// ajax/approve_batch.php
session_start();
require_once '../includes/bootstrap.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['batch_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$batchId = intval($_POST['batch_id']);
$action = $_POST['action'];
$notes = $_POST['notes'] ?? '';
$userId = $_SESSION['user_id'] ?? 1; // Default to admin if not set

if ($action === 'approve') {
    $sql = "UPDATE batch_scans SET 
            approved_by = ?, 
            approved_at = NOW(), 
            approval_status = 'approved',
            notes = CONCAT(COALESCE(notes, ''), '\n\nApproved by user #{$userId} on ', NOW(), ': ', ?)
            WHERE id = ?";
} elseif ($action === 'reject') {
    $sql = "UPDATE batch_scans SET 
            approved_by = ?, 
            approved_at = NOW(), 
            approval_status = 'rejected',
            status = 'cancelled',
            notes = CONCAT(COALESCE(notes, ''), '\n\nRejected by user #{$userId} on ', NOW(), ': ', ?)
            WHERE id = ?";
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("isi", $userId, $notes, $batchId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Batch updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update batch: ' . $conn->error]);
}

$conn->close();
