<?php
/**
 * Order Factory
 *
 * The WooCommerce order factory creating the right order objects.
 *
 * @version 2.5.0
 * @package Wallet_System_For_Woocommerce
 */

 use Automattic\WooCommerce\Utilities\OrderUtil;
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/admin
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string $hook      The plugin page slug.
	 */
	public function wsfwp_admin_enqueue_styles( $hook ) {
		$screen = get_current_screen();

		if ( isset( $screen->id ) && ( 'wp-swings_page_wallet_system_for_woocommerce_menu' == $screen->id || 'wp-swings_page_wallet_coupon_for_woocommerce_menu' == $screen->id || 'wpswings_page_wallet_system_for_woocommerce_menu' == $screen->id ) ) {

			wp_enqueue_style( 'wps-wws-select2-css', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/select-2/wallet-system-for-woocommerce-pro-select2.css', array(), time(), 'all' );

			wp_enqueue_style( 'wps-wws-meterial-css', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/material-design/material-components-web.min.css', array(), time(), 'all' );
			wp_enqueue_style( 'wps-wws-meterial-css2', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/material-design/material-components-v5.0-web.min.css', array(), time(), 'all' );
			wp_enqueue_style( 'wps-wws-meterial-lite', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/material-design/material-lite.min.css', array(), time(), 'all' );

			wp_enqueue_style( 'wps-wws-meterial-icons-css', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/material-design/icon.css', array(), time(), 'all' );

			wp_enqueue_style( $this->plugin_name . '-admin-globalse', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/src/scss/wallet-system-for-woocommerce-pro-admin-global.css', array( 'wps-wws-meterial-icons-css' ), time(), 'all' );

			wp_enqueue_style( 'wps-admin-min-css', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/css/wps-admin.min.css', array(), $this->version, 'all' );
		}
		if ( isset( $screen->id ) && ( 'wp-swings_page_wallet_system_for_woocommerce_menu' == $screen->id || 'wpswings_page_wallet_system_for_woocommerce_menu' == $screen->id ) ) {
			wp_enqueue_style( $this->plugin_name . '-admin-global', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/css/wallet-system-for-woocommerce-pro-admin-extra.css', array(), time(), 'all' );
		}

		if ( isset( $screen->id ) && ( 'wps_cpt_coupons' === $screen->id || 'edit-wps_cpt_coupons' === $screen->id ) ) {

			wp_enqueue_style( $this->plugin_name . '-admin-wallet-coupon.css', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/src/scss/wallet-system-for-woocommerce-pro-wallet-coupon.css', array(), time(), 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string $hook      The plugin page slug.
	 */
	public function wsfwp_admin_enqueue_scripts( $hook ) {

		$screen = get_current_screen();

		if ( isset( $screen->id ) && ( 'wp-swings_page_wallet_system_for_woocommerce_pro_menu' === $screen->id || 'wp-swings_page_wallet_system_for_woocommerce_menu' === $screen->id || 'wpswings_page_wallet_system_for_woocommerce_menu' == $screen->id || 'wp-swings_page_wallet_coupon_for_woocommerce_menu' === $screen->id ) ) {
			wp_enqueue_script( 'wps-wws-select2', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/select-2/wallet-system-for-woocommerce-pro-select2.js', array( 'jquery' ), time(), false );

			wp_enqueue_script( 'wps-wws-metarial-js', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/material-design/material-components-web.min.js', array(), time(), false );
			wp_enqueue_script( 'wps-wws-metarial-js2', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/material-design/material-components-v5.0-web.min.js', array(), time(), false );
			wp_enqueue_script( 'wps-wws-metarial-lite', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/material-design/material-lite.min.js', array(), time(), false );

			wp_register_script( $this->plugin_name . 'admin-js', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/src/js/wallet-system-for-woocommerce-pro-admin.js', array( 'jquery', 'wps-wws-select2', 'wps-wws-metarial-js', 'wps-wws-metarial-js2', 'wps-wws-metarial-lite' ), $this->version, false );

			wp_localize_script(
				$this->plugin_name . 'admin-js',
				'wsfwp_admin_param',
				array(
					'ajaxurl'                       => admin_url( 'admin-ajax.php' ),
					'reloadurl'                     => admin_url( 'admin.php?page=wallet_system_for_woocommerce_pro_menu' ),
					'wsfwp_gen_tab_enable'            => get_option( 'wsfwp_radio_switch_demo' ),
					'wsfwp_ajax_error'                => __( 'An error occured!', 'wallet-system-for-woocommerce-pro' ),
					'wsfwp_license_field_error'       => __( 'Please enter license key.', 'wallet-system-for-woocommerce-pro' ),
					'minimum_wallet_recharge_error' => __( 'Minimum wallet recharge amount should be less than maximum wallet recharge amount.', 'wallet-system-for-woocommerce-pro' ),
					'maximum_wallet_recharge_error' => __( 'Maximum wallet recharge amount should be greater than minimum wallet recharge amount.', 'wallet-system-for-woocommerce-pro' ),
					'minimum_wallet_transfer_error' => __( 'Minimum wallet transfer amount should be less than maximum wallet transfer amount.', 'wallet-system-for-woocommerce-pro' ),
					'maximum_wallet_transfer_error' => __( 'Maximum wallet transfer amount should be greater than minimum wallet transfer amount.', 'wallet-system-for-woocommerce-pro' ),
					'minimum_wallet_widthdrawal_error' => __( 'Minimum wallet widthdrawal amount should be less than maximum wallet widthdrawal amount.', 'wallet-system-for-woocommerce-pro' ),
					'maximum_wallet_widthdrawal_error' => __( 'Maximum wallet widthdrawal amount should be greater than minimum wallet widthdrawal amount.', 'wallet-system-for-woocommerce-pro' ),
					'wsfwp_admin_param_location'      => ( admin_url( 'admin.php' ) . '?page=wallet_system_for_woocommerce_menu&wsfw_tab=wallet-system-for-woocommerce-general' ),
					'nonce'                           => wp_create_nonce( 'check-nonce' ),
					'remove' => __( 'Remove', 'wallet-system-for-woocommerce-pro' ),

				)
			);

			wp_enqueue_script( $this->plugin_name . 'admin-js' );
		}
		if ( isset( $screen->id ) && ( 'wp-swings_page_wallet_system_for_woocommerce_menu' === $screen->id || 'wpswings_page_wallet_system_for_woocommerce_menu' == $screen->id ) ) {
			wp_register_script( $this->plugin_name . 'admin-js', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/src/js/wallet-system-for-woocommerce-pro-admin.js', array( 'jquery' ), $this->version, false );

			wp_localize_script(
				$this->plugin_name . 'admin-js',
				'wsfwp_admin_param',
				array(
					'ajaxurl'                       => admin_url( 'admin-ajax.php' ),
					'reloadurl'                     => admin_url( 'admin.php?page=wallet_system_for_woocommerce_pro_menu' ),
					'wsfwp_gen_tab_enable'            => get_option( 'wsfwp_radio_switch_demo' ),
					'wsfwp_ajax_error'                => __( 'An error occured!', 'wallet-system-for-woocommerce-pro' ),
					'wsfwp_license_field_error'       => __( 'Please enter license key.', 'wallet-system-for-woocommerce-pro' ),
					'minimum_wallet_recharge_error' => __( 'Minimum wallet recharge amount should be less than maximum wallet recharge amount.', 'wallet-system-for-woocommerce-pro' ),
					'maximum_wallet_recharge_error' => __( 'Maximum wallet recharge amount should be greater than minimum wallet recharge amount.', 'wallet-system-for-woocommerce-pro' ),
					'minimum_wallet_transfer_error' => __( 'Minimum wallet transfer amount should be less than maximum wallet transfer amount.', 'wallet-system-for-woocommerce-pro' ),
					'maximum_wallet_transfer_error' => __( 'Maximum wallet transfer amount should be greater than minimum wallet transfer amount.', 'wallet-system-for-woocommerce-pro' ),
					'minimum_wallet_widthdrawal_error' => __( 'Minimum wallet widthdrawal amount should be less than maximum wallet widthdrawal amount.', 'wallet-system-for-woocommerce-pro' ),
					'maximum_wallet_widthdrawal_error' => __( 'Maximum wallet widthdrawal amount should be greater than minimum wallet widthdrawal amount.', 'wallet-system-for-woocommerce-pro' ),
					'wsfwp_admin_param_location'      => ( admin_url( 'admin.php' ) . '?page=wallet_system_for_woocommerce_menu&wsfw_tab=wallet-system-for-woocommerce-general' ),
					'negative_value_message_error' => __( 'Enter value greater than 0.', 'wallet-system-for-woocommerce-pro' ),
					'datatable_pagination_text'     => __( 'Rows per page _MENU_', 'wallet-system-for-woocommerce-pro' ),
					'datatable_info'                => __( '_START_ - _END_ of _TOTAL_', 'wallet-system-for-woocommerce-pro' ),
					'datatable_excel'               => __( 'Export Excel', 'wallet-system-for-woocommerce-pro' ),
					'datatable_csv'                 => __( 'Export CSV', 'wallet-system-for-woocommerce-pro' ),
					'nonce'                           => wp_create_nonce( 'check-nonce' ),
					'remove' => __( 'Remove', 'wallet-system-for-woocommerce-pro' ),
				)
			);

			wp_enqueue_script( $this->plugin_name . 'admin-js' );

			if ( ! empty( $_REQUEST['wsfw_tab'] ) ) {
				if ( 'wallet-system-wallet-transactions' === $_REQUEST['wsfw_tab'] || 'wps-user-wallet-transactions' === $_REQUEST['wsfw_tab'] ) {
					wp_enqueue_script( 'wps-wws-datetable-button', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/datatable/dataTables.buttons.js', array( 'wps-admin-min-js' ), WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION, true );
					wp_enqueue_script( 'wps-wws-datetable-zip', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/datatable/jszip.js', array( 'wps-admin-min-js' ), WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION, true );
					wp_enqueue_script( 'wps-wws-datetable-buttonhtml', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/datatable/buttons.html5.js', array( 'wps-admin-min-js' ), WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION, true );
					wp_enqueue_script( 'wps-wws-datetable-colvis', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'package/lib/datatable/buttons.colVis.js', array( 'wps-admin-min-js' ), WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION, true );
				}
			}
		}

		if ( isset( $screen->id ) && 'wps_cpt_coupons' === $screen->id ) {

			wp_register_script( $this->plugin_name . 'wallet-coupon-js', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/src/js/wallet-system-for-woocommerce-pro-wallet-coupon.js', array( 'jquery' ), $this->version, false );

			wp_localize_script(
				$this->plugin_name . 'wallet-coupon-js',
				'wsfwp_admin_coupon_param',
				array(
					'ajaxurl'                       => admin_url( 'admin-ajax.php' ),
					'reloadurl'                     => admin_url( 'admin.php?page=wallet_system_for_woocommerce_pro_menu' ),
					'wsfwp_gen_tab_enable'            => get_option( 'wsfwp_radio_switch_demo' ),
					'wsfwp_ajax_error'                => __( 'An error occured!', 'wallet-system-for-woocommerce-pro' ),
					'generate_button_wallet_coupon'  => __( 'Generate Wallet Coupon Code', 'wallet-system-for-woocommerce-pro' ),
					'characters'                     => apply_filters( 'woocommerce_coupon_code_generator_characters', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789' ),
					'char_length'                    => apply_filters( 'woocommerce_coupon_code_generator_character_length', 10 ),
					'prefix'                         => apply_filters( 'woocommerce_coupon_code_generator_prefix', '' ),
					'suffix'                         => apply_filters( 'woocommerce_coupon_code_generator_suffix', '' ),

				)
			);

			wp_enqueue_script( $this->plugin_name . 'wallet-coupon-js' );

		}

		if ( isset( $screen->id ) && 'edit-wps_cpt_coupons' === $screen->id ) {

			wp_enqueue_script( 'wps-wws-metarial-js', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/src/js/wallet-system-for-woocommerce-pro-coupon-page.js', array(), time(), false );

		}
		if ( isset( $screen->id ) && 'plugins' === $screen->id ) {

			wp_register_script( $this->plugin_name . 'notice-admin-js', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/src/js/wallet-system-for-woocommerce-pro-notice-admin.js', array( 'jquery' ), $this->version, false );

			wp_localize_script(
				$this->plugin_name . 'notice-admin-js',
				'wsfwp_admin_notice_param',
				array(
					'ajaxurl'                       => admin_url( 'admin-ajax.php' ),
					'reloadurl'                     => admin_url( 'admin.php?page=wallet_system_for_woocommerce_pro_menu' ),

					'wsfwp_ajax_error'                => __( 'An error occured!', 'wallet-system-for-woocommerce-pro' ),
					'wsfwp_admin_param_location'      => ( admin_url( 'admin.php' ) . '?page=wallet_system_for_woocommerce_menu&wsfw_tab=wallet-system-for-woocommerce-general' ),
					'nonce'                           => wp_create_nonce( 'check-nonce' ),
				)
			);

			wp_enqueue_script( $this->plugin_name . 'notice-admin-js' );

		}
	}

	/**
	 * Removing default submenu of parent menu in backend dashboard
	 *
	 * @since   1.0.0
	 */
	public function wps_wsfwp_remove_default_submenu() {
		global $submenu;
		if ( is_array( $submenu ) && array_key_exists( 'wps-plugins', $submenu ) ) {
			if ( isset( $submenu['wps-plugins'][0] ) ) {
				unset( $submenu['wps-plugins'][0] );
			}
		}
	}


	/**
	 * Wallet System for WooCommerce admin menu page.
	 *
	 * @since    1.0.0
	 */
	public function wsfw_options_menu_html__() {
		include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-admin-coupon.php';
	}

	/**
	 * Wallet System for WooCommerce Pro admin menu page.
	 *
	 * @since    1.0.0
	 */
	public function wsfwp_options_menu_html() {

		include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-admin-dashboard.php';
	}


	/**
	 * Wallet System for WooCommerce Pro admin menu page.
	 *
	 * @since    1.0.0
	 * @param array $wsfwp_settings_general Settings fields.
	 */
	public function wsfwp_admin_general_settings_page( $wsfwp_settings_general ) {
		if ( is_array( $wsfwp_settings_general ) && ! empty( $wsfwp_settings_general ) ) {
			$wsfwp_settings_general[] = array(
				'title'       => __( 'Message For Customer', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'text',
				'description' => __( 'Enter message for customer at the time of withdrawal request', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_withdrawal_page_message',
				'id'          => 'wsfwp_withdrawal_page_message',
				'value'       => get_option( 'wsfwp_withdrawal_page_message' ),
				'placeholder' => __( 'message', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			);
			$wsfwp_settings_general[] = array(
				'title'       => __( 'Admin Email for wallet Withdrawal Request', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'text',
				'description' => __( 'Enter the admin mail id to get the update of withdrawal request', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_withdrawal_admin_withdrawal_request_email',
				'id'          => 'wsfwp_withdrawal_admin_withdrawal_request_email',
				'value'       => get_option( 'wsfwp_withdrawal_admin_withdrawal_request_email' ),
				'placeholder' => __( 'Enter Email Id of Admin', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			);
		}
		return $wsfwp_settings_general;
	}



	/**
	 * Check whether order has user id as meta key
	 *
	 * @param int $user_id user id.
	 * @param int $order_id order id.
	 * @return int
	 */
	public function wsfwp_check_order_meta_for_userid( $user_id, $order_id ) {
		$wallet_userid = '';
		$order = wc_get_order( $order_id );
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			$wallet_userid = $order->get_meta( 'wps_wsfwp_user_id', true );
		} else {
			$wallet_userid = get_post_meta( $order_id, 'wps_wsfwp_user_id', true );
		}
		return $wallet_userid;
	}

	/**
	 * Check whether order has recharge_reason as meta key
	 *
	 * @param int    $order_id order id.
	 * @param string $recharge_reason reason for recharge.
	 * @return string
	 */
	public function wsfwp_check_order_meta_for_recharge_reason( $order_id, $recharge_reason = '' ) {
		$recharge_reason = '';
		$order = wc_get_order( $order_id );
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			$recharge_reason = $order->get_meta( 'wps_wsfwp_recharge_reason', true );
		} else {
			$recharge_reason = get_post_meta( $order_id, 'wps_wsfwp_recharge_reason', true );
		}
		return $recharge_reason;
	}

	/**
	 * Check license key is valid or not.
	 *
	 * @return void
	 */
	public function wps_wsfwp_check_license() {

		$user_license_key = get_option( 'wps_wsfwp_lic_key', false );
		$server_name   = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '';

		$api_params = array(
			'slm_action'        => 'slm_check',
			'secret_key'        => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_SPECIAL_SECRET_KEY,
			'license_key'       => $user_license_key,
			'_registered_domain' => $server_name,
			'item_reference'    => urlencode( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_ITEM_REFERENCE ),
			'product_reference' => 'WPSPK-67573',
		);

		$query = esc_url_raw( add_query_arg( $api_params, WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_LICENSE_SERVER_URL ) );
		$mwb_response = wp_remote_get(
			$query,
			array(
				'timeout' => 20,
				'sslverify' => false,
			)
		);
		$license_data = json_decode( wp_remote_retrieve_body( $mwb_response ) );
		if ( ! empty( $license_data ) && isset( $license_data ) ) {
			if ( isset( $license_data->result ) && 'success' === $license_data->result && isset( $license_data->status ) && 'active' === $license_data->status ) {

				if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
					require_once ABSPATH . '/wp-admin/includes/plugin.php';
				}
				if ( is_plugin_active_for_network( 'wallet-system-for-woocommerce-pro/wallet-system-for-woocommerce-pro.php' ) || is_multisite() ) {
					global $wpdb;
					foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
						switch_to_blog( $blog_id );
						update_option( 'wps_wsfwp_pro_valid_license', true );
						restore_current_blog();
					}
				} else {
					update_option( 'wps_wsfwp_pro_valid_license', true );

					 // Subscription Code.
					if ( isset( $license_data->subscr_status ) ) {
						if ( 'on-hold' === $license_data->subscr_status || 'cancelled' === $license_data->subscr_status ) {
							update_option( 'wps_wsfwp_pro_subscription_status', true );
						} else {
							update_option( 'wps_wsfwp_pro_subscription_status', false );
						}

						$today_date = gmdate( 'Y-m-d', strtotime( 'now' ) );
						$reminder_date = gmdate( 'Y-m-d', strtotime( '-5 days', strtotime( $license_data->date_renewed ) ) );

						if ( 'pending-cancel' === $license_data->subscr_status && ( $today_date >= $reminder_date && $today_date < $license_data->date_renewed ) ) {
							update_option( 'wps_wsfwp_pro_subscription_renewdate', $license_data->date_renewed );
						} else {
							delete_option( 'wps_wsfwp_pro_subscription_renewdate' );
						}
					}
				}
			} else {

				if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
					require_once ABSPATH . '/wp-admin/includes/plugin.php';
				}
				if ( is_plugin_active_for_network( 'wallet-system-for-woocommerce-pro/wallet-system-for-woocommerce-pro.php' ) || is_multisite() ) {
					global $wpdb;
					foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
						switch_to_blog( $blog_id );
						delete_option( 'wps_wsfwp_pro_valid_license' );
						restore_current_blog();
					}
				} else {
					delete_option( 'wps_wsfwp_pro_valid_license' );
				}
			}
		}
	}

	/**
	 * Validate license key.
	 *
	 * @return void
	 */
	public function wps_wsfwp_check_license_key_status() {
		check_ajax_referer( 'check-nonce', 'nonce' );

		self::wps_wsfwp_check_license();
		$wps_subscr_renewdate = get_option( 'wps_wsfwp_pro_subscription_renewdate', false );
		$wpss_subscr_status = get_option( 'wps_wsfwp_pro_subscription_status', false );
		if ( isset( $wpss_subscr_status ) && true == $wpss_subscr_status ) {

			echo json_encode(
				array(
					'status' => false,
					'msg' => __(
						'Subscription is not renewed yet.',
						'wallet-system-for-woocommerce-pro'
					),
				)
			);
		} else if ( ! empty( $wps_subscr_renewdate ) && '0000-00-00' != $wps_subscr_renewdate ) {

			echo json_encode(
				array(
					'status' => false,
					'msg' => __(
						'Subscription is not renewed yet.',
						'wallet-system-for-woocommerce-pro'
					),
				)
			);
		} else {
			echo json_encode(
				array(
					'status' => true,
					'msg' => __(
						'License Status Updated. Please Wait.',
						'wallet-system-for-woocommerce-pro'
					),
				)
			);
		}

		wp_die();
	}

	/**
	 * Function to show notification for subscription status.
	 *
	 * @return void
	 */
	public function wps_subscription_notification_html() {
		$screen = get_current_screen();
		if ( isset( $screen->id ) ) {
			$pagescreen = $screen->id;
		}
		if ( ( isset( $pagescreen ) && 'plugins' === $pagescreen ) || ( 'wp-swings_page_home' == $pagescreen ) || ( 'wp-swings_page_wallet_system_for_woocommerce_menu' == $pagescreen ) || 'wpswings_page_wallet_system_for_woocommerce_menu' == $pagescreen ) {
			$wps_subscr_status = get_option( 'wps_wsfwp_pro_subscription_status', false );
			if ( isset( $wps_subscr_status ) && true == $wps_subscr_status ) {

				?>
					<div class="wps-subsc_notice notice notice-warning is-dismissible">
					<p><?php esc_attr_e( 'Stay on track! Renew your subscription for', 'wallet-system-for-woocommerce-pro' ); ?><b><?php esc_attr_e( ' Wallet System For WooCommerce ', 'wallet-system-for-woocommerce-pro' ); ?></b><?php esc_attr_e( 'now to maintain uninterrupted service!', 'wallet-system-for-woocommerce-pro' ); ?> <a href="https://wpswings.com/my-account/subscriptions/" target="_blank"> <b><?php esc_attr_e( 'Renew Now', 'wallet-system-for-woocommerce-pro' ); ?></b></a>. <?php esc_attr_e( 'If already renewed', 'wallet-system-for-woocommerce-pro' ); ?>, <a href="#" id="wps_check_license"><b><?php esc_attr_e( 'Validate Now', 'wallet-system-for-woocommerce-pro' ); ?></b></a>.</p>
					</div>
				   
				<?php
			}

			$wps_subscr_renewdate = get_option( 'wps_wsfwp_pro_subscription_renewdate', false );
			$today_date = gmdate( 'Y-m-d', strtotime( 'now' ) );
			$reminder_date = gmdate( 'Y-m-d', strtotime( '-5 days', strtotime( $wps_subscr_renewdate ) ) );

			$date1 = new DateTime( $wps_subscr_renewdate );
			$date2 = new DateTime( $today_date );
			$interval = $date1->diff( $date2 );

			if ( isset( $wps_subscr_renewdate ) && ( $today_date >= $reminder_date && $today_date < $wps_subscr_renewdate ) ) {

				?>
					<div class="wps-subsc_notice notice notice-warning is-dismissible">
						<p><strong>
						<?php
						esc_html_e( 'Your Subscription Will Expire in ', 'wallet-system-for-woocommerce-pro' );
						echo esc_html( $interval->days );
						esc_html_e( ' days', 'wallet-system-for-woocommerce-pro' );
						?>
						.</strong><br>
						<?php esc_attr_e( 'Stay on track! Renew your subscription for', 'wallet-system-for-woocommerce-pro' ); ?><b><?php esc_attr_e( ' Wallet System For WooCommerce ', 'wallet-system-for-woocommerce-pro' ); ?></b><?php esc_attr_e( 'now to maintain uninterrupted service!', 'wallet-system-for-woocommerce-pro' ); ?> <a href="https://wpswings.com/my-account/subscriptions/" target="_blank"> <b><?php esc_attr_e( 'Renew Now', 'wallet-system-for-woocommerce-pro' ); ?></b></a>. <?php esc_attr_e( 'If already renewed', 'wallet-system-for-woocommerce-pro' ); ?>, <a href="#" id="wps_check_license"><b><?php esc_attr_e( 'Validate Now', 'wallet-system-for-woocommerce-pro' ); ?></b></a>.</p>
					</div>
				   
				<?php
			}
		}
	}


	/**
	 * Show message if license is not activated.
	 *
	 * @return void
	 */
	public function wps_wsfwp_show_additional_section() {
		$callname_lic         = Wallet_System_For_Woocommerce_Pro::$lic_callback_function;
		$callname_lic_initial = Wallet_System_For_Woocommerce_Pro::$lic_ini_callback_function;
		$day_count            = Wallet_System_For_Woocommerce_Pro::$callname_lic_initial();
		if ( $day_count > 0 ) {
			if ( ! get_option( 'wps_wsfwp_pro_valid_license', 0 ) ) {
				$day_count_warning = floor( $day_count );
				$day_string  = sprintf( '%s', $day_count_warning, number_format_i18n( $day_count_warning ) );
				$day_string .= esc_html__( ' days', 'wallet-system-for-woocommerce-pro' );
				?>
				<div class="thirty-days-notice wps-header-container wps-bg-white wps-r-8">
					<h1 class="wps-header-title">
						<p>
							<strong><a href="?page=wallet_system_for_woocommerce_menu&wsfw_tab=wallet-system-for-woocommerce-pro-license"><?php echo esc_html__( 'Activate', 'wallet-system-for-woocommerce-pro' ); ?></a>
							<?php
							esc_html_e( ' the license key before ', 'wallet-system-for-woocommerce-pro' );
							echo '<span id="wps-wsfw-day-count" >' . esc_html( $day_string ) . '</span>';
							esc_html_e( ' or you may risk losing data and the Wallet System for WooCommerce Pro plugin will also become dysfunctional.', 'wallet-system-for-woocommerce-pro' );
							?>
							</strong>
						</p>
					</h1>
				</div>
				<?php
			}
		} elseif ( ! get_option( 'wps_wsfwp_pro_valid_license', 0 ) ) {
			?>
		<div class="thirty-days-notice wps-header-container wps-bg-white wps-r-8">
			<h1 class="wps-header-title">
				<p>
					<strong><?php esc_html_e( ' Your trial period is over please activate license to use the Wallet System for WooCommerce Pro features.', 'wallet-system-for-woocommerce-pro' ); ?></strong>
				</p>
			</h1>
		</div>
				<?php

		}
	}

	/**
	 * Add license tab in plugin setting.
	 *
	 * @param array $default_tabs default setting tabs.
	 * @return array
	 */
	public function wps_wsfwp_plug_extra_tabs( $default_tabs ) {
		$new_tabs = array();
		if ( is_array( $default_tabs ) && ! empty( $default_tabs ) ) {
			foreach ( $default_tabs as $tab => $default_tab ) {
				if ( 'wallet-system-rest-api' === $tab ) {
					if ( ! get_option( 'wps_wsfwp_pro_valid_license', 0 ) ) {
						$new_tabs['wallet-system-for-woocommerce-pro-license'] = array(
							'title'     => esc_html__( 'Activate License', 'wallet-system-for-woocommerce-pro' ),
							'name'      => 'wallet-system-for-woocommerce-pro-license',
							'file_path' => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-license.php',
						);
					}
				}
				$new_tabs[ $tab ] = $default_tab;
			}
		}
		return $new_tabs;
	}


	/**
	 * New tab added for restriction function.
	 *
	 * @param [type] $default_tabs is tab for restriction.
	 * @return mixed
	 */
	public function wps_wsfwp_plug_wallet_restriction_tab( $default_tabs ) {
		$default_tabs['wallet-system-for-woocommerce-pro-withdrawal-setting-tab'] = array(
			'title' => esc_html__( 'Withdrawal Settings', 'wallet-system-for-woocommerce-pro' ),
			'name'  => 'wallet-system-for-woocommerce-pro-withdrawal-setting-tab',
			'file_path' => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-withdrawal-setting-tab.php',
		);

		$default_tabs['wallet-system-for-woocommerce-pro-wallet-restriction'] = array(
			'title'     => esc_html__( 'Wallet Regulation', 'wallet-system-for-woocommerce-pro' ),
			'name'      => 'wallet-system-for-woocommerce-pro-wallet-restriction',
			'file_path' => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-restriction.php',
		);
		$default_tabs['wallet-system-for-woocommerce-pro-wallet-promotions'] = array(
			'title'     => esc_html__( 'Wallet Promotions', 'wallet-system-for-woocommerce-pro' ),
			'name'      => 'wallet-system-for-woocommerce-pro-wallet-promotions',
			'file_path' => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-promotions.php',
		);
		$default_tabs['wallet-system-for-woocommerce-pro-wallet-recharge-tab'] = array(
			'title'     => esc_html__( 'Wallet Quick Recharge', 'wallet-system-for-woocommerce-pro' ),
			'name'      => 'wallet-system-for-woocommerce-pro-wallet-recharge-tab',
			'file_path' => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-recharge-tab.php',
		);

		return $default_tabs;
	}

	/**
	 * Return path for license page.
	 *
	 * @param string $path path of license page.
	 * @return string
	 */
	public function wps_wsfwp_template_path( $path ) {

		if ( defined( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_VERSION' ) ) {
			if ( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-license.php' === $path ) {
				$path = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-license.php';
			}
		}

		return $path;
	}

	/**
	 * Path for wallet restriction tab.
	 *
	 * @param [type] $path is the url for wallet restriction tab.
	 * @return mixed
	 */
	public function wps_wsfwp_template_path_wallet_restriction( $path ) {

		if ( defined( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_VERSION' ) ) {
			if ( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-restriction.php' === $path ) {
				$path = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-restriction.php';
			} elseif ( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-promotions.php' === $path ) {
				$path = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-promotions.php';
			} elseif ( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-recharge-tab.php' === $path ) {
				$path = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-wallet-recharge-tab.php';
			} elseif ( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-withdrawal-setting-tab.php' === $path ) {
				$path = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-withdrawal-setting-tab.php';
			}
		}

		return $path;
	}

	/**
	 * This is used to create comment html.
	 *
	 * @param array $wsfw_settings_template setting template.
	 * @return array
	 */
	public function wsfw_admin_wallet_action_settings_promotions_tab_array( $wsfw_settings_template ) {

		$wsfw_settings_template   = apply_filters( 'wsfw_wallet_action_comment_extra_settings_array', $wsfw_settings_template );
		$wsfw_settings_template[] = array(
			'type'        => 'submit',
			'name'        => 'wsfw_button_wallet_promotions_tab',
			'id'          => 'wsfw_button_wallet_promotions_tab',
			'button_text' => __( 'Save Settings', 'wallet-system-for-woocommerce-pro' ),
			'class'       => 'wsfw-button-class',
		);
		return $wsfw_settings_template;
	}

	/**
	 * This is used to create comment html.
	 *
	 * @param array $wsfw_settings_template setting template.
	 * @return array
	 */
	public function wsfw_admin_wallet_withdrawal_ettings_tab_array( $wsfw_settings_template ) {

		$wsfw_settings_template   = apply_filters( 'wsfw_button_wallet_withdrawal_paypal_tab_array', $wsfw_settings_template );
		$wsfw_settings_template[] = array(
			'type'        => 'submit',
			'name'        => 'wsfw_button_wallet_withdrawal_paypal_tab',
			'id'          => 'wsfw_button_wallet_withdrawal_paypal_tab',
			'button_text' => __( 'Save Settings', 'wallet-system-for-woocommerce-pro' ),
			'class'       => 'wsfw-button-class',
		);
		return $wsfw_settings_template;
	}



	/**
	 * This is used to enable wallet recharge.
	 *
	 * @param array $wsfw_settings_template setting template.
	 * @return array
	 */
	public function wsfw_wallet_action_recharge_enable_settings_tab( $wsfw_settings_template ) {

		$wsfw_settings_template   = apply_filters( 'wsfw_wallet_action_comment_extra_settings_array', $wsfw_settings_template );
		$wsfw_settings_template[] = array(
			'title'       => __( 'Enable Wallet Recharge Tab', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'radio-switch',
			'description' => '',
			'name'        => 'wps_wsfwp_wallet_recharge_tab_enable',
			'id'          => 'wps_wsfwp_wallet_recharge_tab_enable',
			'value'       => 'on',
			'class'       => 'wsfw-radio-switch-class',
			'options'     => array(
				'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
				'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
			),
		);
		return $wsfw_settings_template;
	}


	/**
	 * This is used to enable wallet promotions.
	 *
	 * @param array $wsfw_settings_template setting template.
	 * @return array
	 */
	public function wsfw_wallet_action_promotion_enable_settings_tab( $wsfw_settings_template ) {

		$wsfw_settings_template   = apply_filters( 'wsfw_wallet_action_comment_extra_settings_array', $wsfw_settings_template );
		$wsfw_settings_template[] = array(
			'title'       => __( 'Enable Wallet Promotions Tab', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'radio-switch',
			'description' => '',
			'name'        => 'wps_wsfwp_wallet_promotion_tab_enable',
			'id'          => 'wps_wsfwp_wallet_promotion_tab_enable',
			'value'       => 'on',
			'class'       => 'wsfw-radio-switch-class',
			'options'     => array(
				'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
				'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
			),

		);
		$wsfw_settings_template[] = array(
			'title'       => __( 'Enable Wallet Limited Offer Timer', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'radio-switch',
			'description' => '',
			'name'        => 'wps_wsfwp_wallet_promotion_tab_limited_offer_enable',
			'id'          => 'wps_wsfwp_wallet_promotion_tab_limited_offer_enable',
			'value'       => 'on',
			'class'       => 'wsfw-radio-switch-class',
			'options'     => array(
				'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
				'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
			),

		);
		return $wsfw_settings_template;
	}

		/**
		 * This is used to enable wallet promotions.
		 *
		 * @param array $wsfw_settings_template setting template.
		 * @return array
		 */
	public function wsfw_wallet_withdrawal_enable_settings_tab( $wsfw_settings_template ) {

		$wsfw_settings_template   = apply_filters( 'wsfw_wallet_action_comment_extra_settings_array', $wsfw_settings_template );
		$wsfw_settings_template[] = array(
			'title'       => __( 'Enable Wallet Withdrawal through Paypal', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'radio-switch',
			'description' => '',
			'name'        => 'wps_wsfwp_wallet_withdrawal_paypal_enable',
			'id'          => 'wps_wsfwp_wallet_withdrawal_paypal_enable',
			'value'       => 'on',
			'class'       => 'wsfw-radio-switch-class',
			'options'     => array(
				'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
				'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
			),

		);
		$wsfw_settings_template[] = array(
			'title'       => __( 'Enable to show Dropdown to Customer for Manual Withdrawal and Paypal Withdrawal', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'radio-switch',
			'description' => '',
			'name'        => 'wps_wsfwp_wallet_withdrawal_paypal_dropdown',
			'id'          => 'wps_wsfwp_wallet_withdrawal_paypal_dropdown',
			'value'       => 'on',
			'class'       => 'wsfw-radio-switch-class',
			'options'     => array(
				'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
				'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
			),

		);
		$wsfw_settings_template[] = array(
			'title'       => __( 'Enter Paypal Client ID', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'text',
			'description' => __( 'Please enter paypal Client ID', 'wallet-system-for-woocommerce-pro' ),
			'name'        => 'wps_wsfwp_wallet_withdrawal_paypal_enable_client_id',
			'id'          => 'wps_wsfwp_wallet_withdrawal_paypal_enable_client_id',
			'value'       => get_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_client_id' ),
			'placeholder' => __( 'Please enter paypal Client ID', 'wallet-system-for-woocommerce-pro' ),
			'class'       => 'wws-text-class',

		);
		$wsfw_settings_template[] = array(
			'title'       => __( 'Enter Paypal Secret Key', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'text',
			'description' => __( 'Please enter paypal Secret Key', 'wallet-system-for-woocommerce-pro' ),
			'name'        => 'wps_wsfwp_wallet_withdrawal_paypal_enable_sceret_key',
			'id'          => 'wps_wsfwp_wallet_withdrawal_paypal_enable_sceret_key',
			'value'       => get_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_sceret_key' ),
			'placeholder' => __( 'Please enter paypal Secret Key', 'wallet-system-for-woocommerce-pro' ),
			'class'       => 'wws-text-class',

		);
		$wsfw_settings_template[] = array(
			'title'       => __( 'Select paypal Mode', 'wallet-system-for-woocommerce-pro' ),
			'type'        => 'select',
			'description' => __( 'Select paypal mode type live or test.', 'wallet-system-for-woocommerce-pro' ),
			'name'        => 'wps_wsfwp_wallet_withdrawal_paypal_enable_mode',
			'id'          => 'wps_wsfwp_wallet_withdrawal_paypal_enable_mode',
			'value'       => get_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_mode', 'test' ),
			'class'       => 'wsfw-radio-switch-class',
			'options'     => apply_filters(
				'wsfw_withdrawal_paypal_mode_type__array',
				array(
					'live' => __( 'Live Mode', 'wallet-system-for-woocommerce-pro' ),
					'test'   => __( 'Test Mode', 'wallet-system-for-woocommerce-pro' ),
				)
			),
		);

		return $wsfw_settings_template;
	}




	/**
	 * This is used to create comment html.
	 *
	 * @param array $wsfw_settings_template setting template.
	 * @return array
	 */
	public function wsfw_admin_wallet_action_settings_recharge_tab_array( $wsfw_settings_template ) {

		$wsfw_settings_template   = apply_filters( 'wsfw_wallet_action_comment_extra_settings_array', $wsfw_settings_template );
		$wsfw_settings_template[] = array(
			'type'        => 'submit',
			'name'        => 'wsfw_button_wallet_recharge_tab',
			'id'          => 'wsfw_button_wallet_recharge_tab',
			'button_text' => __( 'Save Settings', 'wallet-system-for-woocommerce-pro' ),
			'class'       => 'wsfw-button-class',
		);
		return $wsfw_settings_template;
	}



	/**
	 * Include pro overview content
	 *
	 * @return void
	 */
	public function wps_wsfwp_include_overview() {
		include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'admin/partials/wallet-system-for-woocommerce-pro-overview.php';
	}

	/**
	 * Convert the orders amount into base currency amount.
	 *
	 * @param string $price price.
	 * @param string $currency currency.
	 * @return string
	 */
	public function wps_wsfwp_update_wallet_to_base_price( $price, $currency ) {
		if ( function_exists( 'wps_mmcsfw_admin_fetch_currency_rates_to_base_currency' ) ) {
			$base_amount = wps_mmcsfw_admin_fetch_currency_rates_to_base_currency( $currency, $price );
			return $base_amount;
		} else {
			return $price;
		}
	}

	/**
	 * Saving the wallet page id on plugin updation.
	 *
	 * @return void
	 */
	public function wps_wsfwp_save_wallet_page_id() {

		$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
		$wallet_page    = get_post( $wallet_page_id );
		if ( empty( $wallet_page ) ) {
			$page = get_page_by_title( 'My Wallet' );
			if ( ! empty( $page ) ) {
				update_option( 'wps_wsfwp_wallet_top_page_id', $page->ID );
				update_option( 'wps_wsfwp_saved_wallet_page_id', 'true' );
			}
		}
	}

	/** Coupon features start from here */

	/**
	 * Register Custom post type wallet coupon function
	 *
	 * @since 1.0.6
	 * @return void
	 */
	public function wps_wsfw_register_cpt_wallet() {

		$labels = array(
			'name'               => esc_html__( 'Wallet Coupons', 'wallet-system-for-woocommerce-pro' ),
			'singular_name'      => esc_html__( 'Wallet Coupon', 'wallet-system-for-woocommerce-pro' ),
			'add_new'            => esc_html__( 'Add New Wallet Coupon', 'wallet-system-for-woocommerce-pro' ),
			'all_items'          => esc_html__( 'All Wallet Coupons', 'wallet-system-for-woocommerce-pro' ),
			'add_new_item'       => esc_html__( 'Add New Wallet Coupon', 'wallet-system-for-woocommerce-pro' ),
			'edit_item'          => esc_html__( 'Edit Wallet Coupon', 'wallet-system-for-woocommerce-pro' ),
			'new_item'           => esc_html__( 'New Wallet Couponn', 'wallet-system-for-woocommerce-pro' ),
			'search_item'        => esc_html__( 'Search Wallet Coupon', 'wallet-system-for-woocommerce-pro' ),
			'not_found'          => esc_html__( 'No Wallet Coupons Found', 'wallet-system-for-woocommerce-pro' ),
			'not_found_in_trash' => esc_html__( 'No Wallet Coupons Found In Trash', 'wallet-system-for-woocommerce-pro' ),
		);

		register_post_type(
			'wps_cpt_coupons',
			array(
				'labels'               => $labels,
				'public'               => true,
				'has_archive'          => false,
				'show_ui'              => true,
				'publicly_queryable'   => true,
				'query_var'            => true,
				'capability_type'      => 'post',
				'hierarchical'         => false,
				'show_in_admin_bar'    => false,
				'show_in_menu'         => true,
				'menu_position'        => null,
				'menu_icon'            => 'dashicons-buddicons-buddypress-logo',
				'register_meta_box_cb' => array( $this, 'wps_wallet_coupon_for_woo_meta_box' ),
				'supports'             => array(
					'title',
					'editor',
				),
				'exclude_from_search'  => false,
				'rewrite'              => array(
					'slug' => esc_html__( 'wallet-coupon', 'wallet-system-for-woocommerce-pro' ),
				),
			)
		);
	}


	/**
	 * Register Custom Meta box for wallet coupon creation.
	 *
	 * @since 1.0.6
	 */
	public function wps_wallet_coupon_for_woo_meta_box() {

		add_meta_box( 'wallet_meta_box', esc_html__( 'Create Wallet Coupon', 'wallet-system-for-woocommerce-pro' ), array( $this, 'wps_wallet_cashback_meta_box_callback' ), 'wps_cpt_coupons' );
	}

	/**
	 * Callback funtion for custom meta boxes of wallet plugin.
	 *
	 * @param string $post Current post object.
	 *
	 * @since 1.0.6
	 */
	public function wps_wallet_cashback_meta_box_callback( $post ) {

		$coupon_id                 = absint( get_the_ID() );
		$wps_wsfw_coupon_amount    = get_post_meta( get_the_ID(), 'wps_wsfw_coupon_amount', true );
		$wps_wsfw_coupon_expiry    = get_post_meta( get_the_ID(), 'wps_wsfw_coupon_expiry', true );
		$wps_wsfw_limit_per_coupon = get_post_meta( get_the_ID(), 'wps_wsfw_limit_per_coupon', true );
		$wps_wsfw_limit_per_user   = get_post_meta( get_the_ID(), 'wps_wsfw_limit_per_user', true );
		wp_nonce_field( 'wps_wsfwp_coupon_creation_nonce', 'wps_wsfwp_coupon_nonce' );
		?>

			<div class="tab">
				  <span class="tablinks active" onclick="wps_wsfw_switch_tab_of_wallet_coupon(event, 'General')">General</span>
				  <span class="tablinks" onclick="wps_wsfw_switch_tab_of_wallet_coupon(event, 'Usage')">Usage Limit</span>
			</div>

			<div id="General" class="tabcontent" style="display: block;">

				<div id="general" aria-labelledby="ui-id-1" role="tabpanel" class="ui-tabs-panel ui-corner-bottom ui-widget-content" aria-hidden="false">
					<p class="form-field coupon_amount_field ">
					<label for="coupon_amount"><strong><?php esc_html_e( 'Coupon amount', 'wallet-system-for-woocommerce-pro' ); ?></strong></label><input type="text" class="short wc_input_price" name="wps_wsfw_coupon_amount" id="wps_wsfw_coupon_amount" value="<?php echo esc_attr( $wps_wsfw_coupon_amount ); ?>" placeholder="0"> </p><p class="form-field expiry_date_field ">
					<label for="coupon_status"><strong><?php esc_html_e( 'Coupon Status  ', 'wallet-system-for-woocommerce-pro' ); ?></strong></label><input type="checkbox" class="date-picker hasDatepicker" name="wps_wsfw_coupon_expiry" id="wps_wsfw_coupon_expiry" value="on" <?php checked( $wps_wsfw_coupon_expiry, 'on' ); ?>><span><?php esc_html_e( 'Enable coupon status to make coupon active', 'wallet-system-for-woocommerce-pro' ); ?></span></p>
				</div>
			</div>

			<div id="Usage" class="tabcontent">
				 <div id="usage_limits" aria-labelledby="ui-id-3" role="tabpanel" class="ui-tabs-panel ui-corner-bottom ui-widget-content" aria-hidden="true" style="display: block;">
					<p class="form-field usage_limit_field ">
						<label for="usage_limit"><strong><?php esc_html_e( 'Usage limit per coupon', 'wallet-system-for-woocommerce-pro' ); ?></strong></label><input type="number" class="short" name="wps_wsfw_limit_per_coupon" id="wps_wsfw_limit_per_coupon" value="<?php echo esc_attr( $wps_wsfw_limit_per_coupon ); ?>" placeholder="Unlimited usage" step="1" min="0"> </p>
					<p class="form-field usage_limit_per_user_field ">
						<label for="usage_limit_per_user"><strong><?php esc_html_e( 'Usage limit per user', 'wallet-system-for-woocommerce-pro' ); ?></strong></label><input type="number" class="short"  name="wps_wsfw_limit_per_user" id="wps_wsfw_limit_per_user" value="<?php echo esc_attr( $wps_wsfw_limit_per_user ); ?>" placeholder="Unlimited usage" step="1" min="0"> </p>
				</div>
			</div>
		<?php
	}

	/**
	 * Save custom post type wallet data function
	 *
	 * @since    1.0.6
	 * @param [type] $post_id is the id of current post.
	 * @return void
	 */
	public function wps_wsfwp_wallet_coupon_for_woo_save_fields( $post_id ) {
		// Return if doing autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Return if doing ajax :: Quick edits.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		// Return on post trash, quick-edit, new post.
		if ( empty( $_POST['action'] ) || 'editpost' != $_POST['action'] ) {
			return;
		}
		check_admin_referer( 'wps_wsfwp_coupon_creation_nonce', 'wps_wsfwp_coupon_nonce' );

		$wps_wsfw_coupon_amount    = ! empty( $_POST['wps_wsfw_coupon_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfw_coupon_amount'] ) ) : '';
		$wps_wsfw_coupon_expiry    = ! empty( $_POST['wps_wsfw_coupon_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfw_coupon_expiry'] ) ) : '';
		$wps_wsfw_limit_per_coupon = ! empty( $_POST['wps_wsfw_limit_per_coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfw_limit_per_coupon'] ) ) : '';
		$wps_wsfw_limit_per_user   = ! empty( $_POST['wps_wsfw_limit_per_user'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfw_limit_per_user'] ) ) : '';

		update_post_meta( $post_id, 'wps_wsfw_coupon_amount', $wps_wsfw_coupon_amount );
		update_post_meta( $post_id, 'wps_wsfw_coupon_expiry', $wps_wsfw_coupon_expiry );
		update_post_meta( $post_id, 'wps_wsfw_limit_per_coupon', $wps_wsfw_limit_per_coupon );
		update_post_meta( $post_id, 'wps_wsfw_limit_per_user', $wps_wsfw_limit_per_user );
	}

	/**
	 * Adding custom column to the custom post type "wallet-coupon"
	 *
	 * @param array $columns is an array of deafult columns in custom post type.
	 *
	 * @since 1.0.6
	 */
	public function wps_wsfwp_wallet_coupon_for_woo_cpt_columns( $columns ) {

		$columns['wallet_coupon_amount']     = esc_html__( 'Wallet Coupon Amount', 'wallet-system-for-woocommerce-pro' );
		$columns['wallet_coupon_status']     = esc_html__( 'Wallet Coupon Status', 'wallet-system-for-woocommerce-pro' );
		$columns['wallet_coupon_user_limit'] = esc_html__( 'Wallet Coupon User Limit', 'wallet-system-for-woocommerce-pro' );
		$columns['wallet_coupon_limit']      = esc_html__( 'Wallet Coupon Limit', 'wallet-system-for-woocommerce-pro' );
		$columns                             = apply_filters( 'wps_wsfwp_wallet_coupon_columns', $columns );
		return $columns;
	}

	/**
	 * Populating custom columns with content.
	 *
	 * @param array   $column is an array of default columns in Custom post type.
	 * @param integer $post_id is the post id.
	 *
	 * @since 1.0.6
	 */
	public function wps_wsfwp_wallet_coupon_for_woo_fill_columns( $column, $post_id ) {

		switch ( $column ) {

			case 'wallet_coupon_amount':
				$wallet_amount = get_post_meta( get_the_ID(), 'wps_wsfw_coupon_amount', true );
				$currency      = get_woocommerce_currency_symbol();

				if ( ! empty( $currency ) && ! empty( $wallet_amount ) ) {
					printf( ' %s %s ', esc_html( $currency ), esc_html( $wallet_amount ) );
				}
				break;
			case 'wallet_coupon_status':
				$expiry = get_post_meta( $post_id, 'wps_wsfw_coupon_expiry', true );
				if ( 'on' === $expiry ) {
					?>
					<p class="wps_active_coupon"><?php echo esc_html_e( 'Active', 'wallet-system-for-woocommerce-pro' ); ?></p>
					<?php
				} else {
					?>
					<p class="wps_expired_coupon"><?php echo esc_html_e( 'Expired', 'wallet-system-for-woocommerce-pro' ); ?></p>
					<?php
				}
				break;
			case 'users':
				$usage_data = get_option( $post_id . '_wallet_coupon_usage_data', array() );
				if ( ! empty( $usage_data ) ) {
					$col_data = array_unique( array_column( $usage_data, 'user_id' ) );
					$col_data = implode( ',', $col_data );
				} else {
					$col_data = '-';
				}
				break;
			case 'wallet_coupon_user_limit':
				$expiry = get_post_meta( $post_id, 'wps_wsfw_limit_per_user', true );
				echo esc_html( ! empty( $expiry ) ? $expiry : '' );
				break;
			case 'wallet_coupon_limit':
				$expiry = get_post_meta( $post_id, 'wps_wsfw_limit_per_coupon', true );
				echo esc_html( ! empty( $expiry ) ? $expiry : '' );
				break;
		}
	}

	/**
	 * Removes media buttons from post types.
	 *
	 * @param [type] $settings is the custom post type settings.
	 * @return mixed
	 *
	 * @since    1.0.6
	 */
	public function wps_wsfwp_wallet_coupon_remove_add_media( $settings ) {
		$current_screen = get_current_screen();

		// Post types for which the media buttons should be removed.
		$post_types = array( 'post' );

		// Bail out if media buttons should not be removed for the current post type.
		if ( ! empty( $current_screen ) && 'wps_cpt_coupons' == $current_screen->post_type ) {
			$settings['media_buttons'] = false;
		}
		return $settings;
	}

	/**
	 * Upgrade Restriction setting for Pro Plugin.
	 *
	 * @param string $html for $html.
	 * @param mixed  $user is the user for which this feature is used.
	 * @return string
	 */
	public function wps_wsfwp_wsfw_wallet_user_restriction_after( $html, $user ) {
		$html  = '';
		$html .= '<span>';
		$html .= '<a class="edit_wallet-check" data-userid="' . esc_attr( $user->ID ) . '" href="" title="Restrict Wallet" >';
		$html .= '<img src="' . esc_url( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_URL ) . 'admin/image/edit.svg"></a>';
		$html .= '<input type="hidden" id="test_' . esc_html( $user->ID ) . '" value="' . $user->ID . '">';
		$html .= '</span>';
		return $html;
	}

	/**
	 * Html Body for user restriction pop up.
	 *
	 * @return void
	 */
	public function wsfw_wallet_restrict_user_pro_after_html() {
		?>
			<div  id ="restrict_user_body">
			</div>
		<?php
	}



	/**
	 * Template used to locate
	 *
	 * @param [type] $template used for file.
	 * @param [type] $template_name name of the template.
	 * @param [type] $template_path path of the template.
	 * @return mixed
	 */
	public function wsfw_wallet_locate_woocommerce_template( $template, $template_name, $template_path ) {

		$wc_path     = WC()->template_path() . $template_name;
		$theme_path  = '/' . $template_name;
		$plugin_path = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'templates/' . $template_name;

		$located = locate_template(
			array(
				$wc_path,
				$theme_path,
				$plugin_path,
			)
		);

		if ( ! $located && file_exists( $plugin_path ) ) {

			$located = apply_filters( 'wsfw_plugin_constant_locate_template', $plugin_path, $template_path );
		} else {

			$located = apply_filters( 'wsfw_plugin_constant_locate_template', $located, $template_path );
		}

		return $located ? $located : $template;
	}


	/**
	 * Html data of modal for user restriction.
	 *
	 * @return void
	 */
	public function wsfw_wallet_restrict_include_modal_data() {

		check_ajax_referer( 'check-nonce', 'nonce' );
		if ( ! empty( $_POST['userid'] ) ) {
			$userid = sanitize_text_field( wp_unslash( $_POST['userid'] ) ); // assigning variation id.
		}

		$template_name = 'admin/wsfw-restrict-modal-edit-tab.php';

		$args = array(
			'userid' => $userid,
		);

		ob_start();
		wc_get_template( $template_name, $args );
		$response['option-edit'] = ob_get_clean();
		wp_send_json( $response );
	}


	/**
	 * Saving of user restriction.
	 *
	 * @return void
	 */
	public function wsfw_wallet_user_restriction_saving() {

		$nonce = ( isset( $_POST['user_update_nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['user_update_nonce'] ) ) : '';
		if ( wp_verify_nonce( $nonce ) ) {

			if ( isset( $_POST['restrict_wallet'] ) && ! empty( $_POST['restrict_wallet'] ) ) {
				$wps_wallet_restrict_every_customer = ( isset( $_POST['wps_wallet-restrict-every-customer'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-every-customer'] ) ) : '';
				update_option( 'wps_wallet_restrict_every_customer', $wps_wallet_restrict_every_customer );
				if ( 'on' == $wps_wallet_restrict_every_customer ) {

					$update = true;
					$user_count = count_users()['total_users'];
					$current_page  = 1;
					$reset_status  = '';
					$get_count = 500;
					$result = '';
					if ( $user_count > $get_count ) {

						$get_count = $get_count;
						$loop_count = $user_count / $get_count;
					} else {
						$get_count = $user_count;
						$loop_count = 0;
					}
					$data = $this->wsfw_wallet_user_restriction_saving_for_each_user( $get_count, $current_page, $update, '' );
					if ( $loop_count > 0 ) {

						for ( $i = 0; $i < $loop_count; $i++ ) {

							if ( intval( $user_count ) >= intval( $data['offset'] ) + intval( $data['per_user'] ) ) {

								if ( $data['offset'] <= 0 ) {

									 $reset_status = $get_count;
								} else {

									$reset_status = floatval( $data['offset'] ) + floatval( $get_count );
								}

								$data = $this->wsfw_wallet_user_restriction_saving_for_each_user( $data['per_user'], $data['current_page'], $update, $data['updated_users'] );

								$result  = false;

							} else {

								$result  = true;
							}
						}
					} else {
						$result  = true;
					}

					$users = get_users();
					foreach ( $users as $user ) {
						$user_id = $user->ID;
						$restrict_topup = ( isset( $_POST['wps_wallet-restrict-topup'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-topup'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_topup', $restrict_topup );
						update_option( 'wps_wallet_restrict_topup', $restrict_topup );

						$restrict_transfer = ( isset( $_POST['wps_wallet-restrict-transfer'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-transfer'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_transfer', $restrict_transfer );
						update_option( 'wps_wallet_restrict_transfer', $restrict_transfer );

						$restrict_fund_request = ( isset( $_POST['wps_wallet-restrict-fund-request'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-fund-request'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet-restrict-fund-request', $restrict_fund_request );
						update_option( 'wps_wallet_restrict_fund_request', $restrict_fund_request );

						$restrict_withdrawal = ( isset( $_POST['wps_wallet-restrict-withdrawal'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-withdrawal'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_withdrawal', $restrict_withdrawal );
						update_option( 'wps_wallet_restrict_withdrawal', $restrict_withdrawal );

						$restrict_coupon = ( isset( $_POST['wps_wallet-restrict-coupon'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-coupon'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_coupon', $restrict_coupon );
						update_option( 'wps_wallet_restrict_coupon', $restrict_coupon );

						$restrict_transactions = ( isset( $_POST['wps_wallet-restrict-transactions'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-transactions'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_transactions', $restrict_transactions );
						update_option( 'wps_wallet_restrict_transactions', $restrict_transactions );

						$restrict_referral = ( isset( $_POST['wps_wallet-restrict-referral'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-referral'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_referral', $restrict_referral );
						update_option( 'wps_wallet_restrict_referral', $restrict_referral );

						$restrict_qrcode = ( isset( $_POST['wps_wallet-restrict-qrcode'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-qrcode'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_qrcode', $restrict_qrcode );
						update_option( 'wps_wallet_restrict_qrcode', $restrict_qrcode );

						$restrict_gateway = ( isset( $_POST['wps_wallet-restrict-wallet-gateway'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-wallet-gateway'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_wallet_gateway', $restrict_gateway );
						update_option( 'wps_wallet_restrict_wallet_gateway', $restrict_gateway );

						$wps_wallet_restrict_wallet_id = ( isset( $_POST['wps_wallet-restrict-wallet-id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-wallet-id'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_wallet_id', $wps_wallet_restrict_wallet_id );
						update_option( 'wps_wallet_restrict_wallet_id', $wps_wallet_restrict_wallet_id );

						$restrict_message_user = ( isset( $_POST['wps_wallet-restrict-message-to-user'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-message-to-user'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_message_to_user', $restrict_message_user );
						update_option( 'wps_wallet_restrict_message_to_user', $restrict_message_user );

						$restrict_message_user_for = ( isset( $_POST['wps_wallet-restrict-message-for'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-message-for'] ) ) : '';
						update_user_meta( $user_id, 'wps_wallet_restrict_message_for', $restrict_message_user_for );
						update_option( 'wps_wallet_restrict_message_for', $restrict_message_user_for );

					}
				} else {
					$user_id = ( isset( $_POST['user_restrict'] ) ) ? sanitize_text_field( wp_unslash( $_POST['user_restrict'] ) ) : '';
					$restrict_topup = ( isset( $_POST['wps_wallet-restrict-topup'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-topup'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_topup', $restrict_topup );
					$restrict_transfer = ( isset( $_POST['wps_wallet-restrict-transfer'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-transfer'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_transfer', $restrict_transfer );
					$restrict_fund_request = ( isset( $_POST['wps_wallet-restrict-fund-request'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-fund-request'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_fund_request', $restrict_fund_request );
					$restrict_withdrawal = ( isset( $_POST['wps_wallet-restrict-withdrawal'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-withdrawal'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_withdrawal', $restrict_withdrawal );
					$restrict_coupon = ( isset( $_POST['wps_wallet-restrict-coupon'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-coupon'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_coupon', $restrict_coupon );
					$restrict_transactions = ( isset( $_POST['wps_wallet-restrict-transactions'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-transactions'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_transactions', $restrict_transactions );
					$restrict_referral = ( isset( $_POST['wps_wallet-restrict-referral'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-referral'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_referral', $restrict_referral );
					$restrict_qrcode = ( isset( $_POST['wps_wallet-restrict-qrcode'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-qrcode'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_qrcode', $restrict_qrcode );
					$restrict_gateway = ( isset( $_POST['wps_wallet-restrict-wallet-gateway'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-wallet-gateway'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_wallet_gateway', $restrict_gateway );

					$wps_wallet_restrict_wallet_id = ( isset( $_POST['wps_wallet-restrict-wallet-id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-wallet-id'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_wallet_id', $wps_wallet_restrict_wallet_id );

					$restrict_message_user = ( isset( $_POST['wps_wallet-restrict-message-to-user'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-message-to-user'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_message_to_user', $restrict_message_user );
					$restrict_message_user_for = ( isset( $_POST['wps_wallet-restrict-message-for'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-message-for'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_message_for', $restrict_message_user_for );

				}
			}
		}
	}

	/**
	 * Ajax for user update.
	 *
	 * @param [type] $user_count current user count.
	 * @param [type] $current_page current page.
	 * @param [type] $update is the key to update value.
	 * @param string $user_updated_count update count of user.
	 * @return array
	 */
	public function wsfw_wallet_user_restriction_saving_for_each_user( $user_count, $current_page, $update, $user_updated_count = '' ) {
		$nonce = ( isset( $_POST['user_update_nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['user_update_nonce'] ) ) : '';
		if ( wp_verify_nonce( $nonce ) ) {

			$updated_users   = 0;
			$number_of_users = 0;
			$args = array(
				'fields'     => 'ID',
			);

			$args['number'] = $user_count;
			$args['offset'] = floatval( $current_page - 1 ) * floatval( $user_count );

			$user_data      = new WP_User_Query( $args );
			$user_data      = $user_data->get_results();

			if ( ! empty( $user_updated_count ) ) {
				$updated_users = $user_updated_count;
			}

			if ( ! empty( $user_data ) && is_array( $user_data ) ) {
				foreach ( $user_data as $key => $user_id ) {

					$restrict_topup = ( isset( $_POST['wps_wallet-restrict-topup'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-topup'] ) ) : '';

					update_user_meta( $user_id, 'wps_wallet_restrict_topup', $restrict_topup );
					$restrict_transfer = ( isset( $_POST['wps_wallet-restrict-transfer'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-transfer'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_transfer', $restrict_transfer );
					$restrict_fund_request = ( isset( $_POST['wps_wallet-restrict-fund-request'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-fund-request'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_fund_request', $restrict_fund_request );
					$restrict_withdrawal = ( isset( $_POST['wps_wallet-restrict-withdrawal'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-withdrawal'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_withdrawal', $restrict_withdrawal );
					$restrict_coupon = ( isset( $_POST['wps_wallet-restrict-coupon'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-coupon'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_coupon', $restrict_coupon );
					$restrict_transactions = ( isset( $_POST['wps_wallet-restrict-transactions'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-transactions'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_transactions', $restrict_transactions );
					$restrict_referral = ( isset( $_POST['wps_wallet-restrict-referral'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-referral'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_referral', $restrict_referral );

					$restrict_qrcode = ( isset( $_POST['wps_wallet-restrict-qrcode'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-qrcode'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_qrcode', $restrict_qrcode );

					$restrict_message_user = ( isset( $_POST['wps_wallet-restrict-message-to-user'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-message-to-user'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_message_to_user', $restrict_message_user );
					$restrict_message_user_for = ( isset( $_POST['wps_wallet-restrict-message-for'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet-restrict-message-for'] ) ) : '';
					update_user_meta( $user_id, 'wps_wallet_restrict_message_for', $restrict_message_user_for );

				}
			}

			$data = array(
				'per_user'     => $user_count,
				'current_page' => $current_page + 1,
				'offset'       => ( $current_page - 1 ) * $user_count,
				'updated_users' => $updated_users,
			);

			return $data;
		}
	}

	/**
	 * Add cashback html at each category.
	 *
	 * @param [type] $term is the object of category.
	 * @return void
	 */
	public function wps_wsfwp_edit_product_cat_cashback_field( $term ) {
		$wps_wsfwp_cashback_type = get_term_meta( $term->term_id, '_wps_wsfwp_cashback_type', true );
		$wps_wsfwp_cashback_amount = get_term_meta( $term->term_id, '_wps_wsfwp_cashback_amount', true );
		?>
			<tr class="form-field">
				<th scope="row" valign="top"><?php esc_html_e( 'Cashback type', 'wallet-system-for-woocommerce-pro' ); ?></th>
				<td>
					<select name="wps_wsfwp_product_cat_cashback_type" id="wps_wsfwp_product_cat_cashback_type">
						<option value="percent"<?php selected( $wps_wsfwp_cashback_type, 'percent' ); ?> ><?php esc_html_e( 'Percentage', 'wallet-system-for-woocommerce-pro' ); ?></option>
						<option value="fixed" <?php selected( $wps_wsfwp_cashback_type, 'fixed' ); ?> ><?php esc_html_e( 'Fixed', 'wallet-system-for-woocommerce-pro' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top"><?php esc_html_e( 'Cashback Amount', 'wallet-system-for-woocommerce-pro' ); ?></th>
				<td><input type="number" step="0.01" name="wps_wsfwp_product_cat_cashback_amount" id="wps_wsfwp_product_cat_cashback_amount" value="<?php echo esc_html( $wps_wsfwp_cashback_amount ); ?>" placeholder=""></td>
			</tr>
			<input type="hidden" id="category_update_nonce" name="category_update_nonce" value="<?php echo esc_attr( wp_create_nonce() ); ?>" />
			<?php
	}


	/**
	 * Undocumented function
	 *
	 * @param [type] $term_id is the particular category id.
	 * @param string $tt_id is the taxonomy id.
	 * @param string $taxonomy is the Taxonomy slug.
	 * @return void
	 */
	public function wps_wsfwp_save_product_cashback_field( $term_id, $tt_id = '', $taxonomy = '' ) {
		$nonce = ( isset( $_POST['category_update_nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['category_update_nonce'] ) ) : '';
		if ( wp_verify_nonce( $nonce ) ) {

			if ( 'product_cat' === $taxonomy ) {
				$term = get_term_by( 'id', $term_id, 'product_cat', 'ARRAY_A' );
				if ( isset( $_POST['wps_wsfwp_product_cat_cashback_type'] ) ) {
					$wps_wsfwp_product_cat_cashback_type = ! empty( $_POST['wps_wsfwp_product_cat_cashback_type'] ) ? map_deep( wp_unslash( $_POST['wps_wsfwp_product_cat_cashback_type'] ), 'sanitize_text_field' ) : '';
					;

					update_term_meta( $term_id, '_wps_wsfwp_cashback_type', $wps_wsfwp_product_cat_cashback_type );
				}
				if ( isset( $_POST['wps_wsfwp_product_cat_cashback_amount'] ) ) {
					$wps_wsfwp_product_cat_cashback_amount = ( isset( $_POST['wps_wsfwp_product_cat_cashback_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_product_cat_cashback_amount'] ) ) : '';
					update_term_meta( $term_id, '_wps_wsfwp_cashback_amount', $wps_wsfwp_product_cat_cashback_amount );
					update_term_meta( $term_id, '_wps_wsfwp_category_rule', $term['name'] );
				}
				if ( empty( $_POST['wps_wsfwp_product_cat_cashback_amount'] ) ) {
					$wps_wsfwp_product_cat_cashback_amount = ( isset( $_POST['wps_wsfwp_product_cat_cashback_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_product_cat_cashback_amount'] ) ) : '';
					delete_term_meta( $term_id, '_wps_wsfwp_cashback_amount', $wps_wsfwp_product_cat_cashback_amount );
					delete_term_meta( $term_id, '_wps_wsfwp_category_rule', '' );
				}
			}
		}
	}

	/**
	 * Check pro plugin is active or not.
	 *
	 * @param bool $check is the boolean variable to check active or not.
	 * @return bool
	 */
	public function wsfwp_check_pro_plugin( $check ) {

		$check = true;
		return $check;
	}

	/**
	 * Add more option to subscription interval.
	 *
	 * @param array $subscription_duration is the array to store more information.
	 * @return array
	 */
	public function wsfw_subscription_type__array( $subscription_duration ) {
		$subscription_duration = array();
		$subscription_duration = array(
			'day' => 'Days',
			'week' => 'Weeks',
			'month' => 'Months',
			'year' => 'Years',
		);
		return $subscription_duration;
	}


	/**
	 * Check pro plugin is active or not.
	 *
	 * @param array $wsfw_settings_template is the array variable used to show setting.
	 * @return array
	 */
	public function wps_wsfwp_wallet_action_auto_topup_extra_settings_array( $wsfw_settings_template ) {
		$wsfwp_settings_template_pro = array();
		$wsfwp_settings_template = array(
			array(
				'title'       => __( 'Enable to select recharge as Subscription or Regular', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'This is used to allow user to recharge as subscription or regular.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_recharege_for_user_enable',
				'id'          => 'wps_wsfw_wallet_action_recharege_for_user_enable',
				'value'       => get_option( 'wps_wsfw_wallet_action_recharege_for_user_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),
		);

		$wsfw_settings_template = array_merge( $wsfw_settings_template, $wsfwp_settings_template );
		return $wsfw_settings_template;
	}

	/**
	 * Function for withdrawal setting.
	 *
	 * @return array
	 */
	public function wps_wsfws_admin_wallet_action_withdrawal_settings_page() {
		$wsfw_settings_template = array(

			array(
				'title'       => __( 'Enable Wallet withdrawal Extra Fee Settings', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'This is switch field demo follow same structure for further use.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_action_withdrawal_enable',
				'id'          => 'wps_wsfwp_wallet_action_withdrawal_enable',
				'value'       => get_option( 'wps_wsfwp_wallet_action_withdrawal_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),
			array(
				'title'       => __( 'Wallet Withdrawal Fee Type', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'select',
				'description' => __( 'Select Withdrawal Fee type Percentage or Fixed.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_cashback_withdrawal_fee_type',
				'id'          => 'wps_wsfwp_cashback_withdrawal_fee_type',
				'value'       => get_option( 'wps_wsfwp_cashback_withdrawal_fee_type', 'percent' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => apply_filters(
					'wsfw_cashback_type__array',
					array(
						'percent' => __( 'Percentage', 'wallet-system-for-woocommerce-pro' ),
						'fixed'   => __( 'Fixed', 'wallet-system-for-woocommerce-pro' ),
					)
				),
			),
			array(
				'title'       => __( 'Enter Fee For Wallet withdrawal Process', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'number',
				'description' => __( 'Enter Fee For Wallet withdrawal Process', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_withdrawal_fee_amount',
				'id'          => 'wps_wsfwp_wallet_withdrawal_fee_amount',
				'step'        => '0.01',
				'min'         => 0,
				'value'       => ! empty( get_option( 'wps_wsfwp_wallet_withdrawal_fee_amount' ) ) ? get_option( 'wps_wsfwp_wallet_withdrawal_fee_amount' ) : 1,
				'placeholder' => __( 'Enter wallet Transfer Fee amount', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			),
		);

		$wsfw_settings_template   = apply_filters( 'wsfwp_wallet_action_auto_withdrawal_settings_array', $wsfw_settings_template );
		return $wsfw_settings_template;
	}

	/**
	 * Function fpor Transfer settings.
	 *
	 * @return array
	 */
	public function wps_wsfws_admin_wallet_action_transfer_settings_page() {

		$wsfw_settings_template = array(

			array(
				'title'       => __( 'Enable Wallet transfer Extra Fee Settings', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'This is switch field demo follow same structure for further use.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_action_transfer_enable',
				'id'          => 'wps_wsfwp_wallet_action_transfer_enable',
				'value'       => get_option( 'wps_wsfwp_wallet_action_transfer_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),
			array(
				'title'       => __( 'Wallet Transfer Fee Type', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'select',
				'description' => __( 'Select Transfer Fee type Percentage or Fixed.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_cashback_transfer_fee_type',
				'id'          => 'wps_wsfwp_cashback_transfer_fee_type',
				'value'       => get_option( 'wps_wsfwp_cashback_transfer_fee_type', 'percent' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => apply_filters(
					'wsfw_cashback_type__array',
					array(
						'percent' => __( 'Percentage', 'wallet-system-for-woocommerce-pro' ),
						'fixed'   => __( 'Fixed', 'wallet-system-for-woocommerce-pro' ),
					)
				),
			),
			array(
				'title'       => __( 'Enter Fee For Wallet Transfer Process', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'number',
				'description' => __( 'Enter Fee For Wallet Transfer Process', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_transfer_fee_amount',
				'id'          => 'wps_wsfwp_wallet_transfer_fee_amount',
				'step'        => '0.01',
				'min'         => 0,
				'value'       => ! empty( get_option( 'wps_wsfwp_wallet_transfer_fee_amount' ) ) ? get_option( 'wps_wsfwp_wallet_transfer_fee_amount' ) : 1,
				'placeholder' => __( 'Enter wallet Transfer Fee amount', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			),
		);

		$wsfw_settings_template   = apply_filters( 'wsfwp_wallet_action_auto_transfer_settings_array', $wsfw_settings_template );
		return $wsfw_settings_template;
	}

	/**
	 * Settings for wallet restriction.
	 *
	 * @return array
	 */
	public function wps_wsfw_admin_wallet_withdrawal_restriction_settings_page() {

		$wsfw_settings_template = array(

			array(
				'title'       => __( 'Enable Wallet Withdrawal Restriction Settings', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'This is switch field demo follow same structure for further use.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_withdrawal_restriction_enable',
				'id'          => 'wps_wsfwp_wallet_withdrawal_restriction_enable',
				'value'       => get_option( 'wps_wsfwp_wallet_withdrawal_restriction_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),

			array(
				'title'       => __( 'Minimum Amount For Wallet Withdrawal ( ', 'wallet-system-for-woocommerce-pro' ) . get_woocommerce_currency_symbol() . ' )',
				'type'        => 'number',
				'description' => __( 'Minimum amount needed to wallet withdrawal.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_min_wallet_withdrawal_amount',
				'min'         => 0,
				'step'        => '0.01',
				'id'          => 'wsfwp_min_wallet_withdrawal_amount',
				'value'       => get_option( 'wsfwp_min_wallet_withdrawal_amount', '' ),
				'class'       => 'wpg-number-clas',
			),
			array(
				'title'       => __( 'Maximum Amount For Wallet Withdrawal ( ', 'wallet-system-for-woocommerce-pro' ) . get_woocommerce_currency_symbol() . ' )',
				'type'        => 'number',
				'description' => __( 'Maximum amount for wallet withdrawal.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_max_wallet_withdrawal_amount',
				'min'         => 0,
				'step'        => '0.01',
				'id'          => 'wsfwp_max_wallet_withdrawal_amount',
				'value'       => get_option( 'wsfwp_max_wallet_withdrawal_amount', '' ),
				'class'       => 'wpg-number-class',
			),

			array(
				'type'        => 'submit',
				'name'        => 'wsfw_button_wallet_restriction',
				'id'          => 'wsfw_button_wallet_restriction',
				'button_text' => __( 'Save Settings', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wsfw-button-class',
			),

		);

		$wsfw_settings_template   = apply_filters( 'wsfwp_wallet_action_wallet_restriction_settings_array', $wsfw_settings_template );
		return $wsfw_settings_template;
	}




		/**
		 * Setting for transfer wallet restriction.
		 *
		 * @return mixed
		 */
	public function wps_wsfw_admin_wallet_recharge_restriction_settings_page() {

		$wsfw_settings_template = array(

			array(
				'title'       => __( 'Enable Wallet Recharge Restriction Settings', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'Enable to restrict wallet recharge', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_recharge_restriction_enable',
				'id'          => 'wps_wsfwp_wallet_recharge_restriction_enable',
				'value'       => get_option( 'wps_wsfwp_wallet_recharge_restriction_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),

			array(
				'title'       => __( 'Minimum Wallet Recharge Amount ( ', 'wallet-system-for-woocommerce-pro' ) . get_woocommerce_currency_symbol() . ' )',
				'type'        => 'number',
				'description' => __( 'Minimum amount needed to recharge wallet.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_min_wallet_recharge_amount',
				'min'         => 0,
				'step'        => '0.01',
				'id'          => 'wsfwp_min_wallet_recharge_amount',
				'value'       => get_option( 'wsfwp_min_wallet_recharge_amount', '' ),
				'class'       => 'wpg-number-class',
			),
			array(
				'title'       => __( 'Maximum Wallet Recharge Amount ( ', 'wallet-system-for-woocommerce-pro' ) . get_woocommerce_currency_symbol() . ' )',
				'type'        => 'number',
				'description' => __( 'Maximum amount for wallet recharge.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_max_wallet_recharge_amount',
				'min'         => 0,
				'step'        => '0.01',
				'id'          => 'wsfwp_max_wallet_recharge_amount',
				'value'       => get_option( 'wsfwp_max_wallet_recharge_amount', '' ),
				'class'       => 'wpg-number-class',
			),
		);

		$wsfw_settings_template   = apply_filters( 'wsfwp_wallet_action_auto_transfer_restriction_array', $wsfw_settings_template );
		return $wsfw_settings_template;
	}

	/**
	 * Setting for transfer wallet restriction.
	 *
	 * @return mixed
	 */
	public function wps_wsfw_admin_wallet_transfer_restriction_settings_page() {

		$wsfw_settings_template = array(

			array(
				'title'       => __( 'Enable Wallet Transfer Restriction Settings', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'This is switch field demo follow same structure for further use.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_transfer_restriction_enable',
				'id'          => 'wps_wsfwp_wallet_transfer_restriction_enable',
				'value'       => get_option( 'wps_wsfwp_wallet_transfer_restriction_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),

			array(
				'title'       => __( 'Minimum Amount For Wallet Transfer ( ', 'wallet-system-for-woocommerce-pro' ) . get_woocommerce_currency_symbol() . ' )',
				'type'        => 'number',
				'description' => __( 'Minimum amount needed to recharge transfer.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_min_wallet_transfer_amount',
				'min'         => 0,
				'step'        => '0.01',
				'id'          => 'wsfwp_min_wallet_transfer_amount',
				'value'       => get_option( 'wsfwp_min_wallet_transfer_amount', '' ),
				'class'       => 'wpg-number-class',
			),
			array(
				'title'       => __( 'Maximum Amount For Wallet Transfer ( ', 'wallet-system-for-woocommerce-pro' ) . get_woocommerce_currency_symbol() . ' )',
				'type'        => 'number',
				'description' => __( 'Maximum amount for wallet transfer.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfwp_max_wallet_transfer_amount',
				'id'          => 'wsfwp_max_wallet_transfer_amount',
				'min'         => 0,
				'step'        => '0.01',
				'value'       => get_option( 'wsfwp_max_wallet_transfer_amount', '' ),
				'class'       => 'wpg-number-class',
			),
		);

		$wsfw_settings_template   = apply_filters( 'wsfwp_wallet_action_auto_transfer_restriction_array', $wsfw_settings_template );
		return $wsfw_settings_template;
	}


	/**
	 * This is used to create comment html.
	 *
	 * @param array $wsfw_settings_template setting template.
	 * @return array
	 */
	public function wsfw_admin_wallet_action_settings_refer_friend_array( $wsfw_settings_template ) {
		$wsfw_settings_template = array(
			array(
				'title'       => __( 'Enable Referral Settings', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'Check this box to enable the Comment Amount when comment is approved..', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_refer_friend_enable',
				'id'          => 'wps_wsfw_wallet_action_refer_friend_enable',
				'value'       => get_option( 'wps_wsfw_wallet_action_refer_friend_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),
			array(
				'title'       => __( 'Enter Referral Amount', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'number',
				'description' => __( 'The amount which new customers will get after their comments are approved..', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_referal_amount',
				'id'          => 'wps_wsfw_wallet_action_referal_amount',
				'min'         => 0,
				'step'        => '0.01',
				'value'       => ! empty( get_option( 'wps_wsfw_wallet_action_referal_amount' ) ) ? get_option( 'wps_wsfw_wallet_action_referal_amount' ) : 1,
				'placeholder' => __( 'Enter comment amount', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			),
			array(
				'title'       => __( 'Enter Referral Description', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'textarea',
				'description' => __( 'Enter message for user that display on product page.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_referral_description',
				'id'          => 'wps_wsfw_wallet_action_referral_description',
				'step'        => '0.01',
				'value'       => ! empty( get_option( 'wps_wsfw_wallet_action_referral_description' ) ) ? get_option( 'wps_wsfw_wallet_action_referral_description' ) : 'You will get 1 amount to refer a friend',
				'placeholder' => __( 'Enter comment description', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			),

			array(
				'title'       => __( 'Refer Via Referral Coupon Code', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'Check this box to enable the Referral via Coupon Code.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_refer_coupon_code_enable',
				'id'          => 'wps_wsfw_wallet_action_refer_coupon_code_enable',
				'value'       => get_option( 'wps_wsfw_wallet_action_refer_coupon_code_enable' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),
			array(
				'title'       => __( 'Amount for the Referral Coupon Discount', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'number',
				'description' => __( 'Enter The Amount For Referral Coupon Discount .', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_referal_coupon_amount',
				'id'          => 'wps_wsfw_wallet_action_referal_coupon_amount',
				'step'        => '0.01',
				'min'         => 0,
				'value'       => get_option( 'wps_wsfw_wallet_action_referal_coupon_amount' ),
				'placeholder' => __( 'Enter comment amount', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			),

			array(
				'title'       => __( 'Referral Purchase Coupon Type', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'select',
				'description' => __( 'Select The Coupon Type Referral Purchase Depending Upon Order Total .', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_referal_coupon_type',
				'id'          => 'wps_wsfw_wallet_action_referal_coupon_type',
				'value'       => get_option( 'wps_wsfw_wallet_action_referal_coupon_type', 'Fixed' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => apply_filters(
					'wsfw_wallet_action_referral_coupon__array',
					array(
						'fixed'   => __( 'Fixed', 'wallet-system-for-woocommerce-pro' ),
						'percent' => __( 'Percentage', 'wallet-system-for-woocommerce-pro' ),
					)
				),
			),

			// multi level referral feature.
			array(
				'title'       => __( 'Enable Multi-Level Referral', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'Check this box to enable the Multi-Level Referral, Multi-Level only applicable at two level Referral', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_refer_multi_level_referral',
				'id'          => 'wps_wsfw_wallet_action_refer_multi_level_referral',
				'value'       => get_option( 'wps_wsfw_wallet_action_refer_multi_level_referral' ),
				'class'       => 'wsfw-radio-switch-class wps_pro_settings',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),
			array(
				'title'       => __( 'Amount for the Multi-Level Refer', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'number',
				'description' => __( 'Enter The Amount For Multi-Level Refer .', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_action_multi_level_amount',
				'id'          => 'wps_wsfw_wallet_action_multi_level_amount',
				'step'        => '0.01',
				'min'         => 0,
				'value'       => get_option( 'wps_wsfw_wallet_action_multi_level_amount' ),
				'placeholder' => __( 'Enter comment amount', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class wps_pro_settings',
			),
			// multi level referral feature.

		);

		return $wsfw_settings_template;
	}

	/**
	 * Function to wallet dashboard layut.
	 *
	 * @param array $wsfw_settings_template as wsfw_settings_template.
	 * @return array
	 */
	public function wsfw_admin_wallet_action_different_layout_array( $wsfw_settings_template ) {
		$wsfw_settings_template = array(
			array(
				'title'       => __( 'Choose Template For Wallet Dashboard', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio',
				'id'          => 'wsfw_wallet_dashboard_template_css',
				'value'       => get_option( 'wsfw_wallet_dashboard_template_css' ),
				'class'       => 'wsfw-radio-switch-class wps_pro_settings',
				'options'     => array(
					'' => __( 'Classic Wallet Dashboard', 'wallet-system-for-woocommerce-pro' ),
					'template1'  => __( 'Modern Wallet Dashboard', 'wallet-system-for-woocommerce-pro' ),
				),
			),
			array(
				'title'    => __( 'Select Color For Wallet Dashboard', 'wallet-system-for-woocommerce-pro' ),
				'type'     => 'text',
				'id'       => 'wps_wsfw_notification_color',
				'description' => __( 'You can also choose the color for Wallet Dashboard.', 'wallet-system-for-woocommerce-pro' ),
				'class'    => 'wsfw-radio-switch-class',
				'value'  => get_option( 'wps_wsfw_notification_color' ),
			),
		);
		return $wsfw_settings_template;
	}

	/**
	 * Wallet instant payment discount setting.
	 *
	 * @param array $wsfw_settings_template  as wsfw_settings_template.
	 * @return array
	 */
	public function wsfw_wallet_action_payment_settings_layout_array( $wsfw_settings_template ) {
		$wsfw_settings_template = array(
			array(
				'title'       => __( 'Enable to Give Instant Discount on Wallet Payment Method', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'radio-switch',
				'description' => __( 'Check this box to enable the Wallet Instant Discount Feature', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wsfw_wallet_instant_discount_wallet',
				'id'          => 'wsfw_wallet_instant_discount_wallet',
				'value'       => get_option( 'wsfw_wallet_instant_discount_wallet' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => array(
					'yes' => __( 'YES', 'wallet-system-for-woocommerce-pro' ),
					'no'  => __( 'NO', 'wallet-system-for-woocommerce-pro' ),
				),
			),

			array(
				'title'       => __( 'Wallet Instant Discount Type', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'select',
				'description' => __( 'Select Instant wallet Discount type Percentage or Fixed.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_wallet_instant_discount_type',
				'id'          => 'wps_wsfwp_wallet_instant_discount_type',
				'value'       => get_option( 'wps_wsfwp_wallet_instant_discount_type', 'Fixed' ),
				'class'       => 'wsfw-radio-switch-class wps_pro_settings',
				'options'     => apply_filters(
					'wsfw_wallet_instant_discount_type__array',
					array(
						'fixed'   => __( 'Fixed', 'wallet-system-for-woocommerce-pro' ),
						'percent' => __( 'Percentage', 'wallet-system-for-woocommerce-pro' ),
					)
				),
			),

			array(
				'title'       => __( 'Enter Discount Value For Wallet Payment Method', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'number',
				'description' => __( 'Enter Discount Value For Wallet Payment Method', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_instant_wallet_discount_value',
				'id'          => 'wps_wsfwp_instant_wallet_discount_value',
				'min'         => 0,
				'step'        => '0.01',
				'value'       => ! empty( get_option( 'wps_wsfwp_instant_wallet_discount_value' ) ) ? get_option( 'wps_wsfwp_instant_wallet_discount_value' ) : 1,
				'placeholder' => __( 'Enter wallet Transfer Fee amount', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class',
			),

			array(
				'title'       => __( 'Enter Description For Wallet Instant Discount Feature', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'textarea',
				'description' => __( 'Enter message for user that display on checkout page for wallet payment method.', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfw_wallet_instant_discount_description',
				'id'          => 'wps_wsfw_wallet_instant_discount_description',
				'step'        => '0.01',
				'value'       => ! empty( get_option( 'wps_wsfw_wallet_instant_discount_description' ) ) ? get_option( 'wps_wsfw_wallet_instant_discount_description' ) : '5% off on Wallet Payment',
				'placeholder' => __( 'Enter instant discount description', 'wallet-system-for-woocommerce-pro' ),
				'class'       => 'wws-text-class wps_pro_settings',
			),
		);
		return $wsfw_settings_template;
	}

	/**
	 * Par gamification compability in wallet.
	 *
	 * @param [type] $wsfw_settings_template as wsfw_settings_template.
	 * @return array
	 */
	public function wsfw_admin_wallet_action_gamification_rule_array( $wsfw_settings_template ) {
		$wsfw_settings_template = array(
			array(
				'title'       => __( 'Select Option in Which user will receive winning from Win Wheel ', 'wallet-system-for-woocommerce-pro' ),
				'type'        => 'select',
				'description' => __( 'Select Rule Type in which Winner Get Winning Price', 'wallet-system-for-woocommerce-pro' ),
				'name'        => 'wps_wsfwp_win_wheel_rule_type',
				'id'          => 'wps_wsfwp_win_wheel_rule_type',
				'value'       => get_option( 'wps_wsfwp_win_wheel_rule_type', 'only wallet' ),
				'class'       => 'wsfw-radio-switch-class',
				'options'     => apply_filters(
					'wsfw_cashback_type__array',
					array(
						'wallet' => __( 'only wallet', 'wallet-system-for-woocommerce-pro' ),
						'point'   => __( 'only point', 'wallet-system-for-woocommerce-pro' ),
						'both'   => __( 'Both i.e wallet and point', 'wallet-system-for-woocommerce-pro' ),
					)
				),
			),
		);

		return $wsfw_settings_template;
	}

	/**
	 * Function for settings of wallet fee.
	 *
	 * @return void
	 */
	public function wps_wsfws_add_template_wallet_fee() {

		$wsfw_active_tab   = isset( $_GET['wsfw_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['wsfw_tab'] ) ) : '';
		if ( 'wallet-system-for-woocommerce-wallet-actions' == $wsfw_active_tab ) {
			$wsfw_wallet_action_withdrawal_fee_settings = apply_filters( 'wsfwp_wallet_action_settings_withdrawal_array', array() );
			$wsfw_wallet_action_transfer_fee_settings = apply_filters( 'wsfwp_wallet_action_settings_transfer_array', array() );
			$wsfw_wallet_action_refer_friend_settings      = apply_filters( 'wsfw_wallet_action_settings_refer_friend_array', array() );
			$wsfw_wallet_action_different_layout_settings      = apply_filters( 'wsfw_wallet_action_different_layout_settings_array', array() );
			$wsfw_wallet_action_payment_settings      = apply_filters( 'wsfw_wallet_action_payment_settings_array', array() );
			$wsfw_wallet_action_gamification_rule_settings      = apply_filters( 'wsfw_wallet_action_gamification_rule_settings_array', array() );
			// custom work.
			$wsfw_wallet_action_user_currency_settings = apply_filters( 'wsfwp_wallet_action_settings_user_currency_array', array() );
			// custom work.
			global $wsfwp_wps_wsfwp_obj;
			$wallet_id          = get_option( 'wps_wsfw_rechargeable_product_id', '' );
			// custom work.
			if ( $wsfw_wallet_action_user_currency_settings ) {
				?>
			<span><b><?php esc_html_e( 'Wallet User Currency Setting', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
				<?php
					$wsfw_wallet_action_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfw_wallet_action_user_currency_settings );
					echo wp_kses_post( $wsfw_wallet_action_html );
				?>
				</br>
			<hr>
			<?php } ?>
			<!-- custom -->


			<div class="wsfw-secion-refer-customize-wallet">
				<span><b><?php esc_html_e( 'Customize Your Wallet Rechargeable Product', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
				<div class="wps-form-group">
					<div class="wps-form-group__label">
						<label for="" class="wps-form-label"><?php esc_html_e( 'Wallet Recharge Product', 'wallet-system-for-woocommerce-pro' ); ?></label>
					</div>
					<div class="wps-form-group__control">
						<a href="<?php echo esc_url( site_url() ) . '/wp-admin/post.php?post=' . esc_attr( $wallet_id ) . '&action=edit'; ?>"><div> <?php esc_html_e( 'Click Here', 'wallet-system-for-woocommerce-pro' ); ?></div></a>
					</div>
				</div>
			</div>
			<hr>

			<div class="wsfw-secion-refer-withdrawal-fees">
				<span><b><?php esc_html_e( 'Wallet Withdrawal Fee Setting', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
				<?php
					$wsfw_wallet_action_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfw_wallet_action_withdrawal_fee_settings );

				if ( ! empty( $wsfw_wallet_action_html ) ) {
					echo wp_kses_post( $wsfw_wallet_action_html );
				}
				?>
				</br>
			</div>
			<hr>
			<div class="wsfw-secion-refer-transfer-fees">
			<span><b><?php esc_html_e( 'Wallet Transfer Fee Setting', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
				<?php
					$wsfw_wallet_action_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfw_wallet_action_transfer_fee_settings );
				if ( ! empty( $wsfw_wallet_action_html ) ) {
					echo wp_kses_post( $wsfw_wallet_action_html );
				}
				?>
				</br>
			</div>
			<hr>
			<div class="wsfw-secion-refer-friend">
			  <span><b><?php esc_html_e( 'Credit Amount On Refer A Friend', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
				<?php
					  $wsfw_wallet_action_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfw_wallet_action_refer_friend_settings );
				if ( ! empty( $wsfw_wallet_action_html ) ) {
					echo wp_kses_post( $wsfw_wallet_action_html );
				}
				?>
			</div>
			<hr>
			<div class="wsfw-secion-wallet_layout">
			  <span><b><?php esc_html_e( 'Wallet Layout Settings', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
				<?php
					  $wsfw_wallet_action_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfw_wallet_action_different_layout_settings );
				if ( ! empty( $wsfw_wallet_action_html ) ) {
					echo wp_kses_post( $wsfw_wallet_action_html );
				}
				?>
			</div>
			<hr>
			<div class="wsfw-secion-wallet_instant_discount">
			  <span><b><?php esc_html_e( 'Wallet Payment Instant Discount Settingss', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
				<?php
					  $wsfw_wallet_action_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfw_wallet_action_payment_settings );
				if ( ! empty( $wsfw_wallet_action_html ) ) {
					echo wp_kses_post( $wsfw_wallet_action_html );
				}
				?>
			</div>
			<hr>
			<?php
			if ( in_array( 'points-and-rewards-for-woocommerce/points-rewards-for-woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				?>
					<div class="wsfw-secion-gamification-rule">
					  <span><b><?php esc_html_e( 'Win Wheel Rule', 'wallet-system-for-woocommerce-pro' ); ?></b></span>
					<?php
						  $wsfw_wallet_action_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfw_wallet_action_gamification_rule_settings );
					if ( ! empty( $wsfw_wallet_action_html ) ) {
						echo wp_kses_post( $wsfw_wallet_action_html );
					}
					?>
					</div>
					<hr>
					<?php
			}
		}
	}


	/**
	 * Admin wallet restriction.
	 *
	 * @return void
	 */
	public function wsfw_admis_save_tab_settings_for_wallet_restriction() {
		global $wsfwp_wps_wsfwp_obj;
		if ( isset( $_POST['wsfw_button_wallet_restriction'] ) ) {

			$nonce = ( isset( $_POST['updatenoncewallet_restriction'] ) ) ? sanitize_text_field( wp_unslash( $_POST['updatenoncewallet_restriction'] ) ) : '';
			if ( wp_verify_nonce( $nonce ) ) {

				$wps_wsfwp_wallet_transfer_restriction_enable = ( isset( $_POST['wps_wsfwp_wallet_transfer_restriction_enable'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_transfer_restriction_enable'] ) ) : '';
				$wsfwp_min_wallet_transfer_amount = ( isset( $_POST['wsfwp_min_wallet_transfer_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wsfwp_min_wallet_transfer_amount'] ) ) : 0;
				$wsfwp_max_wallet_transfer_amount = ( isset( $_POST['wsfwp_max_wallet_transfer_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wsfwp_max_wallet_transfer_amount'] ) ) : 0;
				$wps_wsfwp_wallet_withdrawal_restriction_enable = ( isset( $_POST['wps_wsfwp_wallet_withdrawal_restriction_enable'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_withdrawal_restriction_enable'] ) ) : '';
				$wsfwp_min_wallet_withdrawal_amount = ( isset( $_POST['wsfwp_min_wallet_withdrawal_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wsfwp_min_wallet_withdrawal_amount'] ) ) : 0;
				$wsfwp_max_wallet_withdrawal_amount = ( isset( $_POST['wsfwp_max_wallet_withdrawal_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wsfwp_max_wallet_withdrawal_amount'] ) ) : 0;

				$wps_wsfwp_wallet_recharge_restriction_enable = ( isset( $_POST['wps_wsfwp_wallet_recharge_restriction_enable'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_recharge_restriction_enable'] ) ) : '';
				$wsfwp_min_wallet_recharge_amount = ( isset( $_POST['wsfwp_min_wallet_recharge_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wsfwp_min_wallet_recharge_amount'] ) ) : 0;
				$wsfwp_max_wallet_recharge_amount = ( isset( $_POST['wsfwp_max_wallet_recharge_amount'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wsfwp_max_wallet_recharge_amount'] ) ) : 0;

				update_option( 'wps_wsfwp_wallet_transfer_restriction_enable', $wps_wsfwp_wallet_transfer_restriction_enable );

				update_option( 'wsfwp_min_wallet_transfer_amount', $wsfwp_min_wallet_transfer_amount );

				update_option( 'wsfwp_max_wallet_transfer_amount', $wsfwp_max_wallet_transfer_amount );

				update_option( 'wps_wsfwp_wallet_withdrawal_restriction_enable', $wps_wsfwp_wallet_withdrawal_restriction_enable );

				update_option( 'wsfwp_min_wallet_withdrawal_amount', $wsfwp_min_wallet_withdrawal_amount );

				update_option( 'wsfwp_max_wallet_withdrawal_amount', $wsfwp_max_wallet_withdrawal_amount );

				update_option( 'wps_wsfwp_wallet_recharge_restriction_enable', $wps_wsfwp_wallet_recharge_restriction_enable );
				update_option( 'wsfwp_min_wallet_recharge_amount', $wsfwp_min_wallet_recharge_amount );
				update_option( 'wsfwp_max_wallet_recharge_amount', $wsfwp_max_wallet_recharge_amount );

				$wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_admin_notice( esc_html__( 'Setting Saved !', 'wallet-system-for-woocommerce-pro' ), 'success' );

			}
		} else {
			$wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_admin_notice( esc_html__( 'Failed security check', 'wallet-system-for-woocommerce-pro' ), 'error' );
		}
	}


	/**
	 * Wallet System for WooCommerce save tab settings.
	 *
	 * @since 1.0.0
	 */
	public function wps_wsfw_admin_save_tab_settings_for_wallet_promotions_tab() {

		global $wsfw_wps_wsfw_obj;
		if ( isset( $_POST['wsfw_button_wallet_promotions_tab'] ) ) {
			$nonce = ( isset( $_POST['updatenoncewallet_action'] ) ) ? sanitize_text_field( wp_unslash( $_POST['updatenoncewallet_action'] ) ) : '';
			if ( wp_verify_nonce( $nonce ) ) {

				if ( isset( $_POST['wallet_promotions_data_title'] ) ) {
					update_option( 'wallet_promotions_data_title', map_deep( wp_unslash( $_POST['wallet_promotions_data_title'] ), 'sanitize_text_field' ) );
				}
				if ( isset( $_POST['wallet_promotions_data_content'] ) ) {
					update_option( 'wallet_promotions_data_content', map_deep( wp_unslash( $_POST['wallet_promotions_data_content'] ), 'sanitize_text_field' ) );
				}
				if ( isset( $_POST['wps_wsfwp_wallet_promotion_tab_enable'] ) ) {
					update_option( 'wps_wsfwp_wallet_promotion_tab_enable', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_promotion_tab_enable'] ) ) );
				} else {
					update_option( 'wps_wsfwp_wallet_promotion_tab_enable', 'no' );

				}
				if ( isset( $_POST['wps_wsfwp_wallet_promotion_tab_limited_offer_enable'] ) ) {
					update_option( 'wps_wsfwp_wallet_promotion_tab_limited_offer_enable', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_promotion_tab_limited_offer_enable'] ) ) );
				} else {
					update_option( 'wps_wsfwp_wallet_promotion_tab_limited_offer_enable', 'no' );

				}

				$wps_wsfw_error_text = esc_html__( 'Settings saved !', 'wallet-system-for-woocommerce-pro' );
				$wsfw_wps_wsfw_obj->wps_wsfw_plug_admin_notice( $wps_wsfw_error_text, 'success' );

			} else {
				$wsfw_wps_wsfw_obj->wps_wsfw_plug_admin_notice( esc_html__( 'Failed security check', 'wallet-system-for-woocommerce-pro' ), 'error' );
			}
		}

		if ( isset( $_POST['wsfw_button_wallet_withdrawal_paypal_tab'] ) ) {
			$nonce = ( isset( $_POST['updatenoncewallet_paypal'] ) ) ? sanitize_text_field( wp_unslash( $_POST['updatenoncewallet_paypal'] ) ) : '';
			if ( wp_verify_nonce( $nonce ) ) {

				if ( isset( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable'] ) ) {
					update_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable'] ) ) );
				} else {
					update_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable', 'no' );

				}

				if ( isset( $_POST['wps_wsfwp_wallet_withdrawal_paypal_dropdown'] ) ) {
					update_option( 'wps_wsfwp_wallet_withdrawal_paypal_dropdown', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_withdrawal_paypal_dropdown'] ) ) );
				} else {
					update_option( 'wps_wsfwp_wallet_withdrawal_paypal_dropdown', 'no' );
				}
				if ( isset( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable_client_id'] ) ) {
					update_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_client_id', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable_client_id'] ) ) );
				}
				if ( isset( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable_sceret_key'] ) ) {
					update_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_sceret_key', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable_sceret_key'] ) ) );
				}
				if ( isset( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable_mode'] ) ) {
					update_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_mode', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_withdrawal_paypal_enable_mode'] ) ) );
				}
				$wps_wsfw_error_text = esc_html__( 'Settings saved !', 'wallet-system-for-woocommerce-pro' );
				$wsfw_wps_wsfw_obj->wps_wsfw_plug_admin_notice( $wps_wsfw_error_text, 'success' );

			} else {
				$wsfw_wps_wsfw_obj->wps_wsfw_plug_admin_notice( esc_html__( 'Failed security check', 'wallet-system-for-woocommerce-pro' ), 'error' );
			}
		}
	}


	/**
	 * Wallet System for WooCommerce save tab settings.
	 *
	 * @since 1.0.0
	 */
	public function wps_wsfw_admin_save_tab_settings_for_wallet_rechargde_tab() {

		global $wsfw_wps_wsfw_obj;
		if ( isset( $_POST['wsfw_button_wallet_recharge_tab'] ) ) {
			$nonce = ( isset( $_POST['updatenoncewallet_action'] ) ) ? sanitize_text_field( wp_unslash( $_POST['updatenoncewallet_action'] ) ) : '';
			if ( wp_verify_nonce( $nonce ) ) {

				$wps_wsfw_gen_flag     = false;

				$wsfw_wallet_action_settings_add_bookie_array = apply_filters( 'wsfw_wallet_action_settings_add_bookie_array', array() );

				$wsfw_settings_wallet_action_new_registration = $wsfw_wallet_action_settings_add_bookie_array;

				if ( isset( $_POST['wps_wallet_action_bookie_array'] ) ) {
					update_option( 'wps_wallet_action_recharge_tab_array', map_deep( wp_unslash( $_POST['wps_wallet_action_bookie_array'] ), 'sanitize_text_field' ) );
				}
				if ( isset( $_POST['wps_wsfwp_wallet_recharge_tab_enable'] ) ) {
					update_option( 'wps_wsfwp_wallet_recharge_tab_enable', sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_wallet_recharge_tab_enable'] ) ) );
				} else {
					update_option( 'wps_wsfwp_wallet_recharge_tab_enable', 'no' );

				}

				$wps_wsfw_error_text = esc_html__( 'Settings saved !', 'wallet-system-for-woocommerce-pro' );
				$wsfw_wps_wsfw_obj->wps_wsfw_plug_admin_notice( $wps_wsfw_error_text, 'success' );

			} else {
				$wsfw_wps_wsfw_obj->wps_wsfw_plug_admin_notice( esc_html__( 'Failed security check', 'wallet-system-for-woocommerce-pro' ), 'error' );
			}
		}
	}


	/**
	 * Function to initiated withdraw.
	 *
	 * @param [type] $user_id is the user id who request for withdrawal.
	 * @param [type] $withdrawal_amount is the amount requested by user.
	 * @param [type] $withdrawal_mail_id withdrawal mail id of paypal.
	 * @param [type] $transaction_type_paypal transaction details for paypal.
	 * @param [type] $withdrawal_id is the current withdraw id.
	 * @return string
	 */
	public function wps_wallet_withdrawal_through_paypal_( $user_id, $withdrawal_amount, $withdrawal_mail_id, $transaction_type_paypal, $withdrawal_id ) {

		$client_id = get_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_client_id' );
		$secret_key = get_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_sceret_key' );
		$mode = get_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable_mode' );
		$response = $this->wps_wgm_generate_token( $withdrawal_mail_id, $client_id, $secret_key, $mode, $withdrawal_amount );

		if ( $response ) {

			$this->show_message_on_form_submit( esc_html__( "error i.e '{$response}'", 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
		} else {
			$walletamount   = get_user_meta( $user_id, 'wps_wallet', true );

			$wallet_payment_gateway = new Wallet_System_For_Woocommerce();
			$wallet_user       = get_user_by( 'id', $user_id );

			$send_email_enable = get_option( 'wps_wsfw_enable_email_notification_for_wallet_update', '' );

			if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {
				$user_name  = $wallet_user->first_name . ' ' . $wallet_user->last_name;
				$mail_text  = sprintf( 'Hello %s,<br/>', $user_name );
				$mail_text .= __( 'Wallet debited by ', 'wallet-system-for-woocommerce-pro' ) . wc_price( $withdrawal_amount, array( 'currency' => get_woocommerce_currency() ) ) . __( ' through wallet Transfer into paypal. Updated Wallet Balance is : ', 'wallet-system-for-woocommerce-pro' ) . wc_price( $walletamount, array( 'currency' => get_woocommerce_currency() ) );
				$to         = $wallet_user->user_email;
				$from       = get_option( 'admin_email' );
				$subject    = __( 'Wallet updating notification', 'wallet-system-for-woocommerce-pro' );
				$headers    = 'MIME-Version: 1.0' . "\r\n";
				$headers   .= 'Content-Type: text/html;  charset=UTF-8' . "\r\n";
				$headers   .= 'From: ' . $from . "\r\n" .
					'Reply-To: ' . $to . "\r\n";

				$wallet_payment_gateway->send_mail_on_wallet_updation( $to, $subject, $mail_text, $headers );

			}

			$transaction_type_paypal = __( 'Wallet debited through withdrawal wallet transfer into paypal : ', 'wallet-system-for-woocommerce-pro' ) . $withdrawal_mail_id . __( ' through user withdrawing request ', 'wallet-system-for-woocommerce-pro' ) . '<a href="#" >#' . $withdrawal_id . '</a>';

			$transaction_data = array(
				'user_id'          => $user_id,
				'amount'           => $withdrawal_amount,
				'currency'         => get_woocommerce_currency(),
				'payment_method'   => esc_html__( 'Transfer to paypal', 'wallet-system-for-woocommerce-pro' ),
				'transaction_type' => htmlentities( $transaction_type_paypal ),
				'transaction_type_1' => 'debit',
				'order_id'         => $withdrawal_id,
				'note'             => '',

			);

			$return_data = '';
			$data = $wallet_payment_gateway->insert_transaction_data_in_table( $transaction_data );
			if ( $data ) {
				$return_data = '<div class="woocommerce-message woocommerce-message--success"><div class="woocommerce-message-content">
			Wallet debited through wallet transfer into paypal ' . $withdrawal_mail_id . '</div></div>';

			}
			return $return_data;
		}
	}

	/**
	 * Show message on form submit
	 *
	 * @param string $wpg_message message to be shown on form submission.
	 * @param string $type error type.
	 * @return void
	 */
	public function show_message_on_form_submit( $wpg_message, $type = 'error' ) {
		$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
		echo wp_kses_post( $wpg_notice );
	}

	/**
	 * Generate token for client.
	 *
	 * @param [type] $withdrawal_mail_id mail id of paypal.
	 * @param [type] $client_id is the paypal client id.
	 * @param [type] $secret_key is the paypal secret key.
	 * @param [type] $mode is the mode of paypal.
	 * @param [type] $gift_card_transfer_amount is the amount of withdrawal.
	 * @return mixed
	 */
	public function wps_wgm_generate_token( $withdrawal_mail_id, $client_id, $secret_key, $mode, $gift_card_transfer_amount ) {
		$ch = curl_init();
		if ( 'test' === $mode ) {
			$PAYPAL_API_URL   = 'https://api-m.sandbox.paypal.com/';

		} else {
			$PAYPAL_API_URL   = 'https://api-m.paypal.com/';

		}
		curl_setopt( $ch, CURLOPT_URL, $PAYPAL_API_URL . 'v1/oauth2/token' );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_USERPWD, $client_id . ':' . $secret_key );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials' );
		$result = curl_exec( $ch );
		if ( empty( $result ) ) {
			die( 'Error: No response.' );
		} else {
			$json = json_decode( $result );
			$accessToken = $json->access_token;
			$result = $this->wps_wgm_payout( $accessToken, $mode, $withdrawal_mail_id, $gift_card_transfer_amount );
			return $result;
		}
		curl_close( $ch );
		// return $accessToken;.
	}

	/**
	 * Send payout message to user.
	 *
	 * @param [type] $token is the valid token of paypal.
	 * @param [type] $test_mode is he mode of paypal account.
	 * @param [type] $email is the user mail id.
	 * @param [type] $current_bal is the user current balance.
	 * @return mixed
	 */
	public function wps_wgm_payout( $token, $test_mode, $email, $current_bal ) {
		if ( 'test' === $test_mode ) {
			$PAYPAL_API_URL   = 'https://api-m.sandbox.paypal.com/';
		} else {
			$PAYPAL_API_URL   = 'https://api-m.paypal.com/';
		}
		$array_to_be_sent = array(
			'sender_batch_header' =>
			array(
				'sender_batch_id' => 'Payouts_' . (string) time(),
				'email_subject' => 'You have a payout!',
				'email_message' => 'You have received a payout! Thanks for using our service!',
			),
			'items' =>
			array(
				0 =>
				array(
					'recipient_type' => 'EMAIL',
					'amount' =>
					array(
						'value' => $current_bal,
						'currency' => 'USD',
					),
					'note' => 'Thanks for your patronage!',
					'sender_item_id' => (string) time(),
					'receiver' => $email,
				),
			),
		);
		$ch = curl_init();
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Bearer ' . $token;
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_URL, $PAYPAL_API_URL . 'v1/payments/payouts' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $array_to_be_sent ) );
		$result = curl_exec( $ch );
		if ( curl_errno( $ch ) ) {
			echo esc_html__( 'Error:', 'temp-woocommerce' ) . esc_html__( curl_error( $ch ) );
		}
		curl_close( $ch );
		$json = json_decode( $result );
		$response = $this->wps_wgm_check_status_payout( $token, $json->batch_header->sender_batch_header->sender_batch_id, $test_mode );
		return $response;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $token is the valid token of paypal.
	 * @param [type] $test_mode is he mode of paypal account.
	 * @param string $batch_id is the batch id of current transaction.
	 * @return mixed
	 */
	public function wps_wgm_check_status_payout( $token, $test_mode, $batch_id = 'Payouts_2018_10000791' ) {
		if ( 'test' === $test_mode ) {
			$PAYPAL_API_URL   = 'https://api.sandbox.paypal.com/v1/payments/';
		} else {
			$PAYPAL_API_URL   = 'https://api-m.paypal.com/v1/payments/';

		}
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $PAYPAL_API_URL . $batch_id );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET' );
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Bearer ' . $token;
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		$result = curl_exec( $ch );
		if ( curl_errno( $ch ) ) {
			echo esc_html__( 'Error:', 'temp-woocommerce' ) . esc_html__( curl_error( $ch ) );
		}
		curl_close( $ch );
		return $result;
	}
}


