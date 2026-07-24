<?php
$host = 'localhost';
$dbname = 'ability_db';
$username = 'root';
$password = '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

$stmt = $pdo->query('SELECT stock_location, COUNT(*) as c FROM items GROUP BY stock_location');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query('SELECT current_location, COUNT(*) as c FROM items GROUP BY current_location');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
