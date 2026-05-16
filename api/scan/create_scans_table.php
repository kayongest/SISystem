<?php
require_once 'includes/db_connect.php';

echo "<h3>Creating Scans Table</h3>";

try {
    // Check if scans table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'scans'");
    if ($stmt->fetch()) {
        echo "✅ Scans table already exists<br>";
    } else {
        // Create scans table
        $sql = "
        CREATE TABLE scans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            scan_type ENUM('check_in', 'check_out', 'maintenance', 'inventory') NOT NULL,
            scan_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            transport_user VARCHAR(255) DEFAULT NULL,
            from_location VARCHAR(255) DEFAULT NULL,
            to_location VARCHAR(255) DEFAULT NULL,
            destination_address TEXT DEFAULT NULL,
            user_contact VARCHAR(50) DEFAULT NULL,
            user_department VARCHAR(100) DEFAULT NULL,
            user_id_number VARCHAR(50) DEFAULT NULL,
            vehicle_plate VARCHAR(50) DEFAULT NULL,
            vehicle_type VARCHAR(50) DEFAULT NULL,
            vehicle_description VARCHAR(255) DEFAULT NULL,
            transport_notes TEXT DEFAULT NULL,
            expected_return DATE DEFAULT NULL,
            priority ENUM('normal', 'urgent', 'high') DEFAULT 'normal',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_item_id (item_id),
            INDEX idx_scan_type (scan_type),
            INDEX idx_scan_timestamp (scan_timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        echo "✅ Scans table created successfully<br>";
    }
    
    // Check for existing data
    $count = $pdo->query("SELECT COUNT(*) FROM scans")->fetchColumn();
    echo "📊 Total scans in database: " . $count . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>