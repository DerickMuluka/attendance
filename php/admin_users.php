<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo jsonResponse(false, 'Unauthorized access');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Get users list
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $search = isset($_GET['search']) ? "%{$_GET['search']}%" : '%';
        $status = isset($_GET['status']) ? $_GET['status'] : '%';
        $role = isset($_GET['role']) ? $_GET['role'] : '%';
        
        // Get users with filters
        $stmt = $pdo->prepare("
            SELECT u.*, d.name as department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search)
            AND u.status LIKE :status 
            AND u.role LIKE :role 
            ORDER BY u.created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':search', $search);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':role', $role);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        // Get total count for pagination
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM users u 
            WHERE (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search)
            AND u.status LIKE :status 
            AND u.role LIKE :role
        ");
        
        $countStmt->bindValue(':search', $search);
        $countStmt->bindValue(':status', $status);
        $countStmt->bindValue(':role', $role);
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        echo jsonResponse(true, 'Users retrieved', [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch(PDOException $e) {
        error_log("Users retrieval error: " . $e->getMessage());
        echo jsonResponse(false, 'Database error occurred');
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Create or update user
    $action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';
    
    if ($action === 'create') {
        createUser();
    } elseif ($action === 'update') {
        updateUser();
    } elseif ($action === 'delete') {
        deleteUser();
    } else {
        echo jsonResponse(false, 'Invalid action');
    }
} else {
    http_response_code(405);
    echo jsonResponse(false, 'Method not allowed');
}

function createUser() {
    global $pdo;
    
    $full_name = sanitizeInput($_POST['full_name']);
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $role = sanitizeInput($_POST['role']);
    $status = sanitizeInput($_POST['status']);
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : null;
    
    // Validate input
    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($role)) {
        echo jsonResponse(false, 'Please fill in all required fields');
        return;
    }
    
    if (!validateEmail($email)) {
        echo jsonResponse(false, 'Please enter a valid email address');
        return;
    }
    
    if (!validatePassword($password)) {
        echo jsonResponse(false, 'Password must be at least 8 characters long and contain uppercase, lowercase letters and numbers');
        return;
    }
    
    try {
        // Check if username or email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $checkStmt->execute(['username' => $username, 'email' => $email]);
        
        if ($checkStmt->rowCount() > 0) {
            echo jsonResponse(false, 'Username or email already exists');
            return;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, username, email, password, department_id, phone, role, status, created_at) 
            VALUES (:full_name, :username, :email, :password, :department_id, :phone, :role, :status, NOW())
        ");
        
        $stmt->execute([
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'password' => $hashed_password,
            'department_id' => $department_id,
            'phone' => $phone,
            'role' => $role,
            'status' => $status
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Create default preferences
        $prefStmt = $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (:user_id)");
        $prefStmt->execute(['user_id' => $user_id]);
        
        // Get the created user
        $userStmt = $pdo->prepare("
            SELECT u.*, d.name as department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = :id
        ");
        $userStmt->execute(['id' => $user_id]);
        $user = $userStmt->fetch();
        
        echo jsonResponse(true, 'User created successfully', ['user' => $user]);
        
    } catch(PDOException $e) {
        error_log("User creation error: " . $e->getMessage());
        echo jsonResponse(false, 'Database error occurred');
    }
}

function updateUser() {
    global $pdo;
    
    $user_id = (int)$_POST['user_id'];
    $full_name = sanitizeInput($_POST['full_name']);
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $role = sanitizeInput($_POST['role']);
    $status = sanitizeInput($_POST['status']);
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : null;
    
    // Validate input
    if (empty($full_name) || empty($username) || empty($email) || empty($role)) {
        echo jsonResponse(false, 'Please fill in all required fields');
        return;
    }
    
    if (!validateEmail($email)) {
        echo jsonResponse(false, 'Please enter a valid email address');
        return;
    }
    
    try {
        // Check if username or email already exists (excluding current user)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
        $checkStmt->execute(['username' => $username, 'email' => $email, 'user_id' => $user_id]);
        
        if ($checkStmt->rowCount() > 0) {
            echo jsonResponse(false, 'Username or email already exists');
            return;
        }
        
        // Update user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = :full_name, username = :username, email = :email, 
                department_id = :department_id, phone = :phone, role = :role, 
                status = :status, updated_at = NOW()
            WHERE id = :user_id
        ");
        
        $stmt->execute([
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'department_id' => $department_id,
            'phone' => $phone,
            'role' => $role,
            'status' => $status,
            'user_id' => $user_id
        ]);
        
        // Get the updated user
        $userStmt = $pdo->prepare("
            SELECT u.*, d.name as department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = :id
        ");
        $userStmt->execute(['id' => $user_id]);
        $user = $userStmt->fetch();
        
        echo jsonResponse(true, 'User updated successfully', ['user' => $user]);
        
    } catch(PDOException $e) {
        error_log("User update error: " . $e->getMessage());
        echo jsonResponse(false, 'Database error occurred');
    }
}

function deleteUser() {
    global $pdo;
    
    $user_id = (int)$_POST['user_id'];
    
    try {
        // Prevent admin from deleting themselves
        if ($user_id == $_SESSION['user_id']) {
            echo jsonResponse(false, 'You cannot delete your own account');
            return;
        }
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        
        echo jsonResponse(true, 'User deleted successfully');
        
    } catch(PDOException $e) {
        error_log("User deletion error: " . $e->getMessage());
        echo jsonResponse(false, 'Database error occurred');
    }
}
?>