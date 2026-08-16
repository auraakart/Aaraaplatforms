<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<style type="text/css">
.wt_iew_licence_container{ padding-bottom:20px; }
.wt_iew_licence_form_table td{ padding-bottom:20px; width:200px; }
.wt_iew_licence_form_table input[type="text"]{ width:100%; display:block; border:solid 1px #ccd0d4;}
.wt_iew_licence_form_table label{ width:100%; display:block; font-weight:bold; }
.wt_iew_licence_table{ margin-bottom:20px; }
.wt_iew_info_box {
    background: #d9edf7;
    border: solid 1px #bce8f1;
    padding: 10px;
    width: 100%;
    margin: 15px 0;
    box-sizing: border-box;
}
</style>
<div class="wt-iew-tab-content wt_iew_licence_container" data-id="<?php echo $target_id;?>">
	<h3><span><?php _e('Activate new license', 'wt-import-export-for-woo');?></span></h3>
        
        <div class="wt_iew_info_box">
	<?php _e("<b>Note</b>: Select product/s for activation as detailed below. If you have purchased the: <br>", 'wt-import-export-for-woo');?>
		<ul style="list-style: disc;padding-left: 30px;">
                        <li><?php _e("Product Import Export for WooCommerce, select <b>'Product Import Export for WooCommerce'</b>", 'wt-import-export-for-woo');?></li>
			<li><?php _e("WordPress Users &amp; WooCommerce Customers Import Export plugin, select <b>'User Import Export for WooCommerce'</b>", 'wt-import-export-for-woo');?></li>
			<li><?php _e("Order, Coupon, Subscription Export Import for WooCommerce, select <b>'Order Import Export for WooCommerce'</b>", 'wt-import-export-for-woo');?></li>
			<li><?php _e("Import Export Suite for WooCommerce, select <b>'Import Export for WooCommerce'</b>", 'wt-import-export-for-woo');?></li>
		</ul>
	</div>
	
	<form method="post" id="iew_licence_manager_form">
		<?php
        // Set nonce:
        if (function_exists('wp_nonce_field'))
        {
            wp_nonce_field(WT_IEW_PLUGIN_ID);
        }
        ?>
        <input type="hidden" name="iew_licence_manager_action" value="activate">
        <input type="hidden" name="action" value="iew_licence_manager_ajax">
		<table class="wp-list-table widefat fixed striped wt_iew_licence_form_table">
			<tr>
				<td style="width:350px;">
					<label><?php _e('Product:', 'wt-import-export-for-woo');?></label>
					<select name="wt_iew_licence_product">
						<option value="">--Select Product--</option>
						<?php
						if(is_array($products))
						{
							$products = array_reverse($products, true);
							foreach ($products as $product_slug=>$product)
							{
								?>
								<option value="<?php echo $product_slug;?>">
									<?php echo $product['product_display_name'];?>
								</option>
								<?php
							}
						}
						?>
					</select>
				</td>
				<td>
					<label><?php _e('License Key:', 'wt-import-export-for-woo');?></label>
					<input type="text" name="wt_iew_licence_key">
				</td>
				<td>
					<label><?php _e('Email:', 'wt-import-export-for-woo');?></label>
					<input type="text" name="wt_iew_licence_email">
				</td>
				<td>
					<label>&nbsp;</label>
					<button class="button button-primary wt_iew_licence_activate_btn"><?php _e('Activate', 'wt-import-export-for-woo');?></button>
				</td>
			</tr>
		</table>
	</form>
	<h3><span><?php _e('License details', 'wt-import-export-for-woo');?></span></h3>
	<div class="wt_iew_licence_list_container">
		
	</div>
</div>