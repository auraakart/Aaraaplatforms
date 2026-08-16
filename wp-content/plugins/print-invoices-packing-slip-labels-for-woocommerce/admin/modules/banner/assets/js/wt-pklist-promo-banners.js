/* global wt_pklist_promo_banners, jQuery */
( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.wt-pklist-promo-dismiss', function () {
		var $btn    = $( this );
		var action  = $btn.data( 'action' );
		var $banner = $btn.closest( '.wt-pklist-promo-banner' );

		var nonceMap = {
			'wt_pklist_dismiss_milestone_cta':  wt_pklist_promo_banners.milestone_nonce,
			'wt_pklist_dismiss_loyalty_cta':    wt_pklist_promo_banners.loyalty_nonce,
			'wt_pklist_dismiss_shipping_promo': wt_pklist_promo_banners.shipping_nonce
		};
		var nonce = nonceMap[ action ] || '';

		$.post( wt_pklist_promo_banners.ajax_url, {
			action: action,
			nonce:  nonce
		}, function ( response ) {
			if ( response.success ) {
				$banner.fadeOut( 300 );
			}
		} );
	} );
}( jQuery ) );
