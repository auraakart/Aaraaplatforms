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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 45 );
		// Fires on subscription save for both HPOS and the legacy post editor.
		add_action( 'woocommerce_process_shop_subscription_meta', array( $this, 'save' ), 30, 2 );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
		// AJAX save from the meta box's own button (no full form submit).
		add_action( 'wp_ajax_aaraa_save_sub_start', array( $this, 'ajax_save' ) );
	}

	/**
	 * Register the "Start Date" meta box on the subscription edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box() {
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
