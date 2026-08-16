$wcfm_affiliate_table = '';

jQuery(document).ready(function($) {
	
	$wcfm_affiliate_table = $('#wcfm-affiliate').DataTable( {
		"processing": true,
		"serverSide": true,
		"bFilter"   : wcfm_datatable_columns.bFilter,
		"pageLength": parseInt(dataTables_config.pageLength),
		"dom"       : 'Bfrtip',
		"responsive": true,
		"language"  : $.parseJSON(dataTables_language),
		"buttons"   : $wcfm_datatable_button_args,
		"columns"   : $.parseJSON(wcfm_datatable_columns.priority),
		"columnDefs": $.parseJSON(wcfm_datatable_columns.defs),
		'ajax': {
			"type"   : "POST",
			"url"    : wcfm_params.ajax_url,
			"data"   : function( d ) {
				d.action           = 'wcfm_ajax_controller',
				d.controller       = 'wcfm-affiliate',
				d.filter_date_form = $filter_date_form,
				d.filter_date_to   = $filter_date_to,
				d.wcfm_ajax_nonce  = wcfm_params.wcfm_ajax_nonce
			},
			"complete" : function () {
				initiateTip();
				
				// Fire wcfm-appointments table refresh complete
				$( document.body ).trigger( 'updated_wcfm_affiliate' );
			}
		}
	} );

	$( document.body ).on( 'wcfm-date-range-refreshed', function() {
		$wcfm_affiliate_table.ajax.reload();
	});
	
	// Affiliate Action Manager
	$( document.body ).on( 'updated_wcfm_affiliate', function() {
		// Enable Affliate
		$('.wcfm_affiliate_enable_button').each(function() {
			$(this).click(function( event ) {
				event.preventDefault();
				var rconfirm = confirm("Are you sure and want to enable this 'Affiliate'?");
				if(rconfirm) {
					$('#wcfm_affiliate_expander').block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					var data = {
						action       : 'wcfm_affiliate_enable',
						memberid     : $(this).data('memberid'),
						wcfm_ajax_nonce             : wcfm_params.wcfm_ajax_nonce,
					}	
					$.post(wcfm_params.ajax_url, data, function(response) {
						if(response) {
							$wcfm_affiliate_table.ajax.reload();
							$('#wcfm_affiliate_expander').unblock();
						}
					});
				}
			});
		});
		
		// Disable Affliate
		$('.wcfm_affiliate_disable_button').each(function() {
			$(this).click(function( event ) {
				event.preventDefault();
				var rconfirm = confirm("Are you sure and want to disable this 'Affiliate'?");
				if(rconfirm) {
					$('#wcfm_affiliate_expander').block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					var data = {
						action       : 'wcfm_affiliate_disable',
						memberid     : $(this).data('memberid'),
						wcfm_ajax_nonce             : wcfm_params.wcfm_ajax_nonce,
					}	
					$.post(wcfm_params.ajax_url, data, function(response) {
						if(response) {
							$wcfm_affiliate_table.ajax.reload();
							$('#wcfm_affiliate_expander').unblock();
						}
					});
				}
			});
		});
		
		// Delete Affliate	
		$('.wcfm_affiliate_delete').each(function() {
			$(this).click(function(event) {
				event.preventDefault();
				var rconfirm = confirm("Are you sure and want to delete this 'Affiliate'?\nYou can't undo this action ...");
				if(rconfirm) deleteWCFMAffiliate($(this));
				return false;
			});
		});
	});
	
	function deleteWCFMAffiliate(item) {
		jQuery('#wcfm_affiliate_expander').block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
		var data = {
			action        : 'delete_wcfm_affiliate',
			affiliateid   : item.data('affiliateid'),
			wcfm_ajax_nonce             : wcfm_params.wcfm_ajax_nonce,
		}	
		jQuery.ajax({
			type:		'POST',
			url: wcfm_params.ajax_url,
			data: data,
			success:	function(response) {
				if($wcfm_affiliate_table) $wcfm_affiliate_table.ajax.reload();
				jQuery('#wcfm_affiliate_expander').unblock();
			}
		});
	}
	
	// Screen Manager
	$( document.body ).on( 'updated_wcfm_affiliate', function() {
		$.each(wcfm_affiliate_screen_manage, function( column, column_val ) {
		  $wcfm_affiliate_table.column(column).visible( false );
		} );
	});
	
	// Dashboard FIlter
	if( $('.wcfm_filters_wrap').length > 0 ) {
		$('.dataTable').before( $('.wcfm_filters_wrap') );
		$('.wcfm_filters_wrap').css( 'display', 'inline-block' );
	}
} );