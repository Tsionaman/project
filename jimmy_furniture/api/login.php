<?php
// Enable error reporting at the VERY TOP
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require "../config/database.php";
require "../models/User.php";

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Email and password are required"]);
    exit;
}

$db = (new Database())->connect();
if (!$db) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$user = new User($db);
$result = $user->login($data['email'], $data['password']);

if ($result) {
    
    $role = isset($result['role']) ? $result['role'] : 'user';
    
    $_SESSION['user'] = [
        "id" => $result['id'],
        "name" => $result['name'],
        "email" => $result['email'],
        "role" => $role 
    ];
    
    echo json_encode([
        "success" => true, 
        "message" => "Login successful",
        "user" => $_SESSION['user']
    ]);
} else {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Invalid email or password"]);
}
?>