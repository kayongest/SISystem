<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

$conn = getConnection();

$query = "SELECT id, name, location FROM stock_locations WHERE is_active = 1 ORDER BY name";
$result = $conn ? $conn->query($query) : false;

$locations = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row['name'];
    }
}

echo json_encode([
    'success' => true,
    'locations' => $locations
]);
?>