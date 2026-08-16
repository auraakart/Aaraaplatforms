<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wt_iew_import_main">
	<p><?php _e($this->step_description, 'wt-import-export-for-woo');?></p>
        <?php if(empty($post_types)){ ?>
            <div class="wt_iew_warn wt_iew_post_type_wrn">
                <?php printf(__('Atleast one of the <b>WebToffee add-ons(Product/Reviews, User, Order/Coupon/Subscription)</b> should be activated to start importing the respective post type.
                        Go to <a href="%s" target="_blank">My accounts->API Downloads</a> to download and activate the add-on. If already installed activate the respective add-on plugin under <a href="%s" target="_blank">Plugins</a>.'),'https://www.webtoffee.com/my-account/my-api-downloads/',admin_url('plugins.php?s=webtoffee'));?>
            </div>
        <?php } ?>
	<table class="form-table wt-iew-form-table">
		<tr>
			<th><label><?php _e('Select a post type to import', 'wt-import-export-for-woo'); ?></label></th>
			<td>
				<select name="wt_iew_import_post_type">
					<option value="">-- <?php _e('Select post type', 'wt-import-export-for-woo'); ?> --</option>
					<?php
					$item_type = isset($item_type) ? $item_type : '';
					foreach($post_types as $key=>$value)
					{
						?>
						<option value="<?php echo $key;?>" <?php echo ($item_type==$key ? 'selected' : '');?>><?php echo $value;?></option>
						<?php
					}
					?>
				</select>
			</td>
			<td></td>
		</tr>
	</table>
</div>