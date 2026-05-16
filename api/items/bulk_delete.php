<?php
// api/items/bulk_delete.php - Bulk delete items
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
$ids = isset($_POST['ids']) ? $_POST['ids'] : [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit();
}

// Sanitize IDs
$ids = array_map('intval', $ids);
$ids = array_filter($ids);

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid item IDs']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Create placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Get items to delete (for logging)
    $selectStmt = $conn->prepare("SELECT id, item_name FROM items WHERE id IN ($placeholders)");
    $selectStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $selectStmt->close();

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items found']);
        exit();
    }

    // Delete the items
    $deleteStmt = $conn->prepare("DELETE FROM items WHERE id IN ($placeholders)");
    $deleteStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $deleteStmt->execute();
    
    $deletedCount = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($deletedCount > 0) {
        // Log the activity
        $itemNames = array_column($items, 'item_name');
        logActivity($conn, $_SESSION['user_id'], 'bulk_delete', "Deleted items: " . implode(', ', $itemNames));
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => "$deletedCount item(s) deleted successfully",
            'deleted_count' => $deletedCount,
            'items' => $items
        ]);
    } else {
        throw new Exception('Failed to delete items');
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting items: ' . $e->getMessage()]);
}