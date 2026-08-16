<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link    https://wpswings.com/
 * @since   1.0.0
 * @package Wallet_System_For_Woocommerce_Pro
 *
 * @wordpress-plugin
 * Plugin Name:       Wallet System for WooCommerce Pro
 * Plugin URI:        https://wpswings.com/product/wallet-system-for-woocommerce-pro/?utm_source=wpswings-wallet-pro&utm_medium=wallet-pro-backend&utm_campaign=pro-plugin
 * Description:       <code><strong>Wallet System for WooCommerce Pro</strong></code> allows users to create a digital wallet on online store and purchase products & services using wallet amount.<a href="https://wpswings.com/woocommerce-plugins/?utm_source=wpswings-wallet-shop&utm_medium=wallet-pro-backend&utm_campaign=shop-page" target="_blank"> Elevate your e-commerce store by exploring more on <strong> WP Swings </strong></a>
 * Version:           2.3.7
 * Author:            WP Swings
 * Author URI:        https://wpswings.com/?utm_source=wpswings-wallet-official&utm_medium=wallet-pro-backend&utm_campaign=official
 * Text Domain:       wallet-system-for-woocommerce-pro
 * Domain Path:       /languages
 * Requires Plugins: woocommerce
 * WC Requires at least: 5.5.0
 * WC tested up to: 9.7.1
 * WP Requires at least: 5.5.0
 * WP tested up to: 6.7.2
 * Requires PHP: 7.2.24
 *
 * License:           WP Swings License
 * License URI:       https://wpswings.com/license-agreement.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

$active_plugins = (array) get_option( 'active_plugins', array() );
if ( is_multisite() ) {
	$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
}
$activated = true;
if ( ! ( array_key_exists( 'wallet-system-for-woocommerce/wallet-system-for-woocommerce.php', $active_plugins ) || in_array( 'wallet-system-for-woocommerce/wallet-system-for-woocommerce.php', $active_plugins ) ) ) {
	$activated = false;
}

if ( $activated ) {
	/**
	 * Define plugin constants.
	 *
	 * @since 1.0.0
	 */
	function define_wallet_system_for_woocommerce_pro_constants() {

		wallet_system_for_woocommerce_pro_constants( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION', '2.3.7' );
		wallet_system_for_woocommerce_pro_constants( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH', plugin_dir_path( __FILE__ ) );
		wallet_system_for_woocommerce_pro_constants( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL', plugin_dir_url( __FILE__ ) );
		wallet_system_for_woocommerce_pro_constants( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_LICENSE_SERVER_URL', 'https://wpswings.com' );
		wallet_system_for_woocommerce_pro_constants( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_ITEM_REFERENCE', 'Wallet System for WooCommerce Pro' );
	}

	/**
	 * Define wps-site update feature.
	 *
	 * @since 1.0.0
	 */
	function auto_update_wallet_system_for_woocommerce_pro() {
		$wps_wsfwp_license_key = get_option( 'wps_wsfwp_lic_key', '' );
		if ( ! defined( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_SPECIAL_SECRET_KEY' ) ) {
			define( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_SPECIAL_SECRET_KEY', '59f32ad2f20102.74284991' );
		}
		wallet_system_for_woocommerce_pro_constants( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_BASE_FILE', __FILE__ );
		wallet_system_for_woocommerce_pro_constants( 'WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_LICENSE_KEY', $wps_wsfwp_license_key );
		include_once 'class-wps-wallet-system-for-woocommerce-pro-update.php';
	}

	/**
	 * Callable function for defining plugin constants.
	 *
	 * @param String $key   Key for contant.
	 * @param String $value value for contant.
	 * @since 1.0.0
	 */
	function wallet_system_for_woocommerce_pro_constants( $key, $value ) {

		if ( ! defined( $key ) ) {

			define( $key, $value );
		}
	}

	/**
	 * The code that runs during plugin activation.
	 * This action is documented in includes/class-wallet-system-for-woocommerce-pro-activator.php
	 *
	 * @param  boolean $network_wide networkwide activate.
	 * @return void
	 */
	function activate_wallet_system_for_woocommerce_pro( $network_wide ) {
		include_once plugin_dir_path( __FILE__ ) . 'includes/class-wallet-system-for-woocommerce-pro-activator.php';
		Wallet_System_For_Woocommerce_Pro_Activator::wallet_system_for_woocommerce_pro_activate( $network_wide );
		$wps_wsfwp_active_plugin = get_option( 'wps_all_plugins_active', false );
		if ( is_array( $wps_wsfwp_active_plugin ) && ! empty( $wps_wsfwp_active_plugin ) ) {
			$wps_wsfwp_active_plugin['wallet-system-for-woocommerce-pro'] = array(
				'plugin_name' => __( 'Wallet System for WooCommerce Pro', 'wallet-system-for-woocommerce-pro' ),
				'active' => '1',
			);
		} else {
			$wps_wsfwp_active_plugin = array();
			$wps_wsfwp_active_plugin['wallet-system-for-woocommerce-pro'] = array(
				'plugin_name' => __( 'Wallet System for WooCommerce Pro', 'wallet-system-for-woocommerce-pro' ),
				'active' => '1',
			);
		}
		update_option( 'wps_all_plugins_active', $wps_wsfwp_active_plugin );

		if ( ! wp_next_scheduled( 'wps_wsfwp_check_license_daily' ) ) {
			wp_schedule_event( time(), 'daily', 'wps_wsfwp_check_license_daily' );
		}
	}

	/**
	 * The code that runs during plugin deactivation.
	 * This action is documented in includes/class-wallet-system-for-woocommerce-pro-deactivator.php
	 */
	function deactivate_wallet_system_for_woocommerce_pro() {
		include_once plugin_dir_path( __FILE__ ) . 'includes/class-wallet-system-for-woocommerce-pro-deactivator.php';
		Wallet_System_For_Woocommerce_Pro_Deactivator::wallet_system_for_woocommerce_pro_deactivate();
		$wps_wsfwp_deactive_plugin = get_option( 'wps_all_plugins_active', false );
		if ( is_array( $wps_wsfwp_deactive_plugin ) && ! empty( $wps_wsfwp_deactive_plugin ) ) {
			foreach ( $wps_wsfwp_deactive_plugin as $wps_wsfwp_deactive_key => $wps_wsfwp_deactive ) {
				if ( 'wallet-system-for-woocommerce-pro' === $wps_wsfwp_deactive_key ) {
					$wps_wsfwp_deactive_plugin[ $wps_wsfwp_deactive_key ]['active'] = '0';
				}
			}
		}
		update_option( 'wps_all_plugins_active', $wps_wsfwp_deactive_plugin );
	}

	register_activation_hook( __FILE__, 'activate_wallet_system_for_woocommerce_pro' );
	register_deactivation_hook( __FILE__, 'deactivate_wallet_system_for_woocommerce_pro' );

	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	include plugin_dir_path( __FILE__ ) . 'includes/class-wallet-system-for-woocommerce-pro.php';

	/**
	 * Creating table whenever a new blog is created
	 *
	 * @param  object $new_site New site object.
	 * @return void
	 */
	function wps_wsfwp_on_create_blog( $new_site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active_for_network( 'wallet-system-for-woocommerce-pro/wallet-system-for-woocommerce-pro.php' ) ) {
			$license_key   = get_option( 'wps_wsfwp_lic_key' );
			$license_valid = get_option( 'wps_wsfwp_pro_valid_license' );
			$blog_id       = $new_site->blog_id;
			switch_to_blog( $blog_id );
			update_option( 'wps_wsfwp_lic_key', $license_key );
			update_option( 'wps_wsfwp_pro_valid_license', $license_valid );
			include_once plugin_dir_path( __FILE__ ) . 'includes/class-wallet-system-for-woocommerce-pro-activator.php';
			Wallet_System_For_Woocommerce_Pro_Activator::wps_wsfwp_create_wallet_page();
			Wallet_System_For_Woocommerce_Pro_Activator::wps_wsfwp_activated_timestamp();
			restore_current_blog();
		}
	}
	add_action( 'wp_initialize_site', 'wps_wsfwp_on_create_blog', 900 );

	/**
	 * Begins execution of the plugin.
	 *
	 * Since everything within the plugin is registered via hooks,
	 * then kicking off the plugin from this point in the file does
	 * not affect the page life cycle.
	 *
	 * @since 1.0.0
	 */
	function run_wallet_system_for_woocommerce_pro() {
		define_wallet_system_for_woocommerce_pro_constants();
		auto_update_wallet_system_for_woocommerce_pro();
		$wsfwp_wsfwp_plugin_standard = new Wallet_System_For_Woocommerce_Pro();
		$wsfwp_wsfwp_plugin_standard->wsfwp_run();
		$GLOBALS['wsfwp_wps_wsfwp_obj'] = $wsfwp_wsfwp_plugin_standard;
	}
	run_wallet_system_for_woocommerce_pro();

	// Add settings link on plugin page.
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wallet_system_for_woocommerce_pro_settings_link' );

	/**
	 * Settings link.
	 *
	 * @since 1.0.0
	 * @param Array $links Settings link array.
	 */
	function wallet_system_for_woocommerce_pro_settings_link( $links ) {

		$my_link = array(
			'<a href="' . admin_url( 'admin.php?page=wallet_system_for_woocommerce_menu' ) . '">' . __( 'Settings', 'wallet-system-for-woocommerce-pro' ) . '</a>',
		);
		return array_merge( $my_link, $links );
	}

	/**
	 * Adding custom setting links at the plugin activation list.
	 *
	 * @param  array  $links_array      array containing the links to plugin.
	 * @param  string $plugin_file_name plugin file name.
	 * @return array
	 */
	function wallet_system_for_woocommerce_pro_custom_settings_at_plugin_tab( $links_array, $plugin_file_name ) {
		if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
			$links_array[] = '<a href="https://demo.wpswings.com/wallet-system-for-woocommerce-pro/?utm_source=wpswings-wallet-demo&utm_medium=wallet-pro-backend&utm_campaign=Demo" target="_blank"><img src="' . esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL ) . 'admin/image/Demo.svg" class="wps-info-img" alt="Demo image">' . __( 'Demo', 'wallet-system-for-woocommerce-pro' ) . '</a>';
			$links_array[] = '<a href="https://docs.wpswings.com/wallet-system-for-woocommerce/?utm_source=wpswings-wallet-doc&utm_medium=wallet-org-backend&utm_campaign=wallet-doc" target="_blank"><img src="' . esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL ) . 'admin/image/Documentation.svg" class="wps-info-img" alt="documentation image">' . __( 'Documentation', 'wallet-system-for-woocommerce-pro' ) . '</a>';
			$links_array[] = '<a href="https://www.youtube.com/watch?v=mnMfoSL0aZc&feature=youtu.be" target="_blank"><img src="' . esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL ) . 'admin/image/YouTube 32px.png" class="wps-info-img" alt="video image">' . __( 'Video', 'wallet-system-for-woocommerce-pro' ) . '</a>';
			$links_array[] = '<a href="https://wpswings.com/submit-query/?utm_source=wpswings-wallet-support&utm_medium=wallet-pro-backend&utm_campaign=support" target="_blank"><img src="' . esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL ) . 'admin/image/Support.svg" class="wps-info-img" alt="support image">' . __( 'Support', 'wallet-system-for-woocommerce-pro' ) . '</a>';
			$links_array[] = '<a href="https://wpswings.com/woocommerce-services/?utm_source=wpswings-wallet-services&utm_medium=wallet-pro-backend&utm_campaign=woocommerce-services" target="_blank"><img style="display: inline-block; margin-right: 6px; margin-top: -3px; max-width: 15px;" src="' . esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL ) . 'admin/image/Services.svg" class="wps-info-img" alt="support image">' . __( 'Services', 'wallet-system-for-woocommerce-pro' ) . '</a>';
			$links_array[] = '<a href="https://wpswings.com/product/wallet-system-for-woocommerce-pro/?utm_source=wpswings-wallet-review&utm_medium=wallet-org-backend&utm_campaign=wallet-review#respond" target="_blank"><img style="display: inline-block; margin-right: 6px; margin-top: -3px; max-width: 15px;"  src="' . esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL ) . 'admin/image/review-icon.svg" class="wps-info-img" alt="support image">' . __( 'Review', 'wallet-system-for-woocommerce-pro' ) . '</a>';
		}
		return $links_array;
	}
	add_filter( 'plugin_row_meta', 'wallet_system_for_woocommerce_pro_custom_settings_at_plugin_tab', 10, 2 );
} else {
	// To deactivate plugin if woocommerce is not installed.
	add_action( 'admin_init', 'wps_wsfwp_plugin_deactivate' );

	/**
	 * Call Admin notices
	 *
	 * @name wps_wsfwp_plugin_deactivate()
	 */
	function wps_wsfwp_plugin_deactivate() {
		deactivate_plugins( plugin_basename( __FILE__ ), true );
		unset( $_GET['activate'] );
		add_action( 'admin_notices', 'wps_wsfwp_plugin_error_notice', 10 );
	}

	/**
	 * Show warning message if Wallet System For WooCommerce is not install
	 *
	 * @name wps_wsfwp_plugin_error_notice()
	 */
	function wps_wsfwp_plugin_error_notice() {
		?>
		<div class="error notice is-dismissible">
			<p>
		<?php esc_html_e( 'Wallet System For WooCommerce is not activated, Please activate Wallet System For WooCommerce first to install Wallet System for WooCommerce Pro.', 'wallet-system-for-woocommerce-pro' ); ?>
			</p>
		</div>
		<?php
	}
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

