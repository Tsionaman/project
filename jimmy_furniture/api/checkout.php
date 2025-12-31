<?php
// api/checkout.php
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
require "../models/Order.php";

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

$user_id = $_SESSION['user']['id'];
$db = (new Database())->connect();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        // Process checkout
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['shipping_address']) || !isset($data['payment_method'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing required fields"]);
            exit;
        }
        
        // Calculate cart total
        $cart = new Cart($db);
        $cart_total = $cart->getCartTotal($user_id);
        
        if ($cart_total <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Cart is empty"]);
            exit;
        }
        
        // Apply bundle discount if applicable
        $cart_items = $cart->getUserCart($user_id);
        $total_items = array_sum(array_column($cart_items, 'quantity'));
        
        if ($total_items >= 4) {
            $cart_total *= 0.85; // 15% discount
        }
        
        // Add shipping cost
        $shipping_cost = 49.99;
        $final_total = $cart_total + $shipping_cost;
        
        // Create order
        $order = new Order($db);
        $result = $order->create(
            $user_id,
            $final_total,
            $data['shipping_address'],
            $data['payment_method']
        );
        
        if ($result['success']) {
            echo json_encode([
                "success" => true,
                "message" => "Order placed successfully",
                "order_number" => $result['order_number']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $result['error']]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>