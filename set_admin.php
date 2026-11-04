<?php
require_once 'config/database.php';
$conn = getConnection();
$password = password_hash('admin123', PASSWORD_DEFAULT);

// Reset both 'admin' and 'kayonga' accounts
$stmt = $conn->prepare("UPDATE users SET password = ?, is_active = 1 WHERE username = 'admin' OR email = 'admin@example.com'");
$stmt->bind_param("s", $password);
$stmt->execute();
$admin_rows = $stmt->affected_rows;
$stmt->close();

$stmt2 = $conn->prepare("UPDATE users SET password = ?, role = 'admin', is_active = 1 WHERE username = 'kayonga' OR email = 'kayonga70@gmail.com'");
$stmt2->bind_param("s", $password);
$stmt2->execute();
$kayonga_rows = $stmt2->affected_rows;
$stmt2->close();

echo "<h2>Password Reset Complete</h2>";
echo "<p>Password for <strong>admin</strong> and <strong>kayonga70@gmail.com</strong> has been set to: <code>admin123</code></p>";
echo "<p><a href='login.php'>Go to Login</a></p>";
?>
