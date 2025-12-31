<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["success" => false]);
    exit;
}

$orderId = $_GET['id'] ?? null;
$userId  = $_SESSION['user']['id'];

$db = (new Database())->connect();

//  Order
$stmt = $db->prepare("
    SELECT id, order_number, status, total_amount, created_at
    FROM orders
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(["success" => false]);
    exit;
}

// Items
$items = $db->prepare("
    SELECT p.name, oi.quantity, oi.price
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");
$items->execute([$orderId]);

// Status history
$history = $db->prepare("
    SELECT status, created_at
    FROM order_status_history
    WHERE order_id = ?
    ORDER BY created_at ASC
");
$history->execute([$orderId]);

echo json_encode([
    "success" => true,
    "order" => $order,
    "items" => $items->fetchAll(),
    "history" => $history->fetchAll()
]);
