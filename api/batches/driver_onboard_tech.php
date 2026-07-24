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

    // Verify batch exists
    $checkQuery = "SELECT id, transport_driver, tech_onboard, movement_type FROM stock_movements WHERE batch_number = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Batch not found.");
    }
    
    $batch = $result->fetch_assoc();
    
    if ($batch['tech_onboard'] == 1) {
        throw new Exception("Technician is already on board.");
    }

    $updateQuery = "UPDATE stock_movements SET tech_onboard = 1, tech_onboard_at = NOW() WHERE batch_number = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $batch_id);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update technician onboard status.");
    }
    
    echo json_encode(['success' => true, 'message' => 'Technician onboard status verified successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
