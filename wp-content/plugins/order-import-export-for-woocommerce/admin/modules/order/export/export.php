<?php

if (!defined('WPINC')) {
    exit;
}
use Automattic\WooCommerce\Utilities\OrderUtil;

if(!class_exists('Wt_Import_Export_For_Woo_Order_Basic_Order_Export')){
class Wt_Import_Export_For_Woo_Order_Basic_Order_Export {

    public $parent_module = null;
    public $table_name;
    public static $is_hpos_enabled;
    public $hpos_sync ;
    private $line_items_max_count = 0;
    private $export_to_separate_columns = false;
    private $export_to_separate_rows = false;     
    private $line_item_meta;
    private $is_wt_invoice_active = false;
    private $is_yith_tracking_active = false;
	private $shipment_tracking_active = false;
    private $is_wc_paypal_active = false;
    public $is_eh_stripe_active = false; 
    public $is_wc_stripe_active = false; 
	private $wpo_wcpdf = false;
    private $exclude_line_items = false;
    private $include_timezone_offset = false;

    /** Cached header/column list for the current batch (built once per batch). */
    private $cached_csv_columns = null;

    public function __construct($parent_object) {

        $this->parent_module = $parent_object;     
        $hpos_data = Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::is_hpos_enabled();
        $this->table_name = $hpos_data['table_name'];
        $this->hpos_sync = $hpos_data['sync'];
        if( strpos($hpos_data['table_name'],'wc_orders') !== false ){
            self::$is_hpos_enabled = true;
        }           
    }

    public function prepare_header() {

        $export_columns = $this->parent_module->get_selected_column_names();

        if (is_plugin_active('print-invoices-packing-slip-labels-for-woocommerce/print-invoices-packing-slip-labels-for-woocommerce.php')):
            $this->is_wt_invoice_active = true;
        endif;
        if (class_exists('Zorem_Woocommerce_Advanced_Shipment_Tracking') || class_exists('WC_Shipment_Tracking')):
            $this->shipment_tracking_active = true;
        endif;
        if (class_exists('WPO_WCPDF')):
            $this->wpo_wcpdf = true;
        endif; 		
		if (is_plugin_active('yith-woocommerce-order-tracking-premium/init.php')):
            $this->is_yith_tracking_active = true;
        endif;  
        if (is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php')):
            $this->is_wc_paypal_active = true;
        endif;
        if (is_plugin_active('eh-stripe-payment-gateway/stripe-payment-gateway.php')):
            $this->is_eh_stripe_active = true;
        endif;

        if (is_plugin_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php')):
            $this->is_wc_stripe_active = true;
        endif;
		
        if ($this->exclude_line_items) {
			return apply_filters('hf_alter_csv_header', $export_columns);
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
        return apply_filters('hf_alter_csv_header', $export_columns);
    }

    /**
     * Return header row for export response. Uses cached header when set by prepare_data_to_export().
     *
     * @return array Header/column list (same format as prepare_header()).
     */
    public function get_header_row() {
        if ( isset( $this->cached_csv_columns ) ) {
            return $this->cached_csv_columns;
        }
        return $this->prepare_header();
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

        $row = array();
        if ($order) {
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
           return $row;
        }
   
    }
        
    public function wt_ier_alter_order_data_before_export_for_separate_row($data_array) {
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

        global $wpdb;

        $export_order_statuses = !empty($form_data['filter_form_data']['wt_iew_order_status']) ? $form_data['filter_form_data']['wt_iew_order_status'] : 'any';
        $products = !empty($form_data['filter_form_data']['wt_iew_products']) ? $form_data['filter_form_data']['wt_iew_products'] : '';
        $email = !empty($form_data['filter_form_data']['wt_iew_email']) ? $form_data['filter_form_data']['wt_iew_email'] : array(); // user email fields return user ids
        $start_date = !empty($form_data['filter_form_data']['wt_iew_date_from']) ? $form_data['filter_form_data']['wt_iew_date_from'] . ' 00:00:00' : wp_date('Y-m-d 00:00:00', 0);
        $end_date = !empty($form_data['filter_form_data']['wt_iew_date_to']) ? $form_data['filter_form_data']['wt_iew_date_to'] . ' 23:59:59.99' : wp_date('Y-m-d 23:59:59.99', current_time('timestamp'));        
        
			$wt_iew_date_created_range = '';
			if ( ! empty( $start_date ) && ! empty( $end_date ) && is_string( $start_date ) && is_string( $end_date ) ) {
				$wt_iew_date_created_range = trim( $start_date ) . '...' . trim( $end_date );
			}
$coupons = !empty($form_data['filter_form_data']['wt_iew_coupons']) ? $form_data['filter_form_data']['wt_iew_coupons'] : array();
        $orders = !empty($form_data['filter_form_data']['wt_iew_orders']) ? array_filter(explode(',', strtolower($form_data['filter_form_data']['wt_iew_orders'])),'trim') : array();

        $export_limit = !empty($form_data['filter_form_data']['wt_iew_limit']) ? intval($form_data['filter_form_data']['wt_iew_limit']) : 999999999; //user limit
        $current_offset = !empty($form_data['filter_form_data']['wt_iew_offset']) ? intval($form_data['filter_form_data']['wt_iew_offset']) : 0; //user offset
        $export_offset = $current_offset;
        $batch_count = !empty($form_data['advanced_form_data']['wt_iew_batch_count']) ? $form_data['advanced_form_data']['wt_iew_batch_count'] : Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::get_advanced_settings('default_export_batch');

        $exclude_already_exported = (!empty($form_data['advanced_form_data']['wt_iew_exclude_already_exported']) && ( $form_data['advanced_form_data']['wt_iew_exclude_already_exported'] === 'Yes' || $form_data['advanced_form_data']['wt_iew_exclude_already_exported'] == 1 )) ? true : false;

        $this->export_to_separate_columns = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate']) && $form_data['advanced_form_data']['wt_iew_export_to_separate'] === 'column') ? true : false;                       
        $this->export_to_separate_rows = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate']) && $form_data['advanced_form_data']['wt_iew_export_to_separate'] === 'row') ? true : false;               
		$this->exclude_line_items = (!empty($form_data['advanced_form_data']['wt_iew_exclude_line_items']) && $form_data['advanced_form_data']['wt_iew_exclude_line_items'] == 'Yes') ? true : false;
		$this->include_timezone_offset = (bool) Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::get_advanced_settings( 'include_timezone_offset' );


        $real_offset = ($current_offset + $batch_offset);

        if($batch_count<=$export_limit)
        {
            if(($batch_offset+$batch_count)>$export_limit) //last offset
            {
                $limit=$export_limit-$batch_offset;
            }else
            {
                $limit=$batch_count;
            }
        }else
        {
            $limit=$export_limit;
        }

        $data_array = array();
        if ($batch_offset < $export_limit)
        {
            $filter_form_data = isset($form_data['filter_form_data']) && is_array( $form_data['filter_form_data']) ? $form_data['filter_form_data'] : array();
		    $advanced_form_data = isset($form_data['filter_form_data']) && is_array( $form_data['advanced_form_data']) ? $form_data['advanced_form_data'] : array();
		    $transient_key = 'wt_iew_orders_export_' . md5( json_encode( array_merge( $filter_form_data, $advanced_form_data)) );
            if($batch_offset === 0){
                set_transient( $transient_key, false, 60 );
                $this->line_items_max_count = $this->get_max_line_items();
                update_option('wt_order_line_items_max_count', $this->line_items_max_count);   
            }
            if(empty($this->line_items_max_count)){
                $this->line_items_max_count = get_option('wt_order_line_items_max_count', false);
                if($this->line_items_max_count === false) {
                    $this->line_items_max_count = $this->get_max_line_items();
                    update_option('wt_order_line_items_max_count', $this->line_items_max_count);
                }
            }
            $order_ids = get_transient( $transient_key );
            if ( $order_ids == false ) {
                $order_ids =0;
                $total_records=0;
                
                if ($exclude_already_exported) {
                    if(strpos($this->table_name, 'wc_order') !== false ){
                        $exclude_query = "SELECT ot.id FROM {$wpdb->prefix}wc_orders as ot LEFT JOIN {$wpdb->prefix}wc_orders_meta as omt ON ot.id = omt.order_id AND omt.meta_key = 'wf_order_exported_status' WHERE omt.order_id IS NULL";
                    }else{
                        $exclude_query = "SELECT ID FROM $wpdb->posts as pt LEFT JOIN $wpdb->postmeta as pmt ON pt.ID = pmt.post_id AND pmt.meta_key = 'wf_order_exported_status' WHERE pmt.post_id IS NULL";
                    }
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
                    $exclude_already_exported_orders = $wpdb->get_col($exclude_query); 
                    // phpcs:enable
                } 
                if (!empty($email) && empty($products) && empty($coupons)) {
                    $args = array(
                        'customer_id' => $email,
                        'paginate' => true,
                        'return' => 'ids',
                        'limit' => $export_limit, //user given limit,
                        'offset' => $current_offset, //user given offset,
                    );                                        
                    						if ( '' !== $wt_iew_date_created_range ) {
							$args['date_created'] = $wt_iew_date_created_range;
						}
$ord_email = wc_get_orders($args);
                    $order_ids = $ord_email->orders;
                } elseif (!empty($products) && empty($coupons) && empty($email)) {
                    $order_ids = self::hf_get_orders_of_products($products, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported); 
                } elseif (!empty($coupons) && empty($products) && empty($email)) {
                    $order_ids = self::hf_get_orders_of_coupons($coupons, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported);
                } elseif (!empty($coupons) && !empty($products) && empty($email)) {
                    $ord_prods = self::hf_get_orders_of_products($products, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported);
                    $ord_coups = self::hf_get_orders_of_coupons($coupons, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported);
                    $order_ids = array_intersect($ord_prods, $ord_coups);
                } elseif (!empty($coupons) && empty($products) && !empty($email)) {
                    $ord_coups = self::hf_get_orders_of_coupons($coupons, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported);
                    $args = array(
                        'customer_id' => $email,
                    						'return' => 'ids',
						);
                    						if ( '' !== $wt_iew_date_created_range ) {
							$args['date_created'] = $wt_iew_date_created_range;
						}
$ord_email = wc_get_orders($args);
                    foreach ($ord_email as $id) {
                        $order_id[] = $id;
                    }
                    $order_ids = array_intersect($order_id, $ord_coups);
                } elseif (empty($coupons) && !empty($products) && !empty($email)) {
                    $ord_prods = self::hf_get_orders_of_products($products, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported);
                    $args = array(
                        'customer_id' => $email,
                    						'return' => 'ids',
						);
                    						if ( '' !== $wt_iew_date_created_range ) {
							$args['date_created'] = $wt_iew_date_created_range;
						}
$ord_email = wc_get_orders($args);
                    foreach ($ord_email as $id) {
                        $order_id[] = $id;
                    }            
                    $order_ids = array_intersect($ord_prods, $order_id);
                } elseif (!empty($coupons) && !empty($products) && !empty($email)) {
                    $ord_prods = self::hf_get_orders_of_products($products, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported);
                    $ord_coups = self::hf_get_orders_of_coupons($coupons, $export_order_statuses, $export_limit, $current_offset, $end_date, $start_date, $exclude_already_exported);
                    $args = array(
                        'customer_id' => $email,
                       						'return' => 'ids',
						);
                    						if ( '' !== $wt_iew_date_created_range ) {
							$args['date_created'] = $wt_iew_date_created_range;
						}
$ord_email = wc_get_orders($args);
                    foreach ($ord_email as $id) {
                        $order_id[] = $id;
                    }
                    $order_ids = array_intersect($ord_prods, $ord_coups, $order_id);
                } else {
                    $query_args = array(
                        'return' => 'ids',
                        'type' => 'shop_order',
                        'order' => 'DESC',
                        'orderby' => 'ID',
                        'status' => $export_order_statuses,
);                   
                    			if ( '' !== $wt_iew_date_created_range ) {
				$query_args['date_created'] = $wt_iew_date_created_range;
			}
$query_args = apply_filters('wt_orderimpexpcsv_export_query_args', $query_args);
                    $query_args['offset'] = $current_offset; //user given offset
                    $query_args['limit'] = $export_limit; //user given limit
                    $query = new WC_Order_Query($query_args);                    
                    $order_ids = $query->get_orders();     
                }
                if(! empty($orders)){
                    $order_ids = array_intersect($order_ids, $orders);
                }
                if ($exclude_already_exported) {
                    $order_ids = array_intersect($order_ids,$exclude_already_exported_orders);
                }
               
                set_transient( $transient_key, $order_ids, 3600 ); // 1 hour; aligns with long multi-batch exports
            }   
            $order_ids = apply_filters('wt_ier_modify_order_ids', $order_ids);
            $total_records = count($order_ids);
            $order_ids = array_slice($order_ids, $batch_offset, $limit);

            // Build header once per batch; generate_row_data() will use this cache.
            $this->cached_csv_columns = $this->prepare_header();

            foreach ($order_ids as $order_id) {
                if(wc_get_order($order_id)){
                    $data_array[] = $this->generate_row_data($order_id);
                    // updating records with expoted status 
                    if(self::$is_hpos_enabled){
						if($this->hpos_sync){
							update_post_meta($order_id, 'wf_order_exported_status', TRUE);
						}
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Its necessary to use direct database query.
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = 'wf_order_exported_status' AND order_id = %d;", $order_id ) );
						$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}wc_orders_meta (order_id, meta_key, meta_value) VALUES (%d, %s, %s)", $order_id, 'wf_order_exported_status', TRUE ) ) ;
						// phpcs:enable
					}else{
						update_post_meta($order_id, 'wf_order_exported_status', TRUE);
					} 
                }
            }
            if($this->export_to_separate_rows){
                $data_array = $this->wt_ier_alter_order_data_before_export_for_separate_row($data_array);
            }
            $data_array = apply_filters('wt_ier_alter_order_data_before_export', $data_array);    
            $return['total'] = $total_records;
            $return['data'] = $data_array;
            if( 0 == $batch_offset && 0 == $total_records ){
				$return['no_post'] = esc_html__( 'Nothing to export under the selected criteria.', 'order-import-export-for-woocommerce' );
		    }
            return $return;
        } 
    }


    public function generate_row_data($order_id) {

        // Use header built once per batch in prepare_data_to_export(); fallback if called elsewhere.
        $csv_columns = isset( $this->cached_csv_columns ) ? $this->cached_csv_columns : $this->prepare_header();
        $version = WC()->version;
     
        $row = array();
        // Get an instance of the WC_Order object
        $order = wc_get_order($order_id);
        $line_items = $shipping_items = $fee_items = $tax_items = $coupon_items = $refund_items = array();

        // Collect line item IDs and load their meta in one query.
        $line_item_ids = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $line_item_ids[] = $item_id;
        }
        $item_meta_map = self::get_order_line_item_meta_batch( $line_item_ids );

        // get line items
        foreach ($order->get_items() as $item_id => $item) {
            /* WC_Abstract_Legacy_Order::get_product_from_item() deprecated since version 4.4.0*/
            $product = ( version_compare( $version, '4.4.0', '<' ) ) ? $order->get_product_from_item($item) : $item->get_product();  
            if (!is_object($product)) {
                $product = new WC_Product(0);
            }
            $item_meta = isset( $item_meta_map[ $item_id ] ) ? $item_meta_map[ $item_id ] : array();
            $prod_type = ( version_compare( $version, '3.0.0', '<' ) ) ? $product->product_type : $product->get_type();
            $line_item = array(
                'name' => html_entity_decode(!empty($item['name']) ? $item['name'] : $product->get_title(), ENT_NOQUOTES, 'UTF-8'),
                'product_id' => ( version_compare( $version, '2.7.0', '<' ) ) ? $product->id : (($prod_type == 'variable' || $prod_type == 'variation' || $prod_type == 'subscription_variation') ? $product->get_parent_id() : $product->get_id()),
                'sku' => $product->get_sku(),
                'quantity' => $item['qty'],
                'total' => wc_format_decimal($order->get_line_total($item), 2),
                'sub_total' => wc_format_decimal($order->get_line_subtotal($item), 2),
            );

            //add line item tax
            $line_tax_data = isset($item['line_tax_data']) ? $item['line_tax_data'] : array();
            $tax_data = maybe_unserialize($line_tax_data);
            $tax_detail = isset($tax_data['total']) ? wc_format_decimal(wc_round_tax_total(array_sum((array) $tax_data['total'])), 2) : '';
            if ($tax_detail != '0.00' && !empty($tax_detail)) {
                $line_item['tax'] = $tax_detail;
                $line_tax_ser = json_encode($line_tax_data);
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
                $line_item['_variation_id'] = ( version_compare( $version, '2.7', '>' ) ) ? $product->get_id() : $product->variation_id;
            }
            $line_items[] = $line_item;
        }
        

		// Get shipping line items from the order
		$line_items_shipping = $order->get_items('shipping');

		foreach ($line_items_shipping as $item_id => $item) {
			$meta = array();

			// Manually build the "Items" field by listing all order products with quantity
			$items_array = array();
			foreach ($order->get_items() as $line_item) {
				$product = $line_item->get_product();
				$product_name = $product ? $product->get_name() : $line_item->get_name(); // Fallback to item name if product object is unavailable
				$qty = $line_item->get_quantity();
				$items_array[] = $product_name . ' &times; ' . $qty; 
			}

			// Concatenate all item strings into a single string for the Items field
			$meta['Items'] = implode(', ', $items_array);

			// Assign shipping method ID without wrapping in quotes
			$meta['method_id'] = isset($item['method_id']) ? $item['method_id'] : '';

            /**
             * Retrieve taxes from meta and serialize (match original format).
             * 
             *  2.6.4 - take tax data using woocommerce default function.
             */
			$taxes = $item->get_taxes();
            
			if (is_array($taxes) || is_object($taxes)) {
				// Serialize arrays/objects into PHP serialized format
				$meta['taxes'] = serialize($taxes);
			} else {
				// Use raw value if already a string
				$meta['taxes'] = is_string($taxes) ? $taxes : '';
			}

			// Ensure all expected keys exist in the meta array
			foreach (array('Items', 'method_id', 'taxes') as $value) {
				if (!isset($meta[$value])) {
					$meta[$value] = '';
				}
			}

			// Concatenate the shipping meta into the expected export format
			$shipping_items[] = trim(implode('|', array(
				'items:' . $meta['Items'],
				'method_id:' . $meta['method_id'],
				'taxes:' . $meta['taxes']
			)));
		}

        
        //get fee and total
        $fee_total = 0;
        $fee_tax_total = 0;

        foreach ($order->get_fees() as $fee_id => $fee) {
            $fee_items[] = implode('|', array(
                'name:' . html_entity_decode($fee['name'], ENT_NOQUOTES, 'UTF-8'),
                'total:' . wc_format_decimal($fee['line_total'], 2),
                'tax:' . wc_format_decimal($fee['line_tax'], 2),
                'tax_data:' . json_encode($fee['line_tax_data'])
            ));
            $fee_total += (float) $fee['line_total'];
            $fee_tax_total += (float) $fee['line_tax'];
        }

        // get tax items
        foreach ($order->get_tax_totals() as $tax_code => $tax) {
            $rate_percent = wc_get_order_item_meta( $tax->id, 'rate_percent', true ) ? wc_get_order_item_meta( $tax->id, 'rate_percent', true ):'';
            $tax_items[] = implode('|', array(
                'rate_id:' . $tax->rate_id,
                'code:' . $tax_code,
                'total:' . wc_format_decimal($tax->amount, 2),
                'label:' . $tax->label,
                'tax_rate_compound:' . $tax->is_compound,
                'rate_percent:'.$rate_percent,
            ));
        }

		// Add coupons.
		foreach ($order->get_items('coupon') as $_ => $coupon_item) {
			$discount_amount = ( version_compare( $version, '4.4.0', '<' ) ) ? $coupon_item['discount_amount'] : $coupon_item->get_discount();
			$discount_amount = !empty($discount_amount) ? $discount_amount : 0;
			$coupon_code = ( version_compare( $version, '4.4.0', '<' ) ) ? $coupon_item['name'] : $coupon_item->get_code();
			$coupon_items[] = implode('|', array(
				'code:' . $coupon_code,
				'amount:' . wc_format_decimal($discount_amount, 2),
			));
		}

        foreach ($order->get_refunds() as $refunded_items) {

            if ( version_compare( $version, '2.7.0', '<' ) ) {
                $refund_items[] = implode('|', array(
                    'amount:' . $refunded_items->get_refund_amount(),
                    'reason:' . $refunded_items->reason,
                    'date:' . wp_date('Y-m-d H:i:s', strtotime($refunded_items->date_created)),
                ));
            } else {
                $refund_items[] = implode('|', array(
                    'amount:' . $refunded_items->get_amount(),
                    'reason:' . $refunded_items->get_reason(),
                    'date:' . Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::format_date_for_export( $refunded_items->get_date_created(), $this->include_timezone_offset ),
                ));
            }
        }

        if (version_compare(WC_VERSION, '2.7', '<')) {
            
            $paid_date = get_post_meta($order->id, '_date_paid');
            $order_data = array(
                'order_id' => $order->id,
                'order_number' => $order->get_order_number(),
                'order_date' => wp_date('Y-m-d H:i:s', strtotime(get_post($order->id)->post_date)),
                'paid_date' => isset($paid_date) ? wp_date('Y-m-d H:i:s', $paid_date) : '',
                'status' => $order->get_status(),
                'shipping_total' => $order->get_total_shipping(),
                'shipping_tax_total' => wc_format_decimal($order->get_shipping_tax(), 2),
                'fee_total' => wc_format_decimal($fee_total, 2),
                'fee_tax_total' => wc_format_decimal($fee_tax_total, 2),
                'tax_total' => wc_format_decimal($order->get_total_tax(), 2),
                'cart_discount' => (defined('WC_VERSION') && version_compare(WC_VERSION, '2.3', '>=')) ? wc_format_decimal($order->get_total_discount(), 2) : (method_exists($order, 'get_cart_discount') ? wc_format_decimal($order->get_cart_discount(), 2) : '0.00'),
                'order_discount' => (defined('WC_VERSION') && version_compare(WC_VERSION, '2.3', '>=')) ? wc_format_decimal($order->get_total_discount(), 2) : (method_exists($order, 'get_order_discount') ? wc_format_decimal($order->get_order_discount(), 2) : '0.00'),
                'discount_total' => wc_format_decimal($order->get_discount_total(), 2),
                'order_total' => wc_format_decimal($order->get_total(), 2),
                'order_subtotal' => wc_format_decimal($order->get_subtotal(), 2), // Get order subtotal
				'order_key' => $order->order_key,
                'order_currency' => $order->get_order_currency(),
                'payment_method' => $order->payment_method,
                'payment_method_title' => $order->payment_method_title,
                'transaction_id' => $order->transaction_id,
                'customer_ip_address' => $order->customer_ip_address,
                'customer_user_agent' => $order->customer_user_agent, 
                'shipping_method' => $order->get_shipping_method(),
                'customer_id' => $order->get_user_id(),
                'customer_user' => $order->get_user_id(),
                'customer_email' => ($a = get_userdata($order->get_user_id())) ? $a->user_email : '',
                'billing_first_name' => $order->billing_first_name,
                'billing_last_name' => $order->billing_last_name,
                'billing_company' => $order->billing_company,
                'billing_email' => $order->billing_email,
                'billing_phone' => $order->billing_phone,
                'billing_address_1' => $order->billing_address_1,
                'billing_address_2' => $order->billing_address_2,
                'billing_postcode' => $order->billing_postcode,
                'billing_city' => $order->billing_city,
                'billing_state' => $order->billing_state,
                'billing_country' => $order->billing_country,
                'shipping_first_name' => $order->shipping_first_name,
                'shipping_last_name' => $order->shipping_last_name,
                'shipping_company' => $order->shipping_company,
                'shipping_phone' => isset($order->shipping_phone) ? $order->shipping_phone : '',                
                'shipping_address_1' => $order->shipping_address_1,
                'shipping_address_2' => $order->shipping_address_2,
                'shipping_postcode' => $order->shipping_postcode,
                'shipping_city' => $order->shipping_city,
                'shipping_state' => $order->shipping_state,
                'shipping_country' => $order->shipping_country,
                'customer_note' => $order->customer_note,
                'wt_import_key' => $order->get_order_number(),
                'shipping_items' => self::format_data(implode(';', $shipping_items)),
                'fee_items' => implode('||', $fee_items),
                'tax_items' => implode(';', $tax_items),
                'coupon_items' => implode(';', $coupon_items),
                'refund_items' => implode(';', $refund_items),
                'order_notes' => implode('||', self::get_order_notes($order)),
                'download_permissions' => $order->download_permissions_granted ? $order->download_permissions_granted : 0,
            );
        } else {
            $paid_date = $order->get_date_paid();
            $order_date = Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::format_date_for_export( $order->get_date_created(), $this->include_timezone_offset );
            $order_data = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'order_date' => $order_date,
                'paid_date' => Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::format_date_for_export( $paid_date, $this->include_timezone_offset ),
                'status' => $order->get_status(),
                'shipping_total' => $order->get_total_shipping(),
                'shipping_tax_total' => wc_format_decimal($order->get_shipping_tax(), 2),
                'fee_total' => wc_format_decimal($fee_total, 2),
                'fee_tax_total' => wc_format_decimal($fee_tax_total, 2),
                'tax_total' => wc_format_decimal($order->get_total_tax(), 2),
                'cart_discount' => (defined('WC_VERSION') && version_compare(WC_VERSION, '2.3', '>=')) ? wc_format_decimal($order->get_total_discount(), 2) : (method_exists($order, 'get_cart_discount') ? wc_format_decimal($order->get_cart_discount(), 2) : '0.00'),
                'order_discount' => (defined('WC_VERSION') && version_compare(WC_VERSION, '2.3', '>=')) ? wc_format_decimal($order->get_total_discount(), 2) : (method_exists($order, 'get_order_discount') ? wc_format_decimal($order->get_order_discount(), 2) : '0.00'),
                'discount_total' => wc_format_decimal($order->get_total_discount(), 2),
                'order_total' => wc_format_decimal($order->get_total(), 2),
                'order_subtotal' => wc_format_decimal($order->get_subtotal(), 2), // Get order subtotal
				'order_key' => $order->get_order_key(),
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
                'shipping_phone' =>  (version_compare(WC_VERSION, '5.6', '<')) ? '' : $order->get_shipping_phone(), 
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
                'order_notes' => implode('||', (defined('WC_VERSION') && version_compare(WC_VERSION, '3.2', '>=')) ? self::get_order_notes_new($order, $this->include_timezone_offset) : self::get_order_notes($order)),
                'download_permissions' => $order->is_download_permitted() ? $order->is_download_permitted() : 0,                
            );
            

        }
        if( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.5', '>=' ) ){
            $wc_order_attribution_device_type = $order->get_meta('_wc_order_attribution_device_type');
            $wc_order_attribution_referrer = $order->get_meta('_wc_order_attribution_referrer');
            $wc_order_attribution_session_count = $order->get_meta('_wc_order_attribution_session_count');
            $wc_order_attribution_session_entry = $order->get_meta('_wc_order_attribution_session_entry');
            $wc_order_attribution_session_pages = $order->get_meta('_wc_order_attribution_session_pages');
            $wc_order_attribution_session_start_time = $order->get_meta('_wc_order_attribution_session_start_time');
            $wc_order_attribution_source_type = $order->get_meta('_wc_order_attribution_source_type');
            $wc_order_attribution_user_agent = $order->get_meta('_wc_order_attribution_user_agent');
            $wc_order_attribution_utm_source = $order->get_meta('_wc_order_attribution_utm_source');
            $order_data['meta:_wc_order_attribution_device_type'] = isset($wc_order_attribution_device_type) ? $wc_order_attribution_device_type : '';
            $order_data['meta:_wc_order_attribution_referrer'] = isset($wc_order_attribution_referrer) ? $wc_order_attribution_referrer : '';
            $order_data['meta:_wc_order_attribution_session_count'] = isset($wc_order_attribution_session_count) ? $wc_order_attribution_session_count : '';
            $order_data['meta:_wc_order_attribution_session_entry'] = isset($wc_order_attribution_session_entry) ? $wc_order_attribution_session_entry : '';
            $order_data['meta:_wc_order_attribution_session_pages'] = isset($wc_order_attribution_session_pages) ? $wc_order_attribution_session_pages : '';
            $order_data['meta:_wc_order_attribution_session_start_time'] = isset($wc_order_attribution_session_start_time) ? $wc_order_attribution_session_start_time : '';
            $order_data['meta:_wc_order_attribution_source_type'] = isset($wc_order_attribution_source_type) ? $wc_order_attribution_source_type : '';
            $order_data['meta:_wc_order_attribution_user_agent'] = isset($wc_order_attribution_user_agent) ? $wc_order_attribution_user_agent : '';
            $order_data['meta:_wc_order_attribution_utm_source'] = isset($wc_order_attribution_utm_source) ? $wc_order_attribution_utm_source : '';
        }
        if ($this->is_wt_invoice_active):
            $invoice_date = $order->get_meta('_wf_invoice_date');
            $invoice_number = $order->get_meta('wf_invoice_number');
            $order_data['meta:wf_invoice_number'] = empty($invoice_number) ? '' : $invoice_number;
            $order_data['meta:_wf_invoice_date'] = empty($invoice_date) ? '' : date_i18n(get_option( 'date_format' ), $invoice_date);
        endif;
        if ($this->is_yith_tracking_active):

            $ywot_tracking_code = $order->get_meta('ywot_tracking_code');
            $ywot_tracking_postcode = $order->get_meta('ywot_tracking_postcode');
            $ywot_carrier_id = $order->get_meta('ywot_carrier_id');
            $ywot_pick_up_date = $order->get_meta('ywot_pick_up_date');
            $ywot_picked_up = $order->get_meta('ywot_picked_up');
            $order_data['meta:ywot_tracking_code'] = empty($ywot_tracking_code) ? '' : $ywot_tracking_code;
            $order_data['meta:ywot_tracking_postcode'] = empty($ywot_tracking_postcode) ? '' : $ywot_tracking_postcode;
            $order_data['meta:ywot_carrier_id'] = empty($ywot_carrier_id) ? '' : $ywot_carrier_id;
            $order_data['meta:ywot_pick_up_date'] = empty($ywot_pick_up_date) ? '' : $ywot_pick_up_date;
            $order_data['meta:ywot_picked_up'] = empty($ywot_picked_up) ? '' : $ywot_picked_up;            
        endif; 
		if ($this->shipment_tracking_active):

            $advanced_shipment_tracking = $order->get_meta('_wc_shipment_tracking_items');
            $order_data['meta:_wc_shipment_tracking_items'] = empty($advanced_shipment_tracking) ? '' : json_encode($advanced_shipment_tracking);
        endif;
		if ($this->wpo_wcpdf):
            $_wcpdf_invoice_number =  $order->get_meta('_wcpdf_invoice_number');
            $_wcpdf_invoice_date = $order->get_meta('_wcpdf_invoice_date');
            $_wcpdf_invoice_number_data = $order->get_meta('_wcpdf_invoice_number_data');
            $_wcpdf_invoice_date_formatted = $order->get_meta('_wcpdf_invoice_date_formatted');
            $_wcpdf_invoice_settings = $order->get_meta('_wcpdf_invoice_settings');     
            $order_data['meta:_wcpdf_invoice_number'] = empty($_wcpdf_invoice_number) ? '' : $_wcpdf_invoice_number;
            $order_data['meta:_wcpdf_invoice_date'] = empty($_wcpdf_invoice_date) ? '' : $_wcpdf_invoice_date;
            $order_data['meta:_wcpdf_invoice_number_data'] = empty($_wcpdf_invoice_number_data) ? '' : json_encode($_wcpdf_invoice_number_data);
			$order_data['meta:_wcpdf_invoice_date_formatted'] = empty($_wcpdf_invoice_date_formatted) ? '' : $_wcpdf_invoice_date_formatted;
			$order_data['meta:_wcpdf_invoice_settings'] = empty($_wcpdf_invoice_settings) ? '' : json_encode($_wcpdf_invoice_settings);
        endif;
        if ($this->is_wc_paypal_active):

            $ppcp_paypal_fees =  $order->get_meta('_ppcp_paypal_fees');
            $order_data['meta:_ppcp_paypal_fees'] = empty($ppcp_paypal_fees) ? '' : json_encode($ppcp_paypal_fees);
         endif; 

         if ($this->is_eh_stripe_active):

            $stripe_fee =  $order->get_meta('eh_stripe_fee');
            $order_data['meta:eh_stripe_fees'] = empty($stripe_fee) ? '' : json_encode($stripe_fee);
         endif; 

         if ($this->is_wc_stripe_active):

            $stripe_fee =  $order->get_meta('_stripe_fee');
            $stripe_currency =  $order->get_meta('_stripe_currency');
            $stripe_net =  $order->get_meta('_stripe_net');
            $order_data['meta:_stripe_fee'] = empty($stripe_fee) ? '' : json_encode($stripe_fee);
            $order_data['meta:_stripe_currency'] = empty($stripe_currency) ? '' : json_encode($stripe_currency);
            $order_data['meta:_stripe_net'] = empty($stripe_net) ? '' : json_encode($stripe_net);
         endif; 
         
        $order_export_data = array();
        foreach ($csv_columns as $key => $value) {
      
            if (!$order_data || array_key_exists($key, $order_data)) {
                $order_export_data[$key] = $order_data[$key];
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
                $order_export_data["line_item_{$i}_name"] = !empty($line_items[$i-1]['name']) ? $line_items[$i-1]['name'] : '';
                $order_export_data["line_item_{$i}_product_id"] = !empty($line_items[$i-1]['product_id']) ? $line_items[$i-1]['product_id'] : '';
                $order_export_data["line_item_{$i}_sku"] = !empty($line_items[$i-1]['sku']) ? $line_items[$i-1]['sku'] : '';
                $order_export_data["line_item_{$i}_quantity"] = !empty($line_items[$i-1]['quantity']) ? $line_items[$i-1]['quantity'] : '';
                $order_export_data["line_item_{$i}_total"] = !empty($line_items[$i-1]['total']) ? $line_items[$i-1]['total'] : '';
                $order_export_data["line_item_{$i}_subtotal"] = !empty($line_items[$i-1]['sub_total']) ? $line_items[$i-1]['sub_total'] : '';
            }
        }
        $order_data_filter_args = array('max_line_items' => $max_line_items);
        
        if ($this->export_to_separate_rows) {
            $order_export_data = $this->wt_line_item_separate_row_csv_data($order, $order_export_data, $order_data_filter_args);
        } 
        return apply_filters('hf_alter_csv_order_data', $order_export_data, $order_data_filter_args);
    }

    public static function hf_get_orders_of_products($products, $export_order_statuses, $export_limit, $export_offset, $end_date, $start_date, $exclude_already_exported, $retun_count = false) {
        global $wpdb;
        $placeholder_values = array();
        if(self::$is_hpos_enabled){
            $order_table = $wpdb->prefix.'wc_orders';
            $order_meta_table = $wpdb->prefix.'wc_orders_meta';

            $query = '';
            $query .= "SELECT DISTINCT po.ID FROM {$order_table} AS po
                LEFT JOIN  {$order_meta_table} AS pm ON pm.id = po.ID
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_id = po.ID
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS om ON om.order_item_id = oi.order_item_id
                WHERE po.type = 'shop_order'
					AND po.status NOT IN ( 'trash' )
                AND oi.order_item_type = 'line_item'
                AND om.meta_key IN ('_product_id','_variation_id')
                AND om.meta_value IN (" . implode( ', ', array_fill( 0, count( $products ), '%d' ) ) . ")
                AND (po.date_created_gmt BETWEEN %s AND %s)";
            $placeholder_values = $products;
            $placeholder_values[] = $start_date;
            $placeholder_values[] = $end_date;
            if ($export_order_statuses != 'any') {
                $query .= " AND po.status IN ( " . implode( ', ', array_fill( 0, count( $export_order_statuses ), '%s' ) ) . " )";
                $placeholder_values = array_merge( $placeholder_values, $export_order_statuses );
            }

            if ($exclude_already_exported) {
                $query .= " AND pm.meta_key = 'wf_order_exported_status' AND pm.meta_value=1";
            }

            if ($retun_count == FALSE) {
                $query .= ' LIMIT %d' . ( ! empty( $export_offset ) ? ' OFFSET %d' : '' );
                $placeholder_values[] = intval( $export_limit );
                if ( ! empty( $export_offset ) ) {
                    $placeholder_values[] = intval( $export_offset );
                }
            }

        }else{
            $query = '';
            $query .= "SELECT DISTINCT po.ID FROM {$wpdb->posts} AS po
                LEFT JOIN  {$wpdb->postmeta} AS pm ON pm.post_id = po.ID
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_id = po.ID
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS om ON om.order_item_id = oi.order_item_id
                WHERE po.post_type = 'shop_order'
					AND po.post_status NOT IN ( 'trash' )
                AND oi.order_item_type = 'line_item'
                AND om.meta_key IN ('_product_id','_variation_id')
                AND om.meta_value IN (" . implode( ', ', array_fill( 0, count( $products ), '%d' ) ) . ")
                AND (po.post_date BETWEEN %s AND %s)";
            $placeholder_values = $products;
            $placeholder_values[] = $start_date;
            $placeholder_values[] = $end_date;
            if ($export_order_statuses != 'any') {
                $query .= " AND po.post_status IN ( " . implode( ', ', array_fill( 0, count( $export_order_statuses ), '%s' ) ) . " )";
                $placeholder_values = array_merge( $placeholder_values, $export_order_statuses );
            }

            if ($exclude_already_exported) {
                $query .= " AND pm.meta_key = 'wf_order_exported_status' AND pm.meta_value=1";
            }

            if ($retun_count == FALSE) {
                $query .= ' LIMIT %d' . ( ! empty( $export_offset ) ? ' OFFSET %d' : '' );
                $placeholder_values[] = intval( $export_limit );
                if ( ! empty( $export_offset ) ) {
                    $placeholder_values[] = intval( $export_offset );
                }
            }
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
        $order_ids = $wpdb->get_col($wpdb->prepare($query, $placeholder_values));
        // phpcs:enable

        if ($retun_count == TRUE) {
            return count($order_ids);
        }
        return $order_ids;
    }

    public static function hf_get_orders_of_coupons($coupons, $export_order_statuses, $export_limit, $export_offset, $end_date, $start_date, $exclude_already_exported, $retun_count = false) {
        global $wpdb;
        $placeholder_values = array();
        if(self::$is_hpos_enabled){
            $order_table = $wpdb->prefix.'wc_orders';
            $order_meta_table = $wpdb->prefix.'wc_orders_meta';

            $query = "SELECT DISTINCT po.ID FROM $order_table AS po
            LEFT JOIN $order_meta_table AS pm ON pm.id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS om ON om.order_item_id = oi.order_item_id
            WHERE po.type = 'shop_order'
					AND po.status NOT IN ( 'trash' )
            AND oi.order_item_type = 'coupon'
            AND oi.order_item_name IN (" . implode( ', ', array_fill( 0, count( $coupons ), '%s' ) ) . ")
            AND (po.date_created_gmt BETWEEN %s AND %s)";
            $placeholder_values = $coupons;
            $placeholder_values[] = $start_date;
            $placeholder_values[] = $end_date;
        if ($export_order_statuses != 'any') {
            $query .= " AND po.status IN ( " . implode( ', ', array_fill( 0, count( $export_order_statuses ), '%s' ) ) . " )";
            $placeholder_values = array_merge( $placeholder_values, $export_order_statuses );
        }
        if ($export_order_statuses == 'any') {
            $defualt_exclude_status = get_post_stati(array('exclude_from_search' => true));
            $stati = array_values(get_post_stati());
            foreach ($stati as $key => $status) {
                if (in_array($status, $defualt_exclude_status, true)) {
                    unset($stati[$key]);
                }
            }
            $query .= " AND po.status IN ( " . implode( ', ', array_fill( 0, count( $stati ), '%s' ) ) . " )";
            $placeholder_values = array_merge( $placeholder_values, $stati );
        }
        if ($exclude_already_exported) {
            $query .= " AND pm.meta_key = 'wf_order_exported_status' AND pm.meta_value=1";
        }
        if ($retun_count == FALSE) {
            $query .= ' LIMIT %d' . ( ! empty( $export_offset ) ? ' OFFSET %d' : '' );
            $placeholder_values[] = intval( $export_limit );
            if ( ! empty( $export_offset ) ) {
                $placeholder_values[] = intval( $export_offset );
            }
        }
        }else{
            $query = "SELECT DISTINCT po.ID FROM {$wpdb->posts} AS po
            LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS om ON om.order_item_id = oi.order_item_id
            WHERE po.post_type = 'shop_order'
					AND po.post_status NOT IN ( 'trash' )
            AND oi.order_item_type = 'coupon'
            AND oi.order_item_name IN (" . implode( ', ', array_fill( 0, count( $coupons ), '%s' ) ) . ")
            AND (po.post_date BETWEEN %s AND %s)";
            $placeholder_values = $coupons;
            $placeholder_values[] = $start_date;
            $placeholder_values[] = $end_date;
            if ($export_order_statuses != 'any') {
                $query .= " AND po.post_status IN ( " . implode( ', ', array_fill( 0, count( $export_order_statuses ), '%s' ) ) . " )";
                $placeholder_values = array_merge( $placeholder_values, $export_order_statuses );
            }
            if ($export_order_statuses == 'any') {
                $defualt_exclude_status = get_post_stati(array('exclude_from_search' => true));
                $stati = array_values(get_post_stati());
                foreach ($stati as $key => $status) {
                    if (in_array($status, $defualt_exclude_status, true)) {
                        unset($stati[$key]);
                    }
                }
                $query .= " AND po.post_status IN ( " . implode( ', ', array_fill( 0, count( $stati ), '%s' ) ) . " )";
                $placeholder_values = array_merge( $placeholder_values, $stati );
            }
            if ($exclude_already_exported) {
                $query .= " AND pm.meta_key = 'wf_order_exported_status' AND pm.meta_value=1";
            }
            if ($retun_count == FALSE) {
                $query .= ' LIMIT %d' . ( ! empty( $export_offset ) ? ' OFFSET %d' : '' );
                $placeholder_values[] = intval( $export_limit );
                if ( ! empty( $export_offset ) ) {
                    $placeholder_values[] = intval( $export_offset );
                }
            }
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
        $order_ids = $wpdb->get_col($wpdb->prepare($query, $placeholder_values));
        // phpcs:enable

        if ($retun_count == TRUE) {
            return count($order_ids);
        }
        return $order_ids;
    }

    public static function get_all_line_item_metakeys() {
        global $wpdb;
        $filter_meta = apply_filters('wt_order_export_select_line_item_meta', array());
        
        $query = "SELECT DISTINCT om.meta_key
            FROM {$wpdb->prefix}woocommerce_order_itemmeta AS om 
            INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON om.order_item_id = oi.order_item_id
            WHERE oi.order_item_type = %s";
        $placeholder_values = array('line_item');
        if ( ! empty( $filter_meta ) ) {
            $query .= " AND om.meta_key IN (" . implode( ', ', array_fill( 0, count( $filter_meta ), '%s' ) ) . ")";
            $placeholder_values = array_merge( $placeholder_values, $filter_meta );
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
        $meta_keys = $wpdb->get_col($wpdb->prepare($query, $placeholder_values));
        // phpcs:enable
        return $meta_keys;
    }

    /**
     * Load meta for multiple order line items in one query.
     * Returns map: order_item_id => array( meta_key => object( meta_value ) ) to match get_order_line_item_meta() structure.
     *
     * @param int[] $item_ids Order item IDs.
     * @return array Map of item_id => meta array (OBJECT_K style per item).
     */
    public static function get_order_line_item_meta_batch( $item_ids ) {
        global $wpdb;
        $item_ids = array_filter( array_unique( array_map( 'absint', (array) $item_ids ) ) );
        if ( empty( $item_ids ) ) {
            return array();
        }
        $filtered_meta = apply_filters( 'wt_order_export_select_line_item_meta', array() );
        $placeholder_values = array();
        $id_placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
        $query = "SELECT order_item_id, meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id IN ($id_placeholders)";
        $placeholder_values = $item_ids;
        if ( ! empty( $filtered_meta ) ) {
            $query .= ' AND meta_key IN (' . implode( ', ', array_fill( 0, count( $filtered_meta ), '%s' ) ) . ')';
            $placeholder_values = array_merge( $placeholder_values, $filtered_meta );
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Params sanitized (absint); filter supplies keys.
        $results = $wpdb->get_results( $wpdb->prepare( $query, $placeholder_values ) );
        // phpcs:enable
        $map = array_fill_keys( $item_ids, array() );
        foreach ( $results as $row ) {
            $map[ (int) $row->order_item_id ][ $row->meta_key ] = (object) array( 'meta_value' => $row->meta_value );
        }
        return $map;
    }

    public static function get_order_line_item_meta($item_id) {
        global $wpdb;
        $filtered_meta = apply_filters('wt_order_export_select_line_item_meta', array());
        $placeholder_values = array();
        $query = "SELECT meta_key,meta_value
            FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d";
        $placeholder_values[] = $item_id;
        if (!empty($filtered_meta)) {
            $query .= " AND meta_key IN (" . implode( ', ', array_fill( 0, count( $filtered_meta ), '%s' ) ) . ")";
            $placeholder_values = array_merge( $placeholder_values, $filtered_meta );
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
        $meta_keys = $wpdb->get_results($wpdb->prepare($query, $placeholder_values), OBJECT_K);
        // phpcs:enable
        return $meta_keys;
    }

    public static function get_order_notes($order) {
        $callback = array('WC_Comments', 'exclude_order_comments');
        $args = array(
            'post_id' => ( version_compare( WC()->version, '2.7.0', '<' ) ) ? $order->id : $order->get_id(),
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
            $order_notes[] = implode('|', array(
                'content:' . str_replace(array("\r", "\n"), ' ', $note->comment_content),
                'date:' . (!empty($date) ? $date : current_time('mysql')),
                'customer:' . $customer_note,
                'added_by:' . $note->added_by
            ));
        }
        return $order_notes;
    }

    public static function get_order_notes_new( $order, $include_offset = false ) {
        $notes = wc_get_order_notes(array('order_id' => $order->get_id(), 'order_by' => 'date_created', 'order' => 'ASC'));
        $order_notes = array();
        foreach ($notes as $note) {
            $order_notes[] = implode('|', array(
                'content:' . str_replace(array("\r", "\n"), ' ', $note->content),
                'date:' . Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::format_date_for_export( $note->date_created, $include_offset ),
                'customer:' . $note->customer_note,
                'added_by:' . $note->added_by
            ));
        }
        return $order_notes;
    }

    public static function get_all_metakeys_and_values($order = null) {
        $in = 1;
        $line_item_values = array();
        foreach ($order->get_items() as $item_id => $item) {
            //$item_meta = function_exists('wc_get_order_item_meta') ? wc_get_order_item_meta($item_id, '', false) : $order->get_item_meta($item_id);
            $item_meta = self::get_order_line_item_meta($item_id);
            foreach ($item_meta as $key => $value) {
                switch ($key) {
                    case '_qty':
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
                        $line_item_value[$key] = $value;
                        break;
                }
            }
            $line_item_values[$in] = !empty($line_item_value) ? $line_item_value : '';
            $in++;
        }
        return $line_item_values;
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
                return $meta_value ? wp_date('Y-m-d', $meta_value) : '';
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

    public static function format_data($data) {
		if (!is_array($data))
			;
		$data = (string) urldecode($data);

		if (function_exists('mb_convert_encoding') &&  function_exists('mb_convert_encoding')) {
			$encoding = mb_detect_encoding( $data, mb_detect_order(), true );
			if ( $encoding ) {
				return mb_convert_encoding( $data, 'UTF-8', $encoding );
			} else {
				return mb_convert_encoding( $data, 'UTF-8', 'UTF-8' );
			}
		}else{
			$newcharstring = '';
			$bom = apply_filters('wt_import_csv_parser_keep_bom', true);
			if ($bom) {
				$newcharstring .= "\xEF\xBB\xBF";
			}
			for ($i = 0; $i < strlen($data); $i++) {
				$charval = ord($data[$i]);
				$newcharstring .= Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::wt_iconv_fallback_int_utf8($charval);
			}
			return $newcharstring;
		} 
	}

    public static function highest_line_item_count($line_item_keys) {
   
        $all_items  = array_count_values(array_column($line_item_keys, 'order_id'));
		$max_count = 0;
		if(count($all_items) > 0){
			$max_count = max($all_items);
		}
        return $max_count;
        
    }
    
    /**
     * Wrap a column in quotes for the CSV
     * @param  string data to wrap
     * @return string wrapped data
     */
    public static function wrap_column($data) {
        return '"' . str_replace('"', '""', $data) . '"';
    }
    
    public static function get_max_line_items() {
        
		global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
		$query_line_items = "select COUNT(p.order_id) AS ttal from {$wpdb->prefix}woocommerce_order_items as p where order_item_type ='line_item' GROUP BY p.order_id ORDER BY ttal DESC LIMIT 1";
		$line_item_keys = $wpdb->get_col($query_line_items);
        // phpcs:enable
		return isset( $line_item_keys[0] ) ? absint( $line_item_keys[0] ) : 0;
    }
}
}


/*
* https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query#adding-custom-parameter-support
* It is possible to add support for custom query variables in wc_get_orders and WC_Order_Query. To do this you need to filter the generated query.
*/
add_filter('woocommerce_order_data_store_cpt_get_orders_query', function ($query, $query_vars) {
   if (!empty($query_vars['wt_meta_query'])) {

       foreach ($query_vars['wt_meta_query'] as $meta_querys) {

           foreach ($meta_querys as $key => $value) {
               $meta_query[$key] = $value;
           }
           if (!empty($meta_query)) {
               $query['meta_query'][] = $meta_query;
           }
       }
   }
   return $query;
}, 10, 2);
