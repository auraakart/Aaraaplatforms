<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<ul class="wt_iew_sub_tab">
	<li style="border-left:none; padding-left: 0px;" data-target="ftp-profiles"><a><?php _e('FTP profiles', 'wt-import-export-for-woo'); ?></a></li>
	<li data-target="add-new-ftp"><a><?php _e('Add new');?></a></li>
</ul>
<div class="wt_iew_sub_tab_container">
	<div class="wt_iew_sub_tab_content" data-id="add-new-ftp" style="display:block;">
		<h3 class="wt_iew_form_title"> <?php _e("Add new FTP profile", 'wt-import-export-for-woo'); ?></h3>
		<form method="post" action="<?php echo esc_url($_SERVER["REQUEST_URI"]);?>" id="wt_iew_ftp_form">
			<input type="hidden" value="0" name="wt_iew_ftp_id" />
			<input type="hidden" value="iew_ftp_ajax" name="action" />
			<input type="hidden" value="save_ftp" name="iew_ftp_action" />				
			<?php
            // Set nonce:
            if (function_exists('wp_nonce_field'))
            {
                wp_nonce_field(WT_IEW_PLUGIN_ID);
            }
            ?>
			<table class="form-table wt-iew-form-table">
				<tr>
					<th><label><?php _e("Profile name", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="text" name="wt_iew_profilename">
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("FTP Server Host/IP", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="text" name="wt_iew_hostname" placeholder="XXX.XXX.XXX.XXX">
						<span class="wt-iew_form_help"><?php _e("Enter your FTP server hostname.", 'wt-import-export-for-woo'); ?></span>
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("FTP User Name", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="text" name="wt_iew_ftpuser">
						<span class="wt-iew_form_help"><?php _e("Enter your FTP username.", 'wt-import-export-for-woo'); ?></span>
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("FTP Password", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="password" name="wt_iew_ftppassword">
						<span class="wt-iew_form_help"><?php _e("Enter your FTP password.", 'wt-import-export-for-woo'); ?></span>
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("FTP Port", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="number" step="1" name="wt_iew_ftpport" value="21">
						<span class="wt-iew_form_help"><?php _e("Enter your FTP port. Use 21 for FTP and 22 for SFTP", 'wt-import-export-for-woo'); ?></span>
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("Default export path", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="text" name="wt_iew_ftpexport_path" value="/">
						<span class="wt-iew_form_help"><?php _e("Default export path. You can add specific path while exporting.", 'wt-import-export-for-woo'); ?></span>
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("Default import path", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="text" step="1" name="wt_iew_ftpimport_path" value="/">
						<span class="wt-iew_form_help"><?php _e("Default import path. You can add specific path while importing.", 'wt-import-export-for-woo'); ?></span>
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("Select encryption", 'wt-import-export-for-woo'); ?>
                                            <span class="dashicons dashicons-editor-help wt-iew-tips" data-wt-iew-tip="<span class='wt_iew_tooltip_span'><?php _e('Sends data over a network with SSL encryption.', 'wt-import-export-for-woo');?></span>"></span>
                                            </label>
                                        </th>
					<td>
						<input type="radio" name="wt_iew_useftps" class="" value="1"> Secure (FTP over TLS) &nbsp;&nbsp;
						<input type="radio" name="wt_iew_useftps" class="" value="0" checked="checked"> Insecure (plain FTP) &nbsp;&nbsp;
			            <span class="wt-iew_form_help"><?php _e("Enable this send data over a network with SSL encryption", 'wt-import-export-for-woo'); ?></span>
        	        </td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("Transfer mode", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="radio" name="wt_iew_passivemode" class="" value="1"> Passive &nbsp;&nbsp;
						<input type="radio" name="wt_iew_passivemode" class="" value="0" checked="checked"> Active &nbsp;&nbsp;
			            <span class="wt-iew_form_help"><?php _e("Enable this to turns passive mode on or off", 'wt-import-export-for-woo'); ?></span>
        	        </td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("Select Protocol", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<input type="radio" name="wt_iew_is_sftp" class="" value="1"> SFTP &nbsp;&nbsp;
						<input type="radio" name="wt_iew_is_sftp" class="" value="0" checked="checked"> FTP &nbsp;&nbsp;
        	        </td>
					<td></td>
				</tr>
			</table>
			<?php 
			$settings_button_title=__('Save settings', 'wt-import-export-for-woo');
			$before_button_text=($this->popup_page==1 ? '<span style="line-height:40px;"><input type="checkbox" id="wt_iew_add_and_use_ftp" name="wt_iew_add_and_use_ftp" value="1"> <label for="wt_iew_add_and_use_ftp">'.__('Use this profile and close the popup', 'wt-import-export-for-woo').'</label></span>' : '');
			$after_button_text='<input type="button" name="wt_iew_abort_test_ftp" value="'.__('Abort FTP testing', 'wt-import-export-for-woo').'" class="button button-secondary wt_iew_abort_test_ftp" style="float:right; margin-right:10px; display:none;" />';
			$after_button_text.='<input type="button" name="wt_iew_test_ftp_form" value="'.__('Test FTP', 'wt-import-export-for-woo').'" class="button button-secondary wt_iew_test_ftp_form" style="float:right; margin-right:10px;" />';
			include WT_IEW_PLUGIN_PATH."admin/views/admin-settings-save-button.php";
		    ?>
	    </form>
	</div>
	<div class="wt_iew_sub_tab_content" data-id="ftp-profiles">
		<h3><?php _e("FTP profiles", 'wt-import-export-for-woo'); ?></h3>
		<div class="wt_iew_ftp_list">
			<?php
			$this->get_ftplist_html();
			?>
		</div>
	</div>
</div>