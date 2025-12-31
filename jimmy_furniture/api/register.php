<?php
header("Content-Type: application/json");
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

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "All fields are required"]);
    exit;
}

$db = (new Database())->connect();
if (!$db) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$user = new User($db);

if ($user->register($data['name'], $data['email'], $data['password'])) {
    echo json_encode(["success" => true, "message" => "Registration successful"]);
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Registration failed. Email may already exist."]);
}
?>