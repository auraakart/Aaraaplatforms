<?php
/**
 * Exit if accessed directly
 *
 * @package Wallet_System_For_Woocommerce_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );
$user_id          = get_current_user_id();
$logged_in_user   = wp_get_current_user();
if ( ! empty( $logged_in_user ) ) {
	$current_user_email = $logged_in_user->user_email ? $logged_in_user->user_email : '';
} else {
	$current_user_email = '';
}
$wallet_bal       = get_user_meta( $user_id, 'wps_wallet', true );
$wallet_bal       = ( ! empty( $wallet_bal ) ) ? $wallet_bal : 0;
$wallet_bal       = apply_filters( 'wps_wsfw_show_converted_price', $wallet_bal );
if ( ! function_exists( 'show_message_on_wallet_form_submit' ) ) {
	/**
	 * Show message on form submit
	 *
	 * @param string $wpg_message message to be shown on form submission.
	 * @param string $type error type.
	 * @return void
	 */
	function show_message_on_wallet_form_submit( $wpg_message, $type = 'error' ) {
		$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
		echo wp_kses_post( $wpg_notice );
	}
}

?>
<?php
if ( wc_post_content_has_shortcode( 'WPS_WALLET_TRANSFER' ) ) {
	?>
	<div class='content wps_wallet_shortcodes'>
		<?php
		if ( $wallet_bal > 0 ) {
			global $wp_session;
			if ( ! empty( $wp_session['wps_wallet_transfer_user_email'] ) ) {
				$useremail = $wp_session['wps_wallet_transfer_user_email'];
			} else {
				$useremail = '';
			}
			if ( ! empty( $wp_session['wps_wallet_transfer_amount'] ) ) {
				$transfer_amount = $wp_session['wps_wallet_transfer_amount'];
			} else {
				$transfer_amount = 0;
			}
			$show_additional_content = apply_filters( 'wps_wsfw_show_additional_content', '', $user_id, $useremail, $transfer_amount );
			if ( ! empty( $show_additional_content ) ) {
				echo wp_kses_post( $show_additional_content ); // phpcs:ignore
			}
			?>
		<h3><?php echo esc_html__( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ); ?></h3>
		<form method="post" action="" id="wps_wallet_transfer_form">
			<p class="wps-wallet-field-container form-row form-row-wide">
				<label for="wps_wallet_transfer_user_email"><?php esc_html_e( 'Transfer to', 'wallet-system-for-woocommerce-pro' ); ?></label>
				<input type="email" class="wps-wallet-userselect" id="wps_wallet_transfer_user_email" name="wps_wallet_transfer_user_email" data-current-email="<?php echo esc_attr( $current_user_email ); ?>" required="">
			</p>
			<p class="transfer-error"></p>
			<p class="wps-wallet-field-container form-row form-row-wide">
				<label for="wps_wallet_transfer_amount"><?php echo esc_html__( 'Amount (', 'wallet-system-for-woocommerce-pro' ) . esc_html( get_woocommerce_currency_symbol( $current_currency ) ) . ')'; ?></label>
				<input type="number" step="0.01" min="0" data-max="<?php echo esc_attr( $wallet_bal ); ?>" id="wps_wallet_transfer_amount" name="wps_wallet_transfer_amount" required="">
			</p>
			<p class="error"></p>
			<p class="wps-wallet-field-container form-row form-row-wide">
				<label for="wps_wallet_transfer_note"><?php esc_html_e( 'What\'s this for', 'wallet-system-for-woocommerce-pro' ); ?></label>
				<textarea name="wps_wallet_transfer_note"></textarea>
			</p>
			<?php
			$show_additional_form_content = apply_filters( 'wps_wsfw_show_additional_form_content', '' );
			if ( ! empty( $show_additional_form_content ) ) {
				echo wp_kses_post( $show_additional_form_content ); // phpcs:ignore
			}
			?>
			<p class="wps-wallet-field-container form-row">
				<input type="hidden" name="current_user_id" value="<?php echo esc_attr( $user_id ); ?>">
				<input type="hidden" name="wps_current_user_email" value="<?php echo esc_attr( $current_user_email ); ?>">
				<input type="submit" class="wps-btn__filled button" id="wps_proceed_transfer" name="wps_proceed_transfer" value="<?php esc_html_e( 'Proceed', 'wallet-system-for-woocommerce-pro' ); ?>">
			</p>
		</form>
			<?php
		} else {
			show_message_on_wallet_form_submit( esc_html__( 'Your wallet amount is 0, you cannot transfer money.', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
		}
		?>
	</div>
	<?php
}
