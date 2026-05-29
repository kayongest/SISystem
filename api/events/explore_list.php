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

    // We want to fetch all events and their aggregated data from stock_movements
    $query = "
        SELECT 
            e.id as event_id,
            e.title,
<<<<<<< HEAD
            CASE WHEN COUNT(sm.id) > 0 THEN 'batch' ELSE e.source END as source,
=======
            e.source,
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
            COALESCE(e.date, MIN(sm.created_at)) as date,
            COALESCE(e.location, MAX(sm.destination_name)) as location,
            COALESCE(e.project_manager, MAX(sm.project_manager)) as project_manager,
            MAX(sm.submitted_by) as technician,
            MAX(sm.movement_type) as movement_type,
            MAX(sm.transport_driver) as driver,
            COUNT(sm.id) as batch_count,
            e.event_image,
            e.description
        FROM events e
        LEFT JOIN stock_movements sm ON LOWER(e.title) = LOWER(sm.event_name)
        GROUP BY e.id, e.title, e.source, e.date, e.location, e.project_manager, e.event_image, e.description
        ORDER BY date DESC
    ";

    $result = $db->query($query);
    if (!$result) {
        throw new Exception('Query failed: ' . $db->error);
    }

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }

    echo json_encode($events);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
