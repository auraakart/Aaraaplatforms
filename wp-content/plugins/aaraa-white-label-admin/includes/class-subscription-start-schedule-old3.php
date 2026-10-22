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
 * POST /wc/v3/orders), a subscription created by hand on the admin edit
 * screen ("Subscriptions -> Add Subscription") via the same
 * woocommerce_process_shop_subscription_meta action Order_Delivery already
 * uses, AND the app's own POST /wp-json/subscriptions endpoint
 * (Subscription_API::create() -> the theme's
 * create_custom_subscription_with_multiple_products(), functions.php),
 * which builds the subscription directly with WCS's low-level
 * wcs_create_subscription() helper and never fires any of the three hooks
 * above. That helper itself fires a real, undocumented-elsewhere hook —
 * `do_action( 'wcs_create_subscription', $subscription )` in
 * wcs-functions.php — right after set_parent_id()/set_start_date()/save(),
 * so it's hooked here too as the fourth path. (This is also the closest
 * match to a `wcs_subscription_created` hook some of this store's older
 * theme code assumes exists — it doesn't; this is the real name.) Because
 * it's the same shared constructor checkout and the REST controller call
 * internally, it fires an extra time on those paths as well; that's
 * harmless; the later, path-specific hooks below still run afterwards and
 * win, and set_parent_order_dates()/set_next_payment_same_day() are both
 * pure functions of start_ts, so re-applying them twice never drifts.
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
 *    it back to "= start" on every save would fight that. So it only
 *    re-aligns while WCS's OWN `can_date_be_updated( 'next_payment' )` check
 *    says a manual date edit is still legitimate right now (pending status,
 *    or a gateway that supports date changes) — the same gate WCS uses to
 *    decide whether to even show Next Payment as an editable field. Once a
 *    subscription is genuinely live and renewing on a gateway that doesn't
 *    support date changes, that gate closes and this stops touching it.
 *
 * WCS also locks the admin Start Date field to plain read-only text once a
 * subscription's status leaves auto-draft/pending (`can_date_be_updated()`
 * in class-wc-subscription.php) — by design, changing when a live
 * subscription "started" is unusual. This store's workflow needs it
 * editable for longer (create, then correct Start Date on a later save), so
 * `init()` also hooks WCS's own `woocommerce_subscription_can_date_be_updated`
 * filter to keep Start Date editable in wp-admin no matter the status,
 * instead of patching the (third-party, update-managed) WCS plugin file
 * itself.
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
		// Keep Start Date editable in wp-admin past auto-draft/pending, so a
		// subscription created with the wrong start can still be corrected on
		// a later save instead of being locked to plain read-only text.
		add_filter( 'woocommerce_subscription_can_date_be_updated', array( $this, 'allow_admin_start_date_edit' ), 20, 3 );
		// WCS's own low-level constructor — covers the app's
		// POST /wp-json/subscriptions endpoint (see class docblock), and
		// doubles up harmlessly as a safety net on checkout/REST.
		add_action( 'wcs_create_subscription', array( $this, 'on_wcs_create_subscription' ), 20, 1 );
		// Hide WCS's own native Start Date row — see hide_native_start_date_row().
		add_action( 'admin_footer', array( $this, 'hide_native_start_date_row' ) );
	}

	/**
	 * Hide WCS's native "Start Date" row in the Schedule metabox.
	 *
	 * That widget's own JS (meta-boxes-subscription.js) hard-codes
	 * `maxDate: today` on the jQuery UI datepicker and re-clamps the
	 * DISPLAYED value to today every time the page finishes loading,
	 * regardless of what's actually saved — purely cosmetic, but it reads
	 * as "the start date reverted" and has caused repeated confusion. The
	 * underlying value is untouched (this only hides the row with CSS; the
	 * hidden `start_timestamp_utc` field it contains keeps submitting
	 * normally, hidden fields aren't affected by display:none), and
	 * Subscription_Delivery's own "Start Date" field is now the
	 * authoritative, un-clamped place to view/edit it.
	 *
	 * @return void
	 */
	public function hide_native_start_date_row() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$target = function_exists( 'wcs_get_page_screen_id' ) ? wcs_get_page_screen_id( 'shop_subscription' ) : 'shop_subscription';
		if ( ! $screen || $screen->id !== $target ) {
			return;
		}
		?>
		<style>
			#subscription-start-date { display: none !important; }
		</style>
		<?php
	}

	/**
	 * wcs_create_subscription() hook handler — see the class docblock.
	 *
	 * @param \WC_Subscription $subscription New subscription.
	 * @return void
	 */
	public function on_wcs_create_subscription( $subscription ) {
		self::apply( $subscription );
	}

	/**
	 * See init() — widen Start Date's editability to any wp-admin save, not
	 * just while a subscription is auto-draft/pending.
	 *
	 * @param bool             $can_update Whether the date can currently be updated.
	 * @param string           $date_type  Date type being checked.
	 * @param \WC_Subscription $subscription Subscription (unused).
	 * @return bool
	 */
	public function allow_admin_start_date_edit( $can_update, $date_type, $subscription ) {
		if ( 'start' === $date_type && is_admin() ) {
			return true;
		}
		return $can_update;
	}

	/**
	 * Checkout hook handler.
	 *
	 * @param \WC_Subscription $subscription New subscription.
	 * @return void
	 */
	public function on_checkout_created( $subscription ) {
		self::apply( $subscription );
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
		self::apply( $subscription );
	}

	/**
	 * Admin "Add Subscription" screen hook handler — see the class docblock
	 * for why the parent-order rule re-applies on every save here while
	 * next_payment only ever applies on the subscription's very first save.
	 *
	 * `woocommerce_process_shop_subscription_meta` fires on EVERY wp-admin
	 * save of a subscription, not just its creation, and
	 * `can_date_be_updated( 'next_payment' )` is true for most active
	 * subscriptions too (pending, or any gateway supporting date changes) —
	 * so gating on that check alone (an earlier version of this method did)
	 * meant next_payment got silently forced back to "= start" on every
	 * later save, clobbering an admin's own deliberate edit to it (via the
	 * native Schedule box or the Aaraa panel's Next Renewal Date field).
	 * `original_post_status === 'auto-draft'` (the same check WCS's own
	 * save() uses) is what actually means "this save is the subscription's
	 * creation" — next_payment is only ever touched here on that one save.
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

		self::set_parent_order_dates( $subscription, $start_ts );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check; WCS's own save() (which fires this action) has already verified its nonce for this same request.
		$is_creating = isset( $_POST['original_post_status'] ) && 'auto-draft' === $_POST['original_post_status'];
		if ( $is_creating && $subscription->can_date_be_updated( 'next_payment' ) ) {
			self::set_next_payment_same_day( $subscription, $start_ts, true );
		}
	}

	/**
	 * Apply the two date rules to a newly created subscription (checkout / REST).
	 *
	 * Also exposed as the reusable, public entry point for anything that
	 * changes a subscription's start date after creation and needs the same
	 * realignment — e.g. Subscription_Delivery's own "Start Date" field,
	 * which exists because WCS's admin Start Date widget hard-codes a
	 * `maxDate` of "today" client-side (see meta-boxes-subscription.js) and
	 * silently clamps any future value back to today, making it unusable
	 * for this store's future-dated subscriptions.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @return void
	 */
	public static function realign_from_start( $subscription ) {
		self::apply( $subscription );
	}

	/**
	 * Apply the two date rules to a newly created subscription (checkout / REST).
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @return void
	 */
	private static function apply( $subscription ) {
		if ( ! $subscription instanceof \WC_Subscription ) {
			return;
		}

		$start_ts = (int) $subscription->get_time( 'start', 'gmt' );
		if ( ! $start_ts ) {
			return;
		}

		self::set_next_payment_same_day( $subscription, $start_ts );
		self::set_parent_order_dates( $subscription, $start_ts );
	}

	/**
	 * next_payment = the same calendar day as start (start + 1 second,
	 * clamped to 23:59:59 that day so it can never roll into the next one).
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param int              $start_ts     Start date, GMT unix timestamp.
	 * @param bool             $idempotent   Skip the write if next_payment's calendar day already matches start's (site-local) — avoids re-saving on every admin request once already aligned.
	 * @return void
	 */
	private static function set_next_payment_same_day( $subscription, $start_ts, $idempotent = false ) {
		if ( $idempotent ) {
			$current_next_ts = (int) $subscription->get_time( 'next_payment', 'gmt' );
			if ( $current_next_ts && wp_date( 'Y-m-d', $current_next_ts ) === wp_date( 'Y-m-d', $start_ts ) ) {
				return; // Already aligned.
			}
		}

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
	private static function set_parent_order_dates( $subscription, $start_ts ) {
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
