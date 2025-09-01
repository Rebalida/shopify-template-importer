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

$templateType = 'master'; 
if (strpos($fileName, 'grs_') === 0) {
    http_response_code(400);
    exit('GRS template cannot be converted to GRS format. Please use the appropriate conversion.');
}

if (strpos($fileName, 'master_') !== 0) {
    error_log("Warning: File without template prefix processed as master template: $fileName");
}

// Master template specific settings
$headerStartRow = 9; // Headers start at row 9 (1-based)
$headerStartColumn = 1; // Headers start at column A (1-based)

// Helper functions
function getValue($row, $columnName, $headers, $fallback = '') {
    $columnIndex = array_search($columnName, $headers);
    if ($columnIndex === false) return $fallback;
    return isset($row[$columnIndex]) && trim($row[$columnIndex]) !== '' ? trim($row[$columnIndex]) : $fallback;
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
function cleanUrl($url) {
    $cleaned = trim($url);
    return filter_var($cleaned, FILTER_VALIDATE_URL) ? $cleaned : '';
}

function cleanDate($date) {
    if (empty($date)) return '';
    return date('Y-m-d', strtotime($date));
}

function formatDecimal($number, $decimals = 4) {
    return number_format((float)$number, $decimals, '.', '');
}

// Define GRS output CSV headers
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

// MASTER TEMPLATE PROCESSING FOR GRS OUTPUT
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

    // Extract data starting from the same column as headers
    $rowData = array_slice($row, $headerStartColumn - 1, count($headers));

    $rewardName = getValue($rowData, 'Product Name', $headers);
    if (empty($rewardName)) {
        $errorLog[] = "Row $rowCount: Missing reward name, skipping";
        continue;
    }

    // Combine Features and Specifications for description
    $description = getValue($rowData, 'Features', $headers) . "\n\n" . 
                  getValue($rowData, 'Specifications', $headers);
    
    // Get cost values with fallbacks
    $productCost = cleanNumeric(getValue($rowData, 'Acheiva Cost ex GST (After Discount)', $headers)) ?: 
                  cleanNumeric(getValue($rowData, 'Buy price ex GST', $headers));

    
    
    // Check if discount exists to determine Is On Sale
    $hasDiscount = !empty(getValue($rowData, 'Discount', $headers));
    
    // Map Master template fields to GRS format
    $outRow = [
        $rewardName,
        trim($description),
        getValue($rowData, 'Brand', $headers),
        getValue($rowData, 'CODE', $headers), 
        getValue($rowData, 'CODE', $headers), 
        formatDecimal($productCost),
        formatDecimal(cleanNumeric(getValue($rowData, 'Freight ex GST', $headers))),
        formatDecimal('0'), 
        formatDecimal(cleanNumeric(getValue($rowData, 'MARKUP (%)', $headers))),
        formatDecimal(cleanNumeric(getValue($rowData, 'RRP', $headers))),
        getValue($rowData, 'Supplier', $headers), 
        cleanUrl(getValue($rowData, 'Image', $headers)),
        'active',
        formatDecimal(cleanNumeric(getValue($rowData, 'Supplier Wholesale ex GST', $headers))),
        formatDecimal(cleanNumeric(getValue($rowData, 'Freight ex GST', $headers))),
        formatDecimal('0'),
        formatDecimal(cleanNumeric(getValue($rowData, 'MARKUP (%)', $headers))),
        $hasDiscount ? 'TRUE' : 'FALSE',
        getValue($rowData, 'EAN-BARCODE', $headers), 
        getValue($rowData, 'EAN-BARCODE', $headers),
        getValue($rowData, 'CODE', $headers), 
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