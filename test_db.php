<?php
require 'config/database.php';
$conn = getConnection();
$stmt = $conn->prepare('SELECT id, username, email, full_name, password, role, department, is_active FROM users WHERE username = ? OR email = ?');
if (!$stmt) {
    echo "Error preparing statement: " . $conn->error;
} else {
    echo "Success";
}
