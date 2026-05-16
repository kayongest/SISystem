<?php
// api/scan/get_logs.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$scan_type = isset($_GET['scan_type']) ? $_GET['scan_type'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

try {
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // Build query
    $sql = "SELECT 
                sl.*,
                i.item_name,
                i.serial_number,
                i.image,
                i.status as item_status,
                u.username,
                u.full_name
            FROM scan_logs sl
            LEFT JOIN items i ON sl.item_id = i.id
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Apply filters
    if (!empty($date_from)) {
        $sql .= " AND DATE(sl.scan_timestamp) >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(sl.scan_timestamp) <= ?";
        $params[] = $date_to;
        $types .= 's';
    }
    
    if (!empty($scan_type)) {
        $sql .= " AND sl.scan_type = ?";
        $params[] = $scan_type;
        $types .= 's';
    }
    
    $sql .= " ORDER BY sl.scan_timestamp DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $scans = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $scans,
        'count' => count($scans),
        'filters' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'scan_type' => $scan_type
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}