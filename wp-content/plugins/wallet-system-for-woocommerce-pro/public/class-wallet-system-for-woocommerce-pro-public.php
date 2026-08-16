<?php
/**
 * Order Factory
 *
 * The WooCommerce order factory creating the right order objects.
 *
 * @version 2.5.0
 * @package Wallet_System_For_Woocommerce
 */

 use Automattic\WooCommerce\Utilities\OrderUtil;
/**
 * The public-facing functionality of the plugin.
 *
 * @link        https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 * namespace wallet_system_for_woocommerce_pro_public.
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/public
 * @author     WP Swings <webmaster@wpswings.com>
 */
class Wallet_System_For_Woocommerce_Pro_Public {

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
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function wsfwp_public_enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'public/css/wps-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function wsfwp_public_enqueue_scripts() {
		$user_id = get_current_user_id();
		$wallet_restrict_withdrawal = apply_filters( 'wallet_restrict_withdrawal', $user_id );
		$wallet_restrict_fund_request = apply_filters( 'wallet_restrict_fund_request', $user_id );

		wp_register_script( $this->plugin_name, WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'public/src/js/wallet-system-for-woocommerce-pro-public.js', array( 'jquery' ), $this->version, false );
		wp_localize_script(
			$this->plugin_name,
			'wsfwp_public_param',
			array(
				'ajaxurl'                        => admin_url( 'admin-ajax.php' ),
				'wsfwp_ajax_error'               => __( 'An error occured!', 'wallet-system-for-woocommerce-pro' ),
				'wsfwp_amount_error'             => __( 'Enter amount greater than 0', 'wallet-system-for-woocommerce-pro' ),
				'wsfwp_transfer_amount_error'    => __( 'Transfer amount should be less than or equal to wallet balance.', 'wallet-system-for-woocommerce-pro' ),
				'wsfwp_recharge_minamount_error' => __( 'Recharge amount should be greater than or equal to ', 'wallet-system-for-woocommerce-pro' ),
				'wsfwp_recharge_maxamount_error' => __( 'Recharge amount should be less than or equal to ', 'wallet-system-for-woocommerce-pro' ),
				'wsfwp_copied_code'              => __( 'Link Copied', 'wallet-system-for-woocommerce-pro' ),
				'nonce'                          => wp_create_nonce( 'ajax-nonce' ),
				'wsfwp_coupon_error'             => __( 'Please enter Coupon Code', 'wallet-system-for-woocommerce-pro' ),
				'wallet_restrict_withdrawal'     => $wallet_restrict_withdrawal,
				'wallet_restrict_fund_request'   => $wallet_restrict_fund_request,
				'withdrawal_trans_heading'       => __( 'Withdrawal Request Transactions', 'wallet-system-for-woocommerce-pro' ),
				'withdrawal_restrict_notice' => __( 'Wallet withdrawal Feature is restrict From Site owner', 'wallet-system-for-woocommerce-pro' ),
			)
		);
		wp_enqueue_script( $this->plugin_name );
	}

	/**
	 * Add content to the wallet page.
	 */
	public function wps_wsfwp_display_wallet_endpoint_content() {

		include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-public-display.php';
	}

	/**
	 * Add content to wallet topup page through qrcode.
	 */
	public function wps_wsfwp_display_wallet_qrcode_topup_content() {
		include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-wallet-qrcode-topup.php';
	}

	/**
	 *  Register new endpoint to use for My Account page.
	 */
	public function wps_wsfwp_wallet_register_endpoint() {
		global $wp_rewrite;
		add_rewrite_endpoint( 'wps-wallet-topup', EP_PERMALINK | EP_PAGES );
		add_rewrite_endpoint( 'wps-qrcode-image', EP_PERMALINK | EP_PAGES );
		$wp_rewrite->flush_rules();
	}

	/**
	 *  Add new query var.
	 *
	 * @param array $vars    Query variable.
	 */
	public function wps_wsfwp_wallet_query_var( $vars ) {
		$vars[] = 'wps-wallet-topup';
		$vars[] = 'wps-qrcode-image';
		return $vars;
	}

	/**
	 * Add user id as custom data to cart item.
	 *
	 * @param array $cart_item_data cart item data.
	 * @param int   $product_id product id.
	 * @return array
	 */
	public function add_wallet_user_id_in_cart( $cart_item_data, $product_id ) {
		if ( WC()->session->__isset( 'wallet_recharge' ) ) {
			$wallet_recharge = WC()->session->get( 'wallet_recharge' );
			if ( isset( $wallet_recharge ) && ! empty( $wallet_recharge ) ) {
				$cart_item_data['user_id'] = $wallet_recharge['userid'];
				if ( array_key_exists( 'recharge_reason', $wallet_recharge ) ) {
					$cart_item_data['recharge_reason'] = $wallet_recharge['recharge_reason'];
				}
			}
		}
		return $cart_item_data;
	}

	/**
	 * Get cart item from woocommerce cart session
	 *
	 * @param array  $item cart item.
	 * @param array  $values cart item value.
	 * @param string $key cart item key.
	 * @return array
	 */
	public function wps_wsfwp_get_cart_items_from_session( $item, $values, $key ) {
		if ( array_key_exists( 'user_id', $values ) ) {
			$item['user_id'] = $values['user_id'];
		}
		if ( array_key_exists( 'recharge_reason', $values ) ) {
			$item['recharge_reason'] = $values['recharge_reason'];
		}
		return $item;
	}

	/**
	 * Show custom data added to cart item in cart section.
	 *
	 * @param string $product_name product name.
	 * @param array  $values cart item value.
	 * @param string $cart_item_key cart item key.
	 * @return string
	 */
	public function wps_wsfwp_show_user_custom_data_in_cart( $product_name, $values, $cart_item_key ) {
		if ( $values['user_id'] > 0 ) {
			$return_string  = $product_name . "</a><dl class='variation'>";
			$return_string .= "<table class='wdm_options_table' id='" . $values['product_id'] . "'>";
			$return_string .= '<tr><td>' . $values['user_id'] . $values['recharge_reason'] . '</td></tr>';
			$return_string .= '</table></dl>';
			return $return_string;
		} else {
			return $product_name;
		}
	}

	/**
	 * REmove custom data user id from cart item data.
	 *
	 * @param  string $cart_item_key cart item key.
	 * @return void
	 */
	public function wps_wsfwp_remove_user_id_from_cart( $cart_item_key ) {
		global $woocommerce;
		$cart = $woocommerce->cart->get_cart();
		foreach ( $cart as $key => $values ) {
			if ( $values['user_id'] == $cart_item_key ) {
				unset( $woocommerce->cart->cart_contents[ $key ] );
			}
		}
	}

	/**
	 * Check whether order has user id as meta key
	 *
	 * @param int $user_id user id.
	 * @param int $order_id order id.
	 * @return int
	 */
	public function wsfwp_check_order_meta_for_userid( $user_id, $order_id ) {
		$wallet_userid = '';
		$order = wc_get_order( $order_id );
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			$wallet_userid = $order->get_meta( 'wps_wsfwp_user_id', true );
		} else {
			$wallet_userid = get_post_meta( $order_id, 'wps_wsfwp_user_id', true );
		}
		return $wallet_userid;
	}

	/**
	 * Check whether order has recharge_reason as meta key
	 *
	 * @param int    $order_id order as id.
	 * @param string $recharge_reason as reason for recharge.
	 * @return string
	 */
	public function wsfwp_check_order_meta_for_recharge_reason( $order_id, $recharge_reason = '' ) {
		$recharge_reason = '';
		$order = wc_get_order( $order_id );
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			$recharge_reason = $order->get_meta( 'wps_wsfwp_recharge_reason', true );
		} else {
			$recharge_reason = get_post_meta( $order_id, 'wps_wsfwp_recharge_reason', true );
		}
		return $recharge_reason;
	}

	/**
	 * Show minimum and maximum value for wallet recharge.
	 *
	 * @return array
	 */
	public function min_max_value_for_wallet_recharge() {
		$wallet_recharge_amount['min_value'] = '';
		$wallet_recharge_amount['max_value'] = '';
		if ( get_option( 'wps_wsfwp_wallet_recharge_restriction_enable', '' ) == 'on' ) {
			$min_wallet_recharge_amount = get_option( 'wsfwp_min_wallet_recharge_amount', 0 );
			$max_wallet_recharge_amount = get_option( 'wsfwp_max_wallet_recharge_amount', '' );
			$wallet_recharge_amount              = array();
			$wallet_recharge_amount['min_value'] = $min_wallet_recharge_amount;
			$wallet_recharge_amount['max_value'] = $max_wallet_recharge_amount;

		}

		return $wallet_recharge_amount;
	}

	/**
	 * Remove custom data added to cart item during removal of cart item.
	 *
	 * @param  string $removed_cart_item_key removed cart item key.
	 * @return void
	 */
	public function wps_wss_remove_value_from_session( $removed_cart_item_key ) {
		$cart = WC()->cart->get_cart();
		foreach ( $cart as $key => $values ) {
			if ( array_key_exists( 'user_id', $values ) ) {
				if ( $values['user_id'] == $removed_cart_item_key ) {
					unset( WC()->cart->cart_contents[ $key ] );
				}
			}
			if ( array_key_exists( 'recharge_reason', $values ) ) {
				if ( $values['recharge_reason'] == $removed_cart_item_key ) {
					unset( WC()->cart->cart_contents[ $key ] );
				}
			}
		}
	}

	/**
	 * Show withdrawal message in widthdrawal request form.
	 *
	 * @return string
	 */
	public function wps_wsfwp_show_withdrawal_message() {
		$show_withdrawal_message = get_option( 'wsfwp_withdrawal_page_message', '' );
		return $show_withdrawal_message;
	}

	/**
	 * Add invitation link if user does not exist.
	 *
	 * @return string
	 */
	public function wps_wsfwp_add_invitation_link_message() {
		$invitation_link = '<a href="" id="wps_wsfwp_open_invitation_box" >' . esc_html__( 'Invite him/her to get the wallet amount', 'wallet-system-for-woocommerce-pro' ) . '</a>';
		return $invitation_link;
	}

	/**
	 * Show message after invitation link is send to user.
	 *
	 * @param string $wpg_message inviatation message.
	 * @param string $type message type.
	 * @return void
	 */
	public function show_message_on_mail_send( $wpg_message, $type = 'error' ) {
		$wpg_notice = "<div class='woocommerce'><p class='" . esc_attr( $type ) . "'>" . $wpg_message . '</p></div>';
		echo wp_kses_post( $wpg_notice );
	}

	/**
	 * Function for transfer fee.
	 *
	 * @return void
	 */
	public function wps_wsfwp_show_wallet_transfer_fee_html() {

		$wps_wsfw_wallet_action_transfer_enable = get_option( 'wps_wsfwp_wallet_action_transfer_enable' );
		$wps_wsfw_wallet_transfer_fee_amount = get_option( 'wps_wsfwp_wallet_transfer_fee_amount' );
		$wps_wsfw_cashback_transfer_fee_type = get_option( 'wps_wsfwp_cashback_transfer_fee_type' );
		$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );
		$wps_wsfw_percent_sybmol = '';
		if ( 'percent' == $wps_wsfw_cashback_transfer_fee_type ) {
			$wps_wsfw_percent_sybmol = '%';
		}
		if ( 'on' == $wps_wsfw_wallet_action_transfer_enable && $wps_wsfw_wallet_transfer_fee_amount > 0 ) {
			?>
			<p class="wps-wallet-field-container form-row form-row-wide">
			<label for="wps_wallet_transfer_fee"><?php echo esc_html__( 'Transfer Fee (', 'wallet-system-for-woocommerce-pro' ) . esc_html( get_woocommerce_currency_symbol( $current_currency ) ) . ')'; ?></label>
				<span style='color:red'><?php esc_html_e( 'Transfer Fee will be Deduct From Amount Value', 'wallet-system-for-woocommerce-pro' ); ?></span>
				<input type="text" step="0.01" min="0" data-max="<?php echo esc_attr( $wps_wsfw_wallet_transfer_fee_amount ); ?>" id="wps_wallet_transfer_fee" name="wps_wallet_transfer_fee" required="" readonly="readonly" value= "<?php echo esc_attr( $wps_wsfw_wallet_transfer_fee_amount . $wps_wsfw_percent_sybmol ); ?>">
			</p>
			<?php
		}
	}

	/**
	 * Function for wallet withdrawal fees.
	 *
	 * @return void
	 */
	public function wps_wsfwp_show_wallet_withdrawal_fee_html() {

		$wps_wsfw_wallet_action_withdrawal_enable = get_option( 'wps_wsfwp_wallet_action_withdrawal_enable' );
		$wps_wsfw_wallet_withdrawal_fee_amount = get_option( 'wps_wsfwp_wallet_withdrawal_fee_amount' );
		$wps_wsfw_cashback_withdrawal_fee_type = get_option( 'wps_wsfwp_cashback_withdrawal_fee_type' );
		$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );
		$wps_wsfw_withdrawal_percent_sybmol = '';
		if ( 'percent' == $wps_wsfw_cashback_withdrawal_fee_type ) {
			$wps_wsfw_withdrawal_percent_sybmol = '%';
		}
		if ( 'on' == $wps_wsfw_wallet_action_withdrawal_enable && $wps_wsfw_wallet_withdrawal_fee_amount > 0 ) {
			?>
			<p class="wps-wallet-field-container form-row form-row-wide">
				<label for="wps_wallet_withdrawal_fee"><?php echo esc_html__( 'Withdrawal Fee (', 'wallet-system-for-woocommerce-pro' ) . esc_html( get_woocommerce_currency_symbol( $current_currency ) ) . ')'; ?></label>
				<span style='color:red'><?php esc_html_e( 'Withdrawal Fee will be Deduct From Amount Value', 'wallet-system-for-woocommerce-pro' ); ?></span>
				<input type="text" step="0.01" min="0" data-max="<?php echo esc_attr( $wps_wsfw_wallet_withdrawal_fee_amount ); ?>" id="wps_wallet_withdrawal_fee" name="wps_wallet_withdrawal_fee" required="" readonly="readonly" value= "<?php echo esc_attr( $wps_wsfw_wallet_withdrawal_fee_amount . $wps_wsfw_withdrawal_percent_sybmol ); ?>">
			</p>
			<?php
		}
	}

	/**
	 * Add popup box in wallet transfer page for sending inviation link to user.
	 *
	 * @param string $content default content.
	 * @param int    $user_id user id.
	 * @param string $email user email id.
	 * @param int    $transfer_amount transfer amount.
	 * @return void
	 */
	public function wps_wsfwp_show_additional_content( $content, $user_id, $email, $transfer_amount ) {
		?>
			<div class="wps_wallet-edit--popupwrap" id="wps_wsfwp_show_inviatation_link_form" >
				<div class="wps_wallet-edit-popup">
					<p><span id="close_wallet_form"><img src="<?php echo esc_url( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_URL ); ?>admin/image/cancel.svg"></span></p>
					<form method="post">
						<div class="wps_wallet-edit-popup-content">
							<div class="wps_wallet-edit-popup-amount">
								<div class="wps_wallet-edit-popup-control">
									<label for="wps_wallet_transfer_note"><?php esc_html_e( 'To(Name)', 'wallet-system-for-woocommerce-pro' ); ?></label>
									<input type="text" id="wps_wallet_username"  name="wps_wallet_username"  >
								</div>
							</div>
						</div>
						<div class="wps_wallet-edit-popup-content">
							<div class="wps_wallet-edit-popup-amount">
								<div class="wps_wallet-edit-popup-control">
									<label for="wps_wallet_transfer_note"><?php esc_html_e( 'Your Message', 'wallet-system-for-woocommerce-pro' ); ?></label>
									<textarea class="wps_wallet-textarea" name="wps_wallet_transfer_note"></textarea>
								</div>
							</div>
						</div>
						<div class="wps_wallet-edit-popup-btn">
							<input type="hidden" id="wallet_transfer_user_id" name="wallet_transfer_user_id" value="<?php echo esc_attr( $user_id ); ?>" />
							<input type="hidden" id="wps_wallet_user_email" name="wps_wallet_user_email" value="<?php echo esc_attr( $email ); ?>" />
							<input type="hidden" id="wps_transfer_amount" name="wps_transfer_amount" value="<?php echo esc_attr( $transfer_amount ); ?>" />
							<input type="hidden" name="wsfwp_send_invitation_link" value="<?php echo esc_html( wp_create_nonce( 'wsfwp-send-invitation-link' ) ); ?>" />
							<input type="submit" name="send_invitation" class="wps-btn wps-btn-popup wps-btn__filled" value="<?php esc_attr_e( 'Send Invitation', 'wallet-system-for-woocommerce-pro' ); ?>">
						</div>
					</form>
				</div>
			</div>
		<?php
	}

	/**
	 * Include qrcode images template to show qr code image.
	 *
	 * @param string $template template path.
	 * @return string
	 */
	public function wps_wsfwp_show_qrcode_image( $template ) {
		$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
		if ( ! empty( $wallet_page_id ) ) {
			if ( is_page( $wallet_page_id ) && ( get_query_var( 'wps-qrcode-image', false ) !== false ) ) {
				$new_template = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-wallet-qrcode-image.php';
				if ( file_exists( $new_template ) ) {
					return $new_template;
				}
			}
		}
		return $template;
	}

	/**
	 * Includes topup page to transfer amount or recharge wallet created through qr code.
	 *
	 * @param string $content page content.
	 * @return string
	 */
	public function content_for_my_wallet_page_wallet_transfer( $content ) {
		$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
		if ( ! empty( $wallet_page_id ) ) {
			if ( is_page( $wallet_page_id ) && ( get_query_var( 'wps-wallet-topup', false ) !== false ) ) {
				include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-wallet-qrcode-topup.php';
			}
		}
		return $content;
	}

	/**
	 * Send mail to user for registration.
	 *
	 * @param string $to user email address.
	 * @param string $subject subject for mail.
	 * @param string $mail_message message for mail.
	 * @param string $headers data to be send in header.
	 * @return boolean
	 */
	public function send_mail_to_user_for_registration( $to, $subject, $mail_message, $headers ) {
		// Here put your Validation and send mail.
		$send_mail = wp_mail( $to, $subject, $mail_message, $headers );
		return $send_mail;
	}

	/**
	 * Add wallet transferred amount to wallet after user registration.
	 *
	 * @param int $user_id user id.
	 * @return void
	 */
	public function wps_save_wallet_amount_after_registration( $user_id ) {
		if ( ! empty( $_GET['transfer_amount'] ) && ! empty( $_GET['user_id'] ) ) {
			$currency        = get_woocommerce_currency();
			$transfer_amount = sanitize_text_field( wp_unslash( $_GET['transfer_amount'] ) );
			$another_user_id = sanitize_text_field( wp_unslash( $_GET['user_id'] ) );
			$wallet_bal      = get_user_meta( $another_user_id, 'wps_wallet', true );
			$returnid        = update_user_meta( $user_id, 'wps_wallet', $transfer_amount );
			$transfer_note   = ! empty( $_GET['transfer_note'] ) ? sanitize_text_field( wp_unslash( $_GET['transfer_note'] ) ) : '';
			if ( $returnid ) {
				$wallet_payment_gateway = new Wallet_System_For_Woocommerce();
				$send_email_enable      = get_option( 'wps_wsfw_enable_email_notification_for_wallet_update', '' );
				// first user.
				$user1 = get_user_by( 'id', $user_id );
				$name1 = $user1->first_name . ' ' . $user1->last_name;

				$user2 = get_user_by( 'id', $another_user_id );
				$name2 = $user2->first_name . ' ' . $user2->last_name;
				if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {
					$balance   = $currency . ' ' . $transfer_amount;
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
					'amount'           => $transfer_amount,
					'currency'         => $currency,
					'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
					'transaction_type' => $transaction_type,
					'transaction_type_1' => 'credit',
					'order_id'         => '',
					'note'             => $transfer_note,

				);

				$wallet_payment_gateway->insert_transaction_data_in_table( $wallet_transfer_data );

				$wallet_bal -= $transfer_amount;
				$update_user = update_user_meta( $another_user_id, 'wps_wallet', abs( $wallet_bal ) );
				if ( $update_user ) {
					$balance   = $currency . ' ' . $transfer_amount;
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
						'user_id'          => $another_user_id,
						'amount'           => $transfer_amount,
						'currency'         => $currency,
						'payment_method'   => __( 'Wallet Transfer', 'wallet-system-for-woocommerce-pro' ),
						'transaction_type' => $transaction_type,
						'transaction_type_1' => 'debit',
						'order_id'         => '',
						'note'             => $transfer_note,

					);

					$wallet_payment_gateway->insert_transaction_data_in_table( $transaction_data );

				}
			}
		}
	}

	/**
	 * Show message on form submit
	 *
	 * @param string $wpg_message message to be shown on form submission.
	 * @param string $type error type.
	 * @return void
	 */
	public function show_message_on_form_submit( $wpg_message, $type = 'error' ) {
		$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
		echo wp_kses_post( $wpg_notice );
	}

	/**
	 * On woocommerce init starts woocmmerce session.
	 *
	 * @return void
	 */
	public function wps_wsfwp_set_woocoomerce_session() {

		if ( ! empty( WC()->session ) && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Return QR Code from a string
	 *
	 * @param string $str qr code path.
	 * @param string $starting_word starting word.
	 * @param string $ending_word ending word.
	 * @return string
	 */
	public function string_between_two_string( $str, $starting_word, $ending_word ) {
		$arr = explode( $starting_word, $str );
		if ( isset( $arr[1] ) ) {
			$arr = explode( $ending_word, $arr[1] );
			return $arr[0];
		}
		return '';
	}
	/**
	 * Returns converted amount for recharge.
	 *
	 * @param float $recharge_amount recharge amount.
	 * @return float
	 */
	public function wps_wsfwp_convert_price_on_cart( $recharge_amount ) {
		if ( function_exists( 'wps_mmcsfw_get_currenct_currency' ) ) {
			$current_currency = wps_mmcsfw_get_currenct_currency();
			if ( function_exists( 'wps_mmcsfw_admin_fetch_currency_rates_to_base_currency' ) ) {
				$base_amount               = wps_mmcsfw_admin_fetch_currency_rates_to_base_currency( $current_currency, $recharge_amount );
				$converted_recharge_amount = apply_filters( 'wps_wsfw_show_converted_price', $base_amount );
				return $converted_recharge_amount;

			}
		} else {
			return $recharge_amount;
		}
	}

	/**
	 * Returns the current currency.
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
	 * Send invitation link to user during wallet transfer.
	 *
	 * @return void
	 */
	public function wps_wsfwp_send_mail_to_user() {
		$wsfwp_send_invitation_link = ! empty( $_POST['wsfwp_send_invitation_link'] ) ? sanitize_text_field( wp_unslash( $_POST['wsfwp_send_invitation_link'] ) ) : '';
		if ( wp_verify_nonce( $wsfwp_send_invitation_link, 'wsfwp-send-invitation-link' ) ) {
			if ( isset( $_POST['send_invitation'] ) && ! empty( $_POST['send_invitation'] ) ) {
				unset( $_POST['send_invitation'] );
				if ( ! empty( $_POST['wallet_transfer_user_id'] ) ) {
					$user_id = sanitize_text_field( wp_unslash( $_POST['wallet_transfer_user_id'] ) );
				}
				$username        = ! empty( $_POST['wps_wallet_username'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_username'] ) ) : '';
				$useremail       = ! empty( $_POST['wps_wallet_user_email'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_user_email'] ) ) : '';
				$transfer_note   = ! empty( $_POST['wps_wallet_transfer_note'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_wallet_transfer_note'] ) ) : '';
				$transfer_amount = ! empty( $_POST['wps_transfer_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['wps_transfer_amount'] ) ) : '';

				$registration_url = add_query_arg(
					array(
						'transfer_amount' => $transfer_amount,
						'user_id'         => $user_id,
						'transfer_note'   => $transfer_note,
					),
					wc_get_page_permalink( 'myaccount' )
				);
				$mail_text_content       = __( 'Click the link for registering your account on ', 'wallet-system-for-woocommerce-pro' );
				$mail_text_content       .= get_bloginfo( 'name' );
				$mail_text_content       .= __( ' and you will get some amount as wallet balance.<br/>', 'wallet-system-for-woocommerce-pro' );
				$mail_text_content       .= ( '<a href="' );
				$mail_text_content       .= esc_url( $registration_url );
				$mail_text_content       .= __( '" style="background:#2196f3;margin-top:15px;text-decoration:none;padding:6px 10px;color:#fff;display:inline-block;" >Create Account</a>', 'wallet-system-for-woocommerce-pro' );
				$mail_text        = __( 'Hello, ', 'wallet-system-for-woocommerce-pro' ) . $username . "\r\n";
				$mail_text       .= $mail_text_content;
				$to               = $useremail;
				$from             = get_option( 'admin_email' );
				$subject          = 'Invitations to join the Wallet System';
				$headers          = 'MIME-Version: 1.0' . "\r\n";
				$headers          = 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
				$headers         .= 'From: ' . $from . "\r\n" .
					'Reply-To: ' . $to . "\r\n";
				$send_mail = $this->send_mail_to_user_for_registration( $to, $subject, $mail_text, $headers );
				if ( $send_mail ) {
					$this->show_message_on_mail_send( esc_html__( 'Registration mail is send to ', 'wallet-system-for-woocommerce-pro' ) . $useremail . esc_html__( ' successfully', 'wallet-system-for-woocommerce-pro' ), 'woocommerce-message' );
				} else {
					$this->show_message_on_mail_send( esc_html__( 'Registration mail is not send to ', 'wallet-system-for-woocommerce-pro' ) . $useremail, 'woocommerce-error' );
				}
			}
		}
	}

	/**
	 * Wallet Coupon Redeem function
	 *
	 * @since    1.0.6
	 * @param [type] $wallet_tabs is the current wallet tab.
	 * @param [type] $org_url is the url of org plugin.
	 * @return mixed
	 */
	public function wps_wsfwp_get_wallet_coupon_section( $wallet_tabs, $org_url ) {

		$transaction_url        = wc_get_endpoint_url( 'wps-wallet', 'wallet-coupon' );

		$wallet_tabs['wallet_coupon'] = array(
			'title'     => esc_html__( 'Wallet Coupon Redeem', 'wallet-system-for-woocommerce-pro' ),
			'url'       => $transaction_url,
			'className' => 'wps_wallet_coupon_tab',
			'icon'      => '<path fill-rule="evenodd" clip-rule="evenodd" d="M6.07141 14.9576C6.07141 12.4169 8.13112 10.3571 10.6719 10.3571H30.0424C32.5831 10.3571 34.6428 12.4169 34.6428 14.9576V25.2126C34.6428 27.7534 32.5831 29.8131 30.0424 29.8131H10.6719C8.13112 29.8131 6.07141 27.7534 6.07141 25.2126V14.9576ZM10.6719 13.1013C9.64667 13.1013 8.81556 13.9324 8.81556 14.9576V25.2126C8.81556 26.2378 9.64667 27.0689 10.6719 27.0689H30.0424C31.0676 27.0689 31.8987 26.2378 31.8987 25.2126V14.9576C31.8987 13.9324 31.0676 13.1013 30.0424 13.1013H10.6719Z" fill="black"/>
							<path fill-rule="evenodd" clip-rule="evenodd" d="M24.5541 16.5719C25.089 16.5719 25.5226 17.0055 25.5226 17.5404L25.5226 23.6744C25.5226 24.2093 25.089 24.6429 24.5541 24.6429C24.0192 24.6429 23.5855 24.2093 23.5855 23.6744L23.5855 17.5404C23.5855 17.0055 24.0192 16.5719 24.5541 16.5719Z" fill="#483DE0"/>',
			'file-path' => $org_url . 'public/partials/wallet-system-for-woocommerce-wallet-coupon.php',
		);

		return $wallet_tabs;
	}

	/**
	 * Walet fund request section function
	 *
	 * @param array $wallet_tabs as wallet tabs.
	 * @param array $org_url as url.
	 * @return array
	 */
	public function wps_wsfwp_get_wallet_fund_request_section( $wallet_tabs, $org_url ) {

		$fund_request_url        = wc_get_endpoint_url( 'wps-wallet', 'wallet-fund-request' );

		$wallet_tabs['wallet_fund_request'] = array(
			'title'     => esc_html__( 'Wallet Fund Request', 'wallet-system-for-woocommerce-pro' ),
			'url'       => $fund_request_url,
			'className' => 'wps_wallet_fund_request_tab',
			'icon'      => '<path fill-rule="evenodd" clip-rule="evenodd" d="M6.07141 14.9576C6.07141 12.4169 8.13112 10.3571 10.6719 10.3571H30.0424C32.5831 10.3571 34.6428 12.4169 34.6428 14.9576V25.2126C34.6428 27.7534 32.5831 29.8131 30.0424 29.8131H10.6719C8.13112 29.8131 6.07141 27.7534 6.07141 25.2126V14.9576ZM10.6719 13.1013C9.64667 13.1013 8.81556 13.9324 8.81556 14.9576V25.2126C8.81556 26.2378 9.64667 27.0689 10.6719 27.0689H30.0424C31.0676 27.0689 31.8987 26.2378 31.8987 25.2126V14.9576C31.8987 13.9324 31.0676 13.1013 30.0424 13.1013H10.6719Z" fill="black"/>
							<path fill-rule="evenodd" clip-rule="evenodd" d="M24.5541 16.5719C25.089 16.5719 25.5226 17.0055 25.5226 17.5404L25.5226 23.6744C25.5226 24.2093 25.089 24.6429 24.5541 24.6429C24.0192 24.6429 23.5855 24.2093 23.5855 23.6744L23.5855 17.5404C23.5855 17.0055 24.0192 16.5719 24.5541 16.5719Z" fill="#483DE0"/>',
			'file-path' => $org_url . 'public/partials/wallet-system-for-woocommerce-wallet-fund-request.php',
		);

		return $wallet_tabs;
	}

	/**
	 * UResrtict User in Pro functionality.
	 *
	 * @param [type] $user_id is the current user id.
	 * @return mixed
	 */
	public function wps_wsfwp_user_restrict_pro_check( $user_id ) {
		$is_user_restricted = '';

		$wps_wallet_restrict_topup = get_user_meta( $user_id, 'wps_wallet_restrict_message_to_user', true );
		return $wps_wallet_restrict_topup;
	}

	/**
	 * Check pro plugin.
	 *
	 * @param [type] $is_pro_plugin is the check for pro.
	 * @return bool
	 */
	public function wps_wsfwp_pro_plugin_check_callback( $is_pro_plugin ) {
		$is_pro_plugin = true;

		return $is_pro_plugin;
	}



	/**
	 * Restrict Topup for Restriction functionality.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_topup( $user_id ) {
		$wps_wallet_restrict_topup = get_user_meta( $user_id, 'wps_wallet_restrict_topup', true );
		return $wps_wallet_restrict_topup;
	}



	/**
	 * Restrict Topup for Restriction functionality.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wps_wallet_restrict_message_for( $user_id ) {
		$wps_wallet_restrict_message_for = get_user_meta( $user_id, 'wps_wallet_restrict_message_for', true );
		return $wps_wallet_restrict_message_for;
	}

	/**
	 * Restrict message for Restriction functionality.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wps_wallet_restrict_message_to_user( $user_id ) {
		$wps_wallet_restrict_message_to_user = get_user_meta( $user_id, 'wps_wallet_restrict_message_to_user', true );
		return $wps_wallet_restrict_message_to_user;
	}




	/**
	 * Restrict Trasnfer for Restriction functionality.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_transfer( $user_id ) {
		$wps_wallet_restrict_transfer = get_user_meta( $user_id, 'wps_wallet_restrict_transfer', true );
		return $wps_wallet_restrict_transfer;
	}
	/**
	 * Restriction withfrawal for Restriction.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_withdrawal( $user_id ) {
		$wps_wallet_restrict_withdrawal = get_user_meta( $user_id, 'wps_wallet_restrict_withdrawal', true );
		return $wps_wallet_restrict_withdrawal;
	}
	/**
	 * Restriction coupon for Restriction.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_coupon( $user_id ) {
		$wps_wallet_restrict_coupon = get_user_meta( $user_id, 'wps_wallet_restrict_coupon', true );
		return $wps_wallet_restrict_coupon;
	}
	/**
	 * Restriction coupon for Restriction.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_fund_request( $user_id ) {
		$wps_wallet_restrict_coupon = get_user_meta( $user_id, 'wps_wallet_restrict_fund_request', true );
		return $wps_wallet_restrict_coupon;
	}
	/**
	 * Restriction tranaction for Restriction.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_transaction( $user_id ) {
		$wps_wallet_restrict_transactions = get_user_meta( $user_id, 'wps_wallet_restrict_transactions', true );
		return $wps_wallet_restrict_transactions;
	}


	/**
	 * Restriction tranaction for Restriction.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_referral( $user_id ) {
		$wps_wallet_restrict_referral = get_user_meta( $user_id, 'wps_wallet_restrict_referral', true );
		return $wps_wallet_restrict_referral;
	}


	/**
	 * Restriction tranaction for Restriction.
	 *
	 * @param int $user_id user id.
	 * @return string
	 */
	public function wps_wsfwp_wallet_restrict_qrcode( $user_id ) {
		$wps_wallet_restrict_qrcode = get_user_meta( $user_id, 'wps_wallet_restrict_qrcode', true );
		return $wps_wallet_restrict_qrcode;
	}


	/**
	 * Add QR code in shortcode.
	 *
	 * @return void
	 */
	public function wps_wsfwp_wallet_display_wrapper_for_q_r_def() {

		include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-public-display.php';
	}



	/**
	 * Add subscription code in wallet recharge.
	 *
	 * @return void
	 */
	public function wsfw_make_wallet_recharge_subscription_body() {
		$wps_wsfw_wallet_action_recharege_for_user_enable = get_option( 'wps_wsfw_wallet_action_recharege_for_user_enable' );
		$wps_wsfw_wallet_action_auto_topup_enable = get_option( 'wps_wsfw_wallet_action_auto_topup_enable' );
		if ( 'on' == $wps_wsfw_wallet_action_auto_topup_enable ) {

			if ( 'on' == $wps_wsfw_wallet_action_recharege_for_user_enable ) {
				?>
				<label for="wps_wallet_recharge_as_subscription"> <?php esc_html_e( 'Make your Recharge as subscription :', 'wallet-system-for-woocommerce-pro' ); ?> </label>
			<p class="wps-wallet wps_user_subscription">
		 <input type="checkbox" id="wps_wallet_recharge_as_subscription" name="wps_wallet_recharge_as_subscription" > <span> 
				<?php
				$wps_sfw_subscription_interval = get_option( 'wps_sfw_subscription_interval', '' );
				$currency = get_woocommerce_currency();
				$current_currency = get_woocommerce_currency_symbol( $currency );
				$text = '(For example:- 10 ' . $current_currency . '/' . $wps_sfw_subscription_interval . ' for 12 ' . $wps_sfw_subscription_interval . ')';
				echo wp_kses_post( sprintf( ' %s', $text ) );
				?>
		 </span>
		</p>
				<?php
			}
		}
	}

	/**
	 * User selected subscription.
	 *
	 * @param int $is_user_subscription user id.
	 * @return string
	 */
	public function wps_wsfwp_get_user_choice_of_subscription( $is_user_subscription ) {
		$wps_wsfw_wallet_action_recharege_for_user_enable = get_option( 'wps_wsfw_wallet_action_recharege_for_user_enable' );

		$wps_wsfw_wallet_action_auto_topup_enable = get_option( 'wps_wsfw_wallet_action_auto_topup_enable' );
		if ( 'on' == $wps_wsfw_wallet_action_auto_topup_enable ) {
			if ( 'on' == $wps_wsfw_wallet_action_recharege_for_user_enable ) {
				$is_user_subscription = true;
			}
		}

		return $is_user_subscription;
	}

	/**
	 * Enque script for block.
	 *
	 * @return void
	 */
	public function wsfwp_wps_enqueue_script_block_eheckout() {

		$restrict_msg = $this->wps_wsfwp_add_wallet_cashback_message_restriction_block_checkout();
		$negative_balance = $this->wps_wsfwp_limit_negative_balance_block_checkout();
		$restrict_gatewaay_enabled  = get_option( 'wps_wsfw_Gateway_Restriction_message_checkout' );
		wp_register_script( 'wallet-system-for-woocommerce-pro-block-checkout', WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'public/src/js/wallet-system-for-woocommerce-pro-block-checkout.js', array( 'jquery' ), $this->version, false );
		wp_localize_script(
			'wallet-system-for-woocommerce-pro-block-checkout',
			'wsfwp_public_param_block',
			array(
				'ajaxurl'                   => admin_url( 'admin-ajax.php' ),
				'nonce'                     => wp_create_nonce( 'ajax-nonce' ),
				'wsfw_restriction_msg_checkout'          => $restrict_msg,
				'gateway_enaled'   => $restrict_gatewaay_enabled,
				'wsfwp_negative_balance_msg'   => $negative_balance,
			)
		);
		wp_enqueue_script( 'wallet-system-for-woocommerce-pro-block-checkout' );
	}

	/**
	 * Cashback message restriction.
	 *
	 * @return string
	 */
	public function wps_wsfwp_add_wallet_cashback_message_restriction_block_checkout() {

		$restrict_gatewaay_enabled  = get_option( 'wps_wsfw_Gateway_Restriction_message_checkout' );
		$restrict_gatewaay  = get_option( 'wps_wsfw_multiselect_cashback_restrict' );
		$all_gateway = WC()->payment_gateways()->payment_gateways();
		$gateway_restriction = '';
		if ( ! empty( $restrict_gatewaay ) ) {
			$count = 0;
			if ( 'on' == $restrict_gatewaay_enabled ) {

				$gateway_restriction = esc_html__( 'Cashback is not applicable for ', 'wallet-system-for-woocommerce-pro' );
				foreach ( $restrict_gatewaay as $key => $value ) {
					if ( 'yes' == $all_gateway[ $value ]->enabled ) {
						if ( 0 == $count ) {
							$gateway_restriction .= esc_html( $all_gateway[ $value ]->title );
						} else {
							$gateway_restriction .= ', ' . esc_html( $all_gateway[ $value ]->title );
						}
						$count++;
					}
				}
			}
		}
		return $gateway_restriction;
	}

	/**
	 * Cashback message restriction.
	 *
	 * @return void
	 */
	public function wps_wsfwp_add_wallet_cashback_message_restriction() {

		$restrict_gatewaay_enabled  = get_option( 'wps_wsfw_Gateway_Restriction_message_checkout' );
		$restrict_gatewaay  = get_option( 'wps_wsfw_multiselect_cashback_restrict' );
		$all_gateway = WC()->payment_gateways()->payment_gateways();
		if ( ! empty( $restrict_gatewaay ) ) {
			$count = 0;
			if ( 'on' == $restrict_gatewaay_enabled ) {
				?>
					<div class="wps_restrict_gateway_message">
				<?php
				echo esc_html__( 'Cashback is not applicable for ', 'wallet-system-for-woocommerce-pro' );
				foreach ( $restrict_gatewaay as $key => $value ) {
					if ( 'yes' == $all_gateway[ $value ]->enabled ) {
						if ( 0 == $count ) {
							echo esc_html( $all_gateway[ $value ]->title );
						} else {
							echo ', ' . esc_html( $all_gateway[ $value ]->title );
						}
						$count++;
					}
				}
				?>
					</div>
				<?php
			}
		}
	}



	/**
	 * Set negative balance message.
	 *
	 * @return void
	 */
	public function wps_wsfwp_limit_negative_balance() {
		$customer_id = get_current_user_id();
		if ( $customer_id > 0 ) {
			$wallet_amount  = get_user_meta( $customer_id, 'wps_wallet', true );
			$wallet_amount  = empty( $wallet_amount ) ? 0 : $wallet_amount;
			$order_number = get_user_meta( $customer_id, 'wsfw_enable_wallet_negative_balance_limit_order', true );
			$order_limit = get_option( 'wsfw_enable_wallet_negative_balance_limit_order' );

			$wallet_amount  = apply_filters( 'wps_wsfw_show_converted_price', $wallet_amount );
			if ( 'on' == get_option( 'wsfw_enable_wallet_negative_balance' ) ) {
				$limit = get_option( 'wsfw_enable_wallet_negative_balance_limit' );

				$amount_left = abs( intval( $wallet_amount ) ) - intval( $limit );

				if ( $wallet_amount > 0 ) {
					$amount_left = $limit;
				}
				if ( ! empty( $amount_left ) ) {
					echo '<div class="payment_method_wallet"> <p> You can use Pay Later balance upto ' . wp_kses_post( wc_price( abs( floatval( $amount_left ) ) ) ) . '  </p></div>';
				}
			}
			do_action( 'wps_wsfw_for_limit_negative_balance_after' );
		}
	}


	/**
	 * Set negative balance message for block checkout.
	 *
	 * @return string
	 */
	public function wps_wsfwp_limit_negative_balance_block_checkout() {
		$customer_id = get_current_user_id();
		$data = '';
		if ( $customer_id > 0 ) {
			$wallet_amount  = get_user_meta( $customer_id, 'wps_wallet', true );
			$wallet_amount  = empty( $wallet_amount ) ? 0 : $wallet_amount;

			$wallet_amount  = apply_filters( 'wps_wsfw_show_converted_price', $wallet_amount );
			if ( 'on' == get_option( 'wsfw_enable_wallet_negative_balance' ) ) {
				$limit = get_option( 'wsfw_enable_wallet_negative_balance_limit' );

				$amount_left = abs( intval( $wallet_amount ) ) - intval( $limit );

				if ( $wallet_amount > 0 ) {
					$amount_left = $limit;
				}
				$order_number = get_user_meta( $customer_id, 'wsfw_enable_wallet_negative_balance_limit_order', true );
				$order_limit = get_option( 'wsfw_enable_wallet_negative_balance_limit_order' );

				if ( intval( $order_number ) >= intval( $order_limit ) ) {
					if ( ! empty( $amount_left ) ) {
						$data = '<div class="payment_method_wallet"> <p> You can use Pay Later balance upto ' . wp_kses_post( wc_price( abs( floatval( $amount_left ) ) ) ) . '  </p></div>';

					}
				}
			}
			do_action( 'wps_wsfw_for_limit_negative_balance_after' );
			return $data;
		}
	}


	/**
	 * This function is used to apply user restriction on new registration.
	 *
	 * @param int $customer_id customer id.
	 * @return void
	 */
	public function wps_wsfw_apply_user_restriction_new_user( $customer_id ) {

		$wps_wallet_restrict_every_customer = get_option( 'wps_wallet_restrict_every_customer' );

		if ( 'on' == $wps_wallet_restrict_every_customer ) {
			$wps_wallet_restrict_topup = get_option( 'wps_wallet_restrict_topup' );
			$wps_wallet_restrict_transfer = get_option( 'wps_wallet_restrict_transfer' );
			$wps_wallet_restrict_withdrawal = get_option( 'wps_wallet_restrict_withdrawal' );
			$wps_wallet_restrict_coupon = get_option( 'wps_wallet_restrict_coupon' );
			$wps_wallet_restrict_transactions = get_option( 'wps_wallet_restrict_transactions' );
			$wps_wallet_restrict_referral = get_option( 'wps_wallet_restrict_referral' );
			$wps_wallet_restrict_qrcode = get_option( 'wps_wallet_restrict_qrcode' );
			$wps_wallet_restrict_message_for = get_option( 'wps_wallet_restrict_message_for' );
			$wps_wallet_restrict_message_to_user = get_option( 'wps_wallet_restrict_message_to_user' );
			$wps_wallet_restrict_wallet_gateway = get_option( 'wps_wallet_restrict_wallet_gateway' );

			update_user_meta( $customer_id, 'wps_wallet_restrict_topup', $wps_wallet_restrict_topup );
			update_user_meta( $customer_id, 'wps_wallet_restrict_transfer', $wps_wallet_restrict_transfer );
			update_user_meta( $customer_id, 'wps_wallet_restrict_withdrawal', $wps_wallet_restrict_withdrawal );
			update_user_meta( $customer_id, 'wps_wallet_restrict_coupon', $wps_wallet_restrict_coupon );
			update_user_meta( $customer_id, 'wps_wallet_restrict_transactions', $wps_wallet_restrict_transactions );
			update_user_meta( $customer_id, 'wps_wallet_restrict_referral', $wps_wallet_restrict_referral );
			update_user_meta( $customer_id, 'wps_wallet_restrict_qrcode', $wps_wallet_restrict_qrcode );
			update_user_meta( $customer_id, 'wps_wallet_restrict_message_to_user', $wps_wallet_restrict_message_to_user );
			update_user_meta( $customer_id, 'wps_wallet_restrict_message_for', $wps_wallet_restrict_message_for );
			update_user_meta( $customer_id, 'wps_wallet_restrict_wallet_gateway', $wps_wallet_restrict_wallet_gateway );

		}
	}

	/**
	 * Fund to inculde new template function.
	 *
	 * @return void
	 */
	public function wsfw_pro_version_wallet_template_file_callback() {
		include_once WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'public/partials/wallet-system-for-woocommerce-pro-wallet-template-display.php';
	}

	/**
	 * Function to add customer css function
	 *
	 * @param array $classes as class.
	 * @return array
	 */
	public function wps_wsfw_add_custom_class_to_my_account_page_and_wallet_endpoint( $classes ) {
		if ( is_account_page() && wc_get_endpoint_url( 'wps-wallet' ) ) {
			// Add your custom class.
			$classes[] = 'wps-body-wallet-class';
		}
		return $classes;
	}

	/**
	 * Wallet Referral function.
	 *
	 * @return void
	 */
	public function wps_wsfw_filter_for_wallet_referral_content() {

		$user_id = get_current_user_id();

		$wps_wsfw_wallet_action_refer_coupon_code_enable = get_option( 'wps_wsfw_wallet_action_refer_coupon_code_enable' );
		$wps_wsfw_wallet_action_referal_coupon_amount = get_option( 'wps_wsfw_wallet_action_referal_coupon_amount' );
		$wps_wsfw_wallet_action_referal_coupon_type = get_option( 'wps_wsfw_wallet_action_referal_coupon_type' );

		if ( 'on' == $wps_wsfw_wallet_action_refer_coupon_code_enable && $wps_wsfw_wallet_action_referal_coupon_amount && $wps_wsfw_wallet_action_referal_coupon_type ) {
			$coupon_code = wps_wsfw_create_referral_code();
			$final_coupon_code = $this->wps_wsfp_wallet_referral_coupon_code( $user_id );
			if ( $final_coupon_code ) {

				?>
				<div class="card-container">
					<h4><?php echo esc_html__( 'Your Coupon Referral Code is:', 'wallet-system-for-woocommerce-pro' ); ?></h4>
					<input type="hidden" id="wps_wsfw_copy_test" name="custId_test" value="<?php echo esc_html( $final_coupon_code ); ?>" readonly="">
					<div id="wps_notify_user_copy_test">
						<code><?php echo esc_html( $final_coupon_code ); ?></code>

						<button onclick="copycouponcode()" class="wps_tooltip" aria-label="copied">
						<?php esc_html_e( 'Copy', 'wallet-system-for-woocommerce-pro' ); ?><img src="<?php echo esc_url( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_URL ) . 'public/images/copy.png'; ?>">
						</button>
					</div>
					<?php
					if ( 'percent' == $wps_wsfw_wallet_action_referal_coupon_type ) {
						?>
							<div class="wps_wsfwp_coupon_referral_desc"><?php esc_html_e( 'and anyone you refer who buys from using this coupon is entitled to ', 'wallet-system-for-woocommerce-pro' ); ?> <?php echo esc_html( $wps_wsfw_wallet_action_referal_coupon_amount ); ?> <?php esc_html_e( '% off as a discount.', 'wallet-system-for-woocommerce-pro' ); ?></div>
							<?php
					} else {
						?>
							<div class="wps_wsfwp_coupon_referral_desc"><?php esc_html_e( 'and anyone you refer who buys from using this coupon is entitled to  ', 'wallet-system-for-woocommerce-pro' ); ?> <?php echo esc_html( get_woocommerce_currency_symbol() . esc_html( apply_filters( 'wps_wpr_show_conversion_price', $wps_wsfw_wallet_action_referal_coupon_amount ) ) ); ?> <?php esc_html_e( ' off as a discount.', 'wallet-system-for-woocommerce-pro' ); ?></div>
							<?php
					}
					?>
					<span class="wps_tooltiptext_scl" id="myTooltip_referral">
					<img src="<?php echo esc_url( WALLET_SYSTEM_FOR_WOOCOMMERCE_DIR_URL ) . 'public/images/copy.png'; ?>"><?php esc_html_e( 'Copied!', 'wallet-system-for-woocommerce-pro' ); ?>
					</span>
					
				</div>
				<?php

			}
		}
	}

	/**
	 * Wallet referrral coupon code function
	 *
	 * @param int $user_id as user id.
	 * @return array
	 */
	public function wps_wsfp_wallet_referral_coupon_code( $user_id ) {

		global $mycouponnreferal;
		$wps_wsfw_wallet_action_refer_coupon_code_enable = get_option( 'wps_wsfw_wallet_action_refer_coupon_code_enable' );
		$wps_wsfw_wallet_action_referal_coupon_amount = get_option( 'wps_wsfw_wallet_action_referal_coupon_amount' );

		if ( 'on' == $wps_wsfw_wallet_action_refer_coupon_code_enable ) {

			$coupon_code = wps_wsfw_create_referral_code();
			update_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code_amount', $wps_wsfw_wallet_action_referal_coupon_amount );

			if ( empty( get_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code', $mycouponnreferal ) ) ) {
				update_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code', $coupon_code );
			}

			$this->wps_wsfwp_coupon_code( $user_id );
			$coupon_code = ! empty( get_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code', true ) ) ? get_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code', true ) : '';
			return $coupon_code;
		}
	}

	/**
	 * Function to coupon code function
	 *
	 * @param int $user_id as user id.
	 * @return void
	 */
	public function wps_wsfwp_coupon_code( $user_id ) {
		global $new_coupon_id, $coupon_code;

		$wps_wsfw_wallet_action_referal_coupon_type = get_option( 'wps_wsfw_wallet_action_referal_coupon_type' );

		$coupon_code = ! empty( get_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code', true ) ) ? get_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code', true ) : '';
		$amount      = ! empty( get_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code_amount', true ) ) ? get_post_meta( $user_id, 'wps_wsfwp_wallet_referal_code_amount', true ) : '0';

		if ( 'fixed' == $wps_wsfw_wallet_action_referal_coupon_type ) {

			$discount_type = 'fixed_cart';
		} else {
			$discount_type = 'percent';
		}

		$coupon = array(
			'post_title'   => $coupon_code,
			'post_content' => 'Wallet And Referral - User ID#' . $user_id,
			'post_status'  => 'publish',
			'post_excerpt' => 'Wallet And Referral- User ID#' . $user_id,
			'post_author'  => 1,
			'post_type'    => 'shop_coupon',
		);

		if ( ! is_admin() ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		if ( ! post_exists( $coupon_code ) ) {

			$new_coupon_id = wp_insert_post( $coupon );
			$wc_coupon     = new WC_Coupon( $coupon_code );
			$wc_coupon->set_discount_type( $discount_type );

			update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
			update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
			update_post_meta( $new_coupon_id, 'individual_use', 'yes' );
			update_post_meta( $new_coupon_id, 'usage_limit', '' );
			update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
			update_post_meta( $new_coupon_id, 'free_shipping', 'no' );
			update_post_meta( $new_coupon_id, 'refferedby_coupon', $user_id );
		}

		if ( post_exists( $coupon_code ) ) {

			$wc_coupon = new WC_Coupon( $coupon_code );
			$wc_coupon->set_discount_type( $discount_type );
			$existing_coupon_id = $wc_coupon->get_id();

			update_post_meta( $existing_coupon_id, 'discount_type', $discount_type );
			update_post_meta( $existing_coupon_id, 'coupon_amount', $amount );
			update_post_meta( $existing_coupon_id, 'individual_use', 'yes' );
			update_post_meta( $existing_coupon_id, 'usage_limit', '' );
			update_post_meta( $existing_coupon_id, 'apply_before_tax', 'yes' );
			update_post_meta( $existing_coupon_id, 'free_shipping', 'no' );
			update_post_meta( $existing_coupon_id, 'refferedby_coupon', $user_id );
		}
	}

	/**
	 * Function to apply coupon code function
	 *
	 * @param int   $valid as valid.
	 * @param array $coupon as coupon.
	 * @param array $object as cart object.
	 * @return array
	 */
	public function wps_wsfw_woocommerce_apply_product_on_coupon( $valid, $coupon, $object ) {
		global $customer_orders;
		$coupon_id = $coupon->get_id();
		$user_id   = get_current_user_id();
		if ( empty( $user_id ) ) {
			return $valid;
		}
		$coupon_user_id = get_post_meta( $coupon_id, 'refferedby_coupon', true );

		if ( isset( $coupon_id ) && $user_id == $coupon_user_id ) {
			throw new Exception( __( 'Referral code cannot be used by self', 'wallet-system-for-woocommerce-pro' ), 100 );
		}

		$customer_orders = get_posts(
			array(
				'numberposts' => 1,
				'meta_key'    => '_customer_user',
				'meta_value'  => $user_id,
				'post_type'   => 'shop_order',
				'post_status' => wc_get_order_types(),
				'fields'      => 'ids',
			)
		);

		if ( count( $customer_orders ) > 0 && ! empty( $coupon_user_id ) ) {
			throw new Exception( __( 'The coupon code is valid only on the first order', 'wallet-system-for-woocommerce-pro' ), 100 );
		}
		return $valid;
	}

	/**
	 * Function to change coupon code status function
	 *
	 * @param int   $order_id as order id.
	 * @param array $old_status as old status.
	 * @param array $new_status as new status.
	 * @return array
	 */
	public function wps_wsfw_woocommerce_order_status_changed_coupon_refer( $order_id, $old_status, $new_status ) {
		if ( $old_status != $new_status && 'processing' == $new_status ) {
			$wps_wsfw_wallet_action_refer_coupon_code_enable = get_option( 'wps_wsfw_wallet_action_refer_coupon_code_enable' );
			if ( 'on' != $wps_wsfw_wallet_action_refer_coupon_code_enable ) {
				return;
			}

			$order         = wc_get_order( $order_id );
			$order_user_id = $order->get_user_id();
			if ( empty( $order_user_id ) || is_null( $order_user_id ) ) {
				return;
			}
			$user_customer = $order->get_user();

			$user_emaill      = $user_customer->user_email;
			$used_cuopon      = $order->get_coupon_codes();
			$wps_wsfw_wallet_action_referal_amount = get_option( 'wps_wsfw_wallet_action_referal_amount' );
			if ( isset( $used_cuopon ) && ! empty( $used_cuopon ) && is_array( $used_cuopon ) ) {
				foreach ( $used_cuopon as $coupon_code ) {
					$coupon_obj = new WC_Coupon( $coupon_code );
					$coupon_id = $coupon_obj->get_id();
					$coupon_user_id = get_post_meta( $coupon_id, 'refferedby_coupon', true );
					if ( ! isset( $coupon_user_id ) && empty( $coupon_user_id ) ) {
						continue;
					}

					$user_wallet_bal = get_user_meta( $coupon_user_id, 'wps_wallet', true );
					$user_wallet_bal = (float) $user_wallet_bal + (float) $wps_wsfw_wallet_action_referal_amount;
					update_user_meta( $coupon_user_id, 'wps_wallet', $user_wallet_bal );

					$wallet_payment_gateway = new Wallet_System_For_Woocommerce();
					$send_email_enable      = get_option( 'wps_wsfw_enable_email_notification_for_wallet_update', '' );
					// first user.
					$user1 = get_user_by( 'id', $coupon_user_id );
					$name1 = $user1->first_name . ' ' . $user1->last_name;
					$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );

					if ( isset( $send_email_enable ) && 'on' === $send_email_enable ) {

						$mail_text1  = esc_html__( 'Hello ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $name1 ) . ",\r\n";
						$mail_text1 .= __( 'Wallet credited by ', 'wallet-system-for-woocommerce-pro' ) . esc_html( $wps_wsfw_wallet_action_referal_amount ) . __( ' through Coupon Referral ', 'wallet-system-for-woocommerce-pro' );
						$to1         = $user1->user_email;
						$from        = get_option( 'admin_email' );
						$subject     = __( 'Wallet updating notification', 'wallet-system-for-woocommerce-pro' );
						$headers1    = 'MIME-Version: 1.0' . "\r\n";
						$headers1   .= 'Content-Type: text/html;  charset=UTF-8' . "\r\n";
						$headers1   .= 'From: ' . $from . "\r\n" .
						'Reply-To: ' . $to1 . "\r\n";

						if ( key_exists( 'wps_wswp_wallet_credit', WC()->mailer()->emails ) ) {

							$customer_email = WC()->mailer()->emails['wps_wswp_wallet_credit'];
							if ( ! empty( $customer_email ) ) {
								$user       = get_user_by( 'id', $coupon_user_id );
								$balance_mail = $wps_wsfw_wallet_action_referal_amount;
								$user_name       = $user->first_name . ' ' . $user->last_name;
								$customer_email->trigger( $coupon_user_id, $user_name, $balance_mail, '' );
							}
						} else {

							$wallet_payment_gateway->send_mail_on_wallet_updation( $to1, $subject, $mail_text1, $headers1 );
						}
					}
					$transaction_type     = __( 'Wallet credited Through Coupon Referral to user ', 'wallet-system-for-woocommerce-pro' ) . $user1->user_email;
					$wallet_transfer_data = array(
						'user_id'          => $coupon_user_id,
						'amount'           => $wps_wsfw_wallet_action_referal_amount,
						'currency'         => $current_currency,
						'payment_method'   => __( 'Wallet Coupon Referral', 'wallet-system-for-woocommerce-pro' ),
						'transaction_type' => $transaction_type,
						'transaction_type_1' => 'credit',
						'order_id'         => '',
						'note'             => '',
					);

					$wallet_payment_gateway->insert_transaction_data_in_table( $wallet_transfer_data );

				}
			} else {
				return;
			}
		}
	}

	/**
	 * Wallet Referral function.
	 *
	 * @return void
	 */
	public function wps_wsfw_filter_for_wallet_multi_level_referral_content() {

		$user_id = get_current_user_id();

		$wps_wsfw_wallet_action_refer_multi_level_referral = get_option( 'wps_wsfw_wallet_action_refer_multi_level_referral' );
		$wps_wsfw_wallet_action_multi_level_amount = get_option( 'wps_wsfw_wallet_action_multi_level_amount' );
		$wps_wsfw_wallet_action_referal_coupon_type = get_option( 'wps_wsfw_wallet_action_referal_coupon_type' );

		if ( 'on' == $wps_wsfw_wallet_action_refer_multi_level_referral && $wps_wsfw_wallet_action_multi_level_amount ) {
			?>
							
				<div class="wps-wallet-referral-notification"><h4><?php echo esc_html__( 'Multi-Level Referral - ', 'wallet-system-for-woocommerce-pro' ); ?></h4><?php esc_html_e( 'You Are Applicable For Multi-level Referral And On Multi-Level Referral you will earn', 'wallet-system-for-woocommerce-pro' ); ?> <?php echo esc_html( get_woocommerce_currency_symbol() . $wps_wsfw_wallet_action_multi_level_amount ); ?> <?php esc_html_e( 'in your wallet.', 'wallet-system-for-woocommerce-pro' ); ?></div>
				<p class="wps-wallet-referral-notification"><?php esc_html_e( 'Muti-Level Referral will work with respect two Level Referral Only.', 'wallet-system-for-woocommerce-pro' ); ?></p>
			<?php

		}
	}
}

