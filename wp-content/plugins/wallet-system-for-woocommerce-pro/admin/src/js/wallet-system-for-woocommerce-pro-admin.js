(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$(document).ready(function() {

		var withdrawal_fee = jQuery('#wps_wsfwp_cashback_withdrawal_fee_type').val();
		if ( withdrawal_fee == 'percent' ) {
			jQuery('#wps_wsfwp_wallet_withdrawal_fee_amount').attr('max',100);
		}

		var transfer_fee = jQuery('#wps_wsfwp_cashback_transfer_fee_type').val();
		if ( transfer_fee == 'percent' ) {
			jQuery('#wps_wsfwp_wallet_transfer_fee_amount').attr('max',100);
		}


		$(document).on("change", "#wps_wsfwp_cashback_withdrawal_fee_type", function(e){
			var withdrawal_fee = jQuery('#wps_wsfwp_cashback_withdrawal_fee_type').val();
			if ( withdrawal_fee == 'percent' ) {
				jQuery('#wps_wsfwp_wallet_withdrawal_fee_amount').attr('max',100);
			} else{
				jQuery('#wps_wsfwp_wallet_withdrawal_fee_amount').attr('max','');
			}
		});

		$(document).on("change", "#wps_wsfwp_cashback_transfer_fee_type", function(e){
			var withdrawal_fee = jQuery('#wps_wsfwp_cashback_transfer_fee_type').val();
			if ( withdrawal_fee == 'percent' ) {
				jQuery('#wps_wsfwp_wallet_transfer_fee_amount').attr('max',100);
			} else{
				jQuery('#wps_wsfwp_wallet_transfer_fee_amount').attr('max','');
			}
		});



		const MDCText = mdc.textField.MDCTextField;
        const textField = [].map.call(document.querySelectorAll('.mdc-text-field'), function(el) {
            return new MDCText(el);
        });
        const MDCRipple = mdc.ripple.MDCRipple;
        const buttonRipple = [].map.call(document.querySelectorAll('.mdc-button'), function(el) {
            return new MDCRipple(el);
        });
        const MDCSwitch = mdc.switchControl.MDCSwitch;
        const switchControl = [].map.call(document.querySelectorAll('.mdc-switch'), function(el) {
            return new MDCSwitch(el);
        });
		$(document).on("click", ".edit_wallet-check", function(e){
			e.preventDefault(e);
			var userid = $(this).attr('data-userid');
			var data = {
				'action': 'wsfw_restrict_include_modal_data',
				'userid': userid,
				'nonce' : wsfwp_admin_param.nonce,
			};
			jQuery.post(wsfwp_admin_param.ajaxurl, data, function(response) {
				jQuery('#restrict_user_body').html('');
				jQuery('#restrict_user_body').append(response['option-edit']);
				$('#restrict_user_pro').show();
		
			});
		});
		
		$(document).on("click", "#close_wallet_form_check", function(e) {
			$('.error').html('');
			$('.wps_wallet-edit-check--popupwrap').find('.userid').remove();
			$('.wps_wallet-edit-check--popupwrap').hide();
			
		});
		$(document).on("click", "#close_wallet_form", function(e) {
			$('#restrict_user_body').html('');
		});
        $('.wps-password-hidden').click(function() {
            if ($('.wps-form__password').attr('type') == 'text') {
                $('.wps-form__password').attr('type', 'password');
            } else {
                $('.wps-form__password').attr('type', 'text');
            }
        });
		$('#wsfwp_min_wallet_recharge_amount').blur(function() {
			var min_amount = $(this).val();
			min_amount = parseInt(min_amount);
			var max_amount = $('#wsfwp_max_wallet_recharge_amount').val();
			max_amount = parseInt(max_amount);
            if ( min_amount > max_amount ) {
				if ($('.error').length === 0) {
					$('#wsfwp_min_wallet_recharge_amount').parent().after('<p class="error">' + wsfwp_admin_param.minimum_wallet_recharge_error + '</p>');
					$('#wsfw_button_demo').prop('disabled', true);
					$(this).val('');
				}
            } else {
                $('.error').remove();
				$('#wsfw_button_demo').prop('disabled', false);
            }
        });
		$('#wsfwp_max_wallet_recharge_amount').blur(function() {
			
			var max_amount = $(this).val();
			var min_amount = $('#wsfwp_min_wallet_recharge_amount').val();
			min_amount = parseInt(min_amount);
			max_amount = parseInt(max_amount);
            if ( min_amount > max_amount ) {
				if ($('.error').length === 0) {
					$('#wsfwp_max_wallet_recharge_amount').parent().after('<p class="error">' + wsfwp_admin_param.maximum_wallet_recharge_error + '</p>');
					$('#wsfw_button_demo').prop('disabled', true);
					$(this).val('');
				}
            } else {
				$('.error').remove();
				$('#wsfw_button_demo').prop('disabled', false);
            }
        });


		$('#wsfwp_min_wallet_transfer_amount').blur(function() {
			
			var min_amount = $(this).val();
			min_amount = parseInt(min_amount);
			var max_amount = $('#wsfwp_max_wallet_transfer_amount').val();
			max_amount = parseInt(max_amount);
            if ( min_amount > max_amount ) {
				if ($('.error').length === 0) {
					$('#wsfwp_min_wallet_transfer_amount').parent().after('<p class="error">' + wsfwp_admin_param.minimum_wallet_transfer_error + '</p>');
					$('#wsfw_button_demo').prop('disabled', true);
					$(this).val('');
				}
            } else {
                $('.error').remove();
				$('#wsfw_button_demo').prop('disabled', false);
            }
        });

		$('#wsfwp_max_wallet_transfer_amount').blur(function() {
			
			var max_amount = $(this).val();
			var min_amount = $('#wsfwp_min_wallet_transfer_amount').val();
			min_amount = parseInt(min_amount);
			max_amount = parseInt(max_amount);
            if ( min_amount > max_amount ) {
				if ($('.error').length === 0) {
					$('#wsfwp_max_wallet_transfer_amount').parent().after('<p class="error">' + wsfwp_admin_param.maximum_wallet_transfer_error + '</p>');
					$('#wsfw_button_demo').prop('disabled', true);
					$(this).val('');
				}
            } else {
				$('.error').remove();
				$('#wsfw_button_demo').prop('disabled', false);
            }
        });


		$('#wsfwp_min_wallet_withdrawal_amount').blur(function() {
			
			var min_amount = $(this).val();
			min_amount = parseInt(min_amount);
			var max_amount = $('#wsfwp_max_wallet_withdrawal_amount').val();
			max_amount = parseInt(max_amount);
            if ( min_amount > max_amount ) {
				if ($('.error').length === 0) {
					$('#wsfwp_min_wallet_withdrawal_amount').parent().after('<p class="error">' + wsfwp_admin_param.minimum_wallet_withdrawal_error + '</p>');
					$('#wsfw_button_demo').prop('disabled', true);
					$(this).val('');
				}
            } else {
                $('.error').remove();
				$('#wsfw_button_demo').prop('disabled', false);
            }
        });

		$('#wsfwp_max_wallet_withdrawal_amount').blur(function() {
			var max_amount = $(this).val();
			var min_amount = $('#wsfwp_min_wallet_withdrawal_amount').val();
			min_amount = parseInt(min_amount);
			max_amount = parseInt(max_amount);
            if ( min_amount > max_amount ) {
				if ($('.error').length === 0) {
					$('#wsfwp_max_wallet_withdrawal_amount').parent().after('<p class="error">' + wsfwp_admin_param.maximum_wallet_widthdrawal_error + '</p>');
					$('#wsfw_button_demo').prop('disabled', true);
					$(this).val('');
				}
            } else {
				$('.error').remove();
				$('#wsfw_button_demo').prop('disabled', false);
            }
        });
	});

	jQuery(document).ready(function(){
			jQuery("#wps-wpg-gen-table_trasa").wrap("<div class='wps_wsfwp_table_wrap'></div>");
	});
	
	$(window).load(function(){
		// add select2 for multiselect.
		if( $(document).find('.wps-defaut-multiselect').length > 0 ) {
			$(document).find('.wps-defaut-multiselect').select2();
		}
	});

	$(document).on( 'blur','#license_key', function(){
		var license_key = $(this).val();
		if ( license_key == '' ) {
			$("#wps_wsfwp_license_activation_status").css("color", "#ff3333");
			jQuery("#wps_wsfwp_license_activation_status").html(wsfwp_admin_param.wsfwp_license_field_error);
			$('#activate_license').prop('disabled', true);
		} else {
			jQuery("#wps_wsfwp_license_activation_status").html('');
			$('#activate_license').prop('disabled', false);	
		}
	});
	
	// on clicking license activation button
	$(document).on('submit', '#wps_wsfwp_license_form', function(e){
		e.preventDefault();
		var license_key = $('#license_key').val();
		if ( license_key == '' ) {
			$("#wps_wsfwp_license_activation_status").css("color", "#ff3333");
			jQuery("#wps_wsfwp_license_activation_status").html(wsfwp_admin_param.wsfwp_license_field_error);
		} else {	
			wps_wsfwp_send_license_request(license_key);
		}
		
	});




	function wps_wsfwp_send_license_request(license_key) {
		
		$.ajax({
		   type: "POST",
		   dataType: "JSON",
		   url: wsfwp_admin_param.ajaxurl,
			data: {
				action: "wps_wsfwp_validate_license_key",
				license_key: license_key,
				nonce : wsfwp_admin_param.nonce,
			},
	
			success: function(data) {
				if (data.status == true) {
					$("#wps_wsfwp_license_activation_status").css("color", "#42b72a");
			
					jQuery("#wps_wsfwp_license_activation_status").html(data.msg);
			
					location = wsfwp_admin_param.wsfwp_admin_param_location;
				} else {
					$("#wps_wsfwp_license_activation_status").css("color", "#ff3333");
			
					jQuery("#wps_wsfwp_license_activation_status").html(data.msg);
			
					jQuery("#license_key").val("");
				}
			},
		})
		.fail(function ( response ) {
			$("#wps_wsfwp_license_activation_status").css("color", "#ff3333");
			jQuery("#wps_wsfwp_license_activation_status").html(wsfwp_admin_param.wsfwp_ajax_error);
		});
	}

	// on clicking license check button
	$(document).on('click', '#wps_check_license', function(e){
		e.preventDefault();
			
		$.ajax({
		   	type: "POST",
		   	dataType: "JSON",
		   	url: wsfwp_admin_param.ajaxurl,
			data: {
				action: "wps_wsfwp_check_license_key_status",
				nonce : wsfwp_admin_param.nonce,
			},
		
			success: function(data) {
				if (data.status == true) {
					jQuery("div.wps-subsc_notice p").html(data.msg);
					setTimeout(function(){
		              	location = wsfwp_admin_param.wsfwp_admin_param_location;
		          	}, 2000);					
				}
				else{
					jQuery("div.wps-subsc_notice p").html(data.msg);
					setTimeout(function(){
		              	location = wsfwp_admin_param.wsfwp_admin_param_location;
		          	}, 2000);
				}
			},
		})
		.fail(function ( response ) {
			location = wsfwp_admin_param.wsfwp_admin_param_location;
		});
		
	});
	
})( jQuery );



jQuery(document).ready(function() {



	jQuery('#wsfw_button_wallet_recharge_tab').on('click', function(e){
		
		
		var count =jQuery('.wsfw-text-class').length;
		var  condition = false;
		for (let index = 0; index < count; index++) {
		
			var value = jQuery(jQuery('.wsfw-text-class')[index]).val();
			if ( parseInt(value) < 0 ){
				condition = true;
				jQuery(jQuery('.wsfw-text-class')[index]).val('');
				
			}
		}
		if ( condition ){
			e.preventDefault(e);
			alert(wsfwp_admin_param.negative_value_message_error);
		}

		if ( ! condition ) {
			jQuery('#wsfw_button_wallet_recharge_tab').trigger('click');
		}



	});

	jQuery('#wps_wallet_bookie').on('click', function(e){
			e.preventDefault(e);
		var html='';
		html+= '<tr> <td> <div class="wps-form-group__control-wps"><label class="mdc-text-field mdc-text-field--outlined"><span class="mdc-notched-outline">';
		html+= '<span class="mdc-notched-outline__leading"></span><span class="mdc-notched-outline__notch">';
		html+= '<span class="mdc-floating-label" id="my-label-id" style=""></span>';	
		html+= '</span><span class="mdc-notched-outline__trailing"></span></span>';
		html+= '<input class="mdc-text-field__input wsfw-text-class" min=0 name="wps_wallet_action_bookie_array[]" id="wps_wallet_action_bookie_array[]" type="text" value="" placeholder="Enter Bookie Name" >';
		html+= '</label> <br> <div class="mdc-text-field-helper-line"> <div class="mdc-text-field-helper-text--persistent wps-helper-text" id="" aria-hidden="true"></div>';
		html+= '</div> </div> </td>';
   		html+='<td><input type="button" onclick="remove_tr_row(this)" class="wps_wallet_action_recharge_tab_remove" value="'+wsfwp_admin_param.remove+'"></td></tr>';
		jQuery('#wps_wallet_action_recharge_tab_table').append(html);
	});	

	jQuery('#wps_wallet_promotions_button').on('click', function(e){
		e.preventDefault(e);
	
	var html='';
	html+= '<tr> <td> <div class="wps-form-group__control"><label class="mdc-text-field mdc-text-field--outlined"><span class="mdc-notched-outline">';
	html+= '<span class="mdc-notched-outline__leading"></span><span class="mdc-notched-outline__notch">';
	html+= '<span class="mdc-floating-label" id="my-label-id" style=""></span>';	
	html+= '</span><span class="mdc-notched-outline__trailing"></span></span>';
	html+= '<input class="mdc-text-field__input wsfw-text-class wps-wallet-text" name="wallet_promotions_data_title[]" id="wallet_promotions_data_title[]" type="text" value="" placeholder="Enter Bookie Name" >';
	html+= '</label> <br> <div class="mdc-text-field-helper-line"> <div class="mdc-text-field-helper-text--persistent wps-helper-text" id="" aria-hidden="true"></div>';
	html+= '</div> </div> </td>';
html+='<td> <div class="wps-form-group__control"> ';
html+= '<label class="mdc-text-field mdc-text-field--outlined mdc-text-field--textarea mdc-text-field--no-label"> <span class="mdc-notched-outline">';
	html+=   '<span class="mdc-notched-outline__leading"></span><span class="mdc-notched-outline__trailing"></span>'
	html+=  '</span>  <span class="mdc-text-field__resizer"><textarea class="mdc-text-field__input"      id="wallet_promotions_data_content[]"      name="wallet_promotions_data_content[]"	placeholder=""  rows="4" cols="40" aria-label="Label"></textarea>';
  html+= '</span></label></div> </td>';
html+='<td><input type="button" onclick="remove_tr_row(this)" class="wps_wallet_action_recharge_tab_remove" value="'+wsfwp_admin_param.remove+'"></td></tr>';

	jQuery('#wps_wallet_promotions_table').append(html);
});	
});



function remove_tr_row(obj){
	obj.parentElement.parentElement.remove();
}