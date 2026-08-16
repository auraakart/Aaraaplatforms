<?php 
if ( !class_exists('Woocommerce') ) { 
	return false;
}
global $woocommerce;
$econis_settings = econis_global_settings();
$cart_layout = econis_get_config('cart-layout','dropdown');
?>
<div class="dropdown mini-cart top-cart" data-text_added="<?php echo esc_html__("Product was added to cart successfully!","econis"); ?>">
	<div class="remove-cart-shadow"></div>
  <a class="dropdown-toggle cart-icon" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	<div class="icons-cart"><i class="icon-bag"></i><span class="cart-count"><?php echo esc_attr($woocommerce->cart->cart_contents_count); ?></span></div>
  </a>
  <div class="dropdown-menu cart-popup" aria-labelledby="dropdownMenuLink">
	<div class="remove-cart">
		<a class="dropdown-toggle cart-remove" data-toggle="dropdown" data-hover="dropdown" data-delay="0" href="#" title="<?php esc_attr_e('View your shopping cart', 'econis'); ?>">
			<?php echo esc_html__("Close","econis") ?><i class="icon_close"></i>
		</a>
	</div>
	<div class="top-total-cart"><?php echo esc_html__("Shopping Cart","econis"); ?>(<?php echo esc_attr($woocommerce->cart->cart_contents_count); ?>)</div>
	<?php woocommerce_mini_cart(); ?>
  </div>
</div>