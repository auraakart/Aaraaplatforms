<?php
/**
 * Allow a custom creation date on REST-created WooCommerce orders.
 *
 * The core WooCommerce REST API marks `date_created` as read-only, so any value
 * passed to POST /wp-json/wc/v3/orders is ignored and the order is stamped with
 * the current server time. This module honours a `date_created` value supplied
 * in the create payload, formatted as "DD-MM-YYYY HH:MM" (or with seconds), and
 * interpreted in the site timezone. An empty, missing or malformed value falls
 * back to the current datetime.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Backdate support for REST order creation.
 */
class Order_Date_API {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_rest_pre_insert_shop_order_object', array( $this, 'apply_date' ), 10, 3 );
	}

	/**
	 * Set the order creation date from the request before it is saved.
	 *
	 * @param \WC_Order        $order    Order object being created/updated.
	 * @param \WP_REST_Request $request  Incoming REST request.
	 * @param bool             $creating True when the order is being created.
	 * @return \WC_Order
	 */
	public function apply_date( $order, $request, $creating ) {
		if ( ! $creating || ! $order instanceof \WC_Order ) {
			return $order;
		}

		$raw = isset( $request['date_created'] ) ? trim( (string) $request['date_created'] ) : '';

		if ( '' === $raw ) {
			$order->set_date_created( time() ); // Empty/omitted -> current datetime.
			return $order;
		}

		// Accept "DD-MM-YYYY HH:MM" or "DD-MM-YYYY HH:MM:SS", in the site timezone.
		$dt = \DateTime::createFromFormat( 'd-m-Y H:i:s', $raw, wp_timezone() );
		if ( ! $dt ) {
			$dt = \DateTime::createFromFormat( 'd-m-Y H:i', $raw, wp_timezone() );
		}

		// set_date_created() expects a GMT timestamp; getTimestamp() already
		// accounts for the timezone the string was parsed in. Malformed -> now.
		$order->set_date_created( $dt ? $dt->getTimestamp() : time() );

		return $order;
	}
}
