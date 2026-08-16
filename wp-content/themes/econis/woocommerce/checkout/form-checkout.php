<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 9.4.0
 */
$econis_settings = econis_global_settings();
$checkout_page_style = econis_get_config('checkout_page_style','checkout-page-style-1');
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'econis' ) ) );
	return;
}
global $woocommerce;
?>
<div class="woocommerce-page-header">
	<ul>
		<li class="shopping-cart-link line-hover">
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html__("Shopping Cart","econis"); ?><span class="cart-count">(<?php echo esc_attr($woocommerce->cart->cart_contents_count); ?>)</span></a>
		</li>
		<li class="checkout-link line-hover active"><a href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php echo esc_html__("Checkout","econis"); ?></a></li>
		<?php if (get_page_by_path('order-tracking')) { ?>
			<li class="order-tracking-link"><a href="<?php echo get_permalink( get_page_by_path( 'order-tracking' ) ); ?>"><?php echo esc_html__("Order Tracking","econis"); ?></a></li>
		<?php } ?>
	</ul>
</div>
<?php if ($checkout_page_style == 'checkout-page-style-1') { ?>
<div class="checkout-top">
	<?php if (! is_user_logged_in()) { ?>
		<div class="content-left-checkout">
			<div class="woocommerce-form-login-toggle">
				<?php wc_print_notice( apply_filters( 'woocommerce_checkout_login_message',  esc_html__( 'Returning customer?', 'econis' ) ) . ' <a href="#" class="showlogin">' . esc_html__( 'Click here to login', 'econis' ) . '</a>', 'notice' ); ?>
			</div>
			<form method="post" class="woocommerce-form woocommerce-form-login logins" style="display: none;">
				<div class="description"><p><?php echo  esc_html__( 'If you have shopped with us before, please enter your details below. If you are a new customer, please proceed to the Billing section.', 'econis' ); ?></p></div>
				<?php do_action( 'woocommerce_login_form_start' ); ?>
				<div class="username">
					<input type="text" class="input-text" placeholder="<?php echo esc_attr__("Username or email *","econis"); ?>" name="username" id="username" />
				</div>
				<div class="password">
					<input class="input-text" type="password" placeholder="<?php echo esc_attr__("Password *","econis"); ?>" name="password" id="password" />
				</div>
				<?php do_action( 'woocommerce_login_form' ); ?>
				<div class="rememberme-lost">
					<div class="rememberme">
						<input name="rememberme" type="checkbox" id="rememberme" value="forever" />
						<label for="rememberme" class="inline"><?php echo esc_html__( 'Remember me', 'econis' ); ?></label>
					</div>
				</div>
				<div class="button-login">
					<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
					<input type="submit" class="button" name="login" value="<?php echo esc_attr__( 'Login', 'econis' ); ?>" /> 
				</div>
				<?php do_action( 'woocommerce_login_form_end' ); ?>
			</form>
		</div>
	<?php } ?>
	<?php if ( wc_coupons_enabled() ) { ?>
		<div class="content-right-checkout">
			<div class="woocommerce-form-coupon-toggle">
				<?php wc_print_notice( apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Have a coupon?', 'econis' ) . ' <a href="#" class="showcoupon">' . esc_html__( 'Click here to enter your code', 'econis' ) . '</a>' ), 'notice' ); ?>
			</div>

			<form class="checkout_coupon woocommerce-form-coupon" method="post" style="display:none">

				<div class="description"><?php echo esc_html__( 'If you have a coupon code, please apply it below.', 'econis' ); ?></div>
				<div class="input-button">
					<input type="text" name="coupon_code" class="input-text" placeholder="<?php echo  esc_attr__( 'Coupon code', 'econis' ); ?>" id="coupon_code" value="" />
					<button type="submit" class="button" name="apply_coupon" value="<?php echo  esc_attr__( 'Apply coupon', 'econis' ); ?>"><?php echo esc_html__( 'Apply coupon', 'econis' ); ?></button>
				</div>
				<div class="clear"></div>
			</form>
		</div>
	<?php } ?>
</div>
<?php } ?>
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
	<div class="row">
		<?php if ( $checkout->get_checkout_fields() ) : ?>
			<div class="col-xl-8 col-lg-7 col-md-12 col-12">
				<?php if ($checkout_page_style == 'checkout-page-style-2') { ?>
				<div class="checkout-top">
					<?php if (! is_user_logged_in()) { ?>
						<div class="content-left-checkout">
							<div class="woocommerce-form-login-toggle">
								<?php wc_print_notice( apply_filters( 'woocommerce_checkout_login_message',  esc_html__( 'Returning customer?', 'econis' ) ) . ' <a href="#" class="showlogin">' . esc_html__( 'Click here to login', 'econis' ) . '</a>', 'notice' ); ?>
							</div>
							<div method="post" class="woocommerce-form woocommerce-form-login login" style="display: none;">
								<div class="description"><p><?php echo  esc_html__( 'If you have shopped with us before, please enter your details below. If you are a new customer, please proceed to the Billing section.', 'econis' ); ?></p></div>
								<?php do_action( 'woocommerce_login_form_start' ); ?>
								<div class="username">
									<input type="text" class="input-text" placeholder="<?php echo esc_attr__("Username or email *","econis"); ?>" name="username" id="username" />
								</div>
								<div class="password">
									<input class="input-text" type="password" placeholder="<?php echo esc_attr__("Password *","econis"); ?>" name="password" id="password" />
								</div>
								<?php do_action( 'woocommerce_login_form' ); ?>
								<div class="rememberme-lost">
									<div class="rememberme">
										<input name="rememberme" type="checkbox" id="rememberme" value="forever" />
										<label for="rememberme" class="inline"><?php echo esc_html__( 'Remember me', 'econis' ); ?></label>
									</div>
								</div>
								<div class="button-login">
									<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
									<input type="submit" class="button" name="login" value="<?php echo esc_attr__( 'Login', 'econis' ); ?>" /> 
								</div>
								<?php do_action( 'woocommerce_login_form_end' ); ?>
							</div>
						</div>
					<?php } ?>
					<?php if ( wc_coupons_enabled() ) { ?>
						<div class="content-right-checkout">
							<div class="woocommerce-form-coupon-toggle">
								<?php wc_print_notice( apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Have a coupon?', 'econis' ) . ' <a href="#" class="showcoupon">' . esc_html__( 'Click here to enter your code', 'econis' ) . '</a>' ), 'notice' ); ?>
							</div>

							<div class="checkout_coupon woocommerce-form-coupon" method="post" style="display:none">

								<div class="description"><?php echo esc_html__( 'If you have a coupon code, please apply it below.', 'econis' ); ?></div>
								<div class="input-button">
									<input type="text" name="coupon_code" class="input-text" placeholder="<?php echo  esc_attr__( 'Coupon code', 'econis' ); ?>" id="coupon_code" value="" />
									<button type="submit" class="button" name="apply_coupon" value="<?php echo  esc_attr__( 'Apply coupon', 'econis' ); ?>"><?php echo esc_html__( 'Apply coupon', 'econis' ); ?></button>
								</div>
								<div class="clear"></div>
							</div>
						</div>
					<?php } ?>
				</div>
				<?php } ?>
				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
				<div class="row" id="customer_details">
					<div class="col-12">
					<?php if ($checkout_page_style == 'checkout-page-style-2') { ?>
						<a class="back-to-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html__("Back to Cart","econis"); ?><span aria-hidden="true" class="arrow_right"></span></a>
					<?php } ?>
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>
					<div class="col-12">
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
				</div>
				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
			</div>
			<div class="col-xl-4 col-lg-5 col-md-12 col-12">
				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
				<div id="order_review" class="woocommerce-checkout-review-order">
					<div class="checkout-review-order-table-wrapper">
						<?php do_action( 'woocommerce_checkout_order_review' ); ?>
					</div>
				</div>
				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</div>
		<?php else: ?>
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
			<div id="order_review" class="woocommerce-checkout-review-order">	
				<div class="checkout-review-order-table-wrapper">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>
			</div>
			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>		
		<?php endif; ?>
	</div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>