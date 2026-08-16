<?php
/**
 * Plugin Name: Email Template Customizer for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/woocommerce-email-template-customizer/
 * Description: Customize your WooCommerce emails effortlessly. Drag and drop elements, edit layouts, and match your store's design without coding knowledge.
 * Version: 1.3.2
 * Author: VillaTheme
 * Author URI: https://villatheme.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: viwec-email-template-customizer
 * Domain Path: /languages
 * Copyright 2019 - 2026 VillaTheme.com. All rights reserved.
 * Requires at least: 5.0
 * Tested up to: 7.0
 * WC requires at least: 7.0
 * WC tested up to: 10.8
 * Requires PHP: 7.0
 * Requires Plugins: woocommerce
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
//compatible with 'High-Performance order storage (COT)'
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );
if ( is_plugin_active( 'woocommerce-email-template-customizer/woocommerce-email-template-customizer.php' ) ) {
	return;
}
define( 'VIWEC_VER', '1.3.2' );
define( 'VIWEC_NAME', 'Email Template Customizer for WooCommerce' );

$plugin_url = plugin_dir_url( __FILE__ );

define( 'VIWEC_SLUG', 'woocommerce-email-template-customizer' );
define( 'VIWEC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VIWEC_INCLUDES', VIWEC_DIR . "includes" . DIRECTORY_SEPARATOR );
define( 'VIWEC_SUPPORT', VIWEC_INCLUDES . "support" . DIRECTORY_SEPARATOR );
define( 'VIWEC_TEMPLATES', VIWEC_INCLUDES . "templates" . DIRECTORY_SEPARATOR );
define( 'VIWEC_LANGUAGES', VIWEC_DIR . "languages" . DIRECTORY_SEPARATOR );

define( 'VIWEC_CSS', $plugin_url . "assets/css/" );
define( 'VIWEC_JS', $plugin_url . "assets/js/" );
define( 'VIWEC_IMAGES', $plugin_url . "assets/img/" );
if ( ! class_exists( 'Woo_Email_Template_Customizer' ) ) {
	class Woo_Email_Template_Customizer {
		public $err_message;
		public $wp_version_require = '5.0';
		public $wc_version_require = '7.0';
		public $php_version_require = '7.0';

		public function __construct() {


			add_action( 'plugins_loaded', function () {

				if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
					require_once VIWEC_SUPPORT . 'support.php';
				}

				$environment = new \VillaTheme_Require_Environment( [
						'plugin_name'     => VIWEC_NAME,
						'php_version'     => $this->php_version_require,
						'wp_version'      => $this->wp_version_require,
						'require_plugins' => [
							[
								'slug' => 'woocommerce',
								'name' => 'WooCommerce',
								'defined_version' => 'WC_VERSION',
								'version' => $this->wc_version_require,
							],
						]
					]
				);

				if ( $environment->has_error() ) {
					return;
				}

				if ( is_file( VIWEC_INCLUDES . 'init.php' ) ) {
					require_once VIWEC_INCLUDES . 'init.php';
				}

				add_action( 'init', [ $this, 'viwec_init' ] );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_actions_link' ) );

			} );
		}

		public function viwec_init() {
			$check_exist   = get_posts( [ 'post_type' => 'viwec_template', 'numberposts' => 1 ] );
			$check_default = get_option( 'viwec_email_default_setting', false );
			if ( empty( $check_exist ) && ! $check_default ) {
				$default_subject = \VIWEC\INC\Email_Samples::default_subject();
				$templates       = \VIWEC\INC\Email_Samples::sample_templates();
				if ( empty( $templates ) || ! is_array( $templates ) ) {
					return;
				}
				$site_title      = get_option( 'blogname' );
				$rtl = is_rtl();
				foreach ( $templates as $key => $template ) {
					$args     = [
						'post_title'  => $default_subject[ $key ] ? str_replace( '{site_title}', $site_title, $default_subject[ $key ] ) : '',
						'post_status' => 'publish',
						'post_type'   => 'viwec_template',
					];
					$post_id  = wp_insert_post( $args );
					$template = $template['basic']['data'];
					$template = str_replace( '\\', '\\\\', $template );
					update_post_meta( $post_id, 'viwec_settings_type', $key );
					update_post_meta( $post_id, 'viwec_email_structure', $template );
					if ($rtl) {
						update_post_meta( $post_id, 'viwec_settings_direction', 'rtl' );
					}
				}
				update_option( 'viwec_email_update_button', true, 'no' );
				update_option( 'viwec_email_default_setting', true, 'no' );
			}
		}

		public function plugin_actions_link( $links ) {
			if ( ! $this->err_message ) {
				$settings_link = '<a href="' . admin_url( 'edit.php?post_type=viwec_template' ) . '">' . __( 'Settings', 'viwec-email-template-customizer' ) . '</a>';
				array_unshift( $links, $settings_link );
			}

			return $links;
		}
	}

	new Woo_Email_Template_Customizer();
}