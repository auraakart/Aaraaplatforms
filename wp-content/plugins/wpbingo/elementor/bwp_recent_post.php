<?php
namespace ElementorWpbingo\Widgets;
use Elementor\Widget_Base;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class Bwp_Recent_Post extends Widget_Base {
	public function get_name() {
		return 'bwp_recent_post';
	}
	public function get_title() {
		return __( 'Wpbingo Recent Post', 'wpbingo' );
	}
	public function get_icon() {
		return 'fa fa-pencil-square-o';
	}	
	public function get_categories() {
		return [ 'general' ];
	}
	protected function register_controls() {
		$number = array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6);
		$categories = get_categories();
		$terms = array();
		if($categories){
			$terms = array( '' => __( 'All Categories', "wpbingo" ) );
			foreach ( $categories as $category ) {
				$terms[$category->slug] = $category->name;
			}			
		}
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
			'description',
			[
				'label' => __( 'Description', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'placeholder' => __( 'Type your Description here', 'wpbingo' ),
			]
		);		
		$this->add_control(
			'category',
			[
				'label' => __( 'Category', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $terms,
			]
		);
		$this->add_control(
			'limit',
			[
				'label' => __( 'Number of Posts', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 6,
				'description' => __( 'Type your Number of Posts', 'wpbingo' ),
			]
		);
		$this->add_control(
			'length',
			[
				'label' => __( 'Excerpt length (in words)', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 25,
				'description' => __( 'Type your Excerpt length (in words)', 'wpbingo' ),
			]
		);
		$this->add_control(
			'item_row',
			[
				'label' => __( 'Number row of Posts', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'placeholder' => __( 'Type your Number row of Testimonial here', 'wpbingo' ),
				'default' => 1,
			]
		);		
		$this->add_control(
			'columns',
			[
				'label' => __( 'Number of Columns >1200px', 'wpbingo' ),
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
			'show_nav',
			[
				'label' => __( 'Show Navigation', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1'  => __( 'Yes', 'wpbingo' ),
					'0' => __( 'No', 'wpbingo' ),
				],
				'default' => '0'
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
				'default' => '0'
			]
		);		
		$this->add_control(
			'layout',
			[
				'label' => __( 'Layout', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'default',
				'options' => [
					'default'  => __( 'Default', 'wpbingo' ),
					'slider'  => __( 'Slider', 'wpbingo' ),
				],
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
					'{{WRAPPER}}  .bwp-recent-post .post-inner' => 'text-align: {{VALUE}};',
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
					'{{WRAPPER}} .bwp-recent-post .entry-title a' => 'color: {{VALUE}};',
				],
				'separator' => 'after',
			]
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'tab_title_hover',
			[
				'label' => __( 'hover', 'wpbingo' ),
			]
		);
		$this->add_control(
			'title_color_hover',
			[
				'label' => __( 'Title Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-title a:hover' => 'color: {{VALUE}};',
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
				'selector' => '{{WRAPPER}} .bwp-recent-post .entry-title',
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
					'{{WRAPPER}} .bwp-recent-post .entry-title' => 'margin-bottom: {{SIZE}}{{UNIT}};margin-top:0;',
				],
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'title_border',
				'selector' => '{{WRAPPER}} .bwp-recent-post .entry-title',
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'title_padding',
			[
				'label' => __( 'Padding', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_categories_style',
			[
				'label' => __( 'Categories', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->start_controls_tabs( 'tabs_categories_hover');
		$this->start_controls_tab(
			'tab_categories_normal',
			[
				'label' => __( 'Normal', 'wpbingo' ),
			]
		);
		$this->add_control(
			'categories_color',
			[
				'label' => __( 'Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-categories a' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);
		$this->add_control(
			'categories_background',
			[
				'label' => __( 'Background', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-categories a' => 'background: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'separator' => 'after',
			]
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'tab_categories_hover',
			[
				'label' => __( 'Hover', 'wpbingo' ),
			]
		);
		$this->add_control(
			'categories_color_hover',
			[
				'label' => __( 'Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-categories a:hover' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);
		$this->add_control(
			'categories_background_hover',
			[
				'label' => __( 'Background', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-categories a:hover' => 'background: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'separator' => 'after',
			]
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'categories_typography',
				'selector' => '{{WRAPPER}} .bwp-recent-post .post-categories a',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);		
		$this->add_responsive_control(
			'categories_bottom_space',
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
					'{{WRAPPER}} .bwp-recent-post .post-categories' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'categories_border',
				'selector' => '{{WRAPPER}} .bwp-recent-post .post-categories a',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'categories_border_radius',
			[
				'label' => __( 'Border Radius', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-categories a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'categories_padding',
			[
				'label' => __( 'Padding', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-categories a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'meta_style',
			[
				'label' => __( 'Content Meta', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->start_controls_tabs( 'tabs_meta_hover');
		$this->start_controls_tab(
			'tab_meta_normal',
			[
				'label' => __( 'Normal', 'wpbingo' ),
			]
		);
		$this->add_control(
			'meta_color',
			[
				'label' => __( 'Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-meta a' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'separator' => 'after',
			]
		);
		$this->end_controls_tab();
		$this->start_controls_tab(
			'tab_meta_hover',
			[
				'label' => __( 'Hover', 'wpbingo' ),
			]
		);
		$this->add_control(
			'meta_color_hover',
			[
				'label' => __( 'Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-meta a:hover' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'separator' => 'after',
			]
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'meta_typography',
				'selector' => '{{WRAPPER}} .bwp-recent-post .entry-meta',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);		
		$this->add_responsive_control(
			'meta_bottom_space',
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
					'{{WRAPPER}} .bwp-recent-post .entry-meta' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'align_meta',
			[
				'label' => __( 'Horizontal Align', 'elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => __( 'Default', 'elementor' ),
					'flex-start' => __( 'Start', 'elementor' ),
					'center' => __( 'Center', 'elementor' ),
					'flex-end' => __( 'End', 'elementor' ),
					'space-between' => __( 'Space Between', 'elementor' ),
					'space-around' => __( 'Space Around', 'elementor' ),
					'space-evenly' => __( 'Space Evenly', 'elementor' ),
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-meta' => 'justify-content: {{VALUE}}',
				],
			]
		);
		$this->add_control(
			'heading_title_icon',
			[
				'label' => __( 'Icon', 'wpbingo' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$this->add_control(
			'icon_meta_color',
			[
				'label' => __( 'Icon Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-meta i' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);
		$this->add_responsive_control(
			'icon_meta_fontsize',
			[
				'label' => __( 'Icon Font Size', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-meta i' => 'font-size: {{SIZE}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'icon_meta_space',
			[
				'label' => __( 'icon Spacing', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .entry-meta i' => 'margin-right: {{SIZE}}{{UNIT}};',
				],
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_description_style',
			[
				'label' => __( 'Description', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_control(
			'description_color',
			[
				'label' => __( 'Description Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-excerpt' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'description_typography',
				'selector' => '{{WRAPPER}} .bwp-recent-post .post-excerpt',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
			]
		);
		$this->add_responsive_control(
			'description_bottom_space',
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
					'{{WRAPPER}} .bwp-recent-post .post-excerpt' => 'margin-bottom: {{SIZE}}{{UNIT}};margin-top:0;',
				],
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'description_border',
				'selector' => '{{WRAPPER}} .bwp-recent-post .post-excerpt',
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'description_padding',
			[
				'label' => __( 'Padding', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-excerpt' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_button_style',
			[
				'label' => __( 'Button', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'button_typography',
				'selector' => '{{WRAPPER}} .bwp-recent-post .btn-read-more a',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .bwp-recent-post .btn-read-more a',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'border_radius',
			[
				'label' => __( 'Border Radius', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .btn-read-more a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'button_padding',
			[
				'label' => __( 'Padding', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .btn-read-more a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'button_bottom_space',
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
					'{{WRAPPER}} .bwp-recent-post .btn-read-more' => 'margin-bottom: {{SIZE}}{{UNIT}};margin-top:0;',
				],
			]
		);
		$this->start_controls_tabs( 'tabs_button_style' );
		$this->start_controls_tab(
			'tab_button_normal',
			[
				'label' => __( 'Normal', 'wpbingo' ),
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label' => __( 'Text Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .btn-read-more a' => 'fill: {{VALUE}}; color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'background',
				'label' => __( 'Background', 'wpbingo' ),
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => '{{WRAPPER}} .bwp-recent-post .btn-read-more a',
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
					'color' => [
						'global' => [
							'default' => Global_Colors::COLOR_ACCENT,
						],
					],
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			[
				'label' => __( 'Hover', 'wpbingo' ),
			]
		);

		$this->add_control(
			'hover_color',
			[
				'label' => __( 'Text Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .btn-read-more a:hover, {{WRAPPER}} .bwp-recent-post .btn-read-more a:focus' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'button_background_hover',
				'label' => __( 'Background', 'wpbingo' ),
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => '{{WRAPPER}} .bwp-recent-post .btn-read-more a:hover, {{WRAPPER}} .bwp-recent-post .btn-read-more a:focus',
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
				],
			]
		);

		$this->add_control(
			'button_hover_border_color',
			[
				'label' => __( 'Border Color', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .btn-read-more a:hover, {{WRAPPER}} .bwp-recent-post .btn-read-more a:focus' => 'border-color: {{VALUE}};',
				],
			]
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_image_style',
			[
				'label' => __( 'Image', 'wpbingo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'image_border',
				'selector' => '{{WRAPPER}} .bwp-recent-post .post-image',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'image_border_radius',
			[
				'label' => __( 'Border Radius', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'image_margin',
			[
				'label' => __( 'Margin', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-image' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_content_style',
			[
				'label' => __( 'Content', 'wpbingo' ),
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
					'{{WRAPPER}} .bwp-recent-post .post-inner' => 'background: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'content_border',
				'selector' => '{{WRAPPER}} .bwp-recent-post .post-inner',
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
					'{{WRAPPER}} .bwp-recent-post .post-inner' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .bwp-recent-post .post-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .bwp-recent-post .post-inner' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .bwp-recent-post .post-inner' => 'box-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}} {{box_shadow_position.VALUE}};',
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
		
		$this->add_control(
			'heading_title_content',
			[
				'label' => __( 'Content Info', 'wpbingo' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'border-content_info',
				'selector' => '{{WRAPPER}} .bwp-recent-post .post-content',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'border_radius_content_info',
			[
				'label' => __( 'Border Radius', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'padding_content_info',
			[
				'label' => __( 'Padding', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->add_responsive_control(
			'margin_content_info',
			[
				'label' => __( 'Margin', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .bwp-recent-post .post-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		extract( shortcode_atts(
			array(
				'number'   => '',
				'title1'   => '',
				'description'   => '',
				'category' => '',
				'limit'	   => 8,
				'length'	=> 25,
				'item_row' => 1,	
				'columns'  => 3,
				'columns1' => 3,
				'columns2' => 3,
				'columns3' => 1,
				'columns4' => 1,
				'show_nav'  => '1',
				'show_pag'  => '1',					
				'layout'   => 'default',
			), $settings )
		);
		$tag_id = 'recent_post_' .rand().time(); 
		if($category){
			$args = array(
			'post_type' => 'post', 
			'posts_per_page' => $limit,
			'tax_query'		=> array(
					array(
						'taxonomy'	=> 'category',
						'field'		=> 'slug',
						'terms'		=> $category
					)
				) 
			);   
		}else{
			$args = array(
			'post_type' => 'post', 
			'posts_per_page' => $limit 
			);    
		}

		$query = new \WP_Query($args);
		$post_count = $query->post_count;
		
		if( $layout == 'default' ) {
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-recent-post/default.php' );
		}elseif( $layout == 'slider' ) {
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-recent-post/slider.php' );
		}
	}
}