<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

try {
    $db = getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $event_title = isset($_GET['event_title']) ? $_GET['event_title'] : null;

    if (!$event_title) {
        throw new Exception('Event title is required');
    }

    // Query to fetch all batch items associated with this event via stock_movements
    $query = "SELECT 
                bi.id,
                bi.item_name,
                bi.quantity,
                bi.serial_number,
                bi.status as item_status,
                sm.batch_number,
                sm.status as batch_status,
                sm.driver_verified,
                sm.created_at,
                sm.id as movement_id
              FROM batch_items bi
              JOIN stock_movements sm ON bi.batch_id = sm.id
              WHERE LOWER(sm.event_name) = LOWER(?) OR LOWER(sm.job_sheet) = LOWER(?)
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
