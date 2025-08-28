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

// Helper: handle generation from title (lowercase with spaces as hyphens)
function to_handle($str) {
    $h = strtolower(trim($str));
    $h = preg_replace('/\s+/', '-', $h);           
    $h = preg_replace('/[^a-z0-9\-]/i', '-', $h);  
    $h = preg_replace('/-+/', '-', $h);
    $h = trim($h, '-'); 
    return $h ?: 'product-' . uniqid();
}

// Helper: get value with fallback
function getValue($row, $key, $fallback = '') {
    $value = trim($row[$key] ?? '');
    return $value !== '' ? $value : $fallback;
}

// Helper: validate and clean numeric values
function cleanNumeric($value, $fallback = '0') {
    $cleaned = trim($value);
    return (is_numeric($cleaned) && $cleaned >= 0) ? $cleaned : $fallback;
}

// Helper: validate boolean values
function cleanBoolean($value, $fallback = 'FALSE') {
    $cleaned = strtolower(trim($value));
    if (in_array($cleaned, ['yes', 'true', '1', 'on', 'active'])) return 'TRUE';
    if (in_array($cleaned, ['no', 'false', '0', 'off', 'inactive'])) return 'FALSE';
    return $fallback;
}

// Helper: validate URL
function cleanUrl($url) {
    $cleaned = trim($url);
    return filter_var($cleaned, FILTER_VALIDATE_URL) ? $cleaned : '';
}

// Helper: generate SEO title from product title
function generateSeoTitle($title, $maxLength = 60) {
    return strlen($title) <= $maxLength ? $title : substr($title, 0, $maxLength - 3) . '...';
}

// Helper: generate SEO description from product description
function generateSeoDescription($description, $maxLength = 160) {
    $cleaned = strip_tags($description);
    return strlen($cleaned) <= $maxLength ? $cleaned : substr($cleaned, 0, $maxLength - 3) . '...';
}

// Category to Product Type mapping (can be extended)
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

// Shopify CSV header
$header = [
    'Handle','Title','Body (HTML)','Vendor','Product Category','Type','Tags','Published',
    'Option1 Name','Option1 Value','Option1 Linked To',
    'Option2 Name','Option2 Value','Option2 Linked To',
    'Option3 Name','Option3 Value','Option3 Linked To',
    'Variant SKU','Variant Grams','Variant Inventory Tracker','Variant Inventory Qty','Variant Inventory Policy',
    'Variant Fulfillment Service','Variant Price','Variant Compare At Price','Variant Requires Shipping','Variant Taxable',
    'Variant Barcode','Image Src','Image Position','Image Alt Text','Gift Card','SEO Title','SEO Description',
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
    'Variant Image','Variant Weight Unit','Variant Tax Code','Cost per item','Status'
];

// Open GRS CSV
if (($in = fopen($inputPath, 'r')) === false) {
    http_response_code(500);
    exit('Could not open source CSV.');
}

$grsHeader = fgetcsv($in, 0, ',');
if (!$grsHeader) {
    http_response_code(400);
    exit('Source CSV has no header.');
}

// For streaming download
$outFile = 'shopify_products_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $outFile . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');

// Write header
fputcsv($out, $header);

$rowCount = 0;
$errorLog = [];

// Process each row
while (($row = fgetcsv($in, 0, ',')) !== false) {
    $rowCount++;
    $r = array_combine($grsHeader, $row);
    
    $title = getValue($r, 'Reward Name');
    if (empty($title)) {
        $errorLog[] = "Row $rowCount: Missing product title, skipping";
        continue;
    }
    
    $desc = getValue($r, 'Reward Description');
    $vendor = getValue($r, 'Brand');
    $model = getValue($r, 'Model');
    $sku = getValue($r, 'SKU');
    $productCost = cleanNumeric(getValue($r, 'Product Cost'), '0');
    $msrp = cleanNumeric(getValue($r, 'MSRP'), '');
    $img = cleanUrl(getValue($r, 'Image URL'));
    $statusRaw = strtolower(getValue($r, 'Status', $config['default_status']));
    $status = in_array($statusRaw, ['active','draft','archived']) ? $statusRaw : $config['default_status'];
    $published = ($statusRaw === 'active') ? 'TRUE' : $config['default_published'];
    $upc = getValue($r, 'Universal Product Code (UPC)');
    $ean = getValue($r, 'European Article Number (EAN)');
    $mpn = getValue($r, 'Manufacturer Part Number (MPN)');
    $asin = getValue($r, 'Amazon Standard ID Number (ASIN)');
    $categoryCode = getValue($r, 'Category Code');
    $shippingCost = cleanNumeric(getValue($r, 'Shipping Cost'), '0');
    $handlingCost = cleanNumeric(getValue($r, 'Handling Cost'), '0');
    $serviceCharge = cleanNumeric(getValue($r, 'Service Charge'), '0');
    $regularProductCost = cleanNumeric(getValue($r, 'Regular Product Cost'), $productCost);
    $regularShippingCost = cleanNumeric(getValue($r, 'Regular Shipping Cost'), '0');
    $regularHandlingCost = cleanNumeric(getValue($r, 'Regular Handling Cost'), '0');
    $regularServiceCharge = cleanNumeric(getValue($r, 'Regular Service Charge'), '0');
    $isOnSale = cleanBoolean(getValue($r, 'Is On Sale'), 'FALSE');
    $releaseDate = getValue($r, 'Release Date');
    $newDuration = cleanNumeric(getValue($r, 'New Duration'), '0');
    $quantity = cleanNumeric(getValue($r, 'Quantity'), '0');
    
    $handle = to_handle($title);
    
    $productType = $productTypeMap[$categoryCode] ?? '';
    
    $tags = array_filter([
        $vendor ? 'brand:' . strtolower($vendor) : null,
        $model,
        $categoryCode ? 'category:' . $categoryCode : null,
        $asin ? 'asin:' . $asin : null,
        $ean ? 'ean:' . $ean : null,
        $mpn ? 'mpn:' . $mpn : null,
        $statusRaw === 'active' ? 'available' : null
    ]);
    $tagsCsv = substr(implode(', ', $tags), 0, $config['max_tag_length']);
    
    $variantPrice = $productCost;
    $compareAt = '';
    if ($config['include_compare_at_price'] && is_numeric($msrp) && $msrp > $productCost) {
        $compareAt = $msrp;
    }
    
    $seoTitle = '';
    $seoDescription = '';
    if ($config['auto_generate_seo']) {
        $seoTitle = generateSeoTitle($title);
        $seoDescription = generateSeoDescription($desc);
    }
    
    $imageAlt = !empty($img) ? $title : '';
    
    $option1Name = $config['default_option1_name'];
    $option1Value = !empty($model) ? $model : $config['default_option1_value'];
    

    $outRow = [
        $handle,
        $title,
        htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'),
        $vendor,
        '',
        $productType,
        $tagsCsv,
        $published,
        
        // Options - Model can go to Option1 Value
        $option1Name,
        $option1Value,
        '',
        getValue($r, 'Option2 Name', ''),
        getValue($r, 'Option2 Value', ''),
        '',
        getValue($r, 'Option3 Name', ''),
        getValue($r, 'Option3 Value', ''),
        '',
        
        // Variant fields with fallbacks
        $sku,
        cleanNumeric(getValue($r, 'Weight'), ''),
        $config['default_inventory_tracker'],
        $quantity,
        // getValue($r, 'Inventory Qty', $config['default_inventory_qty']),
        getValue($r, 'Inventory Policy', $config['default_inventory_policy']),
        $config['default_fulfillment_service'],
        $variantPrice,
        $compareAt,
        cleanBoolean(getValue($r, 'Requires Shipping'), $config['default_requires_shipping']),
        cleanBoolean(getValue($r, 'Taxable'), $config['default_taxable']),
        $upc,
        
        // Image fields
        $img,
        getValue($r, 'Image Position', $config['default_image_position']),
        $imageAlt,
        cleanBoolean(getValue($r, 'Gift Card'), $config['default_gift_card']),
        
        // SEO fields
        $seoTitle,
        $seoDescription,
        
        // Metafields following the custom metafields specification
        $asin,
        $categoryCode,
        $handlingCost,
        $isOnSale,
        $newDuration,
        $regularHandlingCost,
        $regularProductCost,
        $regularServiceCharge,
        $regularShippingCost,
        $releaseDate,
        $serviceCharge,
        $shippingCost,
        
        // Additional Shopify metafields (can be populated from source if available)
        getValue($r, 'Accessory Size', ''),
        getValue($r, 'Age Group', ''),
        getValue($r, 'Bag Case Features', ''),
        getValue($r, 'Bag Case Material', ''),
        getValue($r, 'Bag Case Storage Features', ''),
        getValue($r, 'Carry Options', ''),
        getValue($r, 'Color Pattern', ''),
        getValue($r, 'Target Gender', ''),
        
        // Final variant fields
        getValue($r, 'Variant Image', ''),
        getValue($r, 'Weight Unit', $config['default_weight_unit']),
        getValue($r, 'Tax Code', ''),
        $productCost,
        $status
    ];

    fputcsv($out, $outRow);
    
    // Flush output periodically for large files
    if ($rowCount % 100 === 0) {
        ob_flush();
        flush();
    }
}

fclose($in);
fclose($out);

// Log any errors (in production, you might want to log these to a file)
if (!empty($errorLog)) {
    error_log("CSV Conversion Errors: " . implode('; ', $errorLog));
}

exit;