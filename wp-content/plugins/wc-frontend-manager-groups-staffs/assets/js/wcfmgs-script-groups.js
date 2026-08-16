jQuery(document).ready(function($) {
		
	$wcfm_groups_table = $('#wcfm-groups').DataTable( {
		"processing": true,
		"serverSide": true,
		"responsive": true,
		"language"  : $.parseJSON(dataTables_language),
		"columns"   : [
										{ responsivePriority: 3 },
										{ responsivePriority: 1 },
										{ responsivePriority: 2 },
										{ responsivePriority: 2 },
										{ responsivePriority: 1 },
								],
		"columnDefs": [ { "targets": 0, "orderable" : false }, 
									  { "targets": 1, "orderable" : false }, 
										{ "targets": 2, "orderable" : false },
										{ "targets": 3, "orderable" : false },
										{ "targets": 4, "orderable" : false },
									],
		'ajax': {
			"type"   : "POST",
			"url"    : wcfm_params.ajax_url,
			"data"   : function( d ) {
				d.action       = 'wcfm_ajax_controller',
				d.controller   = 'wcfm-groups',
				d.wcfm_ajax_nonce  = wcfm_params.wcfm_ajax_nonce
			},
			"complete" : function () {
				initiateTip();
				
				// Fire wcfm-groups table refresh complete
				$( document.body ).trigger( 'updated_wcfm-groups' );
			}
		}
	} );
	
	// Delete Group
	$( document.body ).on( 'updated_wcfm-groups', function() {
		$('.wcfm_group_delete').each(function() {
			$(this).click(function(event) {
				event.preventDefault();
				var rconfirm = confirm("Are you sure and want to delete this 'Group'?\nYou can't undo this action ...");
				if(rconfirm) deleteWCFMGroup($(this));
				return false;
			});
		});
	});
	
	function deleteWCFMGroup(item) {
		jQuery('#wcfm-groups_wrapper').block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
		var data = {
			action  : 'delete_wcfm_group',
			groupid : item.data('groupid'),
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce
		}	
		jQuery.ajax({
			type:		'POST',
			url: wcfm_params.ajax_url,
			data: data,
			success:	function(response) {
				if($wcfm_groups_table) $wcfm_groups_table.ajax.reload();
				jQuery('#wcfm-groups_wrapper').unblock();
			}
		});
	}

	$('#wcfm-groups').on('click', '.wcfm_group_duplicate', function(){
		
        $('#wcfm-groups_wrapper').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
        var data = {
            action : 'wcfmgs_duplicate_group',
            wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce,
            groupid : $(this).data('groupid')
        } 
        //console.log(data);  
        jQuery.ajax({
            type:       'POST',
            url: wcfm_params.ajax_url,
            data: data,
            success:    function(response) {
                if(response) {
                    $response_json = $.parseJSON(response);
                    if($response_json.status) {
                        if( $response_json.redirect ) window.location = $response_json.redirect;    
                    }
                }
            }
        });
        $('#wcfm-groups_wrapper').unblock();
        return false;
    });
} );