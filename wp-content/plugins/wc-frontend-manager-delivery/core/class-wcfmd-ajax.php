<?php

/**
 * WCFM Delivery plugin core
 *
 * Plugin Ajax Controler
 *
 * @author 		WC Lovers
 * @package 	wcfmd/core
 * @version   1.0.0
 */

class WCFMd_Ajax {

	public $controllers_path;

	public function __construct() {
		global $WCFM, $WCFMd;

		$this->controllers_path = $WCFMd->plugin_path . 'controllers/';

		add_action('after_wcfm_ajax_controller', array(&$this, 'wcfmd_ajax_controller'));

		// Generate Delivery Assign Html
		add_action('wp_ajax_wcfmd_delivery_boy_assign_html', array(&$this, 'wcfmd_delivery_boy_assign_html'));

		// WCfM Marketplace Delivery Assigned
		add_action('wp_ajax_wcfmd_delivery_boy_assign', array(&$this, 'wcfmd_delivery_boy_assign'));

		// Mark Order Delivered
		add_action('wp_ajax_mark_wcfm_order_delivered', array(&$this, 'wcfmd_mark_order_delivered'));

		// Delivery Boy Delete
		add_action('wp_ajax_delete_wcfm_delivery_boy', array(&$this, 'delete_wcfm_delivery_boy'));

		// Route Optimizer: pending deliveries with coordinates
		add_action('wp_ajax_wcfmd_get_route_deliveries', array(&$this, 'wcfmd_get_route_deliveries'));
	}


	/**
	 * WCFM Delivery Ajax Controllers
	 */
	public function wcfmd_ajax_controller() {
		global $WCFM, $WCFMgs;

		$controller = '';
		if (isset($_POST['controller'])) {
			$controller = $_POST['controller'];

			switch ($controller) {

				case 'wcfm-delivery-boys':
					include_once($this->controllers_path . 'wcfmd-controller-delivery-boys.php');
					new WCFMd_Delivery_Boys_Controller();
					break;

				case 'wcfm-delivery-boys-manage':
					include_once($this->controllers_path . 'wcfmd-controller-delivery-boys-manage.php');
					new WCFMd_Delivery_Boys_Manage_Controller();
					break;

				case 'wcfm-deliveries':
				case 'wcfm-delivery-boys-stats':
					if (apply_filters('wcfm_is_show_marketplace_itemwise_orders', true)) {
						include_once($this->controllers_path . 'wcfmd-controller-delivery-boys-itemized-stats.php');
					} else {
						include_once($this->controllers_path . 'wcfmd-controller-delivery-boys-stats.php');
					}
					if (defined('WCFM_REST_API_CALL')) {
						$delivery_boy_object = new WCFMd_Delivery_Boys_Stats_Controller();
						return $delivery_boy_object->processing();
					} else {
						new WCFMd_Delivery_Boys_Stats_Controller();
					}
					break;
			}
		}
	}

	/**
	 * Generate Delivery Boy Assign HTML
	 */
	function wcfmd_delivery_boy_assign_html() {
		global $WCFM, $WCFMd;

		include_once($WCFMd->library->views_path . 'wcfmd-view-delivery-boy-assign.php');
		die;
	}

	/**
	 * WCfM Marketplace Delivery Boy Assigned
	 */
	function wcfmd_delivery_boy_assign() {
		global $WCFM, $WCFMd, $woocommerce, $wpdb;

		$user_id = apply_filters('wcfm_current_vendor_id', get_current_user_id());

		if (!empty($_POST['orderid'])) {

			$wcfm_tracking_data = array();
			parse_str($_POST['tracking_data'], $wcfm_tracking_data);
			$order_id       = absint($wcfm_tracking_data['wcfm_tracking_order_id']);
			$product_ids    = $wcfm_tracking_data['wcfm_tracking_product_id'];
			$product_ids    = explode(",", $product_ids);
			$order_item_ids = $wcfm_tracking_data['wcfm_tracking_order_item_id'];
			$order_item_ids = explode(",", $order_item_ids);
			$order          = wc_get_order($order_id);

			$wcfm_delivery_boy  = absint($wcfm_tracking_data['wcfm_delivery_boy']);

			if ($wcfm_delivery_boy) {
				foreach ($order_item_ids as $index => $order_item_id) {
					do_action('wcfmd_delivery_boy_assigned', $order_id, $order_item_id, $wcfm_tracking_data, $product_ids[$index]);
				}
			}
			if (defined('WCFM_REST_API_CALL')) {
				return $wcfm_tracking_data;
			}
			echo '{"status" : true, "message" : "' . __('Details successfully updated.', 'wc-frontend-manager-delivery') . '"}';
		} else {
			if (defined('WCFM_REST_API_CALL')) {
				return '{"status" : false, "message" : "' . __('Details update failed.', 'wc-frontend-manager-delivery') . '"}';
			}
			echo '{"status" : false, "message" : "' . __('Details update failed.', 'wc-frontend-manager-delivery') . '"}';
		}
		die;
	}

	/**
	 * Mark Order Delivered
	 */
	function wcfmd_mark_order_delivered() {
		global $WCFM, $WCFMd, $wpdb;

		$delivery_ids = absint($_POST['delivery_id']);

		$delivery_ids = explode(",", $delivery_ids);

		$delivered_not_notified = false;

		if ($delivery_ids) {
			foreach ($delivery_ids as $delivery_id) {
				$sql  = "SELECT * FROM `{$wpdb->prefix}wcfm_delivery_orders`";
				$sql .= " WHERE 1=1";
				$sql .= " AND ID = %d";
				$delivery_details = $wpdb->get_results($wpdb->prepare($sql, $delivery_ids));

				if (!empty($delivery_details)) {
					foreach ($delivery_details as $delivery_detail) {

						// Update Delivery Order Status Update
						$wpdb->update("{$wpdb->prefix}wcfm_delivery_orders", array('delivery_status' => 'delivered', 'delivery_date' => date('Y-m-d H:i:s', current_time('timestamp', 0))), array('ID' => $delivery_id), array('%s', '%s'), array('%d'));

						$order = wc_get_order($delivery_detail->order_id);
						$wcfm_delivery_boy_user = get_userdata($delivery_detail->delivery_boy);

						if (apply_filters('wcfm_is_show_marketplace_itemwise_orders', true)) {
							// Admin Notification
							$wcfm_messages = sprintf(__('Order <b>%s</b> item <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery'), '#<a class="wcfm_dashboard_item_title" target="_blank" href="' . get_wcfm_view_order_url($delivery_detail->order_id) . '">' . $order->get_order_number() . '</a>', get_the_title($delivery_detail->product_id), '<a class="wcfm_dashboard_item_title" target="_blank" href="' . get_wcfm_delivery_boys_stats_url($delivery_detail->delivery_boy) . '">' . $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name . '</a>');
							$WCFM->wcfm_notification->wcfm_send_direct_message(-2, 0, 0, 0, $wcfm_messages, 'delivery_complete');

							// Vendor Notification
							if ($delivery_detail->vendor_id) {
								$WCFM->wcfm_notification->wcfm_send_direct_message(-1, $delivery_detail->vendor_id, 1, 0, $wcfm_messages, 'delivery_complete');
							}

							// Order Note
							$wcfm_messages = sprintf(__('Order <b>%s</b> item <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery'), '#<span class="wcfm_dashboard_item_title">' . $order->get_order_number() . '</span>', get_the_title($delivery_detail->product_id), $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name);
							$comment_id = $order->add_order_note($wcfm_messages, apply_filters('wcfm_is_allow_delivery_note_to_customer', '1'));

							do_action('wcfmd_after_order_item_mark_delivered', $delivery_detail->order_id, $delivery_detail->product_id, $delivery_detail);
						} elseif (!$delivered_not_notified) {
							// Admin Notification
							$wcfm_messages = sprintf(__('Order <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery'), '#<a class="wcfm_dashboard_item_title" target="_blank" href="' . get_wcfm_view_order_url($delivery_detail->order_id) . '">' . $order->get_order_number() . '</a>', '<a class="wcfm_dashboard_item_title" target="_blank" href="' . get_wcfm_delivery_boys_stats_url($delivery_detail->delivery_boy) . '">' . $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name . '</a>');
							$WCFM->wcfm_notification->wcfm_send_direct_message(-2, 0, 0, 0, $wcfm_messages, 'delivery_complete');

							// Vendor Notification
							if ($delivery_detail->vendor_id) {
								$WCFM->wcfm_notification->wcfm_send_direct_message(-1, $delivery_detail->vendor_id, 1, 0, $wcfm_messages, 'delivery_complete');
							}

							// Order Note
							$wcfm_messages = sprintf(__('Order <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery'), '#<span class="wcfm_dashboard_item_title">' . $order->get_order_number() . '</span>', $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name);
							$comment_id = $order->add_order_note($wcfm_messages, apply_filters('wcfm_is_allow_delivery_note_to_customer', '1'));

							do_action('wcfmd_after_order_mark_delivered', $delivery_detail->order_id, $delivery_detail);

							$delivered_not_notified = true;
						}
					}

					//if( defined('WCFM_REST_API_CALL') ) {
					//return '{"status": true, "message": "' . __( 'Delivery status updated.', 'wc-frontend-manager-delivery' ) . '"}';
					//}
				}
			}
		}

		if (defined('WCFM_REST_API_CALL')) {
			return '{"status": true, "message": "' . __('Delivery status updated.', 'wc-frontend-manager-delivery') . '"}';
		} else {
			echo '{"status": true, "message": "' . __('Delivery status updated.', 'wc-frontend-manager-delivery') . '"}';
		}

		die;
	}

	/**
	 * Return pending deliveries with coordinates for route optimizer.
	 */
	function wcfmd_get_route_deliveries() {
		global $wpdb;

		$delivery_boy_id = absint( $_POST['delivery_boy_id'] ?? 0 );
		if ( ! $delivery_boy_id ) {
			wp_send_json_error( 'Invalid delivery person.' );
		}

		$current_user_id = get_current_user_id();
		if ( $current_user_id !== $delivery_boy_id && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wcfm_delivery_orders
			 WHERE delivery_boy = %d AND delivery_status = 'pending' AND is_trashed = 0
			 ORDER BY ID ASC",
			$delivery_boy_id
		) );

		// Group rows by order_id so we can collect all items per order.
		$order_rows = [];
		foreach ( $rows as $row ) {
			$order_rows[ (int) $row->order_id ][] = $row;
		}

		$deliveries = [];

		foreach ( $order_rows as $order_id => $order_row_list ) {
			$order = wc_get_order( $order_id );
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}

			$lat  = (float) $order->get_meta( '_wcfmmp_user_location_lat', true );
			$lng  = (float) $order->get_meta( '_wcfmmp_user_location_lng', true );
			$addr = (string) $order->get_meta( '_wcfmmp_user_location', true );

			if ( ! $addr ) {
				// Build address from individual fields — get_formatted_shipping_address()
				// includes the customer name which breaks Google Maps geocoding.
				$parts = array_filter( [
					$order->get_shipping_address_1(),
					$order->get_shipping_address_2(),
					$order->get_shipping_city(),
					$order->get_shipping_state(),
					$order->get_shipping_postcode(),
					$order->get_shipping_country(),
				] );
				if ( empty( $parts ) ) {
					$parts = array_filter( [
						$order->get_billing_address_1(),
						$order->get_billing_address_2(),
						$order->get_billing_city(),
						$order->get_billing_state(),
						$order->get_billing_postcode(),
						$order->get_billing_country(),
					] );
				}
				$addr = implode( ', ', $parts );
			}

			if ( ! $addr && ! ( $lat && $lng ) ) {
				continue;
			}

			$first = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
			$last  = $order->get_shipping_last_name()  ?: $order->get_billing_last_name();

			// Collect all line items for this order from the delivery rows.
			$items = [];
			foreach ( $order_row_list as $row ) {
				try {
					$line_item = new WC_Order_Item_Product( $row->item_id );
					$items[] = [
						'qty'  => (int) $line_item->get_quantity(),
						'name' => $line_item->get_name(),
					];
				} catch ( Exception $e ) {
					// skip invalid item
				}
			}

			$deliveries[] = [
				'delivery_id'   => (int) $order_row_list[0]->ID,
				'order_id'      => $order_id,
				'order_number'  => $order->get_order_number(),
				'customer_name' => trim( $first . ' ' . $last ) ?: __( 'Customer', 'wc-frontend-manager-delivery' ),
				'phone'         => $order->get_billing_phone(),
				'address'       => $addr,
				'lat'           => $lat ?: null,
				'lng'           => $lng ?: null,
				'items'         => $items,
			];
		}

		wp_send_json_success( $deliveries );
	}

	/**
	 * Handle Delivery Boy Delete
	 */
	public function delete_wcfm_delivery_boy() {
		global $WCFM, $WCFMu;

		$deliveryboyid = $_POST['deliveryboyid'];

		if ($deliveryboyid) {
			if (wp_delete_user($deliveryboyid)) {
				echo 'success';
				die;
			}
			die;
		}
	}
}
