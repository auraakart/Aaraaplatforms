

jQuery( document ).ready(function() {

   setTimeout(() => {         
        if (wsfwp_public_param_block.gateway_enaled == 'on' ){
          if ( wsfwp_public_param_block.wsfw_restriction_msg_checkout != '' ) {
            jQuery('.wc-block-checkout__payment-method .wc-block-components-title').append('<br><div class="wps_restrict_gateway_message"> '+wsfwp_public_param_block.wsfw_restriction_msg_checkout+'</div>');
          }
        }
        if (wsfwp_public_param_block.wsfwp_negative_balance_msg != '' ) {
            jQuery(wsfwp_public_param_block.wsfwp_negative_balance_msg).insertAfter("#radio-control-wc-payment-method-options-wps_wcb_wallet_payment_gateway__label");
        }
    }, "1000");
});
