<?php
/**
 * Delivery Schedule Calendar REST API.
 *
 * Combines a customer's subscription delivery schedule with their actual
 * parent/renewal orders and pause dates into a single day-by-day calendar for
 * a date range — the source for an app calendar screen (a "delivery day" dot,
 * a "will be skipped" dot, order history, upcoming deliveries).
 *
 * Endpoint:
 *   GET /wp-json/delivery-calendar
 *       ?customer_id=123&start_date=2026-09-01&end_date=2026-09-30
 *       [&subscription_id=58908]
 *
 * For every day in range, and for each of the customer's subscriptions
 * (optionally narrowed to one via subscription_id), reports:
 *   - is_delivery_day : whether the day matches the subscription's delivery
 *                        schedule (Every Day / Alternate / Weekend / Custom),
 *                        computed the same way {@see Subscription_Delivery}
 *                        and {@see Renewal_Schedule_Guard} do.
 *   - is_paused        : whether the day is one of the subscription's pause
 *                        dates — past dates are history, future dates are
 *                        scheduled; both come from the same durable list,
 *                        {@see Subscription_Delivery::read_pause_dates()}.
 *   - order            : the parent/renewal/resubscribe/switch order (if any)
 *                        whose `delivery_date` meta falls on that day.
 *   - status           : delivered | processing | paused | upcoming | missed
 *                        | not_scheduled | <raw WC status for a
 *                        cancelled/refunded/failed order>.
 *
 * The response also carries a top-level `one_time_orders` list: any of the
 * customer's orders with a `delivery_date` in range that are NOT tied to a
 * subscription (a plain one-off purchase) — flat and date-sorted, since a
 * one-time order has no schedule to project a day's status against.
 *
 * Read-only. A separate module — nothing else in the plugin depends on it.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Per-customer delivery calendar endpoint.
 */
class Delivery_Calendar_API {

	const REST_NS = 'delivery-calendar';

	/**
	 * Longest range answerable in one call (just over a year).
	 */
	const MAX_DAYS = 370;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route at the bare namespace root so it serves at
	 * /wp-json/delivery-calendar, matching the project's other custom APIs.
	 *
	 * @return void
	 */
	public function register_routes() {
		add_filter( 'rest_endpoints', array( $this, 'alias_base_route' ) );

		register_rest_route(
			self::REST_NS,
			'/',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'customer_id'     => array( 'required' => true, 'type' => 'integer' ),
					'start_date'      => array( 'required' => true, 'type' => 'string' ),
					'end_date'        => array( 'required' => true, 'type' => 'string' ),
					'subscription_id' => array( 'required' => false, 'type' => 'integer' ),
				),
			)
		);
	}

	/**
	 * Serve the handler at the bare namespace root (with or without a
	 * trailing slash), the same way the project's other custom APIs do.
	 *
	 * @param array $endpoints All registered REST endpoints.
	 * @return array
	 */
	public function alias_base_route( $endpoints ) {
		$slashed = '/' . self::REST_NS . '/';
		$bare    = '/' . self::REST_NS;
		if ( isset( $endpoints[ $slashed ] ) ) {
			$endpoints[ $bare ] = $endpoints[ $slashed ];
		}
		return $endpoints;
	}

	/* --------------------------------------------------------------------- *
	 * Main handler.
	 * --------------------------------------------------------------------- */

	/**
	 * GET /delivery-calendar.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_calendar( \WP_REST_Request $request ) {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return new \WP_Error( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'aaraa-white-label-admin' ), array( 'status' => 500 ) );
		}

		$customer_id = absint( $request->get_param( 'customer_id' ) );
		if ( ! $customer_id ) {
			return new \WP_Error( 'missing_customer_id', __( 'customer_id is required.', 'aaraa-white-label-admin' ), array( 'status' => 400 ) );
		}

		$start = $this->parse_date( $request->get_param( 'start_date' ) );
		$end   = $this->parse_date( $request->get_param( 'end_date' ) );
		if ( ! $start || ! $end ) {
			return new \WP_Error( 'invalid_dates', __( 'start_date and end_date must be valid Y-m-d dates.', 'aaraa-white-label-admin' ), array( 'status' => 400 ) );
		}
		if ( $end < $start ) {
			return new \WP_Error( 'invalid_range', __( 'end_date must not be before start_date.', 'aaraa-white-label-admin' ), array( 'status' => 400 ) );
		}

		$days = $this->date_range( $start, $end );
		if ( count( $days ) > self::MAX_DAYS ) {
			return new \WP_Error(
				'range_too_large',
				sprintf(
					/* translators: %d: maximum days */
					__( 'The date range cannot span more than %d days.', 'aaraa-white-label-admin' ),
					self::MAX_DAYS
				),
				array( 'status' => 400 )
			);
		}

		$only_sub_id = absint( $request->get_param( 'subscription_id' ) );

		// Collected across ALL of the customer's subscriptions (not just a
		// subscription_id-narrowed subset), so an order tied to a subscription
		// never also shows up as "one-time" just because its own subscription
		// was filtered out of the response.
		$all_subs      = array();
		$sub_order_ids = array();
		foreach ( wcs_get_users_subscriptions( $customer_id ) as $sub ) {
			if ( ! is_a( $sub, 'WC_Subscription' ) ) {
				continue;
			}
			$all_subs[] = $sub;
			foreach ( $sub->get_related_orders( 'ids', 'any' ) as $order_id ) {
				$sub_order_ids[ (int) $order_id ] = true;
			}
		}

		$subscriptions = array();
		foreach ( $all_subs as $sub ) {
			if ( $only_sub_id && $sub->get_id() !== $only_sub_id ) {
				continue;
			}
			$subscriptions[] = $this->subscription_calendar( $sub, $days );
		}

		$one_time_orders = $this->one_time_orders( $customer_id, $days, $sub_order_ids );

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'customer_id'      => $customer_id,
				'start_date'       => $start,
				'end_date'         => $end,
				'today'            => current_time( 'Y-m-d' ),
				'count'            => count( $subscriptions ),
				'subscriptions'    => $subscriptions,
				'one_time_orders'  => $one_time_orders,
			),
			200
		);
	}

	/* --------------------------------------------------------------------- *
	 * Per-subscription calendar.
	 * --------------------------------------------------------------------- */

	/**
	 * Build the day-by-day calendar for one subscription.
	 *
	 * @param \WC_Subscription $sub  Subscription.
	 * @param string[]         $days Y-m-d dates in range.
	 * @return array<string,mixed>
	 */
	private function subscription_calendar( $sub, array $days ) {
		$sub_id = $sub->get_id();
		$today  = current_time( 'Y-m-d' );

		$pause_dates = class_exists( __NAMESPACE__ . '\\Subscription_Delivery' )
			? array_flip( Subscription_Delivery::read_pause_dates( $sub_id ) )
			: array();

		$orders_by_day = $this->orders_by_day( $sub, $days );

		$items = array();
		foreach ( $sub->get_items() as $item ) {
			$items[] = array(
				'product_id' => $item->get_product_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
			);
		}

		$out_days = array();
		foreach ( $days as $day ) {
			$is_paused       = isset( $pause_dates[ $day ] );
			$is_delivery_day = $this->is_delivery_day( $sub_id, $day, $sub );
			$order           = isset( $orders_by_day[ $day ] ) ? $orders_by_day[ $day ] : null;

			$out_days[] = array(
				'date'            => $day,
				'is_today'        => ( $day === $today ),
				'is_delivery_day' => $is_delivery_day,
				'is_paused'       => $is_paused,
				'status'          => $this->day_status( $day, $today, $is_delivery_day, $is_paused, $order ),
				'order'           => $order,
			);
		}

		return array(
			'subscription_id' => $sub_id,
			'status'          => $sub->get_status(),
			'items'           => $items,
			'schedule'        => $this->schedule_summary( $sub_id ),
			'slot'            => $this->slot_summary( $sub_id ),
			'start_date'      => $sub->get_date( 'start' ),
			'next_payment'    => $sub->get_date( 'next_payment' ),
			'days'            => $out_days,
		);
	}

	/* --------------------------------------------------------------------- *
	 * One-time (non-subscription) orders.
	 * --------------------------------------------------------------------- */

	/**
	 * The customer's one-time orders in range — anything with a `delivery_date`
	 * in range that is NOT already accounted for under a subscription (parent,
	 * renewal, resubscribe or switch order). A flat, date-sorted list rather than
	 * a per-day object: there is no schedule to project for a one-time order, so
	 * "not_scheduled"/"missed"/"upcoming" don't apply — only orders that actually
	 * exist are ever reported here.
	 *
	 * @param int                $customer_id  Customer id.
	 * @param string[]           $days         Y-m-d dates in range.
	 * @param array<int,bool>    $exclude_ids  Order ids already tied to a subscription.
	 * @return array<int,array{date:string,order:array<string,mixed>}>
	 */
	private function one_time_orders( $customer_id, array $days, array $exclude_ids ) {
		if ( ! function_exists( 'wc_get_orders' ) || empty( $days ) ) {
			return array();
		}

		$meta_key = class_exists( __NAMESPACE__ . '\\Order_Delivery' ) ? Order_Delivery::META_DELIVERY_DATE : 'delivery_date';
		$wanted   = array_flip( $days );
		$from     = reset( $days );
		$to       = end( $days );

		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'status'      => array( 'processing', 'completed' ),
				'limit'       => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => $meta_key,
						'value'   => array( $from, $to . ' 23:59:59' ),
						'compare' => 'BETWEEN',
					),
				),
			)
		);

		$out = array();
		foreach ( $orders as $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}
			$order_id = $order->get_id();
			if ( isset( $exclude_ids[ $order_id ] ) ) {
				continue; // Already represented under its own subscription.
			}
			// Wallet recharge / renewal top-up orders are not deliveries.
			if ( $order->get_meta( '_aaraa_renewal_sub_id' ) || 'yes' === $order->get_meta( 'wps_wallet_recharge_order' ) ) {
				continue;
			}
			$raw = (string) $order->get_meta( $meta_key );
			if ( '' === $raw ) {
				continue;
			}
			$day = substr( trim( $raw ), 0, 10 );
			if ( ! isset( $wanted[ $day ] ) ) {
				continue;
			}

			$created = $order->get_date_created();
			$out[]   = array(
				'date'  => $day,
				'order' => array(
					'id'            => $order_id,
					'type'          => 'order',
					'status'        => $order->get_status(),
					'total'         => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ),
					'currency'      => $order->get_currency(),
					'delivery_date' => $day,
					'date_created'  => $created ? $created->date( 'Y-m-d H:i:s' ) : null,
				),
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		return $out;
	}

	/**
	 * The order (parent/renewal/resubscribe/switch) for each day in range whose
	 * `delivery_date` meta falls on that day — date portion only, handling both
	 * the date-only and full-datetime storage shapes the meta can hold.
	 *
	 * @param \WC_Subscription $sub  Subscription.
	 * @param string[]         $days Y-m-d dates in range.
	 * @return array<string,array<string,mixed>> date => order summary.
	 */
	private function orders_by_day( $sub, array $days ) {
		$wanted = array_flip( $days );
		$out    = array();

		$ids = $sub->get_related_orders( 'ids', 'any' );
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );

		$meta_key = class_exists( __NAMESPACE__ . '\\Order_Delivery' ) ? Order_Delivery::META_DELIVERY_DATE : 'delivery_date';

		foreach ( $ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$raw = (string) $order->get_meta( $meta_key );
			if ( '' === $raw ) {
				continue;
			}
			$day = substr( trim( $raw ), 0, 10 );
			if ( ! isset( $wanted[ $day ] ) ) {
				continue;
			}
			$out[ $day ] = $this->order_summary( $order, $sub, $day );
		}

		return $out;
	}

	/**
	 * A compact order summary for one calendar day.
	 *
	 * @param \WC_Order        $order Order.
	 * @param \WC_Subscription $sub   Owning subscription.
	 * @param string           $day   Y-m-d the order was matched to.
	 * @return array<string,mixed>
	 */
	private function order_summary( $order, $sub, $day ) {
		$created = $order->get_date_created();
		return array(
			'id'            => $order->get_id(),
			'type'          => $this->order_type( $order, $sub ),
			'status'        => $order->get_status(),
			'total'         => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ),
			'currency'      => $order->get_currency(),
			'delivery_date' => $day,
			'date_created'  => $created ? $created->date( 'Y-m-d H:i:s' ) : null,
		);
	}

	/**
	 * Which of the subscription's related-order roles an order plays.
	 *
	 * @param \WC_Order        $order Order.
	 * @param \WC_Subscription $sub   Owning subscription.
	 * @return string parent|renewal|resubscribe|switch|order
	 */
	private function order_type( $order, $sub ) {
		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
			return 'renewal';
		}
		if ( (int) $order->get_id() === (int) $sub->get_parent_id() ) {
			return 'parent';
		}
		if ( function_exists( 'wcs_order_contains_resubscribe' ) && wcs_order_contains_resubscribe( $order ) ) {
			return 'resubscribe';
		}
		if ( function_exists( 'wcs_order_contains_switch' ) && wcs_order_contains_switch( $order ) ) {
			return 'switch';
		}
		return 'order';
	}

	/**
	 * A day's overall state for the calendar dot.
	 *
	 * A paused day always reads as "paused" even if a (since-cancelled) order
	 * happens to exist for it. Otherwise a real order's own status wins for
	 * anything terminal (cancelled/refunded/failed); any other order status is
	 * bucketed as "delivered" (day already passed) or "processing" (today or
	 * future — the renewal order was already created ahead of the delivery).
	 * With no order at all, a scheduled delivery day is "missed" (past) or
	 * "upcoming" (today/future, no renewal order created yet); a non-schedule
	 * day is "not_scheduled".
	 *
	 * @param string     $day              Y-m-d.
	 * @param string     $today            Y-m-d, site-local today.
	 * @param bool       $is_delivery_day  Whether the schedule delivers this day.
	 * @param bool       $is_paused        Whether the day is a pause date.
	 * @param array|null $order            Order summary from order_summary(), or null.
	 * @return string
	 */
	private function day_status( $day, $today, $is_delivery_day, $is_paused, $order ) {
		if ( $is_paused ) {
			return 'paused';
		}
		if ( $order ) {
			$terminal = array( 'cancelled', 'refunded', 'failed', 'trash' );
			if ( in_array( $order['status'], $terminal, true ) ) {
				return $order['status'];
			}
			return ( $day <= $today ) ? 'delivered' : 'processing';
		}
		if ( $is_delivery_day ) {
			return ( $day < $today ) ? 'missed' : 'upcoming';
		}
		return 'not_scheduled';
	}

	/* --------------------------------------------------------------------- *
	 * Delivery-schedule projection.
	 *
	 * Mirrors Renewal_Schedule_Guard::is_renewal_day(), but for the delivery
	 * day itself rather than its D-1 renewal/prep day — this is what the
	 * calendar needs to mark, since the app shows delivery dots, not prep days.
	 * --------------------------------------------------------------------- */

	/**
	 * Whether $date is a delivery day for the subscription's schedule.
	 *
	 * @param int              $sub_id Subscription id.
	 * @param string           $date   Y-m-d.
	 * @param \WC_Subscription $sub    Subscription (for the alternate-day anchor).
	 * @return bool
	 */
	private function is_delivery_day( $sub_id, $date, $sub ) {
		// A subscription has no deliveries before it starts — a requested
		// range starting earlier than the subscription (e.g. a calendar
		// month that only partly overlaps a future-dated subscription) must
		// not mark those earlier days as delivery days, regardless of the
		// schedule type.
		$anchor = $this->anchor_date( $sub );
		if ( '' !== $anchor && $date < $anchor ) {
			return false;
		}

		$type = (string) get_post_meta( $sub_id, Subscription_Delivery::META_SCHEDULE, true );

		if ( '' === $type || 'daily' === $type ) {
			return true;
		}

		if ( 'alternate' === $type ) {
			if ( '' === $anchor ) {
				return true; // No anchor to reason about — don't block.
			}
			$diff = (int) round( ( strtotime( $date . ' 00:00:00' ) - strtotime( $anchor . ' 00:00:00' ) ) / DAY_IN_SECONDS );
			return 0 === ( abs( $diff ) % 2 );
		}

		// weekend / custom.
		$days = $this->schedule_weekdays( $sub_id, $type );
		if ( empty( $days ) ) {
			return true; // Nothing to constrain against.
		}
		$weekday = (int) gmdate( 'w', strtotime( $date . ' 00:00:00 +0000' ) );
		return in_array( $weekday, $days, true );
	}

	/**
	 * Delivery weekday indices (PHP `w`, 0 = Sun) for the schedule.
	 *
	 * @param int    $sub_id Subscription id.
	 * @param string $type   Schedule type.
	 * @return int[]
	 */
	private function schedule_weekdays( $sub_id, $type ) {
		if ( 'weekend' === $type ) {
			return array( 0, 6 );
		}
		$days = get_post_meta( $sub_id, Subscription_Delivery::META_DAYS, true );
		if ( ! is_array( $days ) ) {
			return array();
		}
		$out = array();
		foreach ( $days as $d ) {
			$d = (int) $d;
			if ( $d >= 0 && $d <= 6 ) {
				$out[] = $d;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * The subscription's start day (Y-m-d, site timezone) — the alternate-day
	 * anchor, same as {@see Renewal_Schedule_Guard}.
	 *
	 * @param \WC_Subscription $sub Subscription.
	 * @return string Y-m-d, or '' if unavailable.
	 */
	private function anchor_date( $sub ) {
		$ts = (int) $sub->get_time( 'start', 'gmt' );
		if ( ! $ts ) {
			$ts = (int) $sub->get_time( 'date_created', 'gmt' );
		}
		return $ts ? wp_date( 'Y-m-d', $ts ) : '';
	}

	/**
	 * Schedule type + label + weekdays, for the app to render a legend.
	 *
	 * @param int $sub_id Subscription id.
	 * @return array{type:string,label:string,days:int[]}
	 */
	private function schedule_summary( $sub_id ) {
		$type  = (string) get_post_meta( $sub_id, Subscription_Delivery::META_SCHEDULE, true );
		$type  = ( '' === $type ) ? 'daily' : $type;
		$types = Subscription_Delivery::schedule_types();

		return array(
			'type'  => $type,
			'label' => isset( $types[ $type ] ) ? $types[ $type ] : $type,
			'days'  => $this->schedule_weekdays( $sub_id, $type ),
		);
	}

	/**
	 * Delivery slot summary, if one is set.
	 *
	 * @param int $sub_id Subscription id.
	 * @return array{id:int,label:string}|null
	 */
	private function slot_summary( $sub_id ) {
		$slot_id = (int) get_post_meta( $sub_id, Subscription_Delivery::META_SLOT, true );
		if ( ! $slot_id || ! class_exists( __NAMESPACE__ . '\\Delivery_Slots_API' ) ) {
			return null;
		}
		$slot = Delivery_Slots_API::slot_by_id( $slot_id );
		if ( ! $slot ) {
			return null;
		}
		return array(
			'id'    => $slot_id,
			'label' => isset( $slot['label'] ) ? $slot['label'] : '',
		);
	}

	/* --------------------------------------------------------------------- *
	 * Helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * Strict Y-m-d check that also rejects impossible dates like 2026-02-31.
	 *
	 * @param mixed $raw Candidate.
	 * @return string The date, or '' if invalid.
	 */
	private function parse_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return '';
		}
		$parts = explode( '-', $raw );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $raw : '';
	}

	/**
	 * Every Y-m-d date from $start to $end, inclusive.
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return string[]
	 */
	private function date_range( $start, $end ) {
		$out    = array();
		$cursor = $start;
		$guard  = 0;
		while ( $cursor <= $end && $guard++ < ( self::MAX_DAYS + 5 ) ) {
			$out[]  = $cursor;
			$cursor = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}
		return $out;
	}
}
