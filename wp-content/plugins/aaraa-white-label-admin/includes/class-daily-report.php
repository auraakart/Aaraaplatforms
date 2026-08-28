<?php
/**
 * Daily Orders Report.
 *
 * For a chosen day (default today) lists every order created that day, split
 * into One Time, Subscription Parent and Subscription Renewal, alongside the
 * renewals that were SKIPPED that day because the next delivery is paused. A
 * counter strip above the table totals each category plus the store's active
 * subscription count, and a date picker moves between days.
 *
 * "Renewal skipped" is derived, not stored: the delivery person prepares one
 * day ahead, so a renewal due on day D fulfils the delivery on D+1. The pause
 * guard therefore skips the renewal due on D whenever D+1 is a pause date — so
 * the skipped renewals for day D are exactly the subscriptions whose D+1
 * delivery is paused.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Daily orders report screen.
 */
class Daily_Report {

	const PAGE = 'aaraa-daily-report';

	/**
	 * No hooks needed — the screen is rendered from the menu callback.
	 *
	 * @return void
	 */
	public function init() {}

	/**
	 * Resolve the requested date (Y-m-d), defaulting to today (site local).
	 *
	 * @return string
	 */
	private function current_date() {
		$raw = isset( $_GET['report_date'] ) ? sanitize_text_field( wp_unslash( $_GET['report_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return $raw;
		}
		return current_time( 'Y-m-d' );
	}

	/**
	 * Gather the day's rows and the counter totals.
	 *
	 * @param string $date Y-m-d (site local).
	 * @return array{rows:array<int,array<string,mixed>>,counts:array<string,int>}
	 */
	private function get_data( $date ) {
		$rows              = array();
		$status_counts     = array(); // WooCommerce order status => count (real orders only).
		$renewal_today_ids = array(); // sub_id => true for renewals created today.
		$waiting_ids       = array(); // sub_id => true counted as waiting.
		$counts        = array(
			'total_orders' => 0,
			'active_subs'  => 0,
			'one_time'     => 0,
			'parent'       => 0,
			'renewal'      => 0,
			'wallet'       => 0,
			'waiting'      => 0,
			'skipped'      => 0,
			'duplicate'    => 0,
		);

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array( 'rows' => $rows, 'counts' => $counts, 'status_counts' => $status_counts );
		}

		// ---- Orders created on the selected day ----------------------------
		$tz    = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$start = ( new \DateTime( $date . ' 00:00:00', $tz ) )->getTimestamp();
		$end   = ( new \DateTime( $date . ' 23:59:59', $tz ) )->getTimestamp();

		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'limit'        => -1,
				'orderby'      => 'date',
				'order'        => 'ASC',
				'date_created' => $start . '...' . $end,
			)
		);

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$type = $this->classify_order( $order );

			++$counts[ $type ];
			++$counts['total_orders'];

			$st                   = $order->get_status();
			$status_counts[ $st ] = ( $status_counts[ $st ] ?? 0 ) + 1;

			$row_sub_id = ( 'one_time' === $type ) ? 0 : $this->subscription_id_for( $order );
			if ( 'renewal' === $type && $row_sub_id ) {
				$renewal_today_ids[ $row_sub_id ] = true;
			}

			$rows[] = array(
				'type'    => $type,
				'order_id' => $order->get_id(),
				'sub_id'  => $row_sub_id,
				'customer' => $this->customer_name( $order ),
				'mobile'  => $this->mobile( $order ),
				'products' => $this->products( $order ),
				'amount'  => (float) $order->get_total(),
				'status'  => $order->get_status(),
				'time'    => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
			);
		}

		$delivery_date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );

		// ---- Subscriptions due to renew today with no renewal yet (waiting) ----
		foreach ( $this->get_waiting_subscriptions( $date, $delivery_date ) as $sub ) {
			++$counts['waiting'];
			$waiting_ids[ $sub->get_id() ] = true;
			$rows[] = array(
				'type'    => 'waiting',
				'order_id' => 0,
				'sub_id'  => $sub->get_id(),
				'customer' => $this->customer_name( $sub ),
				'mobile'  => $this->mobile( $sub ),
				'products' => $this->products( $sub ),
				'amount'  => (float) $sub->get_total(),
				'status'  => $sub->get_status(),
				'time'    => 0,
			);
		}

		// ---- Renewal Skipped: every ACTIVE subscription that neither renewed
		// today nor is waiting. This makes the subscription buckets a clean
		// partition of the active subscriptions, so the day's counts reconcile:
		//   Active = (distinct active subs renewed) + Waiting + Skipped
		// and, because the Subscription Renewal box counts renewal ORDERS (which
		// include same-day duplicates):
		//   Active = Subscription Renewal − Duplicate Renewal + Waiting + Skipped.
		// A skipped sub is one whose renewal isn't due today for ANY reason — an
		// every-2nd-day / alternate off day, a paused delivery, or simply not due.
		$active_ids            = $this->get_active_subscription_ids();
		$counts['active_subs'] = count( $active_ids );

		foreach ( $active_ids as $sid ) {
			$sid = (int) $sid;
			if ( isset( $renewal_today_ids[ $sid ] ) || isset( $waiting_ids[ $sid ] ) ) {
				continue; // Renewed today or waiting to renew — not skipped.
			}
			$sub = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sid ) : null;
			if ( ! $sub ) {
				continue;
			}
			++$counts['skipped'];
			$rows[] = array(
				'type'    => 'skipped',
				'order_id' => 0,
				'sub_id'  => $sid,
				'customer' => $this->customer_name( $sub ),
				'mobile'  => $this->mobile( $sub ),
				'products' => $this->products( $sub ),
				'amount'  => (float) $sub->get_total(),
				'status'  => $sub->get_status(),
				'time'    => 0,
			);
		}

		// Duplicate renewals: the same subscription with more than one renewal
		// order on this day. The first is legitimate; each extra is a duplicate
		// (a double charge). Flag every row in a duplicated set so both the
		// original and its duplicates are visible together.
		$renewal_freq = array();
		foreach ( $rows as $row ) {
			if ( 'renewal' === $row['type'] && $row['sub_id'] ) {
				$renewal_freq[ $row['sub_id'] ] = ( $renewal_freq[ $row['sub_id'] ] ?? 0 ) + 1;
			}
		}
		foreach ( $renewal_freq as $count ) {
			if ( $count > 1 ) {
				$counts['duplicate'] += ( $count - 1 ); // Surplus (double-charge) orders.
			}
		}
		foreach ( $rows as &$row ) {
			$row['is_duplicate'] = ( 'renewal' === $row['type'] && $row['sub_id'] && ( $renewal_freq[ $row['sub_id'] ] ?? 0 ) > 1 );
		}
		unset( $row );

		// Order rows by category then creation time.
		$order_rank = array( 'one_time' => 0, 'parent' => 1, 'renewal' => 2, 'wallet' => 3, 'waiting' => 4, 'skipped' => 5 );
		usort(
			$rows,
			static function ( $a, $b ) use ( $order_rank ) {
				$ra = $order_rank[ $a['type'] ] ?? 9;
				$rb = $order_rank[ $b['type'] ] ?? 9;
				if ( $ra !== $rb ) {
					return $ra <=> $rb;
				}
				// Within a category, keep the same subscription's rows together so
				// duplicate renewals sit side by side, then order by time.
				if ( (int) $a['sub_id'] !== (int) $b['sub_id'] ) {
					return (int) $a['sub_id'] <=> (int) $b['sub_id'];
				}
				return $a['time'] <=> $b['time'];
			}
		);

		// Busiest status first.
		arsort( $status_counts );

		return array( 'rows' => $rows, 'counts' => $counts, 'status_counts' => $status_counts );
	}

	/**
	 * IDs of every active subscription, from the same source as the active-count
	 * card so the two agree exactly.
	 *
	 * @return int[]
	 */
	private function get_active_subscription_ids() {
		if ( post_type_exists( 'shop_subscription' ) ) {
			$ids = get_posts(
				array(
					'post_type'        => 'shop_subscription',
					'post_status'      => 'wc-active',
					'fields'           => 'ids',
					'numberposts'      => -1,
					'no_found_rows'    => true,
					'suppress_filters' => true,
				)
			);
			if ( ! empty( $ids ) ) {
				return array_map( 'intval', (array) $ids );
			}
		}

		// HPOS fallback: subscriptions live in the orders table.
		global $wpdb;
		$table = $wpdb->prefix . 'wc_orders';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { // phpcs:ignore WordPress.DB
			return array_map(
				'intval',
				(array) $wpdb->get_col( // phpcs:ignore WordPress.DB
					$wpdb->prepare( "SELECT id FROM {$table} WHERE type = %s AND status = %s", 'shop_subscription', 'wc-active' ) // phpcs:ignore WordPress.DB.PreparedSQL
				)
			);
		}
		return array();
	}

	/**
	 * Active subscriptions whose next payment is due on the selected day but which
	 * have no renewal order yet — "waiting for renewal".
	 *
	 * A subscription whose next-day (D+1) delivery is paused is excluded (its
	 * renewal is legitimately skipped, counted separately); one that already has a
	 * renewal dated that day is excluded too.
	 *
	 * @param string $date          Y-m-d (site local) — the renewal day.
	 * @param string $delivery_date Y-m-d (site local) — the D+1 delivery day.
	 * @return \WC_Subscription[]
	 */
	private function get_waiting_subscriptions( $date, $delivery_date ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wcs_get_subscription' ) ) {
			return array();
		}

		// next_payment is stored as GMT; translate the selected local day to a GMT
		// window and match subscriptions whose next payment falls inside it.
		$tz        = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$gmt_start = gmdate( 'Y-m-d H:i:s', ( new \DateTime( $date . ' 00:00:00', $tz ) )->getTimestamp() );
		$gmt_end   = gmdate( 'Y-m-d H:i:s', ( new \DateTime( $date . ' 23:59:59', $tz ) )->getTimestamp() );

		$ids = wc_get_orders(
			array(
				'type'       => 'shop_subscription',
				'status'     => array( 'active' ),
				'limit'      => -1,
				'return'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_schedule_next_payment',
						'value'   => array( $gmt_start, $gmt_end ),
						'compare' => 'BETWEEN',
					),
				),
			)
		);

		$out = array();
		foreach ( (array) $ids as $sid ) {
			$sid = (int) $sid;
			$sub = wcs_get_subscription( $sid );
			if ( ! $sub ) {
				continue;
			}
			// D+1 delivery paused → this is a "renewal skipped", not "waiting".
			$paused = class_exists( __NAMESPACE__ . '\\Pause_History' )
				? Pause_History::pause_dates_for( $sid )
				: array();
			if ( in_array( $delivery_date, $paused, true ) ) {
				continue;
			}
			// Already has a renewal for this day → not waiting.
			if ( $this->has_renewal_for_day( $sub, $date ) ) {
				continue;
			}
			$out[] = $sub;
		}
		return $out;
	}

	/**
	 * Whether a subscription already has a (non-void) renewal order created on the
	 * given site-local day.
	 *
	 * @param \WC_Subscription $sub  Subscription.
	 * @param string           $date Y-m-d (site local).
	 * @return bool
	 */
	private function has_renewal_for_day( $sub, $date ) {
		foreach ( (array) $sub->get_related_orders( 'ids', 'renewal' ) as $oid ) {
			$order = wc_get_order( (int) $oid );
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			if ( $order->has_status( array( 'cancelled', 'trash' ) ) ) {
				continue;
			}
			$created = $order->get_date_created();
			if ( $created && wp_date( 'Y-m-d', $created->getTimestamp() ) === $date ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Store-wide count of active subscriptions.
	 *
	 * Uses wp_count_posts() (indexed, cheap) for the classic shop_subscription
	 * store; if that returns nothing — e.g. subscriptions kept in the HPOS orders
	 * table — it falls back to a direct count on that table.
	 *
	 * @return int
	 */
	private function count_active_subscriptions() {
		if ( post_type_exists( 'shop_subscription' ) ) {
			$counts = wp_count_posts( 'shop_subscription' );
			$n      = isset( $counts->{'wc-active'} ) ? (int) $counts->{'wc-active'} : 0;
			if ( $n > 0 ) {
				return $n;
			}
		}

		// HPOS fallback: subscriptions live in the orders table.
		global $wpdb;
		$table = $wpdb->prefix . 'wc_orders';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { // phpcs:ignore WordPress.DB
			return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = %s AND status = %s", 'shop_subscription', 'wc-active' ) // phpcs:ignore WordPress.DB.PreparedSQL
			);
		}
		return 0;
	}

	/**
	 * Classify an order: wallet, renewal, parent (subscription-creating) or
	 * one_time.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	private function classify_order( $order ) {
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
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, 'parent' ) ) {
			return 'parent';
		}
		return 'one_time';
	}

	/**
	 * First related subscription id for an order (0 when none).
	 *
	 * @param \WC_Order $order Order.
	 * @return int
	 */
	private function subscription_id_for( $order ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return 0;
		}
		$subs = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
		if ( empty( $subs ) ) {
			return 0;
		}
		$first = reset( $subs );
		return ( is_object( $first ) && method_exists( $first, 'get_id' ) ) ? (int) $first->get_id() : (int) key( $subs );
	}

	/**
	 * Billing / account name for an order or subscription.
	 *
	 * @param \WC_Abstract_Order $order Order or subscription.
	 * @return string
	 */
	private function customer_name( $order ) {
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( '' === $name && method_exists( $order, 'get_user' ) ) {
			$user = $order->get_user();
			$name = $user ? $user->display_name : '';
		}
		return $name;
	}

	/**
	 * Mobile number for an order or subscription.
	 *
	 * @param \WC_Abstract_Order $order Order or subscription.
	 * @return string
	 */
	private function mobile( $order ) {
		$mobile = (string) $order->get_billing_phone();
		if ( '' === $mobile ) {
			$mobile = (string) $order->get_meta( '_shipping_mobile_number' );
		}
		return $mobile;
	}

	/**
	 * "Product × qty, …" list for an order or subscription.
	 *
	 * @param \WC_Abstract_Order $order Order or subscription.
	 * @return string
	 */
	private function products( $order ) {
		$items = array();
		foreach ( (array) $order->get_items() as $item ) {
			$items[] = $item->get_name() . ' × ' . (int) $item->get_quantity();
		}
		return implode( ', ', $items );
	}

	/**
	 * Human label for a row type.
	 *
	 * @param string $type Row type key.
	 * @return string
	 */
	private function type_label( $type ) {
		switch ( $type ) {
			case 'one_time':
				return __( 'One Time Order', 'aaraa-white-label-admin' );
			case 'parent':
				return __( 'Subscription Parent', 'aaraa-white-label-admin' );
			case 'renewal':
				return __( 'Subscription Renewal', 'aaraa-white-label-admin' );
			case 'wallet':
				return __( 'Wallet Recharge Order', 'aaraa-white-label-admin' );
			case 'waiting':
				return __( 'Waiting for Renewal', 'aaraa-white-label-admin' );
			case 'skipped':
				return __( 'Renewal Skipped', 'aaraa-white-label-admin' );
		}
		return ucwords( str_replace( '_', ' ', (string) $type ) );
	}

	/**
	 * Human-readable status label (orders + subscriptions).
	 *
	 * @param string $status Status key (no wc- prefix).
	 * @return string
	 */
	private function status_label( $status ) {
		if ( function_exists( 'wcs_get_subscription_statuses' ) ) {
			$all = wcs_get_subscription_statuses();
			if ( isset( $all[ 'wc-' . $status ] ) ) {
				return $all[ 'wc-' . $status ];
			}
		}
		if ( function_exists( 'wc_get_order_status_name' ) ) {
			return wc_get_order_status_name( $status );
		}
		return ucwords( str_replace( '-', ' ', (string) $status ) );
	}

	/**
	 * Accent colour for an order status box (falls back to slate grey).
	 *
	 * @param string $status Status key (no wc- prefix).
	 * @return string Hex colour.
	 */
	private function status_color( $status ) {
		$map = array(
			'processing'     => '#10B981',
			'completed'      => '#3B82F6',
			'on-hold'        => '#F59E0B',
			'pending'        => '#64748B',
			'cancelled'      => '#EF4444',
			'refunded'       => '#F97316',
			'failed'         => '#B91C1C',
			'checkout-draft' => '#94A3B8',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : '#64748B';
	}

	/**
	 * Render one counter card.
	 *
	 * @param string $label   Card label.
	 * @param int    $value   Card value.
	 * @param string $accent  Accent colour (hex).
	 * @return void
	 */
	private function stat_card( $label, $value, $accent ) {
		?>
		<div style="flex:1 1 140px;min-width:140px;background:#fff;border:1px solid #E2E8F0;border-left:4px solid <?php echo esc_attr( $accent ); ?>;border-radius:10px;padding:12px 16px;">
			<div style="font-size:12px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.03em;"><?php echo esc_html( $label ); ?></div>
			<div style="font-size:26px;font-weight:700;color:#0F172A;line-height:1.2;margin-top:4px;"><?php echo esc_html( number_format_i18n( $value ) ); ?></div>
		</div>
		<?php
	}

	/**
	 * Render the admin screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this report.', 'aaraa-white-label-admin' ) );
		}

		$date   = $this->current_date();
		$data   = $this->get_data( $date );
		$rows          = $data['rows'];
		$counts        = $data['counts'];
		$status_counts = isset( $data['status_counts'] ) ? $data['status_counts'] : array();
		$base          = admin_url( 'admin.php' );
		$pretty = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
		$today  = current_time( 'Y-m-d' );

		echo '<div class="wrap aaraa-wallet">';
		?>
		<div class="aaraa-wallet__bar">
			<h1 class="aaraa-wallet__title"><?php esc_html_e( 'Daily Reports', 'aaraa-white-label-admin' ); ?></h1>
		</div>

		<div class="aaraa-wallet__panel" style="border-top:1px solid #E2E8F0;border-radius:10px;">
			<form method="get" class="aaraa-wallet__toolbar" style="margin:0 0 14px;flex-wrap:wrap;gap:14px;align-items:flex-end;justify-content:flex-start;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<label>
					<strong><?php esc_html_e( 'Date', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="date" name="report_date" value="<?php echo esc_attr( $date ); ?>" />
				</label>
				<span>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'aaraa-white-label-admin' ); ?></button>
					<a class="button <?php echo $date === $today ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE ), $base ) ); ?>"><?php esc_html_e( 'Today', 'aaraa-white-label-admin' ); ?></a>
				</span>
			</form>

			<div style="display:flex;flex-wrap:wrap;gap:12px;margin:0 0 18px;">
				<?php
				$this->stat_card( __( 'Total Orders', 'aaraa-white-label-admin' ), $counts['total_orders'], '#0F172A' );
				$this->stat_card( __( 'Active Subscription', 'aaraa-white-label-admin' ), $counts['active_subs'], '#14B8C4' );
				$this->stat_card( __( 'One Time Order', 'aaraa-white-label-admin' ), $counts['one_time'], '#3B82F6' );
				$this->stat_card( __( 'Subscription Parent', 'aaraa-white-label-admin' ), $counts['parent'], '#8B5CF6' );
				$this->stat_card( __( 'Subscription Renewal', 'aaraa-white-label-admin' ), $counts['renewal'], '#10B981' );
				$this->stat_card( __( 'Wallet Order', 'aaraa-white-label-admin' ), $counts['wallet'], '#F59E0B' );
				$this->stat_card( __( 'Duplicate Renewal', 'aaraa-white-label-admin' ), $counts['duplicate'], '#DB2777' );
				$this->stat_card( __( 'Waiting for Renewal', 'aaraa-white-label-admin' ), $counts['waiting'], '#6366F1' );
				$this->stat_card( __( 'Renewal Skipped', 'aaraa-white-label-admin' ), $counts['skipped'], '#EF4444' );

				// Status-wise boxes for the day's orders (busiest status first).
				foreach ( $status_counts as $status_key => $status_num ) {
					$label = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status_key ) : ucwords( str_replace( '-', ' ', (string) $status_key ) );
					$this->stat_card(
						/* translators: %s: order status name */
						sprintf( __( 'Status: %s', 'aaraa-white-label-admin' ), $label ),
						$status_num,
						$this->status_color( $status_key )
					);
				}
				?>
			</div>

			<p class="description" style="margin:0 0 12px;">
				<?php
				printf(
					/* translators: 1: number of rows, 2: date. */
					esc_html__( '%1$s record(s) on %2$s.', 'aaraa-white-label-admin' ),
					esc_html( number_format_i18n( count( $rows ) ) ),
					esc_html( $pretty )
				);
				?>
			</p>

			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Order ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Subscription ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Customer Name', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Mobile', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Products × Qty', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $rows ) : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$row_bg = '';
							if ( ! empty( $row['is_duplicate'] ) ) {
								$row_bg = ' style="background:#FCE7F3;"'; // Duplicate renewal — pink.
							} elseif ( 'waiting' === $row['type'] ) {
								$row_bg = ' style="background:#EEF2FF;"'; // Waiting for renewal — indigo.
							} elseif ( 'skipped' === $row['type'] ) {
								$row_bg = ' style="background:#FEF2F2;"'; // Renewal skipped — red.
							}
							?>
							<tr<?php echo $row_bg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<td>
									<?php echo esc_html( $this->type_label( $row['type'] ) ); ?>
									<?php if ( ! empty( $row['is_duplicate'] ) ) : ?>
										<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:8px;background:#DB2777;color:#fff;font-size:11px;font-weight:600;"><?php esc_html_e( 'Duplicate', 'aaraa-white-label-admin' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $row['order_id'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['order_id'] . '&action=edit' ) ); ?>">#<?php echo (int) $row['order_id']; ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $row['sub_id'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['sub_id'] . '&action=edit' ) ); ?>">#<?php echo (int) $row['sub_id']; ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td><?php echo $row['customer'] ? esc_html( $row['customer'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['mobile'] ? esc_html( $row['mobile'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['products'] ? esc_html( $row['products'] ) : '&mdash;'; ?></td>
								<td><?php echo wp_kses_post( wc_price( $row['amount'] ) ); ?></td>
								<td>
									<?php
									if ( 'skipped' === $row['type'] ) {
										echo esc_html__( 'Renewal skipped', 'aaraa-white-label-admin' );
									} elseif ( 'waiting' === $row['type'] ) {
										echo esc_html__( 'Waiting for renewal', 'aaraa-white-label-admin' );
									} else {
										echo esc_html( $this->status_label( $row['status'] ) );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'No orders or skipped renewals on this date.', 'aaraa-white-label-admin' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		echo '</div>';
	}
}
