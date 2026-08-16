<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wt_iew_export_main">
	<p><?php _e($step_info['description'], 'wt-import-export-for-woo'); ?></p>
	<div class="meta_mapping_box">
		<div class="meta_mapping_box_hd_nil wt_iew_noselect">
			<?php _e('Default fields', 'wt-import-export-for-woo');?>
			<span class="meta_mapping_box_selected_count_box"><span class="meta_mapping_box_selected_count_box_num">0</span> <?php _e(' columns(s) selected', 'wt-import-export-for-woo'); ?></span>
		</div>
		<div style="clear:both;"></div>
		<div class="meta_mapping_box_con" data-sortable="0" data-loaded="1" data-field-validated="0" data-key="" style="display:inline-block;">
			<table class="wt-iew-mapping-tb wt-iew-exporter-default-mapping-tb">
				<thead>
					<tr>
			    		<th>
			    			<input type="checkbox" name="" class="wt_iew_mapping_checkbox_main">
			    		</th>
			    		<th width="35%"><?php _e('Column', 'wt-import-export-for-woo');?></th>
			    		<th><?php _e('Column name', 'wt-import-export-for-woo');?></th>
			    	</tr>
				</thead>
				<tbody>
				<?php
				$draggable_tooltip=__("Drag to rearrange the columns", 'wt-import-export-for-woo');
				$tr_count=0;
				foreach($form_data_mapping_fields as $key=>$val)
				{
					if(isset($mapping_fields[$key]))
					{
						$label=$mapping_fields[$key];
						include "_export_mapping_tr_html.php";
					  	unset($mapping_fields[$key]); //remove the field from default list
					  	$tr_count++;
					}	
				}
				if(count($mapping_fields)>0)
				{

					foreach($mapping_fields as $key=>$label)
					{
						
						if('billing_first_name' === $key){ ?>
							<tr id="columns_billing_address_fields" style="background-color: #f6f6f6">
								<th style="border: none;" colspan="3">
									<input type="checkbox" name="billing_column_group" class="columns_key wt_iew_address_checkbox_sub" value="billing_column_group" checked >
								<label class="wt_iew_mapping_column_label"><?php esc_html_e('Billing address');?></label>
								</th>
							</tr>
						<?php
						}
						if('shipping_first_name' === $key){ ?>
							<tr id="columns_shipping_address_fields" style="background-color: #f6f6f6">
								<th style="border: none;" colspan="3">
									<input type="checkbox" name="shipping_column_group" class="columns_key wt_iew_address_checkbox_sub" value="shipping_column_group" checked >
								<label class="wt_iew_mapping_column_label"><?php esc_html_e('Shipping address');?></label>
								</th>
							</tr>
						<?php
						}
						
						$disable_mapping_fields = apply_filters( 'wt_ier_disable_mapping_fields', array( 'aov', 'total_spent'));
						if( in_array( $key, $disable_mapping_fields )){
							$val = array($key, 0); //disable the field
						}else{
							$val = array($key, 1); //enable the field		
						}	
						include "_export_mapping_tr_html.php";
						$tr_count++;
					}
				}
				if($tr_count==0)
				{
					?>
					<tr>
						<td colspan="3" style="text-align:center;">
							<?php _e('No fields found.', 'wt-import-export-for-woo'); ?>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
		</div>
	</div>
	<div style="clear:both;"></div>
	<?php
	if($this->mapping_enabled_fields)
	{
		foreach($this->mapping_enabled_fields as $mapping_enabled_field_key=>$mapping_enabled_field)
		{
			$mapping_enabled_field=(!is_array($mapping_enabled_field) ? array($mapping_enabled_field, 0) : $mapping_enabled_field);
			
			if(count($form_data_mapping_enabled_fields)>0)
			{
				if(in_array($mapping_enabled_field_key, $form_data_mapping_enabled_fields))
				{
					$mapping_enabled_field[1]=1;
				}else
				{
					$mapping_enabled_field[1]=0;
				}
			}
			?>
			<div class="meta_mapping_box">
				<div class="meta_mapping_box_hd wt_iew_noselect">
					<span class="dashicons dashicons-arrow-right"></span>
					<?php echo $mapping_enabled_field[0];?>
					<?php if( 'Hidden meta' == trim( $mapping_enabled_field[0] ) ): ?>
					<span class="dashicons dashicons-editor-help wt-iew-tips" data-wt-iew-tip="<span class='wt_iew_tooltip_span'><?php _e('Lists the third party plugin custom fields and additional meta fields etc.', 'wt-import-export-for-woo');?></span>"></span>
					<?php endif; ?>
					<?php if( 'Taxonomies (cat/tags/shipping-class)' == trim( $mapping_enabled_field[0] ) ): ?>
					<span class="dashicons dashicons-editor-help wt-iew-tips" data-wt-iew-tip="<span class='wt_iew_tooltip_span'><?php _e('Lists WooCommerce based product groupings such as tags, categories and shipping-classes.', 'wt-import-export-for-woo');?></span>"></span>
					<?php endif; ?>
                                        <?php if( 'Meta (custom fields)' == trim( $mapping_enabled_field[0] ) ): ?>
					<span class="dashicons dashicons-editor-help wt-iew-tips" data-wt-iew-tip="<span class='wt_iew_tooltip_span'><?php _e('Lists custom meta data fields added by the plugins.', 'wt-import-export-for-woo');?></span>"></span>
					<?php endif; ?>
                                        <?php if( 'Attributes' == trim( $mapping_enabled_field[0] ) ): ?>
					<span class="dashicons dashicons-editor-help wt-iew-tips" data-wt-iew-tip="<span class='wt_iew_tooltip_span'><?php _e('Lists custom Attributes created. This listing can be empty when no custom attributes is created.', 'wt-import-export-for-woo');?></span>"></span>
					<?php endif; ?> 
					<span class="meta_mapping_box_selected_count_box"><span class="meta_mapping_box_selected_count_box_num">0</span> <?php _e(' columns(s) selected', 'wt-import-export-for-woo'); ?></span>
				</div>
				<div style="clear:both;"></div>
				<div class="meta_mapping_box_con" data-sortable="0" data-loaded="0" data-field-validated="0" data-key="<?php echo $mapping_enabled_field_key;?>"></div>
			</div>
			<div style="clear:both;"></div>
			<?php
		}
	}
	?>
</div>