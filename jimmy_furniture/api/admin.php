<?php
// api/admin.php
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
require "../models/User.php";
require "../models/Product.php";

session_start();

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Admin access required"]);
    exit;
}

$db = (new Database())->connect();
$user = new User($db);
$product = new Product($db);

// Get dashboard stats
$users_stmt = $db->query("SELECT COUNT(*) as count FROM users");
$users_count = $users_stmt->fetch()['count'];

$products_stmt = $db->query("SELECT COUNT(*) as count FROM products");
$products_count = $products_stmt->fetch()['count'];

$orders_stmt = $db->query("SELECT COUNT(*) as count FROM orders");
$orders_count = $orders_stmt->fetch()['count'];

$revenue_stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
$revenue = $revenue_stmt->fetch()['total'] ?: 0;

// Get recent products
$recent_products_stmt = $db->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5");
$recent_products = $recent_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent orders with user info
$recent_orders_stmt = $db->query("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent users
$recent_users_stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "stats" => [
        "total_users" => $users_count,
        "total_products" => $products_count,
        "total_orders" => $orders_count,
        "total_revenue" => $revenue
    ],
    "recent_products" => $recent_products,
    "recent_orders" => $recent_orders,
    "recent_users" => $recent_users
]);
?>