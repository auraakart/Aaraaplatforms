<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'wpbingoMenuScriptsStyles' ) ) {
	class wpbingoMenuScriptsStyles {
		
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 999 );
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		}
		
		function admin_scripts( $hook ) {
			wp_enqueue_style( 'buy-together-backend', plugins_url( 'assets/css/backend.css', __FILE__) );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_register_script( 'buy-together-backend', plugins_url( 'assets/js/backend.js', __FILE__), array(), null );
			wp_localize_script( 'buy-together-backend', 'buy_together',
				array(
					'ajaxurl'            => admin_url( 'admin-ajax.php' ),
					'security'           => wp_create_nonce( 'bwp_backend_nonce' ),
					'editing_product_id' => bwp_get_current_editing_product_id()
				)
			);
			wp_enqueue_script( 'buy-together-backend' );
		}
		
		function frontend_scripts( $hook ) {
			wp_enqueue_style( 'buy-together-frontend', plugins_url( 'assets/css/frontend.css', __FILE__) );
			wp_enqueue_script( 'buy-together-frontend', plugins_url( 'assets/js/frontend.js', __FILE__), array(), null );
			$add_to_cart_text         = esc_html__( 'Add All To Cart', 'wpbingo' );
			$adding_to_cart_text      = esc_html__( 'Adding To Cart...', 'wpbingo' );
			$view_cart_text           = esc_html__( 'View cart', 'wpbingo' );
			$no_product_selected_text = esc_html__( 'You must select at least one product', 'wpbingo' );
			
			$bwp_args = array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'bwp_nonce' ),
				'text'     => array(
					'for_num_of_items'         => esc_html__( 'For {{number}} item(s)', 'wpbingo' ),
					'add_to_cart_text'         => $add_to_cart_text,
					'adding_to_cart_text'      => $adding_to_cart_text,
					'view_cart'                => $view_cart_text,
					'no_product_selected_text' => $no_product_selected_text,
					'add_to_cart_success'      => esc_html__( '{{number}} product(s) was successfully added to your cart.', 'wpbingo' ),
					'add_to_cart_fail_single'  => esc_html__( 'One product is out of stock.', 'wpbingo' ),
					'add_to_cart_fail_plural'  => esc_html__( '{{number}} products were out of stocks.', 'wpbingo' )
				)
			);
			if ( class_exists( 'WooCommerce' ) ) {
				$bwp_args['price_format']             = get_woocommerce_price_format();
				$bwp_args['price_decimals']           = wc_get_price_decimals();
				$bwp_args['price_thousand_separator'] = wc_get_price_thousand_separator();
				$bwp_args['price_decimal_separator']  = wc_get_price_decimal_separator();
				$bwp_args['currency_symbol']          = get_woocommerce_currency_symbol();
				$bwp_args['wc_tax_enabled']           = wc_tax_enabled();
				$bwp_args['cart_url']                 = wc_get_cart_url();
				if ( wc_tax_enabled() ) {
					$bwp_args['ex_tax_or_vat'] = WC()->countries->ex_tax_or_vat();
				} else {
					$bwp_args['ex_tax_or_vat'] = '';
				}
			}
			wp_localize_script( 'buy-together-frontend', 'buy_together', $bwp_args );
		}
	}
	new wpbingoMenuScriptsStyles();
}