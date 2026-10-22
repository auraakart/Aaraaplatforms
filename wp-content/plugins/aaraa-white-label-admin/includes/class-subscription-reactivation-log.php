<?php
/**
 * Recently Reactivated Subscriptions report.
 *
 * Lists subscriptions whose STATUS actually transitioned pause -> active —
 * a real status change, as opposed to Subscription_Resume_Report, which
 * forecasts scheduled delivery-resume DATES from the pause calendar. This
 * fires from any trigger that ends in WC_Subscription::update_status(
 * 'active', ... ): the admin status dropdown, WCFM's own auto-resume cron,
 * the "Resume Now" Aaraa panel button, the My Account frontend pause/resume
 * action, or this plugin's REST API (POST /subscriptions/resume, or an
 * empty-dates POST /subscriptions/pause). All of them fire the same
 * woocommerce_subscription_status_on-hold_to_active action regardless of the
 * caller, so hooking it here covers admin, frontend and API alike in one
 * place. ("Pause" is this store's/UI's name for WCS's native 'on-hold'
 * status — WCS itself has no status literally called "pause", so the hook
 * name must use the real status slug, not the display label.)
 *
 * Reuses the existing Customer_Logs table (already used by the Customer 360
 * screen) rather than a new one — channel 'system', event
 * 'subscription_reactivated' — so this is purely additive: one insert per
 * transition, and a report screen that reads them back across all customers.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Records and reports pause -> active subscription status changes.
 */
class Subscription_Reactivation_Log {

	const PAGE  = 'aaraa-subscription-reactivation-log';
	const EVENT = 'subscription_reactivated';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_subscription_status_on-hold_to_active', array( $this, 'log_reactivation' ), 20, 1 );
	}

	/* --------------------------------------------------------------------- *
	 * Logging.
	 * --------------------------------------------------------------------- */

	/**
	 * Record a pause -> active transition in the customer log.
	 *
	 * @param \WC_Subscription $subscription Subscription that just went active.
	 * @return void
	 */
	public function log_reactivation( $subscription ) {
		if ( ! $subscription instanceof \WC_Subscription || ! class_exists( __NAMESPACE__ . '\\Customer_Logs' ) ) {
			return;
		}

		$user_id = (int) $subscription->get_customer_id();
		if ( ! $user_id ) {
			return;
		}

		$products = array();
		foreach ( (array) $subscription->get_items() as $item ) {
			$products[] = $item->get_name() . ' × ' . (int) $item->get_quantity();
		}

		Customer_Logs::add(
			$user_id,
			'system',
			self::EVENT,
			sprintf(
				/* translators: %s: subscription number */
				__( 'Subscription #%s reactivated — status changed from Pause to Active.', 'aaraa-white-label-admin' ),
				$subscription->get_order_number()
			),
			array(
				'status' => 'active',
				'meta'   => array(
					'subscription_id' => $subscription->get_id(),
					'products'        => implode( ', ', $products ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Report screen.
	 * --------------------------------------------------------------------- */

	/**
	 * The search term, trimmed.
	 *
	 * @return string
	 */
	private function current_search() {
		return isset( $_GET['reactivation_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['reactivation_search'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * How many days back to look, defaulting to 7.
	 *
	 * @return int
	 */
	private function current_days() {
		$raw = isset( $_GET['reactivation_days'] ) ? absint( $_GET['reactivation_days'] ) : 7; // phpcs:ignore WordPress.Security.NonceVerification
		return $raw > 0 ? min( $raw, 365 ) : 7;
	}

	/**
	 * Recent reactivation log rows, matched against the search term.
	 *
	 * @param int    $days   How many days back to look.
	 * @param string $search Free-text: name / email / mobile / subscription id.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_rows( $days, $search ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Customer_Logs' ) || ! Customer_Logs::table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = Customer_Logs::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$raw   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE channel = %s AND event = %s AND created_at >= %s ORDER BY id DESC LIMIT 500",
				'system',
				self::EVENT,
				$since
			)
		);

		$digits = preg_replace( '/\D+/', '', (string) $search );
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );

		$rows = array();
		foreach ( (array) $raw as $log ) {
			$meta     = $log->meta ? json_decode( $log->meta, true ) : array();
			$sub_id   = isset( $meta['subscription_id'] ) ? (int) $meta['subscription_id'] : 0;
			$products = isset( $meta['products'] ) ? (string) $meta['products'] : '';

			$user   = get_userdata( (int) $log->user_id );
			$name   = $user ? $user->display_name : '';
			$email  = $user ? $user->user_email : '';
			$mobile = $user ? (string) get_user_meta( $user->ID, 'billing_phone', true ) : '';

			if ( '' !== $search ) {
				$hay   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name . ' ' . $email ) : strtolower( $name . ' ' . $email );
				$match = ( (string) $sub_id === $search )
					|| ( '' !== $needle && false !== strpos( $hay, $needle ) )
					|| ( '' !== $digits && false !== strpos( preg_replace( '/\D+/', '', $mobile ), $digits ) );
				if ( ! $match ) {
					continue;
				}
			}

			$rows[] = array(
				'id'             => $sub_id,
				'customer'       => $name,
				'mobile'         => $mobile,
				'products'       => $products,
				'reactivated_at' => $log->created_at, // GMT.
			);
		}

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

		$days   = $this->current_days();
		$search = $this->current_search();
		$rows   = $this->get_rows( $days, $search );

		echo '<div class="wrap aaraa-wallet">';
		?>
		<div class="aaraa-wallet__bar">
			<h1 class="aaraa-wallet__title"><?php esc_html_e( 'Recently Reactivated Subscriptions', 'aaraa-white-label-admin' ); ?></h1>
		</div>

		<div class="aaraa-wallet__panel" style="border-top:1px solid #E2E8F0;border-radius:10px;">
			<form method="get" class="aaraa-wallet__toolbar" style="margin:0 0 14px;flex-wrap:wrap;gap:14px;align-items:flex-end;justify-content:flex-start;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<label>
					<strong><?php esc_html_e( 'Days back', 'aaraa-white-label-admin' ); ?></strong><br />
					<select name="reactivation_days">
						<?php foreach ( array( 1, 7, 14, 30, 90 ) as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $days, $opt ); ?>>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of days */
										_n( 'Last %d day', 'Last %d days', $opt, 'aaraa-white-label-admin' ),
										$opt
									)
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<strong><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></strong><br />
					<input type="search" name="reactivation_search" value="<?php echo esc_attr( $search ); ?>" style="min-width:280px;" placeholder="<?php esc_attr_e( 'Name, email, mobile or subscription ID', 'aaraa-white-label-admin' ); ?>" />
				</label>
				<span>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'aaraa-white-label-admin' ); ?></button>
				</span>
			</form>

			<p class="description" style="margin:0 0 12px;">
				<?php
				printf(
					/* translators: 1: number of reactivations, 2: number of days. */
					esc_html__( '%1$s subscription(s) reactivated (Pause → Active) in the last %2$d day(s).', 'aaraa-white-label-admin' ),
					esc_html( number_format_i18n( count( $rows ) ) ),
					(int) $days
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
						<th><?php esc_html_e( 'Reactivated At', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $rows ) : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td>
									<?php if ( $row['id'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['id'] . '&action=edit' ) ); ?>">#<?php echo (int) $row['id']; ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td><?php echo $row['customer'] ? esc_html( $row['customer'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['mobile'] ? esc_html( $row['mobile'] ) : '&mdash;'; ?></td>
								<td><?php echo $row['products'] ? esc_html( $row['products'] ) : '&mdash;'; ?></td>
								<td><?php echo esc_html( get_date_from_gmt( $row['reactivated_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No subscriptions reactivated in this period.', 'aaraa-white-label-admin' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		echo '</div>';
	}
}
