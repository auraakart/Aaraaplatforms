<?php 
	$query_string = econis_get_query_string();
	parse_str($query_string, $params);
	$category_slug = isset( $params['product_cat'] ) ? $params['product_cat'] : '';
	$terms =	get_terms( 'product_cat', 
	array(  
		'hide_empty' => true,	
		'parent' => 0	
	));
	$class_ajax_search 	= "";	 
	$ajax_search 		= econis_get_config('show-ajax-search',false);
	$limit_ajax_search 		= econis_get_config('limit-ajax-search',5);
	if($ajax_search){
		$class_ajax_search = "ajax-search";
	}
?>
<form role="search" method="get" class="search-from <?php echo esc_attr($class_ajax_search); ?>" action="<?php echo esc_url(home_url( '/' )); ?>" data-admin="<?php echo admin_url( 'admin-ajax.php', 'econis' ); ?>" data-noresult="<?php echo esc_html__("No Result","econis") ; ?>" data-limit="<?php echo esc_attr($limit_ajax_search); ?>">
	<div class="search-box">
		<input type="text" value="<?php echo get_search_query(); ?>" name="s" id="ss" class="input-search s" placeholder="<?php echo esc_attr__( 'Search product...', 'econis' ); ?>" />
		<div class="result-search-products-content">
			<ul class="result-search-products">
			</ul>
		</div>
	</div>
	<input type="hidden" name="post_type" value="product" />
	<button id="searchsubmit2" class="btn" type="submit">
		<span class="search-icon">
			<i class="icon-search"></i>
		</span>
		<span><?php echo esc_html__('search','econis'); ?></span>
	</button>
</form>