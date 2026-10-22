<?php
/**
 * Show the related subscription on the Orders admin.
 *
 * Adds a "Subscription" column to the WooCommerce orders list table (HPOS
 * admin.php?page=wc-orders and the legacy edit.php?post_type=shop_order) that
 * links to the subscription an order belongs to. Covers both the parent order
 * that created the subscription and every renewal order.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription-id column for the orders list.
 */
class Order_Subscription_Column {

	const COLUMN = 'aaraa_subscription';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		// HPOS orders list table (admin.php?page=wc-orders).
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_column' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Legacy CPT orders list table (edit.php?post_type=shop_order).
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Stop the row-selection checkbox from opening the order (see below).
		add_action( 'admin_footer', array( $this, 'checkbox_no_link_script' ) );
	}

	/**
	 * Keep the row-selection checkbox from navigating to the order.
	 *
	 * WooCommerce's own wc-orders.js makes the whole order row clickable and only
	 * skips navigation when the click target matches `a, a *, .no-link, .no-link
	 * *, button, button *`. A checkbox is an <input>, which isn't excluded, so
	 * clicking it opened the order instead of selecting the row. Marking the
	 * check-column cell with WooCommerce's own `.no-link` class makes the checkbox
	 * (a descendant, so `.no-link *`) an excluded target, so WC skips navigation.
	 * Core's multi-select — delegated on document.body, which runs earlier in the
	 * bubble — is untouched. Uses WC's sanctioned opt-out; no core edits.
	 *
	 * @return void
	 */
	public function checkbox_no_link_script() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$screens = array(
			'woocommerce_page_wc-orders',
			'woocommerce_page_wc-orders--shop_subscription',
			'edit-shop_order',
			'edit-shop_subscription',
		);
		if ( ! in_array( $screen->id, $screens, true ) ) {
			return;
		}
		?>
		<script id="aaraa-orders-checkbox-nolink">
		( function () {
			function mark() {
				var cells = document.querySelectorAll( '.wp-list-table tbody .check-column' );
				for ( var i = 0; i < cells.length; i++ ) {
					cells[ i ].classList.add( 'no-link' );
				}
			}
			if ( 'loading' !== document.readyState ) { mark(); }
			else { document.addEventListener( 'DOMContentLoaded', mark ); }
		} )();
		</script>
		<?php
	}

	/**
	 * Insert the Subscription column just after the order number (or at the end).
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_column( $columns ) {
		if ( ! is_array( $columns ) || isset( $columns[ self::COLUMN ] ) ) {
			return $columns;
		}
		$label = __( 'Subscription', 'aaraa-white-label-admin' );
		$out   = array();
		foreach ( $columns as $key => $value ) {
			$out[ $key ] = $value;
			if ( 'order_number' === $key ) {
				$out[ self::COLUMN ] = $label;
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = $label;
		}
		return $out;
	}

	/**
	 * Render the subscription cell: linked id(s), or a dash for a plain order.
	 *
	 * @param string             $column        Column key.
	 * @param \WC_Order|int|null  $order_or_post Order (HPOS) or post id (CPT).
	 * @return void
	 */
	public function render_column( $column, $order_or_post = null ) {
		if ( self::COLUMN !== $column ) {
			return;
		}
		echo wp_kses_post( $this->subscription_html( $order_or_post ) );
	}

	/**
	 * Linked subscription id(s) for an order, or a dash when there are none.
	 *
	 * @param \WC_Order|int|null $order_or_post Order (HPOS) or post id (CPT).
	 * @return string
	 */
	private function subscription_html( $order_or_post ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return '&mdash;';
		}

		$order = ( $order_or_post instanceof \WC_Order )
			? $order_or_post
			: wc_get_order( is_object( $order_or_post ) ? (int) $order_or_post->ID : (int) $order_or_post );
		if ( ! $order instanceof \WC_Order ) {
			return '&mdash;';
		}

		// Every subscription this order relates to — as its parent, a renewal, a
		// resubscribe or a switch order.
		$subs = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
		if ( empty( $subs ) ) {
			return '&mdash;';
		}

		$links = array();
		foreach ( $subs as $sub_id => $sub ) {
			$id   = is_object( $sub ) && method_exists( $sub, 'get_id' ) ? $sub->get_id() : (int) $sub_id;
			$edit = is_object( $sub ) && method_exists( $sub, 'get_edit_order_url' ) ? $sub->get_edit_order_url() : '';
			if ( $edit ) {
				$links[] = '<a href="' . esc_url( $edit ) . '">#' . (int) $id . '</a>';
			} else {
				$links[] = '#' . (int) $id;
			}
		}

		return implode( ', ', $links );
	}
}
