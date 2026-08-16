<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * Override this template by copying it to yourtheme/woocommerce/content-single-product.php
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 */
global $product; 
?>
<div id="quickview-container-<?php the_ID(); ?>">
	<div class="quickview-container woocommerce">
		<a href="#" class="quickview-close" data-product_id="<?php echo the_ID(); ?>"></a>
		<?php
        global $product;
            /**
             * woocommerce_before_single_product hook
             *
             * @hooked woocommerce_show_messages - 10
             */
             do_action( 'woocommerce_before_single_product' );
        ?>
        <div itemscope itemtype="http://schema.org/Product" id="product-<?php echo the_ID(); ?>" <?php post_class("product single-product"); ?>>
			<div class="product_detail">
				<div class="row">
					<div class="img-quickview">							
						<div class="slider_img_productd">
							<!-- woocommerce_show_product_images -->
							<?php
								/**
								 * woocommerce_show_product_images hook
								 *
								 * @hooked woocommerce_show_product_sale_flash - 10
								 * @hooked woocommerce_show_product_images - 20
								 */
								wc_get_template( 'single-product/thumbnails-image/quickview.php' );
							?>
						</div>							
					</div>
					<div class="bwp-single-info">
						<div class="content_product_detail entry-summary">
							<!-- woocommerce_template_single_title - 5 -->
							<!-- woocommerce_template_single_rating - 10 -->
							<!-- woocommerce_template_single_price - 20 -->
							<!-- woocommerce_template_single_excerpt - 30 -->
							<!-- woocommerce_template_single_add_to_cart 40 -->
							<?php
								/**
								 * woocommerce_single_product_summary hook
								 *
								 * @hooked woocommerce_template_single_title - 5
								 * @hooked woocommerce_template_single_price - 10
								 * @hooked woocommerce_template_single_excerpt - 20
								 * @hooked woocommerce_template_single_add_to_cart - 30
								 * @hooked woocommerce_template_single_meta - 40
								 * @hooked woocommerce_template_single_sharing - 50
								 */
								 $product_short_desc = econis_get_config('product-short-desc',true);
								 remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
								 if($product_short_desc){
									add_action( 'woocommerce_single_product_summary', 'econis_quickview_short_desc', 15 );
								}
								do_action( 'woocommerce_single_product_summary' );
							?>
						</div>
					</div>
				</div>
			</div><!-- .summary -->
		</div>
        <?php do_action( 'woocommerce_after_single_product' ); ?>
        <div class="clearfix"></div>
    </div>
</div>