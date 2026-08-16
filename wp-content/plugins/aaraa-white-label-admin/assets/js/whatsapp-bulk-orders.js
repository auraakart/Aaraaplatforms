/**
 * Bulk "Send WhatsApp" for the WooCommerce Orders list.
 *
 * Adds a button next to Bulk actions. Clicking it opens a modal where you pick
 * an approved template, map its variables/buttons to order tokens, preview the
 * message, and send it to the selected orders' customers. Tokens are resolved
 * per order on the server; each send is logged and noted on the order.
 */
( function ( $ ) {
	'use strict';

	var cfg     = window.AaraaWABulk || {};
	var i18n    = cfg.i18n || {};
	var samples = cfg.samples || {};
	var tokens  = cfg.tokens || [];
	// Prefer the templates already cached by the Template Manager (instant, no
	// second live API call). Fall back to an AJAX fetch only if none are cached.
	var DEFS    = ( cfg.defs && ! Array.isArray( cfg.defs ) && Object.keys( cfg.defs ).length ) ? cfg.defs : null;
	var $modal  = null;

	/* --------------------------------------------------- helpers ------ */

	function escHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	function fmt( tpl ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( tpl || '' ).replace( /%\d?\$?d|%s/g, function () { return args[ i++ ]; } );
	}

	function isToken( raw ) {
		return tokens.some( function ( t ) { return t.t === raw; } );
	}

	function selectedOrderIds() {
		var ids = [];
		$( 'input[name="id[]"]:checked, input[name="post[]"]:checked, input[name="order[]"]:checked' ).each( function () {
			var v = parseInt( $( this ).val(), 10 );
			if ( v ) { ids.push( v ); }
		} );
		return ids;
	}

	/* ----------------------------------------------- mapping rows ----- */

	function tokenOptions( selectedRaw ) {
		var out = '<option value="">' + escHtml( i18n.chooseToken ) + '</option>';
		tokens.forEach( function ( tk ) {
			var sel = ( selectedRaw === tk.t ) ? ' selected' : '';
			out += '<option value="' + escHtml( tk.t ) + '"' + sel + '>' + escHtml( tk.label ) + ' ' + escHtml( tk.t ) + '</option>';
		} );
		var customSel = ( selectedRaw && ! isToken( selectedRaw ) ) ? ' selected' : '';
		out += '<option value="__custom"' + customSel + '>' + escHtml( i18n.customText ) + '</option>';
		return out;
	}

	function mapRow( slot, savedRaw, extraClass, dataAttrs ) {
		var custom = savedRaw && ! isToken( savedRaw );
		return '<div class="wab-map-row ' + extraClass + '" ' + ( dataAttrs || '' ) + '>' +
			'<span class="wab-slot">' + escHtml( slot ) + '</span>' +
			'<select class="wab-var">' + tokenOptions( savedRaw ) + '</select>' +
			'<input type="text" class="wab-var-custom" placeholder="' + escHtml( i18n.customValue ) + '" value="' + escHtml( custom ? savedRaw : '' ) + '"' + ( custom ? '' : ' style="display:none;"' ) + ' />' +
			'</div>';
	}

	function readMapRow( $row ) {
		var val = $row.find( '.wab-var' ).val();
		if ( '__custom' === val ) {
			return { raw: $row.find( '.wab-var-custom' ).val().trim(), isToken: false };
		}
		if ( val ) {
			return { raw: val, isToken: true };
		}
		return { raw: '', isToken: false };
	}

	/* ------------------------------------------------- rendering ------ */

	function curDef() {
		var name = $modal.find( '.wab-template' ).val();
		var lang = $modal.find( '.wab-lang' ).val();
		if ( ! name || ! DEFS[ name ] ) { return null; }
		var langs = DEFS[ name ].langs;
		return langs[ lang ] || langs[ Object.keys( langs )[ 0 ] ];
	}

	function renderVariables( def ) {
		var $c = $modal.find( '.wab-vars' ).empty();
		if ( ! def || ! def.vars ) { $c.append( '<p class="wab-hint">&mdash;</p>' ); return; }
		for ( var i = 1; i <= def.vars; i++ ) {
			$c.append( mapRow( '{{' + i + '}}', '', 'wab-var-row', '' ) );
		}
	}

	function renderButtons( def ) {
		var $c      = $modal.find( '.wab-btns' ).empty();
		var buttons = ( def && def.buttons ) || [];
		if ( ! buttons.length ) { $c.append( '<p class="wab-hint">' + escHtml( i18n.noButtons ) + '</p>' ); return; }
		buttons.forEach( function ( b ) {
			if ( b.has_var ) {
				var label = '<span class="wab-btn-label">' + escHtml( b.text || '' ) + ' &mdash; ' + escHtml( i18n.urlButton ) + '</span>';
				$c.append( '<div class="wab-btn-wrap">' + label + mapRow( '', '', 'wab-btn-var', 'data-index="' + b.index + '" data-subtype="url"' ) + '</div>' );
			} else if ( b.text ) {
				$c.append( '<div class="wab-btn-wrap"><span class="wab-btn-label">' + escHtml( b.text ) + ' <em>(' + escHtml( i18n.staticButton ) + ')</em></span></div>' );
			}
		} );
	}

	function renderPreview() {
		var def   = curDef();
		var $wrap = $modal.find( '.wab-preview' );
		if ( ! def ) { $wrap.html( '<p class="wab-hint">' + escHtml( i18n.pickTemplate ) + '</p>' ); return; }

		var vals = [];
		$modal.find( '.wab-var-row' ).each( function () { vals.push( readMapRow( $( this ) ) ); } );

		var body = escHtml( def.body || '' ).replace( /\{\{\s*(\d+)\s*\}\}/g, function ( m, n ) {
			var v = vals[ parseInt( n, 10 ) - 1 ];
			if ( ! v || '' === v.raw ) { return '<span class="wab-miss">{{' + n + '}}</span>'; }
			var display = v.isToken ? ( samples[ v.raw ] !== undefined ? samples[ v.raw ] : v.raw ) : v.raw;
			return '<span class="wab-fill">' + escHtml( display ) + '</span>';
		} );

		var btns = '';
		( def.buttons || [] ).forEach( function ( b ) { if ( b.text ) { btns += '<div class="wab-bbtn">' + escHtml( b.text ) + '</div>'; } } );

		var out = '<div class="wab-bubble">' + body + ( btns ? '<div class="wab-bubble-btns">' + btns + '</div>' : '' ) + '</div>';
		$wrap.html( out );
	}

	function onTemplateChange() {
		var name  = $modal.find( '.wab-template' ).val();
		var $lang = $modal.find( '.wab-lang' ).empty();
		var $note = $modal.find( '.wab-note' ).hide().text( '' );

		if ( ! name || ! DEFS[ name ] ) {
			$modal.find( '.wab-lang-field' ).hide();
			renderVariables( null );
			renderButtons( null );
			renderPreview();
			return;
		}
		var langs = Object.keys( DEFS[ name ].langs );
		langs.forEach( function ( l ) { $lang.append( '<option value="' + escHtml( l ) + '">' + escHtml( l ) + '</option>' ); } );
		$lang.val( langs.indexOf( 'en' ) > -1 ? 'en' : langs[ 0 ] );
		$modal.find( '.wab-lang-field' ).toggle( langs.length > 1 );

		var def = curDef();
		if ( def && def.status && 'APPROVED' !== def.status ) { $note.text( i18n.notApproved ).show(); }
		renderVariables( def );
		renderButtons( def );
		renderPreview();
	}

	/* --------------------------------------------------- template pick */

	function buildTemplateSelect() {
		var names = Object.keys( DEFS || {} );
		var $sel  = $modal.find( '.wab-template' );
		$sel.empty().append( $( '<option></option>' ).val( '' ).text( i18n.selectTemplate ) );
		names.forEach( function ( n ) { $sel.append( $( '<option></option>' ).val( n ).text( n ) ); } );

		var opts = { width: '100%', placeholder: i18n.selectTemplate, allowClear: true, dropdownParent: $modal.find( '.wab-body' ) };
		try {
			if ( $.fn.selectWoo ) { $sel.selectWoo( opts ); }
			else if ( $.fn.select2 ) { $sel.select2( opts ); }
		} catch ( e ) { /* fall back to the native select */ }
	}

	/* -------------------------------------------------------- send ---- */

	function doSend( ids ) {
		var name = $modal.find( '.wab-template' ).val();
		if ( ! name ) { window.alert( i18n.chooseFirst ); return; }

		var params = [];
		$modal.find( '.wab-var-row' ).each( function () { params.push( readMapRow( $( this ) ).raw ); } );

		var btns = [];
		$modal.find( '.wab-btn-var' ).each( function () {
			var $r = $( this );
			var v  = readMapRow( $r );
			if ( '' !== v.raw ) { btns.push( { index: parseInt( $r.data( 'index' ), 10 ), sub_type: $r.data( 'subtype' ) || 'url', param: v.raw } ); }
		} );

		var $status = $modal.find( '.wab-status' ).text( i18n.sending );
		var $send   = $modal.find( '.wab-send' ).prop( 'disabled', true );

		$.post( cfg.ajax, {
			action:    cfg.sendAction,
			nonce:     cfg.sendNonce,
			order_ids: ids,
			template:  name,
			language:  $modal.find( '.wab-lang' ).val() || 'en',
			params:    params.join( ', ' ),
			buttons:   btns.length ? JSON.stringify( btns ) : ''
		} ).done( function ( resp ) {
			if ( resp && resp.success ) {
				var d = resp.data;
				$status.text( fmt( i18n.done, d.sent, d.failed, d.skipped || 0 ) );
				renderResults( d.results );
				// Keep Send disabled after a completed run so the same selection
				// can't be messaged again by accident. Reopen the modal to resend.
				$send.prop( 'disabled', true );
			} else {
				$status.text( ( resp && resp.data && resp.data.message ) ? resp.data.message : i18n.requestFailed );
				$send.prop( 'disabled', false );
			}
		} ).fail( function () {
			$status.text( i18n.requestFailed );
			$send.prop( 'disabled', false );
		} );
	}

	function renderResults( results ) {
		if ( ! results || ! results.length ) { return; }
		var html = '<ul class="wab-results">';
		results.forEach( function ( r ) {
			html += '<li class="' + ( r.ok ? 'ok' : 'fail' ) + '">#' + escHtml( r.id ) + ' — ' + escHtml( r.msg ) + '</li>';
		} );
		html += '</ul>';
		$modal.find( '.wab-results-wrap' ).html( html );
	}

	/* -------------------------------------------------------- modal --- */

	function ensureModal() {
		if ( $modal ) { return; }
		$modal = $(
			'<div class="wab-overlay" style="display:none;">' +
				'<div class="wab-modal">' +
					'<div class="wab-head"><strong class="wab-title"></strong><button type="button" class="wab-close" aria-label="Close">&times;</button></div>' +
					'<div class="wab-body">' +
						'<div class="wab-field"><label>' + escHtml( i18n.template ) + '</label><select class="wab-template"></select><p class="wab-hint wab-note" style="display:none;"></p></div>' +
						'<div class="wab-field wab-lang-field" style="display:none;"><label>' + escHtml( i18n.language ) + '</label><select class="wab-lang"></select></div>' +
						'<div class="wab-cols">' +
							'<div class="wab-col">' +
								'<div class="wab-field"><label>' + escHtml( i18n.variables ) + '</label><div class="wab-vars"></div></div>' +
								'<div class="wab-field"><label>' + escHtml( i18n.buttons ) + '</label><div class="wab-btns"></div></div>' +
							'</div>' +
							'<div class="wab-col"><div class="wab-field"><label>' + escHtml( i18n.preview ) + '</label><div class="wab-preview"></div></div></div>' +
						'</div>' +
						'<div class="wab-results-wrap"></div>' +
					'</div>' +
					'<div class="wab-foot"><span class="wab-status"></span><span class="wab-spacer"></span>' +
						'<button type="button" class="button wab-cancel">' + escHtml( i18n.cancel ) + '</button> ' +
						'<button type="button" class="button button-primary wab-send">' + escHtml( i18n.send ) + '</button>' +
					'</div>' +
				'</div>' +
			'</div>'
		);
		$( 'body' ).append( $modal );

		$modal.on( 'click', '.wab-close, .wab-cancel', closeModal );
		$modal.on( 'click', function ( e ) { if ( e.target === $modal.get( 0 ) ) { closeModal(); } } );
		$modal.on( 'change', '.wab-template', onTemplateChange );
		$modal.on( 'change', '.wab-lang', function () {
			var def = curDef();
			renderVariables( def ); renderButtons( def ); renderPreview();
		} );
		$modal.on( 'change', '.wab-var', function () {
			$( this ).siblings( '.wab-var-custom' ).toggle( '__custom' === $( this ).val() );
			renderPreview();
		} );
		$modal.on( 'input', '.wab-var-custom', renderPreview );
	}

	function closeModal() {
		if ( $modal ) { $modal.hide(); }
	}

	function openModal( ids ) {
		ensureModal();
		$modal.find( '.wab-title' ).text( fmt( i18n.title, ids.length ) );
		$modal.find( '.wab-status' ).text( '' );
		$modal.find( '.wab-results-wrap' ).empty();
		$modal.find( '.wab-send' ).prop( 'disabled', false ).off( 'click' ).on( 'click', function () { doSend( ids ); } );
		$modal.show();

		if ( DEFS ) { buildTemplateSelect(); onTemplateChange(); return; }

		$modal.find( '.wab-vars' ).html( '<p class="wab-hint">' + escHtml( i18n.loading ) + '</p>' );
		$.post( cfg.ajax, { action: cfg.fetchAction, nonce: cfg.fetchNonce } )
			.done( function ( resp ) {
				if ( resp && resp.success && resp.data && resp.data.defs ) {
					DEFS = resp.data.defs;
					buildTemplateSelect();
					onTemplateChange();
				} else {
					$modal.find( '.wab-vars' ).html( '<p class="wab-hint">' + escHtml( i18n.loadFailed ) + '</p>' );
				}
			} )
			.fail( function () {
				$modal.find( '.wab-vars' ).html( '<p class="wab-hint">' + escHtml( i18n.loadFailed ) + '</p>' );
			} );
	}

	/* --------------------------------------------------- button + css  */

	function addButton() {
		var $bar = $( '.tablenav.top .bulkactions' ).first();
		if ( ! $bar.length || $bar.find( '.aaraa-wa-bulk-btn' ).length ) { return; }
		$( '<button type="button" class="button aaraa-wa-bulk-btn"></button>' )
			.text( i18n.button )
			.appendTo( $bar )
			.on( 'click', function ( e ) {
				e.preventDefault();
				if ( ! cfg.enabled ) { window.alert( i18n.disabled ); return; }
				var ids = selectedOrderIds();
				if ( ! ids.length ) { window.alert( i18n.noSelection ); return; }
				openModal( ids );
			} );
	}

	function injectCss() {
		var css =
			'.aaraa-wa-bulk-btn{margin-left:6px;}' +
			'.wab-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:flex-start;justify-content:center;padding:5vh 12px;}' +
			'.wab-modal{background:#fff;border-radius:8px;max-width:900px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.3);}' +
			'.wab-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e2e4e7;position:sticky;top:0;background:#fff;}' +
			'.wab-head strong{font-size:15px;}' +
			'.wab-close{border:0;background:none;font-size:24px;line-height:1;cursor:pointer;color:#646970;}' +
			'.wab-body{padding:16px 18px;}' +
			'.wab-field{margin:0 0 14px;}' +
			'.wab-field > label{display:block;font-weight:600;margin:0 0 5px;}' +
			'.wab-template,.wab-lang{width:100%;min-width:260px;max-width:420px;}' +
			'.wab-cols{display:grid;grid-template-columns:1fr 320px;gap:18px;}' +
			'@media(max-width:760px){.wab-cols{grid-template-columns:1fr;}}' +
			'.wab-hint{color:#646970;font-size:12px;}' +
			'.wab-map-row{display:flex;align-items:center;gap:8px;margin:0 0 8px;}' +
			'.wab-slot{flex:0 0 44px;font-weight:600;color:#2271b1;}' +
			'.wab-map-row select{min-width:170px;}' +
			'.wab-var-custom{flex:1;}' +
			'.wab-btn-wrap{margin:0 0 8px;}' +
			'.wab-btn-label{display:block;font-size:12px;margin:0 0 3px;}' +
			'.wab-preview{background:#e6ddd3;border-radius:8px;padding:12px;}' +
			'.wab-bubble{background:#fff;border-radius:8px;padding:10px 12px;box-shadow:0 1px 1px rgba(0,0,0,.13);font-size:13px;line-height:1.5;white-space:pre-wrap;word-break:break-word;}' +
			'.wab-bubble .wab-fill{background:#dff5e6;border-radius:3px;padding:0 3px;}' +
			'.wab-bubble .wab-miss{background:#fbeaea;color:#b32d2e;border-radius:3px;padding:0 3px;}' +
			'.wab-bubble-btns{margin-top:8px;border-top:1px solid #eee;}' +
			'.wab-bubble-btns .wab-bbtn{text-align:center;color:#0a7cff;padding:8px 4px 2px;font-weight:500;}' +
			'.wab-foot{display:flex;align-items:center;gap:10px;padding:12px 18px;border-top:1px solid #e2e4e7;position:sticky;bottom:0;background:#fff;}' +
			'.wab-spacer{flex:1;}' +
			'.wab-status{color:#1d2327;font-size:13px;}' +
			'.wab-results{margin:12px 0 0;max-height:160px;overflow:auto;font-size:12px;}' +
			'.wab-results li.ok{color:#1a7f45;}' +
			'.wab-results li.fail{color:#b32d2e;}';
		$( '<style id="aaraa-wa-bulk-css"></style>' ).text( css ).appendTo( 'head' );
	}

	$( function () {
		injectCss();
		addButton();
	} );

} )( jQuery );
