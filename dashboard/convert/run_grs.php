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

function cleanDate($date) {
    if (empty($date)) return '';
    return date('Y-m-d', strtotime($date));
}

// Define output CSV headers
$outputHeader = [
    'Reward Name',
    'Reward Description',
    'Brand',
    'Model',
    'SKU',
    'Product Cost',
    'Shipping Cost',
    'Handling Cost',
    'Service Charge',
    'MSRP',
    'Category Code',
    'Image URL',
    'Status',
    'Regular Product Cost',
    'Regular Shipping Cost',
    'Regular Handling Cost',
    'Regular Service Charge',
    'Is On Sale',
    'Universal Product Code (UPC)',
    'European Article Number (EAN)',
    'Manufacturer Part Number (MPN)',
    'Amazon Standard ID Number (ASIN)',
    'Release Date',
    'New Duration'
];

// For streaming download
$outFile = 'rewards_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $outFile . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fputcsv($out, $outputHeader);

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

    $rewardName = getValue($row, 'Product Name', $headers);
    if (empty($rewardName)) {
        $errorLog[] = "Row $rowCount: Missing reward name, skipping";
        continue;
    }

    // Combine Features and Specifications for description
    $description = getValue($row, 'Features', $headers) . "\n\n" . 
                  getValue($row, 'Specifications', $headers);
    
    // Get cost values with fallbacks
    $productCost = cleanNumeric(getValue($row, 'Acheiva Cost ex GST (After Discount)', $headers)) ?: 
                  cleanNumeric(getValue($row, 'Buy price ex GST', $headers));
    
    // Check if discount exists to determine Is On Sale
    $hasDiscount = !empty(getValue($row, 'Discount', $headers));
    
    $outRow = [
        $rewardName,
        trim($description),
        getValue($row, 'Brand', $headers),
        getValue($row, 'CODE', $headers),
        getValue($row, 'CODE', $headers),
        $productCost,
        cleanNumeric(getValue($row, 'Freight ex GST', $headers)),
        '0',
        cleanNumeric(getValue($row, 'MARKUP (%)', $headers)),
        cleanNumeric(getValue($row, 'RRP', $headers)),
        getValue($row, 'Supplier', $headers),
        cleanUrl(getValue($row, 'Image', $headers)),
        'active',
        cleanNumeric(getValue($row, 'Supplier Wholesale ex GST', $headers)),
        cleanNumeric(getValue($row, 'Freight ex GST', $headers)),
        '0',
        cleanNumeric(getValue($row, 'MARKUP (%)', $headers)),
        $hasDiscount ? 'TRUE' : 'FALSE',
        getValue($row, 'EAN-BARCODE', $headers),
        getValue($row, 'EAN-BARCODE', $headers),
        getValue($row, 'CODE', $headers),
        '',
        '',
        ''
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