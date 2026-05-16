<?php
// api/get_categories.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => '',
    'categories' => []
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

    // Check if categories table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($tableCheck->num_rows === 0) {
        // Return empty array if table doesn't exist
        $response['success'] = true;
        $response['categories'] = [];
        $response['message'] = 'Categories table not found';
        echo json_encode($response);
        exit();
    }

    // Get categories
    $sql = "SELECT id, name FROM categories ORDER BY name";
    $result = $conn->query($sql);

    if ($result) {
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = [
                'id' => (int)$row['id'],
                'name' => $row['name']
            ];
        }
        $response['success'] = true;
        $response['categories'] = $categories;
        $response['message'] = 'Categories loaded successfully';
    } else {
        throw new Exception('Failed to fetch categories: ' . $conn->error);
    }

    $db->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
exit();
?>