<?php

// Shopify product fields
function buildShopifyProductPayload($row) {
    return [
        "product" => [
            "title" => $row["Reward Name"] ?? "Untitled",
            "body_html" => $row["Reward Description"] ?? "",
            "vendor" => $row["Brand"] ?? "Unknown",
            "tags" => $row["Model"] ?? "",
            "status" => (strtolower($row["Status"] ?? "") === "active") ? "active" : "draft",
            "variants" => [[
                "sku" => $row["SKU"] ?? "",
                "price" => strval($row["Product Cost"] ?? "0"),
                "compare_at_price" => strval($row["MSRP"] ?? ""),
                "barcode" => $row["Universal Product Code (UPC)"] ?? ""
            ]],
            "images" => [
                ["src" => $row["Image URL"] ?? ""]
            ]
        ]
    ];
}

// Shopify metafields mapping
function getMetafields($row) {
    return [
        "shipping_cost" => ["number_decimal", $row["Shipping Cost"] ?? null],
        "handling_cost" => ["number_decimal", $row["Handling Cost"] ?? null],
        "service_charge" => ["number_decimal", $row["Service Charge"] ?? null],
        "category_code" => ["single_line_text_field", $row["Category Code"] ?? null],
        "regular_product_cost" => ["number_decimal", $row["Regular Product Cost"] ?? null],
        "regular_shipping_cost" => ["number_decimal", $row["Regular Shipping Cost"] ?? null],
        "regular_handling_cost" => ["number_decimal", $row["Regular Handling Cost"] ?? null],
        "regular_service_charge" => ["number_decimal", $row["Regular Service Charge"] ?? null],
        "is_on_sale" => ["boolean", $row["Is On Sale"] ?? null],
        "release_date" => ["date", $row["Release Date"] ?? null],
        "new_duration" => ["number_integer", $row["New Duration"] ?? null],
        "asin" => ["single_line_text_field", $row["Amazon Standard ID Number (ASIN)"] ?? null]
    ];
}
