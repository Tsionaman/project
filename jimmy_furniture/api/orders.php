<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
session_start();

require "../config/database.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

$user = $_SESSION['user'];
$isAdmin = ($user['role'] === 'admin');

$db = (new Database())->connect();

// ==================== HANDLE DELETE REQUEST ====================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Admin access required"]);
        exit;
    }
    
    // Get order ID from URL
    $orderId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$orderId) {
        echo json_encode(["success" => false, "error" => "Order ID required"]);
        exit;
    }
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Delete order items first (foreign key constraint)
        $sql = "DELETE FROM order_items WHERE order_id = :order_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        // Delete the order
        $sql = "DELETE FROM orders WHERE id = :order_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        $db->commit();
        
        echo json_encode(["success" => true, "message" => "Order deleted successfully"]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["success" => false, "error" => "Failed to delete order: " . $e->getMessage()]);
    }
    exit;
}

// ==================== HANDLE PUT REQUEST (UPDATE STATUS) ====================
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Admin access required"]);
        exit;
    }
    
    // Get data from request body
    $data = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($data['order_id']) ? intval($data['order_id']) : null;
    $status = isset($data['status']) ? trim($data['status']) : null;
    
    if (!$orderId || !$status) {
        echo json_encode(["success" => false, "error" => "Order ID and status required"]);
        exit;
    }
    
    // Validate status
    $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowedStatuses)) {
        echo json_encode(["success" => false, "error" => "Invalid status value"]);
        exit;
    }
    
    try {
        $sql = "UPDATE orders SET status = :status WHERE id = :order_id";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':status' => $status,
            ':order_id' => $orderId
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Order status updated to $status"]);
        } else {
            echo json_encode(["success" => false, "error" => "Order not found or status unchanged"]);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    }
    exit;
}

// ==================== HANDLE GET REQUEST (LIST ORDERS) ====================
if ($isAdmin) {
    $sql = "
        SELECT 
            o.*,
            COALESCE(u.email, o.guest_email, 'Guest') AS customer_name,
            COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();
} else {
    $sql = "
        SELECT 
            o.*,
            COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.user_id = :user_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user['id']]);
}

echo json_encode([
    "success" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);