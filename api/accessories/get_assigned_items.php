<?php
// api/accessories/get_assigned_items.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$accessory_id = intval($_GET['accessory_id'] ?? 0);

if ($accessory_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid accessory ID']);
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Get accessory details
    $accStmt = $conn->prepare("SELECT name FROM accessories WHERE id = ?");
    $accStmt->bind_param("i", $accessory_id);
    $accStmt->execute();
    $accRes = $accStmt->get_result();
    $accRow = $accRes->fetch_assoc();
    $accStmt->close();

    if (!$accRow) {
        echo json_encode(['success' => false, 'message' => 'Accessory not found']);
        exit();
    }

    // Fetch assigned items
    $stmt = $conn->prepare("
        SELECT 
            i.id, 
            i.item_name, 
            i.serial_number, 
            i.category,
            ia.assigned_date as assigned_at
        FROM items i
        INNER JOIN item_accessories ia ON i.id = ia.item_id
        WHERE ia.accessory_id = ?
        ORDER BY i.item_name ASC
    ");
    $stmt->bind_param("i", $accessory_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => intval($row['id']),
            'item_name' => $row['item_name'],
            'serial_number' => $row['serial_number'] ?? 'N/A',
            'category' => $row['category'] ?? 'General',
            'assigned_at' => $row['assigned_at'] ? date('M d, Y', strtotime($row['assigned_at'])) : null
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'accessory_name' => $accRow['name'],
        'items' => $items,
        'count' => count($items)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
