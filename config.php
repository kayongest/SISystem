<?php
// config.php

// ==================== SESSION CONFIGURATION ====================
// This must be called BEFORE session_start() anywhere in your application

// Set session name first
session_name('equipment_manager');

// Configure session cookie parameters
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https" : "http";
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 86400, // 1 day
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => $protocol === 'https',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// ==================== DATABASE CONFIGURATION ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ability_db');

// ==================== SITE CONFIGURATION ====================
// Auto-detect BASE_URL with project folder detection
$host = $_SERVER['HTTP_HOST'];
$script = $_SERVER['SCRIPT_NAME'];
$request_uri = $_SERVER['REQUEST_URI'];

// Detect if we're in a project subdirectory
$project_folder = 'ability_app_main';

// Check if the request URI contains the project folder
if (
    strpos($request_uri, '/' . $project_folder . '/') !== false ||
    strpos($script, '/' . $project_folder . '/') !== false
) {
    // We're in the project folder
    $base_url = $protocol . '://' . $host . '/' . $project_folder . '/';
} else {
    // We're at the root or different configuration
    $path = dirname($script);
    if ($path !== '/') {
        $base_url = rtrim("$protocol://$host$path", '/') . '/';
    } else {
        $base_url = "$protocol://$host/";
    }
}

// Clean up double slashes
$base_url = str_replace('//', '/', $base_url);
$base_url = str_replace(':/', '://', $base_url);

define('BASE_URL', $base_url);

// Site Settings
define('SITE_NAME', 'aBility Equipment Manager'); // Changed to match your app name
define('SITE_VERSION', '1.0.0');
define('ADMIN_EMAIL', 'admin@example.com');

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', 'uploads/');
define('QR_CODE_DIR', 'assets/qrcodes/'); // Updated to match likely structure

// Error Reporting (for development)
if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>