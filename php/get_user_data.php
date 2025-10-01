<?php
require_once 'config.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, phone, department, avatar, role FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            jsonResponse(true, 'User data retrieved', ['user' => $user]);
        } else {
            jsonResponse(false, 'User not found');
        }
        
    } catch(PDOException $e) {
        jsonResponse(false, 'Database error occurred');
    }
} else {
    jsonResponse(false, 'Invalid request method');
}
?>