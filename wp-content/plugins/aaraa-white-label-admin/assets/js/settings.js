/**
 * Aaraa settings: colour pickers + media selectors.
 *
 * Depends on jQuery + wp-color-picker (WP core) and the media modal.
 *
 * @package Aaraa\Admin
 */
( function ( $ ) {
	'use strict';

	$( function () {
		// Colour pickers.
		if ( $.fn.wpColorPicker ) {
			$( '.aaraa-color-field' ).wpColorPicker();
		}

		// Media selectors.
		$( '.aaraa-media-field' ).each( function () {
			var $field   = $( this ),
				$input   = $field.find( 'input[type="hidden"]' ),
				$preview = $field.find( '.aaraa-media-preview' ),
				frame;

			$field.on( 'click', '.aaraa-media-select', function ( e ) {
				e.preventDefault();

				if ( frame ) {
					frame.open();
					return;
				}

				frame = wp.media( {
					title: ( window.aaraaSettings && window.aaraaSettings.chooseImage ) || 'Select image',
					button: {
						text: ( window.aaraaSettings && window.aaraaSettings.useImage ) || 'Use this image'
					},
					library: { type: 'image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON(),
						url = attachment.sizes && attachment.sizes.medium
							? attachment.sizes.medium.url
							: attachment.url;

					$input.val( attachment.id );
					$preview.html( $( '<img>', { src: url, alt: '' } ) );
				} );

				frame.open();
			} );

			$field.on( 'click', '.aaraa-media-clear', function ( e ) {
				e.preventDefault();
				$input.val( '0' );
				$preview.empty();
			} );
		} );
	} );
}( jQuery ) );
