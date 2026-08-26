<?php
/**
 * Daily Delivery Report.
 *
 * Lists the day's orders one row per line item, with the delivery mapping
 * (slot / hub / person) resolved to names, and can export the same table as a
 * PDF or email it as an attachment.
 *
 * PDF rendering reuses whichever dompdf copy is already present on the site
 * (bundled by the wallet or invoice plugins) rather than shipping another one.
 * If none can be loaded the report degrades to a printable HTML download.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Daily delivery report screen.
 */
class Delivery_Report {

	const PAGE = 'aaraa-delivery-report';

	/**
	 * Cron hook fired once a day at the configured time.
	 */
	const CRON_HOOK = 'aaraa_daily_delivery_report';

	/**
	 * Option holding the schedule settings.
	 */
	const OPTION = 'aaraa_delivery_report_schedule';

	/**
	 * Option holding the outcome of the last automatic run.
	 */
	const OPTION_LAST = 'aaraa_delivery_report_last_run';

	/**
	 * Register hooks. Export/mail run on admin_init so they can stream or redirect.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Scheduling.
	 * --------------------------------------------------------------------- */

	/**
	 * Schedule settings, merged over the defaults.
	 *
	 * @return array{enabled:int, time:string, email:string, scope:string}
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		return wp_parse_args(
			$saved,
			array(
				'enabled' => 0,
				'time'    => '06:00',
				'email'   => get_option( 'admin_email' ),
				'scope'   => 'today',
				'fmt_pdf' => 1,
				'fmt_csv' => 0,
			)
		);
	}

	/**
	 * The next occurrence of HH:MM in site time, as a UTC timestamp.
	 *
	 * Built from wp_timezone() rather than time()+offset so DST transitions
	 * keep the mail arriving at the same wall-clock time.
	 *
	 * @param string $time HH:MM.
	 * @return int
	 */
	private static function next_run( $time ) {
		$tz  = wp_timezone();
		$now = new \DateTimeImmutable( 'now', $tz );

		$parts = explode( ':', $time );
		$hour  = isset( $parts[0] ) ? (int) $parts[0] : 6;
		$min   = isset( $parts[1] ) ? (int) $parts[1] : 0;

		$next = $now->setTime( $hour, $min, 0 );
		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Drop any existing event and re-arm it from the current settings.
	 *
	 * @return void
	 */
	public static function reschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );

		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		wp_schedule_event( self::next_run( $settings['time'] ), 'daily', self::CRON_HOOK );
	}

	/**
	 * Remove the event entirely (deactivation).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Safety net: re-arm the event if it went missing while still enabled.
	 *
	 * Cron entries live in an option that gets wiped by migrations, staging
	 * pulls and some cache plugins, so a silently dead schedule is common.
	 *
	 * @return void
	 */
	public function maybe_schedule() {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		self::reschedule();
	}

	/**
	 * Cron callback: build and email the report.
	 *
	 * @return void
	 */
	public function run_scheduled() {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$date = ( 'yesterday' === $settings['scope'] )
			? gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -1 day' ) )
			: current_time( 'Y-m-d' );

		// One send per report date, whatever else fires this hook.
		$last = get_option( self::OPTION_LAST, array() );
		$last = is_array( $last ) ? $last : array();
		if ( isset( $last['date'] ) && $last['date'] === $date && 'sent' === ( $last['result'] ?? '' ) ) {
			return;
		}

		$result = $this->mail_report( $date, $settings['email'] );

		update_option(
			self::OPTION_LAST,
			array(
				'date'   => $date,
				'result' => $result,
				'when'   => current_time( 'mysql' ),
				'to'     => $settings['email'],
			),
			false
		);
	}

	/* --------------------------------------------------------------------- *
	 * Data.
	 * --------------------------------------------------------------------- */

	/**
	 * The report date (Y-m-d), defaulting to today in site time.
	 *
	 * @return string
	 */
	private function date() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$date = isset( $_REQUEST['report_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['report_date'] ) ) : '';
		if ( $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}
		return current_time( 'Y-m-d' );
	}

	/**
	 * The active slot / hub / person filters, from the request.
	 *
	 * @return array{slot:int, hub:int, boy:int}
	 */
	private function filters() {
		// phpcs:disable WordPress.Security.NonceVerification
		// Boy filter: 0 = all, -1 = not assigned, positive = a specific delivery boy.
		$boy = isset( $_REQUEST['report_boy'] ) ? (int) $_REQUEST['report_boy'] : 0;
		if ( $boy < -1 ) {
			$boy = 0;
		}
		return array(
			'slot' => isset( $_REQUEST['report_slot'] ) ? absint( $_REQUEST['report_slot'] ) : 0,
			'hub'  => isset( $_REQUEST['report_hub'] ) ? absint( $_REQUEST['report_hub'] ) : 0,
			'boy'  => $boy,
		);
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * The free-text search term from the request (name / email / phone / order id).
	 *
	 * @return string
	 */
	private function search() {
		// phpcs:ignore WordPress.Security.NonceVerification
		return isset( $_REQUEST['report_search'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['report_search'] ) ) ) : '';
	}

	/**
	 * Delivery boys for the filter dropdown.
	 *
	 * Merges two sources so no assignable boy is hidden:
	 *   1. Users in the `wcfm_delivery_boy` role.
	 *   2. Any user actually assigned as a delivery boy on a customer profile.
	 *
	 * Imported/migrated boys often lack the role yet are still assigned to
	 * customers and orders (e.g. "Silambarasan"). A role-only list would hide
	 * them and their orders could never be filtered — so we add them back here.
	 *
	 * @return array<int, array{id:int,name:string,hub:int}>
	 */
	private function delivery_boys_for_filter() {
		$boys = array();

		if ( class_exists( __NAMESPACE__ . '\\Delivery_Admin' ) ) {
			$role_users = get_users(
				array(
					'role'    => Delivery_Admin::ROLE_BOY,
					'orderby' => 'display_name',
					'order'   => 'ASC',
					'number'  => 500,
				)
			);
			foreach ( $role_users as $bu ) {
				$boys[ (int) $bu->ID ] = array(
					'id'   => (int) $bu->ID,
					'name' => $bu->display_name ? $bu->display_name : $bu->user_login,
					'hub'  => (int) get_user_meta( $bu->ID, Delivery_Admin::META_BOY_HUB, true ),
				);
			}
		}

		// Add anyone assigned as a delivery boy on a customer profile who is not
		// already listed (covers boys without the wcfm_delivery_boy role).
		if ( class_exists( __NAMESPACE__ . '\\Customer_Delivery' ) ) {
			global $wpdb;
			$assigned = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> '' AND meta_value <> '0'",
					Customer_Delivery::META_BOY
				)
			);
			foreach ( (array) $assigned as $bid ) {
				$bid = (int) $bid;
				if ( ! $bid || isset( $boys[ $bid ] ) ) {
					continue;
				}
				$u = get_userdata( $bid );
				if ( ! $u ) {
					continue;
				}
				$boys[ $bid ] = array(
					'id'   => $bid,
					'name' => $u->display_name ? $u->display_name : $u->user_login,
					'hub'  => (int) get_user_meta( $bid, Delivery_Admin::META_BOY_HUB, true ),
				);
			}
		}

		uasort(
			$boys,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return array_values( $boys );
	}

	/**
	 * id => name map of delivery slots.
	 *
	 * @return array<int, string>
	 */
	private function slot_map() {
		global $wpdb;
		$table = Delivery_Admin::slots_table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB
			return array();
		}
		$out = array();
		foreach ( (array) $wpdb->get_results( "SELECT id, name, start_time, end_time FROM {$table}" ) as $row ) { // phpcs:ignore WordPress.DB
			$label = $row->name;
			if ( $row->start_time || $row->end_time ) {
				$label .= ' (' . $row->start_time . '-' . $row->end_time . ')';
			}
			$out[ (int) $row->id ] = $label;
		}
		return $out;
	}

	/**
	 * id => name map of delivery hubs.
	 *
	 * @return array<int, string>
	 */
	private function hub_map() {
		global $wpdb;
		$table = Delivery_Admin::hubs_table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB
			return array();
		}
		$out = array();
		foreach ( (array) $wpdb->get_results( "SELECT ID, name FROM {$table}" ) as $row ) { // phpcs:ignore WordPress.DB
			$out[ (int) $row->ID ] = $row->name;
		}
		return $out;
	}

	/**
	 * Build the report rows for a date — one row per order line item.
	 *
	 * @param string                       $date    Y-m-d.
	 * @param array{slot:int,hub:int,boy:int} $filters Optional slot/hub/person filters.
	 * @param string                       $search  Optional free-text term (name/email/phone/order id).
	 * @return array<int, array<string, string>>
	 */
	public function get_rows( $date, $filters = array(), $search = '' ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$filters = wp_parse_args( $filters, array( 'slot' => 0, 'hub' => 0, 'boy' => 0 ) );
		$needle  = ( '' !== $search ) ? strtolower( $search ) : '';

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'date_created' => $date,
				'status'       => array( 'processing' ),
				'orderby'      => 'date',
				'order'        => 'ASC',
			)
		);

		$slots  = $this->slot_map();
		$hubs   = $this->hub_map();
		$people = array();
		$rows   = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			$slot_id = (int) $order->get_meta( Order_Delivery::META_SLOT );
			$hub_id  = (int) $order->get_meta( Order_Delivery::META_HUB );
			$boy_id  = (int) $order->get_meta( Order_Delivery::META_BOY );

			// Apply the slot / hub / person filters.
			if ( $filters['slot'] && $slot_id !== $filters['slot'] ) {
				continue;
			}
			if ( $filters['hub'] && $hub_id !== $filters['hub'] ) {
				continue;
			}
			if ( -1 === $filters['boy'] ) {
				// "Not assigned" — keep only orders with no delivery boy.
				if ( $boy_id ) {
					continue;
				}
			} elseif ( $filters['boy'] && $boy_id !== $filters['boy'] ) {
				continue;
			}

			// Free-text search across name, email, billing phone and order id/number.
			if ( '' !== $needle ) {
				$haystack = strtolower(
					implode(
						' ',
						array(
							(string) $order->get_order_number(),
							(string) $order->get_id(),
							(string) $order->get_billing_first_name(),
							(string) $order->get_billing_last_name(),
							(string) $order->get_billing_email(),
							(string) $order->get_billing_phone(),
							(string) $order->get_meta( '_shipping_mobile_number' ),
						)
					)
				);
				if ( false === strpos( $haystack, $needle ) ) {
					continue;
				}
			}

			if ( $boy_id && ! isset( $people[ $boy_id ] ) ) {
				$user             = get_userdata( $boy_id );
				$people[ $boy_id ] = $user ? $user->display_name : '#' . $boy_id;
			}

			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			if ( '' === $name ) {
				$name = $order->get_formatted_billing_full_name();
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

			$cid     = (int) $order->get_customer_id();
			$sub_id  = $this->subscription_id_for( $order );

			foreach ( $order->get_items() as $item ) {
				$rows[] = array(
					'cid'      => $cid, // for sorting only; not a displayed column.
					'boy_id'   => $boy_id, // for boy-wise grouping; not a displayed column.
					'order'    => $order->get_order_number(),
					'order_id' => $order->get_id(),
					'subscription' => $sub_id ? '#' . $sub_id : '—',
					'customer' => $name,
					'mobile'   => $phone,
					'address'  => $address,
					'product'  => $item->get_name(),
					'qty'      => (string) $item->get_quantity(),
					'slot'     => isset( $slots[ $slot_id ] ) ? $slots[ $slot_id ] : '—',
					'hub'      => isset( $hubs[ $hub_id ] ) ? $hubs[ $hub_id ] : '—',
					'boy'      => isset( $people[ $boy_id ] ) ? $people[ $boy_id ] : '—',
				);
			}
		}

		// Order the report by customer NAME (A→Z, case-insensitive), keeping each
		// customer's line items together. Ties break by customer id (so two people
		// with the same name stay separate), then order number, then product.
		usort(
			$rows,
			static function ( $a, $b ) {
				$byname = strcasecmp( (string) $a['customer'], (string) $b['customer'] );
				if ( 0 !== $byname ) {
					return $byname;
				}
				if ( $a['cid'] !== $b['cid'] ) {
					return $a['cid'] <=> $b['cid'];
				}
				if ( $a['order'] !== $b['order'] ) {
					return strcmp( (string) $a['order'], (string) $b['order'] );
				}
				return strcmp( (string) $a['product'], (string) $b['product'] );
			}
		);

		return $rows;
	}

	/**
	 * Roll the line items up into product name => total quantity.
	 *
	 * Keyed on the line item name rather than the product id so that variations
	 * and renamed products read the same way here as they do in the table above.
	 *
	 * @param array $rows Report rows from get_rows().
	 * @return array<string, float>
	 */
	public function summary_rows( $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			$name = isset( $row['product'] ) ? $row['product'] : '';
			if ( '' === $name ) {
				continue;
			}
			if ( ! isset( $out[ $name ] ) ) {
				$out[ $name ] = 0.0;
			}
			$out[ $name ] += (float) $row['qty'];
		}
		ksort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
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

	/* --------------------------------------------------------------------- *
	 * Export / mail.
	 * --------------------------------------------------------------------- */

	/**
	 * Dispatch the PDF download and the send-mail action.
	 *
	 * @return void
	 */
	public function handle_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( self::PAGE !== $page ) {
			return;
		}
		if ( ! Customers_Admin::current_user_can_manage() ) {
			return;
		}

		$action = isset( $_POST['aaraa_report_action'] ) ? sanitize_key( wp_unslash( $_POST['aaraa_report_action'] ) ) : '';

		// Send mail (POST, intent in a hidden field — never a rewritten URL).
		if ( 'mail' === $action ) {
			check_admin_referer( 'aaraa_delivery_report' );
			$this->send_mail();
			return;
		}

		// Save the daily schedule.
		if ( 'schedule' === $action ) {
			check_admin_referer( 'aaraa_delivery_report' );
			$this->save_schedule();
			return;
		}

		// Download PDF (GET link).
		// phpcs:ignore WordPress.Security.NonceVerification
		$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'pdf' === $get_action ) {
			check_admin_referer( 'aaraa_delivery_report_pdf' );
			$this->stream_pdf();
		}
		// Download CSV (opens in Excel).
		if ( 'csv' === $get_action ) {
			check_admin_referer( 'aaraa_delivery_report_csv' );
			$this->stream_csv();
		}
		// Download boy-wise (a ZIP of per-boy files + the common report).
		if ( 'boywise' === $get_action ) {
			check_admin_referer( 'aaraa_delivery_report_boywise' );
			$this->stream_boywise();
		}
	}

	/**
	 * Persist the schedule form and re-arm the cron event.
	 *
	 * @return void
	 */
	private function save_schedule() {
		$time = isset( $_POST['schedule_time'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_time'] ) ) : '';
		if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$this->redirect( array( 'notice' => 'badtime' ) );
		}

		$email = isset( $_POST['schedule_email'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_email'] ) ) : '';
		$valid = array();
		foreach ( explode( ',', $email ) as $address ) {
			$address = sanitize_email( trim( $address ) );
			if ( is_email( $address ) ) {
				$valid[] = $address;
			}
		}
		if ( empty( $valid ) ) {
			$this->redirect( array( 'notice' => 'bademail' ) );
		}

		$scope = isset( $_POST['schedule_scope'] ) ? sanitize_key( wp_unslash( $_POST['schedule_scope'] ) ) : 'today';

		// At least one attachment format; default to PDF if neither is ticked.
		$fmt_pdf = empty( $_POST['schedule_fmt_pdf'] ) ? 0 : 1;
		$fmt_csv = empty( $_POST['schedule_fmt_csv'] ) ? 0 : 1;
		if ( ! $fmt_pdf && ! $fmt_csv ) {
			$fmt_pdf = 1;
		}

		update_option(
			self::OPTION,
			array(
				'enabled' => empty( $_POST['schedule_enabled'] ) ? 0 : 1,
				'time'    => $time,
				'email'   => implode( ', ', $valid ),
				'scope'   => ( 'yesterday' === $scope ) ? 'yesterday' : 'today',
				'fmt_pdf' => $fmt_pdf,
				'fmt_csv' => $fmt_csv,
			),
			false
		);

		self::reschedule();

		$this->redirect( array( 'notice' => 'saved' ) );
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
	 * Render the report to PDF bytes, or false when no PDF engine is available.
	 *
	 * @param string $date    Y-m-d.
	 * @param array  $filters Optional slot/hub/person filters.
	 * @param string $search  Optional free-text term.
	 * @return string|false
	 */
	private function pdf_bytes( $date, $filters = array(), $search = '' ) {
		$dompdf = $this->dompdf();
		if ( ! $dompdf ) {
			return false;
		}
		$dompdf->loadHtml( $this->report_html( $this->get_rows( $date, $filters, $search ), $date ) );
		$dompdf->setPaper( 'A4', 'landscape' );
		$dompdf->render();
		return $dompdf->output();
	}

	/**
	 * Stream the report as a download (PDF when possible, HTML otherwise).
	 *
	 * @return void
	 */
	private function stream_pdf() {
		$date    = $this->date();
		$filters = $this->filters();
		$search  = $this->search();
		$name    = 'delivery-report-' . $date;
		$pdf     = $this->pdf_bytes( $date, $filters, $search );

		nocache_headers();

		if ( false === $pdf ) {
			// No PDF engine — hand back printable HTML so the report is still usable.
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $name . '.html"' );
			echo $this->report_html( $this->get_rows( $date, $filters, $search ), $date ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Stream the combined report as a CSV download (opens in Excel).
	 *
	 * @return void
	 */
	private function stream_csv() {
		$date = $this->date();
		$rows = $this->get_rows( $date, $this->filters(), $this->search() );
		$csv  = $this->report_csv( $rows, $date );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="delivery-report-' . $date . '.csv"' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Stream the boy-wise report: a ZIP containing one PDF per delivery boy plus
	 * the common report. When ZipArchive is unavailable, fall back to a single
	 * combined PDF (a page per delivery boy). HTML is used if no PDF engine loads.
	 *
	 * @return void
	 */
	private function stream_boywise() {
		$date    = $this->date();
		$filters = $this->filters();
		$search  = $this->search();

		nocache_headers();

		$formats = $this->selected_formats();

		if ( class_exists( '\ZipArchive' ) ) {
			$docs = $this->build_documents( $date, $filters, $search, $formats );
			$tmp  = wp_tempnam( 'delivery-report-boywise-' . $date . '.zip' );
			$zip  = new \ZipArchive();
			if ( is_string( $tmp ) && true === $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				foreach ( $docs as $doc ) {
					$zip->addFromString( $doc['name'] . '.' . $doc['ext'], $doc['bytes'] );
				}
				$zip->close();
				$bytes = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				wp_delete_file( $tmp );

				header( 'Content-Type: application/zip' );
				header( 'Content-Disposition: attachment; filename="delivery-report-' . $date . '-boywise.zip"' );
				header( 'Content-Length: ' . strlen( $bytes ) );
				echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput
				exit;
			}
		}

		// No ZipArchive — hand back one combined document instead. Prefer PDF when
		// selected (a page per boy); otherwise a single combined CSV.
		$name = 'delivery-report-' . $date . '-boywise';

		if ( ! in_array( 'pdf', $formats, true ) && in_array( 'csv', $formats, true ) ) {
			$rows = $this->get_rows( $date, $filters, $search );
			$csv  = $this->report_csv( $rows, $date );
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $name . '.csv"' );
			header( 'Content-Length: ' . strlen( $csv ) );
			echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		$html = $this->combined_html( $date, $filters, $search );
		$pdf  = $this->pdf_bytes_from_html( $html );

		if ( false === $pdf ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $name . '.html"' );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Email the report as an attachment.
	 *
	 * @return void
	 */
	private function send_mail() {
		$date = $this->date();
		$to   = isset( $_POST['report_email'] ) ? sanitize_text_field( wp_unslash( $_POST['report_email'] ) ) : '';
		$to   = $to ? $to : get_option( 'admin_email' );

		$this->redirect( array( 'notice' => $this->mail_report( $date, $to, $this->filters(), $this->search() ) ) );
	}

	/**
	 * Build the report for a date and email it.
	 *
	 * Shared by the Send Mail button and the daily cron, so both behave
	 * identically and there is only one place for this to go wrong.
	 *
	 * @param string $date    Y-m-d.
	 * @param string $to      One or more comma-separated addresses.
	 * @param array  $filters Optional slot/hub/person filters.
	 * @param string $search  Optional free-text term.
	 * @return string One of sent|empty|bademail|writefail|mailfail.
	 */
	private function mail_report( $date, $to, $filters = array(), $search = '' ) {
		$recipients = array();
		foreach ( explode( ',', (string) $to ) as $address ) {
			$address = sanitize_email( trim( $address ) );
			if ( is_email( $address ) ) {
				$recipients[] = $address;
			}
		}
		if ( empty( $recipients ) ) {
			return 'bademail';
		}

		$rows = $this->get_rows( $date, $filters, $search );
		if ( empty( $rows ) ) {
			return 'empty';
		}

		// Build the common report plus one document per delivery boy, in each
		// configured format (PDF and/or CSV), and write each to the uploads dir so
		// they can be attached as separate files.
		$docs   = $this->build_documents( $date, $filters, $search, $this->selected_formats() );
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] );
		$files  = array();

		foreach ( $docs as $doc ) {
			$file = $dir . $doc['name'] . '.' . $doc['ext'];
			if ( false === file_put_contents( $file, $doc['bytes'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				// Clean up anything already written before bailing.
				foreach ( $files as $written ) {
					wp_delete_file( $written );
				}
				return 'writefail';
			}
			$files[] = $file;
		}

		$boy_count = max( 0, count( $files ) - 1 ); // all docs minus the common report.
		$subject   = sprintf(
			/* translators: %s: report date */
			__( 'Daily Delivery Report — %s', 'aaraa-white-label-admin' ),
			$date
		);
		$body = sprintf(
			/* translators: 1: date, 2: number of rows, 3: number of delivery boys */
			__( 'Attached is the delivery report for %1$s (%2$d items): one common report plus a separate report for each of the %3$d delivery boys.', 'aaraa-white-label-admin' ),
			$date,
			count( $rows ),
			$boy_count
		);

		$sent = wp_mail( $recipients, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ), $files );

		foreach ( $files as $file ) {
			wp_delete_file( $file );
		}

		return $sent ? 'sent' : 'mailfail';
	}

	/**
	 * Redirect back to the report with a notice.
	 *
	 * @param array $args Query args.
	 * @return void
	 */
	private function redirect( $args = array() ) {
		$f     = $this->filters();
		$carry = array_filter(
			array(
				'report_slot'   => $f['slot'],
				'report_hub'    => $f['hub'],
				'report_boy'    => $f['boy'],
				'report_search' => $this->search(),
			)
		);
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page'        => self::PAGE,
						'report_date' => $this->date(),
					),
					$carry,
					$args
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Markup.
	 * --------------------------------------------------------------------- */

	/**
	 * The report table columns.
	 *
	 * @param bool $with_ids Whether to include Order ID / Subscription ID — only
	 *                       the overall report does; the per-delivery-person
	 *                       (boy-wise) sheets leave them off.
	 * @return array<string, string>
	 */
	private function columns( $with_ids = false ) {
		$cols = array();
		if ( $with_ids ) {
			$cols['order_id']     = __( 'Order ID', 'aaraa-white-label-admin' );
			$cols['subscription'] = __( 'Subscription ID', 'aaraa-white-label-admin' );
		}
		$cols['customer'] = __( 'Customer Name', 'aaraa-white-label-admin' );
		$cols['mobile']   = __( 'Mobile', 'aaraa-white-label-admin' );
		$cols['address']  = __( 'Address', 'aaraa-white-label-admin' );
		$cols['product']  = __( 'Product Name', 'aaraa-white-label-admin' );
		$cols['qty']      = __( 'Quantity', 'aaraa-white-label-admin' );
		$cols['slot']     = __( 'Delivery Slot', 'aaraa-white-label-admin' );
		$cols['hub']      = __( 'Delivery Hub', 'aaraa-white-label-admin' );
		$cols['boy']      = __( 'Delivery Boy', 'aaraa-white-label-admin' );
		return $cols;
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
	 * Standalone HTML used for both the PDF and the emailed fallback.
	 *
	 * @param array  $rows Report rows.
	 * @param string $date Y-m-d.
	 * @return string
	 */
	private function report_html( $rows, $date, $boy_label = '' ) {
		return $this->report_head() . $this->report_section( $rows, $date, $boy_label ) . '</body></html>';
	}

	/**
	 * The shared document head + opening <body> (styles for PDF and HTML fallback).
	 *
	 * @return string
	 */
	private function report_head() {
		$html  = '<html><head><meta charset="utf-8"><style>';
		$html .= 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10px;color:#0F172A;}';
		$html .= 'h1{font-size:16px;margin:0 0 2px;}';
		$html .= '.sub{font-size:10px;color:#64748B;margin:0 0 10px;}';
		$html .= 'table{width:100%;border-collapse:collapse;}';
		$html .= 'th,td{border:1px solid #CBD5E1;padding:5px 6px;text-align:left;vertical-align:top;}';
		$html .= 'th{background:#10B7D4;color:#fff;font-size:10px;}';
		$html .= 'tr:nth-child(even) td{background:#F8FAFC;}';
		$html .= 'h2{font-size:13px;margin:18px 0 6px;}';
		$html .= 'table.summary{width:45%;}';
		$html .= 'table.summary td.qty,table.summary th.qty{text-align:right;width:110px;}';
		$html .= 'tfoot td{font-weight:bold;background:#EEF2F6;}';
		$html .= '.section{page-break-before:always;}';
		$html .= '</style></head><body>';
		return $html;
	}

	/**
	 * One report section: heading, the line-item table and the order summary.
	 * Used on its own (single PDF) and repeated (boy-wise combined PDF).
	 *
	 * @param array  $rows      Report rows.
	 * @param string $date      Y-m-d.
	 * @param string $boy_label Optional delivery-boy name for the heading.
	 * @return string
	 */
	private function report_section( $rows, $date, $boy_label = '' ) {
		// Overall report (no delivery-boy heading) carries the id columns; the
		// per-delivery-person sections do not.
		$cols = $this->columns( '' === $boy_label );

		$title = get_bloginfo( 'name' ) . ' — ' . __( 'Daily Delivery Report', 'aaraa-white-label-admin' );
		$html  = '<h1>' . esc_html( $title ) . '</h1>';
		$sub   = esc_html( $date ) . ' &middot; ' . esc_html( sprintf( _n( '%d item', '%d items', count( $rows ), 'aaraa-white-label-admin' ), count( $rows ) ) );
		if ( '' !== $boy_label ) {
			$sub = '<strong>' . esc_html( sprintf( /* translators: %s: delivery boy name */ __( 'Delivery Boy: %s', 'aaraa-white-label-admin' ), $boy_label ) ) . '</strong> &middot; ' . $sub;
		}
		$html .= '<p class="sub">' . $sub . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- parts escaped above.
		$html .= '<table><thead><tr>';
		foreach ( $cols as $label ) {
			$html .= '<th>' . esc_html( $label ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			$html .= '<tr><td colspan="' . count( $cols ) . '">' . esc_html__( 'No orders for this date.', 'aaraa-white-label-admin' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$html .= '<tr>';
				foreach ( array_keys( $cols ) as $key ) {
					$html .= '<td>' . esc_html( isset( $row[ $key ] ) ? $row[ $key ] : '' ) . '</td>';
				}
				$html .= '</tr>';
			}
		}

		$html .= '</tbody></table>';

		// Order summary — what actually has to be loaded onto the vans.
		$summary = $this->summary_rows( $rows );
		if ( ! empty( $summary ) ) {
			$total = 0.0;
			$html .= '<h2>' . esc_html__( 'Order Summary', 'aaraa-white-label-admin' ) . '</h2>';
			$html .= '<table class="summary"><thead><tr>';
			$html .= '<th>' . esc_html__( 'Product Name', 'aaraa-white-label-admin' ) . '</th>';
			$html .= '<th class="qty">' . esc_html__( 'Total Quantity', 'aaraa-white-label-admin' ) . '</th>';
			$html .= '</tr></thead><tbody>';
			foreach ( $summary as $product => $qty ) {
				$total += $qty;
				$html  .= '<tr><td>' . esc_html( $product ) . '</td>';
				$html  .= '<td class="qty">' . esc_html( $this->qty_label( $qty ) ) . '</td></tr>';
			}
			$html .= '</tbody><tfoot><tr>';
			$html .= '<td>' . esc_html__( 'Total', 'aaraa-white-label-admin' ) . '</td>';
			$html .= '<td class="qty">' . esc_html( $this->qty_label( $total ) ) . '</td>';
			$html .= '</tr></tfoot></table>';
		}

		return $html;
	}

	/**
	 * Group report rows by delivery boy, named boys A→Z then "Not assigned" last.
	 *
	 * @param array $rows Report rows (must include boy_id + boy).
	 * @return array<int, array{boy_id:int,name:string,slug:string,rows:array}>
	 */
	private function group_rows_by_boy( $rows ) {
		$groups = array();
		foreach ( $rows as $row ) {
			$bid = isset( $row['boy_id'] ) ? (int) $row['boy_id'] : 0;
			if ( ! isset( $groups[ $bid ] ) ) {
				$name = ( $bid && isset( $row['boy'] ) && '—' !== $row['boy'] )
					? $row['boy']
					: __( 'Not assigned', 'aaraa-white-label-admin' );
				$groups[ $bid ] = array(
					'boy_id' => $bid,
					'name'   => $name,
					'slug'   => $bid ? ( sanitize_title( $name ) . '-' . $bid ) : 'unassigned',
					'rows'   => array(),
				);
			}
			$groups[ $bid ]['rows'][] = $row;
		}

		uasort(
			$groups,
			static function ( $a, $b ) {
				if ( 0 === $a['boy_id'] ) {
					return 1; // unassigned last.
				}
				if ( 0 === $b['boy_id'] ) {
					return -1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return array_values( $groups );
	}

	/**
	 * Build the set of documents for a date: one common report plus one per
	 * delivery boy. Each entry is a ready-to-write file (PDF, or HTML when no PDF
	 * engine is available).
	 *
	 * @param string $date    Y-m-d.
	 * @param array  $filters Slot/hub/boy filters.
	 * @param string $search  Free-text term.
	 * @return array<int, array{name:string,ext:string,bytes:string}>
	 */
	private function build_documents( $date, $filters = array(), $search = '', $formats = null ) {
		$formats = ( null === $formats ) ? array( 'pdf' ) : (array) $formats;
		$rows    = $this->get_rows( $date, $filters, $search );
		$groups  = $this->group_rows_by_boy( $rows );
		$docs    = array();

		foreach ( $formats as $fmt ) {
			// Common report (everything).
			$docs[] = $this->make_document( 'delivery-report-' . $date, $rows, $date, '', $fmt );

			// One report per delivery boy — file name starts with the boy's name.
			foreach ( $groups as $group ) {
				$docs[] = $this->make_document(
					$group['slug'] . '-delivery-report-' . $date,
					$group['rows'],
					$date,
					$group['name'],
					$fmt
				);
			}
		}

		return $docs;
	}

	/**
	 * Render one report to a document array in the requested format. PDF falls
	 * back to HTML when no PDF engine is available; CSV is always available.
	 *
	 * @param string $basename  File name without extension.
	 * @param array  $rows      Report rows.
	 * @param string $date      Y-m-d.
	 * @param string $boy_label Optional delivery-boy name for the heading.
	 * @param string $fmt       'pdf' | 'csv'.
	 * @return array{name:string,ext:string,bytes:string}
	 */
	private function make_document( $basename, $rows, $date, $boy_label = '', $fmt = 'pdf' ) {
		if ( 'csv' === $fmt ) {
			return array( 'name' => $basename, 'ext' => 'csv', 'bytes' => $this->report_csv( $rows, $date, $boy_label ) );
		}
		$html = $this->report_html( $rows, $date, $boy_label );
		$pdf  = $this->pdf_bytes_from_html( $html );
		if ( false === $pdf ) {
			return array( 'name' => $basename, 'ext' => 'html', 'bytes' => $html );
		}
		return array( 'name' => $basename, 'ext' => 'pdf', 'bytes' => $pdf );
	}

	/**
	 * Render the report as CSV (opens directly in Excel). Includes a title row,
	 * the line-item table and the order summary. A UTF-8 BOM is prepended so Excel
	 * shows accented / non-ASCII names correctly.
	 *
	 * @param array  $rows      Report rows.
	 * @param string $date      Y-m-d.
	 * @param string $boy_label Optional delivery-boy name.
	 * @return string CSV bytes.
	 */
	private function report_csv( $rows, $date, $boy_label = '' ) {
		// Overall CSV carries the id columns; per-delivery-person CSVs do not.
		$cols = $this->columns( '' === $boy_label );
		$fh   = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$title = get_bloginfo( 'name' ) . ' - ' . __( 'Daily Delivery Report', 'aaraa-white-label-admin' ) . ' - ' . $date;
		if ( '' !== $boy_label ) {
			$title .= ' - ' . sprintf( /* translators: %s: delivery boy name */ __( 'Delivery Boy: %s', 'aaraa-white-label-admin' ), $boy_label );
		}
		fputcsv( $fh, array( $title ) );
		fputcsv( $fh, array() );
		fputcsv( $fh, array_values( $cols ) );

		foreach ( $rows as $row ) {
			$line = array();
			foreach ( array_keys( $cols ) as $key ) {
				$line[] = isset( $row[ $key ] ) ? $row[ $key ] : '';
			}
			fputcsv( $fh, $line );
		}

		$summary = $this->summary_rows( $rows );
		if ( ! empty( $summary ) ) {
			$total = 0.0;
			fputcsv( $fh, array() );
			fputcsv( $fh, array( __( 'Order Summary', 'aaraa-white-label-admin' ) ) );
			fputcsv( $fh, array( __( 'Product Name', 'aaraa-white-label-admin' ), __( 'Total Quantity', 'aaraa-white-label-admin' ) ) );
			foreach ( $summary as $product => $qty ) {
				$total += $qty;
				fputcsv( $fh, array( $product, $this->qty_label( $qty ) ) );
			}
			fputcsv( $fh, array( __( 'Total', 'aaraa-white-label-admin' ), $this->qty_label( $total ) ) );
		}

		rewind( $fh );
		$csv = (string) stream_get_contents( $fh );
		fclose( $fh );

		return "\xEF\xBB\xBF" . $csv; // UTF-8 BOM for Excel.
	}

	/**
	 * The formats selected for the automatic email / boy-wise ZIP, always at least
	 * one (defaults to PDF).
	 *
	 * @return string[] Subset of ['pdf','csv'].
	 */
	private function selected_formats() {
		$settings = self::settings();
		$formats  = array();
		if ( ! empty( $settings['fmt_pdf'] ) ) {
			$formats[] = 'pdf';
		}
		if ( ! empty( $settings['fmt_csv'] ) ) {
			$formats[] = 'csv';
		}
		return empty( $formats ) ? array( 'pdf' ) : $formats;
	}

	/**
	 * Render report HTML to PDF bytes (landscape A4), or false if dompdf is absent.
	 *
	 * @param string $html Report HTML.
	 * @return string|false
	 */
	private function pdf_bytes_from_html( $html ) {
		$dompdf = $this->dompdf();
		if ( ! $dompdf ) {
			return false;
		}
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'landscape' );
		$dompdf->render();
		return $dompdf->output();
	}

	/**
	 * One combined document: the common report followed by a page per delivery
	 * boy. Used for the boy-wise download when ZipArchive is unavailable.
	 *
	 * @param string $date    Y-m-d.
	 * @param array  $filters Filters.
	 * @param string $search  Search term.
	 * @return string Combined HTML.
	 */
	private function combined_html( $date, $filters = array(), $search = '' ) {
		$rows = $this->get_rows( $date, $filters, $search );
		$html = $this->report_head();
		$html .= $this->report_section( $rows, $date );
		foreach ( $this->group_rows_by_boy( $rows ) as $group ) {
			$html .= '<div class="section">' . $this->report_section( $group['rows'], $date, $group['name'] ) . '</div>';
		}
		$html .= '</body></html>';
		return $html;
	}

	/**
	 * The admin screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'aaraa-white-label-admin' ) );
		}

		$date    = $this->date();
		$filters = $this->filters();
		$search  = $this->search();
		$rows    = $this->get_rows( $date, $filters, $search );
		$cols    = $this->columns( true ); // On-screen overall report shows the id columns.
		$base    = admin_url( 'admin.php' );

		$slot_opts = $this->slot_map();
		$hub_rows  = class_exists( __NAMESPACE__ . '\\Delivery_Admin' ) ? Delivery_Admin::hubs_list() : array();
		$boys = $this->delivery_boys_for_filter();

		// Filter values to carry through the PDF link and mail form.
		$carry = array_filter(
			array(
				'report_slot'   => $filters['slot'],
				'report_hub'    => $filters['hub'],
				'report_boy'    => $filters['boy'],
				'report_search' => $search,
			)
		);

		echo '<div class="wrap aaraa-wallet">';
		?>
		<div class="aaraa-wallet__bar">
			<h1 class="aaraa-wallet__title"><?php esc_html_e( 'Daily Delivery Report', 'aaraa-white-label-admin' ); ?></h1>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'page' => self::PAGE, 'action' => 'pdf', 'report_date' => $date ), $carry ), $base ), 'aaraa_delivery_report_pdf' ) ); ?>">
				<?php esc_html_e( 'Download PDF', 'aaraa-white-label-admin' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'page' => self::PAGE, 'action' => 'csv', 'report_date' => $date ), $carry ), $base ), 'aaraa_delivery_report_csv' ) ); ?>">
				<?php esc_html_e( 'Download CSV', 'aaraa-white-label-admin' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'page' => self::PAGE, 'action' => 'boywise', 'report_date' => $date ), $carry ), $base ), 'aaraa_delivery_report_boywise' ) ); ?>">
				<?php esc_html_e( 'Download boy-wise (ZIP)', 'aaraa-white-label-admin' ); ?>
			</a>
		</div>
		<?php
		$this->notices();
		?>

		<div class="aaraa-wallet__panel" style="border-top:1px solid #E2E8F0;border-radius:10px;">
			<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
			<form method="get" class="aaraa-wallet__toolbar" style="margin:0;flex-wrap:wrap;gap:14px;align-items:flex-end;justify-content:flex-start;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<label>
					<strong><?php esc_html_e( 'Date', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="date" name="report_date" value="<?php echo esc_attr( $date ); ?>" />
				</label>
				<label>
					<strong><?php esc_html_e( 'Delivery Slot', 'aaraa-white-label-admin' ); ?></strong><br />
					<select name="report_slot">
						<option value="0"><?php esc_html_e( 'All delivery slots', 'aaraa-white-label-admin' ); ?></option>
						<?php foreach ( $slot_opts as $sid => $slabel ) : ?>
							<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $filters['slot'], $sid ); ?>><?php echo esc_html( $slabel ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<strong><?php esc_html_e( 'Delivery Hub', 'aaraa-white-label-admin' ); ?></strong><br />
					<select name="report_hub" id="aaraa-f-hub">
						<option value="0"><?php esc_html_e( 'All delivery hubs', 'aaraa-white-label-admin' ); ?></option>
						<?php foreach ( $hub_rows as $h ) : ?>
							<option value="<?php echo esc_attr( $h->ID ); ?>" <?php selected( $filters['hub'], (int) $h->ID ); ?>><?php echo esc_html( $h->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<strong><?php esc_html_e( 'Delivery Boy', 'aaraa-white-label-admin' ); ?></strong><br />
					<select name="report_boy" id="aaraa-f-boy">
						<option value="0"><?php esc_html_e( 'All delivery boys', 'aaraa-white-label-admin' ); ?></option>
						<option value="-1" <?php selected( $filters['boy'], -1 ); ?>><?php esc_html_e( 'Not assigned', 'aaraa-white-label-admin' ); ?></option>
						<?php foreach ( $boys as $b ) : ?>
							<option value="<?php echo esc_attr( $b['id'] ); ?>" data-hub="<?php echo esc_attr( $b['hub'] ); ?>" <?php selected( $filters['boy'], $b['id'] ); ?>><?php echo esc_html( $b['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<strong><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="search" name="report_search" value="<?php echo esc_attr( $search ); ?>" style="min-width:240px;" placeholder="<?php esc_attr_e( 'Name, email, phone or order ID', 'aaraa-white-label-admin' ); ?>" />
				</label>
				<span>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'aaraa-white-label-admin' ); ?></button>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE ), $base ) ); ?>"><?php esc_html_e( 'Today', 'aaraa-white-label-admin' ); ?></a>
				</span>
			</form>

			<?php // Mail form posts to the current URL, intent in a hidden field. The email box sits right before the Send Mail button. ?>
			<form method="post" class="aaraa-wallet__toolbar" style="margin:0;justify-content:flex-end;gap:10px;align-items:flex-end;flex-wrap:wrap;">
				<?php wp_nonce_field( 'aaraa_delivery_report' ); ?>
				<input type="hidden" name="aaraa_report_action" value="mail" />
				<input type="hidden" name="report_date" value="<?php echo esc_attr( $date ); ?>" />
				<input type="hidden" name="report_slot" value="<?php echo esc_attr( $filters['slot'] ); ?>" />
				<input type="hidden" name="report_hub" value="<?php echo esc_attr( $filters['hub'] ); ?>" />
				<input type="hidden" name="report_boy" value="<?php echo esc_attr( $filters['boy'] ); ?>" />
				<input type="hidden" name="report_search" value="<?php echo esc_attr( $search ); ?>" />
				<label style="margin:0;">
					<strong><?php esc_html_e( 'Send PDF to', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="email" name="report_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" required />
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Mail', 'aaraa-white-label-admin' ); ?></button>
			</form>
			</div>

			<script>
			( function () {
				var hub = document.getElementById( 'aaraa-f-hub' );
				var boy = document.getElementById( 'aaraa-f-boy' );
				if ( ! hub || ! boy ) { return; }
				function sync() {
					var h = hub.value;
					for ( var i = 0; i < boy.options.length; i++ ) {
						var o = boy.options[ i ];
						if ( ! o.value ) { o.hidden = false; o.disabled = false; continue; }
						var oh   = o.getAttribute( 'data-hub' ) || '0';
						var show = ( '0' === h || '' === h || oh === h );
						o.hidden   = ! show;
						o.disabled = ! show;
					}
					var sel = boy.options[ boy.selectedIndex ];
					if ( sel && sel.hidden ) { boy.value = '0'; }
				}
				hub.addEventListener( 'change', sync );
				sync();
			} )();
			</script>

			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<?php foreach ( $cols as $label ) : ?>
							<th><?php echo esc_html( $label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="<?php echo (int) count( $cols ); ?>"><?php esc_html_e( 'No orders for this date.', 'aaraa-white-label-admin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<?php foreach ( array_keys( $cols ) as $key ) : ?>
									<td><?php echo esc_html( isset( $row[ $key ] ) ? $row[ $key ] : '' ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="description" style="margin-top:10px;">
				<?php echo esc_html( sprintf( _n( '%d item', '%d items', count( $rows ), 'aaraa-white-label-admin' ), count( $rows ) ) ); ?>
				&middot; <?php esc_html_e( 'Cancelled, refunded and failed orders are excluded.', 'aaraa-white-label-admin' ); ?>
			</p>

			<?php
			$summary = $this->summary_rows( $rows );
			if ( ! empty( $summary ) ) :
				$total = 0.0;
				?>
				<h2 style="margin:22px 0 8px;font-size:15px;"><?php esc_html_e( 'Order Summary', 'aaraa-white-label-admin' ); ?></h2>
				<table class="widefat striped aaraa-wallet__table" style="max-width:520px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product Name', 'aaraa-white-label-admin' ); ?></th>
							<th style="text-align:right;width:140px;"><?php esc_html_e( 'Total Quantity', 'aaraa-white-label-admin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $summary as $product => $qty ) : ?>
							<?php $total += $qty; ?>
							<tr>
								<td><?php echo esc_html( $product ); ?></td>
								<td style="text-align:right;"><?php echo esc_html( $this->qty_label( $qty ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Total', 'aaraa-white-label-admin' ); ?></th>
							<th style="text-align:right;"><?php echo esc_html( $this->qty_label( $total ) ); ?></th>
						</tr>
					</tfoot>
				</table>
			<?php endif; ?>
		</div>

		<?php $this->render_schedule_panel(); ?>
		<?php
		echo '</div>';
	}

	/**
	 * The "Automatic daily email" settings panel.
	 *
	 * @return void
	 */
	private function render_schedule_panel() {
		$settings = self::settings();
		$next     = wp_next_scheduled( self::CRON_HOOK );
		$last     = get_option( self::OPTION_LAST, array() );
		$last     = is_array( $last ) ? $last : array();
		?>
		<div class="aaraa-wallet__panel" style="border-top:1px solid #E2E8F0;border-radius:10px;margin-top:18px;">
			<h2 style="margin:0 0 4px;font-size:15px;"><?php esc_html_e( 'Automatic daily email', 'aaraa-white-label-admin' ); ?></h2>
			<p class="description" style="margin:0 0 12px;">
				<?php esc_html_e( 'Sends this report once a day, on its own, at the time below — one common report plus a separate file for each delivery boy (in the formats ticked below), all attached to the same email. Times are in your site timezone.', 'aaraa-white-label-admin' ); ?>
				<code><?php echo esc_html( wp_timezone_string() ); ?></code>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'aaraa_delivery_report' ); ?>
				<input type="hidden" name="aaraa_report_action" value="schedule" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled', 'aaraa-white-label-admin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="schedule_enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
								<?php esc_html_e( 'Send this report automatically every day', 'aaraa-white-label-admin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa_schedule_time"><?php esc_html_e( 'Send at', 'aaraa-white-label-admin' ); ?></label></th>
						<td>
							<input type="time" id="aaraa_schedule_time" name="schedule_time" value="<?php echo esc_attr( $settings['time'] ); ?>" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Attach as', 'aaraa-white-label-admin' ); ?></th>
						<td>
							<label style="margin-right:16px;">
								<input type="checkbox" name="schedule_fmt_pdf" value="1" <?php checked( ! empty( $settings['fmt_pdf'] ) ); ?> />
								<?php esc_html_e( 'PDF', 'aaraa-white-label-admin' ); ?>
							</label>
							<label>
								<input type="checkbox" name="schedule_fmt_csv" value="1" <?php checked( ! empty( $settings['fmt_csv'] ) ); ?> />
								<?php esc_html_e( 'CSV / Excel', 'aaraa-white-label-admin' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Tick both to attach every report in both formats. If neither is ticked, PDF is used.', 'aaraa-white-label-admin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa_schedule_email"><?php esc_html_e( 'Send to', 'aaraa-white-label-admin' ); ?></label></th>
						<td>
							<input type="text" id="aaraa_schedule_email" name="schedule_email" class="regular-text" value="<?php echo esc_attr( $settings['email'] ); ?>" required />
							<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas.', 'aaraa-white-label-admin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Report for', 'aaraa-white-label-admin' ); ?></th>
						<td>
							<label style="margin-right:16px;">
								<input type="radio" name="schedule_scope" value="today" <?php checked( 'yesterday' !== $settings['scope'] ); ?> />
								<?php esc_html_e( "That day's orders", 'aaraa-white-label-admin' ); ?>
							</label>
							<label>
								<input type="radio" name="schedule_scope" value="yesterday" <?php checked( 'yesterday', $settings['scope'] ); ?> />
								<?php esc_html_e( "The previous day's orders", 'aaraa-white-label-admin' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Pick the previous day when the mail goes out early in the morning, before that day\'s orders have been placed.', 'aaraa-white-label-admin' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Schedule', 'aaraa-white-label-admin' ); ?></button>
				</p>
			</form>

			<p class="description">
				<?php if ( $next ) : ?>
					<strong><?php esc_html_e( 'Next run:', 'aaraa-white-label-admin' ); ?></strong>
					<?php echo esc_html( wp_date( 'D, d M Y H:i', $next ) ); ?>
				<?php else : ?>
					<strong><?php esc_html_e( 'Next run:', 'aaraa-white-label-admin' ); ?></strong>
					<?php esc_html_e( 'not scheduled.', 'aaraa-white-label-admin' ); ?>
				<?php endif; ?>

				<?php if ( ! empty( $last['when'] ) ) : ?>
					&middot; <strong><?php esc_html_e( 'Last run:', 'aaraa-white-label-admin' ); ?></strong>
					<?php echo esc_html( $last['when'] ); ?>
					(<?php echo esc_html( $this->result_label( $last['result'] ?? '' ) ); ?>)
				<?php endif; ?>
			</p>

			<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
				<p class="description">
					<?php esc_html_e( 'WP-Cron is disabled in wp-config.php, so a real server cron must be calling wp-cron.php for this to fire.', 'aaraa-white-label-admin' ); ?>
				</p>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'WordPress cron only runs when someone visits the site, so the mail can be late on a quiet morning. For an exact time, add a server cron job calling wp-cron.php every five minutes.', 'aaraa-white-label-admin' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Human label for a stored run result.
	 *
	 * @param string $result Result key.
	 * @return string
	 */
	private function result_label( $result ) {
		$map = array(
			'sent'      => __( 'sent', 'aaraa-white-label-admin' ),
			'empty'     => __( 'skipped — no orders', 'aaraa-white-label-admin' ),
			'bademail'  => __( 'failed — invalid address', 'aaraa-white-label-admin' ),
			'mailfail'  => __( 'failed — mail server rejected it', 'aaraa-white-label-admin' ),
			'writefail' => __( 'failed — uploads folder not writable', 'aaraa-white-label-admin' ),
		);
		return isset( $map[ $result ] ) ? $map[ $result ] : $result;
	}

	/**
	 * Screen notices.
	 *
	 * @return void
	 */
	private function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
		$map    = array(
			'sent'      => array( 'success', __( 'Report emailed.', 'aaraa-white-label-admin' ) ),
			'saved'     => array( 'success', __( 'Schedule saved.', 'aaraa-white-label-admin' ) ),
			'badtime'   => array( 'error', __( 'Enter a valid time in 24-hour HH:MM format.', 'aaraa-white-label-admin' ) ),
			'empty'     => array( 'error', __( 'Nothing to send — there are no orders for that date.', 'aaraa-white-label-admin' ) ),
			'bademail'  => array( 'error', __( 'Enter a valid email address.', 'aaraa-white-label-admin' ) ),
			'mailfail'  => array( 'error', __( 'WordPress could not send the email. Check your SMTP settings.', 'aaraa-white-label-admin' ) ),
			'writefail' => array( 'error', __( 'Could not write the attachment to the uploads folder.', 'aaraa-white-label-admin' ) ),
		);
		if ( isset( $map[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $map[ $notice ][0] ),
				esc_html( $map[ $notice ][1] )
			);
		}
	}
}
