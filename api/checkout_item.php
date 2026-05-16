<?php
header('Content-Type: application/json');
require_once '../includes/bootstrap.php';
require_once '../includes/database_fix.php';

session_start();

$db = new DatabaseFix();
$conn = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$itemId = $input['item_id'] ?? null;
$technicianId = $input['technician_id'] ?? null;
$expectedReturnDate = $input['expected_return_date'] ?? null;
$destinationLocation = $input['destination_location'] ?? null;
$purpose = $input['purpose'] ?? null;
$notes = $input['notes'] ?? null;

if (!$itemId || !$technicianId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if item is available
    $checkStmt = $conn->prepare("SELECT status, item_name FROM items WHERE id = ? FOR UPDATE");
    $checkStmt->bind_param("i", $itemId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $item = $result->fetch_assoc();
    
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    if ($item['status'] !== 'available') {
        throw new Exception('Item is not available for checkout');
    }
    
    // Generate checkout code
    $checkoutCode = 'CHK-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Create checkout record
    $insertStmt = $conn->prepare("
        INSERT INTO item_checkouts (item_id, technician_id, checkout_code, expected_return_date, 
                                    destination_location, purpose, notes, status, approved_by, approved_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'checked_out', ?, NOW())
    ");
    
    $userId = $_SESSION['user_id'] ?? null;
    $insertStmt->bind_param("iisssssi", $itemId, $technicianId, $checkoutCode, $expectedReturnDate, 
                            $destinationLocation, $purpose, $notes, $userId);
    $insertStmt->execute();
    $checkoutId = $conn->insert_id;
    
    // Update item status
    $updateStmt = $conn->prepare("UPDATE items SET status = 'in_use', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $itemId);
    $updateStmt->execute();
    
    // Log scan history
    $logStmt = $conn->prepare("
        INSERT INTO scan_history (item_id, user_id, technician_id, scan_type, checkout_id,
                                  previous_status, new_status, previous_location, notes)
        VALUES (?, ?, ?, 'check_out', ?, 'available', 'in_use', ?, ?)
    ");
    
    $previousLocation = $item['stock_location'] ?? 'Stock';
    $logStmt->bind_param("iiisss", $itemId, $userId, $technicianId, $checkoutId, $previousLocation, $notes);
    $logStmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Item checked out successfully. Code: {$checkoutCode}",
        'checkout_code' => $checkoutCode
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>