<?php
// get_current_permissions.php - Returns current permissions and role mappings as JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once 'bootstrap.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getConnection();

// Load all permissions
$permissions = [];
$perm_result = $conn->query("SELECT id, name, display_name, description FROM permissions ORDER BY display_name ASC");
if ($perm_result) {
    while ($row = $perm_result->fetch_assoc()) {
        $permissions[(int)$row['id']] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'display_name' => $row['display_name'],
            'description' => $row['description'] ?? '',
            'roles' => []
        ];
    }
}

// Load role permissions
$rp_result = $conn->query("SELECT role, permission_id FROM role_permissions");
if ($rp_result) {
    while ($row = $rp_result->fetch_assoc()) {
        $pid = (int)$row['permission_id'];
        if (isset($permissions[$pid])) {
            $permissions[$pid]['roles'][] = $row['role'];
        }
    }
}

echo json_encode(array_values($permissions));
exit();
