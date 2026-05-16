<?php
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the current file path relative to document root
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Build base URL
    if ($script_dir === '/' || $script_dir === '\\') {
        $base_url = $protocol . '://' . $host . '/';
    } else {
        $base_url = $protocol . '://' . $host . rtrim($script_dir, '/') . '/';
    }
    
    define('BASE_URL', $base_url);
}
?>