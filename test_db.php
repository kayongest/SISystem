<?php
<<<<<<< HEAD
require 'config/database.php';
$conn = getConnection();
$stmt = $conn->prepare('SELECT id, username, email, full_name, password, role, department, is_active FROM users WHERE username = ? OR email = ?');
if (!$stmt) {
    echo "Error preparing statement: " . $conn->error;
} else {
    echo "Success";
}
=======
require 'includes/db_connect.php';
$stmt = $pdo->query("SELECT id, item_name, status FROM items LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
