<?php
/**
 * Editable Start Date for a subscription.
 *
 * WooCommerce Subscriptions locks the start date once a subscription leaves the
 * pending / auto-draft state (`can_date_be_updated( 'start' )` returns false), so
 * its Schedule box only shows the start as read-only text. This adds a small
 * "Start Date" meta box on the subscription edit screen with an editable
 * date+time field, saved through the subscription's own update_dates() so the
 * schedule stays consistent.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Start-date editor meta box for subscriptions.
 */
class Subscription_Start_Date {

	const NONCE = 'aaraa_sub_start';
	const FIELD = 'aaraa_start_datetime';

	/**
	 * Subscription ids queued for a final parent-order date alignment at shutdown.
	 *
	 * @var array<int,bool>
	 */
	private static $align_queue = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 45 );
		// Fires on subscription save for both HPOS and the legacy post editor.
		add_action( 'woocommerce_process_shop_subscription_meta', array( $this, 'save' ), 30, 2 );
		// Keep the parent order's date aligned to the subscription start date
		// (WCS stamps the parent with "now" even for a future-dated start). Runs
		// late so it sees the final start value from whichever path set it.
		add_action( 'woocommerce_process_shop_subscription_meta', array( $this, 'align_parent_order_date' ), 40, 2 );
		// Safety net: a future-dated (pending) parent order makes the subscription's
		// derived "last payment date" fall in the future, which otherwise blocks
		// cancel/trash/delete (WCS requires cancelled/end to be after last payment).
		// Clamp a future value down so those operations keep working. Only affects
		// the future case — normal subscriptions never have a future last order.
		add_filter( 'woocommerce_subscription_get_last_order_date_created_date', array( $this, 'clamp_future_last_order_date' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
		// AJAX save from the meta box's own button (no full form submit).
		add_action( 'wp_ajax_aaraa_save_sub_start', array( $this, 'ajax_save' ) );
		// Allow a future date in WooCommerce Subscriptions' native Schedule
		// datepicker (it otherwise caps the start date at today). Runs after WCS
		// enqueues its meta-box script so the inline override is attached to it.
		add_action( 'admin_enqueue_scripts', array( $this, 'unlock_native_start_picker' ), 99 );
	}

	/**
	 * Let the native "Start Date" schedule picker accept future dates.
	 *
	 * WooCommerce Subscriptions' meta-boxes-subscription.js does two things that
	 * block a future start date on the Add/Edit Subscription screen:
	 *   1. sets the start datepicker's `maxDate` to today, and
	 *   2. on change, force-resets any start date that is not before "now" back
	 *      to the current time.
	 * The server only validates relative date ordering (trial/next/end must be
	 * after start), not that start is in the past, so a future start saves fine.
	 * This inline override — attached to WCS's own script handle, so it runs
	 * right after and only on the subscription screen — clears `maxDate` and
	 * rebinds the start field's change handler to keep WCS's UTC-timestamp sync
	 * while dropping the "must be in the past" reset. Update-safe (no WCS edits).
	 *
	 * @return void
	 */
	public function unlock_native_start_picker() {
		$handle = 'wcs-admin-meta-boxes-subscription';
		if ( ! wp_script_is( $handle, 'enqueued' ) && ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		$js = <<<'JS'
jQuery( function ( $ ) {
	var $start = $( '.woocommerce-subscriptions.date-picker#start' );
	if ( ! $start.length || 'undefined' === typeof moment ) { return; }

	// 1) Allow future dates in the calendar (WCS caps maxDate at today).
	try { $start.datepicker( 'option', 'maxDate', null ); } catch ( e ) {}

	// 2) Replace only the start field's change handler: keep the UTC-timestamp
	//    sync WCS relies on for saving, but drop its "reset to now" for a future
	//    date. Other date fields (trial end / next payment / end) are untouched.
	$start.off( 'change' ).on( 'change', function () {
		var type  = $( this ).attr( 'id' ),
			$hour = $( '#' + type + '_hour' ),
			$min  = $( '#' + type + '_minute' ),
			$ts   = $( '#' + type + '_timestamp_utc' );

		// Cleared date -> clear time + timestamp, matching WCS.
		if ( '' === $( this ).val() ) {
			$hour.val( '' ); $min.val( '' ); $ts.val( 0 );
			$( 'body' ).trigger( 'wcs-updated-date', type );
			return;
		}

		var oneHour = moment().add( 1, 'hours' );
		if ( 0 === $hour.val().length ) { $hour.val( oneHour.format( 'HH' ) ); }
		if ( 0 === $min.val().length )  { $min.val( oneHour.format( 'mm' ) ); }

		var p = $( this ).val().split( '-' );
		var stamp = moment( {
			years:   p[ 0 ],
			months:  p[ 1 ] - 1,
			date:    p[ 2 ],
			hours:   $hour.val(),
			minutes: $min.val(),
			seconds: oneHour.format( 'ss' )
		} ).utc().unix();

		// NaN becomes the string "NaN", which WCS's submit guard catches.
		$ts.val( stamp );
		$( 'body' ).trigger( 'wcs-updated-date', type );
	} );
} );
JS;

		wp_add_inline_script( $handle, $js );
	}

	/**
	 * Register the "Start Date" meta box on the subscription edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box( $post_type = '', $post_or_order = null ) {
		// Skip our box while WCS still lets the native Schedule picker edit the
		// start (creation / pending) — the native picker, with our future-date
		// unlock, handles it there. Our box is for existing subscriptions where
		// WCS locks the native start to read-only text. Only hide when we can
		// positively confirm the native start is still editable.
		$sub_id = is_a( $post_or_order, 'WC_Order' ) ? $post_or_order->get_id() : ( isset( $post_or_order->ID ) ? (int) $post_or_order->ID : 0 );
		if ( $sub_id && function_exists( 'wcs_get_subscription' ) ) {
			$sub = wcs_get_subscription( $sub_id );
			if ( $sub && $sub->can_date_be_updated( 'start' ) ) {
				return;
			}
		}

		$screens = array( 'shop_subscription' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-subscription' );
		}
		foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
			add_meta_box(
				'aaraa-subscription-start',
				__( 'Start Date', 'aaraa-white-label-admin' ),
				array( $this, 'render' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Meta box body: an editable date+time field pre-filled with the current start.
	 *
	 * @param \WP_Post|\WC_Order $post_or_object Subscription (HPOS) or post (CPT).
	 * @return void
	 */
	public function render( $post_or_object ) {
		$sub_id = is_a( $post_or_object, 'WC_Order' ) ? $post_or_object->get_id() : ( isset( $post_or_object->ID ) ? (int) $post_or_object->ID : 0 );
		$sub    = $sub_id && function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : null;
		if ( ! $sub ) {
			echo '<p>' . esc_html__( 'Subscription not available.', 'aaraa-white-label-admin' ) . '</p>';
			return;
		}

		$start_gmt = $sub->get_date( 'start' ); // GMT 'Y-m-d H:i:s' or ''.
		$value     = $start_gmt ? get_date_from_gmt( $start_gmt, 'Y-m-d\TH:i' ) : '';

		wp_nonce_field( self::NONCE, 'aaraa_start_nonce', false );
		?>
		<p style="margin:6px 0;">
			<label for="<?php echo esc_attr( self::FIELD ); ?>"><strong><?php esc_html_e( 'Subscription start date &amp; time', 'aaraa-white-label-admin' ); ?></strong></label>
		</p>
		<input type="datetime-local" id="<?php echo esc_attr( self::FIELD ); ?>" name="<?php echo esc_attr( self::FIELD ); ?>" value="<?php echo esc_attr( $value ); ?>" style="width:100%;" />
		<p style="margin:10px 0 0;">
			<?php // type="button" — must NOT submit the surrounding subscription form. ?>
			<button type="button" class="button button-primary" id="aaraa_start_save"><?php esc_html_e( 'Save Start Date', 'aaraa-white-label-admin' ); ?></button>
			<span class="spinner" id="aaraa_start_spinner" style="float:none;margin:0 0 0 6px;"></span>
		</p>
		<p id="aaraa_start_msg" role="status" aria-live="polite" style="margin:6px 0 0;"></p>
		<p class="description" style="margin-top:8px;">
			<?php esc_html_e( 'Saved in the store timezone. If the new start is on/after the next payment, the next payment is moved forward one billing cycle to keep the schedule valid.', 'aaraa-white-label-admin' ); ?>
		</p>
		<script>
		( function () {
			var btn = document.getElementById( 'aaraa_start_save' );
			if ( ! btn || btn.dataset.bound ) { return; }
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', function () {
				var input = document.getElementById( '<?php echo esc_js( self::FIELD ); ?>' );
				var nonce = document.querySelector( 'input[name="aaraa_start_nonce"]' );
				var msg   = document.getElementById( 'aaraa_start_msg' );
				var sp    = document.getElementById( 'aaraa_start_spinner' );
				if ( ! input || ! nonce ) { return; }
				sp.classList.add( 'is-active' );
				btn.disabled = true;
				msg.textContent = '';
				var body = new URLSearchParams();
				body.append( 'action', 'aaraa_save_sub_start' );
				body.append( 'order_id', '<?php echo (int) $sub_id; ?>' );
				body.append( 'nonce', nonce.value );
				body.append( 'start', input.value );
				fetch( ( window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>' ), {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
					sp.classList.remove( 'is-active' );
					btn.disabled = false;
					if ( res && res.success ) {
						msg.style.color = '#15803d';
						msg.textContent = ( res.data && res.data.message ) || 'Saved.';
						setTimeout( function () { location.reload(); }, 800 );
					} else {
						msg.style.color = '#b91c1c';
						msg.textContent = ( res && res.data && res.data.message ) || 'Could not save the start date.';
					}
				} ).catch( function () {
					sp.classList.remove( 'is-active' );
					btn.disabled = false;
					msg.style.color = '#b91c1c';
					msg.textContent = 'Request failed. Please try again.';
				} );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Persist an edited start date through the subscription's schedule.
	 *
	 * @param int   $sub_id       Subscription id.
	 * @param mixed $post_or_order Post (legacy) or WC_Subscription (HPOS).
	 * @return void
	 */
	public function save( $sub_id, $post_or_order = null ) {
		if ( ! isset( $_POST['aaraa_start_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aaraa_start_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_subscriptions' ) ) {
			return;
		}

		// During creation, WooCommerce Subscriptions renders the native Schedule
		// start date as an editable picker and submits `start_timestamp_utc`. In
		// that case let the native value win — our box's field was only pre-filled
		// with the created date, so saving it here would clobber a future start
		// the user just picked. Our box is meant for existing subscriptions, where
		// WCS locks the native start to read-only text (no start_timestamp_utc).
		$native_start = isset( $_POST['start_timestamp_utc'] ) ? trim( (string) wp_unslash( $_POST['start_timestamp_utc'] ) ) : '';
		if ( '' !== $native_start && '0' !== $native_start ) {
			return;
		}

		$raw = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
		$sub = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : null;
		if ( '' === $raw || ! $sub ) {
			return;
		}

		$result = $this->apply_start( $sub, $raw );
		if ( ! $result['ok'] && '' !== $result['message'] ) {
			set_transient( 'aaraa_start_err_' . get_current_user_id(), $result['message'], 60 );
		}
	}

	/**
	 * Align the parent order's creation date with the subscription start date.
	 *
	 * When a subscription is created with a future start date, WooCommerce
	 * Subscriptions still stamps the parent order with the current time. This
	 * moves the parent order's `date_created` to the subscription's start date so
	 * the initial order sits on the day service actually begins.
	 *
	 * @param int   $sub_id        Subscription id.
	 * @param mixed $post_or_order Post (legacy) or WC_Subscription (HPOS).
	 * @return void
	 */
	public function align_parent_order_date( $sub_id, $post_or_order = null ) {
		$sub_id = (int) $sub_id;
		if ( $sub_id <= 0 || ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}
		$sub = wcs_get_subscription( $sub_id );
		if ( ! $sub ) {
			return;
		}

		// Capture the start WCS has stored at this point — priority 40 runs after
		// the native Schedule save (priority 10), so this is the value the admin
		// actually submitted, before WCS reverts a future start later in the flow.
		// Prefer whichever is further in the future between the stored start and
		// the explicitly posted timestamp.
		$captured = (int) $sub->get_time( 'start', 'gmt' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted = isset( $_POST['start_timestamp_utc'] ) ? (int) wp_unslash( $_POST['start_timestamp_utc'] ) : 0;
		$target = max( $captured, $posted );
		if ( $target <= 0 ) {
			return;
		}

		// Enforce now (covers the normal case), then again at shutdown — the last
		// thing in the request — so a later revert is overwritten with the target.
		$this->do_align_parent( $sub_id, $target );

		if ( empty( self::$align_queue ) ) {
			add_action( 'shutdown', array( $this, 'run_shutdown_alignment' ), 5 );
		}
		$prev = isset( self::$align_queue[ $sub_id ] ) ? (int) self::$align_queue[ $sub_id ] : 0;
		self::$align_queue[ $sub_id ] = max( $target, $prev );
	}

	/**
	 * Final start / creation / parent-order alignment, run last in the request.
	 *
	 * @return void
	 */
	public function run_shutdown_alignment() {
		foreach ( self::$align_queue as $sub_id => $target ) {
			$this->do_align_parent( (int) $sub_id, (int) $target );
		}
		self::$align_queue = array();
	}

	/**
	 * Force a subscription's creation date, start date and parent order date to
	 * the same value.
	 *
	 * For a future-dated subscription these three should all sit on the same day.
	 * WooCommerce keeps them as separate values (creation date, the `_schedule_start`
	 * date, and the parent order's own date), and can revert a future start, so
	 * this re-asserts all three to the target. Reads fresh, so it is safe to call
	 * more than once per request; leaves a subscription without a parent order's
	 * dates aligned but simply skips the order step.
	 *
	 * @param int $sub_id    Subscription id.
	 * @param int $target_ts Target date (GMT timestamp); 0 to use the stored start.
	 * @return void
	 */
	private function do_align_parent( $sub_id, $target_ts = 0 ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}
		$sub = wcs_get_subscription( $sub_id );
		if ( ! $sub ) {
			return;
		}

		$target_ts = (int) $target_ts;
		if ( $target_ts <= 0 ) {
			$target_ts = (int) $sub->get_time( 'start', 'gmt' );
		}
		if ( $target_ts <= 0 ) {
			return;
		}

		// Re-assert the subscription's creation date and start date together when
		// either has drifted from the target (WCS can revert a future start). If
		// WCS refuses (e.g. it would break date ordering), fall back to the stored
		// start so nothing is left inconsistent.
		$created  = $sub->get_date_created();
		$drifted  = ( (int) $sub->get_time( 'start', 'gmt' ) !== $target_ts )
			|| ( $created && (int) $created->getTimestamp() !== $target_ts );
		if ( $drifted ) {
			$gmt = gmdate( 'Y-m-d H:i:s', $target_ts );
			try {
				$sub->update_dates(
					array(
						'date_created' => $gmt,
						'start'        => $gmt,
					),
					'gmt'
				);
				$sub->save();
			} catch ( \Exception $e ) {
				$target_ts = (int) $sub->get_time( 'start', 'gmt' );
				if ( $target_ts <= 0 ) {
					return;
				}
			}
		}

		$parent_id = $sub->get_parent_id();
		if ( ! $parent_id ) {
			return; // No parent order to align.
		}
		$parent = wc_get_order( $parent_id );
		if ( ! $parent ) {
			return;
		}

		$p_created = $parent->get_date_created();
		if ( $p_created && (int) $p_created->getTimestamp() === $target_ts ) {
			return; // Already aligned.
		}

		// set_date_created() treats an integer as a GMT timestamp (HPOS-safe).
		$parent->set_date_created( $target_ts );
		$parent->save();
		$parent->add_order_note(
			sprintf(
				/* translators: %s: subscription id. */
				__( 'Order date set to the start date of subscription #%s.', 'aaraa-white-label-admin' ),
				$sub_id
			)
		);
	}

	/**
	 * Clamp a future "last order created" date so it never blocks cancellation.
	 *
	 * Because the parent order is intentionally dated on a future start date,
	 * WooCommerce Subscriptions' derived `last_order_date_created` can be in the
	 * future. WCS then rejects any cancelled/end date (which is "now") for being
	 * before that last-payment date, and cancel / trash / delete fatally throws.
	 * When the value is in the future we return the subscription's own creation
	 * time instead (always in the past), so the date-ordering check passes and the
	 * schedule stays valid. Non-future values (every normal subscription) pass
	 * through unchanged.
	 *
	 * @param mixed            $date         Date string 'Y-m-d H:i:s' (in $timezone), 0, or null.
	 * @param \WC_Subscription $subscription Subscription being queried.
	 * @param string           $timezone     'gmt' or 'site'.
	 * @return mixed
	 */
	public function clamp_future_last_order_date( $date, $subscription, $timezone = 'gmt' ) {
		if ( empty( $date ) || ! is_string( $date ) || ! $subscription instanceof \WC_Subscription ) {
			return $date;
		}

		$is_gmt = ( 'gmt' === strtolower( (string) $timezone ) );
		$tz     = $is_gmt ? new \DateTimeZone( 'UTC' ) : ( function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' ) );

		try {
			$dt  = new \DateTime( $date, $tz );
			$now = new \DateTime( 'now', $tz );
		} catch ( \Exception $e ) {
			return $date;
		}

		if ( $dt <= $now ) {
			return $date; // Not in the future — leave every normal subscription alone.
		}

		// Future last-order date only happens for a future-dated pending parent.
		// Fall back to the subscription's real creation time (in the past).
		$created = $subscription->get_date_created();
		if ( $created instanceof \WC_DateTime ) {
			$c = clone $created;
			$c->setTimezone( $is_gmt ? new \DateTimeZone( 'UTC' ) : $tz );
			return $c->date( 'Y-m-d H:i:s' );
		}

		$now->modify( '-1 minute' );
		return $now->format( 'Y-m-d H:i:s' );
	}

	/**
	 * AJAX: save the start date from the meta box's own button.
	 *
	 * @return void
	 */
	public function ajax_save() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_subscriptions' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'aaraa-white-label-admin' ) ) );
		}

		$sub_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$raw    = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '';
		$sub    = $sub_id && function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : null;

		if ( ! $sub ) {
			wp_send_json_error( array( 'message' => __( 'Subscription not found.', 'aaraa-white-label-admin' ) ) );
		}
		if ( '' === $raw ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a start date and time.', 'aaraa-white-label-admin' ) ) );
		}

		$result = $this->apply_start( $sub, $raw );
		if ( $result['ok'] ) {
			// Keep the parent order's date on the (new) start date too.
			$this->do_align_parent( $sub_id );
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}
		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Apply a new start date to a subscription through its schedule.
	 *
	 * @param \WC_Subscription $sub Subscription.
	 * @param string           $raw Entered value (store-local "Y-m-d\TH:i").
	 * @return array{ok:bool,message:string}
	 */
	private function apply_start( $sub, $raw ) {
		// Interpret the entered value in the store timezone.
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		try {
			$dt = new \DateTime( $raw, $tz );
		} catch ( \Exception $e ) {
			return array( 'ok' => false, 'message' => __( 'That date could not be read.', 'aaraa-white-label-admin' ) );
		}
		$new_ts = $dt->getTimestamp();
		$cur_ts = (int) $sub->get_time( 'start', 'gmt' );
		if ( $new_ts === $cur_ts ) {
			return array( 'ok' => true, 'message' => __( 'Start date unchanged.', 'aaraa-white-label-admin' ) );
		}

		$dates = array( 'start' => gmdate( 'Y-m-d H:i:s', $new_ts ) );

		// Keep the schedule ordering valid: if the new start is on/after the next
		// payment, roll next payment forward one billing cycle from the new start.
		$np = (int) $sub->get_time( 'next_payment', 'gmt' );
		if ( $np && $np <= $new_ts ) {
			$period                = $sub->get_billing_period();
			$period                = in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ? $period : 'day';
			$interval              = max( 1, (int) $sub->get_billing_interval() );
			$dates['next_payment'] = gmdate( 'Y-m-d H:i:s', strtotime( "+{$interval} {$period}", $new_ts ) );
		}

		try {
			$sub->update_dates( $dates, 'gmt' );
			$sub->save();
			$sub->add_order_note(
				sprintf(
					/* translators: %s: new start date/time */
					__( 'Start date changed to %s (via admin).', 'aaraa-white-label-admin' ),
					get_date_from_gmt( $dates['start'], 'd-M-Y H:i' )
				)
			);
		} catch ( \Exception $e ) {
			return array( 'ok' => false, 'message' => $e->getMessage() );
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %s: new start date/time */
				__( 'Start date saved: %s.', 'aaraa-white-label-admin' ),
				get_date_from_gmt( $dates['start'], 'd-M-Y H:i' )
			),
		);
	}

	/**
	 * Show a one-time error notice when a start-date change was rejected.
	 *
	 * @return void
	 */
	public function maybe_notice() {
		$key = 'aaraa_start_err_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( ! $msg ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error is-dismissible"><p>'
			. esc_html__( 'Start date not changed: ', 'aaraa-white-label-admin' )
			. esc_html( $msg )
			. '</p></div>';
	}
}
