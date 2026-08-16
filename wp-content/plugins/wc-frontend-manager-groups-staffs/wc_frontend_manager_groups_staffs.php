<?php
/**
 * Plugin Name: WCFM - WooCommerce Frontend Manager - Groups & Staffs
 * Plugin URI: https://wclovers.com
 * Description: Now categorize your Store Vendors and control Shop Managers & Staffs. Smartly and Peacfully.
 * Author: WC Lovers
 * Version: 3.4.10
 * Author URI: https://wclovers.com
 *
 * Text Domain: wc-frontend-manager-groups-staffs
 * Domain Path: /lang/
 *
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

if ( ! class_exists( 'WCFMgs_Dependencies' ) )
	require_once 'helpers/class-wcfmgs-dependencies.php';

require_once 'helpers/wcfmgs-core-functions.php';
require_once 'wc_frontend_manager_groups_staffs_config.php';

if(!defined('WCFMgs_TOKEN')) exit;
if(!defined('WCFMgs_TEXT_DOMAIN')) exit;


if(!WCFMgs_Dependencies::woocommerce_plugin_active_check()) {
	add_action( 'admin_notices', 'wcfmgs_woocommerce_inactive_notice' );
} else {

	if(!WCFMgs_Dependencies::wcfm_plugin_active_check()) {
		add_action( 'admin_notices', 'wcfmgs_wcfm_inactive_notice' );
	} else {
		if(!class_exists('WCFMgs')) {
			include_once( 'core/class-wcfmgs.php' );
			global $WCFMgs;
			$WCFMgs = new WCFMgs( __FILE__ );
			$GLOBALS['WCFMgs'] = $WCFMgs;
			
			// Activation Hooks
			register_activation_hook( __FILE__, array('WCFMgs', 'activate_wcfmgs') );
			register_activation_hook( __FILE__, 'flush_rewrite_rules' );
			
			// Deactivation Hooks
			register_deactivation_hook( __FILE__, array('WCFMgs', 'deactivate_wcfmgs') );

			/**
             * 	Declaring WooCommerce High-Performance Order Storage(HPOS) compatibility
             */
			add_action( 'before_woocommerce_init', function() {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			} );

		}
	}
}
?>