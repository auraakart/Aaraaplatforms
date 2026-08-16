<?php
/**
 * Main class for Cross Promotion Banners.
 *
 * @version 1.0.1
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

if ( version_compare( WBTE_UIEW_CROSS_PROMO_BANNER_VERSION, get_option( 'wbfte_promotion_banner_version', WBTE_UIEW_CROSS_PROMO_BANNER_VERSION ), '==' ) && ! class_exists( 'Wbte_Cross_Promotion_Banners' ) ) {

	class Wbte_Cross_Promotion_Banners {


		public function __construct() {

			/**
			 * Class includes helper functions for pklist invoice cta banner
			 */
			if ( ! get_option( 'wt_hide_invoice_cta_banner' ) ) {
				require_once plugin_dir_path(__FILE__) . 'class-wt-pklist-cta-banner.php';
			}

			/**
			 * Class includes helper functions for smart coupon cta banner
			 */
			if ( ! get_option( 'wt_hide_smart_coupon_cta_banner' ) ) {
				require_once plugin_dir_path(__FILE__) . 'class-wt-smart-coupon-cta-banner.php';
			}

			/**
			 * Class includes helper functions for pklist invoice cta banner
			 */
			if ( ! get_option( 'wt_hide_product_ie_cta_banner' ) ) {
				require_once plugin_dir_path(__FILE__) . 'class-wt-p-iew-cta-banner.php';
			}

			/**
			 * Class includes helper functions for accessibility cta banner (shared options/class with other WebToffee plugins).
			 *
			 * @since 2.7.3
			 */
			if ( ! get_option( 'cya11y_hide_accessyes_cta_banner' ) && ! defined( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED' ) ) {
				define( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED', true );
				require_once plugin_dir_path( __FILE__ ) . 'class-wbte-accessibility-banner.php';
			}

			/**
			 * Banner 1 — History tab common Suite CTA.
			 * Same file shipped in all three basic plugins; the define guard
			 * ensures only the first-loaded copy executes.
			 *
			 * @since 2.7.4
			 */
			if ( ! defined( 'WT_IEW_HISTORY_BANNER_LOADED' ) ) {
				define( 'WT_IEW_HISTORY_BANNER_LOADED', true );
				require_once plugin_dir_path( __FILE__ ) . 'class-wbte-iew-history-banner.php';
			}

			/**
			 * Banner 2 — Analytics → Products tab (Premium / Suite rotation).
			 *
			 * @since 2.7.4
			 */
			if ( ! defined( 'WT_IEW_ANALYTICS_PRODUCTS_BANNER_LOADED' ) ) {
				define( 'WT_IEW_ANALYTICS_PRODUCTS_BANNER_LOADED', true );
				require_once plugin_dir_path( __FILE__ ) . 'class-wbte-iew-analytics-products-banner.php';
			}

			/**
			 * Banner 3 — Analytics → Orders tab (Premium / Suite rotation).
			 *
			 * @since 2.7.4
			 */
			if ( ! defined( 'WT_IEW_ANALYTICS_ORDERS_BANNER_LOADED' ) ) {
				define( 'WT_IEW_ANALYTICS_ORDERS_BANNER_LOADED', true );
				require_once plugin_dir_path( __FILE__ ) . 'class-wbte-iew-analytics-orders-banner.php';
			}
		}

		public static function get_banner_version() {
			return WBTE_UIEW_CROSS_PROMO_BANNER_VERSION;
		}
	}

	new Wbte_Cross_Promotion_Banners();
}