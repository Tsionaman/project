<?php
require "../../config/database.php";

if (!isset($_GET['tx_ref'])) {
    echo json_encode(["success" => false]);
    exit;
}

$tx_ref = $_GET['tx_ref'];
$secret = "CHASECK_TEST-FbmPPbBBPY2l3WIvWbdgiIOzuysqt33M";

$ch = curl_init("https://api.chapa.co/v1/transaction/verify/$tx_ref");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $secret"
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['status'] !== 'success' || $result['data']['status'] !== 'success') {
    echo json_encode(["success" => false]);
    exit;
}

// extract order number
$order_number = null;
foreach (explode('_', $tx_ref) as $part) {
    if (strpos($part, 'JIMMY-') === 0) {
        $order_number = $part;
        break;
    }
}

$db = (new Database())->connect();
$db->beginTransaction();

// confirm order
$db->prepare("
    UPDATE orders SET status='confirmed'
    WHERE order_number=?
")->execute([$order_number]);

// reduce stock
$items = $db->prepare("
    SELECT product_id, quantity FROM order_items
    WHERE order_id = (SELECT id FROM orders WHERE order_number=?)
");
$items->execute([$order_number]);

foreach ($items->fetchAll() as $item) {
    $db->prepare("
        UPDATE products SET stock_quantity = stock_quantity - ?
        WHERE id = ?
    ")->execute([$item['quantity'], $item['product_id']]);
}

$db->commit();

echo json_encode(["success" => true]);
