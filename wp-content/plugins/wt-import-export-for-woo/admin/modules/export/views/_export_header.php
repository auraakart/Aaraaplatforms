<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wt-iew-settings-header">
	<h3>
		<?php _e('Export', 'wt-import-export-for-woo'); ?><?php if($this->step!='post_type'){ ?> <span class="wt_iew_step_head_post_type_name"></span><?php } ?>: <?php _e($this->step_title, 'wt-import-export-for-woo'); ?>
	</h3>
	<span class="wt_iew_step_info" title="<?php _e($this->step_summary, 'wt-import-export-for-woo'); ?>">
		<?php _e($this->step_summary, 'wt-import-export-for-woo'); ?>
	</span>
</div>