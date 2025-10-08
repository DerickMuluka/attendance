<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    
    // Check if preferences already exist
    $checkStmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
    $checkStmt->execute([$user['id']]);
    $existingPrefs = $checkStmt->fetch();
    
    $allowedFields = ['theme', 'email_notifications', 'language', 'two_factor'];
    $updateFields = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            
            // Handle boolean values
            if (is_bool($data[$field])) {
                $params[] = $data[$field] ? 1 : 0;
            } else {
                $params[] = sanitizeInput($data[$field]);
            }
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid preferences to update']);
        exit;
    }
    
    if ($existingPrefs) {
        // Update existing preferences
        $query = "UPDATE user_preferences SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE user_id = ?";
        $params[] = $user['id'];
    } else {
        // Insert new preferences
        $query = "INSERT INTO user_preferences (user_id, " . implode(', ', $allowedFields) . ") VALUES (?" . str_repeat(', ?', count($allowedFields)) . ")";
        
        // Add user_id as first parameter
        array_unshift($params, $user['id']);
        
        // Add default values for missing fields
        foreach ($allowedFields as $field) {
            if (!isset($data[$field])) {
                // Set default values
                $defaults = [
                    'theme' => 'auto',
                    'email_notifications' => 1,
                    'language' => 'en',
                    'two_factor' => 0
                ];
                $params[] = $defaults[$field];
            }
        }
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Get updated preferences
    $prefStmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $prefStmt->execute([$user['id']]);
    $preferences = $prefStmt->fetch() ?: [];
    
    // Log preferences update
    logActivity($user['id'], 'preferences_update', 'User preferences updated');
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Preferences saved successfully',
        'preferences' => $preferences
    ]);
    
} catch (Exception $e) {
    error_log("Save preferences error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>