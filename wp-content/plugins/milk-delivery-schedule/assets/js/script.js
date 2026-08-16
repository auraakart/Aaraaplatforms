jQuery(function($){

 var $box = $('.mds-box');
 if( ! $box.length ) return;

 // Move the Delivery Schedule below the All Products for Subscriptions options
 // (the "Purchase one time / Subscribe for" prompt), so it reads underneath the
 // subscribe choice rather than above it.
 var $apfs = $('.wcsatt-options-wrapper').first();
 if( ! $apfs.length ){
   $apfs = $('.wcsatt-options-prompt').first();
 }
 if( $apfs.length ){
   $box.insertAfter( $apfs );
 }

 // Place the quantity selector on its own line directly below the card.
 var $qty = $box.closest('form.cart').find('.quantity').first();
 if( $qty.length ){
   $qty.addClass('mds-qty-below').insertAfter( $box );
 }

 // Is the "Subscribe for" option currently chosen?
 function isSubscribe(){
   var $prompt = $('input.wcsatt-options-prompt-action-input');
   if( $prompt.length ){
     return $prompt.filter(':checked').val() === 'yes';
   }
   // No prompt shown (subscription is forced) → always applicable.
   return true;
 }

 // Show the schedule only for the subscribe option; disable its inputs while
 // hidden so nothing is submitted for a one-time purchase.
 function toggleBox(){
   if( isSubscribe() ){
     $box.slideDown();
     $box.find('input, select').prop('disabled', false);
   } else {
     $box.hide();
     $box.find('input, select').prop('disabled', true);
   }
 }

 $('body').on('change', 'input.wcsatt-options-prompt-action-input', toggleBox);
 // Some layouts switch the plan via a dropdown instead of the prompt radios.
 $('body').on('change', '.wcsatt-options-product select, [name^="convert_to_sub_"]', toggleBox);

 // Existing custom-days toggle.
 $('body').on('change', 'input[name=delivery_schedule]', function(){
   if( $(this).val() === 'custom' ) $('#mds-custom-days').slideDown();
   else { $('#mds-custom-days').slideUp(); $('#mds-custom-days input').prop('checked', false); }
 });

 // Initial state on load.
 toggleBox();
});
