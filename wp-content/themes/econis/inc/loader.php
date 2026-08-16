<?php
if ( ! function_exists( 'econis_setup' ) ) :
		function econis_setup() {
			load_theme_textdomain( 'econis', get_template_directory() . '/languages' );
			// Add RSS feed links to <head> for posts and comments.
			add_theme_support( 'automatic-feed-links' );
			// Enable support for Post Thumbnails, and declare two sizes.
			add_theme_support( 'post-thumbnails' );
			set_post_thumbnail_size( 720, 484, true );
			add_image_size( 'econis-full-width', 1440, 1000, true );
			add_image_size( 'econis-blog-sidebar', 500, 500, true );
			add_image_size( 'econis-blog-layout-1', 900, 1000, true );
			add_theme_support( 'title-tag' );
			/*
			 * Switch default core markup for search form, comment form, and comments
			 * to output valid HTML5.
			 */
			add_theme_support( 'html5', array(
				'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
			) );
			/*
			 * Enable support for Post Formats.
			 * See http://codex.wordpress.org/Post_Formats
			 */
			add_theme_support( 'post-formats', array(
				'aside', 'image', 'video', 'quote', 'link', 'gallery', 'status', 'audio', 'chat'
			) );
			// This theme allows users to set a custom background.
			add_theme_support( 'custom-background', apply_filters( 'econis_custom_background_args', array(
				'default-color' => 'f5f5f5',
			) ) );
			// Custom image header
			$econis_image_headers = array(
				'default-image' => get_template_directory_uri().'/images/logo/logo-default.png',
				'uploads'       => true
			);
			add_theme_support( 'custom-header', $econis_image_headers );
			// Tell the TinyMCE editor to use a custom stylesheet
			add_editor_style( 'css/editor-style.css' );
			// This theme uses its own gallery styles.
			add_filter( 'use_default_gallery_style', '__return_false' );
			add_theme_support( 'woocommerce' );
		}
		endif;
		add_action( 'after_setup_theme', 'econis_setup' );
		// econis_setup
		function econis_pingback_header() {
			if ( is_singular() && pings_open() ) {
				echo '<link rel="pingback" href="', esc_url( get_bloginfo( 'pingback_url' ) ), '">';
			}
		}
		add_action( 'wp_head', 'econis_pingback_header' );
		function econis_widgets_init() {
			register_sidebar( array(
				'name'          => esc_html__( 'Sidebar Blog', 'econis' ),
				'id'            => 'sidebar-blog',
				'description'   => esc_html__( 'Add widgets here to appear in your sidebar blog.', 'econis' ),
				'before_widget' => '<aside id="%1$s" class="widget %2$s">',
				'after_widget'  => '</aside>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			) );
			register_sidebar( array(
				'name'          => esc_html__( 'Header Top Link', 'econis' ),
				'id'            => 'top-link',
				'description'   => esc_html__( 'Main sidebar that appears on the top.', 'econis' ),
				'before_widget' => '<aside id="%1$s" class="widget %2$s">',
				'after_widget'  => '</aside>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			) );
			register_sidebar( array(
				'name'          => esc_html__( 'Sidebar Shop', 'econis' ),
				'id'            => 'sidebar-product',
				'description'   => esc_html__( 'Add widgets here to appear in your sidebar shop.', 'econis' ),
				'before_widget' => '<aside id="%1$s" class="widget %2$s">',
				'after_widget'  => '</aside>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			) );
			register_sidebar( array(
				'name'          => esc_html__( 'Sidebar Single Product', 'econis' ),
				'id'            => 'sidebar-single-product',
				'description'   => esc_html__( 'Add widgets here to appear in your sidebar single shop.', 'econis' ),
				'before_widget' => '<aside id="%1$s" class="widget %2$s">',
				'after_widget'  => '</aside>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			) );
			register_sidebar( array(
				'name'          => esc_html__( 'Sidebar Vendor', 'econis' ),
				'id'            => 'sidebar-vendor',
				'description'   => esc_html__( 'Add widgets here to appear in your sidebar vendor.', 'econis' ),
				'before_widget' => '<aside id="%1$s" class="widget %2$s">',
				'after_widget'  => '</aside>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			) );			
			register_sidebar( array(
				'name'          => esc_html__( 'Newsletter Popup', 'econis' ),
				'id'            => 'newletter-popup-form',
				'description'   => esc_html__( 'Add widgets here to appear in your newsletter popup.', 'econis' ),
				'before_widget' => '<aside id="%1$s" class="widget clearfix %2$s">',
				'after_widget'  => '</aside>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			) );
		}
		add_action( 'widgets_init', 'econis_widgets_init' );
		function econis_fonts_url() {
			$fonts_url = '';
			$poppins = _x( 'on', 'Poppins font: on or off', 'econis' );
			if ( 'off' !== $poppins ) {
				$font_families = array();
				if ( 'off' !== $poppins) {
					$font_families[] = 'Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900';
				}
				$query_args = array(
					'family' =>	urlencode( implode( '|', $font_families ) ),
					'subset' =>	urlencode( 'latin,latin-ext' ),
				);
				$fonts_url = add_query_arg( $query_args, 'https://fonts.googleapis.com/css2' );
			}
			return esc_url_raw( $fonts_url );
		}
		function econis_scripts_styles() {
			wp_enqueue_style( 'econis-fonts', econis_fonts_url(), array(), null );
		}
		add_action( 'wp_enqueue_scripts', 'econis_scripts_styles' );	
		function econis_add_scripts() {
			// Load our main stylesheet.
			wp_enqueue_style( 'econis-style', get_stylesheet_uri() );
			if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}
			wp_enqueue_script( 'popper',get_template_directory_uri().'/js/popper.min.js', array('jquery'), null, true);
			wp_enqueue_script( 'bootstrap',get_template_directory_uri().'/js/bootstrap.min.js', array('jquery'), null, true);
			wp_enqueue_script( 'jquery-mmenu-all',get_template_directory_uri().'/js/jquery.mmenu.all.min.js', array('jquery'), null, true);
			wp_enqueue_script( 'slick',get_template_directory_uri().'/js/slick.min.js',array('jquery'), null, true);
			wp_enqueue_script( 'instafeed',get_template_directory_uri().'/js/instafeed.min.js', array('jquery'), null, true);
			wp_enqueue_script( 'jquery-countdown',get_template_directory_uri().'/js/jquery.countdown.min.js', array('jquery'), null, true);
			wp_enqueue_script( 'jquery-elevatezoom', get_template_directory_uri() . '/js/jquery.elevatezoom.js' , array('jquery'), null, true );
			wp_enqueue_script( 'photoswipe', get_template_directory_uri().'/js/photoswipe.min.js' , array('jquery'), null, true );
			wp_enqueue_script( 'photoswipe-ui-default', get_template_directory_uri().'/js/photoswipe-ui-default.min.js' , array('jquery'), null, true );
			wp_enqueue_script( 'jquery-circlestime', get_template_directory_uri().'/js/jquery.circlestime.js', array('jquery'), null, true);
			wp_enqueue_script( '360imagerotate', get_template_directory_uri().'/js/360imagerotate.js', array('jquery'), null, true);
			wp_register_script( 'econis-portfolio', get_template_directory_uri() . '/js/portfolio.js', array('jquery'), null, true );
			wp_enqueue_script( 'econis-portfolio' );
			wp_enqueue_script( 'sly',get_template_directory_uri().'/js/sly.min.js', array('jquery'), null, true);
			if (!is_admin()) {
				wp_enqueue_script( 'econis-script', get_template_directory_uri() . '/js/functions.js', array( 'jquery'), null, true );
			}
			$direction = econis_get_direction();
			if( is_rtl() || $direction == "rtl"){
				wp_enqueue_style( 'bootstrap-rtl',get_template_directory_uri().'/css/bootstrap-rtl.css' );
			}else{
				wp_enqueue_style( 'bootstrap', get_template_directory_uri().'/css/bootstrap.css' );
			}
			wp_enqueue_style('circlestime', get_template_directory_uri().'/css/jquery.circlestime.css', array(), null);
			wp_enqueue_style( 'mmenu-all', get_template_directory_uri().'/css/jquery.mmenu.all.css' );
			wp_enqueue_style('slick', get_template_directory_uri().'/css/slick/slick.css', array(), null);
			wp_enqueue_style( 'photoswipe', get_template_directory_uri().'/css/photoswipe.css' );
			wp_enqueue_style( 'font-awesome',get_template_directory_uri().'/css/font-awesome.css' );
			wp_enqueue_style( 'materia',get_template_directory_uri().'/css/materia.css' );
			wp_enqueue_style( 'elegant',get_template_directory_uri().'/css/elegant.css' );
			wp_enqueue_style( 'wpbingo',get_template_directory_uri().'/css/wpbingo.css' );
			wp_enqueue_style( 'icomoon',get_template_directory_uri().'/css/icomoon.css' );
			if (!is_admin()) {
				wp_enqueue_style( 'econis-style-template', get_template_directory_uri().'/css/template.css');
			}
			add_filter( 'woocommerce_enqueue_styles', '__return_false' );	
		}
		add_action( 'wp_enqueue_scripts', 'econis_add_scripts' );
		function econis_admin_style() {
		  wp_enqueue_style('econis-admin-styles', get_template_directory_uri().'/inc/admin/css/options.css');
		}
		add_action('admin_enqueue_scripts', 'econis_admin_style');	
?>