<?php
// api/get_accessories.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => '',
    'accessories' => []
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

    // Check if accessories table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accessories'");
    if ($tableCheck->num_rows === 0) {
        // Return empty array if table doesn't exist
        $response['success'] = true;
        $response['accessories'] = [];
        $response['message'] = 'Accessories table not found';
        echo json_encode($response);
        exit();
    }

    // Get accessories
    $sql = "SELECT id, name, description, total_quantity, available_quantity 
            FROM accessories 
            WHERE is_active = 1 OR is_active IS NULL
            ORDER BY name";
    $result = $conn->query($sql);

    if ($result) {
        $accessories = [];
        while ($row = $result->fetch_assoc()) {
            $accessories[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'total_quantity' => (int)($row['total_quantity'] ?? 0),
                'available_quantity' => (int)($row['available_quantity'] ?? 0)
            ];
        }
        $response['success'] = true;
        $response['accessories'] = $accessories;
        $response['message'] = 'Accessories loaded successfully';
    } else {
        throw new Exception('Failed to fetch accessories: ' . $conn->error);
    }

    $db->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
exit();
?>