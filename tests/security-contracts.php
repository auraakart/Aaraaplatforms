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
$assert(!str_contains($cc_gateway, 'public $card_number;'), 'CCAvenue must not collect card numbers');
$assert(!str_contains($cc_gateway, 'public $cvv_number;'), 'CCAvenue must not collect verification values');
$assert(str_contains($cc_gateway, "CCAvenue is temporarily unavailable."), 'CCAvenue checkout must be disabled until verified');
$paytm_plugin = $read('wp-content/plugins/paytm-payments/class.paytm.php');
$assert(!str_contains($paytm_plugin, '$resParams = $_POST;'), 'Paytm plugin must never trust a callback after provider verification fails');
$assert(str_contains($paytm_plugin, 'hash_equals($expected_mid, $provider_mid)'), 'Paytm plugin must validate merchant');
$assert(str_contains($paytm_plugin, '$same_amount'), 'Paytm plugin must match order amount');
$paytm_store = $read('wp-content/plugins/paytm-payments/woo-paytm.php');
$assert(str_contains($paytm_store, '$wpdb->insert('), 'Paytm transaction storage must use wpdb safe insert');
$assert(str_contains($paytm_store, '$wpdb->update('), 'Paytm transaction storage must use wpdb safe update');
$paytm_save_start = strpos($paytm_store, 'function saveTxnResponse(');
$assert($paytm_save_start !== false, 'Paytm save function missing');
$paytm_save = substr($paytm_store, $paytm_save_start, strpos($paytm_store, "add_action('plugins_loaded'", $paytm_save_start) - $paytm_save_start);
$assert(!str_contains($paytm_save, '$wpdb->query($sql)'), 'Paytm transaction persistence must not concatenate SQL');
$assert(str_contains($cc_gateway, 'hash_equals((string) $this->merchant_id, $merchant)'), 'CCAvenue must check merchant');
$assert(str_contains($cc_gateway, '$order->get_total()'), 'CCAvenue callback must match order total');
$assert(str_contains($cc_gateway, '$order->get_payment_method()'), 'CCAvenue callback must check order gateway');
// The legacy CCAvenue endpoint must never finalize a payment from a decrypted callback.
$cc_callback_start = strpos($cc_gateway, 'function check_ccavenue_response()');
$cc_callback_end = strpos($cc_gateway, 'function currency_convert(', $cc_callback_start);
$assert($cc_callback_start !== false && $cc_callback_end !== false, 'CCAvenue callback boundaries missing');
$cc_callback = substr($cc_gateway, $cc_callback_start, $cc_callback_end - $cc_callback_start);
$assert(!str_contains($cc_callback, 'payment_complete('), 'Legacy CCAvenue callback must never settle payments');
$assert(!str_contains($cc_callback, "update_status('failed'"), 'Legacy CCAvenue callback must not mark orders failed without provider verification');
$assert(str_contains($cc_callback, 'status_header(202)'), 'CCAvenue callback must await independent settlement verification');
$assert(str_contains($paytm_plugin, 'if (!$order->is_paid())'), 'Paytm callback must guard previously paid orders');
$assert(str_contains($paytm_plugin, "Transaction ID missing from provider response."), 'Paytm success must require transaction reference');
$assert(str_contains($paytm_plugin, 'is_array($resParams)'), 'Paytm status handling must account for API failures');
$paytm_ajax_start = strpos($paytm_store, 'function savetxnstatus()');
$paytm_ajax_end = strpos($paytm_store, 'function saveTxnResponse(', $paytm_ajax_start);
$assert($paytm_ajax_start !== false && $paytm_ajax_end !== false, 'Paytm AJAX handler missing');
$paytm_ajax = substr($paytm_store, $paytm_ajax_start, $paytm_ajax_end - $paytm_ajax_start);
$assert(str_contains($paytm_ajax, "current_user_can('manage_woocommerce')"), 'Paytm AJAX must verify manager privilege');
$assert(str_contains($paytm_ajax, "get_payment_method()"), 'Paytm AJAX must verify gateway ownership');
$assert(str_contains($paytm_ajax, "check_ajax_referer"), 'Paytm AJAX must verify nonce');
$assert(!str_contains($paytm_ajax, "response' => " . '$resParams'), 'Paytm AJAX must not return raw gateway data');
foreach (['wp-config.php', 'wp-config-aaraa.php'] as $name) {
    $config = $read($name);
    $assert(str_contains($config, "aaraa_tenant_from_host("), "{$name}: strict tenant routing required");
    $assert(!str_contains($config, "sys_get_temp_dir()"), "{$name}: must not cache DB secrets in temp PHP");
}
$assert(str_contains($cc_gateway, 'public function is_available()'), 'CCAvenue must hide unverified gateway');
$assert(str_contains($cc_gateway, 'return false;'), 'CCAvenue must reject checkout availability');
$assert(str_contains($paytm_plugin, 'A provider outage is not proof that the payment failed.'), 'Paytm provider outage must not mark an order failed');
echo "Security source contracts passed.\n";
