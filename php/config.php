<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// JWT Secret key for token generation
define('JWT_SECRET', 'your-super-secret-jwt-key-here-change-in-production');
define('JWT_ALGORITHM', 'HS256');

// Application settings
define('BASE_URL', 'http://localhost/attendance-system/');
define('SITE_NAME', 'AttendancePro');

// Email settings (for password reset)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-email-password');
define('FROM_EMAIL', 'noreply@attendancepro.com');
define('FROM_NAME', 'AttendancePro System');

// QR Code settings
define('QR_VALIDITY_MINUTES', 5); // QR code validity in minutes
define('QR_REGENERATION_MINUTES', 1); // QR code regeneration interval

// Geolocation settings (in meters)
define('ALLOWED_RADIUS', 100); // Maximum allowed distance from designated location

// Time settings
define('LATE_THRESHOLD', '09:15:00'); // Time after which attendance is marked late
define('WORK_START_TIME', '08:00:00');
define('WORK_END_TIME', '17:00:00');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection error']);
        exit;
    }
}

// Generate JWT token
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
    $payload['exp'] = time() + (7 * 24 * 60 * 60); // 7 days expiration
    $payload = json_encode($payload);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

// Verify JWT token
function verifyJWT($token) {
    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        $signatureProvided = $parts[2];
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($base64UrlSignature !== $signatureProvided) {
            return false;
        }
        
        $payload = json_decode($payload, true);
        
        // Check if token is expired
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    } catch (Exception $e) {
        error_log("JWT verification failed: " . $e->getMessage());
        return false;
    }
}

// Get authenticated user from token
function getAuthenticatedUser() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $payload = verifyJWT($token);
        
        if ($payload && isset($payload['user_id'])) {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, status, avatar FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                return $user;
            }
        }
    }
    
    return null;
}

// Check if user is authenticated
function isAuthenticated() {
    return getAuthenticatedUser() !== null;
}

// Check if user is admin
function isAdmin() {
    $user = getAuthenticatedUser();
    return $user && $user['role'] === 'admin';
}

// Require authentication
function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
}

// Require admin privileges
function requireAdmin() {
    requireAuth();
    
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
        exit;
    }
}

// Generate random token for password reset
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Sanitize input data
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Calculate distance between two coordinates (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $latDelta = $lat2 - $lat1;
    $lonDelta = $lon2 - $lon1;
    
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));
    
    return $angle * $earthRadius;
}

// Determine attendance status based on time
function getAttendanceStatus($time) {
    $currentTime = strtotime($time);
    $lateThreshold = strtotime(LATE_THRESHOLD);
    $workStart = strtotime(WORK_START_TIME);
    
    if ($currentTime < $workStart) {
        return 'Early';
    } elseif ($currentTime <= $lateThreshold) {
        return 'Present';
    } else {
        return 'Late';
    }
}

// Send email function
function sendEmail($to, $subject, $body) {
    // In a real implementation, you would use PHPMailer or similar
    // This is a placeholder implementation
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

// Log activity
function logActivity($userId, $action, $details = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $details]);
        return true;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

// Get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Rate limiting function
function checkRateLimit($key, $limit = 5, $timeout = 60) {
    $pdo = getDBConnection();
    $ip = getClientIP();
    $now = time();
    
    // Clean up old entries
    $pdo->prepare("DELETE FROM rate_limits WHERE timestamp < ?")
        ->execute([$now - $timeout]);
    
    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE ip = ? AND action_key = ? AND timestamp > ?");
    $stmt->execute([$ip, $key, $now - $timeout]);
    $result = $stmt->fetch();
    
    if ($result['count'] >= $limit) {
        return false;
    }
    
    // Log this attempt
    $pdo->prepare("INSERT INTO rate_limits (ip, action_key, timestamp) VALUES (?, ?, ?)")
        ->execute([$ip, $key, $now]);
    
    return true;
}
?>