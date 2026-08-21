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
	}

	/**
	 * If the subscription's due day is a pause date, skip the renewal.
	 *
	 * @param int $sub_id Subscription id.
	 * @return void
	 */
	public function maybe_skip( $sub_id ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}
		$sub = wcs_get_subscription( $sub_id );
		if ( ! $sub ) {
			return;
		}

		$due_ts = (int) $sub->get_time( 'next_payment', 'gmt' );
		if ( ! $due_ts ) {
			return;
		}

		// The calendar day being renewed, in the site timezone (IST).
		$due_date = wp_date( 'Y-m-d', $due_ts );
		if ( ! self::is_pause_date( $sub_id, $due_date ) ) {
			return; // Not a pause day — let WCS renew as normal.
		}

		// Pause day: block this renewal and keep the schedule moving.
		$this->remove_wcs_renewal_handlers();
		$this->advance_next_payment( $sub, $sub_id, $due_ts );

		$sub->add_order_note(
			sprintf(
				/* translators: %s: paused date (Y-m-d). */
				__( 'Renewal skipped — %s is a pause date (no renewal order created).', 'aaraa-white-label-admin' ),
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
		$interval = max( 1, (int) $sub->get_billing_interval() );
		$period   = $sub->get_billing_period();
		$period   = $period ? $period : 'day';
		$now      = time();
		$new_ts   = $due_ts;
		$guard    = 0;

		do {
			$new_ts = function_exists( 'wcs_add_time' )
				? (int) wcs_add_time( $interval, $period, $new_ts )
				: $new_ts + DAY_IN_SECONDS;
			$day = wp_date( 'Y-m-d', $new_ts );
			$guard++;
			// The landing day must also be a valid delivery-schedule renewal day,
			// so the two guards agree on where billing resumes.
			$not_renewal_day = class_exists( __NAMESPACE__ . '\\Renewal_Schedule_Guard' )
				&& ! Renewal_Schedule_Guard::is_renewal_day( $sub_id, $day, $sub );
		} while ( ( $new_ts <= $now || self::is_pause_date( $sub_id, $day ) || $not_renewal_day ) && $guard < 120 );

		if ( $new_ts <= $now ) {
			return; // Couldn't find a valid future date — leave it for WCS/reconcile.
		}

		try {
			$sub->update_dates( array( 'next_payment' => gmdate( 'Y-m-d H:i:s', $new_ts ) ), 'gmt' );
			$sub->save();
		} catch ( \Exception $e ) {
			// Non-fatal: WCS/reconcile will settle the schedule on the next active day.
		}
	}
}
