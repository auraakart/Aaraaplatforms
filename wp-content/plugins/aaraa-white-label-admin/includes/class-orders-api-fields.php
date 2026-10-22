<?php
/**
 * Adds `order_type` and `delivery_date` to the standard `GET /wc/v3/orders`
 * response (and single-order GET/POST/PUT responses) — the stock endpoint
 * exposes neither.
 *
 * `order_type` classifies the order the same way Aaraa Customer 360 and the
 * order exports already do: wallet (a wallet recharge/top-up order),
 * renewal, parent (the order that created a subscription), or one_time.
 *
 * `delivery_date` is the day (and, if set, time) the order is scheduled to
 * deliver, read from the unprefixed `delivery_date` order meta — the same
 * key the ordering app already sends as meta_data at checkout (visible in
 * the order's own Custom Fields box) and that the order edit screen's
 * "Aaraa Delivery" panel edits (see Order_Delivery::META_DELIVERY_DATE, the
 * same key). When an order has none, the field falls back to the order's
 * own `date_created`.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Extra fields on the orders REST response.
 */
class Orders_API_Fields {

	/**
	 * Delivery date/time meta — shared with Order_Delivery::META_DELIVERY_DATE
	 * (same key, one source of truth) and with whatever the app already sent.
	 *
	 * @see Order_Delivery::META_DELIVERY_DATE
	 */
	const DELIVERY_META = 'delivery_date';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_fields' ), 10, 3 );
	}

	/**
	 * Append the two fields to the prepared response.
	 *
	 * @param \WP_REST_Response $response Response object.
	 * @param \WC_Order         $order    Order object.
	 * @param \WP_REST_Request  $request  Request object.
	 * @return \WP_REST_Response
	 */
	public function add_fields( $response, $order, $request ) {
		if ( ! $order instanceof \WC_Order ) {
			return $response;
		}

		$response->data['order_type']    = $this->order_type( $order );
		$response->data['delivery_date'] = $this->delivery_date( $order );

		return $response;
	}

	/**
	 * Classify the order: wallet, renewal, parent, or one_time.
	 *
	 * Mirrors Exports::order_type_label() / Customers_Admin::order_type_label(),
	 * as a machine-readable slug instead of a translated label.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	private function order_type( $order ) {
		if ( 'yes' === $order->get_meta( 'wps_wallet_recharge_order' ) ) {
			return 'wallet';
		}
		$recharge_pid = (int) get_option( 'wps_wsfw_rechargeable_product_id', 0 );
		if ( $recharge_pid ) {
			foreach ( $order->get_items() as $item ) {
				if ( method_exists( $item, 'get_product_id' ) && (int) $item->get_product_id() === $recharge_pid ) {
					return 'wallet';
				}
			}
		}

		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
			return 'renewal';
		}
		if ( function_exists( 'wcs_order_contains_resubscribe' ) && wcs_order_contains_resubscribe( $order ) ) {
			return 'resubscribe';
		}
		if ( function_exists( 'wcs_order_contains_switch' ) && wcs_order_contains_switch( $order ) ) {
			return 'switch';
		}
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, 'parent' ) ) {
			return 'parent';
		}
		return 'one_time';
	}

	/**
	 * The order's delivery date/time: its own `delivery_date` meta if
	 * present (date-only, as the app sends it, or a full datetime once
	 * edited on the order screen), else date_created.
	 *
	 * Formatted the same way core formats `date_created` (site-local,
	 * "Y-m-d\TH:i:s"), so the two fields are directly comparable.
	 *
	 * @param \WC_Order $order Order.
	 * @return string|null
	 */
	private function delivery_date( $order ) {
		$raw = trim( (string) $order->get_meta( self::DELIVERY_META ) );
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}):(\d{2}))?/', $raw, $m ) ) {
			$time = isset( $m[2] ) ? $m[2] . ':' . $m[3] . ':00' : '00:00:00';
			return wc_rest_prepare_date_response( $m[1] . ' ' . $time, false );
		}
		return wc_rest_prepare_date_response( $order->get_date_created(), false );
	}
}
