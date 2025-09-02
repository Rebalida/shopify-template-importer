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
    'max_tag_length' => 255
];

// Master template specific settings
$headerStartRow = 9; // Headers start at row 9 (1-based)
$headerStartColumn = 1; // Headers start at column A (1-based)

// Helper functions
function to_handle($str) {
    $h = strtolower(trim($str));
    $h = preg_replace('/\s+/', '-', $h);           
    $h = preg_replace('/[^a-z0-9\-]/i', '-', $h);  
    $h = preg_replace('/-+/', '-', $h);
    $h = trim($h, '-'); 
    return $h ?: 'product-' . uniqid();
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
    'Variant Image', 'Variant Weight Unit', 'Variant Tax Code', 'Cost per item', 'Status'
];

// For streaming download
$outFile = 'shopify_products_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $outFile . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fputcsv($out, $header);

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

        // Extract data starting from the same column as headers
        $rowData = array_slice($row, $headerStartColumn - 1, count($headers));

        // Combine Features and Specifications for Body HTML
        $bodyHtml = getValue($rowData, 'Features', $headers) . "\n\n" . 
                    getValue($rowData, 'Specifications', $headers);

        $outRow = [
            getValue($rowData, 'CODE', $headers), // Handle
            $title,
            htmlspecialchars($bodyHtml, ENT_QUOTES, 'UTF-8'),
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
            'active'
        ];

        fputcsv($out, $outRow);
        
        if ($rowCount % 100 === 0) {
            ob_flush();
            flush();
        }
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
        $productCost = cleanNumeric(getValue($r, 'Product Cost', null), '0');
        $msrp = cleanNumeric(getValue($r, 'MSRP', null), '');
        $img = cleanUrl(getValue($r, 'Image URL', null));
        $statusRaw = strtolower(getValue($r, 'Status', null, $config['default_status']));
        $status = in_array($statusRaw, ['active','draft','archived']) ? $statusRaw : $config['default_status'];
        $published = ($statusRaw === 'active') ? 'TRUE' : $config['default_published'];
        $upc = getValue($r, 'Universal Product Code (UPC)', null);
        $ean = getValue($r, 'European Article Number (EAN)', null);
        $categoryCode = getValue($r, 'Category Code', null);
        
        $product_handle = to_handle($title);
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
        
        $outRow = [
            $product_handle,
            $title,
            htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'),
            $vendor,
            '',
            $productType,
            $tagsCsv,
            $published,
            $option1Name,
            $option1Value,
            '', // Option1 Linked To
            '', // Option2 Name
            '', // Option2 Value
            '', // Option2 Linked To
            '', // Option3 Name
            '', // Option3 Value
            '', // Option3 Linked To
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
            '', // Accessory size
            '', // Age group
            '', // Bag/Case features
            '', // Bag/Case material
            '', // Bag/Case storage features
            '', // Carry options
            '', // Color
            '', // Target gender
            '', // Variant Image
            $config['default_weight_unit'], // Variant Weight Unit
            '',
            $productCost, // Cost per item
            $status // Status
        ];

        fputcsv($out, $outRow);
        
        if ($rowCount % 100 === 0) {
            ob_flush();
            flush();
        }
    }
}

fclose($handle);
fclose($out);

if (!empty($errorLog)) {
    error_log("CSV Conversion Errors: " . implode('; ', $errorLog));
}

exit;