<?php

//Germanized for WooCommerce by vendidero
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class VIWEC_Plugins_Woo_Germanized {

	public function __construct() {
		if (!class_exists('WC_GZD_Customer_Helper')){
			return;
		}
		add_action('woocommerce_gzd_order_confirmation',[$this,'woocommerce_gzd_order_confirmation'], 10 ,1);
	}
	public function woocommerce_gzd_order_confirmation($order){
		if (function_exists('wc_gzd_remove_all_hooks')){
			wc_gzd_remove_all_hooks( 'woocommerce_get_checkout_order_received_url', 1005 );
		}
	}
}