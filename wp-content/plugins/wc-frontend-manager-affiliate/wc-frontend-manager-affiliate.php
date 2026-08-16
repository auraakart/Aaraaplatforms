<?php
/**
 * Plugin Name: WCFM - WooCommerce Frontend Manager - Affiliate
 * Plugin URI: https://wclovers.com/product/woocommerce-frontend-manager-affiliate/
 * Description: Manage your marketplace affiliate system. Easily and Smoothly.
 * Author: WC Lovers
 * Version: 1.3.0
 * Author URI: https://wclovers.com
 *
 * Text Domain: wc-frontend-manager-affiliate
 * Domain Path: /lang/
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 10.7.0
 *
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

if ( ! class_exists( 'WCFMaf_Dependencies' ) )
	require_once 'helpers/class-wcfmaf-dependencies.php';

require_once 'helpers/wcfmaf-core-functions.php';
require_once 'wc-frontend-manager-affiliate-config.php';

if(!defined('WCFMaf_TOKEN')) exit;
if(!defined('WCFMaf_TEXT_DOMAIN')) exit;


if(!WCFMaf_Dependencies::woocommerce_plugin_active_check()) {
	add_action( 'admin_notices', 'wcfmaf_woocommerce_inactive_notice' );
} else {

	if(!WCFMaf_Dependencies::wcfm_plugin_active_check()) {
		add_action( 'admin_notices', 'wcfmaf_wcfm_inactive_notice' );
	} else {
		if(!WCFMaf_Dependencies::wcfmmp_plugin_active_check()) {
			add_action( 'admin_notices', 'wcfmaf_wcfmmp_inactive_notice' );
		} else {
			if(!class_exists('WCFMaf')) {
				include_once( 'core/class-wcfmaf.php' );
				global $WCFMaf;
				$WCFMaf = new WCFMaf( __FILE__ );
				$GLOBALS['WCFMaf'] = $WCFMaf;
				
				// Activation Hooks
				register_activation_hook( __FILE__, array('wcfmaf', 'activate_wcfmaf') );
				register_activation_hook( __FILE__, 'flush_rewrite_rules' );
				
				// Deactivation Hooks
				register_deactivation_hook( __FILE__, array('wcfmaf', 'deactivate_wcfmaf') );

				/**
				 * 	Declaring WooCommerce High-Performance Order Storage(HPOS) compatibility
				 */
				add_action('before_woocommerce_init', function () {
					if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
						\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
					}
				});
			}
		}
	}
}
?>