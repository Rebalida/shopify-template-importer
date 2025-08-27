<?php

// Helper: handle generation from title (lowercase with spaces as hyphens)
function generateHandle($title) {
    $handle = strtolower(trim($title));
    $handle = preg_replace('/\s+/', '-', $handle);
    $handle = preg_replace('/[^a-z0-9\-]/i', '-', $handle);
    $handle = preg_replace('/-+/', '-', $handle);
    $handle = trim($handle, '-');
    return $handle ?: 'product-' . uniqid();
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
function cleanBoolean($value, $fallback = false) {

    if (is_bool($value)) {
        return $value;
    }

    $cleaned = strtolower(trim($value));
    if (in_array($cleaned, ['yes', 'true', '1', 'on', 'active'])) return true;
    if (in_array($cleaned, ['no', 'false', '0', 'off', 'inactive'])) return false;
    return $fallback;
}

// Helper: validate URL
function cleanUrl($url) {
    $cleaned = trim($url);
    return filter_var($cleaned, FILTER_VALIDATE_URL) ? $cleaned : '';
}

function generateTags($row){
    $tags = array_filter([
        getValue($row, 'Brand') ? 'brand:' . strtolower(getValue($row, 'Brand')) : null,
        getValue($row, 'Model'),
        getValue($row, 'Category Code') ? 'category:' . getValue($row, 'Category Code') : null,
        getValue($row, 'Amazon Standard ID Number (ASIN)') ? 'asin:' . getValue($row, 'Amazon Standard ID Number (ASIN)') : null,
        getValue($row, 'European Article Number (EAN)') ? 'ean:' . getValue($row, 'European Article Number (EAN)') : null,
        getValue($row, 'Manufacturer Part Number (MPN)') ? 'mpn:' . getValue($row, 'Manufacturer Part Number (MPN)') : null,
        (strtolower(getValue($row, 'Status')) === 'active') ? 'available' : null
    ]);

    return implode(', ', $tags);
}

// Shopify product fields
function buildShopifyProductPayload($row) {
    $title = getValue($row, 'Reward Name');
    $description = getValue($row, 'Reward Description');
    $vendor = getValue($row, 'Brand');
    $sku = getValue($row, 'SKU');
    $productCost = cleanNumeric(getValue($row, 'Product Cost'), '0');
    $msrp = cleanNumeric(getValue($row, 'MSRP'));
    $imageUrl = cleanUrl(getValue($row, 'Image URL'));
    $status = (strtolower(getValue($row, 'Status')) === 'active') ? 'active' : 'draft';
    $upc = getValue($row, 'Universal Product Code (UPC)');
    $model = getValue($row, 'Model');
    $quantity = cleanNumeric(getValue($row, 'Quantity'), '0');

    $handle = generateHandle($title);

    $tags = generateTags($row);

    $variant = [
        "sku" => $sku,
        "price" => strval($productCost),
        "barcode" => $upc,
        "requires_shipping" => true,
        "taxable" => true,
        "inventory_management" => "shopify",
        "inventory_policy" => "deny",
        "fulfillment_service" => "manual",
        "inventory_quantity" => intval($quantity)
    ];

    if (is_numeric($msrp) && $msrp > $productCost) {
        $variant["compare_at_price"] = strval($msrp);
    }

    $options = [];
    $variantOptions = [];

    if (!empty($model)) {
        $options[] = [
            "name" => "Model",
            "values" => [$model]
        ];
        $variantOptions = ["option1" => $model];
    } else {
        $options[] = [
            "name" => "Title",
            "values" => ["Default Title"]
        ];
        $variantOptions = ["option1" => "Default Title"];
    }

    $variant = array_merge($variant, $variantOptions);

    $product = [
        "title" => $title,
        "body_html" => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
        "vendor" => $vendor,
        "handle" => $handle,
        "tags" => $tags,
        "status" => $status,
        "options" => $options,
        "variants" => [$variant]
    ];

    if (!empty($imageUrl)) {
        $product["images"] = [[
            "src" => $imageUrl,
            "alt" => $title,
            "position" => 1
        ]];
    }
    
    return ["product" => $product];
}

// Shopify metafields mapping
function getMetafields($row) {
    $metafields = [];

    $metafieldMappings = [
        "shipping_cost" => ["number_decimal", getValue($row, "Shipping Cost")],
        "handling_cost" => ["number_decimal", getValue($row, "Handling Cost")],
        "service_charge" => ["number_decimal", getValue($row, "Service Charge")],
        "category_code" => ["single_line_text_field", getValue($row, "Category Code")],
        "regular_product_cost" => ["number_decimal", getValue($row, "Regular Product Cost")],
        "regular_shipping_cost" => ["number_decimal", getValue($row, "Regular Shipping Cost")],
        "regular_handling_cost" => ["number_decimal", getValue($row, "Regular Handling Cost")],
        "regular_service_charge" => ["number_decimal", getValue($row, "Regular Service Charge")],
        "is_on_sale" => ["boolean", cleanBoolean(getValue($row, "Is On Sale"))],
        "release_date" => ["date", getValue($row, "Release Date")],
        "new_duration" => ["number_integer", cleanNumeric(getValue($row, "New Duration"))],
        "asin" => ["single_line_text_field", getValue($row, "Amazon Standard ID Number (ASIN)")],
        "ean" => ["single_line_text_field", getValue($row, "European Article Number (EAN)")],
        "mpn" => ["single_line_text_field", getValue($row, "Manufacturer Part Number (MPN)")]
    ];

    foreach ($metafieldMappings as $key => [$type, $value]) {
        if ($type === "boolean") {
            $metafields[$key] = [$type, $value ? "true" : "false"];
        } else {
            if ($value !== null && $value !== '' && trim(strval($value)) !== '') {
                $metafields[$key] = [$type, $value];
            }
        }
    }
    
    return $metafields;
}

// Helper function to create individual metafield payload
function createMetafieldPayload($productId, $key, $type, $value, $namespace = "grs") {
    return [
        "metafield" => [
            "namespace" => $namespace,
            "key" => $key,
            "type" => $type,
            "value" => $value,
            "owner_resource" => "product",
            "owner_id" => $productId
        ]
    ];
}