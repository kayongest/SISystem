<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "ability_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE `accessories` ADD `category` varchar(100) DEFAULT NULL AFTER `name`";

if ($conn->query($sql) === TRUE) {
    echo "Column category added successfully.";
} else {
    // Ignore if column already exists
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "Column category already exists.";
    } else {
        echo "Error: " . $conn->error;
    }
}
$conn->close();
?>
