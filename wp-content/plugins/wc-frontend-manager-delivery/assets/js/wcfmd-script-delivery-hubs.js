jQuery( document ).ready( function ( $ ) {
	'use strict';

	var hubTable;

	/* ── DataTable ──────────────────────────────────────────────── */
	hubTable = $( '#wcfmd-delivery-hubs' ).DataTable( {
		ajax: {
			url    : wcfmd_hub_params.ajax_url,
			type   : 'POST',
			data   : { action: 'wcfmd_hub_list', nonce: wcfmd_hub_params.nonce },
			dataSrc: function ( json ) { return json.success ? json.data : []; }
		},
		columns: [
			{ data: 'id' },
			{ data: 'name' },
			{ data: 'area' },
			{ data: 'address' },
			{
				data  : 'status',
				render: function ( val, type ) {
					if ( type !== 'display' ) return val;
					var color = val === 'active' ? '#28a745' : '#6c757d';
					var label = val.charAt( 0 ).toUpperCase() + val.slice( 1 );
					return '<span style="color:' + color + ';font-weight:600;">' + label + '</span>';
				}
			},
			{ data: 'created' },
			{
				data     : null,
				orderable: false,
				render   : function ( data, type, row ) {
					return '<a href="#" class="wcfm-btn btn-xs btn-default wcfmd-hub-edit" data-id="' + row.id + '" style="margin-right:4px;">Edit</a>'
						+ '<a href="#" class="wcfm-btn btn-xs btn-danger wcfmd-hub-delete" data-id="' + row.id + '">Delete</a>';
				}
			}
		],
		order     : [ [ 0, 'desc' ] ],
		pageLength: 25,
		language  : { emptyTable: 'No delivery hubs found.' }
	} );

	/* ── Modal helpers ──────────────────────────────────────────── */
	function openModal() {
		$( '#wcfmd-hub-overlay' ).fadeIn( 150 );
		$( '#wcfmd-hub-modal' ).fadeIn( 150 );
	}
	function closeModal() {
		$( '#wcfmd-hub-overlay, #wcfmd-hub-modal' ).fadeOut( 150 );
	}
	function showModalMsg( text, ok ) {
		$( '#wcfmd-hub-modal-msg' )
			.text( text )
			.css( {
				background: ok ? '#d4edda' : '#f8d7da',
				color     : ok ? '#155724' : '#721c24',
				border    : '1px solid ' + ( ok ? '#c3e6cb' : '#f5c6cb' )
			} )
			.show();
	}
	function showPageMsg( text, ok ) {
		$( '#wcfmd-hub-page-msg' )
			.text( text )
			.css( {
				background: ok ? '#d4edda' : '#f8d7da',
				color     : ok ? '#155724' : '#721c24',
				border    : '1px solid ' + ( ok ? '#c3e6cb' : '#f5c6cb' )
			} )
			.show();
		setTimeout( function () { $( '#wcfmd-hub-page-msg' ).fadeOut(); }, 3000 );
	}

	$( '#wcfmd-hub-overlay, #wcfmd-hub-modal-close' ).on( 'click', function ( e ) {
		e.preventDefault();
		closeModal();
	} );

	/* ── Add New ────────────────────────────────────────────────── */
	$( document ).on( 'click', '#wcfmd-hub-add-btn', function ( e ) {
		e.preventDefault();
		$( '#wcfmd-hub-id' ).val( '' );
		$( '#wcfmd-hub-name' ).val( '' );
		$( '#wcfmd-hub-area' ).val( '' );
		$( '#wcfmd-hub-address' ).val( '' );
		$( '#wcfmd-hub-status' ).val( 'active' );
		$( '#wcfmd-hub-modal-title' ).text( 'Add Delivery Hub' );
		$( '#wcfmd-hub-modal-msg' ).hide();
		openModal();
	} );

	/* ── Edit ───────────────────────────────────────────────────── */
	$( document ).on( 'click', '.wcfmd-hub-edit', function ( e ) {
		e.preventDefault();
		var id = $( this ).data( 'id' );
		$.post( wcfmd_hub_params.ajax_url, {
			action : 'wcfmd_hub_get',
			nonce  : wcfmd_hub_params.nonce,
			hub_id : id
		}, function ( resp ) {
			if ( ! resp.success ) return;
			var h = resp.data;
			$( '#wcfmd-hub-id' ).val( h.ID );
			$( '#wcfmd-hub-name' ).val( h.name );
			$( '#wcfmd-hub-area' ).val( h.area );
			$( '#wcfmd-hub-address' ).val( h.address );
			$( '#wcfmd-hub-status' ).val( h.status );
			$( '#wcfmd-hub-modal-title' ).text( 'Edit Delivery Hub' );
			$( '#wcfmd-hub-modal-msg' ).hide();
			openModal();
		} );
	} );

	/* ── Save ───────────────────────────────────────────────────── */
	$( document ).on( 'click', '#wcfmd-hub-save', function () {
		var name = $( '#wcfmd-hub-name' ).val().trim();
		if ( ! name ) { showModalMsg( 'Hub name is required.', false ); return; }

		var $btn = $( this ).text( 'Saving...' ).prop( 'disabled', true );

		$.post( wcfmd_hub_params.ajax_url, {
			action      : 'wcfmd_hub_save',
			nonce       : wcfmd_hub_params.nonce,
			hub_id      : $( '#wcfmd-hub-id' ).val(),
			hub_name    : name,
			hub_area    : $( '#wcfmd-hub-area' ).val(),
			hub_address : $( '#wcfmd-hub-address' ).val(),
			hub_status  : $( '#wcfmd-hub-status' ).val()
		}, function ( resp ) {
			$btn.text( 'SAVE HUB' ).prop( 'disabled', false );
			if ( resp.success ) {
				showModalMsg( resp.data.message, true );
				hubTable.ajax.reload( null, false );
				setTimeout( closeModal, 1000 );
			} else {
				showModalMsg( resp.data || 'Something went wrong.', false );
			}
		} ).fail( function () {
			$btn.text( 'SAVE HUB' ).prop( 'disabled', false );
			showModalMsg( 'Request failed. Try again.', false );
		} );
	} );

	/* ── Delete ─────────────────────────────────────────────────── */
	$( document ).on( 'click', '.wcfmd-hub-delete', function ( e ) {
		e.preventDefault();
		if ( ! confirm( 'Are you sure you want to delete this hub?' ) ) return;
		var id = $( this ).data( 'id' );
		$.post( wcfmd_hub_params.ajax_url, {
			action : 'wcfmd_hub_delete',
			nonce  : wcfmd_hub_params.nonce,
			hub_id : id
		}, function ( resp ) {
			if ( resp.success ) {
				showPageMsg( resp.data.message, true );
				hubTable.ajax.reload( null, false );
			}
		} );
	} );
} );
