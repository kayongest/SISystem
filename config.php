<?php
// config.php

// ==================== SESSION CONFIGURATION ====================
// Configure session parameters only if session is not active yet
if (session_status() === PHP_SESSION_NONE) {
    // Set session name first
    session_name('equipment_manager');

    // Configure session cookie parameters
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $protocol === 'https',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Set HTTP Security Headers
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// ==================== DATABASE CONFIGURATION ====================
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'ability_db');

// ==================== SITE CONFIGURATION ====================
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

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
        if ($path !== '/' && $path !== '\\') {
            $base_url = rtrim("$protocol://$host$path", '/') . '/';
        } else {
            $base_url = "$protocol://$host/";
        }
    }

    // Clean up double slashes
    $base_url = str_replace('//', '/', $base_url);
    $base_url = str_replace(':/', '://', $base_url);

    define('BASE_URL', $base_url);
}

// Site Settings
if (!defined('SITE_NAME')) define('SITE_NAME', 'aBility Equipment Manager');
if (!defined('SITE_VERSION')) define('SITE_VERSION', '1.0.0');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@example.com');

// File Upload Settings
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', 'uploads/');
if (!defined('QR_CODE_DIR')) define('QR_CODE_DIR', 'assets/qrcodes/');

// Error Reporting (for development)
if (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>