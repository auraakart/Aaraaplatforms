<?php
/**
 * Hide subscription parent/renewal orders from the WooCommerce REST orders list.
 *
 * `GET wp-json/wc/v3/orders` should only list regular, one-time orders. The
 * order that originally created a subscription, and every renewal order
 * generated for it since, are subscription bookkeeping, not orders a REST
 * consumer placed on their own — so both are excluded from this endpoint.
 * (Subscriptions themselves are a separate order type and never appear in
 * this endpoint to begin with, so they need no special handling here.)
 *
 * The exclusion is done as a SQL subquery, not a huge inline list of ids
 * passed via the generic `exclude` query arg — on this store that list runs
 * into the tens of thousands (a subscription with daily renewals accumulates
 * one renewal order per delivery), and a `NOT IN (…)` clause with that many
 * literal values was silently failing to filter anything out. A `NOT IN
 * (SELECT …)` subquery has no such problem and never needs to grow.
 *
 * The marker query var (`aaraa_exclude_subscription_orders`) scopes the SQL
 * clause to only this REST request. It's added once here, then read back by
 * the clause filters below — never applied to any other wc_get_orders() call
 * on the site, so subscription renewal processing, admin screens, reports,
 * etc. are all unaffected.
 *
 * Only the collection endpoint is touched: `GET /orders/{id}` for a specific
 * order still works as normal.
 *
 * This also forces the orders endpoint to send no-cache headers. WordPress's
 * REST server only sends them when `is_user_logged_in()` is true (see
 * WP_REST_Server::serve_request()), which a key/OAuth-authenticated API
 * consumer never is — so without this, a page/edge cache (LiteSpeed's
 * included) is free to cache the response and keep serving a stale list
 * until that exact URL happens to be purged.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Excludes subscription-related orders from wc/v3/orders.
 */
class Orders_API_Exclude_Subscriptions {

	const MARKER = 'aaraa_exclude_subscription_orders';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_rest_orders_prepare_object_query', array( $this, 'mark_query' ) );

		// HPOS: WC_Order_Query -> OrdersTableQuery.
		add_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'exclude_clauses_hpos' ), 10, 2 );

		// Legacy CPT storage: WP_Query.
		add_filter( 'posts_where', array( $this, 'exclude_clauses_legacy' ), 10, 2 );

		add_filter( 'rest_send_nocache_headers', array( $this, 'force_nocache_for_orders_endpoint' ) );
	}

	/**
	 * Flag this REST query so the clause filters below know to apply the
	 * exclusion.
	 *
	 * @param array $args Query args, passed on to wc_get_orders().
	 * @return array
	 */
	public function mark_query( $args ) {
		$args[ self::MARKER ] = true;
		return $args;
	}

	/**
	 * Append the exclusion subqueries to a marked HPOS orders-table query.
	 *
	 * @param array $clauses     Query clause pieces (join, where, …).
	 * @param \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery $order_query The query instance.
	 * @return array
	 */
	public function exclude_clauses_hpos( $clauses, $order_query ) {
		if ( ! $order_query->get( self::MARKER ) ) {
			return $clauses;
		}

		global $wpdb;
		$orders_table = $order_query->get_table_name( 'orders' );
		$meta_table   = $wpdb->prefix . 'wc_orders_meta';

		$clauses['where']  = empty( $clauses['where'] ) ? '1=1 ' : $clauses['where'] . ' ';
		$clauses['where'] .= "AND {$orders_table}.id NOT IN ( SELECT parent_order_id FROM {$orders_table} WHERE type = 'shop_subscription' AND parent_order_id > 0 )";
		$clauses['where'] .= " AND {$orders_table}.id NOT IN ( SELECT order_id FROM {$meta_table} WHERE meta_key = '_subscription_renewal' )";

		return $clauses;
	}

	/**
	 * Append the exclusion subqueries to a marked legacy (CPT) orders query.
	 *
	 * @param string    $where Existing WHERE clause.
	 * @param \WP_Query $query The query instance.
	 * @return string
	 */
	public function exclude_clauses_legacy( $where, $query ) {
		if ( ! is_a( $query, 'WP_Query' ) || ! $query->get( self::MARKER ) ) {
			return $where;
		}

		global $wpdb;

		$where .= " AND {$wpdb->posts}.ID NOT IN ( SELECT post_parent FROM {$wpdb->posts} WHERE post_type = 'shop_subscription' AND post_parent > 0 )";
		$where .= " AND {$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_subscription_renewal' )";

		return $where;
	}

	/**
	 * Force no-cache headers specifically on the orders REST endpoint (any
	 * version, list or single item), regardless of how the request is
	 * authenticated. Also tells LiteSpeed's cache directly, in case a
	 * server-level rule would otherwise cache the response before honouring
	 * the Cache-Control header.
	 *
	 * @param bool $send Whether WP_REST_Server would otherwise send no-cache headers.
	 * @return bool
	 */
	public function force_nocache_for_orders_endpoint( $send ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( ! preg_match( '#/wc/v\d+/orders(?:[/?]|$)#', $uri ) ) {
			return $send;
		}

		do_action( 'litespeed_control_set_nocache', 'WooCommerce orders REST response reflects live data and must not be cached' );

		return true;
	}
}
