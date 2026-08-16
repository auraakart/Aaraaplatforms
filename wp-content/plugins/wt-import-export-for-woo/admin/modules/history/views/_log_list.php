<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}
/* delete after redirect */
if(isset($_GET['wt_iew_delete_log'])) 
{
	?>
	<script type="text/javascript">
		window.location.href='<?php echo admin_url('admin.php?page='.$this->module_id.'_log'); ?>';
	</script>
	<?php
}
?>
<div class="wt_iew_history_page">
	<h2 class="wp-heading-inline"><?php _e('Import Logs', 'wt-import-export-for-woo');?></h2>
	<p>
		<?php _e('Lists developer logs mostly required for debugging purposes. Options to view detailed logs are available along with delete and download(that can be shared with the support team in case of issues).', 'wt-import-export-for-woo');?>
	</p>

	<?php
	$log_path=Wt_Import_Export_For_Woo_Log::$log_dir;
	$log_files = glob($log_path.'/*'.'.log');
	
	$max_list_count = 50;
	$total_records = count( $log_files );
	$offset=(isset($_GET['offset']) ? absint($_GET['offset']) : 0);
	$url_params_allowed=array();
	$url_params_allowed['max_data']= $max_list_count ;
	$pagination_url_params=array('wt_iew_history_log'=>$url_params_allowed, 'page'=>$this->module_id.'_log');
	
	if(is_array($log_files) && count($log_files)>0)
	{
            foreach ($log_files as $key => $value) {                  
                $date_time = str_replace('.log','',substr($value, strrpos($value, '_') + 1));
                $d = DateTime::createFromFormat('Y-m-d H i s A', $date_time);
                if ($d == false) {
                    $index = $date_time;
                } else {
                   $index = $d->getTimestamp();
                }
                $indexed_log_files[$index] = $value;                                
            }           
		krsort($indexed_log_files);
                $log_files = $indexed_log_files;

		?>
	<div class="wt_iew_bulk_action_box">
		<select class="wt_iew_bulk_action wt_iew_select">
			<option value=""><?php _e('Bulk Actions', 'wt-import-export-for-woo'); ?></option>
			<option value="delete"><?php _e('Delete', 'wt-import-export-for-woo'); ?></option>
		</select>
		<button class="button button-primary wt_iew_bulk_action_logs_btn" type="button" style="float:left;"><?php _e('Apply', 'wt-import-export-for-woo'); ?></button>
	</div>
	<?php echo self::gen_pagination_html($total_records, $max_list_count, $offset, 'admin.php', $pagination_url_params); ?>
		<table class="wp-list-table widefat fixed striped history_list_tb log_list_tb">
		<thead>
			<tr>
				<th width="100">
					<input type="checkbox" name="" class="wt_iew_history_checkbox_main">
					<?php _e("No."); ?>
				</th>
				<th class="log_file_name_col"><?php _e("File", 'wt-import-export-for-woo'); ?></th>
				<th><?php _e("Actions", 'wt-import-export-for-woo'); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php
		$i=$offset;
		$log_files = array_slice( $log_files, $offset, $max_list_count );
		foreach($log_files as $log_file)
		{
			$i++;
			$file_name=basename($log_file);
			?>
			<tr>
				<th>
					<input type="checkbox" value="<?php echo $file_name;?>" name="logfile_name[]" class="wt_iew_history_checkbox_sub">
					<?php echo $i;?>						
				</td>
				<td class="log_file_name_col"><a class="wt_iew_view_log_btn" data-log-file="<?php echo $file_name;?>"><?php echo $file_name; ?></a></td>
				<td>
					<a class="wt_iew_delete_log" data-href="<?php echo str_replace('_log_file_', $file_name, $delete_url);?>"><?php _e('Delete', 'wt-import-export-for-woo'); ?></a>
					| <a class="wt_iew_view_log_btn" data-log-file="<?php echo $file_name;?>"><?php _e("View", 'wt-import-export-for-woo');?></a>
					| <a class="wt_iew_download_log_btn" href="<?php echo str_replace('_log_file_', $file_name, $download_url);?>"><?php _e("Download", 'wt-import-export-for-woo');?></a>
				</td>
			</tr>
			<?php
		}
		?>
		</tbody>
		</table>
		<?php echo self::gen_pagination_html($total_records, $max_list_count, $offset, 'admin.php', $pagination_url_params); ?>
		<?php
	}else
	{
		?>
		<h4 class="wt_iew_history_no_records"><?php _e("No logs found.", 'wt-import-export-for-woo'); ?>
		<?php if(Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('enable_import_log')==0): ?>		
			<span> <?php _e('Please enable import log under', 'wt-import-export-for-woo'); ?> <a target="_blank" href="<?php echo admin_url('admin.php?page=wt_import_export_for_woo') ?>"><?php _e('settings', 'wt-import-export-for-woo'); ?></a></span>		
		<?php endif; ?>
		</h4>
		<?php
	}
	?>
</div>