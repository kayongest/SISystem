<?php

/**
 * Common functions for aBility Manager
 * COMPLETE VERSION
 */

// Define BASE_URL if not defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}



/**
 * Common functions for aBility Manager
 * COMPLETE VERSION
 */

// Define BASE_URL if not defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Check if user is logged in - FIXED VERSION
// Check if user is logged in - FIXED VERSION
function isLoggedIn()
{
    // Session should already be started in bootstrap.php
    // Check for multiple possible session variables your login might set
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        error_log("isLoggedIn(): TRUE - user_id found: " . $_SESSION['user_id']);
        return true;
    }

    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        error_log("isLoggedIn(): TRUE - logged_in is true");
        return true;
    }

    if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        error_log("isLoggedIn(): TRUE - username found: " . $_SESSION['username']);
        return true;
    }

    if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
        error_log("isLoggedIn(): TRUE - user_role found: " . $_SESSION['user_role']);
        return true;
    }

    error_log("isLoggedIn(): FALSE - no session variables found");
    error_log("Available session variables: " . print_r($_SESSION, true));
    return false;
}

/**
 * Check if the current user has a specific role
 * @param string $role The role to check (e.g., 'admin', 'technician', 'user')
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role)
{
    // First check if user is logged in
    if (!isLoggedIn()) {
        return false;
    }

    // Check for user role in session - try different possible session variable names
    $userRole = null;

    if (isset($_SESSION['user_role'])) {
        $userRole = $_SESSION['user_role'];
    } elseif (isset($_SESSION['role'])) {
        $userRole = $_SESSION['role'];
    } elseif (isset($_SESSION['user']['role'])) {
        $userRole = $_SESSION['user']['role'];
    }

    // If no role found, return false
    if (empty($userRole)) {
        error_log("hasRole(): No role found in session");
        return false;
    }

    // Handle if role is stored as an array (multiple roles)
    if (is_array($userRole)) {
        return in_array(strtolower($role), array_map('strtolower', $userRole));
    }

    // Simple string comparison (case-insensitive)
    return strtolower($userRole) === strtolower($role);
}

/**
 * Check if current user is an admin
 * @return bool True if user is admin
 */
function isAdmin()
{
    return hasRole('admin');
}

/**
 * Check if user has any of the given roles
 * @param array $roles Array of roles to check
 * @return bool True if user has any of the roles
 */
function hasAnyRole($roles)
{
    if (!isLoggedIn()) {
        return false;
    }

    foreach ($roles as $role) {
        if (hasRole($role)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has all of the given roles
 * @param array $roles Array of roles to check
 * @return bool True if user has all roles
 */
function hasAllRoles($roles)
{
    if (!isLoggedIn()) {
        return false;
    }

    foreach ($roles as $role) {
        if (!hasRole($role)) {
            return false;
        }
    }
    return true;
}

/**
 * Redirect if user doesn't have permission
 * @param array|string $requiredRoles Role or array of roles required
 * @param string $redirectTo Page to redirect to if not authorized
 */
function requireRole($requiredRoles, $redirectTo = 'dashboard_sections.php')
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    $roles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];

    if (!hasAnyRole($roles)) {
        $_SESSION['toast_message'] = 'You do not have permission to access this page';
        $_SESSION['toast_type'] = 'error';
        header('Location: ' . $redirectTo);
        exit();
    }
}

/**
 * Get role display name
 * @param string $role Role key
 * @return string Role display name
 */
function getRoleDisplayName($role)
{
    $roleNames = [
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'user' => 'User',
        'stock_manager' => 'Stock Manager',
        'stock_controller' => 'Stock Controller',
        'tech_lead' => 'Tech Lead',
        'technician' => 'Technician',
        'driver' => 'Driver'
    ];

    return $roleNames[$role] ?? ucfirst($role);
}


/**
 * Check if current user is a technician
 * @return bool True if user is technician
 */
function isTechnician()
{
    return hasRole('technician');
}

/**
 * Get current user's role
 * @return string|null The user's role or null if not set
 */
/**
 * Get current user's role
 * @return string|null The user's role or null if not set
 */
/**
 * Get current user's role
 * @return string|null The user's role or null if not set
 */
function getUserRole()
{
    if (!isLoggedIn()) {
        return null;
    }

    // Check for role in session - try different possible variable names
    if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }

    if (isset($_SESSION['role']) && !empty($_SESSION['role'])) {
        return $_SESSION['role'];
    }

    if (isset($_SESSION['user']['role']) && !empty($_SESSION['user']['role'])) {
        return $_SESSION['user']['role'];
    }

    // If no role found in session, try to get from database
    if (isset($_SESSION['user_id'])) {
        $connection = null;
        if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
            $connection = $GLOBALS['conn'];
        } else {
            if (file_exists(__DIR__ . '/db_connect.php')) {
                require_once __DIR__ . '/db_connect.php';
                if (function_exists('getConnection')) {
                    $connection = getConnection();
                }
            }
        }

        if ($connection) {
            $stmt = $connection->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $_SESSION['role'] = $row['role']; // Cache it back to session
                return $row['role'];
            }
            $stmt->close();
        }
    }

    return null;
}


/**
 * Get all roles for the current user
 * @return array Array of role names
 */
function getUserRoles()
{
    if (!isLoggedIn()) {
        return [];
    }

    $user_id = $_SESSION['user_id'];
    $roles = [];

    // Get database connection
    static $connection = null;
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        $connection = $GLOBALS['conn'];
    } elseif (!$connection) {
        if (file_exists(__DIR__ . '/db_connect.php')) {
            require_once __DIR__ . '/db_connect.php';
            if (function_exists('getConnection')) {
                $connection = getConnection();
            }
        }
    }

    if (!$connection) {
        return [];
    }

    // Get primary role from users table
    $stmt = $connection->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['role']) {
            $roles[] = $row['role'];
        }
    }
    $stmt->close();

    // Get additional roles from user_roles table
    $stmt = $connection->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['role'], $roles)) {
            $roles[] = $row['role'];
        }
    }
    $stmt->close();

    return $roles;
}

/**
 * Get number of accessible pages for current user
 * @return array ['total' => total pages, 'accessible' => accessible count, 'percentage' => percentage]
 */
/**
 * Get number of accessible pages for current user
 * @return array ['total' => total pages, 'accessible' => accessible count, 'percentage' => percentage]
 */
/**
 * Get number of accessible pages for current user
 * @return array ['total' => total pages, 'accessible' => accessible count, 'percentage' => percentage]
 */
function getUserAccessStats()
{
    // Define all possible modules/pages
    $all_modules = [
        'view_dashboard',
        'view_events',
        'view_equipment',
        'add_equipment',
        'edit_equipment',
        'delete_equipment',
        'import_export',
        'scan_single',
        'scan_bulk',
        'view_scan_history',
        'view_reports',
        'manage_technicians',
        'manage_stock_locations',
        'view_batch_history',
        'view_accessories',
        'manage_users',
        'view_users',
        'manage_permissions',
        'manage_settings',
        'manage_events',
        'view_items',
        'manage_items'
    ];

    $total_pages = count($all_modules);
    $accessible_pages = 0;

    // Count how many modules the user actually has permission for
    foreach ($all_modules as $permission) {
        if (hasPermission($permission)) {
            $accessible_pages++;
        }
    }

    // Calculate percentage
    $percentage = $total_pages > 0 ? round(($accessible_pages / $total_pages) * 100) : 0;

    return [
        'total' => $total_pages,
        'accessible' => $accessible_pages,
        'percentage' => $percentage
    ];
}


/**
 * Get role icon and message
 * @return array ['icon' => fontawesome class, 'message' => display message]
 */
function getRoleDisplayInfo()
{
    if (isAdmin()) {
        return ['icon' => 'fa-crown', 'message' => 'Full Access Granted'];
    } elseif (hasRole('manager')) {
        return ['icon' => 'fa-chart-pie', 'message' => 'Manager Access Granted'];
    } elseif (hasRole('stock_manager')) {
        return ['icon' => 'fa-boxes-packing', 'message' => 'Stock Manager Access Granted'];
    } elseif (hasRole('stock_controller')) {
        return ['icon' => 'fa-chart-gantt', 'message' => 'Stock Controller Access Granted'];
    } elseif (hasRole('tech_lead')) {
        return ['icon' => 'fa-laptop-code', 'message' => 'Tech-Lead Access Granted'];
    } elseif (hasRole('technician')) {
        return ['icon' => 'fa-screwdriver-wrench', 'message' => 'Tech Access Granted'];
    } elseif (hasRole('user')) {
        return ['icon' => 'fa-user-check', 'message' => 'Basic Access Granted'];
    } elseif (hasRole('driver')) {
        return ['icon' => 'fa-van-shuttle', 'message' => 'Driver Access Granted'];
    } else {
        return ['icon' => 'fa-lock-open', 'message' => 'Access Granted'];
    }
}


/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_equipment')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
/**
 * Check if current user has a specific permission
 * @param string $permission_name The permission name (e.g., 'view_dashboard')
 * @return bool True if user has permission
 */
function hasPermission($permission_name)
{
    // Step 1: Check if user is logged in
    if (!isLoggedIn()) {
        error_log("hasPermission($permission_name): User not logged in");
        return false;
    }

    // Step 2: Get user's role
    $user_role = getUserRole();

    if (empty($user_role)) {
        error_log("hasPermission($permission_name): No role found");
        return false;
    }

    // Step 3: Admin always has all permissions
    if ($user_role === 'admin') {
        return true;
    }

    // Step 4: Get database connection
    $connection = null;
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        $connection = $GLOBALS['conn'];
    } else {
        if (file_exists(__DIR__ . '/db_connect.php')) {
            require_once __DIR__ . '/db_connect.php';
            if (function_exists('getConnection')) {
                $connection = getConnection();
            }
        }
    }

    if (!$connection) {
        error_log("hasPermission($permission_name): No database connection");
        return false;
    }

    // Step 5: DIRECT QUERY - Join role_permissions with permissions table
    $query = "
        SELECT 1 
        FROM role_permissions rp
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role = ? AND p.name = ?
        LIMIT 1
    ";

    $stmt = $connection->prepare($query);
    if (!$stmt) {
        error_log("hasPermission($permission_name) prepare error: " . $connection->error);
        return false;
    }

    $stmt->bind_param("ss", $user_role, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();

    $hasPermission = $result->num_rows > 0;
    $stmt->close();

    error_log("hasPermission($permission_name) for role $user_role: " . ($hasPermission ? 'GRANTED' : 'DENIED'));

    return $hasPermission;
}


/**
 * Debug function to check technician permissions
 * Use this in your dashboard_full.php file when needed
 */
function debugTechnicianPermissions()
{
    // Only run if user is technician and we're in debug mode
    if (!isLoggedIn() || getUserRole() !== 'technician') {
        return;
    }

    global $conn;

    echo "<div style='background: #f8f9fa; padding: 15px; margin: 15px; border-left: 4px solid #ffc107;'>";
    echo "<h5>🔍 Technician Permission Debug</h5>";

    // Test specific permissions
    $test_perms = [
        'view_dashboard',
        'view_events',
        'view_equipment',
        'scan_single',
        'scan_bulk',
        'view_scan_history',
        'view_reports',
        'manage_technicians'
    ];

    foreach ($test_perms as $perm) {
        $result = hasPermission($perm);
        echo "<div>";
        echo "<strong>$perm</strong>: " . ($result ? '✅ YES' : '❌ NO');

        // If false, check why
        if (!$result) {
            // Check if permission exists in database
            $check = $conn->query("SELECT id FROM permissions WHERE name = '$perm'");
            if ($check && $check->num_rows > 0) {
                $perm_id = $check->fetch_assoc()['id'];
                $role_check = $conn->query("SELECT id FROM role_permissions WHERE role = 'technician' AND permission_id = $perm_id");
                echo " - Permission exists (ID: $perm_id) | Role has it: " . ($role_check && $role_check->num_rows > 0 ? '✅' : '❌');
            } else {
                echo " - ❌ Permission '$perm' not found in permissions table!";
            }
        }
        echo "</div>";
    }

    // Show all permissions for technician from database
    echo "<h6 class='mt-3'>All technician permissions in DB:</h6>";
    $tech_perms = $conn->query("
        SELECT p.name, p.display_name 
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role = 'technician'
    ");

    if ($tech_perms && $tech_perms->num_rows > 0) {
        echo "<ul>";
        while ($row = $tech_perms->fetch_assoc()) {
            echo "<li>✅ " . $row['name'] . " - " . $row['display_name'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='text-danger'>❌ No permissions found for technician role!</p>";
    }

    echo "</div>";
}


/**
 * DEBUG FUNCTION: Test and display permission status
 * @param string $permission_name The permission name to test
 * @return bool The permission result
 */
function debugHasPermission($permission_name)
{
    $result = hasPermission($permission_name);
    $role = getUserRole();
    $color = $result ? 'green' : 'red';
    $icon = $result ? '✅' : '❌';

    echo "<div style='background: #f8f9fa; padding: 8px; margin: 4px; border-left: 4px solid $color; font-family: monospace;'>";
    echo "$icon <strong style='color: $color;'>" . ($result ? 'GRANTED' : 'DENIED') . "</strong> - ";
    echo "Permission: <code>$permission_name</code> | ";
    echo "Role: <code>$role</code>";

    // If denied, show additional debug info
    if (!$result && $role !== 'admin') {
        // Check if the permission exists in the database
        global $conn;
        if (isset($conn)) {
            $check_perm = $conn->query("SELECT id FROM permissions WHERE name = '$permission_name'");
            if ($check_perm && $check_perm->num_rows > 0) {
                $perm_id = $check_perm->fetch_assoc()['id'];
                $check_role = $conn->query("SELECT id FROM role_permissions WHERE role = '$role' AND permission_id = $perm_id");
                echo "<br><small style='color: #666; margin-left: 20px;'>";
                echo "Permission exists in DB (ID: $perm_id) | ";
                echo "Role has it: " . ($check_role && $check_role->num_rows > 0 ? '✅ YES' : '❌ NO');
                echo "</small>";
            } else {
                echo "<br><small style='color: #f00; margin-left: 20px;'>⚠️ Permission '$permission_name' not found in permissions table!</small>";
            }
        }
    }

    echo "</div>";

    return $result;
}


/**
 * Get user's full name from session or database
 * @return string User's full name
 */
function getUserFullName()
{
    // Check if full_name exists in session
    if (isset($_SESSION['full_name']) && !empty($_SESSION['full_name'])) {
        return $_SESSION['full_name'];
    }

    // Fall back to username
    if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        return $_SESSION['username'];
    }

    // Try to fetch from database if user_id exists
    if (isset($_SESSION['user_id'])) {
        global $conn;
        if (isset($conn)) {
            $stmt = $conn->prepare("SELECT full_name, username FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $full_name = $row['full_name'] ?? $row['username'];
                $_SESSION['full_name'] = $full_name; // Cache it
                return $full_name;
            }
            $stmt->close();
        }
    }

    return 'User';
}


/**
 * Log user activity
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $description Description of the action
 * @return bool True on success, false on failure
 */
function logActivity($conn, $user_id, $action, $description = '')
{
    try {
        // Check if activity_log table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'activity_log'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            // Create activity_log table if it doesn't exist
            $createTable = "CREATE TABLE IF NOT EXISTS activity_log (
                id INT(11) PRIMARY KEY AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                action VARCHAR(50) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            )";

            if (!$conn->query($createTable)) {
                error_log("Failed to create activity_log table: " . $conn->error);
                return false;
            }
        }

        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

        // Insert activity log
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");

        if (!$stmt) {
            error_log("Failed to prepare activity log statement: " . $conn->error);
            return false;
        }

        $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}



/**
 * Get condition badge HTML
 */
function getConditionBadge($condition)
{
    $conditions = [
        'excellent' => ['label' => 'Excellent', 'class' => 'bg-success'],
        'good'      => ['label' => 'Good', 'class' => 'bg-primary'],
        'fair'      => ['label' => 'Fair', 'class' => 'bg-info'],
        'poor'      => ['label' => 'Poor', 'class' => 'bg-warning'],
        'broken'    => ['label' => 'Broken', 'class' => 'bg-danger'],
        'repair'    => ['label' => 'Needs Repair', 'class' => 'bg-dark'],
        'new'       => ['label' => 'New', 'class' => 'bg-success'],
        'damaged'   => ['label' => 'Damaged', 'class' => 'bg-danger']
    ];

    if (isset($conditions[$condition])) {
        $badge = $conditions[$condition];
        return '<span class="badge ' . $badge['class'] . '">' . $badge['label'] . '</span>';
    }

    return '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($condition)) . '</span>';
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status)
{
    $statuses = [
        'available'     => ['label' => 'Available', 'class' => 'bg-success'],
        'in_use'        => ['label' => 'In Use', 'class' => 'bg-primary'],
        'maintenance'   => ['label' => 'Maintenance', 'class' => 'bg-warning'],
        'reserved'      => ['label' => 'Reserved', 'class' => 'bg-info'],
        'disposed'      => ['label' => 'Disposed', 'class' => 'bg-secondary'],
        'lost'          => ['label' => 'Lost', 'class' => 'bg-danger'],
        'retired'       => ['label' => 'Retired', 'class' => 'bg-dark']
    ];

    if (isset($statuses[$status])) {
        $badge = $statuses[$status];
        return '<span class="badge ' . $badge['class'] . '">' . $badge['label'] . '</span>';
    }

    return '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>';
}

/**
 * Get category badge HTML
 */
function getCategoryBadge($category)
{
    $colors = [
        'Audio'         => 'primary',
        'Video'         => 'success',
        'Lighting'      => 'warning',
        'Translation'   => 'info',
        'IT'            => 'danger',
        'Rigging'       => 'secondary',
        'Electrical'    => 'dark',
        'Furniture'     => 'purple',
        'Other'         => 'light'
    ];

    $color = isset($colors[$category]) ? $colors[$category] : 'secondary';
    return '<span class="badge bg-' . $color . '">' . htmlspecialchars($category) . '</span>';
}

// Get item accessories
function getItemAccessories($item_id, $conn)
{
    $accessories = [];

    try {
        // Check if item_accessories table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'item_accessories'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return $accessories; // Return empty array if table doesn't exist
        }

        // Use a simpler query to avoid SQL errors
        $stmt = $conn->prepare("
            SELECT a.id, a.name, a.description 
            FROM accessories a
            INNER JOIN item_accessories ia ON a.id = ia.accessory_id 
            WHERE ia.item_id = ? AND a.is_active = 1
        ");

        // Debug: Check if prepare was successful
        if (!$stmt) {
            error_log("SQL prepare error: " . $conn->error);
            return $accessories;
        }

        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $accessories[] = $row;
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Error in getItemAccessories: " . $e->getMessage());
        // Return empty array on error
    }

    return $accessories;
}

// Get accessory badge
function getAccessoryBadge($accessory_name)
{
    if (empty($accessory_name)) {
        return '<span class="badge bg-secondary">None</span>';
    }

    $accessoryColors = [
        'power cable' => 'danger',
        'hdmi cable' => 'info',
        'usb cable' => 'success',
        'remote' => 'warning',
        'stand' => 'dark',
        'case' => 'secondary',
        'battery' => 'danger',
        'adapter' => 'primary',
        'ethernet' => 'info',
        'manual' => 'light text-dark',
        'warranty' => 'success',
        'screws' => 'secondary',
        'lens' => 'info',
        'memory' => 'primary',
        'stylus' => 'warning',
        'dongle' => 'info',
        'microphone' => 'success',
        'tripod' => 'dark',
        'other' => 'secondary'
    ];

    $color = 'secondary';
    foreach ($accessoryColors as $key => $col) {
        if (stripos($accessory_name, $key) !== false) {
            $color = $col;
            break;
        }
    }

    return '<span class="badge bg-' . $color . '">' . htmlspecialchars($accessory_name) . '</span>';
}

function formatDate($date, $format = 'M d, Y H:i')
{
    if (empty($date) || $date == '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($date));
}

// Add this function for low stock accessories
function getLowStockAccessories($conn, $limit = 10)
{
    $low_stock = [];

    try {
        // First check if the accessories table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'accessories'");
        if ($checkTable->num_rows === 0) {
            error_log("Accessories table does not exist");
            return $low_stock;
        }

        $sql = "
            SELECT 
                a.id,
                a.name,
                a.description,
                a.available_quantity,
                a.minimum_stock,
                a.total_quantity,
                COUNT(DISTINCT ia.item_id) as assigned_count
            FROM accessories a
            LEFT JOIN item_accessories ia ON a.id = ia.accessory_id
            WHERE a.is_active = 1 
            AND a.available_quantity <= a.minimum_stock
            GROUP BY a.id
            ORDER BY a.available_quantity ASC
            LIMIT ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $low_stock = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting low stock accessories: " . $e->getMessage());
    }

    return $low_stock;
}

// Add this function for stock alerts
function getStockAlerts($conn)
{
    $alerts = [
        'low_stock_count' => 0,
        'out_of_stock_count' => 0,
        'low_stock_items' => []
    ];

    try {
        // First check if the accessories table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'accessories'");
        if ($checkTable->num_rows === 0) {
            error_log("Accessories table does not exist");
            return $alerts;
        }

        // Get low stock count
        $sql = "
            SELECT 
                COUNT(*) as low_stock,
                SUM(CASE WHEN available_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
            FROM accessories 
            WHERE is_active = 1 
            AND available_quantity <= minimum_stock
        ";

        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $alerts['low_stock_count'] = $row['low_stock'];
            $alerts['out_of_stock_count'] = $row['out_of_stock'];
        }

        // Get top 5 low stock items
        $lowStmt = $conn->prepare("
            SELECT name, available_quantity, minimum_stock
            FROM accessories
            WHERE is_active = 1 
            AND available_quantity <= minimum_stock
            ORDER BY available_quantity ASC
            LIMIT 5
        ");

        if ($lowStmt) {
            $lowStmt->execute();
            $lowResult = $lowStmt->get_result();
            $alerts['low_stock_items'] = $lowResult->fetch_all(MYSQLI_ASSOC);
            $lowStmt->close();
        }
    } catch (Exception $e) {
        error_log("Error getting stock alerts: " . $e->getMessage());
    }

    return $alerts;
}

function getCategories()
{
    return [
        'Audio'         => 'Audio Equipment',
        'Video'         => 'Video Equipment',
        'Lighting'      => 'Lighting Equipment',
        'Translation'   => 'Translation Equipment',
        'IT'            => 'IT Equipment',
        'Rigging'       => 'Rigging Equipment',
        'Electrical'    => 'Electrical Equipment',
        'Furniture'     => 'Furniture',
        'Other'         => 'Other'
    ];
}

function getDepartments()
{
    return [
        'AUD'         => 'Audio',
        'VID'         => 'Video',
        'LIGT'      => 'Lighting',
        'TRN'   => 'Translation',
        'IT'            => 'IT',
        'RIG'       => 'Rigging',
        'ELECTR'    => 'Electrical',
        'FURNT'     => 'Furniture',
    ];
}

function getLocations()
{
    return [
        'BK Arena'      => 'BK Arena',
        'Ndera'         => 'Ndera',
        'Masoro'        => 'Masoro',
        'KCC'           => 'KCC',
        'Warehouse A'   => 'Warehouse A',
        'Warehouse B'   => 'Warehouse B',
        'On Site'       => 'On Site',
        'In Transit'    => 'In Transit'
    ];
}

function getConditions()
{
    return [
        'new'       => 'New',
        'good'      => 'Good',
        'fair'      => 'Fair',
        'poor'      => 'Poor',
        'damaged'   => 'Damaged',
        'repair'    => 'Needs Repair',
        'excellent' => 'Excellent',
        'broken'    => 'Broken'
    ];
}

function getStatuses()
{
    return [
        'available'     => 'Available',
        'in_use'        => 'In Use',
        'reserved'      => 'Reserved',
        'maintenance'   => 'Maintenance',
        'disposed'      => 'Disposed',
        'lost'          => 'Lost',
        'retired'       => 'Retired'
    ];
}

// Check if user is logged in (for API endpoints)
function requireApiAuthentication()
{
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required',
            'redirect' => BASE_URL . 'login.php'
        ]);
        exit();
    }
}

// Add this to your functions.php if not already there
function redirect($url, $statusCode = 303)
{
    header('Location: ' . $url, true, $statusCode);
    exit();
}

// Get dashboard statistics
function getDashboardStats($db)
{
    $stats = [
        'total_items'   => 0,
        'available'     => 0,
        'in_use'        => 0,
        'maintenance'   => 0,
        'categories'    => 0,
        'reserved'      => 0,
        'disposed'      => 0,
        'lost'          => 0
    ];

    try {
        // Total items
        $result = $db->query("SELECT COUNT(*) as count FROM items");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_items'] = $row['count'] ?? 0;
        }

        // Available items
        $result = $db->query("SELECT COUNT(*) as count FROM items WHERE status = 'available'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['available'] = $row['count'] ?? 0;
        }

        // In use items
        $result = $db->query("SELECT COUNT(*) as count FROM items WHERE status = 'in_use'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['in_use'] = $row['count'] ?? 0;
        }

        // Maintenance items
        $result = $db->query("SELECT COUNT(*) as count FROM items WHERE status = 'maintenance'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['maintenance'] = $row['count'] ?? 0;
        }

        // Distinct categories
        $result = $db->query("SELECT COUNT(DISTINCT category) as count FROM items");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['categories'] = $row['count'] ?? 0;
        }

        // Reserved items
        $result = $db->query("SELECT COUNT(*) as count FROM items WHERE status = 'reserved'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['reserved'] = $row['count'] ?? 0;
        }

        // Disposed items
        $result = $db->query("SELECT COUNT(*) as count FROM items WHERE status = 'disposed'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['disposed'] = $row['count'] ?? 0;
        }

        // Lost items
        $result = $db->query("SELECT COUNT(*) as count FROM items WHERE status = 'lost'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['lost'] = $row['count'] ?? 0;
        }

        return $stats;
    } catch (Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        return $stats;
    }
}

// Get recent items
function getRecentItems($conn, $limit = 10)
{
    $items = [];

    // Try multiple query approaches
    $queries = [
        // Try with joins (Primary approach)
        "SELECT i.*, 
                c.name as category_name, 
                d.name as department_name,
                i.category as category_id,
                i.department as department_id
         FROM items i
         LEFT JOIN categories c ON i.category = c.id
         LEFT JOIN departments d ON i.department = d.id OR i.department = d.code
         WHERE 1=1 
         ORDER BY i.created_at DESC 
         LIMIT $limit",

        // Try without departments join
        "SELECT i.*, c.name as category 
         FROM items i
         LEFT JOIN categories c ON i.category_id = c.id
         WHERE i.is_active = 1 
         ORDER BY i.created_at DESC 
         LIMIT $limit",

        // Try simplest
        "SELECT * FROM items 
         WHERE is_active = 1 
         ORDER BY created_at DESC 
         LIMIT $limit",

        // Try without created_at
        "SELECT * FROM items 
         WHERE is_active = 1 
         ORDER BY id DESC 
         LIMIT $limit",

        // Last resort
        "SELECT * FROM items LIMIT $limit"
    ];

    foreach ($queries as $sql) {
        try {
            $result = $conn->query($sql);
            if ($result) {
                $items = $result->fetch_all(MYSQLI_ASSOC);
                $result->free();
                if (!empty($items)) {
                    error_log("Success with query: " . substr($sql, 0, 50) . "...");
                    break;
                }
            }
        } catch (Exception $e) {
            error_log("Query failed: " . $e->getMessage());
            continue;
        }
    }

    return $items;
}

/**
 * Check if serial number exists
 */
function serialExists($serial_number, $conn)
{
    try {
        $stmt = $conn->prepare("SELECT id FROM items WHERE serial_number = ?");
        $stmt->bind_param("s", $serial_number);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    } catch (Exception $e) {
        error_log("Error checking serial: " . $e->getMessage());
        return false;
    }
}

// Function to get total quantity per item name (aggregating all serial numbers)
function getTotalQuantityPerItem($conn, $item_name = null)
{
    if ($item_name) {
        $stmt = $conn->prepare("
            SELECT 
                item_name,
                COUNT(*) as unique_serial_numbers,
                SUM(quantity) as total_units,
                category,
                brand_model,
                GROUP_CONCAT(serial_number SEPARATOR ', ') as serial_numbers
            FROM items 
            WHERE item_name = ?
            GROUP BY item_name, brand_model, category
        ");
        $stmt->bind_param("s", $item_name);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    } else {
        // Get all items grouped by name
        return $conn->query("
            SELECT 
                item_name,
                category,
                brand_model,
                COUNT(*) as unique_serial_numbers,
                SUM(quantity) as total_units,
                MIN(created_at) as first_added,
                MAX(updated_at) as last_updated
            FROM items 
            GROUP BY item_name, brand_model, category
            ORDER BY total_units DESC, item_name
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

// Export equipment data to CSV
function exportToCSV($data, $filename = 'equipment_export.csv')
{
    if (empty($data)) {
        return false;
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // Add header
    fputcsv($output, array_keys($data[0]));

    // Add data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

// Calculate depreciation
function calculateDepreciation($purchasePrice, $purchaseDate, $lifespanYears = 5, $depreciationMethod = 'straight')
{
    if (empty($purchaseDate) || $purchasePrice <= 0) {
        return $purchasePrice;
    }

    $purchaseTimestamp = strtotime($purchaseDate);
    $currentTimestamp = time();

    if ($purchaseTimestamp === false || $purchaseTimestamp > $currentTimestamp) {
        return $purchasePrice;
    }

    $secondsInYear = 365 * 24 * 60 * 60;
    $yearsUsed = ($currentTimestamp - $purchaseTimestamp) / $secondsInYear;

    if ($depreciationMethod === 'straight') {
        $annualDepreciation = $purchasePrice / $lifespanYears;
        $totalDepreciation = $annualDepreciation * min($yearsUsed, $lifespanYears);
        $currentValue = $purchasePrice - $totalDepreciation;
    } else {
        // Double declining balance method
        $rate = 2 / $lifespanYears;
        $currentValue = $purchasePrice;
        for ($i = 0; $i < min($yearsUsed, $lifespanYears); $i++) {
            $currentValue -= $currentValue * $rate;
        }
    }

    return max(0, round($currentValue, 2));
}

// Generate next equipment code
function generateNextEquipmentCode($db)
{
    try {
        $sql = "SELECT MAX(id) as max_id FROM items";
        $result = $db->query($sql);

        if ($result) {
            $row = $result->fetch_assoc();
            $nextId = ($row['max_id'] ?? 0) + 1;
            return 'EQ-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {
        error_log("Equipment code error: " . $e->getMessage());
        return 'EQ-' . date('Ymd') . rand(100, 999);
    }
}

// Get equipment by ID
function getEquipmentById($db, $id)
{
    try {
        $sql = "SELECT * FROM items WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row;
    } catch (Exception $e) {
        error_log("Get equipment error: " . $e->getMessage());
        return null;
    }
}

// Search equipment
function searchEquipment($db, $searchTerm, $category = '', $status = '', $location = '')
{
    $items = [];

    try {
        $sql = "SELECT * FROM items WHERE 1=1";
        $params = [];
        $types = '';

        if (!empty($searchTerm)) {
            $sql .= " AND (item_name LIKE ? OR serial_number LIKE ? OR description LIKE ? OR brand_model LIKE ?)";
            $searchTerm = "%$searchTerm%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }

        if (!empty($category)) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= 's';
        }

        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if (!empty($location)) {
            $sql .= " AND stock_location = ?";
            $params[] = $location;
            $types .= 's';
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        $stmt->close();
        return $items;
    } catch (Exception $e) {
        error_log("Search equipment error: " . $e->getMessage());
        return [];
    }
}

// Get equipment count by status
function getEquipmentCountByStatus($db)
{
    $counts = [
        'available'     => 0,
        'in_use'        => 0,
        'maintenance'   => 0,
        'reserved'      => 0,
        'disposed'      => 0,
        'lost'          => 0,
        'retired'       => 0
    ];

    try {
        $sql = "SELECT status, COUNT(*) as count FROM items GROUP BY status";
        $result = $db->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $status = $row['status'];
                if (isset($counts[$status])) {
                    $counts[$status] = $row['count'];
                }
            }
        }

        return $counts;
    } catch (Exception $e) {
        error_log("Count by status error: " . $e->getMessage());
        return $counts;
    }
}

// Get equipment count by category
function getEquipmentCountByCategory($db)
{
    $counts = [];

    try {
        $sql = "SELECT category, COUNT(*) as count FROM items GROUP BY category ORDER BY count DESC";
        $result = $db->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $counts[$row['category']] = $row['count'];
            }
        }

        return $counts;
    } catch (Exception $e) {
        error_log("Count by category error: " . $e->getMessage());
        return $counts;
    }
}

// Get equipment count by location
function getEquipmentCountByLocation($db)
{
    $counts = [];

    try {
        $sql = "SELECT stock_location, COUNT(*) as count FROM items GROUP BY stock_location ORDER BY count DESC";
        $result = $db->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $counts[$row['stock_location']] = $row['count'];
            }
        }

        return $counts;
    } catch (Exception $e) {
        error_log("Count by location error: " . $e->getMessage());
        return $counts;
    }
}

// Get all items with all fields
function getAllItems($db, $limit = null, $offset = 0)
{
    $items = [];

    try {
        $sql = "SELECT 
                    id, item_name, serial_number, category, department, 
                    description, brand_model, `condition`, stock_location, 
                    notes, quantity, status, image, qr_code,
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                    DATE_FORMAT(updated_at, '%Y-%m-d %H:%i:%s') as updated_at
                FROM items 
                ORDER BY created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ii', $limit, $offset);
        } else {
            $stmt = $db->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        $stmt->close();
        return $items;
    } catch (Exception $e) {
        error_log("Get all items error: " . $e->getMessage());
        return [];
    }
}

// Get item details for view page
function getItemDetails($db, $id)
{
    try {
        $sql = "SELECT 
                    id, item_name, serial_number, category, department, 
                    description, brand_model, `condition`, stock_location, 
                    notes, quantity, status, image, qr_code,
                    DATE_FORMAT(created_at, '%Y-%m-d %H:%i:%s') as created_at,
                    DATE_FORMAT(updated_at, '%Y-%m-d %H:%i:%s') as updated_at
                FROM items 
                WHERE id = ?";

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        return $item;
    } catch (Exception $e) {
        error_log("Get item details error: " . $e->getMessage());
        return null;
    }
}

// Generate unique serial number
function generateUniqueSerial($db, $prefix = 'EQ')
{
    try {
        // Try to get the next ID
        $sql = "SELECT MAX(id) as max_id FROM items";
        $result = $db->query($sql);

        if ($result) {
            $row = $result->fetch_assoc();
            $nextId = ($row['max_id'] ?? 0) + 1;

            // Generate serial with prefix and timestamp
            $timestamp = time();
            $serial = $prefix . '-' . date('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            // Check if serial exists
            $checkStmt = $db->prepare("SELECT id FROM items WHERE serial_number = ?");
            $checkStmt->bind_param('s', $serial);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                // If exists, add random number
                $serial = $serial . '-' . rand(100, 999);
            }

            $checkStmt->close();
            return $serial;
        }
    } catch (Exception $e) {
        error_log("Generate serial error: " . $e->getMessage());
    }

    // Fallback
    return $prefix . '-' . date('YmdHis') . '-' . rand(1000, 9999);
}

/**
 * Generate single QR code
 */
function generateSingleQRCode($item_id, $item_name, $serial_number, $conn = null)
{
    try {
        // Use the same logic as generate_all_qr_codes.php
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];

        $data = json_encode([
            'id' => $item_id,
            'name' => $item_name,
            'serial' => $serial_number,
            'system' => 'aBility Inventory',
            'url' => $protocol . "://" . $host . '/items/view.php?id=' . $item_id,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Create QR directory if it doesn't exist
        $qrDir = '../uploads/qr_codes/';
        if (!file_exists($qrDir)) {
            if (!mkdir($qrDir, 0777, true)) {
                throw new Exception("Failed to create QR directory");
            }
        }

        // Generate filename
        $cleanName = preg_replace('/[^a-z0-9]/i', '_', $item_name);
        $cleanSerial = preg_replace('/[^a-z0-9]/i', '_', $serial_number);
        $filename = 'qr_' . $item_id . '_' . $cleanName . '_' . $cleanSerial . '.png';
        $filepath = $qrDir . $filename;
        $qrDbPath = 'uploads/qr_codes/' . $filename;

        // Try to generate QR using external API
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: aBility-Inventory/1.0\r\n",
                'ignore_errors' => true
            ]
        ]);

        // Try QRServer API first
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($data);
        $qrImage = @file_get_contents($qrUrl, false, $context);

        if ($qrImage === false) {
            // Fallback to Google Charts
            $qrUrl = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($data);
            $qrImage = @file_get_contents($qrUrl, false, $context);
        }

        if ($qrImage !== false && file_put_contents($filepath, $qrImage) !== false) {
            return $qrDbPath;
        }

        throw new Exception("Failed to generate QR code image");
    } catch (Exception $e) {
        error_log("Single QR generation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if QR code generation is working
 */
function checkQRCodeAPIs()
{
    $testData = 'Test QR Code - aBility Inventory';
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $apis = [
        'QRServer' => 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($testData),
        'GoogleCharts' => 'https://chart.googleapis.com/chart?chs=100x100&cht=qr&chl=' . urlencode($testData)
    ];

    $results = [];
    foreach ($apis as $name => $url) {
        try {
            $response = @file_get_contents($url, false, $context);
            $results[$name] = ($response !== false && strlen($response) > 100);
        } catch (Exception $e) {
            $results[$name] = false;
        }
    }

    return $results;
}

/**
 * Get QR code statistics
 */
function getQRCodeStats($conn)
{
    $stats = [
        'total_items' => 0,
        'with_qr' => 0,
        'without_qr' => 0,
        'pending' => 0,
        'invalid' => 0
    ];

    try {
        // Total items
        $result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status NOT IN ('disposed', 'lost')");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_items'] = $row['count'];
        }

        // Items with valid QR codes
        $result = $conn->query("SELECT COUNT(*) as count FROM items 
                               WHERE qr_code IS NOT NULL 
                               AND qr_code != '' 
                               AND qr_code != 'pending'
                               AND status NOT IN ('disposed', 'lost')");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['with_qr'] = $row['count'];
        }

        // Items without QR codes
        $result = $conn->query("SELECT COUNT(*) as count FROM items 
                               WHERE (qr_code IS NULL OR qr_code = '')
                               AND status NOT IN ('disposed', 'lost')");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['without_qr'] = $row['count'];
        }

        // Items with pending QR codes
        $result = $conn->query("SELECT COUNT(*) as count FROM items 
                               WHERE qr_code = 'pending'
                               AND status NOT IN ('disposed', 'lost')");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['pending'] = $row['count'];
        }

        // Check for invalid QR codes (files that don't exist)
        $result = $conn->query("SELECT id, qr_code FROM items 
                               WHERE qr_code IS NOT NULL 
                               AND qr_code != ''
                               AND qr_code != 'pending'
                               AND status NOT IN ('disposed', 'lost')");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $filePath = '../' . $row['qr_code'];
                if (!file_exists($filePath)) {
                    $stats['invalid']++;
                }
            }
        }
    } catch (Exception $e) {
        error_log("QR stats error: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Validate all QR codes in database
 */
function validateQRCodes($conn)
{
    $results = [
        'valid' => 0,
        'invalid' => 0,
        'missing_files' => []
    ];

    try {
        $query = "SELECT id, item_name, qr_code FROM items 
                  WHERE qr_code IS NOT NULL 
                  AND qr_code != '' 
                  AND qr_code != 'pending'";

        $result = $conn->query($query);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $filePath = '../' . $row['qr_code'];

                if (file_exists($filePath)) {
                    // Check if it's a valid image
                    $imageInfo = @getimagesize($filePath);
                    if ($imageInfo !== false && $imageInfo[0] > 0) {
                        $results['valid']++;
                    } else {
                        $results['invalid']++;
                        $results['missing_files'][] = [
                            'id' => $row['id'],
                            'name' => $row['item_name'],
                            'reason' => 'Invalid image file'
                        ];
                    }
                } else {
                    $results['invalid']++;
                    $results['missing_files'][] = [
                        'id' => $row['id'],
                        'name' => $row['item_name'],
                        'reason' => 'File not found'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("QR validation error: " . $e->getMessage());
    }

    return $results;
}

/**
 * Repair missing QR codes
 */
function repairQRCodes($conn, $limit = 50)
{
    $results = [
        'repaired' => 0,
        'failed' => 0,
        'errors' => []
    ];

    try {
        // Find items with missing QR files
        $query = "SELECT i.id, i.item_name, i.serial_number, i.qr_code 
                  FROM items i 
                  WHERE i.qr_code IS NOT NULL 
                  AND i.qr_code != '' 
                  AND i.qr_code != 'pending'
                  AND i.status NOT IN ('disposed', 'lost')
                  LIMIT ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $filePath = '../' . $row['qr_code'];

            if (!file_exists($filePath)) {
                // Try to regenerate QR code
                $newQrPath = generateSingleQRCode($row['id'], $row['item_name'], $row['serial_number'], $conn);

                if ($newQrPath) {
                    // Update database
                    $updateStmt = $conn->prepare("UPDATE items SET qr_code = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $newQrPath, $row['id']);

                    if ($updateStmt->execute()) {
                        $results['repaired']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to update database for item ID: " . $row['id'];
                    }

                    $updateStmt->close();
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to generate QR for item: " . $row['item_name'];
                }
            }
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("QR repair error: " . $e->getMessage());
        $results['errors'][] = $e->getMessage();
    }

    return $results;
}

/**
 * Download QR code for single item
 */
function downloadQRCode($item_id, $conn)
{
    try {
        $query = "SELECT item_name, serial_number, qr_code FROM items WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (!empty($row['qr_code']) && $row['qr_code'] != 'pending') {
                $filePath = '../' . $row['qr_code'];

                if (file_exists($filePath)) {
                    // Set headers for download
                    header('Content-Type: image/png');
                    header('Content-Disposition: attachment; filename="QR_' .
                        preg_replace('/[^a-z0-9]/i', '_', $row['item_name']) . '_' .
                        preg_replace('/[^a-z0-9]/i', '_', $row['serial_number']) . '.png"');
                    header('Content-Length: ' . filesize($filePath));

                    readfile($filePath);
                    exit;
                } else {
                    throw new Exception("QR code file not found");
                }
            } else {
                throw new Exception("No QR code available for this item");
            }
        } else {
            throw new Exception("Item not found");
        }
    } catch (Exception $e) {
        error_log("Download QR error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get batch QR code generation progress
 */
function getBatchQRProgress($batchId)
{
    $progressFile = '../uploads/temp/qr_progress_' . $batchId . '.json';

    if (file_exists($progressFile)) {
        $progressData = json_decode(file_get_contents($progressFile), true);

        if (isset($progressData['completed'])) {
            unlink($progressFile); // Clean up when done
        }

        return $progressData;
    }

    return [
        'status' => 'not_found',
        'message' => 'Progress data not found'
    ];
}

/**
 * Clean up old QR files
 */
function cleanupOldQRFiles($days = 30)
{
    $qrDir = '../uploads/qr_codes/';
    $tempDir = '../uploads/temp_qr_zip/';

    $deleted = 0;
    $errors = [];

    // Clean QR directory
    if (file_exists($qrDir)) {
        $files = glob($qrDir . '*.png');
        $cutoffTime = time() - ($days * 24 * 60 * 60);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                } else {
                    $errors[] = "Failed to delete: " . basename($file);
                }
            }
        }
    }

    // Clean temp ZIP directory
    if (file_exists($tempDir)) {
        $dirs = glob($tempDir . '*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                // Delete files in directory
                $tempFiles = glob($dir . '/*');
                foreach ($tempFiles as $tempFile) {
                    if (is_file($tempFile)) {
                        unlink($tempFile);
                    }
                }

                // Delete directory
                if (rmdir($dir)) {
                    $deleted++;
                } else {
                    $errors[] = "Failed to delete temp directory: " . basename($dir);
                }
            }
        }

        // Clean old ZIP files
        $zipFiles = glob($tempDir . '*.zip');
        foreach ($zipFiles as $zipFile) {
            if (filemtime($zipFile) < time() - (24 * 60 * 60)) { // 1 day
                if (unlink($zipFile)) {
                    $deleted++;
                } else {
                    $errors[] = "Failed to delete ZIP: " . basename($zipFile);
                }
            }
        }
    }

    return [
        'deleted' => $deleted,
        'errors' => $errors
    ];
}

/**
 * Test QR code generation (for debugging)
 */
function testQRGeneration()
{
    $results = [
        'api_test' => checkQRCodeAPIs(),
        'directory_permissions' => [
            'uploads' => is_writable('../uploads'),
            'qr_codes' => is_writable('../uploads/qr_codes/'),
            'temp_qr_zip' => is_writable('../uploads/temp_qr_zip/')
        ],
        'php_extensions' => [
            'gd' => extension_loaded('gd'),
            'zip' => class_exists('ZipArchive'),
            'curl' => function_exists('curl_init'),
            'json' => function_exists('json_encode')
        ],
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ];

    return $results;
}

/**
 * Get Excel column letters for dropdown
 */
function getExcelColumns($maxColumns = 26)
{
    $columns = [];
    for ($i = 1; $i <= $maxColumns; $i++) {
        $columns[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
    }
    return $columns;
}

/**
 * Create field mapping dropdown HTML
 */
function renderFieldMappingDropdown($fieldName, $label, $isRequired = false, $headers = [])
{
    $requiredStar = $isRequired ? ' <span class="text-danger">*</span>' : '';

    $html = '<div class="mb-3">';
    $html .= '<label for="' . htmlspecialchars($fieldName) . '" class="form-label">';
    $html .= htmlspecialchars($label) . $requiredStar . '</label>';
    $html .= '<select class="form-select" id="' . htmlspecialchars($fieldName) . '" ';
    $html .= 'name="field_mapping[' . htmlspecialchars($fieldName) . ']">';
    $html .= '<option value="">-- Select Column --</option>';

    foreach ($headers as $index => $header) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        $displayText = $colLetter . ': ' . htmlspecialchars($header);
        $html .= '<option value="' . $colLetter . '">' . $displayText . '</option>';
    }

    $html .= '</select>';
    $html .= '</div>';

    return $html;
}

/**
 * Quick CSV import for simple files
 */
function quickCSVImport($csvFile, $conn)
{
    $results = [
        'success' => 0,
        'errors' => [],
        'skipped' => 0
    ];

    try {
        $handle = fopen($csvFile, "r");
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }

        // Read header
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception("CSV file is empty or invalid");
        }

        // Simple mapping - assume first 3 columns are required
        $rowNum = 1;
        while (($data = fgetcsv($handle)) !== FALSE) {
            $rowNum++;

            try {
                // Basic validation - require at least 3 columns
                if (count($data) < 3) {
                    $results['skipped']++;
                    continue;
                }

                $itemData = [
                    'item_name' => $data[0] ?? '',
                    'serial_number' => $data[1] ?? '',
                    'category' => $data[2] ?? '',
                    'brand' => $data[3] ?? '',
                    'model' => $data[4] ?? '',
                    'department' => $data[5] ?? '',
                    'description' => $data[6] ?? '',
                    'condition' => $data[7] ?? 'good',
                    'stock_location' => $data[8] ?? '',
                    'quantity' => intval($data[9] ?? 1),
                    'status' => $data[10] ?? 'available',
                    'notes' => $data[11] ?? ''
                ];

                // Check if serial exists
                $checkSql = "SELECT id FROM items WHERE serial_number = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('s', $itemData['serial_number']);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    // Update
                    $updateSql = "UPDATE items SET 
                        item_name = ?, category = ?, brand = ?, model = ?, 
                        department = ?, description = ?, `condition` = ?, 
                        stock_location = ?, quantity = ?, status = ?, notes = ?,
                        updated_at = NOW()
                        WHERE serial_number = ?";

                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param(
                        'ssssssssisss',
                        $itemData['item_name'],
                        $itemData['category'],
                        $itemData['brand'],
                        $itemData['model'],
                        $itemData['department'],
                        $itemData['description'],
                        $itemData['condition'],
                        $itemData['stock_location'],
                        $itemData['quantity'],
                        $itemData['status'],
                        $itemData['notes'],
                        $itemData['serial_number']
                    );

                    if ($updateStmt->execute()) {
                        $results['success']++;
                    }

                    $updateStmt->close();
                } else {
                    // Insert
                    $insertSql = "INSERT INTO items (
                        item_name, serial_number, category, brand, model,
                        department, description, `condition`, stock_location,
                        quantity, status, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param(
                        'sssssssssiss',
                        $itemData['item_name'],
                        $itemData['serial_number'],
                        $itemData['category'],
                        $itemData['brand'],
                        $itemData['model'],
                        $itemData['department'],
                        $itemData['description'],
                        $itemData['condition'],
                        $itemData['stock_location'],
                        $itemData['quantity'],
                        $itemData['status'],
                        $itemData['notes']
                    );

                    if ($insertStmt->execute()) {
                        $results['success']++;
                    }

                    $insertStmt->close();
                }

                $checkStmt->close();
            } catch (Exception $e) {
                $results['errors'][] = "Row $rowNum: " . $e->getMessage();
                $results['skipped']++;
            }
        }

        fclose($handle);
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }

    return $results;
}


/**
 * Get performance metrics for technicians based on movement activity
 */
function getTechnicianPerformance($conn, $limit = 5)
{
    $results = [];
    try {
        $query = "SELECT u.username, COUNT(sm.id) as scans, '98%' as accuracy, '2.5h' as avg_time
                  FROM users u
                  JOIN stock_movements sm ON u.id = sm.user_id
                  GROUP BY u.id
                  ORDER BY scans DESC
                  LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting technician performance: " . $e->getMessage());
    }
    return $results;
}
