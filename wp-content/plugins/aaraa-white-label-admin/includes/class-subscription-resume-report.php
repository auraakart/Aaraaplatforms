<?php
/**
 * Subscription Resume Report.
 *
 * Lists subscriptions that RESUME on a given date — i.e. delivery restarts that
 * day after a pause. A subscription resumes on date X when the day before (X-1)
 * is one of its chosen pause dates ( `_wcfmu_pause_dates` ) and X itself is not.
 * Mirrors the Subscription Pause Report, but keyed on the resume day.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription resume report screen.
 */
class Subscription_Resume_Report {

	const PAGE = 'aaraa-subscription-resume-report';

	/**
	 * No hooks needed — the screen is rendered from the menu callback.
	 *
	 * @return void
	 */
	public function init() {}

	/**
	 * Resolve the requested date (Y-m-d), defaulting to tomorrow (site local).
	 *
	 * @return string
	 */
	private function current_date() {
		$raw = isset( $_GET['resume_date'] ) ? sanitize_text_field( wp_unslash( $_GET['resume_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return $raw;
		}
		return gmdate( 'Y-m-d', current_time( 'timestamp' ) + DAY_IN_SECONDS );
	}

	/**
	 * The search term, trimmed.
	 *
	 * @return string
	 */
	private function current_search() {
		return isset( $_GET['resume_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['resume_search'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Subscriptions resuming on the given date, matched against the search term.
	 * Resume day X = (X-1) is a pause date and X is not.
	 *
	 * @param string $date   Y-m-d (the resume day).
	 * @param string $search Free-text: name / email / mobile / subscription id.
	 * @return array<int,array<string,string>> Rows with id, customer, mobile, products.
	 */
	private function get_rows( $date, $search ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array();
		}

		// Candidate subscriptions from live meta AND historical order notes, so
		// past resume dates (pruned from meta on resume) are still reported.
		$ids = Pause_History::candidates_for_resume( $date );
		if ( empty( $ids ) ) {
			return array();
		}

		$digits = preg_replace( '/\D+/', '', (string) $search );
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );

		$rows = array();
		foreach ( $ids as $sub_id ) {
			$sub_id = (int) $sub_id;

			// Verify the date is truly a resume day for this sub (meta or notes).
			if ( ! in_array( $date, Pause_History::resume_dates_for( $sub_id ), true ) ) {
				continue;
			}

			$sub = wcs_get_subscription( $sub_id );
			if ( ! $sub ) {
				continue;
			}

			$name = trim( $sub->get_billing_first_name() . ' ' . $sub->get_billing_last_name() );
			if ( '' === $name ) {
				$user = $sub->get_user();
				$name = $user ? $user->display_name : '';
			}
			$email  = (string) $sub->get_billing_email();
			$mobile = (string) $sub->get_billing_phone();
			if ( '' === $mobile ) {
				$mobile = (string) $sub->get_meta( '_shipping_mobile_number' );
			}

			// Search filter: id / name / email / mobile.
			if ( '' !== $search ) {
				$hay = ( function_exists( 'mb_strtolower' ) ? mb_strtolower( $name . ' ' . $email ) : strtolower( $name . ' ' . $email ) );
				$match =
					( (string) $sub_id === $search )
					|| ( '' !== $needle && false !== strpos( $hay, $needle ) )
					|| ( '' !== $digits && false !== strpos( preg_replace( '/\D+/', '', $mobile ), $digits ) );
				if ( ! $match ) {
					continue;
				}
			}

			$products = array();
			foreach ( (array) $sub->get_items() as $item ) {
				$products[] = $item->get_name() . ' × ' . (int) $item->get_quantity();
			}

			$renewal = $this->last_renewal( $sub );

			$rows[] = array(
				'id'             => $sub_id,
				'customer'       => $name,
				'mobile'         => $mobile,
				'products'       => implode( ', ', $products ),
				'status'         => $sub->get_status(),
				'ts'             => Pause_History::resume_action_time( $sub_id, $date ),
				'renewal_id'     => $renewal['id'],
				'renewal_date'   => $renewal['date'],   // Y-m-d (site local) or ''.
				'renewal_label'  => $renewal['label'],  // Pretty date+time or ''.
				'renewal_ts'     => $renewal['ts'],     // Unix ts of last renewal (0 if none).
				'renewal_status' => $renewal['status'], // Order status key (no wc- prefix).
			);
		}

		// Oldest renewal order date first; rows with no renewal fall to the bottom,
		// then break ties by newest subscription.
		usort(
			$rows,
			static function ( $a, $b ) {
				$a_has = $a['renewal_ts'] > 0;
				$b_has = $b['renewal_ts'] > 0;
				if ( $a_has !== $b_has ) {
					return $a_has ? -1 : 1; // rows with a renewal come before those without.
				}
				if ( $a['renewal_ts'] !== $b['renewal_ts'] ) {
					return $a['renewal_ts'] <=> $b['renewal_ts']; // oldest first.
				}
				return (int) $b['id'] - (int) $a['id'];
			}
		);

		return $rows;
	}

	/**
	 * The most recent renewal order for a subscription.
	 *
	 * @param \WC_Subscription $sub Subscription object.
	 * @return array{id:int,date:string,label:string,ts:int,status:string}
	 */
	private function last_renewal( $sub ) {
		$empty = array(
			'id'     => 0,
			'date'   => '',
			'label'  => '',
			'ts'     => 0,
			'status' => '',
		);

		if ( ! is_object( $sub ) || ! method_exists( $sub, 'get_related_orders' ) ) {
			return $empty;
		}

		$ids = $sub->get_related_orders( 'ids', 'renewal' );
		if ( empty( $ids ) ) {
			return $empty;
		}

		// Pick the renewal with the newest creation date.
		$best_id     = 0;
		$best_ts     = 0;
		$best_date   = null;
		$best_status = '';
		foreach ( (array) $ids as $oid ) {
			$order = wc_get_order( (int) $oid );
			if ( ! $order ) {
				continue;
			}
			$created = $order->get_date_created();
			$ts      = $created ? $created->getTimestamp() : 0;
			if ( $ts >= $best_ts ) {
				$best_ts     = $ts;
				$best_id     = (int) $oid;
				$best_date   = $created;
				$best_status = $order->get_status();
			}
		}

		if ( ! $best_id ) {
			return $empty;
		}

		return array(
			'id'     => $best_id,
			'date'   => $best_date ? $best_date->date_i18n( 'Y-m-d' ) : '',
			'label'  => $best_date ? $best_date->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '',
			'ts'     => $best_ts,
			'status' => $best_status,
		);
	}

	/**
	 * Human-readable subscription status label.
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
		return ucwords( str_replace( '-', ' ', (string) $status ) );
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
		$search = $this->current_search();
		$rows   = $this->get_rows( $date, $search );
		$base   = admin_url( 'admin.php' );
		$pretty = date_i18n( get_option( 'date_format' ), strtotime( $date ) );

		echo '<div class="wrap aaraa-wallet">';
		?>
		<div class="aaraa-wallet__bar">
			<h1 class="aaraa-wallet__title"><?php esc_html_e( 'Subscription Resume Report', 'aaraa-white-label-admin' ); ?></h1>
		</div>

		<div class="aaraa-wallet__panel" style="border-top:1px solid #E2E8F0;border-radius:10px;">
			<form method="get" class="aaraa-wallet__toolbar" style="margin:0 0 14px;flex-wrap:wrap;gap:14px;align-items:flex-end;justify-content:flex-start;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<label>
					<strong><?php esc_html_e( 'Date', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="date" name="resume_date" value="<?php echo esc_attr( $date ); ?>" />
				</label>
				<label>
					<strong><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="search" name="resume_search" value="<?php echo esc_attr( $search ); ?>" style="min-width:280px;" placeholder="<?php esc_attr_e( 'Name, email, mobile or subscription ID', 'aaraa-white-label-admin' ); ?>" />
				</label>
				<span>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'aaraa-white-label-admin' ); ?></button>
					<?php
					$today_ymd    = current_time( 'Y-m-d' );
					$tomorrow_ymd = gmdate( 'Y-m-d', current_time( 'timestamp' ) + DAY_IN_SECONDS );
					?>
					<a class="button <?php echo $date === $tomorrow_ymd ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE ), $base ) ); ?>"><?php esc_html_e( 'Tomorrow', 'aaraa-white-label-admin' ); ?></a>
					<a class="button <?php echo $date === $today_ymd ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'resume_date' => $today_ymd ), $base ) ); ?>"><?php esc_html_e( 'Today', 'aaraa-white-label-admin' ); ?></a>
				</span>
			</form>

			<p class="description" style="margin:0 0 12px;">
				<?php
				printf(
					/* translators: 1: number of resuming subscriptions, 2: date. */
					esc_html__( '%1$s subscription(s) resuming on %2$s.', 'aaraa-white-label-admin' ),
					esc_html( number_format_i18n( count( $rows ) ) ),
					esc_html( $pretty )
				);
				?>
			</p>

			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Subscription ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Customer Name', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Mobile', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Products × Qty', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Last Renewal Order ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Last Renewal Order Date', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Last Renewal Order Status', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $rows ) : ?>
						<?php
						$today_ymd = current_time( 'Y-m-d' );
						foreach ( $rows as $row ) :
							// Flag rows whose last renewal order date is NOT today.
							$not_today = ( $row['renewal_date'] !== $today_ymd );
							?>
							<tr<?php echo $not_today ? ' style="background:#FDE2E1;"' : ''; ?>>
								<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['id'] . '&action=edit' ) ); ?>">#<?php echo (int) $row['id']; ?></a></td>
								<td><?php echo $row['customer'] ? esc_html( $row['customer'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['mobile'] ? esc_html( $row['mobile'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['products'] ? esc_html( $row['products'] ) : '&mdash;'; ?></td>
								<td><?php echo esc_html( $this->status_label( $row['status'] ) ); ?></td>
								<td>
									<?php if ( $row['renewal_id'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['renewal_id'] . '&action=edit' ) ); ?>">#<?php echo (int) $row['renewal_id']; ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td><?php echo $row['renewal_label'] ? esc_html( $row['renewal_label'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['renewal_status'] ? esc_html( wc_get_order_status_name( $row['renewal_status'] ) ) : '&mdash;'; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'No subscriptions resume on this date.', 'aaraa-white-label-admin' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		echo '</div>';
	}
}
