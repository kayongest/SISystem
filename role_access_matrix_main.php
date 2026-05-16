<?php
// Add this right after the opening <?php tag
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off error display for AJAX requests
ini_set('log_errors', 1); // But keep logging errors

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax) {
    // For AJAX requests, we want to catch any errors
    ob_start(); // Start output buffering
}


// Add this right after the opening <?php tag
error_log("========== PAGE LOAD ==========");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . print_r($_POST, true));
}

// role_access_matrix.php - Role-Based Access Control Management
require_once 'bootstrap.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Check if user is admin (only admins can access this page)
if (!isAdmin()) {
    $_SESSION['toast_message'] = 'You do not have permission to access role management';
    $_SESSION['toast_type'] = 'error';
    header('Location: dashboard_full.php');
    exit();
}

// Get database connection
$conn = getConnection();

$pageTitle = "Role Access Matrix - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'User Management' => 'users.php',
    'Role Access Matrix' => ''
];

// Get all permissions from database
$permissions = [];
$perm_result = $conn->query("SELECT * FROM permissions ORDER BY display_name");
if ($perm_result) {
    while ($row = $perm_result->fetch_assoc()) {
        $permissions[$row['id']] = $row;
    }
}


// Handle form submission to save permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {

    // Check if this is an AJAX request
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // For AJAX requests, turn off error display and set JSON header early
    if ($is_ajax) {
        // Turn off error reporting to prevent HTML output
        error_reporting(0);
        ini_set('display_errors', 0);

        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set JSON header
        header('Content-Type: application/json');
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        $permissions_saved = 0;
        $permissions_removed = 0;

        // Process ONLY the permissions that were sent in the request
        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $perm_id => $role_permissions) {
                // Sanitize permission_id
                $perm_id = filter_var($perm_id, FILTER_VALIDATE_INT);
                if ($perm_id === false) {
                    continue; // Skip invalid permission IDs
                }

                foreach ($role_permissions as $role => $value) {
                    // Sanitize role
                    $role = preg_replace('/[^a-z_]/', '', $role);
                    if (empty($role)) {
                        continue; // Skip invalid roles
                    }

                    $should_have = ($value === 'on');

                    // Check if it currently exists
                    $check_stmt = $conn->prepare("SELECT 1 FROM role_permissions WHERE role = ? AND permission_id = ?");
                    if (!$check_stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $check_stmt->bind_param("si", $role, $perm_id);
                    $check_stmt->execute();
                    $exists = $check_stmt->get_result()->num_rows > 0;
                    $check_stmt->close();

                    if ($should_have && !$exists) {
                        // Add permission
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
                        // Remove permission
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

        // Commit transaction
        $conn->commit();

        if ($is_ajax) {
            // Clear any output buffers again
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
        // Rollback on error
        $conn->rollback();

        if ($is_ajax) {
            // Clear any output buffers
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
        // Clear all permissions
        $conn->query("DELETE FROM role_permissions");

        // Define default permissions based on your matrix
        $default_permissions = [
            'admin' => 'all', // Special case - we'll handle separately
            'manager' => [
                'view_dashboard',     // ID: 1
                'view_events',        // ID: 8
                'view_equipment',     // ID: 25
                'import_export',      // ID: 10
                'view_reports',       // ID: 11
                'manage_technicians', // ID: 9
                'view_users',         // ID: 5
                'manage_items'        // ID: 2
            ],
            'stock_manager' => [
                'view_dashboard',     // ID: 1
                'view_events',        // ID: 8
                'view_equipment',     // ID: 25
                'import_export',      // ID: 10
                'view_items'          // ID: 3
            ],
            'stock_controller' => [
                'view_dashboard',     // ID: 1
                'view_events',        // ID: 8
                'view_equipment',     // ID: 25
                'view_reports',       // ID: 11
                'manage_items'        // ID: 2
            ],
            'tech_lead' => [
                'view_dashboard',     // ID: 1
                'view_events',        // ID: 8
                'view_equipment',     // ID: 25
                'manage_technicians'  // ID: 9
            ],
            'technician' => [
                'view_dashboard',     // ID: 1
                'scan_single',        // ID: 29
                'scan_bulk'           // ID: 30
            ],
            'user' => [
                'view_dashboard',     // ID: 1
                'view_events',        // ID: 8
                'view_equipment'      // ID: 25
            ],
            'driver' => [
                'view_dashboard',     // ID: 1
                'view_events',        // ID: 8
                'view_equipment'      // ID: 25
            ]
        ];

        // Get permission IDs by name
        $perm_names = [];
        $result = $conn->query("SELECT id, name FROM permissions");
        while ($row = $result->fetch_assoc()) {
            $perm_names[$row['name']] = $row['id'];
        }

        // Insert default permissions
        $insert_stmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role, permission_id, created_at) VALUES (?, ?, NOW())");
        $insert_count = 0;

        // Handle admin separately - give ALL permissions
        foreach ($perm_names as $perm_name => $perm_id) {
            $insert_stmt->bind_param("si", 'admin', $perm_id);
            $insert_stmt->execute();
            if ($insert_stmt->affected_rows > 0) {
                $insert_count++;
            }
        }

        // Handle other roles
        foreach ($default_permissions as $role => $perms) {
            if ($role === 'admin') continue; // Already handled

            foreach ($perms as $perm_name) {
                if (isset($perm_names[$perm_name])) {
                    $perm_id = $perm_names[$perm_name];
                    $insert_stmt->bind_param("si", $role, $perm_id);
                    $insert_stmt->execute();
                    if ($insert_stmt->affected_rows > 0) {
                        $insert_count++;
                    }
                }
            }
        }

        $insert_stmt->close();
        $conn->commit();

        $_SESSION['toast_message'] = "Permissions reset to default values ($insert_count permissions restored)";
        $_SESSION['toast_type'] = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['toast_message'] = 'Error resetting permissions: ' . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }

    header('Location: role_access_matrix.php');
    exit();
}

require_once 'views/partials/header.php';

// Define all roles with display names and colors
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

// Load existing permissions from database
$db_permissions = [];
$result = $conn->query("SELECT role, permission_id FROM role_permissions");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $db_permissions[$row['role']][$row['permission_id']] = true;
    }
}

// Add this debug code to verify permissions are loaded
error_log("Loaded permissions for roles: " . count($db_permissions));
foreach (array_keys($roles) as $role) {
    $count = isset($db_permissions[$role]) ? count($db_permissions[$role]) : 0;
    error_log("Role $role has $count permissions");
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

<!-- Rest of your HTML remains exactly the same -->


<style>
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
        /* background: white; */
        border-radius: 10px;
        padding: 1rem;
        /* margin-bottom: 1.5rem; */
        /* border: 1px solid #e9ecef; */
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
        padding: 0.75rem 2rem;
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

    /* Add to your existing styles */
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

    /* Disabled checkbox style */
    .form-check-input:disabled {
        opacity: 0.5;
        cursor: wait;
    }
</style>

<div class="matrix-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1><i class="fas fa-shield-alt me-2"></i>Role Access Matrix</h1>
                <p>Configure which roles have access to which permissions using the toggle switches</p>
            </div>
            <div>
                <span class="badge bg-white text-primary p-3">
                    <i class="fas fa-users-cog me-2"></i><?php echo count($roles); ?> Roles | <?php echo $total_permissions; ?> Permissions
                </span>
            </div>
        </div>
    </div>


    <!-- Quick Stats Cards -->
    <!-- <div class="row mt-4 mb-2">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle p-3 me-3" style="background: rgba(40, 167, 69, 0.1);">
                            <i class="fas fa-check-circle fa-2x" style="color: #28a745;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Total Permissions</h6>
                            <h3 class="mb-0"><?php echo $total_permissions; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle p-3 me-3" style="background: rgba(35, 76, 106, 0.1);">
                            <i class="fas fa-users fa-2x" style="color: #234c6a;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Total Roles</h6>
                            <h3 class="mb-0"><?php echo count($roles); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle p-3 me-3" style="background: rgba(255, 193, 7, 0.1);">
                            <i class="fas fa-crown fa-2x" style="color: #ffc107;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Admin Access</h6>
                            <h3 class="mb-0"><?php echo $totals['admin']; ?>/<?php echo $total_permissions; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle p-3 me-3" style="background: rgba(108, 117, 125, 0.1);">
                            <i class="fas fa-tools fa-2x" style="color: #6c757d;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Technician Access</h6>
                            <h3 class="mb-0"><?php echo $totals['technician']; ?>/<?php echo $total_permissions; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->





    <div class="accordion" id="accordionPanelsStayOpenExample">
        <div class="accordion-item">
            <h2 class="accordion-header" style="background: #fffffffb;">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true" aria-controls="panelsStayOpen-collapseOne">
                    <!-- Legend -->
                    <div class="legend-card">
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
                    <!-- Stats Summary (Keep this at the bottom) -->
                    <div class="row mt-5">
                        <?php foreach ($roles as $role_key => $role_info): ?>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle p-2 me-2" style="background: <?php echo $role_info['color']; ?>20; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user" style="color: <?php echo $role_info['color']; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted"><?php echo $role_info['name']; ?></div>
                                            <div class="role-count"><?php echo $totals[$role_key]; ?> <small style="font-size: 0.9rem; color: #6c757d;">/<?php echo $total_permissions; ?></small></div>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 4px;">
                                        <div class="progress-bar" role="progressbar"
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
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseTwo" aria-expanded="false" aria-controls="panelsStayOpen-collapseTwo">
                    Roles & Permissions Matrix
                </button>
            </h2>
            <div id="panelsStayOpen-collapseTwo" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <!-- Access Matrix Card -->
                    <div class="matrix-card">
                        <form method="POST" id="accessMatrixForm" onsubmit="console.log('Form submitting...');">

                            <!-- Items Per Page Selector -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <label class="me-2">Show</label>
                                    <select id="itemsPerPage" class="form-select form-select-sm d-inline-block w-auto" onchange="changeItemsPerPage()">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <label class="ms-2">entries</label>
                                </div>
                                <div class="text-muted">
                                    <span id="showingInfo">Showing 1 to 5 of <?php echo $total_permissions; ?> permissions</span>
                                </div>
                            </div>

                            <!-- Search Box -->
                            <div class="row mb-3">
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
                                                        <span class="role-name"><?php echo $role_info['name']; ?></span>
                                                        <span class="role-badge" style="background: <?php echo $role_info['color']; ?>">
                                                            <?php echo $role_key; ?>
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
                                // Initialize permissions data with a timestamp to prevent caching
                                var permissionsData =
                                    <?php
                                    $permissions_data = [];
                                    foreach ($permissions as $perm_id => $permission) {
                                        $role_perms = [];
                                        foreach (array_keys($roles) as $role_key) {
                                            $role_perms[$role_key] = roleHasPermission($role_key, $perm_id);
                                        }
                                        $permissions_data[] = [
                                            'id' => $perm_id,
                                            'display_name' => $permission['display_name'],
                                            'name' => $permission['name'],
                                            'description' => $permission['description'] ?? '',
                                            'role_permissions' => $role_perms
                                        ];
                                    }
                                    echo json_encode($permissions_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                    ?>;

                                // Add timestamp to prevent caching
                                var dataTimestamp = <?php echo time(); ?>;

                                var roles = <?php echo json_encode(array_keys($roles)); ?>;
                                var roleColors = <?php echo json_encode(array_column($roles, 'color', 'key')); ?>;

                                console.log('Permissions Data loaded:', permissionsData.length, 'items at', new Date(dataTimestamp * 1000).toLocaleString());
                                console.log('Roles:', roles);

                                // Log a sample of permissions for debugging
                                if (permissionsData.length > 0) {
                                    console.log('Sample permission:', permissionsData[0]);
                                }
                            </script>

                            <!-- Pagination Controls -->
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="previousPage()" id="prevPageBtn">
                                        <i class="fas fa-chevron-left me-1"></i>Previous
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="nextPage()" id="nextPageBtn">
                                        Next<i class="fas fa-chevron-right ms-1"></i>
                                    </button>
                                    <!-- Add this near your pagination controls -->
                                    <div class="text-center mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="reloadPermissionsFromServer()">
                                            <i class="fas fa-sync-alt me-1"></i> Refresh
                                        </button>
                                    </div>
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

                            <!-- Stats Summary (Keep this at the bottom) -->
                            <!-- <div class="row mt-5">
                <?php foreach ($roles as $role_key => $role_info): ?>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle p-2 me-2" style="background: <?php echo $role_info['color']; ?>20; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="color: <?php echo $role_info['color']; ?>"></i>
                                </div>
                                <div>
                                    <div class="small text-muted"><?php echo $role_info['name']; ?></div>
                                    <div class="role-count"><?php echo $totals[$role_key]; ?> <small style="font-size: 0.9rem; color: #6c757d;">/<?php echo $total_permissions; ?></small></div>
                                </div>
                            </div>
                            <div class="progress mt-2" style="height: 4px;">
                                <div class="progress-bar" role="progressbar"
                                    style="width: <?php echo $total_permissions > 0 ? ($totals[$role_key] / $total_permissions) * 100 : 0; ?>%; background: <?php echo $role_info['color']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div> -->

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <!-- <button type="button" class="btn-reset" onclick="resetToDefault()">
                    <i class="fas fa-undo me-2"></i>Reset to Default
                </button> -->
                                <!-- <button type="submit" name="save_permissions" class="btn-save">
                    <i class="fas fa-save me-2"></i>Save Permissions
                </button> -->
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

    // ---------- Global Variables ----------
    let currentPage = 1;
    let itemsPerPage = 5;
    let filteredData = [];
    let allData = [];

    // ---------- Initialization ----------
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize data
        filteredData = [...permissionsData];
        allData = [...permissionsData];

        // Initial render
        renderTable();
        renderPagination();
        updateSwitchCounts();

        // Add event listeners to all switches
        document.querySelectorAll('.form-check-input').forEach(checkbox => {
            checkbox.addEventListener('change', updateSwitchCounts);
        });

        // Log current state for debugging
        console.log('Permissions Matrix Initialized:');
        console.log('- Current page:', currentPage);
        console.log('- Items per page:', itemsPerPage);
        console.log('- Total permissions:', allData.length);
        console.log('- Roles:', roles);
    });

    // ---------- Form Submission Prevention ----------
    document.getElementById('accessMatrixForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent form submission since we use auto-save
        return false;
    });

    // ---------- Table Rendering ----------
    function renderTable() {
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = filteredData.slice(start, end);

        let html = '';
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

        document.getElementById('permissionsTableBody').innerHTML = html;

        // Update showing info
        updatePaginationInfo();
    }

    // ---------- Pagination Info Update ----------
    function updatePaginationInfo() {
        const start = filteredData.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0;
        const end = Math.min(currentPage * itemsPerPage, filteredData.length);
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);

        // Update showing info
        const showingInfo = document.getElementById('showingInfo');
        if (showingInfo) {
            showingInfo.innerHTML = `Showing ${start} to ${end} of ${filteredData.length} permissions`;
        }

        // Update page displays
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

        // Update button states
        const prevBtn = document.getElementById('prevPageBtn');
        const nextBtn = document.getElementById('nextPageBtn');

        if (prevBtn) prevBtn.disabled = currentPage === 1;
        if (nextBtn) nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    // ---------- Pagination Controls ----------
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
        if (page < 1 || page > Math.ceil(filteredData.length / itemsPerPage)) return;
        currentPage = page;
        filteredData = [...allData];
        renderTable();
        renderPagination();
    }

    function previousPage() {
        if (currentPage > 1) {
            currentPage--;
            filteredData = [...allData];
            renderTable();
            renderPagination();
        }
    }

    function nextPage() {
        const totalPages = Math.ceil(allData.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            filteredData = [...allData];
            renderTable();
            renderPagination();
        }
    }

    function changeItemsPerPage() {
        itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
        currentPage = 1;
        renderTable();
        renderPagination();
    }

    // ---------- Search/Filter ----------
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

    // ---------- Auto-Save Function ----------
    function autoSavePermission(checkbox) {
        const match = checkbox.name.match(/permissions\[(\d+)\]\[(\w+)\]/);
        if (!match) {
            console.error('Invalid checkbox name format:', checkbox.name);
            return;
        }

        const permId = match[1];
        const role = match[2];
        const isChecked = checkbox.checked;

        console.log(`🔄 Saving: ${role} - permission_id ${permId} = ${isChecked ? 'ON' : 'OFF'}`);

        // Show loading indicator
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
                // Try to parse JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 300));
                    throw new Error('Server returned invalid JSON. Check for PHP errors.');
                }

                if (data.success) {
                    console.log(`✅ Save successful for ${role} - permission_id ${permId}`);

                    // Show success indicator
                    showSaveFeedback(checkbox, '✓ Saved', '#28a745');

                    // Update local data
                    const permIndex = allData.findIndex(p => p.id == permId);
                    if (permIndex !== -1) {
                        if (!allData[permIndex].role_permissions) {
                            allData[permIndex].role_permissions = {};
                        }
                        allData[permIndex].role_permissions[role] = isChecked;
                    }

                    // Reset checkbox state
                    checkbox.style.backgroundColor = '';
                    checkbox.disabled = false;
                } else {
                    throw new Error(data.message || 'Save failed');
                }
            })
            .catch(error => {
                console.error('❌ Auto-save failed:', error);

                // Show error feedback
                showSaveFeedback(checkbox, '✗ Failed', '#dc3545');

                // Revert checkbox after delay
                setTimeout(() => {
                    checkbox.style.backgroundColor = '';
                    checkbox.disabled = false;
                    checkbox.checked = !isChecked; // Revert state
                }, 1500);
            });
    }

    // Helper function for save feedback
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

    // ---------- Reload from Server ----------
    function reloadPermissionsFromServer() {
        const refreshBtn = event?.target;
        const originalText = refreshBtn ? refreshBtn.innerHTML : '';

        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
            refreshBtn.disabled = true;
        }

        fetch('get_current_permissions.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(serverData => {
                // Update allData with server data
                allData = permissionsData.map(localPerm => {
                    const serverPerm = serverData.find(p => p.id === localPerm.id);
                    if (serverPerm) {
                        const newRolePerms = {};
                        roles.forEach(role => {
                            newRolePerms[role] = serverPerm.roles ? serverPerm.roles.includes(role) : false;
                        });
                        localPerm.role_permissions = newRolePerms;
                    }
                    return localPerm;
                });

                // Update filteredData and re-render
                filteredData = [...allData];
                renderTable();
                renderPagination();
                updateSwitchCounts();

                console.log('✅ Permissions reloaded from server');

                // Show success message
                alert('Permissions reloaded successfully!');
            })
            .catch(error => {
                console.error('❌ Failed to reload permissions:', error);
                alert('Failed to reload permissions. Check console for details.');
            })
            .finally(() => {
                if (refreshBtn) {
                    refreshBtn.innerHTML = originalText;
                    refreshBtn.disabled = false;
                }
            });
    }

    // ---------- Utility Functions ----------
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
            form.action = '';

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

    // ---------- Expose functions globally ----------
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

<?php require_once 'views/partials/footer.php'; ?>

<?php
// At the end of role_access_matrix.php
if ($is_ajax) {
    // If there was any unexpected output, log it
    $unexpected_output = ob_get_clean();
    if (!empty($unexpected_output)) {
        error_log("Unexpected output in AJAX response: " . $unexpected_output);
    }
}
?>