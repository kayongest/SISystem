<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = getConnection();

    // Fetch active brands
    $query = "SELECT id, code, name FROM brands WHERE is_active = 1 ORDER BY name";
    $result = $db->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $db->error);
    }

    $brands = [];
    while ($row = $result->fetch_assoc()) {
        $brands[] = $row;
    }

    echo json_encode([
        'success' => true,
        'brands' => $brands
    ]);
} catch (Exception $e) {
    error_log("Get brands error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch brands'
    ]);
}
