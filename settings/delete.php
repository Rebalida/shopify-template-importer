<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    header('Content-Type: application/json');
    http_response_code(405); 
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Shop ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM shops WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
    } else {
        header('Content-Type: application/json');
        http_response_code(404); 
        echo json_encode(['status' => 'error', 'message' => 'Shop not found']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to delete shop'
    ]);
}