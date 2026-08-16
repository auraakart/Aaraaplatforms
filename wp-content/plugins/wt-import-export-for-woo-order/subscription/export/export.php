<?php

if (!defined('WPINC')) {
    exit;
}
use Automattic\WooCommerce\Utilities\OrderUtil;

class Wt_Import_Export_For_Woo_Subscription_Export {

    public $parent_module = null;
    private $line_items_max_count = 0;
    private $export_to_separate_columns = false;
    private $export_to_separate_rows = false;    
    private $line_item_meta;
    private $exclude_line_items = false; 

    public function __construct($parent_object) {

        $this->parent_module = $parent_object;
    }

    public function prepare_header() {

        $export_columns = $this->parent_module->get_selected_column_names();
        
        if($this->exclude_line_items){
            return apply_filters('hf_alter_coupon_csv_header', $export_columns);
        }
        
        $this->line_item_meta = self::get_all_line_item_metakeys();
        
        $max_line_items = $this->line_items_max_count;
      
        for ($i = 1; $i <= $max_line_items; $i++) {
            $export_columns["line_item_{$i}"] = "line_item_{$i}";
        }        
        if ($this->export_to_separate_columns) {
            for ($i = 1; $i <= $max_line_items; $i++) {
                foreach ($this->line_item_meta as $meta_value) {
                    $new_val = str_replace("_", " ", $meta_value);
                    $export_columns["line_item_{$i}_name"] = "Product Item {$i} Name";
                    $export_columns["line_item_{$i}_product_id"] = "Product Item {$i} id";
                    $export_columns["line_item_{$i}_sku"] = "Product Item {$i} SKU";
                    $export_columns["line_item_{$i}_quantity"] = "Product Item {$i} Quantity";
                    $export_columns["line_item_{$i}_total"] = "Product Item {$i} Total";
                    $export_columns["line_item_{$i}_subtotal"] = "Product Item {$i} Subtotal";
                    if (in_array($meta_value, array("_product_id", "_qty", "_variation_id", "_line_total", "_line_subtotal", "_tax_class", "_line_tax", "_line_tax_data", "_line_subtotal_tax"))) {
                        continue;
                    } else {
                        $export_columns["line_item_{$i}_$meta_value"] = "Product Item {$i} $new_val";
                    }
                }
            }
        }
        
        if ($this->export_to_separate_rows) {
            $export_columns = $this->wt_line_item_separate_row_csv_header($export_columns);
        }
                        
        return apply_filters('hf_alter_coupon_csv_header', $export_columns, $max_line_items);
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
            /* to fix the data sort order issue  */ 
            $uset_data = array('line_item_product_id','line_item_name','line_item_sku','line_item_quantity','line_item_subtotal','line_item_subtotal_tax','line_item_total','line_item_total_tax','item_refunded','item_refunded_qty','item_meta');
            foreach ($uset_data as $value) {
                unset($order_export_data[$value]);
            }            
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
                
        $export_statuses = !empty($form_data['filter_form_data']['wt_iew_statuses']) ? $form_data['filter_form_data']['wt_iew_statuses'] : 'any';        
        $start_date = !empty($form_data['filter_form_data']['wt_iew_start_date']) ? $form_data['filter_form_data']['wt_iew_start_date'] : '';//date('Y-m-d 00:00', 0);
        $end_date = !empty($form_data['filter_form_data']['wt_iew_end_date']) ? $form_data['filter_form_data']['wt_iew_end_date']. ' 23:59:59.99' : '';//date('Y-m-d 23:59', current_time('timestamp'));
        $next_pay_date = !empty($form_data['filter_form_data']['wt_iew_next_pay_date']) ? $form_data['filter_form_data']['wt_iew_next_pay_date'] : '';
        
        $payment_methods = !empty($form_data['filter_form_data']['wt_iew_payment_methods']) ? wc_clean($form_data['filter_form_data']['wt_iew_payment_methods']) : array();        
        $email = !empty($form_data['filter_form_data']['wt_iew_email']) ? wc_clean($form_data['filter_form_data']['wt_iew_email']) : array();
        $products = !empty($form_data['filter_form_data']['wt_iew_products']) ? wc_clean($form_data['filter_form_data']['wt_iew_products']): array();

        $coupons = !empty($form_data['filter_form_data']['wt_iew_coupons']) ?  array_filter(explode(',', strtolower($form_data['filter_form_data']['wt_iew_coupons'])),'trim'): array();
                               
        $export_sortby = !empty($form_data['filter_form_data']['wt_iew_sort_columns']) ? implode(' ', $form_data['filter_form_data']['wt_iew_sort_columns']) : 'ID'; // get_post accept spaced string
        $export_sort_order = !empty($form_data['filter_form_data']['wt_iew_order_by']) ? $form_data['filter_form_data']['wt_iew_order_by'] : 'ASC';
        
        $export_limit = !empty($form_data['filter_form_data']['wt_iew_limit']) ? intval($form_data['filter_form_data']['wt_iew_limit']) : 999999999; //user limit
        $current_offset = !empty($form_data['filter_form_data']['wt_iew_offset']) ? intval($form_data['filter_form_data']['wt_iew_offset']) : 0; //user offset
        $batch_count = !empty($form_data['advanced_form_data']['wt_iew_batch_count']) ? $form_data['advanced_form_data']['wt_iew_batch_count'] : Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('default_export_batch');          

        $exclude_already_exported = (!empty($form_data['advanced_form_data']['wt_iew_exclude_already_exported']) && $form_data['advanced_form_data']['wt_iew_exclude_already_exported'] == 'Yes') ? true : false;
        
        $this->export_to_separate_columns = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate_columns']) && $form_data['advanced_form_data']['wt_iew_export_to_separate_columns'] == 'Yes') ? true : false;                       
        if(!$this->export_to_separate_columns){
            $this->export_to_separate_columns = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate']) && $form_data['advanced_form_data']['wt_iew_export_to_separate'] == 'column') ? true : false;               
        }

        $this->export_to_separate_rows = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate_rows']) && $form_data['advanced_form_data']['wt_iew_export_to_separate_rows'] == 'Yes') ? true : false;               
        if(!$this->export_to_separate_rows){
            $this->export_to_separate_rows = (!empty($form_data['advanced_form_data']['wt_iew_export_to_separate']) && $form_data['advanced_form_data']['wt_iew_export_to_separate'] == 'row') ? true : false;               
        }
        $this->exclude_line_items = (!empty($form_data['advanced_form_data']['wt_iew_exclude_line_items']) && $form_data['advanced_form_data']['wt_iew_exclude_line_items'] == 'Yes') ? true : false;               
        
        
        
        $subscription_plugin = 'WC';
        if(class_exists('HF_Subscription')){
            $subscription_plugin = 'HF';
        }

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
            if(self::is_hpos_enabled()){
                global $wpdb;
                $format = array();
                $post_type = ($subscription_plugin == 'WC') ? 'shop_subscription' : 'hf_shop_subscription';
                $query_args="SELECT DISTINCT po.id FROM {$wpdb->prefix}wc_orders as po LEFT JOIN {$wpdb->prefix}wc_orders_meta AS pm ON po.id = pm.order_id WHERE type = %s"; 
                $format[]=$post_type;
                if( $end_date ){
                    $query_args.= "AND date_created_gmt<=%s";
                    $format[] = $end_date;
                }
                if( $start_date ){
                    $query_args.= "AND  date_created_gmt>=%s ";
                    $format[] = $start_date;
                }
                if (!empty($export_statuses)) {
                    $statuses = $export_statuses;
                    if (!empty($statuses) && is_array($statuses)) {
                        $value=array();
                        foreach($statuses as $status){
                           
                            $values[] = "'$status'";
                        }
                        $statuses = implode(",", $values);
                        if (!in_array($statuses, array('any', 'trash'))) {
                            $query_args .= " AND po.status IN ($statuses)";
                        }
                    }
                }  
                
                if (!empty($payment_methods)) {
                    $query_args .= " AND (";
                    $first_payment_method = true;
                    foreach ($payment_methods as $key => $value) {
                        $value = strtolower($value);
                        if (!$first_payment_method) {
                            $query_args .= " OR";
                        }
                        $query_args .= " po.payment_method = %s";
                        $format[] = $value;
                        $first_payment_method = false;
                    }
                    $query_args .= ")";
                }
                if (!empty($next_pay_date)) {
                    $query_args .= " AND (pm.meta_key = '_schedule_next_payment' AND pm.meta_value LIKE %s)";
                    $format[] = '%' . $next_pay_date . '%';
                }
                $query_args .= " ORDER BY id DESC";
                
            }else{
                $query_args = array(
                    'fields' => 'ids',
                    'post_type' =>  ($subscription_plugin == 'WC') ? 'shop_subscription' : 'hf_shop_subscription',
                    'post_status' => 'any',                
                    'orderby' => $export_sortby,
                    'order' => $export_sort_order,                
                );
                
                if( $end_date || $start_date ){
                    $query_args['date_query'] =  array(
                        array(
                            'before' => $end_date,
                            'after' => $start_date,
                            'inclusive' => true,
                        ),
                    );
                }
                
                if (!empty($export_statuses)) {
                    $statuses = $export_statuses;
                    if (!empty($statuses) && is_array($statuses)) {
                        $query_args['post_status'] = implode(',', $statuses);
                        if (!in_array($query_args['post_status'], array('any', 'trash'))) {
                            $query_args['post_status'] = self::hf_sanitize_subscription_status_keys($query_args['post_status']);
                        }
                    }
                }            
                
                if (!empty($payment_methods)) {
                    $meta_query = array('relation' => 'OR');
                    foreach ($payment_methods as $key => $value) {
                        $value = strtolower($value);
                        $meta_query[] = array(
                            'key' => '_payment_method',
                            'value' => $value,
                        );
                    }
                    $query_args['meta_query'][] =$meta_query;
                }
                
                if (!empty($next_pay_date)) {                
                   $query_args['meta_query'][]  = array(
                            'key' => '_schedule_next_payment',
                            'value' => $next_pay_date,
                            'compare' => 'LIKE'
                        );
                      
                }
            }
         
                                    
            $query_args = apply_filters('woocommerce_get_subscriptions_query_args', $query_args);


            $subscription_order_ids = $prod_subscription_ids = $coupon_subscription_ids = 0;
            
            if (!empty($email)) {

                global $wpdb;				
				$subscription_type = 'shop_subscription';
                if( class_exists( 'HF_Subscription')){              
					$subscription_type = 'hf_shop_subscription';
				}
                $query = "SELECT {$wpdb->prefix}posts.ID FROM {$wpdb->prefix}posts INNER JOIN {$wpdb->prefix}postmeta ON ( {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id ) WHERE ( ( {$wpdb->prefix}postmeta.meta_key = '_customer_user' AND {$wpdb->prefix}postmeta.meta_value IN ('". implode("','", $email) ."') ) ) AND {$wpdb->prefix}posts.post_type = '$subscription_type' " ;
				$subscription_order_ids = $wpdb->get_col($query);           
            } 
             
            if (!empty($products) ) {

				$prod_subscription_ids = array();
				if( function_exists('wcs_get_subscriptions_for_product')){
					$prod_subscription_ids = wcs_get_subscriptions_for_product($products);
				}else{
					if( function_exists('hf_get_subscriptions_for_product')){
						$prod_subscription_ids = hf_get_subscriptions_for_product($products);
					}
				}
            }

            if (!empty($coupons)) {
				
				$subscription_type = 'shop_subscription';
                if( class_exists( 'HF_Subscription')){              
					$subscription_type = 'hf_shop_subscription';;
				}
				$coupon_subscription_ids = self::wt_get_subscription_of_coupons($coupons, $subscription_type);
            }         
            
            /**
            *   taking total records
            */
            $total_records=0;
            if($batch_offset==0) //first batch
            {
                $total_item_args = $query_args;
                if(self::is_hpos_enabled()){
                    $total_item_args.=" LIMIT %d OFFSET %d";
                    $format[] = $export_limit;
                    $format[] = $current_offset;
                    $subscription_post_ids = $wpdb->get_col($wpdb->prepare( $total_item_args, $format));
                }else{
                    $total_item_args['posts_per_page'] = $export_limit; //user given limit
                    $total_item_args['offset'] = $current_offset; //user given offset                
                    $subscription_post_ids = get_posts($total_item_args);    
                }
                
                if (!empty($email)) {
                    if(!empty($subscription_order_ids)){
						$subscription_post_ids = array_intersect($subscription_order_ids, $subscription_post_ids);  
					}
                }
                if (!empty($products) ) { 
					if(!empty($prod_subscription_ids)){
						$subscription_post_ids = array_intersect($prod_subscription_ids, $subscription_post_ids);
					}
                }
                if (!empty($coupons)) {
					if(!empty($coupon_subscription_ids)){
						$subscription_post_ids = array_intersect($coupon_subscription_ids, $subscription_post_ids);
					}
                }
              
                                                
                foreach ($subscription_post_ids as $key => $subscription_id) {
                    if (!$subscription_id )
                        unset($subscription_post_ids[$key]);

                    if ($exclude_already_exported) {
                        if(get_post_meta($subscription_id, 'wt_subscription_exported_status', 1))
                            unset($subscription_post_ids[$key]);
                    }                    
                }
                                
                
                $total_records = count($subscription_post_ids);   
                
                $this->line_items_max_count = $this->get_max_line_items($subscription_post_ids);
                                                
                add_option('wt_subscription_order_line_items_max_count',$this->line_items_max_count);  
                                
            }
            
            if(empty($this->line_items_max_count)){
                $this->line_items_max_count = get_option('wt_subscription_order_line_items_max_count');
            }
            if(self::is_hpos_enabled()){
                $query_args.=" LIMIT %d OFFSET %d";
                if($batch_offset==0){
                    $format= array_slice($format,0,count($format)-2);
                }
				$format[]=  $limit;
				$format[]=  $real_offset;
                $subscription_post_ids = $wpdb->get_col($wpdb->prepare( $query_args, $format));
            }else{
                $query_args['offset'] = $real_offset;
                $query_args['posts_per_page'] = $limit;
                    
                $subscription_post_ids = get_posts($query_args);  
            }
            
         


            if (!empty($email)) {
                if(!empty($subscription_order_ids)){
                    $subscription_post_ids = array_intersect($subscription_order_ids, $subscription_post_ids);  
                }
            }
            if (!empty($products) ) { 
                if(!empty($prod_subscription_ids)){
                    $subscription_post_ids = array_intersect($prod_subscription_ids, $subscription_post_ids);
                }
            }
            if (!empty($coupons)) {
                if(!empty($coupon_subscription_ids)){
                    $subscription_post_ids = array_intersect($coupon_subscription_ids, $subscription_post_ids);
                }
            }
            
          
            $subscriptions = array();
            foreach ($subscription_post_ids as $subscription_id) {
                if (!$subscription_id )
                    break; 
                
                if ($exclude_already_exported) {
                    if(self::is_hpos_enabled()){
                        $sql = "SELECT meta_value from {$wpdb->prefix}wc_orders_meta WHERE order_id = $subscription_id AND meta_key = 'wt_subscription_exported_status'";
                        $result=$wpdb->get_col( $sql);  
                        if(empty($result)){
                            $result=false;
                        }

                    }else{
                        $result = get_post_meta($subscription_id, 'wt_subscription_exported_status', 1);
                    }
                    if($result)
                        break;  
                }

                $subscriptions[]  = self::hf_get_subscription($subscription_id);
            }
          
            $subscriptions = apply_filters('hf_retrieved_subscriptions', $subscriptions);

            if($subscription_plugin == 'WC'){
            // Loop orders
                foreach ($subscriptions as $subscription) {
                   
                    $data_array[] = $this->get_subscriptions_csv_row($subscription);
                    
                    // updating records with expoted status 
                    update_post_meta($subscription->get_id(), 'wt_subscription_exported_status', TRUE);
                }
            }else{
                // Loop orders
                foreach ($subscriptions as $subscription) {

                    $data_array[] = $this->get_wt_subscriptions_csv_row($subscription);
                    
                    // updating records with expoted status 
                    update_post_meta($subscription->get_id(), 'wt_subscription_exported_status', TRUE);
                }
            }

            if($this->export_to_separate_rows){
                $data_array = $this->wt_ier_alter_order_data_befor_export_for_separate_row($data_array);
            }
            
            $data_array = apply_filters('wt_ier_alter_subscriptions_data_befor_export', $data_array);  
            
            
            $return['total'] = $total_records; 
            $return['data'] = $data_array;
			if( 0 == $batch_offset && 0 == $total_records ){
            	$return['no_post'] = __( 'Nothing to export under the selected criteria. Please check and try adjusting the filters.' );
            }
            return $return;
        }
    }  
    
   

    public static function hf_get_subscription($subscription) {
        if (is_object($subscription) && self::hf_is_subscription($subscription)) {
            $subscription = $subscription->id;
        }
        $subscription_plugin = 'WC';
        if(class_exists('HF_Subscription')){
            $subscription_plugin = 'HF';
        }
        if($subscription_plugin == 'WC'){
        if (!class_exists('WC_Subscription')):
            require WP_PLUGIN_DIR.'/woocommerce-subscriptions/wcs-functions.php';
            require WP_PLUGIN_DIR.'/woocommerce-subscriptions/includes/class-wc-subscription.php';
        endif;
        $subscription = new WC_Subscription($subscription);
        }else{
        if (!class_exists('HF_Subscription')):
            require WP_PLUGIN_DIR.'/xa-woocommerce-subscriptions/includes/subscription-common-functions.php';
            require WP_PLUGIN_DIR.'/xa-woocommerce-subscriptions/includes/components/class-subscription.php';
        endif;
        $subscription = new HF_Subscription($subscription);
        }
        if (!self::hf_is_subscription($subscription)) {
            $subscription = false;
        }
        return apply_filters('hf_get_subscription', $subscription);
    }
    
    
    public static function hf_is_subscription($subscription) {
        if (is_object($subscription) && (is_a($subscription, 'WC_Subscription') || is_a($subscription, 'HF_Subscription'))) {
            $is_subscription = true;
        } elseif (is_numeric($subscription) && ('shop_subscription' == get_post_type($subscription) || 'hf_shop_subscription' == get_post_type($subscription))) {
            $is_subscription = true;
        } else {
            $is_subscription = false;
        }
        return apply_filters('hf_is_subscription', $is_subscription, $subscription);
    }
    
    
    public function get_subscriptions_csv_row($subscription) {
                        
        //$csv_columns = $this->parent_module->get_selected_column_names();          
        $csv_columns = $this->prepare_header();
        
        
//        if (empty($export_columns)) {
//            $export_columns = $csv_columns;
//        }
        $fee_total = $fee_tax_total = 0;
        $fee_items = $shipping_items = array();
        if (0 != sizeof(array_intersect(array_keys($csv_columns), array('fee_total', 'fee_tax_total', 'fee_items')))) {
            foreach ($subscription->get_fees() as $fee_id => $fee) {
                $fee_items[] = implode('|', array(
                    'name:' . html_entity_decode($fee['name'], ENT_NOQUOTES, 'UTF-8'),
                    'total:' . wc_format_decimal($fee['line_total'], 2),
                    'tax:' . wc_format_decimal($fee['line_tax'], 2),
                    'tax_data:' . $fee['line_tax_data'],
                    'tax_class:' . $fee['tax_class'],
                ));
                $fee_total += (float) $fee['line_total'];
                $fee_tax_total += (float)$fee['line_tax'];
            }
        }
        
        $line_items_shipping = $subscription->get_items('shipping');
        foreach ($line_items_shipping as $item_id => $item) {
            if (is_object($item)) {
                if ($meta_data = $item->get_formatted_meta_data('')) :
                    foreach ($meta_data as $meta_id => $meta) :
                        if (in_array($meta->key, $line_items_shipping)) {
                            continue;
                        }
                        // html entity decode is not working preoperly
                        $shipping_items[] = implode('|', array('item:' . wp_kses_post($meta->display_key), 'value:' . str_replace('&times;', 'X', strip_tags($meta->display_value))));
                    endforeach;
                endif;
            }
        }

        if (!function_exists('get_user_by')) {
            require ABSPATH . 'wp-includes/pluggable.php';
        }

        $user_values = get_user_by('ID',(WC()->version < '2.7') ? $subscription->customer_user : $subscription->get_customer_id());
        
        // Preparing data for export
        foreach ($csv_columns as $header_key => $_) {
            switch ($header_key) {
                case 'subscription_id':
                    $value = (WC()->version < '2.7') ? $subscription->id : $subscription->get_id();
                    break;
                case 'subscription_status':
                    $value = (WC()->version < '2.7') ? $subscription->post_status : $subscription->get_status();
                    break;
                case 'customer_id':
                    $value = (WC()->version < '2.7') ? $subscription->customer_user : $subscription->get_customer_id();
                    break;
                case 'customer_username':
                    $value = is_object($user_values) ? $user_values->user_login : '';
                    break;
                case 'customer_email':
                    $value = is_object($user_values) ? $user_values->user_email : '';
                    break;
                case 'fee_total':
                case 'fee_tax_total':
                    $value = ${$header_key};
                    break;
                case 'order_shipping_tax':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_shipping_tax();
                    break;
                case 'order_total':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_total();
                    break;
                case 'order_tax':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_total_tax();
                    break;
                case 'order_shipping':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_total_shipping();
                    break;
                case 'cart_discount_tax':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_discount_tax();
                    break;
                case 'cart_discount':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_total_discount();
                    break;
                case 'date_created':
                case 'trial_end_date':
                case 'next_payment_date':
                case 'last_order_date_created':
                case 'end_date':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_date($header_key);
                    break;
                case 'billing_period':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_period();
                    break;
                case 'billing_interval':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_interval();
                    break;
                case 'payment_method':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_payment_method();
                    break;
                case 'payment_method_title':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_payment_method_title();
                    break;
                case 'billing_first_name':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_first_name();
                    break;
                case 'billing_last_name':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_last_name();
                    break;
                case 'billing_email':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_email();
                    break;
                case 'billing_phone':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_phone();
                    break;
                case 'billing_address_1':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_address_1();
                    break;
                case 'billing_address_2':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_address_2();
                    break;
                case 'billing_postcode':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_postcode();
                    break;
                case 'billing_city':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_city();
                    break;
                case 'billing_state':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_state();
                    break;
                case 'billing_country':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_country();
                    break;
                case 'billing_company':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_billing_company();
                    break;
                case 'shipping_first_name':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_first_name();
                    break;
                case 'shipping_last_name':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_last_name();
                    break;
                case 'shipping_address_1':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_address_1();
                    break;
                case 'shipping_address_2':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_address_2();
                    break;
                case 'shipping_postcode':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_postcode();
                    break;
                case 'shipping_city':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_city();
                    break;
                case 'shipping_state':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_state();
                    break;
                case 'shipping_country':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_country();
                    break;
                case 'shipping_company':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_shipping_company();
                    break;
                case 'shipping_phone':
                    $value = (version_compare(WC_VERSION, '5.6', '<')) ? '' : $subscription->get_shipping_phone();
                    break;                
                case 'customer_note':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_customer_note();
                    break;
                case 'order_currency':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_currency();
                    break;
                case 'post_parent':
                    if (!empty($subscription->get_parent() ))
                        $value = $subscription->get_parent_id();
                    else
                        $value = 0;
                    break;
                case 'order_notes':
                    $order_notes = implode('||', (defined('WC_VERSION') && (WC_VERSION >= 3.2)) ? self::get_order_notes_new($subscription) : self::get_order_notes($subscription));
                    
//                    remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));
//                    $notes = get_comments(array('post_id' => (WC()->version < '2.7') ? $subscription->id : $subscription->get_id(), 'approve' => 'approve', 'type' => 'order_note'));
//                    add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));
//                    $order_notes = array();
//                    foreach ($notes as $note) {
//                        $order_notes[] = str_replace(array("\r", "\n"), ' ', $note->comment_content);
//                    }
//                    if (!empty($order_notes)) {
//                        $value = implode(';', $order_notes);
//                    } else {
//                        $value = '';
//                    }
                    if (!empty($order_notes)) {
                        $value = $order_notes;
                    } else {
                        $value = '';
                    }
                                        
                    break;
                case 'renewal_orders':
                    $renewal_orders = $subscription->get_related_orders('ids', 'renewal');
                    if (!empty($renewal_orders)) {
                        $value = implode('|', $renewal_orders);
                    } else {
                        $value = '';
                    }
                    break; 
                    
                    case 'order_items':    
                    $line_items = array();
                    $order_items = array();
                    foreach ($subscription->get_items() as $item_id => $item) {
                        $product = $item->get_product();
                        if (!is_object($product)) {
                            $product = new WC_Product(0);
                        }

            //                        $product_id = self::hf_get_canonical_product_id($item);
                        $item_meta = self::get_order_line_item_meta($item_id);
                        $prod_type = (WC()->version < '3.0.0') ? $product->product_type : $product->get_type();
                        $line_item = array(
                            'product_id' => (WC()->version < '2.7.0') ? $product->id : (($prod_type == 'variable' || $prod_type == 'variation' || $prod_type == 'subscription_variation') ? $product->get_parent_id() : $product->get_id()),
                            'name' => html_entity_decode($item['name'], ENT_NOQUOTES, 'UTF-8'),
                            'sku' => $product->get_sku(),
                            'quantity' => $item['qty'],
                            'total' => wc_format_decimal($subscription->get_line_total($item), 2),
                            'sub_total' => wc_format_decimal($subscription->get_line_subtotal($item), 2),
                        );

                        // add line item tax
                        $line_tax_data = isset($item['line_tax_data']) ? $item['line_tax_data'] : array();
                        $tax_data = maybe_unserialize($line_tax_data);
                        $tax_detail = isset($tax_data['total']) ? wc_format_decimal(wc_round_tax_total(array_sum((array) $tax_data['total'])), 2) : '';
                        if ($tax_detail != '0.00' && !empty($tax_detail)) {
                            $line_item['tax'] = $tax_detail;
                        }
                        $line_tax_ser = maybe_serialize($line_tax_data);
                        $line_item['tax_data'] = $line_tax_ser;

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
                                    if(is_object($value))
                                    $value = $value->meta_value;
                                    if (is_array($value))
                                        $value = implode(',', $value);
                                    $line_item[$key] = $value;
                                    break;
                            }
                        }

                        if ($prod_type === 'variable' || $prod_type === 'variation' || $prod_type === 'subscription_variation') {
                            $line_item['_variation_id'] = (WC()->version > '2.7') ? $product->get_id() : $product->variation_id;
                        }
                        
						$order_item_data_arr = array();
                        foreach ($line_item as $name => $value) {
                            $order_item_data_arr[$name] = $name . ':' . $value;
                        }
                        
                        $order_item_data = implode('|', $order_item_data_arr);

                        if ($line_item) {
                            $order_items[] = $order_item_data;
                            $line_items[] = $line_item;
                        }
                    }
                    if (!empty($order_items)) {
                        $value = implode('||', $order_items);
                    }                                        
                    break; 
                case 'coupon_items':
                    $coupon_items = array();
                    foreach ($subscription->get_items('coupon') as $_ => $coupon_item) {
                        $coupon = new WC_Coupon($coupon_item['name']);
                        $coupon_post = get_post($coupon->id);
                        $coupon_items[] = implode('|', array(
                            'code:' . $coupon_item['name'],
                            'description:' . ( is_object($coupon_post) ? $coupon_post->post_excerpt : '' ),
                            'amount:' . wc_format_decimal($coupon_item['discount_amount'], 2),
                        ));
                    }
                    if (!empty($coupon_items)) {
                        $value = implode(';', $coupon_items);
                    } else {
                        $value = '';
                    }
                    break;

                case 'payment_token_data';
                    $token_id = $subscription->get_payment_tokens();
                    if($token_id){
                        $t_id = maybe_unserialize($token_id);
                        if(isset($t_id[0])){
                            $data_store    = WC_Data_Store::load( 'payment-token' );
                            $token_data = $data_store->get_tokens( array('token_id'=>$t_id[0]) );
                            $token_meta_data = $data_store->get_metadata( $t_id[0] );
                            $token = array(
                                'token_data' => ( ! empty( $token_data ) ) ? $token_data : '',
                                'token_meta_data' => ( ! empty ( $token_meta_data ) ) ? $token_meta_data : '',
                                );
                        }
                    }
                    if(isset($token) && !empty ($token)){
                        $value =serialize($token);
                    }else{
                        $value = '';
                    }
                    break;
                case 'download_permissions':
                    $value = (WC()->version < '2.7') ? ($subscription->download_permissions_granted ? $subscription->download_permissions_granted : 0) :($subscription->is_download_permitted());
                    break;
                case 'shipping_method':
                    $shipping_lines = array();
                    foreach ($subscription->get_shipping_methods() as $shipping_item_id => $shipping_item) {
                        $shipping_lines[] = implode('|', array(
                            'method_id:' . $shipping_item['method_id'],
                            'method_title:' . $shipping_item['name'],
                            'total:' . wc_format_decimal($shipping_item['cost'], 2),
                            'total_tax:' . wc_format_decimal( $shipping_item['total_tax'], 2 ),
							'taxes:' . maybe_serialize($shipping_item['taxes']),
                            )
                        );
                    }
                    if (!empty($shipping_lines)) {
                        $value = implode(';', $shipping_lines);
                    } else {
                        $value = '';
                    }
                    break;
                case 'fee_items':
                    $value = implode(';', $fee_items);
                    break;
                case 'shipping_items':
                    $value = implode(';', $shipping_items);
                    break;
                case 'tax_items':
                    $tax_items = array();
                    foreach ( $subscription->get_taxes() as $tax_code => $tax ) {
						$tax_items[] = implode('|', array(
							'rate_id:' . $tax->get_rate_id(),
							'code:' . $tax->get_rate_code(),
							'total:' . wc_format_decimal($tax->get_tax_total(), 2),
							'label:' . $tax->get_label(),
							'tax_rate_compound:' . $tax->get_compound(),
							'shipping_tax_amount:' . $tax->get_shipping_tax_total(),
							'rate_percent:' . $tax->get_rate_percent(),
						));
                    }
                    if (!empty($tax_items)) {
                        $value = implode(';', $tax_items);
                    } else {
                        $value = '';
                    }
                    break;
                default :
                    if(strstr($header_key, 'meta:')){
                        if($header_key == 'meta:_payment_tokens'){
                            $payment_tokens = $subscription->get_payment_tokens();
                        }else{
                            $value = maybe_serialize($subscription->get_meta(str_replace('meta:', '', $header_key),TRUE));
                        }
                    } else {
                        $value = '';
                    }
            }
            $csv_row[$header_key] = $value;
        }

//        $subscription_data = array();
//        foreach ($csv_columns as $header_key => $_) {
//            // Strict string comparison, as values like '0' are valid
//            $value = ( '' !== $csv_row[$header_key] ) ? $csv_row[$header_key] : '';
//            $subscription_data[$header_key] = $value;
//        }
        
        $subscription_data = array();
        foreach ($csv_columns as $key => $value) {
            if (!$csv_row || array_key_exists($key, $csv_row)) {
                $subscription_data[$key] = $csv_row[$key];
            } 
        }
//        return apply_filters('hf_alter_subscription_data', $data, $export_columns, $csv_columns);
//        return apply_filters('hf_alter_subscription_data', $data, $csv_columns, $csv_columns);  //support for old customer
               

        if($this->exclude_line_items){
            return apply_filters('hf_alter_subscription_data', $subscription_data, $csv_columns, $csv_columns,  array('max_line_items' => 0));
        }
      
        $li = 1;
        foreach ($line_items as $line_item) {
            foreach ($line_item as $name => $value) {
                $line_item[$name] = $name . ':' . $value;
            }
            $line_item = implode(apply_filters('wt_change_item_separator', '|'), $line_item);
            $subscription_data["line_item_{$li}"] = $line_item;
            $li++;
        }
                
        $max_line_items = $this->line_items_max_count;
        for ($i = 1; $i <= $max_line_items; $i++) {
            $subscription_data["line_item_{$i}"] = !empty($subscription_data["line_item_{$i}"]) ? self::format_data($subscription_data["line_item_{$i}"]) : '';
        }                       
        if ($this->export_to_separate_columns) {
            $line_item_values = self::get_all_metakeys_and_values($subscription);
            $this->line_item_meta = self::get_all_line_item_metakeys();
            $max_line_items = $this->line_items_max_count; 
            
            for ($i = 1; $i <= $max_line_items; $i++) {
                $line_item_array = explode(apply_filters('wt_change_item_separator', '|'), $subscription_data["line_item_{$i}"]);                 
                foreach ($this->line_item_meta as $meta_val) {
                    $subscription_data["line_item_{$i}_product_id"] = !empty($line_item_array[0]) ? substr($line_item_array[0], strpos($line_item_array[0], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_name"] = !empty($line_item_array[1]) ? substr($line_item_array[1], strpos($line_item_array[1], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_sku"] = !empty($line_item_array[2]) ? substr($line_item_array[2], strpos($line_item_array[2], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_quantity"] = !empty($line_item_array[3]) ? substr($line_item_array[3], strpos($line_item_array[3], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_total"] = !empty($line_item_array[4]) ? substr($line_item_array[4], strpos($line_item_array[4], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_subtotal"] = !empty($line_item_array[5]) ? substr($line_item_array[5], strpos($line_item_array[5], ':') + 1) : '';
                    if (in_array($meta_val, array("_product_id", "_qty", "_variation_id", "_line_total", "_line_subtotal", "_tax_class", "_line_tax", "_line_tax_data", "_line_subtotal_tax"))) {
                        continue;
                    } else {
                        $subscription_data["line_item_{$i}_$meta_val"] = !empty($line_item_values[$i][$meta_val]) ? $line_item_values[$i][$meta_val] : '';
                    }
                }
            }
        }                         

        $order_data_filter_args = array('max_line_items' => $max_line_items);

        if ($this->export_to_separate_rows) {
            $subscription_data = $this->wt_line_item_separate_row_csv_data($subscription, $subscription_data, $order_data_filter_args);
        }  
        
        return apply_filters('hf_alter_subscription_data', $subscription_data, $csv_columns, $csv_columns, $order_data_filter_args);                                
    }
    
    
    public function get_wt_subscriptions_csv_row($subscription) {
        
//        $csv_columns = $this->parent_module->get_selected_column_names();
        $csv_columns = $this->prepare_header();
        
        

        $fee_total = $fee_tax_total = 0;
        $fee_items = $shipping_items = array();

        if (0 != sizeof(array_intersect(array_keys($csv_columns), array('fee_total', 'fee_tax_total', 'fee_items')))) {
            foreach ($subscription->get_fees() as $fee_id => $fee) {
                $fee_items[] = implode('|', array(
                    'name:' . html_entity_decode($fee['name'], ENT_NOQUOTES, 'UTF-8'),
                    'total:' . wc_format_decimal($fee['line_total'], 2),
                    'tax:' . wc_format_decimal($fee['line_tax'], 2),
                    'tax_class:' . $fee['tax_class'],
                ));
				
                $fee_total += (float)$fee['line_total'];
                $fee_tax_total += (float)$fee['line_tax'];
            }
        }
        
        $line_items_shipping = $subscription->get_items('shipping');
        foreach ($line_items_shipping as $item_id => $item) {
            if (is_object($item)) {
                if ($meta_data = $item->get_formatted_meta_data('')) :
                    foreach ($meta_data as $meta_id => $meta) :
                        if (in_array($meta->key, $line_items_shipping)) {
                            continue;
                        }
                        // html entity decode is not working preoperly
                        $shipping_items[] = implode('|', array('item:' . wp_kses_post($meta->display_key), 'value:' . str_replace('&times;', 'X', strip_tags($meta->display_value))));
                    endforeach;
                endif;
            }
        }
        if (!function_exists('get_user_by')) {
            require ABSPATH . 'wp-includes/pluggable.php';
        }
        $user_values = get_user_by('ID', (WC()->version < '2.7') ? $subscription->customer_user : $subscription->get_customer_id());
        
        // Preparing data for export
        foreach ($csv_columns as $header_key => $_) {
            switch ($header_key) {
                case 'subscription_id':
                    $value = $subscription->get_id();
                    break;
                case 'subscription_status':
                    $value = $subscription->get_status();
                    break;
                case 'customer_id':
                    $value = is_object($user_values) ? $user_values->ID : '';
                    break;
                case 'customer_username':
                    $value = is_object($user_values) ? $user_values->user_login : '';
                    break;
                case 'customer_email':
                    $value = is_object($user_values) ? $user_values->user_email : '';
                    break;
                case 'fee_total':
                case 'fee_tax_total':
                    $value = ${$header_key};
                    break;
                case 'order_shipping':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_total_shipping();
                    break;
                case 'order_shipping_tax':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_shipping_tax();
                    break;
                case 'order_tax':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_total_tax();
                    break;
                case 'cart_discount':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_total_discount();
                    break;
                case 'cart_discount_tax':
                    $value = (WC()->version < '2.7') ? (empty($subscription->{$header_key}) ? 0 : $subscription->{$header_key}) : $subscription->get_discount_tax();
                    break;
                case 'order_total':
                    $value = empty($subscription->get_total()) ? 0 : $subscription->get_total();
                    break;
                case 'date_created':
                    $value = $subscription->get_date('date_created');
                    break;
                case 'trial_end_date':
                    $value = $subscription->get_date('trial_end_date');
                    break;
                case 'next_payment_date':
                    $value = $subscription->get_date('next_payment_date');
                    break;
                case 'last_order_date_created':
                    $value = $subscription->get_date('last_order_date_created');
                    break;
                case 'end_date':
                    $value = $subscription->get_date('end_date');
                    break;
                case 'order_currency':
                    $value = (WC()->version < '2.7') ? $subscription->{$header_key} :$subscription->get_currency();
                    break;
                case 'billing_period':
                case 'billing_interval':
                case 'payment_method':
                case 'payment_method_title':
                case 'billing_first_name':
                case 'billing_last_name':
                case 'billing_email':
                case 'billing_phone':
                case 'billing_address_1':
                case 'billing_address_2':
                case 'billing_postcode':
                case 'billing_city':
                case 'billing_state':
                case 'billing_country':
                case 'billing_company':
                case 'shipping_first_name':
                case 'shipping_last_name':
                case 'shipping_address_1':
                case 'shipping_address_2':
                case 'shipping_postcode':
                case 'shipping_city':
                case 'shipping_state':
                case 'shipping_country':
                case 'shipping_company':
                case 'shipping_phone':                    
                case 'customer_note':
                    
                    $m_key = "get_$header_key";
                    
                    if(method_exists($subscription, $m_key)){
                        $value = $subscription->{$m_key}();
                    }else{
                        $value = $subscription->{$header_key};
                    }
                    break;
                case 'post_parent':
                        $post = get_post( $subscription->get_id() );
                        $value = $post->post_parent;
                    break;
                case 'order_notes':
					$order_notes = implode('||', (defined('WC_VERSION') && (WC_VERSION >= 3.2)) ? self::get_order_notes_new($subscription) : self::get_order_notes($subscription));
                    /*
					remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));
                    $notes = get_comments(array('post_id' => $subscription->get_id(), 'approve' => 'approve', 'type' => 'order_note'));
                    add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));
                    $order_notes = array();
                    foreach ($notes as $note) {
                        $order_notes[] = str_replace(array("\r", "\n"), ' ', $note->comment_content);
                    }
                    if (!empty($order_notes)) {
                        $value = implode(';', $order_notes);
                    } else {
                        $value = '';
                    }
					 * 
					 */
					if (!empty($order_notes)) {
                        $value = $order_notes;
                    } else {
                        $value = '';
                    }
                    break;
                case 'renewal_orders':
                    $renewal_orders = $subscription->get_related_orders('ids', 'renewal');
                    if (!empty($renewal_orders)) {
                        $value = implode('|', $renewal_orders);
                    } else {
                        $value = '';
                    }
                    break; 
                    
                case 'order_items':    
                    $line_items = array();
                    $order_items = array();
                    foreach ($subscription->get_items() as $item_id => $item) {
                        $product = $item->get_product();
                        if (!is_object($product)) {
                            $product = new WC_Product(0);
                        }

            //                        $product_id = self::hf_get_canonical_product_id($item);
                        $item_meta = self::get_order_line_item_meta($item_id);
                        $prod_type = (WC()->version < '3.0.0') ? $product->product_type : $product->get_type();
                        $line_item = array(
                            'product_id' => (WC()->version < '2.7.0') ? $product->id : (($prod_type == 'variable' || $prod_type == 'variation' || $prod_type == 'subscription_variation') ? $product->get_parent_id() : $product->get_id()),
                            'name' => html_entity_decode($item['name'], ENT_NOQUOTES, 'UTF-8'),
                            'sku' => $product->get_sku(),
                            'quantity' => $item['qty'],
                            'total' => wc_format_decimal($subscription->get_line_total($item), 2),
                            'sub_total' => wc_format_decimal($subscription->get_line_subtotal($item), 2),
                        );

                        // add line item tax
                        $line_tax_data = isset($item['line_tax_data']) ? $item['line_tax_data'] : array();
                        $tax_data = maybe_unserialize($line_tax_data);
                        $tax_detail = isset($tax_data['total']) ? wc_format_decimal(wc_round_tax_total(array_sum((array) $tax_data['total'])), 2) : '';
                        if ($tax_detail != '0.00' && !empty($tax_detail)) {
                            $line_item['tax'] = $tax_detail;
                        }
                        $line_tax_ser = maybe_serialize($line_tax_data);
                        $line_item['tax_data'] = $line_tax_ser;

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
                                    if(is_object($value))
                                    $value = $value->meta_value;
                                    if (is_array($value))
                                        $value = implode(',', $value);
                                    $line_item[$key] = $value;
                                    break;
                            }
                        }

                        if ($prod_type === 'variable' || $prod_type === 'variation' || $prod_type === 'subscription_variation') {
                            $line_item['_variation_id'] = (WC()->version > '2.7') ? $product->get_id() : $product->variation_id;
                        }
                        
                        foreach ($line_item as $name => $value) {
                            $order_item_data[$name] = $name . ':' . $value;
                        }
                        
                        $order_item_data = implode('|', $order_item_data);

                        if ($line_item) {
                            $order_items[] = $order_item_data;
                            $line_items[] = $line_item;
                        }
                    }
                    if (!empty($order_items)) {
                        $value = implode('||', $order_items);
                    }                                        
                    break; 
                case 'coupon_items':
                    $coupon_items = array();
					
                    foreach ($subscription->get_items('coupon') as $_ => $coupon_item) {
						
						$coupon_name = (WC()->version < '4.3.9') ? $coupon_item['name'] : $coupon_item->get_name();
						$coupon_amount = (WC()->version < '4.3.9') ? $coupon_item['discount_amount'] : $coupon_item->get_discount();
						
                        $coupon = new WC_Coupon($coupon_name);
                        $coupon_post = get_post(((WC()->version < '2.7') ? $coupon->id : $coupon->get_id()));
                        $coupon_items[] = implode('|', array(
                            'code:' . $coupon_name,
                            'description:' . ( is_object($coupon_post) ? $coupon_post->post_excerpt : '' ),
                            'amount:' . wc_format_decimal($coupon_amount, 2),
                                )
                        );
                    }

                    if (!empty($coupon_items)) {
                        $value = implode(';', $coupon_items);
                    } else {
                        $value = '';
                    }
                    break;
                case 'download_permissions':
                    $value = (WC()->version < '2.7') ? ($subscription->download_permissions_granted ? $subscription->download_permissions_granted : 0) :($subscription->is_download_permitted());
                    break;
                case 'shipping_method':
                    $shipping_lines = array();
                    foreach ($subscription->get_shipping_methods() as $shipping_item_id => $shipping_item) {
                        $shipping_lines[] = implode('|', array(
                            'method_id:' . $shipping_item['method_id'],
                            'method_title:' . $shipping_item['name'],
                            'total:' . wc_format_decimal($shipping_item['cost'], 2),
                        ));
                    }
                    if (!empty($shipping_lines)) {
                        $value = implode(';', $shipping_lines);
                    } else {
                        $value = '';
                    }
                    break;
                case 'fee_items':
                    $value = implode(';', $fee_items);
                    break;
                 case 'shipping_items':
                    $value = implode(';', $shipping_items);
                    break;
                case 'tax_items':
                    $tax_items = array();
                    foreach ($subscription->get_tax_totals() as $tax_code => $tax) {
                        $tax_items[] = implode('|', array(
                            'rate_id:' . $tax->rate_id,
                            'code:' . $tax->label,
                            'total:' . wc_format_decimal($tax->amount, 2),
                            'label:'.$tax->label,
                            'tax_rate_compound:'.$tax->is_compound,
                        ));
                    }
                    if (!empty($tax_items)) {
                        $value = implode(';', $tax_items);
                    } else {
                        $value = '';
                    }
                    break;
                default :
                    if(strstr($header_key, 'meta:')){
                        $value = maybe_serialize(get_post_meta((WC()->version < '2.7') ? $subscription->id : $subscription->get_id(), str_replace('meta:', '', $header_key),TRUE));
                    } else {
                        $value = '';
                    }
            }
            $csv_row[$header_key] = $value;
        }
        
//        $subscription_data = array();
//        foreach ($csv_columns as $header_key => $_) {
//
//            // Strict string comparison, as values like '0' are valid
//            $value = ( '' !== $csv_row[$header_key] ) ? $csv_row[$header_key] : '';
//            $subscription_data[$header_key] = $value;
//        }

        
        $subscription_data = array();
        foreach ($csv_columns as $key => $value) {
            if (!$csv_row || array_key_exists($key, $csv_row)) {
                $subscription_data[$key] = $csv_row[$key];
            } 
        }
        
        if($this->exclude_line_items){
            return apply_filters('hf_alter_subscription_data', $subscription_data, $csv_columns, $csv_columns,  array('max_line_items' => 0));
        }
      
        $li = 1;
        foreach ($line_items as $line_item) {
            foreach ($line_item as $name => $value) {
                $line_item[$name] = $name . ':' . $value;
            }
            $line_item = implode(apply_filters('wt_change_item_separator', '|'), $line_item);
            $subscription_data["line_item_{$li}"] = $line_item;
            $li++;
        }
                
        $max_line_items = $this->line_items_max_count;
        for ($i = 1; $i <= $max_line_items; $i++) {
            $subscription_data["line_item_{$i}"] = !empty($subscription_data["line_item_{$i}"]) ? self::format_data($subscription_data["line_item_{$i}"]) : '';
        }                       
        if ($this->export_to_separate_columns) {
            $line_item_values = self::get_all_metakeys_and_values($subscription);
            $this->line_item_meta = self::get_all_line_item_metakeys();
            $max_line_items = $this->line_items_max_count; 
            
            for ($i = 1; $i <= $max_line_items; $i++) {
                $line_item_array = explode(apply_filters('wt_change_item_separator', '|'), $subscription_data["line_item_{$i}"]);                 
                foreach ($this->line_item_meta as $meta_val) {
                    $subscription_data["line_item_{$i}_product_id"] = !empty($line_item_array[0]) ? substr($line_item_array[0], strpos($line_item_array[0], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_name"] = !empty($line_item_array[1]) ? substr($line_item_array[1], strpos($line_item_array[1], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_sku"] = !empty($line_item_array[2]) ? substr($line_item_array[2], strpos($line_item_array[2], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_quantity"] = !empty($line_item_array[3]) ? substr($line_item_array[3], strpos($line_item_array[3], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_total"] = !empty($line_item_array[4]) ? substr($line_item_array[4], strpos($line_item_array[4], ':') + 1) : '';
                    $subscription_data["line_item_{$i}_subtotal"] = !empty($line_item_array[5]) ? substr($line_item_array[5], strpos($line_item_array[5], ':') + 1) : '';
                    if (in_array($meta_val, array("_product_id", "_qty", "_variation_id", "_line_total", "_line_subtotal", "_tax_class", "_line_tax", "_line_tax_data", "_line_subtotal_tax"))) {
                        continue;
                    } else {
                        $subscription_data["line_item_{$i}_$meta_val"] = !empty($line_item_values[$i][$meta_val]) ? $line_item_values[$i][$meta_val] : '';
                    }
                }
            }
        }                         

        $order_data_filter_args = array('max_line_items' => $max_line_items);

        if ($this->export_to_separate_rows) {
            $subscription_data = $this->wt_line_item_separate_row_csv_data($subscription, $subscription_data, $order_data_filter_args);
        } 
 
        
//        return apply_filters('hf_alter_subscription_data', $data, $export_columns, $csv_columns);
        return apply_filters('hf_alter_subscription_data', $subscription_data, $csv_columns, $csv_columns);
    }            
    
    public static function hf_sanitize_subscription_status_keys($status_key) {
        if (!is_string($status_key) || empty($status_key)) {
            return '';
        }
        $status_key = ( 'wc-' === substr($status_key, 0, 3) ) ? $status_key : sprintf('wc-%s', $status_key);
        return $status_key;
    }
    
    public static function get_all_line_item_metakeys() {
        global $wpdb;
        $filter_meta = apply_filters('wt_subscription_export_select_line_item_meta', array());
        $filter_meta = !empty($filter_meta) ? implode("','", $filter_meta) : '';
        $query = "SELECT DISTINCT om.meta_key
            FROM {$wpdb->prefix}woocommerce_order_itemmeta AS om 
            INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON om.order_item_id = oi.order_item_id
            WHERE oi.order_item_type = 'line_item'";
        if (!empty($filter_meta)) {
            $query .= " AND om.meta_key IN ('" . $filter_meta . "')";
        }
        $meta_keys = $wpdb->get_col($query);
        return $meta_keys;
    }
    
    public static function get_order_line_item_meta($item_id){
        global $wpdb;
        $filtered_meta = apply_filters('wt_subscription_export_select_line_item_meta',array());
        $filtered_meta = !empty($filtered_meta) ? implode("','",$filtered_meta) : '';
        $query = "SELECT meta_key,meta_value
            FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = '$item_id'";
        if(!empty($filtered_meta)){
            $query .= " AND meta_key IN ('".$filtered_meta."')";
        }
        $meta_keys = $wpdb->get_results($query , OBJECT_K );
        return $meta_keys;
    }
    
    public static function wt_get_subscription_of_coupons($coupons, $subscription_type='shop_subscription'){
         global $wpdb;
 


        if(self::is_hpos_enabled()){
            $query = "SELECT DISTINCT po.ID FROM {$wpdb->prefix}wc_orders AS po
            LEFT JOIN {$wpdb->prefix}wc_orders_meta AS pm ON pm.post_id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS om ON om.order_item_id = oi.order_item_id
            WHERE po.type = '$subscription_type'
            AND oi.order_item_type = 'coupon'
            AND oi.order_item_name IN ('". implode("','", $coupons) ."')";
        }else{
            $query = "SELECT DISTINCT po.ID FROM {$wpdb->posts} AS po
            LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_id = po.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS om ON om.order_item_id = oi.order_item_id
            WHERE po.post_type = '$subscription_type'
            AND oi.order_item_type = 'coupon'
            AND oi.order_item_name IN ('". implode("','", $coupons) ."')";
        }
        $subscription_ids = $wpdb->get_col($query);
        return $subscription_ids;
        
    }
    
    public static function get_order_notes($order) {
        $callback = array('WC_Comments', 'exclude_order_comments');
        $args = array(
            'post_id' => (WC()->version < '2.7.0') ? $order->id : $order->get_id(),
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

    public static function get_order_notes_new($order) {
        $notes = wc_get_order_notes(array('order_id' => $order->get_id(), 'order_by' => 'date_created', 'order' => 'ASC'));
        $order_notes = array();
        foreach ($notes as $note) {
            $order_notes[] = implode('|', array(
                'content:' . str_replace(array("\r", "\n"), ' ', $note->content),
                'date:' . $note->date_created->date('Y-m-d H:i:s'),
                'customer:' . $note->customer_note,
                'added_by:' . $note->added_by
            ));
        }
        return $order_notes;
    }
    
    public static function get_all_metakeys_and_values($order = null) {
        $in = 1;
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
    
    public static function format_data($data) {
        if (!is_array($data))
            ;
        $data = (string) urldecode($data);
//        $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);        
        $use_mb = function_exists('mb_detect_encoding');
        $enc = '';
        if ($use_mb) {
            $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);
        }
        $data = ( $enc == 'UTF-8' ) ? $data : utf8_encode($data);

        return $data;
    }
    
    public static function highest_line_item_count($line_item_keys) {
   
        $all_items  = array_count_values(array_column($line_item_keys, 'order_id'));
        return max($all_items);
        
    }
    
    public static function get_max_line_items($order_ids) {
        
        global $wpdb;
        $query_line_items = "select p.order_id, p.order_item_type from {$wpdb->prefix}woocommerce_order_items as p where order_item_type ='line_item' and p.order_item_id = p.order_item_id";
        $line_item_keys = $wpdb->get_results($query_line_items, ARRAY_A);                
        $max_line_items = self::highest_line_item_count($line_item_keys);
        return $max_line_items;        
    }

    public static function is_hpos_enabled() {
		$is_hpos_enabled = false;
        $hpos_data = Wt_Import_Export_For_Woo_Common_Helper::is_hpos_enabled();
	
        if( strpos($hpos_data['table_name'],'wc_orders') !== false ||  $hpos_data['sync']){
            $is_hpos_enabled = true;
        }
        return $is_hpos_enabled;
    }




}
