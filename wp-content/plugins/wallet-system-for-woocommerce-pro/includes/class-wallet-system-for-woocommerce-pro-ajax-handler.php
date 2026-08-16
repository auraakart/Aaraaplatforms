<?php
/**
 * Handles all admin ajax requests.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 */

/**
 * Handles all admin ajax requests.
 *
 * All the functions required for handling admin ajax requests
 * required by the plugin.
 *
 * @since      1.0.0
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro_Ajax_Handler {

	/**
	 * Construct.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_wps_generate_qr_code', array( &$this, 'wps_generate_qr_code' ) );
	}

	/**
	 * Generates qr code image.
	 *
	 * @return void
	 */
	public function wps_generate_qr_code() {

		if ( is_user_logged_in() ) {
			$generate = true;
			$message = array();
			$user_id = get_current_user_id();
			$unique_code = rand();

			require_once plugin_dir_path( __DIR__ ) . '/WalletQrcode/qrlib.php';

			$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
			$page_url       = get_permalink( $wallet_page_id );
			if ( get_option( 'permalink_structure' ) ) {
				if ( substr( $page_url, -1 ) == '/' ) {
					$wallet_topup_link = $page_url . 'wps-wallet-topup/' . $unique_code . '/';
				} else {
					$wallet_topup_link = $page_url . '/wps-wallet-topup/' . $unique_code;
				}
			} else {
				$wallet_topup_link = add_query_arg( 'wps-wallet-topup', $unique_code, $page_url );
			}

			$upload_dir        = wp_upload_dir();
			$upload_url        = $upload_dir['subdir'] . '/wallet-qr-' . $unique_code . '.png';
			$upload_path       = $upload_dir['path'] . '/wallet-qr-' . $unique_code . '.png';
			// Generates QR Code and Stores it in directory given.
			QRcode::png( $wallet_topup_link, $upload_path );

			$wallet_qr_code = update_user_meta( $user_id, 'wallet_qr_code_image', $upload_url );
			if ( $wallet_qr_code ) {
				$message['message'] = esc_html__( 'image is saved', 'wallet-system-for-woocommerce-pro' );
				$message['type']    = $wallet_topup_link1;
			} else {
				$message['message'] = esc_html__( 'image is not saved', 'wallet-system-for-woocommerce-pro' );
				$message['type']    = esc_html__( 'woocommerce-error', 'wallet-system-for-woocommerce-pro' );
			}
			wp_send_json( $message );
			wp_die();

		}
	}
}
