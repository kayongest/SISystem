<?php
require_once 'config/database.php';

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$debug_info = []; // Array to store debug information
$remember_me = false;

// Check for AJAX verification flag (bypass password check)
$ajax_verified = isset($_SESSION['ajax_verified_user_id']) &&
    isset($_SESSION['ajax_verified_username']);

// Check for remember me cookie
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_token'])) {
    // Verify remember me token and auto-login
    $token = $_COOKIE['remember_token'];
    try {
        $conn = getConnection();

        // First check if user_tokens table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'user_tokens'");
        if ($table_check->num_rows > 0) {
            $stmt = $conn->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW()");
            if ($stmt) {
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $token_data = $result->fetch_assoc();
                    // Get user data and log them in
                    $user_stmt = $conn->prepare("SELECT id, username, email, full_name, role, department, is_active, signature_image, profile_image FROM users WHERE id = ?");
                    if ($user_stmt) {
                        $user_stmt->bind_param("i", $token_data['user_id']);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();

                        if ($user_result && $user_result->num_rows === 1) {
                            $user = $user_result->fetch_assoc();
                            if ($user['is_active'] == 1) {
                                $_SESSION['logged_in'] = true;
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['role'] = $user['role'];
                                $_SESSION['user_role'] = $user['role'];
                                $_SESSION['department'] = $user['department'];
                                $_SESSION['signature_image'] = $user['signature_image'];
                                $_SESSION['profile_image'] = $user['profile_image'];

                                header('Location: dashboard_full.php');
                                exit();
                            }
                        }
                        $user_stmt->close();
                    }
                }
                $stmt->close();
            }
        }
        $conn->close();
    } catch (Exception $e) {
        // Log error but don't show to user
        error_log("Remember me login error: " . $e->getMessage());
    }
}

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard_full.php');
    exit();
}

// Rate limiting
$rate_limit_key = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
$rate_limit_file = sys_get_temp_dir() . '/' . $rate_limit_key;
$max_attempts = 5;
$lockout_time = 900; // 15 minutes

if (file_exists($rate_limit_file)) {
    $attempts = json_decode(file_get_contents($rate_limit_file), true);
    if ($attempts && isset($attempts['count']) && $attempts['count'] >= $max_attempts && time() - $attempts['time'] < $lockout_time) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
        $debug_info[] = "Rate limit exceeded for IP: " . $_SERVER['REMOTE_ADDR'];
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember']);

    // Check if user was pre-verified via AJAX
    if ($ajax_verified && $_SESSION['ajax_verified_username'] === $username) {
        // User already verified via AJAX, bypass password check
        $user_id = $_SESSION['ajax_verified_user_id'];
        $full_name = $_SESSION['ajax_verified_full_name'];
        $email = $_SESSION['ajax_verified_email'];
        $role = $_SESSION['ajax_verified_role'];
        $department = $_SESSION['ajax_verified_department'];
        $roles = $_SESSION['ajax_verified_roles'] ?? [$role];

        // Set session variables directly
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['user_role'] = $role;
        $_SESSION['department'] = $department;
        $_SESSION['user_roles'] = $roles;
        $_SESSION['is_admin'] = in_array('admin', $roles);
        $_SESSION['is_manager'] = in_array('manager', $roles);
        $_SESSION['is_staff'] = in_array('staff', $roles);
        $_SESSION['signature_image'] = $_SESSION['ajax_verified_signature_image'] ?? null;
        $_SESSION['profile_image'] = $_SESSION['ajax_verified_profile_image'] ?? null;
        $_SESSION['last_activity'] = time();
        $_SESSION['timeout'] = 1800;

        // Clear AJAX verification session variables
        unset($_SESSION['ajax_verified_user_id']);
        unset($_SESSION['ajax_verified_username']);
        unset($_SESSION['ajax_verified_full_name']);
        unset($_SESSION['ajax_verified_email']);
        unset($_SESSION['ajax_verified_role']);
        unset($_SESSION['ajax_verified_department']);
        unset($_SESSION['ajax_verified_roles']);

        // Handle "Remember Me"
        if ($remember_me) {
            $table_check = $conn->query("SHOW TABLES LIKE 'user_tokens'");
            if ($table_check && $table_check->num_rows > 0) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                $token_stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                if ($token_stmt) {
                    $token_stmt->bind_param("iss", $user_id, $token, $expires);
                    if ($token_stmt->execute()) {
                        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
                    }
                    $token_stmt->close();
                }
            }
        }

        // Log successful login
        logLoginAttempt($username, 'SUCCESS', 'AJAX verified login');

        // Default redirection based on roles
        $redirect = 'dashboard_full.php';
        if ($role === 'driver') {
            $redirect = 'driver_batches.php';
        }

        // Override with session redirect if set (takes priority)
        if (isset($_SESSION['redirect_url']) && !empty($_SESSION['redirect_url'])) {
            $redirect = $_SESSION['redirect_url'];
        }

        unset($_SESSION['redirect_url']);
        header('Location: ' . $redirect);
        exit();
    }

    // Regular login flow (fallback for non-AJAX or non-verified)
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
        $debug_info[] = "Validation failed: Empty fields";
    } else {
        try {
            $conn = getConnection();
            $debug_info[] = "Database connection established";

            // First, get basic user info
            $stmt = $conn->prepare("SELECT id, username, email, full_name, password, role, department, is_active, signature_image, profile_image FROM users WHERE username = ? OR email = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result) {
                throw new Exception("Failed to execute query: " . $stmt->error);
            }

            $debug_info[] = "Query executed. Rows found: " . $result->num_rows;

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $debug_info[] = "User found: " . htmlspecialchars($user['username']);
                $debug_info[] = "User active status: " . $user['is_active'];
                $debug_info[] = "Full name from DB: " . ($user['full_name'] ?? 'Not set');

                if ($user['is_active'] == 0) {
                    $error = 'Your account is disabled. Please contact administrator.';
                    $debug_info[] = "Login failed: Account disabled";

                    // Log failed attempt
                    logLoginAttempt($username, 'FAILED', 'Account disabled');
                } elseif (password_verify($password, $user['password'])) {
                    $debug_info[] = "Password verification: SUCCESS";

                    // Update last login if the column exists
                    try {
                        // Check if last_login column exists
                        $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
                        if ($column_check && $column_check->num_rows > 0) {
                            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
                            if ($update_stmt) {
                                $ip = $_SERVER['REMOTE_ADDR'];
                                $update_stmt->bind_param("si", $ip, $user['id']);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                    } catch (Exception $e) {
                        // Ignore if columns don't exist
                        $debug_info[] = "Note: Could not update last_login: " . $e->getMessage();
                    }

                    // Set session variables
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['department'] = $user['department'];
                    $_SESSION['signature_image'] = $user['signature_image'];
                    $_SESSION['profile_image'] = $user['profile_image'];
                    $_SESSION['last_activity'] = time();

                    // Set session timeout (30 minutes)
                    $_SESSION['timeout'] = 1800;

                    $debug_info[] = "Full name set in session: " . $_SESSION['full_name'];

                    // Get all roles for this user
                    $roles = [$user['role']];

                    // Check for additional roles if user_roles table exists
                    $table_check = $conn->query("SHOW TABLES LIKE 'user_roles'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $roles_stmt = $conn->prepare("SELECT role FROM user_roles WHERE user_id = ?");
                        if ($roles_stmt) {
                            $roles_stmt->bind_param("i", $user['id']);
                            $roles_stmt->execute();
                            $roles_result = $roles_stmt->get_result();
                            if ($roles_result) {
                                while ($role_row = $roles_result->fetch_assoc()) {
                                    if (!in_array($role_row['role'], $roles)) {
                                        $roles[] = $role_row['role'];
                                    }
                                }
                            }
                            $roles_stmt->close();
                        }
                    }

                    $_SESSION['user_roles'] = $roles;
                    $_SESSION['is_admin'] = in_array('admin', $roles);
                    $_SESSION['is_manager'] = in_array('manager', $roles);
                    $_SESSION['is_staff'] = in_array('staff', $roles);

                    $debug_info[] = "User roles: " . implode(', ', $roles);

                    // Handle "Remember Me"
                    if ($remember_me) {
                        // Check if user_tokens table exists
                        $table_check = $conn->query("SHOW TABLES LIKE 'user_tokens'");
                        if ($table_check && $table_check->num_rows > 0) {
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                            // Store token in database
                            $token_stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                            if ($token_stmt) {
                                $token_stmt->bind_param("iss", $user['id'], $token, $expires);
                                if ($token_stmt->execute()) {
                                    // Set cookie
                                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
                                    $debug_info[] = "Remember me token created";
                                }
                                $token_stmt->close();
                            }
                        } else {
                            $debug_info[] = "Note: user_tokens table doesn't exist, skipping remember me";
                        }
                    }

                    // Clear rate limiting on successful login
                    if (file_exists($rate_limit_file)) {
                        unlink($rate_limit_file);
                    }

                    // Log successful login
                    logLoginAttempt($username, 'SUCCESS', '');

                    $debug_info[] = "Processing redirect";

                    // Default redirection based on roles
                    $redirect = 'dashboard_full.php';
                    if ($user['role'] === 'driver') {
                        $redirect = 'driver_batches.php';
                    }

                    // Override with session redirect if set (takes priority)
                    if (isset($_SESSION['redirect_url']) && !empty($_SESSION['redirect_url'])) {
                        $redirect = $_SESSION['redirect_url'];
                    }

                    $debug_info[] = "Redirecting to: " . $redirect;
                    unset($_SESSION['redirect_url']);

                    header('Location: ' . $redirect);
                    exit();
                } else {
                    $error = 'Invalid password';
                    $debug_info[] = "Password verification: FAILED";

                    // Update rate limiting
                    updateRateLimit($rate_limit_file);

                    // Log failed attempt
                    logLoginAttempt($username, 'FAILED', 'Invalid password');
                }
            } else {
                $error = 'User not found';
                $debug_info[] = "User not found in database";

                // Update rate limiting
                updateRateLimit($rate_limit_file);

                // Log failed attempt
                logLoginAttempt($username, 'FAILED', 'User not found');
            }

            $stmt->close();
            $conn->close();
            $debug_info[] = "Database connection closed";
        } catch (Exception $e) {
            $error = 'An error occurred during login. Please try again.';
            $debug_info[] = "Exception: " . $e->getMessage();
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Helper function to update rate limit
function updateRateLimit($file)
{
    $attempts = ['count' => 1, 'time' => time()];
    if (file_exists($file)) {
        $existing = json_decode(file_get_contents($file), true);
        if ($existing && isset($existing['count'])) {
            $attempts['count'] = $existing['count'] + 1;
            $attempts['time'] = time();
        }
    }
    file_put_contents($file, json_encode($attempts));
}

// Helper function to log login attempts
function logLoginAttempt($username, $status, $reason)
{
    $log_file = __DIR__ . '/logs/login_attempts.log';
    $log_dir = dirname($log_file);

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_entry = date('Y-m-d H:i:s') . " | IP: " . $_SERVER['REMOTE_ADDR'] . " | User: " . $username . " | Status: " . $status . " | Reason: " . $reason . " | User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Get system stats for display
$stats = ['total_items' => 0, 'low_stock' => 0, 'active_users' => 0];
try {
    $conn = getConnection();

    // Check if tables exist before querying
    $tables = ['inventory_items', 'users'];
    foreach ($tables as $table) {
        $table_check = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$table_check || $table_check->num_rows === 0) {
            $debug_info[] = "Table '$table' doesn't exist yet";
        }
    }

    // Get total items in inventory
    $result = $conn->query("SELECT COUNT(*) as total FROM inventory_items WHERE is_active = 1");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_items'] = $row['total'] ?? 0;
    }

    // Check if reorder_level column exists
    $column_check = $conn->query("SHOW COLUMNS FROM inventory_items LIKE 'reorder_level'");
    if ($column_check && $column_check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as total FROM inventory_items WHERE quantity <= reorder_level AND is_active = 1");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['low_stock'] = $row['total'] ?? 0;
        }
    }

    // Get active users
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['active_users'] = $row['total'] ?? 0;
    }

    $conn->close();
} catch (Exception $e) {
    $debug_info[] = "Stats error: " . $e->getMessage();
    error_log("Stats error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aBility Inventory Management System - Login</title>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marvel:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        * {
            font-family: "Marvel", sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
            background-size: cover;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .container {
            display: flex;
            width: 1100px;
            max-width: 95%;
            min-height: 600px;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }

        /* Left Panel - Branding & Stats */
        .brand-panel {
            flex: 0.7;
            background: #2D4356;
            align-items: center;
            padding-top: 10rem;
            color: white;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .brand-header {
            position: relative;
            z-index: 1;
        }

        .brand-header h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .brand-header h1 span {
            color: #ffffff;
        }

        .brand-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        /* Right Panel - Login Form */
        .login-panel {
            flex: 1;
            padding: 40px;
            background: white;
            display: flex;
            flex-direction: column;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-header h2 {
            font-size: 2rem;
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .login-form {
            flex: 1;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            color: #999;
            font-size: 1.1rem;
        }

        .input-wrapper input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .input-wrapper input:focus {
            border-color: #667eea;
            background: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-wrapper input::placeholder {
            color: #999;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            color: #999;
            cursor: pointer;
            font-size: 1.1rem;
        }

        .toggle-password:hover {
            color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #2D4356;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(45, 67, 86, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .login-btn.loading .btn-text {
            display: none;
        }

        .login-btn.loading .btn-loader {
            display: inline-block;
        }

        .btn-loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        .alert i {
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                margin: 20px;
            }

            .brand-panel {
                padding: 30px;
            }

            .brand-header h1 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <!-- Left Panel - Branding & Stats -->
        <div class="brand-panel">
            <div class="brand-header">
                <h1>a<span>Bility</span></h1>
                <p>"Find anything. Track everything. Save Hours"</p>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="login-panel">
            <div class="login-header">
                <h2>Welcome Back!</h2>
                <p>Please login to access your inventory dashboard</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error" id="errorMessage">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Registration successful! Please login with your credentials.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['password_reset']) && $_GET['password_reset'] == 'true'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Password reset successful! Please login with your new password.
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" id="loginForm" class="login-form">
                <div class="input-group">
                    <label class="input-label" for="username">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username or email"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required
                            autofocus>
                    </div>
                </div>

                <div class="input-group">
                    <label class="input-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword()" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="submitBtn">
                    <span class="btn-text">Login</span>
                    <span class="btn-loader"></span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Verify credentials via AJAX
        async function verifyCredentials(username, password) {
            try {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('password', password);
                formData.append('ajax', '1');

                const response = await fetch('login_ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                return result;
            } catch (error) {
                console.error('Verification error:', error);
                return {
                    success: false,
                    message: 'Network error. Please try again.'
                };
            }
        }

        // Show alert function
        function showAlert(message, type) {
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>${message}`;

            const loginHeader = document.querySelector('.login-header');
            loginHeader.insertAdjacentElement('afterend', alertDiv);

            setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => alertDiv.remove(), 1000);
            }, 5000);
        }

        // Horizontal Wizard Toast
        function showVerificationToast(callback) {
            const existingToast = document.querySelector('.verification-toast');
            if (existingToast) existingToast.remove();

            const toast = document.createElement('div');
            toast.className = 'verification-toast';
            toast.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 28px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
            z-index: 9999;
            width: 680px;
            max-width: 90vw;
            animation: toastPopIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            overflow: hidden;
        `;

            toast.innerHTML = `
            <div style="background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%); padding: 20px 30px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-crown" style="color: #FFD700; font-size: 20px;"></i>
                        </div>
                        <div>
                            <h5 style="margin: 0; color: white; font-size: 1.2rem; font-weight: 700;">aBility Access Wizard</h5>
                            <p style="margin: 0; color: rgba(255,255,255,0.7); font-size: 0.8rem;">Secure authentication flow</p>
                        </div>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 6px 12px; border-radius: 20px;">
                        <span id="wizardTimer" style="color: white; font-size: 0.85rem; font-weight: 600;"><i class="fas fa-clock" style="margin-right: 5px;"></i>10s</span>
                    </div>
                </div>
            </div>
            <div class="toast-content" style="padding: 30px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 35px; position: relative;">
                    <div style="position: absolute; top: 25px; left: 60px; right: 60px; height: 3px; background: #e0e0e0; z-index: 0;">
                        <div id="wizardLineFill" style="width: 0%; height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); transition: width 0.5s ease;"></div>
                    </div>
                    <div id="step1" style="text-align: center; position: relative; z-index: 1; flex: 1;">
                        <div id="step1Circle" style="width: 50px; height: 50px; margin: 0 auto; background: #4CAF50; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check" style="color: white; font-size: 20px;"></i>
                        </div>
                        <div style="margin-top: 10px;">
                            <div style="font-weight: 700; color: #333; font-size: 0.85rem;">Credentials</div>
                            <div id="step1Status" style="font-size: 0.7rem; color: #4CAF50;">✓ Verified</div>
                        </div>
                    </div>
                    <div id="step2" style="text-align: center; position: relative; z-index: 1; flex: 1;">
                        <div id="step2Circle" style="width: 50px; height: 50px; margin: 0 auto; background: #f0f0f0; border: 2px solid #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-shield-alt" style="color: #999; font-size: 20px;"></i>
                        </div>
                        <div style="margin-top: 10px;">
                            <div style="font-weight: 700; color: #333; font-size: 0.85rem;">Permissions</div>
                            <div id="step2Status" style="font-size: 0.7rem; color: #999;">Pending</div>
                        </div>
                    </div>
                    <div id="step3" style="text-align: center; position: relative; z-index: 1; flex: 1;">
                        <div id="step3Circle" style="width: 50px; height: 50px; margin: 0 auto; background: #f0f0f0; border: 2px solid #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tachometer-alt" style="color: #999; font-size: 20px;"></i>
                        </div>
                        <div style="margin-top: 10px;">
                            <div style="font-weight: 700; color: #333; font-size: 0.85rem;">Dashboard</div>
                            <div id="step3Status" style="font-size: 0.7rem; color: #999;">Pending</div>
                        </div>
                    </div>
                </div>
                <div id="wizardContent" style="background: #f8f9fa; border-radius: 16px; padding: 25px; margin-bottom: 20px; min-height: 180px;">
                    <div id="wizardIcon" style="text-align: center; margin-bottom: 15px;">
                        <i class="fas fa-spinner fa-pulse" style="font-size: 48px; color: #234c6a;"></i>
                    </div>
                    <div id="wizardTitle" style="text-align: center; font-size: 1.3rem; font-weight: 700; color: #234c6a; margin-bottom: 10px;">Verifying Credentials</div>
                    <div id="wizardMessage" style="text-align: center; color: #666; font-size: 0.9rem; line-height: 1.5;">Checking your login information...</div>
                </div>
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 0.8rem; color: #666;">Overall Progress</span>
                        <span id="overallProgress" style="font-size: 0.8rem; font-weight: 700; color: #234c6a;">0%</span>
                    </div>
                    <div style="height: 8px; background: #e0e0e0; border-radius: 10px; overflow: hidden;">
                        <div id="overallProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #234c6a, #4CAF50, #8BC34A); border-radius: 10px; transition: width 0.3s ease;"></div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <div style="width: 25px; height: 25px; background: rgba(35, 76, 106, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-lock" style="color: #234c6a; font-size: 12px;"></i>
                    </div>
                    <p style="margin: 0; color: #888; font-size: 0.8rem;">
                        <span id="footerWizardMessage">Secure authentication in progress...</span>
                    </p>
                </div>
            </div>
        `;

            document.body.appendChild(toast);

            // Add animations
            if (!document.getElementById('toastAnimations')) {
                const style = document.createElement('style');
                style.id = 'toastAnimations';
                style.textContent = `
                @keyframes toastPopIn {
                    0% { opacity: 0; transform: translate(-50%, -40%) scale(0.8); }
                    100% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
                }
                @keyframes toastSlideOut {
                    0% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
                    100% { opacity: 0; transform: translate(-50%, -60%) scale(0.9); }
                }
                @keyframes stepPulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }
                .step-active { animation: stepPulse 0.5s ease; }
            `;
                document.head.appendChild(style);
            }

            const step2Circle = document.getElementById('step2Circle');
            const step3Circle = document.getElementById('step3Circle');
            const step2Status = document.getElementById('step2Status');
            const step3Status = document.getElementById('step3Status');
            const wizardLineFill = document.getElementById('wizardLineFill');
            const wizardIcon = document.getElementById('wizardIcon');
            const wizardTitle = document.getElementById('wizardTitle');
            const wizardMessage = document.getElementById('wizardMessage');
            const overallProgressBar = document.getElementById('overallProgressBar');
            const overallProgressSpan = document.getElementById('overallProgress');
            const footerMessage = document.getElementById('footerWizardMessage');
            const timerElement = document.getElementById('wizardTimer');

            const stages = [{
                    name: 'credentials',
                    duration: 3000,
                    title: 'Verifying Credentials',
                    message: 'Checking your login information and validating access rights...',
                    icon: '<i class="fas fa-user-check" style="font-size: 48px; color: #4CAF50;"></i>',
                    updateStep: () => {}
                },
                {
                    name: 'permissions',
                    duration: 4000,
                    title: 'Loading Permissions',
                    message: 'Retrieving your role-based access controls and security settings...',
                    icon: '<i class="fas fa-shield-alt" style="font-size: 48px; color: #234c6a;"></i>',
                    updateStep: () => {
                        step2Circle.style.background = '#4CAF50';
                        step2Circle.style.border = 'none';
                        step2Circle.innerHTML = '<i class="fas fa-check" style="color: white; font-size: 20px;"></i>';
                        step2Status.innerHTML = '✓ Loaded';
                        step2Status.style.color = '#4CAF50';
                        wizardLineFill.style.width = '50%';
                    }
                },
                {
                    name: 'dashboard',
                    duration: 3000,
                    title: 'Preparing Dashboard',
                    message: 'Building your personalized dashboard with recent activity and metrics...',
                    icon: '<i class="fas fa-chart-line" style="font-size: 48px; color: #FF9800;"></i>',
                    updateStep: () => {
                        step3Circle.style.background = '#FF9800';
                        step3Circle.style.border = 'none';
                        step3Circle.innerHTML = '<i class="fas fa-spinner fa-pulse" style="color: white; font-size: 20px;"></i>';
                        step3Status.innerHTML = 'Loading...';
                        step3Status.style.color = '#FF9800';
                        wizardLineFill.style.width = '75%';
                    }
                }
            ];

            let currentStage = 0;
            let startTime = Date.now();
            let stageStartTime = startTime;
            let timeoutIds = [];

            function updateProgress() {
                const now = Date.now();
                const totalElapsed = (now - startTime) / 1000;
                const totalDuration = stages.reduce((sum, stage) => sum + stage.duration, 0) / 1000;
                const overallPercent = Math.min(100, (totalElapsed / totalDuration) * 100);
                overallProgressBar.style.width = overallPercent + '%';
                overallProgressSpan.textContent = Math.round(overallPercent) + '%';
                const remaining = Math.max(0, totalDuration - totalElapsed);
                timerElement.innerHTML = `<i class="fas fa-clock" style="margin-right: 5px;"></i>${Math.ceil(remaining)}s`;
            }

            function advanceToNextStage() {
                if (currentStage >= stages.length) {
                    completeWizard();
                    return;
                }
                const stage = stages[currentStage];
                wizardTitle.textContent = stage.title;
                wizardMessage.textContent = stage.message;
                wizardIcon.innerHTML = stage.icon;
                stage.updateStep();

                const stepDiv = currentStage === 0 ? document.getElementById('step1') : currentStage === 1 ? document.getElementById('step2') : document.getElementById('step3');
                stepDiv.classList.add('step-active');
                setTimeout(() => stepDiv.classList.remove('step-active'), 500);

                const timeoutId = setTimeout(() => {
                    currentStage++;
                    stageStartTime = Date.now();
                    if (currentStage === 1) {
                        step2Circle.style.background = '#FF9800';
                        step2Circle.style.border = 'none';
                        step2Circle.innerHTML = '<i class="fas fa-spinner fa-pulse" style="color: white; font-size: 20px;"></i>';
                        step2Status.innerHTML = 'Loading...';
                        step2Status.style.color = '#FF9800';
                        wizardLineFill.style.width = '25%';
                    } else if (currentStage === 2) {
                        step2Circle.style.background = '#4CAF50';
                        step2Circle.innerHTML = '<i class="fas fa-check" style="color: white; font-size: 20px;"></i>';
                        step2Status.innerHTML = '✓ Complete';
                        step2Status.style.color = '#4CAF50';
                        step3Circle.style.background = '#FF9800';
                        step3Circle.style.border = 'none';
                        step3Circle.innerHTML = '<i class="fas fa-spinner fa-pulse" style="color: white; font-size: 20px;"></i>';
                        step3Status.innerHTML = 'Loading...';
                        step3Status.style.color = '#FF9800';
                        wizardLineFill.style.width = '50%';
                    }
                    if (currentStage < stages.length) advanceToNextStage();
                    else completeWizard();
                }, stage.duration);
                timeoutIds.push(timeoutId);
            }

            function completeWizard() {
                wizardTitle.textContent = 'Access Granted!';
                wizardMessage.textContent = 'All verifications complete. Redirecting you to your dashboard...';
                wizardIcon.innerHTML = '<i class="fas fa-check-circle" style="font-size: 48px; color: #4CAF50;"></i>';
                step2Circle.style.background = '#4CAF50';
                step2Circle.innerHTML = '<i class="fas fa-check" style="color: white; font-size: 20px;"></i>';
                step2Status.innerHTML = '✓ Complete';
                step2Status.style.color = '#4CAF50';
                step3Circle.style.background = '#4CAF50';
                step3Circle.innerHTML = '<i class="fas fa-check" style="color: white; font-size: 20px;"></i>';
                step3Status.innerHTML = '✓ Ready';
                step3Status.style.color = '#4CAF50';
                wizardLineFill.style.width = '100%';
                overallProgressBar.style.width = '100%';
                overallProgressSpan.textContent = '100%';
                timerElement.innerHTML = '<i class="fas fa-check-circle"></i> Complete';
                footerMessage.innerHTML = '✓ Access granted - Redirecting to dashboard';

                setTimeout(() => {
                    const toastElement = document.querySelector('.verification-toast');
                    if (toastElement) {
                        toastElement.style.animation = 'toastSlideOut 0.3s ease';
                        toastElement.style.opacity = '0';
                        setTimeout(() => {
                            if (toastElement.parentNode) toastElement.remove();
                            if (typeof callback === 'function') callback();
                        }, 300);
                    }
                }, 1500);
            }

            const progressInterval = setInterval(updateProgress, 100);
            advanceToNextStage();

            const safetyTimeout = setTimeout(() => {
                clearInterval(progressInterval);
                timeoutIds.forEach(id => clearTimeout(id));
                if (typeof callback === 'function') callback();
            }, 15000);
            timeoutIds.push(safetyTimeout);
        }

        // Single form submission handler with AJAX verification
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showAlert('Please fill in all fields', 'error');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            // Verify credentials via AJAX
            const verification = await verifyCredentials(username, password);

            if (!verification.success) {
                showAlert(verification.message, 'error');
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                document.querySelector('.login-form').style.animation = 'shake 0.5s ease';
                setTimeout(() => {
                    document.querySelector('.login-form').style.animation = '';
                }, 500);
                return;
            }

            // Credentials are valid, show wizard then submit
            showVerificationToast(function() {
                document.getElementById('loginForm').submit();
            });
        });

        // Auto-hide error message
        const errorMessage = document.getElementById('errorMessage');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.opacity = '0';
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 300);
            }, 5000);
        }

        console.log('Login page loaded - Inventory Management System');
    </script>

</body>

</html>