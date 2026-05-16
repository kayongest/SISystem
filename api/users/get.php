<?php
// api/users/get.php - Get user data
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $conn = getConnection();

    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit();
    }

    $userId = (int)$_GET['id'];

    // Check permissions - users can only view their own data, admins/managers can view all
    $currentUserRole = $_SESSION['role'] ?? 'user';
    $currentUserId = $_SESSION['user_id'] ?? 0;

    if ($currentUserRole !== 'admin' && $currentUserRole !== 'manager' && $userId !== $currentUserId) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, username, email, role, department, is_active, created_at, updated_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
