var wt_iew_licence=(function( $ ) {
	//'use strict';
	var wt_iew_licence=
	{
		status_checked:false,
		Set:function()
		{
			this.list_data();
			this.activation();
		},
		check_status:function()
		{
			if($('.wt-iew-tab-content[data-id="wt-licence"]').is(':visible'))
			{
				wt_iew_licence.do_status_check();
			}
			$('.nav-tab[href="#wt-licence"]').click(function(){
				if(wt_iew_licence.status_checked===false)
				{
					wt_iew_licence.do_status_check();
				}
			});
		},
		do_status_check:function()
		{
			wt_iew_licence.status_checked=true;
			if($('.wt_iew_licence_table tbody .licence_tr').length==0)
			{
				return false;
			}

			$('.wt_iew_licence_table .status_td, .wt_iew_licence_table .action_td').html('...');
			
			$.ajax({
				url:wt_iew_licence_params.ajax_url,
				data:{'action': 'iew_licence_manager_ajax', 'iew_licence_manager_action': 'check_status', '_wpnonce':wt_iew_licence_params.nonce},
				type:'post',
				dataType:"json",
				success:function(data)
				{
					wt_iew_licence.list_data();
				},
				error:function()
				{
					wt_iew_licence.list_data();
				}
			});
		},
		update_status_tab_icon:function()
		{			
			if($('.wt_iew_licence_table .status_td').length>0)
			{
				var status=true;
			}else
			{
				var status=false;
			}

			if(status)
			{
				$('[name="wt_iew_licence_product"] option').each(function(){
					var vl=$(this).val();
					var licence_tr=$('.wt_iew_licence_table .licence_tr[data-product="'+vl+'"]');
					if(licence_tr.length==0)
					{
						status=false;
					}
				});
			}

			if(status)
			{
				$('.wt_iew_licence_table .status_td').each(function(){
					var st=$(this).attr('data-status');
					if(st=='inactive' || st=='')
					{
						status=false;
					}
				});
			}

			var tab_icon_elm=$('.wt-iew-tab-head .nav-tab[href="#wt-licence"] .dashicons')
			if(status)
			{
				tab_icon_elm.replaceWith(wt_iew_licence_params.tab_icons['active']);
			}else
			{
				tab_icon_elm.replaceWith(wt_iew_licence_params.tab_icons['inactive']);	
			}
		},
		list_data:function()
		{
			$.ajax({
				url:wt_iew_licence_params.ajax_url,
				data:{'action': 'iew_licence_manager_ajax', 'iew_licence_manager_action': 'licence_list', '_wpnonce':wt_iew_licence_params.nonce},
				type:'post',
				dataType:"json",
				success:function(data)
				{
					if(data.status==true)
					{
						$('.wt_iew_licence_list_container').html(data.html);
						wt_iew_licence.deactivation();
						if(wt_iew_licence.status_checked===false)
						{
							wt_iew_licence.check_status();
						}
					}else
					{
						wt_iew_notify_msg.error(wt_iew_licence_params.msgs.unable_to_fetch);
					}
				},
				error:function()
				{
					wt_iew_notify_msg.error(wt_iew_licence_params.msgs.unable_to_fetch);
				}
			});
		},
		deactivation:function()
		{
			$('.wt_iew_licence_deactivate_btn').click(function(){
				if(confirm(wt_iew_licence_params.msgs.sure))
				{
					wt_iew_licence.do_deactivate($(this));
				}
			});
		},
		do_deactivate:function(btn)
		{
			var btn_txt_back=btn.html();
			btn.html(wt_iew_licence_params.msgs.please_wait).prop('disabled', true);
			var key=btn.attr('data-key');
			var action=btn.attr('data-action');
			$.ajax({
				url:wt_iew_licence_params.ajax_url,
				data:{'action': 'iew_licence_manager_ajax', 'iew_licence_manager_action': action, '_wpnonce':wt_iew_licence_params.nonce, 'wt_iew_licence_key':key},
				type:'post',
				dataType:"json",
				success:function(data)
				{
					if(data.status==true)
					{	
						wt_iew_notify_msg.success(data.msg);
						if(btn.parents('tbody').find('tr').length>1)
						{
							btn.parents('tr').remove();
						}else
						{
							wt_iew_licence.list_data();
						}
					}else
					{
						btn.html(btn_txt_back).prop('disabled', false);
						wt_iew_notify_msg.error(wt_iew_licence_params.msgs.error);
					}
				},
				error:function()
				{
					btn.html(btn_txt_back).prop('disabled', false);
					wt_iew_notify_msg.error(wt_iew_licence_params.msgs.error);
				}
			});
		},
		activation:function()
		{
			$('#iew_licence_manager_form').submit(function(e){
				e.preventDefault();
				var this_form=$(this);
				var licence_key=$.trim(this_form.find('[name="wt_iew_licence_key"]').val());
				if(licence_key=="")
				{
					wt_iew_notify_msg.error(wt_iew_licence_params.msgs.key_mandatory);
					return false;
				}
				var btn=this_form.find('.wt_iew_licence_activate_btn');
				var btn_txt_back=btn.html();
				btn.html(wt_iew_licence_params.msgs.please_wait).prop('disabled', true);
				$.ajax({
					url:wt_iew_licence_params.ajax_url,
					data:this_form.serialize(),
					type:'post',
					dataType:"json",
					success:function(data)
					{
						btn.html(btn_txt_back).prop('disabled', false);
						if(data.status==true)
						{
							this_form[0].reset();
							wt_iew_notify_msg.success(data.msg);
							wt_iew_licence.list_data();
						}else
						{
							wt_iew_notify_msg.error(data.msg);
						}
					},
					error:function()
					{
						btn.html(btn_txt_back).prop('disabled', false);
						wt_iew_notify_msg.error(wt_iew_licence_params.msgs.error);
					}
				});
			});
		}
	}
	return wt_iew_licence;
	
})( jQuery );

jQuery(function() {			
	wt_iew_licence.Set();
});