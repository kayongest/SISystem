<?php

// bootstrap.php - Debug at the very top
error_log("bootstrap.php loaded at " . date('Y-m-d H:i:s'));

// bootstrap.php
session_start(); // This must be at the VERY top

// Debug session
error_log("=== BOOTSTRAP STARTED ===");
error_log("Session ID: " . session_id());
error_log("Session status: " . session_status());
error_log("Session data before bootstrap: " . json_encode($_SESSION));

// Set default timezone if needed
date_default_timezone_set('UTC'); // Change to your timezone

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================== FIXED BASE URL ==================
if (!defined('BASE_URL')) {
    // Detect protocol
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];

    // Set to your ACTUAL directory - CORRECTED
    define('BASE_URL', $protocol . '://' . $host . '/ability_app_main/');

    // Debug
    error_log("BOOTSTRAP: BASE_URL set to: " . BASE_URL);
}

// Define absolute path
define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Include required files
require_once 'database_fix.php';
require_once 'functions.php';

// ================== SIMPLE SESSION-BASED ROLE FUNCTIONS ==================
// These functions work without database connection

// Check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        // Check for multiple possible session variables
        return (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ||
            (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ||
            (isset($_SESSION['username']) && !empty($_SESSION['username']));
    }
}

// Check if user is admin (from session)
if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

// Check if user has specific role (from session)
if (!function_exists('userHasRole')) {
    function userHasRole($role)
    {
        return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
}

// Get current user role
if (!function_exists('getCurrentUserRole')) {
    function getCurrentUserRole()
    {
        if (!isLoggedIn()) {
            return 'guest';
        }
        return $_SESSION['user_role'] ?? 'user';
    }
}

// Check if user is stock controller
if (!function_exists('isStockController')) {
    function isStockController()
    {
        return userHasRole('stock_controller');
    }
}

// Check if user is technician
if (!function_exists('isTechnician')) {
    function isTechnician()
    {
        return userHasRole('technician');
    }
}
// ================== END ROLE FUNCTIONS ==================

// Only define these functions if they don't already exist
if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 303)
    {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
}

// Simple flash message functions
if (!function_exists('setFlashMessage')) {
    function setFlashMessage($type, $message)
    {
        $_SESSION['flash_messages'][$type] = $message;
    }
}

if (!function_exists('getFlashMessage')) {
    function getFlashMessage($type)
    {
        if (isset($_SESSION['flash_messages'][$type])) {
            $message = $_SESSION['flash_messages'][$type];
            unset($_SESSION['flash_messages'][$type]);
            return $message;
        }
        return null;
    }
}

// Check if user is logged in (for pages)
if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        if (!isLoggedIn()) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
            redirect('login.php');
        }
    }
}

// CSRF token generation and validation
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken($token)
    {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }
}

// Sanitize output
if (!function_exists('sanitizeOutput')) {
    function sanitizeOutput($data)
    {
        if (is_array($data)) {
            return array_map('sanitizeOutput', $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

// Get current page URL
if (!function_exists('getCurrentUrl')) {
    function getCurrentUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}

// Format date
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'Y-m-d H:i:s')
    {
        if (empty($date) || $date == '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($date));
    }
}

// Check if request is AJAX
if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// Get user IP address
if (!function_exists('getUserIP')) {
    function getUserIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}

// Generate random string
if (!function_exists('generateRandomString')) {
    function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

// Validate email
if (!function_exists('isValidEmail')) {
    function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

// Truncate text
if (!function_exists('truncateText')) {
    function truncateText($text, $maxLength = 100)
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, 0, $maxLength) . '...';
    }
}

// Check if string is JSON
if (!function_exists('isJson')) {
    function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

// Get file extension
if (!function_exists('getFileExtension')) {
    function getFileExtension($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}

// Check if file is image
if (!function_exists('isImageFile')) {
    function isImageFile($filename)
    {
        $extension = getFileExtension($filename);
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        return in_array($extension, $imageExtensions);
    }
}

// Check if file is allowed
if (!function_exists('isAllowedFile')) {
    function isAllowedFile($filename, $allowedExtensions = [])
    {
        $extension = getFileExtension($filename);
        return in_array($extension, $allowedExtensions);
    }
}

// Generate slug
if (!function_exists('generateSlug')) {
    function generateSlug($text)
    {
        // Replace non-letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // Trim
        $text = trim($text, '-');

        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // Lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }
}

// Debug function
if (!function_exists('debug')) {
    function debug($data, $die = false)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        if ($die) {
            die();
        }
    }
}

error_log("=== BOOTSTRAP COMPLETED ===");
