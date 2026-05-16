<?php
// api/get_single_department.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => '',
    'department' => null
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    // Get department ID
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        throw new Exception('Department ID required');
    }

    // Get root directory
    $rootDir = realpath(dirname(__FILE__) . '/..');

    // Include database connection
    $dbFile = $rootDir . '/includes/db_connect.php';
    if (!file_exists($dbFile)) {
        throw new Exception('Database configuration not found');
    }

    require_once $dbFile;

    // Create database connection
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get single department
    $sql = "SELECT id, name FROM departments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Department not found');
    }

    $department = $result->fetch_assoc();
    
    $response['success'] = true;
    $response['department'] = $department;
    $response['message'] = 'Department loaded successfully';

    $db->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
exit();
?>