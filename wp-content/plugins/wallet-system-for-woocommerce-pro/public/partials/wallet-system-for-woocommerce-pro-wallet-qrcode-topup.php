<?php
/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link        https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/public/partials
 */

$product_id      = get_option( 'wps_wsfw_rechargeable_product_id', '' );
global $wp;
$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );
$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
$request_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_url; // phpcs:ignore
if ( isset( $_POST['wps_recharge_wallet_qrcode'] ) && ! empty( $_POST['wps_recharge_wallet_qrcode'] ) ) {
	$nonce = ( isset( $_POST['verifynonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['verifynonce'] ) ) : '';
	if ( wp_verify_nonce( $nonce ) ) {
		unset( $_POST['wps_recharge_wallet_qrcode'] );
		$recharge_amount = ! empty( $_POST['wps_wallet_recharge_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_recharge_amount'] ) ) : '';
		if ( ! empty( $_POST['user_id'] ) ) {
			$user_id = sanitize_text_field( wp_unslash( $_POST['user_id'] ) );

		}
		$wallet_action   = ! empty( $_POST['wps_wallet_action'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_action'] ) ) : '';
		$recharge_reason = ! empty( $_POST['wps_wallet_transfer_note'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_transfer_note'] ) ) : '';
		if ( 'Wallet Recharge' === $wallet_action ) {
			$recharge_amount = apply_filters( 'wps_wsfw_convert_to_base_price', $recharge_amount );
			$product_id      = ( isset( $_POST['product_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';
			WC()->session->set(
				'wallet_recharge',
				array(
					'userid'          => $user_id,
					'rechargeamount'  => $recharge_amount,
					'productid'       => $product_id,
					'recharge_reason' => $recharge_reason,
				)
			);
			WC()->session->set( 'recharge_amount', $recharge_amount );

			echo '<script>window.location.href = "' . esc_url( wc_get_cart_url() ) . '";</script>';
		} elseif ( 'Wallet Transfer' === $wallet_action ) {
			$wallet_transfer_amount = apply_filters( 'wps_wsfw_convert_to_base_price', $recharge_amount );
			$update = true;
			if ( is_user_logged_in() ) {
				$current_user_id = get_current_user_id();
				$wallet_bal      = get_user_meta( $current_user_id, 'wps_wallet', true );
				if ( $wallet_bal < $wallet_transfer_amount ) {
					show_message_on_qrcode_generation( 'Please enter amount less than or equal to wallet balance', 'woocommerce-error' );
					$update = false;
				}
				if ( $update ) {
					$user_wallet_bal  = get_user_meta( $user_id, 'wps_wallet', true );
					$user_wallet_bal += $wallet_transfer_amount;
					$returnid         = update_user_meta( $user_id, 'wps_wallet', $user_wallet_bal );

					if ( $returnid ) {
						$wallet_payment_gateway = new Wallet_System_For_Woocommerce();
						$send_email_enable      = get_option( 'wps_wsfw_enable_email_notification_for_wallet_update', '' );
						// first user.
						$user1 = get_user_by( 'id', $user_id );
						$name1 = $user1->first_name . ' ' . $user1->last_name;

						$user2 = get_user_by( 'id', $current_user_id );
						$name2 = $user2->first_name . ' ' . $user2->last_name;
						if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {
							$balance   = $current_currency . ' ' . $recharge_amount;
							$mail_text1  = esc_html__( 'Hello ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $name1 ) . ",\r\n";
							$mail_text1 .= __( 'Wallet credited by ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $balance ) . __( ' through wallet transfer by ', 'wallet-system-for-woocommerce-pro' ) . $name2;
							$to1         = $user1->user_email;
							$from        = get_option( 'admin_email' );
							$subject     = __( 'Wallet updating notification', 'wallet-system-for-woocommerce-pro' );
							$headers1    = 'MIME-Version: 1.0' . "\r\n";
							$headers1   .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
							$headers1   .= 'From: ' . $from . "\r\n" .
								'Reply-To: ' . $to1 . "\r\n";

							$wallet_payment_gateway->send_mail_on_wallet_updation( $to1, $subject, $mail_text1, $headers1 );

						}

						$transaction_type     = __( 'Wallet credited by user ', 'wallet-system-for-woocommerce-pro' ) . $user2->user_email . __( ' to user ', 'wallet-system-for-woocommerce-pro' ) . $user1->user_email;
						$wallet_transfer_data = array(
							'user_id'          => $user_id,
							'amount'           => $recharge_amount,
							'currency'         => $current_currency,
							'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
							'transaction_type' => $transaction_type,
							'transaction_type_1' => 'credit',
							'order_id'         => '',
							'note'             => $recharge_reason,

						);

						$wallet_payment_gateway->insert_transaction_data_in_table( $wallet_transfer_data );

						$wallet_bal -= $wallet_transfer_amount;
						$update_user = update_user_meta( $current_user_id, 'wps_wallet', abs( $wallet_bal ) );
						if ( $update_user ) {
							$balance   = $current_currency . ' ' . $recharge_amount;
							if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {
								$mail_text2  = esc_html__( 'Hello ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $name2 ) . ",\r\n";
								$mail_text2 .= __( 'Wallet debited by ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $balance ) . __( ' through wallet transfer to ', 'wallet-system-for-woocommerce-pro' ) . $name1;
								$to2         = $user2->user_email;
								$headers2    = 'MIME-Version: 1.0' . "\r\n";
								$headers2   .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
								$headers2   .= 'From: ' . $from . "\r\n" .
									'Reply-To: ' . $to2 . "\r\n";

								$wallet_payment_gateway->send_mail_on_wallet_updation( $to2, $subject, $mail_text2, $headers2 );
							}
							$transaction_type = __( 'Wallet debited from user ', 'wallet-system-for-woocommerce-pro' ) . $user2->user_email . __( ' wallet, transferred to user ', 'wallet-system-for-woocommerce-pro' ) . $user1->user_email;
							$transaction_data = array(
								'user_id'          => $current_user_id,
								'amount'           => $recharge_amount,
								'currency'         => $current_currency,
								'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
								'transaction_type' => $transaction_type,
								'transaction_type_1' => 'debit',
								'order_id'         => '',
								'note'             => $recharge_reason,

							);

							$result = $wallet_payment_gateway->insert_transaction_data_in_table( $transaction_data );
							show_message_on_qrcode_generation( 'Amount is transferred successfully', 'woocommerce-message' );

						} else {
							show_message_on_qrcode_generation( 'Amount is not transferred', 'woocommerce-error' );
						}
					} else {
						show_message_on_qrcode_generation( 'No user  found.', 'woocommerce-error' );
					}
				}
				?>
				<?php
			} else {
				$login_url = add_query_arg( 'redirect_to', urlencode( $current_url ), esc_url( wc_get_page_permalink( 'myaccount' ) ) );
				show_message_on_qrcode_generation( 'User is not logged in please <a href="' . esc_url( $login_url ) . '" >Login</a> your account first.', 'woocommerce-error' );
			}
		}
	} else {
		show_message_on_qrcode_generation( 'Failed security check', 'woocommerce-error' );
	}
}

if ( ! function_exists( 'show_message_on_qrcode_generation' ) ) {
	/**
	 * Show message on qrcode generation.
	 *
	 * @param string $wpg_message message.
	 * @param string $type message type.
	 * @return void
	 */
	function show_message_on_qrcode_generation( $wpg_message, $type = 'error' ) {
		$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
		echo wp_kses_post( $wpg_notice );
	}
}



?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<?php

/**
 * Get the unique qr code.
 *
 * @param  string $str           qrcode whole string.
 * @param  string $starting_word starting_word.
 * @param  string $ending_word   ending_word.
 * @return string
 */
function string_between_two_string( $str, $starting_word, $ending_word ) {
	$arr = explode( $starting_word, $str );
	if ( isset( $arr[1] ) ) {
		$arr = explode( $ending_word, $arr[1] );
		return $arr[0];
	}
	return '';
}

$start = 'qr-';
$end   = '.png';

$recharge_wallet_by_qr = false;
$users                 = get_users();
foreach ( $users as $user ) {
	$user_id        = $user->ID;
	$wallet_qr_code = get_user_meta( $user_id, 'wallet_qr_code_image', true );
	$qrcode         = string_between_two_string( $wallet_qr_code, $start, $end );
	$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
	$page_url       = get_permalink( $wallet_page_id );
	if ( get_option( 'permalink_structure' ) ) {
		if ( substr( $page_url, -1 ) == '/' ) {
			$qr_topup_url = $page_url . 'wps-wallet-topup/' . $qrcode . '/';
		} else {
			$qr_topup_url = $page_url . '/wps-wallet-topup/' . $qrcode;
		}
	} else {
		$qr_topup_url = add_query_arg( 'wps-wallet-topup', $qrcode, $page_url );
	}
	if ( $current_url == $qr_topup_url ) {
		$wallet_user_id        = $user_id;
		$recharge_wallet_by_qr = true;
	}
}
$wsfw_min_max_value = apply_filters( 'wsfw_min_max_value_for_wallet_recharge', array() );
if ( is_array( $wsfw_min_max_value ) ) {
	if ( ! empty( $wsfw_min_max_value['min_value'] ) ) {
		$min_value = $wsfw_min_max_value['min_value'];
		$min_value = apply_filters( 'wps_wsfw_show_converted_price', $min_value );
	} else {
		$min_value = 0;
	}
	if ( ! empty( $wsfw_min_max_value['max_value'] ) ) {
		$max_value = $wsfw_min_max_value['max_value'];
		$max_value = apply_filters( 'wps_wsfw_show_converted_price', $max_value );
	} else {
		$max_value = '';
	}
}

?>

<div class="wps_wcb_wallet_display_wrapper">
	<?php
	if ( is_user_logged_in() ) {
		$current_user_id = get_current_user_id();
		$wallet_bal      = get_user_meta( $current_user_id, 'wps_wallet', true );
		?>
		<div class="wps_wcb_wallet_balance_container"> 
			<h4><?php esc_html_e( 'Wallet Balance', 'wallet-system-for-woocommerce-pro' ); ?></h4>
			<p>
			<?php
			$wallet_bal = apply_filters( 'wps_wsfw_show_converted_price', $wallet_bal );
			echo wp_kses_post( wc_price( $wallet_bal, array( 'currency' => $current_currency ) ) );
			?>
			</p>
		</div>
			<?php
	}
	?>
	<div class="wps_wcb_main_tabs_template">
		<div class="wps_wcb_body_template">
			<div class="wps_wcb_content_template wps_wsfwp_content_template">

				<div class='content-section'>
					<div class='content active'>
					<?php

					if ( $recharge_wallet_by_qr ) {
						?>
						<form method="post" action="" id="wps_wallet_transfer_form">
							<p class="wps-wallet-field-container form-row form-row-wide">
								<label for="wps_qr_wallet_transfer"><?php echo esc_html__( 'Enter Amount (', 'wallet-system-for-woocommerce-pro' ) . esc_html( get_woocommerce_currency_symbol( $current_currency ) ) . ')'; ?></label>
								<input type="number" id="wps_qr_wallet_transfer" class="wallet_recharge_input_field" step="0.01" data-min="<?php echo esc_attr( $min_value ); ?>" data-max="<?php echo esc_attr( $max_value ); ?>" data-transfer="<?php echo ( ! empty( $wallet_bal ) ) ? esc_attr( $wallet_bal ) : ''; ?>" name="wps_wallet_recharge_amount" required>
							</p>
							<p class="error"></p>
							<p class="wps-wallet-field-container form-row form-row-wide">
								<label for="wps_wallet_action"><?php esc_html_e( 'Wallet Action', 'wallet-system-for-woocommerce-pro' ); ?></label>
								<select name="wps_wallet_action" id="wps_wallet_action" required>
									<option value="" selected disabled hidden><?php esc_html_e( 'Select option', 'wallet-system-for-woocommerce-pro' ); ?></option>
									<?php
									$user_id                = get_current_user_id();
									$wps_wallet_restrict_topup = get_user_meta( $user_id, 'wps_wallet_restrict_topup', true );
									$wps_wallet_restrict_transfer = get_user_meta( $user_id, 'wps_wallet_restrict_transfer', true );
									if ( 'on' != $wps_wallet_restrict_transfer ) {

										if ( ! empty( $current_user_id ) ) {
											if ( $current_user_id != $wallet_user_id ) {
												echo '<option value="Wallet Transfer">' . esc_html__( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ) . '</option>';
											}
										} else {
											echo '<option value="Wallet Transfer">' . esc_html__( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ) . '</option>';
										}
									}
									if ( 'on' != $wps_wallet_restrict_topup ) {

										echo '<option value="Wallet Recharge">' . esc_html__( 'Wallet Recharge', 'wallet-system-for-woocommerce-pro' ) . '</option>';

									}
									?>
									</select>
								<?php
								if ( ! is_user_logged_in() ) {
									$login_url = add_query_arg( 'redirect_to', urlencode( $current_url ), esc_url( wc_get_page_permalink( 'myaccount' ) ) );
									echo '<span class="wps-wallet-field-login-error"><a href="' . esc_url( $login_url ) . '" >' . esc_html__( 'Login', 'wallet-system-for-woocommerce-pro' ) . '</a>' . esc_html__( ' to Transfer money.', 'wallet-system-for-woocommerce-pro' ) . '</span>';
								}
								?>
							</p>

							<p class="wps-wallet-field-container form-row form-row-wide">
								<label for="wps_wallet_transfer_note"><?php esc_html_e( 'Reason', 'wallet-system-for-woocommerce-pro' ); ?></label>
								<textarea name="wps_wallet_transfer_note"></textarea>
							</p>
							<p class="wps-wallet-field-container form-row">
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $wallet_user_id ); ?>">
								<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
								<input type="hidden" id="verifynonce" name="verifynonce" value="<?php echo esc_attr( wp_create_nonce() ); ?>" />
								<input type="submit" class="wps-btn__filled button" id="wps_recharge_wallet" name="wps_recharge_wallet_qrcode" value="Proceed">
							</p>
						</form>
					<?php } else { ?>
					<p class="wsfwp_error" ><?php esc_html_e( 'Not valid url', 'wallet-system-for-woocommerce-pro' ); ?></p>
					<?php } ?>
					</div>
				
				</div>
			</div>
		</div>
	</div>
</div>
