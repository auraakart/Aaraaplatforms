<?php
/**
 * WCFMu plugin core
 *
 * Custom subscription schedules + REST API creation
 *
 * @author      WC Lovers
 * @package     wcfmu/core
 * @version     1.0.0
 */

class WCFMu_Subscription_Create {

    /**
     * Singleton-ish handle so the Aaraa White Label Admin plugin can own the
     * REST route registration while reusing these handlers.
     *
     * @var WCFMu_Subscription_Create|null
     */
    private static $instance = null;

    /**
     * The live instance (set on construction).
     *
     * @return WCFMu_Subscription_Create|null
     */
    public static function instance() {
        return self::$instance;
    }

    public function __construct() {
        if ( ! function_exists( 'wcs_create_subscription' ) ) {
            return;
        }

        self::$instance = $this;

        // REST routes are registered by the Aaraa White Label Admin plugin
        // (\Aaraa\Admin\Subscription_API) under the bare `subscriptions`
        // namespace, delegating to the handlers in this class.
        add_action( 'wcfm_load_scripts',                     [ $this, 'load_scripts' ], 31 );
        add_action( 'wp_ajax_wcfm_create_subscription',      [ $this, 'ajax_create_subscription' ] );
        add_action( 'wp_ajax_wcfm_search_sub_products',      [ $this, 'ajax_search_products' ] );
        add_action( 'wp_ajax_wcfm_search_sub_customers',     [ $this, 'ajax_search_customers' ] );

        // After each renewal, recalculate next_payment for weekend/custom schedules
        add_action( 'woocommerce_subscription_payment_complete', [ $this, 'update_next_payment_for_custom_schedule' ], 20 );

        // After each renewal, advance delivery dates list
        add_action( 'woocommerce_subscription_renewal_payment_complete', [ $this, 'update_next_payment_after_renewal' ], 20 );
    }

    /* ── Script localisation ──────────────────────────────────── */

    public function load_scripts( $end_point ) {
        if ( $end_point !== 'wcfm-subscriptions' ) {
            return;
        }
        wp_localize_script( 'wcfm_subscriptions_js', 'wcfmu_sub_create_params', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wcfm_sub_create_nonce' ),
        ] );
    }

    /* ── REST API ─────────────────────────────────────────────── */

    /**
     * REST namespace. Endpoints live at /wp-json/subscriptions/… (no wcfm/v1
     * version segment), matching the project's other bare-namespace APIs.
     */
    const REST_NS = 'subscriptions';

    public function register_rest_routes() {
        $ns = self::REST_NS;

        // Serve the create endpoint at the bare namespace root, with or without
        // a trailing slash.
        add_filter( 'rest_endpoints', [ $this, 'alias_base_route' ] );

        register_rest_route( $ns, '/', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_create_subscription' ],
            'permission_callback' => [ $this, 'rest_permission_check' ],
            'args'                => $this->get_rest_args(),
        ] );

        register_rest_route( $ns, '/(?P<id>\d+)/pause', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_pause_subscription' ],
            'permission_callback' => [ $this, 'rest_pause_resume_permission_check' ],
            'args'                => [
                'id'          => [ 'required' => true, 'type' => 'integer' ],
                'pause_dates' => [ 'required' => true ],
                'customer_id' => [ 'required' => false, 'type' => 'integer', 'default' => 0 ],
            ],
        ] );

        register_rest_route( $ns, '/(?P<id>\d+)/resume', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_resume_subscription' ],
            'permission_callback' => [ $this, 'rest_pause_resume_permission_check' ],
            'args'                => [
                'id'          => [ 'required' => true, 'type' => 'integer' ],
                'customer_id' => [ 'required' => false, 'type' => 'integer', 'default' => 0 ],
            ],
        ] );
    }

    /**
     * Serve the create handler at the bare namespace root (/wp-json/subscriptions),
     * so it works with or without the trailing slash.
     *
     * @param array $endpoints All registered REST endpoints.
     * @return array
     */
    public function alias_base_route( $endpoints ) {
        $slashed = '/' . self::REST_NS . '/';
        $bare    = '/' . self::REST_NS;
        if ( isset( $endpoints[ $slashed ] ) ) {
            $endpoints[ $bare ] = $endpoints[ $slashed ];
        }
        return $endpoints;
    }

    public function rest_permission_check() {
        return current_user_can( 'manage_woocommerce' );
    }

    public function rest_pause_resume_permission_check( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', 'Authentication required.', [ 'status' => 401 ] );
        }
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }
        $sub_id       = absint( $request->get_param('id') );
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            return new WP_Error( 'not_found', 'Subscription not found.', [ 'status' => 404 ] );
        }
        if ( (int) $subscription->get_user_id() !== get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', 'You do not have permission to manage this subscription.', [ 'status' => 403 ] );
        }
        return true;
    }

    public function get_rest_args() {
        return [
            'customer_id'    => [ 'required' => true,  'type' => 'integer', 'minimum' => 1 ],
            // Either items[] or product_id must be supplied; enforced in normalise_items().
            'items'          => [
                'required' => false,
                'type'     => 'array',
                'default'  => [],
                'items'    => [
                    'type'       => 'object',
                    'properties' => [
                        'product_id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => true ],
                        'quantity'   => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
                    ],
                ],
            ],
            'product_id'     => [ 'required' => false, 'type' => 'integer', 'minimum' => 1, 'default' => 0 ],
            'quantity'       => [ 'required' => false, 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
            'schedule_type'  => [
                'required' => true,
                'type'     => 'string',
                'enum'     => [ 'daily', 'alternate', 'weekend', 'custom' ],
            ],
            'custom_days'    => [
                'required' => false,
                'type'     => 'array',
                'items'    => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 6 ],
                'default'  => [],
            ],
            'start_date'     => [ 'required' => false, 'type' => 'string', 'default' => '' ],
            'payment_method' => [ 'required' => false, 'type' => 'string', 'default' => '' ],
            'status'         => [
                'required' => false,
                'type'     => 'string',
                'enum'     => [ 'active', 'pending', 'on-hold' ],
                'default'  => 'active',
            ],
            'billing'        => [ 'required' => false, 'type' => 'object', 'default' => [] ],
            'shipping'       => [ 'required' => false, 'type' => 'object', 'default' => [] ],
        ];
    }

    /**
     * Resolve the requested products into a list of [ product, quantity ] pairs.
     *
     * Accepts items[] with a per-product quantity, or the legacy single product_id +
     * quantity shape still sent by the admin AJAX screen.
     *
     * @return array|WP_Error
     */
    private function normalise_items( array $params ) {
        $raw = [];

        if ( ! empty( $params['items'] ) ) {
            $raw = (array) $params['items'];
        } elseif ( ! empty( $params['product_id'] ) ) {
            $raw = [ [ 'product_id' => $params['product_id'], 'quantity' => $params['quantity'] ?? 1 ] ];
        }

        if ( empty( $raw ) ) {
            return new WP_Error( 'invalid_product', 'Please select a product. Supply items[] or product_id.' );
        }

        $items = [];

        foreach ( $raw as $entry ) {
            $entry      = (array) $entry;
            $product_id = absint( $entry['product_id'] ?? 0 );
            $quantity   = max( 1, absint( $entry['quantity'] ?? 1 ) );

            if ( ! $product_id ) {
                return new WP_Error( 'invalid_product', 'Each item requires a product_id.' );
            }

            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                return new WP_Error( 'invalid_product', sprintf( 'Product %d not found.', $product_id ) );
            }

            // Same product listed twice: combine into one line item.
            if ( isset( $items[ $product_id ] ) ) {
                $items[ $product_id ]['quantity'] += $quantity;
                continue;
            }

            $items[ $product_id ] = [ 'product' => $product, 'quantity' => $quantity ];
        }

        return array_values( $items );
    }

    /**
     * Address fields accepted in the billing/shipping request objects.
     * Anything else in the payload is ignored.
     */
    private static $address_fields = [
        'first_name', 'last_name', 'company', 'email', 'phone',
        'address_1', 'address_2', 'city', 'state', 'postcode', 'country',
    ];

    /**
     * Merge a request-supplied address over the customer's saved address.
     *
     * @param array  $base     Address pulled from the WC_Customer profile.
     * @param array  $override Address fields from the request body.
     * @return array|WP_Error
     */
    private function merge_address( array $base, array $override ) {
        if ( empty( $override ) ) {
            return $base;
        }

        foreach ( self::$address_fields as $field ) {
            if ( ! isset( $override[ $field ] ) ) {
                continue;
            }
            $base[ $field ] = 'email' === $field
                ? sanitize_email( $override[ $field ] )
                : sanitize_text_field( $override[ $field ] );
        }

        // WooCommerce stores country/state as codes. Accept a full name too and resolve it,
        // otherwise tax and shipping zones silently fail to match.
        if ( ! empty( $base['country'] ) ) {
            $code = $this->resolve_country_code( $base['country'] );
            if ( false === $code ) {
                return new WP_Error( 'invalid_country', sprintf( 'Unrecognised country "%s". Use the ISO code, e.g. "IN".', $base['country'] ) );
            }
            $base['country'] = $code;

            if ( ! empty( $base['state'] ) ) {
                $state = $this->resolve_state_code( $code, $base['state'] );
                if ( false === $state ) {
                    return new WP_Error( 'invalid_state', sprintf( 'Unrecognised state "%s" for country "%s".', $base['state'], $code ) );
                }
                $base['state'] = $state;
            }
        }

        return $base;
    }

    /** Resolve "IN" or "India" to "IN". Returns false if unrecognised. */
    private function resolve_country_code( $value ) {
        $value     = trim( (string) $value );
        $countries = WC()->countries ? WC()->countries->get_countries() : [];

        if ( isset( $countries[ strtoupper( $value ) ] ) ) {
            return strtoupper( $value );
        }
        foreach ( $countries as $code => $name ) {
            if ( 0 === strcasecmp( $name, $value ) ) {
                return $code;
            }
        }
        return false;
    }

    /** Resolve "TN" or "Tamil Nadu" to "TN" within a country. Returns false if unrecognised. */
    private function resolve_state_code( $country_code, $value ) {
        $value  = trim( (string) $value );
        $states = WC()->countries ? WC()->countries->get_states( $country_code ) : [];

        // Countries with no defined state list accept free text.
        if ( empty( $states ) ) {
            return $value;
        }
        if ( isset( $states[ strtoupper( $value ) ] ) ) {
            return strtoupper( $value );
        }
        foreach ( $states as $code => $name ) {
            if ( 0 === strcasecmp( $name, $value ) ) {
                return $code;
            }
        }
        return false;
    }

    public function rest_create_subscription( WP_REST_Request $request ) {
        $params = [
            'customer_id'    => (int) $request->get_param( 'customer_id' ),
            'items'          => (array) $request->get_param( 'items' ),
            'product_id'     => (int) $request->get_param( 'product_id' ),
            'quantity'       => max( 1, (int) $request->get_param( 'quantity' ) ),
            'schedule_type'  => sanitize_text_field( $request->get_param( 'schedule_type' ) ),
            'custom_days'    => array_map( 'absint', (array) $request->get_param( 'custom_days' ) ),
            'start_date'     => sanitize_text_field( $request->get_param( 'start_date' ) ),
            'payment_method' => sanitize_text_field( $request->get_param( 'payment_method' ) ),
            'status'         => sanitize_text_field( $request->get_param( 'status' ) ),
            'billing'        => (array) $request->get_param( 'billing' ),
            'shipping'       => (array) $request->get_param( 'shipping' ),
        ];

        $result = $this->create_subscription_from_params( $params );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( $result, 201 );
    }

    public function rest_pause_subscription( WP_REST_Request $request ) {
        $sub_id       = absint( $request->get_param('id') );
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            return new WP_REST_Response( [ 'message' => 'Subscription not found.' ], 404 );
        }

        // Optional customer_id scoping (admin only)
        $customer_id = (int) $request->get_param('customer_id');
        if ( $customer_id && current_user_can('manage_woocommerce') && (int) $subscription->get_user_id() !== $customer_id ) {
            return new WP_REST_Response( [ 'message' => 'Subscription does not belong to that customer.' ], 403 );
        }

        $current_status = str_replace('wc-', '', $subscription->get_status());
        if ( in_array($current_status, ['cancelled', 'expired'], true) ) {
            return new WP_REST_Response( [ 'message' => 'Cannot pause a cancelled or expired subscription.' ], 400 );
        }

        // Normalise pause_dates: string, array, or {start, end} object
        $raw = $request->get_param('pause_dates');
        $dates = $this->normalise_pause_dates( $raw );

        if ( empty($dates) ) {
            return new WP_REST_Response( [ 'message' => 'No valid future dates provided.' ], 400 );
        }

        sort($dates);
        $today       = current_time('Y-m-d');
        $first_date  = reset($dates);
        $last_date   = end($dates);
        $resume_date = date('Y-m-d', strtotime($last_date . ' +1 day'));

        update_post_meta( $sub_id, '_wcfmu_pause_dates',  wp_json_encode($dates) );
        update_post_meta( $sub_id, '_wcfmu_pause_resume', $resume_date );

        // Date-scoped pause: the subscription is paused only on each requested
        // date and delivers on every other day (so non-consecutive dates work).
        // The shared engine arms a sync event on each date boundary — in the
        // SITE timezone (local midnight, so the whole calendar day is covered) —
        // and applies today's state immediately.
        $wcfmu_wcs = new WCFMu_WCSubscriptions();
        $wcfmu_wcs->schedule_pause_sync( $sub_id, $dates );
        $wcfmu_wcs->sync_pause_status( $sub_id );
        $status = str_replace('wc-', '', $subscription->get_status());

        $note_schedule = class_exists( '\\Aaraa\\Admin\\Subscription_Delivery' )
            ? \Aaraa\Admin\Subscription_Delivery::describe_pause_schedule( $dates )
            : implode( ', ', $dates );
        $subscription->add_order_note(
            sprintf(
                'Pause set via API (customer #%d) — paused only on: %s.',
                $customer_id,
                $note_schedule
            )
        );

        return new WP_REST_Response( [
            'id'           => $sub_id,
            'status'       => $status,
            'is_paused'    => ( 'pause' === $status ),
            'pause_dates'  => $dates,
            'pause_starts' => $first_date,
            'resume_date'  => $resume_date,
        ], 200 );
    }

    public function rest_resume_subscription( WP_REST_Request $request ) {
        $sub_id       = absint( $request->get_param('id') );
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            return new WP_REST_Response( [ 'message' => 'Subscription not found.' ], 404 );
        }

        $customer_id = (int) $request->get_param('customer_id');
        if ( $customer_id && current_user_can('manage_woocommerce') && (int) $subscription->get_user_id() !== $customer_id ) {
            return new WP_REST_Response( [ 'message' => 'Subscription does not belong to that customer.' ], 403 );
        }

        $current_status = str_replace('wc-', '', $subscription->get_status());
        if ( $current_status !== 'pause' ) {
            return new WP_REST_Response( [ 'message' => 'Subscription is not paused.', 'status' => $current_status ], 200 );
        }

        wp_clear_scheduled_hook('wcfmu_auto_resume_subscription', [ $sub_id ]);

        // Log the API trigger as a subscription note.
        $subscription->add_order_note( sprintf( 'Resume triggered via API (customer #%d).', $customer_id ) );

        $wcfmu_wcs = new WCFMu_WCSubscriptions();
        $result    = $wcfmu_wcs->auto_resume_subscription( $sub_id );

        return new WP_REST_Response( array_merge( [ 'id' => $sub_id ], $result ), 200 );
    }

    /**
     * UTC timestamp for the start (00:00) of a given calendar date in the
     * site's timezone. Used to schedule pause/resume on local day boundaries.
     *
     * @param string $date Y-m-d.
     * @return int
     */
    private function local_day_start_ts( $date ) {
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone( 'UTC' );
            $dt = new DateTime( $date . ' 00:00:00', $tz );
            return $dt->getTimestamp();
        } catch ( Exception $e ) {
            return strtotime( $date . ' 00:00:00 UTC' );
        }
    }

    private function normalise_pause_dates( $raw ) {
        $today = current_time('Y-m-d');
        $dates = [];

        if ( is_string($raw) ) {
            // Single date string
            $d = sanitize_text_field($raw);
            if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d >= $today ) $dates[] = $d;
        } elseif ( is_array($raw) ) {
            if ( isset($raw['start']) && isset($raw['end']) ) {
                // Range object
                $start = sanitize_text_field($raw['start']);
                $end   = sanitize_text_field($raw['end']);
                if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ) {
                    $cur = strtotime($start);
                    $fin = strtotime($end);
                    while ( $cur <= $fin ) {
                        $d = date('Y-m-d', $cur);
                        if ( $d >= $today ) $dates[] = $d;
                        $cur = strtotime('+1 day', $cur);
                    }
                }
            } else {
                // Array of individual dates
                foreach ( $raw as $d ) {
                    $d = sanitize_text_field($d);
                    if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d >= $today ) $dates[] = $d;
                }
            }
        }

        return array_unique($dates);
    }

    /* ── AJAX — Create subscription ───────────────────────────── */

    public function ajax_create_subscription() {
        check_ajax_referer( 'wcfm_sub_create_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $custom_days_raw = isset( $_POST['custom_days'] ) ? (array) $_POST['custom_days'] : [];

        $params = [
            'customer_id'    => absint( $_POST['customer_id'] ?? 0 ),
            'product_id'     => absint( $_POST['product_id'] ?? 0 ),
            'quantity'       => max( 1, absint( $_POST['quantity'] ?? 1 ) ),
            'schedule_type'  => sanitize_text_field( $_POST['schedule_type'] ?? '' ),
            'custom_days'    => array_map( 'absint', $custom_days_raw ),
            'start_date'     => sanitize_text_field( $_POST['start_date'] ?? '' ),
            'payment_method' => sanitize_text_field( $_POST['payment_method'] ?? '' ),
            'status'         => sanitize_text_field( $_POST['status'] ?? 'active' ),
        ];

        $result = $this->create_subscription_from_params( $params );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    /* ── Core creation logic ──────────────────────────────────── */

    private function create_subscription_from_params( array $params ) {
        if ( ! $params['customer_id'] ) {
            return new WP_Error( 'invalid_customer', 'Please select a customer.' );
        }

        $items = $this->normalise_items( $params );
        if ( is_wp_error( $items ) ) {
            return $items;
        }

        $schedule_map = [
            'daily'     => [ 'period' => 'day', 'interval' => 1 ],
            'alternate' => [ 'period' => 'day', 'interval' => 2 ],
            'weekend'   => [ 'period' => 'day', 'interval' => 1 ],
            'custom'    => [ 'period' => 'day', 'interval' => 1 ],
        ];

        if ( ! isset( $schedule_map[ $params['schedule_type'] ] ) ) {
            return new WP_Error( 'invalid_schedule', 'Please select a valid schedule type.' );
        }

        $billing      = $schedule_map[ $params['schedule_type'] ];
        $start_date   = ! empty( $params['start_date'] )
            ? gmdate( 'Y-m-d H:i:s', strtotime( $params['start_date'] ) )
            : gmdate( 'Y-m-d H:i:s' );

        $valid_statuses = [ 'active', 'pending', 'on-hold' ];
        $sub_status     = in_array( $params['status'], $valid_statuses, true ) ? $params['status'] : 'active';

        /* ── Resolve custom days early (needed for validation + note) ── */
        $custom_days = null;

        if ( $params['schedule_type'] === 'weekend' ) {
            $custom_days = [ 0, 6 ]; // Sun=0, Sat=6

        } elseif ( $params['schedule_type'] === 'custom' ) {
            $custom_days = array_values( array_unique(
                array_filter( $params['custom_days'], function ( $d ) {
                    return $d >= 0 && $d <= 6;
                } )
            ) );
            if ( empty( $custom_days ) ) {
                return new WP_Error( 'invalid_days', 'Please select at least one day for custom schedule.' );
            }
        }

        /* ── Build human-readable schedule label ──────────────────── */
        $day_names = [ 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday' ];

        $schedule_labels = [
            'daily'     => 'Every Day',
            'alternate' => 'Alternative Day (Every 2 Days)',
            'weekend'   => 'Every Weekend (Saturday & Sunday)',
            'custom'    => 'Custom: ' . implode( ', ', array_map( function ( $d ) use ( $day_names ) {
                return $day_names[ $d ] ?? $d;
            }, $custom_days ?: [] ) ),
        ];
        $schedule_label = $schedule_labels[ $params['schedule_type'] ] ?? $params['schedule_type'];

        /* ── 1. Create the parent order ───────────────────────────── */
        $order = wc_create_order( [
            'customer_id' => $params['customer_id'],
            'created_via' => 'wcfm',
        ] );

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        // Start from the customer's saved address, then let the request override it.
        $customer = new WC_Customer( $params['customer_id'] );

        $billing_address = $this->merge_address( [
            'first_name' => $customer->get_billing_first_name(),
            'last_name'  => $customer->get_billing_last_name(),
            'company'    => $customer->get_billing_company(),
            'email'      => $customer->get_billing_email(),
            'phone'      => $customer->get_billing_phone(),
            'address_1'  => $customer->get_billing_address_1(),
            'address_2'  => $customer->get_billing_address_2(),
            'city'       => $customer->get_billing_city(),
            'state'      => $customer->get_billing_state(),
            'postcode'   => $customer->get_billing_postcode(),
            'country'    => $customer->get_billing_country(),
        ], $params['billing'] ?? [] );

        if ( is_wp_error( $billing_address ) ) {
            $order->delete( true );
            return $billing_address;
        }

        // No explicit shipping address: mirror billing when one was supplied, since these are
        // delivered goods and a request-supplied billing address is the delivery address.
        $shipping_override = $params['shipping'] ?? [];
        if ( empty( $shipping_override ) && ! empty( $params['billing'] ) ) {
            $shipping_override = $params['billing'];
        }

        $shipping_address = $this->merge_address( [
            'first_name' => $customer->get_shipping_first_name(),
            'last_name'  => $customer->get_shipping_last_name(),
            'company'    => $customer->get_shipping_company(),
            'address_1'  => $customer->get_shipping_address_1(),
            'address_2'  => $customer->get_shipping_address_2(),
            'city'       => $customer->get_shipping_city(),
            'state'      => $customer->get_shipping_state(),
            'postcode'   => $customer->get_shipping_postcode(),
            'country'    => $customer->get_shipping_country(),
        ], $shipping_override );

        if ( is_wp_error( $shipping_address ) ) {
            $order->delete( true );
            return $shipping_address;
        }

        $order->set_address( $billing_address, 'billing' );
        $order->set_address( $shipping_address, 'shipping' );

        foreach ( $items as $item ) {
            $order->add_product( $item['product'], $item['quantity'] );
        }

        if ( ! empty( $params['payment_method'] ) ) {
            $gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : [];
            if ( isset( $gateways[ $params['payment_method'] ] ) ) {
                $order->set_payment_method( $gateways[ $params['payment_method'] ] );
            } else {
                $order->set_payment_method( $params['payment_method'] );
            }
        }

        $order->calculate_totals();

        // Store delivery schedule on order
        update_post_meta( $order->get_id(), '_wcfm_delivery_schedule', $params['schedule_type'] );
        if ( $custom_days !== null ) {
            update_post_meta( $order->get_id(), '_wcfm_delivery_days', $custom_days );
        }

        $order_status_map = [
            'active'  => 'processing',
            'pending' => 'pending',
            'on-hold' => 'on-hold',
        ];
        $order->update_status(
            $order_status_map[ $sub_status ] ?? 'processing',
            sprintf( __( 'Subscription order created via WCFM. Delivery Schedule: %s', 'wc-frontend-manager-ultimate' ), $schedule_label ),
            true
        );
        $order->save();

        /* ── 2. Create subscription linked to the parent order ────── */
        $subscription = wcs_create_subscription( [
            'order_id'         => $order->get_id(),
            'customer_id'      => $params['customer_id'],
            'billing_period'   => $billing['period'],
            'billing_interval' => $billing['interval'],
            'status'           => 'pending',
            'start_date'       => $start_date,
            'created_via'      => 'wcfm',
        ] );

        if ( is_wp_error( $subscription ) ) {
            $order->delete( true );
            return $subscription;
        }

        foreach ( $items as $item ) {
            $subscription->add_product( $item['product'], $item['quantity'] );
        }

        // Renewal orders inherit the address from the subscription, not the parent order.
        $subscription->set_address( $billing_address, 'billing' );
        $subscription->set_address( $shipping_address, 'shipping' );

        $subscription->calculate_totals();

        if ( ! empty( $params['payment_method'] ) ) {
            $subscription->set_payment_method( $params['payment_method'] );
        }

        /* ── 3. Schedule meta on subscription (all types) ─────────── */
        update_post_meta( $subscription->get_id(), '_wcfm_delivery_schedule', $params['schedule_type'] );

        if ( $custom_days !== null ) {
            update_post_meta( $subscription->get_id(), '_wcfm_delivery_days', $custom_days );
        }

        /* ── 4. Activate + set next_payment ──────────────────────── */
        $subscription->update_status( $sub_status );

        if ( $custom_days !== null ) {
            $next = self::get_next_delivery_date( $custom_days, $start_date );
            if ( $next ) {
                $subscription->update_dates( [ 'next_payment' => $next ] );
            }
        }

        $subscription->save();

        $line_items = [];
        foreach ( $items as $item ) {
            $line_items[] = [
                'product_id' => $item['product']->get_id(),
                'name'       => $item['product']->get_name(),
                'quantity'   => $item['quantity'],
            ];
        }

        return [
            'id'             => $subscription->get_id(),
            'order_id'       => $order->get_id(),
            'line_items'     => $line_items,
            'schedule'       => $schedule_label,
            'status'         => $subscription->get_status(),
            'total'          => $subscription->get_total(),
            'next_payment'   => $subscription->get_date( 'next_payment' ),
            'edit_url'       => get_wcfm_subscriptions_manage_url( $subscription->get_id() ),
        ];
    }

    /* ── Post-renewal: recalculate next_payment ───────────────── */

    public function update_next_payment_for_custom_schedule( $subscription ) {
        $schedule = get_post_meta( $subscription->get_id(), '_wcfm_delivery_schedule', true );
        if ( ! in_array( $schedule, [ 'weekend', 'custom' ], true ) ) {
            return;
        }

        $days = (array) get_post_meta( $subscription->get_id(), '_wcfm_delivery_days', true );
        if ( empty( $days ) ) {
            return;
        }

        $next = self::get_next_delivery_date( $days );
        if ( $next ) {
            $subscription->update_dates( [ 'next_payment' => $next ] );
            $subscription->save();
        }
    }

    /* ── Next delivery date calculator ───────────────────────── */

    public static function get_next_delivery_date( array $days_of_week, $from = null ) {
        if ( empty( $days_of_week ) ) {
            return null;
        }
        $dt = new DateTime( $from ?: 'now', new DateTimeZone( 'UTC' ) );
        $dt->modify( '+1 day' );
        $dt->setTime( 0, 0, 0 );

        for ( $i = 0; $i < 8; $i++ ) {
            if ( in_array( (int) $dt->format( 'w' ), $days_of_week, true ) ) {
                return $dt->format( 'Y-m-d H:i:s' );
            }
            $dt->modify( '+1 day' );
        }

        return null;
    }

    /* ── AJAX — Product search ────────────────────────────────── */

    public function ajax_search_products() {
        check_ajax_referer( 'wcfm_sub_create_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $search = sanitize_text_field( $_POST['q'] ?? '' );

        $posts = get_posts( [
            'post_type'      => [ 'product', 'product_variation' ],
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            's'              => $search,
        ] );

        $results = [];
        foreach ( $posts as $p ) {
            $product = wc_get_product( $p->ID );
            $price   = $product ? wc_price( $product->get_price() ) : '';
            $results[] = [
                'id'   => $p->ID,
                'text' => $p->post_title . ' (#' . $p->ID . ')' . ( $price ? ' — ' . wp_strip_all_tags( $price ) : '' ),
            ];
        }

        wp_send_json( [ 'results' => $results ] );
    }

    /* ── AJAX — Customer search ───────────────────────────────── */

    public function ajax_search_customers() {
        check_ajax_referer( 'wcfm_sub_create_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $search = sanitize_text_field( $_POST['q'] ?? '' );

        $users = get_users( [
            'search'         => '*' . $search . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'fields'         => [ 'ID', 'display_name', 'user_email' ],
            'number'         => 30,
        ] );

        $results = [];
        foreach ( $users as $u ) {
            $results[] = [
                'id'   => $u->ID,
                'text' => $u->display_name . ' (' . $u->user_email . ')',
            ];
        }

        wp_send_json( [ 'results' => $results ] );
    }

    /* ── Post-renewal: advance delivery dates ────────────────────── */

    public function update_next_payment_after_renewal( $subscription ) {
        $sub_id = $subscription->get_id();
        $saved  = json_decode( get_post_meta($sub_id, '_wcfm_sub_delivery_dates', true), true );
        if ( ! is_array($saved) || empty($saved) ) {
            return;
        }

        $today   = date('Y-m-d');
        $remaining = array_values( array_filter($saved, function($d) use ($today) { return $d > $today; }) );

        update_post_meta( $sub_id, '_wcfm_sub_delivery_dates', wp_json_encode($remaining) );

        if ( ! empty($remaining) && $subscription->can_date_be_updated('next_payment') ) {
            $subscription->update_dates([ 'next_payment' => $remaining[0] . ' 00:00:00' ]);
        }
    }

} // end class WCFMu_Subscription_Create
