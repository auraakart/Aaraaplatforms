<?php
/**
 * Main class for Cross Promotion Banners.
 *
 * @package Wt_Smart_Coupon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Current version is equal to the latest version and class does not exist.
if ( version_compare( WBTE_PKLIST_CROSS_PROMO_BANNER_VERSION, get_option( 'wbfte_promotion_banner_version', WBTE_PKLIST_CROSS_PROMO_BANNER_VERSION ), '==' ) && ! class_exists( 'Wbte_Cross_Promotion_Banners' ) ) {

	/**
	 * Class Wbte_Cross_Promotion_Banners
	 *
	 * This class is responsible for displaying the cross promotion banners.
	 */
	class Wbte_Cross_Promotion_Banners {

		/**
		 * Constructor.
		 */
		public function __construct() {
			
			/**
			 * Class includes helper functions for pklist invoice cta banner
			 */
			if ( ! get_option( 'wt_hide_invoice_cta_banner' ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'class-wt-invoice-cta-banner.php';
			}

			/**
			 * Class includes helper functions for smart coupon cta banner
			 */
			if ( ! get_option( 'wt_hide_smart_coupon_cta_banner' ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'class-wt-smart-coupon-cta-banner.php';
			}

			/**
			 * Class includes helper functions for pklist invoice cta banner
			 */
			if ( ! get_option( 'wt_hide_product_ie_cta_banner' ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'class-wt-p-iew-cta-banner.php';
			}
		}

		/**
		 * Get the banner version.
		 *
		 * @return string
		 */
		public static function get_banner_version() {
			return WBTE_PKLIST_CROSS_PROMO_BANNER_VERSION;
		}
	}

	new Wbte_Cross_Promotion_Banners();
}
