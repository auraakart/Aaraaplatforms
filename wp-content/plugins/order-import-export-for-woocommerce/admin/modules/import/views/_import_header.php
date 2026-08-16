<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wt-iew-settings-header">
	<h3>
		<?php esc_html_e('Import', 'order-import-export-for-woocommerce'); ?><?php if($this->step!='post_type'){ ?> <span class="wt_iew_step_head_post_type_name"></span><?php } ?>: <?php echo wp_kses_post($this->step_title); ?>
	</h3>
	<span class="wt_iew_step_info" title="<?php echo esc_attr($this->step_summary); ?>">
		<?php /* step count summary */
		echo wp_kses_post($this->step_summary);
		?>
	</span>
</div>