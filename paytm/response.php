<?php
/**
 * Paytm callback: confirm status with Paytm before changing a WooCommerce order.
 */
require_once dirname( __DIR__ ) . '/wp-load.php';
require_once __DIR__ . '/PaytmChecksum.php';
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );

function aaraa_paytm_callback_error( $status, $message ) {
    http_response_code( $status );
    echo $message;
    exit;
}
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    header( 'Allow: POST' );
    aaraa_paytm_callback_error( 405, 'POST required.' );
}
$mid = getenv( 'PAYTM_MID' );
$key = getenv( 'PAYTM_MERCHANT_KEY' );
if ( ! $mid || ! $key ) {
    aaraa_paytm_callback_error( 503, 'Payment service unavailable.' );
}
$params = wp_unslash( $_POST );
if ( ! is_array( $params ) || empty( $params['CHECKSUMHASH'] ) || empty( $params['ORDERID'] ) ) {
    aaraa_paytm_callback_error( 400, 'Invalid callback.' );
}
$checksum = $params['CHECKSUMHASH'];
unset( $params['CHECKSUMHASH'] );
if ( ! PaytmChecksum::verifySignature( $params, $key, $checksum ) ) {
    aaraa_paytm_callback_error( 403, 'Invalid callback signature.' );
}
$order_id = absint( $params['ORDERID'] );
if ( (string) $order_id !== (string) $params['ORDERID'] ) {
    aaraa_paytm_callback_error( 400, 'Invalid order reference.' );
}
$order = wc_get_order( $order_id );
if ( ! $order || $order->get_payment_method() !== 'paytm' || $order->get_currency() !== 'INR' ) {
    aaraa_paytm_callback_error( 404, 'Order unavailable.' );
}

// Do not trust the browser/callback status. Ask the gateway for its final state.
$body = array( 'mid' => $mid, 'orderId' => (string) $order_id );
$request = array( 'body' => $body, 'head' => array(
    'signature' => PaytmChecksum::generateSignature( wp_json_encode( $body, JSON_UNESCAPED_SLASHES ), $key ),
) );
$response = wp_remote_post( 'https://secure.paytmpayments.com/v3/order/status', array(
    'timeout' => 20,
    'headers' => array( 'Content-Type' => 'application/json' ),
    'body' => wp_json_encode( $request, JSON_UNESCAPED_SLASHES ),
) );
if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
    aaraa_paytm_callback_error( 502, 'Could not verify transaction.' );
}
$status_response = json_decode( wp_remote_retrieve_body( $response ), true );
$result = is_array( $status_response ) ? ( $status_response['body'] ?? array() ) : array();
$result_signature = $status_response['head']['signature'] ?? '';
if ( ! is_array( $result ) || ! $result_signature ||
    ! PaytmChecksum::verifySignature( wp_json_encode( $result, JSON_UNESCAPED_SLASHES ), $key, $result_signature ) ) {
    aaraa_paytm_callback_error( 502, 'Could not authenticate provider response.' );
}
if ( (string) ( $result['mid'] ?? '' ) !== $mid ||
    (string) ( $result['orderId'] ?? '' ) !== (string) $order_id ||
    ! isset( $result['txnAmount'] ) ||
    abs( (float) $result['txnAmount'] - (float) $order->get_total() ) > 0.001 ) {
    aaraa_paytm_callback_error( 409, 'Transaction does not match order.' );
}
$status = $result['resultInfo']['resultStatus'] ?? '';
if ( $status === 'TXN_SUCCESS' && ! empty( $result['txnId'] ) ) {
    if ( ! $order->is_paid() ) {
        $order->payment_complete( sanitize_text_field( $result['txnId'] ) );
        $order->add_order_note( 'Paytm payment verified using Transaction Status API.' );
    }
    echo 'Payment successful.';
} elseif ( $status === 'TXN_FAILURE' ) {
    if ( ! $order->is_paid() ) {
        $order->update_status( 'failed', 'Paytm confirmed transaction failure.' );
    }
    echo 'Payment failed.';
} else {
    http_response_code( 202 );
    echo 'Payment pending verification.';
}
