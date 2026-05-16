<?php
// helpers.php

/**
 * Generate a URL
 */
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Generate an asset URL
 */
function asset($path) {
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Generate an API URL
 */
function api_url($endpoint) {
    return url('api/' . ltrim($endpoint, '/'));
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header('Location: ' . url($url));
    exit();
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
    }
}

/**
 * Get database connection
 */
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Escape for JavaScript
 */
function escapeJS($string) {
    return addslashes($string);
}
?>