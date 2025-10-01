<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireAuth();

try {
    $user = getAuthenticatedUser();
    $pdo = getDBConnection();
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    
    $startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : null;
    $endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : null;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
    
    // Build query
    $query = "
        SELECT a.*, u.full_name, u.department 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.user_id = ?
    ";
    
    $params = [$user['id']];
    
    if ($startDate) {
        $query .= " AND DATE(a.timestamp) >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $query .= " AND DATE(a.timestamp) <= ?";
        $params[] = $endDate;
    }
    
    if ($status && in_array($status, ['Present', 'Late', 'Early'])) {
        $query .= " AND a.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY a.timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $attendance = $stmt->fetchAll();
    
    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM attendance a 
        WHERE a.user_id = ?
    ";
    
    $countParams = [$user['id']];
    
    if ($startDate) {
        $countQuery .= " AND DATE(a.timestamp) >= ?";
        $countParams[] = $startDate;
    }
    
    if ($endDate) {
        $countQuery .= " AND DATE(a.timestamp) <= ?";
        $countParams[] = $endDate;
    }
    
    if ($status) {
        $countQuery .= " AND a.status = ?";
        $countParams[] = $status;
    }
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch()['total'];
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'Early' THEN 1 ELSE 0 END) as early_days,
            MIN(timestamp) as first_record,
            MAX(timestamp) as last_record
        FROM attendance 
        WHERE user_id = ?
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $attendance,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ],
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Get attendance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>