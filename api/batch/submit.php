<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No items']);
    exit();
}

$conn->begin_transaction();

try {
    // Use default user ID (1) since session has 0
    $userId = 1;
    $technicianId = isset($input['technician']['id']) ? (int)$input['technician']['id'] : 'NULL';
    $technicianName = $conn->real_escape_string($input['technician']['full_name'] ?? 'Technician');
    
    // Generate batch ID
    $batchId = 'BATCH-' . date('YmdHis') . '-' . uniqid();
    $batchName = 'Batch ' . date('Y-m-d H:i:s');
    
    // Get job details
    $jobDetails = $input['jobDetails'] ?? [];
    $actionApplied = $conn->real_escape_string($jobDetails['batchAction'] ?? 'transfer');
    $locationApplied = $conn->real_escape_string($jobDetails['batchLocation'] ?? 'KCC');
    $batchNotes = $conn->real_escape_string($jobDetails['batchNotes'] ?? '');
    
    // Calculate totals
    $totalItems = count($input['items']);
    $uniqueItems = $totalItems;
    
    // Insert batch header
    $sql = "INSERT INTO batch_scans (
        batch_id, batch_name, total_items, unique_items, 
        submitted_by, requested_by, technician_name,
        action_applied, location_applied, notes, 
        status, approval_status, submitted_at
    ) VALUES (
        '$batchId', '$batchName', $totalItems, $uniqueItems,
        $userId, $technicianId, '$technicianName',
        '$actionApplied', '$locationApplied', '$batchNotes',
        'pending', 'pending', NOW()
    )";
    
    if (!$conn->query($sql)) {
        throw new Exception("Batch insert failed: " . $conn->error);
    }
    
    $batchDbId = $conn->insert_id;
    
    // Insert items
    $itemSuccess = 0;
    foreach ($input['items'] as $item) {
        $itemId = isset($item['id']) && $item['id'] > 0 ? (int)$item['id'] : 'NULL';
        $itemName = $conn->real_escape_string($item['name'] ?? $item['item_name'] ?? 'Unknown');
        $serialNumber = $conn->real_escape_string($item['serial_number'] ?? $item['serial'] ?? '');
        $category = $conn->real_escape_string($item['category'] ?? '');
        $status = $conn->real_escape_string($item['status'] ?? 'available');
        $location = $conn->real_escape_string($item['stock_location'] ?? $item['location'] ?? '');
        $quantity = (int)($item['quantity'] ?? 1);
        
        $itemSql = "INSERT INTO batch_items (
            batch_id, item_id, item_name, serial_number, category,
            original_status, new_status, original_location, new_location,
            quantity, scanned_at
        ) VALUES (
            '$batchId', $itemId, '$itemName', '$serialNumber', '$category',
            '$status', '$status', '$location', '$locationApplied',
            $quantity, NOW()
        )";
        
        if ($conn->query($itemSql)) {
            $itemSuccess++;
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Batch submitted successfully!',
        'batch_id' => $batchId,
        'batch_db_id' => $batchDbId,
        'items_submitted' => $itemSuccess
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>