<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
session_start();

require "../../config/database.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

$user = $_SESSION['user'];
$isAdmin = ($user['role'] === 'admin');

$db = (new Database())->connect();

if ($isAdmin) {
    
    $sql = "
        SELECT 
            o.*,
            CASE 
                WHEN o.user_id IS NOT NULL THEN u.email
                WHEN o.guest_email IS NOT NULL THEN o.guest_email
                ELSE 'Guest'
            END AS customer_name,
            COALESCE(o.guest_email, u.email) AS customer_email,
            c.name AS courier_name,
            COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN users c ON c.id = o.courier_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();
} else {
    // USER → only their orders
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
    $stmt->execute([
        ':user_id' => $user['id']
    ]);
}

echo json_encode([
    "success" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);

