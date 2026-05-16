<?php
// api/scan/stats.php
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

try {
    $db = getDatabase();
    $conn = $db->getConnection();
    
    $stats = [];
    
    // Total scans
    $result = $conn->query("SELECT COUNT(*) as total FROM scan_logs");
    $stats['total_scans'] = $result->fetch_assoc()['total'];
    
    // Check-ins today
    $result = $conn->query("SELECT COUNT(*) as total FROM scan_logs WHERE scan_type = 'check_in' AND DATE(scan_timestamp) = CURDATE()");
    $stats['check_ins_today'] = $result->fetch_assoc()['total'];
    
    // Check-outs today
    $result = $conn->query("SELECT COUNT(*) as total FROM scan_logs WHERE scan_type = 'check_out' AND DATE(scan_timestamp) = CURDATE()");
    $stats['check_outs_today'] = $result->fetch_assoc()['total'];
    
    // Active users (users who scanned today)
    $result = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM scan_logs WHERE DATE(scan_timestamp) = CURDATE()");
    $stats['active_users'] = $result->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}