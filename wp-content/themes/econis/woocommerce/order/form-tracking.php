<?php
/**
 * Order tracking form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/form-tracking.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;
global $woocommerce,$post;
?>
<div class="woocommerce-page-header">
	<ul>
		<li class="shopping-cart-link">
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html__("Shopping Cart","econis"); ?><span class="cart-count">(<?php echo esc_attr($woocommerce->cart->cart_contents_count); ?>)</span></a>
		</li>
		<li class="checkout-link"><a href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php echo esc_html__("Checkout","econis"); ?></a></li>
		<?php if (get_page_by_path('order-tracking')) { ?>
			<li class="order-tracking-link active"><a href="<?php echo get_permalink( get_page_by_path( 'order-tracking' ) ); ?>"><?php echo esc_html__("Order Tracking","econis"); ?></a></li>
		<?php } ?>
	</ul>
</div>
<form action="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" method="post" class="woocommerce-form woocommerce-form-track-order track_order">

	<p><?php echo esc_html__( 'To track your order please enter your Order ID in the box below and press the "Track" button. This was given to you on your receipt and in the confirmation email you should have received.', 'econis' ); ?></p>

	<p class="form-row form-row-first"><label for="orderid"><?php echo esc_html__( 'Order ID', 'econis' ); ?></label> <input class="input-text" type="text" name="orderid" id="orderid" value="<?php echo isset( $_REQUEST['orderid'] ) ? esc_attr( wp_unslash( $_REQUEST['orderid'] ) ) : ''; ?>" placeholder="<?php echo esc_attr__( 'Found in your order confirmation email.', 'econis' ); ?>" /></p><?php // @codingStandardsIgnoreLine ?>
	<p class="form-row form-row-last"><label for="order_email"><?php echo esc_html__( 'Billing email', 'econis' ); ?></label> <input class="input-text" type="text" name="order_email" id="order_email" value="<?php echo isset( $_REQUEST['order_email'] ) ? esc_attr( wp_unslash( $_REQUEST['order_email'] ) ) : ''; ?>" placeholder="<?php echo esc_attr__( 'Email you used during checkout.', 'econis' ); ?>" /></p><?php // @codingStandardsIgnoreLine ?>
	<div class="clear"></div>

	<p class="form-row"><button type="submit" class="button" name="track" value="<?php echo esc_attr__( 'Track', 'econis' ); ?>"><?php echo esc_html__( 'Track', 'econis' ); ?></button></p>
	<?php wp_nonce_field( 'woocommerce-order_tracking', 'woocommerce-order-tracking-nonce' ); ?>

</form>
