<?php 
	$list = new \WP_Query( $args ); 
	$product_col_large	= econis_get_config('product_col_large',4);	
	$product_col_medium = econis_get_config('product_col_medium',3);
	$product_col_sm 	= econis_get_config('product_col_sm',1);
	$product_col_xs 	= econis_get_config('product_col_xs',1);
	$limit_bestseller	= econis_get_config('bestseller_limit',9);
	$current_category = get_queried_object();
	$product_visibility_term_ids = wc_get_product_visibility_term_ids();
	$id_category =  is_tax() ? get_queried_object()->term_id : 0;
	if( $id_category != 0 ){
		$args = array(
			'post_type' 			=> 'product',
			'tax_query' => array(
				array(
					'taxonomy'	=> 'product_cat',
					'field'	=> 'slug',
					'terms'		=> $current_category->slug,
					'operator' => 'IN'
				)
			),
			'paged'	=> 1,
			'showposts'				=> $limit_bestseller,
			'meta_key' 		 		=> 'total_sales',
			'orderby' 		 		=> 'meta_value_num'
		);
	}else{
		$args = array(
			'post_type' 			=> 'product',		
			'post_status' 			=> 'publish',
			'showposts'				=> $limit_bestseller,
			'meta_key' 		 		=> 'total_sales',
			'orderby' 		 		=> 'meta_value_num'
		);
	}
	$list = new \WP_Query( $args );
?>
<?php if ( $list -> have_posts() ): ?>
<div class="bestseller-product">
	<div class="title-bestseller"><h2><?php echo esc_html__("Best Seller Items ","econis") ?></h2></div>
	<div class="slick-carousel products-list grid" data-slidestoscroll="true" data-nav="true" data-columns4="<?php echo esc_attr($product_col_xs); ?>" data-columns3="<?php echo esc_attr($product_col_xs); ?>" data-columns2="<?php echo esc_attr($product_col_sm); ?>" data-columns1="<?php echo esc_attr($product_col_medium); ?>" data-columns="<?php echo esc_attr($product_col_large); ?>">	
	<?php while($list->have_posts()): $list->the_post();global $product, $post, $wpdb, $average; ?>
		<div class="item">
			<?php wc_get_template_part( 'content-grid', 'product' ); ?>
		</div>
	<?php endwhile; wp_reset_postdata(); ?>
	</div>
</div>
<?php endif; ?>