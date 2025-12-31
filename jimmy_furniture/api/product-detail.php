<?php
// api/product-detail.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require "../config/database.php";
require "../models/Product.php";

$db = (new Database())->connect();
$product = new Product($db);

if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    $product_data = $product->find($product_id);
    
    if ($product_data) {
        echo json_encode([
            "success" => true,
            "product" => $product_data
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Product not found"
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Product ID is required"
    ]);
}
?>