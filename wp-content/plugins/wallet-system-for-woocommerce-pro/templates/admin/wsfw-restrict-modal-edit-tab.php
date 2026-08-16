<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the html field for general tab.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Mwb_Multi_Currency_Switcher_For_Woocommerce
 * @subpackage Mwb_Multi_Currency_Switcher_For_Woocommerce/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id  = esc_html( $userid );
$wps_wallet_restrict_every_customer = get_option( 'wps_wallet_restrict_every_customer', true );
$wps_wallet_restrict_topup = get_user_meta( $user_id, 'wps_wallet_restrict_topup', true );
$wps_wallet_restrict_transfer = get_user_meta( $user_id, 'wps_wallet_restrict_transfer', true );
$wps_wallet_restrict_fund_request = get_user_meta( $user_id, 'wps_wallet_restrict_fund_request', true );
$wps_wallet_restrict_withdrawal = get_user_meta( $user_id, 'wps_wallet_restrict_withdrawal', true );
$wps_wallet_restrict_coupon = get_user_meta( $user_id, 'wps_wallet_restrict_coupon', true );
$wps_wallet_restrict_transactions = get_user_meta( $user_id, 'wps_wallet_restrict_transactions', true );
$wps_wallet_restrict_referral = get_user_meta( $user_id, 'wps_wallet_restrict_referral', true );
$wps_wallet_restrict_qrcode = get_user_meta( $user_id, 'wps_wallet_restrict_qrcode', true );
$wps_wallet_restrict_wallet_gateway = get_user_meta( $user_id, 'wps_wallet_restrict_wallet_gateway', true );
$wps_wallet_restrict_message = get_user_meta( $user_id, 'wps_wallet_restrict_message_to_user', true );
$wps_wallet_restrict_message_for = get_user_meta( $user_id, 'wps_wallet_restrict_message_for', true );
$wps_wallet_restrict_wallet_id = get_user_meta( $user_id, 'wps_wallet_restrict_wallet_id', true );


if ( empty( $wps_wallet_restrict_message_for ) ) {
	$wps_wallet_restrict_message_for = __( 'Some functionalities are restricted by Admin but you can use your wallet amount !!', 'wallet-system-for-woocommerce-pro' );
}

?>
<div class="wps_wallet-edit--popupwrap" id="restrict_user_pro">
			<div class="wps_wallet-edit-popup" id ="restrict_user_body">
<p><span id="close_wallet_form"><img src="<?php echo esc_url( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_URL ); ?>admin/image/cancel.svg"></span></p>
<form method="post">
			<div class="wps_wallet-edit-popup-content">
				<h3><?php echo esc_html__( 'Restrict User', 'wallet-system-for-woocommerce-pro' ); ?></h4>
				<div class="wps_wallet-edit-popup-amount">
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Wallet Topup', 'wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-topup" id="wps_wallet-restrict-topup"  class="wps_wallet-restrict-topup" 
						<?php
						if ( 'on' == $wps_wallet_restrict_topup ) {
							echo 'checked=on';}
						?>
						>
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Wallet Transfer', 'wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-transfer" id="wps_wallet-restrict-transfer"  class="wps_wallet-restrict-transfer" 
						<?php
						if ( 'on' == $wps_wallet_restrict_transfer ) {
							echo 'checked=on';}
						?>
						>	
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Wallet Fund Request', 'wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-fund-request" id="wps_wallet-restrict-fund-request"  class="wps_wallet-restrict-fund-request" 
						<?php
						if ( 'on' == $wps_wallet_restrict_fund_request ) {
							echo 'checked=on';}
						?>
						>	
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Wallet Withdrawal Request', 'wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-withdrawal" id="wps_wallet-restrict-withdrawal"  class="wps_wallet-restrict-withdrawal" 
						<?php
						if ( 'on' == $wps_wallet_restrict_withdrawal ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Wallet Coupon Redeem', 'wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-coupon" id="wps_wallet-restrict-coupon"  class="wps_wallet-restrict-coupon" 
						<?php
						if ( 'on' == $wps_wallet_restrict_coupon ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Transactions', ' wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-transactions" id="wps_wallet-restrict-transactions"  class="wps_wallet-restrict-transactions" 
						<?php
						if ( 'on' == $wps_wallet_restrict_transactions ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Referral', ' wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-referral" id="wps_wallet-restrict-referral"  class="wps_wallet-restrict-referral" 
						<?php
						if ( 'on' == $wps_wallet_restrict_referral ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict QR Code', ' wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-qrcode" id="wps_wallet-restrict-qrcode"  class="wps_wallet-restrict-qrcode" 
						<?php
						if ( 'on' == $wps_wallet_restrict_qrcode ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Wallet Gateway', ' wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-wallet-gateway" id="wps_wallet-restrict-wallet-gateway"  class="wps_wallet-restrict-wallet-gateway" 
						<?php
						if ( 'on' == $wps_wallet_restrict_wallet_gateway ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Restrict Wallet Id', ' wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-wallet-id" id="wps_wallet-restrict-wallet-id"  class="wps_wallet-restrict-wallet-gateway" 
						<?php
						if ( 'on' == $wps_wallet_restrict_wallet_id ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Show Restriction Message to User', ' wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="checkbox" name="wps_wallet-restrict-message-to-user" id="wps_wallet-restrict-message-to-user"  class="wps_wallet-restrict-message-to-user" 
						<?php
						if ( 'on' == $wps_wallet_restrict_message ) {
							echo 'checked=on';}
						?>
						>
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<?php echo esc_html__( 'Enter Restriction Message for User', ' wallet-system-for-woocommerce-pro' ); ?>
						</label>
						<input type="textarea" name="wps_wallet-restrict-message-for" id="wps_wallet-restrict-message-for"  class="wps_wallet-restrict-message-for"  value="<?php echo esc_attr( $wps_wallet_restrict_message_for ); ?>">
						
					</div>
					<div class="wps_wallet-edit-popup-label">
						<label for="wps_wallet-edit-popup-input" class="wps_wallet-edit-popup-input">
							<input type="checkbox" name="wps_wallet-restrict-every-customer" id="wps_wallet-restrict-every-customer"  class="wps_wallet-restrict-every-customer" 
							<?php
							if ( 'on' == $wps_wallet_restrict_every_customer ) {
								echo 'checked=on';}
							?>
							>
							<span class="error"><?php echo esc_html__( 'Apply For All User', 'wallet-system-for-woocommerce-pro' ); ?></span>
						</label>
					</div>
				</div>
			</div>
			<div class="wps_wallet-edit-popup-btn">
				<input type="hidden" id="user_restrict_id" name="user_restrict" value = "<?php echo esc_attr( $user_id ); ?>">
				<input type="hidden" id="user_update_nonce" name="user_update_nonce" value="<?php echo esc_attr( wp_create_nonce() ); ?>" />
				<input type="submit" name="restrict_wallet" class="wps-btn wps-btn__filled" value="<?php esc_html_e( 'Restrict Wallet', 'wallet-system-for-woocommerce-pro' ); ?>">
			</div>
</form></div>
</div>
