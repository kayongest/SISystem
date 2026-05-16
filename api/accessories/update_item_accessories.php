<?php
// api/accessories/update_item_accessories.php
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
        $conn->begin_transaction();
        
        $item_id = intval($_POST['item_id'] ?? 0);
        $accessories_str = $_POST['accessories'] ?? '';
        
        if ($item_id < 1) {
            throw new Exception('Invalid item ID');
        }
        
        // Parse accessories string (comma-separated IDs)
        $new_accessories = [];
        if (!empty($accessories_str)) {
            $new_accessories = array_map('intval', explode(',', $accessories_str));
            $new_accessories = array_filter($new_accessories, function($id) {
                return $id > 0;
            });
            $new_accessories = array_unique($new_accessories);
        }
        
        // Get current accessories
        $currentStmt = $conn->prepare("
            SELECT accessory_id FROM item_accessories WHERE item_id = ?
        ");
        $currentStmt->bind_param("i", $item_id);
        $currentStmt->execute();
        $currentResult = $currentStmt->get_result();
        $current_accessories = [];
        while ($row = $currentResult->fetch_assoc()) {
            $current_accessories[] = $row['accessory_id'];
        }
        $currentStmt->close();
        
        // Find accessories to add and remove
        $to_add = array_diff($new_accessories, $current_accessories);
        $to_remove = array_diff($current_accessories, $new_accessories);
        
        // Check availability for accessories to add
        if (!empty($to_add)) {
            $add_list = implode(',', $to_add);
            $checkStmt = $conn->prepare("
                SELECT id, name, available_quantity 
                FROM accessories 
                WHERE id IN ($add_list) AND is_active = 1
            ");
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            $available_accessories = [];
            while ($acc = $checkResult->fetch_assoc()) {
                if ($acc['available_quantity'] < 1) {
                    throw new Exception('Accessory "' . $acc['name'] . '" is out of stock');
                }
                $available_accessories[] = $acc['id'];
            }
            $checkStmt->close();
            
            // Remove any accessories that aren't available
            $to_add = array_intersect($to_add, $available_accessories);
        }
        
        // Remove accessories
        if (!empty($to_remove)) {
            $remove_list = implode(',', $to_remove);
            
            $deleteStmt = $conn->prepare("
                DELETE FROM item_accessories 
                WHERE item_id = ? AND accessory_id IN ($remove_list)
            ");
            $deleteStmt->bind_param("i", $item_id);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Update accessory quantities (increase available)
            foreach ($to_remove as $acc_id) {
                $updateStmt = $conn->prepare("
                    UPDATE accessories 
                    SET available_quantity = available_quantity + 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param("i", $acc_id);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
        
        // Add accessories
        if (!empty($to_add)) {
            $addStmt = $conn->prepare("
                INSERT INTO item_accessories (item_id, accessory_id, created_at)
                VALUES (?, ?, NOW())
            ");
            
            foreach ($to_add as $acc_id) {
                $addStmt->bind_param("ii", $item_id, $acc_id);
                $addStmt->execute();
                
                // Update accessory quantities (decrease available)
                $updateStmt = $conn->prepare("
                    UPDATE accessories 
                    SET available_quantity = GREATEST(0, available_quantity - 1),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param("i", $acc_id);
                $updateStmt->execute();
                $updateStmt->close();
            }
            $addStmt->close();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Accessories updated successfully. ' .
                         (count($to_add) > 0 ? 'Added ' . count($to_add) . ' accessories. ' : '') .
                         (count($to_remove) > 0 ? 'Removed ' . count($to_remove) . ' accessories.' : ''),
            'added_count' => count($to_add),
            'removed_count' => count($to_remove)
        ]);
        
        $db->close();
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->in_transaction) {
            $conn->rollback();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>