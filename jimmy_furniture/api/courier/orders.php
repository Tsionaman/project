<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'courier') {
    http_response_code(403);
    echo json_encode(["success" => false]);
    exit;
}

$courierId = $_SESSION['user']['id'];
$db = (new Database())->connect();

$stmt = $db->prepare("
    SELECT 
        o.*,
        CASE 
            WHEN o.user_id IS NULL THEN o.guest_email
            ELSE u.email
        END AS customer_email,
        CASE
            WHEN o.user_id IS NULL THEN o.guest_name
            ELSE u.name
        END AS customer_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.courier_id = ?
    AND o.status = 'shipped'
    ORDER BY o.created_at DESC
");

$stmt->execute([$courierId]);

echo json_encode([
    "success" => true,
    "data" => $stmt->fetchAll()
]);
