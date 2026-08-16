/**
 * WhatsApp Status Config enhancer.
 *
 * Progressive enhancement for the Status Config tab. Each status card ships
 * with plain inputs (template / language / body params / buttons) that submit
 * on their own. Once the approved template definitions load over AJAX, this
 * script hides those inputs and builds a searchable template picker, guided
 * variable mapping, button mapping and a live preview on top of them — keeping
 * the same hidden fields in sync so the save path never changes. If the fetch
 * fails, the plain inputs stay visible and everything still works by hand.
 */
( function ( $ ) {
	'use strict';

	var cfg     = window.AaraaWAStatus || {};
	var i18n    = cfg.i18n || {};
	var samples = cfg.samples || {};
	var DEFS    = {};

	/* -------------------------------------------------- helpers ------- */

	function escHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	function fmt( tpl, arg ) {
		return String( tpl || '' ).replace( '%s', arg );
	}

	function groupTokens( group ) {
		return ( cfg.tokens && cfg.tokens[ group ] ) || [];
	}

	function isToken( group, raw ) {
		return groupTokens( group ).some( function ( t ) { return t.t === raw; } );
	}

	function splitCsv( val ) {
		return String( val || '' ).split( ',' ).map( function ( s ) { return s.trim(); } );
	}

	/* ------------------------------------------------- mapping rows --- */

	function tokenOptions( group, selectedRaw ) {
		var out = '<option value="">' + escHtml( i18n.chooseToken ) + '</option>';
		groupTokens( group ).forEach( function ( tk ) {
			var sel = ( selectedRaw === tk.t ) ? ' selected' : '';
			out += '<option value="' + escHtml( tk.t ) + '"' + sel + '>' +
				escHtml( tk.label ) + ' ' + escHtml( tk.t ) + '</option>';
		} );
		var customSel = ( selectedRaw && ! isToken( group, selectedRaw ) ) ? ' selected' : '';
		out += '<option value="__custom"' + customSel + '>' + escHtml( i18n.customText ) + '</option>';
		return out;
	}

	function mapRow( group, slot, savedRaw, extraClass, dataAttrs ) {
		var custom  = savedRaw && ! isToken( group, savedRaw );
		var custVal = custom ? savedRaw : '';
		return '<div class="wa-map-row ' + extraClass + '" ' + ( dataAttrs || '' ) + '>' +
			'<span class="wa-var-slot">' + escHtml( slot ) + '</span>' +
			'<select class="wa-var">' + tokenOptions( group, savedRaw ) + '</select>' +
			'<input type="text" class="regular-text wa-var-custom" placeholder="' + escHtml( i18n.customValue ) + '" ' +
				'value="' + escHtml( custVal ) + '"' + ( custom ? '' : ' style="display:none;"' ) + ' />' +
			'</div>';
	}

	function readMapRow( $row ) {
		var val = $row.find( '.wa-var' ).val();
		if ( '__custom' === val ) {
			return { raw: $row.find( '.wa-var-custom' ).val().trim(), isToken: false };
		}
		if ( val ) {
			return { raw: val, isToken: true };
		}
		return { raw: '', isToken: false };
	}

	/* --------------------------------------------------- rendering ---- */

	function renderVariables( card, def ) {
		var group = card.data( 'group' );
		var saved = splitCsv( card.find( '.wa-f-params' ).val() );
		var $c    = card.find( '.wa-vars' ).empty();
		if ( ! def || ! def.vars ) {
			$c.append( '<p class="wa-hint">&mdash;</p>' );
			return;
		}
		for ( var i = 1; i <= def.vars; i++ ) {
			$c.append( mapRow( group, '{{' + i + '}}', saved[ i - 1 ] || '', 'wa-var-row', '' ) );
		}
	}

	function renderButtons( card, def ) {
		var group    = card.data( 'group' );
		var $c       = card.find( '.wa-btns' ).empty();
		var buttons  = ( def && def.buttons ) || [];
		var savedMap = {};
		try {
			( JSON.parse( card.find( '.wa-f-buttons' ).val() || '[]' ) || [] ).forEach( function ( b ) {
				savedMap[ b.index ] = b.param;
			} );
		} catch ( e ) {}

		if ( ! buttons.length ) {
			$c.append( '<p class="wa-hint">' + escHtml( i18n.noButtons ) + '</p>' );
			return;
		}

		buttons.forEach( function ( b ) {
			if ( b.has_var ) {
				var saved = ( savedMap[ b.index ] !== undefined ) ? savedMap[ b.index ] : '';
				var label = '<span class="wa-btn-label">' + escHtml( b.text || '' ) + ' &mdash; ' + escHtml( i18n.urlButton ) + '</span>';
				var row   = mapRow( group, '', saved, 'wa-btn-var', 'data-index="' + b.index + '" data-subtype="url"' );
				$c.append( '<div class="wa-btn-row">' + label + row + '</div>' );
			} else if ( b.text ) {
				$c.append( '<div class="wa-btn-row"><span class="wa-btn-label">' + escHtml( b.text ) +
					' <span class="wa-btn-static">(' + escHtml( i18n.staticButton ) + ')</span></span></div>' );
			}
		} );
	}

	function renderPreview( card ) {
		var def   = card.data( 'curdef' );
		var $wrap = card.find( '.wa-preview' );
		if ( ! def ) {
			$wrap.html( '<p class="wa-preview-empty">' + escHtml( i18n.pickTemplate ) + '</p>' );
			return;
		}

		var vals = [];
		card.find( '.wa-var-row' ).each( function () { vals.push( readMapRow( $( this ) ) ); } );

		var body = escHtml( def.body || '' ).replace( /\{\{\s*(\d+)\s*\}\}/g, function ( m, n ) {
			var v = vals[ parseInt( n, 10 ) - 1 ];
			if ( ! v || '' === v.raw ) {
				return '<span class="wa-miss">{{' + n + '}}</span>';
			}
			var display = v.isToken ? ( samples[ v.raw ] !== undefined ? samples[ v.raw ] : v.raw ) : v.raw;
			return '<span class="wa-fill">' + escHtml( display ) + '</span>';
		} );

		var btns = '';
		( def.buttons || [] ).forEach( function ( b ) {
			if ( b.text ) {
				btns += '<div class="wa-bbtn">' + escHtml( b.text ) + '</div>';
			}
		} );

		var out = '<div class="wa-preview-wrap"><div class="wa-bubble">' + body;
		if ( btns ) {
			out += '<div class="wa-bubble-btns">' + btns + '</div>';
		}
		out += '</div></div>';
		$wrap.html( out );
	}

	/* ----------------------------------------------------- compose ---- */

	function compose( card ) {
		var vals = [];
		card.find( '.wa-var-row' ).each( function () { vals.push( readMapRow( $( this ) ).raw ); } );
		card.find( '.wa-f-params' ).val( vals.join( ', ' ) );

		var btns = [];
		card.find( '.wa-btn-var' ).each( function () {
			var $r = $( this );
			var v  = readMapRow( $r );
			if ( '' !== v.raw ) {
				btns.push( { index: parseInt( $r.data( 'index' ), 10 ), sub_type: $r.data( 'subtype' ) || 'url', param: v.raw } );
			}
		} );
		card.find( '.wa-f-buttons' ).val( btns.length ? JSON.stringify( btns ) : '' );

		renderPreview( card );
	}

	/* ------------------------------------------------ template flow --- */

	function rebuildForLang( card ) {
		var name = card.find( '.wa-f-template' ).val();
		var def  = DEFS[ name ];
		if ( ! def ) {
			return;
		}
		var langs = Object.keys( def.langs );
		var lang  = card.find( '.wa-lang' ).val() || ( langs.indexOf( 'en' ) > -1 ? 'en' : langs[ 0 ] );
		card.find( '.wa-f-language' ).val( lang );

		var ldef = def.langs[ lang ] || def.langs[ langs[ 0 ] ];
		card.data( 'curdef', ldef );

		var $note = card.find( '.wa-tpl-note' );
		if ( ldef.status && 'APPROVED' !== ldef.status ) {
			$note.text( i18n.notApproved ).show();
		} else {
			$note.hide().text( '' );
		}

		renderVariables( card, ldef );
		renderButtons( card, ldef );
		compose( card );
	}

	function selectTemplate( card, name ) {
		card.find( '.wa-f-template' ).val( name );

		if ( ! name ) {
			card.data( 'curdef', null );
			card.find( '.wa-tpl-note' ).hide().text( '' );
			card.find( '.wa-lang-field' ).hide();
			card.find( '.wa-vars' ).html( '<p class="wa-hint">&mdash;</p>' );
			card.find( '.wa-btns' ).empty();
			renderPreview( card );
			return;
		}

		var def = DEFS[ name ];
		if ( ! def ) {
			// Saved name isn't in the approved list — fall back to manual editing.
			card.data( 'curdef', null );
			card.find( '.wa-tpl-note' ).text( fmt( i18n.notInList, name ) ).show();
			card.find( '.wa-lang-field, .wa-cfg-baseline' ).show();
			card.find( '.wa-vars' ).html( '<p class="wa-hint">&mdash;</p>' );
			card.find( '.wa-btns' ).empty();
			renderPreview( card );
			return;
		}

		var langs = Object.keys( def.langs );
		var prev  = card.find( '.wa-f-language' ).val();
		var $lang = card.find( '.wa-lang' ).empty();
		langs.forEach( function ( l ) {
			$lang.append( '<option value="' + escHtml( l ) + '">' + escHtml( l ) + '</option>' );
		} );
		$lang.val( langs.indexOf( prev ) > -1 ? prev : ( langs.indexOf( 'en' ) > -1 ? 'en' : langs[ 0 ] ) );
		card.find( '.wa-lang-field' ).toggle( langs.length > 1 );

		rebuildForLang( card );
	}

	/* ------------------------------------------------------ picker ---- */

	function buildSelect( card ) {
		var names   = Object.keys( DEFS );
		var current = card.find( '.wa-f-template' ).val();
		var $sel    = $( '<select class="wa-template-select"></select>' );

		$sel.append( $( '<option></option>' ).val( '' ).text( i18n.selectPlaceholder || '' ) );
		names.forEach( function ( n ) {
			$sel.append( $( '<option></option>' ).val( n ).text( n ) );
		} );
		// Keep a saved-but-unknown template selectable so it isn't silently lost.
		if ( current && names.indexOf( current ) === -1 ) {
			$sel.append( $( '<option></option>' ).val( current ).text( current ) );
		}
		$sel.val( current );

		card.find( '.wa-pick-holder' ).append( $sel );

		var opts = { width: '100%', placeholder: i18n.selectPlaceholder || i18n.searchTemplate, allowClear: true };
		if ( $.fn.selectWoo ) {
			$sel.selectWoo( opts );
		} else if ( $.fn.select2 ) {
			$sel.select2( opts );
		}

		$sel.on( 'change', function () { selectTemplate( card, $( this ).val() || '' ); } );
	}

	/* ----------------------------------------------------- enhance ---- */

	function updateOn( card ) {
		card.toggleClass( 'is-on', card.find( 'input[name$="[enabled]"]' ).is( ':checked' ) );
	}

	function enhance( card ) {
		var rich =
			'<div class="wa-left">' +
				'<div class="wa-field"><label>' + escHtml( i18n.template ) + '</label>' +
					'<div class="wa-pick-holder"></div><p class="wa-hint wa-tpl-note" style="display:none;"></p></div>' +
				'<div class="wa-field wa-lang-field" style="display:none;"><label>' + escHtml( i18n.language ) + '</label>' +
					'<select class="wa-lang"></select></div>' +
				'<div class="wa-field"><label>' + escHtml( i18n.variables ) + '</label><div class="wa-vars"></div></div>' +
				'<div class="wa-field"><label>' + escHtml( i18n.buttons ) + '</label><div class="wa-btns"></div></div>' +
			'</div>' +
			'<div class="wa-right"><div class="wa-field"><label>' + escHtml( i18n.preview ) + '</label>' +
				'<div class="wa-preview"></div></div></div>';

		card.find( '.wa-cfg-rich' ).html( rich ).removeAttr( 'hidden' );
		card.find( '.wa-cfg-baseline' ).hide();

		buildSelect( card );

		var current = card.find( '.wa-f-template' ).val();
		if ( current ) {
			selectTemplate( card, current );
		} else {
			renderPreview( card );
		}

		card.on( 'change', '.wa-lang', function () { rebuildForLang( card ); } );
		card.on( 'change', '.wa-var', function () {
			$( this ).siblings( '.wa-var-custom' ).toggle( '__custom' === $( this ).val() );
			compose( card );
		} );
		card.on( 'input', '.wa-var-custom', function () { compose( card ); } );
		card.on( 'change', 'input[name$="[enabled]"]', function () { updateOn( card ); } );

		updateOn( card );
	}

	/* -------------------------------------------------------- boot ---- */

	$( function () {
		var $loading = $( '#wa-status-loading' ).show();
		$( '.wa-cfg-card' ).each( function () { updateOn( $( this ) ); } );

		function fail() {
			$loading.removeClass( 'notice-info' ).addClass( 'notice-warning' )
				.html( '<p>' + escHtml( i18n.loadFailed ) + '</p>' );
		}

		$.post( cfg.ajax, { action: cfg.action, nonce: cfg.nonce } )
			.done( function ( resp ) {
				if ( resp && resp.success && resp.data && resp.data.defs ) {
					DEFS = resp.data.defs;
					$( '.wa-cfg-card' ).each( function () { enhance( $( this ) ); } );
					$loading.hide();
				} else {
					fail();
				}
			} )
			.fail( fail );
	} );

} )( jQuery );
