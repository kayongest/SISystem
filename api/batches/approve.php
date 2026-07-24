<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ability_db';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    $conn->close();
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$batch_id = $input['batch_id'] ?? '';
$action = $input['action'] ?? '';
$notes = $input['notes'] ?? '';

if (empty($batch_id)) {
    echo json_encode(['success' => false, 'message' => 'Batch ID required']);
    $conn->close();
    exit();
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    $conn->close();
    exit();
}

// Check if batch exists and is pending
$checkSql = "SELECT status, approval_status, stock_controller_id, movement_type FROM stock_movements WHERE batch_number = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $batch_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
    $checkStmt->close();
    $conn->close();
    exit();
}

$batch = $result->fetch_assoc();

// Role-Based Restriction: Stock Controllers can only approve batches assigned to them OR transport batches (involving a driver)
$isTransport = in_array($batch['movement_type'], ['transport', 'stock_to_venue_transport', 'stock_to_stock']);
if ($_SESSION['role'] === 'stock_controller' && !$isTransport && $batch['stock_controller_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Access denied. You can only manage batches assigned to you.']);
    $checkStmt->close();
    $conn->close();
    exit();
}

$currentStatus = $batch['status'] ?? $batch['approval_status'];
if ($currentStatus !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Batch already ' . $currentStatus]);
    $checkStmt->close();
    $conn->close();
    exit();
}
$checkStmt->close();

// Update batch
if ($action === 'approve') {
    $sql = "UPDATE stock_movements 
            SET status = 'approved', 
                approval_status = 'approved',
                approved_by_id = ?, 
                approved_at = NOW(),
                approval_notes = ?
            WHERE batch_number = ?";
} else {
    $sql = "UPDATE stock_movements 
            SET status = 'rejected', 
                approval_status = 'rejected',
                approved_by_id = ?, 
                approved_at = NOW(),
                rejection_reason = ?
            WHERE batch_number = ?";
}

$stmt = $conn->prepare($sql);
$approved_by_id = $_SESSION['user_id'];

if ($action === 'approve') {
    $stmt->bind_param("iss", $approved_by_id, $notes, $batch_id);
} else {
    $stmt->bind_param("iss", $approved_by_id, $notes, $batch_id);
}

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => $action === 'approve' ? 'Batch approved successfully!' : 'Batch rejected',
        'batch_id' => $batch_id,
        'status' => $action === 'approve' ? 'approved' : 'rejected'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update batch: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>