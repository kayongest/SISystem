<?php
// includes/permissions.php - Role-based access control functions

/**
 * Check if current user has admin role
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if current user has manager role
 */
function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

/**
 * Check if current user has admin or manager role
 */
function isAdminOrManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
}

/**
 * Check if user can manage users
 * Admin can manage all users
 * Manager can manage users in their department
 */
function canManageUsers() {
    return isAdminOrManager();
}

/**
 * Check if user can manage specific user
 * Admin can manage all users
 * Manager can manage users in their department
 * Users cannot manage other users
 */
function canManageUser($targetUserId, $targetUserRole = null, $targetUserDepartment = null) {
    if (!isAdminOrManager()) {
        return false;
    }

    if (isAdmin()) {
        return true;
    }

    // Manager restrictions
    if (isManager()) {
        // Cannot manage admins or other managers
        if (in_array($targetUserRole, ['admin', 'manager'])) {
            return false;
        }

        // Can only manage users in their own department
        $currentUserDept = $_SESSION['department'] ?? '';
        if (!empty($currentUserDept) && $targetUserDepartment !== $currentUserDept) {
            return false;
        }
    }

    return true;
}

/**
 * Check if user can manage technicians
 * Admin can manage all technicians
 * Manager can manage technicians in their department
 */
function canManageTechnicians() {
    return isAdminOrManager();
}

/**
 * Check if user can delete users
 * Only admins can delete users
 */
function canDeleteUsers() {
    return isAdmin();
}

/**
 * Check if user can assign roles
 * Admin can assign any role
 * Manager can assign limited roles
 */
function canAssignRole($role) {
    if (isAdmin()) {
        return true;
    }

    if (isManager()) {
        $allowedRoles = ['user', 'technician', 'driver'];
        return in_array($role, $allowedRoles);
    }

    return false;
}

/**
 * Get available roles for current user
 */
function getAvailableRoles() {
    if (isAdmin()) {
        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'user' => 'User',
            'stock_manager' => 'Stock Manager',
            'stock_controller' => 'Stock Controller',
            'tech_lead' => 'Tech Lead',
            'technician' => 'Technician',
            'driver' => 'Driver'
        ];
    }

    if (isManager()) {
        return [
            'user' => 'User',
            'technician' => 'Technician',
            'driver' => 'Driver'
        ];
    }

    return [];
}

/**
 * Check if user can view system settings
 */
function canViewSettings() {
    return isAdminOrManager();
}

/**
 * Check if user can modify system settings
 */
function canModifySettings() {
    return isAdmin();
}

/**
 * Check if user can view reports
 */
function canViewReports() {
    return isAdminOrManager();
}

/**
 * Check if user can export data
 */
function canExportData() {
    return isAdminOrManager();
}

/**
 * Get user role display name
 */
function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'user' => 'User',
        'stock_manager' => 'Stock Manager',
        'stock_controller' => 'Stock Controller',
        'tech_lead' => 'Tech Lead',
        'technician' => 'Technician',
        'driver' => 'Driver'
    ];

    return $roles[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/**
 * Get role color for badges
 */
function getRoleColor($role) {
    $colors = [
        'admin' => '#dc3545',
        'manager' => '#fd7e14',
        'user' => '#6c757d',
        'stock_manager' => '#355872',
        'stock_controller' => '#6610f2',
        'tech_lead' => '#d63384',
        'technician' => '#20c997',
        'driver' => '#fd7e14'
    ];

    return $colors[$role] ?? '#6c757d';
}
?>
