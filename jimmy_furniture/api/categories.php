<?php
// api/categories.php
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

session_start();

// Check admin access for write operations
function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

$db = (new Database())->connect();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get single category by ID or slug
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($category) {
                // Get parent category name if exists
                if ($category['parent_id']) {
                    $parent_stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
                    $parent_stmt->execute([$category['parent_id']]);
                    $parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);
                    $category['parent_name'] = $parent ? $parent['name'] : null;
                }
                
                echo json_encode(["success" => true, "category" => $category]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Category not found"]);
            }
            exit;
        }
        
        // Get all categories with optional filters
        $activeOnly = isset($_GET['active']) ? $_GET['active'] == 'true' : false;
        $withCounts = isset($_GET['with_counts']) ? $_GET['with_counts'] == 'true' : false;
        
        $query = "SELECT c.*";
        
        if ($withCounts) {
            $query .= ", COUNT(p.id) as product_count";
        }
        
        $query .= " FROM categories c";
        
        if ($withCounts) {
            $query .= " LEFT JOIN products p ON c.id = p.category_id";
        }
        
        if ($activeOnly) {
            $query .= " WHERE c.is_active = TRUE";
        }
        
        $query .= " GROUP BY c.id";
        $query .= " ORDER BY c.sort_order ASC, c.name ASC";
        
        $stmt = $db->query($query);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["success" => true, "data" => $categories]);
        break;
        
    case 'POST':
        // Create new category (admin only)
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Admin access required"]);
            exit;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        $required = ['name', 'slug'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Missing field: $field"]);
                exit;
            }
        }
        
        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Slug can only contain lowercase letters, numbers, and hyphens"]);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO categories (name, slug, description, parent_id, image_url, is_active, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['slug'],
                $data['description'] ?? '',
                $data['parent_id'] ?? null,
                $data['image_url'] ?? null,
                $data['is_active'] ?? true,
                $data['sort_order'] ?? 0
            ]);
            
            $categoryId = $db->lastInsertId();
            
            // Get the created category
            $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true, 
                "message" => "Category created successfully",
                "category" => $category
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Category with this name or slug already exists"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Failed to create category: " . $e->getMessage()]);
            }
        }
        break;
        
    case 'PUT':
        // Update category (admin only)
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Admin access required"]);
            exit;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        $categoryId = isset($_GET['id']) ? intval($_GET['id']) : (isset($data['id']) ? $data['id'] : null);
        
        if (!$categoryId) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Category ID required"]);
            exit;
        }
        
        // Check if category exists
        $check_stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
        $check_stmt->execute([$categoryId]);
        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Category not found"]);
            exit;
        }
        
        // Build update query
        $fields = [];
        $values = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $values[] = $data['name'];
        }
        
        if (isset($data['slug'])) {
            // Validate slug format
            if (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Slug can only contain lowercase letters, numbers, and hyphens"]);
                exit;
            }
            $fields[] = "slug = ?";
            $values[] = $data['slug'];
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $values[] = $data['description'];
        }
        
        if (isset($data['parent_id'])) {
            // Prevent setting parent to itself
            if ($data['parent_id'] == $categoryId) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Category cannot be its own parent"]);
                exit;
            }
            $fields[] = "parent_id = ?";
            $values[] = $data['parent_id'] ?: null;
        }
        
        if (isset($data['image_url'])) {
            $fields[] = "image_url = ?";
            $values[] = $data['image_url'];
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $values[] = (bool)$data['is_active'];
        }
        
        if (isset($data['sort_order'])) {
            $fields[] = "sort_order = ?";
            $values[] = intval($data['sort_order']);
        }
        
        if (empty($fields)) {
            echo json_encode(["success" => false, "error" => "No fields to update"]);
            exit;
        }
        
        $values[] = $categoryId;
        
        try {
            $sql = "UPDATE categories SET " . implode(", ", $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            
            echo json_encode(["success" => true, "message" => "Category updated successfully"]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Category with this name or slug already exists"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Failed to update category: " . $e->getMessage()]);
            }
        }
        break;
        
    case 'DELETE':
        // Delete category (admin only)
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Admin access required"]);
            exit;
        }
        
        $categoryId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$categoryId) {
            $data = json_decode(file_get_contents("php://input"), true);
            $categoryId = isset($data['id']) ? $data['id'] : null;
        }
        
        if (!$categoryId) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Category ID required"]);
            exit;
        }
        
        try {
            // Check if category has products
            $check_stmt = $db->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
            $check_stmt->execute([$categoryId]);
            $result = $check_stmt->fetch();
            
            if ($result['product_count'] > 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Cannot delete category with products. Move or delete products first."]);
                exit;
            }
            
            // Check if category has subcategories
            $check_stmt = $db->prepare("SELECT COUNT(*) as child_count FROM categories WHERE parent_id = ?");
            $check_stmt->execute([$categoryId]);
            $result = $check_stmt->fetch();
            
            if ($result['child_count'] > 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Cannot delete category with subcategories. Delete or reassign subcategories first."]);
                exit;
            }
            
            // Delete the category
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Category deleted successfully"]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Category not found"]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to delete category: " . $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>