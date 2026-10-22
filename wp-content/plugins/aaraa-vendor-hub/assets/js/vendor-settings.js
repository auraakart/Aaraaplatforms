/**
 * Vendor App Config edit screen behaviour:
 *  - the banner repeater (add/remove rows, `banners[i][field]` naming so
 *    $_POST['banners'] arrives as a plain indexed array of associative rows)
 *  - the WordPress media picker on every image-type field (static branding
 *    fields and dynamically-added banner rows alike, via delegation)
 *  - two-way sync between each color field's native swatch and its hex text
 *    input
 */
( function () {
	'use strict';

	/* --------------------------------------------------------------------- *
	 * Media picker — delegated, so it also covers banner rows added later.
	 * --------------------------------------------------------------------- */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.aaraa-vh-media-btn' ) : null;
		if ( ! btn || 'undefined' === typeof wp || ! wp.media ) {
			return;
		}
		e.preventDefault();

		var field   = btn.closest( '.aaraa-vh-image-field' );
		var input   = field ? field.querySelector( 'input' ) : null;
		var preview = field ? field.querySelector( '.aaraa-vh-media-preview' ) : null;
		if ( ! input ) {
			return;
		}

		var frame = wp.media( {
			title:    'Select or upload an image',
			multiple: false,
			library:  { type: 'image' },
			button:   { text: 'Use this image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			input.value = attachment.url;
			if ( preview ) {
				preview.src = attachment.url;
				preview.style.display = 'inline-block';
			}
		} );

		frame.open();
	} );

	/* --------------------------------------------------------------------- *
	 * Color swatch <-> hex text sync.
	 * --------------------------------------------------------------------- */
	document.addEventListener( 'input', function ( e ) {
		if ( e.target.classList && e.target.classList.contains( 'aaraa-vh-color-swatch' ) ) {
			var field = e.target.closest( '.aaraa-vh-color-field' );
			var text  = field ? field.querySelector( '.aaraa-vh-color-text' ) : null;
			if ( text ) {
				text.value = e.target.value;
			}
		}
		if ( e.target.classList && e.target.classList.contains( 'aaraa-vh-color-text' ) ) {
			var field2  = e.target.closest( '.aaraa-vh-color-field' );
			var swatch  = field2 ? field2.querySelector( '.aaraa-vh-color-swatch' ) : null;
			if ( swatch && /^#[0-9A-Fa-f]{6}$/.test( e.target.value ) ) {
				swatch.value = e.target.value;
			}
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap    = document.getElementById( 'aaraa-vh-banners' );
		var tpl     = document.getElementById( 'aaraa-vh-banner-row-tpl' );
		var addBtn  = document.getElementById( 'aaraa-vh-banner-add' );
		var dataEl  = document.getElementById( 'aaraa-vh-banners-data' );
		if ( ! wrap || ! tpl || ! addBtn ) {
			return;
		}

		var existing = [];
		if ( dataEl ) {
			try {
				existing = JSON.parse( dataEl.textContent || '[]' ) || [];
			} catch ( e ) {
				existing = [];
			}
		}

		function reindex() {
			var rows = wrap.querySelectorAll( '.aaraa-vh-banner-row' );
			rows.forEach( function ( row, i ) {
				row.querySelectorAll( '[data-field]' ).forEach( function ( input ) {
					input.name = 'banners[' + i + '][' + input.getAttribute( 'data-field' ) + ']';
				} );
				var idInput = row.querySelector( '[data-id-field]' );
				if ( idInput ) {
					idInput.name = 'banners[' + i + '][id]';
				}
			} );
		}

		function addRow( data ) {
			data = data || {};
			var frag = tpl.content.cloneNode( true );
			var row  = frag.querySelector( '.aaraa-vh-banner-row' );

			var idInput = document.createElement( 'input' );
			idInput.type = 'hidden';
			idInput.setAttribute( 'data-id-field', '1' );
			idInput.value = data.id || '';
			row.appendChild( idInput );

			row.querySelectorAll( '[data-field]' ).forEach( function ( input ) {
				var field = input.getAttribute( 'data-field' );
				if ( 'isActive' === field ) {
					input.checked = ( undefined === data.isActive ) ? true : !! data.isActive;
				} else if ( undefined !== data[ field ] ) {
					input.value = data[ field ];
					if ( 'imageUrl' === field && data[ field ] ) {
						var preview = row.querySelector( '.aaraa-vh-media-preview' );
						if ( preview ) {
							preview.src = data[ field ];
							preview.style.display = 'inline-block';
						}
					}
				}
			} );

			row.querySelector( '.aaraa-vh-banner-remove' ).addEventListener( 'click', function () {
				row.remove();
				reindex();
			} );

			wrap.appendChild( row );
			reindex();
		}

		existing.forEach( addRow );

		addBtn.addEventListener( 'click', function () {
			addRow( { sortOrder: wrap.querySelectorAll( '.aaraa-vh-banner-row' ).length + 1 } );
		} );
	} );
} )();
