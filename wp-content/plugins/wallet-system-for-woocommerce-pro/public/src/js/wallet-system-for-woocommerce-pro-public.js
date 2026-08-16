(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
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


	
	$(document).on( 'click','#wps_generate_qr_code', function(e){ 
		e.preventDefault();
		var user_id = $( '#wps_generate_qr_code' ).data('user-id');
		console.log('gggg');
		$.ajax({
			type: 'POST',
			url: wsfwp_public_param.ajaxurl,
			data: {
				action: 'wps_generate_qr_code',
				user_id: user_id,

			},
			success: function( response ) {
				
				if ( response.type !== 'woocommerce-error' ) {
					location.reload();
				} else {
					$( '#wps_generate_qr_code' ).after(response.message);
					location.reload();
					
				}
			}

		}) .fail(function ( response ) {
			$( '#wps_generate_qr_code' ).after('<span style="color:red;" >' + wsfwp_public_param.wsfwp_ajax_error + '</span>');		
		});
		
	});

	$(document).on('click','.wps-wp_ctlt-in-qr_code-icon',function(){
		$('.wps-wp_ctlt-in-wrap .wps-wp_ctlt-in-qr_code').addClass('wps-qr_show');
	})

	$(document).on('click','.wps-wp_ctlt-in-qr_code-close,.wps-wp_ctlt-in-wrap .wps-wp_ctlt-in-qr_code-shadow',function(){
		$('.wps-wp_ctlt-in-wrap .wps-wp_ctlt-in-qr_code').removeClass('wps-qr_show');
	})



	// QR update

	$(document).on( 'click','#wps_wsfwp_open_invitation_box', function(e){ 
		e.preventDefault(e);
		console.log('ttttt');
		$( '#wps_wsfwp_show_inviatation_link_form' ).show();
		$( '.wps-wp_ctr-transfer' ).click();
		var wps_withdrawal_invite_link = $('#wps_wsfwp_show_inviatation_link_form').detach();
		$(wps_withdrawal_invite_link).insertBefore('#wps-wp_ct');
	});

	$(document).on("click", "#close_wallet_form", function(e){
		$('#wps_wsfwp_show_inviatation_link_form').hide();
	});

	$(document).on( 'blur','#wps_qr_wallet_transfer', function(){
		var action = $('#wps_wallet_action').val();
		var amount = $(this).val();
		if ( action == 'Wallet Transfer' ) {
			var maxamount = $(this).data('transfer');
			maxamount = parseFloat(maxamount);
			if ( amount <= 0 ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_amount_error);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else if ( amount > maxamount ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_transfer_amount_error);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else {
				$('.error').hide();
				$('#wps_recharge_wallet').prop('disabled', false);
			}
		} else if ( action == 'Wallet Recharge' ) {
			var minamount = $(this).data('min');
			var maxamount = $(this).data('max');
			minamount = parseFloat(minamount);
			maxamount = parseFloat(maxamount);
			if ( amount <= 0 ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_amount_error);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else if ( amount > maxamount ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_recharge_maxamount_error + maxamount);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else if ( amount < minamount) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_recharge_minamount_error + minamount);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else {
				$('.error').hide();
				$('#wps_recharge_wallet').prop('disabled', false);
			}
		} else {
			if ( amount <= 0 ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_amount_error);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else {
				$('.error').hide();
				$('#wps_recharge_wallet').prop('disabled', false);
			}
		}
		
	});

	$(document).on( 'click','#wps_wallet_action', function() {
			
		var action = $(this).val();
		if ( action == 'Wallet Transfer' ) {
			$('.wps-wallet-field-login-error').show();
			var amount = $('#wps_qr_wallet_transfer').val();
			var maxamount = $('#wps_qr_wallet_transfer').data('transfer');
			maxamount = parseFloat(maxamount);
			if ( amount <= 0 ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_amount_error);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else if ( amount > maxamount ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_transfer_amount_error);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else {
				$('.error').hide();
				$('#wps_recharge_wallet').prop('disabled', false);
			}
		} else {
			$('.wps-wallet-field-login-error').hide();
			var amount = $('#wps_qr_wallet_transfer').val();
			var minamount = $('#wps_qr_wallet_transfer').data('min');
			var maxamount = $('#wps_qr_wallet_transfer').data('max');
			minamount = parseFloat(minamount);
			maxamount = parseFloat(maxamount);
			if ( amount <= 0 ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_amount_error);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else if ( amount > maxamount ) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_recharge_maxamount_error + maxamount);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else if ( amount < minamount) {
				$('.error').show();
				$('.error').html(wsfwp_public_param.wsfwp_recharge_minamount_error + minamount);
				$('#wps_recharge_wallet').prop('disabled', true);
			} else {
				$('.error').hide();
				$('#wps_recharge_wallet').prop('disabled', false);
			}
		}

	});

	// wallet coupon.
	$(document).on('click', '#wps_coupon_wallet', function( e ){
		e.preventDefault();
		var wps_wsfwp_coupon_code = $('#wps_wsfw_coupon_code').val();
		var wps_current_user_id   = $('#wps_current_user_id').val();
		$('.wsfw__redeem_form_message').html('');

		if ( wps_wsfwp_coupon_code == '' || wps_wsfwp_coupon_code == undefined || wps_wsfwp_coupon_code == null) {
			$('.wsfw__redeem_form_message').css('color', 'red');
			$('.wsfw__redeem_form_message').html( wsfwp_public_param.wsfwp_coupon_error );
			$('.wsfw__redeem_form_message').show();
			setTimeout(function () {
				$('.wsfw__redeem_form_message').hide();
				$('#wps_wsfw_coupon_code').val('');
			}, 1500 );
		} else {
			$('.loading_image').show();
			$.ajax({
				url  : wsfwp_public_param.ajaxurl,
				type : 'POST',
				data : {
					'action'                : 'wps_wsfwp_redeem_coupon_amount',
					'wps_wsfwp_nonce'       : wsfwp_public_param.nonce,
					'wps_wsfwp_coupon_code' : wps_wsfwp_coupon_code,
					'wps_current_user_id'   : wps_current_user_id,
				},
				success : function( response ) {
					$('.loading_image').hide();
					$('.wsfw__redeem_form_message').show();
					if ( response.status == true ) {
						$('.wsfw__redeem_form_message').css('color', 'green');
						$('.wsfw__redeem_form_message').html( response.msg );
					} else {
						$('.wsfw__redeem_form_message').css('color', 'red');
						$('.wsfw__redeem_form_message').html( response.msg );
					}
					setTimeout(function () {
						location.reload();
					}, 1500 );
				}
			});
		}
	});

	$(document).on( 'click','#copyQRCode', function() {
		$('#copyQrCodeUrl').attr('type', 'text').select();
		document.execCommand('copy');
		$('#copyQrCodeUrl').attr('type', 'hidden');
		var tooltip = document.getElementById("myTooltip");
  		tooltip.innerHTML = wsfwp_public_param.wsfwp_copied_code;
	});


	// wallet new template js code.
	jQuery(document).ready(function($) {
        $('a.wps-wp_ctr-link').on('click', function(e) {
			e.preventDefault();
            $('a.wps-wp_ctr-link').removeClass('wps-wp_ctrl--active');
            $('a.wps-wp_ctrml').removeClass('wps-wp_ctrl--active');
            $(this).addClass('wps-wp_ctrl--active');
        });
        $('a.wps-wp_ctrml').on('click', function(e) {
			e.preventDefault();
            $('a.wps-wp_ctr-link').removeClass('wps-wp_ctrl--active');
            $('a.wps-wp_ctrml').removeClass('wps-wp_ctrl--active');
            $(this).addClass('wps-wp_ctrl--active');
        });

        // Transaction History
        $('.wps-wp_ctlh-history').on('click', function() {
            $(this).removeClass('wps-wp_ctrl--active');
            $('.wps-wp_ctlh-recent').addClass('wps-wp_ctrl--active');
            $('.wps-wp_ctlm').removeClass('wps-wp_ctrl--active');
            $('.wps-wp_ctl-history').addClass('wps-wp_ctrl--active');
			$('.wps-wp_ct-left .wps-wallet-transaction-container.wps_withdrawal_trans_class').remove();
			$('.wps-wp_ctl-main .wps-wp_ctlm-title.wps_wsfwp_withrawal_request_trans').remove();
			$('.wps_wcb_wallet_balance_container_fund.wps_fund_trans_class').remove();

        });

        // Recent History
        $('.wps-wp_ctlh-recent').on('click', function() {
            $(this).removeClass('wps-wp_ctrl--active');
            $('.wps-wp_ctlh-history').addClass('wps-wp_ctrl--active');
            $('.wps-wp_ctlm').removeClass('wps-wp_ctrl--active');
            $('.wps-wp_ctl-recent').addClass('wps-wp_ctrl--active');
			$('.wps-wp_ct-left .wps-wallet-transaction-container.wps_withdrawal_trans_class').remove();
			$('.wps-wp_ctl-main .wps-wp_ctlm-title.wps_wsfwp_withrawal_request_trans').remove();
			$('.wps_wcb_wallet_balance_container_fund.wps_fund_trans_class').remove();
        });

        // Add Amount
        $('a.wps-wp_ctr-add').on('click', function(e) {
			e.preventDefault();
            $('.wps-wp_ctr-con').hide();
            $('.wps-wp_ctr-add-content').show();
        });

        // Transfer Amount
        $('a.wps-wp_ctr-transfer').on('click', function(e) {
			e.preventDefault();
            $('.wps-wp_ctr-con').hide();
            $('.wps-wp_ctr-transfer-content').show();
        });

        // Withdraw Amount
        $('a.wps-wp_ctr-withdraw').on('click', function(e) {
			e.preventDefault();
            $('.wps-wp_ctr-con').hide();
            $('.wps-wp_ctr-withdraw-content').show();
        });

        // Redeem Amount
        $('a.wps-wp_ctrm-redeem').on('click', function(e) {
			e.preventDefault();
			$('.wps-wp_ctr-link').removeClass('wps-wp_ctrl--active');
			$('.wps-wp_ctr-more').addClass('wps-wp_ctrl--active');
            $('.wps-wp_ctr-con').hide();
            $('.wps-wp_ctr-redeem-content').show();
        });

		// fund request
		$('a.wps-wp_ctrm-wallet_fund_request').on('click', function(e) {
			e.preventDefault();
			$('.wps-wp_ctr-link').removeClass('wps-wp_ctrl--active');
			$('.wps-wp_ctr-more').addClass('wps-wp_ctrl--active');
            $('.wps-wp_ctr-con').hide();
            $('.wps-wp_ctr-wallet_fund_request-content').show();
			
        });
		

        // Generate QR
        $('a.wps-wp_ctrm-qr').on('click', function(e) {
			e.preventDefault();
			$('.wps-wp_ctr-link').removeClass('wps-wp_ctrl--active');
			$('.wps-wp_ctr-more').addClass('wps-wp_ctrl--active');
            $('.wps-wp_ctr-con').hide();
            $('.wps-wp_ctr-qr-content').show();
			
        });

        // Other
        $('a.wps-wp_ctrm-other').on('click', function(e) {
			e.preventDefault();
			$('.wps-wp_ctr-link').removeClass('wps-wp_ctrl--active');
			$('.wps-wp_ctr-more').addClass('wps-wp_ctrl--active');
            $('.wps-wp_ctr-con').hide();
            $('.wps-wp_ctr-other-content').show();
        });

		// show user on mobile
		$('#wps-cp_ctlt-user').on('click', function(e) {
			$('.wps-wp_ct-right').addClass('wps-wp_ctrl--active');
		});

		$('.wps-wp_ctr-close').on('click', function(e) {
			$('.wps-wp_ct-right').removeClass('wps-wp_ctrl--active');
		});

		$('.wps-wp_ctr-link.wps-wp_ctr-withdraw').on('click',function(){
			$('.wps-wp_ctl-main .wps-wallet-transaction-container.wps_withdrawal_trans_class').remove();			
			$('.wps-wp_ctr-withdraw-content .wps-wallet-transaction-container').addClass('wps_withdrawal_trans_class');
			var wps_withdrawal_trans_clone = $('.wps-wp_ctr-withdraw-content .wps-wallet-transaction-container.wps_withdrawal_trans_class').clone();
			var wps_withdrawal_trans = wps_withdrawal_trans_clone.detach();
			$('.wps-wp_ctlm').removeClass('wps-wp_ctrl--active');
			$('.wps-wp_ctl-main .wps-wp_ctlm-title.wps_wsfwp_withrawal_request_trans').remove();
			$('.wps_wcb_wallet_balance_container_fund.wps_fund_trans_class').remove();
			if( wsfwp_public_param.wallet_restrict_withdrawal == 'on' ){

				$('.wps-wp_ctl-main').append('<div class="wps-wp_ctlm-title wps_wsfwp_withrawal_request_trans"><div class="wsfw_show_user_restriction_notice">'+wsfwp_public_param.withdrawal_restrict_notice+'</div></div>');
			}else{

				$('.wps-wp_ctl-main').append('<div class="wps-wp_ctlm-title wps_wsfwp_withrawal_request_trans">'+wsfwp_public_param.withdrawal_trans_heading+'</div>');
			}
			$('.wps-wp_ctl-main').append(wps_withdrawal_trans);
		})


		



		$('a.wps-wp_ctrm-wallet_fund_request').on('click', function () {
			// Remove any previously appended elements
			$('.wps_wcb_wallet_balance_container_fund.wps_fund_trans_class').remove();
		
			// Clone the original wallet container and add the 'wps_fund_trans_class' class
			const $walletContainer = $('.wps_wcb_wallet_balance_container_fund');
			const clonedContainer = $walletContainer.clone(true).addClass('wps_fund_trans_class');
		
			// Detach the cloned container (but keep events and data)
			const wps_fund_trans = clonedContainer.detach();
		
			// Handle active states and restrictions
			$('.wps-wp_ctlm').removeClass('wps-wp_ctrl--active');
		
			if (wsfwp_public_param.wallet_restrict_fund_request === 'on') {
				const restrictionNotice = `
					<div class="wps-wp_ctlm-title wps_wsfwp_fund_request_trans">
						<div class="wsfw_show_user_restriction_notice">${wsfwp_public_param.withdrawal_restrict_notice}</div>
					</div>`;
				$('.wps-wp_ctl-main').append(restrictionNotice);
			}
		
			// Append the cloned (and detached) container to the DOM
			$('.wps-wp_ctl-main').append(wps_fund_trans);
		
			// Show the fund receive table and clean up previous elements
			$('.wps_fund_recieve_table').show();
			$('.wps-wp_ct-left .wps-wallet-transaction-container.wps_withdrawal_trans_class').remove();
			$('.wps-wp_ctl-main .wps-wp_ctlm-title.wps_wsfwp_withrawal_request_trans').remove();
			$('.wps_wcb_wallet_balance_container_fund').show();
			$('.wps-wp_ctr-wallet_fund_request-content .wps_wcb_wallet_balance_container_fund').hide();
			jQuery('.wps_fund_recieve_table').hide();
		});
		
		// Table width fix
		function adjustTableWidth() {
			var data_table_width = $('.wps-wp_ctl-head').width();
			console.log(data_table_width);
			$('.wps-wallet-transaction-container').css('width', data_table_width + 'px');
		}
		
		// Run after a delay on page load
		setTimeout(adjustTableWidth, 100);
		
		// Run on window resize
		$(window).on('resize', function() {
			adjustTableWidth();
		});
		
		

    });

	// wallet new template js code.

})( jQuery );
function copycouponcode() {
    // Get the input field containing the coupon code
    var copyText = document.getElementById("wps_wsfw_copy_test");
    
    if (navigator.clipboard) {
        // Use the Clipboard API to copy the text
        navigator.clipboard.writeText(copyText.value).then(function() {
            // Update tooltip with success message
            jQuery('#myTooltip_referral').html("Copied: " + copyText.value);
        }).catch(function(err) {
            console.error("Could not copy text: ", err);
            jQuery('#myTooltip_referral').html("Failed to copy");
        });
    } else {
        // Fallback for older browsers
        var textArea = document.createElement("textarea");
        textArea.value = copyText.value;
        textArea.style.position = "fixed"; // Prevent scrolling to the bottom
        textArea.style.top = "-9999px";
        document.body.appendChild(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
            jQuery('#myTooltip_referral').html("Copied: " + copyText.value);
        } catch (err) {
            console.error("Fallback copy failed: ", err);
            jQuery('#myTooltip_referral').html("Failed to copy");
        }

        document.body.removeChild(textArea);
    }
}
