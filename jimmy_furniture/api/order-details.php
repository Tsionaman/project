<?php
// api/order-details.php
header("Content-Type: application/json");
require "../config/database.php";
require "../models/Order.php";

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Order ID is required"]);
    exit;
}

$order_id = intval($_GET['id']);
$db = (new Database())->connect();
$order = new Order($db);

// Check if user is admin
$isAdmin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';

// Get order details
$order_details = $order->getOrderDetails($order_id, $isAdmin ? null : $_SESSION['user']['id']);

if ($order_details) {
    echo json_encode([
        "success" => true,
        "order" => $order_details
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "error" => "Order not found or access denied"
    ]);
}
?>