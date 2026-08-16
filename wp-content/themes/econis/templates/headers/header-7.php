	<?php 
		$econis_settings = econis_global_settings();
		$cart_layout = econis_get_config('cart-layout','dropdown');
		$cart_style = econis_get_config('cart-style','light');
		$show_minicart = (isset($econis_settings['show-minicart']) && $econis_settings['show-minicart']) ? ($econis_settings['show-minicart']) : false;
		$enable_sticky_header = ( isset($econis_settings['enable-sticky-header']) && $econis_settings['enable-sticky-header'] ) ? ($econis_settings['enable-sticky-header']) : false;
		$show_searchform = (isset($econis_settings['show-searchform']) && $econis_settings['show-searchform']) ? ($econis_settings['show-searchform']) : false;
		$show_wishlist = (isset($econis_settings['show-wishlist']) && $econis_settings['show-wishlist']) ? ($econis_settings['show-wishlist']) : false;
		$show_currency = (isset($econis_settings['show-currency']) && $econis_settings['show-currency']) ? ($econis_settings['show-currency']) : false;
		$show_menutop = (isset($econis_settings['show-menutop']) && $econis_settings['show-menutop']) ? ($econis_settings['show-menutop']) : false;
		$show_mostsearch = (isset($econis_settings['show-mostsearch']) && $econis_settings['show-mostsearch']) ? ($econis_settings['show-mostsearch']) : false;
		$sticky_header = (isset($econis_settings['enable-sticky-header']) && $econis_settings['enable-sticky-header']) ? ($econis_settings['enable-sticky-header']) : false;
	?>
	<h1 class="bwp-title hide"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
	<header id='bwp-header' class="bwp-header header-v7">
		<?php econis_campbar(); ?>
		<?php if($sticky_header) { econis_menu_stcky(); } ?>
		<?php econis_menu_mobile(); ?>
		<div class="header-desktop">
			<?php if(($show_minicart || $show_wishlist || $show_searchform || is_active_sidebar('top-link')) && class_exists( 'WooCommerce' ) ){ ?>
			<div class='header-wrapper' data-sticky_header="<?php echo esc_attr($econis_settings['enable-sticky-header']); ?>">
				<div class="container">
					<div class="row">
						<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 col-12 header-left header-center">
							<div class="content-header-main">
								<div class="wpbingo-menu-mobile header-menu">
									<div class="header-menu-bg">
										<?php econis_top_menu(); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="col-xl-2 col-lg-2 col-md-12 col-sm-12 col-12 ">
							<?php econis_header_logo(); ?>
						</div>
						<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 col-12 header-right">
							<div class="header-page-link">
								<div class="login-header">
									<?php if (is_user_logged_in()) { ?>
										<a class="active-login" href="<?php echo get_permalink( get_option('woocommerce_myaccount_page_id') ); ?>" ><?php echo esc_html__("My Account","econis") ?></a>
									<?php }else{ ?>
										<a class="active-login" href="#" ><?php echo esc_html__("Login / Sign up","econis") ?></a>
										<?php econis_login_form(); ?>
									<?php } ?>
								</div>
								<!-- Begin Search -->
								<?php if($show_searchform && class_exists( 'WooCommerce' )){ ?>
								<div class="search-box">
									<div class="search-toggle"><i class="icon-search"></i></div>
								</div>
								<?php } ?>
								<!-- End Search -->		
								<?php if($show_wishlist && class_exists( 'WPCleverWoosw' )){ ?>
								<div class="wishlist-box">
									<a href="<?php echo WPcleverWoosw::get_url(); ?>"><i class="icon-heart"></i></a>
									<span class="count-wishlist"><?php echo WPcleverWoosw::get_count(); ?></span>
								</div>
								<?php } ?>
								<?php if($show_minicart && class_exists( 'WooCommerce' )){ ?>
								<div class="econis-topcart <?php echo esc_attr($cart_layout); ?> <?php echo esc_attr($cart_style); ?>">
									<?php get_template_part( 'woocommerce/minicart-ajax' ); ?>
								</div>
								<?php } ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php }else{ ?>
				<div class="header-normal">
					<div class='header-wrapper' data-sticky_header="<?php echo esc_attr($econis_settings['enable-sticky-header']); ?>">
						<div class="container">
							<div class="row">
								<div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-6 header-left">
									<?php econis_header_logo(); ?>
								</div>
								<div class="col-xl-9 col-lg-9 col-md-6 col-sm-6 col-6 wpbingo-menu-mobile header-main">
									<div class="header-menu-bg">
										<?php econis_top_menu(); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php } ?>
		</div>
	</header><!-- End #bwp-header -->