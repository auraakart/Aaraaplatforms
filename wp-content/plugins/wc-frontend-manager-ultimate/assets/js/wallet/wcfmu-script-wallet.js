jQuery( document ).ready( function ( $ ) {
	'use strict';

	var walletUsersTable;
	var walletTransTable;
	var transAjaxData = {};
	var searchTimer;

	/* ── Wallet Users table ─────────────────────────────────────── */
	walletUsersTable = $( '#wcfmu-wallet-users' ).DataTable( {
		'ajax': {
			'url'    : wcfm_wallet_params.ajax_url,
			'type'   : 'POST',
			'data'   : { action: 'wcfm_wallet_list', nonce: wcfm_wallet_params.nonce },
			'dataSrc': function ( json ) { return json.success ? json.data : []; }
		},
		'columns': [
			{ data: 'id' },
			{ data: 'name' },
			{ data: 'email' },
			{
				data: 'balance_raw',
				render: function ( val, type, row ) {
					if ( type === 'display' ) return row.balance;
					return val;
				}
			},
			{
				data    : null,
				orderable: false,
				defaultContent: '',
				render  : function ( data, type, row ) {
					return '<a href="#" class="wcfm-btn btn-xs btn-default wcfmu-edit-wallet" '
						+ 'data-user-id="' + row.id + '" '
						+ 'data-user-name="' + row.name + '" '
						+ 'data-balance-raw="' + row.balance_raw + '" '
						+ 'style="margin-right:4px;">Edit</a>'
						+ '<a href="#" class="wcfm-btn btn-xs btn-default wcfmu-view-transactions" '
						+ 'data-user-id="' + row.id + '" data-user-name="' + row.name + '">View</a>';
				}
			}
		],
		'order'     : [ [ 3, 'desc' ] ],
		'pageLength': 25,
		'language'  : { 'emptyTable': 'No wallet users found.' }
	} );

	/* ── Wallet Transactions table ──────────────────────────────── */
	transAjaxData = { action: 'wcfm_wallet_transactions', nonce: wcfm_wallet_params.nonce };

	walletTransTable = $( '#wcfmu-wallet-transactions' ).DataTable( {
		'ajax': {
			'url'    : wcfm_wallet_params.ajax_url,
			'type'   : 'POST',
			'data'   : function () { return $.extend( {}, transAjaxData ); },
			'dataSrc': function ( json ) { return json.success ? json.data : []; }
		},
		'columns': [
			{ data: 'id' },
			{
				data  : 'user_name',
				render: function ( val, type, row ) {
					return val + '<br><small style="color:#999;">' + row.user_email + '</small>';
				}
			},
			{
				data  : 'amount_raw',
				render: function ( val, type, row ) {
					if ( type === 'display' ) return row.amount;
					return val;
				}
			},
			{
				data  : 'direction',
				render: function ( val, type ) {
					if ( type !== 'display' ) return val;
					var color = val === 'credit' ? '#28a745' : '#dc3545';
					var label = val ? val.charAt(0).toUpperCase() + val.slice(1) : '-';
					return '<span style="color:' + color + ';font-weight:600;">' + label + '</span>';
				}
			},
			{ data: 'type' },
			{ data: 'payment_method' },
			{ data: 'note' },
			{ data: 'date' }
		],
		'order'     : [ [ 0, 'desc' ] ],
		'pageLength': 25,
		'language'  : { 'emptyTable': 'No transactions found.' }
	} );

	/* ── Tab switching ──────────────────────────────────────────── */
	$( document ).on( 'click', '.wcfmu-wallet-tab', function ( e ) {
		e.preventDefault();
		var tab = $( this ).data( 'tab' );
		$( '.wcfmu-wallet-tab' ).css( { background: '#f1f1f1', color: '#555', fontWeight: 'normal' } );
		$( this ).css( { background: '#fff', color: '#23282d', fontWeight: '600' } );
		$( '.wcfmu-wallet-tab-content' ).hide();
		$( '#wcfmu-wallet-tab-' + tab ).show();
		if ( tab === 'transactions' ) walletTransTable.columns.adjust();
		else walletUsersTable.columns.adjust();
	} );

	/* ── View transactions for a specific user ──────────────────── */
	$( document ).on( 'click', '.wcfmu-view-transactions', function ( e ) {
		e.preventDefault();
		var userId   = $( this ).data( 'user-id' );
		var userName = $( this ).data( 'user-name' );
		$( '.wcfmu-wallet-tab[data-tab="transactions"]' ).trigger( 'click' );
		transAjaxData = { action: 'wcfm_wallet_transactions', nonce: wcfm_wallet_params.nonce, user_id: userId };
		walletTransTable.ajax.reload();
		$( '#wcfmu-wallet-filter-name' ).text( userName );
		$( '#wcfmu-wallet-user-filter' ).show();
	} );

	/* ── Clear user filter ──────────────────────────────────────── */
	$( document ).on( 'click', '#wcfmu-wallet-clear-filter', function ( e ) {
		e.preventDefault();
		transAjaxData = { action: 'wcfm_wallet_transactions', nonce: wcfm_wallet_params.nonce };
		walletTransTable.ajax.reload();
		$( '#wcfmu-wallet-user-filter' ).hide();
	} );

	/* ── Modal helpers ──────────────────────────────────────────── */
	function openModal( key ) {
		$( '#wcfmu-wallet-' + key + '-overlay' ).fadeIn( 150 );
		$( '#wcfmu-wallet-' + key + '-modal' ).fadeIn( 150 );
	}
	function closeModal( key ) {
		$( '#wcfmu-wallet-' + key + '-overlay, #wcfmu-wallet-' + key + '-modal' ).fadeOut( 150 );
	}
	function showMsg( key, text, ok ) {
		var $msg = $( '#wcfmu-wallet-' + key + '-msg' );
		$msg.text( text )
			.css( { background: ok ? '#d4edda' : '#f8d7da', color: ok ? '#155724' : '#721c24', border: '1px solid ' + ( ok ? '#c3e6cb' : '#f5c6cb' ) } )
			.show();
	}

	$( document ).on( 'click', '.wcfmu-modal-close', function ( e ) {
		e.preventDefault();
		closeModal( $( this ).data( 'modal' ) );
	} );
	$( document ).on( 'click', '#wcfmu-wallet-edit-overlay, #wcfmu-wallet-add-overlay', function () {
		closeModal( 'edit' );
		closeModal( 'add' );
	} );

	/* ── Edit Wallet ────────────────────────────────────────────── */
	$( document ).on( 'click', '.wcfmu-edit-wallet', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		$( '#wcfmu-wallet-edit-user-id' ).val( $btn.data( 'user-id' ) );
		$( '#wcfmu-wallet-edit-title' ).text( 'Update Wallet — ' + $btn.data( 'user-name' ) );
		$( '#wcfmu-wallet-edit-amount' ).val( '' );
		$( '#wcfmu-wallet-edit-note' ).val( '' );
		$( 'input[name="wcfmu_edit_action"]' ).prop( 'checked', false );
		$( '#wcfmu-wallet-edit-msg' ).hide();
		openModal( 'edit' );
	} );

	$( document ).on( 'click', '#wcfmu-wallet-edit-submit', function () {
		var userId  = $( '#wcfmu-wallet-edit-user-id' ).val();
		var amount  = $( '#wcfmu-wallet-edit-amount' ).val();
		var action  = $( 'input[name="wcfmu_edit_action"]:checked' ).val();
		var note    = $( '#wcfmu-wallet-edit-note' ).val();

		if ( ! amount || parseFloat( amount ) <= 0 ) { showMsg( 'edit', 'Please enter a valid amount.', false ); return; }
		if ( ! action ) { showMsg( 'edit', 'Please select Credit or Debit.', false ); return; }

		var $btn = $( this ).text( 'Updating...' ).prop( 'disabled', true );

		$.post( wcfm_wallet_params.ajax_url, {
			action     : 'wcfm_wallet_update',
			nonce      : wcfm_wallet_params.nonce,
			user_id    : userId,
			amount     : amount,
			action_type: action,
			note       : note
		}, function ( resp ) {
			$btn.text( 'UPDATE WALLET' ).prop( 'disabled', false );
			if ( resp.success ) {
				showMsg( 'edit', 'Wallet updated successfully! New balance: ' + resp.data.formatted, true );
				walletUsersTable.ajax.reload( null, false );
				walletTransTable.ajax.reload( null, false );
				setTimeout( function () { closeModal( 'edit' ); }, 1200 );
			} else {
				showMsg( 'edit', resp.data || 'Something went wrong.', false );
			}
		} ).fail( function () {
			$btn.text( 'UPDATE WALLET' ).prop( 'disabled', false );
			showMsg( 'edit', 'Request failed. Please try again.', false );
		} );
	} );

	/* ── Add Wallet ─────────────────────────────────────────────── */
	$( document ).on( 'click', '#wcfmu-wallet-add-btn', function ( e ) {
		e.preventDefault();
		$( '#wcfmu-wallet-user-search' ).val( '' );
		$( '#wcfmu-wallet-add-user-id' ).val( '' );
		$( '#wcfmu-wallet-add-amount' ).val( '' );
		$( '#wcfmu-wallet-add-note' ).val( '' );
		$( 'input[name="wcfmu_add_action"]' ).prop( 'checked', false );
		$( '#wcfmu-wallet-user-results' ).hide();
		$( '#wcfmu-wallet-add-msg' ).hide();
		openModal( 'add' );
	} );

	/* Customer search autocomplete */
	$( document ).on( 'input', '#wcfmu-wallet-user-search', function () {
		clearTimeout( searchTimer );
		$( '#wcfmu-wallet-add-user-id' ).val( '' );
		var q = $( this ).val().trim();
		if ( q.length < 2 ) { $( '#wcfmu-wallet-user-results' ).hide(); return; }

		searchTimer = setTimeout( function () {
			$.post( wcfm_wallet_params.ajax_url, {
				action: 'wcfm_wallet_search_users',
				nonce : wcfm_wallet_params.nonce,
				q     : q
			}, function ( resp ) {
				if ( ! resp.results || ! resp.results.length ) {
					$( '#wcfmu-wallet-user-results' ).html( '<div style="padding:9px 12px;color:#999;font-size:13px;">No users found</div>' ).show();
					return;
				}
				var html = '';
				$.each( resp.results, function ( i, user ) {
					html += '<div class="wcfmu-user-result" data-id="' + user.id + '" data-name="' + user.text + '" '
						+ 'style="padding:9px 12px;cursor:pointer;border-bottom:1px solid #f5f5f5;font-size:13px;"'
						+ ' onmouseenter="this.style.background=\'#f5f5f5\'" onmouseleave="this.style.background=\'\'>"'
						+ '>' + user.text + '</div>';
				} );
				$( '#wcfmu-wallet-user-results' ).html( html ).show();
			} );
		}, 300 );
	} );

	$( document ).on( 'click', '.wcfmu-user-result', function () {
		$( '#wcfmu-wallet-add-user-id' ).val( $( this ).data( 'id' ) );
		$( '#wcfmu-wallet-user-search' ).val( $( this ).data( 'name' ) );
		$( '#wcfmu-wallet-user-results' ).hide();
	} );

	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#wcfmu-wallet-user-search, #wcfmu-wallet-user-results' ).length ) {
			$( '#wcfmu-wallet-user-results' ).hide();
		}
	} );

	$( document ).on( 'click', '#wcfmu-wallet-add-submit', function () {
		var userId  = $( '#wcfmu-wallet-add-user-id' ).val();
		var amount  = $( '#wcfmu-wallet-add-amount' ).val();
		var action  = $( 'input[name="wcfmu_add_action"]:checked' ).val();
		var note    = $( '#wcfmu-wallet-add-note' ).val();

		if ( ! userId ) { showMsg( 'add', 'Please select a customer.', false ); return; }
		if ( ! amount || parseFloat( amount ) <= 0 ) { showMsg( 'add', 'Please enter a valid amount.', false ); return; }
		if ( ! action ) { showMsg( 'add', 'Please select Credit or Debit.', false ); return; }

		var $btn = $( this ).text( 'Updating...' ).prop( 'disabled', true );

		$.post( wcfm_wallet_params.ajax_url, {
			action     : 'wcfm_wallet_update',
			nonce      : wcfm_wallet_params.nonce,
			user_id    : userId,
			amount     : amount,
			action_type: action,
			note       : note
		}, function ( resp ) {
			$btn.text( 'UPDATE WALLET' ).prop( 'disabled', false );
			if ( resp.success ) {
				showMsg( 'add', 'Wallet updated successfully! New balance: ' + resp.data.formatted, true );
				walletUsersTable.ajax.reload( null, false );
				walletTransTable.ajax.reload( null, false );
				setTimeout( function () { closeModal( 'add' ); }, 1200 );
			} else {
				showMsg( 'add', resp.data || 'Something went wrong.', false );
			}
		} ).fail( function () {
			$btn.text( 'UPDATE WALLET' ).prop( 'disabled', false );
			showMsg( 'add', 'Request failed. Please try again.', false );
		} );
	} );
} );
