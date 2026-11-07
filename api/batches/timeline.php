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
    echo json_encode(['success' => false, 'message' => 'Please log in to view timeline', 'timeline' => []]);
    $conn->close();
    exit();
}

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$sql = "SELECT 
            b.id,
            b.batch_number as batch_id,
            b.created_at as date,
            b.status,
            b.approval_status,
            b.event_name,
            b.movement_type,
            (SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = b.id) as item_count,
            (SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = b.id) as total_quantity,
            COALESCE(NULLIF(b.destination_room, ''), b.destination_name, 'N/A') as location,
            COALESCE(t.full_name, 'Unknown') as technician
        FROM stock_movements b
        LEFT JOIN users t ON b.technician_id = t.id
        WHERE DATE(b.created_at) BETWEEN ? AND ?";

// Role-Based Filtering: Stock Controllers only see their assigned batches
$userRole = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = trim($_SESSION['username'] ?? '');
$userFullName = trim($_SESSION['full_name'] ?? '');
$isAdmin = in_array($userRole, ['admin', 'superadmin']);
$isStockController = ($userRole === 'stock_controller' || strtolower($userName) === 'mirene');

$params = [$date_from, $date_to];
$types = "ss";

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

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$timeline = [];
while ($row = $result->fetch_assoc()) {
    $timeline[] = $row;
}

echo json_encode([
    'success' => true,
    'timeline' => $timeline
]);

$stmt->close();
$conn->close();
?>
