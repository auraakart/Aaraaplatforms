<?php
/**
 * Renewal delivery-schedule guard.
 *
 * Ensures a subscription's renewal order is only generated on a day that matches
 * its delivery schedule ( `_wcfm_delivery_schedule` / `_wcfm_delivery_days` ).
 *
 * The delivery person prepares one day BEFORE the delivery, so a renewal order
 * for a delivery day D must be created on D-1 (the "prep" / renewal day). This
 * guard maps each schedule type to the set of valid *renewal* days and, when a
 * scheduled renewal fires on a day that is not one of them, skips that fire and
 * advances `next_payment` to the next valid renewal day:
 *
 *   daily      — every day is a renewal day (guard never skips).
 *   alternate  — every other day, anchored on the subscription start date.
 *   weekend    — delivery Sat+Sun  => renewal Fri+Sat  (each delivery day - 1).
 *   custom     — delivery = chosen days => renewal = each chosen day - 1.
 *
 * It composes with {@see Renewal_Pause_Guard}: the day it advances to is always
 * both a valid renewal day AND not a pause date, and the pause guard's own
 * advance likewise honours this schedule. This guard runs before the pause guard
 * (priority -1100 < -1000).
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Skips renewals that fall on a day the delivery schedule does not renew.
 */
class Renewal_Schedule_Guard {

	/**
	 * Whether we removed WCS's renewal handlers for the current fire.
	 *
	 * @var bool
	 */
	private $removed = false;

	/**
	 * Hook the guard before WCS's own renewal handlers and before the pause guard.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'maybe_skip' ), -1100, 1 );
	}

	/**
	 * If the due day is not a valid renewal day for the schedule, skip the renewal.
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
		if ( self::is_renewal_day( $sub_id, $due_date, $sub ) ) {
			return; // Valid renewal day — let it proceed (the pause guard may still skip).
		}

		// Not a renewal day for this schedule: block this fire and keep moving.
		$this->remove_wcs_renewal_handlers();
		$this->advance_next_payment( $sub, $sub_id, $due_ts );

		$sub->add_order_note(
			sprintf(
				/* translators: 1: skipped date (Y-m-d), 2: schedule type. */
				__( 'Renewal skipped — %1$s is not a delivery-schedule renewal day (schedule: %2$s).', 'aaraa-white-label-admin' ),
				$due_date,
				self::schedule_summary( $sub_id )
			)
		);

		// Restore WCS's handlers at the very end of this fire so every other
		// subscription in the same run still renews normally.
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'restore' ), PHP_INT_MAX, 0 );
	}

	/**
	 * Is $date a valid renewal (prep) day for the subscription's delivery schedule?
	 *
	 * @param int              $sub_id Subscription id.
	 * @param string           $date   Y-m-d (site timezone).
	 * @param \WC_Subscription $sub    Optional loaded subscription (for the anchor).
	 * @return bool
	 */
	public static function is_renewal_day( $sub_id, $date, $sub = null ) {
		$type = (string) get_post_meta( (int) $sub_id, '_wcfm_delivery_schedule', true );

		// No schedule set, or plain daily delivery: every day renews.
		if ( '' === $type || 'daily' === $type ) {
			return true;
		}

		$ts = strtotime( $date . ' 00:00:00' );
		if ( ! $ts ) {
			return true;
		}

		if ( 'alternate' === $type ) {
			$anchor = self::start_date( $sub_id, $sub );
			if ( '' === $anchor ) {
				return true; // No anchor to reason about — don't block.
			}
			$diff = (int) round( ( strtotime( $date . ' 00:00:00' ) - strtotime( $anchor . ' 00:00:00' ) ) / DAY_IN_SECONDS );
			return 0 === ( abs( $diff ) % 2 );
		}

		// weekend / custom: renewal weekday = (delivery weekday - 1) mod 7.
		$days = self::delivery_days( $sub_id, $type );
		if ( empty( $days ) ) {
			return true; // Nothing to constrain against.
		}
		$renewal_weekdays = array();
		foreach ( $days as $d ) {
			$renewal_weekdays[] = ( (int) $d + 6 ) % 7; // d - 1, wrapping Sun(0) -> Sat(6).
		}
		$weekday = (int) gmdate( 'w', strtotime( $date . ' 00:00:00 +0000' ) );
		return in_array( $weekday, $renewal_weekdays, true );
	}

	/**
	 * Delivery weekday indices (PHP `w`, 0 = Sun) for the schedule.
	 *
	 * @param int    $sub_id Subscription id.
	 * @param string $type   Schedule type.
	 * @return int[]
	 */
	private static function delivery_days( $sub_id, $type ) {
		if ( 'weekend' === $type ) {
			return array( 0, 6 );
		}
		$days = get_post_meta( (int) $sub_id, '_wcfm_delivery_days', true );
		if ( ! is_array( $days ) ) {
			return array();
		}
		$out = array();
		foreach ( $days as $d ) {
			$d = (int) $d;
			if ( $d >= 0 && $d <= 6 ) {
				$out[] = $d;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * The subscription's start day (Y-m-d, site timezone) — the alternate anchor.
	 *
	 * @param int              $sub_id Subscription id.
	 * @param \WC_Subscription $sub    Optional loaded subscription.
	 * @return string Y-m-d, or '' if unavailable.
	 */
	private static function start_date( $sub_id, $sub = null ) {
		if ( ! $sub && function_exists( 'wcs_get_subscription' ) ) {
			$sub = wcs_get_subscription( $sub_id );
		}
		if ( ! $sub ) {
			return '';
		}
		$ts = (int) $sub->get_time( 'start', 'gmt' );
		if ( ! $ts ) {
			$ts = (int) $sub->get_time( 'date_created', 'gmt' );
		}
		return $ts ? wp_date( 'Y-m-d', $ts ) : '';
	}

	/**
	 * Short schedule label for the order note.
	 *
	 * @param int $sub_id Subscription id.
	 * @return string
	 */
	private static function schedule_summary( $sub_id ) {
		$type = (string) get_post_meta( (int) $sub_id, '_wcfm_delivery_schedule', true );
		return '' === $type ? 'daily' : $type;
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
	 * Advance next_payment to the next future day that is both a valid renewal day
	 * for the schedule AND not a pause date, so billing keeps flowing.
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
		$ok     = false;

		do {
			$new_ts += DAY_IN_SECONDS;
			$day     = wp_date( 'Y-m-d', $new_ts );
			$guard++;
			$is_pause = class_exists( __NAMESPACE__ . '\\Renewal_Pause_Guard' )
				&& Renewal_Pause_Guard::is_pause_date( $sub_id, $day );
			$ok = ( $new_ts > $now )
				&& self::is_renewal_day( $sub_id, $day, $sub )
				&& ! $is_pause;
		} while ( ! $ok && $guard < 120 );

		if ( ! $ok ) {
			return; // Couldn't find a valid future day — leave it for WCS/reconcile.
		}

		try {
			$sub->update_dates( array( 'next_payment' => gmdate( 'Y-m-d H:i:s', $new_ts ) ), 'gmt' );
			$sub->save();
		} catch ( \Exception $e ) {
			// Non-fatal: WCS/reconcile will settle the schedule on the next valid day.
		}
	}
}
