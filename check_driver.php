<?php
$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    die("Connection failed");
}
echo "--- ALL MOVEMENTS ---\n";
$result = $conn->query("SELECT id, batch_number, status, movement_type, transport_driver, created_at FROM stock_movements");
while ($row = $result->fetch_assoc()) {
    echo "Batch: " . $row['batch_number'] . " | Status: " . $row['status'] . " | Type: " . $row['movement_type'] . " | Driver: '" . $row['transport_driver'] . "' | Created: " . $row['created_at'] . "\n";
}
$conn->close();
?>
