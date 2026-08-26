<?php
/**
 * Renewal duplicate guard.
 *
 * Guarantees a subscription creates at most ONE renewal order per calendar day
 * (site local). Milk delivery renews once a day, so a second renewal on the same
 * day is always a duplicate — a double charge — regardless of how it was made:
 * WCS's scheduled payment firing twice, the pause/un-pause wallet reconciler, the
 * "Create pending renewal order" admin action, or a legacy theme handler.
 *
 * It hooks `wcs_renewal_order_created` early (priority 5, before the wallet debit
 * at 30). When a renewal is created and the subscription already has another
 * renewal dated today, the new order is flagged so the wallet never debits it and
 * is immediately cancelled with an explanatory note — the first renewal of the
 * day stands, every later one is voided.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Cancels a second+ renewal order created for a subscription on the same day.
 */
class Renewal_Duplicate_Guard {

	/** Order meta: records the pre-existing renewal this duplicate collided with. */
	const VOID_META = '_aaraa_duplicate_renewal';

	/**
	 * Hook the guard before the wallet debit.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wcs_renewal_order_created', array( $this, 'block_duplicate' ), 5, 2 );
	}

	/**
	 * Cancel a renewal that duplicates one already created for the subscription
	 * today, before the wallet handler (priority 30) can charge it.
	 *
	 * @param \WC_Order        $renewal_order The freshly-created renewal.
	 * @param \WC_Subscription $subscription  Source subscription.
	 * @return \WC_Order The renewal order (this is a filter).
	 */
	public function block_duplicate( $renewal_order, $subscription ) {
		if ( ! $renewal_order instanceof \WC_Order || ! is_a( $subscription, 'WC_Subscription' ) ) {
			return $renewal_order;
		}

		$created = $renewal_order->get_date_created();
		$day     = $created ? wp_date( 'Y-m-d', $created->getTimestamp() ) : current_time( 'Y-m-d' );
		$this_id = $renewal_order->get_id();

		// Look for another renewal of THIS subscription created on the same day.
		// A cancelled/trashed order is ignored (it may be a previously-voided
		// duplicate); anything else — including a refunded one — counts as the
		// day's renewal, so only one is ever charged.
		$existing = 0;
		foreach ( (array) $subscription->get_related_orders( 'ids', 'renewal' ) as $oid ) {
			$oid = (int) $oid;
			if ( $oid === $this_id ) {
				continue;
			}
			$other = wc_get_order( $oid );
			if ( ! $other instanceof \WC_Order ) {
				continue;
			}
			if ( $other->has_status( array( 'cancelled', 'trash' ) ) ) {
				continue;
			}
			$oc = $other->get_date_created();
			if ( $oc && wp_date( 'Y-m-d', $oc->getTimestamp() ) === $day ) {
				$existing = $oid;
				break;
			}
		}

		if ( ! $existing ) {
			return $renewal_order; // First renewal of the day — allowed.
		}

		// Duplicate: stop the wallet debit (DONE_META short-circuits Renewal_Wallet)
		// and cancel the order so it can never be paid.
		if ( class_exists( __NAMESPACE__ . '\\Renewal_Wallet' ) ) {
			$renewal_order->update_meta_data( Renewal_Wallet::DONE_META, 'duplicate' );
		}
		$renewal_order->update_meta_data( self::VOID_META, $existing );
		$renewal_order->add_order_note(
			sprintf(
				/* translators: 1: date, 2: subscription number, 3: existing renewal order id */
				__( 'Duplicate renewal for %1$s — subscription #%2$s already has renewal #%3$s today. Auto-cancelled to prevent a double charge.', 'aaraa-white-label-admin' ),
				$day,
				$subscription->get_order_number(),
				$existing
			)
		);
		if ( ! $renewal_order->has_status( 'cancelled' ) ) {
			$renewal_order->update_status( 'cancelled', __( 'Duplicate renewal auto-cancelled — one renewal per day.', 'aaraa-white-label-admin' ) );
		}
		$renewal_order->save();

		return $renewal_order;
	}
}
