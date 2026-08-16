<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
if ( ! defined( 'WPINC' ) ) {
    die;
}

$product_meta_selected_list = Wf_Woocommerce_Packing_List::get_option('wf_product_meta_fields');
$product_product_first_meta_key = "";
$product_first_meta_key = "";

if(is_array($product_meta_selected_list) && !empty($product_meta_selected_list)){
    $product_first_meta_key = function_exists('array_key_first') ? array_key_first($product_meta_selected_list): key( array_slice( $product_meta_selected_list, 0, 1, true ) );
}
if (null === $product_first_meta_key || "" === $product_first_meta_key) {
     $product_meta_key_label = "";
}else {
    $product_meta_key_label = $product_meta_selected_list[$product_first_meta_key];
}
?>


<div class="wt_pklist_product_meta_custom_field_form" style="display:none;">
	<div class="wt_pklist_checkout_field_tab">
		<div class="wt_pklist_custom_field_tab_head active_tab" data-target="add_new" data-add-title="<?php esc_attr_e('Add new', 'print-invoices-packing-slip-labels-for-woocommerce');?>" data-edit-title="<?php esc_attr_e('Edit','print-invoices-packing-slip-labels-for-woocommerce');?>">
			<span class="wt_pklist_custom_field_tab_head_title wfte_custom_field_tab_head_title"> <?php 
			if(empty($product_meta_selected_list)){
				esc_html_e('Add new', 'print-invoices-packing-slip-labels-for-woocommerce');
			}else{
				esc_html_e('Edit', 'print-invoices-packing-slip-labels-for-woocommerce');
			}
		?></span>
			<div class="wt_pklist_custom_field_tab_head_patch"></div>
		</div>
		<div class="wt_pklist_custom_field_tab_head wt_add_new_pro_tab" id="wt_add_new_order_meta" onclick="order_meta_add_buy_pro();" style="<?php if(empty($product_meta_selected_list)){echo 'display:none;'; } ?>">
			<span class="wt_pklist_custom_field_tab_head_title"> 
				<?php esc_html_e('Add new', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
			</span>
			<div class="wt_pklist_custom_field_tab_head_patch"></div>
		</div>
	</div>
	<div class="wt_pklist_custom_field_tab_content active_tab" data-id="add_new">
    	<div class="wt_pklist_custom_field_tab_form_row wt_pklist_custom_field_form_notice">
    		<?php
    			if(empty($product_meta_selected_list)){
    				esc_html_e('You can add custom/predefined product meta using its key', 'print-invoices-packing-slip-labels-for-woocommerce');
    			}else{
    				esc_html_e('You can edit an existing item by using its key.', 'print-invoices-packing-slip-labels-for-woocommerce');
    			}
    		?>
    	</div>
    	<div class='wt_pklist_custom_field_tab_form_row'>
			<div style='width:48%; float:left;'><?php esc_html_e('Field Name', 'print-invoices-packing-slip-labels-for-woocommerce'); ?><i style="color:red;">*</i>: <input type='text' name='wt_pklist_new_custom_field_title' value="<?php echo esc_attr($product_meta_key_label); ?>" data-required="1" style='width:100%'/></div>
			<div style='width:48%; float:right;'><?php esc_html_e('Meta Key', 'print-invoices-packing-slip-labels-for-woocommerce'); ?><i style="color:red;">*</i>: 
				<input type="text" value="<?php echo esc_attr($product_first_meta_key); ?>" name="wt_pklist_new_custom_field_key" class="wt_pklist_new_custom_field_key" oninput="do_auto_complete()">
			</div>
		</div>
		<div class='wt_pklist_custom_field_tab_form_row wfte_pro_order_meta_alert_box' style="display:none;">
			<p class="wfte-alert wfte-alert-info"><?php echo esc_html__('To add more than one custom product meta,','print-invoices-packing-slip-labels-for-woocommerce'); ?> <a href="https://www.webtoffee.com/product/woocommerce-pdf-invoices-packing-slips/" target="_blank" style="font-weight:bold; text-decoration: none;"><?php echo esc_html__('upgrade to premium version','print-invoices-packing-slip-labels-for-woocommerce'); ?> </a></p>
		</div>
	</div>
	<div class="wt_pklist_custom_field_tab_content" data-id="list_view" style="height:155px; overflow:auto;">
		
	</div>
</div>
