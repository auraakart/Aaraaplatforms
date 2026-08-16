<?php
/**
 * Fired during plugin activation
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro_Activator {

	/**
	 * Activation function.
	 *
	 * @since    1.0.0
	 * @param boolean $network_wide networkwide activate.
	 * @return void
	 */
	public static function wallet_system_for_woocommerce_pro_activate( $network_wide ) {

		global $wpdb;
		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network and activate plugin on each one.
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::wps_wsfwp_create_wallet_page();
				self::wps_wsfwp_activated_timestamp();
				restore_current_blog();
			}
		} else {
			self::wps_wsfwp_create_wallet_page();
			self::wps_wsfwp_activated_timestamp();
		}
	}

	/**
	 * Activated timestamp.
	 *
	 * @return void
	 */
	public static function wps_wsfwp_activated_timestamp() {
		$mwb_wws_activated_timestamp = get_option( 'mwb_wws_activated_timestamp', '' );
		if ( empty( $mwb_wws_activated_timestamp ) ) {
			$timestamp = get_option( 'wps_wsfwp_activated_timestamp', 'not_set' );
			if ( 'not_set' === $timestamp ) {
				$current_time = current_time( 'timestamp' );
				$thirty_days  = strtotime( '+30 days', $current_time );
				update_option( 'wps_wsfwp_activated_timestamp', $thirty_days );
			}
		}
	}
	/**
	 * Create create wallet page on new blog creation.
	 *
	 * @return void
	 */
	public static function wps_wsfwp_create_wallet_page() {
		$mwb_wws_wallet_top_page_id = get_option( 'mwb_wws_wallet_top_page_id', '' );
		if ( empty( $mwb_wws_wallet_top_page_id ) ) {
			$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
			$page           = get_post( $wallet_page_id );
			if ( empty( $page ) ) {
				// create My Wallet name page.
				$my_wallet = array(
					'post_title'  => __( 'My Wallet', 'wallet-system-for-woocommerce-pro' ),
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_author' => 1,
				);
				$post_id   = wp_insert_post( $my_wallet );
				if ( ! is_wp_error( $post_id ) ) {
					update_option( 'wps_wsfwp_wallet_top_page_id', $post_id );
				}
			}
		}
	}
}
