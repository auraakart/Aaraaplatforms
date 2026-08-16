<?php
namespace ElementorWpbingo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class Bwp_Cta extends Widget_Base {
	public function get_name() {
		return 'bwp_cta';
	}
	public function get_title() {
		return __( 'Wpbingo Counter Up', 'wpbingo' );
	}
	public function get_icon() {
		return 'fa fa-sort-numeric-asc';
	}	
	public function get_categories() {
		return [ 'general' ];
	}
	protected function register_controls() {
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
				'label' => __( 'Sub Title', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'placeholder' => __( 'Type your Sub title here', 'wpbingo' ),
			]
		);
		$this->add_control(
			'count',
			[
				'label' => __( 'Count', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 0,
				'placeholder' => __( 'Type your Count here', 'wpbingo' ),
			]
		);		
		$this->add_control(
			'image',
			[
				'label' => __( 'Choose Image', 'wpbingo' ),
				'type' => \Elementor\Controls_Manager::MEDIA,
				'default' => [
					'url' => \Elementor\Utils::get_placeholder_image_src(),
				],
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
				],
			]
		);		
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		$title1 = ( $settings['title1'] ) ? $settings['title1'] : '';
		$subtitle = ( $settings['subtitle'] ) ? $settings['subtitle'] : '';
		$desc		 = 	( $settings['desc'] ) ? $settings['desc'] : '';
		$image		 = 	( $settings['image'] && $settings['image']['url'] ) ? $settings['image']['url'] : '';
		$count		 = 	( $settings['count'] ) ? $settings['count'] : '';
		$layout		 = 	( $settings['layout'] ) ? $settings['layout'] : 'default';
		if( $settings['layout'] == 'default' || $settings['layout'] == 'default2' || $settings['layout'] == 'layout1' ){
			include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'bwp-cta/default.php' );
		}
	}
}
