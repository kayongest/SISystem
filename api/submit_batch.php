<?php
// api/submit_batch.php
session_start();

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_clean();
header('Content-Type: application/json');

function jsonResponse($success, $message, $extra = [])
{
    $response = array_merge(['success' => $success, 'message' => $message], $extra);
    echo json_encode($response);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Please log in to submit batches');
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    jsonResponse(false, 'No data received');
}

$data = json_decode($rawInput, true);
if (!$data) {
    jsonResponse(false, 'Invalid JSON data');
}

if (empty($data['items']) || !is_array($data['items'])) {
    jsonResponse(false, 'No items in batch');
}

if (empty($data['technician_id'])) {
    jsonResponse(false, 'Technician not authenticated');
}

$conn = new mysqli('localhost', 'root', '', 'ability_db');

if ($conn->connect_error) {
    jsonResponse(false, 'Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Generate unique batch number
$batchNumber = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

// Count the placeholders: There are 24 ? marks in the query
// Let me write them explicitly:
// 1.batch_number, 2.technician_id, 3.submitted_by, 
// 4.stock_controller_id, 5.stock_controller_name, 6.stock_location_name,
// 7.movement_type, 8.source_type, 9.source_id, 10.source_name,
// 11.destination_type, 12.destination_id, 13.destination_name, 14.destination_room,
// 15.event_name, 16.job_sheet, 17.project_manager, 18.notes,
// 19.transport_vehicle_type, 20.transport_vehicle_number, 21.transport_driver, 22.transport_date,
// 23.status, 24.created_at

$batchSql = "INSERT INTO stock_movements (
    batch_number, technician_id, submitted_by,
    stock_controller_id, stock_controller_name, stock_location_name,
    movement_type, source_type, source_id, source_name,
    destination_type, destination_id, destination_name, destination_room,
    event_name, job_sheet, project_manager, notes,
    transport_vehicle_type, transport_vehicle_number, transport_driver, transport_date,
    status, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

$stmt = $conn->prepare($batchSql);

if (!$stmt) {
    jsonResponse(false, 'Database prepare error: ' . $conn->error);
}

// Prepare all values
$submittedBy = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown';
$sourceType = $data['source']['type'] ?? null;
$sourceId = $data['source']['id'] ?? null;
$sourceName = $data['source']['name'] ?? null;
$destinationType = $data['destination']['type'] ?? null;
$destinationId = $data['destination']['id'] ?? null;
$destinationName = $data['destination']['name'] ?? null;
$destinationRoom = $data['destination']['room'] ?? null;
$eventName = $data['event_name'] ?? null;
$jobSheet = $data['job_sheet'] ?? null;
$projectManager = $data['project_manager'] ?? null;
$notes = $data['notes'] ?? null;
$transportVehicleType = $data['transport']['vehicle_type'] ?? null;
$transportVehicleNumber = $data['transport']['vehicle_number'] ?? null;
$transportDriver = $data['transport']['driver'] ?? null;
$transportDate = $data['transport']['transport_date'] ?? null;
$stockControllerId = isset($data['stock_controller_id']) && !empty($data['stock_controller_id']) ? (int)$data['stock_controller_id'] : null;
$stockControllerName = !empty($data['stock_controller_name']) ? $data['stock_controller_name'] : null;
$stockLocationName = !empty($data['stock_location']) ? $data['stock_location'] : null;

// Bind parameters - 22 parameters (status and created_at are hardcoded)
$stmt->bind_param(
    "sisissssssssssssssssss",  // 22 characters for 22 parameters
    $batchNumber,                    // 1 - string
    $data['technician_id'],         // 2 - int
    $submittedBy,                   // 3 - string
    $stockControllerId,             // 4 - int (can be null)
    $stockControllerName,           // 5 - string (can be null)
    $stockLocationName,             // 6 - string (can be null)
    $data['movement_type'],         // 7 - string
    $sourceType,                    // 8 - string (can be null)
    $sourceId,                      // 9 - string (can be null)
    $sourceName,                    // 10 - string (can be null)
    $destinationType,               // 11 - string (can be null)
    $destinationId,                 // 12 - string (can be null)
    $destinationName,               // 13 - string (can be null)
    $destinationRoom,               // 14 - string (can be null)
    $eventName,                     // 15 - string (can be null)
    $jobSheet,                      // 16 - string (can be null)
    $projectManager,                // 17 - string (can be null)
    $notes,                         // 18 - string (can be null)
    $transportVehicleType,          // 19 - string (can be null)
    $transportVehicleNumber,        // 20 - string (can be null)
    $transportDriver,               // 21 - string (can be null)
    $transportDate                  // 22 - string (can be null)
);

if (!$stmt->execute()) {
    error_log("Batch insert error: " . $stmt->error);
    jsonResponse(false, 'Failed to save batch: ' . $stmt->error);
}

$batchId = $conn->insert_id;
$stmt->close();

// Insert items
$itemSql = "INSERT INTO batch_items (
    batch_id, item_id, item_name, serial_number, quantity, status, location
) VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($itemSql);
if (!$stmt) {
    jsonResponse(false, 'Failed to prepare items insert: ' . $conn->error);
}

$itemsSaved = 0;
foreach ($data['items'] as $item) {
    $itemId = $item['id'] ?? null;
    $itemName = $item['name'];
    $serialNumber = $item['serial_number'] ?? null;
    $quantity = $item['quantity'] ?? 1;
    $status = $item['status'] ?? 'available';
    // Use batch destination as the location for batch items
    $destName = $data['destination']['name'] ?? null;
    $destRoom = $data['destination']['room'] ?? null;
    $location = $destName . (!empty($destRoom) ? " ($destRoom)" : "");
    if (empty($location)) {
        $location = $item['stock_location'] ?? 'Unknown';
    }

    $stmt->bind_param(
        "iississ",
        $batchId,
        $itemId,
        $itemName,
        $serialNumber,
        $quantity,
        $status,
        $location
    );

    if ($stmt->execute()) {
        $itemsSaved++;
    } else {
        error_log("Failed to insert item: " . $stmt->error);
    }
}
$stmt->close();
$conn->close();

jsonResponse(true, "Batch #{$batchNumber} submitted successfully with {$itemsSaved} items!", [
    'batch_id' => $batchId,
    'batch_number' => $batchNumber,
    'user_role' => $_SESSION['role'] ?? 'technician'  // Add this line
]);
