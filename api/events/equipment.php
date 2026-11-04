<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $event_title = isset($_GET['event_title']) ? trim($_GET['event_title']) : '';

    if ($event_title === '') {
        echo json_encode([
            'success' => true,
            'event_title' => '',
            'total_unique_records' => 0,
            'total_quantity' => 0,
            'data' => []
        ]);
        exit;
    }

    // Query to fetch all batch items associated with this event via stock_movements
    $query = "SELECT 
                bi.id,
                bi.item_id,
                bi.item_name,
                bi.quantity,
                bi.serial_number,
                COALESCE(NULLIF(bi.item_status, ''), bi.status, 'pending') as item_status,
                sm.batch_number,
                sm.status as batch_status,
                sm.driver_verified,
                sm.created_at,
                sm.id as movement_id
              FROM batch_items bi
              JOIN stock_movements sm ON bi.batch_id = sm.id
              WHERE LOWER(TRIM(sm.event_name)) = LOWER(TRIM(?)) OR LOWER(TRIM(sm.job_sheet)) = LOWER(TRIM(?))
              ORDER BY sm.created_at DESC, bi.item_name ASC";

    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }

    $stmt->bind_param("ss", $event_title, $event_title);
    $stmt->execute();
    $result = $stmt->get_result();

    $equipment = [];
    $total_items = 0;
    
    while ($row = $result->fetch_assoc()) {
        $equipment[] = $row;
        $total_items += (int)$row['quantity'];
    }
    
    $stmt->close();

    echo json_encode([
        'success' => true,
        'event_title' => $event_title,
        'total_unique_records' => count($equipment),
        'total_quantity' => $total_items,
        'data' => $equipment
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'total_unique_records' => 0,
        'total_quantity' => 0,
        'data' => []
    ]);
}
?>
