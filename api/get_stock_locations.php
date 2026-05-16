<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/database_fix.php';

$db = new DatabaseFix();
$conn = $db->getConnection();

$query = "SELECT id, name, location FROM stock_locations WHERE is_active = 1 ORDER BY name";
$result = $conn->query($query);

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