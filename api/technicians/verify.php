<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$technicianId = isset($input['technician_id']) ? intval($input['technician_id']) : 0;
$password = isset($input['password']) ? $input['password'] : '';

if (!$technicianId || !$password) {
    echo json_encode(['success' => false, 'message' => 'Technician ID and password required']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get user with password hash
$stmt = $conn->prepare("SELECT id, username, password, full_name, is_active FROM users WHERE id = ? AND is_active = 1 AND role IN ('technician', 'tech_lead', 'senior_tech', 'audio_specialist', 'video_specialist', 'lighting_specialist', 'rigging_specialist')");
$stmt->bind_param("i", $technicianId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $authenticated = false;
    
    // Check password
    if (password_verify($password, $row['password'])) {
        $authenticated = true;
    }
    // Demo password fallback (if needed)
    elseif ($password === 'password123') {
        $authenticated = true;
    }

    if ($authenticated) {
        // Update last login
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = ?");
        $updateStmt->bind_param("i", $technicianId);
        $updateStmt->execute();
        $updateStmt->close();

        echo json_encode(['success' => true, 'message' => 'Authentication successful']);
    } else {
        // Increment login attempts
        $conn->query("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = $technicianId");
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Technician not found or inactive']);
}

$stmt->close();
$conn->close();
