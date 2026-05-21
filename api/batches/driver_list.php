<?php
// api/batches/technician_list.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$username = $_SESSION['username'] ?? '';

try {
// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

    // Use session full_name to match transport_driver
    $driver_name = $_SESSION['full_name'] ?? $_SESSION['username'];

    // Verify user is actually a driver (optional but good for safety)
    $checkQuery = "SELECT id FROM users WHERE id = ? AND (role = 'driver' OR role = 'admin')";
    $stmt = $conn->prepare($checkQuery);
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No driver record found for your account.']);
            $conn->close();
            exit();
        }
        $stmt->close();
    }

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT 
            sm.id,
            sm.batch_number as batch_id,
            sm.created_at as date,
            sm.status,
            sm.approval_status,
            sm.event_name,
            sm.job_sheet,
            sm.source_name,
            sm.destination_name,
            sm.destination_room,
            sm.movement_type,
            sm.project_manager,
            sm.submitted_by,
            sm.stock_controller_name,
            sm.stock_controller_id,
            (SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = sm.id) as item_count,
            (SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = sm.id) as total_quantity,
            COALESCE(t.full_name, 'Unknown') as technician,
            sm.driver_verified
        FROM stock_movements sm
        LEFT JOIN users t ON sm.technician_id = t.id
        WHERE sm.transport_driver = ?
        AND sm.movement_type IN ('transport', 'stock_to_venue_transport', 'stock_to_stock')
        AND DATE(sm.created_at) BETWEEN ? AND ?";

$params = [$driver_name, $date_from, $date_to];
$types = "sss";

if (!empty($status)) {
    // Check both status and approval_status for compatibility
    $sql .= " AND (sm.status = ? OR sm.approval_status = ?)";
    $params[] = $status;
    $params[] = $status;
    $types .= "ss";
}

if (!empty($search)) {
    $sql .= " AND (sm.batch_number LIKE ? OR sm.event_name LIKE ? OR sm.job_sheet LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$sql .= " ORDER BY sm.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
while ($row = $result->fetch_assoc()) {
    $batches[] = [
        'batch_id' => $row['batch_id'],
        'date' => $row['date'],
        'event_name' => $row['event_name'],
        'job_sheet' => $row['job_sheet'],
        'item_count' => (int)($row['item_count'] ?? 0),
        'total_quantity' => (int)($row['total_quantity'] ?? 0),
        'source_name' => $row['source_name'],
        'destination' => $row['destination_room'] ? $row['destination_name'] . ' - ' . $row['destination_room'] : $row['destination_name'],
        'status' => $row['status'] ?? 'pending',
        'stock_controller_name' => $row['stock_controller_name'],
        'submitted_by' => $row['submitted_by'],
        'technician' => $row['technician'],
        'driver_verified' => (int)($row['driver_verified'] ?? 0),
        'movement_type' => $row['movement_type']
    ];
}
$stmt->close();

// Get statistics
$statsSql = "SELECT 
                COUNT(DISTINCT sm.id) as total_batches,
                SUM((SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = sm.id)) as total_items,
                SUM(CASE WHEN sm.status = 'pending' OR sm.approval_status = 'pending' THEN 1 ELSE 0 END) as pending_batches,
                SUM(CASE WHEN sm.status = 'approved' OR sm.approval_status = 'approved' THEN 1 ELSE 0 END) as approved_batches,
                SUM(CASE WHEN sm.status = 'completed' OR sm.approval_status = 'completed' THEN 1 ELSE 0 END) as completed_batches,
                SUM(CASE WHEN sm.movement_type IN ('stock_to_stock', 'stock_to_venue_transport') THEN 1 ELSE 0 END) as gate_passes
            FROM stock_movements sm
            WHERE sm.transport_driver = ?
            AND sm.movement_type IN ('transport', 'stock_to_venue_transport', 'stock_to_stock')
            AND DATE(sm.created_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($statsSql);
if ($stmt) {
    $stmt->bind_param("sss", $driver_name, $date_from, $date_to);
    $stmt->execute();
    $statsResult = $stmt->get_result();
    $stats = $statsResult->fetch_assoc();
    $stmt->close();
} else {
    $stats = ['total_batches' => 0, 'total_items' => 0, 'pending_batches' => 0, 'approved_batches' => 0, 'completed_batches' => 0, 'gate_passes' => 0];
}

    echo json_encode([
        'success' => true,
        'batches' => $batches,
        'stats' => [
            'total_batches' => (int)($stats['total_batches'] ?? 0),
            'total_items' => (int)($stats['total_items'] ?? 0),
            'pending_batches' => (int)($stats['pending_batches'] ?? 0),
            'approved_batches' => (int)($stats['approved_batches'] ?? 0),
            'completed_batches' => (int)($stats['completed_batches'] ?? 0),
            'gate_passes' => (int)($stats['gate_passes'] ?? 0)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn) $conn->close();
}
?>