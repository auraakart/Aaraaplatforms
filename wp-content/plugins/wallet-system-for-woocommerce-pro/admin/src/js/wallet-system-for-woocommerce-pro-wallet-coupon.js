jQuery( document ).ready(function($) {
 
    jQuery( '.post-type-wps_cpt_coupons' ).find( '#title' ).after(
        '<a href="#" class="button generate-wallet-coupon-code">' + wsfwp_admin_coupon_param.generate_button_wallet_coupon + '</a>'
    );
    jQuery(".generate-wallet-coupon-code").click(function(){
        var $coupon_code_field = $( '#title' ),
            $coupon_code_label = $( '#title-prompt-text' ),
            $result = '';
        for ( var i = 0; i < wsfwp_admin_coupon_param.char_length; i++ ) {
            $result += wsfwp_admin_coupon_param.characters.charAt(
                Math.floor( Math.random() * wsfwp_admin_coupon_param.characters.length )
            );
        }
        $result = wsfwp_admin_coupon_param.prefix + $result + wsfwp_admin_coupon_param.suffix;
        $coupon_code_field.trigger( 'focus' ).val( $result );
        $coupon_code_label.addClass( 'screen-reader-text' );
        
        });

        jQuery('#message a').html('');
        
       

});



function wps_wsfw_switch_tab_of_wallet_coupon(evt, cityName) {
        
    // Declare all variables
    var i, tabcontent, tablinks;
  
    // Get all elements with class="tabcontent" and hide them
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
      tabcontent[i].style.display = "none";
    }
  
    // Get all elements with class="tablinks" and remove the class "active"
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
      tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
  
    // Show the current tab, and add an "active" class to the button that opened the tab
    document.getElementById(cityName).style.display = "block";
    evt.currentTarget.className += " active";
  }