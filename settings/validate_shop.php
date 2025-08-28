<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$shop_url = $_POST['shop_url'] ?? '';
$access_token = $_POST['access_token'] ?? '';

if (empty($shop_url) || empty($access_token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Shop URL and access token are required']);
    exit;
}

$shop_domain = str_replace(['https://', 'http://', '/'], '', $shop_url);
if (!str_ends_with( $shop_domain, '.myshopify.com' )) {
    $shop_domain .= '.myshopify.com';
}

$api_url = "https://{$shop_domain}/admin/api/2023-10/shop.json";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        "X-Shopify-Access-Token: {$access_token}",
        "Content-Type: application/json"
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curl_error = curl_error($curl);
curl_close($curl);

if ($curl_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to Shopify API'
    ]);
    exit;
}

if ($http_code === 200) {
    $shop_data = json_decode($response, true);
    
    if ($shop_data && isset($shop_data['shop'])) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Shop credentials are valid',
            'shop_name' => $shop_data['shop']['name'],
            'shop_domain' => $shop_data['shop']['domain']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid response from Shopify API'
        ]);
    }
} elseif ($http_code === 401) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid access token. Please check your credentials.'
    ]);
} elseif ($http_code === 404) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Shop not found. Please check the shop URL.'
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => "Failed to validate credentials (HTTP {$http_code})"
    ]);
} 

?>