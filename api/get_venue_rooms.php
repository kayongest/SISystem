<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/database_fix.php';

$db = new DatabaseFix();
$conn = $db->getConnection();

$query = "
    SELECT v.name as venue_name, vr.room_name 
    FROM venue_rooms vr 
    JOIN venues v ON vr.venue_id = v.id 
    WHERE vr.is_active = 1 
    ORDER BY v.name, vr.room_name
";
$result = $conn->query($query);

$rooms = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = [
            'venue_name' => $row['venue_name'],
            'room_name' => $row['room_name']
        ];
    }
}

echo json_encode([
    'success' => true,
    'rooms' => $rooms
]);
?>