<?php
// models/Wishlist.php
class Wishlist {
    private $conn;
    private $table = "wishlist";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get user wishlist with product details
    public function getUserWishlist($user_id) {
        $stmt = $this->conn->prepare("
            SELECT w.*, p.name, p.price, p.image_url, p.description, p.stock_quantity
            FROM {$this->table} w
            JOIN products p ON w.product_id = p.id
            WHERE w.user_id = :user_id
            ORDER BY w.added_at DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll();
    }

    // Add item to wishlist
    public function addToWishlist($user_id, $product_id) {
        // Check if product exists
        $productStmt = $this->conn->prepare("
            SELECT id FROM products WHERE id = :product_id
        ");
        $productStmt->execute([':product_id' => $product_id]);
        
        if (!$productStmt->fetch()) {
            return false; // Product doesn't exist
        }

        // Check if already in wishlist
        $checkStmt = $this->conn->prepare("
            SELECT id FROM {$this->table} 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        $checkStmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
        
        if ($checkStmt->fetch()) {
            return true; // Already exists, but that's okay
        }

        // Add to wishlist
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table} (user_id, product_id)
            VALUES (:user_id, :product_id)
        ");
        return $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
    }

    // Remove item from wishlist
    public function removeFromWishlist($user_id, $product_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM {$this->table} 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        return $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
    }

    // Check if product is in wishlist
    public function isInWishlist($user_id, $product_id) {
        $stmt = $this->conn->prepare("
            SELECT id FROM {$this->table} 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
        return (bool)$stmt->fetch();
    }

    // Get wishlist count
    public function getWishlistCount($user_id) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM {$this->table} 
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?: 0;
    }

    // Move wishlist item to cart
    public function moveToCart($user_id, $product_id, $quantity = 1) {
        // First add to cart
        $cart = new Cart($this->conn);
        $added = $cart->addToCart($user_id, $product_id, $quantity);
        
        if ($added) {
            // Remove from wishlist
            $this->removeFromWishlist($user_id, $product_id);
        }
        
        return $added;
    }
}
?>