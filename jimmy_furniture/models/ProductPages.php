<?php
class ProductPages {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Get products by category slug
     */
    public function getProductsByCategorySlug($categorySlug, $search = null, $limit = null, $offset = 0) {
        // First, get the category ID from slug
        $categoryQuery = "SELECT id FROM categories WHERE slug = ? AND is_active = TRUE LIMIT 1";
        $categoryStmt = $this->conn->prepare($categoryQuery);
        $categoryStmt->bind_param("s", $categorySlug);
        $categoryStmt->execute();
        $categoryResult = $categoryStmt->get_result();
        $category = $categoryResult->fetch_assoc();
        
        if (!$category) {
            return [];
        }
        
        $categoryId = $category['id'];
        
        // Get products
        $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.category_id = ?";
        
        $params = [$categoryId];
        $types = "i";
        
        if ($search) {
            $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }
        
        $query .= " ORDER BY p.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            $types .= "ii";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    /**
     * Get product count by category slug
     */
    public function getProductCountByCategorySlug($categorySlug) {
        $categoryQuery = "SELECT id FROM categories WHERE slug = ? AND is_active = TRUE LIMIT 1";
        $categoryStmt = $this->conn->prepare($categoryQuery);
        $categoryStmt->bind_param("s", $categorySlug);
        $categoryStmt->execute();
        $categoryResult = $categoryStmt->get_result();
        $category = $categoryResult->fetch_assoc();
        
        if (!$category) {
            return 0;
        }
        
        $categoryId = $category['id'];
        
        $query = "SELECT COUNT(*) as total FROM products WHERE category_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['total'] ?? 0;
    }
}