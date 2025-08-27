<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

// Shopify API
$SHOP_URL = $_ENV['SHOP_URL'];
$ACCESS_TOKEN = $_ENV['ACCESS_TOKEN'];
$API_VERSION = $_ENV['API_VERSION'];

// Database
$DB_HOST = $_ENV['DB_HOST'];
$DB_NAME = $_ENV['DB_NAME'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];

try {
    $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", $DB_HOST, $DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'details' => $e->getMessage()
    ], JSON_THROW_ON_ERROR);
    exit;
}