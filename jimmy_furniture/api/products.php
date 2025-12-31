<?php
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

session_start();

// Check admin access for write operations
function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

$db = (new Database())->connect();
$product = new Product($db);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get single product by ID
        if (isset($_GET['id'])) {
            $product_data = $product->find(intval($_GET['id']));
            
            if ($product_data) {
                echo json_encode(["success" => true, "product" => $product_data]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Product not found"]);
            }
            exit;
        }
        
        // Get all products with optional filters
        $category = isset($_GET['category']) ? $_GET['category'] : null;
        $search = isset($_GET['search']) ? $_GET['search'] : null;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : null;
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        
        $products = $product->all($category, $search, $limit, $offset);
        echo json_encode(["success" => true, "data" => $products]);
        break;
        
    case 'POST':
        // Create new product (admin only)
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Admin access required"]);
            exit;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        $required = ['name', 'description', 'price', 'image_url', 'category'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Missing field: $field"]);
                exit;
            }
        }
        
        $stock_quantity = isset($data['stock_quantity']) ? $data['stock_quantity'] : 0;
        
        if ($product->create(
            $data['name'],
            $data['description'],
            $data['price'],
            $data['image_url'],
            $data['category_id'],
            $stock_quantity
        )) {
            echo json_encode(["success" => true, "message" => "Product created successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to create product"]);
        }
        break;
        
    case 'PUT':
        // Update product (admin only)
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Admin access required"]);
            exit;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing product ID"]);
            exit;
        }
        
        // Get existing product
        $existing = $product->find($data['id']);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Product not found"]);
            exit;
        }
        
        // Merge with existing data
        $updateData = array_merge($existing, $data);
        
        if ($product->update(
            $updateData['id'],
            $updateData['name'],
            $updateData['description'],
            $updateData['price'],
            $updateData['image_url'],
            $updateData['category_id'],
            $updateData['stock_quantity']
        )) {
            echo json_encode(["success" => true, "message" => "Product updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to update product"]);
        }
        break;
                
        case 'DELETE':
            // Delete product (admin only)
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(["success" => false, "error" => "Admin access required"]);
                exit;
            }
            
            // Get product ID from URL parameter or request body
            $productId = isset($_GET['id']) ? intval($_GET['id']) : null;
            
            // If not in URL, check request body
            if (!$productId) {
                $data = json_decode(file_get_contents("php://input"), true);
                $productId = isset($data['id']) ? $data['id'] : null;
            }
            
            if (!$productId) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Missing product ID"]);
                exit;
            }
            
            if ($product->delete($productId)) {
                echo json_encode(["success" => true, "message" => "Product deleted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Failed to delete product"]);
            }
            break;
        
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>


