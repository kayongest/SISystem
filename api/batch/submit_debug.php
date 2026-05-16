<?php
// api/batch/submit_debug.php - SIMPLIFIED DEBUG VERSION
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Log the input for debugging
file_put_contents('batch_debug.txt', 
    "[" . date('Y-m-d H:i:s') . "]\n" . 
    json_encode($input, JSON_PRETTY_PRINT) . "\n\n", 
    FILE_APPEND
);

// Check if we have valid data
if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'No JSON data received',
        'debug' => file_get_contents('php://input')
    ]);
    exit();
}

// Validate required fields
if (!isset($input['technician']) || !isset($input['items'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields',
        'received' => array_keys($input)
    ]);
    exit();
}

// Return success without database for testing
echo json_encode([
    'success' => true,
    'message' => 'DEBUG MODE: Batch would be submitted',
    'technician' => $input['technician']['full_name'] ?? 'Unknown',
    'item_count' => count($input['items']),
    'batch_id' => $input['batchId'] ?? 'DEBUG-' . time(),
    'debug_mode' => true
]);