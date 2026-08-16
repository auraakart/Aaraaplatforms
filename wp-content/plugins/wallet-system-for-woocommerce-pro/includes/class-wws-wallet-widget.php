<?php
/**
 * Exit if accessed directly
 *
 * @package Wallet_System_For_Woocommerce_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class to create wallet widget.
 */
class Wws_Wallet_Widget extends WP_Widget {

	/**
	 * Constructor for the widget class.
	 */
	public function __construct() {
		parent::__construct(
			// Base ID of your widget.
			'wws_wallet_widget',
			// Widget name will appear in UI.
			__( 'Wallet Widget', 'wallet-system-for-woocommerce-pro' ),
			// Widget description.
			array( 'description' => __( 'Wallet widget will show user wallet balance in frontend.', 'wallet-system-for-woocommerce-pro' ) )
		);
	}

	/**
	 * Creating widget front-end.
	 *
	 * @param array  $args arguments.
	 * @param object $instance instance.
	 * @return void
	 */
	public function widget( $args, $instance ) {

		$enable = get_option( 'wps_wsfw_enable', '' );
		if ( isset( $enable ) && 'on' === $enable ) {
			$title = apply_filters( 'widget_title', $instance['title'] );
			// before and after widget arguments are defined by themes.
			echo wp_kses_post( $args['before_widget'] );
			if ( ! empty( $title ) ) {
				echo wp_kses_post( $args['before_title'] . $title . $args['after_title'] );
			}

			if ( is_user_logged_in() ) {
				$customer_id  = get_current_user_id();
				$walletamount = get_user_meta( $customer_id, 'wps_wallet', true );

				$wallet_bal       = apply_filters( 'wps_wsfw_show_converted_price', $walletamount );
				$current_currency = apply_filters( 'wps_wsfw_get_current_currency', get_woocommerce_currency() );

				echo '<p>' . wp_kses_post( wc_price( $wallet_bal, array( 'currency' => $current_currency ) ) ) . '</p>';

			} else {
				echo '<p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" >' . esc_html__( 'Login', 'wallet-system-for-woocommerce-pro' ) . '</a>' . esc_html__( ' to know your wallet amount.', 'wallet-system-for-woocommerce-pro' ) . '</p>';
			}
			echo wp_kses_post( $args['after_widget'] );
		}
	}

	/**
	 * Widget Backend.
	 *
	 * @param object $instance instance.
	 * @return void
	 */
	public function form( $instance ) {
		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		} else {
			$title = __( 'Your Wallet Balance is:', 'wallet-system-for-woocommerce-pro' );
		}
		// Widget admin form.
		?>
		<p>
		<label for="<?php echo esc_html( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wallet-system-for-woocommerce-pro' ); ?></label> 
		<input class="widefat" id="<?php echo esc_html( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_html( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}

	/**
	 * Updating widget replacing old instances with new.
	 *
	 * @param object $new_instance new instance.
	 * @param object $old_instance old instance.
	 * @return object
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}
}
// Register widget for wallet.
add_action( 'widgets_init', 'wws_wallet_widget' );
/**
 * Register wallet widget to show wallet amount in sidebar.
 *
 * @return void
 */
function wws_wallet_widget() {
	register_widget( 'wws_wallet_widget' );
}
