<?php
if (!defined('ABSPATH')) {
    exit;
}
if(isset($cron_list) && is_array($cron_list) && count($cron_list)>0)
{
	?>
<div class="cron_list_wrapper">
	<table class="wp-list-table widefat fixed striped cron_list_tb" style="margin-bottom:55px;">
	<thead>
		<tr>
			<th width="40"><?php _e("No.", 'wt-import-export-for-woo'); ?></th>
			<th width="160"><?php _e("Name", 'wt-import-export-for-woo'); ?></th>			
			<th width="80"><?php _e("Action type", 'wt-import-export-for-woo'); ?></th>
			<th width="70"><?php _e("Post type", 'wt-import-export-for-woo'); ?></th>
			<th width="100"><?php _e("Cron type", 'wt-import-export-for-woo'); ?></th>
			<th width="80">
				<?php _e("Status", 'wt-import-export-for-woo'); ?>
				<span class="dashicons dashicons-editor-help wt-iew-tips" 
					data-wt-iew-tip="
					<span class='wt_iew_tooltip_span'><?php echo sprintf(__('%sFinished%s - Process completed'), '<b>', '</b>');?></span><br />
					<span class='wt_iew_tooltip_span'><?php echo sprintf(__('%sDisabled%s - The process has been disabled temporarily'), '<b>', '</b>');?> </span><br />
					<span class='wt_iew_tooltip_span'><?php echo sprintf(__('%sRunning%s - Process currently active and running'), '<b>', '</b>');?> </span><br />
					<span class='wt_iew_tooltip_span'><?php echo sprintf(__('%sUploading%s - Processed records are being uploaded to the specified location, finalizing export.'), '<b>', '</b>');?> </span><br />
					<span class='wt_iew_tooltip_span'><?php echo sprintf(__('%sDownloading%s - Input records are being downloaded from the specified location prior to import process.'), '<b>', '</b>');?> </span>">			
				</span>
			</th>
			<th><?php _e("Time", 'wt-import-export-for-woo'); ?></th>
			<th width="150"><?php _e("History", 'wt-import-export-for-woo'); ?></th>
			<th width="140"><?php _e("Actions", 'wt-import-export-for-woo'); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php
	$i=0;
	foreach($cron_list as $key =>$cron_item)
	{
		$i++;
                $item_type = ucfirst($cron_item['item_type']);
		?>
		<tr>
			<td><?php echo $i;?></td>
			<td>
				<?php
				$cron_data = ($cron_item['cron_data'] != "" ? maybe_unserialize($cron_item['cron_data']) : array());
				echo isset($cron_data['schedule_name']) ?  esc_html($cron_data['schedule_name']) : ''
				?>
			</td>			
			<td><?php echo ucfirst($cron_item['action_type']); ?></td>
			<td><?php echo $item_type; ?></td>
			<td><?php echo ($cron_item['schedule_type']=='server_cron' ? __('Server cron', 'wt-import-export-for-woo') : __('WordPress cron', 'wt-import-export-for-woo')); ?></td>
			<td>
				<span class="wt_iew_badge" style="<?php echo (isset(self::$status_color_arr[$cron_item['status']]) ? 'background:'.self::$status_color_arr[$cron_item['status']] : ''); ?>">
					<?php
					echo (isset(self::$status_label_arr[$cron_item['status']]) ? self::$status_label_arr[$cron_item['status']] : __('Unknown'));
					?>
				</span>
				<?php
				/**
				* 	Show completed percentage if status is running
				*/
				if($cron_item['status']==self::$status_arr['running'] && $cron_item['history_id']>0)
				{
					$history_module_obj=Wt_Import_Export_For_Woo::load_modules('history');
					if(!is_null($history_module_obj))
					{
						$history_entry=$history_module_obj->get_history_entry_by_id($cron_item['history_id']);
						if($history_entry)
						{
							echo '<br />'.number_format((($history_entry['offset']/$history_entry['total'])*100), 2).'% '.__(' Done');
						}
					}
				}
				?>
			</td>
			<td>
				<?php
					if($cron_item['status']==self::$status_arr['finished'] || $cron_item['status']==self::$status_arr['disabled'])
					{
						if($cron_item['last_run']>0)
						{
							echo __('Last run: ').date_i18n('Y-m-d h:i:s A', $cron_item['last_run']).'<br />';
						}

						/**
						*	Finished, so waiting for next run
						*/
						if($cron_item['status']==self::$status_arr['finished'] && $cron_item['start_time']>0 && $cron_item['start_time']!=$cron_item['last_run'])
						{
							echo __('Next run: ').date_i18n('Y-m-d h:i:s A', $cron_item['start_time']).'<br />';
						}
					}

					if($cron_item['status']==self::$status_arr['running'] || $cron_item['status']==self::$status_arr['uploading'] || $cron_item['status']==self::$status_arr['downloading'])
					{
						if($cron_item['last_run']>0 && $cron_item['start_time']!=$cron_item['last_run'])
						{
							echo __('Last run: ').date_i18n('Y-m-d h:i:s A', $cron_item['last_run']).'<br />';
						}else
						{
							echo __('Started at: ').date_i18n('Y-m-d h:i:s A', $cron_item['start_time']).'<br />';
						}
					}

					if($cron_item['status']==self::$status_arr['not_started'] && $cron_item['start_time']>0)
					{
						echo __('Will start at: ').date_i18n('Y-m-d h:i:s A', $cron_item['start_time']).'<br />';
					}
				?>
			</td>
			<td>
				<?php
				$history_arr=($cron_item['history_id_list']!="" ? maybe_unserialize($cron_item['history_id_list']) : array());
				$history_arr=(is_array($history_arr) ? $history_arr : array());
				if(count($history_arr)>0)
				{
					$history_module_obj=Wt_Import_Export_For_Woo::load_modules('history');
					if(!is_null($history_module_obj))
					{
						$history_entry=$history_module_obj->get_history_entry_by_id($history_arr);
						if($history_entry)
						{
							_e(sprintf('Total %d entries found.', count($history_entry)));
					?>
						<br />
						<a target="_blank" href="<?php echo admin_url('admin.php?page='.Wt_Import_Export_For_Woo::get_module_id('history').'&wt_iew_cron_id='.$cron_item['id']);?>">
							<?php _e('View');?> <span class="dashicons dashicons-external"></span>
						</a>
					<?php
						}
					}
				}else
				{
					_e('No entries found.', 'wt-import-export-for-woo');
				}
				?>
			</td>
			<td>
				<?php
				$page_id=(isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '');

				/* status change section */
				$action_label=__('Disable');
				$action='disable';
				if($cron_item['status'] == self::$status_arr['disabled'])
				{
					$action='enable';
					$action_label=__('Enable');
				}
				$action_url=wp_nonce_url(admin_url('admin.php?page='.$page_id.'&wt_iew_change_schedule_status='.$action.'&wt_iew_cron_id='.$cron_item['id']), WT_IEW_PLUGIN_ID);
				
				/* delete section */
				$delete_url=wp_nonce_url(admin_url('admin.php?page='.$page_id.'&wt_iew_delete_schedule=1&wt_iew_cron_id='.$cron_item['id']), WT_IEW_PLUGIN_ID);
				
                                /* edit action */
                                if($cron_item['action_type'] == 'import'){
                                    $edit_url = admin_url('admin.php?page=wt_import_export_for_woo_import&wt_iew_cron_edit_id='.$cron_item['id']);
                                }else{
                                    $edit_url = admin_url('admin.php?page=wt_import_export_for_woo_export&wt_iew_cron_edit_id='.$cron_item['id']);
                                }
                                
                                if(!class_exists("Wt_Import_Export_For_Woo_$item_type")){
                                    $edit_url = '#';
                                }
                                
				?>
                            <a class="wt_iew_cron_edit wt_iew_action_btn" href="<?php echo $edit_url; ?>" ><?php _e('Edit');?></a> | <a href="<?php echo $action_url;?>"><?php echo $action_label;?></a> | <a class="wt_iew_delete_cron" data-href="<?php echo $delete_url;?>"><?php _e('Delete'); ?></a>
				<?php
				if($cron_item['schedule_type']=='server_cron')
				{
					$cron_url=$this->generate_cron_url($cron_item['id'], $cron_item['action_type'], $cron_item['item_type']);
				?>
					| <a class="wt_iew_cron_url" data-href="<?php echo $cron_url;?>" title="<?php _e('Generate new cron URL.');?>"><?php _e('Cron URL');?></a>
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
</div>

	<?php //include plugin_dir_path(__FILE__).'/_schedule_update.php'; ?>
	<?php
}else
{
	?>
	<h4 style="margin-bottom:55px; text-align:center; background:#fff; padding:15px 0px;"><?php _e("No scheduled actions found."); ?></h4>
	<?php
}
?>