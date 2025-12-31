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
require "../models/Wishlist.php";

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

$user_id = $_SESSION['user']['id'];
$db = (new Database())->connect();
$wishlist = new Wishlist($db);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get wishlist items
        $items = $wishlist->getUserWishlist($user_id);
        $count = $wishlist->getWishlistCount($user_id);
        
        echo json_encode([
            "success" => true,
            "data" => $items,
            "count" => $count
        ]);
        break;
        
    case 'POST':
        // Add to wishlist
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['product_id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing product ID"]);
            exit;
        }
        
        if ($wishlist->addToWishlist($user_id, $data['product_id'])) {
            $count = $wishlist->getWishlistCount($user_id);
            echo json_encode([
                "success" => true,
                "message" => "Added to wishlist",
                "wishlist_count" => $count
            ]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Failed to add to wishlist"]);
        }
        break;
        
    case 'DELETE':
        // Remove from wishlist
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['product_id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Missing product ID"]);
            exit;
        }
        
        if ($wishlist->removeFromWishlist($user_id, $data['product_id'])) {
            echo json_encode(["success" => true, "message" => "Removed from wishlist"]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Failed to remove from wishlist"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>