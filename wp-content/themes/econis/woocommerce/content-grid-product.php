<?php
	global $product, $woocommerce_loop, $post;
	$econis_settings = econis_global_settings();
	$stock = ( $product->is_in_stock() )? 'in-stock' : 'out-stock' ;
	if(!isset($layout_shop)){
		$layout_shop = econis_get_config('layout_shop','1');
	}	
?>
<?php if ($layout_shop == '1') { ?>
	<?php remove_action('woocommerce_after_shop_loop_item', 'econis_add_loop_wishlist_link', 20 ); ?>
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
				if(isset($econis_settings['product-wishlist']) && $econis_settings['product-wishlist'] && class_exists( 'WPCleverWoosw' ) ){
					econis_add_loop_wishlist_link();
				}
			?>
			<div class='product-button'>
				<?php do_action('woocommerce_after_shop_loop_item'); ?>
			</div>
			<?php if($stock == "out-stock"): ?>
				<div class="product-stock">    
					<span class="stock"><?php echo esc_html__( 'Out Of Stock', 'econis' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="products-content">
			<div class="contents">
				<?php woocommerce_template_loop_rating(); ?>
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
			</div>
		</div>
	</div>
<?php }elseif ($layout_shop == '2') { ?>
	<?php
	remove_action('woocommerce_after_shop_loop_item', 'econis_add_loop_wishlist_link', 20 );
	remove_action('woocommerce_after_shop_loop_item', 'econis_woocommerce_template_loop_add_to_cart', 15 );
	?>
	<div class="products-entry content-product2 clearfix product-wapper">
		<div class="products-thumb">
			<?php
				/**
				 * woocommerce_before_shop_loop_item_title hook
				 *
				 * @hooked woocommerce_show_product_loop_sale_flash - 10
				 * @hooked woocommerce_template_loop_product_thumbnail - 10
				 */
				do_action( 'woocommerce_before_shop_loop_item_title' );
				if(isset($econis_settings['product-wishlist']) && $econis_settings['product-wishlist'] && class_exists( 'WPCleverWoosw' ) ){
					econis_add_loop_wishlist_link();
				}
			?>
			<div class='product-button'>
				<?php do_action('woocommerce_after_shop_loop_item'); ?>
			</div>
			<?php if($stock == "out-stock"): ?>
				<div class="product-stock">    
					<span class="stock"><?php echo esc_html__( 'Out Of Stock', 'econis' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="products-content">
			<div class="contents">
				<?php woocommerce_template_loop_rating(); ?>
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
				<div class="btn-atc">
					<?php econis_woocommerce_template_loop_add_to_cart(); ?>
				</div>
			</div>
		</div>
	</div>
<?php }elseif ($layout_shop == '3') { ?>
	<?php
	remove_action('woocommerce_before_shop_loop_item_title', 'econis_add_countdownt_item', 15 );
	?>
	<div class="products-entry content-product3 clearfix product-wapper">
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
		<div class="products-content">
			<div class="contents">
				<?php woocommerce_template_loop_rating(); ?>
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
			</div>
		</div>
	</div>
<?php }elseif ($layout_shop == '4') { ?>
	<div class="products-entry content-product4 clearfix product-wapper">
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
			<?php if($stock == "out-stock"): ?>
				<div class="product-stock">    
					<span class="stock"><?php echo esc_html__( 'Out Of Stock', 'econis' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="products-content">
			<div class="contents">
				<?php woocommerce_template_loop_rating(); ?>
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
			</div>
		</div>
	</div>
<?php }elseif ($layout_shop == '5') { ?>
	<?php
	remove_action('woocommerce_after_shop_loop_item', 'econis_add_loop_wishlist_link', 20 );
	remove_action('woocommerce_after_shop_loop_item', 'econis_woocommerce_template_loop_add_to_cart', 15 );
	?>
	<div class="products-entry content-product5 clearfix product-wapper">
		<div class="products-thumb">
			<?php
				/**
				 * woocommerce_before_shop_loop_item_title hook
				 *
				 * @hooked woocommerce_show_product_loop_sale_flash - 10
				 * @hooked woocommerce_template_loop_product_thumbnail - 10
				 */
				do_action( 'woocommerce_before_shop_loop_item_title' );
				if(isset($econis_settings['product-wishlist']) && $econis_settings['product-wishlist'] && class_exists( 'WPCleverWoosw' ) ){
					econis_add_loop_wishlist_link();
				}
			?>
			<div class='product-button'>
				<?php do_action('woocommerce_after_shop_loop_item'); ?>
			</div>
			<?php if($stock == "out-stock"): ?>
				<div class="product-stock">    
					<span class="stock"><?php echo esc_html__( 'Out Of Stock', 'econis' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="products-content">
			<div class="contents">
				<?php woocommerce_template_loop_rating(); ?>
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
				<div class="btn-atc">
<style>
.subscribe_button {
    padding: 12px 25px;
    background: #000;
    color: #fff;
    border-radius: 20px;
    -webkit-border-radius: 20px;
    -moz-border-radius: 20px;
    -ms-border-radius: 20px;
    -o-border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.subscribe_button:hover {
    background: #000;
    color: #fff;
}
@media only screen and (max-width: 600px) {
    .subscribe_button {
        padding: 5px 10px;
        background: #000;
        color: #fff;
        border-radius: 20px;
        -webkit-border-radius: 20px;
        -moz-border-radius: 20px;
        -ms-border-radius: 20px;
        -o-border-radius: 20px;
        font-size: 12px;
    }
}
</style>
<?php

global $product;

$subscription_plans = [];

$schemes = get_post_meta($product->get_id(), '_wcsatt_schemes', true);

if (!empty($schemes) && is_array($schemes)) {

    foreach ($schemes as $scheme) {

        $subscription_plans[] = [
            'interval'        => (int) $scheme['subscription_period_interval'],
            'period'          => $scheme['subscription_period'],
            'length'          => (int) $scheme['subscription_length'],
            'pricing_method'  => $scheme['subscription_pricing_method'],
            'regular_price'   => $scheme['subscription_regular_price'],
            'sale_price'      => $scheme['subscription_sale_price'],
            'price'           => $scheme['subscription_price'],
            'discount'        => $scheme['subscription_discount'],
            'advance_amount'  => (float) $scheme['subscription_advance_amount'],
        ];
    }
}

if(!empty($subscription_plans)){
?>
				    <div data-title="Subscribe" style="margin-right:15px;margin-top: 10px;" >
				        <a rel="nofollow" href="<?php esc_url(the_permalink()); ?>" class="subscribe_button" >Subscribe</a>
				    </div>
				    <?php } ?>
					<?php econis_woocommerce_template_loop_add_to_cart(); ?>
					
				</div>
			</div>
		</div>
	</div>
<?php }elseif ($layout_shop == '6') { ?>
	<div class="products-entry content-product6 clearfix product-wapper">
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
			<?php if($stock == "out-stock"): ?>
				<div class="product-stock">    
					<span class="stock"><?php echo esc_html__( 'Out Of Stock', 'econis' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="products-content">
			<div class="contents">
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
				<?php woocommerce_template_loop_rating(); ?>
			</div>
		</div>
	</div>
<?php }elseif ($layout_shop == '7') { ?>
	<div class="products-entry content-product7 clearfix product-wapper">
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
			<?php if($stock == "out-stock"): ?>
				<div class="product-stock">    
					<span class="stock"><?php echo esc_html__( 'Out Of Stock', 'econis' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="products-content">
			<div class="contents">
				<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
				<?php woocommerce_template_loop_rating(); ?>
			</div>
		</div>
	</div>
<?php } ?>