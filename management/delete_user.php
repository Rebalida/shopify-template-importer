<?php
require_once '../config/config.php';
require_once '../auth/middleware.php';

// Ensure only admins can access this
checkAuth('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    header('Content-Type: application/json');
    http_response_code(405); 
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit;
}

// Prevent users from deleting themselves
if ($id == $_SESSION['user_id']) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own account']);
    exit;
}

try {
    // Check if user exists first
    $checkStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
    $checkStmt->execute([$id]);
    $user = $checkStmt->fetch();
    
    if (!$user) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        error_log("User deleted by admin (ID: {$_SESSION['user_id']}): {$user['first_name']} {$user['last_name']} ({$user['email']})");
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete user']);
    }
} catch (PDOException $e) {
    error_log("User deletion error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error occurred while deleting user'
    ]);
}