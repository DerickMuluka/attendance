<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || empty(trim($data['email']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    $email = sanitizeInput($data['email']);
    
    if (!isValidEmail($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Check rate limiting
    if (!checkRateLimit('password_reset_' . $email, 3, 3600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many reset attempts. Please try again later.']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Always return success to prevent email enumeration
    if (!$user) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent'
        ]);
        exit;
    }
    
    // Generate reset token
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour expiration
    
    // Delete any existing tokens for this email
    $deleteStmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
    $deleteStmt->execute([$email]);
    
    // Insert new token
    $insertStmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $insertStmt->execute([$email, $token, $expiresAt]);
    
    // Create reset link
    $resetLink = BASE_URL . "reset_password.html?token=" . urlencode($token);
    
    // Create email content
    $subject = "Password Reset Request - " . SITE_NAME;
    $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; padding: 12px 24px; background: #4361ee; color: white; text-decoration: none; border-radius: 5px; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . SITE_NAME . "</h1>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                    <p>You requested to reset your password. Click the button below to create a new password:</p>
                    <p style='text-align: center;'>
                        <a href='$resetLink' class='button'>Reset Password</a>
                    </p>
                    <p>If you didn't request this reset, please ignore this email. Your password will remain unchanged.</p>
                    <p><strong>Note:</strong> This link will expire in 1 hour for security reasons.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    // Send email
    $emailSent = sendEmail($email, $subject, $body);
    
    if (!$emailSent) {
        error_log("Failed to send password reset email to: $email");
    }
    
    // Log password reset request
    logActivity($user['id'], 'password_reset_request', 'Password reset requested');
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'If the email exists, a password reset link has been sent'
    ]);
    
} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>