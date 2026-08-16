/**
 * Wallet Management — Add / Edit popups and customer autocomplete.
 */
( function ( $ ) {
	'use strict';

	// WordPress defines window.ajaxurl on every admin page and it always points
	// at the real /wp-admin/admin-ajax.php, so it survives admin_url() filtering.
	function endpoint() {
		return ( 'undefined' !== typeof window.ajaxurl && window.ajaxurl ) ? window.ajaxurl : AaraaWallet.ajax;
	}

	function openModal( id ) {
		var $m = $( '#' + id );
		if ( ! $m.length ) {
			return;
		}
		$m.attr( 'aria-hidden', 'false' ).addClass( 'is-open' );
		$( 'body' ).addClass( 'aaraa-modal-open' );
		$m.find( 'input[type="number"], input[type="text"]' ).first().trigger( 'focus' );
	}

	function closeModal( $m ) {
		$m.attr( 'aria-hidden', 'true' ).removeClass( 'is-open' );
		$( 'body' ).removeClass( 'aaraa-modal-open' );
	}

	$( function () {

		// --- Open Add ---
		$( document ).on( 'click', '.aaraa-wallet__add', function ( e ) {
			e.preventDefault();
			var $m = $( '#aaraa-modal-add' );

			// Reset a shared add/edit modal (delivery screens) back to "add" state.
			$m.find( '[data-record-id]' ).val( '' );
			$m.find( 'input[type="text"], input[type="email"], input[type="time"], textarea' )
				.filter( '[data-f-name],[data-f-address],[data-f-area],[data-f-first],[data-f-last],[data-f-email],[data-f-login],[data-f-phone],[data-f-start],[data-f-end]' )
				.val( '' );
			$m.find( '[data-f-order]' ).val( 0 );
			$m.find( '[data-f-hub]' ).val( '0' );
			$m.find( '[data-f-status]' ).val( 'active' );
			$m.find( '[data-only-new]' ).show();

			var $title = $m.find( '[data-modal-title]' );
			if ( $title.length ) {
				// Remember the original "Add …" label the first time round.
				if ( ! $title.data( 'addLabel' ) ) {
					$title.data( 'addLabel', $title.text() );
				}
				$title.text( $title.data( 'addLabel' ) );
			}
			openModal( 'aaraa-modal-add' );
		} );

		// --- Open Edit ---
		$( document ).on( 'click', '.aaraa-wallet__edit', function ( e ) {
			e.preventDefault();
			var $btn = $( this );

			// Wallet screen has a dedicated edit modal.
			if ( $( '#aaraa-modal-edit' ).length ) {
				var $w = $( '#aaraa-modal-edit' );
				$w.find( '[data-user-id]' ).val( $btn.data( 'id' ) );
				$w.find( '[data-edit-name]' ).text( $btn.data( 'name' ) || '' );
				$w.find( '[data-edit-balance]' ).text( $btn.data( 'balance' ) || '' );
				$w.find( 'input[name="wtx_amount"]' ).val( '' );
				$w.find( 'input[name="wtx_note"]' ).val( '' );
				$w.find( 'input[name="wtx_type"][value="credit"]' ).prop( 'checked', true );
				openModal( 'aaraa-modal-edit' );
				return;
			}

			// Delivery screens reuse the single add modal: map every data-X on the
			// button onto the field marked [data-f-X].
			var $m = $( '#aaraa-modal-add' );
			$.each( this.attributes, function () {
				if ( 0 !== this.name.indexOf( 'data-' ) ) {
					return;
				}
				var key = this.name.slice( 5 );
				if ( 'id' === key ) {
					$m.find( '[data-record-id]' ).val( this.value );
					return;
				}
				$m.find( '[data-f-' + key + ']' ).val( this.value );
			} );
			$m.find( '[data-only-new]' ).hide();
			var $t = $m.find( '[data-modal-title]' );
			if ( $t.length ) {
				if ( ! $t.data( 'addLabel' ) ) {
					$t.data( 'addLabel', $t.text() );
				}
				// "+ Add Hub" → "Edit Hub"
				$t.text( $t.data( 'addLabel' ).replace( /^\+?\s*Add/i, 'Edit' ) );
			}
			openModal( 'aaraa-modal-add' );
		} );

		// --- Close (backdrop, X, Cancel, Esc) ---
		$( document ).on( 'click', '.aaraa-modal [data-close]', function () {
			closeModal( $( this ).closest( '.aaraa-modal' ) );
		} );
		$( document ).on( 'keydown', function ( e ) {
			if ( 27 === e.keyCode ) {
				closeModal( $( '.aaraa-modal.is-open' ) );
			}
		} );

		// --- Guard: need either a picked customer or something to look up ---
		$( document ).on( 'submit', '[data-add-form]', function ( e ) {
			var $form   = $( this );
			var hasId   = $form.find( '[data-user-id]' ).val();
			var hasText = $.trim( $form.find( '[data-user-search]' ).val() || '' );
			if ( ! hasId && ! hasText ) {
				e.preventDefault();
				$form.find( '[data-user-search]' ).trigger( 'focus' );
				window.alert( 'Enter a customer name, mobile or email.' );
			}
		} );

		// --- Autocomplete on the Add search field ---
		var timer = null;
		var $search  = $( '[data-user-search]' );
		var $results = $( '[data-results]' );
		var $chosen  = $( '[data-chosen]' );
		var $userId  = $( '#aaraa-modal-add [data-user-id]' );

		function clearChoice() {
			$userId.val( '' );
			$chosen.attr( 'hidden', true ).text( '' );
		}

		$search.on( 'input', function () {
			clearChoice();
			var term = $.trim( $search.val() );
			window.clearTimeout( timer );
			if ( term.length < 2 ) {
				$results.attr( 'hidden', true ).empty();
				return;
			}
			timer = window.setTimeout( function () {
				$.getJSON( endpoint(), {
					action: 'aaraa_wallet_user_search',
					nonce: AaraaWallet.nonce,
					term: term
				} ).done( function ( items ) {
					$results.empty();
					if ( ! items || ! items.length ) {
						$results.append( $( '<li class="is-empty">' ).text( 'No customers found' ) );
					} else {
						$.each( items, function ( i, it ) {
							$( '<li>' )
								.attr( 'data-id', it.id )
								.text( it.text )
								.appendTo( $results );
						} );
					}
					$results.removeAttr( 'hidden' );
				} );
			}, 280 );
		} );

		// Pick a result. The text stays in the field so it also submits as
		// `user_query` (a server-side fallback if the hidden id is ever lost).
		$( document ).on( 'click', '[data-results] li[data-id]', function () {
			var $li = $( this );
			$userId.val( $li.data( 'id' ) );
			$search.val( $li.text() );
			$chosen.text( '✓ ' + $li.text() ).removeAttr( 'hidden' );
			$results.attr( 'hidden', true ).empty();
		} );

		// Hide results when clicking away.
		$( document ).on( 'click', function ( e ) {
			if ( ! $( e.target ).closest( '.aaraa-wallet__ac' ).length ) {
				$results.attr( 'hidden', true );
			}
		} );
	} );
} )( jQuery );
