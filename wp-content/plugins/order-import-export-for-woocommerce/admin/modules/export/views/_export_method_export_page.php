<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wt_iew_export_main">
	<p><?php //echo wp_kses_post($step_info['description']); ?></p>
	
    <div class="wt_iew_warn wt_iew_method_export_wrn" style="display:none;">
		<?php esc_html_e('Please select an export method', 'order-import-export-for-woocommerce');?>
	</div>

    <div class="wt_iew_warn wt_iew_export_template_wrn" style="display:none;">
        <?php esc_html_e('Please select an export template.', 'order-import-export-for-woocommerce');?>
    </div>
    <div id="product-type-message" class="updated" style="margin:0px;display: none;background: #dceff4;"><p><?php esc_html_e('The free version of this plugin exports and imports only WooCommerce Simple, Grouped and External/Affiliate product types.', 'order-import-export-for-woocommerce'); ?></p></div>
	<table class="form-table wt-iew-form-table">
		<tr>
			<th><label><?php esc_html_e('Select an export method', 'order-import-export-for-woocommerce');?></label></th>
			<td colspan="2" style="width:75%;">
                <div class="wt_iew_radio_block">
                    <?php
					if(empty($this->mapping_templates)){
						unset($this->export_obj->export_methods['template']);
					}
                    foreach($this->export_obj->export_methods as $key => $value) 
                    {
                        ?>
                        <p>
                            <input type="radio" value="<?php echo esc_attr($key);?>" id="wt_iew_export_<?php echo esc_attr($key);?>_export" name="wt_iew_export_method_export" <?php checked($this->export_method, $key);?>><b><label for="wt_iew_export_<?php echo esc_attr($key);?>_export"><?php echo esc_html($value['title']); ?></label></b> <br />
                            <span><label for="wt_iew_export_<?php echo esc_attr($key);?>_export"><?php echo wp_kses_post($value['description']); ?></label></span>
                        </p>
                        <?php
                    }
                    ?>
                </div>

			</td>
		</tr>


		<tr class="wt-iew-export-method-options wt-iew-export-method-options-template" style="display:none;">
    		<th><label><?php esc_html_e('Export template', 'order-import-export-for-woocommerce');?></label></th>
    		<td>
    			<select class="wt-iew-export-template-sele">
    				<option value="0">-- <?php esc_html_e('Select a template', 'order-import-export-for-woocommerce'); ?> --</option>
    				<?php
    				foreach($this->mapping_templates as $mapping_template)
    				{
    				?>
    					<option value="<?php echo esc_attr($mapping_template['id']);?>" <?php selected($form_data_export_template, $mapping_template['id']); ?>>
    						<?php echo esc_html($mapping_template['name']);?>
    					</option>
    				<?php
    				}
    				?>
    			</select>
    		</td>
    		<td>
    		</td>
    	</tr>
	</table>
</div>