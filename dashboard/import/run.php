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

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/helpers.php';

try {
    // Validate input
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

            try {
                // Build product payload
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

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if (curl_error($ch)) {
                    throw new Exception('cURL error: ' . curl_error($ch));
                }
                
                curl_close($ch);

                if ($httpCode === 201) {
                    $respData = json_decode($response, true);
                    $productId = $respData["product"]["id"] ?? null;

                    if (!$productId) {
                        throw new Exception('Product created but no ID returned');
                    }

                    // Attach metafields
                    $metafields = getMetafields($row);
                    $metafieldErrors = [];
                    
                    foreach ($metafields as $key => [$type, $value]) {
                        if ($value !== null && trim($value) !== "") {
                            $metaPayload = [
                                "metafield" => [
                                    "namespace" => "grs",
                                    "key" => $key,
                                    "type" => $type,
                                    "value" => $value,
                                    "owner_resource" => "product",
                                    "owner_id" => $productId
                                ]
                            ];

                            $metaUrl = $SHOP_URL . "/admin/api/" . $API_VERSION . "/metafields.json";
                            $ch = curl_init($metaUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                "Content-Type: application/json",
                                "X-Shopify-Access-Token: $ACCESS_TOKEN"
                            ]);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metaPayload));
                            
                            $metaResponse = curl_exec($ch);
                            $metaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($metaHttpCode !== 201) {
                                $metafieldErrors[] = "Failed to create metafield '$key': HTTP $metaHttpCode";
                            }
                        }
                    }

                    $results[] = [
                        'title' => $row["Reward Name"] ?? 'Unknown Product',
                        'status' => 'success',
                        'product_id' => $productId,
                        'metafield_errors' => $metafieldErrors
                    ];
                    $successCount++;

                } else {
                    $errorData = json_decode($response, true);
                    $errorMessage = isset($errorData['errors']) 
                        ? json_encode($errorData['errors']) 
                        : $response;
                    
                    $results[] = [
                        'title' => $row["Reward Name"] ?? 'Unknown Product',
                        'status' => 'failed',
                        'http_code' => $httpCode,
                        'error' => $errorMessage
                    ];
                    $failCount++;
                }

            } catch (Exception $e) {
                $results[] = [
                    'title' => $row["Reward Name"] ?? 'Unknown Product',
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                $failCount++;
            }
        }
        fclose($handle);
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_processed' => $totalProcessed,
            'successful' => $successCount,
            'failed' => $failCount
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