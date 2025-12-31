<?php
// api/cartPages.php - For public cart operations
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "../config/database.php";

session_start();

class CartPages {
    private PDO $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Check if user is logged in (for public pages)
     */
    private function isLoggedIn() {
        return isset($_SESSION['user']);
    }
    
    /**
     * Add to cart (public version)
     */
    public function addToCart($productId, $quantity = 1) {
        // Check if user is logged in
        if (!$this->isLoggedIn()) {
            return [
                "success" => false,
                "requires_login" => true,
                "message" => "Please login to add items to cart"
            ];
        }
        
        $userId = $_SESSION['user']['id'];
        
        try {
            // Check if product exists and has stock
            $productQuery = "SELECT id, name, price, stock_quantity, image_url FROM products WHERE id = :product_id";
            $productStmt = $this->conn->prepare($productQuery);
            $productStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $productStmt->execute();
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                return ["success" => false, "error" => "Product not found"];
            }
            
            if ($product['stock_quantity'] <= 0) {
                return ["success" => false, "error" => "Product out of stock"];
            }
            
            // Check if already in cart (using 'cart' table, not 'cart_items')
            $checkQuery = "SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([
                ':user_id' => $userId,
                ':product_id' => $productId
            ]);
            
            $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                // Update quantity
                $newQuantity = $item['quantity'] + $quantity;
                
                $updateQuery = "UPDATE cart SET quantity = :quantity WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $result = $updateStmt->execute([
                    ':quantity' => $newQuantity,
                    ':id' => $item['id']
                ]);
            } else {
                // Insert new item
                $insertQuery = "INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)";
                $insertStmt = $this->conn->prepare($insertQuery);
                $result = $insertStmt->execute([
                    ':user_id' => $userId,
                    ':product_id' => $productId,
                    ':quantity' => $quantity
                ]);
            }
            
            if ($result) {
                // Get updated cart count
                $countQuery = "SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id";
                $countStmt = $this->conn->prepare($countQuery);
                $countStmt->execute([':user_id' => $userId]);
                $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
                $cartCount = $countRow['total'] ?? 0;
                
                return [
                    "success" => true,
                    "message" => "Added to cart",
                    "cart_count" => (int)$cartCount,
                    "product_name" => $product['name'],
                    "product_price" => $product['price']
                ];
            }
            
            return ["success" => false, "error" => "Failed to add to cart"];
            
        } catch (PDOException $e) {
            error_log("Cart error: " . $e->getMessage());
            return [
                "success" => false,
                "error" => "Database error: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get cart items for user
     */
    public function getCart($userId) {
        try {
            $query = "SELECT c.*, p.name, p.price, p.image_url, p.stock_quantity 
                     FROM cart c 
                     JOIN products p ON c.product_id = p.id 
                     WHERE c.user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':user_id' => $userId]);
            
            $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                "success" => true,
                "data" => $cartItems
            ];
        } catch (PDOException $e) {
            return [
                "success" => false,
                "error" => "Failed to fetch cart"
            ];
        }
    }
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['product_id'])) {
            echo json_encode(["success" => false, "error" => "Missing product ID"]);
            exit;
        }
        
        $productId = intval($data['product_id']);
        $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
        
        $cart = new CartPages();
        $result = $cart->addToCart($productId, $quantity);
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "error" => "Server error: " . $e->getMessage()
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request to fetch cart
    try {
        if (!isset($_SESSION['user'])) {
            echo json_encode([
                "success" => false,
                "requires_login" => true,
                "message" => "Please login to view cart"
            ]);
            exit;
        }
        
        $userId = $_SESSION['user']['id'];
        $cart = new CartPages();
        $result = $cart->getCart($userId);
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "error" => "Server error"
        ]);
    }
} else {
    echo json_encode([
        "success" => false, 
        "error" => "Method not allowed"
    ]);
}
?>