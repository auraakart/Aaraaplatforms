/**
 * Order edit + orders list: reload the Delivery Person list when the hub changes.
 */
( function ( $ ) {
	'use strict';

	// WordPress defines window.ajaxurl on every admin page and it always points
	// at the real /wp-admin/admin-ajax.php, so it survives admin_url() filtering.
	function endpoint() {
		return ( 'undefined' !== typeof window.ajaxurl && window.ajaxurl ) ? window.ajaxurl : AaraaOrderDelivery.ajax;
	}

	function reload( $hub, $person ) {
		var hub    = $hub.val();
		var chosen = $person.val();

		$person.prop( 'disabled', true );

		$.getJSON( endpoint(), {
			action: 'aaraa_hub_boys',
			nonce: AaraaOrderDelivery.nonce,
			hub: hub
		} ).done( function ( people ) {
			$person.empty().append(
				$( '<option>' ).val( '0' ).text( AaraaOrderDelivery.none )
			);
			$.each( people || [], function ( i, p ) {
				$person.append( $( '<option>' ).val( p.id ).text( p.text ) );
			} );
			// Keep the existing pick when that person also belongs to the new hub.
			$person.val( chosen );
			if ( null === $person.val() ) {
				$person.val( '0' );
			}
		} ).always( function () {
			$person.prop( 'disabled', false );
		} );
	}

	$( function () {
		// Order edit metabox.
		$( document ).on( 'change', '#aaraa_delivery_hub', function () {
			reload( $( this ), $( '#aaraa_delivery_boy' ) );
		} );

		// Orders list filter row: the person dropdown lists every delivery boy
		// (plus "Not assigned"), so it is intentionally NOT narrowed by hub here.

		// Save the panel on its own, without submitting the order form.
		$( document ).on( 'click', '#aaraa_save_delivery', function ( e ) {
			e.preventDefault();

			var $btn     = $( this );
			var $spinner = $( '.aaraa-orddel__spinner' );
			var $msg     = $( '.aaraa-orddel__msg' );

			$btn.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );
			$msg.removeClass( 'ok error' ).text( '' );

			$.post( endpoint(), {
				action: 'aaraa_save_order_delivery',
				nonce: $( '#aaraa_delivery_nonce' ).val(),
				order_id: $( '#aaraa_delivery_order_id' ).val(),
				slot: $( '#aaraa_delivery_slot' ).val(),
				hub: $( '#aaraa_delivery_hub' ).val(),
				boy: $( '#aaraa_delivery_boy' ).val()
			} ).done( function ( res ) {
				if ( res && res.success ) {
					// Reflect exactly what the server stored.
					$( '#aaraa_delivery_slot' ).val( res.data.slot || '' );
					$( '#aaraa_delivery_hub' ).val( res.data.hub || '0' );
					$( '#aaraa_delivery_boy' ).val( res.data.boy || '0' );
					$msg.addClass( 'ok' ).text( res.data.message );
				} else {
					$msg.addClass( 'error' ).text(
						( res && res.data && res.data.message ) ? res.data.message : 'Could not save.'
					);
				}
			} ).fail( function ( xhr ) {
				$msg.addClass( 'error' ).text( 'Request failed (' + xhr.status + ').' );
			} ).always( function () {
				$btn.prop( 'disabled', false );
				$spinner.removeClass( 'is-active' );
			} );
		} );
	} );
} )( jQuery );
