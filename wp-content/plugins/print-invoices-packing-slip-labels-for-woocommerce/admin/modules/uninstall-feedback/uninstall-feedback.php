<?php
/**
 * Uninstall Feedback
 *
 * @link       
 * @since 2.5.0     
 *
 * @package  Wf_Woocommerce_Packing_List  
 */
if (!defined('ABSPATH')) {
    exit;
}
class Wf_Woocommerce_Packing_List_Uninstall_Feedback
{
	protected $api_url='https://feedback.webtoffee.com/wp-json/wfinvoice/v1/uninstall';
    protected $current_version=WF_PKLIST_VERSION;
    protected $auth_key='wfinvoice_uninstall_1234#';
    protected $plugin_id='wfinvoice';
    public function __construct()
	{
        add_action('admin_footer', array($this,'deactivate_scripts'));
        add_action('wp_ajax_wfinvoice_submit_uninstall_reason', array($this,"send_uninstall_reason"));
        add_filter('plugin_action_links_'.plugin_basename(WF_PKLIST_PLUGIN_FILENAME),array($this,'plugin_action_links'));
    }
    public function plugin_action_links($links) 
	{
		if(array_key_exists('deactivate',$links))
		{
            $links['deactivate']=str_replace('<a', '<a class="wfinvoice-deactivate-link"',$links['deactivate']);
        }
		return $links;
	}
    private function get_uninstall_reasons()
    {
        $mpdf_plugin_data = Wf_Woocommerce_Packing_List_Admin::get_mpdf_plugin_data();
        $mpdf_url = isset($mpdf_plugin_data['mpdf_action_url']) ? $mpdf_plugin_data['mpdf_action_url'] : '';
        
        $reasons = array(
            array(
                'id' => 'could-not-understand',
                'text' => __('I couldn\'t understand how to make it work', 'print-invoices-packing-slip-labels-for-woocommerce'),
                'type' => 'textarea',
                'placeholder' => __('Would you like us to assist you?', 'print-invoices-packing-slip-labels-for-woocommerce')
            ),
            array(
                'id' => 'no-language-support',
                'text' => __('Language support issues', 'print-invoices-packing-slip-labels-for-woocommerce'),
                'type' => 'main_reason',
                'sub_reason'=>array(
                    array(
                        'id' => 'settings-not-in-my-language',
                        'text'=>__('Plugin settings not available in my language', 'print-invoices-packing-slip-labels-for-woocommerce'),
                        'type' => 'text',
                        'placeholder' => __('Which language?', 'print-invoices-packing-slip-labels-for-woocommerce'),
                    ),
                    array(
                        'id' => 'language-support-in-pdf',
                        /* translators: 1$s: HTML link opening tag, 2$s: HTML link closing tag */
                        'text'=>__('Missing RTL/Unicode language(e.g Chinese, Arabic etc) support for PDF document.', 'print-invoices-packing-slip-labels-for-woocommerce').'<br /><span style="padding-left:20px;">'.sprintf(__('Already tried the free %1$s WebToffee mPDF addon %2$s.', 'print-invoices-packing-slip-labels-for-woocommerce'), '<a href="'.$mpdf_url.'" target="_blank">', '</a>').'</span>' . __(' Click the link to activate the plugin and try printing the document again.', 'print-invoices-packing-slip-labels-for-woocommerce'),
                        'type' => 'text',
                        'placeholder' => __('Which language?', 'print-invoices-packing-slip-labels-for-woocommerce'),
                    )
                )
            ),
            array(
                'id' => 'unable-to-add-vat',
                'text' => __('I am unable to add customer VAT(or GSTIN or ABN or any other tax id etc)','print-invoices-packing-slip-labels-for-woocommerce'),
                'type' => 'main_reason',
                'sub_reason' => array(
                    array(
                        'id' => 'unable_to_add_vat_from_another_plugin',
                        'text' => __('Unable to add from third party plugin that collects customer VAT(or GSTIN or ABN etc)','print-invoices-packing-slip-labels-for-woocommerce'),
                        'type' => 'text',
                        'placeholder' => __('Mention the name of the plugin or meta key if any','print-invoices-packing-slip-labels-for-woocommerce')
                    ),
                    array(
                        'id' => 'need_field_to_add_from_checkout_page',
                        'text' => __('Need a field to add the tax id on checkout page','print-invoices-packing-slip-labels-for-woocommerce'),
                        'type' => '',
                        'placeholder' => '',
                    ),
                ),
            ),
            array(
                'id' => 'found-better-plugin',
                'text' => __('I found a better plugin', 'print-invoices-packing-slip-labels-for-woocommerce'),
                'type' => 'text',
                'placeholder' => __('Which plugin?', 'print-invoices-packing-slip-labels-for-woocommerce')
            ),
            array(
                'id' => 'not-have-that-feature',
                'text' => __('The plugin is great, but I need specific feature that you don\'t support', 'print-invoices-packing-slip-labels-for-woocommerce'),
                'type' => 'textarea',
                'placeholder' => __('Could you tell us more about that feature?', 'print-invoices-packing-slip-labels-for-woocommerce')
            ),
            array(
                'id' => 'did-not-work-as-expected',
                'text' => __('The plugin didn\'t work as expected', 'print-invoices-packing-slip-labels-for-woocommerce'),
                'type' => 'textarea',
                'placeholder' => __('What did you expect?', 'print-invoices-packing-slip-labels-for-woocommerce')
            ),
            array(
                'id' => 'other',
                'text' => __('Other', 'print-invoices-packing-slip-labels-for-woocommerce'),
                'type' => 'textarea',
                'placeholder' => __('Could you tell us a bit more?', 'print-invoices-packing-slip-labels-for-woocommerce')
            ),
        );

        return $reasons;
    }

    public function deactivate_scripts()
    {
        global $pagenow;
        if('plugins.php' != $pagenow)
        {
            return;
        }
        $reasons = $this->get_uninstall_reasons();
        ?>
        <div class="wfinvoice-modal" id="wfinvoice-wfinvoice-modal">
            <div class="wfinvoice-modal-wrap">
                <div class="wfinvoice-modal-header">
                    <h3><?php esc_html_e('If you have a moment, please let us know why you are deactivating:', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h3>
                </div>
                <div class="wfinvoice-modal-body">
                    <ul class="reasons">
                        <?php 
                        foreach ($reasons as $reason) 
                        { 
                        ?>
                            <li data-type="<?php echo esc_attr($reason['type']); ?>" data-placeholder="<?php echo esc_attr(isset($reason['placeholder']) ? $reason['placeholder'] : ''); ?>">
                                <label><input type="radio" name="selected-reason" value="<?php echo esc_attr($reason['id']); ?>"><?php echo esc_html($reason['text']); ?></label>
                                <?php
                                if($reason['type']=='main_reason')
                                {
                                    ?>
                                    <ul class="sub_reasons" data-parent="<?php echo esc_attr($reason['id']); ?>">
                                        <?php
                                        foreach($reason['sub_reason'] as $sub_reason)
                                        {
                                           ?>
                                            <li data-type="<?php echo esc_attr($sub_reason['type']); ?>" data-placeholder="<?php echo esc_attr($sub_reason['placeholder']); ?>">
                                                <label><input type="radio" name="selected-sub-reason" value="<?php echo esc_attr($sub_reason['id']); ?>"><?php echo wp_kses_post($sub_reason['text']); ?></label>
                                            </li>
                                           <?php 
                                        }
                                        ?>
                                    </ul>
                                    <?php

                                }
                                ?>
                            </li>
                        <?php 
                        } 
                        ?>
                    </ul>
                    <label style="margin-bottom: 0;">
                        <span class="wt-checkbox-top-border"></span>
                        <input type="checkbox" id="wt_contact_me_checkbox" name="wt_contact_me_checkbox" value="1">
                        <?php esc_html_e("WebToffee can contact me about this feedback.", "print-invoices-packing-slip-labels-for-woocommerce"); ?>
                    </label>
                    <div id="wt_email_field_wrap" style="display:none; margin-top:10px;">
                        <label for="wt_contact_email" style="font-weight:bold;"><?php esc_html_e("Enter your email address.", "print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                        <br>
                        <input type="email" id="wt_contact_email" name="wt_contact_email" class="input-text" style="width:75%; height: 40px; padding:2px; margin-top:10px; border-radius:5px; border:2px solid #2874ba; padding-left:15px;" placeholder="<?php esc_attr_e("Enter email address", "print-invoices-packing-slip-labels-for-woocommerce"); ?>">
                        <div id="wt_email_error" style="color:red; display:none; font-size:12px; margin-top:5px;"></div>
                    </div>
                    <label style="display:block; margin-top:15px;"><input type="checkbox" id="wt_delete_all_settings" name="wt_delete_all_settings" value="1"> <?php esc_html_e("Delete all the settings made by this plugin","print-invoices-packing-slip-labels-for-woocommerce"); ?></label>
                    <div class="wt_pklist_policy_infobox">
                        <?php esc_html_e("We do not collect any personal data when you submit this form. It's your feedback that we value.", "print-invoices-packing-slip-labels-for-woocommerce");?>
                        <a href="https://www.webtoffee.com/privacy-policy/" target="_blank"><?php esc_html_e('Privacy Policy', 'print-invoices-packing-slip-labels-for-woocommerce');?></a>
                    </div>
                </div>
                <div class="wfinvoice-modal-footer">
                    <a class="button-primary" href="https://wordpress.org/support/plugin/print-invoices-packing-slip-labels-for-woocommerce/#new-topic-0" target="_blank">
                        <span class="dashicons dashicons-external" style="margin-top:3px;"></span> 
                        <?php esc_html_e('Go to support', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
                    <button class="button-primary wfinvoice-model-submit"><?php esc_html_e('Submit & Deactivate', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></button>
                    <button class="button-secondary wfinvoice-model-cancel"><?php esc_html_e('Cancel', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></button>
                    <a href="#" class="dont-bother-me"><?php esc_html_e('I rather wouldn\'t say', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
                </div>
            </div>
        </div>
        <style type="text/css">
            .wfinvoice-modal { position: fixed; z-index: 99999; top: 0; right: 0; bottom: 0; left: 0; background: rgba(0,0,0,0.5); display: none; }
            .wfinvoice-modal.modal-active {display: block;}
            .wfinvoice-modal-wrap { width: 50%; position: relative;  margin: 5vh auto; background: #fff; max-height: 90vh; display: flex; flex-direction: column; }
            .wfinvoice-modal-header { border-bottom: 1px solid #eee; padding: 8px 20px; flex: 0 0 auto; }
            .wfinvoice-modal-header h3 { line-height: 150%; margin: 0; }
            .wfinvoice-modal-body {padding: 5px 20px 5px 20px; flex: 1 1 auto; overflow-y: auto;}
            .wfinvoice-modal-body .input-text,.wfinvoice-modal-body textarea {width:75%;}
            .wfinvoice-modal-body .input-text::placeholder,.wfinvoice-modal-body textarea::placeholder{ font-size:12px; }
            .wfinvoice-modal-body .reason-input { margin-top: 5px; margin-left: 20px; }
            .wfinvoice-modal-body label:has(#wt_contact_me_checkbox) { padding-top: 8px; display: block; width: 100%; margin-bottom: 0; }
            .wfinvoice-modal-body label[for="wt_delete_all_settings"],
            .wfinvoice-modal-body label:has(#wt_delete_all_settings) { margin-top: 8px; }
            .wfinvoice-modal-footer { border-top: 1px solid #eee; padding: 12px 20px; text-align: left; flex: 0 0 auto; }
            .wt_pklist_policy_infobox{font-style:italic; text-align:left; font-size:12px; color:#aaa; line-height:14px; margin-top:15px;}
            .wt_pklist_policy_infobox a{ font-size:11px; color:#4b9cc3; text-decoration-color: #99c3d7; }
            .sub_reasons{ display:none; margin-left:15px; margin-top:10px; }
            a.dont-bother-me{ color:#939697; text-decoration-color:#d0d3d5; float:right; margin-top:7px; }
            .reasons li{ padding-top:5px; }
            .wt-checkbox-top-border { display: block; border-top: 1px solid #ddd; margin-top: 1px; margin-bottom: 15px; padding-top: 8px; width: 100%; }
        </style>
        <script type="text/javascript">
            (function ($) {
                $(function () {
                    var modal = $('#wfinvoice-wfinvoice-modal');
                    var deactivateLink = '';
                    $('#the-list').on('click', 'a.wfinvoice-deactivate-link', function (e) {
                        e.preventDefault();
                        modal.addClass('modal-active');
                        deactivateLink = $(this).attr('href');
                        modal.find('a.dont-bother-me').attr('href', deactivateLink);
                        modal.find('a.dont-bother-me').attr('data-href',deactivateLink);
                        modal.find('input[type="radio"]:checked').prop('checked', false);
                    });

                    $('#wt_delete_all_settings').on('change',function(){
                        var dlink = modal.find('a.dont-bother-me').attr('data-href');
                        if($(this).is(":checked")){
                            deactivateLink = deactivateLink+'&delete_all_settings=1';
                        }else{
                            deactivateLink = dlink;
                        }
                        modal.find('a.dont-bother-me').attr('href', deactivateLink);
                    });
                    
                    modal.on('click', 'button.wfinvoice-model-cancel', function (e) {
                        e.preventDefault();
                        modal.removeClass('modal-active');
                    });

                    modal.on('click', 'input[type="radio"]', function () {
                        var reason_id=$(this).val();
                        var parent = $(this).parents('li:first');
                        var inputType = parent.data('type');
                        modal.find('.reason-input').remove();
                        if($(this).attr('name')=='selected-reason')
                        {
                            modal.find('.sub_reasons').hide();
                        }
                        if(inputType=='main_reason')
                        {
                            modal.find('.sub_reasons[data-parent="'+reason_id+'"]').show();
                            modal.find('.sub_reasons[data-parent="'+reason_id+'"] input[type="radio"]:checked').trigger('click');
                        }else
                        {
                            var inputPlaceholder = parent.data('placeholder'),
                            reasonInputHtml = '<div class="reason-input">' + (('text' === inputType) ? '<input type="text" class="input-text" size="40" />' : '<textarea rows="5" cols="45"></textarea>') + '</div>';

                            if(inputType !== '')
                            {
                                parent.append($(reasonInputHtml));
                                parent.find('input, textarea').attr('placeholder', inputPlaceholder).focus();
                            }
                        }
                    });

                    modal.on('click', 'button.wfinvoice-model-submit', function (e) {
                        e.preventDefault();
                        var button = $(this);
                        if (button.hasClass('disabled')) {
                            return;
                        }
                        
                        var reason_id='none';
                        var reason_info='';
                        var $radio = $('input[type="radio"][name="selected-reason"]:checked', modal);

                        if($radio.length>0)
                        {
                            reason_id=$radio.val();
                            var $selected_reason = $radio.parents('li:first');
                            if($selected_reason.attr('data-type')=="main_reason")
                            {
                                var sub_reason=$selected_reason.find('.sub_reasons');
                                var sub_reason_input=sub_reason.find('input[type="radio"][name="selected-sub-reason"]:checked');
                                if(sub_reason_input.length>0)
                                {
                                    reason_id+=' | '+sub_reason_input.val();
                                    var sub_reason_info_input = sub_reason_input.parents('li:first').find('textarea, input[type="text"]');
                                    if(sub_reason_info_input.length>0)
                                    {
                                        reason_info=$.trim(sub_reason_info_input.val());
                                    }
                                }
                            }else
                            {
                                var reason_info_input=$selected_reason.find('textarea, input[type="text"]');
                                if(reason_info_input.length>0)
                                {
                                    reason_info=$.trim(reason_info_input.val());
                                }
                            }  
                        }
                        // Email validation
                        var emailCheckbox = $('#wt_contact_me_checkbox');
                        var emailField = $('#wt_contact_email');
                        var emailError = $('#wt_email_error');
                        emailError.hide();
                        if (emailCheckbox.is(':checked')) {
                            var emailVal = emailField.val();
                            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                            if (!emailVal || !emailPattern.test(emailVal)) {
                                emailError.text('<?php echo esc_js(__('Please enter a valid email address.', 'print-invoices-packing-slip-labels-for-woocommerce')); ?>').show();
                                emailField.focus();
                                return;
                            }
                        }
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'wfinvoice_submit_uninstall_reason',
                                _wpnonce: '<?php echo esc_js(wp_create_nonce(WF_PKLIST_PLUGIN_NAME));?>',
                                reason_id: reason_id,
                                reason_info: reason_info,
                                user_email: $('#wt_contact_me_checkbox').is(':checked') ? $('#wt_contact_email').val() : ''
                            },
                            beforeSend: function () {
                                button.addClass('disabled');
                                button.text('Processing...');
                            },
                            complete: function () {
                                window.location.href = deactivateLink;
                            }
                        });
                    });

                    $('#wt_contact_me_checkbox').on('change', function() {
                        if ($(this).is(':checked')) {
                            $('#wt_email_field_wrap').slideDown();
                        } else {
                            $('#wt_email_field_wrap').slideUp();
                            $('#wt_contact_email').val('');
                        }
                    });
                });
            }(jQuery));
        </script>
        <?php
    }

    public function send_uninstall_reason()
    {
        global $wpdb;
        $nonce=isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : ''; 
        if(!(wp_verify_nonce($nonce,WF_PKLIST_PLUGIN_NAME)))
        {   
            wp_send_json_error();
        }
        if(!isset($_POST['reason_id']))
        {
            wp_send_json_error();
        }
        
        //$current_user = wp_get_current_user();
        $data = array(
            'reason_id' => sanitize_text_field(wp_unslash($_POST['reason_id'])),
            'plugin' =>$this->plugin_id,
            'auth' =>$this->auth_key,
            'date' => gmdate("M d, Y h:i:s A"),
            'url' => '',
            'user_email' => isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '',
            'reason_info' => isset($_REQUEST['reason_info']) ? trim(stripslashes(sanitize_text_field(wp_unslash($_REQUEST['reason_info'])))) : '',
            'software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '',
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'wp_version' => get_bloginfo('version'),
            'wc_version' => (!class_exists( 'WooCommerce' )) ? '' : WC()->version,
            'locale' => get_locale(),
            'languages' => implode( ",", get_available_languages() ),
            'theme' => wp_get_theme()->get('Name'),
            'multisite' => is_multisite() ? 'Yes' : 'No',
            'wfinvoice_version' =>$this->current_version,
        );
        // Write an action/hook here in webtoffe to recieve the data
        $resp = wp_remote_post($this->api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => false,
            'body' => $data,
            'cookies' => array()
                )
        );
        wp_send_json_success();
    }
}
new Wf_Woocommerce_Packing_List_Uninstall_Feedback();