<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db_connect.php';
$conn = getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to view stats', 'stats' => []]);
    $conn->close();
    exit();
}

$raw_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$raw_to = $_GET['date_to'] ?? date('Y-m-d');
$date_from = $conn->real_escape_string($raw_from);
$date_to = $conn->real_escape_string($raw_to);

$stats = [];

// Role-Based Filtering: Stock Controllers only see their assigned batches
$userRole = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = trim($_SESSION['username'] ?? '');
$userFullName = trim($_SESSION['full_name'] ?? '');
$isAdmin = in_array($userRole, ['admin', 'superadmin']);
$isStockController = ($userRole === 'stock_controller' || strtolower($userName) === 'mirene');

$roleFilter = "";
if (!$isAdmin && $isStockController) {
    $scParts = [];
    if ($userId > 0) {
        $scParts[] = "stock_controller_id = " . $userId;
    }
    if (!empty($userName)) {
        $escapedUser = $conn->real_escape_string($userName);
        $scParts[] = "LOWER(stock_controller_name) = LOWER('" . $escapedUser . "')";
        $scParts[] = "LOWER(stock_controller_name) LIKE LOWER('" . $escapedUser . " (%')";
    }
    if (!empty($userFullName) && strcasecmp($userFullName, $userName) !== 0) {
        $escapedFull = $conn->real_escape_string($userFullName);
        $scParts[] = "LOWER(stock_controller_name) = LOWER('" . $escapedFull . "')";
        $scParts[] = "LOWER(stock_controller_name) LIKE LOWER('" . $escapedFull . " (%')";
    }

    if (!empty($scParts)) {
        $roleFilter = " AND (" . implode(" OR ", $scParts) . ")";
    } else {
        $roleFilter = " AND 1=0";
    }
}

// Total batches in date range
$result = $conn->query("SELECT COUNT(*) as total FROM stock_movements WHERE (DATE(created_at) BETWEEN '$date_from' AND '$date_to') $roleFilter");
$stats['total_batches'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total items in date range
$result = $conn->query("SELECT COALESCE(SUM((SELECT SUM(quantity) FROM batch_items bi WHERE bi.batch_id = sm.id)), 0) as total FROM stock_movements sm WHERE (DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to') $roleFilter");
$row = $result ? $result->fetch_assoc() : null;
$stats['total_items'] = $row['total'] ?? 0;

// Unique technicians
$result = $conn->query("SELECT COUNT(DISTINCT technician_id) as total FROM stock_movements WHERE technician_id IS NOT NULL $roleFilter");
$stats['unique_technicians'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Status counts
$result = $conn->query("SELECT COUNT(*) as total FROM stock_movements WHERE (status = 'pending' OR approval_status = 'pending') $roleFilter");
$stats['pending_batches'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM stock_movements WHERE (status = 'approved' OR approval_status = 'approved') $roleFilter");
$stats['approved_batches'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM stock_movements WHERE (status = 'rejected' OR approval_status = 'rejected') $roleFilter");
$stats['rejected_batches'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM stock_movements WHERE (status = 'completed' OR approval_status = 'completed') $roleFilter");
$stats['completed_batches'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Gate passes count
$result = $conn->query("SELECT COUNT(*) as total FROM stock_movements WHERE movement_type IN ('stock_to_stock', 'stock_to_venue_transport') AND (DATE(created_at) BETWEEN '$date_from' AND '$date_to') $roleFilter");
$stats['gate_passes'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

$stats['batch_trend'] = 0;
$stats['items_trend'] = 0;

echo json_encode([
    'success' => true,
    'stats' => $stats
]);

$conn->close();
?>
