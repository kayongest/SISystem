<?php
require_once 'includes/database_fix.php';

$db = new DatabaseFix();
$conn = $db->getConnection();

$sqls = [
    // Create item_checkouts table
    "CREATE TABLE IF NOT EXISTS `item_checkouts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `item_id` int(11) NOT NULL,
        `technician_id` int(11) NOT NULL,
        `checkout_code` varchar(50) NOT NULL,
        `checkout_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expected_return_date` datetime DEFAULT NULL,
        `actual_return_date` datetime DEFAULT NULL,
        `purpose` text,
        `destination_location` varchar(255) DEFAULT NULL,
        `status` enum('checked_out','returned','overdue','lost') DEFAULT 'checked_out',
        `approved_by` int(11) DEFAULT NULL,
        `approved_at` datetime DEFAULT NULL,
        `notes` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `checkout_code` (`checkout_code`),
        KEY `item_id` (`item_id`),
        KEY `technician_id` (`technician_id`),
        KEY `status` (`status`),
        CONSTRAINT `item_checkouts_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
        CONSTRAINT `item_checkouts_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // Create scan_history table
    "CREATE TABLE IF NOT EXISTS `scan_history` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `item_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `technician_id` int(11) DEFAULT NULL,
        `scan_type` enum('check_in','check_out','inventory','maintenance') NOT NULL,
        `checkout_id` int(11) DEFAULT NULL,
        `previous_status` varchar(50) DEFAULT NULL,
        `new_status` varchar(50) DEFAULT NULL,
        `previous_location` varchar(255) DEFAULT NULL,
        `new_location` varchar(255) DEFAULT NULL,
        `notes` text,
        `scanned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ip_address` varchar(45) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `item_id` (`item_id`),
        KEY `user_id` (`user_id`),
        KEY `technician_id` (`technician_id`),
        KEY `checkout_id` (`checkout_id`),
        KEY `scan_type` (`scan_type`),
        KEY `scanned_at` (`scanned_at`),
        CONSTRAINT `scan_history_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
        CONSTRAINT `scan_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `scan_history_ibfk_3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
        CONSTRAINT `scan_history_ibfk_4` FOREIGN KEY (`checkout_id`) REFERENCES `item_checkouts` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

foreach ($sqls as $sql) {
    if ($conn->query($sql)) {
        echo "✓ Table created/updated successfully<br>";
    } else {
        echo "✗ Error: " . $conn->error . "<br>";
    }
}

echo "<br>Database update complete!";
?>