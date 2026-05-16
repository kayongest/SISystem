<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ability_db';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit();
}

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$stats = [];

// Role-Based Filtering
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$roleFilter = "";
if ($userRole === 'stock_controller') {
    $roleFilter = " AND stock_controller_id = " . intval($userId);
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

$stats['batch_trend'] = 0;
$stats['items_trend'] = 0;

echo json_encode([
    'success' => true,
    'stats' => $stats
]);

$conn->close();
?>