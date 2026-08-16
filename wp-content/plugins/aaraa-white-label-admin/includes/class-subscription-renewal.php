<?php
/**
 * Subscription renewal payment flow (via WP Swings wallet recharge).
 *
 * A "Send Payment Reminder" WhatsApp (built in WhatsApp_Admin) carries a signed
 * link to /renewal-subscription. That page routes the customer into WP Swings'
 * built-in wallet recharge for the reminder amount — WP Swings takes the gateway
 * payment and credits the wallet itself. This module does NOT create its own
 * order; it only:
 *
 *   1. tags the recharge checkout order with the subscription + customer, so the
 *      right wallet is credited even if the visitor isn't logged in, and
 *   2. reactivates the subscription and logs the transaction on its notes once
 *      the recharge order reaches a wallet-crediting status.
 *
 * The amount is signed into the link so it cannot be tampered with client-side.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renewal reminder → WP Swings recharge → subscription reactivation.
 */
class Subscription_Renewal {

	/** URL slug for the renewal landing page. */
	const ENDPOINT = 'renewal-subscription';

	/** Query var the rewrite rule sets. */
	const QVAR = 'aaraa_renewal';

	/** Option flag guarding the one-time rewrite flush. */
	const RW_FLAG = 'aaraa_renewal_rw_v1';

	/** WC session key carrying the renewal context into checkout. */
	const SESSION = 'aaraa_renewal_ctx';

	/** Order meta: the subscription this recharge renews. */
	const ORDER_SUB = '_aaraa_renewal_sub_id';
	/** Order meta: the customer whose wallet must be credited. */
	const ORDER_CUSTOMER = '_aaraa_renewal_customer';
	/** Order meta: guard so subscription activation runs exactly once. */
	const ORDER_DONE = '_aaraa_renewal_done';

	/** Subscription meta: the last reminder amount requested (informational). */
	const SUB_PENDING = '_aaraa_renewal_pending_amount';

	/** WP Swings option holding the rechargeable product id. */
	const WPS_PRODUCT_OPTION = 'wps_wsfw_rechargeable_product_id';

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );

		// Tag the recharge checkout order with the renewal context.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_order' ), 20, 2 );
		// Make sure WP Swings credits the subscription's customer (not a guest).
		add_filter( 'wsfw_check_order_meta_for_userid', array( $this, 'filter_recharge_userid' ), 20, 2 );
		// Reactivate + log once the recharge order lands.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 30, 4 );
	}

	/* --------------------------------------------------------------------- *
	 * Signed link.
	 * --------------------------------------------------------------------- */

	/**
	 * Normalise an amount to a fixed decimal string for signing/consistency.
	 *
	 * @param float|string $amount Amount.
	 * @return string
	 */
	private static function amt_str( $amount ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		return number_format( (float) $amount, $decimals, '.', '' );
	}

	/**
	 * HMAC signature binding a subscription id to an amount.
	 *
	 * @param int          $sub_id Subscription id.
	 * @param float|string $amount Amount.
	 * @return string
	 */
	public static function sign( $sub_id, $amount ) {
		return hash_hmac( 'sha256', (int) $sub_id . '|' . self::amt_str( $amount ), wp_salt( 'auth' ) );
	}

	/**
	 * Verify a token for a subscription id + amount.
	 *
	 * @param int          $sub_id Subscription id.
	 * @param float|string $amount Amount.
	 * @param string       $token  Provided token.
	 * @return bool
	 */
	public static function verify( $sub_id, $amount, $token ) {
		$expected = self::sign( $sub_id, $amount );
		return is_string( $token ) && hash_equals( $expected, $token );
	}

	/**
	 * Build the customer-facing payment link.
	 *
	 * @param int          $sub_id Subscription id.
	 * @param float|string $amount Amount.
	 * @return string
	 */
	public static function payment_link( $sub_id, $amount ) {
		return add_query_arg(
			array(
				'sub' => (int) $sub_id,
				'amt' => self::amt_str( $amount ),
				't'   => self::sign( $sub_id, $amount ),
			),
			home_url( '/' . self::ENDPOINT . '/' )
		);
	}

	/* --------------------------------------------------------------------- *
	 * Rewrite / routing.
	 * --------------------------------------------------------------------- */

	/**
	 * Register the pretty endpoint and flush rules once.
	 *
	 * @return void
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^' . self::ENDPOINT . '/?$', 'index.php?' . self::QVAR . '=1', 'top' );

		if ( '1' !== get_option( self::RW_FLAG ) ) {
			flush_rewrite_rules( false );
			update_option( self::RW_FLAG, '1', false );
		}
	}

	/**
	 * Whitelist the query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_var( $vars ) {
		$vars[] = self::QVAR;
		return $vars;
	}

	/**
	 * Render the landing page (and start the recharge on POST).
	 *
	 * @return void
	 */
	public function maybe_render() {
		if ( ! get_query_var( self::QVAR ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$sub_id = isset( $_GET['sub'] ) ? absint( $_GET['sub'] ) : 0;
		$amount = isset( $_GET['amt'] ) ? self::amt_str( wp_unslash( $_GET['amt'] ) ) : '';
		$token  = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$subscription = ( $sub_id && function_exists( 'wcs_get_subscription' ) ) ? wcs_get_subscription( $sub_id ) : false;

		$error = '';
		if ( ! $subscription ) {
			$error = __( 'This renewal link is not valid.', 'aaraa-white-label-admin' );
		} elseif ( (float) $amount <= 0 || ! self::verify( $sub_id, $amount, $token ) ) {
			$error = __( 'This renewal link has expired or been altered. Please ask for a fresh link.', 'aaraa-white-label-admin' );
		} elseif ( ! $subscription->get_user_id() ) {
			$error = __( 'This subscription has no customer account, so the wallet cannot be topped up.', 'aaraa-white-label-admin' );
		} elseif ( ! $this->recharge_product_id() ) {
			$error = __( 'Wallet recharge is not configured on the store. Please contact support.', 'aaraa-white-label-admin' );
		}

		if ( ! $error && isset( $_POST['aaraa_renewal_pay'] ) ) {
			if ( isset( $_POST['aaraa_renewal_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aaraa_renewal_nonce'] ) ), 'aaraa_renewal_pay' ) ) {
				$this->start_recharge( $subscription, (float) $amount ); // redirects.
			}
			$error = __( 'Security check failed. Please reload the page and try again.', 'aaraa-white-label-admin' );
		}

		$this->render_page( $subscription, (float) $amount, $error );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Recharge hand-off.
	 * --------------------------------------------------------------------- */

	/**
	 * The configured WP Swings rechargeable product id.
	 *
	 * @return int
	 */
	private function recharge_product_id() {
		return (int) get_option( self::WPS_PRODUCT_OPTION, 0 );
	}

	/**
	 * Set up WP Swings' recharge session + our renewal context, then bounce to
	 * the cart (WP Swings moves it on to checkout).
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param float            $amount       Renewal amount.
	 * @return void
	 */
	private function start_recharge( $subscription, $amount ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$product_id  = $this->recharge_product_id();
		$customer_id = $subscription->get_user_id();
		$amount_str  = self::amt_str( $amount );

		// WP Swings recharge session (same shape its My Account form sets).
		WC()->session->set(
			'wallet_recharge',
			array(
				'userid'         => $customer_id,
				'rechargeamount' => $amount_str,
				'productid'      => $product_id,
			)
		);
		WC()->session->set( 'recharge_amount', $amount_str );

		// Our own context, read back when the checkout order is created.
		WC()->session->set(
			self::SESSION,
			array(
				'sub'      => $subscription->get_id(),
				'customer' => $customer_id,
				'amount'   => $amount_str,
			)
		);

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
			WC()->cart->add_to_cart( $product_id );
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Stamp the recharge checkout order with the renewal context and force its
	 * customer to the subscription's customer, so WP Swings credits the right
	 * wallet even for a guest checkout.
	 *
	 * @param \WC_Order $order Order being created.
	 * @param array     $data  Posted checkout data (unused).
	 * @return void
	 */
	public function stamp_order( $order, $data ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$ctx = WC()->session->get( self::SESSION );
		if ( empty( $ctx ) || empty( $ctx['sub'] ) ) {
			return;
		}
		$order->update_meta_data( self::ORDER_SUB, (int) $ctx['sub'] );
		$order->update_meta_data( self::ORDER_CUSTOMER, (int) $ctx['customer'] );
		if ( ! empty( $ctx['customer'] ) ) {
			$order->set_customer_id( (int) $ctx['customer'] );
		}
		WC()->session->__unset( self::SESSION );
	}

	/**
	 * Point WP Swings' recharge crediting at the subscription's customer.
	 *
	 * @param int $userid   Default user id (order owner).
	 * @param int $order_id Recharge order id.
	 * @return int
	 */
	public function filter_recharge_userid( $userid, $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$customer = (int) $order->get_meta( self::ORDER_CUSTOMER );
			if ( $customer ) {
				return $customer;
			}
		}
		return $userid;
	}

	/**
	 * When a renewal recharge order reaches a wallet-crediting status, reactivate
	 * the subscription and log it. Idempotent via the ORDER_DONE guard. The
	 * wallet credit itself is done by WP Swings — we don't touch the balance.
	 *
	 * @param int       $order_id Order id.
	 * @param string    $from     Old status.
	 * @param string    $to       New status.
	 * @param \WC_Order $order    Order.
	 * @return void
	 */
	public function on_status_changed( $order_id, $from, $to, $order ) {
		if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		$sub_id = (int) $order->get_meta( self::ORDER_SUB );
		if ( ! $sub_id || $order->get_meta( self::ORDER_DONE ) ) {
			return;
		}
		if ( ! in_array( $to, $this->crediting_statuses(), true ) ) {
			return;
		}

		$order->update_meta_data( self::ORDER_DONE, 1 );
		$order->save();

		$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : false;
		if ( ! $subscription ) {
			return;
		}
		$this->activate_subscription( $subscription );
		$this->log_subscription( $subscription, (float) $order->get_total(), (int) $order->get_meta( self::ORDER_CUSTOMER ), $order );
	}

	/**
	 * Order statuses at which WP Swings credits a recharge (default: completed).
	 *
	 * @return array<string>
	 */
	private function crediting_statuses() {
		$statuses = get_option( 'wps_wsfw_wallet_order_auto_process' );
		if ( ! is_array( $statuses ) || empty( $statuses ) ) {
			$statuses = array( 'completed' );
		}
		return array_map( 'strval', $statuses );
	}

	/**
	 * Reactivate the subscription if it isn't already active.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @return void
	 */
	private function activate_subscription( $subscription ) {
		if ( $subscription->has_status( 'active' ) ) {
			return;
		}
		try {
			$subscription->update_status( 'active', __( 'Reactivated after a successful renewal payment.', 'aaraa-white-label-admin' ) );
		} catch ( \Exception $e ) {
			$subscription->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Could not auto-activate the subscription after renewal payment: %s', 'aaraa-white-label-admin' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Add the transaction record to the subscription's notes (its log).
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param float            $amount       Amount recharged.
	 * @param int              $customer_id  Customer whose wallet was credited.
	 * @param \WC_Order        $order        Recharge order.
	 * @return void
	 */
	private function log_subscription( $subscription, $amount, $customer_id, $order ) {
		$money   = function_exists( 'wc_price' ) ? wp_strip_all_tags( html_entity_decode( wc_price( $amount ) ) ) : (string) $amount;
		$balance = ( $customer_id && class_exists( __NAMESPACE__ . '\\Customers_Admin' ) ) ? Customers_Admin::get_wallet_balance( $customer_id ) : 0;
		$bal     = function_exists( 'wc_price' ) ? wp_strip_all_tags( html_entity_decode( wc_price( $balance ) ) ) : (string) $balance;

		$subscription->add_order_note(
			sprintf(
				/* translators: 1: amount, 2: order number, 3: new wallet balance */
				__( 'Renewal payment of %1$s received (wallet recharge order #%2$s). Amount credited to wallet — balance now %3$s. Subscription reactivated.', 'aaraa-white-label-admin' ),
				$money,
				$order->get_order_number(),
				$bal
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Landing page.
	 * --------------------------------------------------------------------- */

	/**
	 * Render the renewal landing page.
	 *
	 * @param \WC_Subscription|false $subscription Subscription (false if invalid).
	 * @param float                  $amount       Renewal amount.
	 * @param string                 $error        Error message, if any.
	 * @return void
	 */
	private function render_page( $subscription, $amount, $error ) {
		$title = __( 'Subscription Renewal Payment', 'aaraa-white-label-admin' );

		get_header();
		echo '<div class="aaraa-renewal-wrap"><div class="aaraa-renewal-card">';
		echo '<h1 class="aaraa-renewal-title">' . esc_html( $title ) . '</h1>';

		if ( $error ) {
			echo '<div class="aaraa-renewal-notice is-error">' . esc_html( $error ) . '</div>';
		}

		if ( $subscription && ! $error ) {
			$money = function_exists( 'wc_price' ) ? wc_price( $amount ) : esc_html( (string) $amount );

			echo '<div class="aaraa-renewal-summary">';
			echo '<div class="aaraa-renewal-row"><span>' . esc_html__( 'Subscription', 'aaraa-white-label-admin' ) . '</span><strong>#' . esc_html( $subscription->get_order_number() ) . '</strong></div>';
			echo '<div class="aaraa-renewal-row"><span>' . esc_html__( 'Customer', 'aaraa-white-label-admin' ) . '</span><strong>' . esc_html( trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ) ) . '</strong></div>';
			echo '<div class="aaraa-renewal-row is-total"><span>' . esc_html__( 'Amount to pay', 'aaraa-white-label-admin' ) . '</span><strong>' . wp_kses_post( $money ) . '</strong></div>';
			echo '</div>';

			echo '<p class="aaraa-renewal-note">' . esc_html__( 'This amount is added to your wallet and your subscription is reactivated. You will choose your payment method on the next step.', 'aaraa-white-label-admin' ) . '</p>';

			echo '<form method="post" class="aaraa-renewal-form">';
			wp_nonce_field( 'aaraa_renewal_pay', 'aaraa_renewal_nonce' );
			echo '<button type="submit" name="aaraa_renewal_pay" value="1" class="button aaraa-renewal-pay">' . esc_html__( 'Proceed to payment', 'aaraa-white-label-admin' ) . '</button>';
			echo '</form>';
		}

		echo '</div></div>';
		$this->page_css();
		get_footer();
	}

	/**
	 * Inline styles for the renewal page.
	 *
	 * @return void
	 */
	private function page_css() {
		?>
		<style>
			.aaraa-renewal-wrap{max-width:520px;margin:40px auto;padding:0 16px;}
			.aaraa-renewal-card{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:26px 26px 30px;box-shadow:0 6px 24px rgba(0,0,0,.06);}
			.aaraa-renewal-title{font-size:22px;margin:0 0 18px;}
			.aaraa-renewal-notice{padding:12px 14px;border-radius:8px;margin:0 0 16px;font-size:14px;}
			.aaraa-renewal-notice.is-error{background:#fbeaea;color:#b32d2e;}
			.aaraa-renewal-summary{border:1px solid #eee;border-radius:8px;padding:6px 14px;margin:0 0 16px;}
			.aaraa-renewal-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #f0f0f0;font-size:15px;}
			.aaraa-renewal-row:last-child{border-bottom:none;}
			.aaraa-renewal-row.is-total{font-size:18px;}
			.aaraa-renewal-row.is-total strong{color:#1a7f45;}
			.aaraa-renewal-note{color:#555;font-size:14px;margin:0 0 20px;}
			.aaraa-renewal-pay{display:inline-block;background:#1a7f45;border:none;color:#fff;font-size:16px;font-weight:600;padding:12px 28px;border-radius:8px;cursor:pointer;}
			.aaraa-renewal-pay:hover{background:#166b3a;color:#fff;}
		</style>
		<?php
	}
}
