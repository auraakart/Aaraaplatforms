<?php
/**
 * Customer-facing Pause / Resume panel on the My Account subscription page.
 *
 * Adds the same Pause & Resume controls the store manager has on the admin
 * subscription screen to the customer's own subscription view
 * (/my-account/view-subscription/{id}/), so a customer can pause their own
 * deliveries for specific dates or a date range and resume early.
 *
 * All the actual work — validating the dates, writing the WCFM Ultimate pause
 * meta, flipping the status and arming the auto-resume cron — is delegated to
 * {@see Subscription_Delivery} static methods, so the customer and admin paths
 * behave identically. The only differences here are the permission model (the
 * subscription must belong to the signed-in customer) and storefront styling.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end subscription pause/resume panel.
 */
class Subscription_Frontend {

	const NONCE = 'aaraa_front_subscription';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_subscription_details_after_subscription_table', array( $this, 'render' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Logged-in customers only — no nopriv handler on purpose.
		add_action( 'wp_ajax_aaraa_front_sub_pause', array( $this, 'ajax_pause' ) );
		add_action( 'wp_ajax_aaraa_front_sub_resume', array( $this, 'ajax_resume' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Assets.
	 * --------------------------------------------------------------------- */

	/**
	 * Load the panel script on the view-subscription endpoint only.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'view-subscription' ) ) {
			return;
		}

		wp_enqueue_script(
			'aaraa-subscription-frontend',
			AARAA_WLA_URL . 'assets/js/subscription-frontend.js',
			array( 'jquery' ),
			aaraa_asset_ver( 'assets/js/subscription-frontend.js' ),
			true
		);
		wp_localize_script(
			'aaraa-subscription-frontend',
			'AaraaFrontSub',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'failed'  => __( 'Request failed. Please try again.', 'aaraa-white-label-admin' ),
				'confirm' => __( 'Pause deliveries for the selected dates?', 'aaraa-white-label-admin' ),
				'resume'  => __( 'Resume deliveries now?', 'aaraa-white-label-admin' ),
				'nodates' => __( 'Add at least one date first.', 'aaraa-white-label-admin' ),
				/* translators: %d: number of selected dates — replaced in JS. */
				'multi'   => __( 'Deliveries will be paused only on these %d dates. Other days are delivered as usual.', 'aaraa-white-label-admin' ),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Access.
	 * --------------------------------------------------------------------- */

	/**
	 * Whether the current user owns (or can manage) this subscription.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @return bool
	 */
	private function can_manage( $subscription ) {
		if ( ! is_a( $subscription, 'WC_Subscription' ) ) {
			return false;
		}
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		return get_current_user_id() && (int) $subscription->get_user_id() === get_current_user_id();
	}

	/**
	 * Whether WCFM Ultimate's `pause` status is registered.
	 *
	 * @return bool
	 */
	private function pause_status_available() {
		return null !== get_post_status_object( 'wc-pause' );
	}

	/**
	 * Saved pause dates for a subscription — robust to how the value was stored.
	 *
	 * The value has, over time, been written as a JSON string. But get_post_meta()
	 * auto-unserializes, so a value stored as a PHP array comes back as an array
	 * (and json_decode() on it yields null). Under HPOS it may also live only in
	 * the order CRUD meta store. Handle every shape: array, JSON string, or a
	 * comma-separated string, from post meta first and the CRUD store as a
	 * fallback.
	 *
	 * @param int $sub_id Subscription id.
	 * @return string[]
	 */
	private function saved_pause_dates( $sub_id ) {
		$raw = get_post_meta( $sub_id, Subscription_Delivery::META_PAUSE, true );

		if ( '' === $raw || null === $raw || array() === $raw ) {
			// HPOS fallback: the order CRUD meta store.
			$sub = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : false;
			if ( $sub ) {
				$raw = $sub->get_meta( Subscription_Delivery::META_PAUSE );
			}
		}

		return $this->parse_dates( $raw );
	}

	/**
	 * Normalise a stored pause-dates value into a clean list of Y-m-d strings.
	 *
	 * @param mixed $raw Array, JSON string, or comma-separated string.
	 * @return string[]
	 */
	private function parse_dates( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}

		if ( is_array( $raw ) ) {
			$list = $raw;
		} else {
			$decoded = json_decode( (string) $raw, true );
			$list    = is_array( $decoded ) ? $decoded : explode( ',', (string) $raw );
		}

		$out = array();
		foreach ( $list as $d ) {
			$d = trim( (string) $d );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
				$out[] = $d;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * The resume date for a paused subscription, robust to missing meta.
	 *
	 * Prefers the stored `_wcfmu_pause_resume` meta; if that is empty (the
	 * subscription was paused through another route) it falls back to the
	 * scheduled auto-resume cron event, then to the day after the last paused
	 * date. Returns '' only when nothing is known.
	 *
	 * @param int      $sub_id      Subscription id.
	 * @param string[] $pause_dates Known paused dates (sorted).
	 * @return string Y-m-d or ''.
	 */
	private function resume_date( $sub_id, $pause_dates ) {
		$stored = (string) get_post_meta( $sub_id, Subscription_Delivery::META_RESUME, true );
		if ( $stored ) {
			return $stored;
		}

		$ts = wp_next_scheduled( Subscription_Delivery::CRON_RESUME, array( $sub_id ) );
		if ( $ts ) {
			// Cron is stored in UTC; show it as the site-local calendar day.
			return wp_date( 'Y-m-d', $ts );
		}

		if ( ! empty( $pause_dates ) ) {
			return gmdate( 'Y-m-d', strtotime( end( $pause_dates ) . ' +1 day' ) );
		}

		return '';
	}

	/**
	 * Group the chosen pause dates into contiguous blocks.
	 *
	 * Each block resumes the day after its own last date, so scattered dates
	 * (5th, 11th) become two blocks (resuming 6th and 12th) while a range
	 * (1st–6th) stays one block (resuming 7th).
	 *
	 * @param string[] $dates Y-m-d dates.
	 * @return array<int,array{start:string,end:string}>
	 */
	private function pause_blocks( $dates ) {
		$dates = array_values( array_unique( array_filter( (array) $dates ) ) );
		sort( $dates );
		if ( empty( $dates ) ) {
			return array();
		}

		$blocks = array();
		$start  = $dates[0];
		$prev   = $dates[0];

		for ( $i = 1, $n = count( $dates ); $i < $n; $i++ ) {
			$expected = gmdate( 'Y-m-d', strtotime( $prev . ' +1 day' ) );
			if ( $dates[ $i ] !== $expected ) {
				$blocks[] = array( 'start' => $start, 'end' => $prev );
				$start    = $dates[ $i ];
			}
			$prev = $dates[ $i ];
		}
		$blocks[] = array( 'start' => $start, 'end' => $prev );

		return $blocks;
	}

	/**
	 * Readable label for one contiguous block (single day or a range).
	 *
	 * @param string $start  Y-m-d.
	 * @param string $end    Y-m-d.
	 * @param string $format PHP date format.
	 * @return string
	 */
	private function format_block( $start, $end, $format ) {
		if ( $start === $end ) {
			return date_i18n( $format, strtotime( $start ) );
		}
		return sprintf(
			/* translators: 1: first date, 2: last date */
			__( '%1$s – %2$s', 'aaraa-white-label-admin' ),
			date_i18n( $format, strtotime( $start ) ),
			date_i18n( $format, strtotime( $end ) )
		);
	}

	/* --------------------------------------------------------------------- *
	 * Render.
	 * --------------------------------------------------------------------- */

	/**
	 * Output the pause/resume panel under the subscription details table.
	 *
	 * @param \WC_Subscription $subscription Subscription (passed by the WCS hook).
	 * @return void
	 */
	public function render( $subscription ) {
		if ( ! $this->can_manage( $subscription ) ) {
			return;
		}
		if ( ! $this->pause_status_available() ) {
			return;
		}

		$sub_id = $subscription->get_id();
		$status = $subscription->get_status();
		$paused = ( 'pause' === $status );
		$today  = current_time( 'Y-m-d' );

		// Nothing to offer on a finished subscription.
		if ( ! $paused && in_array( $status, array( 'cancelled', 'expired', 'pending-cancel', 'switched' ), true ) ) {
			return;
		}

		$pause_dates = $this->saved_pause_dates( $sub_id );
		$fmt         = get_option( 'date_format' );
		// Only the days still ahead (today onward) are relevant to show.
		$upcoming    = array_values(
			array_filter(
				$pause_dates,
				static function ( $d ) use ( $today ) {
					return $d >= $today;
				}
			)
		);
		$blocks       = $this->pause_blocks( $upcoming );
		// A schedule exists if it is paused right now, or has upcoming dates.
		$has_schedule = $paused || ! empty( $upcoming );
		?>
		<div class="aaraa-fsub" data-subid="<?php echo esc_attr( $sub_id ); ?>">
			<h2 class="aaraa-fsub__title"><?php esc_html_e( 'Pause Subscription', 'aaraa-white-label-admin' ); ?></h2>

			<p class="aaraa-fsub__note">
				<strong><?php esc_html_e( 'Note:', 'aaraa-white-label-admin' ); ?></strong>
				<?php esc_html_e( 'Changes made after 8:00 PM will take effect from the following delivery cycle. Tomorrow\'s scheduled delivery cannot be paused or resumed and will be attempted as planned.', 'aaraa-white-label-admin' ); ?>
			</p>

			<?php if ( $has_schedule ) : ?>

				<div class="aaraa-fsub__paused">
					<strong>
						<?php
						echo $paused
							? esc_html__( 'Your deliveries are paused.', 'aaraa-white-label-admin' )
							: esc_html__( 'A pause is scheduled.', 'aaraa-white-label-admin' );
						?>
					</strong>
				</div>

				<?php if ( ! empty( $blocks ) ) : ?>
					<table class="aaraa-fsub__facts">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Paused dates', 'aaraa-white-label-admin' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Deliveries resume', 'aaraa-white-label-admin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $blocks as $block ) : ?>
								<tr>
									<td><?php echo esc_html( $this->format_block( $block['start'], $block['end'], $fmt ) ); ?></td>
									<td><?php echo esc_html( date_i18n( $fmt, strtotime( $block['end'] . ' +1 day' ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<?php $resume_date = $this->resume_date( $sub_id, $pause_dates ); ?>
					<table class="aaraa-fsub__facts">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Paused dates', 'aaraa-white-label-admin' ); ?></th>
								<td><?php esc_html_e( 'Until you resume', 'aaraa-white-label-admin' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Deliveries resume', 'aaraa-white-label-admin' ); ?></th>
								<td>
									<?php
									echo $resume_date
										? esc_html( date_i18n( $fmt, strtotime( $resume_date ) ) )
										: esc_html__( 'When you resume below', 'aaraa-white-label-admin' );
									?>
								</td>
							</tr>
						</tbody>
					</table>
				<?php endif; ?>

				<p>
					<button type="button" class="button aaraa-fsub__btn aaraa-fsub__btn--resume" id="aaraa_fsub_resume">
						<?php
						echo $paused
							? esc_html__( 'Resume Now', 'aaraa-white-label-admin' )
							: esc_html__( 'Cancel Scheduled Pause', 'aaraa-white-label-admin' );
						?>
					</button>
					<span class="aaraa-fsub__spinner" aria-hidden="true"></span>
				</p>
				<p class="aaraa-fsub__muted">
					<?php
					echo $paused
						? esc_html__( 'Resuming restarts deliveries from the next scheduled day.', 'aaraa-white-label-admin' )
						: esc_html__( 'Cancelling clears the scheduled pause dates.', 'aaraa-white-label-admin' );
					?>
				</p>

			<?php else : ?>

				<div class="aaraa-fsub__tabs">
					<button type="button" class="aaraa-fsub__tab is-active" data-mode="dates">
						<?php esc_html_e( 'Specific Dates', 'aaraa-white-label-admin' ); ?>
					</button>
					<button type="button" class="aaraa-fsub__tab" data-mode="range">
						<?php esc_html_e( 'Date Range', 'aaraa-white-label-admin' ); ?>
					</button>
				</div>

				<div class="aaraa-fsub__mode" data-mode="dates">
					<label class="aaraa-fsub__label" for="aaraa_fsub_date"><?php esc_html_e( 'Add a date', 'aaraa-white-label-admin' ); ?></label>
					<div class="aaraa-fsub__addrow">
						<input type="date" id="aaraa_fsub_date" min="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>" />
						<button type="button" class="button aaraa-fsub__btn aaraa-fsub__btn--add" id="aaraa_fsub_add"><?php esc_html_e( 'Add', 'aaraa-white-label-admin' ); ?></button>
					</div>
					<ul class="aaraa-fsub__chips" id="aaraa_fsub_chips"></ul>
					<p class="aaraa-fsub__muted" id="aaraa_fsub_none"><?php esc_html_e( 'No dates selected yet.', 'aaraa-white-label-admin' ); ?></p>
					<p class="aaraa-fsub__warn" id="aaraa_fsub_gap" style="display:none;"></p>
				</div>

				<div class="aaraa-fsub__mode" data-mode="range" style="display:none;">
					<label class="aaraa-fsub__label" for="aaraa_fsub_from"><?php esc_html_e( 'Pause from', 'aaraa-white-label-admin' ); ?></label>
					<input type="date" id="aaraa_fsub_from" class="aaraa-fsub__wide" min="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>" />
					<label class="aaraa-fsub__label" for="aaraa_fsub_to"><?php esc_html_e( 'Pause to', 'aaraa-white-label-admin' ); ?></label>
					<input type="date" id="aaraa_fsub_to" class="aaraa-fsub__wide" min="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>" />
					<p class="aaraa-fsub__muted"><?php esc_html_e( 'Inclusive. Deliveries restart the next day.', 'aaraa-white-label-admin' ); ?></p>
				</div>

				<p>
					<button type="button" class="button aaraa-fsub__btn aaraa-fsub__btn--pause" id="aaraa_fsub_pause">
						<?php esc_html_e( 'Pause', 'aaraa-white-label-admin' ); ?>
					</button>
					<span class="aaraa-fsub__spinner" aria-hidden="true"></span>
				</p>

			<?php endif; ?>

			<p class="aaraa-fsub__msg" id="aaraa_fsub_msg" role="status" aria-live="polite"></p>
		</div>

		<style>
			.aaraa-fsub { margin: 24px 0; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; max-width: 460px; }
			.aaraa-fsub__title { font-size: 16px; margin: 0 0 10px; }
			.aaraa-fsub__note { margin: 0 0 16px; padding: 10px 12px; background: #eff6ff; border-left: 3px solid #1e3a8a; border-radius: 6px; color: #334155; font-size: 12.5px; line-height: 1.5; }
			.aaraa-fsub__note strong { color: #1e3a8a; }
			.aaraa-fsub__tabs { display: flex; border: 1px solid #1e3a8a; border-radius: 8px; overflow: hidden; margin-bottom: 16px; }
			.aaraa-fsub__tab { flex: 1; padding: 10px 8px; border: 0; background: #fff; color: #1e3a8a; cursor: pointer; font-size: 13px; font-weight: 600; }
			.aaraa-fsub__tab + .aaraa-fsub__tab { border-left: 1px solid #1e3a8a; }
			.aaraa-fsub__tab.is-active { background: #1e3a8a; color: #fff; }
			.aaraa-fsub__label { display: block; font-weight: 600; margin: 0 0 6px; }
			.aaraa-fsub__addrow { display: flex; gap: 10px; align-items: stretch; }
			.aaraa-fsub__addrow input[type=date] { flex: 1; min-width: 0; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
			.aaraa-fsub input.aaraa-fsub__wide { display: block; width: 100%; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 12px; box-sizing: border-box; }
			.aaraa-fsub__btn { border-radius: 8px; cursor: pointer; font-weight: 600; padding: 9px 18px; border: 1px solid transparent; line-height: 1.2; }
			.aaraa-fsub__btn--add { background: #fff; color: #1e3a8a; border-color: #1e3a8a; }
			.aaraa-fsub__btn--pause { background: #17a2b8; color: #fff; }
			.aaraa-fsub__btn--pause:hover { background: #138496; }
			.aaraa-fsub__btn--resume { background: #16a34a; color: #fff; }
			.aaraa-fsub__btn--resume:hover { background: #15803d; }
			.aaraa-fsub__btn[disabled] { opacity: .6; cursor: default; }
			.aaraa-fsub__chips { list-style: none; margin: 12px 0 0; padding: 0; }
			.aaraa-fsub__chips li { display: inline-flex; align-items: center; gap: 6px; margin: 0 6px 6px 0; padding: 4px 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 16px; font-size: 13px; }
			.aaraa-fsub__chips button { border: 0; background: none; cursor: pointer; color: #b91c1c; font-size: 16px; line-height: 1; padding: 0; }
			.aaraa-fsub__muted { color: #64748b; font-size: 13px; margin: 8px 0 0; }
			.aaraa-fsub__warn { color: #475569; font-size: 13px; margin: 8px 0 0; }
			.aaraa-fsub__paused { padding: 12px 14px; background: #fef3c7; border-radius: 8px; margin-bottom: 14px; }
			.aaraa-fsub__paused > div { margin-top: 4px; }
			.aaraa-fsub__facts { width: 100%; border-collapse: collapse; margin: 0 0 14px; }
			.aaraa-fsub__facts th, .aaraa-fsub__facts td { text-align: left; padding: 8px 10px; border: 1px solid #e2e8f0; font-size: 14px; vertical-align: top; }
			.aaraa-fsub__facts thead th { width: 50%; background: #f1f5f9; color: #475569; font-weight: 600; }
			.aaraa-fsub__facts tbody th { width: 40%; background: #f8fafc; color: #475569; font-weight: 600; }
			.aaraa-fsub__facts tbody td { font-weight: 600; color: #1e293b; }
			.aaraa-fsub__msg { margin: 12px 0 0; font-size: 13px; }
			.aaraa-fsub__msg.ok { color: #15803d; }
			.aaraa-fsub__msg.error { color: #b91c1c; }
			.aaraa-fsub__spinner { display: none; width: 16px; height: 16px; margin-left: 8px; vertical-align: middle; border: 2px solid #cbd5e1; border-top-color: #1e3a8a; border-radius: 50%; animation: aaraa-fsub-spin .7s linear infinite; }
			.aaraa-fsub__spinner.is-active { display: inline-block; }
			@keyframes aaraa-fsub-spin { to { transform: rotate(360deg); } }
		</style>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * AJAX.
	 * --------------------------------------------------------------------- */

	/**
	 * Shared guard: nonce, login, ownership.
	 *
	 * @return \WC_Subscription Dies with a JSON error when not permitted.
	 */
	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please sign in and try again.', 'aaraa-white-label-admin' ) ) );
		}
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			wp_send_json_error( array( 'message' => __( 'Subscriptions are not available.', 'aaraa-white-label-admin' ) ) );
		}

		$sub_id       = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
		$subscription = $sub_id ? wcs_get_subscription( $sub_id ) : false;
		if ( ! $subscription || ! $this->can_manage( $subscription ) ) {
			wp_send_json_error( array( 'message' => __( 'This subscription is not available on your account.', 'aaraa-white-label-admin' ) ) );
		}

		return $subscription;
	}

	/**
	 * AJAX: pause over the selected dates / range.
	 *
	 * @return void
	 */
	public function ajax_pause() {
		$subscription = $this->guard();

		if ( in_array( $subscription->get_status(), array( 'cancelled', 'expired', 'pending-cancel', 'switched' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This subscription can no longer be paused.', 'aaraa-white-label-admin' ) ) );
		}

		// Same validation the admin panel uses.
		$dates  = Subscription_Delivery::resolve_pause_dates();
		$result = Subscription_Delivery::apply_pause( $subscription, $dates, 'customer' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: number of days, 2: resume date */
					__( 'Deliveries paused for %1$d day(s). They resume on %2$s.', 'aaraa-white-label-admin' ),
					$result['count'],
					date_i18n( get_option( 'date_format' ), strtotime( $result['resume'] ) )
				),
				'reload'  => true,
			)
		);
	}

	/**
	 * AJAX: resume now.
	 *
	 * @return void
	 */
	public function ajax_resume() {
		$subscription = $this->guard();
		$sub_id       = $subscription->get_id();

		if ( 'pause' === $subscription->get_status() ) {
			// Currently paused → resume (wallet-aware active/on-hold) + clear.
			Subscription_Delivery::resume_subscription( $subscription, 'customer' );
			$message = __( 'Deliveries resumed.', 'aaraa-white-label-admin' );
		} else {
			// A future pause that hasn't started → just cancel it; leave the
			// current (active) status untouched.
			Subscription_Delivery::clear_pause_schedule( $sub_id );
			$subscription->add_order_note( __( 'Scheduled pause cancelled (by customer).', 'aaraa-white-label-admin' ) );
			$message = __( 'Scheduled pause cancelled.', 'aaraa-white-label-admin' );
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'reload'  => true,
			)
		);
	}
}
