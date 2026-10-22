<?php
/**
 * Plugin Name: Aaraa Vendor Hub
 * Description: Central vendor directory admin (wp_vendors) — app-config settings page per brand, and the GET /wp-json/aaraakart/v1/app-config API the mobile app reads on startup. Install this on the CENTRAL aaraakart.com hub site whose own WordPress database IS `admin_aaraakart_maindb` — not on any individual vendor's own site.
 * Version: 1.0.0
 * Author: Aaraa
 * Text Domain: aaraa-vendor-hub
 *
 * @package Aaraa\VendorHub
 */

namespace Aaraa\VendorHub;

defined( 'ABSPATH' ) || exit;

define( 'AARAA_VH_VERSION', '1.0.0' );
define( 'AARAA_VH_DIR', plugin_dir_path( __FILE__ ) );
define( 'AARAA_VH_URL', plugin_dir_url( __FILE__ ) );
define( 'AARAA_VH_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4-style autoloader for the Aaraa\VendorHub namespace.
 *
 * Maps Aaraa\VendorHub\Vendor_Model to includes/class-vendor-model.php.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Aaraa\\VendorHub\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$relative = strtolower( str_replace( array( '_', '\\' ), array( '-', '/' ), $relative ) );
		$file     = AARAA_VH_DIR . 'includes/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance();
	}
);

register_activation_hook( __FILE__, array( '\\Aaraa\\VendorHub\\Vendor_Model', 'migrate' ) );
