<?php
header("Content-Type: application/json");
session_start();

require "../../config/database.php";

$chapaSecretKey = "CHASECK_TEST-FbmPPbBBPY2l3WIvWbdgiIOzuysqt33M";

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['order_id']) ||
    empty($data['order_number']) ||
    empty($data['amount']) ||
    empty($data['email'])
) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing required data"]);
    exit;
}

$order_id = $data['order_id'];
$order_number = $data['order_number'];
$amount = $data['amount'];
$email = $data['email'];
$is_guest = isset($data['is_guest']) ? (bool)$data['is_guest'] : false;

$db = (new Database())->connect();

// Check if user is guest or logged in
if ($is_guest) {
    // Guest user: validate order email matches
    $stmt = $db->prepare("SELECT guest_email FROM orders WHERE id = ? AND user_id IS NULL");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Guest order not found"]);
        exit;
    }
    
    if ($order['guest_email'] !== $email) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Email does not match order"]);
        exit;
    }
    
    // Store guest order in session for tracking
    $_SESSION['guest_order_id'] = $order_id;
    $_SESSION['guest_order_email'] = $email;
    
} else {
    // Logged-in user
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Not authenticated"]);
        exit;
    }
    
    // Verify order belongs to logged-in user
    $stmt = $db->prepare("SELECT user_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order || $order['user_id'] != $_SESSION['user']['id']) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Not authorized for this order"]);
        exit;
    }
}

//  guest name extraction
$tx_ref = "JIMMY_" . $order_number . "_" . time();

// Get customer name for Chapa
$name_stmt = $db->prepare("
    SELECT 
        CASE 
            WHEN o.user_id IS NOT NULL THEN u.name 
            ELSE o.guest_name 
        END as customer_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
");
$name_stmt->execute([$order_id]);
$name_data = $name_stmt->fetch();
$customer_name = $name_data['customer_name'] ?? 'Customer';

$payload = [
    "amount" => (string)$amount,
    "email" => $email,
    "tx_ref" => $tx_ref,
    "callback_url" => "http://localhost/jimmy_furniture/api/payments/chapa_callback.php",
    "return_url" => "http://localhost/jimmy_furniture/payment-success.html?tx_ref=$tx_ref",
    "customization" => [
        "title" => "Jimmy Furniture",
        "description" => "Order " . $order_number
    ],
    "first_name" => explode(' ', $customer_name)[0] ?? 'Customer',
    "last_name" => implode(' ', array_slice(explode(' ', $customer_name), 1)) ?? '',
    "meta" => [
        "order_id" => $order_id,
        "order_number" => $order_number,
        "user_type" => $is_guest ? 'guest' : 'registered'
    ]
];

$ch = curl_init("https://api.chapa.co/v1/transaction/initialize");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $chapaSecretKey",
        "Content-Type: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!isset($result['status']) || $result['status'] !== 'success') {
    echo json_encode([
        "success" => false,
        "error" => "Failed to initialize payment",
        "details" => $result
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "checkout_url" => $result['data']['checkout_url'],
    "tx_ref" => $tx_ref
]);