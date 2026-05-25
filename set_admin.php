<?php
require_once 'config/database.php';
$conn = getConnection();
$password = password_hash('admin123', PASSWORD_DEFAULT);
if ($conn->query("UPDATE users SET password = '$password' WHERE username = 'admin'")) {
    echo "Admin password updated successfully to 'admin123'!";
} else {
    echo "Error updating password: " . $conn->error;
}
?>
