/**
 * Subscription edit screen: delivery schedule + pause/resume panel.
 *
 * Pause dates are chosen on a month calendar: click a day to pause it (turns red),
 * click again to remove it, or drag across days to select several. A calendar
 * marked data-readonly="1" only displays the saved pause dates (past ones muted).
 */
( function ( $ ) {
	'use strict';

	var MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
	var DOW    = [ 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' ];

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function ymd( y, m, d ) {
		return y + '-' + pad( m + 1 ) + '-' + pad( d );
	}

	function daysInMonth( y, m ) {
		return new Date( y, m + 1, 0 ).getDate();
	}

	function firstDow( y, m ) {
		return new Date( y, m, 1 ).getDay();
	}

	function buildCalendar( el, onChange ) {
		var min      = el.getAttribute( 'data-min' );
		var today    = el.getAttribute( 'data-today' );
		var readonly = '1' === el.getAttribute( 'data-readonly' );
		var selected = {};

		try {
			( JSON.parse( el.getAttribute( 'data-selected' ) || '[]' ) || [] ).forEach( function ( d ) {
				selected[ d ] = true;
			} );
		} catch ( e ) {}

		var parts = today.split( '-' );
		var view  = { y: parseInt( parts[ 0 ], 10 ), m: parseInt( parts[ 1 ], 10 ) - 1 };
		var dragging = false;
		var dragAdd  = true;

		function selectable( date ) {
			return ! readonly && date >= min;
		}

		function apply( date, add, cell ) {
			if ( ! selectable( date ) ) {
				return;
			}
			if ( add ) {
				selected[ date ] = true;
				cell.classList.add( 'is-sel' );
			} else {
				delete selected[ date ];
				cell.classList.remove( 'is-sel' );
			}
			if ( onChange ) {
				onChange( futureDates() );
			}
		}

		function futureDates() {
			return Object.keys( selected ).filter( function ( d ) {
				return d >= min;
			} ).sort();
		}

		function render() {
			var y = view.y;
			var m = view.m;
			var html = '';

			html += '<div class="aaraa-cal__head">';
			html += '<button type="button" class="aaraa-cal__nav" data-nav="-1" aria-label="Previous month">‹</button>';
			html += '<span class="aaraa-cal__title">' + MONTHS[ m ] + ' ' + y + '</span>';
			html += '<button type="button" class="aaraa-cal__nav" data-nav="1" aria-label="Next month">›</button>';
			html += '</div>';

			html += '<div class="aaraa-cal__grid">';
			DOW.forEach( function ( d ) {
				html += '<div class="aaraa-cal__dow">' + d + '</div>';
			} );

			var lead = firstDow( y, m );
			var i;
			for ( i = 0; i < lead; i++ ) {
				html += '<div class="aaraa-cal__day is-empty"></div>';
			}

			var total = daysInMonth( y, m );
			for ( i = 1; i <= total; i++ ) {
				var date = ymd( y, m, i );
				var cls  = 'aaraa-cal__day';
				if ( date === today ) {
					cls += ' is-today';
				}
				if ( selected[ date ] ) {
					cls += date >= min ? ' is-sel' : ' is-pastsel';
				} else if ( date < min ) {
					cls += ' is-disabled';
				}
				html += '<div class="' + cls + '" data-date="' + date + '">' + i + '</div>';
			}

			html += '</div>';
			el.innerHTML = html;
		}

		$( el ).on( 'click', '.aaraa-cal__nav', function () {
			var dir = parseInt( $( this ).data( 'nav' ), 10 );
			view.m += dir;
			if ( view.m < 0 ) {
				view.m = 11;
				view.y -= 1;
			} else if ( view.m > 11 ) {
				view.m = 0;
				view.y += 1;
			}
			render();
		} );

		if ( ! readonly ) {
			el.addEventListener( 'pointerdown', function ( ev ) {
				var cell = ev.target.closest( '.aaraa-cal__day[data-date]' );
				if ( ! cell ) {
					return;
				}
				var date = cell.getAttribute( 'data-date' );
				if ( ! selectable( date ) ) {
					return;
				}
				ev.preventDefault();
				dragging = true;
				dragAdd  = ! selected[ date ];
				apply( date, dragAdd, cell );
			} );

			el.addEventListener( 'pointerover', function ( ev ) {
				if ( ! dragging ) {
					return;
				}
				var cell = ev.target.closest( '.aaraa-cal__day[data-date]' );
				if ( ! cell ) {
					return;
				}
				apply( cell.getAttribute( 'data-date' ), dragAdd, cell );
			} );

			document.addEventListener( 'pointerup', function () {
				dragging = false;
			} );
		}

		render();

		return { future: futureDates };
	}

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

		/* ── Pause calendar ─────────────────────────────────────── */

		var picker = null;
		$( '.aaraa-cal' ).each( function () {
			var isEditable = '0' === this.getAttribute( 'data-readonly' );
			var cal = buildCalendar( this, isEditable ? function ( future ) {
				$( '#aaraa_pause_gap' )
					.text( future.length > 1 ? AaraaSubDelivery.multi.replace( '%d', future.length ) : '' )
					.toggle( future.length > 1 );
			} : null );
			if ( isEditable ) {
				picker = cal;
			}
		} );

		$( document ).on( 'click', '#aaraa_sub_pause', function () {
			if ( ! picker ) {
				return;
			}
			var dates = picker.future();
			if ( ! dates.length ) {
				say( $( '#aaraa_sub_pause_msg' ), AaraaSubDelivery.nodates, false );
				return;
			}
			if ( ! window.confirm( AaraaSubDelivery.confirm ) ) {
				return;
			}
			post( { action: 'aaraa_sub_pause', mode: 'dates', dates: dates }, $( this ), $( '#aaraa_sub_pause_msg' ) );
		} );

		$( document ).on( 'click', '#aaraa_sub_resume', function () {
			post( { action: 'aaraa_sub_resume' }, $( this ), $( '#aaraa_sub_pause_msg' ) );
		} );
	} );
} )( jQuery );
