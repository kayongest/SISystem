<?php
// api/users/create.php - Create new user
header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $department = trim($_POST['department'] ?? '');

    // Validate required fields
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    // Validate role
    $validRoles = ['admin', 'manager', 'user', 'stock_manager', 'stock_controller', 'tech_lead', 'technician', 'driver'];
    if (!in_array($role, $validRoles)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, department, created_at, updated_at, is_active)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)
    ");

    $result = $stmt->execute([$username, $email, $hashedPassword, $role, $department]);

    if ($result) {
        $userId = $pdo->lastInsertId();

        // Log activity
        logActivity($pdo, $_SESSION['user_id'], 'user_created', "Created user: $username (ID: $userId)");

        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }

} catch (Exception $e) {
    error_log("Error creating user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
