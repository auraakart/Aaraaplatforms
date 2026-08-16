<?php
/**
 * Cross-sells
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cross-sells.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     9.6.0
 */
if( econis_get_config( 'product-crosssells' ) == '1' ) :
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly
	}
	global $product, $woocommerce_loop;
	$crosssells = WC()->cart->get_cross_sells();
	if (sizeof($crosssells) == 0) return;
	$meta_query = WC()->query->get_meta_query();
	$args = array(
		'post_type'           => 'product',
		'ignore_sticky_posts' => 1,
		'no_found_rows'       => 1,
		'posts_per_page'       => (int)econis_get_config( 'product-crosssells-count' ),
		'orderby'             => $orderby,
		'post__not_in'        => $crosssells,
		'meta_query'          => $meta_query
	);	
	$products = new WP_Query( $args );
	$woocommerce_loop['columns'] = $columns;
	if ( $products->have_posts() ) : ?>
		<div class="cross_sell">
			<div class="title-block"><h2><?php echo esc_html__( 'You may be interested in...', 'econis' ); ?></h2></div>
			<div class="content-product-list">
				<div class="products-list grid slick-carousel"  data-columns4="1" data-columns3="2" data-columns2="2" data-columns1="<?php echo esc_attr((int)econis_get_config( 'product-crosssells-cols',3 )); ?>" data-columns="<?php echo esc_attr((int)econis_get_config( 'product-crosssells-cols',3 )); ?>">
					<?php while ( $products->have_posts() ) : $products->the_post(); 
						global $product;
					?>
					<div class="products-entry content-product1 clearfix product-wapper">
						<div class="products-thumb">
							<?php
								/**
								 * woocommerce_before_shop_loop_item_title hook
								 *
								 * @hooked woocommerce_show_product_loop_sale_flash - 10
								 * @hooked woocommerce_template_loop_product_thumbnail - 10
								 */
								do_action( 'woocommerce_before_shop_loop_item_title' );
							?>
							<div class='product-button'>
								<?php do_action('woocommerce_after_shop_loop_item'); ?>
							</div>
						</div>
						<div class="products-content">
							<div class="contents">
								<div class="content-top">
									<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
									<?php woocommerce_template_loop_rating(); ?>
								</div>
								<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
								<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
							</div>
						</div>
					</div>
					<?php endwhile; // end of the loop. ?>
				</div>
			</div>	
		</div>
	<?php endif;
	wp_reset_postdata();
endif;