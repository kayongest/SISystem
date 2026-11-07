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
    echo json_encode(['success' => false, 'message' => 'Please log in to view batches', 'batches' => [], 'total' => 0]);
    $conn->close();
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
            b.stock_location_name,
            b.movement_type,
            b.transport_driver,
            b.driver_verified,
            b.tech_onboard
        FROM stock_movements b
        LEFT JOIN users t ON b.technician_id = t.id
        WHERE 1=1";

$params = [];
$types = "";

// Role-Based Filtering: Stock Controllers only see their assigned batches
$userRole = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = trim($_SESSION['username'] ?? '');
$userFullName = trim($_SESSION['full_name'] ?? '');
$isAdmin = in_array($userRole, ['admin', 'superadmin']);
$isStockController = ($userRole === 'stock_controller' || strtolower($userName) === 'mirene');

if (!$isAdmin && $isStockController) {
    $scConditions = [];
    if ($userId > 0) {
        $scConditions[] = "b.stock_controller_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    if (!empty($userName)) {
        $scConditions[] = "LOWER(b.stock_controller_name) = LOWER(?)";
        $params[] = $userName;
        $types .= "s";
        $scConditions[] = "LOWER(b.stock_controller_name) LIKE LOWER(CONCAT(?, ' (%'))";
        $params[] = $userName;
        $types .= "s";
    }
    if (!empty($userFullName) && strcasecmp($userFullName, $userName) !== 0) {
        $scConditions[] = "LOWER(b.stock_controller_name) = LOWER(?)";
        $params[] = $userFullName;
        $types .= "s";
        $scConditions[] = "LOWER(b.stock_controller_name) LIKE LOWER(CONCAT(?, ' (%'))";
        $params[] = $userFullName;
        $types .= "s";
    }

    if (!empty($scConditions)) {
        $sql .= " AND (" . implode(" OR ", $scConditions) . ")";
    } else {
        $sql .= " AND 1=0";
    }
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
