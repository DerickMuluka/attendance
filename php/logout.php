<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $user = getAuthenticatedUser();
    
    if ($user) {
        $pdo = getDBConnection();
        
        // Delete remember me tokens
        if (isset($_COOKIE['remember_me'])) {
            $token = $_COOKIE['remember_me'];
            $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
            $stmt->execute([$token]);
            
            // Clear remember me cookie
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        }
        
        // Log logout activity
        logActivity($user['id'], 'logout', 'User logged out');
    }
    
    // Clear any existing output
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful'
    ]);
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>