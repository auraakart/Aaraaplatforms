<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<tr id="columns_<?php echo $key;?>">
	<td>
		<input type="checkbox" name="columns_key[]" class="columns_key wt_iew_mapping_checkbox_sub" value="<?php echo $key;?>" <?php echo ($checked==1 ? 'checked' : ''); ?>>
	</td>
	<td>
		<label class="wt_iew_mapping_column_label"><?php echo $label;?></label>
	</td>
	<td>
		<input type="hidden" name="columns_val[]" class="columns_val" value="<?php echo $val;?>" data-type="<?php echo $type;?>">
		<span data-wt_iew_popover="1" data-title="" data-content-container=".wt_iew_mapping_field_editor_container" class="wt_iew_mapping_field_val"><?php echo $val;?></span>		
	</td>
	<td>
		<span style="margin-left:20px;cursor:pointer" data-wt_iew_popover="1" data-title="" data-content-container=".wt_iew_mapping_field_editor_container" class="dashicons dashicons-edit wt-iew-tips" data-wt-iew-tip="<span class='wt_iew_tooltip_span'><?php _e('Use expression', 'wt-import-export-for-woo');?></span>"></span>
	</td>
	<td style="background-color:#f6f6f6;">
		<?php 
		$clean_key = str_replace(array( '{', '}' ), '', $val);
		$prev_value = '-';
		if (isset($file_heading_default_fields[$clean_key])) {
			$prev_value = $file_heading_default_fields[$clean_key];
		} elseif(isset($file_heading_meta_fields[$clean_key])) {
			$prev_value = $file_heading_meta_fields[$clean_key];
		}elseif(isset($sample_data[$clean_key])) {
			$prev_value = $sample_data[$clean_key];
		}
		?>
		<label class="wt_iew_mapping_column_preview"><?php echo Wt_Iew_IE_Helper::wt_truncate( esc_html($prev_value), 80); ?></label>
	</td>	
</tr>