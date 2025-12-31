<?php
// models/Cart.php
class Cart {
    private $conn;
    private $table = "cart";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get user cart with product details
    public function getUserCart($user_id) {
        $stmt = $this->conn->prepare("
            SELECT c.*, p.name, p.price, p.image_url, p.stock_quantity
            FROM {$this->table} c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = :user_id
            ORDER BY c.added_at DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll();
    }

    // Add item to cart
    public function addToCart($user_id, $product_id, $quantity = 1) {
        // Check if product exists and has stock
        $productStmt = $this->conn->prepare("
            SELECT stock_quantity FROM products WHERE id = :product_id
        ");
        $productStmt->execute([':product_id' => $product_id]);
        $product = $productStmt->fetch();
        
        if (!$product) {
            return false; // Product doesn't exist
        }
        
        // Check stock availability
        if ($product['stock_quantity'] < $quantity) {
            return false; // Not enough stock
        }

        // Check if item already exists in cart
        $checkStmt = $this->conn->prepare("
            SELECT id, quantity FROM {$this->table} 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        $checkStmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            
            // Check if new quantity exceeds stock
            if ($product['stock_quantity'] < $newQuantity) {
                return false;
            }
            
            $stmt = $this->conn->prepare("
                UPDATE {$this->table} 
                SET quantity = :quantity 
                WHERE id = :id
            ");
            return $stmt->execute([
                ':quantity' => $newQuantity,
                ':id' => $existing['id']
            ]);
        } else {
            // Insert new item
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} (user_id, product_id, quantity)
                VALUES (:user_id, :product_id, :quantity)
            ");
            return $stmt->execute([
                ':user_id' => $user_id,
                ':product_id' => $product_id,
                ':quantity' => $quantity
            ]);
        }
    }

    // Update cart item quantity
    public function updateQuantity($user_id, $item_id, $quantity) {
        // Get product info to check stock
        $checkStmt = $this->conn->prepare("
            SELECT p.stock_quantity 
            FROM {$this->table} c
            JOIN products p ON c.product_id = p.id
            WHERE c.id = :id AND c.user_id = :user_id
        ");
        $checkStmt->execute([':id' => $item_id, ':user_id' => $user_id]);
        $result = $checkStmt->fetch();
        
        if (!$result || $quantity <= 0) {
            return false;
        }
        
        if ($result['stock_quantity'] < $quantity) {
            return false; // Not enough stock
        }
        
        $stmt = $this->conn->prepare("
            UPDATE {$this->table} 
            SET quantity = :quantity 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([
            ':id' => $item_id,
            ':user_id' => $user_id,
            ':quantity' => $quantity
        ]);
    }

    // Remove item from cart
    public function removeFromCart($user_id, $item_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM {$this->table} 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([':id' => $item_id, ':user_id' => $user_id]);
    }

    // Clear user's cart
    public function clearCart($user_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM {$this->table} WHERE user_id = :user_id
        ");
        return $stmt->execute([':user_id' => $user_id]);
    }

    // Get cart total
    public function getCartTotal($user_id) {
        $stmt = $this->conn->prepare("
            SELECT SUM(c.quantity * p.price) as total
            FROM {$this->table} c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch();
        return $result['total'] ?: 0;
    }

    // Get cart count
    public function getCartCount($user_id) {
        $stmt = $this->conn->prepare("
            SELECT SUM(quantity) as count
            FROM {$this->table} 
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?: 0;
    }
}
?>