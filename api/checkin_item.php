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
$returnCondition = $input['return_condition'] ?? 'good';
$notes = $input['notes'] ?? null;

if (!$itemId) {
    echo json_encode(['success' => false, 'message' => 'Item ID required']);
    exit();
}

$conn->begin_transaction();

try {
    // Get current item status
    $checkStmt = $conn->prepare("SELECT status, item_name, stock_location FROM items WHERE id = ? FOR UPDATE");
    $checkStmt->bind_param("i", $itemId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $item = $result->fetch_assoc();
    
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    $previousStatus = $item['status'];
    
    if ($previousStatus === 'available') {
        throw new Exception('Item is already available in stock');
    }
    
    // Find active checkout record
    $checkoutStmt = $conn->prepare("
        SELECT id FROM item_checkouts 
        WHERE item_id = ? AND status = 'checked_out' 
        ORDER BY checkout_date DESC LIMIT 1
    ");
    $checkoutStmt->bind_param("i", $itemId);
    $checkoutStmt->execute();
    $checkoutResult = $checkoutStmt->get_result();
    $checkout = $checkoutResult->fetch_assoc();
    
    // Update checkout record
    if ($checkout) {
        $updateCheckoutStmt = $conn->prepare("
            UPDATE item_checkouts 
            SET actual_return_date = NOW(), status = 'returned', notes = CONCAT(notes, ' Return notes: ', ?)
            WHERE id = ?
        ");
        $updateCheckoutStmt->bind_param("si", $notes, $checkout['id']);
        $updateCheckoutStmt->execute();
    }
    
    // Update item status and condition
    $updateStmt = $conn->prepare("
        UPDATE items 
        SET status = 'available', condition = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->bind_param("si", $returnCondition, $itemId);
    $updateStmt->execute();
    
    // Log scan history
    $userId = $_SESSION['user_id'] ?? null;
    $logStmt = $conn->prepare("
        INSERT INTO scan_history (item_id, user_id, scan_type, checkout_id,
                                  previous_status, new_status, previous_location, notes)
        VALUES (?, ?, 'check_in', ?, ?, 'available', ?, ?)
    ");
    
    $checkoutId = $checkout['id'] ?? null;
    $logStmt->bind_param("iiisss", $itemId, $userId, $checkoutId, $previousStatus, $item['stock_location'], $notes);
    $logStmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Item checked in successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>