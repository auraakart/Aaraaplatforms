<?php
/**
 * Subscription REST API (create / pause / resume).
 *
 * The routes live here — in our own plugin, under the bare `subscriptions`
 * namespace, matching the project's other custom APIs — so plugin updates to
 * WC Frontend Manager Ultimate cannot remove the API surface.
 *
 * The handlers themselves remain in WCFM Ultimate's
 * {@see WCFMu_Subscription_Create} (they are tightly coupled to that plugin's
 * subscription-creation helpers and auto-resume cron), so this module simply
 * registers the routes and delegates to that controller instance.
 *
 * Endpoints:
 *   POST /wp-json/subscriptions               → create a subscription (admin)
 *   POST /wp-json/subscriptions/{id}/pause    → pause for given dates
 *   POST /wp-json/subscriptions/{id}/resume   → resume (wallet-checked)
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the subscription API routes and delegates to WCFM's handlers.
 */
class Subscription_API {

	const REST_NS = 'subscriptions';

	// Where the pause type ('temporary' | 'permanent') is stored per subscription.
	const META_PAUSE_TYPE = '_aaraa_pause_type';

	/**
	 * Normalise a pause_type value to 'temporary' or 'permanent'.
	 *
	 * @param mixed $value Raw request value.
	 * @return string 'temporary' (default) or 'permanent'.
	 */
	public static function sanitize_pause_type( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return ( 'permanent' === $value ) ? 'permanent' : 'temporary';
	}

	/**
	 * The stored pause type for a subscription (default 'temporary').
	 *
	 * @param int $sub_id Subscription id.
	 * @return string
	 */
	public static function get_pause_type( $sub_id ) {
		$stored = (string) get_post_meta( (int) $sub_id, self::META_PAUSE_TYPE, true );
		return ( 'permanent' === $stored ) ? 'permanent' : 'temporary';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Enrich WooCommerce's own subscriptions REST response
		// (GET /wp-json/wc/v3/subscriptions?customer=<id> and single item)
		// with pause + delivery information, so consumers using the standard
		// endpoint also receive is_paused / pause_dates / resume_date and the
		// delivery slot + schedule.
		add_filter( 'woocommerce_rest_prepare_shop_subscription_object', array( $this, 'add_delivery_and_pause_data' ), 10, 3 );
	}

	/**
	 * Append pause + delivery fields to the standard WC subscriptions REST response.
	 *
	 * Fires for every subscription the wc/v3 controller prepares — the list
	 * endpoint (`?customer=<id>`) and the single-subscription endpoint alike.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param mixed             $object   The WC_Subscription (or post) being prepared.
	 * @param \WP_REST_Request  $request  The request.
	 * @return \WP_REST_Response
	 */
	public function add_delivery_and_pause_data( $response, $object, $request ) {
		if ( ! ( $response instanceof \WP_REST_Response ) ) {
			return $response;
		}

		$sub_id = 0;
		if ( is_object( $object ) && method_exists( $object, 'get_id' ) ) {
			$sub_id = (int) $object->get_id();
		} elseif ( is_object( $object ) && isset( $object->ID ) ) {
			$sub_id = (int) $object->ID;
		}
		if ( ! $sub_id && isset( $response->data['id'] ) ) {
			$sub_id = (int) $response->data['id'];
		}
		if ( ! $sub_id ) {
			return $response;
		}

		/* Pause data. */
		$pause_dates = self::parse_pause_dates( get_post_meta( $sub_id, '_wcfmu_pause_dates', true ) );
		$resume_date = (string) get_post_meta( $sub_id, '_wcfmu_pause_resume', true );

		$status = isset( $response->data['status'] ) ? $response->data['status'] : '';
		if ( '' === $status && is_object( $object ) && method_exists( $object, 'get_status' ) ) {
			$status = $object->get_status();
		}

		$response->data['is_paused']   = ( 'pause' === $status );
		$response->data['pause_dates'] = $pause_dates;
		$response->data['resume_date'] = '' !== $resume_date ? $resume_date : null;
		$response->data['pause_type']  = self::get_pause_type( $sub_id );

		/* Delivery slot + schedule. */
		$response->data['delivery_slot']     = $this->subscription_delivery_slot( $sub_id, $object );
		$response->data['delivery_schedule'] = $this->subscription_delivery_schedule( $sub_id, $object );

		return $response;
	}

	/**
	 * Normalise a stored pause-dates value into a clean list of Y-m-d strings.
	 *
	 * get_post_meta() auto-unserializes, so a value saved as a PHP array comes
	 * back as an array (json_decode on which yields null). Handle arrays, JSON
	 * strings and comma-separated strings alike.
	 *
	 * @param mixed $raw Stored value.
	 * @return string[]
	 */
	public static function parse_pause_dates( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}
		if ( is_array( $raw ) ) {
			$list = $raw;
		} else {
			$decoded = json_decode( (string) $raw, true );
			$list    = is_array( $decoded ) ? $decoded : explode( ',', (string) $raw );
		}
		$out = array();
		foreach ( $list as $d ) {
			$d = trim( (string) $d );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
				$out[] = $d;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Read a subscription meta value HPOS-safely: prefer the CRUD object's meta
	 * (the canonical/HPOS store), fall back to post_meta (legacy writers).
	 *
	 * @param int    $sub_id Subscription ID.
	 * @param mixed  $object The WC_Subscription object, if available.
	 * @param string $key    Meta key.
	 * @return mixed
	 */
	private function read_meta( $sub_id, $object, $key ) {
		if ( is_object( $object ) && method_exists( $object, 'get_meta' ) ) {
			$val = $object->get_meta( $key );
			if ( '' !== $val && null !== $val && array() !== $val ) {
				return $val;
			}
		}
		return get_post_meta( $sub_id, $key, true );
	}

	/**
	 * The subscription's delivery slot, resolved to readable details.
	 *
	 * @param int   $sub_id Subscription ID.
	 * @param mixed $object The WC_Subscription object, if available.
	 * @return array<string,mixed>|null Slot details, or null when none set.
	 */
	private function subscription_delivery_slot( $sub_id, $object = null ) {
		$slot_id = (int) $this->read_meta( $sub_id, $object, '_aaraa_delivery_slot' );
		if ( ! $slot_id ) {
			return null;
		}
		if ( class_exists( '\Aaraa\Admin\Delivery_Slots_API' ) ) {
			$slot = Delivery_Slots_API::slot_by_id( $slot_id );
			if ( $slot ) {
				return $slot;
			}
		}
		return array( 'id' => $slot_id );
	}

	/**
	 * The subscription's delivery schedule (type + days), with readable labels.
	 *
	 * @param int   $sub_id Subscription ID.
	 * @param mixed $object The WC_Subscription object, if available.
	 * @return array<string,mixed>|null Schedule details, or null when none set.
	 */
	private function subscription_delivery_schedule( $sub_id, $object = null ) {
		$type = (string) $this->read_meta( $sub_id, $object, '_wcfm_delivery_schedule' );
		if ( '' === $type ) {
			return null;
		}

		$type_labels = class_exists( '\Aaraa\Admin\Subscription_Delivery' )
			? Subscription_Delivery::schedule_types()
			: array();
		$day_labels  = class_exists( '\Aaraa\Admin\Subscription_Delivery' )
			? Subscription_Delivery::weekdays()
			: array();

		$days = $this->read_meta( $sub_id, $object, '_wcfm_delivery_days' );
		$days = is_array( $days ) ? array_values( array_map( 'absint', $days ) ) : array();

		$day_names = array();
		foreach ( $days as $d ) {
			if ( isset( $day_labels[ $d ] ) ) {
				$day_names[] = $day_labels[ $d ];
			}
		}

		return array(
			'type'      => $type,
			'label'     => isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type,
			'days'      => $days,
			'day_names' => $day_names,
		);
	}

	/**
	 * The WCFM subscription controller instance that owns the handlers.
	 *
	 * @return \WCFMu_Subscription_Create|null
	 */
	private function handler() {
		if ( class_exists( '\WCFMu_Subscription_Create' ) && method_exists( '\WCFMu_Subscription_Create', 'instance' ) ) {
			return \WCFMu_Subscription_Create::instance();
		}
		return null;
	}

	/**
	 * Register the routes under the bare `subscriptions` namespace.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = self::REST_NS;

		add_filter( 'rest_endpoints', array( $this, 'alias_base_route' ) );

		// /wp-json/subscriptions
		//   GET  — list a customer's subscriptions (incl. pause dates)
		//   POST — create (product_ids + delivery slot/schedule)
		register_rest_route(
			$ns,
			'/',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_subscriptions' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'customer_id' => array( 'required' => true, 'type' => 'integer' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Pause — POST /wp-json/subscriptions/pause
		// subscription_id + customer_id (both mandatory) travel in the body.
		register_rest_route(
			$ns,
			'/pause',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pause' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'subscription_id' => array( 'required' => true, 'type' => 'integer' ),
					'pause_dates'     => array( 'required' => true ),
					'customer_id'     => array( 'required' => true, 'type' => 'integer' ),
					'pause_type'      => array(
						'required' => false,
						'type'     => 'string',
						'enum'     => array( 'temporary', 'permanent' ),
						'default'  => 'temporary',
					),
				),
			)
		);

		// Resume — POST /wp-json/subscriptions/resume
		register_rest_route(
			$ns,
			'/resume',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resume' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'subscription_id' => array( 'required' => true, 'type' => 'integer' ),
					'customer_id'     => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);
	}

	/**
	 * Serve the create handler at the bare namespace root (/wp-json/subscriptions),
	 * with or without a trailing slash.
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

	/* --------------------------------------------------------------------- *
	 * Ownership check (customer_id based).
	 * --------------------------------------------------------------------- */

	/**
	 * Require a customer_id that owns the target subscription.
	 *
	 * The app authenticates by customer_id rather than a WordPress session, so
	 * pause/resume are gated here instead of via is_user_logged_in().
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	/**
	 * Copy the body's subscription_id onto the `id` param that the ownership
	 * check and WCFM handlers read (they were written for a URL {id} segment).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return void
	 */
	private function normalise_id( \WP_REST_Request $request ) {
		$sub_id = absint( $request->get_param( 'subscription_id' ) );
		if ( ! $sub_id ) {
			$sub_id = absint( $request->get_param( 'id' ) );
		}
		$request->set_param( 'id', $sub_id );
	}

	private function assert_owner( \WP_REST_Request $request ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return $this->unavailable();
		}

		$customer_id = absint( $request->get_param( 'customer_id' ) );
		if ( ! $customer_id ) {
			return new \WP_Error( 'missing_customer_id', __( 'customer_id is required.', 'aaraa-white-label-admin' ), array( 'status' => 400 ) );
		}

		$sub_id       = absint( $request->get_param( 'id' ) );
		$subscription = wcs_get_subscription( $sub_id );
		if ( ! $subscription ) {
			return new \WP_Error( 'not_found', __( 'Subscription not found.', 'aaraa-white-label-admin' ), array( 'status' => 404 ) );
		}

		if ( (int) $subscription->get_user_id() !== $customer_id ) {
			return new \WP_Error( 'forbidden', __( 'Subscription does not belong to that customer.', 'aaraa-white-label-admin' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/* --------------------------------------------------------------------- *
	 * Route callbacks (delegate to WCFM's handlers).
	 * --------------------------------------------------------------------- */

	/**
	 * List a customer's subscriptions, including any pause dates.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_subscriptions( \WP_REST_Request $request ) {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return $this->unavailable();
		}

		$customer_id = absint( $request->get_param( 'customer_id' ) );
		if ( ! $customer_id ) {
			return new \WP_Error( 'missing_customer_id', __( 'customer_id is required.', 'aaraa-white-label-admin' ), array( 'status' => 400 ) );
		}

		$out = array();
		foreach ( wcs_get_users_subscriptions( $customer_id ) as $sub ) {
			if ( ! is_a( $sub, 'WC_Subscription' ) ) {
				continue;
			}
			$sub_id = $sub->get_id();

			$pause_dates = self::parse_pause_dates( get_post_meta( $sub_id, '_wcfmu_pause_dates', true ) );
			$resume_date = (string) get_post_meta( $sub_id, '_wcfmu_pause_resume', true );

			$items = array();
			foreach ( $sub->get_items() as $item ) {
				$items[] = array(
					'product_id' => $item->get_product_id(),
					'name'       => $item->get_name(),
					'quantity'   => $item->get_quantity(),
					'total'      => wc_format_decimal( $item->get_total(), wc_get_price_decimals() ),
				);
			}

			$out[] = array(
				'id'               => $sub_id,
				'status'           => $sub->get_status(),
				'total'            => wc_format_decimal( $sub->get_total(), wc_get_price_decimals() ),
				'currency'         => $sub->get_currency(),
				'billing_period'   => $sub->get_billing_period(),
				'billing_interval' => (int) $sub->get_billing_interval(),
				'start_date'       => $sub->get_date( 'start' ),
				'next_payment'     => $sub->get_date( 'next_payment' ),
				'end_date'         => $sub->get_date( 'end' ),
				'is_paused'        => 'pause' === $sub->get_status(),
				'pause_dates'      => $pause_dates,
				'resume_date'      => '' !== $resume_date ? $resume_date : null,
				'pause_type'       => self::get_pause_type( $sub_id ),
				'items'            => $items,
			);
		}

		return new \WP_REST_Response(
			array(
				'success'       => true,
				'customer_id'   => $customer_id,
				'count'         => count( $out ),
				'subscriptions' => $out,
			),
			200
		);
	}

	/**
	 * Create a subscription.
	 *
	 * Prefers this project's multi-product creator
	 * (create_custom_subscription_with_multiple_products: product_ids + delivery
	 * slot/schedule). Falls back to WCFM's create handler if that is unavailable.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return mixed
	 */
	public function create( \WP_REST_Request $request ) {
		if ( function_exists( 'create_custom_subscription_with_multiple_products' ) ) {
			return create_custom_subscription_with_multiple_products( $request );
		}
		$handler = $this->handler();
		if ( ! $handler ) {
			return $this->unavailable();
		}
		return $handler->rest_create_subscription( $request );
	}

	/**
	 * Pause a subscription.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function pause( \WP_REST_Request $request ) {
		$this->normalise_id( $request );
		$owner = $this->assert_owner( $request );
		if ( is_wp_error( $owner ) ) {
			return $owner;
		}
		$handler = $this->handler();
		if ( ! $handler ) {
			return $this->unavailable();
		}
		try {
			$response = $handler->rest_pause_subscription( $request );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'pause_failed', $e->getMessage(), array( 'status' => 409 ) );
		}

		// Persist the pause type and echo it back on a successful pause.
		$pause_type = self::sanitize_pause_type( $request->get_param( 'pause_type' ) );
		if ( $response instanceof \WP_REST_Response && $response->get_status() >= 200 && $response->get_status() < 300 ) {
			$sub_id = absint( $request->get_param( 'id' ) );
			if ( $sub_id ) {
				update_post_meta( $sub_id, self::META_PAUSE_TYPE, $pause_type );
			}
			$data = $response->get_data();
			if ( is_array( $data ) ) {
				$data['pause_type'] = $pause_type;
				$response->set_data( $data );
			}
		}

		return $response;
	}

	/**
	 * Resume a subscription.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resume( \WP_REST_Request $request ) {
		$this->normalise_id( $request );
		$owner = $this->assert_owner( $request );
		if ( is_wp_error( $owner ) ) {
			return $owner;
		}
		$handler = $this->handler();
		if ( ! $handler ) {
			return $this->unavailable();
		}
		try {
			return $handler->rest_resume_subscription( $request );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'resume_failed', $e->getMessage(), array( 'status' => 409 ) );
		}
	}

	/**
	 * Error returned when WCFM Ultimate (and its subscription handlers) is not available.
	 *
	 * @return \WP_Error
	 */
	private function unavailable() {
		return new \WP_Error(
			'subscription_api_unavailable',
			__( 'The subscription service is not available.', 'aaraa-white-label-admin' ),
			array( 'status' => 503 )
		);
	}
}
