<?php
header("Content-Type: application/json");

require "../../config/database.php";

/**
 * Chapa sends tx_ref via GET
 */
if (!isset($_GET['tx_ref'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing tx_ref"]);
    exit;
}

$tx_ref = $_GET['tx_ref'];

/**
 * Extract order number from tx_ref
 * Format: JIMMY_ORDERNUMBER_timestamp
 */
$parts = explode("_", $tx_ref);
if (count($parts) < 2) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid tx_ref"]);
    exit;
}

$order_number = null;

foreach ($parts as $part) {
    if (strpos($part, 'JIMMY-') === 0) {
        $order_number = $part;
        break;
    }
}

if (!$order_number) {
    throw new Exception("Order number not found in tx_ref");
}

/**
 * Chapa verification
 */
$chapaSecretKey = "CHASECK_TEST-FbmPPbBBPY2l3WIvWbdgiIOzuysqt33M";

$ch = curl_init("https://api.chapa.co/v1/transaction/verify/$tx_ref");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $chapaSecretKey"
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (
    !isset($result['status']) ||
    $result['status'] !== 'success' ||
    $result['data']['status'] !== 'success'
) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Payment not verified"]);
    exit;
}

$db = (new Database())->connect();

/**
 * Start transaction
 */
try {
    $db->beginTransaction();

    // Get order
    $orderStmt = $db->prepare("
        SELECT id, status 
        FROM orders 
        WHERE order_number = ?
        FOR UPDATE
    ");
    $orderStmt->execute([$order_number]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new Exception("Order not found");
    }

    if ($order['status'] === 'confirmed') {
        // Idempotency: already processed
        $db->commit();
        echo json_encode(["success" => true, "message" => "Order already confirmed"]);
        exit;
    }

    $orderId = $order['id'];

    /**
     * Reduce stock
     */
    $itemsStmt = $db->prepare("
        SELECT oi.product_id, oi.quantity
        FROM order_items oi
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();

    foreach ($items as $item) {
        $updateStock = $db->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity - ?
            WHERE id = ? AND stock_quantity >= ?
        ");
        $updateStock->execute([
            $item['quantity'],
            $item['product_id'],
            $item['quantity']
        ]);

        if ($updateStock->rowCount() === 0) {
            throw new Exception("Insufficient stock");
        }
    }

    /**
     * Update order status
     */
    $updateOrder = $db->prepare("
        UPDATE orders
        SET status = 'confirmed'
        WHERE id = ?
    ");
    $updateOrder->execute([$orderId]);

    $db->commit();

    echo json_encode([
        "success" => true,
        "order_number" => $order_number,
        "status" => "confirmed"
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
