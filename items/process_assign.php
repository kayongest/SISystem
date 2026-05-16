<?php
// items/process_assign.php
session_start();
require_once '../includes/bootstrap.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$accessory_id = intval($_POST['accessory_id'] ?? 0);
$item_ids = $_POST['item_ids'] ?? [];

if ($accessory_id < 1) {
    $_SESSION['error_message'] = 'Invalid accessory ID';
    header('Location: ../accessories.php');
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Delete all existing assignments for this accessory
    $deleteStmt = $conn->prepare("DELETE FROM item_accessories WHERE accessory_id = ?");
    $deleteStmt->bind_param("i", $accessory_id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Add new assignments if any items selected
    if (!empty($item_ids)) {
        $insertStmt = $conn->prepare("
            INSERT INTO item_accessories (accessory_id, item_id, assigned_date) 
            VALUES (?, ?, NOW())
        ");
        
        foreach ($item_ids as $item_id) {
            $item_id = intval($item_id);
            if ($item_id > 0) {
                $insertStmt->bind_param("ii", $accessory_id, $item_id);
                $insertStmt->execute();
            }
        }
        $insertStmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = 'Accessory assignments updated successfully!';
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && method_exists($conn, 'rollback')) {
        $conn->rollback();
    }
    
    $_SESSION['error_message'] = 'Error updating assignments: ' . $e->getMessage();
} finally {
    if (isset($db)) {
        $db->close();
    }
}

// Redirect back to assignment page
header('Location: assign_accessory.php?accessory_id=' . $accessory_id);
exit();
?>