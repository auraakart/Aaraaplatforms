<?php
/*
Plugin Name: Wpbingo Core
Plugin URI: https://wpbingosite.com/
Description: Use For Wpbingo Theme.
Version: 1.1.3
Author: Wpbingo
Author URI: https://wpbingosite.com/
*/

// don't load directly
if (!defined('ABSPATH'))
    die('-1');

require_once( dirname(__FILE__) . '/function.php');
require_once( dirname(__FILE__) . '/elementor.php');
define('WPBINGO_ELEMENTOR_PATH', dirname(__FILE__) . '/elementor/');
define('WPBINGO_ELEMENTOR_TEMPLATE_PATH', dirname(__FILE__) . '/elementor-template/');
define('WPBINGO_WIDGET_PATH', dirname(__FILE__) . '/widgets/');
define('WPBINGO_WIDGET_TEMPLATE_PATH', dirname(__FILE__) . '/widgets-template/');
define('WPBINGO_CONTENT_TYPES_LIB', dirname(__FILE__) . '/lib/');
class WpbingoShortcodesClass {
    function __construct() {
        // Init plugins
		$this->loadInit();
		add_filter( 'wp_calculate_image_srcset', array( $this, 'bwp_disable_srcset' ) );
		remove_filter('pre_term_description', 'wp_filter_kses');
		load_plugin_textdomain('wpbingo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
	function loadInit() {
		global $woocommerce;
		if ( ! isset( $woocommerce ) || ! function_exists( 'WC' ) ) {
			add_action( 'admin_notices', array( $this, 'bwp_woocommerce_admin_notice' ) );
			return;
		}else{		
			add_action('wp_enqueue_scripts', array( $this, 'bwp_framework_script' ) );	
			require_once(WPBINGO_CONTENT_TYPES_LIB.'settings/save_settings.php');
			$this->bwp_load_file(WPBINGO_WIDGET_PATH);
			$this->bwp_load_file(WPBINGO_CONTENT_TYPES_LIB);
			add_action( 'widgets_init', array( $this, 'register_widgets' ) );
			add_action( 'init',array( $this, 'wpbingo_remove_default_action'));
		}
    }
	function register_widgets(){
		register_widget( 'bwp_recent_post_widget');
		register_widget( 'bwp_ajax_filter_widget' );
	}	
	function wpbingo_remove_default_action(){
		remove_filter( 'woocommerce_product_loop_start', 'woocommerce_maybe_show_product_subcategories' );
		if(class_exists("WeDevs_Dokan")){
			remove_filter( 'woocommerce_get_item_data', 'dokan_product_seller_info', 10 );
		}
	}
	function bwp_load_file($path){
		$files = array_diff(scandir($path), array('..', '.'));
		if(count($files)>0){
			foreach ($files as  $file) {
				if (strpos($file, '.php') !== false)
					require_once($path . $file);
			}
		}		
	}
	function bwp_framework_script(){
		wp_enqueue_script( 'jquery-ui-slider', false, array('jquery'));
		wp_enqueue_script('bwp_wpbingo_js',plugins_url( '/wpbingo/assets/js/wpbingo.js' ),array("jquery"),false,true);
		wp_register_script( 'jquery-cookie', plugins_url( '/wpbingo/assets/js/jquery.cookie.min.js' ), array( 'jquery' ), null, true );
		wp_enqueue_script( 'jquery-cookie' );
		wp_register_script( 'wpbingo-newsletter', plugins_url( '/wpbingo/assets/js/newsletter.js' ), array('jquery','jquery-cookie'), null, true );
		wp_enqueue_script( 'wpbingo-newsletter' );
		wp_register_style( 'bwp_woocommerce_filter_products', plugins_url('/wpbingo/assets/css/bwp_ajax_filter.css') );
		if (!wp_style_is('bwp_woocommerce_filter_products')) {
			wp_enqueue_style('bwp_woocommerce_filter_products');
		}
		wp_register_script('bwp_woocommerce_filter', plugins_url( '/wpbingo/assets/js/filter.js' ), array('jquery'), null, true);	
		wp_localize_script( 'bwp_woocommerce_filter', 'filter_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		wp_enqueue_script('bwp_woocommerce_filter');		
	}
	function bwp_woocommerce_admin_notice(){ ?>
		<div class="error">
			<p><?php echo esc_html__( 'Wpbingo is enabled but not effective. It requires WooCommerce in order to work.', 'wpbingo' ); ?></p>
		</div>
		<?php
	}
	function bwp_disable_srcset( $sources ){	
		return false;	
	}
}
// Finally initialize code
new WpbingoShortcodesClass();

	
	