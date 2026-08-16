<?php

namespace Wtpdf\Banners;

if (!defined('ABSPATH')) {
    exit;
}

class Wt_Mpdf_Language_Banner {
    private $dismiss_option_key = 'wt_pklist_mpdf_banner_dismissed_';
    private $mpdf_plugin_slug = 'mpdf-addon-for-pdf-invoices/wt-woocommerce-packing-list-mpdf.php';
    private $mpdf_url = 'https://wordpress.org/plugins/mpdf-addon-for-pdf-invoices/';

    public function __construct() {
        add_action('admin_notices', array($this, 'maybe_show_banner'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_notice_styles'));
        add_action('wp_ajax_wt_pklist_dismiss_mpdf_banner', array($this, 'dismiss_banner'));
    }

    public function enqueue_notice_styles() {
        // Enqueue dashicons for info icon if not already present
        wp_enqueue_style('dashicons');
    }

    public function maybe_show_banner() {
        if (!$this->should_show_banner()) {
            return;
        }
        $lang_info = $this->get_current_language_info();
        $is_rtl = $lang_info['is_rtl'];
        $lang_name = $lang_info['name'];
        $banner_class = $is_rtl ? 'notice notice-info is-dismissible wt-mpdf-language-banner-rtl' : 'notice notice-info is-dismissible';
        ?>
        <div class="<?php echo esc_attr($banner_class); ?>" id="wt-mpdf-language-banner" style="display: flex; align-items: flex-start;<?php echo $is_rtl ? ' direction: rtl;' : ''; ?> padding: 10px 0 10px 0;">
            <span class="dashicons dashicons-info" style="font-size: 24px; color: #2271b1; margin: 4px 15px 4px 15px;<?php echo $is_rtl ? 'margin: 4px 10px 10px 16px;' : ''; ?>"></span>
            <div style="flex:1; min-width:0;">
                <strong style="font-size:16px; color:#2271b1;"><?php esc_html_e('Language Detection Notice', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></strong><br>
                <?php printf(
                    /* translators: %s: Detected language name */
                    esc_html__('Your site language is detected as %s. For better compatibility with your language we recommend installing the mPDF add-on.', 'print-invoices-packing-slip-labels-for-woocommerce'),
                    '<b>' . esc_html($lang_name) . '</b>'
                ); ?>
                <a class="wt-mpdf-banner-link" href="<?php echo esc_url($this->mpdf_url); ?>" target="_blank" style="color: #2271b1; text-decoration: underline; margin-left: 4px;">
                    <?php esc_html_e('You may install the free mPDF add-on by clicking here.', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>
                </a>
            </div>
            <button type="button" class="notice-dismiss" onclick="wtPklistDismissMpdfBanner()" aria-label="<?php esc_attr_e('Dismiss this notice', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>"></button>
        </div>
        <script type="text/javascript">
        function wtPklistDismissMpdfBanner() {
            var banner = document.getElementById('wt-mpdf-language-banner');
            if (banner) banner.style.display = 'none';
            var data = { action: 'wt_pklist_dismiss_mpdf_banner', _ajax_nonce: '<?php echo esc_js(wp_create_nonce('wt_pklist_dismiss_mpdf_banner')); ?>' };
            jQuery.post(ajaxurl, data);
        }
        </script>
        <?php
    }

    private function should_show_banner() {
        if (!current_user_can('manage_options')) return false;
        
        $allowed_pages = array(
            'wf_woocommerce_packing_list',
            'wf_woocommerce_packing_list_invoice',
            'wf_woocommerce_packing_list_packinglist',
            'wf_woocommerce_packing_list_deliverynote',
            'wf_woocommerce_packing_list_shippinglabel',
            'wf_woocommerce_packing_list_dispatchlabel'
        );
        
        if (!isset($_GET['page']) || !in_array($_GET['page'], $allowed_pages)) return false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended @codingStandardsIgnoreLine -- This is a safe use of isset.
        
        $user_id = get_current_user_id();
        if (get_user_meta($user_id, $this->dismiss_option_key . $user_id, true)) return false;
        if (\Wf_Woocommerce_Packing_List_Admin::check_if_mpdf_used()) return false;
        if (is_plugin_active($this->mpdf_plugin_slug)) return false;
        $lang_info = $this->get_current_language_info();
        // Show for RTL or charactered languages
        if ($lang_info['is_rtl'] || $lang_info['is_charactered']) return true;
        return false;
    }

    public function dismiss_banner() {
        check_ajax_referer('wt_pklist_dismiss_mpdf_banner');
        $user_id = get_current_user_id();
        update_user_meta($user_id, $this->dismiss_option_key . $user_id, 1);
        wp_die();
    }

    private function get_current_language_info() {
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        } else {
            $locale = get_locale();
        }
        $lang_list = \Wf_Woocommerce_Packing_List_Admin::get_language_list();
        $lang_info = array('name' => $locale, 'is_rtl' => is_rtl(), 'is_charactered' => false);
        if (isset($lang_list[$locale])) {
            $lang_info['name'] = $lang_list[$locale]['name'];
            $lang_info['is_rtl'] = !empty($lang_list[$locale]['is_rtl']);
            // Charactered: check for CJK, Arabic, etc.
            $native = $lang_list[$locale]['native_name'];
            if (preg_match('/[\x{4e00}-\x{9fff}\x{3040}-\x{30ff}\x{ac00}-\x{d7af}\x{0600}-\x{06ff}\x{0590}-\x{05ff}]/u', $native)) {
                $lang_info['is_charactered'] = true;
            }
        }
        return $lang_info;
    }
}

new \Wtpdf\Banners\Wt_Mpdf_Language_Banner(); 