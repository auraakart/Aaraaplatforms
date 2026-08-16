<?php 
global $product, $woocommerce_loop, $post;
remove_action('woocommerce_after_shop_loop_item', 'econis_quickview', 35 );
add_action('woocommerce_before_shop_loop_item_title', 'econis_quickview', 35 );
?>
<div class="products-entry clearfix product-wapper">
	<div class="row">
		<div class="col-sm-6">
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
			</div>
		</div>
		<div class="col-sm-6">
			<div class="products-content">
				<?php woocommerce_template_loop_rating(); ?>
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
				<div class='product-button'>
					<?php do_action('woocommerce_after_shop_loop_item'); ?>
				</div>
			</div>
		</div>
	</div>
</div>