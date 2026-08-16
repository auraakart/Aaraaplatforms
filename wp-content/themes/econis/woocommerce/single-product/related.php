<?php
/**
 * Related Products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/related.php.
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
 * @version     10.3.0
 */
$layout_shop = econis_get_config('layout_shop','1');
if( econis_get_config( 'product-related' ) == '1' ) :
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly
	}
	global $product, $woocommerce_loop;
	if ( empty( $product ) || ! $product->exists() ) {
		return;
	}
	$related =  wc_get_related_products( $product->get_id() );
	if ( sizeof( $related ) === 0 ) return;
	$args = apply_filters( 'woocommerce_related_products_args', array(
		'post_type'            => 'product',
		'ignore_sticky_posts'  => 1,
		'no_found_rows'        => 1,
		'posts_per_page'       => (int)econis_get_config( 'product-related-count' ),
		'orderby'              => $orderby,
		'post__in'             => $related,
		'post__not_in'         => array( $product->get_id() )
	) );
	$products = new WP_Query( $args );
	$woocommerce_loop['columns'] = 1;
	if ( $products->have_posts() ) : ?>
		<div class="related">
			<div class="title-block"><h2><?php echo esc_html__( 'Related Products', 'econis' ); ?></h2></div>
			<div class="content-product-list">
				<div class="products-list grid slick-carousel" data-nav="true" data-slidestoscroll="true" data-columns4="2" data-columns3="2" data-columns2="2" data-columns1="3" data-columns="<?php echo esc_attr((int)econis_get_config( 'product-related-cols',3 )); ?>">
					<?php while ( $products->have_posts() ) : $products->the_post(); ?>
						<?php wc_get_template_part( 'content-grid', 'product' ); ?>
					<?php endwhile; // end of the loop. ?>
				</div>
			</div>	
		</div>
	<?php endif;
	wp_reset_postdata();
endif;