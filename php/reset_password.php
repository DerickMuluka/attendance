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
    
    if (!isset($data['token']) || empty(trim($data['token']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Reset token is required']);
        exit;
    }
    
    if (!isset($data['password']) || empty(trim($data['password']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        exit;
    }
    
    $token = sanitizeInput($data['token']);
    $password = $data['password'];
    
    // Validate password strength
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter']);
        exit;
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Get reset token
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();
    
    if (!$resetRequest) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user password
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $updateStmt->execute([$hashedPassword, $resetRequest['email']]);
    
    // Delete used reset token
    $deleteStmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
    $deleteStmt->execute([$token]);
    
    // Get user for logging
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute([$resetRequest['email']]);
    $user = $userStmt->fetch();
    
    if ($user) {
        // Log password reset
        logActivity($user['id'], 'password_reset', 'Password reset successfully');
        
        // Invalidate all existing sessions
        $deleteTokensStmt = $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?");
        $deleteTokensStmt->execute([$user['id']]);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>