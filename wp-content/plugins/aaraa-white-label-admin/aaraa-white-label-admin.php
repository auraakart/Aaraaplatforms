<?php
/**
 * Plugin Name:       Aaraa White Label Admin
 * Plugin URI:        https://aaraakart.com/
 * Description:       White-labels the WordPress admin — custom admin & login URLs, an Aaraa SaaS admin theme, login branding, dashboard widgets, and security hardening. WooCommerce & HPOS compatible.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Aaraa Platforms
 * Author URI:        https://aaraakart.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aaraa-white-label-admin
 * Domain Path:       /languages
 * Network:           true
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

define( 'AARAA_WLA_VERSION', '1.0.0' );
define( 'AARAA_WLA_FILE', __FILE__ );
define( 'AARAA_WLA_DIR', plugin_dir_path( __FILE__ ) );
define( 'AARAA_WLA_URL', plugin_dir_url( __FILE__ ) );
define( 'AARAA_WLA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4-style autoloader for the Aaraa\Admin namespace.
 *
 * Maps Aaraa\Admin\Admin_URL_Rewriter to includes/class-admin-url-rewriter.php.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Aaraa\\Admin\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$relative = strtolower( str_replace( array( '_', '\\' ), array( '-', '/' ), $relative ) );
		$file     = AARAA_WLA_DIR . 'includes/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// Procedural helpers (settings accessors, option schema).
require_once AARAA_WLA_DIR . 'includes/helpers.php';

// Lifecycle hooks.
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'deactivate' ) );

/**
 * Declare High-Performance Order Storage (HPOS) compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', AARAA_WLA_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', AARAA_WLA_FILE, true );
		}
	}
);

/*
 * Boot immediately (not on plugins_loaded) so the URL rewriter can register its
 * own plugins_loaded:1 request interceptor before that action fires. Plugin files
 * are included at wp-settings.php:573, before plugins_loaded at :622.
 */
Plugin::instance();
