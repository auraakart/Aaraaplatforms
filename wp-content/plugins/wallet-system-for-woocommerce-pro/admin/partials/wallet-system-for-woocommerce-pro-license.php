<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to show license tab content
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

?>
<div class="wps-wsfw-gen-section-form wps-wsfwp_activate_license"> 
	<h4><?php esc_html_e( 'Your License', 'wallet-system-for-woocommerce-pro' ); ?></h4>
	<?php
	$license_key = get_option( 'wps_wsfwp_lic_key', '' );
	?>
	<form id="wps_wsfwp_license_form" > 
		<div class="wpg-secion-wrap">
			<div class="wps-form-group wps-form-group2">
				<div class="wps-form-group__control">
					<p><?php esc_html_e( 'This is the License Activation Panel. After purchasing extension from WP Swings you will get the purchase code of this extension. Please verify your purchase below so that you can use feature of Wallet System for WooCommerce Pro plugin.', 'wallet-system-for-woocommerce-pro' ); ?></p>
					<p>
						<label for="license_key"><?php esc_html_e( 'Purchase Code : ', 'wallet-system-for-woocommerce-pro' ); ?></label>
						<input type="text" id="license_key" name="license_key" class="wsfw-number-class" value="<?php echo esc_attr( $license_key ); ?>" placeholder="<?php esc_attr_e( 'Enter License key', 'wallet-system-for-woocommerce-pro' ); ?>" >
					</p>
					<p id="wps_wsfwp_license_activation_status"></p>
					<input type="submit" id="activate_license" class="wps-btn wps-btn__filled" name="activate_license"  value="<?php esc_attr_e( 'Activate License', 'wallet-system-for-woocommerce-pro' ); ?>">
				</div>
			</div>
		</div>
	</form>

</div>
