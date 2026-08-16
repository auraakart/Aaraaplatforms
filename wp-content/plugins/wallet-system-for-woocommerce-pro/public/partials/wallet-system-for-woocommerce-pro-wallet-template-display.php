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
?>
 <script type="text/javascript">

	setInterval(function time(){
	var d = new Date();

	var hours = 24 - d.getHours();
	var min = 60 - d.getMinutes();
	if((min + '').length == 1){
		min = '0' + min;
	}
	var sec = 60 - d.getSeconds();
	if((sec + '').length == 1){
			sec = '0' + sec;
	}
	jQuery('#the-final-countdown').html(hours+'h:'+min+'m:'+sec+'s')
	}, 1000);
						
</script>
		<?php
		wp_cache_set( 'wps_upsell_countdown_timer', 'true' );
		$user_id = get_current_user_id();
		$first_name = get_user_meta( $user_id, 'first_name', true );
		$last_name = get_user_meta( $user_id, 'last_name', true );
		$user = new WP_User( $user_id );
		$roles = $user->roles;
		$avatar_url = get_avatar_url( $user_id );
		$logged_in_user = wp_get_current_user();
		$wallet_bal             = get_user_meta( $user_id, 'wps_wallet', true );
		$is_pro_plugin = false;
		$is_pro_plugin = apply_filters( 'wps_wsfwp_pro_plugin_check', $is_pro_plugin );
		$is_user_restricted     = get_user_meta( $user_id, 'user_restriction_for_wallet', true );
		if ( $is_pro_plugin ) {
			$is_user_restricted = '';
		}

		if ( empty( $wallet_bal ) ) {
			$wallet_bal = 0;
		}
		$wallet_restrict_topup = apply_filters( 'wallet_restrict_topup', $user_id );
		$wallet_restrict_transfer = apply_filters( 'wallet_restrict_transfer', $user_id );
		$wallet_restrict_withdrawal = apply_filters( 'wallet_restrict_withdrawal', $user_id );
		$wallet_restrict_fund_request = apply_filters( 'wallet_restrict_fund_request', $user_id );
		$wps_wallet_restrict_wallet_id     = get_user_meta( $user_id, 'wps_wallet_restrict_wallet_id', true );
		$wallet_restrict_coupon = apply_filters( 'wallet_restrict_coupon', $user_id );
		$wallet_restrict_transaction = apply_filters( 'wallet_restrict_transaction', $user_id );
		$wallet_restrict_referral = apply_filters( 'wallet_restrict_referral', $user_id );
		$wallet_restrict_qrcode = apply_filters( 'wallet_restrict_qrcode', $user_id );

		$wps_wsfw_wallet_action_refer_friend_enable = get_option( 'wps_wsfw_wallet_action_refer_friend_enable' );


		$wps_wsfw_enable_cashback = get_option( 'wps_wsfw_enable_cashback' );
		$wps_wallet_cashback_bal = get_user_meta( $user_id, 'wps_wallet_cashback_bal', true );
		$wps_wallet_cashback_bal = empty( $wps_wallet_cashback_bal ) ? 0 : $wps_wallet_cashback_bal;

		$is_pro_plugin = apply_filters( 'wps_wsfwp_pro_plugin_check', $is_pro_plugin );
		$wps_wallet_restrict_message_to_user = 'on';
		$wps_wallet_restrict_message_for = '';
		if ( $is_pro_plugin ) {
			$wps_wallet_restrict_message_to_user = apply_filters( 'wps_wallet_restrict_message_to_user', $user_id );
			$wps_wallet_restrict_message_for = apply_filters( 'wps_wallet_restrict_message_for', $user_id );
		}

		if ( ! empty( $logged_in_user ) ) {
			$current_user_email = $logged_in_user->user_email ? $logged_in_user->user_email : '';
		} else {
			$current_user_email = '';
		}
		$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );
		$product_id             = get_option( 'wps_wsfw_rechargeable_product_id', '' );
		$enable_wallet_recharge = get_option( 'wsfw_enable_wallet_recharge', '' );
		/**
		 * Show message on form submit.
		 *
		 * @param string $wpg_message message to be shown on form submission.
		 * @param string $type error type.
		 * @return void
		 */
		if ( ! function_exists( 'show_message_on_form_submit' ) ) {

			function show_message_on_form_submit( $wpg_message, $type = 'error' ) {
				$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
				echo wp_kses_post( $wpg_notice );
			}
		}

		$nonce = ( isset( $_POST['wps_verifynonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_verifynonce'] ) ) : '';

		if ( isset( $_POST['wps_recharge_wallet'] ) && ! empty( $_POST['wps_recharge_wallet'] ) ) {
			unset( $_POST['wps_recharge_wallet'] );

			if ( empty( $_POST['wps_wallet_recharge_amount'] ) ) {
				show_message_on_form_submit( esc_html__( 'Please enter amount greater than 0', 'wallet-system-for-woocommerce' ), 'woocommerce-error' );
			} else {
				$recharge_amount = sanitize_text_field( wp_unslash( $_POST['wps_wallet_recharge_amount'] ) );
				$recharge_amount = apply_filters( 'wps_wsfw_convert_to_base_price', $recharge_amount );

				if ( ! empty( $_POST['user_id'] ) ) {
					$user_id = sanitize_text_field( wp_unslash( $_POST['user_id'] ) );
				}
				$product_id = ( isset( $_POST['product_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';
				WC()->session->set(
					'wallet_recharge',
					array(
						'userid'         => $user_id,
						'rechargeamount' => $recharge_amount,
						'productid'      => $product_id,
					)
				);
				WC()->session->set( 'recharge_amount', $recharge_amount );
				echo '<script>window.location.href = "' . esc_url( wc_get_cart_url() ) . '";</script>';
			}
		}
		if ( isset( $_POST['wps_proceed_transfer'] ) && ! empty( $_POST['wps_proceed_transfer'] ) ) {
			unset( $_POST['wps_proceed_transfer'] );
			$update = true;
			// check whether $_POST key 'current_user_id' is empty or not.
			if ( ! empty( $_POST['current_user_id'] ) ) {
				$user_id = sanitize_text_field( wp_unslash( $_POST['current_user_id'] ) );
			}

			$wallet_bal             = get_user_meta( $user_id, 'wps_wallet', true );
			$wallet_bal             = ( ! empty( $wallet_bal ) ) ? $wallet_bal : 0;
			$wps_current_user_email = ! empty( $_POST['wps_current_user_email'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_current_user_email'] ) ) : '';
			$another_user_email     = ! empty( $_POST['wps_wallet_transfer_user_email'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_transfer_user_email'] ) ) : '';
			$transfer_note          = ! empty( $_POST['wps_wallet_transfer_note'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_transfer_note'] ) ) : '';
			$user                   = get_user_by( 'email', $another_user_email );
			$transfer_amount        = ! empty( $_POST['wps_wallet_transfer_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_transfer_amount'] ) ) : 0;
			$wallet_transfer_amount = apply_filters( 'wps_wsfw_convert_to_base_price', $transfer_amount );
			if ( $user ) {
				$another_user_id = $user->ID;
			} else {
				$invitation_link = apply_filters( 'wsfw_add_invitation_link_message', '' );
				if ( ! empty( $invitation_link ) ) {
					global $wp_session;
					$wp_session['wps_wallet_transfer_user_email'] = $another_user_email;
					$wp_session['wps_wallet_transfer_amount']     = $wallet_transfer_amount;
				}
				show_message_on_form_submit( 'Email Id does not exist. ' . $invitation_link, 'woocommerce-error' );
				$update = false;
			}
			if ( empty( $_POST['wps_wallet_transfer_amount'] ) ) {
				show_message_on_form_submit( esc_html__( 'Please enter amount greater than 0', 'wallet-system-for-woocommerce' ), 'woocommerce-error' );
				$update = false;
			} elseif ( $wallet_bal < $wallet_transfer_amount ) {
				show_message_on_form_submit( esc_html__( 'Please enter amount less than or equal to wallet balance', 'wallet-system-for-woocommerce' ), 'woocommerce-error' );
				$update = false;
			} elseif ( $another_user_email == $wps_current_user_email ) {
				show_message_on_form_submit( esc_html__( 'You cannot transfer amount to yourself.', 'wallet-system-for-woocommerce' ), 'woocommerce-error' );
				$update = false;
			}
			if ( $update ) {
				$user_wallet_bal  = get_user_meta( $another_user_id, 'wps_wallet', true );
				$user_wallet_bal  = ( ! empty( $user_wallet_bal ) ) ? $user_wallet_bal : 0;


				// extra fee working.
				$wps_wsfwp_wallet_action_transfer_enable = get_option( 'wps_wsfwp_wallet_action_transfer_enable' );
				$wps_wsfwp_wallet_transfer_fee_amount = get_option( 'wps_wsfwp_wallet_transfer_fee_amount' );

				$wps_wsfwp_cashback_transfer_fee_type = get_option( 'wps_wsfwp_cashback_transfer_fee_type' );

				if ( 'on' == $wps_wsfwp_wallet_action_transfer_enable && $wps_wsfwp_wallet_transfer_fee_amount > 0 ) {
					if ( $wallet_transfer_amount > $wps_wsfwp_wallet_transfer_fee_amount ) {

						if ( 'percent' == $wps_wsfwp_cashback_transfer_fee_type ) {
							$fee_amount = ( ( $wallet_transfer_amount * $wps_wsfwp_wallet_transfer_fee_amount ) / 100 );
							$user_wallet_bal += $wallet_transfer_amount - ( ( $wallet_transfer_amount * $wps_wsfwp_wallet_transfer_fee_amount ) / 100 );

						} else {
							$fee_amount = $wps_wsfwp_wallet_transfer_fee_amount;
							$user_wallet_bal += $wallet_transfer_amount - $wps_wsfwp_wallet_transfer_fee_amount;
						}
					} else if ( $wps_wsfwp_wallet_transfer_fee_amount > $wallet_transfer_amount ) {
						if ( 'percent' == $wps_wsfwp_cashback_transfer_fee_type ) {
							$fee_amount = ( ( $wallet_transfer_amount * $wps_wsfwp_wallet_transfer_fee_amount ) / 100 );

							$user_wallet_bal += ( ( $wps_wsfwp_wallet_transfer_fee_amount * $wallet_transfer_amount ) / 100 ) - $wallet_transfer_amount;

						} else {
							$fee_amount = $wps_wsfwp_wallet_transfer_fee_amount;
							$user_wallet_bal += $wps_wsfwp_wallet_transfer_fee_amount - $wallet_transfer_amount;
						}
					}
				} else {
					$user_wallet_bal += $wallet_transfer_amount;
				}

				// extra fee working.

				$returnid         = update_user_meta( $another_user_id, 'wps_wallet', $user_wallet_bal );

				if ( $returnid ) {
					$wallet_payment_gateway = new Wallet_System_For_Woocommerce();
					$send_email_enable      = get_option( 'wps_wsfw_enable_email_notification_for_wallet_update', '' );
					// first user.
					$user1 = get_user_by( 'id', $another_user_id );
					$name1 = $user1->first_name . ' ' . $user1->last_name;

					$user2 = get_user_by( 'id', $user_id );
					$name2 = $user2->first_name . ' ' . $user2->last_name;
					$balance   = $current_currency . ' ' . $transfer_amount;
					if ( 'on' == $wps_wsfwp_wallet_action_transfer_enable && $wps_wsfwp_wallet_transfer_fee_amount > 0 ) {

						if ( 'percent' == $wps_wsfwp_cashback_transfer_fee_type ) {
							$wps_wsfwp_wallet_transfer_fee_amount = ( ( $wallet_transfer_amount * $wps_wsfwp_wallet_transfer_fee_amount ) / 100 );

						} else {
							$wps_wsfwp_wallet_transfer_fee_amount = $wps_wsfwp_wallet_transfer_fee_amount;

						}
					}
					if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {

						$mail_text1  = esc_html__( 'Hello ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $name1 ) . ",\r\n";
						$mail_text1 .= __( 'Wallet credited by ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $balance ) . __( ' through wallet transfer by ', 'wallet-system-for-woocommerce-pro' ) . $name2;
						$to1         = $user1->user_email;
						$from        = get_option( 'admin_email' );
						$subject     = __( 'Wallet updating notification', 'wallet-system-for-woocommerce-pro' );
						$headers1    = 'MIME-Version: 1.0' . "\r\n";
						$headers1   .= 'Content-Type: text/html;  charset=UTF-8' . "\r\n";
						$headers1   .= 'From: ' . $from . "\r\n" .
						'Reply-To: ' . $to1 . "\r\n";

						$customer_email_credit = '';
						if ( key_exists( 'wps_wswp_wallet_credit_transfer', WC()->mailer()->emails ) ) {

							$customer_email_credit = WC()->mailer()->emails['wps_wswp_wallet_credit_transfer'];
							if ( ! empty( $customer_email_credit ) ) {
								// $user       = get_user_by( 'id', $user2 );
								$balance_mail = $transfer_amount;
								if ( 'on' == $wps_wsfwp_wallet_action_transfer_enable && $wps_wsfwp_wallet_transfer_fee_amount > 0 ) {

									$balance_mail = floatval( $balance_mail ) - floatval( $wps_wsfwp_wallet_transfer_fee_amount );
								}

								$balance   = $current_currency . ' ' . $balance_mail;
								// $user_name       = $user->first_name . ' ' . $user->last_name;
								$customer_email_credit->trigger( $user1, $name1, $balance, $mail_text1 );
							}
						} else {

							$wallet_payment_gateway->send_mail_on_wallet_updation( $to1, $subject, $mail_text1, $headers1 );

						}
					}
					$transaction_type     = __( 'Wallet credited by user ', 'wallet-system-for-woocommerce-pro' ) . $user2->user_email . __( ' to user ', 'wallet-system-for-woocommerce-pro' ) . $user1->user_email;
					$transaction_type_1 = 'credit';
					// extra fee.
					if ( 'on' == $wps_wsfwp_wallet_action_transfer_enable && $wps_wsfwp_wallet_transfer_fee_amount > 0 ) {

						if ( $wallet_transfer_amount > $wps_wsfwp_wallet_transfer_fee_amount ) {

							$wallet_transfer_data = array(
								'user_id'          => $another_user_id,
								'amount'           => $wallet_transfer_amount - $wps_wsfwp_wallet_transfer_fee_amount,
								'currency'         => $current_currency,
								'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
								'transaction_type' => $transaction_type,
								'transaction_type_1' => $transaction_type_1,
								'order_id'         => '',
								'note'             => $transfer_note,
							);

						} else if ( $wps_wsfwp_wallet_transfer_fee_amount > $wallet_transfer_amount ) {

							$wallet_transfer_data = array(
								'user_id'          => $another_user_id,
								'amount'           => $wallet_transfer_amount - $wps_wsfwp_wallet_transfer_fee_amount,
								'currency'         => $current_currency,
								'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
								'transaction_type' => $transaction_type,
								'transaction_type_1' => $transaction_type_1,
								'order_id'         => '',
								'note'             => $transfer_note,
							);

						}
					} else {

						$wallet_transfer_data = array(
							'user_id'          => $another_user_id,
							'amount'           => $transfer_amount,
							'currency'         => $current_currency,
							'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
							'transaction_type' => $transaction_type,
							'transaction_type_1' => $transaction_type_1,
							'order_id'         => '',
							'note'             => $transfer_note,
						);

					}

					$wallet_payment_gateway->insert_transaction_data_in_table( $wallet_transfer_data );
					$wallet_transfer_data = array();
					$wallet_bal -= $wallet_transfer_amount;
					$update_user = update_user_meta( $user_id, 'wps_wallet', abs( $wallet_bal ) );
					if ( $update_user ) {
						$balance   = $current_currency . ' ' . $transfer_amount;
						if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {
							$mail_text2  = esc_html__( 'Hello ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $name2 ) . ",\r\n";
							$mail_text2 .= __( 'Wallet debited by ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $transfer_amount ) . __( ' through wallet transfer to ', 'wallet-system-for-woocommerce-pro' ) . $name1;
							$to2         = $user2->user_email;
							$headers2    = 'MIME-Version: 1.0' . "\r\n";
							$headers2   .= 'Content-Type: text/html;  charset=UTF-8' . "\r\n";
							$headers2   .= 'From: ' . $from . "\r\n" .
							'Reply-To: ' . $to2 . "\r\n";

							$wallet_payment_gateway->send_mail_on_wallet_updation( $to2, $subject, $mail_text2, $headers2 );
						}
						$transaction_type = __( 'Wallet debited from user ', 'wallet-system-for-woocommerce-pro' ) . $user2->user_email . __( ' wallet, transferred to user ', 'wallet-system-for-woocommerce-pro' ) . $user1->user_email;
						$wps_wsfwp_wallet_transfer_fee_amount = get_option( 'wps_wsfwp_wallet_transfer_fee_amount' );
						if ( 'percent' == $wps_wsfwp_cashback_transfer_fee_type ) {
							$wps_wsfwp_wallet_transfer_fee_amount = ( ( $wallet_transfer_amount * $wps_wsfwp_wallet_transfer_fee_amount ) / 100 );

						} else {
							$wps_wsfwp_wallet_transfer_fee_amount = $wps_wsfwp_wallet_transfer_fee_amount;

						}
						if ( $wps_wsfwp_wallet_transfer_fee_amount && 'on' == $wps_wsfwp_wallet_action_transfer_enable ) {
							$transaction_type .= __( '( inculding Transfer Fee of', 'wallet-system-for-woocommerce-pro' ) . get_woocommerce_currency_symbol() . '' . $wps_wsfwp_wallet_transfer_fee_amount . __( ')', 'wallet-system-for-woocommerce-pro' );
						}
						$transaction_data = array(
							'user_id'          => $user_id,
							'amount'           => $transfer_amount,
							'currency'         => $current_currency,
							'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
							'transaction_type' => $transaction_type,
							'transaction_type_1' => 'debit',
							'order_id'         => '',
							'note'             => $transfer_note,

						);

						$result = $wallet_payment_gateway->insert_transaction_data_in_table( $transaction_data );
						show_message_on_form_submit( esc_html__( 'Amount is transferred successfully', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-message' );
					} else {
						show_message_on_form_submit( esc_html__( 'Amount is not transferred', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
					}
				} else {
					show_message_on_form_submit( esc_html__( 'No user found.', 'wallet-system-for-woocommerce' ), 'woocommerce-error' );
				}
			}
		}
		if ( isset( $_POST['wps_withdrawal_request'] ) && ! empty( $_POST['wps_withdrawal_request'] ) ) {
			unset( $_POST['wps_withdrawal_request'] );


			if ( ! empty( $_POST['wallet_user_id'] ) ) {
				$user_id  = sanitize_text_field( wp_unslash( $_POST['wallet_user_id'] ) );
				$user     = get_user_by( 'id', $user_id );
				$username = $user->user_login;

			}

			$args          = array(
				'post_title'  => $username,
				'post_type'   => 'wallet_withdrawal',
				'post_status' => 'publish',
			);
			$withdrawal_id = wp_insert_post( $args );
			if ( ! empty( $withdrawal_id ) ) {
				wp_update_post(
					array(
						'ID'          => $withdrawal_id,
						'post_status' => 'pending1',
					)
				);
				foreach ( $_POST as $key => $value ) {
					if ( ! empty( $value ) ) {

						$value = sanitize_text_field( $value );
						if ( 'wps_wallet_withdrawal_amount' === $key ) {

							$wps_wsfwp_wallet_action_withdrawal_enable = get_option( 'wps_wsfwp_wallet_action_withdrawal_enable' );
								$is_manual_fees = true;
								$wps_wallet_withdrawal_option = get_option( 'wps_wsfwp_wallet_withdrawal_paypal_enable' );

							if ( 'on' == $wps_wallet_withdrawal_option ) {

								$wps_wallet_withdrawal_option = isset( $_POST['wps_wallet_withdrawal_option'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_withdrawal_option'] ) ) : '';
								if ( 'manual' != $wps_wallet_withdrawal_option ) {
									$wps_wsfwp_wallet_action_withdrawal_enable = 'off';
								}
							}
								$wps_wsfwp_wallet_withdrawal_fee_amount = get_option( 'wps_wsfwp_wallet_withdrawal_fee_amount' );
								$wps_wsfwp_cashback_withdrawal_fee_type = get_option( 'wps_wsfwp_cashback_withdrawal_fee_type' );
							if ( 'on' == $wps_wsfwp_wallet_action_withdrawal_enable && $wps_wsfwp_wallet_withdrawal_fee_amount > 0 ) {
								if ( $value >= $wps_wsfwp_wallet_withdrawal_fee_amount ) {

									if ( 'percent' == $wps_wsfwp_cashback_withdrawal_fee_type ) {

										$wps_wsfwp_wallet_withdrawal_fee_amount = ( $wps_wsfwp_wallet_withdrawal_fee_amount * $value ) / 100;
										$value = $value - $wps_wsfwp_wallet_withdrawal_fee_amount;
										update_post_meta( $withdrawal_id, 'wps_wsfwp_wallet_withdrawal_fee_amount', $wps_wsfwp_wallet_withdrawal_fee_amount );

									} else {

										$value = $value - $wps_wsfwp_wallet_withdrawal_fee_amount;
										update_post_meta( $withdrawal_id, 'wps_wsfwp_wallet_withdrawal_fee_amount', $wps_wsfwp_wallet_withdrawal_fee_amount );
									}
								} else if ( $wps_wsfwp_wallet_withdrawal_fee_amount > $value ) {

									if ( 'percent' == $wps_wsfwp_cashback_withdrawal_fee_type ) {

										$wps_wsfwp_wallet_withdrawal_fee_amount = ( $wps_wsfwp_wallet_withdrawal_fee_amount * $value ) / 100;
										$value = $wps_wsfwp_wallet_withdrawal_fee_amount - $value;
										update_post_meta( $withdrawal_id, 'wps_wsfwp_wallet_withdrawal_fee_amount', $wps_wsfwp_wallet_withdrawal_fee_amount );

									} else {

										$value = $wps_wsfwp_wallet_withdrawal_fee_amount - $value;
										update_post_meta( $withdrawal_id, 'wps_wsfwp_wallet_withdrawal_fee_amount', $wps_wsfwp_wallet_withdrawal_fee_amount );
									}
								}
							} else {
								$value = $value;
							}
								$withdrawal_bal = apply_filters( 'wps_wsfw_convert_to_base_price', $value );
							// $withdrawal_bal = apply_filters( 'wps_wsfw_convert_to_base_price', $value );
							update_post_meta( $withdrawal_id, $key, $withdrawal_bal );
						} else {
							update_post_meta( $withdrawal_id, $key, $value );
						}
					}
				}

				update_user_meta( $user_id, 'disable_further_withdrawal_request', true );

				// wp_register_script( 'wps-public-shortcode-dis', false, array(), '1.0.0', false );
				// wp_enqueue_script( 'wps-public-shortcode-dis' );
				// wp_add_inline_script( 'wps-public-shortcode-dis', 'window.location.href = "' . $current_url . '"' );
			}
		}

		if ( isset( $_POST['wps_wallet_fund_request'] ) && ! empty( $_POST['wps_wallet_fund_request'] ) ) {

			$update = true;
			$wps_wallet_fund_request_another_user_email     = ! empty( $_POST['wps_wallet_fund_request_another_user_email'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_fund_request_another_user_email'] ) ) : '';
			$wps_wallet_fund_request_amount        = ! empty( $_POST['wps_wallet_fund_request_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_fund_request_amount'] ) ) : 0;
			$wps_wallet_note        = ! empty( $_POST['wps_wallet_note'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_note'] ) ) : 0;
			$wallet_user_id = ! empty( $_POST['wallet_user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wallet_user_id'] ) ) : 0;

			$wps_current_user_email = ! empty( $_POST['wps_current_user_email'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_current_user_email'] ) ) : 0;

			$user                   = get_user_by( 'email', $wps_wallet_fund_request_another_user_email );

			if ( $user ) {
				$another_user_id = $user->ID;
			} else {
				$invitation_link = apply_filters( 'wsfw_add_invitation_link_message', '' );
				if ( ! empty( $invitation_link ) ) {
					global $wp_session;
					$wp_session['wps_wallet_transfer_user_email'] = $wps_wallet_fund_request_another_user_email;
					$wp_session['wps_wallet_transfer_amount']     = $wps_wallet_fund_request_amount;
				}
				show_message_on_form_submit( 'Email Id does not exist. ' . $invitation_link, 'woocommerce-error' );
				$update = false;
			}

			if ( empty( $wps_wallet_fund_request_amount ) ) {
				show_message_on_form_submit( esc_html__( 'Please enter amount greater than 0', 'wallet-system-for-woocommerce' ), 'woocommerce-error' );
				$update = false;
			} elseif ( $wps_wallet_fund_request_another_user_email == $wps_current_user_email ) {
				show_message_on_form_submit( esc_html__( 'You cannot request fund to yourself.', 'wallet-system-for-woocommerce' ), 'woocommerce-error' );
				$update = false;
			}
			if ( $update ) {

				if ( ! empty( $wallet_user_id ) ) {
					$user_id  = sanitize_text_field( wp_unslash( $wallet_user_id ) );
					$user     = get_user_by( 'id', $user_id );
					$username = $user->user_login;

				}
				$args          = array(
					'post_title'  => $username,
					'post_type'   => 'wallet_fund_request',
					'post_status' => 'publish',
				);
				$withdrawal_id = wp_insert_post( $args );

				if ( ! empty( $withdrawal_id ) ) {
					wp_update_post(
						array(
							'ID'          => $withdrawal_id,
							'post_status' => 'pending1',
						)
					);
					foreach ( $_POST as $key => $value ) {
						if ( ! empty( $value ) ) {
							$value = sanitize_text_field( $value );
							if ( 'wps_wallet_fund_request_amount' === $key ) {
								$withdrawal_bal = apply_filters( 'wps_wsfw_convert_to_base_price', $value );
								update_post_meta( $withdrawal_id, $key, $withdrawal_bal );
							} else {
								update_post_meta( $withdrawal_id, $key, $value );
							}
						}
					}
					update_post_meta( $withdrawal_id, 'requested_user_id', $another_user_id );

				}
			}
		}

		if ( isset( $_POST['wps_wallet_generate_wallet_id'] ) && ! empty( $_POST['wps_wallet_generate_wallet_id'] ) ) {
			$assign_wallet_id  = sanitize_text_field( wp_unslash( $_POST['assign_wallet_id'] ) );
			$existing_wallet_id = get_user_meta( $assign_wallet_id, 'wps_wallet_id', true );

			if ( empty( $existing_wallet_id ) ) {
				$common_obj  = new Wallet_System_For_Woocommerce_Common( 'wallet system for woocommerce', WALLET_SYSTEM_FOR_WOOCOMMERCE_VERSION );
				$wallet_id = $common_obj->wps_wsfw_generate_unique_wallet_id( $assign_wallet_id );
				update_user_meta( $assign_wallet_id, 'wps_wallet_id', $wallet_id );
			}
		}

		?>
<div class="wps-wp_ct" id="wps-wp_ct">
	<div class="wps-wp_ct-in">
		<div class="wps-wp_ct-left">
			<div class="wps-wp_ctl-top">
				<div class="wps-wp_ctlt-in-wrap">
					<div class="wps-wp_ctlt-in">
						<div class="wps-wp_ctl-title"><?php esc_html_e( 'Hi! ', 'wallet-system-for-woocommerce-pro' ); ?><?php echo $first_name . ' ' . $last_name; ?></div>
						<div class="wps-wp_ctl-desc"><?php esc_html_e( 'Welcome to Wallet Dashboard', 'wallet-system-for-woocommerce-pro' ); ?></div>
					</div>

					<?php
					$wallet_qr_code_image = get_user_meta( $user_id, 'wallet_qr_code_image', true );

					if ( $wallet_qr_code_image && 'on' != $wallet_restrict_qrcode ) {

						?>
							<!-- QR Content -->
							<div class= "wps-wp_ctlt-in-qr_code-icon" title="<?php esc_html_e( 'View Wallet QR Code', 'wallet-system-for-woocommerce-pro' ); ?>">
								<?php
								include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/image/qr-code.svg';
								?>
							</div>
							<div class= "wps-wp_ctlt-in-qr_code">
								<div class= "wps-wp_ctlt-in-qr_code-shadow"></div>
								<div class= "wps-wp_ctlt-in-qr_code-in">
									<span class="wps-wp_ctlt-in-qr_code-close">&times;</span>
									<?php
									include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-public-display.php';
									?>
								</div>
							</div>
							<!-- QR Content -->
							<?php
					}
					?>
				</div>
				<div class="wps-wp_ctlti-img" id="wps-cp_ctlt-user">
				<img src="<?php echo $avatar_url; ?>" alt="user" class="wps-wp_ctru-img" />
				</div>
			</div>
			<div class="wps-wp_ctl-head">
				<div class="wps-wp_ctlh-in wps-wp_ctlh-left">
					<div class="wps-wp_ctlhl-in">
						<div class="wps-wp_ctlh-label"><?php esc_html_e( 'Wallet balance', 'wallet-system-for-woocommerce-pro' ); ?></div>
						<div class="wps-wp_ctlh-amount">
							<?php
							$wallet_bal = apply_filters( 'wps_wsfw_show_converted_price', $wallet_bal );
							$wps_wsfwp_wallet_user_currency_setting = get_option( 'wps_wsfwp_wallet_user_currency_setting' );
							if ( 'yes' == $wps_wsfwp_wallet_user_currency_setting ) {

								$wps_wallet_last_order_currency = get_user_meta( $user_id, 'wps_wallet_last_order_currency', true );
								if ( $wps_wallet_last_order_currency == $current_currency ) {

									echo wp_kses_post( wc_price( $wallet_bal, array( 'currency' => $current_currency ) ) );

								} else {

									$wallet_bal = 0;
									echo wp_kses_post( wc_price( $wallet_bal, array( 'currency' => $current_currency ) ) );

								}
							} else if ( 'no' == $wps_wsfwp_wallet_user_currency_setting ) {

								$wps_wallet_order_geolocation_currency = get_user_meta( $user_id, 'wps_wallet_order_geolocation_currency', true );
								if ( $wps_wallet_order_geolocation_currency == $current_currency ) {

									echo wp_kses_post( wc_price( $wallet_bal, array( 'currency' => $current_currency ) ) );

								} else {

									$wallet_bal = 0;
									echo wp_kses_post( wc_price( $wallet_bal, array( 'currency' => $current_currency ) ) );
								}
							} else {

								echo wp_kses_post( wc_price( $wallet_bal, array( 'currency' => $current_currency ) ) );

							}
							?>
						</div>
					</div>
					<?php
					if ( 'on' != $wallet_restrict_transaction ) {
						?>
						<span class="wps-wp_ctlh-btn wps-wp_ctlh-history wps-wp_ctrl--active"><?php esc_html_e( 'History', 'wallet-system-for-woocommerce-pro' ); ?></span>
						<span class="wps-wp_ctlh-btn wps-wp_ctlh-recent"><?php esc_html_e( 'Recent', 'wallet-system-for-woocommerce-pro' ); ?></span>
						<?php
					}
					?>
				</div>
				<?php
				if ( 'on' == $wps_wsfw_enable_cashback ) {
					?>
					<div class="wps-wp_ctlh-in wps-wp_ctlh-right">
						<div class="wps-wp_ctlhr-in">
							<div class="wps-wp_ctlh-label"><?php esc_html_e( 'Cashback', 'wallet-system-for-woocommerce-pro' ); ?></div>
							<div class="wps-wp_ctlh-amount">
								<?php
								if ( 'on' == $wps_wsfw_enable_cashback ) {
									?>
										<div class="wps_wcb_wallet_cashback_wrap">
										<?php
										echo wp_kses_post( wc_price( $wps_wallet_cashback_bal, array( 'currency' => $current_currency ) ) );
										?>
										</div>
										<?php
								}
								?>
							</div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
			<div class="wps-wp_ctl-refer_freind">
				<?php
				if ( 'on' == $wps_wsfw_wallet_action_refer_friend_enable ) {
					?>
												<div class="wps-wp_ctlm-title">
							<?php
								esc_html_e( 'Refer A Friend', 'wallet-system-for-woocommerce-pro' );
							?>
								</div>
								<?php
								if ( 'on' != $wallet_restrict_referral ) {
									?>
						  <div>
								<!-- Other Content -->
									<?php
									include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-referral.php';

								} else {
									?>
							<div class="wps-wp_ctr-con wps-wp_ctr-other-content">
								<div class="wsfw_show_user_restriction_notice">
									<?php
									if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
										echo esc_html( $wps_wallet_restrict_message_for );

									} else {
										esc_html_e( 'Wallet Referral Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
									}
									?>
								</div>
							</div>
									<?php
								}
								?>
						</div>
							<?php
				}
				?>
			</div>
			<div class="wps-wp_ctl-main">
				<?php
				if ( 'on' != $wallet_restrict_transaction ) {
					?>
					<div class="wps-wp_ctlm wps-wp_ctl-history">
						<div class="wps-wp_ctlm-title"><?php esc_html_e( 'Transaction History', 'wallet-system-for-woocommerce-pro' ); ?></div>
						<div class="wps-wp_ctlm-trans">        
							<!-- transaction history code -->
								<div class='content active'>
								<div class="wps-wallet-transaction-container">
									<table class="wps-wsfw-wallet-field-table " id="transactions_table">
										<thead>
											<tr>
												<th>#</th>
												<th><?php esc_html_e( 'Transaction Id', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Amount', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Details', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Method', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Date', 'wallet-system-for-woocommerce' ); ?></th>
											</tr>
										</thead>
										<tbody>
										   <?php
											global $wpdb;

											$table_name   = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
											$transactions = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'wps_wsfw_wallet_transaction WHERE user_id = %s ORDER BY `Id` DESC', $user_id ) );
											if ( ! empty( $transactions ) && is_array( $transactions ) ) {
												$i = 1;
												foreach ( $transactions as $transaction ) {
													$transaction_amount_bal = apply_filters( 'wps_wsfw_show_converted_price', $transaction->amount );
													$user           = get_user_by( 'id', $transaction->user_id );
													$transaction_id = $transaction->id;
													$tranasction_symbol = '';
													if ( 'credit' == $transaction->transaction_type_1 ) {
														$tranasction_symbol = '+';
													} elseif ( 'debit' == $transaction->transaction_type_1 ) {
														$tranasction_symbol = '-';
													}
													?>
													<tr>
														<td><?php echo esc_html( $i ); ?></td>
														<td>
														<?php
															$date = date_create( $transaction->date );
															echo esc_html( $date->getTimestamp() . $transaction->id );

														?>
														</td>
														<td class='wps_wallet_<?php echo esc_attr( $transaction->transaction_type_1 ); ?>' ><?php echo esc_html( $tranasction_symbol ) . wp_kses_post( wc_price( $transaction_amount_bal, array( 'currency' => $transaction->currency ) ) ); ?></td>
														<td class="details" ><?php echo wp_kses_post( html_entity_decode( $transaction->transaction_type ) ); ?></td>
														<td>
														<?php
														$payment_methods = WC()->payment_gateways->payment_gateways();
														foreach ( $payment_methods as $key => $payment_method ) {
															if ( $key == $transaction->payment_method ) {
																$method = esc_html__( 'Online Payment', 'wallet-system-for-woocommerce' );
															} else {
																$method = $transaction->payment_method;
															}
															break;
														}
														echo esc_html( $method );
														?>
														</td>
														<td>
														<?php
														$date_format = get_option( 'date_format', 'm/d/Y' );
														$date        = date_create( $transaction->date );
														$wps_wsfw_time_zone = get_option( 'timezone_string' );
														if ( ! empty( $wps_wsfw_time_zone ) ) {
															$date = date_create( $transaction->date );
															echo esc_html( date_format( $date, $date_format ) );
															// extra code.( need validation if require).
															$date->setTimezone( new DateTimeZone( get_option( 'timezone_string' ) ) );
															// extra code.
															echo ' ' . esc_html( date_format( $date, 'H:i:s' ) );
														} else {

															$date_format = get_option( 'date_format', 'm/d/Y' );
															$date        = date_create( $transaction->date );
															echo esc_html( date_format( $date, $date_format ) );
															echo ' ' . esc_html( date_format( $date, 'H:i:s' ) );
														}
														?>
														</td>
													</tr>
													<?php
													$i++;
												}
											}

											?>
										</tbody>
									</table>
								</div>
	
								<?php
								// including regular expression jquery.
								wp_enqueue_script( 'anchor-tag', WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_URL . 'public/src/js/wallet-system-for-woocommerce-anchor.js', array(), WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION, 'all' );
								?>
	
								<!-- removing the anchor tag href attibute using regular expression -->	
								<script>
								jQuery( "#transactions_table tr td" ).each(function( index ) {
									var details = jQuery( this ).html();
									var patt = new RegExp("<a");
									var res = patt.test(details);
									if ( res ) {
										jQuery(this).children('a').removeAttr("href");
									}
								});
								</script>
							</div>
							<!-- transaction history code -->   
						</div>
					</div>
					<div class="wps-wp_ctlm wps-wp_ctl-recent wps-wp_ctrl--active">
						<div class="wps-wp_ctlm-title"><?php esc_html_e( 'Recent Transactions', 'wallet-system-for-woocommerce-pro' ); ?></div>
						<div class="wps-wp_ctlm-trans">
							<!-- Table for transaction -->
	
								<!-- recent transaction history code -->
								<div class='content active'>
								<div class="wps-wallet-transaction-container">
									<table class="wps-wsfw-wallet-field-table " id="transactions_table">
										<thead>
											<tr>
												<th>#</th>
												<th><?php esc_html_e( 'Transaction Id', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Amount', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Details', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Method', 'wallet-system-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Date', 'wallet-system-for-woocommerce' ); ?></th>
											</tr>
										</thead>
										<tbody>
										   <?php
											global $wpdb;

											$table_name = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
											$transactions = $wpdb->get_results(
												$wpdb->prepare(
													'SELECT * FROM ' . $table_name . ' WHERE user_id = %s ORDER BY `Id` DESC LIMIT 10',
													$user_id
												)
											);

										   if ( ! empty( $transactions ) && is_array( $transactions ) ) {
											   $i = 1;
											   foreach ( $transactions as $transaction ) {
												   $transaction_amount_bal = apply_filters( 'wps_wsfw_show_converted_price', $transaction->amount );
												   $user           = get_user_by( 'id', $transaction->user_id );
												   $transaction_id = $transaction->id;
												   $tranasction_symbol = '';
												   if ( 'credit' == $transaction->transaction_type_1 ) {
													   $tranasction_symbol = '+';
												   } elseif ( 'debit' == $transaction->transaction_type_1 ) {
													   $tranasction_symbol = '-';
												   }
													?>
													<tr>
														<td><?php echo esc_html( $i ); ?></td>
														<td>
													   <?php
														   $date = date_create( $transaction->date );
														   echo esc_html( $date->getTimestamp() . $transaction->id );

														?>
														</td>
														<td class='wps_wallet_<?php echo esc_attr( $transaction->transaction_type_1 ); ?>' ><?php echo esc_html( $tranasction_symbol ) . wp_kses_post( wc_price( $transaction_amount_bal, array( 'currency' => $transaction->currency ) ) ); ?></td>
														<td class="details" ><?php echo wp_kses_post( html_entity_decode( $transaction->transaction_type ) ); ?></td>
														<td>
														<?php
														$payment_methods = WC()->payment_gateways->payment_gateways();
														foreach ( $payment_methods as $key => $payment_method ) {
															if ( $key == $transaction->payment_method ) {
																$method = esc_html__( 'Online Payment', 'wallet-system-for-woocommerce' );
															} else {
																$method = $transaction->payment_method;
															}
															break;
														}
														echo esc_html( $method );
														?>
														</td>
														<td>
														<?php
														$date_format = get_option( 'date_format', 'm/d/Y' );
														$date        = date_create( $transaction->date );
														$wps_wsfw_time_zone = get_option( 'timezone_string' );
														if ( ! empty( $wps_wsfw_time_zone ) ) {
															$date = date_create( $transaction->date );
															echo esc_html( date_format( $date, $date_format ) );
															// extra code.( need validation if require).
															$date->setTimezone( new DateTimeZone( get_option( 'timezone_string' ) ) );
															// extra code.
															echo ' ' . esc_html( date_format( $date, 'H:i:s' ) );
														} else {

															$date_format = get_option( 'date_format', 'm/d/Y' );
															$date        = date_create( $transaction->date );
															echo esc_html( date_format( $date, $date_format ) );
															echo ' ' . esc_html( date_format( $date, 'H:i:s' ) );
														}
														?>
														</td>
													</tr>
													<?php
													$i++;
											   }
										   }

											?>
										</tbody>
									</table>
								</div>
	
								<?php
								// including regular expression jquery.
								wp_enqueue_script( 'anchor-tag', WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_URL . 'public/src/js/wallet-system-for-woocommerce-anchor.js', array(), WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_VERSION, 'all' );
								?>
	
								<!-- removing the anchor tag href attibute using regular expression -->	
								<script>
								jQuery( "#transactions_table tr td" ).each(function( index ) {
									var details = jQuery( this ).html();
									var patt = new RegExp("<a");
									var res = patt.test(details);
									if ( res ) {
										jQuery(this).children('a').removeAttr("href");
									}
								});
								</script>
							</div>
							<!--  recent transaction history code -->   
	
						</div>
					</div>
					<?php
				} else {
					?>
					<div class="wps-wp_ctlm wps-wp_ctl-recent wps-wp_ctrl--active">
						<div class="wsfw_show_user_restriction_notice">
						   <?php
							if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
								echo esc_html( $wps_wallet_restrict_message_for );

							} else {
								esc_html_e( 'Wallet Transaction Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
							}
							?>
						</div>
					</div>
					<?php
				}
				?>

			</div>
		</div>
		<div class="wps-wp_ct-right">
			<span class="wps-wp_ctr-close">&times;</span>
			<div class="wps-wp_ctr-user">
				<img src="<?php echo $avatar_url; ?>" alt="user" class="wps-wp_ctru-img" />
				<!-- avatar_url -->
				<div class="wps-wp_ctru-title"><?php echo $first_name . ' ' . $last_name; ?></div>
				<div class="wps-wp_ctru-desc"><?php echo $roles[0]; ?></div>
				<?php
				$wps_wallet_id = get_user_meta( $user_id, 'wps_wallet_id', true );
				if ( empty( $wps_wallet_id ) ) {
					$wps_wallet_id = 'Not Generated';
				}
				if ( 'on' != $wps_wallet_restrict_wallet_id ) {
					?>
					<div class="wps_wsfw_wallet_user_id">
						<h4><?php esc_html_e( 'wallet id - ', 'wallet-system-for-woocommerce-pro' ); ?><strong><?php echo esc_html( $wps_wallet_id ); ?></strong></h4>
					<?php
					if ( 'Not Generated' == $wps_wallet_id ) {
						?>
						<form method="post" action="">
							<input type="hidden" name="assign_wallet_id" value="<?php echo esc_attr( $user_id ); ?>">
							<input type="hidden" id="wps_verifynonce" name="wps_verifynonce" value="<?php echo esc_attr( wp_create_nonce() ); ?>" />
							<input type="submit" class=" button" id="wps_wallet_generate_wallet_id" name="wps_wallet_generate_wallet_id" value="<?php esc_html_e( 'Generate Wallet ID', 'wallet-system-for-woocommerce' ); ?>" >
						</form>
						<?php
					}
					?>
					</div>
					<?php
				}

				?>
			</div>
			<div class="wps-wp_ctr-links">
				<?php
				if ( ! empty( $product_id ) && ! empty( $enable_wallet_recharge ) ) {

					?>
						<a href="#" class="wps-wp_ctr-link wps-wp_ctr-add wps-wp_ctrl--active">
							<div class="wps-wp_ctrl-img">
								<img src="<?php echo WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'public/image/add.svg'; ?>" />
							</div>
							<div class="wps-wp_ctrl-label"><?php esc_html_e( 'Add', 'wallet-system-for-woocommerce-pro' ); ?></div>
						</a>
					<?php

				}

				?>
						<a href="#" class="wps-wp_ctr-link wps-wp_ctr-transfer">
							<div class="wps-wp_ctrl-img">
								<img src="<?php echo WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'public/image/transfer.svg'; ?>" />
							</div>
							<div class="wps-wp_ctrl-label"><?php esc_html_e( 'Transfer', 'wallet-system-for-woocommerce-pro' ); ?></div>
						</a>
					
						<a href="#" class="wps-wp_ctr-link wps-wp_ctr-withdraw">
							<div class="wps-wp_ctrl-img">
								<img src="<?php echo WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'public/image/withdraw.svg'; ?>" />
							</div>
							<div class="wps-wp_ctrl-label"><?php esc_html_e( 'Withdraw', 'wallet-system-for-woocommerce-pro' ); ?></div>
						</a>
				   
					<a href="#" class="wps-wp_ctr-link wps-wp_ctr-more">
						<div class="wps-wp_ctrl-img">
							<img src="<?php echo WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'public/image/more.svg'; ?>" />
						</div>
						<div class="wps-wp_ctrl-label"><?php esc_html_e( 'More', 'wallet-system-for-woocommerce-pro' ); ?></div>
					</a>
						 
				<div class="wps-wp_ctrlm-links">
					<div class="wps-wp_ctrlm-links-in">
					   
							
						<a href="#" class="wps-wp_ctrml wps-wp_ctrm-wallet_fund_request"><?php esc_html_e( 'Wallet Fund Request', 'wallet-system-for-woocommerce-pro' ); ?></a>
						<a href="#" class="wps-wp_ctrml wps-wp_ctrm-redeem"><?php esc_html_e( 'Redeem', 'wallet-system-for-woocommerce-pro' ); ?></a>
						<?php
						$wallet_qr_code_image = get_user_meta( $user_id, 'wallet_qr_code_image', true );
						if ( ! $wallet_qr_code_image ) {
							?>
								<a href="#" class="wps-wp_ctrml wps-wp_ctrm-qr"><?php esc_html_e( 'Generate QR', 'wallet-system-for-woocommerce-pro' ); ?></a>	
							<?php

						}
						?>
					</div>
				</div>
			</div>
			<div class="wps-wp_ctr-main">
			<?php
			 $is_wallet_recharge_enabled = get_option( 'wps_wsfwp_wallet_promotion_tab_enable' );
			if ( 'on' == $is_wallet_recharge_enabled ) {
				?>
		 
		 
						 <div class="wallet-promotion-tab">
							 <div class="wps-wsfw__prom-tab-head">
								 <h3><span class="wps-pr-title"><?php echo esc_html__( 'Wallet Promotion :', 'wallet-system-for-woocommerce' ); ?></span></h3>
								<?php

								$is_wallet_recharge_enabled = get_option( 'wps_wsfwp_wallet_promotion_tab_limited_offer_enable' );
								if ( 'on' == $is_wallet_recharge_enabled ) {
									?>
								 <p class="wps-pr-sub"><?php echo esc_html__( 'Limited Time Only:', 'wallet-system-for-woocommerce' ); ?> <span  class="wps-pr-time" id="the-final-countdown"></span></p>
									 <?php

								}
								?>
							 </div>
							 <div class="wps-wsfw__prom-tab-wrap">
		 
		 
				 <?php

							$wallet_promotions_data_title = get_option( 'wallet_promotions_data_title' );

					$wallet_promotions_data_content = get_option( 'wallet_promotions_data_content' );

					if ( ! empty( $wallet_promotions_data_title ) && is_array( $wallet_promotions_data_title ) ) {
						if ( '' == $wallet_promotions_data_title[0] ) {
							$wallet_promotions_data_title = array();
						}
					} else {
						$wallet_promotions_data_title = array();
					}
					if ( ! empty( $wallet_promotions_data_content ) && is_array( $wallet_promotions_data_content ) ) {
						if ( '' == $wallet_promotions_data_content[0] ) {
							$wallet_promotions_data_content = array();
						}
					} else {
						$wallet_promotions_data_content = array();
					}
						$wps_wallet_recharge_tab_cashback_type = get_option( 'wps_wallet_recharge_tab_cashback_type' );

					if ( ! empty( $wallet_promotions_data_title ) && is_array( $wallet_promotions_data_title ) ) {
						$index = 0;
						$count_data = count( $wallet_promotions_data_title );
						if ( $count_data > 0 ) {


							for ( $i = 0; $i < $count_data; $i++ ) {
								?>
						 <div class="wps-wsfw__prom-tab-item wps-active">
									 <div class="wps-pr__item-wrap">
										 <span class="wps-pr-offer"><?php echo esc_html( $wallet_promotions_data_title[ $i ] ); ?></span>
										 <p class="wps-pr-offer-desc"><?php echo esc_html( $wallet_promotions_data_content[ $i ] ); ?></p>
									 </div>
								 </div>
								<?php
							}
						}
					}
					?>
										 
							 </div>
						 </div>
		 
						<?php
			}
			if ( ! empty( $product_id ) && ! empty( $enable_wallet_recharge ) ) {
				if ( 'on' != $wallet_restrict_topup ) {
					?>
					<div class="wps-wp_ctr-con wps-wp_ctr-add-content wps-wp_ctrl--active">
						<form method="POST">
						<!-- Add Amount Content -->
						
					<?php

					include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-wallet-recharge.php';
					?>
						</form>
						<!-- Add Amount Content -->
					</div>
					<?php
				} else {
					?>
						<div class="wps-wp_ctr-con wps-wp_ctr-add-content wps-wp_ctrl--active">
							<div class="wsfw_show_user_restriction_notice">
							<?php
							if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
								echo esc_html( $wps_wallet_restrict_message_for );

							} else {
								esc_html_e( 'Wallet Top-up or Add balance Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
							}
							?>
							</div>
						</div>
						<?php
				}
			}
			if ( 'on' != $wallet_restrict_transfer ) {
				?>
					<!-- Transfer Content -->
					<div class="wps-wp_ctr-con wps-wp_ctr-transfer-content">
						<form method="POST">
					<?php
					   include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-wallet-transfer.php';
					?>
						</form>   
					</div>
					<!-- Transfer Content -->
					<?php
			} else {
				?>
					<div class="wps-wp_ctr-con wps-wp_ctr-transfer-content">
						<div class="wsfw_show_user_restriction_notice">
						<?php
						if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
							echo esc_html( $wps_wallet_restrict_message_for );

						} else {
							esc_html_e( 'Wallet Transfer Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
						}
						?>
						</div>
					</div>
					<?php
			}
			if ( 'on' != $wallet_restrict_withdrawal ) {
				?>
						<!-- Withdraw Content -->
						<div class="wps-wp_ctr-con wps-wp_ctr-withdraw-content">
							<form method="POST">
								<?php
								include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-wallet-withdrawal.php';
								?>
							</form>
						</div>
						<!-- wallet withdraw content -->
					<?php
			} else {
				?>
					<div class="wps-wp_ctr-con wps-wp_ctr-withdraw-content">
						<div class="wsfw_show_user_restriction_notice">
						<?php
						if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
							echo esc_html( $wps_wallet_restrict_message_for );

						} else {
							esc_html_e( 'Wallet withdrawal Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
						}
						?>
						</div>
					</div>
					<?php
			}
				// wallet fund request
			if ( 'on' != $wallet_restrict_fund_request ) {
				?>
					  <div class="wps-wp_ctr-con wps-wp_ctr-wallet_fund_request-content">
							<form method="POST">
						<?php
						include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-wallet-fund-request.php';
						?>
							</form>
						</div>
					<?php
			} else {
				?>
					<div class="wps-wp_ctr-con wps-wp_ctr-wallet_fund_request-content">
						<div class="wsfw_show_user_restriction_notice">
						<?php
						if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
							echo esc_html( $wps_wallet_restrict_message_for );

						} else {
							esc_html_e( 'Wallet Fund Request Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
						}
						?>
						</div>
					</div>
					<?php
			}

				// wallet fund request
			if ( 'on' != $wallet_restrict_coupon ) {
				?>
						<!-- Redeem Content -->
						<div class="wps-wp_ctr-con wps-wp_ctr-redeem-content">
						<?php
						include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-wallet-coupon.php';
						?>
						</div>
						<!-- Redeem Content -->
					<?php
			} else {
				?>
					<div class="wps-wp_ctr-con wps-wp_ctr-redeem-content">
						<div class="wsfw_show_user_restriction_notice">
						<?php
						if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
							echo esc_html( $wps_wallet_restrict_message_for );

						} else {
							esc_html_e( 'Wallet coupon redeem Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
						}
						?>
						</div>
					</div>
					<?php
			}
			if ( 'on' != $wallet_restrict_qrcode ) {
				?>
					<!-- QR Content -->
					<div class="wps-wp_ctr-con wps-wp_ctr-qr-content">
					<?php
					include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-public-display.php';
					?>
					</div>
					<!-- QR Content -->
					<?php
			} else {
				?>
					<div class="wps-wp_ctr-con wps-wp_ctr-qr-content">
						<div class="wsfw_show_user_restriction_notice">
						<?php
						if ( ! empty( $wps_wallet_restrict_message_for ) && 'on' == $wps_wallet_restrict_message_to_user ) {
							echo esc_html( $wps_wallet_restrict_message_for );

						} else {
							esc_html_e( 'Wallet QR Code Feature is restrict From Site owner', 'wallet-system-for-woocommerce' );
						}
						?>
						</div>
					</div>
					<?php
			}
			?>
			</div>
		</div>
	</div>
</div>