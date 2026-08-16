<?php
/**
 * WCS_ATT_Integrations class
 *
 * @package  WooCommerce All Products for Subscriptions
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility with other extensions.
 *
 * @class    WCS_ATT_Integrations
 * @version  6.0.0
 */
class WCS_ATT_Integrations {

	/**
	 * Min required plugin versions to check.
	 *
	 * @var array
	 */
	private static $required = array();

	/**
	 * Cache block based cart detection result.
	 *
	 * @since  3.3.0
	 * @var    array
	 */
	private static $is_block_based_cart = null;

	/**
	 * Initialize.
	 */
	public static function init() {

		self::$required = array(
			'cp'     => '6.2.0',
			'pb'     => '6.2.0',
			'addons' => '6.7.0',
			'blocks' => '7.2.0',
		);

		// Cart/Checkout Block support.
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) && version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), self::$required['blocks'] ) >= 0 ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-blocks.php';
		}

		// Product Bundles and Composite Products support.
		if ( class_exists( 'WC_Bundles' ) || class_exists( 'WC_Composite_Products' ) || class_exists( 'WC_Mix_and_Match' ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-pb-cp.php';
			WCS_ATT_Integration_PB_CP::init();
		}

		// Product Add-Ons support.
		if ( class_exists( 'WC_Product_Addons' ) && defined( 'WC_PRODUCT_ADDONS_VERSION' ) && version_compare( WC_PRODUCT_ADDONS_VERSION, self::$required['addons'] ) >= 0 ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-pao.php';
			WCS_ATT_Integration_PAO::init();
		}

		// Name Your Price support.
		if ( class_exists( 'WC_Name_Your_Price' ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-nyp.php';
			WCS_ATT_Integration_NYP::init();
		}

		// Flatsome compatibility.
		if ( function_exists( 'wc_is_active_theme' ) && wc_is_active_theme( 'flatsome' ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-fs.php';
		}

		// Square compatibility.
		if ( class_exists( 'WooCommerce\Square\Plugin' ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-square.php';
		}

		// AfterPay compatibility.
		if ( class_exists( 'WC_Gateway_Afterpay' ) && is_callable( array( 'WC_Gateway_Afterpay', 'getInstance' ) ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-afterpay.php';
			WCS_ATT_Integration_AfterPay::init();
		}

		// Stripe compatibility.
		if ( class_exists( 'WC_Gateway_Stripe' ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-stripe.php';
		}

		// WooPayments compatibility.
		if ( class_exists( 'WC_Payments' ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-wc-payments.php';
		}

		// PayPal compatibility.
		if ( class_exists( '\WooCommerce\PayPalCommerce\PluginModule' ) ) {
			require_once WCS_ATT_ABSPATH . 'includes/integrations/class-wcs-att-integration-paypal.php';
		}

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );

		// Declare Blocks compatibility.
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_blocks_compatibility' ) );

		if ( is_admin() ) {
			// Check plugin min versions.
			add_action( 'admin_init', array( __CLASS__, 'display_notices' ) );
		}
	}

	/**
	 * Declare HPOS( Custom Order tables) compatibility.
	 *
	 * @since 4.0.3
	 */
	public static function declare_hpos_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		$compatibility = WCS_ATT_Core_Compatibility::is_wc_version_gte( '7.6.0' );

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCS_ATT()->plugin_basename(), $compatibility );
	}

	/**
	 * Declare cart/checkout Blocks compatibility.
	 *
	 * @since 4.1.4
	 */
	public static function declare_blocks_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WCS_ATT()->plugin_basename(), true );
	}

	/**
	 * Checks versions of compatible/integrated/deprecated extensions.
	 *
	 * @since  2.4.0
	 *
	 * @return void
	 */
	public static function display_notices() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// PB version check.
		if ( class_exists( 'WC_Bundles' ) && function_exists( 'WC_PB' ) ) {
			$required_version = self::$required['pb'];
			if ( version_compare( WCS_ATT()->plugin_version( true, WC_PB()->version ), $required_version ) < 0 ) {

				$extension      = __( 'Product Bundles', 'woocommerce-all-products-for-subscriptions' );
				$extension_full = __( 'WooCommerce Product Bundles', 'woocommerce-all-products-for-subscriptions' );
				$extension_url  = 'https://woocommerce.com/products/product-bundles/';
				$notice         = sprintf( __( 'The installed version of <strong>%1$s</strong> is not supported by <strong>All Products for WooCommerce Subscriptions</strong>. Please update <a href="%2$s" target="_blank">%3$s</a> to version <strong>%4$s</strong> or higher.', 'woocommerce-all-products-for-subscriptions' ), $extension, $extension_url, $extension_full, $required_version );

				WCS_ATT_Admin_Notices::add_dismissible_notice(
					$notice,
					array(
						'dismiss_class' => 'pb_lt_' . $required_version,
						'type'          => 'native',
					)
				);
			}
		}

		// CP version check.
		if ( class_exists( 'WC_Composite_Products' ) && function_exists( 'WC_CP' ) ) {
			$required_version = self::$required['cp'];
			if ( version_compare( WCS_ATT()->plugin_version( true, WC_CP()->version ), $required_version ) < 0 ) {

				$extension      = __( 'Composite Products', 'woocommerce-all-products-for-subscriptions' );
				$extension_full = __( 'WooCommerce Composite Products', 'woocommerce-all-products-for-subscriptions' );
				$extension_url  = 'https://woocommerce.com/products/composite-products/';
				$notice         = sprintf( __( 'The installed version of <strong>%1$s</strong> is not supported by <strong>All Products for WooCommerce Subscriptions</strong>. Please update <a href="%2$s" target="_blank">%3$s</a> to version <strong>%4$s</strong> or higher.', 'woocommerce-all-products-for-subscriptions' ), $extension, $extension_url, $extension_full, $required_version );

				WCS_ATT_Admin_Notices::add_dismissible_notice(
					$notice,
					array(
						'dismiss_class' => 'cp_lt_' . $required_version,
						'type'          => 'native',
					)
				);
			}
		}

		// PAO version check.
		if ( class_exists( 'WC_Product_Addons' ) ) {

			$required_version = self::$required['addons'];

			if ( ! defined( 'WC_PRODUCT_ADDONS_VERSION' ) || version_compare( WC_PRODUCT_ADDONS_VERSION, $required_version ) < 0 ) {

				$extension      = __( 'Product Add-Ons', 'woocommerce-all-products-for-subscriptions' );
				$extension_full = __( 'WooCommerce Product Add-Ons', 'woocommerce-all-products-for-subscriptions' );
				$extension_url  = 'https://woocommerce.com/products/product-add-ons/';
				$notice         = sprintf( __( 'The installed version of <strong>%1$s</strong> is not supported by <strong>All Products for WooCommerce Subscriptions</strong>. Please update <a href="%2$s" target="_blank">%3$s</a> to version <strong>%4$s</strong> or higher.', 'woocommerce-all-products-for-subscriptions' ), $extension, $extension_url, $extension_full, $required_version );

				WCS_ATT_Admin_Notices::add_dismissible_notice(
					$notice,
					array(
						'dismiss_class' => 'addons_lt_' . $required_version,
						'type'          => 'native',
					)
				);
			}
		}
	}

	/**
	 * Whether the cart page contains the cart block.
	 *
	 * @since  3.3.0
	 *
	 * @param  string $route
	 * @return boolean
	 */
	public static function is_block_based_cart() {

		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
			return false;
		}

		if ( is_null( self::$is_block_based_cart ) ) {

			self::$is_block_based_cart = false;

			$checkout_block_data = class_exists( 'WC_Blocks_Utils' ) ? WC_Blocks_Utils::get_blocks_from_page( 'woocommerce/cart', 'cart' ) : false;

			if ( ! empty( $checkout_block_data ) ) {
				self::$is_block_based_cart = true;
			}
		}

		return self::$is_block_based_cart;
	}
}

add_action( 'plugins_loaded', array( 'WCS_ATT_Integrations', 'init' ), 20 );
