<?php
header("Content-Type: application/json");
session_start();

require "../config/database.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['shipping_address']) ||
    empty($data['payment_method']) ||
    empty($data['email'])
) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing required data"]);
    exit;
}

$db = (new Database())->connect();

// Check if user is logged in
$user_id = isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;
$is_guest = !$user_id;

// Collect guest information
$guest_email = trim($data['email']);
$guest_name = trim($data['name'] ?? '');
$guest_phone = trim($data['phone'] ?? '');
$shipping_address = trim($data['shipping_address']);
$payment_method = trim($data['payment_method']);

/**
 * 1. Get cart items - different sources for logged-in vs guest
 */
if ($user_id) {
    // Logged-in user: get from database cart
    $cartStmt = $db->prepare("
        SELECT c.product_id, c.quantity, p.price, p.name, p.stock_quantity
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.user_id = ?
    ");
    $cartStmt->execute([$user_id]);
    $cartItems = $cartStmt->fetchAll();
} else {
    // Guest: get from local storage (passed via request)
    if (empty($data['cart_items']) || !is_array($data['cart_items'])) {
        echo json_encode(["success" => false, "error" => "Cart items required for guest checkout"]);
        exit;
    }
    
    // Validate and fetch product details from database
    $cartItems = [];
    foreach ($data['cart_items'] as $item) {
        $product_id = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        
        $stmt = $db->prepare("
            SELECT id as product_id, price, name, stock_quantity 
            FROM products 
            WHERE id = ?
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            if ($product['stock_quantity'] < $quantity) {
                echo json_encode([
                    "success" => false, 
                    "error" => "Insufficient stock for {$product['name']}"
                ]);
                exit;
            }
            
            $product['quantity'] = $quantity;
            $cartItems[] = $product;
        }
    }
}

if (empty($cartItems)) {
    echo json_encode(["success" => false, "error" => "Cart is empty"]);
    exit;
}

/**
 * 2. Calculate total
 */
$totalAmount = 0;
foreach ($cartItems as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}

/**
 * 3. Create order (transaction)
 */
$orderNumber = 'JIMMY-' . strtoupper(uniqid());

try {
    $db->beginTransaction();

    // Insert order - user_id can be NULL for guests
    $orderStmt = $db->prepare("
        INSERT INTO orders 
        (order_number, user_id, total_amount, status, shipping_address, payment_method,
         guest_email, guest_name, guest_phone, created_at)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())
    ");
    
    $orderStmt->execute([
        $orderNumber,
        $user_id, // NULL for guests
        $totalAmount,
        $shipping_address,
        $payment_method,
        $guest_email,
        $guest_name,
        $guest_phone
    ]);

    $orderId = $db->lastInsertId();

    // Insert order items and update stock
    $itemStmt = $db->prepare("
        INSERT INTO order_items 
        (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    
    $updateStockStmt = $db->prepare("
        UPDATE products 
        SET stock_quantity = stock_quantity - ?
        WHERE id = ?
    ");

    foreach ($cartItems as $item) {
        $itemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['quantity'],
            $item['price']
        ]);
        
        $updateStockStmt->execute([
            $item['quantity'],
            $item['product_id']
        ]);
    }

    // Clear cart only for logged-in users
    if ($user_id) {
        $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);
    }

    $db->commit();

    // Store order ID in session for guest tracking
    if ($is_guest) {
        $_SESSION['guest_order_id'] = $orderId;
        $_SESSION['guest_order_email'] = $guest_email;
    }

    echo json_encode([
        "success" => true,
        "order_id" => $orderId,
        "order_number" => $orderNumber,
        "total_amount" => $totalAmount,
        "is_guest" => $is_guest
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    error_log("Order creation failed: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => "Order creation failed. Please try again."
    ]);
}