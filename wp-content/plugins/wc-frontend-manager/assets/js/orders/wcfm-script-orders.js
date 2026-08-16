$wcfm_orders_table = '';
$order_status = '';	
$filter_by_date = '';
$order_product = '';
$commission_status = '';
$order_vendor = '';
$delivery_boy = '';
var orderTableRefrsherTime = '';

jQuery(document).ready(function($) {
		
	$order_vendor = GetURLParameter( 'order_vendor' );
		
	// Dummy Mark Complete Dummy
	$( document.body ).on( 'updated_wcfm-orders', function() {
		$('.wcfm_order_mark_complete_dummy').each(function() {
			$(this).click(function(event) {
				event.preventDefault();
				alert( wcfm_dashboard_messages.wcfmu_upgrade_notice );
				return false;
			});
		});
	});
	
	// Invoice Dummy
	$( document.body ).on( 'updated_wcfm-orders', function() {
		$('.wcfm_pdf_invoice_dummy').each(function() {
			$(this).click(function(event) {
				event.preventDefault();
				alert( wcfm_dashboard_messages.pdf_invoice_upgrade_notice );
				return false;
			});
		});
	});
	
	// Invoice dummy - vendor
	$( document.body ).on( 'updated_wcfm-orders', function() {
		$('.wcfm_pdf_invoice_vendor_dummy').each(function() {
			$(this).click(function(event) {
				event.preventDefault();
				alert( wcfm_dashboard_messages.wcfmu_missing_feature );
				return false;
			});
		});
	});
	
	// Mark Shipped dummy - vendor
	$( document.body ).on( 'updated_wcfm-orders', function() {
		$('.wcfm_wcvendors_order_mark_shipped_dummy').each(function() {
			$(this).click(function(event) {
				event.preventDefault();
				alert( wcfm_dashboard_messages.wcfmu_missing_feature );
				return false;
			});
		});
	});
	
	if( dataTables_config.is_allow_hidden_export ) {
		$wcfm_datatable_button_args = [
																		{
																			extend: 'print',
																		},
																		{
																			extend: 'pdfHtml5',
																			orientation: 'landscape',
																			pageSize: 'LEGAL'
																		},
																		{
																			extend: 'excelHtml5',
																		}, 
																		{
																			extend: 'csv',
																		}
																	];
	}
	
	// Build columns/defs with prepended checkbox column
	var $wcfm_orders_cols = $.parseJSON(wcfm_datatable_columns.priority);
	$wcfm_orders_cols.unshift({ "responsivePriority": 1, "data": null, "defaultContent": "" });
	// Remap data indices: original column N is now at position N+1, so it must read data[N]
	for (var i = 1; i < $wcfm_orders_cols.length; i++) {
		$wcfm_orders_cols[i].data = i - 1;
	}
	var $wcfm_orders_defs = $.parseJSON(wcfm_datatable_columns.defs);
	$wcfm_orders_defs = $wcfm_orders_defs.map(function(d) { d.targets = d.targets + 1; return d; });
	$wcfm_orders_defs.unshift({ "targets": 0, "data": null, "defaultContent": "", "orderable": false, "searchable": false, "width": "36px" });

	$wcfm_orders_table = $('#wcfm-orders').DataTable( {
		"processing": true,
		"serverSide": true,
		"responsive": { "details": { "type": "column", "target": -1 } },
		"bFilter"   : wcfm_datatable_columns.bFilter,
		"pageLength": parseInt(dataTables_config.pageLength),
		"dom"       : 'Bfrtip',
		"language"  : $.parseJSON(dataTables_language),
    "buttons"   : $wcfm_datatable_button_args,
		"columns"   : $wcfm_orders_cols,
		"columnDefs": $wcfm_orders_defs,
		"drawCallback": function() {
			var api = this.api();
			api.rows().every(function() {
				var $row = $(this.node());
				var $firstTd = $row.find('td:first-child');
				var orderId = $firstTd.next('td').find('span[data-order-id]').data('order-id');
				if (!orderId) orderId = $firstTd.find('span[data-order-id]').data('order-id');
				if (orderId && !$firstTd.find('.wcfm-bulk-select').length) {
					$firstTd.html('<input type="checkbox" class="wcfm-bulk-select" data-id="' + orderId + '" style="vertical-align:middle;">');
				}
			});
			wcfmOrdersUpdateBulkBar();
		},
		'ajax': {
			"type"   : "POST",
			"url"    : wcfm_params.ajax_url,
			"data"   : function( d ) {
				d.action            = 'wcfm_ajax_controller',
				d.controller        = 'wcfm-orders',
				d.order_status      = GetURLParameter( 'order_status' ),
				d.filter_date_form  = $filter_date_form,
				d.filter_date_to    = $filter_date_to,
				d.order_product     = $order_product,
				d.commission_status = $commission_status,
				d.order_vendor      = $order_vendor,
				d.delivery_boy      = $delivery_boy,
				d.wcfm_ajax_nonce   = wcfm_params.wcfm_ajax_nonce
			},
			"complete" : function () {
				initiateTip();

				$('.show_order_items').click(function(e) {
					e.preventDefault();
					$(this).next('div.order_items').toggleClass( "order_items_visible" );
					return false;
				});

				// Fire wcfm-orders table refresh complete
				$( document.body ).trigger( 'updated_wcfm-orders' );
			}
		}
	} );

	// Bulk selection helpers
	function wcfmOrdersUpdateBulkBar() {
		var count = $('#wcfm-orders').find('.wcfm-bulk-select:checked').length;
		$('#wcfm-orders-selected-count').text(count + ' selected');
		if (count > 0) {
			$('#wcfm-orders-bulk-bar').css('display', 'flex');
		} else {
			$('#wcfm-orders-bulk-bar').hide();
			$('#wcfm-orders-bulk-result').text('').css('color', 'green');
		}
	}

	// Select All
	$(document).on('change', '#wcfm-orders-check-all', function() {
		var checked = $(this).is(':checked');
		$('#wcfm-orders').find('.wcfm-bulk-select').prop('checked', checked);
		wcfmOrdersUpdateBulkBar();
	});

	// Prevent DataTables responsive from intercepting checkbox clicks
	$(document).on('click', '#wcfm-orders .wcfm-bulk-select', function(e) {
		e.stopPropagation();
	});

	// Per-row checkbox
	$(document).on('change', '#wcfm-orders .wcfm-bulk-select', function() {
		var $checkboxes = $('#wcfm-orders').find('.wcfm-bulk-select');
		var total    = $checkboxes.length;
		var selected = $checkboxes.filter(':checked').length;
		$('#wcfm-orders-check-all').prop('indeterminate', selected > 0 && selected < total);
		$('#wcfm-orders-check-all').prop('checked', total > 0 && selected === total);
		wcfmOrdersUpdateBulkBar();
	});

	// Bulk Apply
	$(document).on('click', '#wcfm-orders-bulk-apply', function() {
		var status = $('#wcfm-orders-bulk-status').val();
		if (!status) { alert('Please select a status.'); return; }
		var ids = [];
		$('#wcfm-orders').find('.wcfm-bulk-select:checked').each(function() { ids.push($(this).data('id')); });
		if (!ids.length) { alert('Please select at least one order.'); return; }
		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#wcfm-orders-bulk-result').text('').css('color', 'green');
		$.ajax({
			type: 'POST',
			url: wcfm_params.ajax_url,
			data: {
				action: 'wcfm_bulk_order_status',
				order_ids: ids,
				order_status: status,
				wcfm_ajax_nonce: wcfm_params.wcfm_ajax_nonce
			},
			success: function(resp) {
				if (resp.success) {
					$('#wcfm-orders-bulk-result').css('color', 'green').text(resp.data.message || 'Status updated.');
					$wcfm_orders_table.ajax.reload(null, false);
					$('#wcfm-orders-check-all').prop('checked', false).prop('indeterminate', false);
				} else {
					$('#wcfm-orders-bulk-result').css('color', 'red').text(resp.data || 'Error updating status.');
				}
				$btn.prop('disabled', false);
			}
		});
	});
	
	$( document.body ).on( 'wcfm-date-range-refreshed', function() {
		$wcfm_orders_table.ajax.reload();
	});
	
	// Product Filter
	if( $('#order_product').length > 0 ) {
		$('#order_product').on('change', function() {
		  $order_product = $('#order_product').val();
		  $wcfm_orders_table.ajax.reload();
		}).select2( $wcfm_product_select_args );
	}
	
	// Commission Status Filter
	if( $('#commission-status').length > 0 ) {
		$('#commission-status').on('change', function() {
			$commission_status = $('#commission-status').val();
			$wcfm_orders_table.ajax.reload();
		});
	}
	
	// Vendor Filter
	if( $('#dropdown_vendor').length > 0 ) {
		$('#dropdown_vendor').on('change', function() {
			$order_vendor = $('#dropdown_vendor').val();
			$wcfm_orders_table.ajax.reload();
		}).select2( $wcfm_vendor_select_args );
	}
	
	// Delivery Boy Filter
	if( $('#wcfm_delivery_boy').length > 0 ) {
		$('#wcfm_delivery_boy').on('change', function() {
			$delivery_boy = $('#wcfm_delivery_boy').val();
			$wcfm_orders_table.ajax.reload();
		});
	}
	
	// Order Table auto Refresher
	function orderTableRefrsher() {
		if( wcfm_orders_auto_refresher.is_allow ) {
			clearTimeout(orderTableRefrsherTime);
			orderTableRefrsherTime = setTimeout(function() {
				$wcfm_orders_table.ajax.reload();
				orderTableRefrsher();
			}, wcfm_orders_auto_refresher.duration  );
		}
	}
	orderTableRefrsher();
	
	// Mark Order as Completed
	$( document.body ).on( 'updated_wcfm-orders', function() {
		$('.wcfm_order_mark_complete').each(function() {
			$(this).click(function(event) {
				event.preventDefault();
				var rconfirm = confirm( wcfm_dashboard_messages.order_mark_complete_confirm );
				if(rconfirm) markCompleteWCFMOrder($(this));
				return false;
			});
		});
	});
	
	function markCompleteWCFMOrder(item) {
		clearTimeout(orderTableRefrsherTime);
		$('#wcfm-orders_wrapper').block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
		var data = {
			action : 'wcfm_order_mark_complete',
			orderid : item.data('orderid'),
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce
		}	
		$.ajax({
			type:		'POST',
			url: wcfm_params.ajax_url,
			data: data,
			success:	function(response) {
				$wcfm_orders_table.ajax.reload();
				$('#wcfm-orders_wrapper').unblock();
				orderTableRefrsher();
			}
		});
	}
	
	// Screen Manager
	$( document.body ).on( 'updated_wcfm-orders', function() {
		$.each(wcfm_orders_screen_manage, function( column, column_val ) {
		  $wcfm_orders_table.column( parseInt(column) + 1 ).visible( false );
		} );
	});

	// Hidden Column
	$( document.body ).on( 'updated_wcfm-orders', function() {
		$.each(wcfm_orders_screen_manage_hidden, function( column, column_val ) {
		  $wcfm_orders_table.column( parseInt(column) + 1 ).visible( false );
		} );
	});
	
	// Dashboard FIlter
	if( $('.wcfm_filters_wrap').length > 0 ) {
		$('.dataTable').before( $('.wcfm_filters_wrap') );
		$('.wcfm_filters_wrap').css( 'display', 'inline-block' );
	}
	
} );