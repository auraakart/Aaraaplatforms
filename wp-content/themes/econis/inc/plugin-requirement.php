<?php
/***** Active Plugin ********/
add_action( 'tgmpa_register', 'econis_register_required_plugins' );
function econis_register_required_plugins() {
    $plugins = array(
		array(
            'name'               => esc_html__('Woocommerce', 'econis'), 
            'slug'               => 'woocommerce', 
            'required'           => false
        ),
		array(
            'name'      		 => esc_html__('Elementor', 'econis'),
            'slug'     			 => 'elementor',
            'required' 			 => false
        ),		
		array(
            'name'               => esc_html__('Revolution Slider', 'econis'), 
			'slug'               => 'revslider',
			'source'             => get_template_directory() . '/plugins/revslider.zip', 
			'required'           => true, 
        ),
		array(
            'name'               => esc_html__('Wpbingo Core', 'econis'), 
            'slug'               => 'wpbingo', 
            'source'             => get_template_directory() . '/plugins/wpbingo.zip',
            'required'           => true, 
        ),			
		array(
            'name'               => esc_html__('Redux Framework', 'econis'), 
            'slug'               => 'redux-framework', 
            'required'           => false
        ),			
		array(
            'name'      		 => esc_html__('Contact Form 7', 'econis'),
            'slug'     			 => 'contact-form-7',
            'required' 			 => false
        ),	
		array(
            'name'     			 => esc_html__('WPC Smart Wishlist for WooCommerce', 'econis'),
            'slug'      		 => 'woo-smart-wishlist',
            'required' 			 => false
        ),
		array(
            'name'     			 => esc_html__('WooCommerce Variation Swatches', 'econis'),
            'slug'      		 => 'variation-swatches-for-woocommerce',
            'required' 			 => false
        ),
		array(
            'name'      		 => esc_html__('WPC Smart Compare for WooCommerce', 'econis'),
            'slug'      		 => 'woo-smart-compare',
            'required'			 => false
        ),
		array(
            'name'     			 => esc_html__('Dokan', 'econis'),
            'slug'      		 => 'dokan-lite',
            'required' 			 => false
        ),		
        array(
            'name'     => esc_html__('Wpbingo AI Commander for WooCommerce', 'econis'),
            'slug'     => 'wpbingo-ai-commander-for-woocommerce',
            'required' => false
        ),
    );
    $config = array();
    tgmpa( $plugins, $config );
}