<?php
/**
 * Singleton loader that wires up this plugin's two pieces: the Vendor
 * Settings admin page and the app-config REST API.
 *
 * @package Aaraa\VendorHub
 */

namespace Aaraa\VendorHub;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin bootstrap.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire modules on construction.
	 */
	private function __construct() {
		add_action( 'admin_init', array( '\\Aaraa\\VendorHub\\Vendor_Model', 'maybe_upgrade' ) );

		$settings = new Vendor_Settings_Page();
		$settings->init();

		$api = new Vendor_Config_API();
		$api->init();
	}

	/**
	 * Prevent cloning / unserialising the singleton.
	 */
	private function __clone() {}
}
