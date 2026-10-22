<?php
/**
 * GET /wp-json/aaraakart/v1/app-config?brand_id={brandId}
 *
 * @package Aaraa\VendorHub
 */

namespace Aaraa\VendorHub;

defined( 'ABSPATH' ) || exit;

/**
 * The app-config REST endpoint.
 */
class Vendor_Config_API {

	const NS = 'aaraakart/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/app-config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'brand_id' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Gate on the shared App Key, when one is configured. See the "API
	 * Access" box on the Vendor App Config screen.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( $request ) {
		$app_key = get_option( Vendor_Settings_Page::API_KEY_OPTION, '' );
		if ( '' === $app_key ) {
			return true; // Not configured yet — endpoint stays open. See admin notice.
		}
		$sent = (string) $request->get_header( 'x-app-key' );
		if ( hash_equals( $app_key, $sent ) ) {
			return true;
		}
		return new \WP_Error( 'rest_forbidden', __( 'Missing or invalid X-App-Key header.', 'aaraa-vendor-hub' ), array( 'status' => 401 ) );
	}

	/**
	 * Build and return the app-config response.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle( $request ) {
		$brand_id = sanitize_text_field( (string) $request->get_param( 'brand_id' ) );
		if ( '' === $brand_id ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'brand_id is required.', 'aaraa-vendor-hub' ) ),
				400
			);
		}

		$row = Vendor_Model::get_by_subdomain( $brand_id );
		if ( ! $row ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Unknown brand_id.', 'aaraa-vendor-hub' ) ),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => Vendor_Model::to_app_config( $row ),
			),
			200
		);
	}
}
