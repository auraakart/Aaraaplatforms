<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link        https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/includes
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wallet_System_For_Woocommerce_Pro_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $wsfwp_onboard    To initializsed the object of class onboard.
	 */
	protected $wsfwp_onboard;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area,
	 * the public-facing side of the site and common side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		if ( defined( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION' ) ) {

			$this->version = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION;
		} else {

			$this->version = '2.3.7';
		}

		$this->plugin_name = 'wallet-system-for-woocommerce-pro';

		$this->wallet_system_for_woocommerce_pro_dependencies();
		$this->wallet_system_for_woocommerce_pro_locale();
		if ( is_admin() ) {
			$this->wallet_system_for_woocommerce_pro_admin_hooks();
		} else {
			$this->wallet_system_for_woocommerce_pro_public_hooks();
		}
		$this->wallet_system_for_woocommerce_pro_common_hooks();

		$this->wallet_system_for_woocommerce_pro_api_hooks();

		// new email work.
		$this->init();
		// new email work.
		// custom function for ajax.
		$this->wallet_system_for_woocommerce_pro_ajax_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wallet_System_For_Woocommerce_Pro_Loader. Orchestrates the hooks of the plugin.
	 * - Wallet_System_For_Woocommerce_Pro_i18n. Defines internationalization functionality.
	 * - Wallet_System_For_Woocommerce_Pro_Admin. Defines all hooks for the admin area.
	 * - Wallet_System_For_Woocommerce_Pro_Common. Defines all hooks for the common area.
	 * - Wallet_System_For_Woocommerce_Pro_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function wallet_system_for_woocommerce_pro_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-wallet-system-for-woocommerce-pro-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-wallet-system-for-woocommerce-pro-i18n.php';

		if ( is_admin() ) {

			// The class responsible for defining all actions that occur in the admin area.
			require_once plugin_dir_path( __DIR__ ) . 'admin/class-wallet-system-for-woocommerce-pro-admin.php';
		} else {

			// The class responsible for defining all actions that occur in the public-facing side of the site.
			require_once plugin_dir_path( __DIR__ ) . 'public/class-wallet-system-for-woocommerce-pro-public.php';

		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-wallet-system-for-woocommerce-pro-ajax-handler.php';
		require_once plugin_dir_path( __DIR__ ) . 'package/rest-api/class-wallet-system-for-woocommerce-pro-rest-api.php';

		/**
		 * This class responsible for defining common functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'common/class-wallet-system-for-woocommerce-pro-common.php';

		/**
		 * The class responsible for creating wallet widget for elementor.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'elementor-widget/class-elementor-wallet-widget.php';

		/**
		 * The class responsible for creating the wallet widget to show in sidebar.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-wws-wallet-widget.php';

		$this->loader = new Wallet_System_For_Woocommerce_Pro_Loader();
	}

	/**
	 * The function is used to include email class.
	 */
	public function init() {
		add_filter( 'woocommerce_email_classes', array( $this, 'wps_wswp_woocommerce_email_classes' ) );
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wallet_System_For_Woocommerce_Pro_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function wallet_system_for_woocommerce_pro_locale() {

		$plugin_i18n = new Wallet_System_For_Woocommerce_Pro_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function wallet_system_for_woocommerce_pro_admin_hooks() {

		$wsfwp_plugin_admin = new Wallet_System_For_Woocommerce_Pro_Admin( $this->wsfwp_get_plugin_name(), $this->wsfwp_get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $wsfwp_plugin_admin, 'wsfwp_admin_enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $wsfwp_plugin_admin, 'wsfwp_admin_enqueue_scripts' );

		// check.
		$this->loader->add_filter( 'wsfw_check_pro_plugin', $wsfwp_plugin_admin, 'wsfwp_check_pro_plugin' );
		// check.

		// Add settings menu for Wallet System for WooCommerce Pro.
		$this->loader->add_action( 'admin_menu', $wsfwp_plugin_admin, 'wps_wsfwp_remove_default_submenu', 50 );

		$this->loader->add_filter( 'wps_wsfw_plug_extra_tabs', $wsfwp_plugin_admin, 'wps_wsfwp_plug_extra_tabs', 10, 1 );
		$this->loader->add_filter( 'wps_wsfw_template_path', $wsfwp_plugin_admin, 'wps_wsfwp_template_path', 10, 1 );
		$this->loader->add_filter( 'wps_wsfw_show_additional_section', $wsfwp_plugin_admin, 'wps_wsfwp_show_additional_section' );
		$this->loader->add_filter( 'wps_wsfw_overview_additional_content', $wsfwp_plugin_admin, 'wps_wsfwp_include_overview' );

		// restriction for pro update.
		$this->loader->add_filter( 'wsfw_wallet_user_restriction_after', $wsfwp_plugin_admin, 'wps_wsfwp_wsfw_wallet_user_restriction_after', 10, 2 );
		$this->loader->add_action( 'wsfw_wallet_restrict_user_pro_after', $wsfwp_plugin_admin, 'wsfw_wallet_restrict_user_pro_after_html' );
		$this->loader->add_action( 'wp_ajax_wsfw_restrict_include_modal_data', $wsfwp_plugin_admin, 'wsfw_wallet_restrict_include_modal_data' );
		$this->loader->add_action( 'wp_ajax_wsfw_restrict_include_modal_data', $wsfwp_plugin_admin, 'wsfw_wallet_restrict_include_modal_data' );
		$this->loader->add_filter( 'woocommerce_locate_core_template', $wsfwp_plugin_admin, 'wsfw_wallet_locate_woocommerce_template', 10, 3 );
		$this->loader->add_filter( 'woocommerce_locate_template', $wsfwp_plugin_admin, 'wsfw_wallet_locate_woocommerce_template', 10, 3 );
		$this->loader->add_action( 'user_restriction_saving', $wsfwp_plugin_admin, 'wsfw_wallet_user_restriction_saving' );
		// license variables.
		$callname_lic         = self::$lic_callback_function;
		$callname_lic_initial = self::$lic_ini_callback_function;
		$day_count            = self::$callname_lic_initial();
		if ( self::$callname_lic() || 0 <= $day_count ) {

			$this->loader->add_action( 'wps_wsfwp_check_license_daily', $wsfwp_plugin_admin, 'wps_wsfwp_check_license' );
		}

		if ( self::$callname_lic() || $day_count > 0 ) {
			// $this->loader->add_filter( 'wsfw_general_extra_settings_array', $wsfwp_plugin_admin, 'wsfwp_admin_general_settings_page', 20 );
			$this->loader->add_filter( 'wsfw_check_order_meta_for_userid', $wsfwp_plugin_admin, 'wsfwp_check_order_meta_for_userid', 10, 2 );
			$this->loader->add_filter( 'wsfw_check_order_meta_for_recharge_reason', $wsfwp_plugin_admin, 'wsfwp_check_order_meta_for_recharge_reason', 10, 2 );
			$this->loader->add_filter( 'wps_wsfw_update_wallet_to_base_price', $wsfwp_plugin_admin, 'wps_wsfwp_update_wallet_to_base_price', 10, 2 );
			// Coupon hooks.
			$this->loader->add_action( 'init', $wsfwp_plugin_admin, 'wps_wsfw_register_cpt_wallet' );
			$this->loader->add_action( 'save_post_wps_cpt_coupons', $wsfwp_plugin_admin, 'wps_wsfwp_wallet_coupon_for_woo_save_fields' );
			// Adding custom columns.
			$this->loader->add_filter( 'manage_wps_cpt_coupons_posts_columns', $wsfwp_plugin_admin, 'wps_wsfwp_wallet_coupon_for_woo_cpt_columns' );
			// Populating columns.
			$this->loader->add_action( 'manage_wps_cpt_coupons_posts_custom_column', $wsfwp_plugin_admin, 'wps_wsfwp_wallet_coupon_for_woo_fill_columns', 10, 2 );
			// Remove add media from wallet coupon cpt.
			$this->loader->add_action( 'wp_editor_settings', $wsfwp_plugin_admin, 'wps_wsfwp_wallet_coupon_remove_add_media', 10, 1 );
			$this->loader->add_filter( 'wsfw_subscription_type__array', $wsfwp_plugin_admin, 'wsfw_subscription_type__array', 10, 1 );
			$this->loader->add_filter( 'wsfw_wallet_action_auto_topup_extra_settings_array', $wsfwp_plugin_admin, 'wps_wsfwp_wallet_action_auto_topup_extra_settings_array', 10, 1 );

			// wallet fee.
			$this->loader->add_filter( 'wsfw_wallet_action_settings_fee_setting', $wsfwp_plugin_admin, 'wps_wsfws_add_template_wallet_fee', 10 );
			$this->loader->add_filter( 'wsfwp_wallet_action_settings_withdrawal_array', $wsfwp_plugin_admin, 'wps_wsfws_admin_wallet_action_withdrawal_settings_page', 10 );
			$this->loader->add_filter( 'wsfwp_wallet_action_settings_transfer_array', $wsfwp_plugin_admin, 'wps_wsfws_admin_wallet_action_transfer_settings_page', 10 );
			$this->loader->add_action( 'wsfw_wallet_action_settings_refer_friend_array', $wsfwp_plugin_admin, 'wsfw_admin_wallet_action_settings_refer_friend_array', 10 );
			$this->loader->add_action( 'wsfw_wallet_action_different_layout_settings_array', $wsfwp_plugin_admin, 'wsfw_admin_wallet_action_different_layout_array', 10 );
			$this->loader->add_action( 'wsfw_wallet_action_payment_settings_array', $wsfwp_plugin_admin, 'wsfw_wallet_action_payment_settings_layout_array', 10 );
			$this->loader->add_action( 'wsfw_wallet_action_gamification_rule_settings_array', $wsfwp_plugin_admin, 'wsfw_admin_wallet_action_gamification_rule_array', 10 );

		}

		$saved_wallet_page_id = get_option( 'wps_wsfwp_saved_wallet_page_id', '' );
		if ( isset( $saved_wallet_page_id ) && 'true' !== $saved_wallet_page_id ) {
			$this->loader->add_action( 'init', $wsfwp_plugin_admin, 'wps_wsfwp_save_wallet_page_id', 10 );
		}
		$cashback_wallet_enable = get_option( 'wps_wsfw_enable_cashback', '' );
		$cashback_wallet_catwise_enable = get_option( 'wps_wsfw_cashback_rule', '' );
		if ( 'on' === $cashback_wallet_enable && 'catwise' == $cashback_wallet_catwise_enable ) {
			$this->loader->add_action( 'product_cat_edit_form_fields', $wsfwp_plugin_admin, 'wps_wsfwp_edit_product_cat_cashback_field', 10, 1 );
			$this->loader->add_action( 'created_term', $wsfwp_plugin_admin, 'wps_wsfwp_save_product_cashback_field', 10, 3 );
			$this->loader->add_action( 'edit_term', $wsfwp_plugin_admin, 'wps_wsfwp_save_product_cashback_field', 10, 3 );
		}

		// wallet restriction tab.
		$this->loader->add_filter( 'wps_wsfw_plugin_standard_admin_settings_tabs_after_wallet_action', $wsfwp_plugin_admin, 'wps_wsfwp_plug_wallet_restriction_tab', 10, 1 );
		$this->loader->add_filter( 'wps_wsfw_template_path', $wsfwp_plugin_admin, 'wps_wsfwp_template_path_wallet_restriction', 10, 1 );
		$this->loader->add_filter( 'wsfw_wallet_restriction_withdrawal_array', $wsfwp_plugin_admin, 'wps_wsfw_admin_wallet_withdrawal_restriction_settings_page', 10 );
		$this->loader->add_filter( 'wsfw_wallet_restriction_transfer_array', $wsfwp_plugin_admin, 'wps_wsfw_admin_wallet_transfer_restriction_settings_page', 10 );
		$this->loader->add_filter( 'wsfw_wallet_restriction_recharge_array', $wsfwp_plugin_admin, 'wps_wsfw_admin_wallet_recharge_restriction_settings_page', 10 );
		$this->loader->add_action( 'wsfw_wallet_action_settings_promotions_tab_array', $wsfwp_plugin_admin, 'wsfw_admin_wallet_action_settings_promotions_tab_array', 10 );
		$this->loader->add_action( 'wsfw_wallet_withrwaral_settings_tab_array', $wsfwp_plugin_admin, 'wsfw_admin_wallet_withdrawal_ettings_tab_array', 10 );

		$this->loader->add_action( 'wsfw_wallet_action_settings_recharge_tab_array', $wsfwp_plugin_admin, 'wsfw_admin_wallet_action_settings_recharge_tab_array', 10 );
		$this->loader->add_action( 'wsfw_wallet_action_recharge_enable_settings', $wsfwp_plugin_admin, 'wsfw_wallet_action_recharge_enable_settings_tab', 10 );
		$this->loader->add_action( 'wsfw_wallet_action_promotions_enable_settings', $wsfwp_plugin_admin, 'wsfw_wallet_action_promotion_enable_settings_tab', 10 );
		$this->loader->add_action( 'wsfw_wallet_action_withdrawal_settings', $wsfwp_plugin_admin, 'wsfw_wallet_withdrawal_enable_settings_tab', 10 );
		$this->loader->add_filter( 'wps_wallet_withdrawal_through_paypal', $wsfwp_plugin_admin, 'wps_wallet_withdrawal_through_paypal_', 10, 5 );

		$this->loader->add_action( 'admin_notices', $wsfwp_plugin_admin, 'wps_subscription_notification_html', 10 );
		$this->loader->add_action( 'wp_ajax_wps_wsfwp_check_license_key_status', $wsfwp_plugin_admin, 'wps_wsfwp_check_license_key_status' );
	}

	/**
	 * Register all of the hooks related to the common functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function wallet_system_for_woocommerce_pro_common_hooks() {

		$wsfwp_plugin_common = new Wallet_System_For_Woocommerce_Pro_Common( $this->wsfwp_get_plugin_name(), $this->wsfwp_get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $wsfwp_plugin_common, 'wsfwp_common_enqueue_styles' );

		$this->loader->add_action( 'wp_enqueue_scripts', $wsfwp_plugin_common, 'wsfwp_common_enqueue_scripts' );
		// license validation.
		$this->loader->add_action( 'wp_ajax_wps_wsfwp_validate_license_key', $wsfwp_plugin_common, 'wps_wsfwp_validate_license_key' );
		// license variables.
		// check.
		$this->loader->add_filter( 'wsfw_check_pro_plugin_common', $wsfwp_plugin_common, 'wsfwp_check_pro_plugin_common' );
		// check.
		$callname_lic         = self::$lic_callback_function;
		$callname_lic_initial = self::$lic_ini_callback_function;
		$day_count            = self::$callname_lic_initial();
		if ( self::$callname_lic() || 0 <= $day_count ) {
			$this->loader->add_filter( 'wps_wsfw_common_update_wallet_to_base_price', $wsfwp_plugin_common, 'wps_wsfwp_common_update_wallet_to_base_price', 10, 2 );
			$enable = get_option( 'wps_wsfw_enable', '' );
			if ( isset( $enable ) && 'on' === $enable ) {
				$this->loader->add_action( 'plugins_loaded', $wsfwp_plugin_common, 'wps_wsfw_wallet_shortcodes' );
				$this->loader->add_action( 'init', $wsfwp_plugin_common, 'wps_wsfw_save_wallet_public_shortcode' );
				// wallet coupon.
				$this->loader->add_action( 'wp_ajax_wps_wsfwp_redeem_coupon_amount', $wsfwp_plugin_common, 'wps_wsfwp_redeem_coupon_amount' );
				$this->loader->add_action( 'wp_ajax_nopriv_wps_wsfwp_redeem_coupon_amount', $wsfwp_plugin_common, 'wps_wsfwp_redeem_coupon_amount' );
				$cashback_wallet_enable = get_option( 'wps_wsfw_enable_cashback', '' );
				$cashback_wallet_catwise_enable = get_option( 'wps_wsfw_cashback_rule', '' );
				if ( 'on' === $cashback_wallet_enable && 'catwise' == $cashback_wallet_catwise_enable ) {
					$this->loader->add_action( 'wsfw_wallet_cashback_using_catwise', $wsfwp_plugin_common, 'wps_wsfwp_cashback_using_catwise', 10, 3 );
				}
			}
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function wallet_system_for_woocommerce_pro_public_hooks() {

		$wsfwp_plugin_public = new Wallet_System_For_Woocommerce_Pro_Public( $this->wsfwp_get_plugin_name(), $this->wsfwp_get_version() );

		// license validation.
		$callname_lic         = self::$lic_callback_function;
		$callname_lic_initial = self::$lic_ini_callback_function;
		$day_count            = self::$callname_lic_initial();
		if ( self::$callname_lic() || 0 <= $day_count ) {
			$enable = get_option( 'wps_wsfw_enable', '' );
			if ( isset( $enable ) && 'on' === $enable ) {
				$this->loader->add_action( 'wp_enqueue_scripts', $wsfwp_plugin_public, 'wsfwp_public_enqueue_styles' );
				$this->loader->add_action( 'wp_enqueue_scripts', $wsfwp_plugin_public, 'wsfwp_public_enqueue_scripts' );
				$this->loader->add_action( 'init', $wsfwp_plugin_public, 'wps_wsfwp_wallet_register_endpoint' );
				$this->loader->add_action( 'query_vars', $wsfwp_plugin_public, 'wps_wsfwp_wallet_query_var' );
				$this->loader->add_action( 'wallet_qr_vode', $wsfwp_plugin_public, 'wps_wsfwp_display_wallet_endpoint_content', 10 );
				$this->loader->add_filter( 'template_include', $wsfwp_plugin_public, 'wps_wsfwp_show_qrcode_image' );
				$this->loader->add_filter( 'the_content', $wsfwp_plugin_public, 'content_for_my_wallet_page_wallet_transfer', 20, 1 );
				$this->loader->add_filter( 'woocommerce_add_cart_item_data', $wsfwp_plugin_public, 'add_wallet_user_id_in_cart', 10, 2 );
				$this->loader->add_filter( 'woocommerce_get_cart_item_from_session', $wsfwp_plugin_public, 'wps_wsfwp_get_cart_items_from_session', 1, 3 );
				$this->loader->add_filter( 'wsfw_min_max_value_for_wallet_recharge', $wsfwp_plugin_public, 'min_max_value_for_wallet_recharge', 10 );
				$this->loader->add_filter( 'wsfw_check_order_meta_for_userid', $wsfwp_plugin_public, 'wsfwp_check_order_meta_for_userid', 10, 2 );
				$this->loader->add_filter( 'wsfw_check_order_meta_for_recharge_reason', $wsfwp_plugin_public, 'wsfwp_check_order_meta_for_recharge_reason', 10, 2 );
				$this->loader->add_action( 'wps_wsfw_remove_value_from_session', $wsfwp_plugin_public, 'wps_wss_remove_value_from_session', 1, 1 );
				$this->loader->add_filter( 'wps_wsfw_show_withdrawal_message', $wsfwp_plugin_public, 'wps_wsfwp_show_withdrawal_message', 1 );
				$this->loader->add_filter( 'wsfw_add_invitation_link_message', $wsfwp_plugin_public, 'wps_wsfwp_add_invitation_link_message', 10 );
				$this->loader->add_filter( 'wps_wsfw_show_additional_content', $wsfwp_plugin_public, 'wps_wsfwp_show_additional_content', 10, 4 );
				// transfer fee.
				$this->loader->add_filter( 'wps_wsfw_show_wallet_transfer_fee_content', $wsfwp_plugin_public, 'wps_wsfwp_show_wallet_transfer_fee_html', 10 );
				// transfer fee.
				// withdrawal fee.
				$this->loader->add_filter( 'wps_wsfw_show_wallet_withdrawal_fee_content', $wsfwp_plugin_public, 'wps_wsfwp_show_wallet_withdrawal_fee_html', 10 );
				// withdrawal fee.
				$this->loader->add_action( 'woocommerce_init', $wsfwp_plugin_public, 'wps_wsfwp_set_woocoomerce_session', 10 );
				$this->loader->add_action( 'user_register', $wsfwp_plugin_public, 'wps_save_wallet_amount_after_registration', 10, 1 );
				$this->loader->add_filter( 'wps_wsfw_get_current_currency', $wsfwp_plugin_public, 'wps_wsfwp_get_current_currency', 10, 1 );
				$this->loader->add_filter( 'wps_wsfw_add_wallet_tabs_before_transaction', $wsfwp_plugin_public, 'wps_wsfwp_get_wallet_coupon_section', 10, 2 );
				$this->loader->add_filter( 'wps_wsfw_add_wallet_tabs_wallet_fund_request', $wsfwp_plugin_public, 'wps_wsfwp_get_wallet_fund_request_section', 10, 2 );

				// new feature work.
				$this->loader->add_filter( 'wsfw_user_restrict_pro_check', $wsfwp_plugin_public, 'wps_wsfwp_user_restrict_pro_check', 10, 1 );
				$this->loader->add_filter( 'wps_wsfwp_pro_plugin_check', $wsfwp_plugin_public, 'wps_wsfwp_pro_plugin_check_callback', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_topup', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_topup', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_transfer', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_transfer', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_withdrawal', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_withdrawal', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_coupon', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_coupon', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_fund_request', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_fund_request', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_transaction', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_transaction', 10, 1 );
				$this->loader->add_filter( 'wps_wallet_restrict_message_to_user', $wsfwp_plugin_public, 'wps_wsfwp_wps_wallet_restrict_message_to_user', 10, 1 );
				$this->loader->add_filter( 'wps_wallet_restrict_message_for', $wsfwp_plugin_public, 'wps_wsfwp_wps_wallet_restrict_message_for', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_referral', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_referral', 10, 1 );
				$this->loader->add_filter( 'wallet_restrict_qrcode', $wsfwp_plugin_public, 'wps_wsfwp_wallet_restrict_qrcode', 10, 1 );

				// new feature work.
				$this->loader->add_action( 'init', $wsfwp_plugin_public, 'wps_wsfwp_send_mail_to_user' );
				$this->loader->add_action( 'wsfw_make_wallet_recharge_subscription', $wsfwp_plugin_public, 'wsfw_make_wallet_recharge_subscription_body' );
				$this->loader->add_action( 'wallet_qr_vode_shotcode', $wsfwp_plugin_public, 'wps_wsfwp_wallet_display_wrapper_for_q_r_def' );

				$this->loader->add_filter( 'wps_wsfw_get_user_choice_of_subscription', $wsfwp_plugin_public, 'wps_wsfwp_get_user_choice_of_subscription', 10, 1 );
				$this->loader->add_action( 'woocommerce_review_order_before_payment', $wsfwp_plugin_public, 'wps_wsfwp_add_wallet_cashback_message_restriction' );
				$this->loader->add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_before', $wsfwp_plugin_public, 'wsfwp_wps_enqueue_script_block_eheckout', 10 );
				$this->loader->add_action( 'wps_wsfw_for_limit_negative_balance', $wsfwp_plugin_public, 'wps_wsfwp_limit_negative_balance' );
				$this->loader->add_action( 'user_register', $wsfwp_plugin_public, 'wps_wsfw_apply_user_restriction_new_user' );

				// wallet dashboard new template.
				$this->loader->add_action( 'wsfw_pro_version_wallet_template_file', $wsfwp_plugin_public, 'wsfw_pro_version_wallet_template_file_callback' );

				$this->loader->add_filter( 'body_class', $wsfwp_plugin_public, 'wps_wsfw_add_custom_class_to_my_account_page_and_wallet_endpoint' );

				$this->loader->add_action( 'wps_wsfw_filter_for_wallet_referral', $wsfwp_plugin_public, 'wps_wsfw_filter_for_wallet_referral_content' );
				$this->loader->add_action( 'woocommerce_coupon_is_valid', $wsfwp_plugin_public, 'wps_wsfw_woocommerce_apply_product_on_coupon', 10, 3 );
				$this->loader->add_action( 'woocommerce_order_status_changed', $wsfwp_plugin_public, 'wps_wsfw_woocommerce_order_status_changed_coupon_refer', 10, 3 );

				$this->loader->add_action( 'wps_wsfw_filter_for_wallet_multi_level_referral', $wsfwp_plugin_public, 'wps_wsfw_filter_for_wallet_multi_level_referral_content' );

			}
		}
	}

	/**
	 * Register all of the hooks related to the api functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function wallet_system_for_woocommerce_pro_api_hooks() {

		$wsfwp_plugin_api = new Wallet_System_For_Woocommerce_Pro_Rest_Api( $this->wsfwp_get_plugin_name(), $this->wsfwp_get_version() );

		$this->loader->add_action( 'rest_api_init', $wsfwp_plugin_api, 'wps_wsfwp_add_endpoint' );
	}

	/**
	 * Register all of the hooks related to ajax
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function wallet_system_for_woocommerce_pro_ajax_hooks() {

		$wsfw_plugin_ajax = new Wallet_System_For_Woocommerce_Pro_Ajax_Handler();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function wsfwp_run() {
		$this->loader->wsfwp_run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function wsfwp_get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wallet_System_For_Woocommerce_Pro_Loader    Orchestrates the hooks of the plugin.
	 */
	public function wsfwp_get_loader() {
		return $this->loader;
	}


	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wallet_System_For_Woocommerce_Pro_Onboard    Orchestrates the hooks of the plugin.
	 */
	public function wsfwp_get_onboard() {
		return $this->wsfwp_onboard;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function wsfwp_get_version() {
		return $this->version;
	}



	/**
	 * The function include email class.
	 *
	 * @name wps_sfw_woocommerce_email_classes.
	 * @since 1.0.0
	 * @param Array $emails emails.
	 */
	public function wps_wswp_woocommerce_email_classes( $emails ) {
		$emails['wps_wswp_wallet_credit'] = require_once plugin_dir_path( __DIR__ ) . 'emails/class-wallet-system-for-woocommerce-pro-credit-email.php';
		$emails['wps_wswp_wallet_credit_cashback'] = require_once plugin_dir_path( __DIR__ ) . 'emails/class-wallet-system-for-woocommerce-pro-credit-cashback-email.php';
		$emails['wps_wswp_wallet_credit_transfer'] = require_once plugin_dir_path( __DIR__ ) . 'emails/class-wallet-system-for-woocommerce-pro-credit-transfer-email.php';

		$emails['wps_wswp_wallet_debit'] = require_once plugin_dir_path( __DIR__ ) . 'emails/class-wallet-system-for-woocommerce-pro-debit-email.php';
		return $emails;
	}

	/**
	 * Check license validity.
	 *
	 * @var string
	 */
	public static $lic_callback_function = 'check_lcns_validity';


	/**
	 * Check license initial days.
	 *
	 * @var string
	 */
	public static $lic_ini_callback_function = 'check_lcns_initial_days';

	/**
	 * Predefined default wps_wsfwp_plug tabs.
	 *
	 * @return  Array       An key=>value pair of Wallet System for WooCommerce Pro tabs.
	 */
	public function wps_wsfwp_plug_default_tabs() {

		$wsfwp_default_tabs = array();

		$wsfwp_default_tabs['wallet-system-for-woocommerce-pro-general'] = array(
			'title'       => esc_html__( 'General Setting', 'wallet-system-for-woocommerce-pro' ),
			'name'        => 'wallet-system-for-woocommerce-pro-general',
		);
		$wsfwp_default_tabs = apply_filters( 'wps_wsfwp_wsfwp_plugin_standard_admin_settings_tabs', $wsfwp_default_tabs );

		$wsfwp_default_tabs['wallet-system-for-woocommerce-pro-system-status'] = array(
			'title'       => esc_html__( 'System Status', 'wallet-system-for-woocommerce-pro' ),
			'name'        => 'wallet-system-for-woocommerce-pro-system-status',
		);
		$wsfwp_default_tabs['wallet-system-for-woocommerce-pro-template'] = array(
			'title'       => esc_html__( 'Templates', 'wallet-system-for-woocommerce-pro' ),
			'name'        => 'wallet-system-for-woocommerce-pro-template',
		);
		$wsfwp_default_tabs['wallet-system-for-woocommerce-pro-overview'] = array(
			'title'       => esc_html__( 'Overview', 'wallet-system-for-woocommerce-pro' ),
			'name'        => 'wallet-system-for-woocommerce-pro-overview',
		);

		return $wsfwp_default_tabs;
	}

	/**
	 * Locate and load appropriate tempate.
	 *
	 * @since   1.0.0
	 * @param string $path path file for inclusion.
	 * @param array  $params parameters to pass to the file for access.
	 */
	public function wps_wsfwp_plug_load_template( $path, $params = array() ) {

		$wsfwp_file_path = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . $path;

		if ( file_exists( $wsfwp_file_path ) ) {

			include $wsfwp_file_path;
		} else {

			/* translators: %s: file path */
			$wsfwp_notice = sprintf( esc_html__( 'Unable to locate file at location "%s". Some features may not work properly in this plugin. Please contact us!', 'wallet-system-for-woocommerce-pro' ), $wsfwp_file_path );
			$this->wps_wsfwp_plug_admin_notice( $wsfwp_notice, 'error' );
		}
	}

	/**

	 * Check the license.

	 * @since 1.0.0

	 * @return boolean true/false
	 */
	public static function check_lcns_validity() {
		$wsfwp_lic_key   = get_option( 'wps_wsfwp_lic_key', '' );
		$wsfwp_lic_valid = get_option( 'wps_wsfwp_pro_valid_license', false );
		if ( ! empty( $wsfwp_lic_key ) && $wsfwp_lic_valid ) {
			return true;
		} else {
			return false;
		}
	}

	/**

	 * Validate the use of features of this plugin for initial days.
	 *
	 * @since 1.0.0
	 * @return boolean true/false
	 */
	public static function check_lcns_initial_days() {

		$thirty_days  = get_option( 'wps_wsfwp_activated_timestamp', 0 );
		$current_time = current_time( 'timestamp' );
		$day_count = ( $thirty_days - $current_time ) / ( 24 * 60 * 60 );
		return $day_count;
	}

	/**
	 * Show admin notices.
	 *
	 * @param  string $wsfwp_message    Message to display.
	 * @param  string $type       notice type, accepted values - error/update/update-nag.
	 * @since  1.0.0
	 */
	public static function wps_wsfwp_plug_admin_notice( $wsfwp_message, $type = 'error' ) {

		$wsfwp_classes = 'notice ';

		switch ( $type ) {

			case 'update':
				$wsfwp_classes .= 'updated is-dismissible';
				break;

			case 'update-nag':
				$wsfwp_classes .= 'update-nag is-dismissible';
				break;

			case 'success':
				$wsfwp_classes .= 'notice-success is-dismissible';
				break;

			default:
				$wsfwp_classes .= 'notice-error is-dismissible';
		}

		$wsfwp_notice  = '<div class="' . esc_attr( $wsfwp_classes ) . ' wps-errorr-8">';
		$wsfwp_notice .= '<p>' . esc_html( $wsfwp_message ) . '</p>';
		$wsfwp_notice .= '</div>';

		if ( ! empty( $wsfwp_notice ) ) {
			echo wp_kses_post( $wsfwp_notice );
		}
	}


	/**
	 * Show WordPress and server info.
	 *
	 * @return  Array $wsfwp_system_data       returns array of all wordpress and server related information.
	 * @since  1.0.0
	 */
	public function wps_wsfwp_plug_system_status() {
		global $wpdb;
		$wsfwp_system_status    = array();
		$wsfwp_wordpress_status = array();
		$wsfwp_system_data      = array();

		// Get the web server.
		$wsfwp_system_status['web_server'] = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

		// Get PHP version.
		$wsfwp_system_status['php_version'] = function_exists( 'phpversion' ) ? phpversion() : __( 'N/A (phpversion function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get the server's IP address.
		$wsfwp_system_status['server_ip'] = isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '';

		// Get the server's port.
		$wsfwp_system_status['server_port'] = isset( $_SERVER['SERVER_PORT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ) ) : '';

		// Get the uptime.
		$wsfwp_system_status['uptime'] = function_exists( 'exec' ) ? @exec( 'uptime -p' ) : __( 'N/A (make sure exec function is enabled)', 'wallet-system-for-woocommerce-pro' );

		// Get the server path.
		$wsfwp_system_status['server_path'] = defined( 'ABSPATH' ) ? ABSPATH : __( 'N/A (ABSPATH constant not defined)', 'wallet-system-for-woocommerce-pro' );

		// Get the OS.
		$wsfwp_system_status['os'] = function_exists( 'php_uname' ) ? php_uname( 's' ) : __( 'N/A (php_uname function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get WordPress version.
		$wsfwp_wordpress_status['wp_version'] = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : __( 'N/A (get_bloginfo function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get and count active WordPress plugins.
		$wsfwp_wordpress_status['wp_active_plugins'] = function_exists( 'get_option' ) ? count( get_option( 'active_plugins' ) ) : __( 'N/A (get_option function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// See if this site is multisite or not.
		$wsfwp_wordpress_status['wp_multisite'] = function_exists( 'is_multisite' ) && is_multisite() ? __( 'Yes', 'wallet-system-for-woocommerce-pro' ) : __( 'No', 'wallet-system-for-woocommerce-pro' );

		// See if WP Debug is enabled.
		$wsfwp_wordpress_status['wp_debug_enabled'] = defined( 'WP_DEBUG' ) ? __( 'Yes', 'wallet-system-for-woocommerce-pro' ) : __( 'No', 'wallet-system-for-woocommerce-pro' );

		// See if WP Cache is enabled.
		$wsfwp_wordpress_status['wp_cache_enabled'] = defined( 'WP_CACHE' ) ? __( 'Yes', 'wallet-system-for-woocommerce-pro' ) : __( 'No', 'wallet-system-for-woocommerce-pro' );

		// Get the total number of WordPress users on the site.
		$wsfwp_wordpress_status['wp_users'] = function_exists( 'count_users' ) ? count_users() : __( 'N/A (count_users function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get the number of published WordPress posts.
		$wsfwp_wordpress_status['wp_posts'] = wp_count_posts()->publish >= 1 ? wp_count_posts()->publish : __( '0', 'wallet-system-for-woocommerce-pro' );

		// Get PHP memory limit.
		$wsfwp_system_status['php_memory_limit'] = function_exists( 'ini_get' ) ? (int) ini_get( 'memory_limit' ) : __( 'N/A (ini_get function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get the PHP error log path.
		$wsfwp_system_status['php_error_log_path'] = ! ini_get( 'error_log' ) ? __( 'N/A', 'wallet-system-for-woocommerce-pro' ) : ini_get( 'error_log' );

		// Get PHP max upload size.
		$wsfwp_system_status['php_max_upload'] = function_exists( 'ini_get' ) ? (int) ini_get( 'upload_max_filesize' ) : __( 'N/A (ini_get function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get PHP max post size.
		$wsfwp_system_status['php_max_post'] = function_exists( 'ini_get' ) ? (int) ini_get( 'post_max_size' ) : __( 'N/A (ini_get function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get the PHP architecture.
		if ( PHP_INT_SIZE == 4 ) {
			$wsfwp_system_status['php_architecture'] = '32-bit';
		} elseif ( PHP_INT_SIZE == 8 ) {
			$wsfwp_system_status['php_architecture'] = '64-bit';
		} else {
			$wsfwp_system_status['php_architecture'] = 'N/A';
		}

		// Get server host name.
		$wsfwp_system_status['server_hostname'] = function_exists( 'gethostname' ) ? gethostname() : __( 'N/A (gethostname function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Show the number of processes currently running on the server.
		$wsfwp_system_status['processes'] = function_exists( 'exec' ) ? @exec( 'ps aux | wc -l' ) : __( 'N/A (make sure exec is enabled)', 'wallet-system-for-woocommerce-pro' );

		// Get the memory usage.
		$wsfwp_system_status['memory_usage'] = function_exists( 'memory_get_peak_usage' ) ? round( memory_get_peak_usage( true ) / 1024 / 1024, 2 ) : 0;

		// Get CPU usage.
		// Check to see if system is Windows, if so then use an alternative since sys_getloadavg() won't work.
		if ( stristr( PHP_OS, 'win' ) ) {
			$wsfwp_system_status['is_windows'] = true;
			$wsfwp_system_status['windows_cpu_usage'] = function_exists( 'exec' ) ? @exec( 'wmic cpu get loadpercentage /all' ) : __( 'N/A (make sure exec is enabled)', 'wallet-system-for-woocommerce-pro' );
		}

		// Get the memory limit.
		$wsfwp_system_status['memory_limit'] = function_exists( 'ini_get' ) ? (int) ini_get( 'memory_limit' ) : __( 'N/A (ini_get function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get the PHP maximum execution time.
		$wsfwp_system_status['php_max_execution_time'] = function_exists( 'ini_get' ) ? ini_get( 'max_execution_time' ) : __( 'N/A (ini_get function does not exist)', 'wallet-system-for-woocommerce-pro' );

		// Get outgoing IP address.
		$wsfwp_system_status['outgoing_ip'] = function_exists( 'file_get_contents' ) ? file_get_contents( 'http://ipecho.net/plain' ) : __( 'N/A (file_get_contents function does not exist)', 'wallet-system-for-woocommerce-pro' );

		$wsfwp_system_data['php'] = $wsfwp_system_status;
		$wsfwp_system_data['wp']  = $wsfwp_wordpress_status;

		return $wsfwp_system_data;
	}

	/**
	 * Generate html components.
	 *
	 * @param  string $wsfwp_components    html to display.
	 * @since  1.0.0
	 */
	public function wps_wsfwp_plug_generate_html( $wsfwp_components = array() ) {
		if ( is_array( $wsfwp_components ) && ! empty( $wsfwp_components ) ) {
			foreach ( $wsfwp_components as $wsfwp_component ) {
				if ( ! empty( $wsfwp_component['type'] ) && ! empty( $wsfwp_component['id'] ) ) {
					switch ( $wsfwp_component['type'] ) {

						case 'hidden':
						case 'number':
						case 'email':
						case 'text':
							?>
						<div class="wps-form-group wps-wws-<?php echo esc_attr( $wsfwp_component['type'] ); ?>">
							<div class="wps-form-group__label">
								<label for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" class="wps-form-label"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
							</div>
							<div class="wps-form-group__control">
								<label class="mdc-text-field mdc-text-field--outlined">
									<span class="mdc-notched-outline">
										<span class="mdc-notched-outline__leading"></span>
										<span class="mdc-notched-outline__notch">
											<?php if ( 'number' != $wsfwp_component['type'] ) { ?>
												<span class="mdc-floating-label" id="my-label-id" style=""><?php echo ( isset( $wsfwp_component['placeholder'] ) ? esc_attr( $wsfwp_component['placeholder'] ) : '' ); ?></span>
											<?php } ?>
										</span>
										<span class="mdc-notched-outline__trailing"></span>
									</span>
									<input
									class="mdc-text-field__input <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>" 
									name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
									id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"
									type="<?php echo esc_attr( $wsfwp_component['type'] ); ?>"
									value="<?php echo ( isset( $wsfwp_component['value'] ) ? esc_attr( $wsfwp_component['value'] ) : '' ); ?>"
									placeholder="<?php echo ( isset( $wsfwp_component['placeholder'] ) ? esc_attr( $wsfwp_component['placeholder'] ) : '' ); ?>"
									min="<?php echo ( isset( $wsfwp_component['min'] ) ? esc_attr( $wsfwp_component['min'] ) : '' ); ?>"
									<?php
									if ( ! empty( $wsfwp_component['step'] ) ) {
										?>
											step="<?php echo esc_attr( $wsfwp_component['step'] ); ?>"
											<?php
									}
									?>
									>
								</label>
								<br>
							
								<div class="mdc-text-field-helper-line">
											<div class="mdc-text-field-helper-text--persistent wps-helper-text" id="" aria-hidden="true"><?php echo ( isset( $wsfwp_component['description'] ) ? esc_attr( $wsfwp_component['description'] ) : '' ); ?></div>
								</div>
							</div>
						</div>
							<?php
							break;

						case 'password':
							?>
						<div class="wps-form-group">
							<div class="wps-form-group__label">
								<label for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" class="wps-form-label"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
							</div>
							<div class="wps-form-group__control">
								<label class="mdc-text-field mdc-text-field--outlined mdc-text-field--with-trailing-icon">
									<span class="mdc-notched-outline">
										<span class="mdc-notched-outline__leading"></span>
										<span class="mdc-notched-outline__notch">
										</span>
										<span class="mdc-notched-outline__trailing"></span>
									</span>
									<input 
									class="mdc-text-field__input <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?> wps-form__password" 
									name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
									id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"
									type="<?php echo esc_attr( $wsfwp_component['type'] ); ?>"
									value="<?php echo ( isset( $wsfwp_component['value'] ) ? esc_attr( $wsfwp_component['value'] ) : '' ); ?>"
									placeholder="<?php echo ( isset( $wsfwp_component['placeholder'] ) ? esc_attr( $wsfwp_component['placeholder'] ) : '' ); ?>"
									>
									<i class="material-icons mdc-text-field__icon mdc-text-field__icon--trailing wps-password-hidden" tabindex="0" role="button">visibility</i>
								</label>
								<div class="mdc-text-field-helper-line">
									<div class="mdc-text-field-helper-text--persistent wps-helper-text" id="" aria-hidden="true"><?php echo ( isset( $wsfwp_component['description'] ) ? esc_attr( $wsfwp_component['description'] ) : '' ); ?></div>
								</div>
							</div>
						</div>
							<?php
							break;

						case 'textarea':
							?>
						<div class="wps-form-group">
							<div class="wps-form-group__label">
								<label class="wps-form-label" for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
							</div>
							<div class="wps-form-group__control">
								<label class="mdc-text-field mdc-text-field--outlined mdc-text-field--textarea"  	for="text-field-hero-input">
									<span class="mdc-notched-outline">
										<span class="mdc-notched-outline__leading"></span>
										<span class="mdc-notched-outline__notch">
											<span class="mdc-floating-label"><?php echo ( isset( $wsfwp_component['placeholder'] ) ? esc_attr( $wsfwp_component['placeholder'] ) : '' ); ?></span>
										</span>
										<span class="mdc-notched-outline__trailing"></span>
									</span>
									<span class="mdc-text-field__resizer">
										<textarea class="mdc-text-field__input <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>" rows="2" cols="25" aria-label="Label" name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>" id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" placeholder="<?php echo ( isset( $wsfwp_component['placeholder'] ) ? esc_attr( $wsfwp_component['placeholder'] ) : '' ); ?>"><?php echo ( isset( $wsfwp_component['value'] ) ? esc_textarea( $wsfwp_component['value'] ) : '' ); // WPCS: XSS ok. ?></textarea>
									</span>
								</label>

							</div>
						</div>

							<?php
							break;

						case 'select':
						case 'multiselect':
							?>
						<div class="wps-form-group">
							<div class="wps-form-group__label">
								<label class="wps-form-label" for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
							</div>
							<div class="wps-form-group__control">
								<div class="wps-form-select">
									<select id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : '' ); ?><?php echo ( 'multiselect' === $wsfwp_component['type'] ) ? '[]' : ''; ?>" id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" class="mdl-textfield__input <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>" <?php echo 'multiselect' === $wsfwp_component['type'] ? 'multiple="multiple"' : ''; ?> >
										<?php
										foreach ( $wsfwp_component['options'] as $wsfwp_key => $wsfwp_val ) {
											?>
											<option value="<?php echo esc_attr( $wsfwp_key ); ?>"
												<?php
												if ( is_array( $wsfwp_component['value'] ) ) {
													selected( in_array( (string) $wsfwp_key, $wsfwp_component['value'], true ), true );
												} else {
													selected( $wsfwp_component['value'], (string) $wsfwp_key );
												}
												?>
												>
												<?php echo esc_html( $wsfwp_val ); ?>
											</option>
											<?php
										}
										?>
									</select>
									<label class="mdl-textfield__label" for="octane"><?php echo ( isset( $wsfwp_component['description'] ) ? esc_attr( $wsfwp_component['description'] ) : '' ); ?></label>
								</div>
							</div>
						</div>

							<?php
							break;

						case 'checkbox':
							?>
						<div class="wps-form-group">
							<div class="wps-form-group__label">
								<label for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" class="wps-form-label"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
							</div>
							<div class="wps-form-group__control wps-pl-4">
								<div class="mdc-form-field">
									<div class="mdc-checkbox">
										<input 
										name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
										id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"
										type="checkbox"
										class="mdc-checkbox__native-control <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>"
										value="<?php echo ( isset( $wsfwp_component['value'] ) ? esc_attr( $wsfwp_component['value'] ) : '' ); ?>"
										<?php checked( $wsfwp_component['value'], '1' ); ?>
										/>
										<div class="mdc-checkbox__background">
											<svg class="mdc-checkbox__checkmark" viewBox="0 0 24 24">
												<path class="mdc-checkbox__checkmark-path" fill="none" d="M1.73,12.91 8.1,19.28 22.79,4.59"/>
											</svg>
											<div class="mdc-checkbox__mixedmark"></div>
										</div>
										<div class="mdc-checkbox__ripple"></div>
									</div>
									<label for="checkbox-1"><?php echo ( isset( $wsfwp_component['description'] ) ? esc_attr( $wsfwp_component['description'] ) : '' ); ?></label>
								</div>
							</div>
						</div>
							<?php
							break;

						case 'radio':
							?>
						<div class="wps-form-group">
							<div class="wps-form-group__label">
								<label for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" class="wps-form-label"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
							</div>
							<div class="wps-form-group__control wps-pl-4">
								<div class="wps-flex-col">
									<?php
									foreach ( $wsfwp_component['options'] as $wsfwp_radio_key => $wsfwp_radio_val ) {
										?>
										<div class="mdc-form-field">
											<div class="mdc-radio">
												<input
												name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
												value="<?php echo esc_attr( $wsfwp_radio_key ); ?>"
												type="radio"
												class="mdc-radio__native-control <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>"
												<?php checked( $wsfwp_radio_key, $wsfwp_component['value'] ); ?>
												>
												<div class="mdc-radio__background">
													<div class="mdc-radio__outer-circle"></div>
													<div class="mdc-radio__inner-circle"></div>
												</div>
												<div class="mdc-radio__ripple"></div>
											</div>
											<label for="radio-1"><?php echo esc_html( $wsfwp_radio_val ); ?></label>
										</div>	
										<?php
									}
									?>
								</div>
							</div>
						</div>
							<?php
							break;

						case 'radio-switch':
							?>

						<div class="wps-form-group">
							<div class="wps-form-group__label">
								<label for="" class="wps-form-label"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
							</div>
							<div class="wps-form-group__control">
								<div>
									<div class="mdc-switch">
										<div class="mdc-switch__track"></div>
										<div class="mdc-switch__thumb-underlay">
											<div class="mdc-switch__thumb"></div>
											<input name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
											type="checkbox" id="<?php echo esc_html( $wsfwp_component['id'] ); ?>" value="on" class="mdc-switch__native-control <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>" role="switch" aria-checked="
											<?php
											if ( 'on' == $wsfwp_component['value'] ) {
												echo 'true';
											} else {
												echo 'false';
											}
											?>
"
											<?php checked( $wsfwp_component['value'], 'on' ); ?>
											>
										</div>
									</div>
								</div>
								<div class="mdc-text-field-helper-line">
									<div class="mdc-text-field-helper-text--persistent wps-helper-text" id="" aria-hidden="true"><?php echo ( isset( $wsfwp_component['description'] ) ? esc_attr( $wsfwp_component['description'] ) : '' ); ?></div>
								</div>
							</div>
						</div>
							<?php
							break;

						case 'button':
							?>
						<div class="wps-form-group">
							<div class="wps-form-group__label"></div>
							<div class="wps-form-group__control">
								<button class="mdc-button mdc-button--raised" name= "<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
									id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"> <span class="mdc-button__ripple"></span>
									<span class="mdc-button__label <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>"><?php echo ( isset( $wsfwp_component['button_text'] ) ? esc_html( $wsfwp_component['button_text'] ) : '' ); ?></span>
								</button>
							</div>
						</div>

							<?php
							break;

						case 'multi':
							?>
							<div class="wps-form-group wps-isfw-<?php echo esc_attr( $wsfwp_component['type'] ); ?>">
								<div class="wps-form-group__label">
									<label for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" class="wps-form-label"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
									</div>
									<div class="wps-form-group__control">
									<?php
									foreach ( $wsfwp_component['value'] as $component ) {
										?>
											<label class="mdc-text-field mdc-text-field--outlined">
												<span class="mdc-notched-outline">
													<span class="mdc-notched-outline__leading"></span>
													<span class="mdc-notched-outline__notch">
														<?php if ( 'number' != $component['type'] ) { ?>
															<span class="mdc-floating-label" id="my-label-id" style=""><?php echo ( isset( $wsfwp_component['placeholder'] ) ? esc_attr( $wsfwp_component['placeholder'] ) : '' ); ?></span>
														<?php } ?>
													</span>
													<span class="mdc-notched-outline__trailing"></span>
												</span>
												<input 
												class="mdc-text-field__input <?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>" 
												name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
												id="<?php echo esc_attr( $component['id'] ); ?>"
												type="<?php echo esc_attr( $component['type'] ); ?>"
												value="<?php echo ( isset( $wsfwp_component['value'] ) ? esc_attr( $wsfwp_component['value'] ) : '' ); ?>"
												placeholder="<?php echo ( isset( $wsfwp_component['placeholder'] ) ? esc_attr( $wsfwp_component['placeholder'] ) : '' ); ?>"
												<?php echo esc_attr( ( 'number' === $component['type'] ) ? 'max=10 min=0' : '' ); ?>
												>
											</label>
								<?php } ?>
									<div class="mdc-text-field-helper-line">
										<div class="mdc-text-field-helper-text--persistent wps-helper-text" id="" aria-hidden="true"><?php echo ( isset( $wsfwp_component['description'] ) ? esc_attr( $wsfwp_component['description'] ) : '' ); ?></div>
									</div>
								</div>
							</div>
								<?php
							break;
						case 'color':
						case 'date':
						case 'file':
							?>
							<div class="wps-form-group wps-isfw-<?php echo esc_attr( $wsfwp_component['type'] ); ?>">
								<div class="wps-form-group__label">
									<label for="<?php echo esc_attr( $wsfwp_component['id'] ); ?>" class="wps-form-label"><?php echo ( isset( $wsfwp_component['title'] ) ? esc_html( $wsfwp_component['title'] ) : '' ); // WPCS: XSS ok. ?></label>
								</div>
								<div class="wps-form-group__control">
									<label class="mdc-text-field mdc-text-field--outlined">
										<input 
										class="<?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>" 
										name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
										id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"
										type="<?php echo esc_attr( $wsfwp_component['type'] ); ?>"
										value="<?php echo ( isset( $wsfwp_component['value'] ) ? esc_attr( $wsfwp_component['value'] ) : '' ); ?>"
										<?php echo esc_html( ( 'date' === $wsfwp_component['type'] ) ? 'max=' . gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-d', mktime() ) . ' + 365 day' ) ) . 'min=' . gmdate( 'Y-m-d' ) . '' : '' ); ?>
										>
									</label>
									<div class="mdc-text-field-helper-line">
										<div class="mdc-text-field-helper-text--persistent wps-helper-text" id="" aria-hidden="true"><?php echo ( isset( $wsfwp_component['description'] ) ? esc_attr( $wsfwp_component['description'] ) : '' ); ?></div>
									</div>
								</div>
							</div>
							<?php
							break;

						case 'submit':
							?>
						<tr valign="top">
							<td scope="row">
								<input type="submit" class="wps-btn wps-btn__filled" 
								name="<?php echo ( isset( $wsfwp_component['name'] ) ? esc_html( $wsfwp_component['name'] ) : esc_html( $wsfwp_component['id'] ) ); ?>"
								id="<?php echo esc_attr( $wsfwp_component['id'] ); ?>"
								class="<?php echo ( isset( $wsfwp_component['class'] ) ? esc_attr( $wsfwp_component['class'] ) : '' ); ?>"
								value="<?php echo esc_attr( $wsfwp_component['button_text'] ); ?>"
								/>
							</td>
						</tr>
							<?php
							break;

						default:
							break;
					}
				}
			}
		}
	}
}
