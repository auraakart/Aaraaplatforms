<?php
/**
 * Read-only REST API for delivery schedule options.
 *
 * Exposes the subscription delivery schedule types (Every Day / Alternate /
 * Weekend / Custom) and the weekday map used by {@see Subscription_Delivery},
 * so the storefront/app can render a schedule picker that matches exactly what
 * the admin metabox and WCFM Ultimate accept.
 *
 * Endpoint:
 *   GET /wp-json/delivery-schedule
 *
 * Public read-only, matching the sibling {@see Delivery_Slots_API}: these are
 * non-sensitive delivery-configuration options.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery schedule options endpoint.
 */
class Delivery_Schedule_API {

	const REST_NS = 'delivery-schedule';

	/**
	 * Schedule types that require the customer to pick weekdays.
	 *
	 * @var string[]
	 */
	const CUSTOM_DAY_TYPES = array( 'custom' );

	/**
	 * Fixed weekdays for a given schedule type (PHP `w`, 0 = Sunday).
	 *
	 * @var array<string,int[]>
	 */
	const FIXED_DAYS = array(
		'weekend' => array( 6, 0 ),
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the listing route at the bare namespace root so it serves at
	 * /wp-json/delivery-schedule.
	 *
	 * @return void
	 */
	public function register_routes() {
		add_filter( 'rest_endpoints', array( $this, 'alias_base_route' ) );

		register_rest_route(
			self::REST_NS,
			'/',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_schedules' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Serve the listing at the bare namespace root (with or without a trailing
	 * slash), the same way {@see Delivery_Slots_API::alias_base_route()} does.
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

	/**
	 * Return the schedule types and weekday map as a JSON list.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function list_schedules( \WP_REST_Request $request ) {
		$payload = self::schedule_payload();

		return new \WP_REST_Response(
			array(
				'success'   => true,
				'count'     => count( $payload['schedules'] ),
				'schedules' => $payload['schedules'],
				'weekdays'  => $payload['weekdays'],
			),
			200
		);
	}

	/**
	 * Build the schedule types and weekday map. Static so callers outside the
	 * endpoint (e.g. the wc/v3/products response wrapper) can reuse it.
	 *
	 * @return array{schedules:array<int,array<string,mixed>>,weekdays:array<int,array<string,mixed>>}
	 */
	public static function schedule_payload() {
		$schedules = array();
		foreach ( Subscription_Delivery::schedule_types() as $key => $label ) {
			$fixed       = isset( self::FIXED_DAYS[ $key ] ) ? self::FIXED_DAYS[ $key ] : array();
			$schedules[] = array(
				'key'           => (string) $key,
				'label'         => (string) $label,
				'requires_days' => in_array( $key, self::CUSTOM_DAY_TYPES, true ),
				'days'          => array_map( 'intval', $fixed ),
			);
		}

		$weekdays = array();
		foreach ( Subscription_Delivery::weekdays() as $value => $name ) {
			$weekdays[] = array(
				'value' => (int) $value,
				'label' => (string) $name,
			);
		}

		return array(
			'schedules' => $schedules,
			'weekdays'  => $weekdays,
		);
	}
}
