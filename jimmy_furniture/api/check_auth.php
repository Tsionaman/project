<?php
header("Content-Type: application/json");
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode([
        "authenticated" => false
    ]);
    exit;
}

echo json_encode([
    "authenticated" => true,
    "user" => [
        "id"    => $_SESSION['user']['id'],
        "name"  => $_SESSION['user']['name'],
        "email" => $_SESSION['user']['email'],
        "role"  => $_SESSION['user']['role'] 
    ]
]);
