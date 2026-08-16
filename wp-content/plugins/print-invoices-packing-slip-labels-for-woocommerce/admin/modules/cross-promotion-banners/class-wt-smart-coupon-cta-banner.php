<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * Class Wt_Smart_Coupon_Cta_Banner
 *
 * This class is responsible for displaying the CTA banner on the coupon edit page.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Wt_Smart_Coupon_Cta_Banner
 *
 * @since    4.7.8  This class is responsible for displaying the CTA banner on the coupon edit page.
 */
if ( ! class_exists( 'Wt_Smart_Coupon_Cta_Banner' ) ) {
    class Wt_Smart_Coupon_Cta_Banner {
        /**
         * Constructor.
         */
        public function __construct() { 
            // Check if premium plugin is active
            if (!in_array('wt-smart-coupon-pro/wt-smart-coupon-pro.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
                add_action('add_meta_boxes', array($this, 'add_meta_box'));
                add_action('wp_ajax_wt_dismiss_smart_coupon_cta_banner', array($this, 'dismiss_banner'));
            }
        }

        /**
         * Enqueue required scripts and styles.
         */
        public function enqueue_scripts($hook) { 
           if (!in_array($hook, array('post.php', 'post-new.php')) || get_post_type() !== 'shop_coupon') {
                return;
            }

            wp_enqueue_style(
				'wt-wbte-cta-banner',
				plugin_dir_url( __FILE__ ) . 'assets/css/wbte-cross-promotion-banners.css',
				array(),
                Wbte_Cross_Promotion_Banners::get_banner_version()
            );

            wp_enqueue_script(
				'wt-wbte-cta-banner',
				plugin_dir_url( __FILE__ ) . 'assets/js/wbte-cross-promotion-banners.js',
				array('jquery'),
                Wbte_Cross_Promotion_Banners::get_banner_version(),
                true
            );

            // Localize script with AJAX data
            wp_localize_script('wt-wbte-cta-banner', 'wt_smart_coupon_cta_banner_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wt_dismiss_smart_coupon_cta_banner_nonce'),
                'action' => 'wt_dismiss_smart_coupon_cta_banner'
            ));
        }

        /**
         * Add the meta box to the coupon edit screen
         */
        public function add_meta_box() {
			if ( ! defined( 'WT_SMART_COUPON_DISPLAY_BANNER' ) ) {
				add_meta_box(
					'wt_coupon_import_export_pro',
					'—',
					array( $this, 'render_banner' ),
					'shop_coupon',
					'side',
					'low'
				);
				define( 'WT_SMART_COUPON_DISPLAY_BANNER', true );
			}
		}

        /**
         * Render the banner HTML.
         */
        public function render_banner() {
            // Check if banner should be hidden based on option
            $hide_banner = get_option('wt_hide_smart_coupon_cta_banner', false);
            
            $plugin_url = 'https://www.webtoffee.com/product/smart-coupons-for-woocommerce/?utm_source=free_plugin_cross_promotion&utm_medium=marketing_coupons_tab&utm_campaign=Smart_coupons';
            $wt_admin_img_path = WF_PKLIST_PLUGIN_URL . 'assets/images';
            
            if ($hide_banner) {
                echo '<style>#wt_coupon_import_export_pro { display: none !important; }</style>';
            }
            ?>
            <div class="wt-cta-banner">
                <div class="wt-cta-content">
                    <div class="wt-cta-header">
                        <img src="<?php echo esc_url($wt_admin_img_path . '/smart-coupon.svg'); ?>" alt="<?php esc_attr_e('Smart Coupons for WooCommerce Pro', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>" class="wt-smart-coupon-cta-icon">
                        <h3><?php esc_html_e('Create better coupon campaigns with advanced WooCommerce coupon features', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h3>
                    </div>

                    <div class="wt-cta-features-header">
                        <h2 style="font-size: 13px; font-weight: 700; color: #4750CB;"><?php esc_html_e('Smart Coupons for WooCommerce Pro', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h2>
                    </div>

                    <ul class="wt-cta-features">
                        <li><?php esc_html_e('Auto-apply coupons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Create attractive Buy X Get Y (BOGO) offers', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Create product quantity/subtotal based discounts', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Offer store credits and gift cards', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Set up smart giveaway campaigns', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Set advanced coupon rules and conditions', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Bulk generate coupons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Shipping, purchase history, and payment method-based coupons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Sign up coupons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Cart abandonment coupons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Create day-specific deals', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Display coupon banners and widgets', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Import coupons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                    </ul>

                    <div class="wt-cta-footer">
                        <div class="wt-cta-footer-links">
                            <a href="#" class="wt-cta-toggle" data-show-text="<?php esc_attr_e('View all premium features', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>" data-hide-text="<?php esc_attr_e('Show less', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>"><?php esc_html_e('View all premium features', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
                            <a href="<?php echo esc_url($plugin_url); ?>" class="wt-cta-button" target="_blank"><img src="<?php echo esc_url($wt_admin_img_path . '/promote_crown.png');?>" style="width: 15.01px; height: 10.08px; margin-right: 8px;"><?php esc_html_e('Get the plugin', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
                        </div>
                        <a href="#" class="wt-cta-dismiss" style="display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none;"><?php esc_html_e('Dismiss', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Handle the dismiss action via AJAX
         */
        public function dismiss_banner() {
            // Verify nonce for security
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wt_dismiss_smart_coupon_cta_banner_nonce')) {                wp_send_json_error('Invalid nonce');
            }

            // Check if user has permission
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            // Update the option to hide the banner
            update_option('wt_hide_smart_coupon_cta_banner', true);

            wp_send_json_success('Banner dismissed successfully');
        }
    }

    new Wt_Smart_Coupon_Cta_Banner();
} 
