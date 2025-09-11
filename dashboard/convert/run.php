
<?php
session_start();
require_once '../../auth/middleware.php';
checkAuth();

// Paths
$fileName = $_GET['file'] ?? null;
if (!$fileName) {
    http_response_code(400);
    exit('No file specified.');
}

$inputPath = __DIR__ . '/../../storage/uploads/' . $fileName;
if (!file_exists($inputPath)) {
    http_response_code(404);
    exit('File not found.');
}

// Determine template type from filename prefix
$templateType = 'master';
if (strpos($fileName, 'grs_') === 0) {
    $templateType = 'grs';
} elseif (strpos($fileName, 'master_') === 0) {
    $templateType = 'master';
}

// Configuration
$config = [
    'default_status' => 'draft',
    'default_published' => 'TRUE',
    'default_inventory_tracker' => 'shopify',
    'default_inventory_qty' => '0',
    'default_inventory_policy' => 'deny',
    'default_fulfillment_service' => 'manual',
    'default_requires_shipping' => 'TRUE',
    'default_taxable' => 'TRUE',
    'default_weight_unit' => 'kg',
    'default_gift_card' => 'FALSE',
    'default_image_position' => '1',
    'default_option1_name' => 'Title',
    'default_option1_value' => 'Default Title',
    'include_compare_at_price' => true,
    'auto_generate_seo' => true,
    'max_tag_length' => 255,
    'max_file_size_mb' => 15 // Maximum file size in MB
];


$headerStartRow = 9;
$headerStartColumn = 1;


$handleTracker = [];
$productIdentities = [];

// Helper functions
function to_handle($str) {
    $h = strtolower(trim($str));
    $h = preg_replace('/\s+/', '-', $h);           
    $h = preg_replace('/[^a-z0-9\-]/i', '-', $h);  
    $h = preg_replace('/-+/', '-', $h);
    $h = trim($h, '-'); 
    return $h ?: 'product-' . uniqid();
}

function getProductIdentityHash($title, $sku, $templateType, $rowData = null, $headers = null) {
    $identityData = [
        'title' => trim(strtolower($title)),
        'sku' => trim($sku)
    ];
    
    if ($templateType === 'master' && $rowData && $headers) {
        $identityData['brand'] = trim(strtolower(getValue($rowData, 'Brand', $headers)));
        $identityData['code'] = trim(strtolower(getValue($rowData, 'CODE', $headers)));
        $identityData['ean'] = trim(getValue($rowData, 'EAN-BARCODE', $headers));
    } elseif ($templateType === 'grs' && $rowData) {
        $identityData['brand'] = trim(strtolower(getValue($rowData, 'Brand', null)));
        $identityData['model'] = trim(strtolower(getValue($rowData, 'Model', null)));
        $identityData['upc'] = trim(getValue($rowData, 'Universal Product Code (UPC)', null));
        $identityData['ean'] = trim(getValue($rowData, 'European Article Number (EAN)', null));
    }
    
    $identityData = array_filter($identityData, function($value) {
        return $value !== '' && $value !== null;
    });
    
    return md5(serialize($identityData));
}

function getUniqueHandle($title, $sku, $templateType, $rowData = null, $headers = null) {
    global $handleTracker, $productIdentities;
    
    $baseHandle = to_handle($title);
    $productHash = getProductIdentityHash($title, $sku, $templateType, $rowData, $headers);
    
    if (isset($productIdentities[$productHash])) {
        return $productIdentities[$productHash];
    }
    
    if (!isset($handleTracker[$baseHandle])) {
        $handleTracker[$baseHandle] = ['count' => 1, 'products' => [$productHash]];
        $productIdentities[$productHash] = $baseHandle;
        return $baseHandle;
    }
    
    foreach ($handleTracker[$baseHandle]['products'] as $existingHash) {
        if ($existingHash === $productHash) {
            return $productIdentities[$productHash];
        }
    }
    
    $handleTracker[$baseHandle]['count']++;
    $counter = $handleTracker[$baseHandle]['count'];
    $uniqueHandle = $baseHandle . '-' . $counter;
    
    while (isset($handleTracker[$uniqueHandle])) {
        $counter++;
        $uniqueHandle = $baseHandle . '-' . $counter;
    }
    
    // Initialize tracker for the new unique handle
    $handleTracker[$uniqueHandle] = ['count' => 1, 'products' => [$productHash]];
    $handleTracker[$baseHandle]['products'][] = $productHash;
    $productIdentities[$productHash] = $uniqueHandle;
    
    return $uniqueHandle;
}

function getValue($row, $columnName, $headers, $fallback = '') {
    if (is_array($headers)) {
        $columnIndex = array_search($columnName, $headers);
        if ($columnIndex === false) return $fallback;
        return isset($row[$columnIndex]) && trim($row[$columnIndex]) !== '' ? trim($row[$columnIndex]) : $fallback;
    } else {
        // For GRS template where $headers is associative array
        $value = trim($row[$columnName] ?? '');
        return $value !== '' ? $value : $fallback;
    }
}

function cleanNumeric($value, $fallback = '0') {
    if ($value === null || $value === '') {
        return $fallback;
    }

    // Remove everything except digits, dot, minus
    $cleaned = preg_replace('/[^\d\.\-]/u', '', $value);

    if ($cleaned === '' || !is_numeric($cleaned)) {
        return $fallback;
    }

    return formatDecimal($cleaned);
}

function cleanBoolean($value, $fallback = 'FALSE') {
    $cleaned = strtolower(trim($value));
    if (in_array($cleaned, ['yes', 'true', '1', 'on', 'active'])) return 'TRUE';
    if (in_array($cleaned, ['no', 'false', '0', 'off', 'inactive'])) return 'FALSE';
    return $fallback;
}

function cleanUrl($url) {
    $cleaned = trim($url);
    return filter_var($cleaned, FILTER_VALIDATE_URL) ? $cleaned : '';
}

function generateSeoTitle($title, $maxLength = 60) {
    return strlen($title) <= $maxLength ? $title : substr($title, 0, $maxLength - 3) . '...';
}

function generateSeoDescription($description, $maxLength = 160) {
    $cleaned = strip_tags($description);
    return strlen($cleaned) <= $maxLength ? $cleaned : substr($cleaned, 0, $maxLength - 3) . '...';
}

function formatDecimal($number, $decimals = 4) {
    return number_format((float)$number, $decimals, '.', '');
}

function cleanHtmlDescription($html) {
    if (empty($html)) {
        return '';
    }
    
    // Convert various break tags to line breaks
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<br\/>/i', "\n", $html);
    $html = preg_replace('/<br>/i', "\n", $html);
    
    // Convert paragraph tags to double line breaks
    $html = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $html);
    $html = preg_replace('/<p[^>]*>/i', "", $html);
    $html = preg_replace('/<\/p>/i', "\n\n", $html);
    
    // Remove other HTML tags
    $html = strip_tags($html);
    
    // Clean up multiple consecutive line breaks
    $html = preg_replace('/\n{3,}/', "\n\n", $html);
    
    // Trim whitespace
    $html = trim($html);
    
    // Wrap each paragraph in <p> tags
    $paragraphs = explode("\n\n", $html);
    $paragraphs = array_map(function($p) {
        $p = trim($p);
        return $p ? "<p>" . $p . "</p>" : '';
    }, $paragraphs);
    
    return implode("\n", array_filter($paragraphs));
}

// Category to Product Type mapping
$productTypeMap = [
    'KitchenandHomewares' => 'Kitchen & Dining',
    'Kitchen' => 'Kitchen & Dining',
    'Homewares' => 'Home & Garden',
    '75' => 'Beauty & Personal Care',
    '68' => 'Sports & Recreation',
    '14' => 'Beauty & Personal Care',
    'Beauty' => 'Beauty & Personal Care',
    'Sports' => 'Sports & Recreation',
    'Golf' => 'Sports & Recreation'
];

// Shopify CSV header (unified for both templates)
$header = [
    'Handle', 'Title', 'Body (HTML)', 'Vendor', 'Product Category', 'Type', 'Tags', 'Published',
    'Option1 Name', 'Option1 Value', 'Option1 Linked To',
    'Option2 Name', 'Option2 Value', 'Option2 Linked To',
    'Option3 Name', 'Option3 Value', 'Option3 Linked To',
    'Variant SKU', 'Variant Grams', 'Variant Inventory Tracker', 'Variant Inventory Qty',
    'Variant Inventory Policy', 'Variant Fulfillment Service', 'Variant Price', 
    'Variant Compare At Price', 'Variant Requires Shipping', 'Variant Taxable',
    'Variant Barcode', 'Image Src', 'Image Position', 'Image Alt Text', 'Gift Card',
    'SEO Title', 'SEO Description',
    'Amazon Standard ID (ASIN) (product.metafields.grs.asin)',
    'Category Code (product.metafields.grs.category_code)',
    'Handling Cost (product.metafields.grs.handling_cost)',
    'Is On Sale (product.metafields.grs.is_on_sale)',
    'New Duration (product.metafields.grs.new_duration)',
    'Regular Handling Cost (product.metafields.grs.regular_handling_cost)',
    'Regular Product Cost (product.metafields.grs.regular_product_cost)',
    'Regular Service Charge (product.metafields.grs.regular_service_charge)',
    'Regular Shipping Cost (product.metafields.grs.regular_shipping_cost)',
    'Release Date (product.metafields.grs.release_date)',
    'Service Charge (product.metafields.grs.service_charge)',
    'Shipping Cost (product.metafields.grs.shipping_cost)',
    'Accessory size (product.metafields.shopify.accessory-size)',
    'Age group (product.metafields.shopify.age-group)',
    'Bag/Case features (product.metafields.shopify.bag-case-features)',
    'Bag/Case material (product.metafields.shopify.bag-case-material)',
    'Bag/Case storage features (product.metafields.shopify.bag-case-storage-features)',
    'Carry options (product.metafields.shopify.carry-options)',
    'Color (product.metafields.shopify.color-pattern)',
    'Target gender (product.metafields.shopify.target-gender)',
    'Variant Image', 'Variant Weight Unit', 'Variant Tax Code', 'Cost per item', 'Status', 'Google Shopping / MPN (product.metafields.google.mpn)',
    'Google Shopping / EAN (product.metafields.google.ean)'
];

// File splitting variables
$maxFileSizeBytes = $config['max_file_size_mb'] * 1024 * 1024; // Convert MB to bytes
$currentFileSize = 0;
$fileCount = 1;
$tempDir = sys_get_temp_dir() . '/csv_split_' . uniqid();
mkdir($tempDir);

$outFiles = [];
$currentFile = null;

// Function to create new output file
function createNewOutputFile($fileCount, $tempDir, $header) {
    $fileName = "shopify_products_part{$fileCount}.csv";
    $filePath = $tempDir . '/' . $fileName;
    $file = fopen($filePath, 'w');
    fputcsv($file, $header);
    return ['handle' => $file, 'path' => $filePath, 'name' => $fileName, 'size' => strlen(implode(',', $header)) + 1];
}

// Create first output file
$currentFile = createNewOutputFile($fileCount, $tempDir, $header);
$outFiles[] = $currentFile;

// Read CSV file
$handle = fopen($inputPath, 'r');
if ($handle === false) {
    http_response_code(500);
    exit('Error opening CSV file');
}

$headers = [];
$rowCount = 0;
$errorLog = [];

if ($templateType === 'master') {
    // MASTER TEMPLATE PROCESSING
    while (($row = fgetcsv($handle)) !== false) {
        $rowCount++;
        
        // Get headers from row 9
        if ($rowCount == 9) {
            $headers = array_slice($row, $headerStartColumn - 1);
            $headers = array_map('trim', $headers);
            continue;
        }
        
        // Skip rows before data starts
        if ($rowCount < 10) {
            continue;
        }

        $title = getValue($row, 'Product Name', $headers);
        if (empty($title)) {
            $errorLog[] = "Row $rowCount: Missing product title, skipping";
            continue;
        }

        $rowData = array_slice($row, $headerStartColumn - 1, count($headers));
        
        $sku = getValue($rowData, 'CODE', $headers);

        $uniqueHandle = getUniqueHandle($title, $sku, $templateType, $rowData, $headers);

        // Combine Features and Specifications for Body HTML
        $bodyHtml = getValue($rowData, 'Features', $headers) . "\n\n" . 
                    getValue($rowData, 'Specifications', $headers);
        $cleanBodyHtml = cleanHtmlDescription($bodyHtml);

        $outRow = [
            $uniqueHandle, 
            $title,
            $cleanBodyHtml,
            getValue($rowData, 'Supplier', $headers),
            '',
            getValue($rowData, 'Brand', $headers),
            getValue($rowData, 'Brand', $headers),
            $config['default_published'],
            $config['default_option1_name'],
            $config['default_option1_value'],
            '',
            '',
            '',
            '',
            getValue($rowData, 'CODE', $headers),
            cleanNumeric(getValue($rowData, 'Weight in Kg', $headers)) * 1000,
            $config['default_inventory_tracker'],
            $config['default_inventory_qty'],
            $config['default_inventory_policy'],
            $config['default_fulfillment_service'],
            formatDecimal(cleanNumeric(getValue($rowData, 'Acheiva Cost ex GST (After Discount)', $headers))),
            cleanNumeric(getValue($rowData, 'RRP', $headers)),
            $config['default_requires_shipping'],
            $config['default_taxable'],
            getValue($rowData, 'EAN-BARCODE', $headers),
            cleanUrl(getValue($rowData, 'Image', $headers)),
            $config['default_image_position'],
            $title,
            $config['default_gift_card'],
            generateSeoTitle($title),
            generateSeoDescription($bodyHtml),
            'active',
            getValue($rowData, 'MPN', $headers),
            getValue($rowData, 'EAN', $headers),
        ];

        // Calculate row size
        $rowSize = strlen(implode(',', $outRow)) + 1; // +1 for newline
        
        // Check if we need to create a new file
        if ($currentFile['size'] + $rowSize > $maxFileSizeBytes) {
            fclose($currentFile['handle']);
            $fileCount++;
            $currentFile = createNewOutputFile($fileCount, $tempDir, $header);
            $outFiles[] = $currentFile;
        }

        fputcsv($currentFile['handle'], $outRow);
        $currentFile['size'] += $rowSize;
    }

} else {
    // GRS TEMPLATE PROCESSING
    $grsHeader = fgetcsv($handle, 0, ',');
    if (!$grsHeader) {
        http_response_code(400);
        exit('GRS CSV has no header.');
    }

    // Process each GRS row
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $rowCount++;
        $r = array_combine($grsHeader, $row);
        
        $title = getValue($r, 'Reward Name', null);
        if (empty($title)) {
            $errorLog[] = "Row $rowCount: Missing product title, skipping";
            continue;
        }
        
        $desc = getValue($r, 'Reward Description', null);
        $vendor = getValue($r, 'Brand', null);
        $model = getValue($r, 'Model', null);
        $sku = getValue($r, 'SKU', null);
        
        // Generate unique handle
        $product_handle = getUniqueHandle($title, $sku, $templateType, $r);
        
        $productCost = cleanNumeric(getValue($r, 'Product Cost', null), '0');
        $msrp = cleanNumeric(getValue($r, 'MSRP', null), '');
        $img = cleanUrl(getValue($r, 'Image URL', null));
        $statusRaw = strtolower(getValue($r, 'Status', null, $config['default_status']));
        $status = in_array($statusRaw, ['active','draft','archived']) ? $statusRaw : $config['default_status'];
        $published = ($statusRaw === 'active') ? 'TRUE' : $config['default_published'];
        $upc = getValue($r, 'Universal Product Code (UPC)', null);
        $ean = getValue($r, 'European Article Number (EAN)', null);
        $categoryCode = getValue($r, 'Category Code', null);
        
        $productType = $productTypeMap[$categoryCode] ?? '';
        
        $tags = array_filter([
            $vendor ? 'brand:' . strtolower($vendor) : null,
            $model,
            $categoryCode ? 'category:' . $categoryCode : null,
            $ean ? 'ean:' . $ean : null,
            $statusRaw === 'active' ? 'available' : null
        ]);
        $tagsCsv = substr(implode(', ', $tags), 0, $config['max_tag_length']);
        
        $variantPrice = $productCost;
        $compareAt = '';
        if ($config['include_compare_at_price'] && is_numeric($msrp) && $msrp > $productCost) {
            $compareAt = $msrp;
        }
        
        $seoTitle = $config['auto_generate_seo'] ? generateSeoTitle($title) : '';
        $seoDescription = $config['auto_generate_seo'] ? generateSeoDescription($desc) : '';
        $imageAlt = !empty($img) ? $title : '';
        $option1Name = $config['default_option1_name'];
        $option1Value = !empty($model) ? $model : $config['default_option1_value'];

        $cleanDesc = cleanHtmlDescription($desc);
        
        $outRow = [
            $product_handle, // Use unique handle
            $title,
            $cleanDesc,
            $vendor,
            '',
            $productType,
            $tagsCsv,
            $published,
            $option1Name,
            $option1Value,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $sku,
            cleanNumeric(getValue($r, 'Weight', null), ''),
            $config['default_inventory_tracker'],
            cleanNumeric(getValue($r, 'Quantity', null), '0'),
            $config['default_inventory_policy'],
            $config['default_fulfillment_service'],
            $variantPrice,
            $compareAt,
            $config['default_requires_shipping'],
            $config['default_taxable'],
            $upc,
            $img,
            $config['default_image_position'],
            $imageAlt,
            $config['default_gift_card'],
            $seoTitle,
            $seoDescription,
            getValue($r, 'Amazon Standard ID Number (ASIN)', null), 
            getValue($r, 'Category Code', null),
            getValue($r, 'Handling Cost', null),
            getValue($r, 'Is On Sale', null),
            getValue($r, 'New Duration', null),
            getValue($r, 'Regular Handling Cost', null),
            getValue($r, 'Regular Product Cost', null),
            getValue($r, 'Regular Service Charge', null),
            getValue($r, 'Regular Shipping Cost', null),
            getValue($r, 'Release Date', null),
            getValue($r, 'Service Charge', null),
            getValue($r, 'Shipping Cost', null),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $config['default_weight_unit'],
            '',
            $productCost,
            $status,
            getValue($r, 'Manufacturer Part Number (MPN)', null),
            getValue($r, 'European Article Number (EAN)', null),
        ];

        // Calculate row size
        $rowSize = strlen(implode(',', $outRow)) + 1;
        
        // Check if we need to create a new file
        if ($currentFile['size'] + $rowSize > $maxFileSizeBytes) {
            fclose($currentFile['handle']);
            $fileCount++;
            $currentFile = createNewOutputFile($fileCount, $tempDir, $header);
            $outFiles[] = $currentFile;
        }

        fputcsv($currentFile['handle'], $outRow);
        $currentFile['size'] += $rowSize;
    }
}

fclose($handle);
fclose($currentFile['handle']);

// If only one file, send it directly
if (count($outFiles) == 1) {
    $file = $outFiles[0];
    // Rename single file to just "shopify_export.csv"
    $singleFileName = 'shopify_export.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $singleFileName . '"');
    header('Content-Length: ' . filesize($file['path']));
    readfile($file['path']);
    unlink($file['path']);
    rmdir($tempDir);
} else {
    // Multiple files - create ZIP archive
    $zipFileName = 'shopify_export.zip';
    $zipPath = $tempDir . '/' . $zipFileName;
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        http_response_code(500);
        exit('Cannot create ZIP file');
    }
    
    foreach ($outFiles as $file) {
        $zip->addFile($file['path'], $file['name']);
    }
    
    $zip->close();
    
    // Send ZIP file
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    
    // Cleanup
    foreach ($outFiles as $file) {
        unlink($file['path']);
    }
    unlink($zipPath);
    rmdir($tempDir);
}

if (!empty($errorLog)) {
    error_log("CSV Conversion Errors: " . implode('; ', $errorLog));
}

exit;