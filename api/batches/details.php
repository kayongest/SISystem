<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ability_db';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$batch_id = $_GET['batch_id'] ?? null;

if (!$batch_id) {
    echo json_encode(['success' => false, 'message' => 'Batch ID required']);
    exit();
}

// Get batch details
$sql = "SELECT 
            b.id,
            b.batch_number as batch_id,
            b.created_at as date,
            (SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = b.id) as item_count,
            (SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = b.id) as total_quantity,
            b.status,
            b.job_sheet,
            b.jobsheet_file,
            b.movement_type,
            b.source_type,
            b.source_id,
            b.destination_type,
            b.destination_id,
            b.destination_room,
            b.project_manager,
            NULL as event_id,
            b.approved_at,
            b.approval_notes,
            b.rejection_reason,
            b.transport_vehicle_type,
            b.transport_vehicle_number,
            b.transport_driver,
            NULL as transport_driver_phone,
            b.transport_date,
            NULL as transport_reference,
            b.notes as transport_notes,
            COALESCE(u.full_name, 'Unknown') as technician_name,
            COALESCE(u.full_name, 'Unknown') as technician,
            COALESCE(u.id, '') as technician_id_number,
            b.submitted_by as submitted_by_name,
            b.submitted_by as submitted_by,
            b.approved_by_name,
            b.source_name,
            b.destination_name,
            b.destination_name as destination,
            b.event_name,
            b.approval_status,
            b.stock_controller_id,
            b.stock_controller_name,
            b.driver_verified,
            b.tech_onboard
        FROM stock_movements b
        LEFT JOIN users u ON b.technician_id = u.id
        WHERE b.batch_number = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL prepare failed']);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $batch_id);
$stmt->execute();
$result = $stmt->get_result();
$batch = $result->fetch_assoc();

if (!$batch) {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
    $stmt->close();
    $conn->close();
    exit();
}

// Get batch items
$itemsSql = "SELECT 
                bi.id,
                bi.item_name,
                bi.serial_number,
                bi.quantity,
                bi.status,
                CASE 
                    WHEN sm.destination_room IS NOT NULL AND sm.destination_room != '' 
                    THEN CONCAT(sm.destination_name, ' (', sm.destination_room, ')')
                    ELSE COALESCE(sm.destination_name, bi.location)
                END as location
            FROM batch_items bi
            LEFT JOIN stock_movements sm ON bi.batch_id = sm.id
            WHERE bi.batch_id = ?";

$stmt2 = $conn->prepare($itemsSql);
if (!$stmt2) {
    echo json_encode(['success' => false, 'message' => 'Items SQL prepare failed']);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt2->bind_param("i", $batch['id']);
$stmt2->execute();
$itemsResult = $stmt2->get_result();

$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = [
        'name' => $item['item_name'] ?? 'N/A',
        'serial' => $item['serial_number'] ?? 'N/A',
        'quantity' => $item['quantity'] ?? 1,
        'status' => $item['status'] ?? 'available',
        'location' => $item['location'] ?? $batch['destination_name'] ?? 'N/A'
    ];
}

$batch['items'] = $items;

echo json_encode([
    'success' => true,
    'batch' => $batch
]);

$stmt->close();
$stmt2->close();
$conn->close();
?>