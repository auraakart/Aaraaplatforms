<?php
/**
 * Renewal pause guard.
 *
 * Guarantees that a subscription's renewal is NOT generated for a day that is
 * one of its pause dates ( `_wcfmu_pause_dates` ), regardless of how the renewal
 * is triggered — WooCommerce Subscriptions' own scheduled action, or a manual /
 * cron `do_action( 'woocommerce_scheduled_subscription_payment', $id )`.
 *
 * WCS only creates the renewal order in `WC_Subscriptions_Manager::process_renewal()`,
 * and only while the subscription `has_status( 'active' )`. On a pause day the
 * subscription is normally `wc-pause`, so WCS already skips it — but a renewal
 * fired while the subscription is still `active` (e.g. a pre-generated next-day
 * renewal, or a timing race before the pause flip) would slip through. This
 * guard runs before WCS's handlers: if the due day is a pause date it removes
 * WCS's renewal handlers for that one fire and advances `next_payment` to the
 * next non-paused day so billing continues cleanly.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Skips renewals that fall on a subscription's pause date.
 */
class Renewal_Pause_Guard {

	/**
	 * Whether we removed WCS's renewal handlers for the current fire.
	 *
	 * @var bool
	 */
	private $removed = false;

	/**
	 * Hook the guard before WCS's own renewal handlers.
	 *
	 * WCS registers: maybe_process_failed_renewal_for_repair (priority 0),
	 * prepare_renewal (1) and gateway_scheduled_subscription_payment (10). A
	 * negative priority guarantees we run first.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'maybe_skip' ), -1000, 1 );
		// Diagnostic: log every renewal order actually created, and by which path,
		// so we can see any that bypass the scheduled-payment hook.
		add_action( 'wcs_renewal_order_created', array( $this, 'log_renewal_created' ), -1000, 2 );
		self::log( 'Renewal_Pause_Guard registered on woocommerce_scheduled_subscription_payment' );
	}

	/**
	 * Diagnostic: record that a renewal order was created (any path).
	 *
	 * @param \WC_Order        $renewal_order Renewal order.
	 * @param \WC_Subscription $subscription  Subscription.
	 * @return \WC_Order
	 */
	public function log_renewal_created( $renewal_order, $subscription ) {
		$sub_id = is_object( $subscription ) ? $subscription->get_id() : 0;
		$np     = ( is_object( $subscription ) && method_exists( $subscription, 'get_time' ) )
			? (int) $subscription->get_time( 'next_payment', 'gmt' )
			: 0;
		self::log(
			sprintf(
				'%d: RENEWAL ORDER CREATED #%s (current next_payment=%s)',
				$sub_id,
				is_object( $renewal_order ) ? $renewal_order->get_id() : '?',
				$np ? wp_date( 'Y-m-d H:i', $np ) : 'n/a'
			)
		);
		return $renewal_order;
	}

	/**
	 * Append a line to the guard's diagnostic log (wp-content/aaraa-renewal-guard.log).
	 * Temporary instrumentation to trace why a renewal was or wasn't skipped on live.
	 *
	 * @param string $message Log line.
	 * @return void
	 */
	private static function log( $message ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}
		$line = '[' . gmdate( 'Y-m-d H:i:s' ) . " UTC] $message\n";
		@file_put_contents( WP_CONTENT_DIR . '/aaraa-renewal-guard.log', $line, FILE_APPEND ); // phpcs:ignore
	}

	/**
	 * If the subscription's due day is a pause date, skip the renewal.
	 *
	 * @param int $sub_id Subscription id.
	 * @return void
	 */
	public function maybe_skip( $sub_id ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			self::log( "$sub_id: wcs_get_subscription missing" );
			return;
		}
		$sub = wcs_get_subscription( $sub_id );
		if ( ! $sub ) {
			self::log( "$sub_id: subscription not found" );
			return;
		}

		$due_ts = (int) $sub->get_time( 'next_payment', 'gmt' );
		if ( ! $due_ts ) {
			self::log( "$sub_id: no next_payment timestamp" );
			return;
		}

		// The delivery person prepares one day ahead, so a renewal that runs on
		// day D fulfils the delivery on D+1. The renewal must therefore be skipped
		// when TOMORROW's delivery (D+1) is a paused date — not when D itself is.
		$due_date      = wp_date( 'Y-m-d', $due_ts );                     // renewal day D.
		$delivery_date = wp_date( 'Y-m-d', $due_ts + DAY_IN_SECONDS );    // delivery day D+1.
		$all_pause     = class_exists( __NAMESPACE__ . '\\Subscription_Delivery' )
			? Subscription_Delivery::read_pause_dates( (int) $sub_id )
			: array();
		self::log(
			sprintf(
				'%d: FIRE next_payment=%s (D=%s, delivery D+1=%s) pause_dates=[%s] durable=%s wcfmu=%s',
				$sub_id,
				wp_date( 'Y-m-d H:i', $due_ts ),
				$due_date,
				$delivery_date,
				implode( ',', $all_pause ),
				wp_json_encode( get_post_meta( (int) $sub_id, '_aaraa_pause_dates', true ) ),
				wp_json_encode( get_post_meta( (int) $sub_id, '_wcfmu_pause_dates', true ) )
			)
		);
		if ( ! self::is_pause_date( $sub_id, $delivery_date ) ) {
			self::log( "$sub_id: delivery $delivery_date NOT paused → allowing renewal" );
			return; // Tomorrow's delivery isn't paused — let WCS renew as normal.
		}
		self::log( "$sub_id: delivery $delivery_date IS paused → SKIPPING renewal" );

		// Delivery is paused: block this renewal and push next_payment forward one
		// day at a time (same time of day) until the delivery day is not paused.
		$this->remove_wcs_renewal_handlers();
		$this->advance_next_payment( $sub, $sub_id, $due_ts );

		$sub->add_order_note(
			sprintf(
				/* translators: 1: paused delivery date (Y-m-d), 2: renewal day it would have run (Y-m-d). */
				__( 'Renewal skipped — the %1$s delivery is paused, so the renewal due on %2$s was not created.', 'aaraa-white-label-admin' ),
				$delivery_date,
				$due_date
			)
		);

		// Restore WCS's handlers at the very end of this fire so every other
		// subscription in the same run still renews normally.
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'restore' ), PHP_INT_MAX, 0 );
	}

	/**
	 * Is $date one of the subscription's chosen pause dates (live meta)?
	 *
	 * @param int    $sub_id Subscription id.
	 * @param string $date   Y-m-d.
	 * @return bool
	 */
	public static function is_pause_date( $sub_id, $date ) {
		// Read the durable, plugin-owned pause dates (unioned with the legacy WCFMu
		// key) so a wiped `_wcfmu_pause_dates` never silently disables the guard.
		if ( class_exists( __NAMESPACE__ . '\\Subscription_Delivery' ) ) {
			return in_array( $date, Subscription_Delivery::read_pause_dates( (int) $sub_id ), true );
		}
		$raw = get_post_meta( (int) $sub_id, '_wcfmu_pause_dates', true );
		if ( empty( $raw ) ) {
			return false;
		}
		$dates = class_exists( __NAMESPACE__ . '\\Subscription_API' )
			? Subscription_API::parse_pause_dates( $raw )
			: array();
		return in_array( $date, $dates, true );
	}

	/**
	 * Remove WCS's renewal handlers for the current action fire.
	 *
	 * @return void
	 */
	private function remove_wcs_renewal_handlers() {
		remove_action( 'woocommerce_scheduled_subscription_payment', 'WC_Subscriptions_Manager::maybe_process_failed_renewal_for_repair', 0 );
		remove_action( 'woocommerce_scheduled_subscription_payment', 'WC_Subscriptions_Manager::prepare_renewal', 1 );
		remove_action( 'woocommerce_scheduled_subscription_payment', array( 'WC_Subscriptions_Payment_Gateways', 'gateway_scheduled_subscription_payment' ), 10 );
		$this->removed = true;
	}

	/**
	 * Re-add WCS's renewal handlers so later fires in the same request renew.
	 *
	 * @return void
	 */
	public function restore() {
		if ( ! $this->removed ) {
			return;
		}
		add_action( 'woocommerce_scheduled_subscription_payment', 'WC_Subscriptions_Manager::maybe_process_failed_renewal_for_repair', 0, 1 );
		add_action( 'woocommerce_scheduled_subscription_payment', 'WC_Subscriptions_Manager::prepare_renewal', 1, 1 );
		add_action( 'woocommerce_scheduled_subscription_payment', array( 'WC_Subscriptions_Payment_Gateways', 'gateway_scheduled_subscription_payment' ), 10, 1 );
		$this->removed = false;
		remove_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'restore' ), PHP_INT_MAX );
	}

	/**
	 * Move next_payment forward to the next non-paused, future day so the
	 * subscription keeps billing after a skipped day.
	 *
	 * @param \WC_Subscription $sub    Subscription.
	 * @param int              $sub_id Subscription id.
	 * @param int              $due_ts Current next_payment UTC epoch.
	 * @return void
	 */
	private function advance_next_payment( $sub, $sub_id, $due_ts ) {
		$now    = time();
		$new_ts = $due_ts;
		$guard  = 0;

		// Move forward one calendar day at a time (keeping the same time of day)
		// until the resulting renewal day D delivers (D+1) on a non-paused day that
		// is also a valid delivery-schedule renewal day.
		do {
			$new_ts  += DAY_IN_SECONDS;
			$day      = wp_date( 'Y-m-d', $new_ts );                     // candidate renewal day D.
			$delivery = wp_date( 'Y-m-d', $new_ts + DAY_IN_SECONDS );    // its delivery day D+1.
			$guard++;
			$not_renewal_day = class_exists( __NAMESPACE__ . '\\Renewal_Schedule_Guard' )
				&& ! Renewal_Schedule_Guard::is_renewal_day( $sub_id, $day, $sub );
		} while ( ( $new_ts <= $now || self::is_pause_date( $sub_id, $delivery ) || $not_renewal_day ) && $guard < 120 );

		if ( $new_ts <= $now || $guard >= 120 ) {
			return; // Couldn't find a valid future date — leave it for WCS/reconcile.
		}

		self::log( sprintf( '%d: advancing next_payment to %s', $sub_id, wp_date( 'Y-m-d H:i', $new_ts ) ) );
		try {
			$sub->update_dates( array( 'next_payment' => gmdate( 'Y-m-d H:i:s', $new_ts ) ), 'gmt' );
			$sub->save();
		} catch ( \Exception $e ) {
			// Non-fatal: WCS/reconcile will settle the schedule on the next active day.
		}
	}
}
