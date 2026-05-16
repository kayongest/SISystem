<?php
// api/items/create_debug.php - Debug version
header('Content-Type: application/json');

// Enable error reporting but don't display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

$rootDir = realpath(__DIR__ . '/../..');
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'item_id' => null,
    'debug' => [
        'rootDir' => $rootDir,
        'currentDir' => __DIR__,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'post_data' => $_POST,
        'files_data' => isset($_FILES) ? array_keys($_FILES) : [],
        'session_status' => session_status(),
        'php_version' => phpversion()
    ]
];

try {
    // Check if we can include files
    $response['debug']['bootstrap_exists'] = file_exists($rootDir . '/bootstrap.php');
    $response['debug']['db_connect_exists'] = file_exists($rootDir . '/includes/db_connect.php');
    $response['debug']['functions_exists'] = file_exists($rootDir . '/includes/functions.php');
    
    // Try to include bootstrap
    require_once $rootDir . '/bootstrap.php';
    $response['debug']['bootstrap_loaded'] = true;
    
    // Try to include other files
    require_once $rootDir . '/includes/db_connect.php';
    require_once $rootDir . '/includes/functions.php';
    $response['debug']['includes_loaded'] = true;
    
    // Check if functions exist
    $response['debug']['function_isLoggedIn_exists'] = function_exists('isLoggedIn');
    
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        throw new Exception('Authentication required', 401);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['debug']['allowed_methods'] = ['POST'];
        throw new Exception('Method not allowed. Use POST method.', 405);
    }
    
    // Test database connection
    $conn = getConnection();
    $response['debug']['db_connected'] = true;
    
    // Test simple query
    $testResult = $conn->query("SELECT 1 as test");
    $response['debug']['db_query_test'] = $testResult ? 'success' : 'failed';
    
    // Check for required fields
    $required = ['item_name', 'serial_number', 'category'];
    $errors = [];
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[$field] = 'This field is required';
        }
    }
    
    $response['debug']['required_fields_check'] = $errors;
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = 'Please fix the errors below';
        http_response_code(400);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    // If we get here, all checks passed
    $response['success'] = true;
    $response['message'] = 'API is working correctly. Ready to accept data.';
    $response['debug']['ready_for_insert'] = true;
    
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    $response['debug']['exception'] = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    http_response_code(500);
}

echo json_encode($response, JSON_PRETTY_PRINT);
exit();