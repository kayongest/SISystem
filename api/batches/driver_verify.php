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

    // Verify batch exists and is transport movement
    $checkQuery = "SELECT id, transport_driver, driver_verified, movement_type FROM stock_movements WHERE batch_number = ?";
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
        throw new Exception("This batch movement type does not require driver load verification.");
    }
    
    if ($batch['driver_verified'] == 1) {
        throw new Exception("This batch has already been verified.");
    }

    $batchDbId = $batch['id'];

    $updateQuery = "UPDATE stock_movements SET driver_verified = 1, driver_verified_at = NOW(), updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $batchDbId);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update verification status.");
    }
    $updateStmt->close();

    // Set batch items to in_transit
    $biStmt = $conn->prepare("UPDATE batch_items SET status = 'in_transit', item_status = 'in_transit' WHERE batch_id = ?");
    if ($biStmt) {
        $biStmt->bind_param("i", $batchDbId);
        $biStmt->execute();
        $biStmt->close();
    }

    // Set equipment in items table to in_transit
    $uItems = $conn->prepare("UPDATE items SET status = 'in_transit', updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
    if ($uItems) {
        $uItems->bind_param("i", $batchDbId);
        $uItems->execute();
        $uItems->close();
    }

    // Log to activity_log
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $desc = "Batch {$batch_id} equipment verified and loaded by {$driver_name}";
    $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action, description, ip_address) VALUES (?, 'transport', 'verified_load', ?, ?)");
    if ($logStmt) {
        $logStmt->bind_param("iss", $_SESSION['user_id'], $desc, $ip);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Equipment load verified and accepted successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
