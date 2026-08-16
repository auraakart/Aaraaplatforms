<?php
/**
 * The common functionality of the plugin.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/common
 */

/**
 * The common functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the common stylesheet and JavaScript.
 * namespace wallet_system_for_woocommerce_pro_common.
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/common
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro_Common {
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the common side of the site.
	 *
	 * @since    1.0.0
	 */
	public function wsfwp_common_enqueue_styles() {
		wp_enqueue_style( $this->plugin_name . 'common', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'common/src/scss/wallet-system-for-woocommerce-pro-common.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the common side of the site.
	 *
	 * @since    1.0.0
	 */
	public function wsfwp_common_enqueue_scripts() {
		wp_register_script( $this->plugin_name . 'common', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'common/src/js/wallet-system-for-woocommerce-pro-common.js', array( 'jquery' ), $this->version, false );
		wp_localize_script( $this->plugin_name . 'common', 'wsfwp_common_param', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		wp_enqueue_script( $this->plugin_name . 'common' );
	}


	/**
	 * Function is used to verify the license code.
	 *
	 * @name mwb_standard_plugin_license_code_update
	 * @since 1.0.0
	 * @param String $license_code is the code of license.
	 */
	public function wps_wsfw_plugin_license_code_update( $license_code ) {
		$wps_wsfw_server_name = ( ! empty( $_SERVER['SERVER_NAME'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '';
		$api_params = array(
			'slm_action'        => 'slm_activate',
			'secret_key'        => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_SPECIAL_SECRET_KEY,
			'license_key'       => $license_code,
			'_registered_domain' => $wps_wsfw_server_name,
			'item_reference'    => urlencode( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_ITEM_REFERENCE ),
			'product_reference' => 'WPSPK-67573',
		);

		$query = esc_url_raw( add_query_arg( $api_params, WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_LICENSE_SERVER_URL ) );

		$wps_wsfw_mps_response = wp_remote_get(
			$query,
			array(
				'timeout' => 20,
				'sslverify' => false,
			)
		);
		return $wps_wsfw_mps_response;
	}


	/**
	 * Validate license key.
	 *
	 * @return void
	 */
	public function wps_wsfwp_validate_license_key() {
		check_ajax_referer( 'check-nonce', 'nonce' );

		$license_key = ( isset( $_POST['license_key'] ) ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : ''; //phpcs:ignore
		$wps_wsfw_mps_response = self::wps_wsfw_plugin_license_code_update( $license_key );

		if ( is_wp_error( $wps_wsfw_mps_response ) ) {
			echo json_encode(
				array(
					'status' => false,
					'msg' => __(
						'An unexpected error occurred. Please try again.',
						'mwb-standard-plugin'
					),
				)
			);
		} else {

			$mwb_mps_license_data = json_decode( wp_remote_retrieve_body( $wps_wsfw_mps_response ) );

			if ( isset( $mwb_mps_license_data->result ) && 'success' === $mwb_mps_license_data->result ) {
				if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
					require_once ABSPATH . '/wp-admin/includes/plugin.php';
				}
				if ( is_plugin_active_for_network( 'wallet-system-for-woocommerce-pro/wallet-system-for-woocommerce-pro.php' ) || is_multisite() ) {
					global $wpdb;
					foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
						switch_to_blog( $blog_id );
						update_option( 'wps_wsfwp_lic_key', $license_key );
						update_option( 'wps_wsfwp_pro_valid_license', true );
						restore_current_blog();
					}
				} else {
					update_option( 'wps_wsfwp_lic_key', $license_key );
					update_option( 'wps_wsfwp_pro_valid_license', true );
				}

				echo json_encode(
					array(
						'status' => true,
						'msg' => __(
							'Successfully Verified. Please Wait.',
							'mwb-standard-plugin'
						),
					)
				);
			} else {
				echo json_encode(
					array(
						'status' => false,
						'msg' => $mwb_mps_license_data->message,
					)
				);
			}
		}
		wp_die();
	}

	/**
	 * Get current currency.
	 *
	 * @param string $currency currency.
	 * @return string
	 */
	public function wps_wsfwp_get_current_currency( $currency ) {
		if ( function_exists( 'wps_mmcsfw_get_currenct_currency' ) ) {
			$current_currency = wps_mmcsfw_get_currenct_currency();
			return $current_currency;
		} else {
			return $currency;
		}
	}

	/**
	 * Convert the orders amount into base currency amount.
	 *
	 * @param string $price price.
	 * @param string $currency currency.
	 * @return string
	 */
	public function wps_wsfwp_common_update_wallet_to_base_price( $price, $currency ) {
		if ( function_exists( 'wps_mmcsfw_admin_fetch_currency_rates_to_base_currency' ) ) {
			$base_amount = wps_mmcsfw_admin_fetch_currency_rates_to_base_currency( $currency, $price );
			return $base_amount;
		} else {
			return $price;
		}
	}

	/**
	 * Show message for guest user.
	 *
	 * @param string $wpg_message message to be shown on form submission.
	 * @param string $type error type.
	 * @return void
	 */
	public function show_message_for_guest_user( $wpg_message, $type = 'error' ) {
		$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
		echo wp_kses_post( $wpg_notice );
	}

	/**
	 * Shortcodes for wallet.
	 *
	 * @return void
	 */
	public function wps_wsfw_wallet_shortcodes() {
		add_shortcode( 'WPS_WALLET_RECHARGE', array( $this, 'wps_wsfw_elementor_wallet_recharge' ) );
		add_shortcode( 'WPS_WALLET_TRANSFER', array( $this, 'wps_wsfw_elementor_wallet_transfer' ) );
		add_shortcode( 'WPS_WITHDRAWAL_REQUEST', array( $this, 'wps_wsfw_elementor_wallet_withdrawal' ) );
		add_shortcode( 'WPS_WALLET_TRANSACTIONS', array( $this, 'wps_wsfw_elementor_wallet_transactions' ) );

		add_shortcode( 'wps-wallet-dashboard', array( $this, 'wps_wallet_system_shortcode_new_layout' ) );
	}

	/**
	 * Shortcode function for wallet new layout
	 *
	 * @return bool
	 */
	public function wps_wallet_system_shortcode_new_layout() {
		ob_start();
		if ( ! is_user_logged_in() ) {
			echo '<div class="woocommerce">';
			wc_get_template( 'myaccount/form-login.php' );
			echo '</div>';
		} else {
			include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-wallet-template-display.php';
		}
		return ob_get_clean();
	}

	/**
	 * Show wallet recharge page according to shortcode.
	 *
	 * @return string
	 */
	public function wps_wsfw_elementor_wallet_recharge() {
		ob_start();
		if ( ! is_user_logged_in() ) {
			$this->show_message_for_guest_user( esc_html__( 'You are not logged in, please log in first for recharging the wallet.', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
		} else {
			include WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'elementor-widget/wps-wws-elementor-wallet-recharge.php';
		}
		return ob_get_clean();
	}

	/**
	 * Show wallet transfer page according to shortcode.
	 *
	 * @return string
	 */
	public function wps_wsfw_elementor_wallet_transfer() {
		ob_start();
		if ( ! is_user_logged_in() ) {
			$this->show_message_for_guest_user( esc_html__( 'You are not logged in, please log in first for transferring the wallet amount.', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
		} else {
			include WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'elementor-widget/wps-wws-elementor-wallet-transfer.php';
		}
		return ob_get_clean();
	}

	/**
	 * Show wallet withdrawal page according to shortcode.
	 *
	 * @return string
	 */
	public function wps_wsfw_elementor_wallet_withdrawal() {
		ob_start();
		if ( ! is_user_logged_in() ) {
			$this->show_message_for_guest_user( esc_html__( 'You are not logged in, please log in first for requesting wallet withdrawal.', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
		} else {
			include WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'elementor-widget/wps-wws-elementor-wallet-withdrawal.php';
		}
		return ob_get_clean();
	}

	/**
	 * Show wallet transaction page according to shortcode.
	 *
	 * @return string
	 */
	public function wps_wsfw_elementor_wallet_transactions() {
		ob_start();
		if ( ! is_user_logged_in() ) {
			$this->show_message_for_guest_user( esc_html__( 'You are not logged in, please log in first to see wallet transactions.', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
		} else {
			include WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'elementor-widget/wps-wws-elementor-wallet-transactions.php';
		}
		return ob_get_clean();
	}

	/**
	 * Show message on form submit
	 *
	 * @param string $wpg_message message to be shown on form submission.
	 * @param string $type error type.
	 * @return void
	 */
	public function show_message_on_wallet_form_submit( $wpg_message, $type = 'woocommerce-error' ) {
		$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
		echo wp_kses_post( $wpg_notice );
	}

	/**
	 * Add wallet to cart, request wallet withdrawal.
	 *
	 * @return void
	 */
	public function wps_wsfw_save_wallet_public_shortcode() {

		$nonce = ( isset( $_POST['wps_verifynonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['wps_verifynonce'] ) ) : '';

		if ( wp_verify_nonce( $nonce ) ) {

			if ( isset( $_POST['wps_recharge_wallet'] ) && ! empty( $_POST['wps_recharge_wallet'] ) ) {

				unset( $_POST['wps_recharge_wallet'] );

				if ( empty( $_POST['wps_wallet_recharge_amount'] ) ) {
					$this->show_message_on_wallet_form_submit( esc_html__( 'Please enter amount greater than 0', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
				} else {
					$recharge_amount = sanitize_text_field( wp_unslash( $_POST['wps_wallet_recharge_amount'] ) );
					$recharge_amount = apply_filters( 'wps_wsfw_convert_to_base_price', $recharge_amount );
					if ( ! empty( $_POST['user_id'] ) ) {
						$user_id = sanitize_text_field( wp_unslash( $_POST['user_id'] ) );
					}
					if ( isset( $_POST['wps_wallet_recharge_as_subscription'] ) ) {
						update_user_meta( $user_id, 'wps_wallet_recharge_as_subscription', 'yes' );
					} else {
						update_user_meta( $user_id, 'wps_wallet_recharge_as_subscription', 'no' );
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
					wp_redirect( wc_get_cart_url() );
					exit();
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
								// extra fee.
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
								update_post_meta( $withdrawal_id, $key, $withdrawal_bal );
							} else {
								update_post_meta( $withdrawal_id, $key, $value );
							}
						}
					}

					update_user_meta( $user_id, 'disable_further_withdrawal_request', true );

					$wsfwp_withdrawal_admin_withdrawal_request_email = get_option( 'wsfwp_withdrawal_admin_withdrawal_request_email', '' );
					if ( ! empty( $wsfwp_withdrawal_admin_withdrawal_request_email ) ) {
							// for simplicity, lets assume that user has typed their first and last name when they sign up.
							$user_full_name = $user->user_firstname . ' ' . $user->user_lastname;

							$amount = ! empty( $_POST['wps_wallet_withdrawal_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_withdrawal_amount'] ) ) : '';
							$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );
							// Now we are ready to build our welcome email.
							$to = $wsfwp_withdrawal_admin_withdrawal_request_email;
							$subject = 'Hi there is Withdrawal Request from ' . $user_full_name;
							$body = '<h2>Dear Admin,</h2></br>
					  <p>You got a Withdrawal Request of ' . $current_currency . ' ' . $amount . ' from ' . $user_full_name . '</p>
					  <p>Please approve the Withdrawal Request.</p>';

							$headers = array( 'Content-Type: text/html; charset=UTF-8' );
						if ( wc_mail( $to, $subject, $body, $headers ) ) {

							error_log( 'email has been successfully sent to user whose email is ' . $wsfwp_withdrawal_admin_withdrawal_request_email );

						} else {

							error_log( 'email failed to sent to user whose email is ' . $wsfwp_withdrawal_admin_withdrawal_request_email );
						}
					}
					$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
					$request_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
					$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_url;
					wp_safe_redirect( $current_url );
					exit();
				}
			}
			if ( isset( $_POST['wps_proceed_transfer'] ) && ! empty( $_POST['wps_proceed_transfer'] ) ) {
				unset( $_POST['wps_proceed_transfer'] );
				$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );
				$update           = true;

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
					$this->show_message_on_wallet_form_submit( esc_html__( 'Email Id does not exist. ', 'wallet-system-for-woocommerce-pro' ) . $invitation_link, 'woocommerce-error' );
					$update = false;
				}
				if ( empty( $_POST['wps_wallet_transfer_amount'] ) ) {
					$this->show_message_on_wallet_form_submit( esc_html__( 'Please enter amount greater than 0', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
					$update = false;
				} elseif ( $wallet_bal < $wallet_transfer_amount ) {
					$this->show_message_on_wallet_form_submit( esc_html__( 'Please enter amount less than or equal to wallet balance', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
					$update = false;
				} elseif ( $another_user_email == $wps_current_user_email ) {
					$this->show_message_on_wallet_form_submit( esc_html__( 'You cannot transfer amount to yourself.', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
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
							$this->show_message_on_wallet_form_submit( esc_html__( 'Amount is transferred successfully', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-message' );
						} else {
							$this->show_message_on_wallet_form_submit( esc_html__( 'Amount is not transferred', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
						}
					} else {
						$this->show_message_on_wallet_form_submit( esc_html__( 'No user found.', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-error' );
					}
					$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
					$request_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
					$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_url;

					echo '<script>';
					echo ' setTimeout(function() {';
					echo "  window.location.href= '" . esc_url( $current_url ) . "'";
					echo '}, 3000);';
					echo '</script>';

				}
			}
		}
	}

	/** Coupon redemption */

	/**
	 * This function is used to redeem coupon.
	 *
	 * @return void
	 */
	public function wps_wsfwp_redeem_coupon_amount() {
		check_ajax_referer( 'ajax-nonce', 'wps_wsfwp_nonce' );
		if ( is_user_logged_in() ) {

			$wps_current_user_id    = ! empty( $_POST['wps_current_user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_current_user_id'] ) ) : '';
			$wps_wsfwp_coupon_code  = ! empty( $_POST['wps_wsfwp_coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wsfwp_coupon_code'] ) ) : '';
			$wallet_user            = get_user_by( 'id', $wps_current_user_id );
			$wallet_payment_gateway = new Wallet_System_For_Woocommerce();
			$send_email_enable      = get_option( 'wps_wsfw_enable_email_notification_for_wallet_update', '' );
			$updated                = false;

			if ( ! empty( $wps_current_user_id ) && ! empty( $wps_wsfwp_coupon_code ) ) {
				$coupon_data = get_posts(
					array(
						'post_type' => 'wps_cpt_coupons',
						'title'     => $wps_wsfwp_coupon_code,
					)
				);

				if ( isset( $coupon_data ) && ! empty( $coupon_data ) ) {
					$coupon_id = $coupon_data[0]->ID;

					if ( ! empty( $coupon_id ) ) {
						$wps_wsfw_coupon_amount    = get_post_meta( $coupon_id, 'wps_wsfw_coupon_amount', true );
						$wps_wsfw_limit_per_coupon = get_post_meta( $coupon_id, 'wps_wsfw_limit_per_coupon', true );
						$wps_wsfw_limit_per_user   = get_post_meta( $coupon_id, 'wps_wsfw_limit_per_user', true );
						$wps_wsfw_coupon_expiry    = get_post_meta( $coupon_id, 'wps_wsfw_coupon_expiry', true );

						if ( 'on' === $wps_wsfw_coupon_expiry ) {

							if ( empty( $wps_wsfw_limit_per_coupon ) && empty( $wps_wsfw_limit_per_user ) ) {

								$usage_data  = get_option( $coupon_id . '_wallet_coupon_usage_data', array() );

								if ( empty( $usage_data ) ) {
									$per_user_count = 0;
								} else {
									$per_user_count = array_count_values( array_column( $usage_data, 'user_id' ) )[ $wps_current_user_id ];
								}

								if ( $wps_wsfw_coupon_amount < 0 || null == $wps_wsfw_coupon_amount ) {
									$wps_wsfw_coupon_amount = 0;
								}

								$temp_arr = array(
									'user_id' => $wps_current_user_id,
								);

								if ( empty( $usage_data ) ) {
									$usage_data = array();
									array_push( $usage_data, $temp_arr );
								} else {
									array_push( $usage_data, $temp_arr );
								}

								$wallet_amount      = get_user_meta( $wps_current_user_id, 'wps_wallet', true );
								$wallet_amount      = ! empty( $wallet_amount ) ? $wallet_amount : 0;
								$wps_updated_point  = (int) $wallet_amount + $wps_wsfw_coupon_amount;
								update_user_meta( $wps_current_user_id, 'wps_wallet', $wps_updated_point );
								update_option( $coupon_id . '_wallet_coupon_usage_data', $usage_data );
								$response['status'] = true;
								$response['msg']    = __( 'Coupon Redeemed Successfully !', 'wallet-system-for-woocommerce-pro' );
								$updated            = true;

							} elseif ( ! empty( $wps_wsfw_limit_per_user ) && empty( $wps_wsfw_limit_per_coupon ) ) {

								$usage_data  = get_option( $coupon_id . '_wallet_coupon_usage_data', array() );

								if ( empty( $usage_data ) ) {
									$per_user_count = 0;
								} else {
									$per_user_count = array_count_values( array_column( $usage_data, 'user_id' ) )[ $wps_current_user_id ];
								}

								if ( $wps_wsfw_limit_per_user > $per_user_count ) {

									$temp_arr = array(
										'user_id' => $wps_current_user_id,
									);

									if ( empty( $usage_data ) ) {
										$usage_data = array();
										array_push( $usage_data, $temp_arr );
									} else {
										array_push( $usage_data, $temp_arr );
									}

									if ( $wps_wsfw_coupon_amount < 0 || null == $wps_wsfw_coupon_amount ) {
										$wps_wsfw_coupon_amount = 0;
									}

									$wallet_amount      = get_user_meta( $wps_current_user_id, 'wps_wallet', true );
									$wallet_amount      = ! empty( $wallet_amount ) ? $wallet_amount : 0;
									$wps_updated_point  = (int) $wallet_amount + $wps_wsfw_coupon_amount;
									update_user_meta( $wps_current_user_id, 'wps_wallet', $wps_updated_point );
									update_option( $coupon_id . '_wallet_coupon_usage_data', $usage_data );
									$response['status'] = true;
									$response['msg']    = __( 'Coupon Redeemed Successfully !', 'wallet-system-for-woocommerce-pro' );
									$updated            = true;

								} else {
									$response['status'] = false;
									$response['msg']    = __( 'Coupon usage limit has been reached !!', 'wallet-system-for-woocommerce-pro' );
								}
							} elseif ( ! empty( $wps_wsfw_limit_per_coupon ) && empty( $wps_wsfw_limit_per_user ) ) {

								$usage_data  = get_option( $coupon_id . '_wallet_coupon_usage_data', array() );

								if ( empty( $usage_data ) ) {
									$usage_count    = 0;
									$per_user_count = 0;
								} else {
									$usage_count    = count( $usage_data );
									$per_user_count = array_count_values( array_column( $usage_data, 'user_id' ) )[ $wps_current_user_id ];
								}

								if ( $wps_wsfw_limit_per_coupon > $usage_count ) {

									$temp_arr = array(
										'user_id' => $wps_current_user_id,
									);

									if ( empty( $usage_data ) ) {
										$usage_data = array();
										array_push( $usage_data, $temp_arr );
									} else {
										array_push( $usage_data, $temp_arr );
									}
									if ( $wps_wsfw_coupon_amount < 0 || null == $wps_wsfw_coupon_amount ) {
										$wps_wsfw_coupon_amount = 0;
									}

									$wallet_amount      = get_user_meta( $wps_current_user_id, 'wps_wallet', true );
									$wallet_amount      = ! empty( $wallet_amount ) ? $wallet_amount : 0;
									$wps_updated_point  = (int) $wallet_amount + $wps_wsfw_coupon_amount;
									update_user_meta( $wps_current_user_id, 'wps_wallet', $wps_updated_point );
									update_option( $coupon_id . '_wallet_coupon_usage_data', $usage_data );
									$response['status'] = true;
									$response['msg']    = __( 'Coupon Redeemed Successfully !', 'wallet-system-for-woocommerce-pro' );
									$updated            = true;

								} else {
									$response['status'] = false;
									$response['msg']    = __( 'Coupon usage limit has been reached !!', 'wallet-system-for-woocommerce-pro' );
								}
							} else {

								$usage_data  = get_option( $coupon_id . '_wallet_coupon_usage_data', array() );

								if ( empty( $usage_data ) ) {
									$usage_count    = 0;
									$per_user_count = 0;
								} else {
									$usage_count    = count( $usage_data );
									$per_user_count = array_count_values( array_column( $usage_data, 'user_id' ) )[ $wps_current_user_id ];
								}

								if ( ( $wps_wsfw_limit_per_coupon > $usage_count ) && ( $wps_wsfw_limit_per_user > $per_user_count ) ) {

									$temp_arr = array(
										'user_id' => $wps_current_user_id,
									);

									if ( empty( $usage_data ) ) {
										$usage_data = array();
										array_push( $usage_data, $temp_arr );
									} else {
										array_push( $usage_data, $temp_arr );
									}
									if ( $wps_wsfw_coupon_amount < 0 || null == $wps_wsfw_coupon_amount ) {
										$wps_wsfw_coupon_amount = 0;
									}

									$wallet_amount      = get_user_meta( $wps_current_user_id, 'wps_wallet', true );
									$wallet_amount      = ! empty( $wallet_amount ) ? $wallet_amount : 0;
									$wps_updated_point  = (int) $wallet_amount + $wps_wsfw_coupon_amount;
									update_user_meta( $wps_current_user_id, 'wps_wallet', $wps_updated_point );
									update_option( $coupon_id . '_wallet_coupon_usage_data', $usage_data );
									$response['status'] = true;
									$response['msg']    = __( 'Coupon Redeemed Successfully !', 'wallet-system-for-woocommerce-pro' );
									$updated            = true;
								} else {
									$response['status'] = false;
									$response['msg']    = __( 'Coupon Code has been already used !!', 'wallet-system-for-woocommerce-pro' );
								}
							}
						} else {
							$response['status'] = false;
							$response['msg']    = __( 'Coupon has been expired !!', 'wallet-system-for-woocommerce-pro' );
						}
					}
				} else {
					$response['status'] = false;
					$response['msg']    = __( 'Invalid Coupon Code !!', 'wallet-system-for-woocommerce-pro' );
				}
			}

			if ( $updated ) {
				$balance   = get_woocommerce_currency() . ' ' . $wps_wsfw_coupon_amount;
				if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {
					$user_name  = $wallet_user->first_name . ' ' . $wallet_user->last_name;
					$mail_text  = sprintf( 'Hello %s', $user_name ) . ",\r\n";
					;
					$mail_text .= __( 'Wallet credited by ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $balance ) . __( ' through coupon redeem.', 'wallet-system-for-woocommerce-pro' );
					$to         = $wallet_user->user_email;
					$from       = get_option( 'admin_email' );
					$subject    = __( 'Wallet updating notification', 'wallet-system-for-woocommerce-pro' );
					$headers    = 'MIME-Version: 1.0' . "\r\n";
					$headers   .= 'Content-Type: text/html;  charset=UTF-8' . "\r\n";
					$headers   .= 'From: ' . $from . "\r\n" .
						'Reply-To: ' . $to . "\r\n";
					$wallet_payment_gateway->send_mail_on_wallet_updation( $to, $subject, $mail_text, $headers );
				}

				$transaction_data = array(
					'user_id'          => $wps_current_user_id,
					'amount'           => $wps_wsfw_coupon_amount,
					'currency'         => get_woocommerce_currency(),
					'payment_method'   => 'coupon',
					'transaction_type' => __( 'Coupon Redeemed', 'wallet-system-for-woocommerce-pro' ),
					'transaction_type_1' => 'credit',
					'order_id'         => '',
					'note'             => '',
				);
				$wallet_payment_gateway->insert_transaction_data_in_table( $transaction_data );
			}
		}
		wp_send_json( $response );
		wp_die();
	}
	/**
	 * Calculate cashback using category feature.
	 *
	 * @param array $term as term.
	 * @param array $product_id as product id.
	 * @param int   $qty as quantity.
	 * @return string
	 */
	public function wps_wsfwp_cashback_using_catwise( $term, $product_id, $qty ) {
		if ( isset( $term[0] ) ) {
			$max_id = $term[0];
		}
		$max_value = 0;
		$wps_wsfwp_cashback_type = get_term_meta( $term[0], '_wps_wsfwp_cashback_type', true );
		if ( 'fixed' == $wps_wsfwp_cashback_type ) {
			$max_value = get_term_meta( $term[0], '_wps_wsfwp_cashback_amount', true );
		}
		if ( 'percent' == $wps_wsfwp_cashback_type ) {
			$product = wc_get_product( $product_id );
			$price = $product->get_price();
			$max_value = get_term_meta( $term[0], '_wps_wsfwp_cashback_amount', true );
			if ( empty( $max_value ) ) {
				$max_value = 0;
			}
			$max_value = ( ( $price * $qty ) * $max_value ) / 100;
		}

		foreach ( $term as $key => $value ) {
			$wps_wsfwp_cashback_type = get_term_meta( $value, '_wps_wsfwp_cashback_type', true );
			if ( 'fixed' == $wps_wsfwp_cashback_type ) {
				$temp = get_term_meta( $value, '_wps_wsfwp_cashback_amount', true );
				if ( $max_value < $temp ) {
					$max_value = $temp;
					$max_id = $value;
				}
			}
			if ( 'percent' == $wps_wsfwp_cashback_type ) {
				$temp = get_term_meta( $value, '_wps_wsfwp_cashback_amount', true );
				$product = wc_get_product( $product_id );
				$price = $product->get_price();
				if ( empty( $temp ) ) {
					$temp = 0;
				}
				$temp = ( ( $price * $qty ) * $temp ) / 100;
				if ( 0 == $temp ) {
					$temp = get_post_meta( $product_id, 'global_cashback_product', true );
				}
				if ( $max_value < $temp ) {
					$max_value = $temp;
					$max_id = $value;
				}
			}
		}
		$wps_wsfwp_cashback_amount = $max_value;
		return $wps_wsfwp_cashback_amount;
	}
	/**
	 * Check pro plugin active for org plugin.
	 *
	 * @param bool $check as check.
	 * @return bool
	 */
	public function wsfwp_check_pro_plugin_common( $check ) {
		return true;
	}
}
