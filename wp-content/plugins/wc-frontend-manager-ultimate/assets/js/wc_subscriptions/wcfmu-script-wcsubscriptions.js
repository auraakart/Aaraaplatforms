$wcfm_subscriptions_table = '';
$subscription_status      = '';
$subscription_product     = '';
$subscription_filter      = '';

jQuery( document ).ready(
	function ($) {
		$wcfm_subscriptions_table = $( '#wcfm-subscriptions' ).DataTable(
			{
				"processing": true,
				"serverSide": true,
				"pageLength": parseInt( dataTables_config.pageLength ),
				"bFilter": true,
				"responsive": { "details": { "type": "column", "target": -1 } },
				"language": $.parseJSON( dataTables_language ),
				"columns": [
				{ responsivePriority: 1, data: null, defaultContent: "" },
				{ responsivePriority: 2, data: 0 },
				{ responsivePriority: 1, data: 1 },
				{ responsivePriority: 4, data: 2 },
				{ responsivePriority: 5, data: 3 },
				{ responsivePriority: 3, data: 4 },
				{ responsivePriority: 6, data: 5 },
				{ responsivePriority: 7, data: 6 },
				{ responsivePriority: 8, data: 7 },
				{ responsivePriority: 9, data: 8 },
				{ responsivePriority: 3, data: 9 },
				{ responsivePriority: 9, data: 10 },
				{ responsivePriority: 1, data: 11 }
				],
				"columnDefs": [
				{ "targets": 0, "orderable": false, "searchable": false, "width": "36px" },
				{ "targets": 1, "orderable": false },
				{ "targets": 2, "orderable": false },
				{ "targets": 3, "orderable": false },
				{ "targets": 4, "orderable": false },
				{ "targets": 5, "orderable": false },
				{ "targets": 6, "orderable": false },
				{ "targets": 7, "orderable": false },
				{ "targets": 8, "orderable": false },
				{ "targets": 9, "orderable": false },
				{ "targets": 10, "orderable": false },
				{ "targets": 11, "orderable": false },
				{ "targets": 12, "orderable": false },
				],
				"drawCallback": function() {
					var api = this.api();
					api.rows().every(function() {
						var $row = $( this.node() );
						var $firstTd = $row.find( 'td:first-child' );
						var subId = $row.find( 'span[data-sub-id]' ).data( 'sub-id' );
						if (subId && !$firstTd.find( '.wcfm-subs-bulk-select' ).length) {
							$firstTd.html( '<input type="checkbox" class="wcfm-subs-bulk-select" data-id="' + subId + '" style="vertical-align:middle;">' );
						}
					});
					wcfmSubsUpdateBulkBar();
				},
				'ajax': {
					"type": "POST",
					"url": wcfm_params.ajax_url,
					"data": function ( d ) {
						d.action               = 'wcfm_ajax_controller',
						d.controller           = 'wcfm-subscriptions',
						d.subscription_status  = GetURLParameter( 'subscription_status' ),
						d.subscription_product = $subscription_product,
						d.subscription_filter  = $subscription_filter,
						d.filter_date_form     = $filter_date_form,
						d.filter_date_to       = $filter_date_to,
						d.wcfm_ajax_nonce      = wcfm_params.wcfm_ajax_nonce
					},
					"complete": function () {
						initiateTip();

						// Fire wcfm-subscriptions table refresh complete
						$( document.body ).trigger( 'updated_wcfm-subscriptions' );
					}
				}
			}
		);

		// Bulk selection helpers
		function wcfmSubsUpdateBulkBar() {
			var count = $( '#wcfm-subscriptions' ).find( '.wcfm-subs-bulk-select:checked' ).length;
			$( '#wcfm-subs-selected-count' ).text( count + ' selected' );
			if (count > 0) {
				$( '#wcfm-subs-bulk-bar' ).css( 'display', 'flex' );
			} else {
				$( '#wcfm-subs-bulk-bar' ).hide();
				$( '#wcfm-subs-bulk-result' ).text( '' ).css( 'color', 'green' );
			}
		}

		// Select All
		$( document ).on( 'change', '#wcfm-subs-check-all', function() {
			var checked = $( this ).is( ':checked' );
			$( '#wcfm-subscriptions' ).find( '.wcfm-subs-bulk-select' ).prop( 'checked', checked );
			wcfmSubsUpdateBulkBar();
		});

		// Prevent DataTables responsive from intercepting checkbox clicks
		$( document ).on( 'click', '#wcfm-subscriptions .wcfm-subs-bulk-select', function( e ) {
			e.stopPropagation();
		});

		// Per-row checkbox
		$( document ).on( 'change', '#wcfm-subscriptions .wcfm-subs-bulk-select', function() {
			var $cbs    = $( '#wcfm-subscriptions' ).find( '.wcfm-subs-bulk-select' );
			var total    = $cbs.length;
			var selected = $cbs.filter( ':checked' ).length;
			$( '#wcfm-subs-check-all' ).prop( 'indeterminate', selected > 0 && selected < total );
			$( '#wcfm-subs-check-all' ).prop( 'checked', total > 0 && selected === total );
			wcfmSubsUpdateBulkBar();
		});

		// Bulk Apply
		$( document ).on( 'click', '#wcfm-subs-bulk-apply', function() {
			var status = $( '#wcfm-subs-bulk-status' ).val();
			if (!status) { alert( 'Please select a status.' ); return; }
			var ids = [];
			$( '#wcfm-subscriptions' ).find( '.wcfm-subs-bulk-select:checked' ).each(function() { ids.push( $( this ).data( 'id' ) ); });
			if (!ids.length) { alert( 'Please select at least one subscription.' ); return; }
			var $btn = $( this );
			$btn.prop( 'disabled', true );
			$( '#wcfm-subs-bulk-result' ).text( '' ).css( 'color', 'green' );
			$.ajax({
				type: 'POST',
				url: wcfm_params.ajax_url,
				data: {
					action: 'wcfm_bulk_subscription_status',
					sub_ids: ids,
					sub_status: status,
					wcfm_ajax_nonce: wcfm_params.wcfm_ajax_nonce
				},
				success: function(resp) {
					if (resp.success) {
						$( '#wcfm-subs-bulk-result' ).css( 'color', 'green' ).text( resp.data.message || 'Status updated.' );
						$wcfm_subscriptions_table.ajax.reload( null, false );
						$( '#wcfm-subs-check-all' ).prop( 'checked', false ).prop( 'indeterminate', false );
					} else {
						$( '#wcfm-subs-bulk-result' ).css( 'color', 'red' ).text( resp.data || 'Error updating status.' );
					}
					$btn.prop( 'disabled', false );
				}
			});
		});

		if ($( '#subscription_product' ).length > 0) {
			$( '#subscription_product' ).on(
				'change',
				function () {
					$subscription_product = $( '#subscription_product' ).val();
					$wcfm_subscriptions_table.ajax.reload();
				}
			).select2( $wcfm_product_select_args );
		}

		$( document.body ).on(
			'wcfm-date-range-refreshed',
			function () {
				$wcfm_subscriptions_table.ajax.reload();
			}
		);

		// Dashboard FIlter
		if ($( '.wcfm_filters_wrap' ).length > 0) {
			$( '.dataTable' ).before( $( '.wcfm_filters_wrap' ) );
			$( '.wcfm_filters_wrap' ).css( 'display', 'inline-block' );
		}

		// Screen Manager
		$( document.body ).on(
			'updated_wcfm-subscriptions',
			function () {
				$.each(
					wcfm_subscriptions_screen_manage,
					function ( column, column_val ) {
						$wcfm_subscriptions_table.column( parseInt( column ) + 1 ).visible( false );
					}
				);
			}
		);
	}
);

/* ── Create Subscription Modal ──────────────────────────────────────────── */
jQuery( document ).ready( function ( $ ) {
	'use strict';

	if ( typeof wcfmu_sub_create_params === 'undefined' ) { return; }

	var subCustTimer;
	var subProdTimer;

	function openSubModal() {
		$( '#wcfmu-sub-create-overlay' ).fadeIn( 150 );
		$( '#wcfmu-sub-create-modal' ).fadeIn( 150 );
	}
	function closeSubModal() {
		$( '#wcfmu-sub-create-overlay, #wcfmu-sub-create-modal' ).fadeOut( 150 );
	}
	function showSubMsg( text, ok ) {
		$( '#wcfmu-sub-create-msg' )
			.text( text )
			.css( {
				background: ok ? '#d4edda' : '#f8d7da',
				color     : ok ? '#155724' : '#721c24',
				border    : '1px solid ' + ( ok ? '#c3e6cb' : '#f5c6cb' )
			} )
			.show();
	}
	function renderAcList( $results, items ) {
		if ( ! items || ! items.length ) {
			$results.html( '<div style="padding:9px 12px;color:#999;font-size:13px;">No results found</div>' ).show();
			return;
		}
		var html = '';
		$.each( items, function ( i, item ) {
			html += '<div class="wcfmu-ac-result" '
				+ 'data-id="' + item.id + '" '
				+ 'data-text="' + $( '<span>' ).text( item.text ).html() + '" '
				+ 'style="padding:9px 12px;cursor:pointer;border-bottom:1px solid #f5f5f5;font-size:13px;">'
				+ $( '<span>' ).text( item.text ).html()
				+ '</div>';
		} );
		$results.html( html ).show();
	}

	$( document ).on( 'click', '#wcfmu-sub-create-btn', function ( e ) {
		e.preventDefault();
		$( '#wcfmu-sub-customer-search, #wcfmu-sub-product-search' ).val( '' );
		$( '#wcfmu-sub-customer-id, #wcfmu-sub-product-id' ).val( '' );
		$( '#wcfmu-sub-quantity' ).val( 1 );
		$( 'input[name="wcfmu_sub_schedule"]' ).prop( 'checked', false );
		$( '.wcfmu-sub-day-check' ).prop( 'checked', false );
		$( '#wcfmu-sub-custom-days' ).hide();
		$( '#wcfmu-sub-start-date' ).val( '' );
		$( '#wcfmu-sub-payment-method' ).val( '' );
		$( '#wcfmu-sub-status' ).val( 'active' );
		$( '#wcfmu-sub-create-msg' ).hide();
		openSubModal();
	} );

	$( document ).on( 'click', '.wcfmu-sub-modal-close, #wcfmu-sub-create-overlay', function ( e ) {
		e.preventDefault();
		closeSubModal();
	} );

	$( document ).on( 'change', 'input[name="wcfmu_sub_schedule"]', function () {
		if ( $( this ).val() === 'custom' ) {
			$( '#wcfmu-sub-custom-days' ).slideDown( 200 );
		} else {
			$( '#wcfmu-sub-custom-days' ).slideUp( 200 );
		}
	} );

	$( document ).on( 'input', '#wcfmu-sub-customer-search', function () {
		clearTimeout( subCustTimer );
		$( '#wcfmu-sub-customer-id' ).val( '' );
		var q = $( this ).val().trim();
		if ( q.length < 2 ) { $( '#wcfmu-sub-customer-results' ).hide(); return; }
		subCustTimer = setTimeout( function () {
			$.post( wcfmu_sub_create_params.ajax_url, {
				action: 'wcfm_search_sub_customers',
				nonce : wcfmu_sub_create_params.nonce,
				q     : q
			}, function ( resp ) {
				renderAcList( $( '#wcfmu-sub-customer-results' ), resp.results );
			} );
		}, 300 );
	} );

	$( document ).on( 'click', '#wcfmu-sub-customer-results .wcfmu-ac-result', function () {
		$( '#wcfmu-sub-customer-id' ).val( $( this ).data( 'id' ) );
		$( '#wcfmu-sub-customer-search' ).val( $( this ).data( 'text' ) );
		$( '#wcfmu-sub-customer-results' ).hide();
	} );

	$( document ).on( 'input', '#wcfmu-sub-product-search', function () {
		clearTimeout( subProdTimer );
		$( '#wcfmu-sub-product-id' ).val( '' );
		var q = $( this ).val().trim();
		if ( q.length < 2 ) { $( '#wcfmu-sub-product-results' ).hide(); return; }
		subProdTimer = setTimeout( function () {
			$.post( wcfmu_sub_create_params.ajax_url, {
				action: 'wcfm_search_sub_products',
				nonce : wcfmu_sub_create_params.nonce,
				q     : q
			}, function ( resp ) {
				renderAcList( $( '#wcfmu-sub-product-results' ), resp.results );
			} );
		}, 300 );
	} );

	$( document ).on( 'click', '#wcfmu-sub-product-results .wcfmu-ac-result', function () {
		$( '#wcfmu-sub-product-id' ).val( $( this ).data( 'id' ) );
		$( '#wcfmu-sub-product-search' ).val( $( this ).data( 'text' ) );
		$( '#wcfmu-sub-product-results' ).hide();
	} );

	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#wcfmu-sub-customer-search, #wcfmu-sub-customer-results' ).length ) {
			$( '#wcfmu-sub-customer-results' ).hide();
		}
		if ( ! $( e.target ).closest( '#wcfmu-sub-product-search, #wcfmu-sub-product-results' ).length ) {
			$( '#wcfmu-sub-product-results' ).hide();
		}
	} );

	$( document ).on( 'click', '#wcfmu-sub-create-submit', function () {
		var customerId   = $( '#wcfmu-sub-customer-id' ).val();
		var productId    = $( '#wcfmu-sub-product-id' ).val();
		var scheduleType = $( 'input[name="wcfmu_sub_schedule"]:checked' ).val();
		var customDays   = [];
		$( '.wcfmu-sub-day-check:checked' ).each( function () { customDays.push( $( this ).val() ); } );

		if ( ! customerId )   { showSubMsg( 'Please select a customer.', false ); return; }
		if ( ! productId )    { showSubMsg( 'Please select a product.', false );  return; }
		if ( ! scheduleType ) { showSubMsg( 'Please select a schedule type.', false ); return; }
		if ( scheduleType === 'custom' && ! customDays.length ) {
			showSubMsg( 'Please select at least one day for custom schedule.', false );
			return;
		}

		var $btn = $( this ).text( 'Creating...' ).prop( 'disabled', true );
		$( '#wcfmu-sub-create-msg' ).hide();

		var postData = {
			action         : 'wcfm_create_subscription',
			nonce          : wcfmu_sub_create_params.nonce,
			customer_id    : customerId,
			product_id     : productId,
			quantity       : $( '#wcfmu-sub-quantity' ).val() || 1,
			schedule_type  : scheduleType,
			start_date     : $( '#wcfmu-sub-start-date' ).val(),
			payment_method : $( '#wcfmu-sub-payment-method' ).val(),
			status         : $( '#wcfmu-sub-status' ).val()
		};

		if ( scheduleType === 'custom' ) {
			postData.custom_days = customDays;
		}

		$.post( wcfmu_sub_create_params.ajax_url, postData, function ( resp ) {
			$btn.text( 'CREATE SUBSCRIPTION' ).prop( 'disabled', false );
			if ( resp.success ) {
				showSubMsg( 'Subscription #' + resp.data.id + ' created (Order #' + resp.data.order_id + ') | Schedule: ' + resp.data.schedule + ' | Next payment: ' + ( resp.data.next_payment || '-' ), true );
				if ( typeof $wcfm_subscriptions_table !== 'undefined' ) {
					$wcfm_subscriptions_table.ajax.reload( null, false );
				}
				setTimeout( function () { closeSubModal(); }, 1800 );
			} else {
				showSubMsg( resp.data || 'Something went wrong.', false );
			}
		} ).fail( function () {
			$btn.text( 'CREATE SUBSCRIPTION' ).prop( 'disabled', false );
			showSubMsg( 'Request failed. Please try again.', false );
		} );
	} );

} );
