<?php
// api/users.php
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
session_start();

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

$db = (new Database())->connect();

// GET - Get all users
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Admin access required"]);
        exit;
    }
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $count_stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $total = $count_stmt->fetch()['total'];
    $pages = ceil($total / $limit);
    
    // Get users with pagination
    $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "data" => $users,
        "total" => $total,
        "pages" => $pages,
        "current_page" => $page
    ]);
    exit;
}

// DELETE - Delete user
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Admin access required"]);
        exit;
    }
    
    // Get user ID from URL parameter or request body
    $userId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    // If not in URL, check request body
    if (!$userId) {
        $data = json_decode(file_get_contents("php://input"), true);
        $userId = isset($data['id']) ? $data['id'] : null;
    }
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "User ID required"]);
        exit;
    }
    
    // Don't allow deleting admin users or yourself
    $check_stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $check_stmt->execute([$userId]);
    $user = $check_stmt->fetch();
    
    if (!$user) {
        echo json_encode(["success" => false, "error" => "User not found"]);
        exit;
    }
    
    if ($user['role'] === 'admin') {
        echo json_encode(["success" => false, "error" => "Cannot delete admin users"]);
        exit;
    }
    
    // Don't allow deleting yourself
    if ($userId == $_SESSION['user']['id']) {
        echo json_encode(["success" => false, "error" => "Cannot delete your own account"]);
        exit;
    }
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Delete user's cart items
        $stmt1 = $db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt1->execute([$userId]);
        
        // Delete user's wishlist items
        $stmt2 = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt2->execute([$userId]);
        
        // Delete user's orders
        $stmt3 = $db->prepare("DELETE FROM orders WHERE user_id = ?");
        $stmt3->execute([$userId]);
        
        // Delete user
        $stmt4 = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt4->execute([$userId]);
        
        $db->commit();
        
        echo json_encode(["success" => true, "message" => "User deleted successfully"]);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Delete user error: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "Failed to delete user"]);
    }
    exit;
}

// PUT - Update user role
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Admin access required"]);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $userId = isset($_GET['id']) ? intval($_GET['id']) : (isset($data['id']) ? $data['id'] : null);
    $role = $data['role'] ?? null;
    
    if (!$userId || !$role) {
        echo json_encode(["success" => false, "error" => "User ID and role required"]);
        exit;
    }
    
    if (!in_array($role, ['admin', 'user','courier'])) {
        echo json_encode(["success" => false, "error" => "Invalid role"]);
        exit;
    }
    
    // Don't allow demoting yourself from admin
    if ($userId == $_SESSION['user']['id'] && $role !== 'admin') {
        echo json_encode(["success" => false, "error" => "Cannot remove admin role from yourself"]);
        exit;
    }
    
    try {
        $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $userId]);
        
        echo json_encode(["success" => true, "message" => "User role updated"]);
    } catch (PDOException $e) {
        error_log("Update user error: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "Failed to update user"]);
    }
    exit;
}

// If no method matches
http_response_code(405);
echo json_encode(["success" => false, "error" => "Method not allowed"]);
?>