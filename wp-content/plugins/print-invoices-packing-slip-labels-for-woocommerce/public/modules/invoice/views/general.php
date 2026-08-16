<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals,WordPress.WP.EnqueuedResources,PluginCheck.CodeAnalysis.Offloading
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wf-tab-content" data-id="<?php echo esc_attr($target_id); ?>">
    <style type="text/css">
    .wf_inv_num_frmt_hlp_btn{ cursor:pointer; }
    .wf_inv_num_frmt_hlp table thead th{ font-weight:bold; text-align:left; }
    .wf_inv_num_frmt_hlp table tbody td{ text-align:left; }
    .wf_inv_num_frmt_hlp .wf_pklist_popup_body{min-width:300px; padding:20px;}
    .wf_inv_num_frmt_append_btn{ cursor:pointer; }
    </style>
    <!-- Newsletter Subscription Box - Sidebar  -->
<?php 
// Check if newsletter banner should be hidden

$pro_invoice_path = 'wt-woocommerce-invoice-addon/wt-woocommerce-invoice-addon.php';
$margin_top = is_plugin_active( $pro_invoice_path ) ? 'top: 140px;' : ''; 

$newsletter_banner_hidden = get_option('wt_newsletter_banner_hidden', false); 

if (!$newsletter_banner_hidden) : 
    ?>
    <div class="wt_newsletter_subscription_widget" style="position:relative; <?php echo is_rtl() ? 'left:0;' : 'right:0;'; echo esc_attr($margin_top);?>">
        <div class="wt_newsletter_subscription_box" ">
            <div class="wt_newsletter_header">
                <div class="wt_newsletter_icon">
                <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <mask id="mask0_11201_2391" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="0" y="0" width="38" height="38">
                        <path d="M38 0H0V38H38V0Z" fill="white"/>
                    </mask>
                    <g mask="url(#mask0_11201_2391)">
                        <path d="M36.4832 16.3331L21.6513 1.50126C21.1882 1.03814 20.5113 1.10939 20.0482 1.57251L1.54695 19.8125C1.08382 20.2756 1.06007 21.2137 1.5232 21.6769L16.3076 36.4612C16.7707 36.9244 17.5307 36.9362 17.9938 36.4731L36.4713 17.9956C36.9345 17.5325 36.9463 16.7962 36.4832 16.3331Z" fill="#FFC44D"/>
                        <path d="M28.501 32.0684H36.8135M34.4385 27.3184H36.8135M7.12598 21.381H20.1885C20.819 21.381 21.376 20.862 21.376 20.1935V8.31843M16.626 26.1309V29.6934M29.6885 16.6309H26.126M36.4806 16.3329C36.9449 16.7972 36.9307 17.5346 36.4664 17.9978L17.9936 36.4717C17.5293 36.936 16.7729 36.9313 16.3097 36.4669L1.52181 21.679C1.0575 21.2147 1.086 20.2766 1.55031 19.8134L20.0456 1.57699C20.5099 1.11386 21.1821 1.0343 21.6464 1.49861L36.4806 16.3329Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                </svg>
                </div>
                <div class="wt_newsletter_title">
                <h3><?php esc_html_e('Subscribe to our newsletter for exclusive offers & updates', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h3>
                </div>
            </div>
                
            <div id="mc_embed_shell">
                <div id="mc_embed_signup">
                    <form action="https://list-manage.us5.list-manage.com/subscribe/post?u=10e843cdec17dd1d2e769ead6&amp;id=d9d25110b9&amp;f_id=0020b8edf0" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank">
                        <div id="mc_embed_signup_scroll"><h2><?php esc_html_e('Subscribe', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h2>
                            <div class="indicates-required"><span class="asterisk">*</span> <?php esc_html_e('indicates required', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></div>
                            <div class="mc-field-group"><label for="mce-EMAIL"></label><input type="email" name="EMAIL" class="required email" id="mce-EMAIL" required="" value="" placeholder="<?php esc_attr_e('Enter your email address', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>"></div>
                            <div class="consent-checkbox">
                                <input type="checkbox" id="consent-checkbox" name="CONSENT" class="required checkbox" required>
                                <label for="consent-checkbox">
                                <?php 
                                    // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
                                    printf(esc_html__(
                                            'I consent to receive newsletters and exclusive offers from WebToffee and agree to the %1$sPrivacy Policy%2$s.',
                                            'print-invoices-packing-slip-labels-for-woocommerce'
                                        ),
                                        '<a href="https://www.webtoffee.com/privacy-policy/" target="_blank">',
                                        '</a>'
                                    );
                                    ?>
                                </label>
                            </div>
                            <div hidden=""><input type="hidden" name="tags" value="4545901"></div>
                            <div id="mce-responses" class="clear">
                            <div class="response" id="mce-error-response" style="display: none;"></div>
                            <div class="response" id="mce-success-response" style="display: none;"></div>
                            </div>
                            <div aria-hidden="true" style="position: absolute; left: -5000px;"><input type="text" name="b_10e843cdec17dd1d2e769ead6_d9d25110b9" tabindex="-1" value=""></div><div class="clear"><input type="submit" name="subscribe" id="mc-embedded-subscribe" class="button" value="<?php esc_attr_e('Subscribe', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>"></div>
                        </div>
                    </form>
                </div>
                <?php 
                // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent WordPress.WP.EnqueuedResources.NonEnqueuedScript @codingStandardsIgnoreStart
                ?>
                <script type="text/javascript">/* Preserve WordPress's jQuery before Mailchimp's mc-validate.js loads its own bundled jQuery 1.9.0 and overwrites the global. */ window.wt_pklist_wp_jquery = window.jQuery; window.wt_pklist_wp_dollar = window.$;</script>
                <script type="text/javascript" src="//s3.amazonaws.com/downloads.mailchimp.com/js/mc-validate.js"></script><script type="text/javascript">(function($) {window.fnames = new Array(); window.ftypes = new Array();fnames[0]='EMAIL';ftypes[0]='email';fnames[1]='FNAME';ftypes[1]='text';fnames[2]='LNAME';ftypes[2]='text';fnames[3]='ADDRESS';ftypes[3]='address';fnames[4]='PHONE';ftypes[4]='phone';fnames[5]='BIRTHDAY';ftypes[5]='birthday';fnames[6]='MMERGE6';ftypes[6]='text';fnames[7]='IS_BOARD';ftypes[7]='text';fnames[8]='IS_CONF';ftypes[8]='text';fnames[9]='IS_CONT';ftypes[9]='text';}(jQuery));var $mcj = jQuery; /* Restore WordPress's jQuery so admin scripts (color picker, jQuery UI, tipTip) keep working; $mcj retains Mailchimp's jQuery for form validation. */ if (window.wt_pklist_wp_jquery) { window.jQuery = window.wt_pklist_wp_jquery; window.$ = window.wt_pklist_wp_dollar; }</script>
                <?php // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent WordPress.WP.EnqueuedResources.NonEnqueuedScript codingStandardsIgnoreEnd ?>
            </div>
        </div>
    </div>
<?php endif; ?>
    <form method="post" class="wf_settings_form">
        <input type="hidden" value="invoice" class="wf_settings_base" />
        <input type="hidden" value="wf_save_settings" class="wf_settings_action" />
        <input type="hidden" value="wt_invoice_general" name="wt_tab_name" class="wt_tab_name" />
        <p><?php esc_html_e('Configure the general settings required for the invoice.','print-invoices-packing-slip-labels-for-woocommerce');?></p>
        <?php
        // Set nonce:
        if (function_exists('wp_nonce_field'))
        {
            wp_nonce_field('wf-update-invoice-'.WF_PKLIST_POST_TYPE);
        }
        $date_frmt_tooltip=__('Click to append with existing data','print-invoices-packing-slip-labels-for-woocommerce');
        $invoice_attachment_wc_email_classes = Wf_Woocommerce_Packing_List::get_option('wt_pdf_invoice_attachment_wc_email_classes',$this->module_id);
        ?>
        <table class="wf-form-table">
            <tbody>
                <?php
                    $settings_arr['invoice_general_general'] = array(

                        'woocommerce_wf_enable_invoice' => array(
                            'type' => 'wt_toggle_checkbox',
                            'id' => 'woocommerce_wf_enable_invoice',
                            'class' => 'woocommerce_wf_enable_invoice',
                            'name' => 'woocommerce_wf_enable_invoice',
                            'value' => "Yes",
                            'checkbox_fields' => array('Yes'=> __('Enable to print, download, and mail invoices.','print-invoices-packing-slip-labels-for-woocommerce')),
                            'label' => array(
                                'text' => __('Enable Invoice','print-invoices-packing-slip-labels-for-woocommerce'),
                                'style' => "font-weight:bold;",
                            ),
                            'tooltip' => true,
                            'col' => 3,
                        ),

                        'wt_inv_gen_hr_line_1' => array(
                            'type' => 'wt_hr_line',
                            'class' => is_plugin_active('wt-woocommerce-invoice-addon/wt-woocommerce-invoice-addon.php') ? 'wf_field_hr' : 'wf_field_hr wf_field_hr_hide',
                            'ref_id' => 'wt_hr_line_1'
                        ),

                        'wt_sub_head_inv_gen_general' => array(
                            'type' => 'wt_sub_head',
                            'class' => 'wt_pklist_field_group_hd_sub',
                            'label' => __('General','print-invoices-packing-slip-labels-for-woocommerce'),
                            'heading_number' => 1,
                            'ref_id' => 'wt_sub_head_1'
                        ),

                        'woocommerce_wf_orderdate_as_invoicedate' => array(
                            'type' => 'wt_radio',
                            'label' => __('Invoice date','print-invoices-packing-slip-labels-for-woocommerce'),
                            'id' => '',
                            'class' => 'woocommerce_wf_orderdate_as_invoicedate',
                            'name' => 'woocommerce_wf_orderdate_as_invoicedate',
                            'value' => '',
                            'radio_fields' => array(
                                    'Yes'=>__('Order date','print-invoices-packing-slip-labels-for-woocommerce'),
                                    'No'=>__('Invoiced date','print-invoices-packing-slip-labels-for-woocommerce'),
                                ),
                            'col' => 3,
                            'tooltip' => true,
                            'alignment' => 'horizontal_with_label',
                            'ref_id' => 'woocommerce_wf_orderdate_as_invoicedate',
                        ),

                        'woocommerce_wf_generate_for_orderstatus' => array(
                            'type' => 'wt_select2_checkbox',
                            'label' => __('Automate invoice creation','print-invoices-packing-slip-labels-for-woocommerce'),
                            'name' => 'woocommerce_wf_generate_for_orderstatus',
                            'id' => 'woocommerce_wf_generate_for_orderstatus_st',
                            'value' => $order_statuses,
                            'checkbox_fields' => $order_statuses,
                            'class' => 'woocommerce_wf_generate_for_orderstatus',
                            'col' => 3,
                            'placeholder' => __('Choose order status','print-invoices-packing-slip-labels-for-woocommerce'),
                            'help_text' => __('Automatically creates invoices for selected order statuses.','print-invoices-packing-slip-labels-for-woocommerce'),
                            'alignment' => 'vertical_with_label',
                            'ref_id' => 'woocommerce_wf_generate_for_orderstatus',
                        ),

                        'wt_pdf_invoice_attachment_wc_email_classes' => array(
                            'type' => 'wt_select2_checkbox',
                            'label' => __('Attach invoice PDF to selected WooCommerce emails.','print-invoices-packing-slip-labels-for-woocommerce'),
                            'name' => 'wt_pdf_invoice_attachment_wc_email_classes',
                            'id' => 'wt_pdf_invoice_attachment_wc_email_classes_st',
                            'value' => $invoice_attachment_wc_email_classes,
                            'checkbox_fields' => Wt_Pklist_Common::wt_pdf_get_wc_email_classes(),
                            'class' => 'wt_pdf_invoice_attachment_wc_email_classes',
                            'col' => 3,
                            'placeholder' => __('Choose email classes','print-invoices-packing-slip-labels-for-woocommerce'),
                            'help_text' => __("Select email types corresponding to the order statuses under Automate invoice creation option. If none are selected, invoices must be generated manually to be attached to emails.","print-invoices-packing-slip-labels-for-woocommerce"),
                            'alignment' => 'vertical_with_label',
                            'ref_id' => 'wt_pdf_invoice_attachment_wc_email_classes',
                        ),

                        'wt_pklist_restrict_invoice_attachment_for' => array(
                            'type' => 'wt_select2_checkbox',
                            'label' => __("Disable invoice attachment for","print-invoices-packing-slip-labels-for-woocommerce"),
                            'name' => 'wt_pklist_restrict_invoice_attachment_for',
                            'id' => 'wt_pklist_restrict_invoice_attachment_for',
                            'value' => '',
                            'checkbox_fields' => Wt_Pklist_Common::get_payment_methods(),
                            'class' => 'wt_pklist_restrict_invoice_attachment_for',
                            'col' => 3,
                            'placeholder' => __('Choose payment methods','print-invoices-packing-slip-labels-for-woocommerce'),
                            'help_text' => __('Select payment methods corresponding to the order statuses under the Automate invoice creation option for which the invoice attachment should be restricted. If none are selected, the invoice will be attached for all payment methods.','print-invoices-packing-slip-labels-for-woocommerce'),
                            'alignment' => 'vertical_with_label',
                            'ref_id' => 'wt_pklist_restrict_invoice_attachment_for',
                        ),

                        'wf_woocommerce_invoice_show_print_button' => array(
                            'type' => 'wt_multi_checkbox',
                            'label' => __("Show print invoice button for customers","print-invoices-packing-slip-labels-for-woocommerce"),
                            'id' => '',
                            'class' => 'wf_woocommerce_invoice_show_print_button',
                            'name' => 'wf_woocommerce_invoice_show_print_button',
                            'value' => '',
                            'checkbox_fields' => array(
                                'order_listing' => __('My account - Order lists page','print-invoices-packing-slip-labels-for-woocommerce'),
                                'order_details' => __('My account - Order details page', 'print-invoices-packing-slip-labels-for-woocommerce'),
                                'order_email' => __('Order email','print-invoices-packing-slip-labels-for-woocommerce'),
                            ),
                            'col' => 3,
                            'alignment' => 'vertical_with_label',
                            'tooltip' => true
                        ),

                        'wt_inv_gen_hr_line_2' => array(
                            'type' => 'wt_hr_line',
                            'class' => 'wf_field_hr',
                            'ref_id' => 'wt_hr_line_2',
                        ));
                    
                    $settings_arr['invoice_general_invoice_number'] = array(
                        'wt_sub_head_inv_gen_inv_no' => array(
                            'type' => 'wt_sub_head',
                            'class' => 'wt_pklist_field_group_hd_sub',
                            'label' => __("Invoice number",'print-invoices-packing-slip-labels-for-woocommerce'),
                            'heading_number' => 2,
                            'ref_id' => 'wt_sub_head_4'
                        ),

                        'invoice_number_format' => array(
                            'type' => 'invoice_number_format',
                        ),

                        'wt_inv_gen_hr_line_3' => array(
                            'type' => 'wt_hr_line',
                            'class' => 'wf_field_hr',
                            'ref_id' => 'wt_hr_line_4',
                        ));
                        
                    $settings_arr['invoice_general_invoice_details'] = array( 
                        'wt_sub_head_inv_gen_others' => array(
                            'type' => 'wt_sub_head',
                            'class' => 'wt_pklist_field_group_hd_sub',
                            'label' => __("Invoice details",'print-invoices-packing-slip-labels-for-woocommerce'),
                            'heading_number' => 3,
                            'ref_id' => 'wt_sub_head_2',
                        ),

                        'wf_invoice_contactno_email' => array(
                            'type'=>"wt_additional_fields",
                            'label'=>__("Order meta fields", 'print-invoices-packing-slip-labels-for-woocommerce'),
                            'name'=>'wf_'.$this->module_base.'_contactno_email',
                            'module_base' => $this->module_base,
                            'ref_id' => 'wt_additional_fields_invoice',
                            'help_text' => __("Select/add order meta to display additional information related to the order on the invoice.","print-invoices-packing-slip-labels-for-woocommerce"),
                        ),

                        'wf_invoice_product_meta_fields' => array(
                            'type'=>"wt_product_meta_fields",
                            'label'=>__("Product metadata", 'print-invoices-packing-slip-labels-for-woocommerce'),
                            'name'=>'wf_'.$this->module_base.'_product_meta',
                            'module_base' => $this->module_base,
                            'ref_id' => 'wt_product_meta_fields_invoice',
                            'help_text' => __("Select/add product meta to display additional information related to the product on the invoice.","print-invoices-packing-slip-labels-for-woocommerce"),
                        ),

                        'woocommerce_wf_packinglist_logo' => array(
                            'type'=>"wt_uploader",
                            'label'=>__("Custom logo for invoice",'print-invoices-packing-slip-labels-for-woocommerce'),
                            'name'=>"woocommerce_wf_packinglist_logo",
                            'id'=>"woocommerce_wf_packinglist_logo",
                            'help_text' => __("If left blank, default to the logo from General settings. Ensure to select company logo from ‘Invoice > Customize > Company Logo’ to reflect on the invoice. Recommended size is 150×50px.","print-invoices-packing-slip-labels-for-woocommerce"),
                        ),

                        'wt_inv_gen_hr_line_4' => array(
                            'type' => 'wt_hr_line',
                            'class' => 'wf_field_hr',
                            'ref_id' => 'wt_hr_line_3',
                        ));


                     $settings_arr['invoice_general_others'] = array( 
                        'wt_sub_head_inv_gen_adv' => array(
                            'type' => 'wt_sub_head',
                            'class' => 'wt_pklist_field_group_hd_sub',
                            'label' => __("Others",'print-invoices-packing-slip-labels-for-woocommerce'),
                            'heading_number' => 4,
                            'ref_id' => 'wt_sub_head_3'
                        ),

                        'wf_woocommerce_invoice_prev_install_orders' => array(
                            'type' => 'wt_single_checkbox',
                            'label' => __("Generate invoices for existing orders","print-invoices-packing-slip-labels-for-woocommerce"),
                            'id' => 'wf_woocommerce_invoice_prev_install_orders',
                            'name' => 'wf_woocommerce_invoice_prev_install_orders',
                            'value' => "Yes",
                            'checkbox_fields' => array('Yes'=> __("Enable to create invoice for orders generated before plugin installation","print-invoices-packing-slip-labels-for-woocommerce")),
                            'class' => "wf_woocommerce_invoice_prev_install_orders",
                            'col' => 3,
                        ),

                        'wf_woocommerce_invoice_free_orders' => array(
                            'type' => 'wt_single_checkbox',
                            'label' => __("Generate invoices for free orders","print-invoices-packing-slip-labels-for-woocommerce"),
                            'id' => 'wf_woocommerce_invoice_free_orders',
                            'name' => 'wf_woocommerce_invoice_free_orders',
                            'value' => "Yes",
                            'checkbox_fields' => array('Yes'=> __("Enable to create invoices for free orders","print-invoices-packing-slip-labels-for-woocommerce")),
                            'class' => "wf_woocommerce_invoice_free_orders",
                            'col' => 3,
                        ),

                        'wf_woocommerce_invoice_free_line_items' => array(
                            'type' => 'wt_single_checkbox',
                            'label' => __("Display free line items in the invoice","print-invoices-packing-slip-labels-for-woocommerce"),
                            'id' => 'wf_woocommerce_invoice_free_line_items',
                            'name' => 'wf_woocommerce_invoice_free_line_items',
                            'value' => "Yes",
                            'checkbox_fields' => array('Yes'=> __("Include free(priced as 0) line items in the invoice","print-invoices-packing-slip-labels-for-woocommerce")),
                            'class' => "wf_woocommerce_invoice_free_line_items",
                            'col' => 3,
                            'help_text' => __('Enable to create invoices for free orders.','print-invoices-packing-slip-labels-for-woocommerce'),
                            'ref_id' => 'wf_woocommerce_invoice_free_line_items',
                        ),

                        'woocommerce_wf_custom_pdf_name' => array(
                            'type' => 'wt_select_dropdown',
                            'label' => __("PDF name format","print-invoices-packing-slip-labels-for-woocommerce"),
                            'id' => "",
                            'name' => "woocommerce_wf_custom_pdf_name",
                            'value' => "",
                            'select_dropdown_fields' => array(
                                    '[prefix][order_no]'=>__('[prefix][order_no]', 'print-invoices-packing-slip-labels-for-woocommerce'),
                                    '[prefix][invoice_no]'=>__('[prefix][invoice_no]', 'print-invoices-packing-slip-labels-for-woocommerce'),
                                ),
                            'class' => "",
                            'col' => 3,
                            'help_text' => __("Select a name format for PDF invoice that includes invoice/order number.","print-invoices-packing-slip-labels-for-woocommerce"),
                            'ref_id' => 'woocommerce_wf_custom_pdf_name',
                        ),

                        'woocommerce_wf_custom_pdf_name_prefix' => array(
                            'type' => "wt_text",
                            'label' => __("Custom PDF name prefix", 'print-invoices-packing-slip-labels-for-woocommerce'),
                            'name' => 'woocommerce_wf_custom_pdf_name_prefix',
                            'help_text'=>__("Input a custom prefix for ‘PDF name format’ that will appear at the beginning of the name. Defaulted to ‘Invoice_’.",'print-invoices-packing-slip-labels-for-woocommerce'),
                            'ref_id' => 'woocommerce_wf_custom_pdf_name_prefix',
                        ),     
                        
                        'woocommerce_wt_use_latest_settings_invoice' => array(
                            'type'  => 'wt_single_checkbox',
                            'label' => __("Use latest settings for invoice","print-invoices-packing-slip-labels-for-woocommerce"),
                            'id'    => 'woocommerce_wt_use_latest_settings_invoice',
                            'name'  => 'woocommerce_wt_use_latest_settings_invoice',
                            'value' => "Yes",
                            'checkbox_fields' => array('Yes'=> ''),
                            'class' => "woocommerce_wt_use_latest_settings_invoice",
                            'col'   => 3,
                            'help_text' => __('Enable to apply the most recent settings to previous order invoices. This will match the previous invoices with the upcoming invoices.Changing the company address, name or any other settings in the future may overwrite previously created invoices with the most up-to-date information.','print-invoices-packing-slip-labels-for-woocommerce'),
                            'ref_id'    => 'woocommerce_wt_use_latest_settings_invoice',
                        ),
                    );
                    
                    $settings_arr = Wf_Woocommerce_Packing_List::add_fields_to_settings($settings_arr,$target_id,$template_type,$this->module_id);

                    if(class_exists('WT_Form_Field_Builder_PRO_Documents')){
                        $Form_builder = new WT_Form_Field_Builder_PRO_Documents();
                    }else{
                        $Form_builder = new WT_Form_Field_Builder();
                    }

                    $h_no = 1;
                    foreach($settings_arr as $settings){
                        foreach($settings as $k => $this_setting){
                            if(isset($this_setting['type']) && "wt_sub_head" === $this_setting['type']){
                                $settings[$k]['heading_number'] = $h_no;
                                $h_no++;
                            }
                        }
                        $Form_builder->generate_form_fields($settings, $this->module_id);
                    }
                ?>
            </tbody>
        </table>
        <div class="wf_inv_num_frmt_hlp wf_pklist_popup">
            <div class="wf_pklist_popup_hd">
                <span style="line-height:40px;" class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Date formats','print-invoices-packing-slip-labels-for-woocommerce');?>
                <div class="wf_pklist_popup_close">X</div>
            </div>
            <div class="wf_pklist_popup_body">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Format','print-invoices-packing-slip-labels-for-woocommerce');?></th><th><?php esc_html_e('Output','print-invoices-packing-slip-labels-for-woocommerce');?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[F]</a></td>
                            <td><?php echo esc_html(gmdate('F')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[dS]</a></td>
                            <td><?php echo esc_html(gmdate('dS')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[M]</a></td>
                            <td><?php echo esc_html(gmdate('M')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[m]</a></td>
                            <td><?php echo esc_html(gmdate('m')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[d]</a></td>
                            <td><?php echo esc_html(gmdate('d')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[D]</a></td>
                            <td><?php echo esc_html(gmdate('D')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[y]</a></td>
                            <td><?php echo esc_html(gmdate('y')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[Y]</a></td>
                            <td><?php echo esc_html(gmdate('Y')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[d/m/y]</a></td>
                            <td><?php echo esc_html(gmdate('d/m/y')); ?></td>
                        </tr>
                        <tr>
                            <td><a class="wf_inv_num_frmt_append_btn" title="<?php echo esc_attr($date_frmt_tooltip); ?>">[d-m-Y]</a></td>
                            <td><?php echo esc_html(gmdate('d-m-Y')); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php 
            include plugin_dir_path( WF_PKLIST_PLUGIN_FILENAME )."admin/views/admin-settings-save-button.php";
        ?>
        
    </form>
</div>
<?php 
    //settings form fields
    do_action('wf_pklist_module_settings_form');
?>
