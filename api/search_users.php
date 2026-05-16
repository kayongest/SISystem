<?php
// api/search_users.php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getConnection();
$query = isset($_GET['query']) ? trim($_GET['query']) : (isset($_GET['q']) ? trim($_GET['q']) : '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $search_term = "%{$query}%";
    
    // Fuzzy search for roles (e.g., 'technicians' -> 'technician')
    $role_search = $search_term;
    if (strlen($query) > 3 && strtolower(substr($query, -1)) === 's') {
        $role_search = "%" . substr($query, 0, -1) . "%";
    }

    // Search users by username, email, full_name, or role
    $sql = "SELECT id, username, email, full_name, role, profile_image 
            FROM users 
            WHERE is_active = 1 
            AND (username LIKE ? OR email LIKE ? OR full_name LIKE ? OR role LIKE ? OR role LIKE ?)
            ORDER BY 
                CASE 
                    WHEN full_name LIKE ? THEN 1
                    WHEN username LIKE ? THEN 2
                    WHEN role LIKE ? THEN 3
                    ELSE 4
                END,
                full_name ASC
            LIMIT 10";
            
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("ssssssss", 
        $search_term, $search_term, $search_term, $search_term, $role_search,
        $search_term, $search_term, $role_search
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Build the correct image path relative to the profile page
        $image_path = !empty($row['profile_image']) && file_exists('../' . $row['profile_image']) 
            ? $row['profile_image'] 
            : null;
            
        $users[] = [
            'id' => $row['id'],
            'username' => htmlspecialchars($row['username']),
            'full_name' => htmlspecialchars($row['full_name'] ?? ''),
            'email' => htmlspecialchars($row['email']),
            'role' => htmlspecialchars($row['role']),
            'profile_image' => $image_path
        ];
    }
    
    echo json_encode($users);
} catch (Exception $e) {
    error_log("Search users error: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred during search']);
}
?>
