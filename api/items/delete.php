<?php
// api/items/delete.php - Delete single item
require_once '../../bootstrap.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check authentication and permission
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!hasPermission('delete_equipment')) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete equipment']);
    exit();
}

$conn = getConnection();

// Get POST data
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if item exists
    $checkStmt = $conn->prepare("SELECT id, item_name FROM items WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit();
    }
    
    $item = $result->fetch_assoc();
    $checkStmt->close();

    // Delete the item
    $deleteStmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    $deleteStmt->execute();
    
    if ($deleteStmt->affected_rows > 0) {
        // Log the activity
        logActivity($conn, $_SESSION['user_id'], 'delete_item', "Deleted item: " . $item['item_name']);
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Item deleted successfully',
            'item' => $item
        ]);
    } else {
        throw new Exception('Failed to delete item');
    }
    
    $deleteStmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting item: ' . $e->getMessage()]);
}