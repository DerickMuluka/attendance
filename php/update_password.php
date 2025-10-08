<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireAuth();

try {
    $user = getAuthenticatedUser();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['current_password']) || !isset($data['new_password']) || !isset($data['confirm_password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password, new password, and confirmation are required']);
        exit;
    }
    
    $currentPassword = $data['current_password'];
    $newPassword = $data['new_password'];
    $confirmPassword = $data['confirm_password'];
    
    // Validate new password
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long']);
        exit;
    }
    
    if (!preg_match('/[A-Z]/', $newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must contain at least one uppercase letter']);
        exit;
    }
    
    if (!preg_match('/[0-9]/', $newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must contain at least one number']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $dbUser = $stmt->fetch();
    
    if (!password_verify($currentPassword, $dbUser['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$hashedPassword, $user['id']]);
    
    // Invalidate all existing sessions and tokens
    $deleteStmt = $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?");
    $deleteStmt->execute([$user['id']]);
    
    // Log password change
    logActivity($user['id'], 'password_change', 'Password changed successfully');
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Update password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>