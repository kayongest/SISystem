<?php
// api/accessories/assign_single.php
session_start();

// Adjust the path based on your directory structure
// Try different paths until you find the right one

$possiblePaths = [
    __DIR__ . '/../../includes/bootstrap.php',      // api/accessories/../../includes/
    __DIR__ . '/../includes/bootstrap.php',         // api/accessories/../includes/
    dirname(__DIR__, 2) . '/includes/bootstrap.php', // Two levels up
    'C:/xampp/htdocs/ability_app_main/includes/bootstrap.php' // Absolute path
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// If still not found, check what's in includes directory
if (!class_exists('DatabaseFix')) {
    // Try to require database directly
    require_once dirname(__DIR__, 2) . '/includes/database_fix.php';
    require_once dirname(__DIR__, 2) . '/includes/functions.php';
}

header('Content-Type: application/json');

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}


// Get input data
$accessory_id = intval($_POST['accessory_id'] ?? 0);
$item_ids = $_POST['item_ids'] ?? [];

if ($accessory_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid accessory ID']);
    exit();
}

if (empty($item_ids) || !is_array($item_ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Begin transaction
    $conn->begin_transaction();

    // First, get current assignments for this accessory
    $currentStmt = $conn->prepare("
        SELECT item_id 
        FROM item_accessories 
        WHERE accessory_id = ?
    ");
    $currentStmt->bind_param("i", $accessory_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $current_items = [];
    while ($row = $currentResult->fetch_assoc()) {
        $current_items[] = $row['item_id'];
    }
    $currentStmt->close();

    // Determine items to add and remove
    $new_items = array_map('intval', $item_ids);
    $items_to_add = array_diff($new_items, $current_items);
    $items_to_remove = array_diff($current_items, $new_items);

    // Remove items that are no longer selected
    if (!empty($items_to_remove)) {
        $removePlaceholders = implode(',', array_fill(0, count($items_to_remove), '?'));
        $removeStmt = $conn->prepare("
            DELETE FROM item_accessories 
            WHERE accessory_id = ? AND item_id IN ($removePlaceholders)
        ");

        $params = array_merge([$accessory_id], $items_to_remove);
        $types = str_repeat('i', count($params));
        $removeStmt->bind_param($types, ...$params);
        $removeStmt->execute();
        $removeStmt->close();
    }

    // Add new items
    $added_count = 0;
    if (!empty($items_to_add)) {
        $addStmt = $conn->prepare("
            INSERT INTO item_accessories (accessory_id, item_id, assigned_date) 
            VALUES (?, ?, NOW())
        ");

        foreach ($items_to_add as $item_id) {
            // Check if item exists
            $checkStmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
            $checkStmt->bind_param("i", $item_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $addStmt->bind_param("ii", $accessory_id, $item_id);
                if ($addStmt->execute()) {
                    $added_count++;
                }
            }
            $checkStmt->close();
        }
        $addStmt->close();
    }

    // Commit transaction
    $conn->commit();

    $removed_count = count($items_to_remove);
    $message = "Assignments updated successfully.";

    if ($added_count > 0) {
        $message .= " Added to {$added_count} item(s).";
    }
    if ($removed_count > 0) {
        $message .= " Removed from {$removed_count} item(s).";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'added' => $added_count,
        'removed' => $removed_count
    ]);
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && method_exists($conn, 'rollback')) {
        $conn->rollback();
    }

    error_log("Assignment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($db)) {
        $db->close();
    }
}
