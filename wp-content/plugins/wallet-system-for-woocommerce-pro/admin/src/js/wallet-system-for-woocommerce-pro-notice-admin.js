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


	// on clicking license check button
	$(document).on('click', '#wps_check_license', function(e){
		e.preventDefault();
			
		$.ajax({
		   	type: "POST",
		   	dataType: "JSON",
		   	url: wsfwp_admin_notice_param.ajaxurl,
			data: {
				action: "wps_wsfwp_check_license_key_status",
				nonce : wsfwp_admin_notice_param.nonce,
			},
		
			success: function(data) {
				if (data.status == true) {
					jQuery("div.wps-subsc_notice p").html(data.msg);
					setTimeout(function(){
		              	location = wsfwp_admin_notice_param.wsfwp_admin_param_location;
		          	}, 2000);					
				}
				else{
					jQuery("div.wps-subsc_notice p").html(data.msg);
					setTimeout(function(){
		              	location = wsfwp_admin_notice_param.wsfwp_admin_param_location;
		          	}, 2000);
				}
			},
		})
		.fail(function ( response ) {
			location = wsfwp_admin_notice_param.wsfwp_admin_param_location;
		});
		
	});

	
})( jQuery );
