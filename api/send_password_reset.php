<?php
// api/send_password_reset.php
require_once '../bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check permissions
$allowedRoles = ['admin', 'manager', 'tech_lead'];
$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if (!in_array($userRole, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Include database connection
require_once '../includes/database_fix.php';

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Get user/technician ID
    $userId = (int)($_POST['user_id'] ?? 0);

    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        exit();
    }

    // Get user email
    $userStmt = $conn->prepare("SELECT email, username, role as type FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $result = $userStmt->get_result();
    $user = $result->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Store reset token
    $tokenStmt = $conn->prepare("
        INSERT INTO password_resets (user_id, token, expires_at, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $tokenStmt->bind_param("iss", $userId, $token, $expires);
    $tokenStmt->execute();
    $tokenStmt->close();

    // Send email (you'll need to configure your email settings)
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/ability_app_main/reset_password.php?token=" . $token;

    $to = $user['email'];
    $subject = "Password Reset Request";
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <h2>Password Reset Request</h2>
        <p>Hello {$user['username']},</p>
        <p>You have requested to reset your password. Click the link below to reset it:</p>
        <p><a href='{$resetLink}'>Reset Password</a></p>
        <p>This link will expire in 24 hours.</p>
        <p>If you didn't request this, please ignore this email.</p>
        <br>
        <p>Best regards,<br>aBility Team</p>
    </body>
    </html>
    ";

    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: noreply@ability.com' . "\r\n";

    if (mail($to, $subject, $message, $headers)) {
        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address) 
            VALUES (?, 'password_reset_sent', ?, ?)
        ");
        $description = "Password reset email sent to {$user['username']}";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $logStmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(['success' => true, 'message' => 'Password reset email sent']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send email']);
    }

    $conn->close();
} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
