<?php
/**
 * Read-only REST API for delivery slots.
 *
 * Exposes the slots managed on the Delivery Slots admin screen
 * ({@see Delivery_Admin}) so the storefront/app can render a slot picker.
 * The data lives in `{prefix}aaraa_delivery_slots`; this module only reads it
 * through the shared {@see Delivery_Admin::slots_list()} helper.
 *
 * Endpoint:
 *   GET /wp-json/delivery-slots            → active slots
 *   GET /wp-json/delivery-slots?status=all → active + inactive
 *
 * Public read-only, matching the other app-facing endpoints in this project
 * (no consumer-key auth): slots are non-sensitive delivery-window metadata.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery slots listing endpoint.
 */
class Delivery_Slots_API {

	const REST_NS = 'delivery-slots';

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
	 * /wp-json/delivery-slots.
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
				'callback'            => array( $this, 'list_slots' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'status' => array(
						'description'       => __( 'Which slots to return: active (default), inactive, or all.', 'aaraa-white-label-admin' ),
						'type'              => 'string',
						'enum'              => array( 'active', 'inactive', 'all' ),
						'default'           => 'active',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Serve the listing at the bare namespace root.
	 *
	 * register_rest_route( ns, '/' ) keys the route as "/delivery-slots/", but
	 * WordPress untrailingslashit's the request path, so /wp-json/delivery-slots
	 * would otherwise hit the auto-generated namespace index. Copying our handler
	 * onto the bare key makes the base URL work with or without a trailing slash.
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
	 * Return the delivery slots as a JSON list.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function list_slots( \WP_REST_Request $request ) {
		$status = (string) $request->get_param( 'status' );
		$status = in_array( $status, array( 'active', 'inactive', 'all' ), true ) ? $status : 'active';

		$slots = self::slots_payload( $status );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'count'   => count( $slots ),
				'slots'   => $slots,
			),
			200
		);
	}

	/**
	 * Build the slots list, filtered by status. Static so callers outside the
	 * endpoint (e.g. the wc/v3/products response wrapper) can reuse it.
	 *
	 * @param string $status active (default), inactive, or all.
	 * @return array<int,array<string,mixed>>
	 */
	public static function slots_payload( $status = 'active' ) {
		$slots = array();
		foreach ( Delivery_Admin::slots_list() as $row ) {
			if ( 'all' !== $status && (string) $row->status !== $status ) {
				continue;
			}
			$slots[] = self::format_slot( $row );
		}
		return $slots;
	}

	/**
	 * Resolve a single slot by ID to its formatted shape.
	 *
	 * Used by callers that store only a slot ID (e.g. the subscription/order
	 * delivery meta) and need the readable slot details in a response.
	 *
	 * @param int $id Slot ID.
	 * @return array<string,mixed>|null Formatted slot, or null if not found.
	 */
	public static function slot_by_id( $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			return null;
		}
		foreach ( Delivery_Admin::slots_list() as $row ) {
			if ( (int) $row->id === $id ) {
				return self::format_slot( $row );
			}
		}
		return null;
	}

	/**
	 * Shape a raw slot row for the API response.
	 *
	 * @param object $row Row from {@see Delivery_Admin::slots_list()}.
	 * @return array<string,mixed>
	 */
	private static function format_slot( $row ) {
		$start = (string) $row->start_time;
		$end   = (string) $row->end_time;

		if ( '' !== $start && '' !== $end ) {
			$time_label = $start . ' - ' . $end;
			$label      = $row->name . ' (' . $time_label . ')';
		} else {
			$time_label = trim( $start . $end );
			$label      = '' !== $time_label ? $row->name . ' (' . $time_label . ')' : (string) $row->name;
		}

		return array(
			'id'         => (int) $row->id,
			'name'       => (string) $row->name,
			'start_time' => $start,
			'end_time'   => $end,
			'time_label' => $time_label,
			'label'      => $label,
			'status'     => (string) $row->status,
		);
	}
}
