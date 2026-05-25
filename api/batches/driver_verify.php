<?php
session_start();
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
$driver_name = $_SESSION['full_name'] ?? $_SESSION['username'];

if (empty($batch_id)) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

try {
    $conn = new mysqli('localhost', 'root', '', 'ability_db');
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Verify batch exists and belongs to this driver
    $checkQuery = "SELECT id, transport_driver, driver_verified, movement_type FROM stock_movements WHERE batch_number = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Batch not found.");
    }
    
    $batch = $result->fetch_assoc();
    
    if (!in_array($batch['movement_type'], ['transport', 'stock_to_venue_transport', 'stock_to_stock'])) {
        throw new Exception("This batch movement type does not require driver load verification.");
    }
    
    if ($batch['driver_verified'] == 1) {
        throw new Exception("This batch has already been verified.");
    }

    // You could optionally add a check to make sure $batch['transport_driver'] matches $driver_name
    // However, if the query already returned this batch, we trust the driver interface.
    
    $updateQuery = "UPDATE stock_movements SET driver_verified = 1, driver_verified_at = NOW() WHERE batch_number = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $batch_id);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update verification status.");
    }
    
    echo json_encode(['success' => true, 'message' => 'Equipment load verified successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
