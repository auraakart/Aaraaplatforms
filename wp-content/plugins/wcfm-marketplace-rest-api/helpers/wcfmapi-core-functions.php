<?php

if (!function_exists('wcfmapi_woocommerce_inactive_notice')) {
	function wcfmapi_woocommerce_inactive_notice() {
?>
		<div id="message" class="error">
			<p><?php 
				/* translators: 1. Opening strong tag, 2. Closing strong tag, 3. Opening anchor tag linking to WooCommerce Multivendor Marketplace plugin page, 4. Closing anchor tag, 5. Opening anchor tag for installing & activating the plugin, 6. Closing anchor tag */
				printf(esc_html__('%1$sWCfM Rest API is inactive.%2$s The %3$sWooCommerce Multivendor Marketplace%4$s must be active for the WCfM Rest API to work. Please %5$sinstall & activate WooCommerce Multivendor Marketplace%6$s', 'wcfm-marketplace-rest-api'), '<strong>', '</strong>', '<a target="_blank" href="http://wordpress.org/extend/plugins/woocommerce/dc-woocommerce-multi-vendor/">', '</a>', '<a href="' . esc_url(admin_url('plugin-install.php?tab=search&s=wc+multivendor+marketplace')) . '">', '&nbsp;&raquo;</a>'); 
			?></p>
		</div>
<?php
	}
}

add_filter('wcfm_one_signal_tokens', 'wcfm_api_change_onesignal_tokens');
function wcfm_api_change_onesignal_tokens($one_signal_tokens) {
	$one_signal_tokens['rest_api_key'] = "MTcwNTE5NjEtOWM0Zi00NzA4LWI3NDgtYjMwODEyYWMwMTI5";
	return $one_signal_tokens;
}

add_filter('wcfm_one_signal_delivery_tokens', 'wcfm_api_change_onesignal_delivery_tokens');
function wcfm_api_change_onesignal_delivery_tokens($one_signal_tokens) {
	$one_signal_tokens['rest_api_key'] = "M2I0MjBjYTMtZTA0Zi00YWQ3LWIxZWMtMDI1ZTk0NDQ0NGQw";
	return $one_signal_tokens;
}


add_filter('woocommerce_rest_product_object_query', 'prepeare_product_filter', 30, 2);


function prepeare_product_filter($args = array(), $request = array()) {
	// Set new rating filter.
	/*if(isset($request['rating'])) {
  		$product_visibility_terms = wc_get_product_visibility_term_ids();
  		// print_r($product_visibility_terms);
		$args['tax_query'][]	= array(
				'taxonomy'      => 'product_visibility',
				'field'         => 'term_taxonomy_id',
				'terms'         => $product_visibility_terms[ 'rated-' . $request['rating'] ],
				'operator'      => 'IN',
				'rating_filter' => true,
		);
  	}*/

	// Filter by rating.
	if (isset($request['rating_filter'])) { // WPCS: input var ok, CSRF ok.
		$rating_filter = array_filter(array_map('absint', explode(',', $request['rating_filter']))); // WPCS: input var ok, CSRF ok, Sanitization ok.
		// print_r($rating_filter);
		$product_visibility_terms = wc_get_product_visibility_term_ids();
		$rating_terms  = array();
		for ($i = 1; $i <= 5; $i++) {
			if (in_array($i, $rating_filter, true) && isset($product_visibility_terms['rated-' . $i])) {
				$rating_terms[] = $product_visibility_terms['rated-' . $i];
			}
		}
		if (!empty($rating_terms)) {
			$args['tax_query'][] = array(
				'taxonomy'      => 'product_visibility',
				'field'         => 'term_taxonomy_id',
				'terms'         => $rating_terms,
				'operator'      => 'IN',
				'rating_filter' => true,
			);
		}
	}

	return $args;
}
