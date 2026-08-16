<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals,WordPress.Security.ValidatedSanitizedInput
/**
 * Class Wt_P_IEW_Cta_Banner
 *
 * This class is responsible for displaying the CTA banner on the coupon edit page.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Wt_P_IEW_Cta_Banner
 *
 * @since    4.7.8  This class is responsible for displaying the CTA banner on the product edit page.
 */
if ( ! class_exists( 'Wt_P_IEW_Cta_Banner' ) ) {
    class Wt_P_IEW_Cta_Banner {
        /**
         * Constructor.
         */
        public function __construct() {  
            // Check if premium plugin is active
            $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
			if (
				! in_array( 'wt-import-export-for-woo-product/wt-import-export-for-woo-product.php', $active_plugins, true ) &&
				! in_array( 'import-export-suite-for-woocommerce/import-export-suite-for-woocommerce.php', $active_plugins, true )
			) {

				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
				add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
				add_action( 'wp_ajax_wt_dismiss_product_ie_cta_banner', array( $this, 'dismiss_banner' ) );
			}
        }
        /**
         * Enqueue required scripts and styles.
         */
        public function enqueue_scripts($hook) {
            if (!in_array($hook, array('post.php', 'post-new.php')) || get_post_type() !== 'product') {
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
				array( 'jquery' ),
				Wbte_Cross_Promotion_Banners::get_banner_version(),
				true
			);

            // Localize script with AJAX data
			wp_localize_script(
				'wt-wbte-cta-banner',
				'wt_product_ie_cta_banner_ajax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wt_dismiss_product_ie_cta_banner_nonce' ),
					'action'   => 'wt_dismiss_product_ie_cta_banner',
				)
			);
        }

        /**
         * Add the meta box to the product edit screen
         */
        public function add_meta_box() {
			global $wpdb;

			$total_products = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"
                SELECT COUNT(ID)
                FROM {$wpdb->posts}
                WHERE post_type = 'product'
                AND post_status NOT IN ('trash')
            "
			);

			// Show banner if there are 50 or more products.
			if ( ! defined( 'WT_PRODUCT_IMPORT_EXPORT_DISPLAY_BANNER' ) && 50 <= $total_products ) {
				add_meta_box(
					'wt_product_import_export_pro',
					__( 'Product Import Export for WooCommerce', 'print-invoices-packing-slip-labels-for-woocommerce' ),
					array( $this, 'render_banner' ),
					'product',
					'side',
					'low'
				);
				define( 'WT_PRODUCT_IMPORT_EXPORT_DISPLAY_BANNER', true );
			}
		}

        /**
         * Render the banner HTML.
         */
        public function render_banner() {
            // Check if banner should be hidden based on option
            $hide_banner = get_option('wt_hide_product_ie_cta_banner', false);
            
            $plugin_url = 'https://www.webtoffee.com/product/product-import-export-woocommerce/?utm_source=free_plugin_cross_promotion&utm_medium=add_new_product_tab&utm_campaign=Product_import_export';
            $wt_admin_img_path = WF_PKLIST_PLUGIN_URL . 'assets/images';
            
            if ($hide_banner) {
                echo '<style>#wt_product_import_export_pro { display: none !important; }</style>';
                return;
            }
            ?>
            <div class="wt-cta-banner">
                <div class="wt-cta-content">
                    <div class="wt-cta-header">
                        <img src="<?php echo esc_url($wt_admin_img_path . '/product-ie.svg'); ?>" alt="<?php esc_attr_e('Product Import Export', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>" class="wt-cta-icon">
                        <h3><?php esc_html_e('Product Import Export for WooCommerce', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h3>
                    </div>

                    <ul class="wt-cta-features">
                        <li><?php esc_html_e('Import, export, or update WooCommerce products', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Supports all types of products (Simple, variable, subscription grouped, and external)', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Multiple file formats - CSV, XML, Excel, and TSV', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Advanced filters and customizations for better control', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Bulk update WooCommerce product data', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Import via FTP/SFTP and URL', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Schedule automated import & export', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
                        <li class="hidden-feature"><?php esc_html_e('Export and Import custom fields and third-party plugin fields', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></li>
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
            if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'wt_dismiss_product_ie_cta_banner_nonce')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized @codingStandardsIgnoreLine -- This is a safe use of isset.
                wp_send_json_error('Invalid nonce');
            }

            // Check if user has permission
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }

            // Update the option to hide the banner
            update_option('wt_hide_product_ie_cta_banner', true);

            wp_send_json_success('Banner dismissed successfully');
        }
    }

    new Wt_P_IEW_Cta_Banner();
}
