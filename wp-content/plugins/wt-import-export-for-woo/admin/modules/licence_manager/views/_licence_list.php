<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}	
?>
<table class="wp-list-table widefat fixed striped wt_iew_licence_table">
	<thead>
		<tr>
			<th><?php _e('License key', 'wt-import-export-for-woo'); ?></th>
			<th style="width:150px;"><?php _e('Email', 'wt-import-export-for-woo'); ?></th>
			<th style="width:100px;"><?php _e('Status', 'wt-import-export-for-woo'); ?></th>
			<th><?php _e('Products', 'wt-import-export-for-woo'); ?></th>
			<th style="width:150px;"><?php _e('Actions', 'wt-import-export-for-woo'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		if(count($licence_data_arr)>0)
		{
			$i=0;
			foreach ($licence_data_arr as $licence_data)
			{
				$i++;
				?>
				<tr class="licence_tr">
					<td>
						<?php echo $this->mask_licence_key($licence_data['key']); ?>	
					</td>
					<td><?php echo $licence_data['email']; ?></td>
					<td class="status_td"><?php echo $this->get_status_label($licence_data['status']); ?></td>
					<td>
						<?php 
						$products=explode(",",$licence_data['products']);
						$products=array_map(function($vl){ return $this->get_display_name($vl); }, $products);
						echo implode('<br />', $products);
						?>	
					</td>
					<td class="action_td">
						<?php 
						$button_label=($licence_data['status']=='active' ? 'Deactivate' : 'Delete');
						$button_action=($licence_data['status']=='active' ? 'deactivate' : 'delete');
						?>
						<button type="button" class="button button-secondary wt_iew_licence_deactivate_btn" data-key="<?php echo $licence_data['key']; ?>" data-action="<?php echo $button_action;?>"><?php _e($button_label, 'wt-import-export-for-woo');?></button>				
					</td>
				</tr>
				<?php
			}
		}else
		{
			?>
			<tr>
				<td colspan="5" style="text-align:center;"><?php _e("No Licence details found.", 'wt-import-export-for-woo'); ?></td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>