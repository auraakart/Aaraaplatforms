<?php
define('econis_version','1.0'); 
define('url_demo','https://wpbingosite.com/wordpress/econis');

require_once( get_template_directory().'/custom-functions.php' );


if (!isset($content_width)) { $content_width = 1200; }
require_once( get_template_directory().'/inc/class-tgm-plugin-activation.php' );
require_once( get_template_directory().'/inc/plugin-requirement.php' );
require_once( get_template_directory().'/inc/megamenu/megamenu.php' );
include_once( get_template_directory().'/inc/megamenu/mega_menu_custom_walker.php' );
require_once( get_template_directory().'/inc/function.php' );
require_once( get_template_directory().'/inc/loader.php' );
include_once( get_template_directory().'/inc/template-tags.php' );
require_once( get_template_directory().'/inc/woocommerce.php' );
require_once( get_template_directory().'/inc/admin/functions.php' );
add_action( 'after_setup_theme', function() {
    include_once( get_template_directory().'/inc/menus.php' );
    require_once( get_template_directory().'/inc/admin/theme-options.php' );
} );
function econis_custom_css() {
	global $econis_page_id;
	$econis_settings = econis_global_settings();
	if (!is_admin()) {
		wp_enqueue_style( 'econis-style-template', get_template_directory_uri().'/css/template.css');
		ob_start(); 
		include( get_template_directory().'/inc/custom-css.php' );
		$content = ob_get_clean();
		$content = str_replace(array("\r\n", "\r"), "\n", $content);
		$csss = explode("\n", $content);
		$custom_css = array();
		foreach ($csss as $i => $css) { if(!empty($css)) $custom_css[] = trim($css); }
		wp_add_inline_style( 'econis-style-template', implode($custom_css) );
	}
}
add_action('wp_enqueue_scripts', 'econis_custom_css' );
function econis_custom_js() {
	if (!is_admin()) {
		wp_enqueue_script( 'econis-script', get_template_directory_uri() . '/js/functions.js', array( 'jquery'), null, true );
		wp_localize_script( 'econis-script', 'econis_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		$custom_js = 'jQuery(function($){ "use strict"; $(document).on("click",".plus, .minus",function(){var t=$(this).closest(".quantity").find(".qty"),a=parseFloat(t.val()),n=parseFloat(t.attr("max")),s=parseFloat(t.attr("min")),e=t.attr("step");a&&""!==a&&"NaN"!==a||(a=0),(""===n||"NaN"===n)&&(n=""),(""===s||"NaN"===s)&&(s=0),("any"===e||""===e||void 0===e||"NaN"===parseFloat(e))&&(e=1),$(this).is(".plus")?t.val(n&&(n==a||a>n)?n:a+parseFloat(e)):s&&(s==a||s>a)?t.val(s):a>0&&t.val(a-parseFloat(e)),t.trigger("change")})});';
		wp_add_inline_script( 'econis-script', $custom_js);
	}
}
add_action('wp_enqueue_scripts', 'econis_custom_js' );




add_action('wp_head', 'myplugin_ajaxurl_head');
add_action('wp_footer', 'myplugin_ajaxurl_footer');
function myplugin_ajaxurl_head() {

   echo '<style>
	form.cart .quantity:before {
		content: "" !important;
		margin-right: 0 !important;
	}
	form.cart .quantity{display: flex;align-items: center;justify-content: center;}
	.qhide{display: none !important;}
	
	</style>';
}
function myplugin_ajaxurl_footer() {

   echo '<script type="text/javascript">
let ajaxurl = "' . admin_url('admin-ajax.php') . '";
let timeout;
jQuery(document).on("change", "div.quantity input.qty", function() {

    if (timeout != undefined) clearTimeout(timeout); 
    if (jQuery(this).val() == "") return; 

    jQuery(this).parent().next().attr("data-quantity", jQuery(this).val())
    let pr = jQuery(this).parent().next();
    let qty = jQuery(this).val();
    let cart_id_key = jQuery(this).parent().next().attr("data-product_id");
    timeout = setTimeout(function() {
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: ajaxurl,
            data: {
                action: "update_item_from_cart",
                "cart_id_key": cart_id_key,
                "qty": qty,
            },
            success: function(data) {

                if (data) {
                    console.log("Updated successfully.");
                } else {
                    console.log("Updated Successfully");
                }
            }

        });




    }, 1000); // schedule update cart event with 1000 miliseconds delay
});		   
         </script>';
}


function update_item_from_cart() {
  #global $woocommerce;  
	$cart_id_key = $_POST['cart_id_key'];   
     $quantity = $_POST['qty'];     

    // Get mini cart
    ob_start();

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item)
    {

        if( (int) $cart_item['product_id'] === (int) $cart_id_key && $cart_item_key === $cart_item['key'])
        {
			
	
            WC()->cart->set_quantity( $cart_item_key, $quantity, $refresh_totals = true );
			$product = $cart_item['data'];
			// Get stock quantity
			//$stock_qty = $product->get_stock_quantity();
			$stock_qty = $product->get_stock_quantity();
			// get cart item quantity
			$item_qty  = $cart_item['quantity'];
			
			echo 'ok';
			
        }
    }
    WC()->cart->calculate_totals();
    WC()->cart->maybe_set_cart_cookies();

	wp_die();
}
add_action('wp_ajax_update_item_from_cart', 'update_item_from_cart');
add_action('wp_ajax_nopriv_update_item_from_cart', 'update_item_from_cart');



function woo_rename_tax_inc_cart( $value ) {
    $value = str_ireplace( 'VAT', 'GST', $value );
    return $value;
}
// default checkout country
add_filter( 'default_checkout_billing_country', 'change_default_checkout_country' );
add_filter( 'default_checkout_shipping_country', 'change_default_checkout_country' );
function change_default_checkout_country() {
    return 'IN'; // country code
}
// default checkout state
add_filter( 'default_checkout_billing_state', 'change_default_checkout_state' );
add_filter( 'default_checkout_shipping_state', 'change_default_checkout_state' );
function change_default_checkout_state() {
    return 'TN'; // state code
}

// Setting one state only
add_filter( 'woocommerce_states', 'custom_woocommerce_state', 10, 1 );
function custom_woocommerce_state( $states ) {
    // Returning a unique state
    return array('IN' => array('TN' => 'Tamil Nadu'));
}
add_filter( 'woocommerce_checkout_fields' , 'default_values_checkout_fields' );
function default_values_checkout_fields( $fields ) {
// You can use this for postcode, address, company, first name, last name and such. 
$fields['billing']['billing_city']['default'] = 'Chennai';
$fields['shipping']['shipping_city']['default'] = 'Chennai';
     return $fields;
}




/**
 * Change Add to Cart button text for subscription products
 */
add_filter( 'woocommerce_product_add_to_cart_text', 'woo_change_add_to_cart_text', 999, 2 );
add_filter( 'woocommerce_product_single_add_to_cart_text', 'woo_change_add_to_cart_text', 999, 2 );

function woo_change_add_to_cart_text( $text, $product ) {

    if ( ! is_object( $product ) ) {
        return $text;
    }

    if ( $product->is_type( 'subscription' ) || $product->is_type( 'variable-subscription' ) ) {
        return 'Subscribe';
    }

    if ( $product->is_type( 'simple' ) ) {
        return 'Buy Once';
    }

    return $text;
}



/**
 * Delivery Schedule for Subscription Products
 */
//add_action( 'woocommerce_before_add_to_cart_button', 'wcfm_delivery_schedule_fields', 99, 9 );

function wcfm_delivery_schedule_fields() {

    global $product;

   /* if ( ! $product || ! $product->is_type( array( 'subscription', 'variable-subscription' ) ) ) {
        return;
    }
    */

    $selected_schedule = isset( $_POST['delivery_schedule'] ) ? sanitize_text_field( $_POST['delivery_schedule'] ) : 'daily';
    $selected_days     = isset( $_POST['delivery_days'] ) ? array_map( 'intval', (array) $_POST['delivery_days'] ) : array();

    $days = array(
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        0 => 'Sun',
    );
    ?>

    <div class="wcfm-delivery-schedule">

        <h4>Delivery Schedule</h4>

        <label class="schedule-option">
            <input type="radio" name="delivery_schedule" value="daily" <?php checked( $selected_schedule, 'daily' ); ?>>
            Every Day
        </label>

        <label class="schedule-option">
            <input type="radio" name="delivery_schedule" value="alternate" <?php checked( $selected_schedule, 'alternate' ); ?>>
            Alternative Days
        </label>

        <label class="schedule-option">
            <input type="radio" name="delivery_schedule" value="weekend" <?php checked( $selected_schedule, 'weekend' ); ?>>
            Every Weekend
        </label>

        <label class="schedule-option">
            <input type="radio" name="delivery_schedule" value="custom" <?php checked( $selected_schedule, 'custom' ); ?>>
            Custom Days
        </label>

        <div id="delivery-custom-days" style="<?php echo ( $selected_schedule === 'custom' ) ? '' : 'display:none;'; ?>margin-top:15px;margin-bottom: 10px;">

            <?php foreach ( $days as $key => $day ) : ?>

                <label class="day-option">
                    <input
                        type="checkbox"
                        name="delivery_days[]"
                        value="<?php echo esc_attr( $key ); ?>"
                        <?php checked( in_array( $key, $selected_days ) ); ?>
                    ><?php echo esc_html( $day ); ?>
                </label>

            <?php endforeach; ?>

        </div>

    </div>

    <style>
    .schedule-option,
    .day-option{
        display:inline-block;
        margin-right:15px;
        margin-bottom:10px;
        cursor:pointer;
    }

    .schedule-option input,
    .day-option input{
        margin-right:5px;
    }

    #delivery-custom-days{
        margin-top:10px;
    }
    </style>

    <script>
    jQuery(function($){

        function toggleCustomDays(){

            if($('input[name="delivery_schedule"]:checked').val()=='custom'){
                $('#delivery-custom-days').slideDown();
            }else{
                $('#delivery-custom-days').slideUp();
            }

        }

        toggleCustomDays();

        $('body').on('change','input[name="delivery_schedule"]',function(){
            toggleCustomDays();
        });

    });
    </script>

    <?php
}

/**
 * Validation
 */
add_filter( 'woocommerce_add_to_cart_validation', 'wcfm_delivery_schedule_validation', 10, 3 );

function wcfm_delivery_schedule_validation( $passed, $product_id, $qty ) {

    if ( isset( $_POST['delivery_schedule'] ) && $_POST['delivery_schedule'] === 'custom' ) {

        if ( empty( $_POST['delivery_days'] ) ) {

            wc_add_notice( 'Please select at least one custom delivery day.', 'error' );

            return false;
        }
    }

    return $passed;
}

/**
 * Save in Cart
 */
add_filter( 'woocommerce_add_cart_item_data', 'wcfm_save_delivery_schedule', 10, 2 );

function wcfm_save_delivery_schedule( $cart_item_data, $product_id ) {

    if ( isset( $_POST['delivery_schedule'] ) ) {

        $cart_item_data['delivery_schedule'] = sanitize_text_field( $_POST['delivery_schedule'] );

        if ( isset( $_POST['delivery_days'] ) ) {
            $cart_item_data['delivery_days'] = array_map( 'intval', $_POST['delivery_days'] );
        }

        $cart_item_data['unique_key'] = md5( microtime() . rand() );
    }

    return $cart_item_data;
}


/**
 * Redirect Administrator to Store Manager Dashboard.
 */
//add_action( 'template_redirect', 'custom_redirect_admin_to_store_manager' );

function custom_redirect_admin_to_store_manager() {

    if ( ! is_user_logged_in() || is_admin() || wp_doing_ajax() ) {
        return;
    }

    // Prevent redirect loop
    if ( strpos( $_SERVER['REQUEST_URI'], '/store-manager/' ) !== false ) {
        return;
    }

    $user = wp_get_current_user();

    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        wp_safe_redirect( home_url( '/store-manager/' ) );
        exit;
    }
}


# ###############################




add_filter('rest_post_dispatch', 'add_pagination_data_to_wc_api_response', 10, 3);
function add_pagination_data_to_wc_api_response($response, $server, $request) {
    $route = $request->get_route();

    // Only modify WooCommerce products endpoint
    if (strpos($route, '/wc/v3/products') !== false) {
        $headers = $response->get_headers();

        $total     = isset($headers['X-WP-Total']) ? (int) $headers['X-WP-Total'] : 0;
        $totalPages = isset($headers['X-WP-TotalPages']) ? (int) $headers['X-WP-TotalPages'] : 0;

        $data = $response->get_data();

        $response->set_data([
            'products'          => $data,
            'total'             => $total,
            'total_pages'       => $totalPages,
            'per_page'          => (int) $request->get_param('per_page'),
            'current_page'      => (int) $request->get_param('page'),
            // Store-wide delivery options, returned once alongside the listing.
            'delivery_slots'    => class_exists('\\Aaraa\\Admin\\Delivery_Slots_API') ? \Aaraa\Admin\Delivery_Slots_API::slots_payload('active') : [],
            'delivery_schedule' => class_exists('\\Aaraa\\Admin\\Delivery_Schedule_API') ? \Aaraa\Admin\Delivery_Schedule_API::schedule_payload() : [],
        ]);
    }

    return $response;
}

add_filter('rest_post_dispatch', 'add_pagination_data_to_wc_api_response2', 10, 3);
function add_pagination_data_to_wc_api_response2($response, $server, $request) {
    $route = $request->get_route();

    // Only modify WooCommerce products endpoint
    if (strpos($route, '/wc/v3/orders') !== false) {
        $headers = $response->get_headers();

        $total     = isset($headers['X-WP-Total']) ? (int) $headers['X-WP-Total'] : 0;
        $totalPages = isset($headers['X-WP-TotalPages']) ? (int) $headers['X-WP-TotalPages'] : 0;

        $data = $response->get_data();

        $response->set_data([
            'products'     => $data,
            'total'        => $total,
            'total_pages'  => $totalPages,
            'per_page'     => (int) $request->get_param('per_page'),
            'current_page' => (int) $request->get_param('page'),
        ]);
    }

    return $response;
}
 

//
add_action('rest_api_init', function () {
    register_rest_route('wc/v3', '/customer-by-phone', [
        'methods'  => 'GET',
        'callback' => 'get_customer_by_phone',
        'args'     => [
            'phone' => [
                'required' => true,
                'validate_callback' => function ($param) {
                    return preg_match('/^\d{10}$/', $param); // 10-digit only
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
        'permission_callback' => function () {
            return current_user_can('manage_woocommerce'); // Secure: only admins/shop managers
        },
    ]);
});

function get_customer_by_phone_old($request) {
    $phone = $request->get_param('phone');

    $users = get_users([
        'role' => 'customer',
        'meta_query' => [[
            'key'     => 'billing_phone',
            'value'   => $phone,
            'compare' => '='
        ]]
    ]);

    if (empty($users)) {
        return new WP_REST_Response(['message' => 'Customer not found'], 404);
    }

    $user = $users[0];
    return new WP_REST_Response([
        'id'       => $user->ID,
        'name'     => $user->display_name,
        'email'    => $user->user_email,
        'username' => $user->user_login,
        'phone'    => get_user_meta($user->ID, 'billing_phone', true),
    ], 200);
}
function get_customer_by_phone($request) {
    $phone = $request->get_param('phone');

    if (empty($phone)) {
        return new WP_REST_Response(['message' => 'Phone is required'], 400);
    }

    // Normalize input phone
    $phone = preg_replace('/\D+/', '', $phone); // remove +, spaces, -
    
    // Possible formats
    $phone_variants = [
        $phone,
        '91' . $phone,
        '+91' . $phone
    ];

    $users = get_users([
        'role' => 'customer',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key'     => 'billing_phone',
                'value'   => $phone,
                'compare' => 'LIKE'
            ],
            [
                'key'     => 'billing_phone',
                'value'   => '91' . $phone,
                'compare' => 'LIKE'
            ],
            [
                'key'     => 'billing_phone',
                'value'   => '+91' . $phone,
                'compare' => 'LIKE'
            ]
        ],
        'number' => 1
    ]);

    if (empty($users)) {
        return new WP_REST_Response(['message' => 'Customer not found'], 404);
    }

    $user = $users[0];

    return new WP_REST_Response([
        'id'       => $user->ID,
        'name'     => $user->display_name,
        'email'    => $user->user_email,
        'username' => $user->user_login,
        'phone'    => get_user_meta($user->ID, 'billing_phone', true),
    ], 200);
}



add_action('rest_api_init', function () {
    register_rest_route('wc/v3', '/product-combo/(?P<slug>[a-zA-Z0-9-_]+)', array(
        'methods' => 'GET',
        'callback' => 'tfv_get_combined_product',
        'permission_callback' => '__return_true',
    ));
});

function tfv_get_combined_product($data) {
    $slug = sanitize_text_field($data['slug']);

    // Get both Buy Once and Subscription products by slug
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => array('buy-once', 'subscription'), // Use actual category slugs
                'operator' => 'IN'
            )
        ),
        'meta_query' => array(
            array(
                'key' => '_product_slug_group',
                'value' => $slug,
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    $results = [];

    foreach ($query->posts as $post) {
        $product = wc_get_product($post->ID);

        $results[] = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'price' => $product->get_price(),
            'sale_price' => $product->get_sale_price(),
            'regular_price' => $product->get_regular_price(),
            'permalink' => get_permalink($product->get_id()),
            'buy_type' => has_term('subscription', 'product_cat', $product->get_id()) ? 'subscription' : 'buy-once',
            'variations' => $product->is_type('variable') ? get_variation_data($product) : null
        );
    }

    return rest_ensure_response([
        'product_slug' => $slug,
        'items' => $results
    ]);
}

// Helper to fetch variation data
function get_variation_data($product) {
    $variations = [];
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        $variations[] = array(
            'id' => $variation_id,
            'price' => $variation->get_price(),
            'attributes' => $variation->get_attributes(),
        );
    }
    return $variations;
}





add_action('rest_api_init', function () {
    register_rest_route('wc/v3', '/create-order-subscription', [
        'methods'  => 'POST',
        'callback' => 'create_custom_subscription_with_multiple_products',
         
    ]);
});


function create_order_and_subscriptiond($request) {
    return 'd';
}
function create_order_and_subscription($request) {
    $params = $request->get_json_params();
 
    $customer_id = $params['customer_id'] ?? 0;
    $product_id = $params['product_id'] ?? 0;

    if (!$customer_id || !$product_id) {
        return new WP_Error('missing_params', 'Customer ID and Product ID required.', ['status' => 400]);
    }

    // Get product
    $product = wc_get_product($product_id);
   /* if (!$product || $product->get_type() !== 'subscription' || $product->get_type() !== 'variable-subscription') {
        return new WP_Error('invalid_product', 'Invalid or non-subscription product.', ['status' => 400]);
    }
*/
    // Create order
    $order = wc_create_order(['customer_id' => $customer_id]);

    $order->add_product($product, 1); // Quantity 1
    $order->calculate_totals();
    $order->set_payment_method('cod'); // or any valid gateway slug
    $order->save();

    // Optionally mark as paid to trigger subscription creation
    $order->payment_complete();

    // Get subscriptions created from this order
    $subscriptions = wcs_get_subscriptions_for_order($order, ['order_type' => 'any']);

    $subs_data = [];

    foreach ($subscriptions as $sub_id => $subscription) {
        $subs_data[] = [
            'subscription_id' => $sub_id,
            'status' => $subscription->get_status(),
            'next_payment_date' => $subscription->get_date('next_payment'),
            'total' => $subscription->get_total(),
        ];
    }

    return [
        'order_id' => $order->get_id(),
        'order_total' => $order->get_total(),
        'subscriptions' => $subs_data,
    ];
}




function create_order_and_variable_subscription($request) {
    $params = $request->get_json_params();

    $customer_id = $params['customer_id'] ?? 0;
    $variation_id = $params['product_variant_id'] ?? 0;
    $billing_data = $params['billing'] ?? [];


    if (!$customer_id || !$variation_id) {
        return new WP_Error('missing_params', 'Customer ID and Variation ID required.', ['status' => 400]);
    }

    // Load variation product
    $variation = wc_get_product($variation_id);

    /*if (!$variation || $variation->get_type() !== 'subscription_variation') {
        return new WP_Error('invalid_product', 'Invalid or non-subscription variation product.', ['status' => 400]);
    }*/

    // Create order
    $order = wc_create_order(['customer_id' => $customer_id]);

    $order->add_product($variation, 1); // Add the selected subscription variation
    
    // Set billing address
    if (!empty($billing_data)) {
        $order->set_address($billing_data, 'billing');
    }
    
    $order->calculate_totals();
    $order->set_payment_method('cod'); // or use 'stripe', 'paypal', etc.
    $order->save();

    // Optionally mark as paid to trigger subscription creation
    $order->payment_complete();

    // Retrieve linked subscription(s)
    $subscriptions = wcs_get_subscriptions_for_order($order, ['order_type' => 'any']);

    $subs_data = [];

    foreach ($subscriptions as $sub_id => $subscription) {
        $subs_data[] = [
            'subscription_id' => $sub_id,
            'status' => $subscription->get_status(),
            'next_payment_date' => $subscription->get_date('next_payment'),
            'interval' => $subscription->get_billing_interval(),
            'period' => $subscription->get_billing_period(),
            'total' => $subscription->get_total(),
        ];
    }

    return [
        'order_id' => $order->get_id(),
        'order_total' => $order->get_total(),
        'subscriptions' => $subs_data,
    ];
}





function create_custom_subscription_with_multiple_products_old($request) {

    $params = $request->get_json_params();

    $user_id      = absint( $params['customer_id'] ?? 0 );
    $product_ids  = $params['product_ids'] ?? [];
    $billing_data = $params['billing'] ?? [];

    if ( ! $user_id ) {
        error_log( 'create-order-subscription: no customer_id in payload. Raw params: ' . print_r( $params, true ) );
        return new WP_Error( 'invalid_customer_id', 'customer_id missing or not numeric in request body.', array( 'status' => 400 ) );
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        error_log( 'create-order-subscription: customer_id ' . $user_id . ' is not a WP user.' );
        return new WP_Error( 'unknown_customer_id', 'No WordPress user exists with ID ' . $user_id . '.', array( 'status' => 400 ) );
    }

    if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
        return new WP_Error( 'invalid_products', 'product_ids must be a non-empty array of product IDs.', array( 'status' => 400 ) );
    }

    $start_date       = ! empty( $params['start_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $params['start_date'] ) ) : gmdate( 'Y-m-d H:i:s' );
    $billing_period   = $params['billing_period'] ?? 0;
    $billing_interval = $params['billing_interval'] ?? 0;

    /* ── Delivery slot + schedule ─────────────────────────────── */
    $delivery_slot     = absint( $params['delivery_slot'] ?? 0 );
    $delivery_schedule = sanitize_text_field( $params['delivery_schedule'] ?? '' );
    $allowed_schedules = array( 'daily', 'alternate', 'weekend', 'custom' );
    if ( ! in_array( $delivery_schedule, $allowed_schedules, true ) ) {
        $delivery_schedule = '';
    }

    // Normalise delivery days (PHP `w`, 0 = Sunday): weekend is Sat+Sun,
    // custom uses the supplied days, everything else has no fixed days.
    $delivery_days = array();
    if ( 'weekend' === $delivery_schedule ) {
        $delivery_days = array( 0, 6 );
    } elseif ( 'custom' === $delivery_schedule ) {
        foreach ( (array) ( $params['delivery_days'] ?? array() ) as $day ) {
            $day = absint( $day );
            if ( $day <= 6 ) {
                $delivery_days[] = $day;
            }
        }
        $delivery_days = array_values( array_unique( $delivery_days ) );
    }

    /* ── Order ────────────────────────────────────────────────── */
    $order = wc_create_order( array( 'customer_id' => $user_id ) );
    if ( is_wp_error( $order ) ) {
        return new WP_Error( 'order_failed', 'Order not created.', array( 'status' => 500 ) );
    }

    if ( ! empty( $billing_data ) ) {
        $order->set_address( $billing_data, 'billing' );
        $order->set_address( $billing_data, 'shipping' );
    }

    // Add every product to the first order.
    $products = array();
    foreach ( $product_ids as $product_id ) {
        $product = wc_get_product( absint( $product_id ) );
        if ( ! $product ) {
            continue;
        }
        $products[] = $product;
        $order->add_product( $product, 1 );

        // Charge the chosen plan's one-time advance on this first order only.
        if ( class_exists( '\\Aaraa\\Admin\\Subscription_Advance' ) ) {
            \Aaraa\Admin\Subscription_Advance::add_order_advance_fee( $order, $product, $billing_interval, $billing_period );
        }
    }

    if ( empty( $products ) ) {
        return new WP_Error( 'invalid_products', 'None of the supplied product IDs resolved to a product.', array( 'status' => 400 ) );
    }

    // Persist the chosen delivery slot on the order too (CRUD + postmeta so it
    // is present under both HPOS order storage and legacy postmeta readers).
    if ( $delivery_slot ) {
        $order->update_meta_data( '_aaraa_delivery_slot', $delivery_slot );
        update_post_meta( $order->get_id(), '_aaraa_delivery_slot', $delivery_slot );
    }

    $order->calculate_totals();
    $order->save();

    /* ── Subscription (one, holding all products) ─────────────── */
    $sub = wcs_create_subscription( array(
        'order_id'         => $order->get_id(),
        'customer_id'      => $user_id, // Pass explicitly; otherwise inferred from the order.
        'status'           => 'pending',
        'billing_period'   => $billing_period,
        'billing_interval' => $billing_interval,
        'start_date'       => $start_date,
    ) );

    if ( is_wp_error( $sub ) ) {
        error_log( 'Subscription error: ' . $sub->get_error_message() );
        return new WP_Error( 'subscription_failed', $sub->get_error_message(), array( 'status' => 500 ) );
    }

    foreach ( $products as $product ) {
        $sub->add_product( $product, 1 );
    }

    if ( ! empty( $billing_data ) ) {
        $sub->set_address( $billing_data, 'billing' );
        $sub->set_address( $billing_data, 'shipping' );
    }

    /* ── Save delivery slot + schedule on the subscription ────── */
    $sub_id = $sub->get_id();

    // Write both ways: through the CRUD object (canonical store — HPOS orders
    // meta table, and what the wc/v3 subscription response / admin read), and
    // via post_meta (the legacy path WCFMu's renewal calculator and the theme's
    // My Account display read). Under HPOS with sync off these are separate
    // stores, so writing only one leaves the other empty.
    if ( $delivery_slot ) {
        $sub->update_meta_data( '_aaraa_delivery_slot', $delivery_slot );
        update_post_meta( $sub_id, '_aaraa_delivery_slot', $delivery_slot );
    }
    if ( '' !== $delivery_schedule ) {
        $sub->update_meta_data( '_wcfm_delivery_schedule', $delivery_schedule );
        update_post_meta( $sub_id, '_wcfm_delivery_schedule', $delivery_schedule );
        if ( ! empty( $delivery_days ) ) {
            $sub->update_meta_data( '_wcfm_delivery_days', $delivery_days );
            update_post_meta( $sub_id, '_wcfm_delivery_days', $delivery_days );
        } else {
            $sub->delete_meta_data( '_wcfm_delivery_days' );
            delete_post_meta( $sub_id, '_wcfm_delivery_days' );
        }
    }

    $sub->calculate_totals();
    $sub->save();

    return array(
        'order_id'          => $order->get_id(),
        'subscription_id'   => $sub_id,
        'delivery_slot'     => $delivery_slot,
        'delivery_schedule' => $delivery_schedule,
        'delivery_days'     => $delivery_days,
    );
}


function create_custom_subscription_with_multiple_products($request) {

    $params = $request->get_json_params();

    $user_id      = absint( $params['customer_id'] ?? 0 );
    $product_ids  = $params['product_ids'] ?? [];
    $billing_data = $params['billing'] ?? [];

    if ( ! $user_id ) {
        error_log( 'create-order-subscription: no customer_id in payload. Raw params: ' . print_r( $params, true ) );
        return new WP_Error( 'invalid_customer_id', 'customer_id missing or not numeric in request body.', array( 'status' => 400 ) );
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        error_log( 'create-order-subscription: customer_id ' . $user_id . ' is not a WP user.' );
        return new WP_Error( 'unknown_customer_id', 'No WordPress user exists with ID ' . $user_id . '.', array( 'status' => 400 ) );
    }

    if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
        return new WP_Error( 'invalid_products', 'product_ids must be a non-empty array of product IDs.', array( 'status' => 400 ) );
    }

    $start_date       = ! empty( $params['start_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $params['start_date'] ) ) : gmdate( 'Y-m-d H:i:s' );
    $billing_period   = $params['billing_period'] ?? 0;
    $billing_interval = $params['billing_interval'] ?? 0;

    /* ── Delivery slot + schedule ─────────────────────────────── */
    $delivery_slot     = absint( $params['delivery_slot'] ?? 0 );
    $delivery_schedule = sanitize_text_field( $params['delivery_schedule'] ?? '' );
    $allowed_schedules = array( 'daily', 'alternate', 'weekend', 'custom' );
    if ( ! in_array( $delivery_schedule, $allowed_schedules, true ) ) {
        $delivery_schedule = '';
    }

    // Normalise delivery days (PHP `w`, 0 = Sunday): weekend is Sat+Sun,
    // custom uses the supplied days, everything else has no fixed days.
    $delivery_days = array();
    if ( 'weekend' === $delivery_schedule ) {
        $delivery_days = array( 0, 6 );
    } elseif ( 'custom' === $delivery_schedule ) {
        // Accept either PHP `w` indices (0=Sun..6=Sat) or day names ("Monday",
        // "mon", …). absint() on a name yields 0, which silently turned every
        // custom day into Sunday — so map names explicitly instead.
        $short = array( 'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6 );
        foreach ( (array) ( $params['delivery_days'] ?? array() ) as $day ) {
            if ( is_int( $day ) || ( is_string( $day ) && ctype_digit( $day ) ) ) {
                $d = (int) $day;
            } else {
                $key = substr( strtolower( trim( (string) $day ) ), 0, 3 ); // "monday"/"mon" → "mon".
                $d   = isset( $short[ $key ] ) ? $short[ $key ] : -1;
            }
            if ( $d >= 0 && $d <= 6 ) {
                $delivery_days[] = $d;
            }
        }
        $delivery_days = array_values( array_unique( $delivery_days ) );
    }

    /* ── Order ────────────────────────────────────────────────── */
    $order = wc_create_order( array( 'customer_id' => $user_id ) );
    if ( is_wp_error( $order ) ) {
        return new WP_Error( 'order_failed', 'Order not created.', array( 'status' => 500 ) );
    }

    if ( ! empty( $billing_data ) ) {
        $order->set_address( $billing_data, 'billing' );
        $order->set_address( $billing_data, 'shipping' );
    }

    // Add every product to the first order.
    $products = array();
    foreach ( $product_ids as $product_id ) {
        $product = wc_get_product( absint( $product_id ) );
        if ( ! $product ) {
            continue;
        }
        $products[] = $product;
        $order->add_product( $product, 1 );

        // Charge the chosen plan's one-time advance on this first order only.
        if ( class_exists( '\\Aaraa\\Admin\\Subscription_Advance' ) ) {
            \Aaraa\Admin\Subscription_Advance::add_order_advance_fee( $order, $product, $billing_interval, $billing_period );
        }
    }

    if ( empty( $products ) ) {
        return new WP_Error( 'invalid_products', 'None of the supplied product IDs resolved to a product.', array( 'status' => 400 ) );
    }

    // Persist the chosen delivery slot on the order too (CRUD + postmeta so it
    // is present under both HPOS order storage and legacy postmeta readers).
    if ( $delivery_slot ) {
        $order->update_meta_data( '_aaraa_delivery_slot', $delivery_slot );
        update_post_meta( $order->get_id(), '_aaraa_delivery_slot', $delivery_slot );
    }

    $order->calculate_totals();
    $order->save();

    /* ── Subscription (one, holding all products) ─────────────── */
    $sub = wcs_create_subscription( array(
        'order_id'         => $order->get_id(),
        'customer_id'      => $user_id, // Pass explicitly; otherwise inferred from the order.
        'status'           => 'pending',
        'billing_period'   => $billing_period,
        'billing_interval' => $billing_interval,
        'start_date'       => $start_date,
    ) );

    if ( is_wp_error( $sub ) ) {
        error_log( 'Subscription error: ' . $sub->get_error_message() );
        return new WP_Error( 'subscription_failed', $sub->get_error_message(), array( 'status' => 500 ) );
    }

    foreach ( $products as $product ) {
        $sub->add_product( $product, 1 );
    }

    if ( ! empty( $billing_data ) ) {
        $sub->set_address( $billing_data, 'billing' );
        $sub->set_address( $billing_data, 'shipping' );
    }

    /* ── Save delivery slot + schedule on the subscription ────── */
    $sub_id = $sub->get_id();

    // Write both ways: through the CRUD object (canonical store — HPOS orders
    // meta table, and what the wc/v3 subscription response / admin read), and
    // via post_meta (the legacy path WCFMu's renewal calculator and the theme's
    // My Account display read). Under HPOS with sync off these are separate
    // stores, so writing only one leaves the other empty.
    if ( $delivery_slot ) {
        $sub->update_meta_data( '_aaraa_delivery_slot', $delivery_slot );
        update_post_meta( $sub_id, '_aaraa_delivery_slot', $delivery_slot );
    }
    if ( '' !== $delivery_schedule ) {
        $sub->update_meta_data( '_wcfm_delivery_schedule', $delivery_schedule );
        update_post_meta( $sub_id, '_wcfm_delivery_schedule', $delivery_schedule );
        if ( ! empty( $delivery_days ) ) {
            $sub->update_meta_data( '_wcfm_delivery_days', $delivery_days );
            update_post_meta( $sub_id, '_wcfm_delivery_days', $delivery_days );
        } else {
            $sub->delete_meta_data( '_wcfm_delivery_days' );
            delete_post_meta( $sub_id, '_wcfm_delivery_days' );
        }
    }

    $sub->calculate_totals();
    $sub->save();

    return array(
        'order_id'          => $order->get_id(),
        'subscription_id'   => $sub_id,
        'delivery_slot'     => $delivery_slot,
        'delivery_schedule' => $delivery_schedule,
        'delivery_days'     => $delivery_days,
    );
}


add_action('rest_api_init', function () {
    register_rest_route('wc/v3', '/subscription-products', [
        'methods' => 'GET',
        'callback' => 'get_subscription_products',
        'permission_callback' => '__return_true',
    ]);
});

function get_subscription_products() {
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => ['subscription', 'variable-subscription'],
            ],
        ],
    ];

    $product_ids = get_posts($args);
    return ['subscription_product_ids' => $product_ids];
}





add_action('rest_api_init', function () {
    register_rest_route('wc/v3', '/get-variation-id', [
        'methods' => 'GET',
        'callback' => 'get_variation_id_by_selected_attributes'
    ]);
});

function get_variation_id_by_selected_attributes( $request) {
    
    $params = $request->get_json_params();

    $parent_id = $params['product_id'] ?? 0;
    $selected_attributes = $params['attributes'] ?? 0;
 
    
    
    $product = wc_get_product( $parent_id );
   /*
    if ( ! $product || ! $product->is_type('variable') ) {
        return false;
    }
   */
    $variations = $product->get_available_variations();
 
    foreach ( $variations as $variation ) {
        $matched = true;

        foreach ( $selected_attributes as $attr_name => $attr_value ) {
            $key = 'attribute_pa_' . sanitize_title($attr_name);

            if ( ! isset($variation['attributes'][$key]) || $variation['attributes'][$key] !== $attr_value ) {
                $matched = false;
                break;
            }
        }

        if ( $matched ) {
            return $variation['variation_id'];
        }
    }

    return '';
}



add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/products', [
        'methods'  => 'GET',
        'callback' => 'get_custom_products',
        'args'     => [
            'page' => [
                'default'            => 1,
                'sanitize_callback'  => 'absint',
            ],
            'per_page' => [
                'default'            => 10,
                'sanitize_callback'  => 'absint',
            ],
        ],
        'permission_callback' => 'my_get_resource_permissions_check',
    ]);
});

/**
 * ✅ Permission check using WooCommerce API keys
 */
function custom_products_permission_check(WP_REST_Request $request) {
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        return new WP_Error('rest_forbidden', 'Authorization header missing.', ['status' => 401]);
    }

    // Extract Basic Auth credentials
    if (preg_match('/Basic\s+(.*)$/i', $headers['Authorization'], $matches)) {
        $auth     = base64_decode($matches[1]);
        list($consumer_key, $consumer_secret) = explode(':', $auth, 2);

        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_api_keys';

        // Validate against WooCommerce stored API keys
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE consumer_key = %s", wc_api_hash($consumer_key))
        );

        if ($row && hash_equals($row->consumer_secret, $consumer_secret)) {
            return true; // ✅ Authentication success
        }
    }

    return new WP_Error('rest_forbidden', 'Invalid Consumer Key or Secret.', ['status' => 401]);
}
function my_get_resource_permissions_check( WP_REST_Request $request ) {
    // Check if the current user can 'manage_woocommerce'
    if ( current_user_can( 'manage_woocommerce' ) ) {
        return true;
    }
    return new WP_Error( 'rest_forbidden', __( 'You do not have permission to access this resource.', 'text-domain' ), array( 'status' => 403 ) );
}


function get_custom_products(WP_REST_Request $request) {
    $page     = $request->get_param('page') ?: 1;
    $per_page = $request->get_param('per_page') ?: 10;

    // Query WooCommerce products
    $args = [
        'status'   => 'publish',
        'limit'    => $per_page,
        'paginate' => true,
        'page'     => $page,
    ];

    $products = wc_get_products($args);
    $result = [];

    foreach ($products->products as $product) {
        // Fetch subscription schemes (meta field)
        $subscription_meta = get_post_meta($product->get_id(), '_wcsatt_schemes', true);

        $result[] = [
    "id"                => $product->get_id(),
    "name"              => $product->get_name(),
    "slug"              => $product->get_slug(),
    "permalink"         => $product->get_permalink(),
    "type"              => $product->get_type(),
    "status"            => $product->get_status(),
    "featured"          => $product->get_featured(),
    "description"       => $product->get_description(),
    "short_description" => $product->get_short_description(),
    "sku"               => $product->get_sku(),
    "regular_price"     => $product->get_regular_price(),
    "sale_price"        => $product->get_sale_price(),
    "manage_stock"      => $product->managing_stock(),
    "stock_quantity"    => $product->get_stock_quantity(),
    "weight"            => $product->get_weight(),
    "dimensions"        => [
        "length" => $product->get_length(),
        "width"  => $product->get_width(),
        "height" => $product->get_height(),
    ],
    "categories"        => array_map(function ($cat) {
        return [
            "id"   => $cat->term_id,
            "name" => $cat->name,
            "slug" => $cat->slug,
        ];
    }, wp_get_post_terms($product->get_id(), 'product_cat')),
    "brands"            => array_map(function ($brand) {
        return [
            "id"   => $brand->term_id,
            "name" => $brand->name,
            "slug" => $brand->slug,
        ];
    }, wp_get_post_terms($product->get_id(), 'product_brand')),
    "tags"              => array_map(function ($tag) {
        return [
            "id"   => $tag->term_id,
            "name" => $tag->name,
            "slug" => $tag->slug,
        ];
    }, wp_get_post_terms($product->get_id(), 'product_tag')),

    "images"            => array_map(function ($img_id) {
        return [
            "id"   => $img_id,
            "src"  => wp_get_attachment_url($img_id),
            "name" => get_the_title($img_id),
            "alt"  => get_post_meta($img_id, '_wp_attachment_image_alt', true),
        ];
    }, array_merge(
        $product->get_image_id() ? [$product->get_image_id()] : [],
        $product->get_gallery_image_ids()
    )),

    "attributes"        => $product->get_attributes(),
    "default_attributes"=> $product->get_default_attributes(),
    "variations"        => $product->is_type('variable') ? $product->get_children() : [],
    "subscription_schemes" => get_post_meta($product->get_id(), '_wcsatt_schemes', true) ?: [],
    "subscription_plans"   => class_exists('\\Aaraa\\Admin\\Subscription_Advance') ? \Aaraa\Admin\Subscription_Advance::plans_for_api($product) : [],
    "advance_amount"       => class_exists('\\Aaraa\\Admin\\Subscription_Advance') ? \Aaraa\Admin\Subscription_Advance::top_level_advance(\Aaraa\Admin\Subscription_Advance::plans_for_api($product)) : 0,
];
    }

    return [
        'products'     => $result,
        'total'        => $products->total,
        'total_pages'  => $products->max_num_pages,
        'per_page'     => $per_page,
        'current_page' => $page,
    ];
}



function send_whatsapp_otp($phone, $otp)
{
    $url = "https://crmapi.bchatnow.com/api/meta/v19.0/706051972595478/messages";

    $payload = [
        "to" => "91".$phone,
        "recipient_type" => "individual",
        "type" => "template",
        "template" => [
            "language" => [
                "policy" => "deterministic",
                "code" => "en"
            ],
            "name" => "tfv_web_cust_login_otp",
            "components" => [
                [
                    "type" => "body",
                    "parameters" => [
                        [
                            "type" => "text",
                            "text" => $otp
                        ]
                    ]
                ],
                [
                    "type" => "button",
                    "sub_type" => "url",
                    "index" => 0,
                    "parameters" => [
                        [
                            "type" => "text",
                            "text" => $otp
                        ]
                    ]
                ]
            ]
        ]
    ];

    $headers = [
        "Authorization: Bearer tzjLCOy47EKSOpURFPVU5ERVJTQ09SRQZeMMefDYREW3Vbf0FWilREFTSAM774ze18eCnWmZicoeimVU5ERVJTQ09SRQ2eKIdFqjnolauMVU5ERVJTQ09SRQVrjE16lyWRT1mXFe4H6a03y738zvp8vymkbBo4uMZC9N5RM",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("WhatsApp Error: " . curl_error($ch));
    }

    curl_close($ch);

    error_log("WhatsApp Response: " . $response);

    return $response;
}


/**
 * Send OTP via SMS
 */
function send_sms_otp($phone, $otp)
{
    // API Configuration
    $api_url   = "http://sms.mysmarthost.in/api/sendsms.php";
    $user      = "madrasmilk";
    $apikey    = "atWhzca1M62UI1Ze9om9";
    $senderid  = "MAMILK";

    // SMS Details
    $message = "Your OTP for logging in to the Madras Milk application is {$otp}.";
    $tid     = "1207160701072793529";
    $type    = "txt";

    $postData = [
        'user'     => $user,
        'apikey'   => $apikey,
        'mobile'   => $phone,
        'senderid' => $senderid,
        'message'  => $message,
        'tid'      => $tid,
        'type'     => $type
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $api_url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response  = curl_exec($ch);
    $curl_err  = curl_errno($ch) ? curl_error($ch) : '';
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($curl_err) {
        error_log("SMS Error: " . $curl_err);
    }

    curl_close($ch);

    error_log("SMS Response: " . $response);

    // --- Log to Aaraa Notifications → SMS ---
    if (class_exists('\\Aaraa\\Admin\\Notifications_Admin')) {
        $ok = ($curl_err === '') && ($http_code >= 200 && $http_code < 400);

        \Aaraa\Admin\Notifications_Admin::log(array(
            'type'      => 'otp',
            'ref'       => \Aaraa\Admin\Notifications_Admin::user_id_for_phone($phone),
            'mobile'    => $phone,
            'message'   => $message,
            'result'    => $ok ? 'sent' : 'failed',
            'http_code' => (string) $http_code,
            'response'  => $curl_err !== '' ? $curl_err : trim((string) $response),
        ));
    }

    return $response;
}




// ✅ Register REST routes
add_action('rest_api_init', function () {
    register_rest_route('otp-login/v1', '/send', array(
        'methods' => 'POST',
        'callback' => 'otp_login_send_otp',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('otp-login/v1', '/verify', array(
        'methods' => 'POST',
        'callback' => 'otp_login_verify_otp',
        'permission_callback' => '__return_true',
    ));
});

/**
 * ✅ Send OTP
 */
function otp_login_send_otp($request) {
    $phone = sanitize_text_field($request['phone']);

    if (empty($phone)) {
        return new WP_Error('missing_phone', 'Phone number is required', array('status' => 400));
    }

    // Find user by billing_phone
    $user_query = new WP_User_Query(array(
        'meta_key'   => 'billing_phone',
        'meta_value' => $phone,
        'number'     => 1,
        'count_total'=> false
    ));

    $users = $user_query->get_results();
    //$users = 0;
    if (empty($users)) {
        $is_already_registered = false;
        $otp = rand(100000, 999999);
        if($phone == '9876543210'){
            $otp = '654321';
        }
        set_transient("otp_for_{$phone}", array(
            'otp' => $otp,
            'expires' => time() + 300
        ), 300);
        //return new WP_Error('user_not_found', 'No user found with this phone number', array('status' => 404));
    }else{
        $is_already_registered = true;
        $user = $users[0];
    
        // Generate OTP
        $otp = rand(100000, 999999);
        if($phone == '9876543210'){
            $otp = '654321';
        }
        // Save OTP in user meta (valid for 5 minutes)
        update_user_meta($user->ID, '_login_otp', $otp);
        update_user_meta($user->ID, '_login_otp_expiry', time() + 300);
    }
    
    
    // Send OTP (example via email - replace with SMS API like Twilio/MSG91)
   // wp_mail($user->user_email, "Your Login OTP", "Your OTP is: $otp");

    if($phone != '9876543210'){
        // Send WhatsApp OTP
        //$wa_response = send_whatsapp_otp($phone, $otp);
        
        // Send SMS OTP
        $sms_response = send_sms_otp($phone, $otp);
    }
 
    return array(
        'success' => true,
        'message' => 'OTP sent successfully',
        'phone'   => $response,
        'is_already_registered' => $is_already_registered
    );
}

/**
 * ✅ Verify OTP and Login
 */
function otp_login_verify_otp_old($request) {
    $phone = sanitize_text_field($request['phone']);
    $otp   = sanitize_text_field($request['otp']);

    if (empty($phone) || empty($otp)) {
        return new WP_Error('missing_fields', 'Phone and OTP are required', array('status' => 400));
    }

    // Find user by billing_phone
    $user_query = new WP_User_Query(array(
        'meta_key'   => 'billing_phone',
        'meta_value' => $phone,
        'number'     => 1,
        'count_total'=> false
    ));

    $users = $user_query->get_results();
 
    if (empty($users)) {
        
        //return new WP_Error('user_not_found', 'No user found with this phone number', array('status' => 404));
    }else{

        $user = $users[0];
        $saved_otp   = get_user_meta($user->ID, '_login_otp', true);
        $otp_expiry  = get_user_meta($user->ID, '_login_otp_expiry', true);
    }
    if ($otp == $saved_otp && time() <= $otp_expiry) {
        $user_id = ''; $user_email = ''; $message = 'Need to create account.'; $is_already_registered = false;
        
        if (!empty($users)) {
            // OTP Valid → Login
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            $user_id = $user->ID;
            $user_email = $user->user_email;
            $message = 'Login successful';
            $is_already_registered = true;
        }
        // Optional: return WooCommerce customer data
        return array(
            'success' => true,
            'message' => $message,
            'user_id' => $user_id,
            'user_email' => $user_email,
            'phone' => $phone,
            'is_already_registered' => $is_already_registered
        );
    }

    return new WP_Error('invalid_otp', 'Invalid or expired OTP', array('status' => 401));
}

function otp_login_verify_otp($request) {
    $phone = sanitize_text_field($request['phone']);
    $otp   = sanitize_text_field($request['otp']);

    if (empty($phone) || empty($otp)) {
        return new WP_Error('missing_fields', 'Phone and OTP are required', array('status' => 400));
    }

    // 🔹 Try to find existing user
    $user_query = new WP_User_Query(array(
        'meta_key'   => 'billing_phone',
        'meta_value' => $phone,
        'number'     => 1,
        'count_total'=> false
    ));
    $users = $user_query->get_results();

    $user = !empty($users) ? $users[0] : null;

    $is_already_registered = false;
    $user_id    = '';
    $user_name = '';
    $user_email = '';
    $message    = '';

    // 🔹 Case 1: Existing user → OTP stored in usermeta
    if ($user) {
        $saved_otp  = get_user_meta($user->ID, '_login_otp', true);
        $otp_expiry = get_user_meta($user->ID, '_login_otp_expiry', true);

        if ($otp == $saved_otp && time() <= $otp_expiry) {
            // ✅ Valid OTP → Login
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);

            $user_id    = $user->ID;
            $user_email = $user->user_email;
            
            $first_name = get_user_meta($user_id, 'first_name', true);
            $last_name  = get_user_meta($user_id, 'last_name', true);
            $billing_first = get_user_meta($user_id, 'billing_first_name', true);
            $billing_last  = get_user_meta($user_id, 'billing_last_name', true);

            if (!empty($first_name) || !empty($last_name)) {
                $user_name = trim($first_name . ' ' . $last_name);
            } elseif (!empty($billing_first) || !empty($billing_last)) {
                $user_name = trim($billing_first . ' ' . $billing_last);
            } else {
                $user_name = $user->display_name;
            }
            
            $message    = 'Login successful';
            $is_already_registered = true;

            return array(
                'success' => true,
                'message' => $message,
                'user_id' => $user_id,
                'user_email' => $user_email,
                'user_name' => $user_name,
                'phone' => $phone,
                'is_already_registered' => $is_already_registered
            );
        }
    }

    // 🔹 Case 2: New user (no account yet) → OTP stored in transient
    $otp_data = get_transient("otp_for_{$phone}");

    if ($otp_data && $otp_data['otp'] == $otp && time() <= $otp_data['expires']) {
        // ✅ OTP valid for new user → allow registration
        $message = 'OTP verified. Please create an account.';
        $is_already_registered = false;

        // clear OTP after use
        delete_transient("otp_for_{$phone}");

        return array(
            'success' => true,
            'message' => $message,
            'user_id' => '',
            'user_email' => '',
            'phone' => $phone,
            'is_already_registered' => $is_already_registered
        );
    }

    // ❌ Invalid OTP
    return new WP_Error('invalid_otp', 'Invalid or expired OTP', array('status' => 401));
}




// ✅ Register REST Routes
add_action('rest_api_init', function () {
    register_rest_route('multi-address/v1', '/add', array(
        'methods' => 'POST',
        'callback' => 'wc_add_user_address',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('multi-address/v1', '/list', array(
        'methods' => 'GET',
        'callback' => 'wc_list_user_addresses',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('multi-address/v1', '/delete', array(
        'methods' => 'POST',
        'callback' => 'wc_delete_user_address',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('multi-address/v1', '/update', array(
        'methods' => 'POST',
        'callback' => 'wc_update_user_address',
        'permission_callback' => '__return_true'
    ));
});

add_action('rest_api_init', function () {
    // Disable cache for all multi-address routes
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
}, 15);
add_filter('rest_post_dispatch', function ($response) {
    nocache_headers();
    return $response;
}, 15);

/**
 * ✅ Add Address
 */
function wc_add_user_address($request) {
    $user_id = intval($request['customer_id']);
    if (!$user_id || !get_userdata($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID', array('status' => 400));
    }

    // Get existing addresses
    $addresses = get_user_meta($user_id, '_additional_addresses', true);
    if (empty($addresses) || !is_array($addresses)) {
        $addresses = array();
    }

    // If is_default is true, unset others
    $is_default = filter_var($request['is_default'], FILTER_VALIDATE_BOOLEAN);
    if ($is_default) {
        foreach ($addresses as &$addr) {
            $addr['is_default'] = false;
        }
    }

    // Build new address
    $new_address = array(
        'address_id'        => wp_generate_uuid4(),
        'address_billingMobNo' => sanitize_text_field($request['address_billingMobNo']),
        'address_billingName'  => sanitize_text_field($request['address_billingName']),
        'address_city'      => sanitize_text_field($request['address_city']),
        'address_country'   => sanitize_text_field($request['address_country']),
        'address_detail'    => sanitize_textarea_field($request['address_detail']),
        'address_landMark'  => sanitize_text_field($request['address_landMark']),
        'address_map'       => sanitize_text_field($request['address_map']),
        'address_postcode'  => sanitize_text_field($request['address_postcode']),
        'address_state'     => sanitize_text_field($request['address_state']),
        'address_type'      => intval($request['address_type']),
        'customer_id'       => $user_id,
        'customer_mail'     => sanitize_email($request['customer_mail']),
        'customer_mobNo'    => sanitize_text_field($request['customer_mobNo']),
        'geo_location'      => $request['geo_location'] ?: null,
        'is_default'        => $is_default,
        'status'            => 1,
    );

    $addresses[] = $new_address;
    update_user_meta($user_id, '_additional_addresses', $addresses);

    return array(
        'success'   => true,
        'message'   => 'Address added successfully',
        'addresses' => $addresses
    );
}

/**
 * List Addresses
 */
function wc_list_user_addresses($request) {
    $user_id = intval($request['user_id']);
    if (!$user_id || !get_userdata($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID', array('status' => 400));
    }

    $addresses = get_user_meta($user_id, '_additional_addresses', true);
    if (empty($addresses)) {
        $addresses = array();
    }

    // Only return active addresses
    $active_addresses = array_filter($addresses, function ($addr) {
        return isset($addr['status']) && intval($addr['status']) === 1;
    });

    return array(
        'success'   => true,
        'addresses' => array_values($active_addresses)
    );
}

function wc_list_user_addresses_v2($request) {
    nocache_headers();
    $user_id = intval($request['user_id']);
    if (!$user_id || !get_userdata($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID', ['status' => 400]);
    }

    $addresses = get_user_meta($user_id, '_additional_addresses', true);
    
 
    
    if (empty($addresses) || !is_array($addresses)) {
        $addresses = [];
    }
    
    
    /*
    $active_addresses = array_filter($addresses, function ($addr) {
        return isset($addr['status']) && intval($addr['status']) === 1;
    });
    */
    
    // Return everything (status 1 + status 0)
    return [
        'success'   => true,
        'addresses' => array_values($addresses) // reset indexes
    ];
}


/**
 * Soft Delete Address
 */
function wc_delete_user_address($request) {
    $user_id    = intval($request['user_id']);
    $address_id = sanitize_text_field($request['address_id']);

    if (!$user_id || !get_userdata($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID', array('status' => 400));
    }

    $addresses = get_user_meta($user_id, '_additional_addresses', true);

    if (!empty($addresses)) {
        foreach ($addresses as &$addr) {
            if ($addr['address_id'] === $address_id) {
                $addr['status'] = 0; // soft delete
                update_user_meta($user_id, '_additional_addresses', $addresses);

               /* 
               return array(
                    'success'   => true,
                    'message'   => 'Address deleted successfully',
                    'addresses' => $addresses
                );
                */
            }
        }
    }
    
    
    $addresses = get_user_meta($user_id, '_additional_addresses', true);
    if (empty($addresses)) {
        $addresses = array();
    }

    // Only return active addresses
    $active_addresses = array_filter($addresses, function ($addr) {
        return isset($addr['status']) && intval($addr['status']) === 1;
    });

    return array(
        'success'   => true,
        'message'   => 'Address deleted successfullyy.',
        'addresses' => array_values($active_addresses)
    );
    
    
    //return new WP_Error('address_not_found', 'Address not found', array('status' => 404));
}


function wc_update_user_address($request) {
    $user_id    = intval($request['customer_id']);
    $address_id = sanitize_text_field($request['address_id']);

    if (!$user_id || !get_userdata($user_id)) {
        return new WP_Error('invalid_user', 'Invalid user ID', ['status' => 400]);
    }

    $addresses = get_user_meta($user_id, '_additional_addresses', true);
    if (empty($addresses) || !is_array($addresses)) {
        return new WP_Error('address_not_found', 'No addresses found for this user', ['status' => 404]);
    }

    $found = false;

    // If is_default is true, unset others
    $is_default = isset($request['is_default']) ? filter_var($request['is_default'], FILTER_VALIDATE_BOOLEAN) : null;
    if ($is_default) {
        foreach ($addresses as &$addr) {
            $addr['is_default'] = false;
        }
    }

    foreach ($addresses as &$addr) {
        if ($addr['address_id'] === $address_id) {
            $found = true;

            // Update fields only if present in request
            $fields = [
                'address_billingMobNo','address_billingName','address_city','address_country','address_detail',
                'address_landMark','address_map','address_postcode','address_state','address_type',
                'customer_mail','customer_mobNo','geo_location','is_default','status'
            ];

            foreach ($fields as $field) {
                if (isset($request[$field])) {
                    if ($field === 'is_default') {
                        $addr[$field] = $is_default;
                    } elseif ($field === 'address_type' || $field === 'status') {
                        $addr[$field] = intval($request[$field]);
                    } else {
                        $addr[$field] = sanitize_text_field($request[$field]);
                    }
                }
            }
            break;
        }
    }

    if (!$found) {
        return new WP_Error('address_not_found', 'Address not found', ['status' => 404]);
    }

    update_user_meta($user_id, '_additional_addresses', $addresses);


    $addresses = get_user_meta($user_id, '_additional_addresses', true);
    if (empty($addresses)) {
        $addresses = array();
    }
    // Only return active addresses
    $active_addresses = array_filter($addresses, function ($addr) {
        return isset($addr['status']) && intval($addr['status']) === 1;
    });

    return [
        'success'   => true,
        'message'   => 'Address updated successfullyy.',
        'addresses' => array_values($active_addresses)
    ];
}


/*
// Add custom input field
add_action('woocommerce_product_options_general_product_data', function() {
    woocommerce_wp_text_input( array(
        'id'          => '_advance_wallet_amount',
        'label'       => __('Advance Wallet Amount', 'woocommerce'),
        'desc_tip'    => true,
        'description' => __('Enter the advance wallet amount for this product.', 'woocommerce'),
        'type'        => 'number',
        'custom_attributes' => array(
            'step' => 'any',
            'min'  => '0'
        ),
    ));
});

// Save custom input field
add_action('woocommerce_admin_process_product_object', function($product) {
    if (isset($_POST['_advance_wallet_amount'])) {
        $product->update_meta_data('_advance_wallet_amount', sanitize_text_field($_POST['_advance_wallet_amount']));
    }
});
*/ 



add_filter( 'woocommerce_subscriptions_email_enabled_renewal_order', '__return_false' );
add_filter( 'woocommerce_email_enabled_new_renewal_order', '__return_false' );
add_filter( 'woocommerce_email_enabled_customer_completed_renewal_order', '__return_false' );
add_filter( 'woocommerce_email_enabled_customer_renewal_invoice', '__return_false' );



/**
 * 1. Register the custom subscription status wc-pause
 */
add_action( 'init', function() {

    register_post_status( 'wc-pause', array(
        'label'                     => 'Pause',
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop(
            'Pause <span class="count">(%s)</span>',
            'Pause <span class="count">(%s)</span>'
        ),
    ));

});


/**
 * 2. Add to subscription status list (internal WCS list)
 */
add_filter( 'wcs_subscription_statuses', function( $statuses ) {
    $statuses['wc-pause'] = 'Pause';
    return $statuses;
});


/**
 * 3. Add to editable subscription statuses (admin dropdown logic)
 */
add_filter( 'woocommerce_valid_subscription_statuses', function( $statuses ) {
    if ( ! in_array( 'pause', $statuses, true ) ) {
        $statuses[] = 'pause';
    }
    return $statuses;
});


/**
 * 4. FIX: Add to admin STATUS DROPDOWN (WCS 3.x → 6.x)
 */
add_filter( 'woocommerce_subscription_statuses_dropdown', function( $statuses ) {

    // WCS dropdown expects key WITHOUT “wc-”
    $statuses['pause'] = 'Pause';

    return $statuses;
});



/**
 * Remove meta_data and _links from WooCommerce Customer REST API response
 */
add_filter( 'woocommerce_rest_prepare_customer', 'custom_wc_rest_customer_response', 10, 3 );
function custom_wc_rest_customer_response( $response, $user, $request ) {

    if ( empty( $response ) ) {
        return $response;
    }

    $data = $response->get_data();

    // Set as null
    $data['meta_data'] = null;
    $data['_links']    = null;

    $response->set_data( $data );

    return $response;
}
add_filter( 'rest_pre_serve_request', function( $served, $result, $request, $server ) {

    if ( strpos( $request->get_route(), '/wc/v3/customers' ) !== false ) {

        header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
        echo wp_json_encode( $result->get_data() );
        return true;
    }

    return $served;

}, 10, 4 );


add_filter('woocommerce_rest_product_object_query', 'aaraa_rest_products_rearranged_order', 10, 2);
function aaraa_rest_products_rearranged_order($args, $request) {
    $params = $request->get_query_params();
    if (!isset($params['orderby'])) {      // only when client didn't pick an order
        $args['orderby'] = 'menu_order';
        $args['order']   = 'ASC';
    }
    return $args;
}





add_filter( 'woocommerce_account_menu_items', 'custom_remove_my_account_menu_items', 999 );

function custom_remove_my_account_menu_items( $items ) {

    // Remove menu items
    unset( $items['dashboard'] );        // Dashboard
    unset( $items['downloads'] );        // Downloads
    unset( $items['compare'] );          // Compare
    unset( $items['followings'] );       // Followings
    unset( $items['support-tickets'] );  // Support Tickets
    unset( $items['inquiry'] );        // Inquiries
    unset( $items['wishlist'] );         // Wishlist

    return $items;
}






add_action( 'add_meta_boxes', 'bbloomer_order_meta_box' );
function bbloomer_order_meta_box() {

	// Screens for the Subscription edit page (legacy post type + HPOS).
	$screens = array( 'shop_subscription', 'woocommerce_page_wc-orders--shop_subscription' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-subscription' );
	}

	foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
		add_meta_box( 'custom_box', 'Send Payment Reminder (whatsapp)', 'bbloomer_single_order_meta_box', $screen, 'advanced', 'high' );
	}
}

function bbloomer_single_order_meta_box( $post_or_order ) {

	// HPOS passes a WC_Order/WC_Subscription object; legacy passes a WP_Post.
	if ( is_a( $post_or_order, 'WC_Abstract_Order' ) ) {
		$order_id = $post_or_order->get_id();
	} elseif ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
		$order_id = (int) $post_or_order->ID;
	} else {
		$order_id = 0;
	}

	$order_id_quote = "'" . esc_js( $order_id ) . "'";
	echo '<button type="button" class="button button-primary" onClick="send_whatsapp_message(' . $order_id_quote . ')">Send Message</button>';
	?>
	<script>
	function send_whatsapp_message(order_id){
		jQuery.ajax({
			url: "https://madrasmilk.aaraakart.com/send_whatsapp_reminder_message.php",
			type: "POST",
			data: { order_id: order_id },
			success: function (response) {
				alert('Whatsapp reminder message has been sent successfully.');
			},
			error: function (xhr) {
				alert('Failed to send reminder (' + xhr.status + ').');
			}
		});
	}
	</script>
	<?php
}















// 1. Add the meta box
add_action('add_meta_boxes', 'custom_subscription_meta_box');
function custom_subscription_meta_box() {
    add_meta_box(
        'custom_subscription_info', // ID
        __('Send Subscription Renewal Payment Link ', 'your-textdomain'), // Title
        'render_custom_subscription_meta_box', // Callback
        'shop_subscription', // Post type
        'side', // Context (side, normal, advanced)
        'default' // Priority
    );
}

// 2. Render the meta box content
function render_custom_subscription_meta_box($post) {
    // Retrieve current value if it exists
    $custom_value = get_post_meta($post->post_parent, '_custom_subscription_info', true);
    $subscription_id = $post->ID;
    $subscription = get_post($subscription_id);
  
$subscription = wcs_get_subscription($subscription_id);

if ($subscription) {
    $subscription_renewal_amount = $subscription->get_total()*30; // Total recurring amount
    //echo 'Subscription Amount: ' . wc_price($amount);
}
    
    ?>
    <label for="subscription_renewal_amount"><?php _e('Renewal Amount :', 'your-textdomain'); ?></label>
    <input type="text" name="subscription_renewal_amount" id="subscription_renewal_amount" value="<?php echo esc_attr($subscription_renewal_amount); ?>" style="width:100%;margin-bottom:10px;" />
     
  <?php
    // Opens the WhatsApp payment-reminder popup (Aaraa plugin: whatsapp-subscription-reminder.js).
    // The popup takes the renewal amount + template and sends a signed link to
    // the /renewal-subscription checkout page.
    echo '<button type="button" class="button button-primary" id="aaraa-sub-reminder-btn" data-subscription="' . esc_attr( $subscription_id ) . '">Send Payment Reminder (WhatsApp)</button>';
?>
<script>
    document.getElementById('subscription_renewal_amount').addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '');
  });
</script>

    <?php
    // Add a nonce for security
    wp_nonce_field('save_custom_subscription_info', 'custom_subscription_info_nonce');
}

// 3. Save the meta box data
add_action('save_post_shop_subscription', 'save_custom_subscription_meta_box');
function save_custom_subscription_meta_box($post_id) {
    // Check nonce
    if (!isset($_POST['custom_subscription_info_nonce']) || !wp_verify_nonce($_POST['custom_subscription_info_nonce'], 'save_custom_subscription_info')) {
        return;
    }

    // Check for autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save custom value
    if (isset($_POST['custom_subscription_info'])) {
        update_post_meta($post_id, '_custom_subscription_info', sanitize_text_field($_POST['custom_subscription_info']));
    }
}




/* wallet user edit page */
// Add checkbox field
add_action( 'show_user_profile', 'add_wallet_checkbox_field' );
add_action( 'edit_user_profile', 'add_wallet_checkbox_field' );

function add_wallet_checkbox_field( $user ) {
    ?>
    <h3>Wallet Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="is_enabled_wallet_on_checkout">Enable Wallet on Checkout</label></th>
            <td>
                <input type="checkbox"
                       name="is_enabled_wallet_on_checkout"
                       id="is_enabled_wallet_on_checkout"
                       value="1"
                       <?php checked( (int) get_user_meta( $user->ID, 'is_enabled_wallet_on_checkout', true ), 1 ); ?> />
                <span class="description">Check to enable wallet option during checkout.</span>
            </td>
        </tr>
    </table>
    <?php
}

// Save checkbox field safely
add_action( 'personal_options_update', 'save_wallet_checkbox_field' );
add_action( 'edit_user_profile_update', 'save_wallet_checkbox_field' );

function save_wallet_checkbox_field( $user_id ) {
    // Security check
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    // Always sanitize input
    $value = isset( $_POST['is_enabled_wallet_on_checkout'] ) ? 1 : 0;

    update_user_meta( $user_id, 'is_enabled_wallet_on_checkout', $value );
}


/* checkout page wallet enable / disable */

// Hook into WooCommerce payment gateways
add_filter( 'woocommerce_available_payment_gateways', 'restrict_wallet_payment_gateway' );

function restrict_wallet_payment_gateway( $available_gateways ) {
    if ( is_admin() ) {
        return $available_gateways; // Don't affect admin orders
    }

    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $wallet_enabled = get_user_meta( $user_id, 'is_enabled_wallet_on_checkout', true );

        // If checkbox is not enabled, remove the wallet gateway
        if ( ! $wallet_enabled && isset( $available_gateways['wps_wcb_wallet_payment_gateway'] ) ) {
            unset( $available_gateways['wps_wcb_wallet_payment_gateway'] );
        }
    }

    return $available_gateways;
}

/* start date input */
// Show Date Picker on subscription-enabled products
add_action( 'woocommerce_before_add_to_cart_button', function() {
    global $product;

    // Show only if product has subscription option
    if ( class_exists( 'WCS_ATT_Product_Schemes' ) && WCS_ATT_Product_Schemes::has_subscription_schemes( $product ) ) {
        echo '<p id="custom_start_date_field" style="display:none; margin-bottom:15px;width: 100%;">
            <label for="custom_start_date"><strong>Choose Subscription Start Date:</strong></label><br>
            <input type="date" name="custom_start_date" min="' . date('Y-m-d') . '">
        </p>';

        // Inline JS to toggle field when "Subscribe" is selected
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const radios = document.querySelectorAll("input[name='subscribe-to-action-input']");
            const field  = document.getElementById("custom_start_date_field");

            function toggleField() {
                let subscribeSelected = false;
                radios.forEach(radio => {
                    if (radio.checked && radio.value === "yes") { 
                        subscribeSelected = true;
                    }
                });
                field.style.display = subscribeSelected ? "block" : "none";

                // Disable input when hidden
                const input = field.querySelector("input");
                if (input) {
                    input.disabled = !subscribeSelected;
                }
            }

            radios.forEach(radio => {
                radio.addEventListener("change", toggleField);
            });

            // Run once on page load
            toggleField();
        });
        </script>
        <?php
    }
});


// Save chosen date to cart item
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    if ( isset($_POST['custom_start_date']) && ! empty($_POST['custom_start_date']) ) {
        $cart_item_data['custom_start_date'] = sanitize_text_field($_POST['custom_start_date']);
    }
    return $cart_item_data;
}, 10, 2 );

// Show chosen date in order line items
add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['custom_start_date'] ) ) {
        $item->add_meta_data( 'Custom Start Date', $values['custom_start_date'], true );
    }
}, 10, 4 );




/*
// Update subscription start/next payment dates
add_action( 'wcs_subscription_created', function( $subscription, $order ) {
    foreach ( $order->get_items() as $item_id => $item ) {
        $custom_start_date = $item->get_meta( 'Custom Start Date', true );
        if ( ! empty( $custom_start_date ) ) {
            $subscription->update_dates( array(
                'start'        => $custom_start_date,
                'trial_end'    => '', // optional
                'next_payment' => $custom_start_date,
            ) );
        }
    }
}, 10, 2 );
*/

add_action( 'wcs_subscription_created', function( $subscription, $order ) {
    foreach ( $order->get_items() as $item_id => $item ) {
        $custom_start_date = $item->get_meta( 'Custom Start Date', true );

        if ( ! empty( $custom_start_date ) ) {
            // Get billing schedule (interval & period)
            $interval = $subscription->get_billing_interval(); // e.g. 1
            $period   = $subscription->get_billing_period();   // e.g. month, week, day, year

            // Calculate correct first renewal date
            $next_payment = strtotime( "+{$interval} {$period}", strtotime( $custom_start_date ) );
            $next_payment = date( 'Y-m-d H:i:s', $next_payment );

            // Update subscription dates
            $subscription->update_dates( array(
                'start'        => $custom_start_date,
                'trial_end'    => '', // optional
                'next_payment' => $next_payment,
            ) );
        }
    }
}, 10, 2 );


/* end date input */





/**
 * Register REST API Route
 */
add_action('rest_api_init', function () {

    register_rest_route('sms/v1', '/send', [
        'methods'             => 'POST',
        'callback'            => 'sms_send_api',
        'permission_callback' => '__return_true', // Change for production
    ]);

});

/**
 * REST API Callback
 */
function sms_send_api(WP_REST_Request $request)
{
    $params = $request->get_json_params();

    $number   = isset($params['number']) ? sanitize_text_field($params['number']) : '';
    $message  = isset($params['message']) ? sanitize_textarea_field($params['message']) : '';
    $senderID = isset($params['senderID']) ? sanitize_text_field($params['senderID']) : 'MAMILK';

    // Validation
    if (empty($number)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Mobile number is required.'
        ], 400);
    }

    if (empty($message)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Message is required.'
        ], 400);
    }

    // Send SMS
    $response = sms_send_message($number, $message, $senderID);

    return new WP_REST_Response($response, 200);
}

/**
 * Send SMS
 */
function sms_send_message($phone, $message, $senderID = 'MAMILK')
{
    $api_url = "http://sms.mysmarthost.in/api/sendsms.php";

    $postData = [
        'user'     => 'madrasmilk',
        'apikey'   => 'atWhzca1M62UI1Ze9om9',
        'mobile'   => $phone,
        'senderid' => $senderID,
        'message'  => $message,
        'tid'      => '1207160701072793529',
        'type'     => 'txt'
    ];

    $response = wp_remote_post($api_url, [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'body' => $postData
    ]);

    if (is_wp_error($response)) {

        error_log('SMS API Error: ' . $response->get_error_message());

        return [
            'success' => false,
            'message' => $response->get_error_message()
        ];
    }

    $body = wp_remote_retrieve_body($response);

    error_log('SMS Response: ' . $body);

    return [
        'success'  => true,
        'response' => $body
    ];
}