<?php
// Include the config file that has PDO connection
require_once 'config/database.php';

// Check if PDO connection exists
if (!isset($pdo) || $pdo === null) {
    die("❌ Database connection failed. Please check your database configuration.");
}

$username = 'kraul';
$password = 'Admin#70.'; // The plain password
$full_name = 'Kayonga Raul';
$email = 'kayonga.raul@ab.com';
$phone = '+250 788700870';
$department = 'IT Ops';
$role = 'admin'; // admin, technician, user, stockcontroller

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert into database
$sql = "INSERT INTO technicians (username, password, full_name, email, phone, department, role, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

$stmt = $pdo->prepare($sql);
$result = $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $department, $role]);

if ($result) {
    echo "✅ Technician '$full_name' added successfully!<br>";
    echo "Username: $username<br>";
    echo "Password: $password<br>";
    echo "Role: $role<br>";

    // Also add sample technicians
    $sample_techs = [
        ['john_tech', 'John Smith', 'john@example.com', '+250 111 222 333', 'Technical', 'technician'],
        ['jane_tech', 'Jane Doe', 'jane@example.com', '+250 444 555 666', 'Operations', 'technician'],
        ['stock_mike', 'Mike Wilson', 'mike@example.com', '+250 777 888 999', 'Warehouse', 'stockcontroller'],
        ['user_tom', 'Tom Brown', 'tom@example.com', '+250 000 111 222', 'Sales', 'user']
    ];

    foreach ($sample_techs as $tech) {
        $hashed = password_hash('Password123!', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tech[0], $hashed, $tech[1], $tech[2], $tech[3], $tech[4], $tech[5]]);
        echo "✅ Added: {$tech[1]}<br>";
    }

    echo "<br>📊 Total 5 technicians added to database!";
} else {
    echo "❌ Error adding technician.";
}
