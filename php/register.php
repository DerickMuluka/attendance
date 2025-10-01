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
    
    // Validate required fields
    $requiredFields = ['username', 'email', 'password', 'confirmPassword', 'fullName'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "$field is required"]);
            exit;
        }
    }
    
    $username = sanitizeInput($data['username']);
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    $confirmPassword = $data['confirmPassword'];
    $fullName = sanitizeInput($data['fullName']);
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : null;
    $department = isset($data['department']) ? sanitizeInput($data['department']) : null;
    
    // Validate email format
    if (!isValidEmail($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
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
    
    // Check if passwords match
    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, department) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword, $fullName, $phone, $department]);
    
    $userId = $pdo->lastInsertId();
    
    // Create default preferences for user
    $prefStmt = $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
    $prefStmt->execute([$userId]);
    
    // Log registration activity
    logActivity($userId, 'registration', 'New user registered');
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'User registered successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>