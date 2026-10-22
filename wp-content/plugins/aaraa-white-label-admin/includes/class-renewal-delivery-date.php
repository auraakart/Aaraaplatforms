<?php
/**
 * Sets `delivery_date` (order date + 1 day) on every subscription renewal
 * order as soon as it's created.
 *
 * `delivery_date` is the same unprefixed order meta key the ordering app
 * sends at checkout for a normal order (see Order_Delivery::META_DELIVERY_DATE
 * and Orders_API_Fields::DELIVERY_META) — a fresh renewal order never has it
 * set by anything else, so a renewal always gets the D+1 day it's actually
 * funding (a renewal order created "today" delivers "tomorrow", the same
 * rule Renewal_Wallet already uses for its own, unrelated bookkeeping).
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Auto-tags renewal orders with their delivery date.
 */
class Renewal_Delivery_Date {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wcs_renewal_order_created', array( $this, 'set_delivery_date' ), 20, 2 );
	}

	/**
	 * Tag the renewal order with its own date + 1 day.
	 *
	 * @param \WC_Order        $renewal_order Newly created renewal order.
	 * @param \WC_Subscription $subscription  Source subscription (unused; part of the filter's signature).
	 * @return \WC_Order
	 */
	public function set_delivery_date( $renewal_order, $subscription ) {
		if ( ! $renewal_order instanceof \WC_Order ) {
			return $renewal_order;
		}

		$created = $renewal_order->get_date_created();
		if ( ! $created ) {
			return $renewal_order;
		}

		$delivery_date = wp_date( 'Y-m-d', $created->getTimestamp() + DAY_IN_SECONDS );

		$renewal_order->update_meta_data( Order_Delivery::META_DELIVERY_DATE, $delivery_date );
		$renewal_order->save();

		return $renewal_order;
	}
}
