<?php
header('Content-Type: application/json');

// Use the same path as your list.php
require_once '../config/database.php'; // Adjust path as needed

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Use the same connection method as list.php
    $db = getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Get unique brand values from items table
    $query = "SELECT DISTINCT brand as id, 
              CASE 
                  WHEN brand = 1 THEN 'Sony'
                  WHEN brand = 2 THEN 'Canon'
                  WHEN brand = 3 THEN 'Nikon'
                  WHEN brand = 4 THEN 'Panasonic'
                  WHEN brand = 5 THEN 'Blackmagic Design'
                  WHEN brand = 6 THEN 'ARRI'
                  WHEN brand = 7 THEN 'RED'
                  WHEN brand = 8 THEN 'Fujifilm'
                  WHEN brand = 9 THEN 'Leica'
                  WHEN brand = 10 THEN 'Zeiss'
                  ELSE CONCAT('Brand ', brand)
              END as name
              FROM items 
              WHERE brand IS NOT NULL AND brand != 0 
              ORDER BY brand";
    
    $result = $db->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $db->error);
    }
    
    $brands = [];
    while ($row = $result->fetch_assoc()) {
        $brands[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }
    
    echo json_encode($brands);
    
} catch (Exception $e) {
    error_log("Brands API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>