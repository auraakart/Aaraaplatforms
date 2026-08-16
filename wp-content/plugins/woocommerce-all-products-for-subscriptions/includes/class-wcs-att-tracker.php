<?php
/**
 * WCS_ATT_Tracker class
 *
 * @package  WooCommerce All Products for Subscriptions
 * @since    3.3.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All Products for WooCommerce Subscriptions Tracker.
 *
 * @class    WCS_ATT_Tracker
 * @version  5.0.5
 */
class WCS_ATT_Tracker {

	/**
	 * Initialize the Tracker.
	 */
	public static function init() {
		if ( 'yes' === get_option( 'woocommerce_allow_tracking', 'no' ) ) {
			add_filter( 'woocommerce_tracker_data', array( __CLASS__, 'add_tracking_data' ), 10 );
		}
	}

	/**
	 * Adds APFS data to the WC tracked data.
	 *
	 * @param  array $data
	 * @return array
	 */
	public static function add_tracking_data( $data ) {

		$data['extensions']['wc_apfs']['settings'] = self::get_settings();
		$data['extensions']['wc_apfs']['products'] = self::get_product_data();

		return $data;
	}

	/**
	 * Gets APFS settings.
	 *
	 * @return array
	 */
	private static function get_settings() {

		$cart_level_schemes = get_option( 'wcsatt_subscribe_to_cart_schemes', array() );

		return array(
			'cart_plans'                    => ! empty( $cart_level_schemes ) && is_array( $cart_level_schemes ) ? count( $cart_level_schemes ) : 0,
			'add_products_to_subscriptions' => 'off' === get_option( 'wcsatt_add_product_to_subscription', 'off' ) ? 'off' : 'on',
			'add_cart_to_subscriptions'     => 'off' === get_option( 'wcsatt_add_cart_to_subscription', 'off' ) ? 'off' : 'on',
		);
	}

	/**
	 * Gets APFS product data.
	 *
	 * @return array
	 */
	private static function get_product_data() {

		global $wpdb;

		$products_with_plans = $wpdb->get_results( "SELECT ID FROM `{$wpdb->posts}` AS posts INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id AND postmeta.meta_key = '_wcsatt_schemes' WHERE posts.post_type = 'product' AND posts.post_status = 'publish'", ARRAY_A );
		$products_with_plans = ! empty( $products_with_plans ) ? wp_list_pluck( $products_with_plans, 'ID' ) : array();
		$products_with_plans = array_map( 'absint', $products_with_plans );
		// This variable adds as many %d placeholders to the query as the IDs. Therefore, we are skipping PHPCS checks for this query.
		$placeholders = implode( ', ', array_fill( 0, count( $products_with_plans ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'products_count'                     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->posts}` WHERE `post_type` = 'product' AND `post_status` = 'publish'" ),
			'products_with_plans_count'          => (int) count( $products_with_plans ),
			'products_with_forced_plan_count'    => empty( $products_with_plans ) ? 0 : (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->posts}` AS posts INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id AND postmeta.meta_key = '_wcsatt_force_subscription' WHERE postmeta.meta_value = 'yes' AND posts.post_type = 'product' AND posts.ID IN ( {$placeholders} ) AND posts.post_status = 'publish'", ...$products_with_plans ) ),
			'products_with_grouped_layout_count' => empty( $products_with_plans ) ? 0 : (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->posts}` AS posts INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id AND postmeta.meta_key = '_wcsatt_layout' WHERE postmeta.meta_value = 'grouped' AND posts.post_type = 'product' AND posts.ID IN ( {$placeholders} ) AND posts.post_status = 'publish'", ...$products_with_plans ) ),
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

WCS_ATT_Tracker::init();
