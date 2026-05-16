<?php
// api/users/delete.php - Delete/deactivate user
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
    // Get user ID
    $userId = (int)($_POST['user_id'] ?? 0);

    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }

    // Prevent deleting self
    if ($userId === $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit;
    }

    // Check if user exists and is active
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Soft delete (deactivate) user
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$userId]);

    if ($result) {
        // Log activity
        logActivity($pdo, $_SESSION['user_id'], 'user_deactivated', "Deactivated user: {$user['username']} (ID: $userId)");

        echo json_encode([
            'success' => true,
            'message' => 'User deactivated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate user']);
    }

} catch (Exception $e) {
    error_log("Error deactivating user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
