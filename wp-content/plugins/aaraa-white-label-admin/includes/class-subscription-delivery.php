<?php
/**
 * "Aaraa Subscription" panel on the subscription edit screen.
 *
 * Brings the two WCFM Ultimate store-manager controls — Delivery Schedule and
 * Pause / Resume — onto admin.php?page=wc-orders--shop_subscription&action=edit.
 *
 * This reads and writes exactly the meta WCFM Ultimate uses, through
 * get_post_meta()/update_post_meta() rather than the order CRUD, because that is
 * where the behaviour actually lives: WCFMu's renewal recalculation
 * (class-wcfmu-subscription-create.php) and the theme's My Account display
 * (themes/farmart/functions.php) both read it with get_post_meta(). Writing it
 * as HPOS order meta instead would store a second copy that nothing reads.
 *
 *   _wcfm_delivery_schedule  daily | alternate | weekend | custom
 *   _wcfm_delivery_days      int[] 0–6 (weekend is stored as [0,6])
 *   _wcfmu_pause_dates       JSON array of Y-m-d
 *   _wcfmu_pause_resume      Y-m-d
 *
 * Resuming delegates to WCFMu's own auto_resume_subscription() so the
 * wallet-balance rule that decides active vs on-hold stays in one place, and
 * pausing arms the same `wcfmu_auto_resume_subscription` cron event.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription delivery schedule + pause metabox.
 */
class Subscription_Delivery {

	const META_SCHEDULE = '_wcfm_delivery_schedule';
	const META_DAYS     = '_wcfm_delivery_days';
	const META_SLOT     = '_aaraa_delivery_slot';
	const META_PAUSE    = '_wcfmu_pause_dates';
	const META_RESUME   = '_wcfmu_pause_resume';

	const CRON_RESUME = 'wcfmu_auto_resume_subscription';

	const NONCE = 'aaraa_subscription_delivery';

	/**
	 * Longest pause we will accept, in days.
	 */
	const MAX_PAUSE_DAYS = 366;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_aaraa_sub_schedule', array( $this, 'ajax_schedule' ) );
		add_action( 'wp_ajax_aaraa_sub_next_payment', array( $this, 'ajax_next_payment' ) );
		add_action( 'wp_ajax_aaraa_sub_pause', array( $this, 'ajax_pause' ) );
		add_action( 'wp_ajax_aaraa_sub_resume', array( $this, 'ajax_resume' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Screen wiring.
	 * --------------------------------------------------------------------- */

	/**
	 * Screen ids for the subscription edit page (HPOS + legacy).
	 *
	 * @return string[]
	 */
	private function screen_ids() {
		$ids = array( 'shop_subscription' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$ids[] = wc_get_page_screen_id( 'shop-subscription' );
		}
		$ids[] = 'woocommerce_page_wc-orders--shop_subscription';
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Register the metabox on every subscription edit screen.
	 *
	 * @return void
	 */
	public function add_box() {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}
		foreach ( $this->screen_ids() as $screen ) {
			add_meta_box(
				'aaraa-subscription-delivery',
				__( 'Aaraa Delivery &amp; Pause', 'aaraa-white-label-admin' ),
				array( $this, 'render' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Load the panel script on the subscription edit screen.
	 *
	 * @return void
	 */
	public function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, $this->screen_ids(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'aaraa-subscription-delivery',
			AARAA_WLA_URL . 'assets/js/subscription-delivery.js',
			array( 'jquery' ),
			aaraa_asset_ver( 'assets/js/subscription-delivery.js' ),
			true
		);
		wp_localize_script(
			'aaraa-subscription-delivery',
			'AaraaSubDelivery',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'failed'  => __( 'Request failed', 'aaraa-white-label-admin' ),
				'confirm' => __( 'Pause this subscription for the selected dates?', 'aaraa-white-label-admin' ),
				'nodates' => __( 'Add at least one date first.', 'aaraa-white-label-admin' ),
				/* translators: %d: number of selected dates — replaced in JS. */
				'multi'   => __( 'Paused only on these %d dates; other days are delivered as usual.', 'aaraa-white-label-admin' ),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Data.
	 * --------------------------------------------------------------------- */

	/**
	 * The schedule types WCFM Ultimate accepts.
	 *
	 * Public + static so the schedule listing API ({@see Delivery_Schedule_API})
	 * shares the exact same source of truth.
	 *
	 * @return array<string, string>
	 */
	public static function schedule_types() {
		return array(
			'daily'     => __( 'Every Day', 'aaraa-white-label-admin' ),
			'alternate' => __( 'Alternative Day', 'aaraa-white-label-admin' ),
			'weekend'   => __( 'Every Weekend (Sat & Sun)', 'aaraa-white-label-admin' ),
			'custom'    => __( 'Custom Day', 'aaraa-white-label-admin' ),
		);
	}

	/**
	 * Weekday map, Monday first — value is PHP's `w` (0 = Sunday).
	 *
	 * @return array<int, string>
	 */
	public static function weekdays() {
		return array(
			1 => __( 'Mon', 'aaraa-white-label-admin' ),
			2 => __( 'Tue', 'aaraa-white-label-admin' ),
			3 => __( 'Wed', 'aaraa-white-label-admin' ),
			4 => __( 'Thu', 'aaraa-white-label-admin' ),
			5 => __( 'Fri', 'aaraa-white-label-admin' ),
			6 => __( 'Sat', 'aaraa-white-label-admin' ),
			0 => __( 'Sun', 'aaraa-white-label-admin' ),
		);
	}

	/**
	 * Active delivery slots for the selector, reusing the slots API as the
	 * single source of truth so admin + API stay in sync.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function available_slots() {
		if ( class_exists( '\Aaraa\Admin\Delivery_Slots_API' ) ) {
			return Delivery_Slots_API::slots_payload( 'active' );
		}
		return array();
	}

	/**
	 * Human-readable label for a slot id (for order notes / responses).
	 *
	 * @param int $slot_id Slot id.
	 * @return string
	 */
	private function slot_label( $slot_id ) {
		if ( class_exists( '\Aaraa\Admin\Delivery_Slots_API' ) ) {
			$slot = Delivery_Slots_API::slot_by_id( $slot_id );
			if ( $slot ) {
				return $slot['label'];
			}
		}
		/* translators: %d: slot id */
		return sprintf( __( 'Slot #%d', 'aaraa-white-label-admin' ), (int) $slot_id );
	}

	/**
	 * Saved custom days as a clean int array.
	 *
	 * @param int $sub_id Subscription id.
	 * @return int[]
	 */
	private function saved_days( $sub_id ) {
		$days = get_post_meta( $sub_id, self::META_DAYS, true );
		if ( ! is_array( $days ) ) {
			return array();
		}
		return array_map( 'absint', $days );
	}

	/**
	 * Saved pause dates as a clean array.
	 *
	 * @param int $sub_id Subscription id.
	 * @return string[]
	 */
	private function saved_pause_dates( $sub_id ) {
		$raw = get_post_meta( $sub_id, self::META_PAUSE, true );
		if ( ! $raw ) {
			return array();
		}
		$dates = json_decode( $raw, true );
		return is_array( $dates ) ? $dates : array();
	}

	/**
	 * Whether WCFM Ultimate has registered its `pause` subscription status.
	 *
	 * Without it update_status('pause') would leave the subscription in an
	 * unknown state, so the panel says so rather than failing halfway.
	 *
	 * @return bool
	 */
	private function pause_status_available() {
		return null !== get_post_status_object( 'wc-pause' );
	}

	/* --------------------------------------------------------------------- *
	 * Render.
	 * --------------------------------------------------------------------- */

	/**
	 * Metabox body.
	 *
	 * @param mixed $post_or_order Post object (legacy) or WC_Order (HPOS).
	 * @return void
	 */
	public function render( $post_or_order ) {
		$sub_id = 0;
		if ( $post_or_order instanceof \WC_Order ) {
			$sub_id = $post_or_order->get_id();
		} elseif ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
			$sub_id = (int) $post_or_order->ID;
		} elseif ( is_numeric( $post_or_order ) ) {
			$sub_id = (int) $post_or_order;
		}

		$subscription = $sub_id ? wcs_get_subscription( $sub_id ) : false;
		if ( ! $subscription ) {
			echo '<p>' . esc_html__( 'Subscription not available.', 'aaraa-white-label-admin' ) . '</p>';
			return;
		}

		$schedule = (string) get_post_meta( $sub_id, self::META_SCHEDULE, true );
		$days     = $this->saved_days( $sub_id );
		$slot_id  = (int) get_post_meta( $sub_id, self::META_SLOT, true );
		$slots    = $this->available_slots();
		$status   = $subscription->get_status();
		$paused   = ( 'pause' === $status );
		$today    = current_time( 'Y-m-d' );
		?>
		<div class="aaraa-subdel" data-subid="<?php echo esc_attr( $sub_id ); ?>">

			<h4 class="aaraa-subdel__head"><?php esc_html_e( 'Delivery Slot', 'aaraa-white-label-admin' ); ?></h4>

			<?php if ( ! empty( $slots ) ) : ?>
				<p>
					<select id="aaraa_sub_slot" class="aaraa-subdel__slot" style="width:100%">
						<option value="0"><?php esc_html_e( '— No specific slot —', 'aaraa-white-label-admin' ); ?></option>
						<?php foreach ( $slots as $slot ) : ?>
							<option value="<?php echo esc_attr( $slot['id'] ); ?>" <?php selected( $slot['id'], $slot_id ); ?>>
								<?php echo esc_html( $slot['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'No active delivery slots are configured.', 'aaraa-white-label-admin' ); ?>
					<?php
					if ( $slot_id ) {
						printf(
							/* translators: %d: slot id */
							' ' . esc_html__( '(Current slot #%d is inactive or removed.)', 'aaraa-white-label-admin' ),
							$slot_id
						);
					}
					?>
				</p>
			<?php endif; ?>

			<hr />

			<h4 class="aaraa-subdel__head"><?php esc_html_e( 'Delivery Schedule', 'aaraa-white-label-admin' ); ?></h4>

			<?php foreach ( $this->schedule_types() as $value => $label ) : ?>
				<label class="aaraa-subdel__radio">
					<input type="radio" name="aaraa_sub_schedule" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $schedule ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>

			<div id="aaraa_sub_days" class="aaraa-subdel__days" style="display:<?php echo ( 'custom' === $schedule ) ? 'block' : 'none'; ?>;">
				<span class="description"><?php esc_html_e( 'Delivery days', 'aaraa-white-label-admin' ); ?></span><br />
				<?php foreach ( $this->weekdays() as $num => $name ) : ?>
					<label class="aaraa-subdel__day">
						<input type="checkbox" class="aaraa-sub-day" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( $num, $days, true ) ); ?> />
						<?php echo esc_html( $name ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<p>
				<button type="button" class="button button-primary" id="aaraa_sub_schedule_save">
					<?php esc_html_e( 'Update Schedule', 'aaraa-white-label-admin' ); ?>
				</button>
				<span class="spinner aaraa-subdel__spinner" style="float:none;margin:0 0 0 6px;"></span>
			</p>
			<p class="aaraa-subdel__msg" id="aaraa_sub_schedule_msg" role="status" aria-live="polite"></p>

			<hr />

			<h4 class="aaraa-subdel__head"><?php esc_html_e( 'Next Renewal Date', 'aaraa-white-label-admin' ); ?></h4>

			<?php
			$np_ts    = (int) $subscription->get_time( 'next_payment', 'gmt' );
			$np_local = $np_ts ? wp_date( 'Y-m-d\TH:i', $np_ts ) : '';
			?>
			<p>
				<input type="datetime-local" id="aaraa_sub_next_payment" value="<?php echo esc_attr( $np_local ); ?>" style="width:100%" />
				<span class="description"><?php esc_html_e( 'Date &amp; time the next renewal order is created (site time).', 'aaraa-white-label-admin' ); ?></span>
			</p>
			<p>
				<button type="button" class="button button-primary" id="aaraa_sub_next_payment_save">
					<?php esc_html_e( 'Update Next Renewal Date', 'aaraa-white-label-admin' ); ?>
				</button>
				<span class="spinner aaraa-subdel__spinner" style="float:none;margin:0 0 0 6px;"></span>
			</p>
			<p class="aaraa-subdel__msg" id="aaraa_sub_next_payment_msg" role="status" aria-live="polite"></p>

			<hr />

			<h4 class="aaraa-subdel__head"><?php esc_html_e( 'Pause Subscription', 'aaraa-white-label-admin' ); ?></h4>

			<?php if ( ! $this->pause_status_available() ) : ?>

				<p class="description">
					<?php esc_html_e( 'The Paused status is registered by WCFM Ultimate. Enable its subscriptions module to pause from here.', 'aaraa-white-label-admin' ); ?>
				</p>

			<?php elseif ( $paused ) : ?>

				<?php
				$resume_date = (string) get_post_meta( $sub_id, self::META_RESUME, true );
				$pause_dates = $this->saved_pause_dates( $sub_id );
				?>
				<p class="aaraa-subdel__paused">
					<strong><?php esc_html_e( 'Currently paused.', 'aaraa-white-label-admin' ); ?></strong>
					<?php if ( $resume_date ) : ?>
						<br /><?php
						printf(
							/* translators: %s: resume date */
							esc_html__( 'Auto-resumes on %s.', 'aaraa-white-label-admin' ),
							esc_html( $resume_date )
						);
						?>
					<?php endif; ?>
					<?php if ( $pause_dates ) : ?>
						<br /><span class="description">
							<?php
							printf(
								/* translators: 1: number of days, 2: first date, 3: last date */
								esc_html__( '%1$d day(s): %2$s to %3$s', 'aaraa-white-label-admin' ),
								count( $pause_dates ),
								esc_html( reset( $pause_dates ) ),
								esc_html( end( $pause_dates ) )
							);
							?>
						</span>
					<?php endif; ?>
				</p>
				<p>
					<button type="button" class="button button-primary" id="aaraa_sub_resume">
						<?php esc_html_e( 'Resume Now', 'aaraa-white-label-admin' ); ?>
					</button>
					<span class="spinner aaraa-subdel__spinner" style="float:none;margin:0 0 0 6px;"></span>
				</p>
				<p class="description">
					<?php esc_html_e( 'Resuming sets the subscription active, or on-hold if the wallet balance will not cover the next renewal.', 'aaraa-white-label-admin' ); ?>
				</p>

			<?php elseif ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) : ?>

				<p class="description">
					<?php esc_html_e( 'A cancelled or expired subscription cannot be paused.', 'aaraa-white-label-admin' ); ?>
				</p>

			<?php else : ?>

				<?php // Same two modes as the WCFM Ultimate store-manager modal. ?>
				<div class="aaraa-subdel__tabs">
					<button type="button" class="aaraa-subdel__tab is-active" data-mode="dates">
						<?php esc_html_e( 'Specific Dates', 'aaraa-white-label-admin' ); ?>
					</button>
					<button type="button" class="aaraa-subdel__tab" data-mode="range">
						<?php esc_html_e( 'Date Range', 'aaraa-white-label-admin' ); ?>
					</button>
				</div>

				<div class="aaraa-subdel__mode" data-mode="dates">
					<p>
						<label for="aaraa_pause_date"><strong><?php esc_html_e( 'Add a date', 'aaraa-white-label-admin' ); ?></strong></label>
						<span class="aaraa-subdel__addrow">
							<input type="date" id="aaraa_pause_date" min="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>" />
							<button type="button" class="button" id="aaraa_pause_add"><?php esc_html_e( 'Add', 'aaraa-white-label-admin' ); ?></button>
						</span>
					</p>
					<ul class="aaraa-subdel__chips" id="aaraa_pause_chips"></ul>
					<p class="description aaraa-subdel__empty" id="aaraa_pause_none">
						<?php esc_html_e( 'No dates selected yet.', 'aaraa-white-label-admin' ); ?>
					</p>
					<p class="description aaraa-subdel__warn" id="aaraa_pause_gap" style="display:none;"></p>
				</div>

				<div class="aaraa-subdel__mode" data-mode="range" style="display:none;">
					<p>
						<label for="aaraa_sub_pause_from"><strong><?php esc_html_e( 'Pause from', 'aaraa-white-label-admin' ); ?></strong></label>
						<input type="date" id="aaraa_sub_pause_from" min="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>" style="width:100%" />
					</p>
					<p>
						<label for="aaraa_sub_pause_to"><strong><?php esc_html_e( 'Pause to', 'aaraa-white-label-admin' ); ?></strong></label>
						<input type="date" id="aaraa_sub_pause_to" min="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>" style="width:100%" />
						<span class="description"><?php esc_html_e( 'Inclusive. Deliveries restart the next day.', 'aaraa-white-label-admin' ); ?></span>
					</p>
				</div>

				<p>
					<button type="button" class="button button-primary" id="aaraa_sub_pause">
						<?php esc_html_e( 'Pause', 'aaraa-white-label-admin' ); ?>
					</button>
					<span class="spinner aaraa-subdel__spinner" style="float:none;margin:0 0 0 6px;"></span>
				</p>

				<?php
				// Upcoming pause dates only (today onward) — past dates are kept in
				// the meta for history but aren't "scheduled" any more.
				$scheduled = array_values(
					array_filter(
						$this->saved_pause_dates( $sub_id ),
						static function ( $d ) use ( $today ) {
							return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d ) && $d >= $today;
						}
					)
				);
				sort( $scheduled );
				?>
				<?php if ( $scheduled ) : ?>
					<div class="aaraa-subdel__saved">
						<strong>
							<?php
							printf(
								/* translators: %d: number of scheduled pause dates */
								esc_html__( 'Scheduled pause dates (%d)', 'aaraa-white-label-admin' ),
								count( $scheduled )
							);
							?>
						</strong>
						<ul>
							<?php foreach ( $scheduled as $d ) : ?>
								<li><?php echo esc_html( $d ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

			<?php endif; ?>

			<p class="aaraa-subdel__msg" id="aaraa_sub_pause_msg" role="status" aria-live="polite"></p>
		</div>

		<style>
			.aaraa-subdel__head { margin: 0 0 8px; font-size: 13px; }
			.aaraa-subdel__radio { display: block; margin-bottom: 6px; }
			.aaraa-subdel__days { margin: 4px 0 10px; padding: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px; }
			.aaraa-subdel__day { display: inline-block; margin: 4px 10px 0 0; }
			.aaraa-subdel__msg { margin: 6px 0 0; }
			.aaraa-subdel__msg.ok { color: #15803D; }
			.aaraa-subdel__msg.error { color: #B91C1C; }
			.aaraa-subdel__paused { padding: 8px; background: #FEF3C7; border-radius: 4px; }
			.aaraa-subdel__tabs { display: flex; margin-bottom: 10px; }
			.aaraa-subdel__tab { flex: 1; padding: 7px 4px; border: 1px solid #C3C4C7; background: #fff; cursor: pointer; font-size: 12px; }
			.aaraa-subdel__tab:first-child { border-radius: 3px 0 0 3px; }
			.aaraa-subdel__tab:last-child { border-left: 0; border-radius: 0 3px 3px 0; }
			.aaraa-subdel__tab.is-active { background: #1E3A8A; border-color: #1E3A8A; color: #fff; font-weight: 600; }
			.aaraa-subdel__addrow { display: flex; gap: 6px; }
			.aaraa-subdel__addrow input { flex: 1; min-width: 0; }
			.aaraa-subdel__chips { margin: 8px 0 0; padding: 0; list-style: none; }
			.aaraa-subdel__chips li { display: inline-flex; align-items: center; gap: 5px; margin: 0 5px 5px 0; padding: 3px 6px; background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 3px; font-size: 12px; }
			.aaraa-subdel__chips button { border: 0; background: none; cursor: pointer; color: #B91C1C; font-size: 14px; line-height: 1; padding: 0; }
			.aaraa-subdel__warn { color: #B45309; }
			.aaraa-subdel__saved { margin: 10px 0 0; padding: 8px 10px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 4px; }
			.aaraa-subdel__saved strong { display: block; font-size: 12px; margin-bottom: 4px; color: #166534; }
			.aaraa-subdel__saved ul { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 5px; }
			.aaraa-subdel__saved li { padding: 2px 7px; background: #DCFCE7; border: 1px solid #86EFAC; border-radius: 3px; font-size: 12px; }
		</style>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * AJAX.
	 * --------------------------------------------------------------------- */

	/**
	 * Shared guard: verify the nonce, capability and subscription.
	 *
	 * @return \WC_Subscription Dies with a JSON error when not permitted.
	 */
	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'aaraa-white-label-admin' ) ) );
		}
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce Subscriptions is not active.', 'aaraa-white-label-admin' ) ) );
		}

		$sub_id       = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
		$subscription = $sub_id ? wcs_get_subscription( $sub_id ) : false;
		if ( ! $subscription ) {
			wp_send_json_error( array( 'message' => __( 'Subscription not found.', 'aaraa-white-label-admin' ) ) );
		}

		return $subscription;
	}

	/**
	 * AJAX: save the delivery schedule.
	 *
	 * @return void
	 */
	public function ajax_schedule() {
		$subscription = $this->guard();
		$sub_id       = $subscription->get_id();

		$schedule = isset( $_POST['schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule'] ) ) : '';
		if ( ! array_key_exists( $schedule, $this->schedule_types() ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a schedule type.', 'aaraa-white-label-admin' ) ) );
		}

		// Weekend is stored as Sat+Sun so the renewal date calculator, which only
		// looks at _wcfm_delivery_days, treats it the same as WCFM Ultimate does.
		if ( 'weekend' === $schedule ) {
			$days = array( 0, 6 );
		} elseif ( 'custom' === $schedule ) {
			$raw  = isset( $_POST['days'] ) ? (array) wp_unslash( $_POST['days'] ) : array();
			$days = array();
			foreach ( $raw as $day ) {
				$day = absint( $day );
				if ( $day <= 6 ) {
					$days[] = $day;
				}
			}
			$days = array_values( array_unique( $days ) );
			if ( empty( $days ) ) {
				wp_send_json_error( array( 'message' => __( 'Select at least one delivery day.', 'aaraa-white-label-admin' ) ) );
			}
		} else {
			$days = array();
		}

		// Delivery slot — 0/empty clears it. Validate against active slots so a
		// stale or bogus id can't be stored.
		$slot_id       = isset( $_POST['slot'] ) ? absint( $_POST['slot'] ) : 0;
		$slot_previous = (int) get_post_meta( $sub_id, self::META_SLOT, true );
		if ( $slot_id ) {
			$valid_ids = wp_list_pluck( $this->available_slots(), 'id' );
			if ( ! in_array( $slot_id, array_map( 'intval', $valid_ids ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'That delivery slot is not available.', 'aaraa-white-label-admin' ) ) );
			}
		}

		$previous = (string) get_post_meta( $sub_id, self::META_SCHEDULE, true );

		// Write through the CRUD object (canonical/HPOS store, and what the
		// wc/v3 response + app read) and via post_meta (legacy readers) so both
		// stores stay in sync under HPOS.
		$subscription->update_meta_data( self::META_SCHEDULE, $schedule );
		update_post_meta( $sub_id, self::META_SCHEDULE, $schedule );
		if ( ! empty( $days ) ) {
			$subscription->update_meta_data( self::META_DAYS, $days );
			update_post_meta( $sub_id, self::META_DAYS, $days );
		} else {
			$subscription->delete_meta_data( self::META_DAYS );
			delete_post_meta( $sub_id, self::META_DAYS );
		}

		if ( $slot_id ) {
			$subscription->update_meta_data( self::META_SLOT, $slot_id );
			update_post_meta( $sub_id, self::META_SLOT, $slot_id );
		} else {
			$subscription->delete_meta_data( self::META_SLOT );
			delete_post_meta( $sub_id, self::META_SLOT );
		}
		$subscription->save();
		if ( $slot_previous !== $slot_id ) {
			$slot_label = $slot_id ? $this->slot_label( $slot_id ) : __( 'No specific slot', 'aaraa-white-label-admin' );
			$subscription->add_order_note(
				sprintf(
					/* translators: %s: delivery slot label */
					__( 'Delivery slot set to %s (via admin).', 'aaraa-white-label-admin' ),
					$slot_label
				)
			);
		}

		if ( $previous !== $schedule || 'custom' === $schedule ) {
			$subscription->add_order_note(
				sprintf(
					/* translators: %s: schedule label */
					__( 'Delivery schedule set to %s (via admin).', 'aaraa-white-label-admin' ),
					$this->schedule_label( $schedule, $days )
				)
			);
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Delivery schedule updated.', 'aaraa-white-label-admin' ),
				'schedule' => $schedule,
				'days'     => $days,
				'slot'     => $slot_id,
			)
		);
	}

	/**
	 * AJAX: set the subscription's next renewal (next payment) date. Available to
	 * shop owners as well as admins (the shared guard allows edit_shop_orders /
	 * manage_woocommerce), so shop owners get the same edit ability as admins.
	 *
	 * @return void
	 */
	public function ajax_next_payment() {
		$subscription = $this->guard();
		$sub_id       = $subscription->get_id();

		if ( in_array( $subscription->get_status(), array( 'cancelled', 'expired', 'switched' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'The next renewal date cannot be set on a cancelled, expired or switched subscription.', 'aaraa-white-label-admin' ) ) );
		}

		// Input is a site-local 'Y-m-dTH:i' (datetime-local) or 'Y-m-d H:i[:s]'.
		$raw = isset( $_POST['next_payment'] ) ? sanitize_text_field( wp_unslash( $_POST['next_payment'] ) ) : '';
		$raw = trim( str_replace( 'T', ' ', $raw ) );
		if ( '' === $raw || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid date and time.', 'aaraa-white-label-admin' ) ) );
		}
		if ( 16 === strlen( $raw ) ) {
			$raw .= ':00';
		}

		try {
			// Convert the site-local input to a UTC MySQL string for WCS.
			$local = new \DateTime( $raw, wp_timezone() );
			if ( $local->getTimestamp() <= time() ) {
				wp_send_json_error( array( 'message' => __( 'The next renewal date must be in the future.', 'aaraa-white-label-admin' ) ) );
			}
			$utc = $local->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

			$subscription->update_dates( array( 'next_payment' => $utc ), 'gmt' );
			$subscription->save();

			$subscription->add_order_note(
				sprintf(
					/* translators: %s: new next renewal date (site time). */
					__( 'Next renewal date changed to %s (via Aaraa panel).', 'aaraa-white-label-admin' ),
					$subscription->get_date_to_display( 'next_payment' )
				)
			);

			wp_send_json_success(
				array(
					'message'      => __( 'Next renewal date updated.', 'aaraa-white-label-admin' ),
					'next_payment' => $subscription->get_date_to_display( 'next_payment' ),
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: pause the subscription over a date range.
	 *
	 * @return void
	 */
	public function ajax_pause() {
		$subscription = $this->guard();
		$sub_id       = $subscription->get_id();

		if ( ! $this->pause_status_available() ) {
			wp_send_json_error( array( 'message' => __( 'The Paused status is not registered — enable the WCFM Ultimate subscriptions module.', 'aaraa-white-label-admin' ) ) );
		}
		if ( in_array( $subscription->get_status(), array( 'cancelled', 'expired' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'A cancelled or expired subscription cannot be paused.', 'aaraa-white-label-admin' ) ) );
		}

		$dates  = self::resolve_pause_dates();
		$result = self::apply_pause( $subscription, $dates, 'admin' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: number of days, 2: resume date */
					__( 'Paused for %1$d day(s). Resumes %2$s.', 'aaraa-white-label-admin' ),
					$result['count'],
					$result['resume']
				),
				'reload'  => true,
			)
		);
	}

	/**
	 * Pause a subscription over an explicit list of dates.
	 *
	 * Shared by the admin metabox and the customer My Account panel so both
	 * behave identically: it writes the same pause meta WCFM Ultimate reads,
	 * flips the status to `pause`, and arms the same auto-resume cron event.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param string[]         $dates        Sorted, validated Y-m-d dates.
	 * @param string           $source       'admin' | 'customer' (for the note).
	 * @return array{resume:string,count:int}|\WP_Error
	 */
	public static function apply_pause( $subscription, array $dates, $source = 'admin' ) {
		if ( empty( $dates ) ) {
			return new \WP_Error( 'no_dates', __( 'No dates to pause.', 'aaraa-white-label-admin' ) );
		}

		// Cutoff: refuse a date whose renewal time has already passed.
		if ( class_exists( __NAMESPACE__ . '\\Subscription_API' ) ) {
			$cutoff_err = Subscription_API::pause_cutoff_error( $subscription, $dates );
			if ( is_wp_error( $cutoff_err ) ) {
				return $cutoff_err;
			}
		}

		$sub_id = $subscription->get_id();
		$dates  = array_values( $dates );
		$resume = gmdate( 'Y-m-d', strtotime( end( $dates ) . ' +1 day' ) );

		update_post_meta( $sub_id, self::META_PAUSE, wp_json_encode( $dates ) );
		update_post_meta( $sub_id, self::META_RESUME, $resume );

		// Delegate to WCFM Ultimate's date-scoped engine so the subscription is
		// paused only on the chosen dates (delivering on any gap days) and the
		// per-date boundary events are armed — exactly the same behaviour as the
		// admin AJAX and the mobile API. Applies today's state immediately.
		try {
			global $WCFMu;
			if ( isset( $WCFMu->wcfmu_wcsubscriptions ) && method_exists( $WCFMu->wcfmu_wcsubscriptions, 'sync_pause_status' ) ) {
				$WCFMu->wcfmu_wcsubscriptions->schedule_pause_sync( $sub_id, $dates );
				$WCFMu->wcfmu_wcsubscriptions->sync_pause_status( $sub_id );
			} else {
				// Fallback (WCFMu engine unavailable): pause now + single resume cron.
				$subscription->update_status( 'pause' );
				wp_clear_scheduled_hook( self::CRON_RESUME, array( $sub_id ) );
				wp_schedule_single_event( strtotime( $resume . ' 00:01:00 UTC' ), self::CRON_RESUME, array( $sub_id ) );
			}
		} catch ( \Exception $e ) {
			delete_post_meta( $sub_id, self::META_PAUSE );
			delete_post_meta( $sub_id, self::META_RESUME );
			return new \WP_Error( 'pause_failed', $e->getMessage() );
		}

		$via = ( 'customer' === $source )
			? __( 'by customer', 'aaraa-white-label-admin' )
			: __( 'via admin', 'aaraa-white-label-admin' );

		$subscription->add_order_note(
			sprintf(
				/* translators: 1: paused blocks with their resume dates, 2: source */
				__( 'Paused: %1$s (%2$s).', 'aaraa-white-label-admin' ),
				self::describe_pause_schedule( $dates ),
				$via
			)
		);

		return array( 'resume' => $resume, 'count' => count( $dates ) );
	}

	/**
	 * Describe a pause selection as contiguous blocks each with its own resume
	 * date, e.g. "2026-08-05 (resumes 2026-08-06); 2026-08-11 (resumes
	 * 2026-08-12)". Scattered dates each resume the next day; a range resumes the
	 * day after its last date.
	 *
	 * @param string[] $dates Y-m-d dates.
	 * @return string
	 */
	public static function describe_pause_schedule( array $dates ) {
		$dates = array_values( array_unique( array_filter( $dates ) ) );
		sort( $dates );
		if ( empty( $dates ) ) {
			return '';
		}

		$blocks = array();
		$start  = $dates[0];
		$prev   = $dates[0];
		for ( $i = 1, $n = count( $dates ); $i < $n; $i++ ) {
			$expected = gmdate( 'Y-m-d', strtotime( $prev . ' +1 day' ) );
			if ( $dates[ $i ] !== $expected ) {
				$blocks[] = array( $start, $prev );
				$start    = $dates[ $i ];
			}
			$prev = $dates[ $i ];
		}
		$blocks[] = array( $start, $prev );

		$parts = array();
		foreach ( $blocks as $block ) {
			$label  = ( $block[0] === $block[1] ) ? $block[0] : ( $block[0] . ' to ' . $block[1] );
			$resume = gmdate( 'Y-m-d', strtotime( $block[1] . ' +1 day' ) );
			/* translators: 1: date or range, 2: resume date */
			$parts[] = sprintf( __( '%1$s (resumes %2$s)', 'aaraa-white-label-admin' ), $label, $resume );
		}

		return implode( '; ', $parts );
	}

	/**
	 * Resolve the posted pause selection into a validated list of Y-m-d dates.
	 *
	 * Mirrors the two modes of the WCFM Ultimate modal: `dates` sends the exact
	 * days picked, `range` sends endpoints that get expanded here. Either way the
	 * stored value is a flat list of days, which is what WCFMu stores too — so
	 * both screens read and write the same shape.
	 *
	 * Sends a JSON error and exits when the selection is unusable.
	 *
	 * @return string[] Sorted, unique, future-only dates.
	 */
	public static function resolve_pause_dates() {
		// phpcs:disable WordPress.Security.NonceVerification -- guard() ran first.
		$mode  = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'range';
		$today = current_time( 'Y-m-d' );
		$dates = array();

		if ( 'dates' === $mode ) {

			$raw = isset( $_POST['dates'] ) ? (array) wp_unslash( $_POST['dates'] ) : array();
			foreach ( $raw as $date ) {
				$date = sanitize_text_field( $date );
				if ( ! self::is_date( $date ) ) {
					continue;
				}
				// Silently dropping past dates would pause days the customer did
				// not ask for, so reject the whole submission instead.
				if ( $date < $today ) {
					wp_send_json_error(
						array(
							'message' => sprintf(
								/* translators: %s: date */
								__( '%s is in the past — remove it and try again.', 'aaraa-white-label-admin' ),
								$date
							),
						)
					);
				}
				$dates[] = $date;
			}

			if ( empty( $dates ) ) {
				wp_send_json_error( array( 'message' => __( 'Select at least one date to pause.', 'aaraa-white-label-admin' ) ) );
			}

			$dates = array_values( array_unique( $dates ) );
			sort( $dates );

		} else {

			$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
			$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';

			if ( ! self::is_date( $from ) ) {
				wp_send_json_error( array( 'message' => __( 'Enter a valid start date.', 'aaraa-white-label-admin' ) ) );
			}
			if ( ! self::is_date( $to ) ) {
				$to = $from;
			}
			if ( $to < $from ) {
				wp_send_json_error( array( 'message' => __( 'The end date is before the start date.', 'aaraa-white-label-admin' ) ) );
			}
			if ( $to < $today ) {
				wp_send_json_error( array( 'message' => __( 'That date range is in the past.', 'aaraa-white-label-admin' ) ) );
			}
			// A range that starts in the past is trimmed rather than rejected —
			// "pause from the 1st" mid-month plainly means "from now on".
			if ( $from < $today ) {
				$from = $today;
			}

			$cursor = $from;
			while ( $cursor <= $to && count( $dates ) < self::MAX_PAUSE_DAYS ) {
				$dates[] = $cursor;
				$cursor  = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
			}
			if ( $cursor <= $to ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %d: maximum days */
							__( 'A pause cannot be longer than %d days.', 'aaraa-white-label-admin' ),
							self::MAX_PAUSE_DAYS
						),
					)
				);
			}
		}

		if ( count( $dates ) > self::MAX_PAUSE_DAYS ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: maximum days */
						__( 'A pause cannot be longer than %d days.', 'aaraa-white-label-admin' ),
						self::MAX_PAUSE_DAYS
					),
				)
			);
		}
		// phpcs:enable

		return $dates;
	}

	/**
	 * AJAX: resume immediately.
	 *
	 * @return void
	 */
	public function ajax_resume() {
		$subscription = $this->guard();

		$message = self::resume_subscription( $subscription, 'admin' );

		wp_send_json_success(
			array(
				'message' => $message,
				'reload'  => true,
			)
		);
	}

	/**
	 * Clear a subscription's pause schedule (meta + all pause cron events)
	 * without changing its status. Used to cancel a future pause that has not
	 * started yet.
	 *
	 * @param int $sub_id Subscription id.
	 * @return void
	 */
	public static function clear_pause_schedule( $sub_id ) {
		// Explicit cancel of a scheduled pause — remove the dates outright.
		delete_post_meta( $sub_id, self::META_PAUSE );
		delete_post_meta( $sub_id, self::META_RESUME );
		wp_clear_scheduled_hook( self::CRON_RESUME, array( $sub_id ) );
		wp_clear_scheduled_hook( 'wcfmu_begin_pause_subscription', array( $sub_id ) );
		wp_clear_scheduled_hook( 'wcfmu_sync_pause_subscription', array( $sub_id ) );
	}

	/**
	 * Resume a paused subscription now.
	 *
	 * Shared by the admin metabox and the customer My Account panel. Delegates to
	 * WCFM Ultimate's auto_resume_subscription() so the wallet-balance rule that
	 * decides active vs on-hold lives in exactly one place.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param string           $source       'admin' | 'customer' (for the note).
	 * @return string Result message.
	 */
	public static function resume_subscription( $subscription, $source = 'admin' ) {
		$sub_id = $subscription->get_id();

		wp_clear_scheduled_hook( self::CRON_RESUME, array( $sub_id ) );
		wp_clear_scheduled_hook( 'wcfmu_begin_pause_subscription', array( $sub_id ) );
		wp_clear_scheduled_hook( 'wcfmu_sync_pause_subscription', array( $sub_id ) );

		global $WCFMu;
		if ( isset( $WCFMu->wcfmu_wcsubscriptions ) && method_exists( $WCFMu->wcfmu_wcsubscriptions, 'auto_resume_subscription' ) ) {
			$result  = (array) $WCFMu->wcfmu_wcsubscriptions->auto_resume_subscription( $sub_id );
			$message = isset( $result['message'] ) ? $result['message'] : __( 'Subscription resumed.', 'aaraa-white-label-admin' );
		} else {
			$subscription->update_status( 'active' );
			aaraa_retain_future_pause_dates( $sub_id ); // end current pause; keep upcoming pause dates
			delete_post_meta( $sub_id, self::META_RESUME );
			$message = __( 'Subscription resumed.', 'aaraa-white-label-admin' );
		}

		$via = ( 'customer' === $source )
			? __( 'by customer', 'aaraa-white-label-admin' )
			: __( 'via admin', 'aaraa-white-label-admin' );

		$subscription->add_order_note(
			sprintf(
				/* translators: %s: source (via admin / by customer) */
				__( 'Resumed from pause (%s).', 'aaraa-white-label-admin' ),
				$via
			)
		);

		return $message;
	}

	/* --------------------------------------------------------------------- *
	 * Helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * Strict Y-m-d check that also rejects impossible dates like 2026-02-31.
	 *
	 * @param string $date Candidate.
	 * @return bool
	 */
	public static function is_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$parts = explode( '-', $date );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}

	/**
	 * Describe a set of paused days for an order note.
	 *
	 * A contiguous run reads as a range; anything with gaps is listed in full,
	 * because "1 Aug to 20 Aug" would misdescribe two scattered days.
	 *
	 * @param string[] $dates Sorted dates.
	 * @return string
	 */
	public static function describe_dates( $dates ) {
		$count = count( $dates );
		if ( 1 === $count ) {
			return $dates[0];
		}

		$span = 1 + (int) round( ( strtotime( end( $dates ) ) - strtotime( reset( $dates ) ) ) / DAY_IN_SECONDS );
		if ( $span === $count ) {
			return sprintf(
				/* translators: 1: first date, 2: last date, 3: number of days */
				__( '%1$s to %2$s (%3$d days)', 'aaraa-white-label-admin' ),
				reset( $dates ),
				end( $dates ),
				$count
			);
		}

		return implode( ', ', $dates );
	}

	/**
	 * Readable label for a schedule, used in order notes.
	 *
	 * @param string $schedule Schedule key.
	 * @param int[]  $days     Custom days.
	 * @return string
	 */
	private function schedule_label( $schedule, $days ) {
		$types = $this->schedule_types();
		$label = isset( $types[ $schedule ] ) ? $types[ $schedule ] : $schedule;

		if ( 'custom' === $schedule && ! empty( $days ) ) {
			$names = array();
			foreach ( $this->weekdays() as $num => $name ) {
				if ( in_array( $num, $days, true ) ) {
					$names[] = $name;
				}
			}
			$label .= ' (' . implode( ', ', $names ) . ')';
		}

		return $label;
	}
}
