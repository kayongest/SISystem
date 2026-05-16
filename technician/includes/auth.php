<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if technician is logged in
 */
function isTechnicianLoggedIn()
{
    return isset($_SESSION['technician_id']);
}

/**
 * Require technician login - redirect if not logged in
 */
function requireTechnicianLogin()
{
    if (!isTechnicianLoggedIn()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: index.php');
        exit();
    }
}

/**
 * Get technician's display name
 */
function getTechnicianName()
{
    return $_SESSION['technician_name'] ?? 'Technician';
}

/**
 * Get technician's ID
 */
function getTechnicianId()
{
    return $_SESSION['technician_id'] ?? null;
}

/**
 * Get technician's username
 */
function getTechnicianUsername()
{
    return $_SESSION['technician_username'] ?? '';
}

/**
 * Get technician's session data
 */
function getTechnicianData()
{
    return [
        'id' => $_SESSION['technician_id'] ?? null,
        'name' => $_SESSION['technician_name'] ?? '',
        'username' => $_SESSION['technician_username'] ?? ''
    ];
}

/**
 * Check if user has specific permission (placeholder for future use)
 */
function hasPermission($permission)
{
    // For now, all technicians have all permissions
    return true;
}
