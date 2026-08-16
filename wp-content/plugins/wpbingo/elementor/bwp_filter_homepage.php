<?php
namespace ElementorWpbingo\Widgets;
use Elementor\Widget_Base;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
if ( ! defined( 'ABSPATH' ) ) exit;
class Bwp_Filter_Homepage extends Widget_Base {
	public function get_name() {
		return 'bwp_filter_homepage';
	}
	public function get_title() {
		return __( 'Wpbingo Filter Homepage', 'wpbingo' );
	}
	public function get_icon() {
		return 'fa fa-sliders';
	}	
	public function get_categories() {
		return [ 'general' ];
	}
	protected function register_controls() {
		$terms = get_terms( 'product_cat', array( 'hide_empty' => false ) );
		$term = array( 'all' => __( 'All Categories', "wpbingo" ) );
		foreach( $terms as $cat ){
			$term[$cat->slug] = $cat -> name;
		}	
		$number = array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7);
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Content', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);		
		$this->add_control(
			'title1',
			[
				'label' => __( 'Title', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'placeholder' => __( 'Type your title here', 'wpbingo' ),
			]
		);
		$this->add_control(
			'numberposts',
			[
				'label' => __( 'Number Of Products', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '5',
				'placeholder' => __( 'Number Of Products', 'wpbingo' ),
			]
		);	
		$this->add_control(
			'item_row',
			[
				'label' => __( 'Number row per column', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array('1' => 1, '2' => 2, '3' => 3),
				'default' => 1
			]
		);		
		$this->add_control(
			'columns',
			[
				'label' => __( 'Number of Columns >1440px', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $number,
				'default' => 1
			]
		);
		$this->add_control(
			'columns1440',
			[
				'label' => __( 'Number of Columns 1200px to 1440px', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $number,
				'default' => 1
			]
		);		
		$this->add_control(
			'columns1',
			[
				'label' => __( 'Number of Columns on 992px to 1199px', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $number,
				'default' => 1
			]
		);
		$this->add_control(
			'columns2',
			[
				'label' => __( 'Number of Columns on 768px to 991px', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $number,
				'default' => 1
			]
		);
		$this->add_control(
			'columns3',
			[
				'label' => __( 'Number of Columns on 480px to 767px', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $number,
				'default' => 1
			]
		);
		$this->add_control(
			'columns4',
			[
				'label' => __( 'Number of Columns in 480px or less than', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $number,
				'default' => 1
			]
		);
		$this->add_control(
			'style_product',
			[
				'label' => __( 'Style content product', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7),
				'default' => 1
			]
		);
		$this->add_control(
			'show_nav',
			[
				'label' => __( 'Show Navigation', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0',
				'condition'   => [
                    'layout' => ["tab_category_slider","tab_category_icon","tab_product_slider","tab_category_slider_2","tab_product_slider_2","tab_product_slider_3",
					"tab_product_slider_4","tab_product_slider_5","tab_category_slider_3","tab_category_slider_4","tab_category_slider_5","tab_category_image","tab_category_scroll"],
                ]				
			]
		);
		$this->add_control(
			'show_pag',
			[
				'label' => __( 'Show Pagination', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0',
				'condition'   => [
                    'layout' => ["tab_category_slider","tab_category_icon","tab_product_slider","tab_category_slider_2","tab_product_slider_2","tab_product_slider_3",
					"tab_product_slider_4","tab_category_slider_3","tab_category_slider_4","tab_category_slider_5","tab_category_image"],
                ]				
			]
		);
		$this->add_control(
			'select_order',
			[
				'label' => __( 'Order Product', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => array('date' => 'Latest', 'rating' => 'Top Rating', 'popularity' => 'Best Selling', 'featured' => 'Featured'),
				'default' => 'date',
				'condition'   => [
                    'layout' => ["tab_category_default","tab_category_slider","tab_category_icon","tab_category_slider_2","tab_category_slider_3","tab_category_slider_4",
					"tab_category_slider_5","tab_category_image","tab_category_scroll"],
                ]				
			]
		);
		$this->add_control(
			'category',
			[
				'label' => __( 'Select Categories', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $term,
				'default' => array(),
				'condition'   => [
                    'layout' => ["default","slider","loadmore","tab_category_default","tab_category_slider","tab_category_icon","tab_category_slider_2","tab_category_slider_3",
					"tab_category_slider_4","tab_category_slider_5","tab_category_image","tab_category_scroll"],
                ]				
			]
		);		
		$this->add_control(
			'select_category',
			[
				'label' => __( 'Category', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $term,
				'default' => '',
				'condition'   => [
                    'layout' => ["tab_product_default","tab_product_default2","tab_product_slider","tab_product_slider_2","tab_product_slider_3","tab_product_slider_4"],
                ]				
			]
		);
		$this->add_control(
			'show_icon',
			[
				'label' => __( 'Show Icon Categories', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0',
				'condition'   => [
                    'layout' => ["tab_category_default"],
                ]				
			]
		);
		$this->add_control(
			'checkbox_order',
			[
				'label' => __( 'Order Product', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => array('date' => 'Latest', 'rating' => 'Top Rating', 'popularity' => 'Best Selling', 'featured' => 'Featured'),
				'default' => array(),
				'condition'   => [
                    'layout' => ["tab_product_default","tab_product_default2","tab_product_slider","tab_product_slider_2","tab_product_slider_3","tab_product_slider_4","loadmore"],
                ]				
			]
		);
		$this->add_control(
			'layout',
			[
				'label' => __( 'Layout', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'default',
				'options' => [
					'default' 	=> __( 'Default', 'wpbingo' ),
					'tab_category_slider'  	=> __( 'Tab Category Slider', 'wpbingo' ),
					'tab_category_slider_2'  	=> __( 'Tab Category Slider 2', 'wpbingo' ),
					'tab_product_slider'  		=> __( 'Tab Product Slider', 'wpbingo' ),
				],
			]
		);		
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_title_style',
			[
				'label' => __( 'Title', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_control(
			'title_color',
			[
				'label' => __( 'Title Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-filter-homepage .title-block h2' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'title_typography',
				'selector' => '{{WRAPPER}} .bwp-filter-homepage .title-block h2',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
			]
		);
		$this->add_responsive_control(
			'title_bottom_space',
			[
				'label' => __( 'Spacing', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-filter-homepage .title-block h2' => 'margin-bottom: {{SIZE}}{{UNIT}};margin-top:0;',
				],
			]
		);
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		extract( shortcode_atts(
			array(
				'title1' => '',
				'category' => '',
				'select_category' => 'all',
				'show_icon'	=> '0',				
				'show_thumbnail1' => 1,
				'show_thumbnail' => 1,
				'numberposts' => 8,
				'columns1440' => 5,
				'columns' => 4,
				'columns1' => 4,
				'columns2' => 3,
				'columns3' => 2,
				'columns4' => 1,
				'style_product' => 1,
				'show_nav'	=> '0',
				'show_pag'	=> '1',
				'select_order' => 'date',
				'checkbox_order' => '',
				'item_row'	=> 1,
				'layout'  => 'default',
			), $settings )
		);
		if( $layout == 'default' ){
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-filter-homepage/default.php' );
		}elseif( $layout == 'slider' ){
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-filter-homepage/slider.php' );
		}elseif( $layout == 'loadmore' ){
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-filter-homepage/loadmore.php' );
		}elseif( $layout == 'tab_category_default' ){
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-filter-homepage/tab-category/default.php' );
		}elseif( $layout == 'tab_category_slider' || $layout == 'tab_category_slider_2' ){
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-filter-homepage/tab-category/slider.php' );
		}elseif( $layout == 'tab_product_slider' ){
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-filter-homepage/tab-product/slider.php' );
		}	
	}
	function woocommerce_filter_homepage_price($default_min_price,$default_max_price){	 
		$currency_symbol = get_woocommerce_currency_symbol();
		echo '
		<div class="bwp-filter-price">
		    <h2>'.esc_html__('Choose Price','wpbingo').'</h2>
			<div class="bwp_slider_price" data-min="'.$default_min_price.'" data-max="'.$default_max_price.'"></div>
			<div class="price-input">
				<span>'.esc_html__('Range : ','wpbingo').'</span>
				'.$currency_symbol.'<span class="text-price-filter text-price-filter-min-text">'.$default_min_price.'</span> -
				'.$currency_symbol.'<span class="text-price-filter text-price-filter-max-text">'.$default_max_price.'</span>	
				<input class="price-filter-min-text hidden"  type="text" value="'.$default_min_price.'">
				<input class="price-filter-max-text hidden"  type="text" value="'.$default_max_price.'">
			</div>
		</div>';
	}	
	function woocommerce_filter_homepage_atribute(){
		$attribute_taxonomies = wc_get_attribute_taxonomies();	
		foreach( $attribute_taxonomies as $att ){
			$taxonomy   = 	wc_attribute_taxonomy_name( $att->attribute_name );
			$orderby 	=	$att->attribute_orderby;
			if($orderby ){
				switch ( $orderby ) {
					case 'name' :
						$get_terms_args['orderby']    = 'name';
						$get_terms_args['menu_order'] = false;
					break;
					case 'id' :
						$get_terms_args['orderby']    = 'id';
						$get_terms_args['order']      = 'ASC';
						$get_terms_args['menu_order'] = false;
					break;
					case 'menu_order' :
						$get_terms_args['menu_order'] = 'ASC';
					break;
				}
			}else{
				$get_terms_args    = array();
			}
			$tax_query = array();
			$get_terms_args['tax_query'] = $tax_query;
			$terms = get_terms( $taxonomy, $get_terms_args );
			if(count($terms)>0):?>
			<div class="bwp-filter-<?php echo esc_attr($att->attribute_name);?>">
				<h2><?php echo esc_html__('Choose ','wpbingo'); ?><?php echo ucfirst( $att->attribute_name ); ?></h2>
				<?php 								
					if(isset($att->attribute_type) && $att->attribute_type == "color"){?>	
						<ul class="<?php echo esc_attr( 'pa_'.$att->attribute_name ); ?>">
							<?php			
								foreach( $terms as $term ){
										$color = get_term_meta( $term->term_id, 'color', true ); 
										echo '<li data-value="'. esc_attr( $term -> slug ) .'">';
												echo '<span class="color" style="background-color:'.esc_attr($color).';"></span>';
												echo '<span>'. esc_html( $term->name ) .'</span>';
										echo '</li> ';
								} ?>
						</ul>						
					<?php }else{?>
						<ul class="<?php echo esc_attr( 'pa_'.$att->attribute_name ); ?>">
							<?php			
								foreach( $terms as $term ){
										echo '<li data-value="'. esc_attr( $term -> slug ) .'">';
												echo '<span>'. esc_html( $term->name ) .'</span>';
										echo '</li> ';
								} ?>
						</ul>
				<?php } ?>
			</div>
			<?php endif;
		}		
	}	
}