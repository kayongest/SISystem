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

// ✅ FIX: Use !empty() so empty strings from JS are treated as null, not used as filter values
$date_from     = !empty($_GET['date_from'])     ? $_GET['date_from']     : null;
$date_to       = !empty($_GET['date_to'])       ? $_GET['date_to']       : null;
$status        = !empty($_GET['status'])        ? $_GET['status']        : null;
$technician_id = !empty($_GET['technician_id']) ? $_GET['technician_id'] : null;
$search        = !empty($_GET['search'])        ? $_GET['search']        : null;

// Build the query to get batches
$sql = "SELECT 
            b.id,
            b.batch_number as batch_id,
            b.created_at as date,
            b.status,
            b.approval_status,
            b.job_sheet,
            b.jobsheet_file,
            b.event_name,
            b.source_name,
            b.destination_name,
            b.destination_room,
            (SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = b.id) as item_count,
            (SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = b.id) as total_quantity,
            COALESCE(NULLIF(b.destination_room, ''), b.destination_name, 'N/A') as location,
            COALESCE(b.destination_room, b.destination_name, 'N/A') as destination,
            COALESCE(t.full_name, 'Unknown') as technician,
            b.submitted_by,
            b.project_manager,
            b.stock_controller_id,
            b.stock_controller_name,
            b.movement_type,
            b.transport_driver,
            b.driver_verified
        FROM stock_movements b
        LEFT JOIN users t ON b.technician_id = t.id
        WHERE 1=1";

$params = [];
$types = "";

// Role-Based Filtering: Stock Controllers only see their assigned batches
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if ($userRole === 'stock_controller') {
    $sql .= " AND (b.stock_controller_id = ? OR b.movement_type IN ('transport', 'stock_to_venue_transport', 'stock_to_stock'))";
    $params[] = $userId;
    $types .= "i";
}

if ($date_from) {
    $sql .= " AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to) {
    $sql .= " AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}
if ($status) {
    $sql .= " AND b.status = ?";
    $params[] = $status;
    $types .= "s";
}
if ($technician_id) {
    $sql .= " AND b.technician_id = ?";
    $params[] = $technician_id;
    $types .= "i";
}
if ($search) {
    $sql .= " AND (b.batch_number LIKE ? OR t.full_name LIKE ? OR b.job_sheet LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$sql .= " ORDER BY b.created_at DESC";

$batches = [];

if (empty($params)) {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $batches[] = $row;
        }
    }
} else {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $batches[] = $row;
        }
        $stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'batches' => $batches,
    'total'   => count($batches)
]);

$conn->close();
?>
