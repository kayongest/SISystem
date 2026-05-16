<?php
// api/batches/save.php
// Add error reporting at the top for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
session_start();

// Log that the file was accessed
error_log("save.php accessed at " . date('Y-m-d H:i:s'));

// FIXED: Changed from '../includes/' to '../../includes/'
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get database connection using your class
$db = getDatabase();
$conn = $db->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
error_log("Received input: " . print_r($input, true));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data: ' . json_last_error_msg()]);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Generate batch ID if not present
    $batch_id = $input['batch_id'] ?? 'BATCH-' . time() . '-' . uniqid();

    $technician = $input['technician'] ?? [];
    $job_details = $input['job_details'] ?? [];

    // Calculate totals
    $item_count = count($input['items'] ?? []);
    $total_quantity = 0;
    foreach ($input['items'] ?? [] as $item) {
        $total_quantity += $item['quantity'] ?? 1;
    }

    error_log("Inserting batch: $batch_id with $item_count items");

    // Insert batch header
    $sql = "INSERT INTO batches (
        batch_id, submitted_at, submitted_by, submitted_by_id,
        technician_id, technician_name, technician_username, technician_department,
        item_count, total_quantity, stock_location, event_name, job_sheet,
        project_manager, vehicle_number, driver_name, batch_action, batch_location,
        approval_notes, batch_notes, status
    ) VALUES (
        ?, NOW(), ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, 'pending'
    )";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $submitted_by = $input['submitted_by'] ?? $_SESSION['username'] ?? 'Unknown';
    $submitted_by_id = $input['submitted_by_id'] ?? $_SESSION['user_id'] ?? 0;
    $technician_id = $technician['id'] ?? null;
    $technician_name = $technician['full_name'] ?? $technician['username'] ?? null;
    $technician_username = $technician['username'] ?? null;
    $technician_dept = $technician['department'] ?? null;
    $stock_loc = $job_details['stock_location'] ?? null;
    $event = $job_details['event_name'] ?? null;
    $job = $job_details['job_sheet'] ?? null;
    $project_mgr = $job_details['project_manager'] ?? null;
    $vehicle = $job_details['vehicle_number'] ?? null;
    $driver = $job_details['driver_name'] ?? null;
    $batch_action = $input['batch_action'] ?? null;
    $batch_loc = $input['batch_location'] ?? null;
    $approval_notes = $input['approval_notes'] ?? null;
    $batch_notes = $input['batch_notes'] ?? null;

    $stmt->bind_param(
        'ssiisssiiisssssssss',
        $batch_id,
        $submitted_by,
        $submitted_by_id,
        $technician_id,
        $technician_name,
        $technician_username,
        $technician_dept,
        $item_count,
        $total_quantity,
        $stock_loc,
        $event,
        $job,
        $project_mgr,
        $vehicle,
        $driver,
        $batch_action,
        $batch_loc,
        $approval_notes,
        $batch_notes
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to execute batch insert: ' . $stmt->error);
    }

    // Insert batch items
    if (!empty($input['items'])) {
        $itemSql = "INSERT INTO batch_items (
            batch_id, item_id, item_name, serial_number,
            quantity, status, location, category
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?
        )";

        $itemStmt = $conn->prepare($itemSql);
        if (!$itemStmt) {
            throw new Exception('Failed to prepare item statement: ' . $conn->error);
        }

        $itemTypes = 'sississs';

        foreach ($input['items'] as $item) {
            $item_id = $item['id'] ?? $item['item_id'] ?? null;
            $item_name = $item['name'] ?? $item['item_name'] ?? 'Unknown';
            $serial = $item['serial'] ?? $item['serial_number'] ?? null;
            $quantity = $item['quantity'] ?? 1;
            $status = $item['status'] ?? 'available';
            $location = $item['location'] ?? $item['stock_location'] ?? null;
            $category = $item['category'] ?? null;

            $itemStmt->bind_param(
                $itemTypes,
                $batch_id,
                $item_id,
                $item_name,
                $serial,
                $quantity,
                $status,
                $location,
                $category
            );

            if (!$itemStmt->execute()) {
                throw new Exception('Failed to insert item: ' . $itemStmt->error);
            }
        }
    }

    // Commit transaction
    $conn->commit();

    error_log("Batch saved successfully: $batch_id");

    echo json_encode([
        'success' => true,
        'message' => 'Batch saved successfully',
        'batch' => [
            'batch_id' => $batch_id,
            'submitted_at' => date('Y-m-d H:i:s'),
            'item_count' => $item_count,
            'total_quantity' => $total_quantity
        ]
    ]);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log('Batch save error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
