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
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
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
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
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
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
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
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
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
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
				<div class="btn-atc">
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
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
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
				<div itemprop="description" class="description">
					<?php echo apply_filters( 'woocommerce_short_description', wp_kses( $post->post_excerpt,'social' ) ) ?>
				</div>
				<h3 class="product-title"><a href="<?php esc_url(the_permalink()); ?>"><?php esc_html(the_title()); ?></a></h3>
				<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>
				<?php woocommerce_template_loop_rating(); ?>
			</div>
		</div>
	</div>
<?php } ?>