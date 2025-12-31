<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require "../config/database.php";
require "../models/Cart.php";

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

$user_id = $_SESSION['user']['id'];
$db = (new Database())->connect();
$cart = new Cart($db);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get cart items
        $items = $cart->getUserCart($user_id);
        $total = $cart->getCartTotal($user_id);
        $count = $cart->getCartCount($user_id);
        
        echo json_encode([
            "success" => true,
            "data" => $items,
            "summary" => [
                "total" => $total,
                "count" => $count
            ]
        ]);
        break;
        
    case 'POST':
        // Add to cart
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['product_id']) || !isset($data['quantity'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing required fields"]);
            exit;
        }
        
        if ($cart->addToCart($user_id, $data['product_id'], $data['quantity'])) {
            $count = $cart->getCartCount($user_id);
            echo json_encode([
                "success" => true,
                "message" => "Added to cart",
                "cart_count" => $count
            ]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Failed to add to cart. Check product availability."]);
        }
        break;
        
    case 'PUT':
        // Update quantity
        parse_str(file_get_contents("php://input"), $data);
        $data = json_decode(key($data), true);
        
        if (!isset($data['item_id']) || !isset($data['quantity'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing required fields"]);
            exit;
        }
        
        if ($cart->updateQuantity($user_id, $data['item_id'], $data['quantity'])) {
            echo json_encode(["success" => true, "message" => "Quantity updated"]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Failed to update quantity"]);
        }
        break;
        
    case 'DELETE':
        // Remove from cart
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['item_id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing item ID"]);
            exit;
        }
        
        if ($cart->removeFromCart($user_id, $data['item_id'])) {
            echo json_encode(["success" => true, "message" => "Removed from cart"]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Failed to remove item"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>