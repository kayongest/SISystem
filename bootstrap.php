<?php
// bootstrap.php - COMPLETELY FIXED VERSION

if (!defined('BASE_PATH')) define('BASE_PATH', realpath(__DIR__));
if (!defined('APP_PATH')) define('APP_PATH', BASE_PATH);

// Set include path
set_include_path(get_include_path() . PATH_SEPARATOR . BASE_PATH);

// Auto-load includes
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});


// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
date_default_timezone_set('Africa/Kigali');

// Define ROOT_DIR based on current file location
if (!defined('ROOT_DIR')) define('ROOT_DIR', __DIR__);

// Define BASE_URL
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    // Auto-detect BASE_URL
    $path = dirname($script);
    if ($path !== '/' && $path !== '\\') {
        define('BASE_URL', rtrim("$protocol://$host$path", '/') . '/');
    } else {
        define('BASE_URL', "$protocol://$host/");
    }
}

// Site configuration
if (!defined('SITE_NAME')) define('SITE_NAME', 'aBility Manager');

// Load functions.php - using absolute path
$functionsPath = ROOT_DIR . '/includes/functions.php';
if (file_exists($functionsPath)) {
    require_once $functionsPath;

    // DEBUG: Uncomment to verify functions are loaded
    // echo "<!-- Functions loaded from: $functionsPath -->";
} else {
    // Log error but don't die immediately
    error_log("ERROR: functions.php not found at: $functionsPath");

    // Define essential functions immediately
    if (!function_exists('isLoggedIn')) {
        function isLoggedIn()
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        }
    }

    if (!function_exists('redirect')) {
        function redirect($url, $statusCode = 303)
        {
            header('Location: ' . $url, true, $statusCode);
            exit();
        }
    }

    // Define other essential functions that might be called
    if (!function_exists('sanitizeInput')) {
        function sanitizeInput($data)
        {
            if (is_array($data)) {
                return array_map('sanitizeInput', $data);
            }
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            return $data;
        }
    }
}

// CSRF functions (if not in functions.php)
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// DEBUG: Check what's loaded
// echo "<!-- Bootstrap loaded from: " . __FILE__ . " -->";
// echo "<!-- ROOT_DIR: " . ROOT_DIR . " -->";
// echo "<!-- BASE_URL: " . BASE_URL . " -->";

// Strict access control for 'user' role
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $boot_role = function_exists('getUserRole') ? getUserRole() : ($_SESSION['user_role'] ?? $_SESSION['role'] ?? null);
    if (in_array($boot_role, ['user', 'technician'])) {
        $allowed_user_pages = [
            'dashboard_full.php',
            'scan_bulk.php',
            'batch_history.php',
            'technician_batch_history.php',
            'batch_details_modal.php',
            'batch_report.php',
            'batch_view.php',
            'gate_pass.php',
            'profile.php',
            'logout.php',
            'login.php',
            'index.php'
        ];
        $current_script = basename($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
        $req_uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_api = (strpos($req_uri, '/api/') !== false) || (strpos($current_script, '_ajax') !== false);

        if (!empty($current_script) && !$is_api && !in_array($current_script, $allowed_user_pages)) {
            $_SESSION['flash_messages']['error'] = 'Access restricted: Your account is only authorized to access Dashboard, Scanner, and Batch History.';
            header('Location: ' . BASE_URL . 'dashboard_full.php');
            exit();
        }
    }
}
