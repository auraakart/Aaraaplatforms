<?php
/**
 * Show the customer's wallet balance on the Subscriptions admin.
 *
 * Adds a "Wallet" column to the subscriptions list table (HPOS and legacy CPT)
 * and a "Customer Wallet" meta box on the subscription edit screen. The balance
 * is read with Customers_Admin::get_wallet_balance() so it matches the Wallet
 * Management screen exactly.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wallet balance column + meta box for subscriptions.
 */
class Subscription_Wallet {

	const COLUMN = 'aaraa_wallet';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		// HPOS subscriptions list table. WCS registers its own column set at the
		// default priority and *replaces* the array, so we must run after it (100)
		// to keep our column.
		add_filter( 'woocommerce_shop_subscription_list_table_columns', array( $this, 'add_column' ), 100 );
		add_action( 'woocommerce_shop_subscription_list_table_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Legacy CPT subscriptions list table.
		add_filter( 'manage_edit-shop_subscription_columns', array( $this, 'add_column' ), 100 );
		add_action( 'manage_shop_subscription_posts_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Edit-screen meta box (HPOS + CPT).
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 40 );
	}

	/**
	 * Insert the Wallet column just before the Orders column (or at the end).
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_column( $columns ) {
		if ( ! is_array( $columns ) || isset( $columns[ self::COLUMN ] ) ) {
			return $columns;
		}
		$label = __( 'Wallet', 'aaraa-white-label-admin' );
		$out   = array();
		foreach ( $columns as $key => $value ) {
			if ( 'orders' === $key ) {
				$out[ self::COLUMN ] = $label;
			}
			$out[ $key ] = $value;
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = $label;
		}
		return $out;
	}

	/**
	 * Render the wallet balance cell.
	 *
	 * @param string             $column       Column key.
	 * @param \WC_Subscription|int $subscription Subscription (HPOS) — absent on CPT.
	 * @return void
	 */
	public function render_column( $column, $subscription = null ) {
		if ( self::COLUMN !== $column ) {
			return;
		}
		echo wp_kses_post( $this->balance_html( $this->resolve_customer_id( $subscription ) ) );
	}

	/**
	 * Register the "Customer Wallet" meta box on the subscription edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		$screens = array( 'shop_subscription' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-subscription' );
		}
		foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
			add_meta_box(
				'aaraa-subscription-wallet',
				__( 'Customer Wallet', 'aaraa-white-label-admin' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Meta box content: the customer's wallet balance + a manage link.
	 *
	 * @param \WP_Post|\WC_Order $post_or_object The subscription (HPOS) or post (CPT).
	 * @return void
	 */
	public function render_meta_box( $post_or_object ) {
		$sub_id = is_a( $post_or_object, 'WC_Order' ) ? $post_or_object->get_id() : ( isset( $post_or_object->ID ) ? (int) $post_or_object->ID : 0 );
		$sub    = $sub_id && function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : null;
		$user_id = $sub ? (int) $sub->get_user_id() : 0;

		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'No customer account linked to this subscription.', 'aaraa-white-label-admin' ) . '</p>';
			return;
		}

		$balance = class_exists( __NAMESPACE__ . '\\Customers_Admin' ) ? Customers_Admin::get_wallet_balance( $user_id ) : 0.0;
		$amount  = function_exists( 'wc_price' ) ? wc_price( $balance ) : number_format_i18n( (float) $balance, 2 );
		$manage  = add_query_arg(
			array( 'page' => 'aaraa-wallet', 'action' => 'view', 'user' => $user_id ),
			admin_url( 'admin.php' )
		);
		?>
		<p style="font-size:20px;font-weight:700;margin:6px 0;"><?php echo wp_kses_post( $amount ); ?></p>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $manage ); ?>"><?php esc_html_e( 'View / manage wallet', 'aaraa-white-label-admin' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Resolve the customer id from a subscription passed to the column callback,
	 * falling back to the global $post / $the_subscription on the CPT screen.
	 *
	 * @param \WC_Subscription|int|null $subscription Subscription or id.
	 * @return int
	 */
	private function resolve_customer_id( $subscription ) {
		$sub_id = 0;
		if ( is_a( $subscription, 'WC_Order' ) ) {
			return (int) $subscription->get_user_id();
		}
		if ( is_int( $subscription ) && $subscription ) {
			$sub_id = $subscription;
		} else {
			global $post, $the_subscription;
			if ( is_a( $the_subscription, 'WC_Subscription' ) ) {
				return (int) $the_subscription->get_user_id();
			}
			$sub_id = isset( $post->ID ) ? (int) $post->ID : 0;
		}
		if ( $sub_id && function_exists( 'wcs_get_subscription' ) ) {
			$sub = wcs_get_subscription( $sub_id );
			if ( $sub ) {
				return (int) $sub->get_user_id();
			}
		}
		return 0;
	}

	/**
	 * Wallet balance as a formatted cell (amount, or a dash when no customer).
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	private function balance_html( $user_id ) {
		if ( ! $user_id ) {
			return '&mdash;';
		}
		$balance = class_exists( __NAMESPACE__ . '\\Customers_Admin' ) ? Customers_Admin::get_wallet_balance( $user_id ) : 0.0;
		return function_exists( 'wc_price' ) ? wc_price( $balance ) : esc_html( number_format_i18n( (float) $balance, 2 ) );
	}
}
