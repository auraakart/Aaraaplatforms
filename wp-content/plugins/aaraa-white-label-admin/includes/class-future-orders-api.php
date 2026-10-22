<?php
/**
 * A standalone `GET /wc/v3/future-orders` endpoint.
 *
 * The stock `GET /wc/v3/orders` endpoint is left completely untouched. This
 * registers a separate route with the exact same response shape and query
 * params (customer, per_page, page, status, …), but with subscription
 * parent orders and renewal orders excluded — for API consumers that
 * specifically want "orders a customer placed on their own", not
 * subscription bookkeeping.
 *
 * Internally it forwards the request to the real orders endpoint via
 * rest_do_request() (an in-process PHP call, not an HTTP round trip), with
 * the exclusion applied only for the duration of that one call — so nothing
 * here ever affects `/orders` itself, or any other wc_get_orders() call on
 * the site.
 *
 * The exclusion is a SQL subquery, not an inline list of ids: this store's
 * accumulated renewal-order count runs into the tens of thousands (daily
 * deliveries), and a `NOT IN (id1, id2, …)` clause with that many literal
 * values silently fails to filter correctly. A `NOT IN (SELECT …)` subquery
 * has no such limit.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and serves `GET /wc/v3/future-orders`.
 */
class Future_Orders_API {

	const MARKER = 'aaraa_exclude_subscription_orders';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_send_nocache_headers', array( $this, 'force_nocache' ) );
	}

	/**
	 * Force no-cache headers on this endpoint, regardless of how the request
	 * is authenticated (an API-key/OAuth consumer is never "logged in" in
	 * the cookie sense WP_REST_Server checks — see serve_request()), so a
	 * page/edge cache (LiteSpeed's included) never serves a stale response.
	 *
	 * @param bool $send Whether WP_REST_Server would otherwise send no-cache headers.
	 * @return bool
	 */
	public function force_nocache( $send ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( ! preg_match( '#/wc/v\d+/future-orders(?:[/?]|$)#', $uri ) ) {
			return $send;
		}

		do_action( 'litespeed_control_set_nocache', 'future-orders REST response reflects live data and must not be cached' );

		return true;
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'wc/v3',
			'/future-orders',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Same permission model as the stock orders endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! wc_rest_check_post_permissions( 'shop_order', 'read' ) ) {
			return new \WP_Error(
				'woocommerce_rest_cannot_view',
				__( 'Sorry, you cannot list resources.', 'aaraa-white-label-admin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Forward the request to /wc/v3/orders, with subscription parent and
	 * renewal orders excluded for just this one call.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$inner = new \WP_REST_Request( 'GET', '/wc/v3/orders' );
		foreach ( $request->get_query_params() as $key => $value ) {
			$inner->set_param( $key, $value );
		}

		add_filter( 'woocommerce_rest_orders_prepare_object_query', array( $this, 'mark_query' ) );
		add_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'exclude_clauses_hpos' ), 10, 2 );
		add_filter( 'posts_where', array( $this, 'exclude_clauses_legacy' ), 10, 2 );

		$response = rest_do_request( $inner );

		remove_filter( 'woocommerce_rest_orders_prepare_object_query', array( $this, 'mark_query' ) );
		remove_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'exclude_clauses_hpos' ), 10 );
		remove_filter( 'posts_where', array( $this, 'exclude_clauses_legacy' ), 10 );

		return $response;
	}

	/**
	 * Flag the inner /orders query so the clause filters below apply to it.
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
}
