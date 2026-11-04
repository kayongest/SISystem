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
    $stmt->close();
    
    if ($batch['tech_onboard'] == 1) {
        throw new Exception("Technician is already on board.");
    }

    $batchDbId = $batch['id'];

    $updateQuery = "UPDATE stock_movements SET tech_onboard = 1, tech_onboard_at = NOW(), updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $batchDbId);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update technician onboard status.");
    }
    $updateStmt->close();

    // Log to activity_log
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $desc = "Batch {$batch_id} technician onboard confirmed by {$driver_name}";
    $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action, description, ip_address) VALUES (?, 'transport', 'tech_onboard', ?, ?)");
    if ($logStmt) {
        $logStmt->bind_param("iss", $_SESSION['user_id'], $desc, $ip);
        $logStmt->execute();
        $logStmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Technician onboard status verified successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
