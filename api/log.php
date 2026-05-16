<?php
// api/scan/log.php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['item_id', 'scan_type', 'from_location', 'to_location', 'transport_user', 'vehicle_plate'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert scan log
    $stmt = $pdo->prepare("
        INSERT INTO scans (
            item_id, 
            user_id, 
            scan_type, 
            from_location, 
            to_location, 
            destination_address,
            transport_user,
            user_contact,
            user_department,
            user_id_number,
            vehicle_plate,
            vehicle_type,
            vehicle_description,
            transport_notes,
            expected_return,
            priority,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $userId = $_SESSION['user_id'] ?? null;
    
    $stmt->execute([
        $input['item_id'],
        $userId,
        $input['scan_type'],
        $input['from_location'],
        $input['to_location'],
        $input['destination_address'] ?? null,
        $input['transport_user'],
        $input['user_contact'] ?? null,
        $input['user_department'] ?? null,
        $input['user_id_number'] ?? null,
        $input['vehicle_plate'],
        $input['vehicle_type'] ?? null,
        $input['vehicle_description'] ?? null,
        $input['transport_notes'] ?? null,
        !empty($input['expected_return']) ? $input['expected_return'] : null,
        $input['priority'] ?? 'normal',
        $input['transport_notes'] ?? null
    ]);
    
    $scanId = $pdo->lastInsertId();
    
    // Update item status based on scan type
    if ($input['scan_type'] === 'check_in') {
        $newStatus = 'available';
        $newLocation = 'Stock';
    } else if ($input['scan_type'] === 'check_out') {
        $newStatus = 'checked_out';
        $newLocation = $input['to_location'];
    } else {
        $newStatus = $input['scan_type'];
        $newLocation = $input['to_location'] ?? $input['from_location'];
    }
    
    // Update item status and location
    $updateStmt = $pdo->prepare("
        UPDATE items 
        SET status = ?, 
            stock_location = ?,
            last_scan = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->execute([$newStatus, $newLocation, $input['item_id']]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Scan logged successfully',
        'scan_id' => $scanId
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}