<?php
// api/technicians/get_all.php - SIMPLIFIED VERSION
session_start();
header('Content-Type: application/json');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database
$rootPath = dirname(dirname(dirname(__FILE__)));
require_once $rootPath . '/config/database.php';

try {
    $conn = getConnection();

    // Simple query to get all active users that could be technicians
    $sql = "SELECT 
                id, 
                username, 
                COALESCE(full_name, username) as full_name,
                email,
                department,
                COALESCE(role, 'user') as role,
                is_active
            FROM users 
            WHERE is_active = 1
            ORDER BY full_name ASC, username ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'email' => $row['email'] ?? '',
            'department' => $row['department'] ?? 'Not specified',
            'role' => $row['role'],
            'is_active' => (bool)$row['is_active']
        ];
    }

    // Get current user info for stock controller
    $current_user = null;
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        $user_sql = "SELECT id, username, full_name, role FROM users WHERE id = ?";
        $stmt = $conn->prepare($user_sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $current_user = $user_result->fetch_assoc();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Technicians loaded successfully',
        'technicians' => $technicians,
        'total' => count($technicians),
        'stock_controller' => $current_user,
        'debug' => [
            'session_user_id' => $_SESSION['user_id'] ?? 'not set',
            'session_username' => $_SESSION['username'] ?? 'not set'
        ]
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'technicians' => [],
        'total' => 0
    ], JSON_PRETTY_PRINT);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
