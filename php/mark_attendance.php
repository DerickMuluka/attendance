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

requireAuth();

try {
    $user = getAuthenticatedUser();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['qr_data']) || !isset($data['latitude']) || !isset($data['longitude'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'QR data and location coordinates are required']);
        exit;
    }
    
    $qrData = sanitizeInput($data['qr_data']);
    $latitude = (float)$data['latitude'];
    $longitude = (float)$data['longitude'];
    $accuracy = isset($data['accuracy']) ? (float)$data['accuracy'] : null;
    
    // Validate coordinates
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Check if QR data is valid and not expired
    // In a real implementation, you would validate against a generated QR code
    // For now, we'll assume the QR data contains a timestamp
    $qrTimestamp = strtotime($qrData);
    $currentTime = time();
    
    if ($currentTime - $qrTimestamp > (QR_VALIDITY_MINUTES * 60)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'QR code has expired']);
        exit;
    }
    
    // Check if user has already marked attendance today
    $today = date('Y-m-d');
    $checkStmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND DATE(timestamp) = ?");
    $checkStmt->execute([$user['id'], $today]);
    
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Attendance already marked for today']);
        exit;
    }
    
    // Define allowed location coordinates (in a real app, this would be configurable)
    $allowedLatitude = -1.2921; // Example: Nairobi coordinates
    $allowedLongitude = 36.8219;
    
    // Calculate distance from allowed location
    $distance = calculateDistance($latitude, $longitude, $allowedLatitude, $allowedLongitude);
    
    if ($distance > ALLOWED_RADIUS) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'You are too far from the allowed location',
            'distance' => round($distance, 2)
        ]);
        exit;
    }
    
    // Determine attendance status based on current time
    $currentTimeFormatted = date('H:i:s');
    $status = getAttendanceStatus($currentTimeFormatted);
    
    // Insert attendance record
    $insertStmt = $pdo->prepare("
        INSERT INTO attendance (user_id, qr_data, latitude, longitude, accuracy, status) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([$user['id'], $qrData, $latitude, $longitude, $accuracy, $status]);
    
    $attendanceId = $pdo->lastInsertId();
    
    // Get the created attendance record
    $attendanceStmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.department 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ?
    ");
    $attendanceStmt->execute([$attendanceId]);
    $attendanceRecord = $attendanceStmt->fetch();
    
    // Log attendance activity
    logActivity($user['id'], 'attendance_marked', "Attendance marked as $status");
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => "Attendance marked successfully as $status",
        'attendance' => $attendanceRecord,
        'distance' => round($distance, 2)
    ]);
    
} catch (Exception $e) {
    error_log("Mark attendance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>