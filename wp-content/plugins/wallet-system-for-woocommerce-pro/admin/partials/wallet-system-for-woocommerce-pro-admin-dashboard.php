<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit(); // Exit if accessed directly.
}

global $wsfwp_wps_wsfwp_obj;
$wsfwp_active_tab   = isset( $_GET['wsfwp_tab'] ) ? sanitize_key( $_GET['wsfwp_tab'] ) : 'wallet-system-for-woocommerce-pro-general';
$wsfwp_default_tabs = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_default_tabs();
?>
<header>
	<div class="wps-header-container wps-bg-white wps-r-8">
		<h1 class="wps-header-title"><?php echo esc_attr( strtoupper( str_replace( '-', ' ', $wsfwp_wps_wsfwp_obj->wsfwp_get_plugin_name() ) ) ); ?></h1>
		<a href="https://docs.wpswings.com/wallet-system-for-woocommerce/?utm_source=wpswings-wallet-doc&utm_medium=wallet-org-backend&utm_campaign=wallet-doc" target="_blank" class="wps-link"><?php esc_html_e( 'Documentation', 'wallet-system-for-woocommerce-pro' ); ?></a>
		<span>|</span>
		<a href="https://wpswings.com/contact-us/" target="_blank" class="wps-link"><?php esc_html_e( 'Support', 'wallet-system-for-woocommerce-pro' ); ?></a>
	</div>
</header>

<main class="wps-main wps-bg-white wps-r-8">
	<nav class="wps-navbar">
		<ul class="wps-navbar__items">
			<?php
			if ( is_array( $wsfwp_default_tabs ) && ! empty( $wsfwp_default_tabs ) ) {

				foreach ( $wsfwp_default_tabs as $wsfwp_tab_key => $wsfwp_default_tabs ) {

					$wsfwp_tab_classes = 'wps-link ';

					if ( ! empty( $wsfwp_active_tab ) && $wsfwp_active_tab === $wsfwp_tab_key ) {
						$wsfwp_tab_classes .= 'active';
					}
					?>
					<li>
						<a id="<?php echo esc_attr( $wsfwp_tab_key ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wallet_system_for_woocommerce_pro_menu' ) . '&wsfwp_tab=' . esc_attr( $wsfwp_tab_key ) ); ?>" class="<?php echo esc_attr( $wsfwp_tab_classes ); ?>"><?php echo esc_html( $wsfwp_default_tabs['title'] ); ?></a>
					</li>
					<?php
				}
			}
			?>
		</ul>
	</nav>

	<section class="wps-section">
		<div>
			<?php
			do_action( 'wps_wsfwp_before_general_settings_form' );
			// if submenu is directly clicked on woocommerce.
			if ( empty( $wsfwp_active_tab ) ) {
				$wsfwp_active_tab = 'wps_wsfwp_plug_general';
			}

			// look for the path based on the tab id in the admin templates.
			$wsfwp_tab_content_path = 'admin/partials/' . $wsfwp_active_tab . '.php';

			$wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_load_template( $wsfwp_tab_content_path );

			do_action( 'wps_wsfwp_after_general_settings_form' );
			?>
		</div>
	</section>
