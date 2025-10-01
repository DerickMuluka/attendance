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
    
    $format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'json';
    $startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : null;
    $endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : null;
    
    // Build query
    $query = "
        SELECT 
            a.timestamp,
            a.status,
            a.latitude,
            a.longitude,
            a.accuracy,
            u.full_name,
            u.department,
            u.email
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
    
    $query .= " ORDER BY a.timestamp DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Format data based on requested format
    switch ($format) {
        case 'csv':
            exportCSV($data, $user, $startDate, $endDate);
            break;
        case 'pdf':
            exportPDF($data, $user, $startDate, $endDate);
            break;
        case 'json':
        default:
            exportJSON($data, $user, $startDate, $endDate);
            break;
    }
    
    // Log export activity
    logActivity($user['id'], 'export_data', "Exported $format report from $startDate to $endDate");
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function exportJSON($data, $user, $startDate, $endDate) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Ymd_His') . '.json"');
    
    echo json_encode([
        'success' => true,
        'user' => [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'department' => $user['department']
        ],
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'data' => $data,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

function exportCSV($data, $user, $startDate, $endDate) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add header information
    fputcsv($output, ['Attendance Report']);
    fputcsv($output, ['User:', $user['full_name']]);
    fputcsv($output, ['Email:', $user['email']]);
    fputcsv($output, ['Department:', $user['department']]);
    fputcsv($output, ['Period:', "$startDate to $endDate"]);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Add data headers
    fputcsv($output, ['Date', 'Time', 'Status', 'Latitude', 'Longitude', 'Accuracy']);
    
    // Add data rows
    foreach ($data as $row) {
        $timestamp = strtotime($row['timestamp']);
        fputcsv($output, [
            date('Y-m-d', $timestamp),
            date('H:i:s', $timestamp),
            $row['status'],
            $row['latitude'],
            $row['longitude'],
            $row['accuracy']
        ]);
    }
    
    fclose($output);
}

function exportPDF($data, $user, $startDate, $endDate) {
    // This would require a PDF library like TCPDF or Dompdf
    // For now, we'll return JSON with a message
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'PDF export functionality requires additional libraries. Please use JSON or CSV format.',
        'data' => $data
    ]);
}
?>