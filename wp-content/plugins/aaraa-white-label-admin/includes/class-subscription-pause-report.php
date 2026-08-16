<?php
/**
 * Subscription Pause Report.
 *
 * Lists subscriptions that are paused on a given date — i.e. the date is one of
 * the subscription's chosen pause dates ( `_wcfmu_pause_dates` ). Defaults to
 * today, with a date picker to inspect any other day, plus a search box that
 * matches customer name / email / mobile / subscription id.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription pause report screen.
 */
class Subscription_Pause_Report {

	const PAGE = 'aaraa-subscription-pause-report';

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
		$raw = isset( $_GET['pause_date'] ) ? sanitize_text_field( wp_unslash( $_GET['pause_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
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
		return isset( $_GET['pause_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['pause_search'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Subscriptions paused on the given date, matched against the search term.
	 *
	 * @param string $date   Y-m-d.
	 * @param string $search Free-text: name / email / mobile / subscription id.
	 * @return array<int,array<string,string>> Rows with id, customer, mobile, products.
	 */
	private function get_rows( $date, $search ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array();
		}

		// Candidate subscriptions from live meta AND historical order notes, so
		// past pause dates (pruned from meta on resume) are still reported.
		$ids = Pause_History::candidates_for_pause( $date );
		if ( empty( $ids ) ) {
			return array();
		}

		$digits = preg_replace( '/\D+/', '', (string) $search );
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );

		$rows = array();
		foreach ( $ids as $sub_id ) {
			$sub_id = (int) $sub_id;

			// Verify the date is truly one of the pause dates (meta or notes).
			if ( ! in_array( $date, Pause_History::pause_dates_for( $sub_id ), true ) ) {
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

			$rows[] = array(
				'id'       => $sub_id,
				'customer' => $name,
				'mobile'   => $mobile,
				'products' => implode( ', ', $products ),
			);
		}

		// Newest subscription first.
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) $b['id'] - (int) $a['id'];
			}
		);

		return $rows;
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
			<h1 class="aaraa-wallet__title"><?php esc_html_e( 'Subscription Pause Report', 'aaraa-white-label-admin' ); ?></h1>
		</div>

		<div class="aaraa-wallet__panel" style="border-top:1px solid #E2E8F0;border-radius:10px;">
			<form method="get" class="aaraa-wallet__toolbar" style="margin:0 0 14px;flex-wrap:wrap;gap:14px;align-items:flex-end;justify-content:flex-start;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<label>
					<strong><?php esc_html_e( 'Date', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="date" name="pause_date" value="<?php echo esc_attr( $date ); ?>" />
				</label>
				<label>
					<strong><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="search" name="pause_search" value="<?php echo esc_attr( $search ); ?>" style="min-width:280px;" placeholder="<?php esc_attr_e( 'Name, email, mobile or subscription ID', 'aaraa-white-label-admin' ); ?>" />
				</label>
				<span>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'aaraa-white-label-admin' ); ?></button>
					<?php
					$today_ymd    = current_time( 'Y-m-d' );
					$tomorrow_ymd = gmdate( 'Y-m-d', current_time( 'timestamp' ) + DAY_IN_SECONDS );
					?>
					<a class="button <?php echo $date === $tomorrow_ymd ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE ), $base ) ); ?>"><?php esc_html_e( 'Tomorrow', 'aaraa-white-label-admin' ); ?></a>
					<a class="button <?php echo $date === $today_ymd ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'pause_date' => $today_ymd ), $base ) ); ?>"><?php esc_html_e( 'Today', 'aaraa-white-label-admin' ); ?></a>
				</span>
			</form>

			<p class="description" style="margin:0 0 12px;">
				<?php
				printf(
					/* translators: 1: number of paused subscriptions, 2: date. */
					esc_html__( '%1$s subscription(s) paused on %2$s.', 'aaraa-white-label-admin' ),
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
					</tr>
				</thead>
				<tbody>
					<?php if ( $rows ) : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['id'] . '&action=edit' ) ); ?>">#<?php echo (int) $row['id']; ?></a></td>
								<td><?php echo $row['customer'] ? esc_html( $row['customer'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['mobile'] ? esc_html( $row['mobile'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['products'] ? esc_html( $row['products'] ) : '&mdash;'; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'No subscriptions are paused on this date.', 'aaraa-white-label-admin' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		echo '</div>';
	}
}
