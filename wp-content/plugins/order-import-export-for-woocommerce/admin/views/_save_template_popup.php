<?php
/**
 * Template saving popup HTML for Import/Export
 *
 * @link            
 *
 * @package  Wt_Import_Export_For_Woo
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wt_iew_template_name wt_iew_popup" data-save-label="<?php esc_attr_e('Save', 'order-import-export-for-woocommerce');?>" data-saveas-label="<?php esc_attr_e('Save as', 'order-import-export-for-woocommerce');?>">
	<div class="wt_iew_popup_hd">
		<span style="line-height:40px;" class="dashicons dashicons-edit"></span>
		<span class="wt_iew_popup_hd_label"></span>
		<div class="wt_iew_popup_close">X</div>
	</div>
	<div class="wt_iew_warn_box">
		<div class="wt_iew_warn wt_iew_template_name_wrn">
			<?php esc_html_e('Please enter name', 'order-import-export-for-woocommerce');?> 
		</div>
	</div>
	<div class="wt_iew_template_name_box">
		<label class="wt_iew_template_name_label"><?php esc_html_e('Template name', 'order-import-export-for-woocommerce');?></label>
		<input type="text" name="wt_iew_template_name_field" class="wt_iew_text_field wt_iew_template_name_field">
		<div class="wt_iew_popup_footer">
			<button type="button" name="" class="button-secondary wt_iew_popup_cancel">
				<?php esc_html_e('Cancel', 'order-import-export-for-woocommerce');?> 
			</button>
			<button type="button" name="" class="button-primary wt_iew_template_create_btn"></button>	
		</div>
	</div>
</div>