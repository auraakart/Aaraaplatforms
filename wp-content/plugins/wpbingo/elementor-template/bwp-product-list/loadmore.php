<?php
$widget_id = isset( $widget_id ) ? $widget_id : 'bwp_woo_slider_'.rand().time();
$class_col_lg = ($columns == 5) ? '2-4'  : (12/$columns);
$class_col_md = ($columns1 == 5) ? '2-4'  : (12/$columns1);
$class_col_sm = ($columns2 == 5) ? '2-4'  : (12/$columns2);
$class_col_xs = ($columns3 == 5) ? '2-4'  : (12/$columns3);
$attributes = 'col-xl-'.$class_col_lg .' col-lg-'.$class_col_md .' col-md-'.$class_col_sm .' col-'.$class_col_xs; 
do_action( 'before' ); 
if ( $list -> have_posts() ){ ?>
	<div id="<?php echo $widget_id; ?>" class="bwp-products-loadmore <?php echo esc_attr($widget_class); ?> <?php echo esc_attr($class); ?> <?php if(empty($title1)) echo 'no-title'; ?>"
		data-attributes= "<?php echo esc_attr($attributes); ?>"
		data-orderby= "<?php echo esc_attr($orderby); ?>"
		data-order= "<?php echo esc_attr($order); ?>"
		data-category = "<?php echo esc_attr($category); ?>"
		data-numberposts = "<?php echo esc_attr($numberposts); ?>"
		data-source = "<?php echo esc_attr($source); ?>"
		data-total	= 	"<?php echo esc_attr($total); ?>"
		data-url = "<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>"
		<?php if($style_product > 1) { ?>data-content_product="<?php echo esc_attr($style_product) ?>"<?php } ?>>
		<?php if($title1) { ?>
		<div class="title-block">
			<h2><?php echo esc_html($title1); ?></h2>
			<?php if($description) { ?>
			<div class="page-description"><?php echo $description; ?></div>
			<?php } ?>       
		</div> 
		<?php } ?>         
		<div class="content products-list grid row">	
		<?php while($list->have_posts()): $list->the_post();
			global $product, $post, $wpdb, $average; ?>
			<div class="item-product <?php echo $attributes; ?>">
				<?php if ($style_product == 1) { ?>
					<?php include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product.php'); ?>
				<?php }elseif ($style_product == 2){ ?>
					<?php include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product2.php'); ?>
				<?php }elseif ($style_product == 3){ ?>
					<?php include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product3.php'); ?>
				<?php }elseif ($style_product == 4){ ?>
					<?php include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product4.php'); ?>
				<?php }elseif ($style_product == 5){ ?>
					<?php include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product5.php'); ?>
				<?php } ?>
			</div>
		<?php endwhile; wp_reset_postdata();?>
		</div>
		<?php if($total > $numberposts) : ?>
		<div class="products_loadmore">
			<button type="button" class="btn btn-default loadmore" name="loadmore">
				<i class="fa fa-refresh" aria-hidden="true"></i>
				<span><?php echo esc_html__('Load More', 'wpbingo'); ?></span>
			</button>
			<input type="hidden"  value="2" class="count_loadmore" />
		</div>	
		<?php endif; ?>	
	</div>
	<?php
	}
?>