<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the html for system status.
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
// Template for showing information about system status.
global $wsfwp_wps_wsfwp_obj;
$wsfwp_default_status = $wsfwp_wps_wsfwp_obj->wps_wsfwp_plug_system_status();
$wsfwp_wordpress_details = is_array( $wsfwp_default_status['wp'] ) && ! empty( $wsfwp_default_status['wp'] ) ? $wsfwp_default_status['wp'] : array();
$wsfwp_php_details = is_array( $wsfwp_default_status['php'] ) && ! empty( $wsfwp_default_status['php'] ) ? $wsfwp_default_status['php'] : array();
?>
<div class="wps-wws-table-wrap">
	<div class="wps-col-wrap">
		<div id="wps-wws-table-inner-container" class="table-responsive mdc-data-table">
			<div class="mdc-data-table__table-container">
				<table class="wps-wws-table mdc-data-table__table wps-table" id="wps-wws-wp">
					<thead>
						<tr>
							<th class="mdc-data-table__header-cell"><?php esc_html_e( 'WP Variables', 'wallet-system-for-woocommerce-pro' ); ?></th>
							<th class="mdc-data-table__header-cell"><?php esc_html_e( 'WP Values', 'wallet-system-for-woocommerce-pro' ); ?></th>
						</tr>
					</thead>
					<tbody class="mdc-data-table__content">
						<?php if ( is_array( $wsfwp_wordpress_details ) && ! empty( $wsfwp_wordpress_details ) ) { ?>
							<?php foreach ( $wsfwp_wordpress_details as $wp_key => $wp_value ) { ?>
								<?php if ( isset( $wp_key ) && 'wp_users' != $wp_key ) { ?>
									<tr class="mdc-data-table__row">
										<td class="mdc-data-table__cell"><?php echo esc_html( $wp_key ); ?></td>
										<td class="mdc-data-table__cell"><?php echo esc_html( $wp_value ); ?></td>
									</tr>
								<?php } ?>
							<?php } ?>
						<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<div class="wps-col-wrap">
		<div id="wps-wws-table-inner-container" class="table-responsive mdc-data-table">
			<div class="mdc-data-table__table-container">
				<table class="wps-wws-table mdc-data-table__table wps-table" id="wps-wws-sys">
					<thead>
						<tr>
							<th class="mdc-data-table__header-cell"><?php esc_html_e( 'Sysytem Variables', 'wallet-system-for-woocommerce-pro' ); ?></th>
							<th class="mdc-data-table__header-cell"><?php esc_html_e( 'System Values', 'wallet-system-for-woocommerce-pro' ); ?></th>
						</tr>
					</thead>
					<tbody class="mdc-data-table__content">
						<?php if ( is_array( $wsfwp_php_details ) && ! empty( $wsfwp_php_details ) ) { ?>
							<?php foreach ( $wsfwp_php_details as $php_key => $php_value ) { ?>
								<tr class="mdc-data-table__row">
									<td class="mdc-data-table__cell"><?php echo esc_html( $php_key ); ?></td>
									<td class="mdc-data-table__cell"><?php echo esc_html( $php_value ); ?></td>
								</tr>
							<?php } ?>
						<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
