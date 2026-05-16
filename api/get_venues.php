<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/database_fix.php';

$db = new DatabaseFix();
$conn = $db->getConnection();

$query = "SELECT id, name, location FROM venues WHERE is_active = 1 ORDER BY name";
$result = $conn->query($query);

$venues = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $venues[] = $row['name'];
    }
}

echo json_encode([
    'success' => true,
    'venues' => $venues
]);
?>