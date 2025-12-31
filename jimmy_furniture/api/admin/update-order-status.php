<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order_id'], $data['status'])) {
    http_response_code(400);
    echo json_encode(["success" => false]);
    exit;
}

$db = (new Database())->connect();

$db->beginTransaction();

try {
    //  Update main order status
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$data['status'], $data['order_id']]);

    // Insert into history
    $stmt = $db->prepare("
        INSERT INTO order_status_history (order_id, status, note)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $data['order_id'],
        $data['status'],
        $data['note'] ?? null
    ]);

    $db->commit();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false]);
}
