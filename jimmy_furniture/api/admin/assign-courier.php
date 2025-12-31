<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Forbidden"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order_id'], $data['courier_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing data"]);
    exit;
}

$db = (new Database())->connect();

/* validate courier */
$check = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'courier'");
$check->execute([$data['courier_id']]);
if (!$check->fetch()) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid courier"]);
    exit;
}

$db->beginTransaction();

try {
    // assign courier + mark shipped
    $db->prepare("
        UPDATE orders 
        SET courier_id = ?, status = 'shipped'
        WHERE id = ?
    ")->execute([$data['courier_id'], $data['order_id']]);

    // history entry
    $db->prepare("
        INSERT INTO order_status_history (order_id, status, note)
        VALUES (?, 'shipped', 'Courier assigned by admin')
    ")->execute([$data['order_id']]);

    $db->commit();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false]);
}
