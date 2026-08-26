/**
 * My Account subscription page: customer pause / resume panel.
 *
 * The pause dates are chosen on a month calendar: tap a day to pause it (it turns
 * red), tap again to remove it, or drag across days to select several. A calendar
 * marked data-readonly="1" just displays the saved pause dates (past ones muted).
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

	/**
	 * Turn a `.aaraa-cal` element into an interactive (or read-only) calendar.
	 * Returns { future: fn } giving the selected today-or-later dates.
	 */
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

		// Month navigation.
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

		// The editable picker (data-readonly="0") drives the Pause submit; any
		// read-only calendars just display the saved schedule.
		var picker = null;
		$( '.aaraa-cal' ).each( function () {
			var isEditable = '0' === this.getAttribute( 'data-readonly' );
			var cal = buildCalendar( this, isEditable ? function ( future ) {
				$( '#aaraa_fsub_gap' )
					.text( future.length > 1 ? AaraaFrontSub.multi.replace( '%d', future.length ) : '' )
					.toggle( future.length > 1 );
			} : null );
			if ( isEditable ) {
				picker = cal;
			}
		} );

		$( document ).on( 'click', '#aaraa_fsub_pause', function () {
			if ( ! picker ) {
				return;
			}
			var dates = picker.future();
			if ( ! dates.length ) {
				say( $( '#aaraa_fsub_msg' ), AaraaFrontSub.nodates, false );
				return;
			}
			if ( ! window.confirm( AaraaFrontSub.confirm ) ) {
				return;
			}
			post( { action: 'aaraa_front_sub_pause', mode: 'dates', dates: dates }, $( this ) );
		} );

		$( document ).on( 'click', '#aaraa_fsub_resume', function () {
			if ( ! window.confirm( AaraaFrontSub.resume ) ) {
				return;
			}
			post( { action: 'aaraa_front_sub_resume' }, $( this ) );
		} );
	} );
} )( jQuery );
