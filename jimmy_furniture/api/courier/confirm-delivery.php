<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'courier') {
    http_response_code(403);
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false]);
    exit;
}

$courierId = $_SESSION['user']['id'];
$db = (new Database())->connect();

/* verify assignment */
$stmt = $db->prepare("
    SELECT id FROM orders
    WHERE id = ? AND courier_id = ?
");
$stmt->execute([$data['order_id'], $courierId]);

if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Not assigned"]);
    exit;
}

$db->beginTransaction();

try {
    // mark delivered
    $db->prepare("
        UPDATE orders SET status = 'delivered'
        WHERE id = ?
    ")->execute([$data['order_id']]);

    // history entry
    $db->prepare("
        INSERT INTO order_status_history (order_id, status, note)
        VALUES (?, 'delivered', 'Delivered by courier')
    ")->execute([$data['order_id']]);

    $db->commit();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false]);
}
