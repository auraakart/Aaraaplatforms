<?php
/**
 * Register a custom "Pause" subscription status for WooCommerce Subscriptions.
 *
 * WCS exposes two extension points for custom statuses:
 *   - `wcs_subscription_statuses` — the key => label map used by the edit-screen
 *     status dropdown and the admin status list.
 *   - `woocommerce_subscriptions_registered_statuses` — read by WCS on
 *     init:9 to register_post_status() each custom status.
 * Transitions are gated per-status by `woocommerce_can_subscription_be_updated_to_{status}`.
 *
 * Registering `wc-pause` here also activates the existing pause feature in
 * [[subscription-delivery]] — its pause_status_available() checks for exactly
 * this post status and only then routes pause/resume through the real status.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * "Pause" subscription status.
 */
class Subscription_Status {

	/**
	 * Registered post status (with the wc- prefix).
	 */
	const STATUS = 'wc-pause';

	/**
	 * Status key WCS uses internally (no wc- prefix).
	 */
	const KEY = 'pause';

	/**
	 * Register hooks.
	 *
	 * The plugin boots before `init`, so these filters are in place before WCS
	 * registers post statuses on init priority 9.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wcs_subscription_statuses', array( $this, 'add_status' ) );
		add_filter( 'woocommerce_subscriptions_registered_statuses', array( $this, 'register_status' ) );
		add_filter( 'woocommerce_can_subscription_be_updated_to_pause', array( $this, 'allow_pause' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_active', array( $this, 'allow_resume' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_on-hold', array( $this, 'allow_resume' ), 10, 2 );
	}

	/**
	 * Add "Pause" to the subscription status list (dropdown + labels), placed
	 * right after "On hold".
	 *
	 * @param array<string,string> $statuses key => label.
	 * @return array<string,string>
	 */
	public function add_status( $statuses ) {
		if ( isset( $statuses[ self::STATUS ] ) ) {
			return $statuses;
		}

		$label    = _x( 'Pause', 'Subscription status', 'aaraa-white-label-admin' );
		$reordered = array();

		foreach ( $statuses as $key => $value ) {
			$reordered[ $key ] = $value;
			if ( 'wc-on-hold' === $key ) {
				$reordered[ self::STATUS ] = $label;
			}
		}

		if ( ! isset( $reordered[ self::STATUS ] ) ) {
			$reordered[ self::STATUS ] = $label;
		}

		return $reordered;
	}

	/**
	 * Have WCS register_post_status() the pause status so it becomes a real,
	 * selectable status that shows in the admin status list.
	 *
	 * @param array<string,mixed> $registered key => label_count noop.
	 * @return array<string,mixed>
	 */
	public function register_status( $registered ) {
		$registered[ self::STATUS ] = _nx_noop(
			'Pause <span class="count">(%s)</span>',
			'Pause <span class="count">(%s)</span>',
			'post status label including post count',
			'aaraa-white-label-admin'
		);

		return $registered;
	}

	/**
	 * Permit pausing a subscription that is not in a terminal state.
	 *
	 * Anything except cancelled / expired / switched can be paused — including
	 * pending (freshly created via the API) and pending-cancel — so the pause
	 * endpoint does not fatal on those statuses.
	 *
	 * @param bool             $can          Whether the transition is allowed.
	 * @param \WC_Subscription $subscription The subscription.
	 * @return bool
	 */
	public function allow_pause( $can, $subscription ) {
		if ( is_a( $subscription, 'WC_Subscription' )
			&& $subscription->has_status( array( 'active', 'on-hold', 'pending', 'pending-cancel', self::KEY ) ) ) {
			return true;
		}

		return $can;
	}

	/**
	 * Permit resuming a paused subscription — to `active` when the wallet covers
	 * the next renewal, or to `on-hold` when it does not. Both targets are gated
	 * by this same rule (WCS otherwise blocks a transition out of the custom
	 * `pause` status), so resume never fatals on an insufficient balance.
	 *
	 * @param bool             $can          Whether the transition is allowed.
	 * @param \WC_Subscription $subscription The subscription.
	 * @return bool
	 */
	public function allow_resume( $can, $subscription ) {
		if ( is_a( $subscription, 'WC_Subscription' ) && $subscription->has_status( self::KEY ) ) {
			return true;
		}

		return $can;
	}
}
