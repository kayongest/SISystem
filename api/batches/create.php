<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// For testing, just return success
echo json_encode([
    'success' => true,
    'message' => 'Batch created successfully (Test Mode)',
    'batch_id' => $input['batch_id'] ?? 'BATCH-' . time(),
    'db_id' => rand(1000, 9999)
]);
?>