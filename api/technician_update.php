<?php
// api/technician_update.php
require_once '../bootstrap.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request
error_log("========== TECHNICIAN UPDATE REQUEST ==========");
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Check authentication
if (!isLoggedIn()) {
    error_log("ERROR: User not logged in");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check permissions - only admin, manager, tech_lead can update technicians
$allowedRoles = ['admin', 'manager', 'tech_lead'];
$userRole = strtolower(trim($_SESSION['role'] ?? ''));
error_log("User role: " . $userRole);
error_log("Allowed roles: " . implode(', ', $allowedRoles));

if (!in_array($userRole, $allowedRoles)) {
    error_log("ERROR: Permission denied for role: " . $userRole);
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Include database connection
require_once '../includes/database_fix.php';

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    if (!$conn) {
        error_log("ERROR: Database connection failed");
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }

    error_log("Database connection successful");

    // Get and validate input
    $techId = (int)($_POST['tech_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $reset_login = isset($_POST['reset_login']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    error_log("Received data - techId: $techId, username: $username, full_name: $full_name, email: $email, role: $role");

    // Validate required fields
    if (empty($techId)) {
        error_log("ERROR: Missing technician ID");
        echo json_encode(['success' => false, 'error' => 'Technician ID is required']);
        exit();
    }

    if (empty($username)) {
        error_log("ERROR: Missing username");
        echo json_encode(['success' => false, 'error' => 'Username is required']);
        exit();
    }

    if (empty($full_name)) {
        error_log("ERROR: Missing full name");
        echo json_encode(['success' => false, 'error' => 'Full name is required']);
        exit();
    }

    if (empty($email)) {
        error_log("ERROR: Missing email");
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        exit();
    }

    if (empty($role)) {
        error_log("ERROR: Missing role");
        echo json_encode(['success' => false, 'error' => 'Role is required']);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("ERROR: Invalid email format: $email");
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        exit();
    }

    // Check if user exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $checkStmt->bind_param("i", $techId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows === 0) {
        error_log("ERROR: User not found with ID: $techId");
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    $checkStmt->close();

    // Check if username already exists for another user
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $checkStmt->bind_param("si", $username, $techId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        error_log("ERROR: Username already exists: $username");
        echo json_encode(['success' => false, 'error' => 'Username already exists for another user']);
        exit();
    }
    $checkStmt->close();

    // Check if email already exists for another user
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkStmt->bind_param("si", $email, $techId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        error_log("ERROR: Email already exists: $email");
        echo json_encode(['success' => false, 'error' => 'Email already exists for another user']);
        exit();
    }
    $checkStmt->close();

    // Build update query based on whether password is provided
    if (!empty($password)) {
        error_log("Updating user with new password");
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if (!$hashed_password) {
            error_log("ERROR: Password hashing failed");
            echo json_encode(['success' => false, 'error' => 'Password hashing failed']);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, password = ?, full_name = ?, email = ?, phone = ?, 
                department = ?, role = ?, is_active = ?
            WHERE id = ?
        ");
        if (!$stmt) {
            error_log("ERROR: Prepare failed - " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("sssssssii", $username, $hashed_password, $full_name, $email, $phone, $department, $role, $is_active, $techId);
    } else {
        error_log("Updating user without password change");
        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, full_name = ?, email = ?, phone = ?, 
                department = ?, role = ?, is_active = ?
            WHERE id = ?
        ");
        if (!$stmt) {
            error_log("ERROR: Prepare failed - " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("ssssssii", $username, $full_name, $email, $phone, $department, $role, $is_active, $techId);
    }

    error_log("Executing update query...");
    if ($stmt->execute()) {
        error_log("Update executed successfully. Affected rows: " . $stmt->affected_rows);

        // If reset_login is checked, update the login_force_reset flag
        if ($reset_login) {
            error_log("Setting force_password_reset flag");
            // Check if the column exists first
            $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'force_password_reset'");
            if ($colCheck->num_rows > 0) {
                $resetStmt = $conn->prepare("UPDATE users SET force_password_reset = 1 WHERE id = ?");
                $resetStmt->bind_param("i", $techId);
                $resetStmt->execute();
                $resetStmt->close();
                error_log("force_password_reset flag set");
            } else {
                error_log("force_password_reset column doesn't exist, skipping");
            }
        }

        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address) 
            VALUES (?, 'technician_updated', ?, ?)
        ");
        if ($logStmt) {
            $description = "Updated technician: $full_name (ID: $techId)";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $logStmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
            $logStmt->execute();
            $logStmt->close();
            error_log("Activity logged");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Technician updated successfully'
        ]);
    } else {
        error_log("ERROR: Update failed - " . $stmt->error);
        echo json_encode(['success' => false, 'error' => 'Failed to update technician: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("EXCEPTION in technician_update: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
