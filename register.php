<?php
require_once 'config/database.php';

$error = '';
$success = '';
$showRedirect = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $department = trim($_POST['department']);

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $conn = getConnection();

        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, department) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $department);

            if ($stmt->execute()) {
                $success = 'Registration successful! Redirecting to login...';
                $showRedirect = true;
            } else {
                $error = 'Registration failed: ' . $conn->error;
            }
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Ability DB Inventory</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap");

        * {
            font-family: "Titillium Web", sans-serif;
        }

        body {
            background: url('assets/images/bg/4.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }

        .container {
            background: rgba(137, 133, 133, 0.48);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            position: relative;
            min-height: 500px;
        }

        h2 {
            text-align: center;
            color: #ffffff;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #ffffff;
        }

        .form-control,
        .form-select {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .input-group .btn {
            border-left: none;
            background: #f8f9fa;
        }

        .input-group .btn:hover {
            background: #e9ecef;
        }

        .input-group .form-control:focus {
            box-shadow: none;
            border-color: #ced4da;
        }

        #passwordMatch {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .password-match {
            color: #198754;
        }

        .password-mismatch {
            color: #dc3545;
        }

        .btn-primary {
            background: #66c9ea;
            border-color: #66c9ea;
            padding: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #34487a;
            border-color: #34487a;
        }

        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            padding: 0.75rem;
            font-weight: 500;
        }

        .error {
            background: rgba(135, 27, 27, 0.2);
            color: #ff6b6b;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid rgba(135, 27, 27, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success {
            background: rgba(56, 136, 56, 0.2);
            color: #51cf66;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid rgba(56, 136, 56, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #ffffff;
        }

        .login-link a {
            text-decoration: none;
            color: #66d6ea;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .form-check-label {
            color: #ffffff;
        }

        .form-check-label a {
            color: #66d6ea;
            text-decoration: none;
        }

        .form-check-label a:hover {
            text-decoration: underline;
        }

        .form-check-input:checked {
            background-color: #66c9ea;
            border-color: #66c9ea;
        }

        /* Redirect Overlay Styles */
        .redirect-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .redirect-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .redirect-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 3rem;
            border-radius: 20px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* Loader Styles */
        .loader-container {
            margin: 2rem 0;
        }

        .loader {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            position: relative;
        }

        .loader-circle {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
        }

        .loader-circle:nth-child(1) {
            border: 8px solid rgba(102, 201, 234, 0.2);
            border-top: 8px solid #66c9ea;
            animation-delay: 0s;
        }

        .loader-circle:nth-child(2) {
            border: 8px solid rgba(52, 72, 122, 0.2);
            border-top: 8px solid #34487a;
            animation-delay: 0.5s;
            transform: rotate(45deg);
        }

        .loader-circle:nth-child(3) {
            border: 8px solid rgba(255, 255, 255, 0.2);
            border-top: 8px solid #ffffff;
            animation-delay: 1s;
            transform: rotate(90deg);
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .redirect-message {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .redirect-subtext {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .countdown {
            font-size: 1.2rem;
            color: #66c9ea;
            font-weight: bold;
            margin-top: 1rem;
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            margin-top: 1rem;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #66c9ea, #34487a);
            border-radius: 3px;
            width: 0%;
            animation: progress 2s linear forwards;
        }

        @keyframes progress {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }

        /* Success Icon Animation */
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            position: relative;
        }

        .checkmark {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: block;
            stroke-width: 4;
            stroke: #51cf66;
            stroke-miterlimit: 10;
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 4;
            stroke-miterlimit: 10;
            stroke: #51cf66;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        /* Notification Icons */
        .error-icon,
        .success-icon-small {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
    </style>
</head>

<body>
    <?php if ($showRedirect): ?>
        <!-- Redirect Overlay -->
        <div class="redirect-overlay active" id="redirectOverlay">
            <div class="redirect-content">
                <div class="success-icon">
                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none" />
                        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                    </svg>
                </div>

                <div class="redirect-message">Registration Successful!</div>
                <div class="redirect-subtext">You will be redirected to login page in <span id="countdown">3</span> seconds</div>

                <div class="loader-container">
                    <div class="loader">
                        <div class="loader-circle"></div>
                        <div class="loader-circle"></div>
                        <div class="loader-circle"></div>
                    </div>
                </div>

                <div class="progress-container">
                    <div class="progress-bar"></div>
                </div>

                <div class="countdown" id="countdownDisplay">Redirecting...</div>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <h2>Create New Account</h2>

        <?php if ($error): ?>
            <div class="error">
                <i class="bi bi-x-circle error-icon"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success && !$showRedirect): ?>
            <div class="success">
                <i class="bi bi-check-circle success-icon-small"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$showRedirect): ?>
            <form method="POST" action="" id="registrationForm">
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" required
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select id="department" name="department" class="form-select">
                                <option value="">Select Department</option>
                                <option value="IT" <?php echo (isset($_POST['department']) && $_POST['department'] == 'IT') ? 'selected' : ''; ?>>IT</option>
                                <option value="AUDIO" <?php echo (isset($_POST['department']) && $_POST['department'] == 'AUDIO') ? 'selected' : ''; ?>>AUDIO</option>
                                <option value="VIDEO" <?php echo (isset($_POST['department']) && $_POST['department'] == 'VIDEO') ? 'selected' : ''; ?>>VIDEO</option>
                                <option value="LIGHTING" <?php echo (isset($_POST['department']) && $_POST['department'] == 'LIGHTING') ? 'selected' : ''; ?>>LIGHTING</option>
                                <option value="RIGGING" <?php echo (isset($_POST['department']) && $_POST['department'] == 'RIGGING') ? 'selected' : ''; ?>>RIGGING</option>
                                <option value="WAREHOUSE" <?php echo (isset($_POST['department']) && $_POST['department'] == 'WAREHOUSE') ? 'selected' : ''; ?>>WAREHOUSE</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="password" class="form-label">Password * (min. 6 characters)</label>
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control" required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text text-light">Must be at least 6 characters long</div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <div class="input-group">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="form-text"></div>
                        </div>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the Terms and Conditions
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="login.php" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                                <i class="bi bi-person-plus me-1"></i> Create Account
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        <?php if ($showRedirect): ?>
            // Redirect countdown functionality
            let countdown = 3;
            const countdownElement = document.getElementById('countdown');
            const countdownDisplay = document.getElementById('countdownDisplay');
            const redirectUrl = 'login.php';

            function updateCountdown() {
                countdownElement.textContent = countdown;
                countdownDisplay.textContent = `Redirecting in ${countdown} second${countdown !== 1 ? 's' : ''}...`;

                if (countdown <= 0) {
                    window.location.href = redirectUrl;
                } else {
                    countdown--;
                    setTimeout(updateCountdown, 1000);
                }
            }

            // Start countdown when page loads
            document.addEventListener('DOMContentLoaded', function() {
                updateCountdown();

                // Auto-redirect after 3 seconds as backup
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 3000);
            });
        <?php endif; ?>

        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Confirm password visibility toggle
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');

            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Password match validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatchDiv = document.getElementById('passwordMatch');

        function checkPasswordMatch() {
            if (passwordInput.value && confirmPasswordInput.value) {
                if (passwordInput.value === confirmPasswordInput.value) {
                    passwordMatchDiv.textContent = '✓ Passwords match';
                    passwordMatchDiv.className = 'form-text password-match';
                } else {
                    passwordMatchDiv.textContent = '✗ Passwords do not match';
                    passwordMatchDiv.className = 'form-text password-mismatch';
                }
            } else {
                passwordMatchDiv.textContent = '';
            }
        }

        if (passwordInput && confirmPasswordInput) {
            passwordInput.addEventListener('input', checkPasswordMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }

        // Form validation before submission
        const registrationForm = document.getElementById('registrationForm');
        if (registrationForm) {
            registrationForm.addEventListener('submit', function(e) {
                // Check password match
                if (passwordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check your password confirmation.');
                    confirmPasswordInput.focus();
                    return;
                }

                // Check password length
                if (passwordInput.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    passwordInput.focus();
                    return;
                }

                // Check terms and conditions
                const termsCheckbox = document.getElementById('terms');
                if (!termsCheckbox.checked) {
                    e.preventDefault();
                    alert('You must agree to the Terms and Conditions.');
                    termsCheckbox.focus();
                    return;
                }

                // Show loading state on submit button
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
                    submitBtn.disabled = true;
                }
            });
        }

        // Initialize Bootstrap tooltips if any
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>

</html>