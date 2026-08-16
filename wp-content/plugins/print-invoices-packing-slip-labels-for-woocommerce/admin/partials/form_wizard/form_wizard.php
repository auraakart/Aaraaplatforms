<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
if (! defined('WPINC')) {
    die;
}
// step 1 field values
$sample_logo    = WF_PKLIST_PLUGIN_URL . 'admin/images/uploader_sample_img.png';
$wc_email_classes = Wt_Pklist_Common::wt_pdf_get_wc_email_classes(true); // Get default wc email classes.
$invoice_module_id = Wf_Woocommerce_Packing_List::get_module_id('invoice');
$company_name = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_companyname');
$street         = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_sender_address_line1');
$street_line_2  = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_sender_address_line2');
$city           = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_sender_city');
$country_arr    = Wf_Woocommerce_Packing_List::get_option('wf_country');
$postal_code    = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_sender_postalcode');
$phone_no       = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_sender_contact_number');
$company_tax_id = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_sender_vat');
$company_logo   = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_packinglist_logo');
$logo_url       = !empty($company_logo) ? $company_logo : $sample_logo;
if (strstr($country_arr, ':')) {
    $country_arr = explode(':', $country_arr);
    $country    = current($country_arr);
    $state      = end($country_arr);
} else {
    $country    = $country_arr;
    $state      = '*';
}

// step 2 field values
$attach_invoice = Wf_Woocommerce_Packing_List::get_option('wt_pdf_invoice_attachment_wc_email_classes', $invoice_module_id);
$invoice_no_type = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_invoice_as_ordernumber', $invoice_module_id);
$invoice_no_format = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_invoice_number_format', $invoice_module_id);
$prefix = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_invoice_number_prefix', $invoice_module_id);
$suffix = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_invoice_number_postfix', $invoice_module_id);
$invoice_start_number = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_invoice_start_number', $invoice_module_id);
$invoice_no_length = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_invoice_padding_number', $invoice_module_id);
$date_frmt_tooltip = __('Click to append with existing data', 'print-invoices-packing-slip-labels-for-woocommerce');
$template_type = "invoice";

// Get current invoice number for preview
$current_invoice_number = (int) Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_Current_Invoice_number', $invoice_module_id);
$current_invoice_number_in_db = $current_invoice_number = ($current_invoice_number < 0 ? 0 : $current_invoice_number);
?>

<style>
    .wt_wrap {
        background-color: #F1F8FE;
    }

    .wt_wrap_wizard_container_inner_empty_col {
        float: left;
        width: 10%;
        height: 1px;
    }

    .wt_wrap_wizard_form {
        width: 85%;
        float: left;
    }

    .wt_wrap_wizard_container {
        width: 90%;
        float: left;
        padding: 40px 15px 100px 15px;
    }

    .wt_wrap_wizard_form_outter {
        float: left;
        width: 100%;
        background-color: #fff;
    }

    .wt_wrap_wizard_form_steps {
        float: left;
        width: 100%;
    }

    .wt_wrap_wizard_form_steps_progress {
        float: left;
        width: 10%;
        padding: 2em;
    }

    .wt_wrap_wizard_form_steps_fields {
        float: left;
        width: 75%;
        padding: 2em;
    }

    ul.progress-bar {
        height: 150px;
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
    }

    ul.progress-bar::after {
        content: "";
        position: absolute;
        top: 0;
        left: 5px;
        background: transparent;
        width: 5px;
        height: 100vh;
    }

    ul.progress-bar li {
        background: #f1f8fe;
        border-radius: 100px;
        width: 15px;
        height: 15px;
        z-index: 1;
        border: 1px solid #F1F8FE;
        position: relative;
    }

    ul.progress-bar li.step_active {
        background: #056BE7;
    }

    ul.progress-bar li span {
        position: absolute;
        left: 20px;
        width: 5em;
    }

    ul.progress-bar li::after {
        content: "";
        position: absolute;
        bottom: 0;
        top: 15px;
        left: 6px;
        background: #F1F8FE;
        width: 3px;
        height: 50px;
    }

    ul.progress-bar li:last-child::after {
        display: none;
    }

    ul.progress-bar li.step_active::after {
        background: #056be7;
    }

    ul.progress-bar li.stop_active::after {
        background: #F1F8FE;
    }

    .wt_form_wizard_field_col,
    .wt_form_wizard_field_row {
        width: 100%;
        float: left;
    }

    .wt_form_wizard_field_col {
        margin: 0 1.5em 0 0;
    }

    .wt_form_wizard_field_col label {
        width: 100%;
        float: left;
        padding: 5px 0;
    }

    .wt_form_wizard_field_col input[type="text"] {
        width: 100%;
        float: left;
        padding: 5px;
        border-radius: 3px;
        border: 1.5px solid #BDC1C6;
        background-color: #FFF;
    }

    .wt_form_wizard_field_col input[type="number"] {
        width: 85px;
        float: left;
        padding: 5px;
        border-radius: 3px;
        border: 1.5px solid #BDC1C6;
        background-color: #FFF;
    }

    .wt_form_wizard_field_col select {
        width: 100%;
        float: left;
        padding: 5px;
        border-radius: 3px;
        border: 1.5px solid #BDC1C6;
    }

    .wt_form_wizard_field_col_2 {
        width: 40%;
    }

    .wt_form_wizard_field_col_1 {
        width: 100%;
    }

    .wt_form_wizard_field_col_3 {
        width: 30%;
    }

    .wt_form_wizard_field_col_4 {
        width: 22%;
    }

    .wt_form_wizard_field_col_5 {
        width: 15%;
    }

    .wt_form_wizard_field_col_3_4 {
        width: 70%;
    }

    .wt_pdf_invoice_attachment_wc_email_classes_label {
        float: initial !important;
        cursor: pointer;
    }

    .wt_wrap_wizard_form_steps h3 {
        margin: 0 0 1em 0;
    }

    .wt_form_wizard_help_text {
        font-style: italic;
        color: #6E7681;
    }

    .wt_form_wizard_footer {
        float: left;
        width: 100%;
    }

    .wt_form_wizard_prev,
    .wt_form_wizard_next,
    .wt_form_wizard_invoice_setup_skip,
    .wt_form_wizard_submit {
        float: right;
    }

    .wt_pklist_btn_secondary {
        margin-right: 15px;
    }

    .wt_pklist_checkbox_div {
        margin-bottom: 10px;
    }

    /* Skip Wizard Confirmation Popup Styles */
    .wt_skip_wizard_confirm_popup {
        border-radius: 8px !important;
        border: none !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        overflow: hidden;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_hd {
        display: none !important;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_body {
        padding: 24px;
        border-radius: 12px 12px 0 0;
        position: relative;
    }

    .wt_skip_wizard_confirm_popup .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .wt_skip_wizard_confirm_popup .popup-title {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }

    .wt_skip_wizard_confirm_popup .popup-close {
        width: 28px;
        height: 28px;
        text-align: center;
        line-height: 24px;
        cursor: pointer;
        font-size: 20px;
        color: #666;
        font-weight: normal;
        border: none;
        background: none;
    }

    .wt_skip_wizard_confirm_popup .popup-close:hover {
        color: #333;
    }

    .wt_skip_wizard_confirm_popup .message {
        text-align: left;
    }

    .wt_skip_wizard_confirm_popup .message p {
        margin-bottom: 0;
        line-height: 1.5;
        font-size: 15px;
        color: #333;
        text-align: left;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer.wf_pklist_popup_footer{
        width: 76%;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer {
        text-align: center;
        border-top: none;
        border-radius: 5px;
        padding: 6px 2px 4px 150px;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer button {
        margin-right: 12px;
        margin-left: 0;
        border-radius: 4px;
        padding: 8px 20px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer button:hover {
        /* Removed animation effects */
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer .button-secondary {
        background: #fff !important;
        color: #3157A6 !important;
        border: none !important;
        font-size: 14px !important;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer .button-secondary:hover {
        background: #f0f4ff !important;
        border: none !important;
        color: #3157A6 !important;
    }

    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer .button-primary {
        background: #3157A6 !important;
        color: #fff !important;
        border: 1px solid #3157A6 !important;
    }
    .wt_skip_wizard_confirm_popup .wf_pklist_popup_footer .button-primary:hover {
        background:rgb(30, 56, 113) !important;
    }
    
</style>
<div class="wt_wrap">
    <div class="wt_heading_section">
        <?php
        //webtoffee branding
        include WF_PKLIST_PLUGIN_PATH . '/admin/views/admin-settings-branding.php';
        ?>
    </div>
    <div class="wt_wrap_wizard_container">
        <div class="wt_wrap_wizard_container_inner_empty_col"></div>
        <form class="wt_wrap_wizard_form" method="post">
            <?php
            if (function_exists('wp_nonce_field')) {
                wp_nonce_field('wt-pklist-form-wizard-' . WF_PKLIST_POST_TYPE);
            }
            ?>
            <h2><?php esc_html_e("Hello there! Let’s begin with the basics", "print-invoices-packing-slip-labels-for-woocommerce") . '...'; ?></h2>
            <div class="wt_wrap_wizard_form_outter">
                <div class="wt_wrap_wizard_form_steps">
                    <div class="wt_wrap_wizard_form_steps_progress">
                        <ul class="wt_form_wizard_progress_bar progress-bar">
                            <li class="wt_form_wizard_progress_step_1 step_active stop_active"><span><strong><?php esc_html_e("Step 1", "print-invoices-packing-slip-labels-for-woocommerce"); ?></strong></span></li>
                            <li class="wt_form_wizard_progress_step_2"><span><strong><?php esc_html_e("Step 2", "print-invoices-packing-slip-labels-for-woocommerce"); ?></strong></span></li>
                            <li class="wt_form_wizard_progress_step_3"><span><?php esc_html_e("Step 3", "print-invoices-packing-slip-labels-for-woocommerce"); ?></span></li>
                        </ul>
                    </div>
                    <div class="wt_wrap_wizard_form_steps_fields" data-wizard-step="1">
                        <h3><?php esc_html_e("Add shop details", "print-invoices-packing-slip-labels-for-woocommerce"); ?></h3>
                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Shop name", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input type="text" name="woocommerce_wf_packinglist_companyname" class="wt_pklist_form_wizard_field" value="<?php echo esc_attr($company_name); ?>">
                            </div>
                        </div>
                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Address line 1", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input type="text" name="woocommerce_wf_packinglist_sender_address_line1" class="wt_pklist_form_wizard_field" value="<?php echo esc_attr($street); ?>">
                            </div>
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Address line 2", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input type="text" name="woocommerce_wf_packinglist_sender_address_line2" class="wt_pklist_form_wizard_field" value="<?php echo esc_attr($street_line_2); ?>">
                            </div>

                        </div>
                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <label><?php esc_html_e("City", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input type="text" name="woocommerce_wf_packinglist_sender_city" class="wt_pklist_form_wizard_field" value="<?php echo esc_attr($city); ?>">
                            </div>
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Country/State", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <select name="wf_country" class="wt_pklist_form_wizard_field">
                                    <option value=""><?php esc_attr_e("Select country", "print-invoices-packing-slip-labels-for-woocommerce"); ?></option>
                                    <?php
                                    ob_start();
                                    WC()->countries->country_dropdown_options($country, $state);
                                    $html = ob_get_clean();
                                    echo $html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    ?>
                                </select>
                            </div>
                        </div>




                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_4 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Postal code", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input type="text" name="woocommerce_wf_packinglist_sender_postalcode" class="wt_pklist_form_wizard_field" value="<?php echo esc_attr($postal_code); ?>">
                            </div>
                            <div class="wt_form_wizard_field_col_3 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Phone number", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input type="text" name="woocommerce_wf_packinglist_sender_contact_number" class="wt_pklist_form_wizard_field" value="<?php echo esc_attr($phone_no); ?>">
                            </div>
                        </div>
                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Tax ID", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input type="text" name="woocommerce_wf_packinglist_sender_vat" class="wt_pklist_form_wizard_field" value="<?php echo esc_attr($company_tax_id); ?>">
                            </div>
                        </div>
                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <label><?php esc_html_e("Upload logo", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                                <input id="woocommerce_wf_packinglist_logo" type="hidden" name="woocommerce_wf_packinglist_logo" value="<?php echo esc_url($company_logo); ?>">
                                <div class="wf_file_attacher_dv">
                                    <div class="wf_file_attacher_inner_dv">
                                        <span class="dashicons dashicons-dismiss wt_logo_dismiss"></span>
                                        <img class="wf_image_preview_small" src="<?php echo esc_url($logo_url); ?>">
                                    </div>
                                    <span class="size_rec"><?php esc_html_e("Recommended size is 150x50px.", "print-invoices-packing-slip-labels-for-woocommerce"); ?></span>
                                    <input type="button" name="upload_image" class="wf_button button button-primary wf_file_attacher" wf_file_attacher_target="#woocommerce_wf_packinglist_logo" value="Upload">
                                </div>
                            </div>
                        </div>

                        <div class="wt_form_wizard_footer">
                            <a class="wt_form_wizard_next wt_pklist_btn wt_pklist_btn_primary" data-target-class="wt_wrap_wizard_form_steps_fields" data-wizard-step="1" data-wizard-next-step="2"><?php esc_html_e("Next", "print-invoices-packing-slip-labels-for-woocommerce"); ?></a>
                            <a class="wt_form_wizard_invoice_setup_skip wt_pklist_btn wt_pklist_btn_empty" href="<?php echo esc_url(admin_url('admin.php?page=wf_woocommerce_packing_list&skip_wizard=1')); ?>" data-wf_popup="wt_skip_wizard_confirm_popup"><?php esc_html_e("Skip invoice setup", "print-invoices-packing-slip-labels-for-woocommerce"); ?></a>
                        </div>
                    </div>
                    <div class="wt_wrap_wizard_form_steps_fields" data-wizard-step="2" style="display:none">
                        <h3><?php esc_html_e("Choose emails for invoice attachment", "print-invoices-packing-slip-labels-for-woocommerce"); ?></h3>
                        <p><?php esc_html_e("Choose the order emails to which you'd like to attach invoices for your customers", "print-invoices-packing-slip-labels-for-woocommerce"); ?></p>
                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_2 wt_form_wizard_field_col">
                                <?php
                                foreach ($wc_email_classes as $or_st => $or_st_label) {
                                    // Check 'customer_completed_order' by default.
                                    if (empty($attach_invoice)) {
                                        $checked = ($or_st === 'customer_completed_order') ? 'checked' : '';
                                    } else {
                                        $checked = in_array($or_st, $attach_invoice) ? 'checked' : '';
                                    }
                                ?>
                                    <div class="wt_pklist_checkbox_div">
                                        <input type="checkbox" name="wt_pdf_invoice_attachment_wc_email_classes[]" value="<?php echo esc_attr($or_st); ?>" id="<?php echo esc_attr('wt_pdf_invoice_attachment_wc_email_classes_label_' . $or_st); ?>" <?php echo esc_attr($checked); ?>>
                                        <label class="wt_pdf_invoice_attachment_wc_email_classes_label" for="<?php echo esc_attr('wt_pdf_invoice_attachment_wc_email_classes_label_' . $or_st); ?>"> <?php echo esc_html($or_st_label); ?></label>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="wt_form_wizard_footer">
                            <a class="wt_form_wizard_next wt_pklist_btn wt_pklist_btn_primary" data-target-class="wt_wrap_wizard_form_steps_fields" data-wizard-step="2" data-wizard-next-step="3"><?php esc_html_e("Next", "print-invoices-packing-slip-labels-for-woocommerce"); ?></a>
                            <a class="wt_form_wizard_prev wt_pklist_btn wt_pklist_btn_secondary" data-target-class="wt_wrap_wizard_form_steps_fields" data-wizard-step="2" data-wizard-prev-step="1"><?php esc_html_e("Back", "print-invoices-packing-slip-labels-for-woocommerce"); ?></a>
                        </div>
                    </div>
                    <div class="wt_wrap_wizard_form_steps_fields" data-wizard-step="3" style="display:none">
                        <h3><?php esc_html_e("Create your unique invoice numbering system", "print-invoices-packing-slip-labels-for-woocommerce"); ?></h3>
                        <div class="wt_form_wizard_field_row" style="margin-bottom: 14px;">
                            <div class="wt_form_wizard_field_col_1 wt_form_wizard_field_col">
                                <p><?php esc_html_e("Complete the invoice number format to suit your requirements", "print-invoices-packing-slip-labels-for-woocommerce"); ?></p>
                            </div>

                            <div class="invoice-input-wrap-wizard">

                                <div class="wf_invoice_number_prefix_pdf_fw_wizard">
                                    <input type="hidden" name="woocommerce_wf_invoice_number_format_pdf_fw" value="<?php echo esc_attr($invoice_no_format); ?>">
                                    <div class="choose_date_div">
                                        <input type="text" name="woocommerce_wf_invoice_number_prefix_pdf_fw" value="<?php echo esc_attr($prefix); ?>">

                                    </div>
                                </div>
                                <div class="wf_invoice_number_select_pdf_fw_wizard">
                                    <select name="woocommerce_wf_invoice_as_ordernumber_pdf_fw">
                                        <option value="Yes" <?php echo "Yes" === $invoice_no_type ? 'selected' : ''; ?>><?php esc_html_e("Order number", "print-invoices-packing-slip-labels-for-woocommerce"); ?></option>
                                        <option value="No" <?php echo "No" === $invoice_no_type ? 'selected' : ''; ?>><?php esc_html_e("Custom number", "print-invoices-packing-slip-labels-for-woocommerce"); ?></option>
                                    </select>
                                </div>
                                <div class="wf_invoice_number_postfix_pdf_fw_wizard">
                                    <div class="choose_date_div">
                                        <input type="text" class="wt_pklist_inv_no_suffix" name="woocommerce_wf_invoice_number_postfix_pdf_fw" value="<?php echo esc_attr($suffix); ?>">

                                    </div>
                                </div>

                            </div>

                            <span class="help-text"><?php esc_html_e("Type in prefix/ suffix / date format if required", "print-invoices-packing-slip-labels-for-woocommerce"); ?></span>

                        </div>

                        <div class="wt-invoice-popup">
                            <div class="wf_inv_num_frmt_hlp_fw  wf_pklist_popup" style="width: 365px;">
                                <div class="wf_pklist_popup_hd" style="display: none;">
                                    <div class="wf_pklist_popup_close"></div>
                                </div>
                                <div class="wf_pklist_popup_body">
                                    <table class="wp-list-table widefat choose_date_table">
                                        <thead>
                                            <tr>
                                                <th class="wt-popup-heading"><?php esc_html_e('Supported shortcodes for date', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[d-m-Y]">[d-m-Y]<span> <?php echo esc_html(gmdate('d-m-Y')); ?></span></a></td>
                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[d/m/y]">[d/m/y]<span> <?php echo esc_html(gmdate('d/m/y')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[d]">[d]<span> <?php echo esc_html(gmdate('d')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[D]">[D]<span> <?php echo esc_html(gmdate('D')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[m]">[m]<span> <?php echo esc_html(gmdate('m')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[M]">[M]<span> <?php echo esc_html(gmdate('M')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[y]">[y]<span> <?php echo esc_html(gmdate('y')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[Y]">[Y]<span> <?php echo esc_html(gmdate('Y')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[F]">[F]<span> <?php echo esc_html(gmdate('F')); ?></span></a></td>

                                            </tr>
                                            <tr class="wf_inv_num_frmt_fw_append_btn_tr">
                                                <td><a class="wf_inv_num_frmt_fw_append_btn" title="<?php echo esc_attr($date_frmt_tooltip) ?>"
                                                        data-format-val="[dS]">[dS]<span> <?php echo esc_html(gmdate('dS')); ?></span></a></td>

                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="wt_form_wizard_field_row wc_custom_no_div">
                            <div class="wt_form_wizard_field_col_1 wt_form_wizard_field_col">
                                <p><?php esc_html_e("What should be the starting number for your invoices?", "print-invoices-packing-slip-labels-for-woocommerce"); ?></p>
                            </div>
                            <div class="wt_form_wizard_field_col_5 wt_form_wizard_field_col">
                                <input type="number" name="woocommerce_wf_invoice_start_number_preview_pdf_fw" value="<?php echo esc_attr($invoice_start_number); ?>" min="0">
                                <input type="hidden" name="woocommerce_wf_invoice_start_number_pdf_fw" value="<?php echo esc_attr($invoice_start_number); ?>" min="0">
                                <input type="hidden" class="wf_current_invoice_number_pdf_fw" value="<?php echo esc_attr($current_invoice_number_in_db); ?>" name="woocommerce_wf_Current_Invoice_number_pdf_fw" class="">
                            </div>
                        </div>
                        <div class="wt_form_wizard_field_row" style="margin-bottom: 30px;">
                            <div class="wt_form_wizard_field_col_1 wt_form_wizard_field_col">
                                <p><?php esc_html_e("What length would you prefer for your invoice number", "print-invoices-packing-slip-labels-for-woocommerce"); ?></p>
                            </div>
                            <div class="wt_form_wizard_field_col_5 wt_form_wizard_field_col">
                                <input type="number" name="woocommerce_wf_invoice_padding_number_pdf_fw" value="<?php echo esc_attr($invoice_no_length); ?>" min="0">
                            </div>
                        </div>
                        <div class="wt_form_wizard_field_row">
                            <div class="wt_form_wizard_field_col_1 wt_form_wizard_field_col">
                                <?php
                                $query = new WC_Order_Query(array(
                                    'limit' => 1,
                                    'orderby' => 'date',
                                    'order' => 'DESC',
                                    'parent' => 0,
                                ));

                                $orders = $query->get_orders();
                                $order_number = "123";
                                if (count($orders) > 0) {
                                    $order = $orders[0];
                                    $order_number = $order->get_order_number();
                                }

                                $inv_num = ++$current_invoice_number;
                                $use_wc_order_number = Wf_Woocommerce_Packing_List::get_option('woocommerce_wf_invoice_as_ordernumber', $invoice_module_id);
                                ?>
                                <input type="hidden" value="<?php echo esc_attr($order_number); ?>" id="sample_invoice_number_pdf_fw">
                                <input type="hidden" id="sample_current_invoice_number_pdf_fw" value="<?php echo esc_attr($current_invoice_number); ?>">

                                <div id="invoice_number_prev_div" class="wt-invoice-preview">
                                    <div class="invoice-preview-header">
                                        <p class="preview-label">
                                            <?php echo  esc_html__("PREVIEW", "print-invoices-packing-slip-labels-for-woocommerce"); ?>
                                        </p>
                                    </div>
                                    <div class="invoice-preview-body">
                                        <span id="preview_invoice_number_pdf_fw"></span>
                                        <p id="preview_invoice_number_text">
                                            <?php echo  esc_html__("If the order number is", "print-invoices-packing-slip-labels-for-woocommerce") . ' ' .
                                                esc_html($order_number) . ',
                                            <br>
                                            ' . sprintf(
                                                    /* translators: %s: Document type (invoice, packing list, etc.) */
                                                    esc_html__("the %s number would be", 'print-invoices-packing-slip-labels-for-woocommerce'),
                                                    esc_html($template_type)
                                                )  ?>
                                        </p>
                                        <p id="preview_invoice_number_text_custom">
                                            <?php echo  sprintf(
                                                /* translators: %s: Document type (invoice, packing list, etc.) */
                                                esc_html__(
                                                'Your next %s number would be',
                                                'print-invoices-packing-slip-labels-for-woocommerce'
                                            ), esc_html($template_type))  ?>
                                        </p>

                                    </div>
                                </div>



                            </div>
                        </div>
                        <div class="wt_form_wizard_footer">
                            <a class="wt_form_wizard_submit wt_pklist_btn wt_pklist_btn_primary"><?php esc_html_e("Finish setup", "print-invoices-packing-slip-labels-for-woocommerce"); ?></a>
                            <a class="wt_form_wizard_prev wt_pklist_btn wt_pklist_btn_secondary" data-wizard-step="3" data-target-class="wt_wrap_wizard_form_steps_fields" data-wizard-prev-step="2"><?php esc_html_e("Back", "print-invoices-packing-slip-labels-for-woocommerce"); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="wt_wrap_wizard_container_inner_empty_col"></div>
    </div>
</div>


<div class="wt_pklist_form_wizard_success wf_pklist_popup" style="border-radius:8px;">
    <div class="wf_pklist_popup_body">
        <div style="background-color: #89c98b;border-radius:50%;margin: 2em 8em 0em;">
            <img src="<?php echo esc_url(WF_PKLIST_PLUGIN_URL . 'admin/images/fm_wz_success.png'); ?>">
        </div>
        <div>
            <p style="font-size: 16px;font-style: normal;font-weight: 600;line-height: 28px;"><?php esc_html_e("Invoice setup successfully", "print-invoices-packing-slip-labels-for-woocommerce"); ?>
        </div>
        <div style="margin-bottom: 2em;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wf_woocommerce_packing_list&complete_wizard=1')); ?>" class="wt_pklist_btn wt_pklist_btn_primary"><?php esc_html_e("Close", "print-invoices-packing-slip-labels-for-woocommerce"); ?></a>
        </div>
    </div>
</div>

<!-- Skip Wizard Confirmation Popup -->
<div class="wt_skip_wizard_confirm_popup wf_pklist_popup" style="width: 590px; height: 200px;">
    <div class="wt_skip_wizard_confirm_popup_main wf_pklist_popup_body">
        <div class="popup-header">
            <span class="popup-title"><?php esc_html_e("Skip setup?", "print-invoices-packing-slip-labels-for-woocommerce"); ?></span>
            <button type="button" class="popup-close wf_pklist_popup_cancel">×</button>
        </div>
        
        <div class="message">
            <p><?php esc_html_e("We recommend completing the setup to ensure the plugin works properly.", "print-invoices-packing-slip-labels-for-woocommerce"); ?></p>
        </div>
    </div>
    
    <div class="wt_skip_wizard_confirm_popup_footer wf_pklist_popup_footer">
        <button type="button" name="" class="button-secondary wt_skip_wizard_confirm_popup_yes">
            <?php esc_html_e("Skip anyway", "print-invoices-packing-slip-labels-for-woocommerce"); ?>
        </button>
        <button type="button" name="" class="button-primary wt_skip_wizard_confirm_popup_finish">
            <?php esc_html_e("Finish setup", "print-invoices-packing-slip-labels-for-woocommerce"); ?>
        </button>	
    </div>
</div>
