<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false]);
    exit;
}

$db = (new Database())->connect();

$stmt = $db->prepare("
    SELECT id, name
    FROM users
    WHERE role = 'courier'
");

$stmt->execute();

echo json_encode([
    "success" => true,
    "data" => $stmt->fetchAll()
]);
