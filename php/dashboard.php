<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo jsonResponse(false, 'Please login first');
    exit;
}

// Get dashboard statistics
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        $user_id = $_SESSION['user_id'];
        
        // Get today's attendance status
        $today = date('Y-m-d');
        $attendanceStmt = $pdo->prepare("SELECT status FROM attendance WHERE user_id = :user_id AND DATE(timestamp) = :today");
        $attendanceStmt->execute(['user_id' => $user_id, 'today' => $today]);
        $todayAttendance = $attendanceStmt->fetch();
        
        // Get monthly statistics
        $monthStart = date('Y-m-01');
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM (
                SELECT DISTINCT DATE(timestamp) as date, status
                FROM attendance 
                WHERE user_id = :user_id AND timestamp >= :month_start
            ) as daily_attendance
        ");
        $statsStmt->execute(['user_id' => $user_id, 'month_start' => $monthStart]);
        $monthlyStats = $statsStmt->fetch();
        
        // Get recent activity
        $activityStmt = $pdo->prepare("
            SELECT timestamp, status, latitude, longitude 
            FROM attendance 
            WHERE user_id = :user_id 
            ORDER BY timestamp DESC 
            LIMIT 5
        ");
        $activityStmt->execute(['user_id' => $user_id]);
        $recentActivity = $activityStmt->fetchAll();
        
        // Get user information
        $userStmt = $pdo->prepare("SELECT full_name, username, email, department FROM users WHERE id = :user_id");
        $userStmt->execute(['user_id' => $user_id]);
        $userInfo = $userStmt->fetch();
        
        echo jsonResponse(true, 'Dashboard data retrieved', [
            'today_attendance' => $todayAttendance,
            'monthly_stats' => $monthlyStats,
            'recent_activity' => $recentActivity,
            'user_info' => $userInfo
        ]);
        
    } catch(PDOException $e) {
        error_log("Dashboard error: " . $e->getMessage());
        echo jsonResponse(false, 'Database error occurred');
    }
} else {
    http_response_code(405);
    echo jsonResponse(false, 'Method not allowed');
}
?>
