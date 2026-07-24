<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "ability_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS `accessory_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table accessory_categories created successfully.\n";
    
    // Insert defaults
    $defaults = ['Video Cable', 'Audio Cable', 'Power Cable'];
    foreach ($defaults as $cat) {
        $stmt = $conn->prepare("INSERT IGNORE INTO accessory_categories (name) VALUES (?)");
        $stmt->bind_param("s", $cat);
        $stmt->execute();
    }
    echo "Defaults inserted.\n";
} else {
    echo "Error creating table: " . $conn->error;
}
$conn->close();
?>
