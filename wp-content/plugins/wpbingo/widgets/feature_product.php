<?php
/**
 * Wpbingo Feature Product Widget
 * Plugin URI: http://www.wpbingosite.com
 * Version: 1.0
 * This Widget help you to show images of product as a beauty tab reponsive slideshow
 */
if ( !class_exists('bwp_feature_product_widget') ) {
	class bwp_feature_product_widget extends WP_Widget {

		/**
		 * Widget setup.
		 */
		function __construct() {
			/* Widget settings. */
			$widget_ops = array( 'classname' => 'bwp_feature_product_widget', 'description' => __('Wpbingo Feature Product Widget', "wpbingo" ) );

			/* Widget control settings. */
			$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'bwp_feature_product_widget' );

			/* Create the widget. */
			parent::__construct( 'bwp_feature_product_widget', __('Wpbingo Feature Product', "wpbingo" ), $widget_ops, $control_ops );
		}	
		
		public function widget( $args, $instance ) {
			/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
			extract($args);
			echo $before_widget;
			$title1 = apply_filters( 'widget_title', empty( $instance['title1'] ) ? '' : $instance['title1'], $instance, $this->id_base );
			$limit		 = 	( $instance['limit'] ) ? $instance['limit'] : 5;	
			$tag_id = 'feature_product_' .rand().time();
			$product_visibility_term_ids = wc_get_product_visibility_term_ids();
			$args = array(
				'post_type'				=> 'product',
				'post_status' 			=> 'publish',
				'ignore_sticky_posts'	=> 1,
				'posts_per_page' 		=> $limit,
				'tax_query'	=> array(
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => $product_visibility_term_ids['featured'],
					)						
				)
			);
			$query = new \WP_Query( $args );
			include(WPBINGO_WIDGET_TEMPLATE_PATH.'bwp-feature-product/default.php' );
			echo $after_widget;
		}
		
		function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
			// strip tag on text field
			$instance['title1'] = strip_tags( $new_instance['title1'] );
			if ( array_key_exists('limit', $new_instance) ){
				$instance['limit'] = intval( $new_instance['limit'] );
			}
			return $instance;
		}
	
		public function form( $instance ) {
			/* Set up some default widget settings. */
			$defaults = array();
			$instance = wp_parse_args( (array) $instance, $defaults );
			$title1 = isset( $instance['title1'] )    ? 	strip_tags($instance['title1']) : '';
			$number     = isset( $instance['limit'] ) ? intval($instance['limit']) : 5;
			?>
			<p>
				<label for="<?php echo $this->get_field_id('title1'); ?>"><?php _e('Title', "wpbingo")?></label>
				<br />
				<input class="widefat" id="<?php echo $this->get_field_id('title1'); ?>" name="<?php echo $this->get_field_name('title1'); ?>"
					type="text"	value="<?php echo esc_attr($title1); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of Products', "wpbingo")?></label>
				<br />
				<input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>"
					type="text"	value="<?php echo esc_attr($number); ?>" />
			</p>
		<?php
		}		
	}
	add_action( 'widgets_init', 'bwp_register_feature_product_widget' );
	function bwp_register_feature_product_widget(){
		register_widget( 'bwp_feature_product_widget');
	}
}
?>