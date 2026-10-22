<?php
/**
 * Adds a "Product" column (name × qty — price, one per line) to the
 * `/my-account/orders/` listing table.
 *
 * Unlike the subscriptions listing (see Subscription_Account_Template), the
 * core orders template already exposes both a columns filter
 * (`woocommerce_account_orders_columns`) and a per-column render action
 * (`woocommerce_my_account_my_orders_column_{id}`), so no template override
 * is needed here — the stock `woocommerce/myaccount/orders.php` template
 * calls both directly.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Product column for the My Account orders table.
 */
class Account_Orders_Columns {

	const COLUMN = 'order-product';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_account_orders_columns', array( $this, 'add_column' ) );
		add_action( 'woocommerce_my_account_my_orders_column_' . self::COLUMN, array( $this, 'render_column' ) );
	}

	/**
	 * Insert the Product column right after the order number.
	 *
	 * @param array $columns Column label, keyed by column id.
	 * @return array
	 */
	public function add_column( $columns ) {
		$out = array();
		foreach ( $columns as $id => $label ) {
			$out[ $id ] = $label;
			if ( 'order-number' === $id ) {
				$out[ self::COLUMN ] = __( 'Product', 'aaraa-white-label-admin' );
			}
		}
		return $out;
	}

	/**
	 * Render "Name × qty — price" for each line item, one per line.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function render_column( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			echo '—';
			return;
		}
		$items = $order->get_items();
		if ( ! $items ) {
			echo '—';
			return;
		}
		$rows = array();
		foreach ( $items as $item ) {
			$rows[] = esc_html( $item->get_name() ) . ' × ' . (int) $item->get_quantity() . ' — ' . wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) );
		}
		echo wp_kses_post( implode( '<br>', $rows ) );
	}
}
