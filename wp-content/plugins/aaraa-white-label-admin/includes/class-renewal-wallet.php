<?php
/**
 * Pay subscription renewals from the customer's wallet.
 *
 * When a renewal order is created the renewal amount is debited from the
 * customer's WP Swings wallet (wps_wallet), recorded in the wallet transaction
 * ledger and noted on the order, and the renewal is marked paid so the
 * subscription stays active. If the wallet cannot cover the amount nothing is
 * debited: the renewal is failed and the subscription goes on-hold, which lets
 * the existing "insufficient wallet balance — please recharge" reminder fire.
 *
 * This replaces the theme's four overlapping renewal handlers (which debited
 * 2–4 times, skipped the balance check and could drive the wallet negative);
 * those are unhooked here so the debit happens exactly once, correctly.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wallet-funded subscription renewals.
 */
class Renewal_Wallet {

	/** Order meta guard: records that this renewal was already settled. */
	const DONE_META = '_aaraa_renewal_wallet_debited';

	/**
	 * Hook the renewal handler and disable the theme's duplicates.
	 *
	 * @return void
	 */
	public function init() {
		// Remove the theme's overlapping renewal handlers (registered when
		// functions.php loads) so only this one runs. Safe if any are absent.
		add_action( 'init', array( $this, 'unhook_theme_handlers' ), 20 );

		// Debit once, when the renewal order is created. Priority 30 so it runs
		// after the delivery auto-assign on the same hook.
		add_filter( 'wcs_renewal_order_created', array( $this, 'debit_on_renewal' ), 30, 2 );
	}

	/**
	 * Detach the theme's renewal wallet handlers.
	 *
	 * @return void
	 */
	public function unhook_theme_handlers() {
		remove_filter( 'wcs_renewal_order_created', 'change_order_and_subscription_status', 10 );
		remove_filter( 'wcs_renewal_order_created', 'my_custom_renewal_action', 10 );
		remove_action( 'woocommerce_subscriptions_renewal_order_created', 'custom_renewal_order_created', 10 );
		remove_action( 'woocommerce_subscription_renewal_payment_complete', 'custom_renewal_payment_complete', 10 );
	}

	/**
	 * Settle a freshly-created renewal order against the customer's wallet.
	 *
	 * @param \WC_Order        $renewal_order Renewal order.
	 * @param \WC_Subscription $subscription  Source subscription.
	 * @return \WC_Order The renewal order (this is a filter).
	 */
	public function debit_on_renewal( $renewal_order, $subscription ) {
		if ( ! $renewal_order instanceof \WC_Order ) {
			return $renewal_order;
		}
		// Never settle the same renewal twice.
		if ( $renewal_order->get_meta( self::DONE_META ) ) {
			return $renewal_order;
		}

		$user_id  = $renewal_order->get_customer_id();
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$amount   = round( (float) $renewal_order->get_total(), $decimals );

		// No customer, or nothing to charge — let WooCommerce handle it.
		if ( ! $user_id || $amount <= 0 ) {
			return $renewal_order;
		}

		$balance = Customers_Admin::get_wallet_balance( $user_id );

		// Insufficient balance → don't touch the wallet; hold the subscription.
		if ( $balance + 0.00001 < $amount ) {
			$renewal_order->update_meta_data( self::DONE_META, 'insufficient' );
			$renewal_order->save();
			$this->hold_for_low_balance( $renewal_order, $subscription, $amount, $balance );
			return $renewal_order;
		}

		$new_balance = round( $balance - $amount, $decimals );

		// Writes wps_wallet + _wps_amount; the wallet-debit SMS/WhatsApp (if the
		// admin has enabled those templates) fires off this meta change.
		Customers_Admin::set_wallet_balance( $user_id, $new_balance );
		$this->record_debit( $user_id, $amount, $renewal_order, $subscription );

		$renewal_order->update_meta_data( self::DONE_META, 'yes' );
		$renewal_order->save();

		$renewal_order->add_order_note(
			sprintf(
				/* translators: 1: amount debited, 2: subscription number, 3: new wallet balance */
				__( 'Renewal paid from wallet — %1$s debited for subscription #%2$s. Wallet balance now %3$s.', 'aaraa-white-label-admin' ),
				$this->money( $amount ),
				$subscription->get_order_number(),
				$this->money( $new_balance )
			)
		);

		// Mark the renewal paid so WooCommerce Subscriptions keeps the
		// subscription active and schedules the next payment.
		// Cap HTTP API timeouts (e.g. WhatsApp/Firebase notifications) to 5 seconds
		// during renewal payment processing to prevent ActionScheduler 300s timeouts.
		$cap_timeout = static function() { return 5; };
		add_filter( 'aaraa_whatsapp_api_timeout', $cap_timeout );
		add_filter( 'http_request_timeout', $cap_timeout );

		try {
			$renewal_order->payment_complete();

			// Ensure subscription status is set to active upon successful wallet payment
			if ( is_a( $subscription, 'WC_Subscription' ) && $subscription->has_status( array( 'on-hold', 'pending' ) ) ) {
				$subscription->update_status( 'active', __( 'Subscription activated after successful wallet payment.', 'aaraa-white-label-admin' ) );
			}
		} catch ( \Throwable $e ) {
			if ( ! $renewal_order->has_status( array( 'processing', 'completed' ) ) ) {
				$renewal_order->update_status( 'processing', __( 'Wallet debited for renewal.', 'aaraa-white-label-admin' ) );
			}
		} finally {
			remove_filter( 'aaraa_whatsapp_api_timeout', $cap_timeout );
			remove_filter( 'http_request_timeout', $cap_timeout );
		}

		return $renewal_order;
	}

	/**
	 * Hold the subscription when the wallet can't cover the renewal.
	 *
	 * @param \WC_Order        $renewal_order Renewal order.
	 * @param \WC_Subscription $subscription  Subscription.
	 * @param float            $amount        Renewal amount.
	 * @param float            $balance       Current wallet balance.
	 * @return void
	 */
	private function hold_for_low_balance( $renewal_order, $subscription, $amount, $balance ) {
		$note = sprintf(
			/* translators: 1: wallet balance, 2: renewal amount */
			__( 'Renewal not charged — wallet balance %1$s is below the renewal amount %2$s.', 'aaraa-white-label-admin' ),
			$this->money( $balance ),
			$this->money( $amount )
		);
		$renewal_order->add_order_note( $note );

		// Fail the renewal order; WooCommerce Subscriptions then puts the
		// subscription on-hold. Set on-hold explicitly too in case the payment
		// flow doesn't (a no-op when it already did), so the recharge reminder
		// that keys off the on-hold status is sent.
		if ( ! $renewal_order->has_status( 'failed' ) ) {
			$renewal_order->update_status( 'failed', __( 'Insufficient wallet balance for renewal.', 'aaraa-white-label-admin' ) );
		}
		if ( is_a( $subscription, 'WC_Subscription' ) && ! $subscription->has_status( 'on-hold' ) ) {
			$subscription->update_status( 'on-hold', $note );
		}
	}

	/**
	 * Record the debit in the WP Swings wallet transaction ledger.
	 *
	 * @param int              $user_id       Customer id.
	 * @param float            $amount         Amount debited (positive).
	 * @param \WC_Order        $renewal_order  Renewal order.
	 * @param \WC_Subscription $subscription   Subscription.
	 * @return void
	 */
	private function record_debit( $user_id, $amount, $renewal_order, $subscription ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Customers_Admin' ) || ! Customers_Admin::wallet_table_exists() ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
		$data  = array(
			'user_id'            => (int) $user_id,
			'amount'             => (float) $amount,
			'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'transaction_type'   => sprintf(
				/* translators: %s: subscription number */
				__( 'Wallet debited for subscription renewal #%s', 'aaraa-white-label-admin' ),
				$subscription->get_order_number()
			),
			'transaction_type_1' => 'debit',
			'payment_method'     => __( 'Auto renewal (wallet)', 'aaraa-white-label-admin' ),
			'transaction_id'     => (string) $renewal_order->get_id(),
			'note'               => sprintf(
				/* translators: %s: renewal order number */
				__( 'Renewal order #%s', 'aaraa-white-label-admin' ),
				$renewal_order->get_order_number()
			),
			'date'               => gmdate( 'Y-m-d H:i:s' ),
		);
		$format = array( '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( Customers_Admin::has_created_by_column() ) {
			$data['created_by'] = (int) $user_id;
			$format[]           = '%d';
		}
		$wpdb->insert( $table, $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Currency-formatted, tag-free amount for notes.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private function money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( html_entity_decode( wc_price( $amount ) ) );
		}
		return number_format( (float) $amount, 2 );
	}
}
