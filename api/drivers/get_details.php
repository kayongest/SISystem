<?php
// api/drivers/get_details.php - Fetch driver details and trip history
require_once '../../bootstrap.php';
require_once '../../includes/functions.php';
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$driverId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$driverId) {
    echo json_encode(['success' => false, 'error' => 'Invalid driver ID']);
    exit();
}

$conn = getConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Fetch driver info
$stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ?");
$stmt->bind_param("i", $driverId);
$stmt->execute();
$driverResult = $stmt->get_result();
$driver = $driverResult->fetch_assoc();

if (!$driver) {
    echo json_encode(['success' => false, 'error' => 'Driver not found']);
    exit();
}

// Fetch trips / movements associated with driver's full_name
$driverName = $driver['full_name'];
$trips = [];
$totalTrips = 0;
$completedTrips = 0;
$pendingTrips = 0;

$tripStmt = $conn->prepare("
    SELECT 
        sm.id,
        sm.batch_number,
        sm.event_name,
        sm.source_name,
        sm.destination_name,
        sm.movement_type,
        sm.status,
        sm.approval_status,
        sm.driver_verified,
        sm.created_at
    FROM stock_movements sm
    WHERE LOWER(sm.transport_driver) = LOWER(?)
    ORDER BY sm.created_at DESC
    LIMIT 20
");

if ($tripStmt) {
    $tripStmt->bind_param("s", $driverName);
    $tripStmt->execute();
    $res = $tripStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $trips[] = $row;
        $totalTrips++;
        if (strtolower($row['status']) === 'completed' || strtolower($row['status']) === 'approved') {
            $completedTrips++;
        } else {
            $pendingTrips++;
        }
    }
    $tripStmt->close();
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'driver' => $driver,
    'metrics' => [
        'total_trips' => $totalTrips,
        'completed_trips' => $completedTrips,
        'pending_trips' => $pendingTrips
    ],
    'trips' => $trips
]);
?>
