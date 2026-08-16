<?php
/**
 * The Template for displaying all single posts.
 *
 * @package dokan
 * @package dokan - 2014 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$store_user   = dokan()->vendor->get( get_query_var( 'author' ) );
$store_info   = $store_user->get_shop_info();
$map_location = $store_user->get_location();
get_header( 'shop' );
?>
    <?php do_action( 'woocommerce_before_main_content' ); ?>
	<div class="container">
		<div class="main-archive-product row">
			<div id="dokan-secondary" class="dokan-clearfix dokan-w3 dokan-store-sidebar bwp-sidebar <?php echo esc_attr(econis_get_class()->class_sidebar_left); ?>" role="complementary">
				<?php dynamic_sidebar( 'sidebar-store' ); ?>
			</div><!-- #secondary .widget-area -->
			<div id="dokan-primary" class="dokan-single-store dokan-w8 <?php echo esc_attr(econis_get_class()->class_product_content); ?>">
				<div id="dokan-content" class="store-page-wrap woocommerce" role="main">
					<?php dokan_get_template_part( 'store-header' ); ?>
					<?php do_action( 'dokan_store_profile_frame_after', $store_user->data, $store_info ); ?>
					<?php if ( have_posts() ) { ?>
						<div class="seller-items">
							<?php woocommerce_product_loop_start(); ?>
								<?php while ( have_posts() ) : the_post(); ?>
									<?php wc_get_template_part( 'content', 'product' ); ?>
								<?php endwhile; // end of the loop. ?>
							<?php woocommerce_product_loop_end(); ?>
						</div>
						<?php dokan_content_nav( 'nav-below' ); ?>
					<?php } else { ?>
						<p class="dokan-info"><?php echo esc_html__( 'No products were found of this vendor!', 'econis' ); ?></p>
					<?php } ?>
				</div>
			</div><!-- .dokan-single-store -->
		</div>
	</div>
    <?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer( 'shop' ); ?>
