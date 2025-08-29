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
    'default_option1_value' => 'Default Title'
];

// Helper functions
function getValue($row, $columnName, $headers, $fallback = '') {
    $columnIndex = array_search($columnName, $headers);
    if ($columnIndex === false) return $fallback;
    return isset($row[$columnIndex]) && trim($row[$columnIndex]) !== '' ? trim($row[$columnIndex]) : $fallback;
}

function cleanNumeric($value, $fallback = '0') {
    $cleaned = trim($value);
    return (is_numeric($cleaned) && $cleaned >= 0) ? $cleaned : $fallback;
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

// Shopify CSV header
$header = [
    'Handle','Title','Body (HTML)','Vendor','Product Category','Type','Tags','Published',
    'Option1 Name','Option1 Value','Option2 Name','Option2 Value','Option3 Name','Option3 Value',
    'Variant SKU','Variant Grams','Variant Inventory Tracker','Variant Inventory Qty',
    'Variant Inventory Policy','Variant Fulfillment Service','Variant Price',
    'Variant Compare At Price','Variant Requires Shipping','Variant Taxable',
    'Variant Barcode','Image Src','Image Position','Image Alt Text','Gift Card',
    'SEO Title','SEO Description','Status'
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

while (($row = fgetcsv($handle)) !== false) {
    $rowCount++;
    
    // Get headers from row 9
    if ($rowCount == 9) {
        $headers = array_map('trim', $row);
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

    // Combine Features and Specifications for Body HTML
    $bodyHtml = getValue($row, 'Features', $headers) . "\n\n" . 
                getValue($row, 'Specifications', $headers);

    $outRow = [
        getValue($row, 'CODE', $headers), // Handle
        $title,
        htmlspecialchars($bodyHtml, ENT_QUOTES, 'UTF-8'),
        getValue($row, 'Supplier', $headers),
        '',
        getValue($row, 'Brand', $headers),
        getValue($row, 'Brand', $headers),
        $config['default_published'],
        $config['default_option1_name'],
        $config['default_option1_value'],
        '',
        '',
        '',
        '',
        getValue($row, 'CODE', $headers),
        cleanNumeric(getValue($row, 'Weight in Kg', $headers)) * 1000,
        $config['default_inventory_tracker'],
        $config['default_inventory_qty'],
        $config['default_inventory_policy'],
        $config['default_fulfillment_service'],
        cleanNumeric(getValue($row, 'Trade ex GST', $headers)),
        cleanNumeric(getValue($row, 'RRP', $headers)),
        $config['default_requires_shipping'],
        $config['default_taxable'],
        getValue($row, 'EAN-BARCODE', $headers),
        cleanUrl(getValue($row, 'Image', $headers)),
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

fclose($handle);
fclose($out);

if (!empty($errorLog)) {
    error_log("CSV Conversion Errors: " . implode('; ', $errorLog));
}

exit;