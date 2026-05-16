<?php
// api/users/get_technicians.php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

$conn = getConnection();

try {
    // Get query parameters
    $department = $_GET['department'] ?? null;
    $active_only = isset($_GET['active']) ? $_GET['active'] == '1' : true;

    // Build query
    $sql = "SELECT 
                id, 
                username, 
                COALESCE(full_name, username) as full_name,
                email,
                department,
                role,
                phone,
                hire_date,
                is_active,
                created_at
            FROM users 
            WHERE is_active = 1 
              AND role IN ('technician', 'tech', 'user')";

    if ($department) {
        $sql .= " AND department LIKE ?";
    }

    $sql .= " ORDER BY full_name ASC";

    $stmt = $conn->prepare($sql);

    if ($department) {
        $dept_param = "%$department%";
        $stmt->bind_param('s', $dept_param);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }

    echo json_encode([
        'success' => true,
        'technicians' => $technicians,
        'count' => count($technicians)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
