<?php
// api/technician_disable.php
require_once '../bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check permissions - only admin, manager, tech_lead can disable technicians
$allowedRoles = ['admin', 'manager', 'tech_lead'];
$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if (!in_array($userRole, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Include database connection
require_once '../includes/database_fix.php';

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Get technician ID
    $techId = (int)($_POST['tech_id'] ?? 0);

    if (empty($techId)) {
        echo json_encode(['success' => false, 'error' => 'Technician ID required']);
        exit();
    }

    // Prevent disabling yourself
    if ($techId == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'You cannot disable your own account']);
        exit();
    }

    // Get user details for logging
    $infoStmt = $conn->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $infoStmt->bind_param("i", $techId);
    $infoStmt->execute();
    $result = $infoStmt->get_result();
    $tech = $result->fetch_assoc();
    $infoStmt->close();

    if (!$tech) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    // Soft delete by setting is_active to 0
    $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $techId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Log activity
            $logStmt = $conn->prepare("
                INSERT INTO activity_log (user_id, action, description, ip_address) 
                VALUES (?, 'technician_disabled', ?, ?)
            ");
            $description = "Disabled technician: {$tech['full_name']} (Username: {$tech['username']})";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $logStmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
            $logStmt->execute();
            $logStmt->close();

            echo json_encode([
                'success' => true, 
                'message' => 'Technician disabled successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Technician not found or already disabled']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to disable technician: ' . $conn->error]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Technician disable error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>