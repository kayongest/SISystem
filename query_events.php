<?php
$conn = new mysqli("localhost", "root", "", "ability_db");
$res = $conn->query("SHOW CREATE TABLE events");
$row = $res->fetch_assoc();
echo $row["Create Table"];
?>
