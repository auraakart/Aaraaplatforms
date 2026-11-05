<?php
/**
 * Legacy CCAvenue response page.
 * Do not expose gateway payloads or claim an order is paid here: this standalone
 * handler does not independently validate and persist WooCommerce payment state.
 * Payment completion must use the installed WooCommerce gateway integration.
 */
require_once __DIR__ . '/Crypto.php';
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || ! CCA_WORKING_KEY ) {
    http_response_code( 400 );
    exit( 'Invalid payment response.' );
}
$encrypted = isset( $_POST['encResp'] ) && is_string( $_POST['encResp'] ) ? $_POST['encResp'] : '';
if ( ! $encrypted || ! ctype_xdigit( $encrypted ) ) {
    http_response_code( 400 );
    exit( 'Invalid payment response.' );
}
$decrypted = decrypt( $encrypted, CCA_WORKING_KEY );
if ( ! is_string( $decrypted ) || $decrypted === '' ) {
    http_response_code( 400 );
    exit( 'Unable to validate payment response.' );
}
// Response is intentionally not printed or treated as proof of payment.
// The dedicated WooCommerce gateway must verify the transaction and update
// the order before the application announces payment success.
http_response_code( 202 );
echo 'Payment response received. Confirm payment status in your account.';
