<?php
// Detect base URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Get the document root and script name
$doc_root = $_SERVER['DOCUMENT_ROOT']; // e.g., C:/xampp/htdocs
$script_name = $_SERVER['SCRIPT_NAME']; // e.g., /ability_app_main/dashboard_full.php

// Calculate base path
$base_path = dirname($script_name);
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
} else {
    $base_path = rtrim($base_path, '/') . '/';
}

// Define constants
define('BASE_URL', $protocol . '://' . $host . $base_path);
define('BASE_PATH', $base_path);

// For debugging
define('IS_DEBUG', true);

if (IS_DEBUG) {
    error_log("BASE_URL: " . BASE_URL);
    error_log("BASE_PATH: " . BASE_PATH);
    error_log("SCRIPT_NAME: " . $script_name);
    error_log("DOCUMENT_ROOT: " . $doc_root);
}
