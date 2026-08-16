<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wt_iew_history_page">
	<h2 class="wp-heading-inline"><?php esc_html_e('Import/Export history', 'order-import-export-for-woocommerce');?></h2>

	<div style="margin-bottom:25px;">
		<?php esc_html_e('Lists the runs and the status corresponding to every import/export with options to re-run, view detailed log or delete entry.', 'order-import-export-for-woocommerce');?>
	</div>


	<div class="wt_iew_history_settings">
		<form action="admin.php">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->module_id );?>">
			<?php	
			if(array_filter(array_column($filter_by, 'values')))
			{
			?>
				<div class="wt_iew_history_settings_hd"><?php esc_html_e('Filter', 'order-import-export-for-woocommerce'); ?></div>
				<div class="wt_iew_history_settings_form_group_box">
					<?php
					foreach ($filter_by as $filter_by_key => $filter_by_value) 
					{
						if(count($filter_by_value['values'])>0)
						{					
						?>
							<div class="wt_iew_history_settings_form_group">
								<label><?php echo wp_kses_post($filter_by_value['label']); ?></label>
								<select name="wt_iew_history[filter_by][<?php echo esc_attr( $filter_by_key );?>]" class="wt_iew_select">
									<option value=""><?php esc_html_e('All', 'order-import-export-for-woocommerce'); ?></option>
									<?php
									$val_labels=$filter_by_value['val_labels'];
									foreach($filter_by_value['values'] as $val)
									{
										?>
										<option value="<?php echo esc_attr($val);?>" <?php selected($filter_by_value['selected_val'], $val);?>><?php echo esc_html(isset($val_labels[$val]) ? $val_labels[$val] : $val);?></option>
										<?php
									}
									?>
								</select>
							</div>
						<?php
						}
					}
					?>
				</div>
			<?php 
			}
			?>

			<div class="wt_iew_history_settings_form_group_box">
				<div class="wt_iew_history_settings_form_group">
					<label><?php esc_html_e('Sort by', 'order-import-export-for-woocommerce'); ?></label>
					<select name="wt_iew_history[order_by]" class="wt_iew_select">
						<?php
						foreach ($order_by as $key => $value) 
						{
							?>
							<option value="<?php echo esc_attr($key);?>" <?php selected($order_by_val, $key);?>><?php echo esc_html($value['label']);?></option>
							<?php
						}
						?>
					</select>
				</div>
				<div class="wt_iew_history_settings_form_group">
					<label><?php esc_html_e('Max record/page', 'order-import-export-for-woocommerce'); ?></label>
					<input type="text" name="wt_iew_history[max_data]" value="<?php echo esc_attr($this->max_records);?>" class="wt_iew_text" style="width:50px;">
				</div>
			</div>
			<div class="wt_iew_history_settings_form_group_box">
				<input type="hidden" name="offset" value="0">
				<?php
				if($list_by_cron) /* list by cron */
				{
					?>
					<input type="hidden" name="wt_iew_cron_id" value="<?php echo esc_attr($cron_id);?>">
					<?php
				}
				?>
				<button class="button button-primary" type="submit" style="float:left;"><?php esc_html_e('Apply', 'order-import-export-for-woocommerce'); ?></button>
			</div>
		</form>
	</div>
	
	<div class="wt_iew_bulk_action_box">
		<select class="wt_iew_bulk_action wt_iew_select">
			<option value=""><?php esc_html_e('Bulk Actions', 'order-import-export-for-woocommerce'); ?></option>
			<option value="delete"><?php esc_html_e('Delete', 'order-import-export-for-woocommerce'); ?></option>
		</select>
		<button class="button button-primary wt_iew_bulk_action_btn" type="button" style="float:left;"><?php esc_html_e('Apply', 'order-import-export-for-woocommerce'); ?></button>
	</div>
	<?php
	echo wp_kses_post(self::gen_pagination_html($total_records, $this->max_records, $offset, 'admin.php', $pagination_url_params));
	?>
	<?php
	if(isset($history_list) && is_array($history_list) && count($history_list)>0)
	{
		?>
		<table class="wp-list-table widefat fixed striped history_list_tb">
		<thead>
			<tr>
				<th width="100">
					<input type="checkbox" name="" class="wt_iew_history_checkbox_main">
					<?php esc_html_e("No.", 'order-import-export-for-woocommerce'); ?>
				</th>
				<th width="50"><?php esc_html_e("Id", 'order-import-export-for-woocommerce'); ?></th>
				<th><?php esc_html_e("Action type", 'order-import-export-for-woocommerce'); ?></th>
				<th><?php esc_html_e("Post type", 'order-import-export-for-woocommerce'); ?></th>
				<th><?php esc_html_e("Started at", 'order-import-export-for-woocommerce'); ?></th>
				<th>
					<?php esc_html_e("Status", 'order-import-export-for-woocommerce'); ?>
					<span class="dashicons dashicons-editor-help wt-iew-tips" 
						data-wt-iew-tip="
						<span class='wt_iew_tooltip_span'>
							<?php 
							// translators: 1: bold tag open, 2: bold tag close
							echo wp_kses_post(sprintf(__('%1$sSuccess%2$s - Process completed successfully', 'order-import-export-for-woocommerce'), '<b>', '</b>'));?></span><br />
						<span class='wt_iew_tooltip_span'><?php 
						// translators: 1: bold tag open, 2: bold tag close
						echo wp_kses_post(sprintf(__('%1$sFailed%2$s - Failed process triggered due to connection/permission or similar issues(unable to establish FTP/DB connection, write permission issues etc.)', 'order-import-export-for-woocommerce'), '<b>', '</b>'));?> </span><br />
						<span class='wt_iew_tooltip_span'><?php 
						// translators: 1: bold tag open, 2: bold tag close
						echo wp_kses_post(sprintf(__('%1$sRunning/Incomplete%2$s - Process that are running currently or that may have been terminated unknowingly(e.g, closing a browser tab while in progress etc)', 'order-import-export-for-woocommerce'), '<b>', '</b>'));?> </span>">			
					</span>
				</th>
				<th>
					<?php esc_html_e("Actions", 'order-import-export-for-woocommerce'); ?>
					<span class="dashicons dashicons-editor-help wt-iew-tips" 
						data-wt-iew-tip=" <span class='wt_iew_tooltip_span'><?php esc_html_e('Re-run will take the user to the respective screen depending on the corresponding action type and the user can initiate the process accordingly.', 'order-import-export-for-woocommerce');?></span>"></span>
				</th>
			</tr>
		</thead>
		<tbody>
		<?php
		$i=$offset;
		foreach($history_list as $key =>$history_item)
		{
			$i++;
			?>
			<tr>
				<th>
					<input type="checkbox" value="<?php echo esc_attr($history_item['id']);?>" name="history_id[]" class="wt_iew_history_checkbox_sub">
					<?php echo esc_html($i);?>						
				</td>
				<td><?php echo esc_html($history_item['id']); ?></td>
				<td><?php echo esc_html(ucfirst($history_item['template_type'])); ?></td>
				<td><?php echo esc_html(ucfirst($history_item['item_type'])); ?></td>
				<td><?php echo esc_html(date_i18n('Y-m-d h:i:s A', $history_item['created_at'])); ?></td>
				<td>
					<?php
					echo esc_html(isset(self::$status_label_arr[$history_item['status']]) ? self::$status_label_arr[$history_item['status']] : __('Unknown', 'order-import-export-for-woocommerce'));
					?>
				</td>
				<td>
					<a class="wt_iew_delete_history" data-href="<?php echo esc_url(str_replace('_history_id_', $history_item['id'], $delete_url));?>"><?php esc_html_e('Delete', 'order-import-export-for-woocommerce'); ?></a>
					<?php
					$form_data_raw = wp_unslash($history_item['data']);
					$form_data = is_array($form_data_raw) ? 
							array_map(function($item) {
								return is_string($item) ? json_decode($item, true) : $item;
							}, $form_data_raw) : 
							json_decode($form_data_raw, true);

					$action_type=$history_item['template_type'];
					if($form_data && is_array($form_data))
					{
						$to_process=(isset($form_data['post_type_form_data']) && isset($form_data['post_type_form_data']['item_type']) ? $form_data['post_type_form_data']['item_type'] : '');
						if($to_process!="")
						{
							if(Wt_Import_Export_For_Woo_Order_Admin_Basic::module_exists($action_type))
							{
								$action_module_id=Wt_Import_Export_For_Woo_Order_Basic::get_module_id($action_type);
								$url=admin_url('admin.php?page='.$action_module_id.'&wt_iew_rerun='.$history_item['id']);
								?>
								 | <a href="<?php echo esc_url($url);?>" target="_blank"><?php esc_html_e("Re-Run", 'order-import-export-for-woocommerce');?></a>
								<?php
							}
						}
					}
					if($action_type=='import' && Wt_Import_Export_For_Woo_Order_Admin_Basic::module_exists($action_type))
					{
						$action_module_obj=Wt_Import_Export_For_Woo_Order_Basic::load_modules($action_type);
						$log_file_name=$action_module_obj->get_log_file_name($history_item['id']);
						$log_file_path=$action_module_obj->get_file_path($log_file_name);
						if(file_exists($log_file_path))
						{
						?>
							| <a class="wt_iew_view_log_btn" data-history-id="<?php echo esc_attr($history_item['id']);?>"><?php esc_html_e("View log", 'order-import-export-for-woocommerce');?></a>
						<?php
						}
					}
                    if($action_type=='export' && Wt_Import_Export_For_Woo_Order_Admin_Basic::module_exists($action_type))
					{
                        $export_download_url=wp_nonce_url(admin_url('admin.php?wt_iew_export_download=true&file='.$history_item['file_name']), WT_IEW_PLUGIN_ID_BASIC);
						?>
                            | <a class="wt_iew_export_download_btn" target="_blank" href="<?php echo esc_url($export_download_url);?>"><?php esc_html_e('Download', 'order-import-export-for-woocommerce');?></a>
						<?php
					}                                        
					?>
				</td>
			</tr>
			<?php	
		}
		?>
		</tbody>
		</table>
		<?php
		echo wp_kses_post(self::gen_pagination_html($total_records, $this->max_records, $offset, 'admin.php', $pagination_url_params));
	}else
	{
		?>
		<h4 class="wt_iew_history_no_records"><?php esc_html_e("No records found.", 'order-import-export-for-woocommerce'); ?></h4>
		<?php
	}
	?>
</div>