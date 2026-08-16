<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<table class="wp-list-table widefat fixed striped ftp_list_tb" style="margin-bottom:15px;">
<thead>
	<tr>
		<th><?php _e("Profile name"); ?></th>
		<th><?php _e("Server Host/IP"); ?></th>
		<th><?php _e("Actions"); ?></th>
	</tr>
</thead>
<tbody>
<?php
if(isset($ftp_list) && is_array($ftp_list) && count($ftp_list)>0)
{
	foreach($ftp_list as $key =>$ftp_item)
	{
		?>
		<tr>
			<td><?php echo $ftp_item['name']; ?></td>
			<td><?php echo $ftp_item['server']; ?></td>
			<td>
				<div class="wt_iew_data_dv">
					<span class="wt_iew_ftp_id"><?php echo $ftp_item['id']; ?></span>
					<span class="wt_iew_profilename"><?php echo $ftp_item['name']; ?></span>
					<span class="wt_iew_hostname"><?php echo $ftp_item['server']; ?></span>
					<span class="wt_iew_ftpuser"><?php echo $ftp_item['user_name']; ?></span>
					<span class="wt_iew_ftppassword"><?php echo $ftp_item['password']; ?></span>
					<span class="wt_iew_ftpport"><?php echo $ftp_item['port']; ?></span>
					<span class="wt_iew_useftps"><?php echo $ftp_item['ftps']; ?></span>
					<span class="wt_iew_passivemode"><?php echo $ftp_item['passive_mode']; ?></span>
					<span class="wt_iew_ftpexport_path"><?php echo $ftp_item['export_path']; ?></span>
					<span class="wt_iew_ftpimport_path"><?php echo $ftp_item['import_path']; ?></span>
					<span class="wt_iew_is_sftp"><?php echo $ftp_item['is_sftp']; ?></span>
				</div>
				<a class="wt_iew_ftp_edit wt_iew_action_btn" data-id="<?php echo esc_attr($ftp_item['id']); ?>"><?php _e('Edit');?></a> | <a class="wt_iew_ftp_delete wt_iew_action_btn" data-id="<?php echo esc_attr($ftp_item['id']); ?>"><?php _e('Delete');?></a>
				<?php
				if($this->popup_page==1)
				{
				?>
				 | <a class="wt_iew_ftp_use wt_iew_action_btn" data-id="<?php echo esc_attr($ftp_item['id']); ?>" title="<?php _e('Use this profile');?>"><?php _e('Use this profile');?></a>
				<?php
				}
				?>
			</td>
		</tr>
		<?php	
	}
}else
{
	?>
	<tr>
		<td colspan="3" style="text-align:center;">
			<?php _e("No FTP profiles found."); ?> <?php _e("Click here to"); ?> <a class="wt_iew_ftp_add" style="cursor:pointer;"><?php _e('Add new');?></a>
		</td>
	</tr>
	<?php
}
?>
</tbody>
</table>