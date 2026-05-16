<?php
// ajax/bulk_approve.php
session_start();
require_once '../includes/bootstrap.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['batch_ids']) || !is_array($_POST['batch_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$batchIds = array_map('intval', $_POST['batch_ids']);
$notes = $_POST['notes'] ?? '';
$userId = $_SESSION['user_id'] ?? 1;
$placeholders = str_repeat('?,', count($batchIds) - 1) . '?';

// Update all selected batches
$sql = "UPDATE batch_scans SET 
        approved_by = ?, 
        approved_at = NOW(), 
        approval_status = 'approved',
        notes = CONCAT(COALESCE(notes, ''), '\n\nApproved by user #{$userId} on ', NOW(), ': ', ?)
        WHERE id IN ($placeholders) AND approval_status = 'pending'";

$stmt = $conn->prepare($sql);

// Build parameters: $userId, $notes, then all batch IDs
$params = array_merge([$userId, $notes], $batchIds);
$types = str_repeat('i', count($batchIds));
$types = 'is' . $types; // $userId is int, $notes is string, batch IDs are ints

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $affectedRows = $stmt->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "Successfully approved {$affectedRows} batches"
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update batches: ' . $conn->error]);
}

$conn->close();
