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
class Bwp_Product_Categories extends Widget_Base {
	public function get_name() {
		return 'bwp_product_categories';
	}
	public function get_title() {
		return __( 'Wpbingo Product Categories', 'wpbingo' );
	}
	public function get_icon() {
		return 'fa fa-users';
	}	
	public function get_categories() {
		return [ 'general' ];
	}
	protected function register_controls() {
		$terms = get_terms( 'product_cat', array( 'hide_empty' => false ) );
		foreach( $terms as $cat ){
			$term[$cat->slug] = $cat -> name;
		}
		$number = array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9);
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
			'subtitle',
			[
				'label' => __( 'Subtitle', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'placeholder' => __( 'Type your Extra Subtitle here', 'wpbingo' ),
			]
		);		
		$this->add_control(
			'category',
			[
				'label' => __( 'Categories', 'wpbingo' ),
				'multiple' => true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'default' => array(),
				'options' => $term,
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
			'show_name',
			[
				'label' => __( 'Show Name Categories', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0',
				'condition'   => [
                    'layout' => ["slider","slider2","slider3"],
                ]
			]
		);
		$this->add_control(
			'show_count',
			[
				'label' => __( 'Show Count Product Categories', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0',
				'condition'   => [
                    'layout' => ["slider","slider2","slider3"],
                ]
			]
		);
		$this->add_control(
			'show_thumbnail',
			[
				'label' => __( 'Show Image Categories', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0',
				'condition'   => [
                    'layout' => ["slider","slider2","slider3"],
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
                    'layout' => ["slider","slider2","slider3"],
                ]
			]
		);
		$this->add_control(
			'show_thumbnail1',
			[
				'label' => __( 'Show Thumbnail Categories', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0',
				'condition'   => [
                    'layout' => ["slider","slider2","slider3"],
                ]
			]
		);
		$this->add_control(
			'limit_children',
			[
				'label' => __( 'Number of Child categories', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '4',
				'condition'   => [
                    'layout' => ["category-children"],
                ]
			]
		);
		$this->add_responsive_control(
			'align',
			[
				'label' => __( 'Alignment', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => __( 'Left', 'wpbingo' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'wpbingo' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'wpbingo' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => __( 'Justified', 'wpbingo' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .item-product-cat-content' => 'text-align: {{VALUE}};',
				],
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
                    'layout' => ["slider", "slider2" ,"slider3"],
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
                    'layout' => ["slider","slider2","slider3"],
                ]				
			]
		);
		$this->add_control(
			'layout',
			[
				'label' => __( 'Layout', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'slider',
				'options' => [
					'slider' 			 	=> __( 'Slider', 'wpbingo' ),
					'slider2' 			 	=> __( 'Slider 2', 'wpbingo' ),
					'slider3' 			 	=> __( 'Slider 3', 'wpbingo' ),
				],
			]
		);		
		$this->end_controls_section();
		
		$this->start_controls_section(
			'categories_title_style',
			[
				'label' => __( 'Title', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->start_controls_tabs( 'tabs_title_hover');
		$this->start_controls_tab(
			'tab_title_normal',
			[
				'label' => __( 'Normal', 'wpbingo' ),
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
					'{{WRAPPER}} .bwp-woo-categories .item-title a' => 'color: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'tab_title_hover',
			[
				'label' => __( 'Hover', 'wpbingo' ),
			]
		);
		$this->add_control(
			'title_color_hover',
			[
				'label' => __( 'Title Color Hover', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-title a:hover' => 'color: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'title_typography',
				'selector' => '{{WRAPPER}} .bwp-woo-categories .item-title',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
			]
		);
		$this->add_responsive_control(
			'title_margin',
			[
				'label' => __( 'Margin', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'title_padding',
			[
				'label' => __( 'Padding', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'categories_count_style',
			[
				'label' => __( 'Count', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_control(
			'count_color',
			[
				'label' => __( 'Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-count' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);		
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'count_typography',
				'selector' => '{{WRAPPER}} .bwp-woo-categories .item-count',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);		
		$this->add_responsive_control(
			'count_bottom_space',
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
					'{{WRAPPER}} .bwp-woo-categories .item-count' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_image_style',
			[
				'label' => __( 'Image', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_responsive_control(
			'width_image',
			[
				'label' => __( 'Max Width', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content > a >div img' => 'max-width: {{SIZE}}{{UNIT}};margin:auto;',
				],
			]
		);
		$this->add_responsive_control(
			'height_image',
			[
				'label' => __( 'Min Height', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content > a >div img' => 'min-height: {{SIZE}}{{UNIT}};object-fit: cover;',
				],
			]
		);
		$this->add_control(
			'border_image_radius',
			[
				'label' => __( 'Border Radius', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content > a >div' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',	
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content > a >div img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',					
				],
			]
		);
		$this->add_responsive_control(
			'image_margin',
			[
				'label' => __( 'Margin', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content > a >div' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_control(
			'image_hover',
			[
				'label' => __( 'Hover Animation', 'wpbingo' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_content_style',
			[
				'label' => __( 'Box Content', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_control(
			'content_color',
			[
				'label' => __( 'Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content' => 'background: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);
		$this->add_responsive_control(
			'align_dot',
			[
				'label' => __( 'Alignment Dot', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => __( 'Left', 'wpbingo' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'wpbingo' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'wpbingo' ),
						'icon' => 'eicon-text-align-right',
					]
				],
				'default' => 'center',
				'selectors' => [
					'{{WRAPPER}}  .bwp-woo-categories .slick-dots' => 'text-align: {{VALUE}};',
				],
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'border',
				'selector' => '{{WRAPPER}} .bwp-woo-categories .item-product-cat-content',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'border_radius_content',
			[
				'label' => __( 'Border Radius', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'padding_content',
			[
				'label' => __( 'Padding', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'margin_content',
			[
				'label' => __( 'Margin', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->add_control(
			'show_box_shadow',
			[
				'label' => _x( 'Show Box Shadow','wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'no' => __( 'No','wpbingo' ),
					'yes' => __( 'Yes','wpbingo' ),
				],
				'default' => 'no',
			]
		);
		$this->add_control(
			'box_shadow',
			[
				'label' => _x( 'Box Shadow','wpbingo' ),
				'type' => \Elementor\Controls_Manager::BOX_SHADOW,
				'selectors' => [
					'{{WRAPPER}} .bwp-woo-categories .item-product-cat-content' => 'box-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}} {{box_shadow_position.VALUE}};',
				],
				'separator' => 'before',
				'condition'   => [
                    'show_box_shadow' => ["yes"],
                ]
			]
		);
		$this->add_control(
			'box_shadow_position',
			[
				'label' => _x( 'Position','wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					' ' => __( 'Outline','wpbingo' ),
					'inset' => __( 'Inset','wpbingo' ),
				],
				'default' => ' ',
				'render_type' => 'ui',
				'condition'   => [
                    'show_box_shadow' => ["yes"],
                ]
			]
		);
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		extract( shortcode_atts(
			array(
				'title1' => '',	
				'subtitle' => '',				
				'orderby' => '',
				'category' => '',
				'item_row' => 1,
				'numberposts' => 5,
				'columns' => 4,
				'columns1440' => 4,
				'columns1' => 4,
				'columns2' => 3,
				'columns3' => 2,
				'columns4' => 1,
				'show_name' => 1,
				'show_count' => 1,
				'show_thumbnail' => 1,
				'show_thumbnail1' => 1,
				'limit_children' => 4,
				'show_icon' => 0,
				'show_nav'	=> '0',
				'show_pag'	=> '0',
				'layout'  => 'slider',
				'image_hover' => '',
			), $settings )
		);
		if( $layout == 'slider' || $layout == 'slider2' || $layout == 'slider3' ){
			include( WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-product-categories/slider.php' );
		}
	}
}
