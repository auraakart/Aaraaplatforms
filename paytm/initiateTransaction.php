<?php

header('Content-Type: application/json');

require_once "PaytmChecksum.php";

// Read JSON or POST
$input = json_decode(file_get_contents("php://input"), true);

$orderId = $input['order_id'] ?? $_POST['order_id'] ?? '';
$amount  = $input['amount'] ?? $_POST['amount'] ?? '';

if (empty($orderId) || empty($amount)) {
    echo json_encode([
        "status" => false,
        "message" => "order_id and amount are required"
    ]);
    exit;
}

// Merchant Details
$mid = "KaySid58626786666048";
$merchantKey = "XDxYCucTL6MJI0nE";
$callbackUrl = "https://madrasmilk.aaraakart.com/paytm/response.php";

$body = [
    "requestType" => "Payment",
    "mid" => $mid,
    "websiteName" => "",
    "orderId" => $orderId,
    "callbackUrl" => $callbackUrl,
    "txnAmount" => [
        "value" => number_format($amount, 2, '.', ''),
        "currency" => "INR"
    ],
    "userInfo" => [
        "custId" => "CUST_" . time()
    ]
];

$signature = PaytmChecksum::generateSignature(
    json_encode($body, JSON_UNESCAPED_SLASHES),
    $merchantKey
);

$request = [
    "body" => $body,
    "head" => [
        "signature" => $signature
    ]
];

$url = "https://secure.paytmpayments.com/theia/api/v1/initiateTransaction?mid={$mid}&orderId={$orderId}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ]
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode([
        "status" => false,
        "error" => curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

echo $response;