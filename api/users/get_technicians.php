<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    // First, check if users table exists and has technician role
    $checkTableQuery = "SHOW TABLES LIKE 'users'";
    $tableResult = $conn->query($checkTableQuery);
    
    if ($tableResult->num_rows == 0) {
        // Return mock data for testing if table doesn't exist
        echo json_encode([
            'success' => true,
            'technicians' => [
                ['id' => 2, 'username' => 'john.tech', 'full_name' => 'John Doe', 'department' => 'Audio'],
                ['id' => 3, 'username' => 'jane.tech', 'full_name' => 'Jane Smith', 'department' => 'Lighting'],
                ['id' => 4, 'username' => 'mike.tech', 'full_name' => 'Mike Johnson', 'department' => 'Rigging']
            ],
            'count' => 3
        ]);
        exit();
    }
    
    // Get all users with technician role - adjust role names based on your database
    $query = "SELECT id, username, full_name, email, department, role 
              FROM users 
              WHERE role = 'technician' OR role = 'Technician' OR role = 'tech' 
              ORDER BY full_name ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception($conn->error);
    }
    
    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'] ?: $row['username'],
            'email' => $row['email'] ?? '',
            'department' => $row['department'] ?? 'Technician',
            'role' => $row['role'] ?? 'technician'
        ];
    }
    
    // If no technicians found, return mock data
    if (empty($technicians)) {
        $technicians = [
            ['id' => 2, 'username' => 'john.tech', 'full_name' => 'John Doe', 'department' => 'Audio'],
            ['id' => 3, 'username' => 'jane.tech', 'full_name' => 'Jane Smith', 'department' => 'Lighting'],
            ['id' => 4, 'username' => 'mike.tech', 'full_name' => 'Mike Johnson', 'department' => 'Rigging']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'technicians' => $technicians,
        'count' => count($technicians)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>