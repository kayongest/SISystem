<?php
require_once __DIR__ . '/../../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$batch_id = $_POST['batch_id'] ?? '';
$driver_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Driver');

if (empty($batch_id)) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

try {
    require_once __DIR__ . '/../../includes/db_connect.php';
    $conn = getConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Verify batch exists, belongs to this driver, and is driver_verified
    $checkQuery = "SELECT id, transport_driver, driver_verified, status, approval_status, movement_type, destination_name, destination_room FROM stock_movements WHERE batch_number = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Batch not found.");
    }

    $batch = $result->fetch_assoc();
    $stmt->close();

    if (!in_array($batch['movement_type'], ['transport', 'stock_to_venue_transport', 'stock_to_stock'])) {
        throw new Exception("This batch movement type does not require driver delivery completion.");
    }

    $currentStatus = $batch['status'] ?? $batch['approval_status'];

    if ($currentStatus === 'completed') {
        throw new Exception("This delivery has already been completed.");
    }

    if ($batch['driver_verified'] != 1) {
        throw new Exception("You must verify that the equipment is loaded before completing delivery.");
    }

    $batchDbId = $batch['id'];

    // Update status to completed
    $updateQuery = "UPDATE stock_movements SET status = 'completed', approval_status = 'completed', driver_completed_at = NOW(), updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $batchDbId);

    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update batch status to completed.");
    }
    $updateStmt->close();

    // Update batch_items status
    $uBi = $conn->prepare("UPDATE batch_items SET status = 'completed', item_status = 'completed' WHERE batch_id = ?");
    if ($uBi) {
        $uBi->bind_param("i", $batchDbId);
        $uBi->execute();
        $uBi->close();
    }

    // Update items table locations and statuses
    $destWithRoom = $batch['destination_name'] . (!empty($batch['destination_room']) ? ' - ' . $batch['destination_room'] : '');
    if ($batch['movement_type'] === 'stock_to_stock') {
        $uItems = $conn->prepare("UPDATE items SET status = 'available', current_location = ?, stock_location = ?, updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
        if ($uItems) {
            $uItems->bind_param("ssi", $batch['destination_name'], $batch['destination_name'], $batchDbId);
            $uItems->execute();
            $uItems->close();
        }
    } else {
        // Venue transport -> in_use at venue/room
        $uItems = $conn->prepare("UPDATE items SET status = 'in_use', current_location = ?, updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
        if ($uItems) {
            $uItems->bind_param("si", $destWithRoom, $batchDbId);
            $uItems->execute();
            $uItems->close();
        }
    }

    // Log to activity_log
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $desc = "Batch {$batch_id} delivery completed (100%) by {$driver_name}";
    $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action, description, ip_address) VALUES (?, 'transport', 'completed_delivery', ?, ?)");
    if ($logStmt) {
        $logStmt->bind_param("iss", $_SESSION['user_id'], $desc, $ip);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Delivery completed successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
