<?php

/**
 * WCFM plugin controllers
 *
 * Plugin Customer Details Orders Controller
 *
 * @author 		WC Lovers
 * @package 	wcfm/controllers/customers
 * @version   3.5.0
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

class WCFM_Customers_Details_Orders_Controller {

	public function __construct() {
		global $WCFM;

		$this->processing();
	}

	public function processing() {
		global $WCFM, $wpdb, $_POST;

		$length = absint($_POST['length']);
		$offset = absint($_POST['start']);
		$customer_id = absint($_POST['customer_id']);
		
		$args = array(
			'posts_per_page'   => $length,
			'offset'           => $offset,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_key'         => '_customer_user',
			'meta_value'       => $customer_id,
			'post_type'        => 'shop_order',
			'post_mime_type'   => '',
			'post_parent'      => '',
			//'author'	   => get_current_user_id(),
			// 'post_status'      => 'any',
			'post_status'      => array_keys(wc_get_order_statuses()),
			'suppress_filters' => false
		);

		$args = apply_filters('wcfm_customer_details_orders_args', $args);
		
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			unset($args['meta_key']);
			unset($args['meta_value']);
			$args['customer_id'] = $customer_id;

			$wcfm_orders_array = wc_get_orders($args);
		} else {
			$wcfm_orders_array = get_posts($args);
		}

		// Get Customer Orders Count
		$order_count = 0;
		$filtered_order_count = 0;
		$args['posts_per_page'] = -1;
		$args['offset'] = 0;

		// @TODO: count total orders using custom query by joining the wcfm orders table
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			unset($args['meta_key']);
			unset($args['meta_value']);
			$args['customer_id'] = $customer_id;

			$wcfm_all_orders_array = wc_get_orders($args);
		} else {
			$wcfm_all_orders_array = get_posts($args);
		}

		if (!empty($wcfm_all_orders_array)) {
			$order_count = count($wcfm_all_orders_array);
			$filtered_order_count = count($wcfm_all_orders_array);
		}

		// Generate Customer Orders JSON
		$datatable_json = [
			'draw'				=> (int) wc_clean($_POST['draw']),
			'recordsTotal'		=> (int) $order_count,
			'recordsFiltered'	=> (int) $filtered_order_count,
			'data'				=> []
		];
		
		if (!empty($wcfm_orders_array)) {
			$index = 0;
			foreach ($wcfm_orders_array as $wcfm_orders_single) {

				if (!is_a($wcfm_orders_single, 'WC_Order')) {
					$the_order = wc_get_order($wcfm_orders_single->ID);
					if (!is_a($the_order, 'WC_Order')) continue;
 				} else {
					$the_order = $wcfm_orders_single;
				}
				
				$order_id = $the_order->get_id();

				if (wcfm_is_vendor()) {
					$is_order_for_vendor = $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($order_id);
					if (!$is_order_for_vendor) continue;
				}

				// Status
				$datatable_json['data'][$index][] =  '<span class="order-status tips wcicon-status-' . sanitize_title($the_order->get_status()) . ' text_tip" data-tip="' . wc_get_order_status_name($the_order->get_status()) . '"></span>';

				// Order
				if (apply_filters('wcfm_allow_view_customer_name', true)) {
					$user_info = array();
					if ($the_order->get_user_id()) {
						$user_info = get_userdata($the_order->get_user_id());
					}

					if (!empty($user_info)) {

						$username = '';

						if ($user_info->first_name || $user_info->last_name) {
							$username .= esc_html(sprintf(_x('%1$s %2$s', 'full name', 'wc-frontend-manager'), ucfirst($user_info->first_name), ucfirst($user_info->last_name)));
						} else {
							$username .= esc_html(ucfirst($user_info->display_name));
						}
					} else {
						if ($the_order->get_billing_first_name() || $the_order->get_billing_last_name()) {
							$username = trim(sprintf(_x('%1$s %2$s', 'full name', 'wc-frontend-manager'), $the_order->get_billing_first_name(), $the_order->get_billing_last_name()));
						} else if ($the_order->get_billing_company()) {
							$username = trim($the_order->get_billing_company());
						} else {
							$username = __('Guest', 'wc-frontend-manager');
						}
					}

					$username = apply_filters('wcfm_order_by_user', $username, $order_id);
				} else {
					$username = __('Guest', 'wc-frontend-manager');
				}

				if (apply_filters('wcfm_is_allow_order_details', true) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($order_id)) {
					$datatable_json['data'][$index][] =  '<a href="' . esc_url(get_wcfm_view_order_url($order_id, $the_order)) . '" class="wcfm_dashboard_item_title">#' . esc_attr($the_order->get_order_number()) . '</a>' . ' ' . __('by', 'wc-frontend-manager') . ' ' . $username;
				} else {
					$datatable_json['data'][$index][] =  '<span class="wcfm_dashboard_item_title">#' . esc_attr($the_order->get_order_number()) . '</span>' . ' ' . __('by', 'wc-frontend-manager') . ' ' . $username;
				}

				// Purchased
				$order_item_details = '<div class="order_items" cellspacing="0">';
				$items = $the_order->get_items('line_item');
				$items = apply_filters('wcfm_valid_line_items', $items, $order_id);
				$total_qty = 0;
				foreach ($items as $key => $item) {
					if (version_compare(WC_VERSION, '4.4', '<')) {
						$product = $the_order->get_product_from_item($item);
					} else {
						$product = $item->get_product();
					}
					$item_meta_html = strip_tags(wc_display_item_meta($item, array(
						'before'    => "\n- ",
						'separator' => "\n- ",
						'after'     => "",
						'echo'      => false,
						'autop'     => false,
					)));

					$total_qty += $item->get_quantity();
					$order_item_details .= '<div class=""><span class="qty">' . $item->get_quantity() . 'x</span><span class="name">' . $item->get_name();
					if (!empty($item_meta_html)) $order_item_details .= '<span class="img_tip" data-tip="' . $item_meta_html . '"></span>';
					$order_item_details .= '</td></div>';
				}
				$order_item_details .= '</div>';
				$datatable_json['data'][$index][] =  '<a href="#" class="show_order_items">' . apply_filters('woocommerce_admin_order_item_count', sprintf(_n('%d item', '%d items', $total_qty, 'wc-frontend-manager'), $total_qty), $the_order) . '</a>' . $order_item_details;

				// Gross Sales
				if (wcfm_is_vendor()) {
					$gross_sales = $WCFM->wcfm_vendor_support->wcfm_get_gross_sales_by_vendor(apply_filters('wcfm_current_vendor_id', get_current_user_id()), '', '', $order_id);
					$total = '<span class="order_total">' . wc_price($gross_sales) . '</span>';
				} else {
					$total = '<span class="order_total">' . $the_order->get_formatted_order_total() . '</span>';
				}

				if ($the_order->get_payment_method_title()) {
					$total .= '<br /><small class="meta">' . __('Via', 'wc-frontend-manager') . ' ' . esc_html($the_order->get_payment_method_title()) . '</small>';
				}
				$datatable_json['data'][$index][] =  $total;

				// Date
				$order_date = (version_compare(WC_VERSION, '2.7', '<')) ? $the_order->order_date : $the_order->get_date_created();
				$datatable_json['data'][$index][] = date_i18n(wc_date_format(), strtotime($order_date));

				// Action
				$actions = '';
				if ($wcfm_is_allow_order_status_update = apply_filters('wcfm_is_allow_order_status_update', true)) {
					$order_status = sanitize_title($the_order->get_status());
					if (!in_array($order_status, array('failed', 'cancelled', 'refunded', 'completed'))) $actions = '<a class="wcfm_order_mark_complete wcfm-action-icon" href="#" data-orderid="' . $order_id . '"><span class="wcfmfa fa-check-circle text_tip" data-tip="' . esc_attr__('Mark as Complete', 'wc-frontend-manager') . '"></span></a>';
				}

				if (apply_filters('wcfm_is_allow_order_details', true) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($order_id)) {
					$actions .= '<a class="wcfm-action-icon" href="' . get_wcfm_view_order_url($order_id, $the_order) . '"><span class="wcfmfa fa-eye text_tip" data-tip="' . esc_attr__('View Details', 'wc-frontend-manager') . '"></span></a>';
				}

				if (WCFM_Dependencies::wcfmu_plugin_active_check() && WCFM_Dependencies::wcfm_wc_pdf_invoices_packing_slips_plugin_active_check()) {
					if (apply_filters('wcfm_is_allow_pdf_invoice', true) && apply_filters('wcfm_is_allow_view_commission', true)) {
						$actions .= '<a class="wcfm_pdf_invoice wcfm-action-icon" href="#" data-orderid="' . $order_id . '"><span class="wcfmfa fa-file-invoice text_tip" data-tip="' . esc_attr__('PDF Invoice', 'wc-frontend-manager') . '"></span></a>';
					}
					if (apply_filters('wcfm_is_allow_pdf_packing_slip', true)) {
						$actions .= '<a class="wcfm_pdf_packing_slip wcfm-action-icon" href="#" data-orderid="' . $order_id . '"><span class="wcfmfa fa-file-powerpoint text_tip" data-tip="' . esc_attr__('PDF Packing Slip', 'wc-frontend-manager') . '"></span></a>';
					}
				} else {
					if ($is_wcfmu_inactive_notice_show = apply_filters('is_wcfmu_inactive_notice_show', true)) {
						$actions .= '<a class="wcfm_pdf_invoice_dummy wcfm-action-icon" href="#" data-orderid="' . $order_id . '"><span class="wcfmfa fa-file-invoice text_tip" data-tip="' . esc_attr__('PDF Invoice', 'wc-frontend-manager') . '"></span></a>';
					}
				}

				$datatable_json['data'][$index][] =  $actions; //apply_filters ( 'wcfm_orders_actions', $actions, $wcfm_orders_single, $the_order );

				$index++;
			}
		}

		wp_send_json($datatable_json);
	}
}


/**
 * WCFM plugin controllers
 *
 * Plugin Customer Details Bookings Controller
 *
 * @author 		WC Lovers
 * @package 	wcfm/controllers/customers
 * @version   3.5.0
 */
class WCFM_Customers_Details_Bookings_Controller {

	public function __construct() {
		global $WCFM;

		$this->processing();
	}

	public function processing() {
		global $WCFM, $wpdb, $_POST;

		$wc_get_booking_status_name = array('paid' => __('Paid & Confirmed', 'wc-frontend-manager'), 'pending-confirmation' => __('Pending Confirmation', 'wc-frontend-manager'), 'unpaid' => __('Un-paid', 'wc-frontend-manager'), 'cancelled' => __('Cancelled', 'wc-frontend-manager'), 'complete' => __('Complete', 'wc-frontend-manager'), 'confirmed' => __('Confirmed', 'wc-frontend-manager'));

		$length = absint($_POST['length']);
		$offset = absint($_POST['start']);
		$customer_id = absint($_POST['customer_id']);

		$include_bookings = apply_filters('wcfm_wcb_include_bookings', '');

		$args = array(
			'posts_per_page'   => $length,
			'offset'           => $offset,
			'category'         => '',
			'category_name'    => '',
			'bookingby'        => 'date',
			'booking'          => 'DESC',
			'include'          => $include_bookings,
			'exclude'          => '',
			'meta_key'         => '_booking_customer_id',
			'meta_value'       => $customer_id,
			'post_type'        => 'wc_booking',
			'post_mime_type'   => '',
			'post_parent'      => '',
			//'author'	   => get_current_user_id(),
			'post_status'      => array('complete', 'paid', 'confirmed', 'pending-confirmation', 'cancelled', 'unpaid'),
			//'suppress_filters' => 0 
		);

		$args = apply_filters('wcfm_bookings_args', $args);

		$wcfm_bookings_array = get_posts($args);

		// Get Product Count
		$booking_count = 0;
		$filtered_booking_count = 0;
		$wcfm_bookings_count = wp_count_posts('wc_booking');
		$booking_count = count($wcfm_bookings_array);
		// Get Filtered Post Count
		$args['posts_per_page'] = -1;
		$args['offset'] = 0;
		$wcfm_filterd_bookings_array = get_posts($args);
		$filtered_booking_count = count($wcfm_filterd_bookings_array);


		// Generate Products JSON
		$wcfm_bookings_json = '';
		$wcfm_bookings_json = '{
															"draw": ' . wc_clean($_POST['draw']) . ',
															"recordsTotal": ' . $booking_count . ',
															"recordsFiltered": ' . $filtered_booking_count . ',
															"data": ';
		if (!empty($wcfm_bookings_array)) {
			$index = 0;
			$wcfm_bookings_json_arr = array();
			foreach ($wcfm_bookings_array as $wcfm_bookings_single) {
				$the_booking = new WC_Booking($wcfm_bookings_single->ID);
				$product_id  = $the_booking->get_product_id('edit');
				$product     = $the_booking->get_product($product_id);
				$the_order   = $the_booking->get_order();

				if ($the_booking->has_status(array('was-in-cart', 'in-cart'))) continue;

				// Status
				$wcfm_bookings_json_arr[$index][] =  '<span class="booking-status tips wcicon-status-' . sanitize_title($the_booking->get_status()) . ' text_tip" data-tip="' . $wc_get_booking_status_name[$the_booking->get_status()] . '"></span>';

				// Booking
				$booking_label =  '<a href="' . get_wcfm_view_booking_url($wcfm_bookings_single->ID, $the_booking) . '" class="wcfm_booking_title">' . __('#', 'wc-frontend-manager') . $wcfm_bookings_single->ID . '</a>';

				$customer = $the_booking->get_customer();
				if (!isset($customer->user_id) || 0 == $customer->user_id) {
					$booking_label .= ' by ';
					if ($customer->name) {
						$guest_name = $customer->name;
						$guest_name = apply_filters('wcfm_booking_by_user', $guest_name, $wcfm_bookings_single->ID, $the_order->get_order_number());
						if (apply_filters('wcfm_allow_view_customer_email', true) && $customer->email) {
							$booking_label .= '<a href="mailto:' .  $customer->email . '">' . $guest_name . '</a>';
						} else {
							$booking_label .= $guest_name;
						}
					} else {
						$booking_label .= sprintf(_x('Guest (%s)', 'Guest string with name from booking order in brackets', 'wc-frontend-manager'), '&ndash;');
					}
				} elseif ($customer) {
					if ($the_order) {
						$guest_name = apply_filters('wcfm_booking_by_user', $customer->name, $wcfm_bookings_single->ID, $the_order->get_order_number());
						$booking_label .= ' by ';
						if (apply_filters('wcfm_allow_view_customer_email', true)) {
							$booking_label .= '<a href="mailto:' .  $customer->email . '">' . $guest_name . '</a>';
						} else {
							$booking_label .= $guest_name;
						}
					} else {
						$guest_name = $customer->name;
						$booking_label .= ' by ';
						if (apply_filters('wcfm_allow_view_customer_email', true)) {
							$booking_label .= '<a href="mailto:' .  $customer->email . '">' . $guest_name . '</a>';
						} else {
							$booking_label .= $guest_name;
						}
					}
				}
				$wcfm_bookings_json_arr[$index][] = $booking_label;

				// Product
				//$resource = $the_booking->get_resource();

				if ($product) {
					$product_post = get_post($product->get_ID());
					$wcfm_bookings_json_arr[$index][] = $product_post->post_title;
					//if ( $resource ) {
					//$wcfm_bookings_json_arr[$index][] = $resource->post_title;
					//}
				} else {
					$wcfm_bookings_json_arr[$index][] = '&ndash;';
				}

				// #of Persons
				/*$persons = get_post_meta( $wcfm_bookings_single->ID, '_booking_persons', true );
				$total_persons = 0;
				if ( ! empty( $persons ) && is_array( $persons ) ) {
					foreach ( $persons as $person_count ) {
						$total_persons = $total_persons + $person_count;
					}
				}

				$wcfm_bookings_json_arr[$index][] =  esc_html( $total_persons );*/

				// Order
				if ($the_order) {
					if (apply_filters('wcfm_is_allow_order_details', true) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($the_order->get_order_number())) {
						$wcfm_bookings_json_arr[$index][] = '<span class="booking-orderno"><a href="' . get_wcfm_view_order_url($the_order->get_order_number(), $the_order) . '">#' . $the_order->get_order_number() . '</a></span><br />' . esc_html(wc_get_order_status_name($the_order->get_status()));
					} else {
						$wcfm_bookings_json_arr[$index][] = '<span class="booking-orderno">#' . $the_order->get_order_number() . '</span><br /> ' . esc_html(wc_get_order_status_name($the_order->get_status()));
					}
				} else {
					$wcfm_bookings_json_arr[$index][] = '&ndash;';
				}

				// Start Date
				$wcfm_bookings_json_arr[$index][] = date_i18n(wc_date_format() . ' ' . wc_time_format(), $the_booking->get_start('edit'));

				// End Date
				$wcfm_bookings_json_arr[$index][] = date_i18n(wc_date_format() . ' ' . wc_time_format(), $the_booking->get_end('edit'));

				// Action
				$actions = '';
				if (current_user_can('manage_bookings_settings') || current_user_can('manage_bookings')) {
					if (WCFM_Dependencies::wcfmu_plugin_active_check()) {
						if (in_array($the_booking->get_status(), array('pending-confirmation'))) $actions = '<a class="wcfm_booking_mark_confirm wcfm-action-icon" href="#" data-bookingid="' . $wcfm_bookings_single->ID . '"><span class="wcfmfa fa-check-circle text_tip" data-tip="' . esc_attr__('Mark as Confirmed', 'wc-frontend-manager') . '"></span></a>';
					}
				}
				$actions .= apply_filters('wcfm_bookings_actions', '<a class="wcfm-action-icon" href="' . get_wcfm_view_booking_url($wcfm_bookings_single->ID, $the_booking) . '"><span class="wcfmfa fa-eye text_tip" data-tip="' . esc_attr__('View Details', 'wc-frontend-manager') . '"></span></a>', $wcfm_bookings_single, $the_booking);
				$wcfm_bookings_json_arr[$index][] = $actions;


				$index++;
			}
		}
		if (!empty($wcfm_bookings_json_arr)) $wcfm_bookings_json .= json_encode($wcfm_bookings_json_arr);
		else $wcfm_bookings_json .= '[]';
		$wcfm_bookings_json .= '
													}';

		echo $wcfm_bookings_json;
	}
}


/**
 * WCFM plugin controllers
 *
 * Plugin Customer Details Appointments Controller
 *
 * @author 		WC Lovers
 * @package 	wcfm/controllers/customers
 * @version   3.5.0
 */
class WCFM_Customers_Details_Appointments_Controller {

	public function __construct() {
		global $WCFM;

		$this->processing();
	}

	public function processing() {
		global $WCFM, $wpdb, $_POST, $WCFMu;

		$wc_get_appointment_status_name = array('paid' => __('Paid', 'wc-frontend-manager'), 'pending-confirmation' => __('Pending Confirmation', 'wc-frontend-manager'), 'unpaid' => __('Un-paid', 'wc-frontend-manager'), 'cancelled' => __('Cancelled', 'wc-frontend-manager'), 'complete' => __('Complete', 'wc-frontend-manager'), 'confirmed' => __('Confirmed', 'wc-frontend-manager'));

		if (class_exists('WC_Deposits')) {
			$wc_get_appointment_status_name['wc-partial-payment'] = __('Partial Paid', 'wc-frontend-manager');
		}



		$length = absint($_POST['length']);
		$offset = absint($_POST['start']);
		$customer_id = absint($_POST['customer_id']);

		$include_appointments = apply_filters('wcfm_wca_include_appointments', '');

		$args = array(
			'posts_per_page'   => $length,
			'offset'           => $offset,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'include'          => $include_appointments,
			'exclude'          => '',
			'meta_key'         => '_appointment_customer_id',
			'meta_value'       => $customer_id,
			'post_type'        => 'wc_appointment',
			'post_mime_type'   => '',
			'post_parent'      => '',
			//'author'	   => get_current_user_id(),
			'post_status'      => array_keys($wc_get_appointment_status_name),
			//'suppress_filters' => 0 
		);

		$args = apply_filters('wcfm_appointments_args', $args);
		$wcfm_appointments_array = get_posts($args);

		// Get Product Count
		$appointment_count = 0;
		$filtered_appointment_count = 0;
		$wcfm_appointments_count = wp_count_posts('wc_appointment');
		$appointment_count = count($wcfm_appointments_array);
		// Get Filtered Post Count
		$args['posts_per_page'] = -1;
		$args['offset'] = 0;
		$wcfm_filterd_appointments_array = get_posts($args);
		$filtered_appointment_count = count($wcfm_filterd_appointments_array);


		// Generate Products JSON
		$wcfm_appointments_json = '';
		$wcfm_appointments_json = '{
															"draw": ' . wc_clean($_POST['draw']) . ',
															"recordsTotal": ' . $appointment_count . ',
															"recordsFiltered": ' . $filtered_appointment_count . ',
															"data": ';
		if (!empty($wcfm_appointments_array)) {
			$index = 0;
			$wcfm_appointments_json_arr = array();
			foreach ($wcfm_appointments_array as $wcfm_appointments_single) {
				$the_appointment = new WC_Appointment($wcfm_appointments_single->ID);
				$product_id  = $the_appointment->get_product_id('edit');
				$product     = $the_appointment->get_product($product_id);
				$the_order   = $the_appointment->get_order();
				if ($the_appointment->has_status(array('was-in-cart', 'in-cart'))) continue;

				// Status
				$wcfm_appointments_json_arr[$index][] =  '<span class="appointment-status tips wcicon-status-' . sanitize_title($the_appointment->get_status()) . ' text_tip" data-tip="' . $wc_get_appointment_status_name[$the_appointment->get_status()] . '"></span>';

				// Appointment
				$appointment_label =  '<a href="' . esc_url(get_wcfm_view_appointment_url($wcfm_appointments_single->ID, $the_appointment)) . '" class="wcfm_appointment_title">#' . $wcfm_appointments_single->ID . '</a>';

				$customer = $the_appointment->get_customer();
				if (!isset($customer->user_id) || 0 == $customer->user_id) {
					$appointment_label .= ' by ';
					if ($customer->full_name) {
						$guest_name = $customer->full_name;
					} else {
						$guest_name = ' - ';
					}
					$appointment_label .= sprintf(_x('Guest (%s)', 'Guest string with name from appointment order in brackets', 'wc-frontend-manager'), $guest_name);
				} elseif ($customer) {
					$appointment_label .= ' by ';
					if (apply_filters('wcfm_allow_view_customer_email', true)) {
						$appointment_label .= '<a href="mailto:' .  $customer->email . '">' . $customer->full_name . '</a>';
					} else {
						$appointment_label .= $customer->full_name;
					}
				}
				$wcfm_appointments_json_arr[$index][] = $appointment_label;

				// Product
				//$resource = $the_appointment->get_resource();

				if ($product) {
					$product_post = get_post($product->get_ID());
					$wcfm_appointments_json_arr[$index][] = $product_post->post_title;
					//if ( $resource ) {
					//$wcfm_appointments_json_arr[$index][] = $resource->post_title;
					//}
				} else {
					$wcfm_appointments_json_arr[$index][] = '-';
				}

				// #of Persons
				/*$persons = get_post_meta( $wcfm_appointments_single->ID, '_appointment_persons', true );
				$total_persons = 0;
				if ( ! empty( $persons ) && is_array( $persons ) ) {
					foreach ( $persons as $person_count ) {
						$total_persons = $total_persons + $person_count;
					}
				}

				$wcfm_appointments_json_arr[$index][] =  esc_html( $total_persons );*/

				// Order
				if ($the_order) {
					if (apply_filters('wcfm_is_allow_order_details', true) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($the_order->get_order_number())) {
						$wcfm_appointments_json_arr[$index][] = '<span class="appointment-orderno"><a href="' . esc_url(get_wcfm_view_order_url($the_order->get_order_number(), $the_order)) . '">#' . $the_order->get_order_number() . '</a></span><br />' . esc_html(wc_get_order_status_name($the_order->get_status()));
					} else {
						$wcfm_appointments_json_arr[$index][] = '<span class="appointment-orderno">#' . $the_order->get_order_number() . '</span><br /> ' . esc_html(wc_get_order_status_name($the_order->get_status()));
					}
				} else {
					$wcfm_appointments_json_arr[$index][] = '&ndash;';
				}

				// Start Date
				$wcfm_appointments_json_arr[$index][] = date_i18n(wc_date_format() . ' ' . wc_time_format(), $the_appointment->get_start('edit'));

				// End Date
				$wcfm_appointments_json_arr[$index][] = date_i18n(wc_date_format() . ' ' . wc_time_format(), $the_appointment->get_end('edit'));

				// Action
				$actions = '';
				if (current_user_can('manage_appointments')) {
					if (in_array($the_appointment->get_status(), array('pending-confirmation'))) $actions = '<a class="wcfm_appointment_mark_confirm wcfm-action-icon" href="#" data-appointmentid="' . $wcfm_appointments_single->ID . '"><span class="wcfmfa fa-check-circle text_tip" data-tip="' . esc_attr__('Mark as Confirmed', 'wc-frontend-manager') . '"></span></a>';
				}
				$actions .= apply_filters('wcfm_appointments_actions', '<a class="wcfm-action-icon" href="' . esc_url(get_wcfm_view_appointment_url($wcfm_appointments_single->ID, $the_appointment)) . '"><span class="wcfmfa fa-eye text_tip" data-tip="' . esc_attr__('View Details', 'wc-frontend-manager') . '"></span></a>', $wcfm_appointments_single, $the_appointment);
				$wcfm_appointments_json_arr[$index][] = $actions;


				$index++;
			}
		}
		if (!empty($wcfm_appointments_json_arr)) $wcfm_appointments_json .= json_encode($wcfm_appointments_json_arr);
		else $wcfm_appointments_json .= '[]';
		$wcfm_appointments_json .= '
													}';

		echo $wcfm_appointments_json;
	}
}


/**
 * WCFM plugin controllers
 *
 * Plugin Customer Details Wallet Transactions Controller
 *
 * @package wcfm/controllers/customers
 */
class WCFM_Customers_Details_Wallet_Controller {

	public function __construct() {
		$this->processing();
	}

	public function processing() {
		global $WCFM, $wpdb, $_POST;

		$draw        = isset( $_POST['draw'] ) ? (int) wc_clean( $_POST['draw'] ) : 1;
		$length      = isset( $_POST['length'] ) ? absint( $_POST['length'] ) : 10;
		$offset      = isset( $_POST['start'] )  ? absint( $_POST['start'] )  : 0;
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		$search      = isset( $_POST['search']['value'] ) ? sanitize_text_field( wc_clean( $_POST['search']['value'] ) ) : '';

		if ( ! $length ) {
			$length = 10;
		}

		$table = $wpdb->prefix . 'wps_wsfw_wallet_transaction';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->suppress_errors( true );

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		if ( ! $table_exists ) {
			$wpdb->suppress_errors( false );
			wp_send_json( array(
				'draw'            => $draw,
				'recordsTotal'    => 0,
				'recordsFiltered' => 0,
				'data'            => array(),
			) );
			return;
		}

		$where_sql  = 'WHERE user_id = %d';
		$base_args  = array( $customer_id );

		if ( $search ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql   .= ' AND (transaction_type LIKE %s OR note LIKE %s OR payment_method LIKE %s)';
			$base_args[]  = $like;
			$base_args[]  = $like;
			$base_args[]  = $like;
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} {$where_sql}",
			...$base_args
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
			...array_merge( $base_args, array( $length, $offset ) )
		), ARRAY_A );

		$wpdb->suppress_errors( false );
		// phpcs:enable

		$data = array();

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {

				// Type badge — transaction_type_1 holds 'credit' or 'debit'
				$type_key  = strtolower( trim( $row['transaction_type_1'] ?? '' ) );
				if ( 'credit' === $type_key ) {
					$type_html = '<span class="wcfm-wallet-type wcfm-wallet-credit">' . esc_html__( 'Credit', 'wc-frontend-manager' ) . '</span>';
				} else {
					$type_html = '<span class="wcfm-wallet-type wcfm-wallet-debit">' . esc_html__( 'Debit', 'wc-frontend-manager' ) . '</span>';
				}

				// Amount
				$amount_html = '<span class="' . ( 'credit' === $type_key ? 'wcfm-wallet-amount-credit' : 'wcfm-wallet-amount-debit' ) . '">'
					. ( 'credit' === $type_key ? '+' : '&minus;' )
					. wc_price( $row['amount'] )
					. '</span>';

				// Description — transaction_type contains the reason (may include raw HTML with order link)
				$desc_html = ! empty( $row['transaction_type'] ) ? wp_kses_post( html_entity_decode( $row['transaction_type'] ) ) : '&ndash;';

				// Order ID — stored in transaction_id column
				$order_html = '&ndash;';
				$order_id   = ! empty( $row['transaction_id'] ) ? absint( $row['transaction_id'] ) : 0;

				if ( $order_id ) {
					$the_order = wc_get_order( $order_id );
					if ( $the_order && apply_filters( 'wcfm_is_allow_order_details', true ) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor( $order_id ) ) {
						$order_html = '<a href="' . esc_url( get_wcfm_view_order_url( $order_id, $the_order ) ) . '" class="wcfm-wallet-order-link">#' . esc_html( $the_order->get_order_number() ) . '</a>';
						if ( $the_order->get_status() ) {
							$order_html .= '<br><small>' . esc_html( wc_get_order_status_name( $the_order->get_status() ) ) . '</small>';
						}
					} else {
						$order_html = '#' . esc_html( $order_id );
					}
				}

				// Payment method
				$payment_html = ! empty( $row['payment_method'] ) ? esc_html( $row['payment_method'] ) : '&ndash;';

				// Note
				$note_html = ! empty( $row['note'] ) ? esc_html( $row['note'] ) : '&ndash;';

				// Date
				$date_html = ! empty( $row['date'] )
					? esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $row['date'] ) ) )
					: '&ndash;';

				$data[] = array(
					$type_html,
					$amount_html,
					$desc_html,
					$order_html,
					$payment_html,
					$note_html,
					$date_html,
				);
			}
		}

		wp_send_json( array(
			'draw'            => $draw,
			'recordsTotal'    => $total,
			'recordsFiltered' => $total,
			'data'            => $data,
		) );
	}
}

/**
 * WCFM Customer Details – Notifications Controller
 *
 * Handles server-side DataTables AJAX for the five notification tabs:
 * system, sms, whatsapp, email, inapp.
 */
class WCFM_Customers_Details_Notification_Controller {

	public function __construct() {
		$this->processing();
	}

	public function processing() {
		global $wpdb, $_POST;

		$draw        = isset( $_POST['draw'] )        ? (int) wc_clean( $_POST['draw'] ) : 1;
		$length      = isset( $_POST['length'] )      ? absint( $_POST['length'] )       : 25;
		$offset      = isset( $_POST['start'] )       ? absint( $_POST['start'] )        : 0;
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] )  : 0;
		$tab         = isset( $_POST['notif_tab'] )   ? sanitize_key( $_POST['notif_tab'] ) : 'system';
		$search      = isset( $_POST['search']['value'] ) ? sanitize_text_field( wc_clean( $_POST['search']['value'] ) ) : '';

		if ( ! $length ) {
			$length = 25;
		}

		switch ( $tab ) {
			case 'email':
				$this->tab_email( $draw, $length, $offset, $customer_id, $search );
				break;
			case 'sms':
				$this->tab_channel( $draw, $length, $offset, $customer_id, $search, 'sms' );
				break;
			case 'whatsapp':
				$this->tab_channel( $draw, $length, $offset, $customer_id, $search, 'whatsapp' );
				break;
			case 'inapp':
				$this->tab_inapp( $draw, $length, $offset, $customer_id, $search );
				break;
			case 'system':
			default:
				$this->tab_system( $draw, $length, $offset, $customer_id, $search );
				break;
		}
	}

	/* ---------------------------------------------------------------
	 * System tab: WooCommerce order notes for this customer's orders
	 * ------------------------------------------------------------- */
	private function tab_system( $draw, $length, $offset, $customer_id, $search ) {
		global $wpdb;

		$order_ids = wc_get_orders( array(
			'customer_id' => $customer_id,
			'return'      => 'ids',
			'limit'       => -1,
			'type'        => 'shop_order',
		) );

		if ( empty( $order_ids ) ) {
			$this->send_empty( $draw );
			return;
		}

		$ids_ph     = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$base_args  = $order_ids;
		$where_extra = '';

		if ( $search ) {
			$where_extra  = ' AND c.comment_content LIKE %s';
			$base_args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->comments} c
			 WHERE c.comment_post_ID IN ({$ids_ph})
			 AND c.comment_type IN ('order_note','customer_note')
			 {$where_extra}",
			...$base_args
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.comment_ID, c.comment_date, c.comment_content, c.comment_type, c.comment_post_ID
			 FROM {$wpdb->comments} c
			 WHERE c.comment_post_ID IN ({$ids_ph})
			 AND c.comment_type IN ('order_note','customer_note')
			 {$where_extra}
			 ORDER BY c.comment_date DESC
			 LIMIT %d OFFSET %d",
			...array_merge( $base_args, array( $length, $offset ) )
		), ARRAY_A );
		// phpcs:enable

		$data = array();
		foreach ( (array) $rows as $row ) {
			$is_customer  = ( 'customer_note' === $row['comment_type'] );
			$badge        = $is_customer
				? '<span class="wcfm-notif-badge wcfm-notif-customer">' . esc_html__( 'Customer', 'wc-frontend-manager' ) . '</span>'
				: '<span class="wcfm-notif-badge wcfm-notif-system-note">' . esc_html__( 'System', 'wc-frontend-manager' ) . '</span>';
			$order_id     = absint( $row['comment_post_ID'] );
			$order_html   = $order_id
				? '<a href="' . esc_url( get_wcfm_view_order_url( $order_id ) ) . '">#' . esc_html( $order_id ) . '</a>'
				: '&ndash;';
			$data[] = array(
				esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $row['comment_date'] ) ) ),
				$badge,
				$order_html,
				wp_kses_post( $row['comment_content'] ),
			);
		}

		wp_send_json( array( 'draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data ) );
	}

	/* ---------------------------------------------------------------
	 * Email tab: transmail failed-email log
	 * ------------------------------------------------------------- */
	private function tab_email( $draw, $length, $offset, $customer_id, $search ) {
		global $wpdb;

		$user = get_userdata( $customer_id );
		if ( ! $user ) {
			$this->send_empty( $draw );
			return;
		}

		$table = $wpdb->prefix . 'transmail_failed_emails';
		$wpdb->suppress_errors( true );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$wpdb->suppress_errors( false );

		if ( ! $exists ) {
			$this->send_empty( $draw );
			return;
		}

		$base_args   = array( $user->user_email );
		$where_extra = '';

		if ( $search ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$where_extra  = ' AND (email_subject LIKE %s OR email_body LIKE %s)';
			$base_args[]  = $like;
			$base_args[]  = $like;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE email_address = %s {$where_extra}",
			...$base_args
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE email_address = %s {$where_extra} ORDER BY failed_at DESC LIMIT %d OFFSET %d",
			...array_merge( $base_args, array( $length, $offset ) )
		), ARRAY_A );
		// phpcs:enable

		$data = array();
		foreach ( (array) $rows as $row ) {
			$status = '<span class="wcfm-notif-badge wcfm-notif-failed">' . esc_html__( 'Failed', 'wc-frontend-manager' ) . '</span>';
			if ( (int) $row['retry_count'] > 0 ) {
				$status .= ' <small>(' . absint( $row['retry_count'] ) . ' retries)</small>';
			}
			$data[] = array(
				esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $row['failed_at'] ) ) ),
				esc_html( $row['email_address'] ),
				esc_html( $row['email_subject'] ),
				$status,
			);
		}

		wp_send_json( array( 'draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data ) );
	}

	/* ---------------------------------------------------------------
	 * SMS / WhatsApp tabs: custom notification log table
	 * Table: {prefix}wcfm_notification_log  (created externally)
	 * Columns: id, customer_id, channel, phone, message, status, sent_at
	 * ------------------------------------------------------------- */
	private function tab_channel( $draw, $length, $offset, $customer_id, $search, $channel ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wcfm_notification_log';
		$wpdb->suppress_errors( true );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$wpdb->suppress_errors( false );

		if ( ! $exists ) {
			$this->send_empty( $draw );
			return;
		}

		$base_args   = array( $customer_id, $channel );
		$where_extra = '';

		if ( $search ) {
			$where_extra  = ' AND message LIKE %s';
			$base_args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND channel = %s {$where_extra}",
			...$base_args
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE customer_id = %d AND channel = %s {$where_extra} ORDER BY sent_at DESC LIMIT %d OFFSET %d",
			...array_merge( $base_args, array( $length, $offset ) )
		), ARRAY_A );
		// phpcs:enable

		$data = array();
		foreach ( (array) $rows as $row ) {
			$status_class = 'sent' === strtolower( $row['status'] ?? '' ) ? 'wcfm-notif-sent' : 'wcfm-notif-failed';
			$data[] = array(
				esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $row['sent_at'] ) ) ),
				esc_html( $row['phone'] ?? '&ndash;' ),
				esc_html( $row['message'] ?? '&ndash;' ),
				'<span class="wcfm-notif-badge ' . $status_class . '">' . esc_html( ucfirst( $row['status'] ?? '' ) ) . '</span>',
			);
		}

		wp_send_json( array( 'draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data ) );
	}

	/* ---------------------------------------------------------------
	 * In-App tab: WCFM messages addressed to this customer
	 * ------------------------------------------------------------- */
	private function tab_inapp( $draw, $length, $offset, $customer_id, $search ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'wcfm_messages';
		$base_args   = array( $customer_id );
		$where_extra = '';

		if ( $search ) {
			$where_extra  = ' AND m.message LIKE %s';
			$base_args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} m WHERE m.message_to = %d {$where_extra}",
			...$base_args
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT m.*, u.display_name
			 FROM {$table} m
			 LEFT JOIN {$wpdb->users} u ON u.ID = m.author_id
			 WHERE m.message_to = %d {$where_extra}
			 ORDER BY m.created DESC
			 LIMIT %d OFFSET %d",
			...array_merge( $base_args, array( $length, $offset ) )
		), ARRAY_A );
		// phpcs:enable

		$type_map = array(
			'order'          => array( 'label' => 'Order',   'class' => 'wcfm-notif-order' ),
			'new_product'    => array( 'label' => 'Product', 'class' => 'wcfm-notif-product' ),
			'product_review' => array( 'label' => 'Review',  'class' => 'wcfm-notif-review' ),
		);

		$data = array();
		foreach ( (array) $rows as $row ) {
			$mtype      = $row['message_type'] ?? '';
			$type_cfg   = $type_map[ $mtype ] ?? array( 'label' => ucfirst( $mtype ), 'class' => 'wcfm-notif-system-note' );
			$type_html  = '<span class="wcfm-notif-badge ' . $type_cfg['class'] . '">' . esc_html( $type_cfg['label'] ) . '</span>';
			$from_html  = $row['author_is_admin'] ? '<strong>Admin</strong>' : esc_html( $row['display_name'] ?? 'System' );
			$data[] = array(
				esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $row['created'] ) ) ),
				$from_html,
				wp_kses_post( $row['message'] ),
				$type_html,
			);
		}

		wp_send_json( array( 'draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data ) );
	}

	private function send_empty( $draw ) {
		wp_send_json( array( 'draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array() ) );
	}
}
