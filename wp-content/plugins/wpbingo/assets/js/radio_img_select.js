/*
 *
 * Revo_Options_radio_img function
 * Changes the radio select option, and changes class on images
 *
 */
function bwp_radio_img_select(relid, labelclass){
	jQuery(this).prev('input[type="radio"]').prop('checked');

	jQuery('.bwp-radio-img-'+labelclass).removeClass('bwp-radio-img-selected');	
	
	jQuery('label[for="'+relid+'"]').addClass('bwp-radio-img-selected');
}//function