<?php
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

require_once '../../config/config.php';
require __DIR__ . '/helpers.php';

try {
    // Validate shop ID
    $shopId = $_GET['shop_id'] ?? null;
    if (!$shopId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Shop ID is required'
        ]);
        exit();
    }

    // Fetch shop credentials
    $stmt = $pdo->prepare("SELECT shop_url, access_token FROM shops WHERE id = ?");
    $stmt->execute([$shopId]);
    $shop = $stmt->fetch();
    
    if (!$shop) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Shop not found'
        ]);
        exit();
    }

    // Use shop credentials
    $SHOP_URL = rtrim($shop['shop_url'], '/');
    $token_data = base64_decode($shop['access_token']);

    // Validate input file
    $fileName = $_GET['file'] ?? null;
    if (!$fileName) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No file specified'
        ]);
        exit();
    }

    // Check file exists
    $filePath = __DIR__ . '/../../storage/uploads/' . $fileName;
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'File not found'
        ]);
        exit();
    }

    $results = [];
    $totalProcessed = 0;
    $successCount = 0;
    $failCount = 0;

    // Process CSV file
    if (($handle = fopen($filePath, "r")) !== false) {
        $header = fgetcsv($handle, 1000, ",");
        
        if (!$header) {
            throw new Exception('Invalid CSV file - no header found');
        }

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $totalProcessed++;
            $row = array_combine($header, $data);
            
            if ($row === false) {
                $results[] = [
                    'title' => 'Unknown Product',
                    'status' => 'failed',
                    'error' => 'Failed to parse CSV row'
                ];
                $failCount++;
                continue;
            }

            // Validate required fields
            $title = getValue($row, 'Reward Name');
            if (empty($title)) {
                $results[] = [
                    'title' => 'Unknown Product',
                    'status' => 'failed',
                    'error' => 'Missing required field: Reward Name'
                ];
                $failCount++;
                continue;
            }

            try {
                // Build product payload using the new mapping logic
                $productPayload = buildShopifyProductPayload($row);

                // Create product via Shopify API
                $url = $SHOP_URL . "/admin/api/" . $API_VERSION . "/products.json";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "X-Shopify-Access-Token: $ACCESS_TOKEN"
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productPayload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if (curl_error($ch)) {
                    throw new Exception('cURL error: ' . curl_error($ch));
                }
                
                curl_close($ch);

                if ($httpCode === 201) {
                    $respData = json_decode($response, true);
                    $productId = $respData["product"]["id"] ?? null;
                    $productHandle = $respData["product"]["handle"] ?? null;

                    if (!$productId) {
                        throw new Exception('Product created but no ID returned');
                    }

                    // Attach metafields following the specification
                    $metafields = getMetafields($row);
                    $metafieldErrors = [];
                    $metafieldSuccess = [];
                    
                    foreach ($metafields as $key => [$type, $value]) {
                        try {
                            $metaPayload = createMetafieldPayload($productId, $key, $type, $value);

                            $metaUrl = $SHOP_URL . "/admin/api/" . $API_VERSION . "/metafields.json";
                            $ch = curl_init($metaUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                "Content-Type: application/json",
                                "X-Shopify-Access-Token: $ACCESS_TOKEN"
                            ]);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metaPayload));
                            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                            
                            $metaResponse = curl_exec($ch);
                            $metaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            
                            if (curl_error($ch)) {
                                $metafieldErrors[] = "Metafield '$key': cURL error - " . curl_error($ch);
                            } elseif ($metaHttpCode === 201) {
                                $metafieldSuccess[] = $key;
                            } else {
                                $metaErrorData = json_decode($metaResponse, true);
                                $metaErrorMsg = isset($metaErrorData['errors']) 
                                    ? json_encode($metaErrorData['errors']) 
                                    : "HTTP $metaHttpCode";
                                $metafieldErrors[] = "Metafield '$key': $metaErrorMsg";
                            }
                            
                            curl_close($ch);
                            
                            // Add small delay to avoid rate limiting
                            usleep(100000); // 0.1 second delay
                            
                        } catch (Exception $e) {
                            $metafieldErrors[] = "Metafield '$key': " . $e->getMessage();
                        }
                    }

                    $results[] = [
                        'title' => $title,
                        'handle' => $productHandle,
                        'status' => 'success',
                        'product_id' => $productId,
                        'metafields_created' => count($metafieldSuccess),
                        'metafields_failed' => count($metafieldErrors),
                        'metafield_errors' => $metafieldErrors
                    ];
                    $successCount++;

                } else {
                    $errorData = json_decode($response, true);
                    $errorMessage = 'HTTP ' . $httpCode;
                    
                    if (isset($errorData['errors'])) {
                        if (is_array($errorData['errors'])) {
                            $errorDetails = [];
                            foreach ($errorData['errors'] as $field => $messages) {
                                if (is_array($messages)) {
                                    $errorDetails[] = "$field: " . implode(', ', $messages);
                                } else {
                                    $errorDetails[] = "$field: $messages";
                                }
                            }
                            $errorMessage = implode('; ', $errorDetails);
                        } else {
                            $errorMessage = $errorData['errors'];
                        }
                    }
                    
                    $results[] = [
                        'title' => $title,
                        'status' => 'failed',
                        'http_code' => $httpCode,
                        'error' => $errorMessage
                    ];
                    $failCount++;
                }

            } catch (Exception $e) {
                $results[] = [
                    'title' => $title,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                $failCount++;
            }
            
            // Add delay between products to avoid rate limiting
            usleep(200000); // 0.2 second delay
        }
        fclose($handle);
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_processed' => $totalProcessed,
            'successful' => $successCount,
            'failed' => $failCount,
            'success_rate' => $totalProcessed > 0 ? round(($successCount / $totalProcessed) * 100, 1) : 0
        ],
        'results' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}