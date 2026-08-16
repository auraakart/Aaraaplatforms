<?php
/**
 * Fired during plugin activation
 *
 * @link        https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/package/rest-api/version1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Wallet_System_For_Woocommerce_Pro_Api_Process' ) ) {

	/**
	 * The plugin API class.
	 *
	 * This is used to define the functions and data manipulation for custom endpoints.
	 *
	 * @since      1.0.0
	 * @package    Wallet_System_For_Woocommerce_Pro
	 * @subpackage Wallet_System_For_Woocommerce_Pro/package/rest-api/version1
	 * @author     WP Swings <webmaster@wpswings.com>
	 */
	class Wallet_System_For_Woocommerce_Pro_Api_Process {

		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    1.0.0
		 */
		public function __construct() {

		}

		/**
		 * Define the function to process data for custom endpoint.
		 *
		 * @since    1.0.0
		 * @param   Array $wsfwp_request  data of requesting headers and other information.
		 * @return  Array $wps_wsfwp_rest_response    returns processed data and status of operations.
		 */
		public function wps_wsfwp_default_process( $wsfwp_request ) {
			$wps_wsfwp_rest_response = array();

			// Write your custom code here.

			$wps_wsfwp_rest_response['status'] = 200;
			$wps_wsfwp_rest_response['data'] = $wsfwp_request->get_headers();
			return $wps_wsfwp_rest_response;
		}
	}
}
