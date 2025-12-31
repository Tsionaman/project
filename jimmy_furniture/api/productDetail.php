<?php
// api/productDetail.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method not allowed"
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Product.php';

try {
    // Read query param
    $id = $_GET['id'] ?? null;

    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Valid product ID is required"
        ]);
        exit;
    }

    // Database connection
    $database = new Database();
    $db = $database->connect();

    // Create Product model
    $productModel = new Product($db);

    // Fetch single product by ID
    $product = $productModel->findById($id);

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Product not found"
        ]);
        exit;
    }
// Parse images
    $product['images'] = $productModel->getProductImages($product['image_url']);
    
    // Get related products (same category)
    $relatedProducts = $productModel->getByCategoryForSingleDetailPage($product['category_id'], 4, $id);
    
    // Also parse images for related products
    foreach ($relatedProducts as &$related) {
        $related['images'] = $productModel->getProductImages($related['image_url']);
    }
    
    echo json_encode([
        "success" => true,
        "data" => [
            "product" => $product,
            "related" => $relatedProducts
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server error",
        "details" => $e->getMessage()
    ]);
}