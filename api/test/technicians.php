<?php
// api/test/technicians.php
session_start();
header('Content-Type: application/json');

// Simulate session for testing if not set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;
    $_SESSION['username'] = 'kayongest';
    $_SESSION['role'] = 'admin';
}

// FIXED PATH: Use absolute path
require_once __DIR__ . '/../../config/database.php';

$conn = getConnection();

try {
    // Test 1: Get all active users
    $sql = "SELECT id, username, full_name, email, department, role 
            FROM users 
            WHERE is_active = 1 
            ORDER BY full_name, username";

    $result = $conn->query($sql);
    $all_users = [];

    while ($row = $result->fetch_assoc()) {
        $all_users[] = $row;
    }

    // Test 2: Get technicians (users with technician-like roles)
    $sql_tech = "SELECT id, username, full_name, email, department, role 
                 FROM users 
                 WHERE is_active = 1 
                   AND role IN ('technician', 'tech', 'user')
                 ORDER BY full_name";

    $result_tech = $conn->query($sql_tech);
    $technicians = [];

    while ($row = $result_tech->fetch_assoc()) {
        $technicians[] = $row;
    }

    // Test 3: Get current user
    $current_user = null;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_sql = "SELECT id, username, full_name, role FROM users WHERE id = ?";
        $stmt = $conn->prepare($user_sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $current_user = $user_result->fetch_assoc();
    }

    echo json_encode([
        'success' => true,
        'test_name' => 'Technician Data Test',
        'database_connected' => true,
        'session_user_id' => $_SESSION['user_id'] ?? 'not set',
        'total_active_users' => count($all_users),
        'total_technicians' => count($technicians),
        'all_users_sample' => array_slice($all_users, 0, 5), // First 5 users
        'technicians_sample' => array_slice($technicians, 0, 5), // First 5 technicians
        'current_user' => $current_user,
        'recommendation' => count($technicians) > 0
            ? 'Use technicians array for dropdown'
            : 'No technicians found. Check user roles in database.'
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'database_error' => $conn->error ?? 'No error'
    ], JSON_PRETTY_PRINT);
} finally {
    $conn->close();
}
