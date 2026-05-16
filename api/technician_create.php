<?php
// api/technician_create.php
require_once '../bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check permissions - only admin, manager, tech_lead can create technicians
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

    // Get and validate input
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'technician';
    $department = trim($_POST['department'] ?? '');

    // Validate required fields
    if (empty($username) || empty($full_name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        exit();
    }

    // Check if username already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit();
    }
    $checkStmt->close();

    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        exit();
    }
    $checkStmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, full_name, email, phone, department, role, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->bind_param("sssssss", $username, $hashed_password, $full_name, $email, $phone, $department, $role);
    
    if ($stmt->execute()) {
        $techId = $stmt->insert_id;
        
        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address) 
            VALUES (?, 'technician_created', ?, ?)
        ");
        $description = "Created new technician: $full_name (Username: $username)";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $logStmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode([
            'success' => true, 
            'message' => 'Technician created successfully',
            'technician_id' => $techId
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create technician: ' . $conn->error]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Technician creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>