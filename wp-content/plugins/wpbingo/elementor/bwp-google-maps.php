<?php
namespace ElementorWpbingo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Bwp_Google_Maps extends Widget_Base {
	public function get_name() {
		return 'bwp_google_maps';
	}
	public function get_title() {
		return __( 'Wpbingo Google Maps', 'wpbingo' );
	}
	public function get_icon() {
		return 'eicon-google-maps';
	}
	public function get_categories() {
		return [ 'general' ];
	}
	public function get_keywords() {
		return [ 'google', 'map', 'embed', 'location' ];
	}
	protected function register_controls() {
		$this->start_controls_section(
			'section_map',
			[
				'label' => __( 'Map', 'wpbingo' ),
			]
		);

		$default_address = __( 'London Eye, London, United Kingdom', 'wpbingo' );
		$this->add_control(
			'address',
			[
				'label' => __( 'Location', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
					'categories' => [
						\Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
					],
				],
				'placeholder' => $default_address,
				'default' => $default_address,
				'label_block' => true,
			]
		);

		$this->add_control(
			'zoom',
			[
				'label' => __( 'Zoom', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'default' => [
					'size' => 10,
				],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 20,
					],
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'height',
			[
				'label' => __( 'Height', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 40,
						'max' => 1440,
					],
				],
				'selectors' => [
					'{{WRAPPER}} iframe' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'view',
			[
				'label' => __( 'View', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::HIDDEN,
				'default' => 'traditional',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_map_style',
			[
				'label' => __( 'Map', 'wpbingo' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'map_filter' );

		$this->start_controls_tab( 'normal',
			[
				'label' => __( 'Normal', 'wpbingo' ),
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Css_Filter::get_type(),
			[
				'name' => 'css_filters',
				'selector' => '{{WRAPPER}} iframe',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'hover',
			[
				'label' => __( 'Hover', 'wpbingo' ),
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Css_Filter::get_type(),
			[
				'name' => 'css_filters_hover',
				'selector' => '{{WRAPPER}}:hover iframe',
			]
		);

		$this->add_control(
			'hover_transition',
			[
				'label' => __( 'Transition Duration', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'max' => 3,
						'step' => 0.1,
					],
				],
				'selectors' => [
					'{{WRAPPER}} iframe' => 'transition-duration: {{SIZE}}s',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['address'] ) ) {
			return;
		}

		if ( 0 === absint( $settings['zoom']['size'] ) ) {
			$settings['zoom']['size'] = 10;
		}

		printf(
			'<div class="elementor-custom-embed"><iframe src="https://maps.google.com/maps?q=%s&amp;t=m&amp;z=%d&amp;output=embed&amp;iwloc=near" aria-label="%s"></iframe></div>',
			rawurlencode( $settings['address'] ),
			absint( $settings['zoom']['size'] ),
			esc_attr( $settings['address'] )
		);
	}
	protected function _content_template() {}
}