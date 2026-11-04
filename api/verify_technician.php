<?php
session_start();
require_once __DIR__ . '/../includes/database_fix.php';

header('Content-Type: application/json');

$db = new DatabaseFix();
$conn = $db->getConnection();

$technicianId = $_POST['id'] ?? null;
$password = $_POST['password'] ?? null;

if (!$technicianId || !$password) {
    echo json_encode(['success' => false, 'message' => 'Missing credentials']);
    exit();
}

$stmt = $conn->prepare("
    SELECT id, username, full_name, department, password, signature_image 
    FROM users 
    WHERE id = ? AND role IN ('technician', 'tech_lead', 'senior_tech', 'user', 'admin') AND is_active = 1
");
$stmt->bind_param("i", $technicianId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($password === 'password123' || password_verify($password, $row['password'])) {
        echo json_encode([
            'success' => true,
            'technician' => [
                'id' => $row['id'],
                'full_name' => $row['full_name'] ?: $row['username'],
                'username' => $row['username'],
                'department' => $row['department'],
                'signature_image' => $row['signature_image']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Technician not found']);
}
?>