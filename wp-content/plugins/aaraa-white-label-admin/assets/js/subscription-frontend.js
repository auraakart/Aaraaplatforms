/**
 * My Account subscription page: customer pause / resume panel.
 */
( function ( $ ) {
	'use strict';

	function endpoint() {
		return AaraaFrontSub.ajax;
	}

	function subId() {
		return $( '.aaraa-fsub' ).data( 'subid' );
	}

	function busy( $btn, on ) {
		$btn.prop( 'disabled', on );
		$btn.siblings( '.aaraa-fsub__spinner' ).toggleClass( 'is-active', on );
	}

	function say( $msg, text, ok ) {
		$msg.removeClass( 'ok error' ).addClass( ok ? 'ok' : 'error' ).text( text );
	}

	function post( data, $btn, done ) {
		var $msg = $( '#aaraa_fsub_msg' );
		busy( $btn, true );
		$msg.removeClass( 'ok error' ).text( '' );

		$.post( endpoint(), $.extend( { nonce: AaraaFrontSub.nonce, subscription_id: subId() }, data ) )
			.done( function ( res ) {
				if ( res && res.success ) {
					say( $msg, res.data.message, true );
					if ( res.data.reload ) {
						window.setTimeout( function () {
							window.location.reload();
						}, 1000 );
						return;
					}
					if ( done ) {
						done( res.data );
					}
				} else {
					say( $msg, ( res && res.data && res.data.message ) ? res.data.message : AaraaFrontSub.failed, false );
				}
			} )
			.fail( function ( xhr ) {
				say( $msg, AaraaFrontSub.failed + ' (' + xhr.status + ').', false );
			} )
			.always( function () {
				busy( $btn, false );
			} );
	}

	$( function () {
		if ( ! $( '.aaraa-fsub' ).length ) {
			return;
		}

		// Mode tabs.
		$( document ).on( 'click', '.aaraa-fsub__tab', function () {
			var mode = $( this ).data( 'mode' );
			$( '.aaraa-fsub__tab' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
			$( '.aaraa-fsub__mode' ).each( function () {
				$( this ).toggle( $( this ).data( 'mode' ) === mode );
			} );
			$( '#aaraa_fsub_msg' ).removeClass( 'ok error' ).text( '' );
		} );

		function activeMode() {
			return $( '.aaraa-fsub__tab.is-active' ).data( 'mode' ) || 'dates';
		}

		/* ── Specific Dates ─────────────────────────────────────── */

		var picked = [];

		function renderChips() {
			var $list = $( '#aaraa_fsub_chips' ).empty();

			picked.forEach( function ( date ) {
				$( '<li>' )
					.append( $( '<span>' ).text( date ) )
					.append(
						$( '<button>' )
							.attr( { type: 'button', 'aria-label': 'Remove ' + date } )
							.addClass( 'aaraa-fsub-remove' )
							.data( 'date', date )
							.text( '×' )
					)
					.appendTo( $list );
			} );

			$( '#aaraa_fsub_none' ).toggle( 0 === picked.length );

			// Deliveries are paused only on the chosen dates, so a helpful hint for
			// multiple days — not a warning about gaps.
			$( '#aaraa_fsub_gap' )
				.text( picked.length > 1 ? AaraaFrontSub.multi.replace( '%d', picked.length ) : '' )
				.toggle( picked.length > 1 );
		}

		$( document ).on( 'click', '#aaraa_fsub_add', function () {
			var date = $( '#aaraa_fsub_date' ).val();
			if ( ! date || picked.indexOf( date ) !== -1 ) {
				return;
			}
			picked.push( date );
			picked.sort();
			renderChips();
		} );

		$( document ).on( 'click', '.aaraa-fsub-remove', function () {
			var date = String( $( this ).data( 'date' ) );
			picked = picked.filter( function ( d ) {
				return d !== date;
			} );
			renderChips();
		} );

		/* ── Date Range ─────────────────────────────────────────── */

		$( document ).on( 'change', '#aaraa_fsub_from', function () {
			var from = $( this ).val();
			var $to  = $( '#aaraa_fsub_to' );
			$to.attr( 'min', from );
			if ( ! $to.val() || $to.val() < from ) {
				$to.val( from );
			}
		} );

		/* ── Submit ─────────────────────────────────────────────── */

		$( document ).on( 'click', '#aaraa_fsub_pause', function () {
			var mode = activeMode();
			var data = { action: 'aaraa_front_sub_pause', mode: mode };

			if ( 'dates' === mode ) {
				if ( ! picked.length ) {
					say( $( '#aaraa_fsub_msg' ), AaraaFrontSub.nodates, false );
					return;
				}
				data.dates = picked;
			} else {
				data.from = $( '#aaraa_fsub_from' ).val();
				data.to   = $( '#aaraa_fsub_to' ).val();
			}

			if ( ! window.confirm( AaraaFrontSub.confirm ) ) {
				return;
			}

			post( data, $( this ) );
		} );

		$( document ).on( 'click', '#aaraa_fsub_resume', function () {
			if ( ! window.confirm( AaraaFrontSub.resume ) ) {
				return;
			}
			post( { action: 'aaraa_front_sub_resume' }, $( this ) );
		} );
	} );
} )( jQuery );
