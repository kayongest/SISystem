<?php
// config/csrf.php - CSRF Protection Implementation

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate a CSRF token and store it in session
     * @return string The generated token
     */
    function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('getCSRFToken')) {
    /**
     * Get the current CSRF token
     * @return string|null The current token or null if not set
     */
    function getCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['csrf_token'] ?? null;
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate a CSRF token
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrfField')) {
    /**
     * Generate HTML for CSRF token hidden input field
     * @return string HTML input field
     */
    function csrfField() {
        $token = generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

if (!function_exists('requireCSRF')) {
    /**
     * Require CSRF token validation for POST requests
     * Terminates script if validation fails
     */
    function requireCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            
            if (!validateCSRFToken($token)) {
                http_response_code(403);
                die(json_encode([
                    'success' => false,
                    'message' => 'CSRF token validation failed. Please refresh the page and try again.'
                ]));
            }
        }
    }
}
?>
