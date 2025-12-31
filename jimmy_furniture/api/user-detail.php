<?php
// api/user-detail.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Admin access required"]);
    exit;
}

$db = (new Database())->connect();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        echo json_encode(["success" => false, "error" => "User ID required"]);
        exit;
    }
    
    // Get user details
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(["success" => false, "error" => "User not found"]);
        exit;
    }
    
    // Get user order statistics
    $order_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_spent,
            AVG(total_amount) as avg_order
        FROM orders 
        WHERE user_id = ? AND status != 'cancelled'
    ");
    $order_stmt->execute([$userId]);
    $order_stats = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "user" => array_merge($user, $order_stats)
    ]);
    exit;
}
?>