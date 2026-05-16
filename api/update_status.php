<?php
// api/update_status.php - Update item status
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'message' => ''];

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in', 401);
    }
    
    // Check for POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required', 405);
    }
    
    // Get parameters
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    if ($item_id <= 0) {
        throw new Exception('Invalid item ID');
    }
    
    $valid_statuses = ['available', 'in_use', 'maintenance', 'reserved', 'disposed', 'lost'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status');
    }
    
    // Define root directory
    $rootDir = realpath(__DIR__ . '/../..');
    
    // Include required files
    require_once $rootDir . '/includes/db_connect.php';
    require_once $rootDir . '/includes/functions.php';
    
    // Get database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Update status
    $sql = "UPDATE items SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $status, $item_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Status updated successfully';
    } else {
        throw new Exception('Update failed: ' . $stmt->error);
    }
    
    $stmt->close();
    $db->close();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    $response['code'] = $e->getCode();
    
    $httpCode = $e->getCode() && $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($httpCode);
}

echo json_encode($response);
exit();
?>