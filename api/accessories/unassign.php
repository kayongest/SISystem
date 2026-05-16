<?php
// api/accessories/unassign.php
session_start();
require_once __DIR__ . '/../../includes/database_fix.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new DatabaseFix();
        $conn = $db->getConnection();

        $item_id = intval($_POST['item_id'] ?? 0);
        $accessory_id = intval($_POST['accessory_id'] ?? 0);

        if ($item_id < 1 || $accessory_id < 1) {
            throw new Exception('Invalid item or accessory ID');
        }


        // Start transaction
        $conn->begin_transaction();

        try {
            // Delete the assignment
            $deleteStmt = $conn->prepare("
                DELETE FROM item_accessories 
                WHERE item_id = ? AND accessory_id = ?
            ");
            $deleteStmt->bind_param("ii", $item_id, $accessory_id);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows === 0) {
                throw new Exception('Assignment not found');
            }

            // Increment available quantity
            $updateStmt = $conn->prepare("
                UPDATE accessories 
                SET available_quantity = available_quantity + 1 
                WHERE id = ?
            ");
            $updateStmt->bind_param("i", $accessory_id);
            $updateStmt->execute();

            // Commit transaction
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Accessory unassigned successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

        $db->close();
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
