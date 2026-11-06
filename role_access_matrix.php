<?php
// role_access_matrix.php - Role-Based Access Control Management
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off error display for AJAX requests
ini_set('log_errors', 1); // Keep logging errors

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax) {
    ob_start(); // Start output buffering for clean JSON responses
}

require_once 'bootstrap.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Check if user is admin (only admins can access this page)
if (!isAdmin()) {
    if ($is_ajax) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    $_SESSION['toast_message'] = 'You do not have permission to access role management';
    $_SESSION['toast_type'] = 'error';
    header('Location: dashboard_full.php');
    exit();
}

// Get database connection
$conn = getConnection();

/**
 * Returns the full catalog of system permissions.
 */
function getSystemPermissionsCatalog()
{
    return [
        ['name' => 'view_dashboard', 'display_name' => 'View Dashboard', 'description' => 'Access the main system dashboard and overview metrics'],
        ['name' => 'view_events', 'display_name' => 'View Events', 'description' => 'View scheduled events, productions, and calendar'],
        ['name' => 'manage_events', 'display_name' => 'Manage Events', 'description' => 'Create, edit, and manage events and event assignments'],
        ['name' => 'view_equipment', 'display_name' => 'View Equipment', 'description' => 'Browse and view equipment inventory and item details'],
        ['name' => 'add_equipment', 'display_name' => 'Add Equipment', 'description' => 'Add new equipment items to inventory'],
        ['name' => 'edit_equipment', 'display_name' => 'Edit Equipment', 'description' => 'Modify existing equipment details, condition, and status'],
        ['name' => 'delete_equipment', 'display_name' => 'Delete Equipment', 'description' => 'Remove or delete equipment items from inventory'],
        ['name' => 'view_items', 'display_name' => 'View Stock Items', 'description' => 'View stock items and catalog listings'],
        ['name' => 'manage_items', 'display_name' => 'Manage Stock Items', 'description' => 'Create, update, and manage stock items and quantities'],
        ['name' => 'import_export', 'display_name' => 'Import & Export Data', 'description' => 'Import and export equipment and inventory data via CSV/Excel'],
        ['name' => 'scan_single', 'display_name' => 'Single Item Scan', 'description' => 'Scan individual equipment QR codes or barcodes'],
        ['name' => 'scan_bulk', 'display_name' => 'Bulk / Batch Scan', 'description' => 'Perform bulk scanning for check-in and check-out batches'],
        ['name' => 'view_scan_history', 'display_name' => 'View Scan History', 'description' => 'View historical logs of all equipment scans'],
        ['name' => 'view_reports', 'display_name' => 'View Reports', 'description' => 'Access and generate inventory, movement, and system reports'],
        ['name' => 'manage_technicians', 'display_name' => 'Manage Technicians', 'description' => 'Add, edit, and manage technician accounts and assignments'],
        ['name' => 'manage_stock_locations', 'display_name' => 'Manage Stock Locations', 'description' => 'Configure and manage warehouse and stock locations'],
        ['name' => 'view_batch_history', 'display_name' => 'View Batch History', 'description' => 'View batch movement history, gate passes, and transfer logs'],
        ['name' => 'technician_batch_history', 'display_name' => 'Technician Batch History', 'description' => 'View technician-specific batch scan and movement history'],
        ['name' => 'view_accessories', 'display_name' => 'View Accessories', 'description' => 'View and manage equipment accessories and stock alerts'],
        ['name' => 'manage_users', 'display_name' => 'Manage Users', 'description' => 'Create, edit, activate, or suspend system user accounts'],
        ['name' => 'view_users', 'display_name' => 'View Users', 'description' => 'View user directory, profiles, and role assignments'],
        ['name' => 'manage_id_badges', 'display_name' => 'Manage ID Badges & Attendance', 'description' => 'Generate staff ID badges, configure Stock/Venue clearance, and scan attendance'],
        ['name' => 'manage_permissions', 'display_name' => 'Manage Permissions', 'description' => 'Configure the role access matrix and system permissions'],
        ['name' => 'manage_settings', 'display_name' => 'Manage Settings', 'description' => 'Configure system settings, categories, and departments']
    ];
}

/**
 * Returns default permissions per role.
 */
function getDefaultRolePermissionsMap()
{
    return [
        'admin' => 'all',
        'manager' => [
            'view_dashboard',
            'view_events',
            'manage_events',
            'view_equipment',
            'add_equipment',
            'edit_equipment',
            'view_items',
            'manage_items',
            'import_export',
            'view_scan_history',
            'view_reports',
            'manage_technicians',
            'view_batch_history',
            'view_accessories',
            'view_users',
            'manage_id_badges'
        ],
        'stock_manager' => [
            'view_dashboard',
            'view_events',
            'view_equipment',
            'add_equipment',
            'edit_equipment',
            'view_items',
            'manage_items',
            'import_export',
            'scan_single',
            'scan_bulk',
            'view_scan_history',
            'manage_stock_locations',
            'view_batch_history',
            'view_accessories',
            'view_reports'
        ],
        'stock_controller' => [
            'view_dashboard',
            'view_events',
            'view_equipment',
            'view_items',
            'manage_items',
            'scan_single',
            'scan_bulk',
            'view_scan_history',
            'view_batch_history',
            'view_accessories',
            'view_reports'
        ],
        'tech_lead' => [
            'view_dashboard',
            'view_events',
            'view_equipment',
            'view_items',
            'scan_single',
            'scan_bulk',
            'view_scan_history',
            'manage_technicians',
            'view_batch_history',
            'technician_batch_history',
            'view_accessories'
        ],
        'technician' => [
            'view_dashboard',
            'view_events',
            'view_equipment',
            'scan_single',
            'scan_bulk',
            'view_scan_history',
            'technician_batch_history',
            'view_accessories'
        ],
        'user' => [
            'view_dashboard',
            'view_events',
            'view_equipment',
            'view_items',
            'view_accessories'
        ],
        'driver' => [
            'view_dashboard',
            'view_events',
            'view_equipment',
            'view_batch_history'
        ]
    ];
}

/**
 * Ensures permissions and role_permissions tables exist and are seeded.
 */
function ensurePermissionsInitialized($conn, $forceResetRoles = false)
{
    // 1. Create permissions table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS `permissions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `display_name` VARCHAR(150) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_perm_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // 2. Create role_permissions table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS `role_permissions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `role` VARCHAR(50) NOT NULL,
            `permission_id` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_role_perm` (`role`, `permission_id`),
            KEY `idx_rp_role` (`role`),
            KEY `idx_rp_perm_id` (`permission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // 3. Ensure all system permissions exist in permissions table
    $existing_perms = [];
    $res = $conn->query("SELECT id, name FROM permissions");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing_perms[$row['name']] = (int)$row['id'];
        }
    }

    $catalog = getSystemPermissionsCatalog();
    $insert_perm_stmt = $conn->prepare("INSERT INTO permissions (name, display_name, description) VALUES (?, ?, ?)");
    if ($insert_perm_stmt) {
        foreach ($catalog as $perm) {
            if (!isset($existing_perms[$perm['name']])) {
                $p_name = $perm['name'];
                $p_disp = $perm['display_name'];
                $p_desc = $perm['description'];
                $insert_perm_stmt->bind_param("sss", $p_name, $p_disp, $p_desc);
                if ($insert_perm_stmt->execute()) {
                    $existing_perms[$p_name] = (int)$insert_perm_stmt->insert_id;
                }
            }
        }
        $insert_perm_stmt->close();
    }

    // 4. Check if role_permissions needs seeding or reset
    $rp_count = 0;
    $count_res = $conn->query("SELECT COUNT(*) AS cnt FROM role_permissions");
    if ($count_res && ($count_row = $count_res->fetch_assoc())) {
        $rp_count = (int)$count_row['cnt'];
    }

    $inserted_role_perms = 0;
    if ($forceResetRoles || $rp_count === 0) {
        if ($forceResetRoles) {
            $conn->query("DELETE FROM role_permissions");
        }

        // Refresh permission map
        $perm_names = [];
        $res2 = $conn->query("SELECT id, name FROM permissions");
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $perm_names[$row['name']] = (int)$row['id'];
            }
        }

        $default_map = getDefaultRolePermissionsMap();
        $insert_rp_stmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role, permission_id, created_at) VALUES (?, ?, NOW())");

        if ($insert_rp_stmt) {
            // Admin gets all permissions
            $admin_role = 'admin';
            foreach ($perm_names as $perm_name => $perm_id) {
                $insert_rp_stmt->bind_param("si", $admin_role, $perm_id);
                $insert_rp_stmt->execute();
                if ($insert_rp_stmt->affected_rows > 0) {
                    $inserted_role_perms++;
                }
            }

            // Other roles get their default permissions
            foreach ($default_map as $role_key => $perms) {
                if ($role_key === 'admin' || !is_array($perms)) {
                    continue;
                }
                foreach ($perms as $perm_name) {
                    if (isset($perm_names[$perm_name])) {
                        $perm_id = $perm_names[$perm_name];
                        $insert_rp_stmt->bind_param("si", $role_key, $perm_id);
                        $insert_rp_stmt->execute();
                        if ($insert_rp_stmt->affected_rows > 0) {
                            $inserted_role_perms++;
                        }
                    }
                }
            }
            $insert_rp_stmt->close();
        }
    }

    return $inserted_role_perms;
}

// Ensure tables and default permissions are initialized
ensurePermissionsInitialized($conn, false);

// Handle AJAX GET request to reload permissions
if (isset($_GET['action']) && $_GET['action'] === 'get_current_permissions') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $perms_out = [];
    $p_res = $conn->query("SELECT id, name, display_name, description FROM permissions ORDER BY display_name ASC");
    if ($p_res) {
        while ($row = $p_res->fetch_assoc()) {
            $pid = (int)$row['id'];
            $perms_out[$pid] = [
                'id' => $pid,
                'name' => $row['name'],
                'display_name' => $row['display_name'],
                'description' => $row['description'] ?? '',
                'roles' => []
            ];
        }
    }

    $rp_res = $conn->query("SELECT role, permission_id FROM role_permissions");
    if ($rp_res) {
        while ($row = $rp_res->fetch_assoc()) {
            $pid = (int)$row['permission_id'];
            if (isset($perms_out[$pid])) {
                $perms_out[$pid]['roles'][] = $row['role'];
            }
        }
    }

    echo json_encode(array_values($perms_out));
    exit();
}

$pageTitle = "Role Access Matrix - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'User Management' => 'users.php',
    'Role Access Matrix' => ''
];

// Get all permissions from database
$permissions = [];
$perm_result = $conn->query("SELECT * FROM permissions ORDER BY display_name ASC");
if ($perm_result) {
    while ($row = $perm_result->fetch_assoc()) {
        $permissions[$row['id']] = $row;
    }
}

// Handle form submission to save permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    if ($is_ajax) {
        error_reporting(0);
        ini_set('display_errors', 0);
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
    }

    $conn->begin_transaction();

    try {
        $permissions_saved = 0;
        $permissions_removed = 0;

        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $perm_id => $role_permissions) {
                $perm_id = filter_var($perm_id, FILTER_VALIDATE_INT);
                if ($perm_id === false) {
                    continue;
                }

                foreach ($role_permissions as $role => $value) {
                    $role = preg_replace('/[^a-z0-9_]/', '', strtolower($role));
                    if (empty($role)) {
                        continue;
                    }

                    $should_have = ($value === 'on');

                    $check_stmt = $conn->prepare("SELECT 1 FROM role_permissions WHERE role = ? AND permission_id = ?");
                    if (!$check_stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $check_stmt->bind_param("si", $role, $perm_id);
                    $check_stmt->execute();
                    $exists = $check_stmt->get_result()->num_rows > 0;
                    $check_stmt->close();

                    if ($should_have && !$exists) {
                        $insert_stmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role, permission_id, created_at) VALUES (?, ?, NOW())");
                        if (!$insert_stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        $insert_stmt->bind_param("si", $role, $perm_id);
                        $insert_stmt->execute();
                        if ($insert_stmt->affected_rows > 0) {
                            $permissions_saved++;
                        }
                        $insert_stmt->close();
                    } elseif (!$should_have && $exists) {
                        $delete_stmt = $conn->prepare("DELETE FROM role_permissions WHERE role = ? AND permission_id = ?");
                        if (!$delete_stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        $delete_stmt->bind_param("si", $role, $perm_id);
                        $delete_stmt->execute();
                        if ($delete_stmt->affected_rows > 0) {
                            $permissions_removed++;
                        }
                        $delete_stmt->close();
                    }
                }
            }
        }

        $conn->commit();

        if ($is_ajax) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            echo json_encode([
                'success' => true,
                'message' => "Permissions updated: $permissions_saved added, $permissions_removed removed",
                'added' => $permissions_saved,
                'removed' => $permissions_removed
            ]);
            exit();
        } else {
            $_SESSION['toast_message'] = "Permissions updated: $permissions_saved added, $permissions_removed removed";
            $_SESSION['toast_type'] = 'success';
            header('Location: role_access_matrix.php');
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();

        if ($is_ajax) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Error saving permissions: ' . $e->getMessage()
            ]);
            exit();
        } else {
            $_SESSION['toast_message'] = 'Error saving permissions: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error';
            header('Location: role_access_matrix.php');
            exit();
        }
    }
}

// Handle reset to default
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_default'])) {
    $conn->begin_transaction();

    try {
        $insert_count = ensurePermissionsInitialized($conn, true);
        $conn->commit();

        $_SESSION['toast_message'] = "Permissions reset to default values ($insert_count role permissions restored)";
        $_SESSION['toast_type'] = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['toast_message'] = 'Error resetting permissions: ' . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }

    header('Location: role_access_matrix.php');
    exit();
}

// Define default system roles with display names and colors
$roles = [
    'admin' => ['name' => 'Administrator', 'color' => '#dc3545'],
    'manager' => ['name' => 'Manager', 'color' => '#fd7e14'],
    'stock_manager' => ['name' => 'Stock Manager', 'color' => '#20c997'],
    'stock_controller' => ['name' => 'Stock Controller', 'color' => '#0dcaf0'],
    'tech_lead' => ['name' => 'Tech Lead', 'color' => '#6f42c1'],
    'technician' => ['name' => 'Technician', 'color' => '#355872'],
    'user' => ['name' => 'User', 'color' => '#6c757d'],
    'driver' => ['name' => 'Driver', 'color' => '#198754']
];

// Also merge any custom active roles registered in the `roles` table (from users.php)
$extra_colors = ['#0d6efd', '#d63384', '#6610f2', '#17a2b8', '#28a745', '#e83e8c'];
$color_idx = 0;
try {
    $check_roles_tbl = $conn->query("SHOW TABLES LIKE 'roles'");
    if ($check_roles_tbl && $check_roles_tbl->num_rows > 0) {
        $r_res = $conn->query("SELECT role_key, role_name FROM roles WHERE is_active = 1 ORDER BY id ASC");
        if ($r_res) {
            while ($r_row = $r_res->fetch_assoc()) {
                $r_key = trim($r_row['role_key']);
                if (!empty($r_key) && !isset($roles[$r_key])) {
                    $roles[$r_key] = [
                        'name' => $r_row['role_name'],
                        'color' => $extra_colors[$color_idx % count($extra_colors)]
                    ];
                    $color_idx++;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Optional roles table check failed: " . $e->getMessage());
}

// Load existing permissions from database
$db_permissions = [];
$result = $conn->query("SELECT role, permission_id FROM role_permissions");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $db_permissions[$row['role']][$row['permission_id']] = true;
    }
}

// Function to check if a role has a permission (for matrix display)
function roleHasPermission($role, $permission_id)
{
    global $db_permissions;
    return isset($db_permissions[$role][$permission_id]);
}

// Calculate totals for each role
$totals = [];
foreach (array_keys($roles) as $role) {
    $totals[$role] = isset($db_permissions[$role]) ? count($db_permissions[$role]) : 0;
}

$total_permissions = count($permissions);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap');

        * {
            font-family: 'Titillium Web', sans-serif;
        }

        :root {
            --primary-color: #234c6a;
            --primary-light: #2c5a7a;
            --primary-dark: #1a3a4f;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }

        .matrix-container {
            padding: 2rem 1.5rem;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
        }

        .page-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }

        .matrix-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .matrix-table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 15px 10px;
            font-weight: 600;
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .matrix-table th:first-child {
            border-radius: 10px 0 0 0;
            text-align: left;
            padding-left: 20px;
        }

        .matrix-table th:last-child {
            border-radius: 0 10px 0 0;
            border-right: none;
        }

        .matrix-table td {
            padding: 15px 10px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .matrix-table td:first-child {
            font-weight: 600;
            color: var(--primary-color);
            padding-left: 20px;
            background: rgba(35, 76, 106, 0.02);
            position: sticky;
            left: 0;
            z-index: 5;
        }

        .matrix-table tbody tr:hover {
            background-color: rgba(35, 76, 106, 0.05);
        }

        .matrix-table tbody tr:hover td:first-child {
            background-color: rgba(35, 76, 106, 0.1);
        }

        .permission-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .permission-icon {
            width: 35px;
            height: 35px;
            background: rgba(35, 76, 106, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .permission-details {
            flex: 1;
        }

        .permission-name {
            font-weight: 600;
            color: var(--primary-dark);
            display: block;
        }

        .permission-description {
            font-size: 0.75rem;
            color: #6c757d;
            display: block;
        }

        .permission-id {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            color: var(--primary-color);
            display: inline-block;
            margin-top: 2px;
        }

        /* Form Switch Styles */
        .form-switch-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .form-check-input {
            width: 3em;
            height: 1.5em;
            margin: 0;
            cursor: pointer;
            background-color: #dc3545;
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
            transition: all 0.3s ease;
        }

        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
            border-color: #28a745;
        }

        .role-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .role-name {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .role-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
            color: white;
        }

        .totals-row {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 700;
        }

        .totals-row td {
            padding: 15px 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .totals-row td:first-child {
            color: var(--primary-dark);
            font-size: 1.1rem;
        }

        .legend-card {
            border-radius: 10px;
            padding: 1rem;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 2rem;
        }

        .legend-switch {
            width: 2.5em;
            height: 1.2em;
            background-color: #28a745;
            border-radius: 2em;
            position: relative;
            margin-right: 8px;
        }

        .legend-switch::after {
            content: '';
            width: 1em;
            height: 1em;
            background: white;
            border-radius: 50%;
            position: absolute;
            right: 0.1em;
            top: 0.1em;
        }

        .legend-switch.off {
            background-color: #dc3545;
        }

        .legend-switch.off::after {
            left: 0.1em;
            right: auto;
        }

        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-save {
            background: #236da2;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(35, 76, 106, 0.3);
            color: white;
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
            color: white;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(35, 76, 106, 0.15);
        }

        .role-count {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
        }

        @media (max-width: 768px) {
            .matrix-container {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .role-name {
                font-size: 0.7rem;
            }
        }

        .auto-save-toast {
            position: absolute;
            background: #28a745;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 3px;
            top: -20px;
            right: 0;
            z-index: 1000;
            animation: fadeInOut 1s ease;
        }

        @keyframes fadeInOut {
            0% {
                opacity: 0;
                transform: translateY(5px);
            }

            20% {
                opacity: 1;
                transform: translateY(0);
            }

            80% {
                opacity: 1;
                transform: translateY(0);
            }

            100% {
                opacity: 0;
                transform: translateY(-5px);
            }
        }

        .form-check-input:disabled {
            opacity: 0.5;
            cursor: wait;
        }
    </style>
</head>

<body>
    <?php
    // Include the page header (which outputs the compact header bar)
    require_once 'views/partials/page_header.php';
    ?>

    <div class="matrix-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1><i class="fas fa-shield-alt me-2"></i>Role Access Matrix</h1>
                    <p>Configure which roles have access to which permissions using the toggle switches</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="users.php" class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill">
                        <i class="fas fa-arrow-left me-1"></i> Back to Users
                    </a>
                    <span class="badge bg-white text-primary p-3">
                        <i class="fas fa-users-cog me-2"></i><span id="headerRolesCount"><?php echo count($roles); ?></span> Roles | <span id="headerPermsCount"><?php echo $total_permissions; ?></span> Permissions
                    </span>
                </div>
            </div>
        </div>

        <div class="accordion" id="accordionPanelsStayOpenExample">
            <div class="accordion-item">
                <h2 class="accordion-header" style="background: #fffffffb;">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true" aria-controls="panelsStayOpen-collapseOne">
                        <!-- Legend -->
                        <div class="legend-card w-100">
                            <div class="d-flex flex-wrap align-items-center">
                                <span class="me-3"><strong>Legend:</strong></span>
                                <div class="legend-item">
                                    <div class="legend-switch"></div>
                                    <span>Access Enabled (Green)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-switch off"></div>
                                    <span>Access Disabled (Red)</span>
                                </div>
                                <div class="ms-auto">
                                    <i class="fas fa-info-circle text-muted me-1"></i>
                                    <small class="text-muted">Toggle switches to enable/disable permissions for each role</small>
                                </div>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <!-- Stats Summary -->
                        <div class="row mt-3">
                            <?php foreach ($roles as $role_key => $role_info): ?>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="stats-card">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle p-2 me-2" style="background: <?php echo $role_info['color']; ?>20; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user" style="color: <?php echo $role_info['color']; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($role_info['name']); ?></div>
                                                <div class="role-count">
                                                    <span id="role_count_<?php echo htmlspecialchars($role_key); ?>"><?php echo $totals[$role_key]; ?></span>
                                                    <small style="font-size: 0.9rem; color: #6c757d;">/<span class="total-perms-num"><?php echo $total_permissions; ?></span></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="progress mt-2" style="height: 4px;">
                                            <div class="progress-bar" role="progressbar"
                                                id="role_progress_<?php echo htmlspecialchars($role_key); ?>"
                                                style="width: <?php echo $total_permissions > 0 ? ($totals[$role_key] / $total_permissions) * 100 : 0; ?>%; background: <?php echo $role_info['color']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseTwo" aria-expanded="true" aria-controls="panelsStayOpen-collapseTwo">
                        <strong><i class="fas fa-table me-2 text-primary"></i>Roles &amp; Permissions Matrix</strong>
                    </button>
                </h2>
                <div id="panelsStayOpen-collapseTwo" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <!-- Access Matrix Card -->
                        <div class="matrix-card">
                            <form method="POST" id="accessMatrixForm">

                                <!-- Items Per Page Selector -->
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <div>
                                        <label class="me-2">Show</label>
                                        <select id="itemsPerPage" class="form-select form-select-sm d-inline-block w-auto" onchange="changeItemsPerPage()">
                                            <option value="5">5</option>
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                        <label class="ms-2">entries</label>
                                    </div>
                                    <div class="text-muted">
                                        <span id="showingInfo">Showing 1 to <?php echo min(10, $total_permissions); ?> of <?php echo $total_permissions; ?> permissions</span>
                                    </div>
                                </div>

                                <!-- Search Box -->
                                <div class="row mb-3 align-items-center">
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0">
                                                <i class="fas fa-search text-muted"></i>
                                            </span>
                                            <input type="text" class="form-control border-start-0" id="searchPermission"
                                                placeholder="Search permissions..." onkeyup="filterPermissions()">
                                        </div>
                                    </div>
                                    <div class="col-md-8 text-end">
                                        <span class="badge bg-info p-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Showing page <span id="currentPageDisplay">1</span> of <span id="totalPagesDisplay">1</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="matrix-table" id="permissionsTable">
                                        <thead>
                                            <tr>
                                                <th>Permission</th>
                                                <?php foreach ($roles as $role_key => $role_info): ?>
                                                    <th>
                                                        <div class="role-header">
                                                            <span class="role-name"><?php echo htmlspecialchars($role_info['name']); ?></span>
                                                            <span class="role-badge" style="background: <?php echo $role_info['color']; ?>">
                                                                <?php echo htmlspecialchars($role_key); ?>
                                                            </span>
                                                        </div>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody id="permissionsTableBody">
                                            <!-- Rows will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Hidden data store for JavaScript -->
                                <script>
                                    var permissionsData = <?php
                                        $permissions_data = [];
                                        foreach ($permissions as $perm_id => $permission) {
                                            $role_perms = [];
                                            foreach (array_keys($roles) as $role_key) {
                                                $role_perms[$role_key] = roleHasPermission($role_key, $perm_id);
                                            }
                                            $permissions_data[] = [
                                                'id' => (int)$perm_id,
                                                'display_name' => $permission['display_name'],
                                                'name' => $permission['name'],
                                                'description' => $permission['description'] ?? '',
                                                'role_permissions' => $role_perms
                                            ];
                                        }
                                        echo json_encode($permissions_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                    ?>;

                                    var dataTimestamp = <?php echo time(); ?>;
                                    var roles = <?php echo json_encode(array_keys($roles)); ?>;
                                </script>

                                <!-- Pagination Controls -->
                                <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="previousPage()" id="prevPageBtn">
                                            <i class="fas fa-chevron-left me-1"></i>Previous
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="nextPage()" id="nextPageBtn">
                                            Next<i class="fas fa-chevron-right ms-1"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="reloadPermissionsFromServer(this)">
                                            <i class="fas fa-sync-alt me-1"></i> Refresh
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark" onclick="resetToDefault()">
                                            <i class="fas fa-undo me-1"></i> Reset to Default
                                        </button>
                                    </div>

                                    <div class="pagination-container">
                                        <ul class="pagination mb-0" id="pagination">
                                            <!-- Pagination will be generated by JavaScript -->
                                        </ul>
                                    </div>

                                    <div>
                                        <span class="text-muted">
                                            Page <span id="currentPage">1</span> of <span id="totalPages">1</span>
                                        </span>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // PERMISSIONS MATRIX - MAIN SCRIPT
        // ============================================

        let currentPage = 1;
        let itemsPerPage = 10;
        let filteredData = [];
        let allData = [];

        document.addEventListener('DOMContentLoaded', function() {
            const perPageSelect = document.getElementById('itemsPerPage');
            if (perPageSelect) {
                itemsPerPage = parseInt(perPageSelect.value, 10) || 10;
            }

            allData = [...permissionsData];
            filteredData = [...allData];

            renderTable();
            renderPagination();
            updateSwitchCounts();
            updateRoleStatsUI();
        });

        document.getElementById('accessMatrixForm').addEventListener('submit', function(e) {
            e.preventDefault();
            return false;
        });

        function renderTable() {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageData = filteredData.slice(start, end);

            let html = '';
            if (pageData.length === 0) {
                html = `<tr><td colspan="${roles.length + 1}" class="text-center py-4 text-muted">No matching permissions found.</td></tr>`;
            } else {
                pageData.forEach(perm => {
                    html += '<tr>';
                    html += `<td>
                                <div class="permission-info">
                                    <div class="permission-icon">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="permission-details">
                                        <span class="permission-name">${escapeHtml(perm.display_name)}</span>
                                        <span class="permission-id">${escapeHtml(perm.name)}</span>
                                        <span class="permission-description">${escapeHtml(perm.description || '')}</span>
                                    </div>
                                </div>
                            </td>`;

                    roles.forEach(role => {
                        const checked = perm.role_permissions && perm.role_permissions[role] ? 'checked' : '';
                        const permId = perm.id;

                        html += `<td class="text-center">
                            <div class="form-switch-container" style="position: relative;">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                        name="permissions[${permId}][${role}]"
                                        id="perm_${permId}_${role}"
                                        ${checked}
                                        onchange="autoSavePermission(this)">
                                </div>
                            </div>
                        </td>`;
                    });

                    html += '</tr>';
                });
            }

            document.getElementById('permissionsTableBody').innerHTML = html;
            updatePaginationInfo();
        }

        function updatePaginationInfo() {
            const start = filteredData.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0;
            const end = Math.min(currentPage * itemsPerPage, filteredData.length);
            const totalPages = Math.max(1, Math.ceil(filteredData.length / itemsPerPage));

            const showingInfo = document.getElementById('showingInfo');
            if (showingInfo) {
                showingInfo.innerHTML = `Showing ${start} to ${end} of ${filteredData.length} permissions`;
            }

            const elements = {
                currentPage: document.getElementById('currentPage'),
                currentPageDisplay: document.getElementById('currentPageDisplay'),
                totalPages: document.getElementById('totalPages'),
                totalPagesDisplay: document.getElementById('totalPagesDisplay')
            };

            if (elements.currentPage) elements.currentPage.textContent = currentPage;
            if (elements.currentPageDisplay) elements.currentPageDisplay.textContent = currentPage;
            if (elements.totalPages) elements.totalPages.textContent = totalPages;
            if (elements.totalPagesDisplay) elements.totalPagesDisplay.textContent = totalPages;

            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');

            if (prevBtn) prevBtn.disabled = currentPage <= 1;
            if (nextBtn) nextBtn.disabled = currentPage >= totalPages || filteredData.length === 0;
        }

        function renderPagination() {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            const pagination = document.getElementById('pagination');
            if (!pagination) return;

            let paginationHtml = '';

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
                    </li>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            pagination.innerHTML = paginationHtml;
        }

        function goToPage(page) {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderTable();
            renderPagination();
        }

        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
                renderPagination();
            }
        }

        function nextPage() {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
                renderPagination();
            }
        }

        function changeItemsPerPage() {
            itemsPerPage = parseInt(document.getElementById('itemsPerPage').value, 10) || 10;
            currentPage = 1;
            renderTable();
            renderPagination();
        }

        function filterPermissions() {
            const searchTerm = document.getElementById('searchPermission').value.toLowerCase().trim();

            if (searchTerm === '') {
                filteredData = [...allData];
            } else {
                filteredData = allData.filter(perm =>
                    (perm.display_name && perm.display_name.toLowerCase().includes(searchTerm)) ||
                    (perm.name && perm.name.toLowerCase().includes(searchTerm)) ||
                    (perm.description && perm.description.toLowerCase().includes(searchTerm))
                );
            }

            currentPage = 1;
            renderTable();
            renderPagination();
        }

        function updateRoleStatsUI() {
            const totalPerms = allData.length;
            document.querySelectorAll('.total-perms-num').forEach(el => {
                el.textContent = totalPerms;
            });
            const headerPerms = document.getElementById('headerPermsCount');
            if (headerPerms) headerPerms.textContent = totalPerms;

            roles.forEach(role => {
                let count = 0;
                allData.forEach(perm => {
                    if (perm.role_permissions && perm.role_permissions[role]) {
                        count++;
                    }
                });
                const countEl = document.getElementById('role_count_' + role);
                if (countEl) countEl.textContent = count;

                const progEl = document.getElementById('role_progress_' + role);
                if (progEl) {
                    const pct = totalPerms > 0 ? (count / totalPerms) * 100 : 0;
                    progEl.style.width = pct + '%';
                }
            });
        }

        function autoSavePermission(checkbox) {
            const match = checkbox.name.match(/permissions\[(\d+)\]\[([a-z0-9_]+)\]/i);
            if (!match) {
                console.error('Invalid checkbox name format:', checkbox.name);
                return;
            }

            const permId = match[1];
            const role = match[2];
            const isChecked = checkbox.checked;

            checkbox.style.backgroundColor = '#ffc107';
            checkbox.disabled = true;

            const formData = new FormData();
            formData.append('save_permissions', '1');
            formData.append(`permissions[${permId}][${role}]`, isChecked ? 'on' : 'off');

            fetch('role_access_matrix.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text.substring(0, 300));
                        throw new Error('Server returned invalid JSON.');
                    }

                    if (data.success) {
                        showSaveFeedback(checkbox, '✓ Saved', '#28a745');

                        const permIndex = allData.findIndex(p => p.id == permId);
                        if (permIndex !== -1) {
                            if (!allData[permIndex].role_permissions) {
                                allData[permIndex].role_permissions = {};
                            }
                            allData[permIndex].role_permissions[role] = isChecked;
                        }

                        checkbox.style.backgroundColor = '';
                        checkbox.disabled = false;
                        updateRoleStatsUI();
                        updateSwitchCounts();
                    } else {
                        throw new Error(data.message || 'Save failed');
                    }
                })
                .catch(error => {
                    console.error('Auto-save failed:', error);
                    showSaveFeedback(checkbox, '✗ Failed', '#dc3545');

                    setTimeout(() => {
                        checkbox.style.backgroundColor = '';
                        checkbox.disabled = false;
                        checkbox.checked = !isChecked;
                    }, 1500);
                });
        }

        function showSaveFeedback(checkbox, message, color) {
            const toast = document.createElement('div');
            toast.className = 'auto-save-toast';
            toast.style.background = color;
            toast.style.color = 'white';
            toast.innerHTML = message;

            checkbox.parentElement.style.position = 'relative';
            checkbox.parentElement.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 1000);
        }

        function reloadPermissionsFromServer(btnEl) {
            const refreshBtn = btnEl || (typeof event !== 'undefined' ? event?.currentTarget : null);
            const originalText = refreshBtn ? refreshBtn.innerHTML : '';

            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
                refreshBtn.disabled = true;
            }

            fetch('role_access_matrix.php?action=get_current_permissions', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(serverData => {
                    if (Array.isArray(serverData)) {
                        allData = serverData.map(serverPerm => {
                            const newRolePerms = {};
                            roles.forEach(role => {
                                newRolePerms[role] = Array.isArray(serverPerm.roles) ? serverPerm.roles.includes(role) : false;
                            });
                            return {
                                id: serverPerm.id,
                                name: serverPerm.name,
                                display_name: serverPerm.display_name,
                                description: serverPerm.description || '',
                                role_permissions: newRolePerms
                            };
                        });
                        permissionsData = [...allData];
                    }

                    filterPermissions();
                    updateRoleStatsUI();
                    updateSwitchCounts();

                    if (typeof showToast === 'function') {
                        showToast('Permissions reloaded successfully!', 'success');
                    }
                })
                .catch(error => {
                    console.error('Failed to reload permissions:', error);
                    if (typeof showToast === 'function') {
                        showToast('Failed to reload permissions. Check console for details.', 'danger');
                    } else {
                        alert('Failed to reload permissions. Check console for details.');
                    }
                })
                .finally(() => {
                    if (refreshBtn) {
                        refreshBtn.innerHTML = originalText;
                        refreshBtn.disabled = false;
                    }
                });
        }

        function updateSwitchCounts() {
            const checkboxes = document.querySelectorAll('.form-check-input');
            let enabled = 0;
            let disabled = 0;

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    enabled++;
                } else {
                    disabled++;
                }
            });

            console.log('Switch counts - Enabled:', enabled, 'Disabled:', disabled);
        }

        function resetToDefault() {
            if (confirm('Reset all permissions to default values? This will reload the page.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'role_access_matrix.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'reset_default';
                input.value = '1';

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function refreshCurrentPage() {
            window.location.href = window.location.pathname + '?t=' + new Date().getTime();
        }

        window.goToPage = goToPage;
        window.previousPage = previousPage;
        window.nextPage = nextPage;
        window.changeItemsPerPage = changeItemsPerPage;
        window.filterPermissions = filterPermissions;
        window.autoSavePermission = autoSavePermission;
        window.reloadPermissionsFromServer = reloadPermissionsFromServer;
        window.resetToDefault = resetToDefault;
        window.refreshCurrentPage = refreshCurrentPage;
        window.updateSwitchCounts = updateSwitchCounts;
    </script>

    <!-- jQuery MUST be loaded first -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- AOS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });

        function showToast(message, type = 'success', duration = 4000) {
            const alertType = (type === 'error') ? 'danger' : type;
            const toast = document.createElement('div');
            toast.className = `alert alert-${alertType} alert-dismissible fade show`;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            toast.style.minWidth = '300px';
            toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
            toast.innerHTML = `
                ${type === 'success' ? '<i class="fas fa-check-circle me-2"></i>' : 
                  (type === 'error' || type === 'danger') ? '<i class="fas fa-exclamation-triangle me-2"></i>' : 
                  '<i class="fas fa-info-circle me-2"></i>'}
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, duration);
        }

        <?php if (isset($_SESSION['toast_message'])): ?>
            showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type'] ?? 'success'; ?>');
            <?php
            unset($_SESSION['toast_message']);
            unset($_SESSION['toast_type']);
            ?>
        <?php endif; ?>
    </script>
</body>

</html>
<?php
if ($is_ajax) {
    $unexpected_output = ob_get_clean();
    if (!empty($unexpected_output)) {
        error_log("Unexpected output in AJAX response: " . $unexpected_output);
    }
}
?>
