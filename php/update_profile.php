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
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Build update query dynamically based on provided fields
    $updateFields = [];
    $params = [];
    
    $allowedFields = ['full_name', 'phone', 'department', 'avatar'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = sanitizeInput($data[$field]);
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
        exit;
    }
    
    $params[] = $user['id'];
    
    $query = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Get updated user data
    $userStmt = $pdo->prepare("SELECT id, username, email, full_name, phone, department, avatar, role FROM users WHERE id = ?");
    $userStmt->execute([$user['id']]);
    $updatedUser = $userStmt->fetch();
    
    // Log profile update
    logActivity($user['id'], 'profile_update', 'Profile information updated');
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $updatedUser
    ]);
    
} catch (Exception $e) {
    error_log("Update profile error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>