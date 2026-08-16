<?php   
if( $category && $category ){
	$arr_category = $category ;
	$category = implode( ',', $category );
	$numberposts = (int)$numberposts;
	$count_loadmore = ceil($numberposts/$columns);
	$class_col_lg = ($columns == 5) ? '2-4'  : (12/$columns);
	$class_col_md = ($columns1 == 5) ? '2-4'  : (12/$columns1);
	$class_col_sm = ($columns2 == 5) ? '2-4'  : (12/$columns2);
	$class_col_xs = ($columns3 == 5) ? '2-4'  : (12/$columns3);
	$class_col = 'col-xl-'.$class_col_lg .' col-lg-'.$class_col_md .' col-md-'.$class_col_sm .' col-'.$class_col_xs; 	
	$cat_selected = '';
	$catalog_orderby_options = apply_filters( 'woocommerce_catalog_orderby', array(
		'menu_order' => esc_html__( 'Default sorting', 'wpbingo' ),
		'popularity' => esc_html__( 'Sort by popularity', 'wpbingo' ),
		'rating'     => esc_html__( 'Sort by average rating', 'wpbingo' ),
		'date'       => esc_html__( 'Sort by newness', 'wpbingo' ),
		'price'      => esc_html__( 'Sort by price: low to high', 'wpbingo' ),
		'price-desc' => esc_html__( 'Sort by price: high to low', 'wpbingo' ),
	));	
	$meta_query	= array();
	if (in_array("all", $arr_category)){
		$tax_query = array();		
	}else{
		$tax_query = array(
			array(
				'taxonomy'      => 'product_cat',
				'field' 		=> 'term_id', //This is optional, as it defaults to 'term_id'
				'terms'         => $category,
				'operator'      => 'IN' // Possible values are 'IN', 'NOT IN', 'AND'.
			)
		);
	}
	$prices = get_filtered_homepage_price($meta_query,$tax_query);
	$default_min_price    = floor( $prices->min_price );
	$default_max_price    = ceil( $prices->max_price );	
	$attribute_taxonomies = wc_get_attribute_taxonomies();	
	if($attribute_taxonomies){
		$atributes = array();
		foreach( $attribute_taxonomies as $att ){
			$atributes[] = 'pa_'.$att->attribute_name;
		}			
		$atributes = implode(',',$atributes);
	}else
		$atributes = '';
?>
<div class="bwp-filter-homepage <?php echo esc_attr($class); ?> filter-<?php echo esc_attr($layout); ?>" data-class_col = "<?php echo esc_attr($class_col); ?>" data-numberposts = "<?php echo esc_attr($numberposts); ?>" data-atributes="<?php echo esc_attr($atributes); ?>">
	<div class="bwp-filter-heading">
		<div class="filter-content">
			<a class="bwp-filter-toggle"><i class="icon_adjust-horiz"></i><?php echo  esc_html__( 'Filter', 'wpbingo' ); ?></a>	
		</div>
		<ul class="filter-category">
		<?php
			foreach($arr_category as $key => $cat){	?>
					<?php if($cat == 'all'){?>
						<li class="<?php if( ( $key + 1 ) == 1 ){echo 'active'; $cat_selected = $cat;}?>" data-value="<?php echo esc_attr($cat); ?>">
							<span><?php echo esc_html__( "All products", 'wpbingo'); ?></span>
						</li>
					<?php }else{?>
						<?php 
						$terms = get_term_by('slug', $cat, 'product_cat');		
						if($terms) : ?>
						<li class="<?php if( ( $key + 1 ) == 1 ){echo 'active'; $cat_selected = $cat;}?>" data-value="<?php echo esc_attr($cat); ?>">
							<span><?php echo $terms->name; ?></span>
							<div class="item-count">
								<?php if($terms->count == 1){?>
									<?php echo esc_attr($terms->count); ?>
								<?php }else{ ?>
									<?php echo esc_attr($terms->count); ?>
								<?php } ?>
							</div>
						</li>	
						<?php endif; ?>		
					<?php }?>			
			<?php } ?>
		</ul>	
	</div>
	<div class="bwp-filter-attribute">
		<div class="bwp-filter-attribute-inner">
			<?php woocommerce_filter_homepage_price($default_min_price,$default_max_price); ?>
			<?php woocommerce_filter_homepage_atribute(); ?>	
			<?php woocommerce_filter_homepage_brand(); ?>	
		</div>
		<div class="clear_all"><span><?php echo  esc_html__( 'Clear All', 'wpbingo' ); ?></span></div>	
	</div>
	<div class="bwp-filter-content">
		<?php
		$args  = array(
			'post_type' 			=> 'product',
			'post_status' 			=> 'publish',
			'ignore_sticky_posts'   => 1,
			'tax_query'	=> array(
					array(
						'taxonomy'	=> 'product_cat',
						'field'		=> 'slug',
						'terms'		=> $cat_selected
					)
				),
			'posts_per_page' 		=> $numberposts,
		);

		$orderby = '';
		$order = '';
		$orderby_value = apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
		$orderby_value = explode( '-', $orderby_value );
		$orderby       = esc_attr( $orderby_value[0] );
		$order         = ! empty( $orderby_value[1] ) ? $orderby_value[1] : $order;
		$orderby = strtolower( $orderby );
		$order   = strtoupper( $order );
		// default - menu_order
		$args['orderby']  = 'title';
		$args['order']    = $order == 'DESC' ? 'DESC' : 'ASC';
		$args['meta_key'] = '';
		
		switch ( $orderby ) {
			case 'rand' :
				$args['orderby']  = 'rand';
			break;
			case 'date' :
				$args['orderby']  = 'date ID';
				$args['order']    = $order == 'ASC' ? 'ASC' : 'DESC';
			break;
			case 'price' :
				$args['orderby']  = "meta_value_num ID";
				$args['order']    = $order == 'DESC' ? 'DESC' : 'ASC';
				$args['meta_key'] = '_price';
			break;
			case 'rating':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating';
				$args['order'] = 'desc';
				break;

			case 'popularity':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = 'total_sales';
				$args['order'] = 'desc';
				break;
			case 'title' :
				$args['orderby']  = 'title';
				$args['order']    = $order == 'DESC' ? 'DESC' : 'ASC';
			break;
		}
		
		if($cat_selected == 'all')
			unset($args['tax_query']);	
		$list = new WP_Query( $args );
		
		$args['posts_per_page'] = -1;
		$list_total = new WP_Query( $args );
		$total = $list_total->post_count;
		?>
		<div class="content products-list grid row">
			<?php while($list->have_posts()): $list->the_post();
				global $product, $post, $wpdb, $average; ?>
				<div class="item-product <?php echo $class_col; ?>">
					<?php include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product.php'); ?>
				</div>
			<?php endwhile; wp_reset_postdata();?>
		</div>
	</div>
	<div class="products_loadmore" <?php if($numberposts >= $total) echo 'style="display:none;"' ?>>
		<button type="button" class="btn btn-default loadmore" name="loadmore">
			<i class="fa fa-refresh" aria-hidden="true"></i>
			<span><?php echo esc_html__('Load more', 'wpbingo'); ?></span>
		</button>
		<input type="hidden"  data-default = "<?php echo esc_attr($count_loadmore + 1); ?>" value="<?php echo esc_attr($count_loadmore + 1); ?>" class="count_loadmore" />
	</div>	
</div>
<?php } ?>