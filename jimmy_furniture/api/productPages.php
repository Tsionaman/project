<?php
// api/productPages.php

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
    // Read query params
    $category = $_GET['category'] ?? null;
    $search   = $_GET['search'] ?? null;
    $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
    $offset   = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    if (!$category) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Category is required"
        ]);
        exit;
    }

    // Database connection (PDO)
    $database = new Database();
    $db = $database->connect();

    // Use EXISTING Product model (unchanged)
    $productModel = new Product($db);

    // Fetch products by category slug
    $products = $productModel->all($category, $search, $limit, $offset);

    echo json_encode([
        "success" => true,
        "data" => $products,
        "count" => count($products)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server error",
        "details" => $e->getMessage()
    ]);
}
