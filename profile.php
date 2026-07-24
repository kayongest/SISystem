<?php
// profile.php - User Profile Management
$current_page = 'profile.php';
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get database connection
$conn = getConnection();

// First, let's check what tables exist and create them if needed
function checkAndCreateTables($conn)
{
    $tables_created = false;

    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        // Create users table
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            phone VARCHAR(20),
            department VARCHAR(50),
            bio TEXT,
            profile_image VARCHAR(500),
            role VARCHAR(50) DEFAULT 'user',
            is_active TINYINT(1) DEFAULT 1,
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        if ($conn->query($sql)) {
            error_log("Users table created successfully");
        } else {
            error_log("Error creating users table: " . $conn->error);
        }
    }

    // Check if activity_log table exists
    $result = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($result->num_rows == 0) {
        // Create activity_log table
        $sql = "CREATE TABLE IF NOT EXISTS activity_log (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            user_id INT(11),
            action VARCHAR(50),
            description TEXT,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        if ($conn->query($sql)) {
            error_log("Activity_log table created successfully");
        } else {
            error_log("Error creating activity_log table: " . $conn->error);
        }
    }

    // Check if user_sessions table exists
    $result = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    if ($result->num_rows == 0) {
        // Create user_sessions table
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            user_id INT(11),
            session_id VARCHAR(255),
            login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            logout_time DATETIME,
            ip_address VARCHAR(45),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        if ($conn->query($sql)) {
            error_log("User_sessions table created successfully");
        } else {
            error_log("Error creating user_sessions table: " . $conn->error);
        }
    }
}

// Create tables if they don't exist
checkAndCreateTables($conn);

$is_editing_other = false;
$target_user_id = $_SESSION['user_id'] ?? null;

if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_SESSION['user_id'])) {
    if (function_exists('isAdmin') && isAdmin()) {
        $target_user_id = (int)$_GET['id'];
        if ($target_user_id !== (int)$_SESSION['user_id']) {
            $is_editing_other = true;
        }
    }
}

$pageTitle = $is_editing_other ? "Edit Profile - aBility" : "My Profile - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    ($is_editing_other ? 'Edit User Profile' : 'My Profile') => ''
];

// Handle form submission
$message = '';
$messageType = '';

// Get user data
try {
    // Check if user exists in database
    $check_sql = "SELECT id FROM users WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);

    if (!$check_stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Database error: " . $conn->error);
    }

    $check_stmt->bind_param("i", $target_user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 0) {
        if ($is_editing_other) {
            die("User not found.");
        } else {
            // User doesn't exist in database - this shouldn't happen if they're logged in
            session_destroy();
            header('Location: login.php');
            exit();
        }
    }

    // Now get full user data
    $sql = "SELECT u.*, 
                   COUNT(DISTINCT a.id) as activity_count
            FROM users u
            LEFT JOIN activity_log a ON u.id = a.user_id
            WHERE u.id = ? AND u.is_active = 1
            GROUP BY u.id";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        // User not found or inactive
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error loading profile: " . $e->getMessage());
    die("Error loading profile: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = trim($_POST['role'] ?? $user['role']);

        // Validate required fields
        if (empty($username) || empty($email)) {
            $message = 'Username and email are required.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email format.';
            $messageType = 'danger';
        } else {
            try {
                // Check if username/email already exists (excluding current user)
                $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? AND is_active = 1";
                $check_stmt = $conn->prepare($check_sql);

                if (!$check_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $check_stmt->bind_param("ssi", $username, $email, $target_user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $existing = $check_result->fetch_assoc();
                $check_stmt->close();

                if ($existing) {
                    $message = 'Username or email already exists.';
                    $messageType = 'danger';
                } else {
                    // Handle password change
                    $passwordChanged = false;

                    if (!empty($new_password)) {
                        if (!$is_editing_other && empty($current_password)) {
                            $message = 'Current password is required to change password.';
                            $messageType = 'danger';
                        } elseif ($new_password !== $confirm_password) {
                            $message = 'New passwords do not match.';
                            $messageType = 'danger';
                        } elseif (strlen($new_password) < 6) {
                            $message = 'New password must be at least 6 characters long.';
                            $messageType = 'danger';
                        } else {
                            if (!$is_editing_other) {
                                // Verify current password
                                $pass_sql = "SELECT password FROM users WHERE id = ?";
                                $pass_stmt = $conn->prepare($pass_sql);

                                if (!$pass_stmt) {
                                    throw new Exception("Prepare failed: " . $conn->error);
                                }

                                $pass_stmt->bind_param("i", $target_user_id);
                                $pass_stmt->execute();
                                $pass_result = $pass_stmt->get_result();
                                $userData = $pass_result->fetch_assoc();
                                $pass_stmt->close();

                                if (!password_verify($current_password, $userData['password'] ?? '')) {
                                    $message = 'Current password is incorrect.';
                                    $messageType = 'danger';
                                } else {
                                    $passwordChanged = true;
                                }
                            } else {
                                // Admin is editing, skip current password check
                                $passwordChanged = true;
                            }
                        }
                    }

                    // Handle profile picture upload
                    $profile_image = $user['profile_image'] ?? '';
                    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = 'uploads/profiles/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                        $file_name = 'profile_' . $target_user_id . '_' . time() . '.' . $file_ext;
                        $target_path = $upload_dir . $file_name;

                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array(strtolower($file_ext), $allowed_types)) {
                            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                                // Delete old profile picture if exists
                                if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                                    unlink($user['profile_image']);
                                }
                                $profile_image = $target_path;
                            }
                        }
                    }

                    // Handle digital signature upload
                    $signature_image = $user['signature_image'] ?? '';
                    if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
                        $sig_dir = 'uploads/signatures/';
                        if (!file_exists($sig_dir)) {
                            mkdir($sig_dir, 0777, true);
                        }

                        $file_ext = pathinfo($_FILES['signature_image']['name'], PATHINFO_EXTENSION);
                        $file_name = 'signature_' . $target_user_id . '_' . time() . '.' . $file_ext;
                        $target_path = $sig_dir . $file_name;

                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array(strtolower($file_ext), $allowed_types)) {
                            if (move_uploaded_file($_FILES['signature_image']['tmp_name'], $target_path)) {
                                // Delete old signature if exists
                                if (!empty($user['signature_image']) && file_exists($user['signature_image'])) {
                                    unlink($user['signature_image']);
                                }
                                $signature_image = $target_path;
                            }
                        }
                    }

                    if ($messageType !== 'danger') {
                        if ($passwordChanged) {
                            // Update with password change
                            $update_sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, department = ?, bio = ?, profile_image = ?, signature_image = ?, password = ?, role = ?, updated_at = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);

                            if (!$update_stmt) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }

                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_role = isAdmin() ? $role : $user['role'];
                            $update_stmt->bind_param("ssssssssssi", $full_name, $username, $email, $phone, $department, $bio, $profile_image, $signature_image, $hashed_password, $update_role, $target_user_id);
                        } else {
                            // Update without password change
                            $update_sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, department = ?, bio = ?, profile_image = ?, signature_image = ?, role = ?, updated_at = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);

                            if (!$update_stmt) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }

                            $update_role = isAdmin() ? $role : $user['role'];
                            $update_stmt->bind_param("sssssssssi", $full_name, $username, $email, $phone, $department, $bio, $profile_image, $signature_image, $update_role, $target_user_id);
                        }

                        $result = $update_stmt->execute();

                        if ($result) {
                            if (!$is_editing_other) {
                                // Update session data
                                $_SESSION['username'] = $username;
                                if (!empty($full_name)) {
                                    $_SESSION['full_name'] = $full_name;
                                }
                                // UPDATE: Set session images so they refresh immediately
                                $_SESSION['profile_image'] = $profile_image;
                                $_SESSION['signature_image'] = $signature_image;
                            }

                            $message = 'Profile updated successfully!';
                            $messageType = 'success';

                            // Refresh user data
                            $refresh_sql = "SELECT u.*, COUNT(a.id) as activity_count FROM users u LEFT JOIN activity_log a ON u.id = a.user_id WHERE u.id = ? GROUP BY u.id";
                            $refresh_stmt = $conn->prepare($refresh_sql);
                            if ($refresh_stmt) {
                                $refresh_stmt->bind_param("i", $target_user_id);
                                $refresh_stmt->execute();
                                $refresh_result = $refresh_stmt->get_result();
                                $user = $refresh_result->fetch_assoc();
                                $refresh_stmt->close();
                            }

                            // Log activity
                            $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, 'profile_updated', ?, ?)";
                            $log_stmt = $conn->prepare($log_sql);
                            if ($log_stmt) {
                                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                                $desc = $is_editing_other ? "Admin updated profile for user ID: $target_user_id" : "Updated profile information";
                                $log_stmt->bind_param("iss", $_SESSION['user_id'], $desc, $ip);
                                $log_stmt->execute();
                                $log_stmt->close();
                            }
                        } else {
                            $message = 'Failed to update profile.';
                            $messageType = 'danger';
                        }

                        if (isset($update_stmt)) {
                            $update_stmt->close();
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $message = 'Error updating profile: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }

    // Return JSON if it's an AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $messageType === 'success',
            'message' => $message,
            'profile_image' => $profile_image . '?v=' . time(), // Cache buster
            'signature_image' => $signature_image . '?v=' . time()
        ]);
        exit();
    }
}

$skip_navbar = false;
require_once 'views/partials/header.php';

// Get recent activity
$recent_activities = [];
try {
    $activity_sql = "SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    $activity_stmt = $conn->prepare($activity_sql);

    if ($activity_stmt) {
        $activity_stmt->bind_param("i", $_SESSION['user_id']);
        $activity_stmt->execute();
        $activity_result = $activity_stmt->get_result();
        $recent_activities = $activity_result->fetch_all(MYSQLI_ASSOC);
        $activity_stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
    $recent_activities = [];
}

// Define roles and departments
$roles = [
    'admin' => 'Administrator',
    'manager' => 'Manager',
    'user' => 'User',
    'stock_manager' => 'Stock Manager',
    'stock_controller' => 'Stock Controller',
    'tech_lead' => 'Tech Lead',
    'technician' => 'Technician',
    'driver' => 'Driver'
];

$departments = [
    'audio' => 'Audio',
    'video' => 'Video',
    'lighting' => 'Lighting',
    'translation' => 'Translation',
    'it' => 'IT',
    'rigging' => 'Rigging',
    'electrical' => 'Electrical',
    'furniture' => 'Furniture',
    'transport' => 'Transport',
    'admin' => 'Administration'
];
?>

<!-- Your existing HTML/CSS/JS code continues here... -->
<style>
    :root {
        --primary-color: #234c6a;
        --primary-light: #2c5a7a;
        --primary-dark: #1a3a4f;
        --secondary-color: #6c757d;
        --success-color: #28a745;
        --info-color: #17a2b8;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
    }

    /* Vertical Sidebar Layout (3:9 Ratio) */
    .profile-layout {
        display: grid;
        grid-template-columns: 3fr 9fr;
        gap: 2rem;
        max-width: 1500px;
        margin: 0 auto;
        padding: 2rem;
    }

    .profile-sidebar {
        position: sticky;
        top: 2rem;
        height: fit-content;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .sidebar-card {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
        text-align: center;
    }

    .profile-container {
        max-width: none;
        padding: 0;
        margin: 0;
    }

    .profile-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        border-radius: 24px;
        padding: 2.5rem;
        color: white;
        text-align: center;
        margin-bottom: 1.5rem;
        box-shadow: 0 15px 35px rgba(35, 76, 106, 0.2);
    }

    .profile-header h1 {
        font-size: 1.8rem;
        margin-top: 1rem;
    }

    .profile-content {
        display: block;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    @media (max-width: 992px) {
        .profile-layout {
            grid-template-columns: 1fr;
            padding: 1rem;
        }

        .profile-sidebar {
            position: relative;
            top: 0;
        }

        .sidebar-card {
            padding: 1.5rem;
        }
    }

    .profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .profile-avatar-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto 1rem;
    }

    .profile-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 600;
        color: var(--primary-color);
        border: 4px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }

    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-upload {
        position: absolute;
        bottom: 0;
        right: 0;
        background: var(--primary-dark);
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 2px solid white;
        transition: all 0.3s ease;
    }

    .avatar-upload:hover {
        transform: scale(1.1);
        background: var(--primary-light);
    }

    .avatar-upload i {
        color: white;
        font-size: 1rem;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(35, 76, 106, 0.1);
        border-color: var(--primary-color);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        background: rgba(35, 76, 106, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 1.5rem;
    }

    .stat-content h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-color);
        margin: 0;
        line-height: 1.2;
    }

    .stat-content p {
        color: var(--secondary-color);
        margin: 0;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Profile Content */
    .profile-content {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 1.5rem;
    }

    /* Sidebar Cards */
    .profile-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
        margin-bottom: 1.5rem;
    }

    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-title i {
        font-size: 1.2rem;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        color: var(--secondary-color);
        font-size: 0.9rem;
    }

    .info-value {
        font-weight: 500;
        color: #2c3e50;
    }

    .badge-role {
        background: rgba(35, 76, 106, 0.1);
        color: var(--primary-color);
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    /* Activity List */
    .activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .activity-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid #e9ecef;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 35px;
        height: 35px;
        background: rgba(35, 76, 106, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
    }

    .activity-details {
        flex: 1;
    }

    .activity-action {
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .activity-time {
        font-size: 0.8rem;
        color: var(--secondary-color);
    }

    /* Form Styles */
    .form-section {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
    }

    .form-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e9ecef;
    }

    .form-label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 0.6rem 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(35, 76, 106, 0.25);
    }

    .btn-primary {
        background: var(--primary-color);
        border: none;
        padding: 0.6rem 1.5rem;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(35, 76, 106, 0.3);
    }

    .btn-outline-secondary {
        border: 1px solid #e0e0e0;
        color: var(--secondary-color);
        padding: 0.6rem 1.5rem;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background: #f8f9fa;
        border-color: var(--secondary-color);
        color: #2c3e50;
    }

    .page-badge {
        background-color: rgba(35, 76, 106, 0.05);
        color: var(--primary-color) !important;
        border: 1px solid rgba(35, 76, 106, 0.1) !important;
        transition: all 0.3s ease;
        font-weight: 500;
        border-radius: 8px !important;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .page-badge:hover {
        background-color: var(--primary-color);
        color: white !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(35, 76, 106, 0.2);
        border-color: var(--primary-color) !important;
    }

    .page-badge i {
        font-size: 0.9rem;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .profile-content {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .profile-header {
            padding: 1.5rem;
        }
    }

    /* Alert Styles */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
    }

    /* Password Change Section */
    .password-section {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 1.5rem;
        margin-top: 1.5rem;
    }

    .password-section h6 {
        color: var(--primary-color);
        margin-bottom: 1rem;
    }

    /* Animations */
    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .glass-caption {
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.5rem;
        bottom: 40px;
        left: 10%;
        right: 10%;
        text-align: left;
    }

    .glass-caption h5 {
        font-weight: 700;
        color: #fff;
        margin-bottom: 0.5rem;
        font-size: 1.5rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .glass-caption p {
        color: rgba(255, 255, 255, 0.95);
        margin-bottom: 0;
        font-size: 1rem;
    }

    #stockManagementCarousel img {
        height: 450px;
        object-fit: cover;
    }

    .rounded-4 {
        border-radius: 1.25rem !important;
    }

    .carousel-indicators [data-bs-target] {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 5px;
    }

    /* Welcome Section */
    .welcome-section {
        background: linear-gradient(135deg, #234c6a 0%, #152e41 100%);
        border-radius: 24px;
        padding: 2.5rem;
        margin-bottom: 2.5rem;
        box-shadow: 0 15px 35px rgba(35, 76, 106, 0.2);
        position: relative;
        overflow: hidden;
    }

    .welcome-section::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
        border-radius: 50%;
    }

    .role-badge-large {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        color: white;
        padding: 8px 16px;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .role-badge-large i {
        color: #ffd700;
    }

    .permission-badge-admin {
        background: rgba(92, 131, 116, 0.2);
        color: #a8d5ba;
        border: 1px solid rgba(92, 131, 116, 0.3);
    }

    .btn-logout-glass {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        border-radius: 20px;
        padding: 8px 20px;
        font-weight: 500;
        transition: all 0.3s;
    }

    .btn-logout-glass:hover {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateY(-2px);
    }
</style>

<form method="POST" enctype="multipart/form-data" id="profileForm">
    <div class="profile-layout fade-in">
        <!-- Sidebar -->
        <div class="profile-sidebar">
            <!-- Main Sidebar Card -->
            <div class="sidebar-card">
                <div class="avatar-container mx-auto mb-3" style="width: 150px; height: 150px; position: relative;">
                    <div class="profile-avatar">
                        <?php if ($user['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" id="avatar_preview" alt="Profile">
                        <?php else: ?>
                            <span id="avatar_placeholder"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <label for="profile_image_input" class="avatar-upload shadow-sm">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profile_image_input" name="profile_image" style="display: none;" accept="image/*">
                </div>

                <h4 class="fw-bold mb-1" style="color: var(--primary-color);">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </h4>
                <div class="badge-role bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3 d-inline-block">
                    <i class="fas fa-shield-alt me-1"></i>
                    <?php echo $roles[$user['role']] ?? $user['role']; ?>
                </div>

                <hr class="opacity-10">

                <div class="text-start">
                    <div class="d-flex flex-column gap-2">
                        <?php
                        $allowed_pages_list = [];

                        // Always show Profile-related or Home if appropriate
                        // But let's follow the user's specific role requests

                        $user_role = getUserRole();

                        if ($user_role === 'admin') {
                            $allowed_pages_list = [
                                'Dashboard' => ['dashboard_full.php', 'fa-tachometer-alt'],
                                'Equipment' => ['items.php', 'fa-boxes'],
                                'Scanning' => ['scan_bulk.php', 'fa-qrcode'],
                                'Batch Approvals' => ['batch_history.php', 'fa-check-double'],
                                'User Management' => ['user_management.php', 'fa-users-cog'],
                                'Reports' => ['reports.php', 'fa-chart-bar']
                            ];
                        } elseif ($user_role === 'stock_controller') {
                            $allowed_pages_list = [
                                'Batch Approvals' => ['batch_history.php', 'fa-check-double'],
                            ];
                        } elseif ($user_role === 'technician') {
                            $allowed_pages_list = [
                                'Bulk Scanning' => ['scan_bulk.php', 'fa-qrcode'],
                                'My Batches' => ['technician_batch_history.php', 'fa-history'],
                            ];
                        } else {
                            // Default fallback
                            $allowed_pages_list = [
                                'Home' => ['index.php', 'fa-home'],
                            ];
                        }

                        foreach ($allowed_pages_list as $p_name => $p_data): ?>
                            <a href="<?php echo $p_data[0]; ?>" class="badge text-decoration-none p-3 page-badge text-start">
                                <i class="fas <?php echo $p_data[1]; ?> me-2"></i>
                                <?php echo $p_name; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>


        </div>

        <!-- Main Content Area -->
        <div class="profile-container">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <!-- Welcome Message with Role Badge -->
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <h2 class="text-white mb-0">
                                Welcome back, <span id="userFullName"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>!
                            </h2>
                        </div>
                        <p class="text-white-50 mb-0">
                            <span class="badge permission-badge-admin" style="font-size: 0.7rem; padding: 4px 8px;">
                                <i class="fas fa-file-alt me-1"></i>
                                <?php echo isAdmin() ? '22 Pages' : '8 Pages'; ?> Accessible
                            </span>
                        </p>
                    </div>

                    <!-- Role & Permissions -->
                    <div class="d-flex align-items-center gap-4">
                        <div class="text-end d-none d-md-block">
                            <div id="permissionBadges" class="d-flex gap-2 flex-wrap align-items-center" style="max-width: 400px; justify-content: flex-end;">

                                <span class="role-badge-large">
                                    <i class="fas fa-crown"></i>
                                    <span id="userRole"><?php echo $roles[$_SESSION['role']] ?? $_SESSION['role']; ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Admin Search Widget (Full Width of Content Area) -->
            <?php if (isAdmin()): ?>
                <div class="profile-card mb-4 border-0 shadow-sm" style="background: #f8f9fa; position: relative; z-index: 1051;">
                    <div class="row align-items-center g-0">
                        <div class="col-md-3 p-4 text-white text-center" style="background:#456882; border-radius:10px; color: white;">
                            <i class="fas fa-users-cog fa-2x mb-2"></i>
                            <h6 class="mb-0">Admin Controls</h6>
                            <p class="small opacity-75 mb-0">Platform Access</p>
                        </div>
                        <div class="col-md-9 p-4">
                            <label class="form-label fw-bold text-primary mb-2">Quick User Search</label>
                            <div class="input-group input-group-lg shadow-sm">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="admin_user_search" class="form-control border-start-0 ps-0" placeholder="Type name, email or technician ID...">
                                <div id="search_results" class="position-absolute w-100 shadow-lg rounded-bottom overflow-hidden" style="top: 100%; left: 0; z-index: 1050; display: none; background: white;">
                                    <!-- Results will appear here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="profile-card p-0 overflow-hidden border-0 shadow-lg">
                <div class="row g-0">
                    <!-- Left Status Column (Summary Info) -->
                    <div class="col-md-4 col-lg-3 border-end" style="background: #fdfdfd;">
                        <div class="p-4 h-100">
                            <div class="mb-5">
                                <h4 class="fw-bold mb-3" style="color: var(--primary-color);">
                                    <i class="fas fa-user-shield me-2 text-primary"></i>
                                    Account Settings
                                </h4>
                                <div class="d-flex align-items-center gap-2 mb-4">
                                    <?php if ($is_editing_other): ?>
                                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                                            <i class="fas fa-edit me-1"></i> Admin Edit Mode
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Personal Access
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-uppercase small fw-bold text-muted mb-3">Personal Information</h6>
                                <div class="info-item mb-3">
                                    <label class="small text-muted d-block mb-1">Department</label>
                                    <span class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?></span>
                                </div>
                                <div class="info-item mb-3">
                                    <label class="small text-muted d-block mb-1">Last Updated</label>
                                    <span class="text-secondary small"><?php echo $user['updated_at'] ? date('M j, Y', strtotime($user['updated_at'])) : 'Never'; ?></span>
                                </div>
                            </div>

                            <hr class="my-4 opacity-10">

                            <div class="mb-4">
                                <h6 class="text-uppercase small fw-bold text-muted mb-3">Digital Signature</h6>
                                <p class="small text-muted mb-3">Used for validating stock transfers and reports.</p>

                                <div id="signature_preview_container">
                                    <?php if (!empty($user['signature_image']) && file_exists($user['signature_image'])): ?>
                                        <div class="signature-preview mb-3 p-3 border rounded-3 bg-white text-center shadow-sm">
                                            <img src="<?php echo htmlspecialchars($user['signature_image']); ?>" alt="Signature" style="max-width: 100%; max-height: 80px; filter: grayscale(1) contrast(1.2);">
                                        </div>
                                        <p class="small text-success fw-medium mb-0" id="signature_status_text">
                                            <i class="fas fa-check-circle me-1"></i> Signature Active
                                        </p>
                                    <?php else: ?>
                                        <div class="mb-3 p-4 border rounded-3 border-dashed text-center bg-white">
                                            <i class="fas fa-signature fa-2x text-muted opacity-25 mb-2"></i>
                                            <p class="small text-muted mb-0" id="signature_status_text">Not uploaded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="button" id="signature_upload_btn" class="btn btn-sm btn-outline-primary mt-3 w-100 rounded-pill" onclick="document.getElementById('signature_image_input').click()">
                                    <i class="fas fa-pen-nib me-1"></i> Update Signature
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Action Column (Edit Form) -->
                    <div class="col-md-8 col-lg-9 bg-white">
                        <div class="p-4 p-lg-5">
                            <div class="d-flex align-items-center mb-4">
                                <h5 class="fw-bold mb-0">
                                    <i class="fas fa-user-edit me-2 text-primary"></i>
                                    Edit Profile Details
                                </h5>
                                <?php if ($is_editing_other): ?>
                                    <a href="profile.php" class="ms-auto btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fas fa-user-circle me-1"></i> My Profile
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 p-lg-5">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Full Name</label>
                                        <input type="text" class="form-control form-control-lg bg-light border-0 px-4" name="full_name"
                                            value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                            placeholder="Enter your full name">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Username *</label>
                                        <input type="text" class="form-control form-control-lg bg-light border-0 px-4" name="username"
                                            value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Email *</label>
                                        <input type="email" class="form-control form-control-lg bg-light border-0 px-4" name="email"
                                            value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Phone Number</label>
                                        <input type="tel" class="form-control form-control-lg bg-light border-0 px-4" name="phone"
                                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                            placeholder="+250 XXX XXX XXX">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Department</label>
                                        <select class="form-select form-select-lg bg-light border-0 px-4" name="department">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $key => $value): ?>
                                                <?php 
                                                $user_dept = strtolower($user['department'] ?? '');
                                                $key_lower = strtolower($key);
                                                $val_lower = strtolower($value);
                                                $is_selected = ($user_dept === $key_lower || $user_dept === $val_lower || (!empty($user_dept) && strpos($user_dept, $key_lower) !== false));
                                                ?>
                                                <option value="<?php echo $key; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                                    <?php echo $value; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Role</label>
                                        <?php if (isAdmin()): ?>
                                            <select class="form-select form-select-lg bg-light border-0 px-4" name="role">
                                                <?php foreach ($roles as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo ($user['role'] ?? '') == $key ? 'selected' : ''; ?>>
                                                        <?php echo $value; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" class="form-control form-control-lg bg-light border-0 px-4"
                                                value="<?php echo $roles[$user['role']] ?? $user['role']; ?>" readonly>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-medium">Bio</label>
                                        <textarea class="form-control bg-light border-0 px-4 py-3" name="bio" rows="3"
                                            placeholder="Tell us a little about yourself"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-4 rounded-4 bg-light bg-opacity-50 border border-light shadow-sm">
                                            <h6 class="fw-bold text-primary mb-3">
                                                <i class="fas fa-lock me-2"></i>
                                                Security & Password
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label small">Current Password</label>
                                                    <input type="password" class="form-control border-0 px-3" name="current_password"
                                                        <?php if ($is_editing_other) echo 'disabled placeholder="••••••••"'; ?>>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">New Password</label>
                                                    <input type="password" class="form-control border-0 px-3" name="new_password">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">Confirm</label>
                                                    <input type="password" class="form-control border-0 px-3" name="confirm_password">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 text-end mt-4">
                                        <hr class="mb-4 opacity-5">
                                        <div class="d-flex gap-3 justify-content-end align-items-center">
                                            <button type="button" class="btn btn-link text-muted text-decoration-none px-4" onclick="window.location.href='dashboard_full.php'">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-sm">
                                                <i class="fas fa-save me-2"></i> Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- End of form fields -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Coming Soon: Stock Management Carousel -->


    <!-- Signature hidden input -->
    <input type="file" name="signature_image" id="signature_image_input" style="display: none;" accept="image/*">
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // Centered toast helper
        function showCenteredToast(message, type) {
            let toastContainer = document.getElementById('centered-toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'centered-toast-container';
                toastContainer.className = 'toast-container position-fixed top-50 start-50 translate-middle p-3';
                toastContainer.style.zIndex = '1055';
                document.body.appendChild(toastContainer);
            }

            const toastId = 'toast-' + Date.now();
            const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
            const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 300px;">
                    <div class="d-flex">
                        <div class="toast-body fw-medium px-4 py-3 fs-6">
                            <i class="fas ${iconClass} me-2 fa-lg"></i> ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = document.getElementById(toastId);

            try {
                const toast = new bootstrap.Toast(toastElement, {
                    delay: 3000
                });
                toast.show();

                toastElement.addEventListener('hidden.bs.toast', function() {
                    toastElement.remove();
                });
            } catch (err) {
                console.error("Toast failed, falling back to alert:", err);
                alert(message);
            }
        }

        // User Search functionality
        const searchInput = document.getElementById('admin_user_search');
        const searchResults = document.getElementById('search_results');

        if (searchInput) {
            // Using 'keyup' and 'input' for maximum compatibility with browsers like Maxthon
            const triggerSearch = function() {
                const query = searchInput.value.trim();
                console.log("Triggering search for:", query);
                console.log("Search query:", query); // Debug for Maxthon

                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }

                // Using 'q' to match the browser's observed behavior
                fetch(`api/search_users.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        console.log("Search data received:", data);
                        if (data.length > 0) {
                            let html = '';
                            data.forEach(u => {
                                html += `
                                    <a href="profile.php?id=${u.id}" class="d-flex align-items-center p-3 text-decoration-none border-bottom search-item">
                                        <div class="rounded-circle bg-primary text-white me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px;">
                                            ${u.full_name ? u.full_name[0].toUpperCase() : u.username[0].toUpperCase()}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">${u.full_name || u.username}</div>
                                            <div class="small text-muted">${u.role} • ${u.email}</div>
                                        </div>
                                        <i class="fas fa-chevron-right ms-auto text-muted opacity-50"></i>
                                    </a>
                                `;
                            });
                            searchResults.innerHTML = html;
                            searchResults.style.display = 'block';
                        } else {
                            searchResults.innerHTML = '<div class="p-3 text-muted text-center">No users found</div>';
                            searchResults.style.display = 'block';
                        }
                    })
                    .catch(err => console.error("Search fetch error:", err));
            };

            searchInput.addEventListener('input', triggerSearch);
            searchInput.addEventListener('keyup', triggerSearch);

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        }

        // Profile Form AJAX Submission
        let lastTriggerElement = null;
        const profileForm = document.getElementById('profileForm');

        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                try {
                    const triggerElement = lastTriggerElement || e.submitter || this.querySelector('button[type="submit"]');
                    lastTriggerElement = null; // reset

                    const newPassword = document.querySelector('input[name="new_password"]').value;
                    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
                    const currentPasswordInput = document.querySelector('input[name="current_password"]');
                    const currentPassword = currentPasswordInput ? currentPasswordInput.value : '';
                    const isCurrentDisabled = currentPasswordInput && currentPasswordInput.disabled;

                    if (newPassword || confirmPassword || (!isCurrentDisabled && currentPassword && currentPassword.trim() !== '')) {
                        if (currentPasswordInput && !isCurrentDisabled && !currentPassword) {
                            showCenteredToast('Current password is required to change password', 'danger');
                            return false;
                        }
                        if (newPassword !== confirmPassword) {
                            showCenteredToast('New passwords do not match', 'danger');
                            return false;
                        }
                        if (newPassword.length < 6) {
                            showCenteredToast('New password must be at least 6 characters long', 'danger');
                            return false;
                        }
                    }

                    const isButton = triggerElement.tagName === 'BUTTON';
                    let originalText = '';
                    if (isButton) {
                        originalText = triggerElement.innerHTML;
                        triggerElement.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
                        triggerElement.disabled = true;
                    }

                    const formData = new FormData(this);
                    console.log("Submitting Profile Form. Files:", {
                        profile: formData.get('profile_image'),
                        signature: formData.get('signature_image')
                    });

                    fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.json();
                        })
                        .then(data => {
                            if (isButton) {
                                triggerElement.innerHTML = originalText;
                                triggerElement.disabled = false;
                            }

                            if (data.success) {
                                showCenteredToast(data.message || 'Profile updated successfully!', 'success');

                                // Update all profile images on the page (including navbar)
                                if (data.profile_image) {
                                    console.log("Updating profile images with:", data.profile_image);
                                    const avatars = document.querySelectorAll('.profile-avatar img, .nav-profile-img, .navbar .avatar-img, #avatar_preview');
                                    avatars.forEach(img => {
                                        img.src = data.profile_image;
                                    });

                                    // Also update the preview container if it was empty
                                    const previewContainer = document.querySelector('.profile-avatar');
                                    const placeholder = document.getElementById('avatar_placeholder');
                                    if (placeholder) placeholder.style.display = 'none';

                                    if (previewContainer && !previewContainer.querySelector('img')) {
                                        previewContainer.innerHTML = `<img src="${data.profile_image}" id="avatar_preview" alt="Profile">`;
                                    }
                                }

                                // Update signature display
                                if (data.signature_image) {
                                    console.log("Updating signature image with:", data.signature_image);
                                    const sigPreviewContainer = document.getElementById('signature_preview_container');
                                    if (sigPreviewContainer) {
                                        sigPreviewContainer.innerHTML = `
                                            <div class="signature-preview mb-3 p-3 border rounded-3 bg-white text-center shadow-sm">
                                                <img src="${data.signature_image}" alt="Signature" style="max-width: 100%; max-height: 80px; filter: grayscale(1) contrast(1.2);">
                                            </div>
                                            <p class="small text-success fw-medium mb-0" id="signature_status_text">
                                                <i class="fas fa-check-circle me-1"></i> Signature Active
                                            </p>
                                        `;
                                    }
                                }

                                // Clear password fields
                                const pwdFields = document.querySelectorAll('input[type="password"]');
                                pwdFields.forEach(f => f.value = '');
                            } else {
                                showCenteredToast(data.message || 'Failed to update profile.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            if (isButton) {
                                triggerElement.innerHTML = originalText;
                                triggerElement.disabled = false;
                            }
                            showCenteredToast('An error occurred while saving. Please try again.', 'danger');
                        });
                } catch (generalError) {
                    console.error('JS Error:', generalError);
                    alert("A JavaScript error occurred: " + generalError.message);
                }
            });
        }

        // Image triggers
        const profileInput = document.getElementById('profile_image_input');
        if (profileInput) {
            profileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    console.log("Profile image selected, triggering submit...");
                    lastTriggerElement = document.querySelector('.avatar-upload');
                    if (typeof profileForm.requestSubmit === 'function') {
                        profileForm.requestSubmit();
                    } else {
                        profileForm.dispatchEvent(new Event('submit', {
                            cancelable: true,
                            bubbles: true
                        }));
                    }
                }
            });
        }

        const signatureInput = document.getElementById('signature_image_input');
        if (signatureInput) {
            signatureInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    console.log("Signature image selected, triggering submit...");
                    lastTriggerElement = document.getElementById('signature_upload_btn');
                    if (typeof profileForm.requestSubmit === 'function') {
                        profileForm.requestSubmit();
                    } else {
                        profileForm.dispatchEvent(new Event('submit', {
                            cancelable: true,
                            bubbles: true
                        }));
                    }
                }
            });
        }
    });
</script>

<?php require_once 'views/partials/footer.php'; ?>