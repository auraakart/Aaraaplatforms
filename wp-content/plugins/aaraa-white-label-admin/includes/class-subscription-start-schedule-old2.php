<?php
/**
 * On every new subscription — from checkout or the REST API alike — aligns
 * the subscription's own next_payment and its parent order's dates to the
 * subscription's start date, per this store's daily-delivery convention
 * (an order placed on day D preps the delivery for day D+1):
 *
 *   - next_payment_date_gmt : same calendar day as start_date_gmt (the very
 *     first renewal isn't "one interval later" like WCS defaults to — it
 *     fires the same day, so a renewal order exists to prep the day AFTER
 *     start).
 *   - Parent order's date_created : start_date_gmt minus 1 day (the parent
 *     order is what preps the very first delivery, on the start date
 *     itself).
 *   - Parent order's `delivery_date` meta : start_date_gmt (see
 *     Order_Delivery::META_DELIVERY_DATE / Orders_API_Fields — the same
 *     unprefixed key everything else already reads).
 *
 * Covers every creation path: storefront checkout, the REST API (both a
 * direct POST /wc/v3/subscriptions and a subscription auto-created from
 * POST /wc/v3/orders), and a subscription created by hand on the admin edit
 * screen ("Subscriptions -> Add Subscription") via the same
 * woocommerce_process_shop_subscription_meta action Order_Delivery already
 * uses.
 *
 * The two rules aren't gated identically on that last (admin) path, because
 * "the admin created it, then immediately corrected Start Date on a second
 * save" is a normal workflow there and each rule tolerates that differently:
 *  - The parent order is a pure function of start (date_created = start - 1,
 *    delivery_date = start), so it's safe — and necessary — to re-align on
 *    EVERY admin save, not just the first: if Start Date is edited later,
 *    the parent order follows it. It's a no-op once already aligned.
 *  - next_payment, once a subscription is live, is actively managed by
 *    admins/WCS as it renews (pauses, wallet holds, manual pushes) — forcing
 *    it back to "= start" on every save would fight that. So it's only ever
 *    set here on the subscription's very first save (original_post_status =
 *    'auto-draft', the same check WCS's own save handler uses to detect a
 *    brand new subscription).
 *
 * WooCommerce Subscriptions itself refuses a next_payment equal to start to
 * the exact second ("must occur after the start date"), so next_payment is
 * set to start + 1 second — the earliest instant that is still the same
 * calendar day (clamped to 23:59:59 on the rare chance start itself is
 * already that second).
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Aligns next_payment and the parent order to a new subscription's start date.
 */
class Subscription_Start_Schedule {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Storefront checkout — fires once the subscription is fully built.
		add_action( 'woocommerce_checkout_subscription_created', array( $this, 'on_checkout_created' ), 20, 1 );
		// REST API — fires for both a direct POST /wc/v3/subscriptions and a
		// subscription auto-created from POST /wc/v3/orders; $creating is
		// false on an update, which this ignores.
		add_action( 'woocommerce_rest_insert_shop_subscription_object', array( $this, 'on_rest_created' ), 20, 3 );
		// Admin "Add Subscription" screen — fires from WITHIN WCS's own save
		// handler, after it has already applied the form's dates/status/items,
		// so $subscription's start date here is whatever the admin entered.
		add_action( 'woocommerce_process_shop_subscription_meta', array( $this, 'on_admin_created' ), 20, 2 );
	}

	/**
	 * Checkout hook handler.
	 *
	 * @param \WC_Subscription $subscription New subscription.
	 * @return void
	 */
	public function on_checkout_created( $subscription ) {
		$this->apply( $subscription );
	}

	/**
	 * REST API hook handler.
	 *
	 * @param \WC_Subscription  $subscription New (or updated) subscription.
	 * @param \WP_REST_Request  $request      Request object (unused).
	 * @param bool              $creating     True when creating, false when updating.
	 * @return void
	 */
	public function on_rest_created( $subscription, $request, $creating ) {
		if ( ! $creating ) {
			return;
		}
		$this->apply( $subscription );
	}

	/**
	 * Admin "Add Subscription" screen hook handler — see the class docblock
	 * for why the parent-order rule re-applies on every save here while
	 * next_payment only applies on the very first one.
	 *
	 * @param int              $subscription_id Subscription id.
	 * @param \WC_Subscription $subscription    Subscription (WCS passes this; may be null on some callers).
	 * @return void
	 */
	public function on_admin_created( $subscription_id, $subscription = null ) {
		if ( ! $subscription instanceof \WC_Subscription && function_exists( 'wcs_get_subscription' ) ) {
			$subscription = wcs_get_subscription( $subscription_id );
		}
		if ( ! $subscription instanceof \WC_Subscription ) {
			return;
		}

		$start_ts = (int) $subscription->get_time( 'start', 'gmt' );
		if ( ! $start_ts ) {
			return;
		}

		$this->set_parent_order_dates( $subscription, $start_ts );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check; WCS's own save() (which fires this action) has already verified its nonce for this same request.
		if ( isset( $_POST['original_post_status'] ) && 'auto-draft' === $_POST['original_post_status'] ) {
			$this->set_next_payment_same_day( $subscription, $start_ts );
		}
	}

	/**
	 * Apply the two date rules to a newly created subscription (checkout / REST).
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @return void
	 */
	private function apply( $subscription ) {
		if ( ! $subscription instanceof \WC_Subscription ) {
			return;
		}

		$start_ts = (int) $subscription->get_time( 'start', 'gmt' );
		if ( ! $start_ts ) {
			return;
		}

		$this->set_next_payment_same_day( $subscription, $start_ts );
		$this->set_parent_order_dates( $subscription, $start_ts );
	}

	/**
	 * next_payment = the same calendar day as start (start + 1 second,
	 * clamped to 23:59:59 that day so it can never roll into the next one).
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param int              $start_ts     Start date, GMT unix timestamp.
	 * @return void
	 */
	private function set_next_payment_same_day( $subscription, $start_ts ) {
		$next_ts = $start_ts + 1;
		if ( gmdate( 'Y-m-d', $next_ts ) !== gmdate( 'Y-m-d', $start_ts ) ) {
			$next_ts = strtotime( gmdate( 'Y-m-d', $start_ts ) . ' 23:59:59 UTC' );
		}

		try {
			$subscription->update_dates( array( 'next_payment' => gmdate( 'Y-m-d H:i:s', $next_ts ) ), 'gmt' );
			$subscription->save();
		} catch ( \Exception $e ) {
			// Non-fatal — leave WCS's own default next_payment in place.
		}
	}

	/**
	 * Parent order date_created = start - 1 day; delivery_date = start day.
	 * Idempotent: a no-op (no write) once the order already matches, so it's
	 * cheap and safe to call on every admin save, not just creation — which
	 * is what lets it follow a Start Date edited after the subscription
	 * already existed.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param int              $start_ts     Start date, GMT unix timestamp.
	 * @return void
	 */
	private function set_parent_order_dates( $subscription, $start_ts ) {
		$parent = $subscription->get_parent();
		if ( ! $parent instanceof \WC_Order ) {
			return;
		}

		$target_created_ts = $start_ts - DAY_IN_SECONDS;
		// delivery_date is a site-local calendar day everywhere else it's
		// written (Renewal_Delivery_Date, the Aaraa Delivery panel), so
		// convert the GMT instant to local here too, not gmdate().
		$target_delivery = wp_date( 'Y-m-d', $start_ts );

		$current_created = $parent->get_date_created();
		$current_created_ts = $current_created ? $current_created->getTimestamp() : 0;
		$current_delivery   = class_exists( __NAMESPACE__ . '\\Order_Delivery' )
			? (string) $parent->get_meta( Order_Delivery::META_DELIVERY_DATE )
			: $target_delivery; // Order_Delivery unavailable — treat as already aligned, skip that part.

		if ( $current_created_ts === $target_created_ts && $current_delivery === $target_delivery ) {
			return; // Already aligned.
		}

		$parent->set_date_created( $target_created_ts );

		if ( class_exists( __NAMESPACE__ . '\\Order_Delivery' ) ) {
			$parent->update_meta_data( Order_Delivery::META_DELIVERY_DATE, $target_delivery );
		}

		$parent->save();
	}
}
