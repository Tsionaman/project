<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

// Admin only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Forbidden"]);
    exit;
}

$db = (new Database())->connect();

$stmt = $db->prepare("
    SELECT id, name, email
    FROM users
    WHERE role = 'courier'
    ORDER BY name ASC
");

$stmt->execute();

echo json_encode([
    "success" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
