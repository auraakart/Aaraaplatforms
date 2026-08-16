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
	<header id='bwp-header' class="bwp-header header-v1">
		<?php econis_campbar(); ?>
		<?php if($sticky_header) { econis_menu_stcky(); } ?>
		<?php if(isset($econis_settings['show-header-top']) && $econis_settings['show-header-top']){ ?>
		<div id="bwp-topbar" class="topbar-v1 hidden-sm hidden-xs">
			<div class="topbar-inner">
				<div class="container">
					<div class="row">
						<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 topbar-left hidden-sm hidden-xs">
							<?php if( isset($econis_settings['address']) && $econis_settings['address'] ) : ?>
							<div class="address hidden-xs">
								<a href="<?php echo esc_html($econis_settings['link_address']); ?>"><i class="icon-pin"></i><?php echo esc_html($econis_settings['address']); ?></a>
							</div>
							<?php endif; ?>
							<?php if( isset($econis_settings['email']) && $econis_settings['email'] ) : ?>
							<div class="email hidden-xs">
								<i class="icon-email"></i><a href="mailto:<?php echo esc_attr($econis_settings['email']); ?>"><?php echo esc_html($econis_settings['email']); ?></a>
							</div>
							<?php endif; ?>
						</div>
						<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12 topbar-right">
							<?php if($show_menutop){ ?>
								<?php wp_nav_menu( 
								  array( 
									  'theme_location' => 'topbar_menu', 
									  'container' => 'false', 
									  'menu_id' => 'topbar_menu', 
									  'menu_class' => 'menu'
								   ) 
								); ?>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php } ?>
		<?php econis_menu_mobile(true); ?>
		<div class="header-desktop">
			<?php if(($show_minicart || $show_wishlist || $show_searchform || is_active_sidebar('top-link')) && class_exists( 'WooCommerce' ) ){ ?>
			<div class="header-top">
				<div class="container">
					<div class="row">
						<div class="col-xl-3 col-lg-3 col-md-12 col-sm-12 col-12 header-left">
							<?php econis_header_logo(); ?>
						</div>
						<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12 header-center">
							<div class="header-search-form">
								<!-- Begin Search -->
								<?php if($show_searchform && class_exists( 'WooCommerce' )){ ?>
									<?php get_template_part( 'search-form' ); ?>
								<?php } ?>
								<!-- End Search -->	
							</div>
						</div>
						<div class="col-xl-3 col-lg-3 col-md-12 col-sm-12 col-12 header-right">
							<div class="header-page-link">
								<div class="login-header">
									<?php if (is_user_logged_in()) { ?>
										<a class="active-login" href="<?php echo get_permalink( get_option('woocommerce_myaccount_page_id') ); ?>" ><?php echo esc_html__("My Account","econis") ?></a>
									<?php }else{ ?>
										<a class="active-login" href="#" ><?php echo esc_html__("Login / Sign up","econis") ?></a>
										<?php econis_login_form(); ?>
									<?php } ?>
								</div>	
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
			<div class='header-wrapper' data-sticky_header="<?php echo esc_attr($sticky_header); ?>">
				<div class="container">
					<div class="row">
						<div class="col-xl-9 col-lg-8 col-md-12 col-sm-12 col-12 header-left content-header">
							<?php $class_vertical = econis_dropdown_vertical_menu(); ?>
							<div class="header-vertical-menu">
								<div class="categories-vertical-menu hidden-sm hidden-xs <?php echo esc_attr($class_vertical); ?>"
									data-textmore="<?php echo esc_html__("Other","econis"); ?>" 
									data-textclose="<?php echo esc_html__("Close","econis"); ?>" 
									data-max_number_1530="<?php echo esc_attr(econis_limit_verticalmenu()->max_number_1530); ?>" 
									data-max_number_1200="<?php echo esc_attr(econis_limit_verticalmenu()->max_number_1200); ?>" 
									data-max_number_991="<?php echo esc_attr(econis_limit_verticalmenu()->max_number_991); ?>">
									<?php echo econis_vertical_menu(); ?>
								</div>
							</div>
							<div class="content-header-main">
								<div class="wpbingo-menu-mobile header-menu">
									<div class="header-menu-bg">
										<?php econis_top_menu(); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="col-xl-3 col-lg-4 col-md-12 col-sm-12 col-12 header-right">
							<?php if( isset($econis_settings['ship']) && $econis_settings['ship'] ) : ?>
							<div class="ship hidden-xs hidden-sm">
								<div class="content">
									<?php echo esc_html($econis_settings['ship']); ?>
								</div>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div><!-- End header-wrapper -->
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