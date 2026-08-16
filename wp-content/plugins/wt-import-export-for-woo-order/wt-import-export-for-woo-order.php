<?php

/**
 * Order import export
 *
 *
 * @link              https://www.webtoffee.com/
 * @since             1.0.0
 * @package           Wt_Import_Export_For_Woo
 *
 * @wordpress-plugin
 * Plugin Name:       Order Import Export for WooCommerce Add-on
 * Plugin URI:        https://www.webtoffee.com/product/woocommerce-import-export-suite/
 * Description:       Order Import Export Add-on for WooCommerce
 * Version:           1.2.4
 * Author:            WebToffee
 * Author URI:        https://www.webtoffee.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wt-import-export-for-woo
 * Domain Path:       /languages
 * WC tested up to:   8.6.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/* Plugin page links */
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'wt_iew_plugin_action_links_order');

function wt_iew_plugin_action_links_order($links)
{
	if(defined('WT_IEW_PLUGIN_ID')) /* main plugin is available */
	{
		$links[] = '<a href="'.admin_url('admin.php?page='.WT_IEW_PLUGIN_ID).'">'.__('Settings', 'wt-import-export-for-woo').'</a>';
	}

	$links[] = '<a href="https://www.webtoffee.com/import-export-woocommerce-orders/" target="_blank">'.__('Documentation', 'wt-import-export-for-woo').'</a>';
	$links[] = '<a href="https://www.webtoffee.com/support/" target="_blank">'.__('Support', 'wt-import-export-for-woo').'</a>';
	return $links;
}

/**
* Missing plugins warning.
*/
add_action( 'admin_notices',  'wt_missing_plugins_warning');
if(!function_exists('wt_missing_plugins_warning')){
    function wt_missing_plugins_warning() {
        if (!get_option('wt_iew_is_active')) {            
            /* Display the notice*/
            $class = 'notice notice-error';                        
            $message = sprintf(__('The <b>WebToffee Import/Export wrapper plugin</b> should be activated in order to import/export any of the post types supported via <b>WebToffee add-ons(Product/Reviews, User, Order/Coupon/Subscription)</b>.
            Go to <a href="%s" target="_blank">My accounts->API Downloads</a> to download and activate the wrapper.  If already installed, activate the wrapper plugin from under <a href="%s" target="_blank">Plugins</a>.', 'wt-import-export-for-woo'),'https://www.webtoffee.com/my-account/my-api-downloads/',admin_url('plugins.php?s=Import%20Export%20for%20WooCommerce'));
            printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), ( $message ) ); 
                                
        }
    }
}

register_activation_hook( __FILE__, 'wt_missing_plugins_warning_on_activation_order' );
function wt_missing_plugins_warning_on_activation_order() {
    if( !get_option('wt_iew_is_active')){
        set_transient( 'wt_missing_plugins_warning_on_activation_order', true, 5 );
    }
}
add_action( 'admin_notices',  'wt_missing_plugins_warning_order',1);
function wt_missing_plugins_warning_order(){
    /* Check transient, if available display the notice on plugin activation */
    if( get_transient( 'wt_missing_plugins_warning_on_activation_order' ) ){

        $class = 'notice notice-error';  
        $post_type = 'order';
        $message = sprintf(__('<b>%s</b> has been activated. However you need to install and activate the <b>WebToffee wrapper plugin</b> also to start export/import of %s.
        Go to <a href="%s" target="_blank">My accounts->API Downloads</a> to download and activate the wrapper. If already installed activate the wrapper plugin from under <a href="%s" target="_blank">Plugins</a>.', 'wt-import-export-for-woo'), ucfirst($post_type) .' import export', $post_type.'s', 'https://www.webtoffee.com/my-account/my-api-downloads/',admin_url('plugins.php?s=Import%20Export%20for%20WooCommerce'));
        printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), ( $message ) );                     

        /* Delete transient, only display this notice once. */
        delete_transient( 'wt_missing_plugins_warning_on_activation_order' );
    }   
}

/* Checking WC is actived or not */
if(!function_exists('is_plugin_active'))
{
    include_once(ABSPATH.'wp-admin/includes/plugin.php');
}
if(!is_plugin_active('woocommerce/woocommerce.php') || !class_exists( 'WooCommerce' ))
{
    add_action( 'admin_notices', 'wt_wc_missing_warning_order' );
}
if(!function_exists('wt_wc_missing_warning_order')){
    function wt_wc_missing_warning_order(){
        
        $install_url = wp_nonce_url(add_query_arg(array('action' => 'install-plugin','plugin' => 'woocommerce',),admin_url( 'update.php' )),'install-plugin_woocommerce');              
        $class = 'notice notice-error';  
        $post_type = 'order';
        $message = sprintf(__('The <b>WooCommerce</b> plugin must be active for <b>%s Import Export for WooCommerce</b> plugin to work.  Please <a href="%s" target="_blank">install & activate WooCommerce</a>.', 'wt-import-export-for-woo'), ucfirst($post_type), esc_url( $install_url ));
        printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), ( $message ) );   
    }
}

add_action( 'wt_order_addon_help_content', 'wt_order_import_export_help_content' );

function wt_order_import_export_help_content() {
	if ( defined( 'WT_IEW_PLUGIN_ID' ) ) {
		?>
			<li>
				<img src="<?php echo WT_IEW_PLUGIN_URL; ?>assets/images/sample-csv.png">
				<h3><?php _e( 'Sample Order CSV', 'wt-import-export-for-woo'); ?></h3>
				<p><?php _e( 'Familiarize yourself with the sample CSV.', 'wt-import-export-for-woo'); ?></p>
				<a target="_blank" href="https://www.webtoffee.com/wp-content/uploads/2023/04/Sample_Orders.csv" class="button button-primary">
				<?php _e( 'Download', 'wt-import-export-for-woo'); ?>        
				</a>
			</li>
			<li>
				<img src="<?php echo WT_IEW_PLUGIN_URL; ?>assets/images/sample-csv.png">
				<h3><?php _e( 'Sample Coupon CSV', 'wt-import-export-for-woo'); ?></h3>
				<p><?php _e( 'Familiarize yourself with the sample CSV.', 'wt-import-export-for-woo'); ?></p>
				<a target="_blank" href="https://www.webtoffee.com/wp-content/uploads/2023/04/Sample_Coupons.csv" class="button button-primary">
				<?php _e( 'Download', 'wt-import-export-for-woo'); ?>        
				</a>
			</li>
			<li>
				<img src="<?php echo WT_IEW_PLUGIN_URL; ?>assets/images/sample-csv.png">
				<h3><?php _e( 'Sample Subscription CSV', 'wt-import-export-for-woo'); ?></h3>
				<p><?php _e( 'Familiarize yourself with the sample CSV.', 'wt-import-export-for-woo'); ?></p>
				<a target="_blank" href="https://www.webtoffee.com/wp-content/uploads/2023/04/Sample_Subscriptions.csv" class="button button-primary">
				<?php _e( 'Download', 'wt-import-export-for-woo'); ?>        
				</a>
			</li>                        
		<?php
	}
}


define( 'WT_OIEW_VERSION', '1.2.4' );

// Hook to licence manager
add_filter('wt_iew_add_licence_manager', 'wt_oiew_add_licence_manager');

function wt_oiew_add_licence_manager($products)
{
    $plugin_slug='wt-import-export-for-woo-order';
    $settings_url='';
    if(defined('WT_IEW_PLUGIN_ID')) // Main plugin is available
    {
        $settings_url = admin_url('admin.php?page='.WT_IEW_PLUGIN_ID.'#wt-licence');
    }
    $products[$plugin_slug]=array(
        'product_id'            =>  'ordercsvimportexport',
        'product_edd_id'        =>  '203058',
        'plugin_settings_url'   =>  $settings_url,
        'product_version'       =>  WT_OIEW_VERSION,
        'product_name'          =>  plugin_basename(__FILE__),
        'product_slug'          =>  $plugin_slug,
        'product_display_name'  =>  'Order Import Export for WooCommerce', //plugin name, no translation needed
    );
    
    return $products;
}

/**
 * Add Export to CSV link in order listing page near the filter button.
 * 
 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
 */
function wt_ier_export_csv_linkin_order_listing_page($which) {

	$currentScreen = get_current_screen();

	if ( 'edit-shop_order' === $currentScreen->id ) {
		echo '<a target="_blank" href="' . admin_url('admin.php?page=wt_import_export_for_woo_export&wt_to_export=order') . '" class="button" style="height:32px;" >' . __('Export to CSV') . ' </a>';
	}
}

add_filter('manage_posts_extra_tablenav', 'wt_ier_export_csv_linkin_order_listing_page');

// De-activate coupon and subscription addons if active, it's included with order addon.
deactivate_plugins('wt-import-export-for-woo-coupon/wt-import-export-for-woo-coupon.php');
deactivate_plugins('wt-import-export-for-woo-subscription/wt-import-export-for-woo-subscription.php');

// HPOS compatibility decleration
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
