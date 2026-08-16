<?php
/**
 * Econis Settings Options
 */
if (!class_exists('Redux_Framework_econis_settings')) {
    class Redux_Framework_econis_settings {
        public $args        = array();
        public $sections    = array();
        public $theme;
        public $ReduxFramework;
        public function __construct() {
            if (!class_exists('ReduxFramework')) {
                return;
            }
            // This is needed. Bah WordPress bugs.  ;)
            if (  true == Redux_Helpers::isTheme(__FILE__) ) {
                $this->initSettings();
            } else {
                add_action('plugins_loaded', array($this, 'initSettings'), 10);
            }
        }
        public function initSettings() {
            $this->theme = wp_get_theme();
            // Set the default arguments
            $this->setArguments();
            // Set a few help tabs so you can see how it's done
            $this->setHelpTabs();
            // Create the sections and fields
            $this->setSections();
            if (!isset($this->args['opt_name'])) { // No errors please
                return;
            }
            $this->ReduxFramework = new ReduxFramework($this->sections, $this->args);
			$custom_font = econis_get_config('custom_font',false);
			if($custom_font != 1){
				remove_action( 'wp_head', array( $this->ReduxFramework, '_output_css' ),150 );
			}
        }
        function compiler_action($options, $css, $changed_values) {
        }
        function dynamic_section($sections) {
            return $sections;
        }
        function change_arguments($args) {
            return $args;
        }
        function change_defaults($defaults) {
            return $defaults;
        }
        function remove_demo() {
        }
        public function setSections() {
            $page_layouts = econis_options_layouts();
            $sidebars = econis_options_sidebars();
            $econis_header_type = econis_options_header_types();
            $econis_banners_effect = econis_options_banners_effect();
            // General Settings  ------------
            $this->sections[] = array(
                'icon' => 'fa fa-home',
                'icon_class' => 'icon',
                'title' => esc_html__('General', 'econis'),
                'fields' => array(                
                )
            );  
            // Layout Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Layout', 'econis'),
                'fields' => array(
                    array(
                        'id' => 'background_img',
                        'type' => 'media',
                        'title' => esc_html__('Background Image', 'econis'),
                        'sub_desc' => '',
                        'default' => ''
                    ),
                    array(
                        'id'=>'show-newletter',
                        'type' => 'switch',
                        'title' => esc_html__('Show Newletter Form', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Show', 'econis'),
                        'off' => esc_html__('Hide', 'econis'),
                    ),
                    array(
                        'id' => 'background_newletter_img',
                        'type' => 'media',
                        'title' => esc_html__('Popup Newletter Image', 'econis'),
                        'url'=> true,
                        'readonly' => false,
                        'sub_desc' => '',
                        'default' => array(
                            'url' => get_template_directory_uri() . '/images/newsletter-image.jpg'
                        )
                    ),
                    array(
                            'id' => 'back_active',
                            'type' => 'switch',
                            'title' => esc_html__('Back to top', 'econis'),
                            'sub_desc' => '',
                            'desc' => '',
                            'default' => '1'// 1 = on | 0 = off
                            ),                          
                    array(
                            'id' => 'direction',
                            'type' => 'select',
                            'title' => esc_html__('Direction', 'econis'),
                            'options' => array( 'ltr' => esc_html__('Left to Right', 'econis'), 'rtl' => esc_html__('Right to Left', 'econis') ),
                            'default' => 'ltr'
                        )        
                )
            );
            // Logo & Icons Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Logo & Icons', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'sitelogo',
                        'type' => 'media',
                        'compiler'  => 'true',
                        'mode'      => false,
                        'title' => esc_html__('Logo', 'econis'),
                        'desc'      => esc_html__('Upload Logo image default here.', 'econis'),
                        'default' => array(
                            'url' => get_template_directory_uri() . '/images/logo/logo.png'
                        )
                    )
                )
            );
			//Vertical Menu
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'subsection' => true,
                'title' => esc_html__('Vertical Menu', 'econis'),
                'fields' => array( 
                    array(
                        'id'        => 'max_number_1530',
                        'type'      => 'text',
                        'title'     => esc_html__('Max number on screen >= 1530px', 'econis'),
                        'default'   => '12'
                    ),
                    array(
                        'id'        => 'max_number_1200',
                        'type'      => 'text',
                        'title'     => esc_html__('Max number on on screen >= 1200px', 'econis'),
                        'default'   => '8'
                    ),
					array(
                        'id'        => 'max_number_991',
                        'type'      => 'text',
                        'title'     => esc_html__('Max number on on screen >= 991px', 'econis'),
                        'default'   => '6'
                    )
                )
            );
            // Header Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Header', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'header_style',
                        'type' => 'image_select',
                        'full_width' => true,
                        'title' => esc_html__('Header Type', 'econis'),
                        'options' => $econis_header_type,
                        'default' => '1'
                    ),
                    array(
                        'id'=>'show-header-top',
                        'type' => 'switch',
                        'title' => esc_html__('Show Header Top', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'show-searchform',
                        'type' => 'switch',
                        'title' => esc_html__('Show Search Form', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'show-ajax-search',
                        'type' => 'switch',
                        'title' => esc_html__('Show Ajax Search', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis')
                    ),
                    array(
                        'id'=>'limit-ajax-search',
                        'type' => 'text',
                        'title' => esc_html__('Limit Of Result Search', 'econis'),
						'default' => 6,
						'required' => array('show-ajax-search','equals',true)
                    ),					
                    array(
                        'id'=>'search-cats',
                        'type' => 'switch',
                        'title' => esc_html__('Show Categories', 'econis'),
                        'required' => array('search-type','equals',array('post', 'product')),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'show-wishlist',
                        'type' => 'switch',
                        'title' => esc_html__('Show Wishlist', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
					array(
                        'id'=>'show-campbar',
                        'type' => 'switch',
                        'title' => esc_html__('Show Campbar', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
					array(
                        'id'=>'link-campbar',
                        'type' => 'text',
                        'title' => esc_html__('Link Campbar', 'econis'),
						'default' => '#',
						'required' => array('show-campbar','equals',true),
                    ),
					array(
                        'id'=>'content-campbar',
                        'type' => 'text',
                        'title' => esc_html__('Content Campbar', 'econis'),
						'default' => esc_html__('20% OFF EVERYTHING – USE CODE:FLASH20 – ENDS SUNDAY', 'econis'),
						'required' => array('show-campbar','equals',true),
                    ),
					array(
						'id' => 'img-campbar',
						'type' => 'media',
						'title' => esc_html__('Image Campbar', 'econis'),
						'url'=> true,
						'readonly' => false,
						'required' => array('show-campbar','equals',true),
						'sub_desc' => '',
						'default' => array(
							'url' => ""
						)
					),
					 array(
                      'id' => 'color-campbar',
                      'type' => 'color',
                      'title' => esc_html__('Color Campbar', 'econis'),
                      'subtitle' => esc_html__('Select a color for Campbar.', 'econis'),
                      'default' => '#424cc7',
                      'transparent' => false,
					  'required' => array('show-campbar','equals',true),
                    ),
					array(
                        'id'=>'show-menutop',
                        'type' => 'switch',
                        'title' => esc_html__('Show Menu Top', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
					array(
                        'id'=>'show-compare',
                        'type' => 'switch',
                        'title' => esc_html__('Show Compare', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
					array(
                        'id'=>'show-minicart',
                        'type' => 'switch',
                        'title' => esc_html__('Show Mini Cart', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
					array(
                        'id'=>'cart-layout',
						'type' => 'button_set',
                        'title' => esc_html__('Cart Layout', 'econis'),
                        'options' => array('dropdown' => esc_html__('Dropdown', 'econis'),
											'popup' => esc_html__('Popup', 'econis')),
						'default' => 'dropdown',
						'required' => array('show-minicart','equals',true),
                    ),
					array(
                        'id'=>'cart-style',
						'type' => 'button_set',
                        'title' => esc_html__('Cart Style', 'econis'),
                        'options' => array('dark' => esc_html__('Dark', 'econis'),
											'light' => esc_html__('Light', 'econis')),
						'default' => 'light',
						'required' => array('show-minicart','equals',true),
                    ),
                    array(
                        'id'=>'enable-sticky-header',
                        'type' => 'switch',
                        'title' => esc_html__('Enable Sticky Header', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
					array(
                        'id'=>'email',
                        'type' => 'text',
                        'title' => esc_html__('Header Email', 'econis'),
                        'default' => ''
                    ),
					array(
                        'id'=>'address',
                        'type' => 'text',
                        'title' => esc_html__('Address', 'econis'),
                        'default' => esc_html__('Find Store', 'econis'),
                    ),
					array(
                        'id'=>'link_address',
                        'type' => 'text',
                        'title' => esc_html__('Link Address', 'econis'),
                        'default' => '#'
                    ),
					array(
                        'id'=>'phone',
                        'type' => 'text',
                        'title' => esc_html__('Phone', 'econis'),
                        'default' => '(+1)202-333-800'
                    ),
					array(
                        'id'=>'ship',
                        'type' => 'text',
                        'title' => esc_html__('Ship', 'econis'),
                        'default' => 'Free Shipping on Orders $300'
                    )
                )
            );
            // Footer Settings
            $footers = econis_get_footers();
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Footer', 'econis'),
                'fields' => array(
                    array(
                        'id' => 'footer_style',
                        'type' => 'image_select',
                        'title' => esc_html__('Footer Style', 'econis'),
                        'sub_desc' => esc_html__( 'Select Footer Style', 'econis' ),
                        'desc' => '',
                        'options' => $footers,
                        'default' => '32'
                    ),
                )
            );
            // Copyright Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Copyright', 'econis'),
                'fields' => array(
                    array(
                        'id' => "footer-copyright",
                        'type' => 'textarea',
                        'title' => esc_html__('Copyright', 'econis'),
                        'default' => sprintf( wp_kses('&copy; Copyright %s. All Rights Reserved.', 'econis'), date('Y') )
                    ),
                    array(
                        'id'=>'footer-payments',
                        'type' => 'switch',
                        'title' => esc_html__('Show Payments Logos', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'footer-payments-image',
                        'type' => 'media',
                        'url'=> true,
                        'readonly' => false,
                        'title' => esc_html__('Payments Image', 'econis'),
                        'required' => array('footer-payments','equals','1'),
                        'default' => array(
                            'url' => get_template_directory_uri() . '/images/payments.png'
                        )
                    ),
                    array(
                        'id'=>'footer-payments-image-alt',
                        'type' => 'text',
                        'title' => esc_html__('Payments Image Alt', 'econis'),
                        'required' => array('footer-payments','equals','1'),
                        'default' => ''
                    ),
                    array(
                        'id'=>'footer-payments-link',
                        'type' => 'text',
                        'title' => esc_html__('Payments Link URL', 'econis'),
                        'required' => array('footer-payments','equals','1'),
                        'default' => ''
                    )
                )
            );
            // Page Title Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Page Title', 'econis'),
                'fields' => array(
					array(
                        'id'=>'show_bg_breadcrumb',
                        'type' => 'switch',
                        'title' => esc_html__('Show Background Breadcrumb', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'page_title',
                        'type' => 'switch',
                        'title' => esc_html__('Show Page Title', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
						'required' => array('show_bg_breadcrumb','equals', true),
                    ),
                    array(
                        'id'=>'page_title_bg',
                        'type' => 'media',
                        'url'=> true,
                        'readonly' => false,
                        'title' => esc_html__('Background', 'econis'),
						'required' => array('show_bg_breadcrumb','equals', true),
	                    'default' => array(
                            'url' => "",
                        )							
                    ),
                    array(
                        'id' => 'breadcrumb',
                        'type' => 'switch',
                        'title' => esc_html__('Show Breadcrumb', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                        'required' => array('show_bg_breadcrumb','equals', true),
                    ),
                )
            );
            // 404 Page Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('404 Error', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'title-error',
                        'type' => 'text',
                        'title' => esc_html__('Content Page 404', 'econis'),
                        'desc' => esc_html__('Input a block slug name', 'econis'),
                        'default' => esc_html__('404', 'econis'),
                    ),
					array(
                        'id'=>'sub-title',
                        'type' => 'text',
                        'title' => esc_html__('Content Page 404', 'econis'),
                        'desc' => esc_html__('Input a block slug name', 'econis'),
                        'default' => esc_html__("Oops! That page can't be found.", "econis"),
                    ), 					
                    array(
                        'id'=>'sub-error',
                        'type' => 'text',
                        'title' => esc_html__('Content Page 404', 'econis'),
                        'desc' => esc_html__('Input a block slug name', 'econis'),
                        'default' => esc_html__("We're really sorry but we can't seem to find the page you were looking for.", "econis"),
                    ),               
                    array(
                        'id'=>'btn-error',
                        'type' => 'text',
                        'title' => esc_html__('Button Page 404', 'econis'),
                        'desc' => esc_html__('Input a block slug name', 'econis'),
                        'default' => esc_html__('Input a block slug name', 'econis'),
                    )                      
                )
            );
            // Social Share Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Social Share', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'social-share',
                        'type' => 'switch',
                        'title' => esc_html__('Show Social Links', 'econis'),
                        'desc' => esc_html__('Show social links in post and product, page, portfolio, etc.', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'share-fb',
                        'type' => 'switch',
                        'title' => esc_html__('Enable Facebook Share', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'share-tw',
                        'type' => 'switch',
                        'title' => esc_html__('Enable Twitter Share', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'share-linkedin',
                        'type' => 'switch',
                        'title' => esc_html__('Enable LinkedIn Share', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'share-pinterest',
                        'type' => 'switch',
                        'title' => esc_html__('Enable Pinterest Share', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                )
            );
            $this->sections[] = array(
				'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Socials Link', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'socials_link',
                        'type' => 'switch',
                        'title' => esc_html__('Enable Socials link', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'target_social_link',
                        'type' => 'switch',
                        'title' => esc_html__('Enable Target Socials Link', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'link-fb',
                        'type' => 'text',
                        'title' => esc_html__('Enter Facebook link', 'econis'),
						'default' => '#'
                    ),
                    array(
                        'id'=>'link-tw',
                        'type' => 'text',
                        'title' => esc_html__('Enter Twitter link', 'econis'),
						'default' => '#'
                    ),
                    array(
                        'id'=>'link-linkedin',
                        'type' => 'text',
                        'title' => esc_html__('Enter LinkedIn link', 'econis'),
						'default' => '#'
                    ),
                    array(
                        'id'=>'link-youtube',
                        'type' => 'text',
                        'title' => esc_html__('Enter Youtube link', 'econis'),
						'default' => '#'
                    ),
                    array(
                        'id'=>'link-pinterest',
                        'type' => 'text',
                        'title' => esc_html__('Enter Pinterest link', 'econis'),
						'default' => '#'
                    ),
                    array(
                        'id'=>'link-instagram',
                        'type' => 'text',
                        'title' => esc_html__('Enter Instagram link', 'econis'),
						'default' => '#'
                    ),
					array(
                        'id'=>'link-tiktok',
                        'type' => 'text',
                        'title' => esc_html__('Enter Tiktok link', 'econis'),
                        'default' => ''
                    ),
                )
            );			
            //     The end -----------
            // Styling Settings  -------------
            $this->sections[] = array(
                'icon' => 'icofont icofont-brand-appstore',
                'icon_class' => 'icon',
                'title' => esc_html__('Styling', 'econis'),
                'fields' => array(              
                )
            );  
            // Color & Effect Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Color & Effect', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'compile-css',
                        'type' => 'switch',
                        'title' => esc_html__('Compile Css', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),					
                    array(
                      'id' => 'main_theme_color',
                      'type' => 'color',
                      'title' => esc_html__('Main Theme Color', 'econis'),
                      'subtitle' => esc_html__('Select a main color for your site.', 'econis'),
                      'default' => '#222222',
                      'transparent' => false,
					  'required' => array('compile-css','equals',array(true)),
                    ),      
                    array(
                        'id'=>'show-loading-overlay',
                        'type' => 'switch',
                        'title' => esc_html__('Loading Overlay', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Show', 'econis'),
                        'off' => esc_html__('Hide', 'econis'),
                    ),
                    array(
                        'id'=>'banners_effect',
                        'type' => 'image_select',
                        'full_width' => true,
                        'title' => esc_html__('Banner Effect', 'econis'),
                        'options' => $econis_banners_effect,
                        'default' => 'banners-effect-1'
                    )                   
                )
            );
            // Typography Settings
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Typography', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'custom_font',
                        'type' => 'switch',
                        'title' => esc_html__('Custom Font', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),				
                    array(
                        'id'=>'select-google-charset',
                        'type' => 'switch',
                        'title' => esc_html__('Select Google Font Character Sets', 'econis'),
                        'default' => false,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
						'required' => array('custom_font','equals',true),
                    ),
                    array(
                        'id'=>'google-charsets',
                        'type' => 'button_set',
                        'title' => esc_html__('Google Font Character Sets', 'econis'),
                        'multi' => true,
                        'required' => array('select-google-charset','equals',true),
                        'options'=> array(
                            'cyrillic' => 'Cyrrilic',
                            'cyrillic-ext' => 'Cyrrilic Extended',
                            'greek' => 'Greek',
                            'greek-ext' => 'Greek Extended',
                            'khmer' => 'Khmer',
                            'latin' => 'Latin',
                            'latin-ext' => 'Latin Extneded',
                            'vietnamese' => 'Vietnamese'
                        ),
                        'default' => array('latin','greek-ext','cyrillic','latin-ext','greek','cyrillic-ext','vietnamese','khmer')
                    ),
                    array(
                        'id'=>'family_font_body',
                        'type' => 'typography',
                        'title' => esc_html__('Body Font', 'econis'),
                        'google' => true,
                        'subsets' => false,
                        'font-style' => false,
                        'text-align' => false,
						'output'      => array('body'),
                        'color' => false,
                        'default'=> array(
                            'color'=>"#777777",
                            'google'=>true,
                            'font-weight'=>'400',
                            'font-family'=>'Open Sans',
                            'font-size'=>'14px',
                            'line-height' => '22px'
                        ),
						'required' => array('custom_font','equals',true)
                    ),
                    array(
                        'id'=>'h1-font',
                        'type' => 'typography',
                        'title' => esc_html__('H1 Font', 'econis'),
                        'google' => true,
                        'subsets' => false,
                        'font-style' => false,
                        'text-align' => false,
                        'color' 	=> false,
						'output'      => array('body h1'),
                        'default'=> array(
                            'color'=>"#1d2127",
                            'google'=>true,
                            'font-weight'=>'400',
                            'font-family'=>'Open Sans',
                            'font-size'=>'36px',
                            'line-height' => '44px'
                        ),
						'required' => array('custom_font','equals',true)
                    ),
                    array(
                        'id'=>'h2-font',
                        'type' => 'typography',
                        'title' => esc_html__('H2 Font', 'econis'),
                        'google' => true,
                        'subsets' => false,
                        'font-style' => false,
                        'text-align' => false,
                        'color' => false,
						'output'      => array('body h2'),
                        'default'=> array(
                            'color'=>"#1d2127",
                            'google'=>true,
                            'font-weight'=>'300',
                            'font-family'=>'Open Sans',
                            'font-size'=>'30px',
                            'line-height' => '40px'
                        ),
						'required' => array('custom_font','equals',true)
                    ),
                    array(
                        'id'=>'h3-font',
                        'type' => 'typography',
                        'title' => esc_html__('H3 Font', 'econis'),
                        'google' => true,
                        'subsets' => false,
                        'font-style' => false,
                        'text-align' => false,
                        'color' => false,
						'output'      => array('body h3'),
                        'default'=> array(
                            'color'=>"#1d2127",
                            'google'=>true,
                            'font-weight'=>'400',
                            'font-family'=>'Open Sans',
                            'font-size'=>'25px',
                            'line-height' => '32px'
                        ),
						'required' => array('custom_font','equals',true)
                    ),
                    array(
                        'id'=>'h4-font',
                        'type' => 'typography',
                        'title' => esc_html__('H4 Font', 'econis'),
                        'google' => true,
                        'subsets' => false,
                        'font-style' => false,
                        'text-align' => false,
                        'color' => false,
						'output'      => array('body h4'),
                        'default'=> array(
                            'color'=>"#1d2127",
                            'google'=>true,
                            'font-weight'=>'400',
                            'font-family'=>'Open Sans',
                            'font-size'=>'20px',
                            'line-height' => '27px'
                        ),
						'required' => array('custom_font','equals',true)
                    ),
                    array(
                        'id'=>'h5-font',
                        'type' => 'typography',
                        'title' => esc_html__('H5 Font', 'econis'),
                        'google' => true,
                        'subsets' => false,
                        'font-style' => false,
                        'text-align' => false,
                        'color' => false,
						'output'      => array('body h5'),
                        'default'=> array(
                            'color'=>"#1d2127",
                            'google'=>true,
                            'font-weight'=>'600',
                            'font-family'=>'Open Sans',
                            'font-size'=>'14px',
                            'line-height' => '18px'
                        ),
						'required' => array('custom_font','equals',true)
                    ),
                    array(
                        'id'=>'h6-font',
                        'type' => 'typography',
                        'title' => esc_html__('H6 Font', 'econis'),
                        'google' => true,
                        'subsets' => false,
                        'font-style' => false,
                        'text-align' => false,
                        'color' => false,
						'output'      => array('body h6'),
                        'default'=> array(
                            'color'=>"#1d2127",
                            'google'=>true,
                            'font-weight'=>'400',
                            'font-family'=>'Open Sans',
                            'font-size'=>'14px',
                            'line-height' => '18px'
                        ),
						'required' => array('custom_font','equals',true)
                    )
                )
            );
            //     The end -----------          
            if ( class_exists( 'Woocommerce' ) ) :
                $this->sections[] = array(
                    'icon' => 'icofont icofont-cart-alt',
                    'icon_class' => 'icon',
                    'title' => esc_html__('Ecommerce', 'econis'),
                    'fields' => array(              
                    )
                );
                $this->sections[] = array(
                    'icon' => 'icofont icofont-double-right',
                    'icon_class' => 'icon',
                    'subsection' => true,
                    'title' => esc_html__('Product Archives', 'econis'),
                    'fields' => array(
						array(
                            'id'=>'shop_paging',
							'title' => esc_html__('Shop Paging', 'econis'),
                            'type' => 'select',
							'options' => array(
								'shop-pagination' => esc_html__('Pagination', 'econis'),
								'shop-infinity' => esc_html__('Infinity', 'econis'),
								'shop-loadmore' => esc_html__('Load More', 'econis'),
                             ),
                            'default' => 'shop-pagination',
                        ),
						array(
                            'id'=>'show_background_shop',
							'title' => esc_html__('Show Background Shop', 'econis'),
                            'type' => 'button_set',
                            'default' => 'no',
							'options' => array(
								'yes' => esc_html__('Yes', 'econis'),
								'no' => esc_html__('No', 'econis')
							),
                        ),
						array(
                            'id'=>'show_catagories_top',
							'title' => esc_html__('Show Categories Top', 'econis'),
                            'type' => 'button_set',
                            'default' => 'no',
							'options' => array(
								'yes' => esc_html__('Yes', 'econis'),
								'no' => esc_html__('No', 'econis')
							),
                        ),
						array(
                            'id'=>'limit_catagories_top',
							'title' => esc_html__('Limit Categories Top', 'econis'),
                            'type' => 'text',
							'required' => array('show_catagories_top','equals','yes'),
                            'default' => '9',
                        ),
						array(
                            'id'=>'limit_children_shop',
							'title' => esc_html__('Limit Children Categories Top', 'econis'),
                            'type' => 'text',
							'required' => array('show_catagories_top','equals','yes'),
                            'default' => '4',
                        ),
						array(
                            'id'=>'layout_shop',
							'title' => esc_html__('Style Layout Shop', 'econis'),
                            'type' => 'button_set',
							'options' => array(
								'1' => esc_html__('Style 1', 'econis'),
								'2' => esc_html__('Style 2', 'econis'),
								'3' => esc_html__('Style 3', 'econis'),
								'4' => esc_html__('Style 4', 'econis'),
								'5' => esc_html__('Style 5', 'econis'),
								'6' => esc_html__('Style 6', 'econis'),
								'7' => esc_html__('Style 7', 'econis'),
                             ),
                            'default' => '1',
                        ),	
						array(
                            'id'=>'show-bestseller-category',
                            'type' => 'switch',
                            'title' => esc_html__('Show Bestseller on Page Category', 'econis'),
                            'type' => 'button_set',
                            'default' => 'no',
							'options' => array(
								'yes' => esc_html__('Yes', 'econis'),
								'no' => esc_html__('No', 'econis')
							),
                        ),
						 array(
                            'id' => 'bestseller_limit',
                            'type' => 'text',
                            'title' => esc_html__('Shop product Bestseller', 'econis'),
                            'default' => '9',
							'required' => array('show-bestseller-category','equals','yes'),
                        ),
						array(
                            'id'=>'show-featured-category',
                            'type' => 'switch',
                            'title' => esc_html__('Show Featured on Page Category', 'econis'),
                            'type' => 'button_set',
                            'default' => 'no',
							'options' => array(
								'yes' => esc_html__('Yes', 'econis'),
								'no' => esc_html__('No', 'econis')
							),
                        ),
						 array(
                            'id' => 'featured_limit',
                            'type' => 'text',
                            'title' => esc_html__('Shop product Featured', 'econis'),
                            'default' => '9',
							'required' => array('show-featured-category','equals','yes'),
                        ),
                        array(
                            'id'=>'show-banner-category',
                            'type' => 'switch',
                            'title' => esc_html__('Show Banner Category', 'econis'),
                            'type' => 'button_set',
                            'default' => 'no',
							'options' => array(
								'yes' => esc_html__('Yes', 'econis'),
								'no' => esc_html__('No', 'econis')
							),
                        ),
						array(
							'id' => 'banner-shop',
							'type' => 'media',
							'title' => esc_html__('Banner Shop', 'econis'),
							'url'=> true,
							'readonly' => false,
							'required' => array('show-banner-category','equals','yes'),
							'sub_desc' => '',
							'default' => array(
								'url' => ""
							)
						),
						array(
                            'id' => 'link-banner-shop',
                            'type' => 'text',
                            'title' => esc_html__('Url Banner Shop', 'econis'),
                            'default' => '#',
							'required' => array('show-banner-category','equals','yes'),
                        ),
						array(
                            'id' => 'subtitle-banner-shop',
                            'type' => 'text',
                            'title' => esc_html__('Subtitle Banner Shop', 'econis'),
                            'default' => esc_html__('All Fruits Products', 'econis'),
							'required' => array('show-banner-category','equals','yes'),
                        ),
						array(
                            'id' => 'title-banner-shop',
                            'type' => 'text',
                            'title' => esc_html__('Title Banner Shop', 'econis'),
                            'default' => esc_html__('Natural, Raw & Organic Protein Powders ', 'econis'),
							'required' => array('show-banner-category','equals','yes'),
                        ),
						array(
                            'id' => 'desc-banner-shop',
                            'type' => 'text',
                            'title' => esc_html__('Description Banner Shop', 'econis'),
                            'default' => esc_html__('30% OFF', 'econis'),
							'required' => array('show-banner-category','equals','yes'),
                        ),
						array(
                            'id' => 'button-banner-shop',
                            'type' => 'text',
                            'title' => esc_html__('Button Banner Shop', 'econis'),
                            'default' => esc_html__('Shop now', 'econis'),
							'required' => array('show-banner-category','equals','yes'),
                        ),
                        array(
                            'id'=>'category-view-mode',
                            'type' => 'button_set',
                            'title' => esc_html__('View Mode', 'econis'),
                            'options' => econis_ct_category_view_mode(),
                            'default' => 'grid',
                        ),
                        array(
                            'id' => 'product_col_large',
                            'type' => 'button_set',
                            'title' => esc_html__('Product Listing column Desktop', 'econis'),
                            'options' => array(
                                    '2' => '2',
                                    '3' => '3',
                                    '4' => '4'                        
                                ),
                            'default' => '4',
                            'sub_desc' => esc_html__( 'Select number of column on Desktop Screen', 'econis' ),
                        ),
                        array(
                            'id' => 'product_col_medium',
                            'type' => 'button_set',
                            'title' => esc_html__('Product Listing column Medium Desktop', 'econis'),
                            'options' => array(
                                    '2' => '2',
                                    '3' => '3',
                                    '4' => '4'                          
                                ),
                            'default' => '3',
                            'sub_desc' => esc_html__( 'Select number of column on Medium Desktop Screen', 'econis' ),
                        ),
                        array(
                            'id' => 'product_col_sm',
                            'type' => 'button_set',
                            'title' => esc_html__('Product Listing column Ipad Screen', 'econis'),
                            'options' => array(
                                    '2' => '2',
                                    '3' => '3',
                                    '4' => '4'                          
                                ),
                            'default' => '3',
                            'sub_desc' => esc_html__( 'Select number of column on Ipad Screen', 'econis' ),
                        ),
						array(
                            'id' => 'product_col_xs',
                            'type' => 'button_set',
                            'title' => esc_html__('Product Listing column Mobile Screen', 'econis'),
                            'options' => array(
									'1' => '1',
                                    '2' => '2',
                                    '3' => '3'                        
                                ),
                            'default' => '2',
                            'sub_desc' => esc_html__( 'Select number of column on Mobile Screen', 'econis' ),
                        ),
                        array(
                            'id'=>'woo-show-rating',
                            'type' => 'switch',
                            'title' => esc_html__('Show Rating in Woocommerce Products Widget', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),						
						array(
                            'id'=>'show-category',
                            'type' => 'switch',
                            'title' => esc_html__('Show Category', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
                        array(
                            'id' => 'product_count',
                            'type' => 'text',
                            'title' => esc_html__('Shop pages show at product', 'econis'),
                            'default' => '12',
                            'sub_desc' => esc_html__( 'Type Count Product Per Shop Page', 'econis' ),
                        ),						
                        array(
                            'id'=>'category-image-hover',
                            'type' => 'switch',
                            'title' => esc_html__('Enable Image Hover Effect', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
                        array(
                            'id'=>'category-hover',
                            'type' => 'switch',
                            'title' => esc_html__('Enable Hover Effect', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
                        array(
                            'id'=>'product-wishlist',
                            'type' => 'switch',
                            'title' => esc_html__('Show Wishlist', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
						array(
							'id'=>'product-compare',
							'type' => 'switch',
							'title' => esc_html__('Show Compare', 'econis'),
							'default' => false,
							'on' => esc_html__('Yes', 'econis'),
							'off' => esc_html__('No', 'econis'),
						),						
                        array(
                            'id'=>'product_quickview',
                            'type' => 'switch',
                            'title' => esc_html__('Show Quick View', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis')
                        ),
                        array(
                            'id'=>'product-quickview-label',
                            'type' => 'text',
                            'required' => array('product-quickview','equals',true),
                            'title' => esc_html__('"Quick View" Text', 'econis'),
                            'default' => ''
                        ),
						array(
                            'id'=>'product-countdown',
                            'type' => 'switch',
                            'title' => esc_html__('Show Product Countdown', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis')
                        ),
						array(
                            'id'=>'checkout_page_style',
                            'title' => esc_html__('Checkout Page Style', 'econis'),
                            'type' => 'button_set',
                            'options' => array(
                                    'checkout-page-style-1' => 'Style 1',
                                    'checkout-page-style-2' => 'Style 2',                        
                                ),
                            'default' => 'style-1',
                        ),
                    )
                );
                $this->sections[] = array(
                    'icon' => 'icofont icofont-double-right',
                    'icon_class' => 'icon',
                    'subsection' => true,
                    'title' => esc_html__('Single Product', 'econis'),
                    'fields' => array(
						array(
							'id'=>'layout_sigle_product',
							'type' => 'button_set',
							'title' => esc_html__('Layout Single Product', 'econis'),
							'options' => array(
								'default' => esc_html__('Default', 'econis'),
								'box' => esc_html__('Box', 'econis'),
								'sidebar' => esc_html__('Sidebar', 'econis')
							),
							'default' => 'default'
						),
                        array(
                            'id'=>'product-stock',
                            'type' => 'switch',
                            'title' => esc_html__('Show "Out of stock" Status', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
						array(
                            'id'=>'show-sticky-cart',
                            'type' => 'switch',
                            'title' => esc_html__('Show Sticky Cart Product', 'econis'),
                            'default' => false,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
						array(
                            'id'=>'show-brands',
                            'type' => 'switch',
                            'title' => esc_html__('Show Brands', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
						array(
                            'id'=>'show-countdown',
                            'type' => 'switch',
                            'title' => esc_html__('Show CountDown', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
						array(
                            'id'=>'show-quick-buy',
                            'type' => 'switch',
                            'title' => esc_html__('Show Button Buy Now', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),						
                        array(
                            'id'=>'product-short-desc',
                            'type' => 'switch',
                            'title' => esc_html__('Show Short Description', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),					
                        array(
                            'id'=>'product-related',
                            'type' => 'switch',
                            'title' => esc_html__('Show Related Product', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
                        array(
                            'id'=>'product-related-count',
                            'type' => 'text',
                            'required' => array('product-related','equals',true),
                            'title' => esc_html__('Related Product Count', 'econis'),
                            'default' => '10'
                        ),
                        array(
                            'id'=>'product-related-cols',
                            'type' => 'button_set',
                            'required' => array('product-related','equals',true),
                            'title' => esc_html__('Related Product Columns', 'econis'),
                            'options' => econis_ct_related_product_columns(),
                            'default' => '4',
                        ),
                        array(
                            'id'=>'product-upsell',
                            'type' => 'switch',
                            'title' => esc_html__('Show Upsell Products', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),                      
                        array(
                            'id'=>'product-upsell-count',
                            'type' => 'text',
                            'required' => array('product-upsell','equals',true),
                            'title' => esc_html__('Upsell Products Count', 'econis'),
                            'default' => '10'
                        ),
                        array(
                            'id'=>'product-upsell-cols',
                            'type' => 'button_set',
                            'required' => array('product-upsell','equals',true),
                            'title' => esc_html__('Upsell Product Columns', 'econis'),
                            'options' => econis_ct_related_product_columns(),
                            'default' => '3',
                        ),
                        array(
                            'id'=>'product-crosssells',
                            'type' => 'switch',
                            'title' => esc_html__('Show Crooss Sells Products', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),                      
                        array(
                            'id'=>'product-crosssells-count',
                            'type' => 'text',
                            'required' => array('product-crosssells','equals',true),
                            'title' => esc_html__('Crooss Sells Products Count', 'econis'),
                            'default' => '10'
                        ),
                        array(
                            'id'=>'product-crosssells-cols',
                            'type' => 'button_set',
                            'required' => array('product-crosssells','equals',true),
                            'title' => esc_html__('Crooss Sells Product Columns', 'econis'),
                            'options' => econis_ct_related_product_columns(),
                            'default' => '3',
                        ),						
                        array(
                            'id'=>'product-hot',
                            'type' => 'switch',
                            'title' => esc_html__('Show "Hot" Label', 'econis'),
                            'desc' => esc_html__('Will be show in the featured product.', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
                        array(
                            'id'=>'product-hot-label',
                            'type' => 'text',
                            'required' => array('product-hot','equals',true),
                            'title' => esc_html__('"Hot" Text', 'econis'),
                            'default' => ''
                        ),
                        array(
                            'id'=>'product-sale',
                            'type' => 'switch',
                            'title' => esc_html__('Show "Sale" Label', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
                         array(
                            'id'=>'product-sale-percent',
                            'type' => 'switch',
                            'required' => array('product-sale','equals',true),
                            'title' => esc_html__('Show Sale Price Percentage', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),  
                        array(
                            'id'=>'product-share',
                            'type' => 'switch',
                            'title' => esc_html__('Show Social Share Links', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
						array(
							'id'=>'description-style',
							'type' => 'select',
							'title' => esc_html__('Description Style', 'econis'),
							'options' => array(
										'full-content' => esc_html__('Full Content', 'econis'),
										'tab' => esc_html__('Tab', 'econis'),
										),
							'default' => 'tab',
						),
                    )
                );
                $this->sections[] = array(
                    'icon' => 'icofont icofont-double-right',
                    'icon_class' => 'icon',
                    'subsection' => true,
                    'title' => esc_html__('Image Product', 'econis'),
                    'fields' => array(
                        array(
                            'id'=>'product-thumbs',
                            'type' => 'switch',
                            'title' => esc_html__('Show Thumbnails', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
                        ),
						array(
                            'id'=>'layout-thumbs',
                            'type' => 'button_set',
                            'title' => esc_html__('Layouts Product', 'econis'),
                            'options' => array('zoom' => esc_html__('Zoom', 'econis'),
												'scroll' => esc_html__('Scroll', 'econis'),
												'special' => esc_html__('Special', 'econis'),
											),	
                            'default' => 'zoom',
                        ),
                        array(
                            'id'=>'position-thumbs',
                            'type' => 'button_set',
                            'title' => esc_html__('Position Thumbnails', 'econis'),
                            'options' => array('left' => esc_html__('Left', 'econis'),
												'right' => esc_html__('Right', 'econis'),
												'bottom' => esc_html__('Bottom', 'econis'),
												'outsite' => esc_html__('Outsite', 'econis')),
                            'default' => 'bottom',
							'required' => array('product-thumbs','equals',true),
                        ),						
                        array(
                            'id' => 'product-thumbs-count',
                            'type' => 'button_set',
                            'title' => esc_html__('Thumbnails Count', 'econis'),
                            'options' => array(
                                    '2' => '2',
                                    '3' => '3',
                                    '4' => '4', 
									'5' => '5', 									
                                    '6' => '6'                          
                                ),
							'default' => '4',
							'required' => array('product-thumbs','equals',true),
                        ),
						 array(
                            'id' => 'video-style',
                            'type' => 'button_set',
                            'title' => esc_html__('Video Style', 'econis'),
                            'options' => array(
                                    'popup' => 'Popup',
                                    'inner' => 'Inner',                          
                                ),
							'default' => 'inner',
                        ),
                        array(
                            'id'=>'zoom-type',
                            'type' => 'button_set',
                            'title' => esc_html__('Zoom Type', 'econis'),
                            'options' => array(
									'inner' => esc_html__('Inner', 'econis'),
									'window' => esc_html__('Window', 'econis'),
									'lens' => esc_html__('Lens', 'econis')
									),
                            'default' => 'inner',
							'required' => array('layout-thumbs','equals',"zoom"),
                        ),
                        array(
                            'id'=>'zoom-scroll',
                            'type' => 'switch',
                            'title' => esc_html__('Scroll Zoom', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
							'required' => array('layout-thumbs','equals',"zoom"),
                        ),
                        array(
                            'id'=>'zoom-border',
                            'type' => 'text',
                            'title' => esc_html__('Border Size', 'econis'),
                            'default' => '2',
							'required' => array('layout-thumbs','equals',"zoom"),
                        ),
                        array(
                            'id'=>'zoom-border-color',
                            'type' => 'color',
                            'title' => esc_html__('Border Color', 'econis'),
                            'default' => '#f9b61e',
							'required' => array('layout-thumbs','equals',"zoom"),
                        ),                      
                        array(
                            'id'=>'zoom-lens-size',
                            'type' => 'text',
                            'required' => array('zoom-type','equals',array('lens')),
                            'title' => esc_html__('Lens Size', 'econis'),
                            'default' => '200',
							'required' => array('layout-thumbs','equals',"zoom"),
                        ),
                        array(
                            'id'=>'zoom-lens-shape',
                            'type' => 'button_set',
                            'required' => array('zoom-type','equals',array('lens')),
                            'title' => esc_html__('Lens Shape', 'econis'),
                            'options' => array('round' => esc_html__('Round', 'econis'), 'square' => esc_html__('Square', 'econis')),
                            'default' => 'square',
							'required' => array('layout-thumbs','equals',"zoom"),
                        ),
                        array(
                            'id'=>'zoom-contain-lens',
                            'type' => 'switch',
                            'required' => array('zoom-type','equals',array('lens')),
                            'title' => esc_html__('Contain Lens Zoom', 'econis'),
                            'default' => true,
                            'on' => esc_html__('Yes', 'econis'),
                            'off' => esc_html__('No', 'econis'),
							'required' => array('layout-thumbs','equals',"zoom"),
                        ),
                        array(
                            'id'=>'zoom-lens-border',
                            'type' => 'text',
                            'required' => array('zoom-type','equals',array('lens')),
                            'title' => esc_html__('Lens Border', 'econis'),
                            'default' => true,
							'required' => array('layout-thumbs','equals',"zoom")
                        ),
                    )
                );
            endif;
            // Blog Settings  -------------
            $this->sections[] = array(
                'icon' => 'icofont icofont-ui-copy',
                'icon_class' => 'icon',
                'title' => esc_html__('Blog', 'econis'),
                'fields' => array(              
                )
            );      
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Blog & Post Archives', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'post-format',
                        'type' => 'switch',
                        'title' => esc_html__('Show Post Format', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'hot-label',
                        'type' => 'text',
                        'title' => esc_html__('"HOT" Text', 'econis'),
                        'desc' => esc_html__('Hot post label', 'econis'),
                        'default' => ''
                    ),
                    array(
                        'id'=>'sidebar_blog',
                        'type' => 'image_select',
                        'title' => esc_html__('Page Layout', 'econis'),
                        'options' => $page_layouts,
                        'default' => 'left'
                    ),
                    array(
                        'id' => 'layout_blog',
                        'type' => 'button_set',
                        'title' => esc_html__('Layout Blog', 'econis'),
                        'options' => array(
                                'list'  =>  esc_html__( 'List', 'econis' ),
                                'grid' =>  esc_html__( 'Grid', 'econis' ),
								'modern' =>  esc_html__( 'Modern', 'econis' ),
								'standar' =>  esc_html__( 'Standar', 'econis' )
                        ),
                        'default' => 'standar',
                        'sub_desc' => esc_html__( 'Select style layout blog', 'econis' ),
                    ),
                    array(
                        'id' => 'blog_col_large',
                        'type' => 'button_set',
                        'title' => esc_html__('Blog Listing column Desktop', 'econis'),
                        'required' => array('layout_blog','equals','grid'),
                        'options' => array(
                                '2' => '2',
                                '3' => '3',
                                '4' => '4',                         
                                '6' => '6'                          
                            ),
                        'default' => '4',
                        'sub_desc' => esc_html__( 'Select number of column on Desktop Screen', 'econis' ),
                    ),
                    array(
                        'id' => 'blog_col_medium',
                        'type' => 'button_set',
                        'title' => esc_html__('Blog Listing column Medium Desktop', 'econis'),
                        'required' => array('layout_blog','equals','grid'),
                        'options' => array(
                                '2' => '2',
                                '3' => '3',
                                '4' => '4',                         
                                '6' => '6'                          
                            ),
                        'default' => '3',
                        'sub_desc' => esc_html__( 'Select number of column on Medium Desktop Screen', 'econis' ),
                    ),   
                    array(
                        'id' => 'blog_col_sm',
                        'type' => 'button_set',
                        'title' => esc_html__('Blog Listing column Ipad Screen', 'econis'),
                        'required' => array('layout_blog','equals','grid'),
                        'options' => array(
                                '2' => '2',
                                '3' => '3',
                                '4' => '4',                         
                                '6' => '6'                          
                            ),
                        'default' => '3',
                        'sub_desc' => esc_html__( 'Select number of column on Ipad Screen', 'econis' ),
                    ),   					
                    array(
                        'id'=>'archives-author',
                        'type' => 'switch',
                        'title' => esc_html__('Show Author', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'archives-comments',
                        'type' => 'switch',
                        'title' => esc_html__('Show Count Comments', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),                  
                    array(
                        'id'=>'blog-excerpt',
                        'type' => 'switch',
                        'title' => esc_html__('Show Excerpt', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'list-blog-excerpt-length',
                        'type' => 'text',
                        'required' => array('blog-excerpt','equals',true),
                        'title' => esc_html__('List Excerpt Length', 'econis'),
                        'desc' => esc_html__('The number of words', 'econis'),
                        'default' => '50',
                    ),
                    array(
                        'id'=>'grid-blog-excerpt-length',
                        'type' => 'text',
                        'required' => array('blog-excerpt','equals',true),
                        'title' => esc_html__('Grid Excerpt Length', 'econis'),
                        'desc' => esc_html__('The number of words', 'econis'),
                        'default' => '12',
                    ),                  
                )
            );
            $this->sections[] = array(
                'icon' => 'icofont icofont-double-right',
                'icon_class' => 'icon',
                'subsection' => true,
                'title' => esc_html__('Single Post', 'econis'),
                'fields' => array(
                    array(
                        'id'=>'post-single-layout',
                        'type' => 'select',
                        'title' => esc_html__('Page Layout', 'econis'),
                        'options' => array(
								'sidebar' =>  esc_html__( 'Sidebar', 'econis' ),
                                'one_column' =>  esc_html__( 'One Column', 'econis' ),
								'prallax_image' =>  esc_html__( 'Prallax Image', 'econis' ),
								'simple_title' =>  esc_html__( 'Simple Title', 'econis' ),
								'sticky_title' =>  esc_html__( 'Sticky Title', 'econis' )
                        ),
                        'default' => 'sidebar'
                    ),
                    array(
                        'id'=>'post-title',
                        'type' => 'switch',
                        'title' => esc_html__('Show Title', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'post-author',
                        'type' => 'switch',
                        'title' => esc_html__('Show Author Info', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
                    ),
                    array(
                        'id'=>'post-comments',
                        'type' => 'switch',
                        'title' => esc_html__('Show Comments', 'econis'),
                        'default' => true,
                        'on' => esc_html__('Yes', 'econis'),
                        'off' => esc_html__('No', 'econis'),
					)
				)
			);	
            $this->sections[] = array(
				'id' => 'wbc_importer_section',
				'title'  => esc_html__( 'Demo Importer', 'econis' ),
				'icon'   => 'fa fa-cloud-download',
				'desc'   => wp_kses( 'Increase your max execution time, try 600 I know its high but trust me.<br>
				Increase your PHP memory limit, try 512MB.<br>
				1. The import process will work best on a clean install. You can use a plugin such as WordPress Reset to clear your data for you.<br>
				2. Ensure all plugins are installed beforehand, e.g. WooCommerce - any plugins that you add content to.<br>
				3. Be patient and wait for the import process to complete. It can take up to 3-5 minutes.<br>
				4. Enjoy','social' ),				
				'fields' => array(
					array(
						'id'   => 'wbc_demo_importer',
						'type' => 'wbc_importer'
					)
				)
            );			
        }
        public function setHelpTabs() {
        }
        public function setArguments() {
            $theme = wp_get_theme(); // For use with some settings. Not necessary.
            $this->args = array(
                'opt_name'          => 'econis_settings',
                'display_name'      => $theme->get('Name') . ' ' . esc_html__('Theme Options', 'econis'),
                'display_version'   => esc_html__('Theme Version: ', 'econis') . econis_version,
                'menu_type'         => 'submenu',
                'allow_sub_menu'    => true,
                'menu_title'        => esc_html__('Theme Options', 'econis'),
                'page_title'        => esc_html__('Theme Options', 'econis'),
                'footer_credit'     => esc_html__('Theme Options', 'econis'),
                'google_api_key' => 'AIzaSyAX_2L_UzCDPEnAHTG7zhESRVpMPS4ssII',
                'disable_google_fonts_link' => true,
                'async_typography'  => false,
                'admin_bar'         => false,
                'admin_bar_icon'       => 'dashicons-admin-generic',
                'admin_bar_priority'   => 50,
                'global_variable'   => '',
                'dev_mode'          => false,
                'customizer'        => false,
                'compiler'          => false,
                'page_priority'     => null,
                'page_parent'       => 'themes.php',
                'page_permissions'  => 'manage_options',
                'menu_icon'         => '',
                'last_tab'          => '',
                'page_icon'         => 'icon-themes',
                'page_slug'         => 'econis_settings',
                'save_defaults'     => true,
                'default_show'      => false,
                'default_mark'      => '',
                'show_import_export' => true,
                'show_options_object' => false,
                'transient_time'    => 60 * MINUTE_IN_SECONDS,
                'output'            => true,
                'output_tag'        => true,
                'database'              => '',
                'system_info'           => false,
                'hints' => array(
                    'icon'          => 'icon-question-sign',
                    'icon_position' => 'right',
                    'icon_color'    => 'lightgray',
                    'icon_size'     => 'normal',
                    'tip_style'     => array(
                        'color'         => 'light',
                        'shadow'        => true,
                        'rounded'       => false,
                        'style'         => '',
                    ),
                    'tip_position'  => array(
                        'my' => 'top left',
                        'at' => 'bottom right',
                    ),
                    'tip_effect'    => array(
                        'show'          => array(
                            'effect'        => 'slide',
                            'duration'      => '500',
                            'event'         => 'mouseover',
                        ),
                        'hide'      => array(
                            'effect'    => 'slide',
                            'duration'  => '500',
                            'event'     => 'click mouseleave',
                        ),
                    ),
                ),
                'ajax_save'                 => true,
                'use_cdn'                   => true,
            );
            // Panel Intro text -> before the form
            if (!isset($this->args['global_variable']) || $this->args['global_variable'] !== false) {
                if (!empty($this->args['global_variable'])) {
                    $v = $this->args['global_variable'];
                } else {
                    $v = str_replace('-', '_', $this->args['opt_name']);
                }
            }
            $this->args['intro_text'] = sprintf('<p style="color: #0088cc">'.wp_kses('Please regenerate again default css files in <strong>Skin > Compile Default CSS</strong> after <strong>update theme</strong>.', 'econis').'</p>', $v);
        }           
    }
	if ( !function_exists( 'wbc_extended_example' ) ) {
		function wbc_extended_example( $demo_active_import , $demo_directory_path ) {
			reset( $demo_active_import );
			$current_key = key( $demo_active_import );	
			if ( isset( $demo_active_import[$current_key]['directory'] ) && !empty( $demo_active_import[$current_key]['directory'] )) {
				//Import Sliders
				if ( class_exists( 'RevSlider' ) ) {
					$wbc_sliders_array = array(
						'econis' => array('slider-1.zip','slider-2.zip','slider-3.zip','slider-4.zip','slider-5.zip','slider-6.zip','slider-7.zip','slider-8.zip')
					);
					$wbc_slider_import = $wbc_sliders_array[$demo_active_import[$current_key]['directory']];
					if( is_array( $wbc_slider_import ) ){
						foreach ($wbc_slider_import as $slider_zip) {
							if ( !empty($slider_zip) && file_exists( $demo_directory_path.'rev_slider/'.$slider_zip ) ) {
								$slider = new RevSlider();
								$slider->importSliderFromPost( true, true, $demo_directory_path.'rev_slider/'.$slider_zip );
							}
						}
					}else{
						if ( file_exists( $demo_directory_path.'rev_slider/'.$wbc_slider_import ) ) {
							$slider = new RevSlider();
							$slider->importSliderFromPost( true, true, $demo_directory_path.'rev_slider/'.$wbc_slider_import );
						}
					}
				}		
				// Import Template Kit
				if ( class_exists( '\Elementor\Plugin' ) ) {
					$import_settings = [];
					$import_settings['referrer'] = "kit-library";
					$zip_path = $demo_directory_path."elementor-kit.zip";
					$import_export_module = \Elementor\Plugin::$instance->app->get_component( 'import-export' );
					$import = $import_export_module->import_kit( $zip_path, $import_settings );
				}
				// Replace URL
				if ( class_exists( '\Elementor\Utils' ) ) {
					\Elementor\Utils::replace_urls( url_demo, site_url() );
				}
				// Setting Menus
				$primary = get_term_by( 'name', 'Main menu', 'nav_menu' );
				$primary_vertical   	= get_term_by( 'name', 'Vertical Menu', 'nav_menu' );
				$primary_mostsearch   	= get_term_by( 'name', 'Most Search Menu', 'nav_menu' );
				$primary_topbar   		= get_term_by( 'name', 'Topbar Menu', 'nav_menu' );
				if ( isset( $primary->term_id ) && isset( $primary_vertical->term_id ) && isset( $primary_mostsearch->term_id ) && isset( $primary_topbar->term_id ) ) {
					set_theme_mod( 'nav_menu_locations', array(
							'main_navigation' 	=> $primary->term_id,
							'vertical_menu' 	=> $primary_vertical->term_id,
							'topbar_menu' 		=> $primary_topbar->term_id	
						)
					);
				}
				// Set HomePage
				$home_page = 'Home 1';
				$page = get_page_by_title( $home_page );
				if ( isset( $page->ID ) ) {
					update_option( 'page_on_front', $page->ID );
					update_option( 'show_on_front', 'page' );
				}					
			}
		}
		// Uncomment the below
		add_action( 'wbc_importer_after_content_import', 'wbc_extended_example', 10, 2 );
	}
    global $reduxEconisSettings;
    $reduxEconisSettings = new Redux_Framework_econis_settings();
}