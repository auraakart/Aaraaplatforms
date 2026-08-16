/**
 * Subscription edit screen: delivery schedule + pause/resume panel.
 */
( function ( $ ) {
	'use strict';

	// WordPress defines window.ajaxurl on every admin page and it always points
	// at the real /wp-admin/admin-ajax.php, so it survives admin_url() filtering.
	function endpoint() {
		return ( 'undefined' !== typeof window.ajaxurl && window.ajaxurl ) ? window.ajaxurl : AaraaSubDelivery.ajax;
	}

	function subId() {
		return $( '.aaraa-subdel' ).data( 'subid' );
	}

	function busy( $btn, on ) {
		$btn.prop( 'disabled', on );
		$btn.siblings( '.aaraa-subdel__spinner' ).toggleClass( 'is-active', on );
	}

	function say( $msg, text, ok ) {
		$msg.removeClass( 'ok error' ).addClass( ok ? 'ok' : 'error' ).text( text );
	}

	function post( data, $btn, $msg, done ) {
		busy( $btn, true );
		$msg.removeClass( 'ok error' ).text( '' );

		$.post( endpoint(), $.extend( { nonce: AaraaSubDelivery.nonce, subscription_id: subId() }, data ) )
			.done( function ( res ) {
				if ( res && res.success ) {
					say( $msg, res.data.message, true );
					if ( res.data.reload ) {
						// Status changed — the surrounding screen is now stale.
						window.setTimeout( function () {
							window.location.reload();
						}, 900 );
						return;
					}
					if ( done ) {
						done( res.data );
					}
				} else {
					say( $msg, ( res && res.data && res.data.message ) ? res.data.message : AaraaSubDelivery.failed, false );
				}
			} )
			.fail( function ( xhr ) {
				say( $msg, AaraaSubDelivery.failed + ' (' + xhr.status + ').', false );
			} )
			.always( function () {
				busy( $btn, false );
			} );
	}

	$( function () {
		// Show the weekday picker only for the custom schedule.
		$( document ).on( 'change', 'input[name="aaraa_sub_schedule"]', function () {
			$( '#aaraa_sub_days' ).toggle( 'custom' === $( this ).val() );
		} );

		$( document ).on( 'click', '#aaraa_sub_schedule_save', function () {
			var days = [];
			$( '.aaraa-sub-day:checked' ).each( function () {
				days.push( $( this ).val() );
			} );

			post(
				{
					action: 'aaraa_sub_schedule',
					schedule: $( 'input[name="aaraa_sub_schedule"]:checked' ).val() || '',
					days: days,
					slot: $( '#aaraa_sub_slot' ).val() || 0
				},
				$( this ),
				$( '#aaraa_sub_schedule_msg' )
			);
		} );

		// Next renewal (next payment) date.
		$( document ).on( 'click', '#aaraa_sub_next_payment_save', function () {
			post(
				{
					action: 'aaraa_sub_next_payment',
					next_payment: $( '#aaraa_sub_next_payment' ).val() || ''
				},
				$( this ),
				$( '#aaraa_sub_next_payment_msg' )
			);
		} );

		// Mode tabs — Specific Dates / Date Range.
		$( document ).on( 'click', '.aaraa-subdel__tab', function () {
			var mode = $( this ).data( 'mode' );
			$( '.aaraa-subdel__tab' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
			$( '.aaraa-subdel__mode' ).each( function () {
				$( this ).toggle( $( this ).data( 'mode' ) === mode );
			} );
			$( '#aaraa_sub_pause_msg' ).removeClass( 'ok error' ).text( '' );
		} );

		function activeMode() {
			return $( '.aaraa-subdel__tab.is-active' ).data( 'mode' ) || 'dates';
		}

		/* ── Specific Dates ─────────────────────────────────────── */

		var picked = [];

		function renderChips() {
			var $list = $( '#aaraa_pause_chips' ).empty();

			picked.forEach( function ( date ) {
				$( '<li>' )
					.append( $( '<span>' ).text( date ) )
					.append(
						$( '<button>' )
							.attr( { type: 'button', 'aria-label': 'Remove ' + date } )
							.addClass( 'aaraa-pause-remove' )
							.data( 'date', date )
							.text( '×' )
					)
					.appendTo( $list );
			} );

			$( '#aaraa_pause_none' ).toggle( 0 === picked.length );

			// Date-scoped: paused only on the chosen dates, delivering on the gaps.
			$( '#aaraa_pause_gap' )
				.text( picked.length > 1 ? AaraaSubDelivery.multi.replace( '%d', picked.length ) : '' )
				.toggle( picked.length > 1 );
		}

		$( document ).on( 'click', '#aaraa_pause_add', function () {
			var date = $( '#aaraa_pause_date' ).val();
			if ( ! date || picked.indexOf( date ) !== -1 ) {
				return;
			}
			picked.push( date );
			picked.sort();
			renderChips();
		} );

		$( document ).on( 'click', '.aaraa-pause-remove', function () {
			var date = String( $( this ).data( 'date' ) );
			picked = picked.filter( function ( d ) {
				return d !== date;
			} );
			renderChips();
		} );

		/* ── Date Range ─────────────────────────────────────────── */

		// Keep the end date at or after the start date.
		$( document ).on( 'change', '#aaraa_sub_pause_from', function () {
			var from = $( this ).val();
			var $to  = $( '#aaraa_sub_pause_to' );
			$to.attr( 'min', from );
			if ( ! $to.val() || $to.val() < from ) {
				$to.val( from );
			}
		} );

		/* ── Submit ─────────────────────────────────────────────── */

		$( document ).on( 'click', '#aaraa_sub_pause', function () {
			var mode = activeMode();
			var data = { action: 'aaraa_sub_pause', mode: mode };

			if ( 'dates' === mode ) {
				if ( ! picked.length ) {
					say( $( '#aaraa_sub_pause_msg' ), AaraaSubDelivery.nodates, false );
					return;
				}
				data.dates = picked;
			} else {
				data.from = $( '#aaraa_sub_pause_from' ).val();
				data.to   = $( '#aaraa_sub_pause_to' ).val();
			}

			if ( ! window.confirm( AaraaSubDelivery.confirm ) ) {
				return;
			}

			post( data, $( this ), $( '#aaraa_sub_pause_msg' ) );
		} );

		$( document ).on( 'click', '#aaraa_sub_resume', function () {
			post( { action: 'aaraa_sub_resume' }, $( this ), $( '#aaraa_sub_pause_msg' ) );
		} );
	} );
} )( jQuery );
