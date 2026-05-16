<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please enter username and password']);
    exit();
}

// Rate limiting
$rate_limit_key = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
$rate_limit_file = sys_get_temp_dir() . '/' . $rate_limit_key;
$max_attempts = 5;
$lockout_time = 900;

if (file_exists($rate_limit_file)) {
    $attempts = json_decode(file_get_contents($rate_limit_file), true);
    if ($attempts && isset($attempts['count']) && $attempts['count'] >= $max_attempts && time() - $attempts['time'] < $lockout_time) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again in 15 minutes.']);
        exit();
    }
}

try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id, username, email, full_name, password, role, department, is_active FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if ($user['is_active'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Your account is disabled. Please contact administrator.']);
        } elseif (password_verify($password, $user['password'])) {
            // Store verified user data in session
            $_SESSION['ajax_verified_user_id'] = $user['id'];
            $_SESSION['ajax_verified_username'] = $user['username'];
            $_SESSION['ajax_verified_full_name'] = $user['full_name'] ?? $user['username'];
            $_SESSION['ajax_verified_email'] = $user['email'];
            $_SESSION['ajax_verified_role'] = $user['role'];
            $_SESSION['ajax_verified_department'] = $user['department'];
            $_SESSION['ajax_verified_roles'] = [$user['role']];
            
            echo json_encode(['success' => true, 'message' => 'Credentials verified']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("AJAX Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>