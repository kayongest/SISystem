<?php
// api/accessories/bulk_assign.php
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

        // Get POST data
        $accessories = $_POST['accessories'] ?? [];
        $items = $_POST['items'] ?? [];
        $mode = $_POST['mode'] ?? 'add';

        // Validate
        if (empty($accessories)) {
            throw new Exception('No accessories selected');
        }

        if (empty($items)) {
            throw new Exception('No equipment items selected');
        }

        // Convert to integers
        $accessory_ids = array_map('intval', $accessories);
        $item_ids = array_map('intval', $items);

        // Start transaction
        $conn->begin_transaction();

        try {
            $totalAssignments = 0;

            foreach ($item_ids as $item_id) {
                if ($mode === 'replace') {
                    // Remove existing assignments for this item
                    $deleteStmt = $conn->prepare("
                        DELETE FROM item_accessories WHERE item_id = ?
                    ");
                    $deleteStmt->bind_param("i", $item_id);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }

                foreach ($accessory_ids as $accessory_id) {
                    // Check if already assigned (for add mode)
                    if ($mode === 'add') {
                        $checkStmt = $conn->prepare("
                            SELECT id FROM item_accessories 
                            WHERE item_id = ? AND accessory_id = ?
                        ");
                        $checkStmt->bind_param("ii", $item_id, $accessory_id);
                        $checkStmt->execute();
                        $checkStmt->store_result();

                        if ($checkStmt->num_rows > 0) {
                            $checkStmt->close();
                            continue; // Skip if already assigned
                        }
                        $checkStmt->close();
                    }

                    // Check if accessory has available quantity
                    $qtyCheck = $conn->prepare("
                        SELECT available_quantity FROM accessories 
                        WHERE id = ? AND is_active = 1 AND available_quantity > 0
                    ");
                    $qtyCheck->bind_param("i", $accessory_id);
                    $qtyCheck->execute();
                    $qtyResult = $qtyCheck->get_result();
                    $qtyRow = $qtyResult->fetch_assoc();
                    $qtyCheck->close();

                    if (!$qtyRow) {
                        throw new Exception("Accessory ID $accessory_id is out of stock or not found");
                    }

                    // Insert assignment
                    $assignStmt = $conn->prepare("
                        INSERT INTO item_accessories (item_id, accessory_id, assigned_at)
                        VALUES (?, ?, NOW())
                    ");
                    $assignStmt->bind_param("ii", $item_id, $accessory_id);
                    $assignStmt->execute();
                    $assignStmt->close();

                    // Decrement available quantity
                    $updateStmt = $conn->prepare("
                        UPDATE accessories 
                        SET available_quantity = available_quantity - 1 
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("i", $accessory_id);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $totalAssignments++;
                }
            }

            // Commit transaction
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => "Successfully assigned $totalAssignments accessory(s)",
                'assignments' => $totalAssignments
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
