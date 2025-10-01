<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireAuth();

try {
    $user = getAuthenticatedUser();
    $pdo = getDBConnection();
    
    $startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-01');
    $endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
    $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'monthly';
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date']);
        exit;
    }
    
    // Generate report based on type
    switch ($type) {
        case 'daily':
            $report = generateDailyReport($pdo, $user['id'], $startDate, $endDate);
            break;
        case 'weekly':
            $report = generateWeeklyReport($pdo, $user['id'], $startDate, $endDate);
            break;
        case 'monthly':
        default:
            $report = generateMonthlyReport($pdo, $user['id'], $startDate, $endDate);
            break;
    }
    
    // Log report generation
    logActivity($user['id'], 'report_generated', "$type report generated from $startDate to $endDate");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'report' => $report,
        'parameters' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'type' => $type
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Generate report error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function generateDailyReport($pdo, $userId, $startDate, $endDate) {
    $query = "
        SELECT 
            DATE(timestamp) as date,
            COUNT(*) as total_entries,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'Early' THEN 1 ELSE 0 END) as early,
            MIN(TIME(timestamp)) as first_checkin,
            MAX(TIME(timestamp)) as last_checkout
        FROM attendance 
        WHERE user_id = ? 
        AND DATE(timestamp) BETWEEN ? AND ?
        GROUP BY DATE(timestamp)
        ORDER BY date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId, $startDate, $endDate]);
    return $stmt->fetchAll();
}

function generateWeeklyReport($pdo, $userId, $startDate, $endDate) {
    $query = "
        SELECT 
            YEAR(timestamp) as year,
            WEEK(timestamp) as week,
            COUNT(*) as total_entries,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'Early' THEN 1 ELSE 0 END) as early,
            MIN(timestamp) as week_start,
            MAX(timestamp) as week_end
        FROM attendance 
        WHERE user_id = ? 
        AND DATE(timestamp) BETWEEN ? AND ?
        GROUP BY YEAR(timestamp), WEEK(timestamp)
        ORDER BY year DESC, week DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId, $startDate, $endDate]);
    return $stmt->fetchAll();
}

function generateMonthlyReport($pdo, $userId, $startDate, $endDate) {
    $query = "
        SELECT 
            YEAR(timestamp) as year,
            MONTH(timestamp) as month,
            COUNT(*) as total_entries,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'Early' THEN 1 ELSE 0 END) as early,
            MIN(timestamp) as month_start,
            MAX(timestamp) as month_end
        FROM attendance 
        WHERE user_id = ? 
        AND DATE(timestamp) BETWEEN ? AND ?
        GROUP BY YEAR(timestamp), MONTH(timestamp)
        ORDER BY year DESC, month DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId, $startDate, $endDate]);
    return $stmt->fetchAll();
}
?>