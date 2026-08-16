<?php
/**
 * The Template for displaying all single products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you (the theme developer).
 * will need to copy the new files to your theme to maintain compatibility. We try to do this.
 * as little as possible, but it does happen. When this occurs the version of the template file will.
 * be bumped and the readme will list any important changes.
 *
 * @see 	    http://docs.woothemes.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
get_header(); ?>
<?php
		/**
		 * woocommerce_before_main_content hook
		 *
		 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
		 * @hooked econis_woocommerce_breadcrumb - 20
		 */
		$product_layout_thumb = econis_get_config("layout-thumbs","zoom");
		$layout_sigle_product = econis_get_config("layout_sigle_product","default");
		do_action( 'woocommerce_before_main_content' );
	?>
<div class="clearfix">
	<div class="contents-detail">
		<div class="main-single-product <?php echo esc_attr($layout_sigle_product); ?>">
			<?php if($layout_sigle_product == 'sidebar' && is_active_sidebar('sidebar-single-product')) { ?>
				<div class="container">
					<div class="row">
						<div class="<?php echo esc_attr(econis_get_class()->class_detail_product_content); ?>">
							<?php while ( have_posts() ) : the_post(); ?>
								<?php wc_get_template_part( 'content', 'single-product' ); ?>
							<?php endwhile; // end of the loop. ?>
						</div>
						<?php if($layout_sigle_product == 'sidebar' && is_active_sidebar('sidebar-single-product')):?>			
							<div class="bwp-sidebar sidebar-single-product <?php echo esc_attr(econis_get_class()->class_sidebar_left); ?>">
								<?php dynamic_sidebar( 'sidebar-single-product' );?>		
							</div>
						<?php endif; ?>
					</div>
				</div>
				<div class="content-single-bottom">
					<?php woocommerce_upsell_display(); ?>
					<?php woocommerce_output_related_products(); ?>
				</div>
			<?php }else{ ?>
				<div class="<?php echo esc_attr(econis_get_class()->class_detail_product_content); ?>">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php wc_get_template_part( 'content', 'single-product' ); ?>
					<?php endwhile; // end of the loop. ?>
				</div>
				<?php
					/**
					 * woocommerce_after_main_content hook
					 *
					 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
					 */
					do_action( 'woocommerce_after_main_content' );
				?>
			<?php } ?>
		</div>
	</div>
</div>
<?php get_footer(); ?>