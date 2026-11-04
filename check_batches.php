<?php
require_once __DIR__ . '/includes/db_connect.php';
$conn = getConnection();
if (!$conn) {
    die("Connection failed");
}
$result = $conn->query("SELECT id, batch_number, status, movement_type, transport_driver, created_at FROM stock_movements");
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Batch: " . $row['batch_number'] . " | Status: " . $row['status'] . " | Type: " . $row['movement_type'] . " | Driver: " . $row['transport_driver'] . " | Created: " . $row['created_at'] . "\n";
}
$conn->close();
?>
