<?php

require_once "PaytmChecksum.php";

$merchantKey = "XDxYCucTL6MJI0nE";

// Paytm sends POST data
$paytmParams = $_POST;

// Get checksum
$paytmChecksum = $_POST["CHECKSUMHASH"] ?? "";

$params = $_POST;
unset($params["CHECKSUMHASH"]);

$isValid = PaytmChecksum::verifySignature($params, $merchantKey, $paytmChecksum);

if (!$isValid) {
    die("Checksum Mismatch");
}
// Transaction details
$orderId    = $_POST['ORDERID'];
$txnId      = $_POST['TXNID'];
$status     = $_POST['STATUS'];
$response   = $_POST['RESPCODE'];
$message    = $_POST['RESPMSG'];
$amount     = $_POST['TXNAMOUNT'];
$paymentMode = $_POST['PAYMENTMODE'];

if ($status == "TXN_SUCCESS") {

    // TODO:
    // 1. Update order in database
    // 2. Save TXNID
    // 3. Mark order as Paid

    echo "Payment Successful";
} else {

    // TODO:
    // Mark order as Failed

    echo "Payment Failed";
}

echo "<pre>";
print_r($_POST);
echo "</pre>";