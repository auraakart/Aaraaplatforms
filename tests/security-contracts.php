<?php
/**
 * Lightweight, dependency-free regression checks for security-sensitive code.
 * These static contracts do not replace integration tests with WordPress,
 * WooCommerce and payment provider sandbox credentials.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $value = file_get_contents($root . '/' . $relative);
    if ($value === false) {
        throw new RuntimeException('Unable to read: ' . $relative);
    }
    return $value;
};
$assert = static function (bool $condition, string $reason): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$reason}\n");
        exit(1);
    }
};
foreach (['wp-config.php', 'wp-config-aaraa.php'] as $name) {
    $config = $read($name);
    foreach (['MAIN_DB_PASSWORD', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
        'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $secret) {
        $envName = $secret === 'MAIN_DB_PASSWORD' ? $secret : 'WP_' . $secret;
        $assert(str_contains($config, "getenv( '{$envName}' )"), "{$name}: missing environment lookup for {$envName}");
    }
    $assert(str_contains($config, "define( 'WP_DEBUG', false )"), "{$name}: debugging must default off");
}
$calendar = $read('wp-content/plugins/aaraa-white-label-admin/includes/class-delivery-calendar-api.php');
$assert(str_contains($calendar, "'permission_callback' => array( \$this, 'can_read_calendar' )"), 'calendar permission callback missing');
$assert(str_contains($calendar, "get_current_user_id() === \$customer_id"), 'calendar ownership check missing');
$assert(!str_contains($calendar, "'permission_callback' => '__return_true'"), 'calendar must not be public');
$init = $read('paytm/initiateTransaction.php');
$assert(str_contains($init, "\$order->get_total()"), 'payment amount must come from stored order');
$assert(!str_contains($init, "\$input['amount']"), 'payment must not trust submitted amounts');
$assert(str_contains($init, "wp_verify_nonce"), 'initiator must verify nonce');
$callback = $read('paytm/response.php');
$assert(str_contains($callback, '/v3/order/status'), 'gateway status verification missing');
$assert(str_contains($callback, 'payment_complete('), 'verified callback must mark paid');
$assert(!str_contains($callback, 'print_r($_POST)'), 'callback must not disclose POST details');
$crypto = $read('ccavenue/Crypto.php');
$assert(str_contains($crypto, 'getenv("CCA_WORKING_KEY")'), 'CCAvenue secrets must be environmental');
foreach (['wp-config-old-1.php', 'wp-config-org.php', 'phpinfo.php', 'wp-content/debug.log'] as $removed) {
    $assert(!file_exists($root . '/' . $removed), "{$removed} must not be tracked in the branch");
}
$cc_gateway = $read('wp-content/plugins/ccavanue-woocommerce-payment-getway/index.php');
$assert(!str_contains($cc_gateway, "update_post_meta(\$order_id,'_post_data',\$_POST)"), 'CCAvenue must not persist raw checkout requests');
$assert(str_contains($cc_gateway, "'card_number'"), 'CCAvenue must explicitly screen card entry');
$assert(str_contains($cc_gateway, "'cvv_number'"), 'CCAvenue must explicitly screen CVV entry');
echo "Security source contracts passed.\n";
