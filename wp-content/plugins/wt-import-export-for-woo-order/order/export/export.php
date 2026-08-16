<?php

if (!defined('WPINC')) {
	exit;
}
use Automattic\WooCommerce\Utilities\OrderUtil;
class Wt_Import_Export_For_Woo_Order_Export {

	public $parent_module = null;
	public $table_name ;
	public $is_hpos_enabled ;
	private $line_items_max_count = 0;
	private $export_to_separate_columns = false;
	private $export_to_separate_rows = false;
	private $exclude_line_items = false;

	public function __construct($parent_object) {

		$this->parent_module = $parent_object;
        $hpos_data = Wt_Import_Export_For_Woo_Common_Helper::is_hpos_enabled();
        $this->table_name = $hpos_data['table_name'];
        if( strpos($hpos_data['table_name'],'wc_orders') !== false ){
            $this->is_hpos_enabled = true;
        }
	}

	public function prepare_header() {

		$export_columns = $this->parent_module->get_selected_column_names();

		if ($this->exclude_line_items) {
			return apply_filters('hf_alter_csv_header', $export_columns, 0);
		}

		$max_line_items = $this->line_items_max_count;

		for ($i = 1; $i <= $max_line_items; $i++) {
			$export_columns["line_item_{$i}"] = "line_item_{$i}";
		}
		if ($this->export_to_separate_columns) {
			for ($i = 1; $i <= $max_line_items; $i++) {
				$export_columns["line_item_{$i}_name"] = "Product Item {$i} Name";
				$export_columns["line_item_{$i}_product_id"] = "Product Item {$i} id";
				$export_columns["line_item_{$i}_sku"] = "Product Item {$i} SKU";
				$export_columns["line_item_{$i}_quantity"] = "Product Item {$i} Quantity";
				$export_columns["line_item_{$i}_total"] = "Product Item {$i} Total";
				$export_columns["line_item_{$i}_subtotal"] = "Product Item {$i} Subtotal";
			}
		}

		if ($this->export_to_separate_rows) {
			$export_columns = $this->wt_line_item_separate_row_csv_header($export_columns);
		}

		return apply_filters('hf_alter_csv_header', $export_columns, $max_line_items);
	}

	public function wt_line_item_separate_row_csv_header($export_columns) {


		foreach ($export_columns as $s_key => $value) {
			if (strstr($s_key, 'line_item_')) {
				unset($export_columns[$s_key]);
			}
		}

		$export_columns["line_item_product_id"] = "item_product_id";
		$export_columns["line_item_name"] = "item_name";
		$export_columns["line_item_sku"] = "item_sku";
		$export_columns["line_item_quantity"] = "item_quantity";
		$export_columns["line_item_subtotal"] = "item_subtotal";
		$export_columns["line_item_subtotal_tax"] = "item_subtotal_tax";
		$export_columns["line_item_total"] = "item_total";
		$export_columns["line_item_total_tax"] = "item_total_tax";
		
		$export_columns["item_refunded"] = "item_refunded";
		$export_columns["item_refunded_qty"] = "item_refunded_qty";
		$export_columns["item_meta"] = "item_meta";
		return $export_columns;
	}

	public function wt_line_item_separate_row_csv_data($order, $order_export_data, $order_data_filter_args) {
		if ($order) {
			if($order->get_items()){
				foreach ($order->get_items() as $item_key => $item) {
					foreach ($order_export_data as $key => $value) {
						if (strpos($key, 'line_item_') !== false) {
							continue;
						} else {
							$data1[$key] = $value;
						}
					}
					$item_data = $item->get_data();
					$product = $item->get_product();

					$data1["line_item_product_id"] = !empty($item_data['product_id']) ? $item_data['product_id'] : '';
					$data1["line_item_name"] = !empty($item_data['name']) ? $item_data['name'] : '';
					$data1["line_item_sku"] = !empty($product) ? $product->get_sku() : '';
					$data1["line_item_quantity"] = !empty($item_data['quantity']) ? $item_data['quantity'] : '';
					$data1["line_item_subtotal"] = !empty($item_data['subtotal']) ? $item_data['subtotal'] : 0;
					$data1["line_item_subtotal_tax"] = !empty($item_data['subtotal_tax']) ? $item_data['subtotal_tax'] : 0;
					$data1["line_item_total"] = !empty($item_data['total']) ? $item_data['total'] : 0;
					$data1["line_item_total_tax"] = !empty($item_data['total_tax']) ? $item_data['total_tax'] : 0;

					$data1["item_refunded"] = !empty($order->get_total_refunded_for_item($item_key)) ? $order->get_total_refunded_for_item($item_key) : '';
					$data1["item_refunded_qty"] = !empty($order->get_qty_refunded_for_item($item_key)) ? absint($order->get_qty_refunded_for_item($item_key)) : '';
					$data1["item_meta"] = !empty($item_data['meta_data']) ? json_encode($item_data['meta_data']) : '';

					$row[] = $data1;
				}
			}else{
				$row[] = $order_export_data;
			}
			return $row;
		}
	}

	public function wt_ier_alter_order_data_befor_export_for_separate_row($data_array) {
		$new_data_array = array();
		foreach ($data_array as $key => $avalue) {
			if (is_array($avalue)) {
				if (count($avalue) == 1) {
					$new_data_array[] = $avalue[0];
				} elseif (count($avalue) > 1) {
					foreach ($avalue as $arrkey => $arrvalue) {
						$new_data_array[] = $arrvalue;
					}
				}
			}
		}
		return $new_data_array;
	}

	/**
	 * Prepare data that will be exported.
	 */
	public function prepare_data_to_export($form_data, $batch_offset) {
		
		$order_by_datetype = !empty($form_data['filter_form_data']['wt_iew_date_type']) ? $form_data['filter_form_data']['wt_iew_date_type'] : 'custom';
		$order_by_dateoption = !empty($form_data['filter_form_data']['wt_iew_date_option']) ? $form_data['filter_form_data']['wt_iew_date_option'] : 'post_date';
		$order_by_statuses = !empty($form_data['filter_form_data']['wt_iew_order_status']) ? $form_data['filter_form_data']['wt_iew_order_status'] : array();
		$order_by_products = !empty($form_data['filter_form_data']['wt_iew_products']) ? $form_data['filter_form_data']['wt_iew_products'] : array();
		$order_by_customers = !empty($form_data['filter_form_data']['wt_iew_email']) ? $form_data['filter_form_data']['wt_iew_email'] : array(); // user email fields return user ids
		$order_by_start_date = !empty($form_data['filter_form_data']['wt_iew_date_from']) ? $form_data['filter_form_data']['wt_iew_date_from'] . ' 00:00:00' : false;
		$order_by_end_date = !empty($form_data['filter_form_data']['wt_iew_date_to']) ? $form_data['filter_form_data']['wt_iew_date_to'] . ' 23:59:59.99' : date('Y-m-d 23:59:59.99', current_time('timestamp'));
		$exclude_already_exported = (!empty($form_data['advanced_form_data']['wt_iew_exclude_already_exported']) && $form_data['advanced_form_data']['wt_iew_exclude_already_exported'] == 'Yes') ? true : false;
		$order_by_coupons = array();
		if (!empty($form_data['filter_form_data']['wt_iew_coupons']) && is_array($form_data['filter_form_data']['wt_iew_coupons'])) {
			$order_by_coupons = $form_data['filter_form_data']['wt_iew_coupons'];
		} elseif (!empty($form_data['filter_form_data']['wt_iew_coupons']) && is_string($form_data['filter_form_data']['wt_iew_coupons'])) {
			$order_by_coupons = array_filter(explode(',', strtolower($form_data['filter_form_data']['wt_iew_coupons'])), 'trim');
		}
		
		if ('last_week' === $order_by_datetype) {
			$order_by_start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
			$order_by_end_date = date('Y-m-d 23:59:59.99', current_time('timestamp'));
		}
		if ('last_month' === $order_by_datetype) {
			$order_by_start_date = date('Y-m-d H:i:s', strtotime('first day of last month'));
			$order_by_end_date = date('Y-m-d H:i:s', strtotime('last day of last month'));
		}
		if ('last_year' === $order_by_datetype) {
			$order_by_start_date = date('Y-m-d H:i:s', strtotime('last year January 1st'));
			$order_by_end_date = date('Y-m-d H:i:s', strtotime('last year December 31st'));
		}

		$order_by_ids = !empty($form_data['filter_form_data']['wt_iew_orders']) ? array_filter(explode(',', strtolower($form_data['filter_form_data']['wt_iew_orders'])), 'trim') : array();
		$order_by_payment_method = !empty($form_data['filter_form_data']['wt_iew_order_payment_method']) ? $form_data['filter_form_data']['wt_iew_order_payment_method'] : array(); // payment method slugs
		$order_by_product_cats = !empty($form_data['filter_form_data']['wt_iew_order_productscat']) ? $form_data['filter_form_data']['wt_iew_order_productscat'] : array(); // product category ids

		$cat_products = array();
		if (!empty($order_by_product_cats)) {
			$cat_products = wc_get_products(array('category' => $order_by_product_cats, 'return' => 'ids', 'limit' => 99999999));
		}

		if(empty($order_by_products)){
			$order_by_products = $cat_products;
		}elseif(!empty($cat_products)){
			$order_by_products = array_intersect($order_by_products, $cat_products);
		}


		$order_by_vendor = !empty($form_data['filter_form_data']['wt_iew_vendor']) ? $form_data['filter_form_data']['wt_iew_vendor'] : 0; // vendor email fields return user id
		$vendor_products = array();
		if ( $order_by_vendor ) {
			$vendor_products = wc_get_products(array('author' => $order_by_vendor, 'return' => 'ids', 'limit' => 99999999));
		}

		if(empty($order_by_products)){
			$order_by_products = $vendor_products;
		}elseif(!empty($vendor_products)){
			$order_by_products = array_intersect($order_by_products, $vendor_products);

		}
	
		$order_by_shipping_methods = !empty($form_data['filter_form_data']['wt_iew_shipping_method']) ? $form_data['filter_form_data']['wt_iew_shipping_method'] : array(); // Shipping methods
		$order_by_billing_locations = !empty($form_data['filter_form_data']['wt_iew_billing_locations_check']) ? $form_data['filter_form_data']['wt_iew_billing_locations_check'] : array(); // Billing locations
		$order_by_shipping_locations = !empty($form_data['filter_form_data']['wt_iew_shipping_locations_check']) ? $form_data['filter_form_data']['wt_iew_shipping_locations_check'] : array(); // Shipping locations

		global $wpdb;
		$left_join_order_items_meta = $order_items_meta_where = $left_join_order_meta = $order_meta_where = array();

		// Orders by products.
		if ( !empty( $order_by_products )  ||  !empty( $vendor_products ) ||  !empty( $cat_products ) ) {
			$product_where = self::prepare_sql_inarray( $order_by_products );
			$left_join_order_items_meta[] = "LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta  AS orderitemmeta_product ON orderitemmeta_product.order_item_id = order_items.order_item_id";
			$order_items_meta_where[] = " (orderitemmeta_product.meta_key IN ('_variation_id', '_product_id') AND orderitemmeta_product.meta_value IN ( $product_where ) )";
		}

		$order_items_meta_where = implode(" AND ", $order_items_meta_where);
		if ($order_items_meta_where) {
			$order_items_meta_where = " AND " . $order_items_meta_where;
		}
		$left_join_order_items_meta = implode("  ", $left_join_order_items_meta);
		if($this->is_hpos_enabled){
			$orderId_field = 'id';
			$order_table = $wpdb->prefix.'wc_orders';
            $order_meta_table = $wpdb->prefix.'wc_orders_meta';
			$order_post_id = 'order_id';
			$post_type = "type";
			$post_status = 'status';

		}else{
			$orderId_field = 'ID';
			$order_table = $wpdb->posts;
            $order_meta_table = $wpdb->postmeta;
			$order_post_id = 'post_id';
			$post_type = "post_type";
			$post_status = 'post_status';
		}


		

		$order_items_where = "";
		if ( $order_items_meta_where ) {
			$order_items_where = " AND orders.{$orderId_field} IN (SELECT DISTINCT order_items.order_id FROM {$wpdb->prefix}woocommerce_order_items as order_items
				$left_join_order_items_meta
				WHERE order_item_type='line_item' $order_items_meta_where )";
		}

		if ( !empty( $order_by_coupons ) ) {
			$values = self::prepare_sql_inarray( $order_by_coupons );
			$order_items_where .= " AND orders.{$orderId_field} IN (SELECT DISTINCT order_coupons.order_id FROM {$wpdb->prefix}woocommerce_order_items as order_coupons
					WHERE order_coupons.order_item_type='coupon'  AND order_coupons.order_item_name in ($values) )";
		}

		// Orders by shipping methods
		if ( !empty( $order_by_shipping_methods ) ) {
			$zone_values = $zone_instance_values = $itemname_values = array();
			foreach ( $order_by_shipping_methods as $value ) {
				if ( preg_match('#^order_item_name:(.+)#', $value, $m) ) {
					$itemname_values[] = $m[1];
				} else {
					$zone_values[] = $value;
					$m = explode(":", $value);
					if (count($m) > 1) {
						$zone_instance_values[] = $m[1];
					}
				}
			}

			$ship_to_method = array();
			if ($zone_values) {
				$zone_values = self::prepare_sql_inarray($zone_values);
				$ship_to_method[] = " (shipping_itemmeta.meta_key='method_id' AND shipping_itemmeta.meta_value IN ($zone_values) ) ";
			}
			if ($zone_instance_values) {
				$zone_instance_values = self::prepare_sql_inarray($zone_instance_values);
				$ship_to_method[] = " (shipping_itemmeta.meta_key='instance_id' AND shipping_itemmeta.meta_value IN ($zone_instance_values ) ) ";
			}
			if ($itemname_values) {
				$itemname_values = self::prepare_sql_inarray($itemname_values);
				$ship_to_method[] = " (order_shippings.order_item_name IN ( $itemname_values ) ) ";
			}
			$ship_to_method = implode(' OR ', $ship_to_method);

			$order_items_where .= " AND orders.{$orderId_field} IN (SELECT order_shippings.order_id FROM {$wpdb->prefix}woocommerce_order_items as order_shippings
						LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS shipping_itemmeta ON  shipping_itemmeta.order_item_id = order_shippings.order_item_id
						WHERE order_shippings.order_item_type='shipping' AND $ship_to_method )";
		}

		// Orders by billing Country/State.
		if ($order_by_billing_locations) {
			$filters = self::country_state_pairs($order_by_billing_locations);
			foreach ($filters as $operator => $fields) {
				foreach ($fields as $field => $values) {
					
					$values = self::prepare_sql_inarray($values);

					if($this->is_hpos_enabled){
						$left_join_order_meta[] = "LEFT JOIN {$wpdb->prefix}wc_order_addresses AS order_address_b{$field} ON order_address_b{$field}.order_id = orders.{$orderId_field}";
            			$order_meta_where[] = " (order_address_b{$field}.{$field} $operator ($values)) ";
					}else{
						$left_join_order_meta[] = "LEFT JOIN {$order_meta_table} AS ordermeta_b{$field} ON ordermeta_b{$field}.post_id = orders.{$orderId_field}";
						$order_meta_where [] = " (ordermeta_b{$field}.meta_key='_billing_$field'  AND ordermeta_b{$field}.meta_value $operator ($values)) ";
					}
				

				}

			}
		}
		// Orders by shipping Country/State.	
		if ($order_by_shipping_locations) {
			$filters = self::country_state_pairs($order_by_shipping_locations);
			foreach ($filters as $operator => $fields) {
				foreach ($fields as $field => $values) {
					$values = self::prepare_sql_inarray($values);
					if($this->is_hpos_enabled){
						$left_join_order_meta[] = "LEFT JOIN {$wpdb->prefix}wc_order_addresses AS order_address_s{$field} ON order_address_s{$field}.order_id = orders.{$orderId_field}";
            			$order_meta_where[] = " (order_address_s{$field}.{$field} $operator ($values)) ";
					}else{
						$left_join_order_meta[] = "LEFT JOIN {$order_meta_table} AS ordermeta_s{$field} ON ordermeta_s{$field}.post_id = orders.{$orderId_field}";
						$order_meta_where [] = " (ordermeta_s{$field}.meta_key='_shipping_$field'  AND ordermeta_s{$field}.meta_value $operator ($values)) ";
					}
				}
			}
		}
		
		// Orders by customers.
		if (!empty($order_by_customers)) {
			$values = self::prepare_sql_inarray($order_by_customers);
			if(!$this->is_hpos_enabled){
				$left_join_order_meta[] = "LEFT JOIN {$order_meta_table} AS ordermeta_customer_user ON ordermeta_customer_user.post_id = orders.{$orderId_field}";
				$order_meta_where [] = " (ordermeta_customer_user.meta_key='_customer_user'  AND ordermeta_customer_user.meta_value in ($values)) ";
			}
		}

		// Orders by payment methods.
		if (!empty($order_by_payment_method) && !$this->is_hpos_enabled) {
			$field = 'payment_method';
			$values = self::prepare_sql_inarray($order_by_payment_method);
			$left_join_order_meta[] = "LEFT JOIN {$order_meta_table} AS ordermeta_payment_method ON ordermeta_payment_method.post_id = orders.{$orderId_field}";
			$order_meta_where [] = " (ordermeta_payment_method.meta_key='_payment_method'  AND ordermeta_payment_method.meta_value in ($values)) ";
		}


		$order_meta_where = implode(" AND ", $order_meta_where);
		if ($order_meta_where !== '') {
			$order_meta_where = " AND " . $order_meta_where;
		}
		$left_join_order_meta = implode("  ", $left_join_order_meta);

		$exclude_orders = array();
		if ($exclude_already_exported) {
			$exclude_orders_qry = "SELECT orders.$orderId_field AS order_id FROM {$order_table} AS orders LEFT JOIN {$order_meta_table} AS ordermeta_already_exported ON (ordermeta_already_exported.{$order_post_id} = orders.{$orderId_field} AND ordermeta_already_exported.meta_key = 'wf_order_exported_status') WHERE orders.{$post_type} = 'shop_order' AND ordermeta_already_exported.meta_value IS NOT NULL";
			$exclude_orders = $wpdb->get_col($exclude_orders_qry);
		}
		
		
		// Orders exclude already exported.
		$base_where = array(1);
		if (!empty($exclude_orders)) {
			$values = self::prepare_sql_inarray($exclude_orders);
			$base_where[] = "orders.{$orderId_field} NOT IN ($values)";
		}

		// Orders by order IDS.
		if (!empty($order_by_ids)) {
			$values = self::prepare_sql_inarray($order_by_ids);
			$base_where[] = "orders.{$orderId_field} IN ($values)";
		}

		// Orders by order statuses.		
		if (!empty($order_by_statuses)) {
			$values = self::prepare_sql_inarray($order_by_statuses);
			$base_where[] = "orders.{$post_status} IN ($values)";
		}
		// Orders by customers hpos.
		if (!empty($order_by_customers) && $this->is_hpos_enabled) {
			$values = self::prepare_sql_inarray($order_by_customers);
			$base_where[] = "orders.customer_id IN ($values)";	
		}
		// Orders by payment methods hpos.
		if (!empty($order_by_payment_method) && $this->is_hpos_enabled) {
			$values = self::prepare_sql_inarray($order_by_payment_method);
			$base_where[] = "orders.payment_method IN ($values)";	
		}
		
		// Orders by date.		
		if (!empty($order_by_start_date) && ( 'post_date' === $order_by_dateoption || 'post_modified' === $order_by_dateoption ) ) {
			if($this->is_hpos_enabled){
				if($order_by_dateoption === 'post_date'){
					$order_by_dateoption  = 'date_created_gmt';
				}elseif($order_by_dateoption === 'post_modified'){
					$order_by_dateoption  = 'date_updated_gmt';
				}
			}
			
			$base_where[] = "orders.$order_by_dateoption >= '$order_by_start_date'";
			$base_where[] = "orders.$order_by_dateoption <= '$order_by_end_date'";
		}
		
		$where_date_meta = array();
		if (!empty($order_by_start_date) && ( 'date_paid' === $order_by_dateoption || 'date_completed' === $order_by_dateoption ) ) {
			if($this->is_hpos_enabled){
				if($order_by_dateoption === 'date_paid'){
					$order_by_dateoption  = 'paid_date';
				}elseif($order_by_dateoption === 'date_completed'){
					$order_by_dateoption  = 'completed_date';
				}
				$start_d = ($order_by_start_date);
				$end_d = ($order_by_end_date);
			}else{
				$start_d = strtotime($order_by_start_date);
				$end_d = strtotime($order_by_end_date);
			}
	
			$where_date_meta[] = "(order_$order_by_dateoption.meta_value>0 AND order_$order_by_dateoption.meta_value >='$start_d' )";
			$where_date_meta[] = "(order_$order_by_dateoption.meta_value>0 AND order_$order_by_dateoption.meta_value <='$end_d' )";
		}
		
		//If date_paid or date_completed.
		if ( $where_date_meta ) {
			$where_d_meta = implode( " AND ", $where_date_meta );
			$base_where[]    = "orders.{$orderId_field}  IN ( SELECT {$order_post_id} FROM {$order_meta_table} AS order_$order_by_dateoption WHERE order_$order_by_dateoption.meta_key ='_$order_by_dateoption' AND $where_d_meta)";
		}

		$order_base_sql = implode(" AND ", $base_where);

		$order_sql_query = "SELECT orders.{$orderId_field} AS order_id FROM {$order_table} AS orders
			{$left_join_order_meta}
			WHERE orders.{$post_type} = 'shop_order' AND $order_base_sql $order_meta_where $order_items_where ORDER BY orders.{$orderId_field} ASC";

		$order_sql_query = apply_filters("wt_ier_order_sql_query", $order_sql_query, $order_base_sql, $order_meta_where, $order_items_where);	
		$filter_form_data = is_array( $form_data['filter_form_data']) ? $form_data['filter_form_data'] : array();
		$advanced_form_data = is_array( $form_data['advanced_form_data']) ? $form_data['advanced_form_data'] : array();
		$transient_key = 'wt_iew_orders_export_' . md5( json_encode( array_merge( $filter_form_data, $advanced_form_data)) );
		$total_queried_orders = get_transient( $transient_key );
		if ( false === $total_queried_orders ) {
			$total_queried_orders = $wpdb->get_col( $order_sql_query );
			set_transient( $transient_key, $total_queried_orders, 60 ); //valid for 60 seconds
		}

		$export_limit = !empty($form_data['filter_form_data']['wt_iew_limit']) ? intval($form_data['filter_form_data']['wt_iew_limit']) : 999999999; //user limit
		$current_offset = !empty($form_data['filter_form_data']['wt_iew_offset']) ? intval($form_data['filter_form_data']['wt_iew_offset']) : 0; //user offset
		$batch_count = !empty($form_data['advanced_form_data']['wt_iew_batch_count']) ? $form_data['advanced_form_data']['wt_iew_batch_count'] : Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('default_export_batch');

		$this->export_to_separate_columns = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate_columns']) && $form_data['advanced_form_data']['wt_iew_export_to_separate_columns'] == 'Yes') ? true : false;
		if (!$this->export_to_separate_columns) {
			$this->export_to_separate_columns = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate']) && $form_data['advanced_form_data']['wt_iew_export_to_separate'] == 'column') ? true : false;
		}

		$this->export_to_separate_rows = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate_rows']) && $form_data['advanced_form_data']['wt_iew_export_to_separate_rows'] == 'Yes') ? true : false;
		if (!$this->export_to_separate_rows) {
			$this->export_to_separate_rows = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate']) && $form_data['advanced_form_data']['wt_iew_export_to_separate'] == 'row') ? true : false;
		}
		$this->exclude_line_items = (!empty($form_data['advanced_form_data']['wt_iew_exclude_line_items']) && $form_data['advanced_form_data']['wt_iew_exclude_line_items'] == 'Yes') ? true : false;
                
                $actual_export_limit = $export_limit;
                if($current_offset != 0){
                    $batch_offset = ($current_offset + $batch_offset);
                    if($actual_export_limit == 999999999){
                        $actual_export_limit = count($total_queried_orders) - $current_offset;
                    }
                    $export_limit = $export_limit + $current_offset;
                }
		if ($batch_count <= $export_limit) {
			if (($batch_offset + $batch_count) > $export_limit) { //last offset
				$limit = $export_limit - $batch_offset;
			} else {
				$limit = $batch_count;
			}
		} else {
			$limit = $export_limit - $batch_offset;
			
		}

		$data_array = array();
		if ($batch_offset < $export_limit) {

			$total_records = 0;
			if ($batch_offset == 0) { // First batch.
				$this->line_items_max_count = $this->get_max_line_items();
				update_option('wt_order_line_items_max_count', $this->line_items_max_count);
			}

			if (empty($this->line_items_max_count)) {
				$this->line_items_max_count = get_option('wt_order_line_items_max_count');
			}


			$order_ids = apply_filters('wt_orderimpexpcsv_alter_order_ids', $total_queried_orders);
			$total_records = count($order_ids);

			$order_ids = array_slice($order_ids, $batch_offset, $limit);
			


			foreach ($order_ids as $order_id) {
				if(wc_get_order($order_id)){
					$data_array[] = $this->generate_row_data($order_id,$form_data);
					// Updating records with expoted status. 	
					if($this->is_hpos_enabled){
						$order = wc_get_order($order_id);
						$order->update_meta_data('wf_order_exported_status', TRUE);
						$order->save();
					}else{
						update_post_meta($order_id, 'wf_order_exported_status', TRUE);
					}				
				}
			}

			if ($this->export_to_separate_rows) {
				$data_array = $this->wt_ier_alter_order_data_befor_export_for_separate_row($data_array);
			}

			$data_array = apply_filters('wt_ier_alter_order_data_befor_export', $data_array);

			if (($batch_offset + $batch_count) > $export_limit) { // Last batch.
				delete_transient($transient_key);
			}
                        
			$return['total'] = $export_limit != 999999999 ? $actual_export_limit : $total_records;
			$return['data'] = $data_array;
                        if( 0 == $batch_offset && 0 == $total_records ){
                             $return['no_post'] = __( 'Nothing to export under the selected criteria. Please check and try adjusting the filters.' );
                         }
			return $return;
		}
	}

	public function generate_row_data($order_id,$form_data) {
 
		$csv_columns = $this->prepare_header();

		$found_meta = array();

		foreach ($csv_columns as $key => $value) {
			if (substr((string) $key, 0, 5) == 'meta:') {
				$found_meta[substr((string) $key, 5)] = $value;
				unset($csv_columns[$key]);
			}
		}
		
		$row = array();
		// Get an instance of the WC_Order object.
		$order = wc_get_order($order_id);
		$line_items = $shipping_items = $fee_items = $tax_items = $coupon_items = $refund_items = array();

		// Get line items.
		foreach ($order->get_items() as $item_id => $item) {
			/* WC_Abstract_Legacy_Order::get_product_from_item() deprecated since version 4.4.0 */
			$product = (WC()->version < '4.4.0') ? $order->get_product_from_item($item) : $item->get_product();
			if (!is_object($product)) {
				$product = new WC_Product(0);
			}

			$item_meta = self::get_order_line_item_meta($item_id);
			$prod_type = $product->get_type();
			$line_item = array(
				'name' => html_entity_decode(!empty($item['name']) ? $item['name'] : $product->get_title(), ENT_NOQUOTES, 'UTF-8'),
				'product_id' => ($prod_type == 'variable' || $prod_type == 'variation' || $prod_type == 'subscription_variation') ? $product->get_parent_id() : $product->get_id(),
				'sku' => $product->get_sku(),
				'quantity' => $item['qty'],
				'total' => wc_format_decimal($order->get_line_total($item), 2),
				'sub_total' => wc_format_decimal($order->get_line_subtotal($item), 2),
			);

			// Add line item tax.
			$line_tax_data = isset($item['line_tax_data']) ? $item['line_tax_data'] : array();
			$tax_data = maybe_unserialize($line_tax_data);
			$tax_detail = isset($tax_data['total']) ? wc_format_decimal(wc_round_tax_total(array_sum((array) $tax_data['total'])), 2) : '';
			if ($tax_detail != '0.00' && !empty($tax_detail)) {
				$line_item['tax'] = $tax_detail;
				$line_tax_ser = maybe_serialize($line_tax_data);
				$line_item['tax_data'] = $line_tax_ser;
			}

			foreach ($item_meta as $key => $value) {
				switch ($key) {
					case '_qty':
					case '_variation_id':
					case '_product_id':
					case '_line_total':
					case '_line_subtotal':
					case '_tax_class':
					case '_line_tax':
					case '_line_tax_data':
					case '_line_subtotal_tax':
						break;

					default:
						if (is_object($value))
							$value = $value->meta_value;
						if (is_array($value))
							$value = implode(',', $value);
							$line_item['meta:' . $key] = $value;
						break;
				}
			}

			$refunded = wc_format_decimal($order->get_total_refunded_for_item($item_id), 2);
			if ($refunded != '0.00') {
				$line_item['refunded'] = $refunded;
			}

			if ($prod_type === 'variable' || $prod_type === 'variation' || $prod_type === 'subscription_variation') {
				$line_item['_variation_id'] = $product->get_id();
			}
			$line_items[] = $line_item;
		}

		// WooCommerce gift cards plugin.
		if (class_exists('WC_GC_Gift_Card')) {

			$giftcards = $order->get_items('gift_card');
			if ($giftcards) {

				foreach ($giftcards as $id => $giftcard_order_item) {
					$giftcard = new WC_GC_Gift_Card($giftcard_order_item->get_giftcard_id());
					if (!$giftcard) {
						continue;
					}

					if ($giftcard->has_expired()) {
						$gift_card_expires = date_i18n(get_option('date_format'), $giftcard->get_expire_date());
					} else {
						$gift_card_expires = 0 === $giftcard->get_expire_date() ? '' : date_i18n(get_option('date_format', $giftcard->get_expire_date()));
					}
					$line_item = array(
						'giftcard-name' => $giftcard_order_item->get_code(),
						'giftcard-id' => $giftcard_order_item->get_giftcard_id(),
						'used_balance' => $giftcard_order_item->get_amount(),
						'available_balance' => $giftcard->get_balance(),
						'gift_card_expires' => $gift_card_expires
					);

					array_push($line_items, $line_item);
				}
			}
		}

		$line_items = apply_filters('wt_iew_export_order_line_items', $line_items, $order_id, $order);

		// Shipping items is just product x qty under shipping method.
		$line_items_shipping = $order->get_items('shipping');

		foreach ($line_items_shipping as $item_id => $item) {
			$item_meta = self::get_order_line_item_meta($item_id);
			foreach ($item_meta as $key => $value) {
				switch ($key) {
					case 'Items':
					case 'method_id':
					case 'taxes':
						if (is_object($value))
							$value = $value->meta_value;
						if (is_array($value))
							$value = implode(',', $value);
						$meta[$key] = $value;
						break;
				}
			}
			foreach (array('Items', 'method_id', 'taxes') as $value) {
				if (!isset($meta[$value])) {
					$meta[$value] = '';
				}
			}
			$shipping_items[] = trim(implode('|', array('items:' . $meta['Items'], 'method_id:' . $meta['method_id'], 'taxes:' . $meta['taxes'])));
		}

		// Get fee and total.
		$fee_total = 0;
		$fee_tax_total = 0;

		foreach ($order->get_fees() as $fee_id => $fee) {
			$fee_items[] = implode('|', array(
				'name:' . html_entity_decode($fee['name'], ENT_NOQUOTES, 'UTF-8'),
				'total:' . wc_format_decimal($fee['line_total'], 2),
				'tax:' . wc_format_decimal($fee['line_tax'], 2),
				'tax_data:' . maybe_serialize($fee['line_tax_data'])
			));
			$fee_total += (float) $fee['line_total'];
            $fee_tax_total += (float) $fee['line_tax'];
		}

		$order_taxes = $order->get_taxes();
		if (!empty($order_taxes)) {
			foreach ($order_taxes as $tax_id => $tax_item) {
				if (!empty($tax_item->get_shipping_tax_total())) {
					$total = $tax_item->get_tax_total() + $tax_item->get_shipping_tax_total();
				} else {
					$total = $tax_item->get_tax_total();
				}
				$tax_items[] = implode('|', array(
					'rate_id:' . $tax_item->get_rate_id(),
					'code:' . $tax_item->get_rate_code(),
					'total:' . wc_format_decimal($tax_item->get_tax_total(), 2),
					'label:' . $tax_item->get_label(),
					'tax_rate_compound:' . $tax_item->get_compound(),
					'shipping_tax_amount:' . $tax_item->get_shipping_tax_total(),
					'rate_percent:' . $tax_item->get_rate_percent(),
				));
			}
		}

		// Add coupons.
		foreach ($order->get_items('coupon') as $_ => $coupon_item) {
			$discount_amount = (WC()->version < '4.4.0') ? $coupon_item['discount_amount'] : $coupon_item->get_discount();
			$discount_amount = !empty($discount_amount) ? $discount_amount : 0;
			$coupon_code = (WC()->version < '4.4.0') ? $coupon_item['name'] : $coupon_item->get_code();
			$coupon_items[] = implode('|', array(
				'code:' . $coupon_code,
				'amount:' . wc_format_decimal($discount_amount, 2),
			));
		}

		foreach ($order->get_refunds() as $refunded_items) {
				$refund_items[] = implode('|', array(
					'amount:' . $refunded_items->get_amount(),
					'reason:' . $refunded_items->get_reason(),
					'date:' . date('Y-m-d H:i:s', strtotime($refunded_items->get_date_created())),
				));
		}

		$order_data = array(
			'order_id' => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'order_date' => date('Y-m-d H:i:s', strtotime(get_post($order->get_id())->post_date)),
			'paid_date' => $order->get_date_paid(),
			'status' => $order->get_status(),
			'shipping_total' => $order->get_total_shipping(),
			'shipping_tax_total' => wc_format_decimal($order->get_shipping_tax(), 2),
			'fee_total' => wc_format_decimal($fee_total, 2),
			'fee_tax_total' => wc_format_decimal($fee_tax_total, 2),
			'tax_total' => wc_format_decimal($order->get_total_tax(), 2),
			'cart_discount' => (defined('WC_VERSION') && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_discount_total(), 2) : wc_format_decimal($order->get_discount_total(), 2),
			'order_discount' => (defined('WC_VERSION') && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_discount_total(), 2) : wc_format_decimal($order->get_discount_total(), 2),
			'discount_total' => wc_format_decimal($order->get_total_discount(), 2),
			'order_total' => wc_format_decimal($order->get_total(), 2),
			'order_subtotal' => wc_format_decimal($order->get_subtotal(), 2), // Get order subtotal
			'order_currency' => $order->get_currency(),
			'payment_method' => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
			'transaction_id' => $order->get_transaction_id(),
			'customer_ip_address' => $order->get_customer_ip_address(),
			'customer_user_agent' => $order->get_customer_user_agent(),
			'shipping_method' => $order->get_shipping_method(),
			'customer_id' => $order->get_user_id(),
			'customer_user' => $order->get_user_id(),
			'customer_email' => ($a = get_userdata($order->get_user_id())) ? $a->user_email : '',
			'billing_first_name' => $order->get_billing_first_name(),
			'billing_last_name' => $order->get_billing_last_name(),
			'billing_company' => $order->get_billing_company(),
			'billing_email' => $order->get_billing_email(),
			'billing_phone' => $order->get_billing_phone(),
			'billing_address_1' => $order->get_billing_address_1(),
			'billing_address_2' => $order->get_billing_address_2(),
			'billing_postcode' => $order->get_billing_postcode(),
			'billing_city' => $order->get_billing_city(),
			'billing_state' => $order->get_billing_state(),
			'billing_country' => $order->get_billing_country(),
			'shipping_first_name' => $order->get_shipping_first_name(),
			'shipping_last_name' => $order->get_shipping_last_name(),
			'shipping_company' => $order->get_shipping_company(),
			'shipping_phone' => (version_compare(WC_VERSION, '5.6', '<')) ? '' : $order->get_shipping_phone(),
			'shipping_address_1' => $order->get_shipping_address_1(),
			'shipping_address_2' => $order->get_shipping_address_2(),
			'shipping_postcode' => $order->get_shipping_postcode(),
			'shipping_city' => $order->get_shipping_city(),
			'shipping_state' => $order->get_shipping_state(),
			'shipping_country' => $order->get_shipping_country(),
			'customer_note' => $order->get_customer_note(),
			'wt_import_key' => $order->get_order_number(),
			'shipping_items' => self::format_data(implode(';', $shipping_items)),
			'fee_items' => implode('||', $fee_items),
			'tax_items' => implode(';', $tax_items),
			'coupon_items' => implode(';', $coupon_items),
			'refund_items' => implode(';', $refund_items),
			'order_notes' => implode('||', (defined('WC_VERSION') && (WC_VERSION >= 3.2)) ? self::get_order_notes_new($order) : self::get_order_notes($order)),
			'download_permissions' => $order->is_download_permitted() ? $order->is_download_permitted() : 0,
		);

		$order_export_data = array();
		foreach ($csv_columns as $key => $value) {
			if (!$order_data || array_key_exists($key, $order_data)) {
				$order_export_data[$key] = $order_data[$key];
			}
		}

		if ($found_meta) {
			foreach ($found_meta as $key => $value) {
				if($this->is_hpos_enabled){
					$order_export_data[$value] = self::format_data(maybe_serialize($order->get_meta($key)));
				}else{
					$order_export_data[$value] = self::format_data(maybe_serialize(get_post_meta($order_data['order_id'], $key, TRUE)));
				}
			}
		}

		if ($this->exclude_line_items) {
			return apply_filters('hf_alter_csv_order_data', $order_export_data, array('max_line_items' => 0));
		}

		$li = 1;
		foreach ($line_items as $line_item) {
			foreach ($line_item as $name => $value) {
				$line_item[$name] = $name . ':' . $value;
			}
			$line_item = implode(apply_filters('wt_change_item_separator', '|'), $line_item);
			$order_export_data["line_item_{$li}"] = $line_item;
			$li++;
		}

		$max_line_items = $this->line_items_max_count;
		for ($i = 1; $i <= $max_line_items; $i++) {
			$order_export_data["line_item_{$i}"] = !empty($order_export_data["line_item_{$i}"]) ? self::format_data($order_export_data["line_item_{$i}"]) : '';
		}

		if ($this->export_to_separate_columns) {

			for ($i = 1; $i <= $max_line_items; $i++) {

				$order_export_data["line_item_{$i}_name"] = !empty($line_items[$i - 1]['name']) ? $line_items[$i - 1]['name'] : '';
				$order_export_data["line_item_{$i}_product_id"] = !empty($line_items[$i - 1]['product_id']) ? $line_items[$i - 1]['product_id'] : '';
				$order_export_data["line_item_{$i}_sku"] = !empty($line_items[$i - 1]['sku']) ? $line_items[$i - 1]['sku'] : '';
				$order_export_data["line_item_{$i}_quantity"] = !empty($line_items[$i - 1]['quantity']) ? $line_items[$i - 1]['quantity'] : '';
				$order_export_data["line_item_{$i}_total"] = !empty($line_items[$i - 1]['total']) ? $line_items[$i - 1]['total'] : '';
				$order_export_data["line_item_{$i}_subtotal"] = !empty($line_items[$i - 1]['sub_total']) ? $line_items[$i - 1]['sub_total'] : '';
			}
		}

		$order_data_filter_args = array('max_line_items' => $max_line_items);

		if ($this->export_to_separate_rows) {
			$order_export_data = $this->wt_line_item_separate_row_csv_data($order, $order_export_data, $order_data_filter_args);
		}

		return apply_filters('hf_alter_csv_order_data', $order_export_data, $order_data_filter_args,$form_data);
	}

	public static function get_order_line_item_meta($item_id) {
		global $wpdb;
		$filtered_meta = apply_filters('wt_order_export_select_line_item_meta', array());
		$filtered_meta = !empty($filtered_meta) ? implode("','", $filtered_meta) : '';
		$query = "SELECT meta_key,meta_value
            FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = '$item_id'";
		if (!empty($filtered_meta)) {
			$query .= " AND meta_key IN ('" . $filtered_meta . "')";
		}
		$meta_keys = $wpdb->get_results($query, OBJECT_K);
		return $meta_keys;
	}

	public static function get_order_notes($order) {
		$callback = array('WC_Comments', 'exclude_order_comments');
		$args = array(
			'post_id' => $order->get_id(),
			'approve' => 'approve',
			'type' => 'order_note'
		);
		remove_filter('comments_clauses', $callback);
		$notes = get_comments($args);
		add_filter('comments_clauses', $callback);
		$notes = array_reverse($notes);
		$order_notes = array();
		foreach ($notes as $note) {
			$date = $note->comment_date;
			$customer_note = 0;
			if (get_comment_meta($note->comment_ID, 'is_customer_note', '1')) {
				$customer_note = 1;
			}
			$order_notes[] = implode(apply_filters('wt_change_item_separator', '|'), array(
				'content:' . str_replace(array("\r", "\n"), ' ', $note->comment_content),
				'date:' . (!empty($date) ? $date : current_time('mysql')),
				'customer:' . $customer_note,
				'added_by:' . $note->added_by
			));
		}
		return $order_notes;
	}

	public static function get_order_notes_new($order) {
		$notes = wc_get_order_notes(array('order_id' => $order->get_id(), 'order_by' => 'date_created', 'order' => 'ASC'));
		$order_notes = array();
		foreach ($notes as $note) {
			$order_notes[] = implode(apply_filters('wt_change_item_separator', '|'), array(
				'content:' . str_replace(array("\r", "\n"), ' ', $note->content),
				'date:' . $note->date_created->date('Y-m-d H:i:s'),
				'customer:' . $note->customer_note,
				'added_by:' . $note->added_by
			));
		}
		return $order_notes;
	}

	/**
	 * Format the data if required
	 * @param  string $meta_value
	 * @param  string $meta name of meta key
	 * @return string
	 */
	public static function format_export_meta($meta_value, $meta) {
		switch ($meta) {
			case '_sale_price_dates_from' :
			case '_sale_price_dates_to' :
				return $meta_value ? date('Y-m-d', $meta_value) : '';
				break;
			case '_upsell_ids' :
			case '_crosssell_ids' :
				return implode('|', array_filter((array) json_decode($meta_value)));
				break;
			default :
				return $meta_value;
				break;
		}
	}
	
	/**
	 * Format data for writing to the output file.
	 * 
	 * @param type $data
	 * @return type
	 */
	public static function format_data($data) {
		if (!is_array($data))
		$data = (string) urldecode($data);
       
		$use_mb = function_exists('mb_detect_encoding');
		$enc = '';
		if ($use_mb) {
			$enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);
		}
		$data = ( $enc == 'UTF-8' ) ? $data : utf8_encode($data);

		return $data;
	}

	/**
	 * Wrap a column in quotes for the CSV
	 * @param  string data to wrap
	 * @return string wrapped data
	 */
	public static function wrap_column($data) {
		return '"' . str_replace('"', '""', $data) . '"';
	}

	/**
	 * Get maximum number of line item for an order in the database.
	 * @return int Max line item number.
	 */
	public static function get_max_line_items() {
		global $wpdb;
		$query_line_items = "select COUNT(p.order_id) AS ttal from {$wpdb->prefix}woocommerce_order_items as p where order_item_type ='line_item' GROUP BY p.order_id ORDER BY ttal DESC LIMIT 1";
		$line_item_keys = $wpdb->get_col($query_line_items);
		$max_line_items = $line_item_keys[0];
		return $max_line_items;
	}
	
	
	/**
	 * Billing/Shipping Country/State pairs - convert in to SQL pairs.
	 * @param  array $pairs Country/State pairs.
	 * @return array Wrapped data.
	 */
	public static function country_state_pairs( $pairs ) {
		$valid_types = array( 'country', 'state' );
		$pair_types = array();
		$delimiters = array(
			'<>' => 'NOT IN',
			'=' => 'IN'
		);
		$sql_operators = array( 'NOT SET', 'IS SET' );

		foreach ( $pairs as $pair ) {
			$pair = trim( $pair );
			$op = '';
			$single_op = false;
			foreach ($delimiters as $delim => $op_seek) {
				$t = explode( $delim, $pair );
				$single_op = in_array($delim, $sql_operators);
				if (count($t) == 2) {
					$op = $op_seek;
					break;
				}
			}
			if (!$op) {
				continue;
			}
			if ($single_op) {
				$t[1] = '';
			}

			list( $filter_type, $filter_value ) = array_map("trim", $t);
			$empty = __('empty');
			if ($empty == $filter_value) {
				$filter_value = '';
			}

			$filter_type = strtolower($filter_type);

			if ($valid_types AND!in_array($filter_type, $valid_types)) {
				continue;
			}

			$filter_type = addslashes($filter_type);
			if (!isset($pair_types[$op])) {
				$pair_types[$op] = array();
			}
			if (!isset($pair_types[$op] [$filter_type])) {
				$pair_types[$op] [$filter_type] = array();
			}
			$pair_types[$op][$filter_type][] = addslashes($filter_value);
		}
		return $pair_types;
	}
	
	/**
	 * Array to single quoted array.
	 * @param  array $arr_values Values to be single quoted.
	 * @return string Wrapped data.
	 */
	public static function prepare_sql_inarray($arr_values) {
		$values = array();
		foreach ($arr_values as $s) {
			$values[] = "'$s'";
		}

		return implode(",", $values);
	}
}
