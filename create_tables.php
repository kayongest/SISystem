<?php
// create_tables.php
session_start();

$host = "localhost";
$username = "root";
$password = "";
$database = "ability_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql1 = "
CREATE TABLE IF NOT EXISTS `accessories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `total_quantity` int(11) NOT NULL DEFAULT 1,
  `available_quantity` int(11) NOT NULL DEFAULT 1,
  `minimum_stock` int(11) NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql2 = "
CREATE TABLE IF NOT EXISTS `item_accessories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `accessory_id` int(11) NOT NULL,
  `assigned_quantity` int(11) NOT NULL DEFAULT 1,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`accessory_id`) REFERENCES `accessories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$success = true;

if ($conn->query($sql1) === TRUE) {
    echo "Table accessories created successfully.<br>";
} else {
    echo "Error creating table accessories: " . $conn->error . "<br>";
    $success = false;
}

if ($conn->query($sql2) === TRUE) {
    echo "Table item_accessories created successfully.<br>";
} else {
    echo "Error creating table item_accessories: " . $conn->error . "<br>";
    $success = false;
}

$conn->close();

if ($success) {
    echo "<br><b>All tables created successfully!</b>";
    echo "<br><a href='accessories.php'>Return to Accessories</a>";
}
?>
