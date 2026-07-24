<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "ability_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Update id = 0 to id = 3 (or max + 1)
$sql1 = "UPDATE accessories SET id = (SELECT max_id + 1 FROM (SELECT MAX(id) as max_id FROM accessories) as temp) WHERE id = 0";
if ($conn->query($sql1) === TRUE) {
    echo "Updated id 0 to a valid id.\n";
} else {
    echo "Error updating id: " . $conn->error . "\n";
}

// 2. Modify column to be AUTO_INCREMENT
$sql2 = "ALTER TABLE accessories MODIFY id int(11) NOT NULL AUTO_INCREMENT";
if ($conn->query($sql2) === TRUE) {
    echo "Set id to AUTO_INCREMENT.\n";
} else {
    echo "Error setting AUTO_INCREMENT: " . $conn->error . "\n";
}

$conn->close();
?>
