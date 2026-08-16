<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
if (!defined('ABSPATH')) {
	exit;
}
?>

<style type="text/css">
	.wf_loader_bg {
		background: rgba(255, 255, 255, .5) url(<?php echo esc_url(WF_PKLIST_PLUGIN_URL); ?>assets/images/wt_logo_loading.gif) center no-repeat;
	}

	.wf_cst_loader {
		box-sizing: border-box;
		position: absolute;
		z-index: 1000;
		width: inherit;
		height: 800px;
		left: 0px;
		display: none;
		top: 0;
	}

	.wf_cst_warn_box {
		padding: 20px;
		padding-bottom: 0px;
	}

	.wf_cst_warn {
		display: inline-block;
		width: 100%;
		box-sizing: border-box;
		padding: 10px;
		background-color: #fff8e5;
		border-left: solid 2px #ffb900;
		color: #333;
	}

	.wf_new_template_wrn_sub {
		display: none;
	}

	.wf_pklist_save_theme_sub_loading {
		display: none;
	}

	.wf_missing_wrn {
		display: inline-block;
		text-decoration: none;
		text-align: center;
		font-size: 12px;
		font-weight: normal;
		width: 100%;
		margin: 0%;
		box-sizing: border-box;
		padding: 4px;
		background-color: #fff8e5;
		border: dashed 1px #ffb900;
		color: #333;
	}

	.wf_missing_wrn:hover {
		color: #333;
	}

	.wf_customize_sidebar {
		float: right;
		width: 28%;
	}

	.wf_customize_sidebartop {
		float: right;
		width: 28%;
	}

	.wf_side_panel * {
		box-sizing: border-box;
	}

	/* Pro feature inline CTA tooltip (speech-bubble style) */
	.wt_pklist_pro_inline_cta {
		position: relative;
		float: left;
		width: 100%;
		box-sizing: border-box;
		padding-top: 0;
		overflow: visible;
		/* Cancels .wf_side_panel margin-bottom (10px) so the banner sits flush under the row above */
		margin-top: -10px;
	}
	.wt_pklist_pro_inline_cta_card {
		position: relative;
		background: #FFFFFF;
		border: 1px solid #E5E5E5;
		border-radius: 16px;
		padding: 15px 5px 15px 15px;
		box-shadow: 0px 14px 24px 0px #00000026;
	}
	/* Tail border — bottom-right, points at the row toggle */
	.wt_pklist_pro_inline_cta_card::before {
		content: '';
		position: absolute;
		bottom: -10px;
		right: 22px;
		left: auto;
		border-left: 10px solid transparent;
		border-right: 10px solid transparent;
		border-top: 10px solid #E5E5E5;
		z-index: 0;
	}
	/* Tail fill — bottom-right */
	.wt_pklist_pro_inline_cta_card::after {
		content: '';
		position: absolute;
		bottom: -9px;
		right: 33px;
		left: auto;
		border-left: 9px solid transparent;
		border-right: 9px solid transparent;
		border-top: 9px solid #FFFFFF;
		z-index: 1;
	}
	/* Top-left of card; translate(-50%) centers badge on the preview/sidebar seam (see .wf_customize_sidebar) */
	.wt_pklist_pro_inline_cta_crown {
		position: absolute;
		left: 0;
		top: 0;
		right: auto;
		transform: translate(-50%, -50%);
		width: 36px;
		height: 36px;
		margin: 0;
		background: #FFFFFF;
		border: 1px solid #E5E5E5;
		border-radius: 50%;
		padding: 6px;
		box-sizing: border-box;
		object-fit: contain;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
		z-index: 3;
	}
	.wt_pklist_pro_inline_cta_body p,
	.wt_pklist_pro_inline_cta_body .wt_pklist_pro_field_tooltip_p {
		margin: 0;
		font-size: 14px;
		font-weight: 500;
		line-height: 140.69%;
		color: #212121;
	}
	.wt_pklist_pro_inline_cta_body .wt_pklist_pro_field_tooltip_link {
		display: block;
		margin-top: 10px;
		color: #2271b1;
		font-weight: 600;
		text-decoration: underline;
	}
	.wt_pklist_pro_inline_cta_body .wt_pklist_pro_field_tooltip_link:hover {
		color: #135e96;
	}

	.wf_side_panel {
		float: left;
		width: 100%;
		box-sizing: border-box;
		min-height: 40px;
		padding-right: 0px;
		margin-bottom: 10px;
		box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
	}

	.wf_side_panel_toggle {
		float: right;
		width: 40px;
		text-align: right;
	}

	.wf_side_panel_hd {
		float: left;
		width: 100%;
		height: auto;
		padding: 5px 15px;
		background: #fafafa;
		border: solid 1px #e5e5e5;
		color: #2b3035;
		min-height: 40px;
		line-height: 30px;
		font-weight: 500;
		cursor: pointer;
	}

	.wf_side_panel_content {
		float: left;
		width: 100%;
		padding: 15px;
		height: auto;
		border: solid 1px #e5e5e5;
		margin-top: -1px;
		display: none;
		background: #fdfdfd;
	}

	.wf_side_panel_info_text {
		float: left;
		width: 100%;
		font-style: italic;
	}

	.wf_side_panel_frmgrp {
		float: left;
		width: 100%;
	}

	.wf_side_panel_frmgrp label {
		float: left;
		width: 100%;
		margin-bottom: 1px;
		margin-top: 8px;
	}

	.wf_side_panel_frmgrp .wf-checkbox {
		margin-top: 8px;
	}

	.wf_side_panel_frmgrp .wf_sidepanel_sele,
	.wf_side_panel_frmgrp .wf_sidepanel_txt,
	.wf_side_panel_frmgrp .wf_sidepanel_txtarea,
	.wf_pklist_text_field {
		display: block;
		width: 100%;
		font-size: .85rem;
		line-height: 1.2;
		color: #495057;
		background-color: #fff;
		background-clip: padding-box;
		border: 1px solid #ced4da;
		min-height: 32px;
		border-radius: 5px;
	}

	.wf_side_panel_frmgrp .wf_sidepanel_sele {
		height: 32px;
	}

	/* google chrome min height issue */
	.wf_inptgrp {
		float: left;
		width: 100%;
		margin-top: 0px;
	}

	.wf_inptgrp input[type="text"] {
		float: left;
		width: 75%;
		border-top-right-radius: 0;
		border-bottom-right-radius: 0;
	}

	.wf_inptgrp .addonblock {
		float: left;
		border: 1px solid #ced4da;
		width: 25%;
		border-radius: 5px;
		border-top-left-radius: 0;
		border-bottom-left-radius: 0;
		background-color: #e9ecef;
		color: #4c535a;
		text-align: center;
		height: 32px;
		margin-left: -2px;
		margin-top: 0px;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.wf_inptgrp .addonblock input[type="text"] {
		display: inline;
		text-align: center;
		box-shadow: none;
		background: none;
		outline: none;
		height: auto;
		line-height: 1;
		border: none;
		width: 100%;
		box-sizing: border-box;
		padding: 0;
		min-width: 0;
	}

	.wf_inptgrp .addonblock input[type="text"]:focus {
		outline: none;
		box-shadow: none;
	}

	.iris-picker,
	.iris-picker * {
		box-sizing: content-box;
	}

	.wp-picker-input-wrap label {
		width: auto;
		margin-top: 0px;
	}

	.wf_cst_headbar {
		float: left;
		height: 70px;
		width: 100%;
		border-bottom: solid 1px #efefef;
		margin-left: -15px;
		padding-right: 30px;
		margin-bottom: 15px;
		margin-top: -14px;
		box-shadow: 0px 2px 3px #efefef;
	}

	.wf_cst_theme_name {
		float: left;
		padding-left: 15px;
		margin: 0px;
		margin-top: 15px;
		margin-bottom: 2px;
	}

	.wf_customizer_tabhead_main {
		float: left;
		width: 70%;
	}

	.wf_customizer_tabhead_inner {
		float: right;
		position: relative;
		z-index: 1;
	}

	.wf_cst_tabhead {
		float: left;
		padding: 8px 12px;
		border: solid 1px #e5e5e5;
		border-bottom: none;
		cursor: pointer;
	}

	.wf_cst_tabhead_vis {
		background: #f5f5f5;
		margin-right: 5px;
	}

	.wf_cst_tabhead_code {
		background: #ebebeb;
		margin-right: -2px;
	}

	.wf_customizer_main {
		float: left;
		width: 100%;
		padding-top: 20px;
	}

	.wf_customize_container_main {
		float: left;
		width: 70%;
		background: #f5f5f5;
		border: solid 1px #e5e5e5;
		margin-top: -1px;
		position: relative;
		z-index: 1;
	}

	.wf_customize_container {
		width: 95%;
		box-sizing: border-box;
		padding: 0%;
		min-height: 500px;
		margin-left: 2.5%;
		margin-top: 2.5%;
		margin-bottom: 2.5%;
		background-color: #fff;
		float: left;
		height: auto;
	}

	.wf_customize_container * {
		box-sizing: border-box;
	}

	.wf_customize_vis_container {
		float: left;
		width: 100%;
		box-sizing: border-box;
		padding: 2%;
		min-height: 500px;
	}

	.wf_customize_code_container {
		float: left;
		width: 100%;
		min-height: 500px;
		display: none;
	}

	.CodeMirror {
		box-sizing: content-box;
		min-height: 500px;
	}

	.CodeMirror * {
		box-sizing: content-box;
	}

	.CodeMirror.cm-s-default {
		min-height: 500px;
		height: auto;
	}

	.wf_dropdown {
		position: absolute;
		z-index: 100;
		background: #fff;
		border: solid 1px #eee;
		padding: 0px;
		display: none;
	}

	.wf_dropdown li {
		padding: 10px 10px;
		margin-bottom: 0px;
		cursor: pointer;
	}

	.wf_dropdown li:hover {
		background: #fafafa;
	}

	.wf_default_template_list {
		width: 100%;
		max-width: 600px;
	}

	.wf_default_template_list_item {
		display: inline-block;
		width: 130px;
		height: 200px;
		margin: 15px;
		padding: 5px;
		cursor: pointer;
	}

	.wf_default_template_list_item img {
		width: 100%;
		max-height: 200px;
		box-shadow: 0px 2px 2px #ccc;
		border: solid 1px #efefef;
	}

	.wf_default_template_list_item a:focus {
		box-shadow: none;
	}

	.wf_default_template_list_item_hd {
		width: 100%;
		display: inline-block;
		padding: 10px 0px;
		text-align: center;
		font-weight: bold;
	}

	.wf_default_template_list_btn_main {
		width: 100%;
		display: inline-block;
		padding: 5px 0px;
		text-align: center;
	}

	.wf_template_name {
		width: 100%;
		max-width: 310px;
	}

	.wf_template_name_box {
		float: left;
		width: 90%;
		padding: 5%;
	}

	.wf_template_name_wrn {
		display: none;
	}

	.wf_my_template {
		width: 100%;
		max-width: 450px;
	}

	.wf_my_template_main {
		float: left;
		width: 90%;
		margin: 5%;
		max-height: 350px;
		overflow: auto;
	}

	.wf_my_template_list {
		float: left;
		width: 100%;
		height: auto;
		min-height: 100px;
	}

	.wf_my_template_item {
		float: left;
		box-sizing: border-box;
		width: 100%;
		height: auto;
		padding: 8px 10px;
		border-bottom: solid 1px #efefef;
		border-top: solid 1px #fff;
		text-align: left;
	}

	.wf_my_template_item_btn {
		float: right;
	}

	.wf_my_template_item_name {
		float: left;
		max-width: 60%;
		height: auto;
		line-height: 28px;
	}

	.wf_codeview_link_btn {
		float: left;
		margin-top: 7px;
		cursor: pointer;
	}

	/* styles inside template */
	.wfte_hidden {
		display: none !important;
	}

	.wfte_text_right {
		text-align: right !important;
	}

	.wfte_text_left {
		text-align: left !important;
	}

	.wfte_text_center {
		text-align: center !important;
	}

	.wf_customize_sidebar {
		max-height: 1047px;
		height: auto;
		overflow-y: auto;
		overflow-x: visible;
		margin-bottom: 1em;
		margin-left: -18px;
		padding-left: 18px;
		box-sizing: border-box;
		position: relative;
		z-index: 2;
	}

	.template_element_hover {
		cursor: pointer;
		background: rgb(245, 245, 245);
		padding: 0.5em;
		border: 1px dotted #ccc;
	}

	.customizer_template_warning_div {
		float: left;
		width: 70%;
		display: none;
	}

	.customizer_template_warning_div .notice-error {
		margin: 0;
	}

	.wt_pklist_dc_save_activate_btn {
		background: #5abd70;
		color: #fff;
		font-size: 13px;
		line-height: 2.15384615;
		min-height: 30px;
		margin: 0;
		padding: 0 10px;
		cursor: pointer;
		border-width: 1px;
		border-style: solid;
		border-radius: 3px;
		border: navajowhite;
		white-space: nowrap;
		box-sizing: border-box;
	}
	.wt-pro-cta-bar {
		margin: 24px 20px 24px 20px;
		max-width: 97%;
		background: #f4f3fd;
		border-left: 4px solid #fff;
		padding: 8px 18px;
		display: flex;
		align-items: center;
		font-size: 13px;
		font-weight: 500;
		color: #222;
		min-height: 38px;
		box-sizing: border-box;
		position: relative;
	}

	.wt-pro-cta-lock {
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 6px;
		width: 28px;
		height: 28px;
		margin-right: 12px;
	}
	.wt-pro-cta-bar a {
		color: #5151CD;
		text-decoration: underline;
		font-weight: 500;
		margin-left: 6px;
	}
	.wt-pro-cta-close {
		margin-left: auto;
		cursor: pointer;
		font-size: 20px;
		color: #888;
		line-height: 1;
		padding-left: 16px;
	}

	/* Banner dismiss button styles */
	.banner_dismiss {
		margin-left: auto;
		cursor: pointer;
		font-size: 20px;
		color: #888;
		line-height: 1;
		padding-left: 16px;
		background: none;
		border: none;
		position: relative;
	}

	.banner_dismiss:before {
		content: "\f158";
		font: normal 20px/1 dashicons;
		color: #888;
		float: left;
	}

	/* Basic template banner styles */
	.basic_template_cta_banner_invoice,
	.basic_template_cta_banner_shippinglabel {
		margin: 24px 20px 24px 20px;
		max-width: 97%;
		background: #f4f3fd;
		padding: 8px 18px;
		display: flex;
		align-items: center;
		font-size: 13px;
		font-weight: 500;
		color: #222;
		min-height: 38px;
		box-sizing: border-box;
		position: relative;
	}
	.wf_cta_banner_box {
		padding: 10px;
		padding-bottom: 0px;
		margin-bottom: 10px;
	}

	/* Disabled state for fields controlled by General tab settings */
	.wf_general_setting_disabled {
		opacity: 0.5;
		pointer-events: none;
		position: relative;
	}

	.wf_general_setting_disabled::after {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(255, 255, 255, 0.3);
		cursor: not-allowed;
		z-index: 10;
	}

	.wf_general_setting_disabled .wf_side_panel_toggle .wf_slide_switch {
		cursor: not-allowed !important;
	}

	.wf_general_setting_disabled .wf_side_panel_toggle .wf_slider {
		background-color: #ccc !important;
		cursor: not-allowed !important;
	}

	.wf_general_setting_disabled_checkbox {
		cursor: not-allowed !important;
		opacity: 0.5;
	}

	.wf_general_setting_disabled_row {
		opacity: 0.6;
	}

	.wf_general_setting_disabled_row input,
	.wf_general_setting_disabled_row select {
		pointer-events: none;
		background-color: #f5f5f5;
	}
</style>
<div class="wf_cst_loader wf_loader_bg"></div>
<div class="wf_my_template wf_pklist_popup">
	<div class="wf_pklist_popup_hd">
		<span style="line-height:40px;" class="dashicons dashicons-list-view"></span> <?php esc_html_e('Templates', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
		<div class="wf_pklist_popup_close">X</div>
	</div>
	<div class="wf_my_template_main wf_pklist_popup_body">
		<div style="float:left; box-sizing:border-box; width:100%; padding:0px 5px; margin-bottom:5px;">
			<input placeholder="<?php esc_html_e('Type template name to search', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>" type="text" name="" class="wf_pklist_text_field wf_my_template_search">
		</div>
		<div class="wf_my_template_list">

		</div>
	</div>
</div>



<div class="wf_default_template_list wf_pklist_popup">
	<div class="wf_default_template_list_hd wf_pklist_popup_hd">
		<span style="line-height:40px;" class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e('Choose a layout.', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
		<div class="wf_pklist_popup_close">X</div>
	</div>
	<div class="wf_default_template_list_main wf_pklist_popup_body" style="max-height: 500px; overflow-y: auto;">
		<div class="wf_cst_warn_box">
			<div class="wf_cst_warn" style="line-height:26px;">
				<?php esc_html_e('All unsaved changes will be lost upon switching to a new layout.', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
				<br />
				<span class="wf_new_template_wrn_sub"><?php esc_html_e('Save before you proceed.', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
					<span class="wf_pklist_save_theme_sub_loading"><?php esc_html_e('Saving...', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
					<button class="button button-secondary wf_pklist_save_theme_sub"><?php esc_html_e('Save', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></button> </span>
			</div>
		</div>
		<?php
		// Render template list items
		foreach ($def_template_arr as $def_template_id => $def_template) {
		?>
			<div class="wf_default_template_list_item" data-id="<?php echo esc_attr($def_template_id); ?>">
				<span class="wf_default_template_list_item_hd"><?php echo esc_html($def_template['title']); ?></span>
				<?php
				if (isset($def_template['preview_img']) && "" !== $def_template['preview_img']) {
					$img_url = isset($def_template['pro_template_url']) && "" !== $def_template['pro_template_url'] 
						? $def_template['pro_template_url'] . $def_template['preview_img']
						: $def_template_url . $def_template['preview_img'];
				?>
					<img src="<?php echo esc_url($img_url); ?>">
				<?php
				} elseif (isset($def_template['preview_html']) && "" !== $def_template['preview_html']) {
					echo wp_kses_post($def_template['preview_html']);
				}
				?>
				<span class="wf_default_template_list_btn_main"></span>
			</div>
		<?php
		}
		
		// Show CTA if only the basic template is available
		if (isset($def_template_arr) && in_array($template_type, ['invoice', 'shippinglabel'])) {
			$template_id = $def_template_arr[0]['id'];  
			if (in_array($template_id, ['template0', 'template1'])) {
				// Determine template type for banner class
				$template_type_for_banner = ($template_id === 'template1') ? 'shippinglabel' : 'invoice';
				$banner_class = 'basic_template_cta_banner_' . $template_type_for_banner;
				// Detect if RTL is enabled
				$is_rtl = is_rtl();
				$border_style = $is_rtl ? 'border-right: 2px solid #5151CD;' : 'border-left: 2px solid #5151CD;';
				
				if (should_show_banner($banner_class)) { 
					$cta_url = ($template_id === 'template1')
						? 'https://www.webtoffee.com/product/woocommerce-shipping-labels-delivery-notes/?utm_source=free_plugin_customize_tab&utm_medium=pdf_basic&utm_campaign=Shipping_Label'
						: 'https://www.webtoffee.com/product/woocommerce-pdf-invoices-packing-slips/?utm_source=free_plugin_customize_tab&utm_medium=pdf_basic&utm_campaign=PDF_invoice';
					$banner_message = ($template_id === 'template1') 
						? __('Get access to more shipping label templates.', 'print-invoices-packing-slip-labels-for-woocommerce')
						: __('Get access to more invoice templates.', 'print-invoices-packing-slip-labels-for-woocommerce');
		?>
			<div class="wt-pro-cta-bar <?php echo esc_attr($banner_class); ?> wt_pklist_dismissible_banner_div wt_pklist_basic_template_banner" id="wt-pro-cta-bar"style="background: #eeedfa; <?php echo esc_attr($border_style); ?> margin: 0 20px 20px 20px;">
				<span class="wt-pro-cta-lock">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none">
						<rect width="24" height="24" rx="6"/>
						<path d="M12 17a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm6-5V9a6 6 0 1 0-12 0v3a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2ZM8 9a4 4 0 1 1 8 0v3H8V9Zm10 8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v5Z" fill="#5c6ac4"/>
					</svg>
				</span>
				<span>
					<?php echo esc_html($banner_message); ?>
					<a href="<?php echo esc_url($cta_url); ?>" target="_blank">
						<?php echo esc_html__('Upgrade to Pro', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
					</a>
				</span>
				<button class="banner_dismiss notice-dismiss" data-banner-class="<?php echo esc_attr($banner_class); ?>" data-banner-interval="0" data-banner-action="0"></button>
			</div>
		<?php 
				}
			}
		} 
		?>
	</div>

	<?php
	/**
	 * Helper function to check if banner should be shown
	 * @param string $banner_class The banner class name
	 * @return bool Whether banner should be shown
	 */
	function should_show_banner($banner_class) {
		$dismiss_banner_arr = Wf_Woocommerce_Packing_List_Pro_Addons::wt_pklist_get_cta_banners($banner_class);
		
		if (empty($dismiss_banner_arr)) {
			return true;
		}
		
		if (isset($dismiss_banner_arr['type']) && "full" === $dismiss_banner_arr['type']) {
			return true;
		}
		
		if (isset($dismiss_banner_arr['type']) && "class" === $dismiss_banner_arr['type'] && isset($dismiss_banner_arr['value'])) {
			$banner_val = $dismiss_banner_arr['value'];
			if (isset($banner_val['class']) && $banner_class === $banner_val['class'] && isset($banner_val['status'])) {
				// Only show banner if status is 1 (currently showing)
				// Status 0 = dismissed, Status 1 = currently showing, Status 2 = remind me later
				return 1 === absint($banner_val['status']);
			}
		}
		
		return false;
	}

	/**
	 * Helper function to get template display name
	 * @param string $template_type The template type
	 * @return string The display name
	 */
	function get_template_display_name($template_type) {
		$template_display_names = [
			'packinglist' => 'packing slip',
			'shippinglabel' => 'shipping label',
			'dispatchlabel' => 'dispatch label',
			'addresslabel' => 'address label',
			'creditnote' => 'credit note',
			'deliverynote' => 'delivery note',
			'proformainvoice' => 'proforma invoice',
		];
		
		return isset($template_display_names[$template_type]) ? $template_display_names[$template_type] : $template_type;
	}

	$show_banner = false;
	if (!empty($template_type) && $enable_code_view) { 
		$template_addon_key = Wf_Woocommerce_Packing_List_Pro_Addons::wt_get_addon_key_by_template_type($template_type);
		
		if (!empty($template_addon_key)) {
			if (true === Wf_Woocommerce_Packing_List_Admin::wt_plugin_active($template_addon_key) && false === Wf_Woocommerce_Packing_List_Admin::wt_plugin_active('wt_adc_addon')) {
				$show_banner = should_show_banner('adc_cta_banner_in_customizer_tab_top');
			}
		}
	}
	if (true === $show_banner) {
	?>
	<?php
	// Detect if RTL is enabled
	$is_rtl = is_rtl();
	$border_style = $is_rtl ? 'border-right: 2px solid #5151CD;' : 'border-left: 2px solid #5151CD;';
	$close_button_style = $is_rtl ? 'float: left;' : 'float: right;';
	?>
	<div class="wf_cta_banner_box">
		<div class="wt-pro-cta-bar adc_cta_banner_in_customizer_tab_top wt_pklist_dismissible_banner_div wt_pklist_customizer_banner" style="background: #eeedfa; <?php echo esc_attr($border_style); ?> margin: 10px auto 14px 8px;">
			<span><svg width="13" height="14" viewBox="0 0 13 14" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M7.59664 13.1544H5.28993C5.05644 13.1544 4.86711 13.3437 4.86711 13.5772C4.86711 13.8107 5.05644 14 5.28993 14H7.59664C7.83013 14 8.01946 13.8107 8.01946 13.5772C8.01946 13.3437 7.83013 13.1544 7.59664 13.1544ZM6.44329 1.67342C6.67678 1.67342 6.86611 1.48409 6.86611 1.2506V0.422819C6.86611 0.189329 6.67678 0 6.44329 0C6.2098 0 6.02047 0.189329 6.02047 0.422819V1.2506C6.02047 1.48409 6.2098 1.67342 6.44329 1.67342ZM12.4638 5.54785H11.636C11.4025 5.54785 11.2132 5.73718 11.2132 5.97067C11.2132 6.20416 11.4025 6.39349 11.636 6.39349H12.4638C12.6972 6.39349 12.8866 6.20416 12.8866 5.97067C12.8866 5.73718 12.6977 5.54785 12.4638 5.54785ZM1.25013 5.54785H0.422819C0.189329 5.54785 0 5.73718 0 5.97067C0 6.20416 0.189329 6.39349 0.422819 6.39349H1.2506C1.48409 6.39349 1.67342 6.20416 1.67342 5.97067C1.67342 5.73718 1.48362 5.54785 1.25013 5.54785ZM2.80658 2.93201C2.88926 3.0147 2.99732 3.05604 3.10584 3.05604C3.21436 3.05604 3.32242 3.0147 3.4051 2.93201C3.57047 2.76711 3.57047 2.49933 3.4051 2.33443L2.81926 1.74859C2.65389 1.58322 2.38611 1.58322 2.22121 1.74859C2.05584 1.91349 2.05584 2.18128 2.22121 2.34617L2.80658 2.93201ZM10.0673 1.74859L9.48195 2.33396C9.31658 2.49886 9.31658 2.76664 9.48195 2.93154C9.56463 3.01423 9.67268 3.05557 9.78121 3.05557C9.88973 3.05557 9.99779 3.01423 10.0805 2.93154L10.6658 2.34617C10.8312 2.18128 10.8312 1.91349 10.6658 1.74859C10.5005 1.58369 10.2322 1.58369 10.0673 1.74859ZM6.44329 2.97946C4.31745 2.97617 2.58812 4.70503 2.58812 6.83463C2.58812 7.85973 2.99027 8.79228 3.64799 9.48054C4.08443 9.93765 4.3555 10.5272 4.3555 11.1591V11.5195C4.3555 11.7788 4.56597 11.9893 4.8253 11.9893H6.44329H8.06128C8.3206 11.9893 8.53107 11.7788 8.53107 11.5195V11.1591C8.53107 10.5272 8.80168 9.93765 9.23859 9.48054C9.89584 8.79228 10.2985 7.8602 10.2985 6.83463C10.2985 4.70503 8.56913 2.9757 6.44329 2.97946ZM6.0651 5.32658C5.51356 5.46376 5.07007 5.9096 4.93476 6.46255C4.88732 6.65564 4.71443 6.7853 4.52463 6.7853C4.49128 6.7853 4.45698 6.78107 4.42362 6.77309C4.19671 6.71765 4.05812 6.48886 4.11356 6.26195C4.32591 5.39423 4.99537 4.72148 5.86074 4.50584C6.08718 4.4504 6.31738 4.58711 6.37329 4.81403C6.42966 5.04094 6.29154 5.2702 6.0651 5.32658Z" fill="#5454A5"/>
			</svg></span>
			<span style="font-weight: 600; color: #5454A5; margin: 0 15px 0 5px;">Did you know?</span> 
			<span style="color: #222; font-weight: 400; width: 60%; text-align: left; ">
				You can unlock full <?php echo esc_html(get_template_display_name($template_type)); ?> customization with our Customizer.
				<a href="https://www.webtoffee.com/product/customizer-for-woocommerce-pdf-invoice/?utm_source=free_plugin_customize_tab&utm_medium=pdf_premium&utm_campaign=PDF_Customizer" target="_blank" style="color: #5151CD; text-decoration: underline; font-weight: 500; display: inline-block; margin-top: 2px;">
					Get Plugin Now
				</a>
			</span>
			<button class="banner_dismiss notice-dismiss" data-banner-class="adc_cta_banner_in_customizer_tab_top" data-banner-interval="0" data-banner-action="0" style="<?php echo esc_attr($close_button_style); ?>"></button>
		</div>
	</div>
	<?php
	}
	?>
</div>

<div class="wf_cst_headbar"<?php echo (isset($preview_only) && $preview_only) ? ' style="height: 50px;"' : ''; ?>>
	<div style="float:left; display:flex; align-items: center; height:100%; width:max-content; padding-left: 10px;">
		<?php if (!isset($preview_only) || !$preview_only) : ?>
		<?php $active_template_name_cleaned_string = str_replace(" (Active)", "", $active_template_name); ?>
		<input type="text" class="wf_template_name_field" value="<?php echo esc_html($active_template_name_cleaned_string); ?>" style="padding: 4px 20px;min-width: 250px;font-size: 16px;">

		<?php
		$tooltip_conf = Wf_Woocommerce_Packing_List_Admin::get_tooltip_configs('create_new_template', Wf_Woocommerce_Packing_List_Customizer::$module_id_static);
		?>
		<a class="wf_pklist_new_template <?php echo esc_attr($tooltip_conf['class']); ?>" style="float:left; width:100%; padding-left:15px; cursor:pointer;" <?php echo wp_kses_post($tooltip_conf['text']); ?>><?php esc_html_e('Change template', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
		<?php else : ?>
		<span style="margin-left: 15px; color: #666; font-size: 13px;"><?php esc_html_e('This preview reflects your current settings from the General tab.', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
		<?php endif; ?>
	</div>
	<?php if (!isset($preview_only) || !$preview_only) : ?>
	<div style="float:right;  margin-right:-15px; display:flex;align-items: center;height: 100%; ">

		<button type="button" name="" class="wf-btn-plain" onclick="window.location.reload(true);">
			<?php esc_html_e('Cancel', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></button>
		<button type="button" name="" class="wf-btn-primary wf_pklist_save_theme btn-disable" style="margin-right: 5px;">
			<?php esc_html_e('Update template', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
		</button>
		<button type="button" name="" class="wf-btn-primary wf_template_create_btn" style="display:none; margin-right: 15px;">
			<?php esc_html_e('Save template', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
		</button>
		<button type="button" class="wf_template_create_btn wt_save_activate wf-btn-success" style="display:none;">
			<?php esc_html_e('Save and activate', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
		</button>
		<?php
		$tooltip_conf = Wf_Woocommerce_Packing_List_Admin::get_tooltip_configs('dropdown_menu', Wf_Woocommerce_Packing_List_Customizer::$module_id_static);
		?>
		<button type="button" name="" class="wf-dot-btn button-secondary wf_customizer_drp_menu <?php echo esc_attr($tooltip_conf['class']); ?>" style="height: 28px;" <?php echo wp_kses_post($tooltip_conf['text']); ?>>
			<span class="dashicons dashicons-ellipsis" style="line-height: 28px;"></span>
		</button>
		<ul class="wf_dropdown" data-target="wf_customizer_drp_menu">
			<li class="wf_activate_theme wf_activate_theme_current" data-id="<?php echo esc_attr($active_template_id); ?>"><?php esc_html_e('Activate', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
			<li class="wf_delete_theme wf_delete_theme_current" data-id="<?php echo esc_attr($active_template_id); ?>"><?php esc_html_e('Delete', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
			<li class="wf_pklist_new_template"><?php esc_html_e('Create new', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
			<li class="wf_pklist_my_templates"><?php esc_html_e('My templates', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
		</ul>
	</div>
	<?php endif; ?>
</div>


<div class="customizer_template_warning_div">
</div>
<div class="wf_customizer_main">
	<p class="template_qr_compatible_err" style="margin: 0 0 10px 0px;width: 68%;background: #ddd;padding: 10px;border-left: 5px solid red;font-size: 14px;display: none;"><?php echo esc_html__("This template is not comaptible with QR Code addon plugin", "print-invoices-packing-slip-labels-for-woocommerce"); ?></p>
	<?php
	$show_qrcode_placeholder = apply_filters('wt_pklist_show_qrcode_placeholder_in_template', false, $template_type);
	$template_qr_compatible_err_val = 0;
	if (!$show_qrcode_placeholder) {
		$template_qr_compatible_err_val = 2;
	}
	?>
	<input type="hidden" name="" id="template_qr_compatible_err_val" value="<?php echo esc_attr($template_qr_compatible_err_val); ?>">
	<?php
	if ($enable_code_view) {
		$show_banner = false;
		if (!empty($template_type)) {
			$template_addon_key = Wf_Woocommerce_Packing_List_Pro_Addons::wt_get_addon_key_by_template_type($template_type);
			$dismiss_banner_arr = Wf_Woocommerce_Packing_List_Pro_Addons::wt_pklist_get_cta_banners('adc_cta_banner_in_customizer_tab');
			if (!empty($template_addon_key)) {
				if (true === Wf_Woocommerce_Packing_List_Admin::wt_plugin_active($template_addon_key) && false === Wf_Woocommerce_Packing_List_Admin::wt_plugin_active('wt_adc_addon')) {
					$show_banner = should_show_banner('adc_cta_banner_in_customizer_tab');
				}
			}
		}
	?>
		<div class="wf_customizer_tabhead_main">
			<?php
			if (true === $show_banner) {
				$template_display_name = get_template_display_name($template_type);
				echo '<div class="adc_cta_banner_in_customizer_tab wt_pklist_dismissible_banner_div wt_pklist_customizer_tab_banner">
					<p>' . sprintf(
					/* translators: %s: Template display name (invoice, packing list, etc.) */
					esc_html__("Unlock limitless possibilities of %s customization with the addon -", 'print-invoices-packing-slip-labels-for-woocommerce'), esc_html($template_display_name)) . '
					<a href="https://www.webtoffee.com/product/customizer-for-woocommerce-pdf-invoice/?utm_source=free_plugin_customizer_top&utm_medium=pdf_premium&utm_campaign=PDF_Customizer&utm_content=' . esc_attr(WF_PKLIST_VERSION) . '" target="_blank">' . esc_html__("Customizer for WooCommerce PDF Invoices Plugin", "print-invoices-packing-slip-labels-for-woocommerce") . '</a>
					</p>
					<button class="banner_dismiss notice-dismiss" data-banner-class="adc_cta_banner_in_customizer_tab" data-banner-interval="0" data-banner-action="0"></button>
				</div>';
			}
			?>
			<div class="wf_customizer_tabhead_inner">
				<div class="wf_cst_tabhead_vis wf_cst_tabhead" data-target="wf_customize_vis_container"><?php esc_html_e('Visual', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></div>
				<div class="wf_cst_tabhead_code wf_cst_tabhead" data-target="wf_customize_code_container"><?php esc_html_e('Code', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></div>
			</div>
		</div>
	<?php
	} else {
		$tooltip_conf = Wf_Woocommerce_Packing_List_Admin::get_tooltip_configs('design_view', Wf_Woocommerce_Packing_List_Customizer::$module_id_static);
	?>
		<!--dummy code view for basic version -->
		<div class="wf_customizer_tabhead_main">
			<div class="wf_customizer_tabhead_inner">
				<div class="wf_cst_tabhead_vis wf_cst_tabhead <?php echo esc_attr($tooltip_conf['class']); ?>" data-target="wf_customize_vis_container" <?php echo wp_kses_post($tooltip_conf['text']); ?>><?php esc_html_e('Visual', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></div>
				<?php if (apply_filters('wt_pklist_show_code_view_al', true, $template_type)) {
				?>
					<div class="wf_cst_tabhead_code wf_cst_tabhead wt_customizer_promotion_popup_btn" data-target="wf_customize_vis_container"><?php esc_html_e('Code', 'print-invoices-packing-slip-labels-for-woocommerce'); ?> <span class="wt_customizer_pro_text" style="color:red;">(<?php esc_html_e('Pro version', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>)</span></div>
				<?php
				} ?>
			</div>
		</div>
	<?php
	}
	?>

	<div class="wf_customize_sidebartop">
		<?php
		do_action('wf_pklist_customizer_editor_sidebar_top', $template_type);

		$enable_pdf_preview = apply_filters('wf_pklist_intl_customizer_enable_pdf_preview', false, $template_type);
		if ($enable_pdf_preview) {
			include "_pdf_preview.php";
		} else { 
			if ( 'shippinglabel' !== $template_type && 'creditnote' !== $template_type ) {
				$template_display_name = get_template_display_name($template_type);
			?>
			<div class="wf_customizer_premium_feature wt_pklist_premium_features_panel">
				<div class="wt_pklist_premium_features_header">
					<img src="<?php echo esc_url(WF_PKLIST_PLUGIN_URL . 'assets/images/Crown.png'); ?>" class="crown-icon" alt="">
					<h3><?php esc_html_e('Premium features', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h3>
				</div>
				<?php /* translators: %s: document type name (e.g. packing slip, invoice, credit note) */ ?>
					<p class="wt_pklist_premium_features_desc"><?php echo sprintf( esc_html__( 'Toggle the pro features on and off to see how they transform your %s.', 'print-invoices-packing-slip-labels-for-woocommerce' ), esc_html( $template_display_name ) ); ?></p>
			</div>
				<?php
				}
			}
			?>
	</div>

	<div class="wf_customize_container_main">
		<div class="wf_customize_container">
			<div class="wf_customize_vis_container wf_customize_inner"></div>
			<div class="wf_customize_code_container wf_customize_inner">
				<textarea id="wfte_code"></textarea>
			</div>
		</div>
	</div>

	<div class="wf_customize_sidebar">
		<?php
		include "_customize_properties.php";
		?>
	</div>
</div>
