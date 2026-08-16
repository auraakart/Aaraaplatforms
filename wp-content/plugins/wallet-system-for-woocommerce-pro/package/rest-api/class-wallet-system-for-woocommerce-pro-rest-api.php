<?php
/**
 * The file that defines the core plugin api class
 *
 * A class definition that includes api's endpoints and functions used across the plugin
 *
 * @link        https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/package/rest-api/version1
 */

/**
 * The core plugin  api class.
 *
 * This is used to define internationalization, api-specific hooks, and
 * endpoints for plugin.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/package/rest-api/version1
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro_Rest_Api {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin api.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the merthods, and set the hooks for the api and
	 *
	 * @since    1.0.0
	 * @param   string $plugin_name    Name of the plugin.
	 * @param   string $version        Version of the plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}


	/**
	 * Define endpoints for the plugin.
	 *
	 * Uses the Wallet_System_For_Woocommerce_Pro_Rest_Api class in order to create the endpoint
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	public function wps_wsfwp_add_endpoint() {
		register_rest_route(
			'wws-route/v1',
			'/wws-dummy-data/',
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'wps_wsfwp_default_callback' ),
				'permission_callback' => array( $this, 'wps_wsfwp_default_permission_check' ),
			)
		);
	}


	/**
	 * Begins validation process of api endpoint.
	 *
	 * @param   Array $request    All information related with the api request containing in this array.
	 * @return  Array   $result   return rest response to server from where the endpoint hits.
	 * @since    1.0.0
	 */
	public function wps_wsfwp_default_permission_check( $request ) {

		// Add rest api validation for each request.
		$result = true;
		return $result;
	}


	/**
	 * Begins execution of api endpoint.
	 *
	 * @param   Array $request    All information related with the api request containing in this array.
	 * @return  Array   $wps_wsfwp_response   return rest response to server from where the endpoint hits.
	 * @since    1.0.0
	 */
	public function wps_wsfwp_default_callback( $request ) {

		require_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'package/rest-api/version1/class-wallet-system-for-woocommerce-pro-api-process.php';
		$wps_wsfwp_api_obj = new Wallet_System_For_Woocommerce_Pro_Api_Process();
		$wps_wsfwp_resultsdata = $wps_wsfwp_api_obj->wps_wsfwp_default_process( $request );
		if ( is_array( $wps_wsfwp_resultsdata ) && isset( $wps_wsfwp_resultsdata['status'] ) && 200 == $wps_wsfwp_resultsdata['status'] ) {
			unset( $wps_wsfwp_resultsdata['status'] );
			$wps_wsfwp_response = new WP_REST_Response( $wps_wsfwp_resultsdata, 200 );
		} else {
			$wps_wsfwp_response = new WP_Error( $wps_wsfwp_resultsdata );
		}
		return $wps_wsfwp_response;
	}
}
