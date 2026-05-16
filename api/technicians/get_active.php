<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $conn = getConnection();

    $sql = "SELECT id, username, full_name, email, phone, department 
            FROM users 
            WHERE is_active = 1 AND (role = 'technician' OR role = 'tech_lead' OR role = 'senior_tech')
            ORDER BY full_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }

    echo json_encode([
        'success' => true,
        'technicians' => $technicians
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching technicians: ' . $e->getMessage()
    ]);
}
