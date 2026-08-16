<?php
/**
 * Deliveries PDF.
 *
 * Streams the delivery run sheet as an inline PDF so the Deliveries PDF page can
 * show it in an iframe, and provides the order lookup that page lists.
 *
 * PDF rendering reuses whichever dompdf copy is already installed on the site
 * (bundled by the wallet or invoice plugins) rather than shipping another one.
 * When none can be loaded the same document is served as printable HTML, so the
 * iframe still shows something usable instead of a broken frame.
 *
 * @package wcfmd/core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delivery run sheet renderer.
 */
class WCFMd_Deliveries_PDF {

	const ACTION = 'wcfmd_deliveries_pdf';
	const NONCE  = 'wcfmd_deliveries_pdf';

	/**
	 * Slot id => name, loaded once per request.
	 *
	 * @var array|null
	 */
	private $slots = null;

	/**
	 * Hook the stream endpoint.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'stream' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Access.
	 * --------------------------------------------------------------------- */

	/**
	 * Whose deliveries the current viewer may see.
	 *
	 * A delivery person is always locked to their own id; a store owner or admin
	 * may look at anyone by passing delivery_boy.
	 *
	 * @param int $requested Requested delivery person id.
	 * @return int|false Delivery person id, 0 for "everyone", false when not allowed.
	 */
	public function viewable_delivery_boy( $requested = 0 ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( function_exists( 'wcfm_is_delivery_boy' ) && wcfm_is_delivery_boy() ) {
			return get_current_user_id();
		}

		if ( current_user_can( 'manage_woocommerce' ) || ( function_exists( 'wcfm_is_vendor' ) && wcfm_is_vendor() ) ) {
			return absint( $requested );
		}

		return false;
	}

	/* --------------------------------------------------------------------- *
	 * Data.
	 * --------------------------------------------------------------------- */

	/**
	 * Delivery rows grouped one per order.
	 *
	 * @param int    $delivery_boy Delivery person id, 0 for everyone.
	 * @param string $status       pending|delivered, empty for both.
	 * @param int    $order_id     Limit to a single order, 0 for all.
	 * @return array
	 */
	public function get_delivery_orders( $delivery_boy = 0, $status = '', $order_id = 0 ) {
		global $wpdb;

		$sql    = "SELECT order_id, vendor_id, delivery_boy, MIN(delivery_status) AS delivery_status, MIN(delivery_date) AS delivery_date
			FROM `{$wpdb->prefix}wcfm_delivery_orders` WHERE is_trashed = 0";
		$params = array();

		if ( $delivery_boy ) {
			$sql     .= ' AND delivery_boy = %d';
			$params[] = $delivery_boy;
		}
		if ( $status ) {
			$sql     .= ' AND delivery_status = %s';
			$params[] = $status;
		}
		if ( $order_id ) {
			$sql     .= ' AND order_id = %d';
			$params[] = $order_id;
		}

		// delivery_boy is grouped rather than aggregated so the sheet stays split
		// per person when an order is shared; the aggregates above keep this
		// valid under ONLY_FULL_GROUP_BY.
		$sql .= ' GROUP BY order_id, vendor_id, delivery_boy ORDER BY order_id ASC';

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Flatten a delivery row into everything the sheet prints.
	 *
	 * @param object $row Delivery row.
	 * @return array|false
	 */
	public function order_summary( $row ) {
		$order = wc_get_order( $row->order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$address = $order->get_formatted_shipping_address();
		if ( ! $address ) {
			$address = $order->get_formatted_billing_address();
		}
		$address = trim( wp_strip_all_tags( str_replace( '<br/>', ', ', (string) $address ) ) );

		$phone = $order->get_billing_phone();
		if ( ! $phone ) {
			$phone = (string) $order->get_meta( '_shipping_mobile_number' );
		}

		// List the whole order, dropping only the lines that belong to a
		// different delivery person.
		//
		// Deliberately not driven off the wcfm_delivery_orders rows: that table
		// holds one row per assigned item, and an item whose row never got
		// written would disappear from the sheet — the driver would arrive a
		// product short with nothing on paper to say so. An unassigned line is
		// still part of the order, so it prints.
		$mine  = (int) $row->delivery_boy;
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$assigned = (int) wc_get_order_item_meta( $item_id, 'wcfm_delivery_boy', true );
			if ( $mine && $assigned && $assigned !== $mine ) {
				continue;
			}
			$items[] = array(
				'name' => $item->get_name(),
				'qty'  => $item->get_quantity(),
			);
		}

		return array(
			'order_id' => $order->get_id(),
			'number'   => $order->get_order_number(),
			'date'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd M Y' ) : '',
			'customer' => trim( $order->get_formatted_billing_full_name() ),
			'phone'    => $phone,
			'address'  => $address,
			'items'    => $items,
			'total'    => $order->get_formatted_order_total(),
			'payment'  => $order->get_payment_method_title(),
			'status'   => $row->delivery_status,
			'slot'     => $this->slot_name( (int) $order->get_meta( '_aaraa_delivery_slot' ) ),
			'note'     => $order->get_customer_note(),
		);
	}

	/**
	 * Resolve a delivery slot id to its name.
	 *
	 * Slots are owned by the Aaraa admin plugin, so this reads its table only
	 * when it exists and simply prints nothing when it does not.
	 *
	 * @param int $slot_id Slot id.
	 * @return string
	 */
	private function slot_name( $slot_id ) {
		global $wpdb;

		if ( ! $slot_id ) {
			return '';
		}

		if ( null === $this->slots ) {
			$this->slots = array();
			$table       = $wpdb->prefix . 'aaraa_delivery_slots';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { // phpcs:ignore WordPress.DB
				foreach ( (array) $wpdb->get_results( "SELECT id, name, start_time, end_time FROM {$table}" ) as $slot ) { // phpcs:ignore WordPress.DB
					$label = $slot->name;
					if ( $slot->start_time || $slot->end_time ) {
						$label .= ' (' . $slot->start_time . '-' . $slot->end_time . ')';
					}
					$this->slots[ (int) $slot->id ] = $label;
				}
			}
		}

		return isset( $this->slots[ $slot_id ] ) ? $this->slots[ $slot_id ] : '';
	}

	/* --------------------------------------------------------------------- *
	 * Stream.
	 * --------------------------------------------------------------------- */

	/**
	 * Serve the run sheet inline for the iframe.
	 *
	 * @return void
	 */
	public function stream() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_die( esc_html__( 'Link expired — reload the page and try again.', 'wc-frontend-manager-delivery' ) );
		}

		$requested    = isset( $_GET['delivery_boy'] ) ? absint( $_GET['delivery_boy'] ) : 0;
		$delivery_boy = $this->viewable_delivery_boy( $requested );
		if ( false === $delivery_boy ) {
			wp_die( esc_html__( 'You are not allowed to view these deliveries.', 'wc-frontend-manager-delivery' ) );
		}

		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$status   = in_array( $status, array( 'pending', 'delivered' ), true ) ? $status : '';
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$download = ! empty( $_GET['download'] );

		$rows    = $this->get_delivery_orders( $delivery_boy, $status, $order_id );
		$orders  = array();
		foreach ( $rows as $row ) {
			$summary = $this->order_summary( $row );
			if ( $summary ) {
				$orders[] = $summary;
			}
		}

		$html = $this->sheet_html( $orders, $status, $delivery_boy );
		$name = 'deliveries-' . ( $order_id ? $order_id : gmdate( 'Y-m-d' ) );

		nocache_headers();

		$dompdf = $this->dompdf();
		if ( ! $dompdf ) {
			// No PDF engine — printable HTML keeps the iframe usable.
			header( 'Content-Type: text/html; charset=utf-8' );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		// Attachment=false so the browser renders it in the frame instead of
		// prompting a download every time the page loads.
		$dompdf->stream( $name . '.pdf', array( 'Attachment' => $download ) );
		exit;
	}

	/**
	 * Load dompdf from whichever plugin already bundles it.
	 *
	 * @return \Dompdf\Dompdf|null
	 */
	private function dompdf() {
		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			$candidates = array(
				WP_PLUGIN_DIR . '/wallet-system-for-woocommerce/package/lib/dompdf/vendor/autoload.php',
				WP_PLUGIN_DIR . '/print-invoices-packing-slip-labels-for-woocommerce/includes/vendor/autoload.php',
			);
			foreach ( $candidates as $file ) {
				if ( is_readable( $file ) ) {
					require_once $file;
					if ( class_exists( '\Dompdf\Dompdf' ) ) {
						break;
					}
				}
			}
		}
		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			return null;
		}
		return new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false ) );
	}

	/**
	 * The run sheet document.
	 *
	 * @param array  $orders       Order summaries.
	 * @param string $status       Active status filter.
	 * @param int    $delivery_boy Delivery person id.
	 * @return string
	 */
	private function sheet_html( $orders, $status, $delivery_boy ) {
		$person = $delivery_boy ? get_userdata( $delivery_boy ) : null;
		$title  = __( 'Delivery Run Sheet', 'wc-frontend-manager-delivery' );

		$meta = array( gmdate( 'd M Y' ) );
		if ( $person ) {
			$meta[] = $person->display_name;
		}
		if ( $status ) {
			$meta[] = ucfirst( $status );
		}
		$meta[] = sprintf(
			/* translators: %d: number of orders */
			_n( '%d order', '%d orders', count( $orders ), 'wc-frontend-manager-delivery' ),
			count( $orders )
		);

		$html  = '<html><head><meta charset="utf-8"><style>';
		$html .= 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#0F172A;margin:0;}';
		$html .= 'h1{font-size:17px;margin:0 0 2px;}';
		$html .= '.sub{font-size:10px;color:#64748B;margin:0 0 14px;}';
		$html .= '.card{border:1px solid #CBD5E1;border-radius:4px;margin-bottom:10px;page-break-inside:avoid;}';
		$html .= '.card h2{font-size:12px;margin:0;padding:6px 9px;background:#0F766E;color:#fff;}';
		$html .= '.card h2 span{float:right;font-weight:normal;font-size:10px;}';
		$html .= '.body{padding:8px 9px;}';
		$html .= '.row{margin-bottom:3px;}';
		$html .= '.k{display:inline-block;width:74px;color:#64748B;}';
		$html .= 'table{width:100%;border-collapse:collapse;margin-top:6px;}';
		$html .= 'th,td{border:1px solid #E2E8F0;padding:4px 6px;text-align:left;}';
		$html .= 'th{background:#F1F5F9;font-size:10px;}';
		$html .= 'td.q{text-align:right;width:60px;}';
		$html .= '.empty{padding:22px;text-align:center;color:#64748B;border:1px dashed #CBD5E1;border-radius:4px;}';
		$html .= 'h2.sum{font-size:13px;margin:18px 0 6px;}';
		$html .= 'table.summary{width:60%;}';
		$html .= 'table.summary th{background:#0F766E;color:#fff;}';
		$html .= 'table.summary td.q,table.summary th.q{text-align:right;width:110px;}';
		$html .= 'table.summary tfoot td{font-weight:bold;background:#EEF2F6;}';
		$html .= '</style></head><body>';

		$html .= '<h1>' . esc_html( get_bloginfo( 'name' ) ) . ' — ' . esc_html( $title ) . '</h1>';
		$html .= '<p class="sub">' . esc_html( implode( ' · ', $meta ) ) . '</p>';

		if ( empty( $orders ) ) {
			$html .= '<p class="empty">' . esc_html__( 'No deliveries to show.', 'wc-frontend-manager-delivery' ) . '</p>';
			return $html . '</body></html>';
		}

		foreach ( $orders as $order ) {
			$html .= '<div class="card">';
			$html .= '<h2>#' . esc_html( $order['number'] ) . ' — ' . esc_html( $order['customer'] );
			$html .= '<span>' . esc_html( ucfirst( $order['status'] ) ) . '</span></h2>';
			$html .= '<div class="body">';

			if ( $order['phone'] ) {
				$html .= '<div class="row"><span class="k">' . esc_html__( 'Mobile', 'wc-frontend-manager-delivery' ) . '</span>' . esc_html( $order['phone'] ) . '</div>';
			}
			$html .= '<div class="row"><span class="k">' . esc_html__( 'Address', 'wc-frontend-manager-delivery' ) . '</span>' . esc_html( $order['address'] ) . '</div>';
			if ( $order['slot'] ) {
				$html .= '<div class="row"><span class="k">' . esc_html__( 'Slot', 'wc-frontend-manager-delivery' ) . '</span>' . esc_html( $order['slot'] ) . '</div>';
			}
			$html .= '<div class="row"><span class="k">' . esc_html__( 'Payment', 'wc-frontend-manager-delivery' ) . '</span>' . wp_kses_post( $order['total'] );
			if ( $order['payment'] ) {
				$html .= ' (' . esc_html( $order['payment'] ) . ')';
			}
			$html .= '</div>';
			if ( $order['note'] ) {
				$html .= '<div class="row"><span class="k">' . esc_html__( 'Note', 'wc-frontend-manager-delivery' ) . '</span>' . esc_html( $order['note'] ) . '</div>';
			}

			$html .= '<table><thead><tr><th>' . esc_html__( 'Product', 'wc-frontend-manager-delivery' ) . '</th>';
			$html .= '<th class="q">' . esc_html__( 'Qty', 'wc-frontend-manager-delivery' ) . '</th></tr></thead><tbody>';
			foreach ( $order['items'] as $item ) {
				$html .= '<tr><td>' . esc_html( $item['name'] ) . '</td><td class="q">' . esc_html( $item['qty'] ) . '</td></tr>';
			}
			$html .= '</tbody></table>';

			$html .= '</div></div>';
		}

		// Order summary — the total quantity of each product across the whole run,
		// so the person knows what to load before setting off.
		$summary = $this->product_totals( $orders );
		if ( ! empty( $summary ) ) {
			$total = 0.0;
			$html .= '<h2 class="sum">' . esc_html__( 'Order Summary', 'wc-frontend-manager-delivery' ) . '</h2>';
			$html .= '<table class="summary"><thead><tr>';
			$html .= '<th>' . esc_html__( 'Product Name', 'wc-frontend-manager-delivery' ) . '</th>';
			$html .= '<th class="q">' . esc_html__( 'Total Quantity', 'wc-frontend-manager-delivery' ) . '</th>';
			$html .= '</tr></thead><tbody>';
			foreach ( $summary as $name => $qty ) {
				$total += $qty;
				$html  .= '<tr><td>' . esc_html( $name ) . '</td><td class="q">' . esc_html( $this->qty_label( $qty ) ) . '</td></tr>';
			}
			$html .= '</tbody><tfoot><tr>';
			$html .= '<td>' . esc_html__( 'Total', 'wc-frontend-manager-delivery' ) . '</td>';
			$html .= '<td class="q">' . esc_html( $this->qty_label( $total ) ) . '</td>';
			$html .= '</tr></tfoot></table>';
		}

		return $html . '</body></html>';
	}

	/**
	 * Aggregate line items across all orders into product name => total quantity.
	 *
	 * Keyed on the item name, so it reads the same as the per-order tables above.
	 *
	 * @param array $orders Order summaries.
	 * @return array<string, float>
	 */
	private function product_totals( $orders ) {
		$totals = array();
		foreach ( $orders as $order ) {
			foreach ( $order['items'] as $item ) {
				$name = $item['name'];
				if ( '' === $name ) {
					continue;
				}
				if ( ! isset( $totals[ $name ] ) ) {
					$totals[ $name ] = 0.0;
				}
				$totals[ $name ] += (float) $item['qty'];
			}
		}
		ksort( $totals, SORT_NATURAL | SORT_FLAG_CASE );
		return $totals;
	}

	/**
	 * Format a quantity: whole numbers stay whole, fractions keep their decimals.
	 *
	 * @param float $qty Quantity.
	 * @return string
	 */
	private function qty_label( $qty ) {
		$qty = (float) $qty;
		if ( abs( $qty - round( $qty ) ) < 0.0001 ) {
			return (string) (int) round( $qty );
		}
		return rtrim( rtrim( number_format( $qty, 3, '.', '' ), '0' ), '.' );
	}

	/**
	 * URL of the streamed document.
	 *
	 * @param array $args Extra query args (order_id, status, download, delivery_boy).
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'action' => self::ACTION,
					'nonce'  => wp_create_nonce( self::NONCE ),
				),
				$args
			),
			admin_url( 'admin-ajax.php' )
		);
	}
}
