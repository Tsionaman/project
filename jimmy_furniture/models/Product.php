<?php

class Product {
    private $conn;
    private $table = "products";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all products with optional filters
    public function all($category = null, $search = null, $limit = null, $offset = 0) {
        // Join with categories table
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE 1=1";
        
        $params = [];
        
        if ($category) {
            $sql .= " AND c.slug = ?";
            $params[] = $category;
        }
        
        if ($search) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // Get single product by ID
public function find($id) {
    // Join with categories table to get category name
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ?";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    // Create new product (admin only)
 public function create($name, $description, $price, $image_url, $category_id, $stock_quantity) {
    // Change 'category' to 'category_id'
    $sql = "INSERT INTO products (name, description, price, image_url, category_id, stock_quantity) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $this->conn->prepare($sql);
    return $stmt->execute([$name, $description, $price, $image_url, $category_id, $stock_quantity]);
}

    // Update product (admin only)
public function update($id, $name, $description, $price, $image_url, $category_id, $stock_quantity) {
    // Change 'category' to 'category_id'
    $sql = "UPDATE products 
            SET name = ?, description = ?, price = ?, image_url = ?, 
                category_id = ?, stock_quantity = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
    $stmt = $this->conn->prepare($sql);
    return $stmt->execute([$name, $description, $price, $image_url, $category_id, $stock_quantity, $id]);
}

    // Delete product (admin only)
    public function delete($id) {
    try {
        $stmt = $this->conn->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Delete product error: " . $e->getMessage());
        return false;
    }
}

    // Get products by category
    public function getByCategory($category) {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} 
            WHERE category = :category 
            ORDER BY created_at DESC
        ");
        $stmt->execute([':category' => $category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getByCategoryForSingleDetailPage($category_id, $limit = 4, $exclude_id = null) {
        $query = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.category_id = :category_id 
                AND p.stock_quantity > 0";
        
        if ($exclude_id) {
            $query .= " AND p.id != :exclude_id";
        }
        
        $query .= " ORDER BY p.created_at DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":category_id", $category_id, PDO::PARAM_INT);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        
        if ($exclude_id) {
            $stmt->bindParam(":exclude_id", $exclude_id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductImages($image_url) {
        if (!$image_url) {
            return [
                'https://images.unsplash.com/photo-1550581190-9c1c48d21d6c?w=800'
            ];
        }
        
        // Split by comma and filter out empty strings
        $urls = preg_split('/\s*,\s*/', $image_url);
        $urls = array_filter($urls, function($url) {
            return !empty(trim($url));
        });
        
        // If no valid URLs, return default
        if (empty($urls)) {
            return [
                'https://images.unsplash.com/photo-1550581190-9c1c48d21d6c?w=800'
            ];
        }
        
        return array_values($urls);
    }

    // Search products
    public function search($keyword) {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} 
            WHERE name LIKE :keyword 
            OR description LIKE :keyword 
            OR category LIKE :keyword
            ORDER BY name
        ");
        $stmt->execute([':keyword' => "%$keyword%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update stock quantity
    public function updateStock($id, $quantity) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table} 
            SET stock_quantity = stock_quantity + :quantity 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':quantity' => $quantity
        ]);
    }
}
?>