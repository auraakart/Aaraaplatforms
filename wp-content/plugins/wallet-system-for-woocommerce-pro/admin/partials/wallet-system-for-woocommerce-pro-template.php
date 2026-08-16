<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the html field for general tab.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wsfwp_wps_wsfwp_obj;
$wsfwp_template_settings = apply_filters( 'wsfwp_template_settings_array', array() );
?>
<!--  template file for admin settings. -->
<div class="wws-section-wrap">
	<?php
		$wsfwp_template_html = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_generate_html( $wsfwp_template_settings );
		echo esc_html( $wsfwp_template_html );
	?>
</div>
