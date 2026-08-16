<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function wallet_system_for_woocommerce_pro_deactivate() {
		wp_clear_scheduled_hook( 'wps_wsfwp_check_license_daily' );
	}
}
