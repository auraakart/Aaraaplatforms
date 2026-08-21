<?php
/**
 * CSV exports for the Aaraa Customers list, and the WooCommerce Orders and
 * Subscriptions list tables.
 *
 * Each page gets an "Export CSV" button that carries the currently-applied
 * filters. The download streams the full filtered set (all pages), in batches,
 * so it works on large stores without exhausting memory.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * CSV export handlers + buttons.
 */
class Exports {

	const QVAR  = 'aaraa_export';
	const NONCE = 'aaraa_export';
	const FIELD = '_aaraa_nonce';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		// Stream the export early, before any page output.
		add_action( 'admin_init', array( $this, 'maybe_export' ) );
		// Export button on the Orders / Subscriptions filter row.
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'orders_export_button' ), 25, 2 );
	}

	/**
	 * Build a nonce-protected export URL from the current request (preserving filters).
	 *
	 * @param string $type customers|orders|subscriptions.
	 * @return string
	 */
	public static function export_url( $type ) {
		$url = add_query_arg( self::QVAR, sanitize_key( $type ) );
		return wp_nonce_url( $url, self::NONCE, self::FIELD );
	}

	/**
	 * Render the Export CSV button on the Orders / Subscriptions list tables.
	 *
	 * @param string $order_type Order type.
	 * @param string $which      Tablenav position.
	 * @return void
	 */
	public function orders_export_button( $order_type = 'shop_order', $which = 'top' ) {
		if ( 'top' !== $which ) {
			return;
		}
		if ( 'shop_order' === $order_type ) {
			$type = 'orders';
		} elseif ( 'shop_subscription' === $order_type ) {
			$type = 'subscriptions';
		} else {
			return;
		}
		printf(
			' <a class="button" href="%s">%s</a>',
			esc_url( self::export_url( $type ) ),
			esc_html__( 'Export CSV', 'aaraa-white-label-admin' )
		);
	}

	/* --------------------------------------------------------------------- *
	 * Dispatch.
	 * --------------------------------------------------------------------- */

	/**
	 * Detect an export request and stream the CSV.
	 *
	 * @return void
	 */
	public function maybe_export() {
		if ( empty( $_GET[ self::QVAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$type = sanitize_key( wp_unslash( $_GET[ self::QVAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $type, array( 'customers', 'orders', 'subscriptions' ), true ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to export.', 'aaraa-white-label-admin' ) );
		}
		check_admin_referer( self::NONCE, self::FIELD );

		if ( 'customers' === $type ) {
			$this->export_customers();
		} else {
			$this->export_orders( 'subscriptions' === $type ? 'shop_subscription' : 'shop_order' );
		}
		// export_* stream and exit.
	}

	/* --------------------------------------------------------------------- *
	 * Exporters.
	 * --------------------------------------------------------------------- */

	/**
	 * Export the Aaraa customers list (respecting the list's filters).
	 *
	 * @return void
	 */
	private function export_customers() {
		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		$table = new Customers_List_Table();
		$ids   = $table->export_ids();

		$slots = $this->slot_map();
		$hubs  = $this->hub_map();

		$out = $this->open_csv( 'customers-' . gmdate( 'Ymd-His' ) . '.csv' );
		fputcsv(
			$out,
			array( 'ID', 'First Name', 'Last Name', 'Display Name', 'Email', 'Mobile', 'Wallet Balance', 'Delivery Slot', 'Delivery Hub', 'Delivery Boy', 'Roles', 'Registered' )
		);

		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			cache_users( $chunk );
			foreach ( $chunk as $id ) {
				$u = get_userdata( $id );
				if ( ! $u ) {
					continue;
				}
				$mobile = get_user_meta( $id, 'billing_phone', true );
				if ( '' === $mobile ) {
					$mobile = get_user_meta( $id, 'mobile', true );
				}
				$slot = (int) get_user_meta( $id, Customer_Delivery::META_SLOT, true );
				$hub  = (int) get_user_meta( $id, Customer_Delivery::META_HUB, true );
				$boy  = (int) get_user_meta( $id, Customer_Delivery::META_BOY, true );

				fputcsv(
					$out,
					array(
						$id,
						get_user_meta( $id, 'first_name', true ),
						get_user_meta( $id, 'last_name', true ),
						$u->display_name,
						$u->user_email,
						$mobile,
						Customers_Admin::get_wallet_balance( $id ),
						isset( $slots[ $slot ] ) ? $slots[ $slot ] : '',
						isset( $hubs[ $hub ] ) ? $hubs[ $hub ] : '',
						$boy ? $this->user_name( $boy ) : '',
						implode( '|', (array) $u->roles ),
						$u->user_registered,
					)
				);
			}
		}
		fclose( $out );
		exit;
	}

	/**
	 * Export orders or subscriptions (respecting the list-table filters).
	 *
	 * @param string $type shop_order|shop_subscription.
	 * @return void
	 */
	private function export_orders( $type ) {
		$is_sub = ( 'shop_subscription' === $type );
		$slots  = $this->slot_map();
		$hubs   = $this->hub_map();

		$out = $this->open_csv( ( $is_sub ? 'subscriptions-' : 'orders-' ) . gmdate( 'Ymd-His' ) . '.csv' );

		if ( $is_sub ) {
			fputcsv( $out, array( 'Subscription', 'Status', 'Customer', 'Mobile', 'Email', 'Recurring Total', 'Start Date', 'Next Payment', 'Delivery Schedule', 'Pause Dates', 'Delivery Slot', 'Delivery Hub', 'Delivery Boy', 'Items' ) );
		} else {
			fputcsv( $out, array( 'Order', 'Date', 'Status', 'Customer', 'Mobile', 'Email', 'Total', 'Payment Method', 'Delivery Slot', 'Delivery Hub', 'Delivery Boy', 'Items', 'Billing Address', 'Shipping Address' ) );
		}

		$page = 1;
		do {
			$res    = wc_get_orders( $this->order_query_args( $type, $page, 200 ) );
			$orders = ( is_object( $res ) && isset( $res->orders ) ) ? $res->orders : array();
			$max    = ( is_object( $res ) && isset( $res->max_num_pages ) ) ? (int) $res->max_num_pages : 1;

			if ( empty( $orders ) ) {
				break;
			}

			foreach ( $orders as $order ) {
				if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
					continue;
				}

				$slot = (int) $order->get_meta( Order_Delivery::META_SLOT );
				$hub  = (int) $order->get_meta( Order_Delivery::META_HUB );
				$boy  = (int) $order->get_meta( Order_Delivery::META_BOY );

				$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
				if ( '' === $name ) {
					$name = $order->get_formatted_billing_full_name();
				}

				$items = array();
				foreach ( $order->get_items() as $item ) {
					$items[] = $item->get_name() . ' x' . $item->get_quantity();
				}

				$slot_name = isset( $slots[ $slot ] ) ? $slots[ $slot ] : '';
				$hub_name  = isset( $hubs[ $hub ] ) ? $hubs[ $hub ] : '';
				$boy_name  = $boy ? $this->user_name( $boy ) : '';

				if ( $is_sub ) {
					fputcsv(
						$out,
						array(
							$order->get_order_number(),
							$order->get_status(),
							$name,
							$order->get_billing_phone(),
							$order->get_billing_email(),
							$order->get_total(),
							method_exists( $order, 'get_date' ) ? $order->get_date( 'start', 'site' ) : '',
							method_exists( $order, 'get_date' ) ? $order->get_date( 'next_payment', 'site' ) : '',
							$this->delivery_schedule_label( $order ),
							$this->pause_dates_list( $order ),
							$slot_name,
							$hub_name,
							$boy_name,
							implode( '; ', $items ),
						)
					);
				} else {
					$created = $order->get_date_created();
					fputcsv(
						$out,
						array(
							$order->get_order_number(),
							$created ? $created->date( 'Y-m-d H:i' ) : '',
							$order->get_status(),
							$name,
							$order->get_billing_phone(),
							$order->get_billing_email(),
							$order->get_total(),
							$order->get_payment_method_title(),
							$slot_name,
							$hub_name,
							$boy_name,
							implode( '; ', $items ),
							$this->one_line( $order->get_formatted_billing_address() ),
							$this->one_line( $order->get_formatted_shipping_address() ),
						)
					);
				}
			}

			++$page;
		} while ( $page <= $max );

		fclose( $out );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Query args (mirror the list-table filters).
	 * --------------------------------------------------------------------- */

	/**
	 * Build wc_get_orders() args from the current request filters.
	 *
	 * @param string $type shop_order|shop_subscription.
	 * @param int    $page Page number.
	 * @param int    $per  Rows per batch.
	 * @return array
	 */
	private function order_query_args( $type, $page, $per ) {
		$args = array(
			'type'     => $type,
			'limit'    => $per,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only export filters.
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		if ( $status && 'all' !== $status ) {
			$args['status'] = $status;
		}

		$customer = isset( $_GET['_customer_user'] ) ? absint( wp_unslash( $_GET['_customer_user'] ) ) : 0;
		if ( $customer ) {
			$args['customer'] = $customer;
		}

		$from = $this->clean_date( isset( $_GET['order_date_from'] ) ? wp_unslash( $_GET['order_date_from'] ) : '' );
		$to   = $this->clean_date( isset( $_GET['order_date_to'] ) ? wp_unslash( $_GET['order_date_to'] ) : '' );
		if ( $from && $to ) {
			if ( $from > $to ) {
				$tmp  = $from;
				$from = $to;
				$to   = $tmp;
			}
			$args['date_created'] = $from . '...' . $to;
		} elseif ( $from ) {
			$args['date_created'] = '>=' . $from;
		} elseif ( $to ) {
			$args['date_created'] = '<=' . $to;
		} else {
			$m = isset( $_GET['m'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_GET['m'] ) ) : '';
			if ( 6 === strlen( $m ) ) {
				$year  = (int) substr( $m, 0, 4 );
				$month = (int) substr( $m, 4, 2 );
				if ( $month >= 1 && $month <= 12 ) {
					$last                 = gmdate( 'Y-m-t', strtotime( "$year-$month-01" ) );
					$args['date_created'] = sprintf( '%04d-%02d-01...%s', $year, $month, $last );
				}
			}
		}

		$slot = isset( $_GET['aaraa_slot'] ) ? absint( wp_unslash( $_GET['aaraa_slot'] ) ) : 0;
		$hub  = isset( $_GET['aaraa_hub'] ) ? absint( wp_unslash( $_GET['aaraa_hub'] ) ) : 0;
		$boy  = isset( $_GET['aaraa_boy'] ) ? (int) wp_unslash( $_GET['aaraa_boy'] ) : 0;
		// phpcs:enable

		$meta = array();
		if ( $slot ) {
			$meta[] = array(
				'key'     => Order_Delivery::META_SLOT,
				'value'   => $slot,
				'compare' => '=',
			);
		}
		if ( $hub ) {
			$meta[] = array(
				'key'     => Order_Delivery::META_HUB,
				'value'   => $hub,
				'compare' => '=',
			);
		}
		if ( -1 === $boy ) {
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => Order_Delivery::META_BOY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => Order_Delivery::META_BOY,
					'value'   => array( '', '0' ),
					'compare' => 'IN',
				),
			);
		} elseif ( $boy > 0 ) {
			$meta[] = array(
				'key'     => Order_Delivery::META_BOY,
				'value'   => $boy,
				'compare' => '=',
			);
		}
		if ( $meta ) {
			$args['meta_query'] = array( array_merge( array( 'relation' => 'AND' ), $meta ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $args;
	}

	/* --------------------------------------------------------------------- *
	 * Helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * Send CSV download headers and return the output stream.
	 *
	 * @param string $filename Download file name.
	 * @return resource
	 */
	private function open_csv( $filename ) {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads unicode correctly.
		return $out;
	}

	/**
	 * Slot id => name map.
	 *
	 * @return array<int, string>
	 */
	private function slot_map() {
		$map = array();
		if ( class_exists( __NAMESPACE__ . '\\Delivery_Admin' ) ) {
			foreach ( Delivery_Admin::slots_list() as $s ) {
				$map[ (int) $s->id ] = $s->name;
			}
		}
		return $map;
	}

	/**
	 * Hub id => name map.
	 *
	 * @return array<int, string>
	 */
	private function hub_map() {
		$map = array();
		if ( class_exists( __NAMESPACE__ . '\\Delivery_Admin' ) ) {
			foreach ( Delivery_Admin::hubs_list() as $h ) {
				$map[ (int) $h->ID ] = $h->name;
			}
		}
		return $map;
	}

	/**
	 * A user's display name (or #id).
	 *
	 * @param int $id User id.
	 * @return string
	 */
	private function user_name( $id ) {
		$u = get_userdata( $id );
		return $u ? ( $u->display_name ? $u->display_name : $u->user_login ) : '#' . $id;
	}

	/**
	 * Flatten a formatted address to a single CSV-friendly line.
	 *
	 * @param string $address Formatted (HTML) address.
	 * @return string
	 */
	private function one_line( $address ) {
		return trim( wp_strip_all_tags( str_replace( array( '<br/>', '<br>', '<br />' ), ', ', (string) $address ) ) );
	}

	/**
	 * Validate a Y-m-d date string (or '').
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function clean_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		$p = explode( '-', $value );
		return checkdate( (int) $p[1], (int) $p[2], (int) $p[0] ) ? $value : '';
	}

	/**
	 * Readable delivery-schedule label for a subscription (type + custom days).
	 *
	 * @param \WC_Abstract_Order $order Subscription object.
	 * @return string e.g. "Custom Day (Tue, Wed)" or "Every Day".
	 */
	private function delivery_schedule_label( $order ) {
		$type = (string) $order->get_meta( '_wcfm_delivery_schedule' );
		if ( '' === $type ) {
			return '';
		}

		$types = class_exists( __NAMESPACE__ . '\\Subscription_Delivery' )
			? Subscription_Delivery::schedule_types()
			: array();
		$label = isset( $types[ $type ] ) ? $types[ $type ] : $type;

		if ( 'custom' === $type ) {
			$days = $order->get_meta( '_wcfm_delivery_days' );
			$days = is_array( $days ) ? array_map( 'intval', $days ) : array();
			if ( ! empty( $days ) && class_exists( __NAMESPACE__ . '\\Subscription_Delivery' ) ) {
				$names = array();
				foreach ( Subscription_Delivery::weekdays() as $num => $name ) {
					if ( in_array( $num, $days, true ) ) {
						$names[] = $name;
					}
				}
				if ( $names ) {
					$label .= ' (' . implode( ', ', $names ) . ')';
				}
			}
		}

		return $label;
	}

	/**
	 * Semicolon-separated list of a subscription's chosen pause dates.
	 *
	 * @param \WC_Abstract_Order $order Subscription object.
	 * @return string e.g. "2026-08-21; 2026-08-25", or '' if none.
	 */
	private function pause_dates_list( $order ) {
		$raw = $order->get_meta( '_wcfmu_pause_dates' );
		if ( empty( $raw ) ) {
			$raw = get_post_meta( $order->get_id(), '_wcfmu_pause_dates', true );
		}
		if ( empty( $raw ) ) {
			return '';
		}
		$dates = class_exists( __NAMESPACE__ . '\\Subscription_API' )
			? Subscription_API::parse_pause_dates( $raw )
			: array();
		return implode( '; ', $dates );
	}
}
