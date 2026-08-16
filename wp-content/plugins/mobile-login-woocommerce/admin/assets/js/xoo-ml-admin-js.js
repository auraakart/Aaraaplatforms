jQuery(document).ready(function($){

	'use strict';

	//Hide defaul field if set to geoolocation
	$('select[name="xoo-ml-phone-options[r-default-country-code-type]"]').on( 'change', function(){
		var $cc = $('select[name="xoo-ml-phone-options[r-default-country-code]"').parents('.xoo-as-setting');
		$(this).val() === 'custom' ? $cc.show() : $cc.hide();
	} ).trigger('change');


	$('select[name="xoo-ml-phone-options[m-operator]"]').on( 'change', function(){

		var $linksEl = $( '.xoo-ml-opt-links' );

		$linksEl.find( 'li' ).hide();

		$linksEl.find( 'li[data-operator="'+ $(this).val() +'"]' ).show();

		$('.xoo-ml-notice').hide();


		var $smsText 	= $('textarea[name="xoo-ml-phone-options[r-sms-txt]"]'),
			$digits 	= $('input[name="xoo-ml-phone-options[otp-digits]"]');

		if( $(this).val() === 'firebase' ){

			$smsText.add( $digits ).addClass('fb-disabled');

			if( !$smsText.siblings('.fb-disabled-txt').length ){
				$smsText.add( $digits ).after('<span class="fb-disabled-txt">Cannot be edited with Google Firebase operator. See templates tab under firebase console settings.</span>');
			}

			$digits.val(6);
		}
		else{
			$smsText.add( $digits ).removeClass('fb-disabled');
		}

	} ).trigger('change');



	$( 'textarea[name="xoo-ml-services-options[fb-config]"]' ).on( 'change', function(){
		$(this).val( $(this).val().replace( 'const firebaseConfig =', '' ) );
	} );

	$('.xoo-sc-tab-content[data-tab="services"] .xoo-as-field input').each( function( index, el ){
		if( $(el).val().length ){
			$('.xoo-ml-service-info').hide();
			return false;
		}
	} )

	$('select[name="xoo-ml-services-options[cus-format]"]').on( 'change', function(){

		var $jsonField 	= $('textarea[name="xoo-ml-services-options[cus-json]"]'),
			$urlField 	= $('textarea[name="xoo-ml-services-options[cus-params]"]');

		if( $(this).val() === 'json' ){
			$urlField.closest('.xoo-as-setting').hide();
			$jsonField.closest('.xoo-as-setting').show();
		}
		else{
			$jsonField.closest('.xoo-as-setting').hide();
			$urlField.closest('.xoo-as-setting').show();
		}

	} ).trigger('change');


	$('select[name="xoo-ml-services-options[cus-auth-type]"]').on( 'change', function(){

		var $tokenField = $('input[name="xoo-ml-services-options[cus-auth-info]"]').closest('.xoo-as-setting');

		if( $(this).val() === 'none' ){
			$tokenField.hide();
		}
		else{
			$tokenField.show();
		}

	} ).trigger('change');


	$('.xoo-admin-ml-verifyno').on( 'click', function(){

		let waba_id 	= $('input[name="xoo-ml-services-options[whatsapp-businessid]"]').val();
		let phone_no 	= $('input[name="xoo-ml-services-options[whatsapp-phoneno]"]').val();
		let token 		= $('input[name="xoo-ml-services-options[whatsapp-token]"]').val();

		if( !waba_id || !phone_no || !token ){
			alert('WABA ID, phone number & Bearer Token cannot be empty.');
			return;
		}

		let $number_id 		= $('input[name="xoo-ml-services-options[whatsapp-phoneid]"]');
		let $container 		= $(this).closest('.xoo-as-field');

		let $button  	= $(this);
		let before_text = $(this).text();

		$button.text( 'Fetching....' ).addClass('xoo-ml-processing');

		if( !$container.find('.xoo-ml-whatsapp-response').length ){
			$container.append('<div class="xoo-ml-whatsapp-response"></div>');
		}

		let $response_cont = $container.find('.xoo-ml-whatsapp-response');

		var data = {
			'waba_id': waba_id,
			'phone_no': phone_no,
			'token': token,
			'action': 'xoo_ml_admin_whatsapp_fetch',
		}

		$.ajax({
			url: xoo_ml_admin_localize.adminurl,
			type: 'POST',
			data: data,
			success: function(response){

				$button.text(before_text).removeClass('xoo-ml-processing');

				if( response.number_id ){
					$number_id.val(response.number_id);
				}

				if( response.message ){
					$response_cont.html( response.message );
				}

				if( response.success ){
					$button.hide();
					$('.xoo-admin-ml-register').addClass('xoo-admin-ml-active');
				}

			}
		});


	} );


	$('.xoo-admin-ml-register .xoo-admin-ml-link').on( 'click', function(){

		let waba_id 	= $('input[name="xoo-ml-services-options[whatsapp-businessid]"]').val();
		let phone_id 	= $('input[name="xoo-ml-services-options[whatsapp-phoneid]"]').val();
		let token 		= $('input[name="xoo-ml-services-options[whatsapp-token]"]').val();
		let pin 		= $(this).siblings('input').val();

		if( !waba_id || !phone_id || !token || !pin ){
			alert('WABA ID, phone ID, pin & Bearer Token cannot be empty.');
			return;
		}

		let $fieldContainer = $(this).closest('.xoo-as-field');

		let $button  	= $(this);
		let before_text = $(this).text();

		$button.text( 'Please wait....' ).addClass('xoo-ml-processing');;

		let $response_cont = $fieldContainer.find('.xoo-ml-whatsapp-response');

		$response_cont.html('');

		var data = {
			'waba_id': waba_id,
			'phone_id': phone_id,
			'token': token,
			'pin': pin,
			'action': 'xoo_ml_admin_whatsapp_register',
		}

		$.ajax({
			url: xoo_ml_admin_localize.adminurl,
			type: 'POST',
			data: data,
			success: function(response){

				$button.text(before_text).removeClass('xoo-ml-processing');;

				if( response.success ){
					$response_cont.html( response.message );
					$fieldContainer.find('.xoo-admin-ml-register').removeClass('xoo-admin-ml-active');
				}

				if( response.message ){
					$response_cont.html( response.message );
				}

			}
		});


	} );





});
