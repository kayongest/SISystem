<?php
// api/users/update.php - Update user
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
    $userId = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $department = trim($_POST['department'] ?? '');

    // Validate required fields
    if (!$userId || empty($username) || empty($email) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $existingUser = $stmt->fetch();

    if (!$existingUser) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Check if username already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    // Check if email already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
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

    // Prepare update query
    $updateFields = ["username = ?", "email = ?", "role = ?", "department = ?", "updated_at = NOW()"];
    $params = [$username, $email, $role, $department];

    // Add password update if provided
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateFields[] = "password = ?";
        $params[] = $hashedPassword;
    }

    $params[] = $userId; // Add user ID at the end

    $stmt = $pdo->prepare("
        UPDATE users
        SET " . implode(", ", $updateFields) . "
        WHERE id = ?
    ");

    $result = $stmt->execute($params);

    if ($result) {
        // Log activity
        $changes = [];
        if ($existingUser['username'] !== $username) {
            $changes[] = "username: {$existingUser['username']} → $username";
        }
        if ($existingUser['email'] !== $email) {
            $changes[] = "email: {$existingUser['email']} → $email";
        }
        if (!empty($password)) {
            $changes[] = "password changed";
        }
        $changes[] = "role: {$existingUser['role']} → $role";

        logActivity($pdo, $_SESSION['user_id'], 'user_updated', "Updated user: $username (ID: $userId) - " . implode(", ", $changes));

        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }

} catch (Exception $e) {
    error_log("Error updating user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
