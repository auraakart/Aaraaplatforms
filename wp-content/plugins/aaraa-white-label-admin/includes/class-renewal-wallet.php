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

	/** Renewal order meta: the delivery date (Y-m-d) this renewal funds (D+1). */
	const DELIVERY_META = '_aaraa_renewal_for_delivery';

	/** Renewal order meta: the delivery date whose pause triggered a wallet refund. */
	const REFUND_META = '_aaraa_pause_refunded';

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

		// Fallback: settle any renewal order that reaches 'pending' without having
		// been debited yet — e.g. created via the WCS "Create pending renewal
		// order" admin action, or any other path that does not fire
		// wcs_renewal_order_created. The DONE_META guard means a renewal already
		// settled by the filter above is never charged twice.
		add_action( 'woocommerce_order_status_pending', array( $this, 'settle_pending_renewal' ), 20, 2 );
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
		$this->settle( $renewal_order, $subscription );
		return $renewal_order;
	}

	/**
	 * Fallback settlement when a renewal order reaches 'pending' without having
	 * been debited by the wcs_renewal_order_created filter (admin "Create pending
	 * renewal order", or any other bypass path).
	 *
	 * @param int            $order_id Order id.
	 * @param \WC_Order|null $order    Order (WooCommerce passes it on newer versions).
	 * @return void
	 */
	public function settle_pending_renewal( $order_id, $order = null ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		// Only auto-pay renewal orders — never parent/checkout orders. During the
		// normal creation flow the renewal relation is added AFTER the order is
		// first saved as pending, so this check is false then and the filter path
		// (above) does the work; this fallback only catches orders that are already
		// established renewals by the time they sit at pending.
		if ( ! function_exists( 'wcs_order_contains_renewal' ) || ! wcs_order_contains_renewal( $order ) ) {
			return;
		}
		if ( $order->get_meta( self::DONE_META ) ) {
			return;
		}
		$subs         = function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ? wcs_get_subscriptions_for_renewal_order( $order ) : array();
		$subscription = ! empty( $subs ) ? reset( $subs ) : null;
		if ( ! $subscription instanceof \WC_Subscription ) {
			return;
		}
		$this->settle( $order, $subscription );
	}

	/**
	 * Debit the renewal amount from the customer's wallet and mark it paid, or
	 * hold the subscription when the balance is short. Idempotent via DONE_META.
	 *
	 * @param \WC_Order        $renewal_order Renewal order.
	 * @param \WC_Subscription $subscription  Source subscription.
	 * @return void
	 */
	private function settle( $renewal_order, $subscription ) {
		// Tag the delivery date this renewal funds (D+1) so a later pause of that
		// delivery can find and reverse this exact renewal. Set once, on any path.
		if ( ! $renewal_order->get_meta( self::DELIVERY_META ) ) {
			$renewal_order->update_meta_data( self::DELIVERY_META, self::funded_delivery_date( $renewal_order ) );
			$renewal_order->save();
		}

		// Never settle the same renewal twice.
		if ( $renewal_order->get_meta( self::DONE_META ) ) {
			return;
		}

		$user_id  = $renewal_order->get_customer_id();
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$amount   = round( (float) $renewal_order->get_total(), $decimals );

		// No customer, or nothing to charge — let WooCommerce handle it.
		if ( ! $user_id || $amount <= 0 ) {
			return;
		}

		$balance = Customers_Admin::get_wallet_balance( $user_id );

		// Insufficient balance → don't touch the wallet; hold the subscription.
		if ( $balance + 0.00001 < $amount ) {
			$renewal_order->update_meta_data( self::DONE_META, 'insufficient' );
			$renewal_order->save();
			$this->hold_for_low_balance( $renewal_order, $subscription, $amount, $balance );
			return;
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
		$renewal_order->payment_complete();
	}

	/* --------------------------------------------------------------------- *
	 * Pause / un-pause reconciliation (money follows the delivery calendar).
	 *
	 * Delivery runs one day after its renewal (D+1), so a renewal created on day
	 * D funds the delivery on D+1. When the pause calendar changes we reconcile:
	 *   - a delivery date that is NEWLY paused but whose renewal already ran →
	 *     refund that renewal to the wallet (Scenario A);
	 *   - a delivery date that was paused and is now UN-paused → create + charge
	 *     the renewal that the pause guard had skipped (Scenario B).
	 * --------------------------------------------------------------------- */

	/**
	 * Reconcile wallet money against a change in a subscription's pause dates.
	 *
	 * Call this after the new pause dates have been persisted. Both lists are the
	 * TODAY-or-future pause dates (Y-m-d) before and after the change; past dates
	 * are ignored because their deliveries can no longer be created or refunded.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param string[]         $old_dates    Future pause dates before the change.
	 * @param string[]         $new_dates    Future pause dates after the change.
	 * @return void
	 */
	public function reconcile_pause_change( $subscription, array $old_dates, array $new_dates ) {
		if ( ! is_a( $subscription, 'WC_Subscription' ) ) {
			return;
		}
		$added   = array_values( array_diff( $new_dates, $old_dates ) ); // newly paused deliveries.
		$removed = array_values( array_diff( $old_dates, $new_dates ) ); // un-paused deliveries.

		foreach ( $added as $delivery_date ) {
			$this->refund_renewal_for_paused_delivery( $subscription, $delivery_date );
		}
		foreach ( $removed as $delivery_date ) {
			$this->create_renewal_for_unpaused_delivery( $subscription, $delivery_date );
		}
	}

	/**
	 * The delivery date (Y-m-d, site local) a renewal order funds: its own tag if
	 * present, else the day after it was created (the D+1 rule).
	 *
	 * @param \WC_Order $order Renewal order.
	 * @return string Y-m-d, or '' when undeterminable.
	 */
	private static function funded_delivery_date( $order ) {
		$tag = (string) $order->get_meta( self::DELIVERY_META );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $tag ) ) {
			return $tag;
		}
		$created = $order->get_date_created();
		if ( ! $created ) {
			return '';
		}
		return wp_date( 'Y-m-d', $created->getTimestamp() + DAY_IN_SECONDS );
	}

	/**
	 * Scenario A — a delivery date was paused after its renewal was already
	 * created and paid from the wallet. Refund that renewal: set it to Refunded,
	 * credit the wallet back, and log it on both the order and the subscription.
	 *
	 * Only wallet-paid renewals (DONE_META = 'yes') are reversed; a renewal that
	 * was never charged (insufficient balance, or COD) leaves the wallet alone.
	 *
	 * @param \WC_Subscription $subscription  Subscription.
	 * @param string           $delivery_date Newly-paused delivery date (Y-m-d).
	 * @return void
	 */
	private function refund_renewal_for_paused_delivery( $subscription, $delivery_date ) {
		$ids = $subscription->get_related_orders( 'ids', 'renewal' );
		if ( empty( $ids ) ) {
			return;
		}
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		foreach ( (array) $ids as $oid ) {
			$order = wc_get_order( (int) $oid );
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			if ( $order->get_meta( self::REFUND_META ) ) {
				continue; // Already refunded for a pause.
			}
			if ( self::funded_delivery_date( $order ) !== $delivery_date ) {
				continue; // Funds a different delivery day.
			}
			if ( 'yes' !== $order->get_meta( self::DONE_META ) ) {
				continue; // Never actually debited the wallet — nothing to give back.
			}
			if ( $order->has_status( array( 'refunded', 'cancelled' ) ) ) {
				continue;
			}

			$user_id = $order->get_customer_id();
			$amount  = round( (float) $order->get_total(), $decimals );
			if ( ! $user_id || $amount <= 0 ) {
				continue;
			}

			$balance     = Customers_Admin::get_wallet_balance( $user_id );
			$new_balance = round( $balance + $amount, $decimals );
			Customers_Admin::set_wallet_balance( $user_id, $new_balance );
			$this->record_credit( $user_id, $amount, $order, $subscription, $delivery_date );

			$order->update_meta_data( self::REFUND_META, $delivery_date );
			$order->add_order_note(
				sprintf(
					/* translators: 1: delivery date, 2: amount refunded, 3: new wallet balance */
					__( 'Delivery %1$s paused after this renewal — %2$s refunded to wallet. Wallet balance now %3$s.', 'aaraa-white-label-admin' ),
					$delivery_date,
					$this->money( $amount ),
					$this->money( $new_balance )
				)
			);

			// WooCommerce core adds a "…issue a refund through your payment gateway"
			// note on the refunded transition (wc_order_fully_refunded). That advice
			// is wrong here — the money went back to the wallet, not a gateway — so
			// suppress just that handler around the status change, then restore it.
			$restore = remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
			$order->update_status( 'refunded', __( 'Delivery paused after renewal — amount refunded to wallet.', 'aaraa-white-label-admin' ) );
			if ( $restore ) {
				add_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
			}
			$order->save();

			$subscription->add_order_note(
				sprintf(
					/* translators: 1: renewal order number, 2: amount, 3: delivery date */
					__( 'Renewal #%1$s refunded to wallet (%2$s) — the %3$s delivery was paused after the renewal was created.', 'aaraa-white-label-admin' ),
					$order->get_order_number(),
					$this->money( $amount ),
					$delivery_date
				)
			);
		}
	}

	/**
	 * Scenario B — a paused delivery date was un-paused, so the renewal the pause
	 * guard had skipped must now be created and charged. Creates the renewal (the
	 * wcs_renewal_order_created filter above debits the wallet or holds on short
	 * balance), tags it with its delivery date, and logs it.
	 *
	 * A renewal created today funds TOMORROW's delivery (the D+1 rule), so we only
	 * create one when the un-paused delivery is tomorrow — i.e. its renewal is due
	 * today and was skipped this morning while the date was still paused. Deliveries
	 * further in the future need nothing now: once un-paused, the normal scheduled
	 * renewal fires on their own renewal day. Deliveries today or in the past can no
	 * longer be funded by a new renewal (that renewal day has already gone), so we
	 * never back-date one — creating a "now" renewal for them would instead fund the
	 * next day, which is the very delivery being paused.
	 *
	 * Also skips a delivery that already has a live renewal (no duplicate charge).
	 *
	 * @param \WC_Subscription $subscription  Subscription.
	 * @param string           $delivery_date Un-paused delivery date (Y-m-d).
	 * @return void
	 */
	private function create_renewal_for_unpaused_delivery( $subscription, $delivery_date ) {
		if ( ! function_exists( 'wcs_create_renewal_order' ) ) {
			return;
		}

		// Only act when the renewal for this delivery is due TODAY (delivery = D+1).
		$today       = current_time( 'Y-m-d' );
		$renewal_day = gmdate( 'Y-m-d', strtotime( $delivery_date . ' -1 day' ) );
		if ( $renewal_day !== $today ) {
			// Past renewal day → can't back-date. Future renewal day → the scheduled
			// renewal will run on its own once the date is un-paused. Either way, do
			// not create a renewal now.
			return;
		}

		// Already has a renewal for this delivery day? Then do nothing. A refunded
		// renewal still counts — the day's renewal was already made (and reversed),
		// so re-creating it would just be a same-day duplicate. Only a cancelled /
		// trashed order (e.g. a previously-voided duplicate) is ignored.
		foreach ( (array) $subscription->get_related_orders( 'ids', 'renewal' ) as $oid ) {
			$order = wc_get_order( (int) $oid );
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			if ( $order->has_status( array( 'cancelled', 'trash' ) ) ) {
				continue;
			}
			if ( self::funded_delivery_date( $order ) === $delivery_date ) {
				return;
			}
		}

		$renewal = wcs_create_renewal_order( $subscription );
		if ( is_wp_error( $renewal ) || ! $renewal instanceof \WC_Order ) {
			return;
		}
		$renewal_id = $renewal->get_id();

		// Reload fresh: the wcs_renewal_order_created filter (settle) has already
		// run against the created order during wcs_create_renewal_order().
		$renewal = wc_get_order( $renewal_id );
		if ( ! $renewal instanceof \WC_Order ) {
			return;
		}
		$renewal->update_meta_data( self::DELIVERY_META, $delivery_date );
		$renewal->add_order_note(
			sprintf(
				/* translators: %s: delivery date */
				__( 'Renewal created for the %s delivery — this date was un-paused.', 'aaraa-white-label-admin' ),
				$delivery_date
			)
		);
		$renewal->save();

		// Fallback: if the create-time filter did not settle it (e.g. another plugin
		// short-circuited the filter), settle now. Idempotent via DONE_META.
		if ( ! $renewal->get_meta( self::DONE_META ) ) {
			$this->settle( $renewal, $subscription );
			$renewal = wc_get_order( $renewal_id );
		}

		$subscription->add_order_note(
			sprintf(
				/* translators: 1: renewal order number, 2: delivery date */
				__( 'Renewal #%1$s created for the %2$s delivery — this date was un-paused.', 'aaraa-white-label-admin' ),
				$renewal ? $renewal->get_order_number() : $renewal_id,
				$delivery_date
			)
		);
	}

	/**
	 * Record a wallet credit (pause refund) in the WP Swings transaction ledger.
	 *
	 * @param int              $user_id       Customer id.
	 * @param float            $amount        Amount credited (positive).
	 * @param \WC_Order        $renewal_order Refunded renewal order.
	 * @param \WC_Subscription $subscription  Subscription.
	 * @param string           $delivery_date Paused delivery date (Y-m-d).
	 * @return void
	 */
	private function record_credit( $user_id, $amount, $renewal_order, $subscription, $delivery_date ) {
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
				/* translators: 1: subscription number, 2: paused delivery date */
				__( 'Wallet refund — subscription #%1$s delivery %2$s paused', 'aaraa-white-label-admin' ),
				$subscription->get_order_number(),
				$delivery_date
			),
			'transaction_type_1' => 'credit',
			'payment_method'     => __( 'Auto refund (wallet)', 'aaraa-white-label-admin' ),
			'transaction_id'     => (string) $renewal_order->get_id(),
			'note'               => sprintf(
				/* translators: %s: renewal order number */
				__( 'Refund of renewal order #%s', 'aaraa-white-label-admin' ),
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
