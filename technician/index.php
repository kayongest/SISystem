<?php
session_start();

// Simple database connection
$host = 'localhost';
$dbname = 'ability_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    $pdo = null;
    $db_error = "Database connection failed. Using demo mode.";
}

// Redirect if already logged in
if (isset($_SESSION['technician_id'])) {
    header('Location: ../dashboard_full.php');
    exit();
}

$error = '';
$demo_mode = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_username = $_POST['username'] ?? '';
    $input_password = $_POST['password'] ?? '';

    // Simple validation
    if (empty($input_username) || empty($input_password)) {
        $error = 'Username and password are required';
    } else {
        if ($pdo) {
            // Database mode
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 AND (role = 'technician' OR role = 'tech_lead' OR role = 'senior_tech')");
            $stmt->execute([$input_username]);
            $technician = $stmt->fetch();

            if ($technician) {
                // Check password - support both plain text and hashed
                if (
                    $input_password === $technician['password'] ||
                    (isset($technician['password']) && password_verify($input_password, $technician['password']))
                ) {
                    // Login successful
                    $_SESSION['technician_id'] = $technician['id'];
                    $_SESSION['technician_name'] = $technician['full_name'];
                    $_SESSION['technician_username'] = $technician['username'];
                    $_SESSION['technician_department'] = $technician['department'] ?? '';

                    // Also set standard root login variables so they can access root dashboard_full.php
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $technician['id'];
                    $_SESSION['username'] = $technician['username'];
                    $_SESSION['full_name'] = $technician['full_name'];
                    $_SESSION['email'] = $technician['email'] ?? '';
                    $_SESSION['role'] = $technician['role'] ?? 'technician';
                    $_SESSION['user_role'] = $technician['role'] ?? 'technician';
                    $_SESSION['department'] = $technician['department'] ?? '';
                    $_SESSION['user_roles'] = [$technician['role'] ?? 'technician'];

                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$technician['id']]);

                    header('Location: ../dashboard_full.php');
                    exit();
                } else {
                    $error = 'Invalid password';
                }
            } else {
                $error = 'User not found';
            }
        } else {
            // Demo mode
            $demo_mode = true;
            if ($input_username === 'admin' && $input_password === 'admin123') {
                $_SESSION['technician_id'] = 1;
                $_SESSION['technician_name'] = 'Demo Admin';
                $_SESSION['technician_username'] = 'admin';
                $_SESSION['technician_department'] = 'Administration';

                // Also set standard root login variables so they can access root dashboard_full.php
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['full_name'] = 'Demo Admin';
                $_SESSION['email'] = 'admin@example.com';
                $_SESSION['role'] = 'technician';
                $_SESSION['user_role'] = 'technician';
                $_SESSION['department'] = 'Administration';
                $_SESSION['user_roles'] = ['technician'];

                header('Location: ../dashboard_full.php');
                exit();
            } else {
                $error = 'Invalid credentials (demo: admin/admin123)';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login as Admin User - COMPANY LOGO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .company-logo {
            background: #1a1a1a;
            color: white;
            padding: 25px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .login-content {
            padding: 40px 35px;
        }

        .login-title {
            text-align: center;
            color: #333;
            font-size: 20px;
            font-weight: 400;
            margin-bottom: 35px;
            letter-spacing: 0.3px;
        }

        .error-alert {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-alert::before {
            content: "⚠️";
            font-size: 16px;
        }

        .demo-notice {
            background: #fff8e6;
            border: 1px solid #ffd699;
            color: #cc7a00;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.2s;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }

        .login-button {
            width: 100%;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }

        .login-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 14px rgba(102, 126, 234, 0.2);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .links-section {
            margin-top: 25px;
            text-align: center;
        }

        .forgot-link {
            display: block;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .help-link {
            display: block;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .footer-links {
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            color: #888;
        }

        .footer-links a {
            color: #666;
            text-decoration: none;
            margin: 0 5px;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .demo-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 193, 7, 0.9);
            color: #333;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
        }

        @media (max-width: 480px) {
            .login-content {
                padding: 30px 25px;
            }

            .company-logo {
                padding: 20px;
                font-size: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">
            <?php if ($demo_mode || isset($db_error)): ?>
                <div class="demo-badge">DEMO MODE</div>
            <?php endif; ?>

            <div class="company-logo">COMPANY LOGO</div>

            <div class="login-content">
                <h2 class="login-title">Login as an Admin User</h2>

                <?php if (isset($db_error)): ?>
                    <div class="demo-notice">
                        <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($demo_mode): ?>
                    <div class="demo-notice">
                        Demo credentials: <strong>admin</strong> / <strong>admin123</strong>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input type="text"
                            class="form-input"
                            id="username"
                            name="username"
                            autocomplete="username"
                            required
                            placeholder="Enter your username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="password-container">
                            <input type="password"
                                class="form-input"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                                placeholder="Enter your password">
                            <button type="button" class="toggle-password" id="togglePassword">
                                👁️
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="login-button">LOGIN</button>
                </form>

                <div class="links-section">
                    <a href="#" class="forgot-link">Forgot your password?</a>
                    <a href="#" class="help-link">Get help Signed in</a>
                </div>

                <div class="footer-links">
                    <a href="#">Terms of use</a> • <a href="#">Privacy policy</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const toggleBtn = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        toggleBtn.addEventListener('click', function() {
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        });

        // Auto focus on username if there's an error
        <?php if ($error): ?>
            document.getElementById('username').focus();
            document.getElementById('username').select();
        <?php else: ?>
            document.getElementById('username').focus();
        <?php endif; ?>

        // Prevent form submission if fields are empty
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in both username and password');
                return false;
            }
        });

        // Add enter key support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                const active = document.activeElement;
                if (active.form) {
                    const submitBtn = active.form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        e.preventDefault();
                        submitBtn.click();
                    }
                }
            }
        });
    </script>
</body>

</html>