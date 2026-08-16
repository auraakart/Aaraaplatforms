<?php
/**
 * Login Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-login.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 9.9.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php wc_print_notices(); ?>
<?php do_action( 'woocommerce_before_customer_login_form' ); ?>
<div class="col2-set row" id="customer_login">
	<div class="col-lg-6 col-md-6 col-sm-12">
		<div class="box-form-login">
			<h2><?php echo esc_html__( 'Login', 'econis' ); ?></h2>
			<div class="box-content">
				<div class="form-login">
					<form method="post" class="login">
						<?php do_action( 'woocommerce_login_form_start' ); ?>
						<div class="username">
							<label for="username"><?php echo esc_html__( 'Username or email address', 'econis' ); ?> <span class="required">*</span></label>
							<input type="text" class="input-text" name="username" id="username" />
						</div>
						<div class="password">
							<label for="password"><?php echo esc_html__( 'Password', 'econis' ); ?> <span class="required">*</span></label>
							<input class="input-text" type="password" name="password" id="password" />
						</div>
						<?php do_action( 'woocommerce_login_form' ); ?>
						<div class="rememberme-lost">
							<div class="rememberme">
								<input name="rememberme" type="checkbox" id="rememberme" value="forever" />
								<label for="rememberme" class="inline"><?php echo esc_html__( 'Remember me', 'econis' ); ?></label>
							</div>
							<div class="lost_password">
								<a href="<?php echo esc_url( wc_lostpassword_url() ); ?>"><?php echo esc_html__( 'Lost your password?', 'econis' ); ?></a>
							</div>
						</div>
						<div class="button-login">
							<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
							<input type="submit" class="button" name="login" value="<?php echo esc_attr__( 'Login', 'econis' ); ?>" /> 
						</div>
						<?php do_action( 'woocommerce_login_form_end' ); ?>
					</form>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-6 col-md-6 col-sm-12">
		<div class="box-form-login">
			<h2 class="register"><?php echo esc_html__( 'Register', 'econis' ); ?></h2>
			<div class="box-content">
				<div class="form-register">
					<form method="post" class="register">
						<?php do_action( 'woocommerce_register_form_start' ); ?>
						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
							<div class="username">
								<label for="reg_username"><?php echo esc_html__( 'Username', 'econis' ); ?> <span class="required">*</span></label>
								<input type="text" class="input-text" name="username" id="reg_username" value="<?php if ( ! empty( $_POST['username'] ) ) echo esc_attr( $_POST['username'] ); ?>" />
							</div>
						<?php endif; ?>
						<div class="email">
							<label for="reg_email"><?php echo esc_html__( 'Email address', 'econis' ); ?> <span class="required">*</span></label>
							<input type="email" class="input-text" name="email" id="reg_email" value="<?php if ( ! empty( $_POST['email'] ) ) echo esc_attr( $_POST['email'] ); ?>" />
						</div>
						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
							<div class="password">
								<label for="reg_password"><?php echo esc_html__( 'Password', 'econis' ); ?> <span class="required">*</span></label>
								<input type="password" class="input-text" name="password" id="reg_password" />
							</div>
						<?php endif; ?>
						<!-- Spam Trap -->
						<div style="<?php echo ( ( is_rtl() ) ? 'right' : 'left' ); ?>: -999em; position: absolute;"><label for="trap"><?php echo esc_html__( 'Anti-spam', 'econis' ); ?></label><input type="text" name="email_2" id="trap" tabindex="-1" /></div>
						<?php do_action( 'woocommerce_register_form' ); ?>
						<?php do_action( 'register_form' ); ?>
						<div class="button-register">
							<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
							<input type="submit" class="button" name="register" value="<?php echo esc_attr__( 'Register', 'econis' ); ?>" />
						</div>
						<?php do_action( 'woocommerce_register_form_end' ); ?>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
<?php do_action( 'woocommerce_after_customer_login_form' ); ?>