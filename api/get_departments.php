<?php
// api/get_departments.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => '',
    'departments' => []
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
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

    // Get departments
    $sql = "SELECT id, name FROM departments ORDER BY name";
    $result = $conn->query($sql);

    if ($result) {
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = [
                'id' => $row['id'],
                'name' => $row['name']
            ];
        }
        $response['success'] = true;
        $response['departments'] = $departments;
        $response['message'] = 'Departments loaded successfully';
    } else {
        throw new Exception('Failed to fetch departments: ' . $conn->error);
    }

    $db->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
exit();
?>