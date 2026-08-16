<?php
/**
 * Wpbingo WooCommerce Categories
 * Plugin URI: http://www.wpbingosite.com
 * Version: 1.0
 * This Widget help you to show images of product as a beauty tab reponsive slideshow
 */
if ( !class_exists('bwp_woo_categories_widget') ) {
	class bwp_woo_categories_widget extends WP_Widget {

		/**
		 * Widget setup.
		 */
		function __construct() {
			/* Widget settings. */
			$widget_ops = array( 'classname' => 'bwp_woo_categories_widget', 'description' => __('Wpbingo WooCommerce Categories', "wpbingo" ) );

			/* Widget control settings. */
			$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'bwp_woo_categories_widget' );

			/* Create the widget. */
			parent::__construct( 'bwp_woo_categories_widget', __('Wpbingo WooCommerce Categories', "wpbingo" ), $widget_ops, $control_ops );
		}	
		
		public function widget( $args, $instance ) {
			/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
			extract($args);
			echo $before_widget;
			$title1 = apply_filters( 'widget_title', empty( $instance['title1'] ) ? '' : $instance['title1'], $instance, $this->id_base );	
			$tag_id = 'woo_categories_' .rand().time();
			extract($instance);
			include(WPBINGO_WIDGET_TEMPLATE_PATH.'bwp-woo-categories/default.php' );
			echo $after_widget;
		}
		
		function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
			// strip tag on text field
			$instance['title1'] = strip_tags( $new_instance['title1'] );
			if ( array_key_exists('show_empty', $new_instance) ){
				$instance['show_empty'] = strip_tags( $new_instance['show_empty'] );
			}
			if ( array_key_exists('show_count', $new_instance) ){
				$instance['show_count'] = strip_tags( $new_instance['show_count'] );
			}			
			return $instance;
		}
	
		public function form( $instance ) {
			/* Set up some default widget settings. */
			$defaults = array();
			$instance = wp_parse_args( (array) $instance, $defaults );
			$title1 = isset( $instance['title1'] )    ? 	strip_tags($instance['title1']) : '';
			$show_empty   			= isset( $instance['show_empty'] ) ? strip_tags($instance['show_empty']) : 1;
			$show_count   			= isset( $instance['show_count'] ) ? strip_tags($instance['show_count']) : 1;
			?>
			<p>
				<label for="<?php echo $this->get_field_id('title1'); ?>"><?php _e('Title', "wpbingo")?></label>
				<br />
				<input class="widefat" id="<?php echo $this->get_field_id('title1'); ?>" name="<?php echo $this->get_field_name('title1'); ?>"
					type="text"	value="<?php echo esc_attr($title1); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('show_empty'); ?>"><?php _e("Show Empty Categories", 'wpbingo')?></label>
				<br/>
				<select class="widefat"
					id="<?php echo $this->get_field_id('show_empty'); ?>"	name="<?php echo $this->get_field_name('show_empty'); ?>">
					<option value="1" <?php if ($show_empty==1){?> selected="selected"
					<?php } ?>>
						<?php _e('Yes', 'wpbingo')?>
					</option>
					<option value="0" <?php if ($show_empty==0){?> selected="selected"
					<?php } ?>>
						<?php _e('No', 'wpbingo')?>
					</option>				
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('show_count'); ?>"><?php _e("Show Count Products", 'wpbingo')?></label>
				<br/>
				<select class="widefat"
					id="<?php echo $this->get_field_id('show_count'); ?>"	name="<?php echo $this->get_field_name('show_count'); ?>">
					<option value="1" <?php if ($show_count==1){?> selected="selected"
					<?php } ?>>
						<?php _e('Yes', 'wpbingo')?>
					</option>
					<option value="0" <?php if ($show_count==0){?> selected="selected"
					<?php } ?>>
						<?php _e('No', 'wpbingo'); ?>
					</option>				
				</select>
			</p>			
		<?php
		}		
	}
	add_action( 'widgets_init', 'bwp_register_woo_categories_widget' );
	function bwp_register_woo_categories_widget(){
		register_widget( 'bwp_woo_categories_widget');
	}
}
?>