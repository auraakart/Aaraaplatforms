<?php
/**
 * Start a Paytm transaction against a server-owned WooCommerce order.
 * Required environment: PAYTM_MID, PAYTM_MERCHANT_KEY, PAYTM_WEBSITE_NAME.
 */
require_once dirname( __DIR__ ) . '/wp-load.php';
require_once __DIR__ . '/PaytmChecksum.php';

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

function aaraa_paytm_init_error( $status, $message ) {
    http_response_code( $status );
    echo wp_json_encode( array( 'status' => false, 'message' => $message ) );
    exit;
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    header( 'Allow: POST' );
    aaraa_paytm_init_error( 405, 'POST required.' );
}

if ( ! is_user_logged_in() ) {
    aaraa_paytm_init_error( 401, 'Authentication required.' );
}

$nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? $_POST['_wpnonce'] ?? '';
if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'wp_rest' ) ) {
    aaraa_paytm_init_error( 403, 'Invalid security token.' );
}

$input = json_decode( file_get_contents( 'php://input' ), true );
$input = is_array( $input ) ? $input : $_POST;
$order_id = absint( $input['order_id'] ?? 0 );
$order = $order_id ? wc_get_order( $order_id ) : false;
if ( ! $order || ( (int) $order->get_customer_id() !== get_current_user_id() && ! current_user_can( 'manage_woocommerce' ) ) ) {
    aaraa_paytm_init_error( 403, 'Order unavailable.' );
}
if ( $order->get_payment_method() !== 'paytm' || $order->is_paid() || ! $order->needs_payment() || $order->get_currency() !== 'INR' ) {
    aaraa_paytm_init_error( 409, 'Order cannot be paid using this method.' );
}
$mid = getenv( 'PAYTM_MID' );
$key = getenv( 'PAYTM_MERCHANT_KEY' );
$website = getenv( 'PAYTM_WEBSITE_NAME' );
if ( ! $mid || ! $key || ! $website ) {
    aaraa_paytm_init_error( 503, 'Payment service not configured.' );
}
$amount = wc_format_decimal( $order->get_total(), 2 );
if ( (float) $amount <= 0 ) {
    aaraa_paytm_init_error( 400, 'Invalid order total.' );
}
$body = array(
    'requestType' => 'Payment',
    'mid' => $mid,
    'websiteName' => $website,
    'orderId' => (string) $order_id,
    'callbackUrl' => home_url( '/paytm/response.php' ),
    'txnAmount' => array( 'value' => $amount, 'currency' => 'INR' ),
    'userInfo' => array( 'custId' => (string) $order->get_customer_id() ),
);
$request = array( 'body' => $body, 'head' => array(
    'signature' => PaytmChecksum::generateSignature( wp_json_encode( $body, JSON_UNESCAPED_SLASHES ), $key ),
) );
$url = 'https://secure.paytmpayments.com/theia/api/v1/initiateTransaction?mid='
    . rawurlencode( $mid ) . '&orderId=' . rawurlencode( (string) $order_id );
$response = wp_remote_post( $url, array(
    'timeout' => 20,
    'headers' => array( 'Content-Type' => 'application/json' ),
    'body' => wp_json_encode( $request, JSON_UNESCAPED_SLASHES ),
) );
if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
    aaraa_paytm_init_error( 502, 'Payment provider unavailable.' );
}
$payload = json_decode( wp_remote_retrieve_body( $response ), true );
if ( ! is_array( $payload ) || empty( $payload['body']['txnToken'] ) ) {
    aaraa_paytm_init_error( 502, 'Payment could not be initialized.' );
}
echo wp_json_encode( array(
    'status' => true,
    'order_id' => $order_id,
    'txnToken' => $payload['body']['txnToken'],
    'mid' => $mid,
) );
