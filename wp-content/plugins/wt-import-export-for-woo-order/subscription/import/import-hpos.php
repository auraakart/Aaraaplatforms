<?php

if (!defined('WPINC')) {
    exit;
}

if(!class_exists('Wt_Import_Export_For_Woo_Subscription_Import_Hpos')){
    class Wt_Import_Export_For_Woo_Subscription_Import_Hpos{
    public $parent_object;

    public function __construct($parent_object){
        $this->parent_object = $parent_object;
    }


    /**
     * Create new posts based on import information
     */
    public function process_subscription_orders($data){
        try{
            do_action('wt_subscription_order_import_before_process_item', $data);
            global $wpdb;
            $merge = !empty($data['merging']);
            $is_order_exist = $this->parent_object->is_order_exist;
            

            $add_memberships = ( isset($_POST['add_memberships']) ) ? sanitize_text_field($_POST['add_memberships']) : FALSE;
            $this->parent_object->hf_log_data_change('hf-subscription-csv-import', __('Process start..'));
            $this->parent_object->hf_log_data_change('hf-subscription-csv-import', __('Processing subscriptions...'));
            $email_customer = false; // set this as settings for choosing weather to mail details for newly created customers.
            $user_id = $this->parent_object->hf_check_customer($data, $email_customer);
            if (is_wp_error($user_id)) {
                $this->parent_object->hf_log_data_change('hf-subscription-csv-import', sprintf(__($user_id->get_error_message())));
                unset($data);
                return new WP_Error( 'parse-error',  sprintf(__($user_id->get_error_message())) );
            } elseif (empty($user_id)) {
                unset($data);
                return new WP_Error( 'parse-error',  __('An error occurred with the customer information provided.') );
            }
            $data['customer_id']=$user_id;
            $new_added = false;
            if ((!empty($data['post_parent'])) && $this->parent_object->link_wt_import_key) { //Check whether post_parent (Parent order ID) is an order or not
                $data['post_parent'] = self::wt_get_order_with_import_key($data['post_parent']);
            }else{ //Check whether post_parent (Parent order ID) is an order or not
                $temp_parent_order_exist = wc_get_order($data['post_parent']);
                $data['post_parent'] = ( $temp_parent_order_exist && $temp_parent_order_exist->get_type() == 'shop_order' ) ? $data['post_parent'] : '';
            }


            
            
            if ($is_order_exist) {   //Execute this when subscription already exist 
                $subscription_data['ID'] = $data['subscription_id'];         
                if(empty($data['start_date'])){                    
                    $data['start_date'] = get_post($data['subscription_id'])->post_date;
                }
                if(class_exists('HF_Subscription')){
                    $subscription = $this->hf_create_subscription($data, TRUE);
                }else{
                    
                    $subscription = $this->wc_create_subscription($data, TRUE);
                 

                }  
                
                $new_added = false;
                if (is_wp_error($subscription)) {
                    $new_added = false;
                    $order_number = $data['order_number'];
                    unset($data);
                    return new WP_Error( 'parse-error',  sprintf(__('> Error inserting %s: %s'), $order_number, $subscription->get_error_message()) );
                }
            } else {
                if(class_exists('HF_Subscription')){
                    $subscription = $this->hf_create_subscription($data);
                }else{
                   
                    $subscription = $this->wc_create_subscription($data);
                } 
                $new_added = true;
                if (is_wp_error($subscription)) {
                    $new_added = false;
                    unset($data);
                    return new WP_Error( 'parse-error',  __($subscription->get_error_message()) );
                }
            }


            $billing_address=array(
                'first_name' =>$data['billing_and_shipping_addr']['billing_first_name'],
                'last_name' =>$data['billing_and_shipping_addr']['billing_last_name'],
                'company' =>$data['billing_and_shipping_addr']['billing_company'],
                'address_1' =>$data['billing_and_shipping_addr']['billing_address_1'],
                'address_2' =>$data['billing_and_shipping_addr']['billing_address_2'],
                'city' =>$data['billing_and_shipping_addr']['billing_city'],
                'state' =>$data['billing_and_shipping_addr']['billing_state'],
                'postcode' =>$data['billing_and_shipping_addr']['billing_postcode'],
                'country' =>$data['billing_and_shipping_addr']['billing_country'],
                'email' =>$data['billing_and_shipping_addr']['billing_email'],
                'phone' =>$data['billing_and_shipping_addr']['billing_phone'],
            );
            $subscription->set_billing_address($billing_address);
            $shipping_address=array(
                'first_name' =>$data['billing_and_shipping_addr']['shipping_first_name'],
                'last_name' =>$data['billing_and_shipping_addr']['shipping_last_name'],
                'company' =>$data['billing_and_shipping_addr']['shipping_company'],
                'address_1' =>$data['billing_and_shipping_addr']['shipping_address_1'],
                'address_2' =>$data['billing_and_shipping_addr']['shipping_address_2'],
                'city' =>$data['billing_and_shipping_addr']['shipping_city'],
                'state' =>$data['billing_and_shipping_addr']['shipping_state'],
                'postcode' =>$data['billing_and_shipping_addr']['shipping_postcode'],
                'country' =>$data['billing_and_shipping_addr']['shipping_country'],
                'phone' =>$data['billing_and_shipping_addr']['shipping_phone'],
            );
            $subscription->set_shipping_address($shipping_address);
            $subscription->set_meta_data('_billing_address_index', implode(' ',  $subscription->get_address('billing')));
            $subscription->update_meta_data('_shipping_address_index', implode(' ', $subscription->get_address('shipping')));
			$current_order_ids = array();
            if (!empty($data['renewal_orders'])) {
                $renewal_orders = explode('|', $data['renewal_orders']);
                if($this->parent_object->link_wt_import_key){
                    foreach ($renewal_orders as $order_id) {
                        $current_order_ids[] = self::wt_get_order_with_import_key($order_id);                
                    }
                } else {
                    foreach ($renewal_orders as $order_id){
                        $order = WC()->order_factory->get_order( $order_id );
                        if(is_object($order)){
                            $current_order_ids[] = $order_id;
                        }
                    }
                }
                $current_order_ids = array_filter($current_order_ids);
                if(!empty($current_order_ids) && !class_exists('HF_Subscription')){
                    update_option('_transient_wcs-related-orders-to-'.( (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id), $current_order_ids);
                    foreach ($current_order_ids as $id){
                        $order = wc_get_order($id);
                        $order->update_meta_data( '_subscription_renewal', (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id );
                        $order->save();
                    }
                } else {
                    foreach ($current_order_ids as $id){
                        $order = wc_get_order($id);
                        $order->update_meta_data( '_subscription_renewal', (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id );
                        $order->save();
                    }
                }
            }

            if(!empty($data['payment_token_data'])){
                $subscription_id = (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id;

                $payment_token_data = array(
                    'gateway_id' => $data['payment_token_data'][0]->gateway_id,
                    'token'	=> $data['payment_token_data'][0]-> token,
                    'user_id' => $subscription->get_user_id(),
                    'type' => $data['payment_token_data'][0]->type,
                    'is_default' => $data['payment_token_data'][0]->is_default,
                );
                $wpdb->insert($wpdb->prefix . 'woocommerce_payment_tokens', $payment_token_data);
                $token_id = $wpdb->insert_id;
                $subscription->get_data_store()->update_payment_token_ids( $subscription,array($token_id) );               
                if( $token_id &&  !empty($data['payment_token_meta_data'])){
                    foreach($data['payment_token_meta_data'] as $p_key => $p_value){
                        $payment_token_metadata = array(
                            'payment_token_id' => $token_id,
                            'meta_key' => $p_key,
                            'meta_value' => $p_value[0],
                        );
                        $wpdb->insert($wpdb->prefix . 'woocommerce_payment_tokenmeta', $payment_token_metadata);
                    }
                }
            } 
            foreach ($data['post_meta'] as $meta_data) {
                switch ($meta_data['key']){
                    case '_coupon_items':
                    case '_order_currency';
                        break;
                    case '_download_permissions':
                        $add_download_permissions = TRUE;
                        $data['download_permissions'] = $meta_data['value'];
                        $subscription->update_meta_data('_download_permissions_granted', $meta_data['value']);
                        break;
                    case '_requires_manual_renewal';
						if($meta_data['value'] == 0){
							$meta_data['value'] = false;
						}
                        $subscription->set_requires_manual_renewal($meta_data['value']);
						break;
                    default:
					$meta_function = 'set_' . ltrim($meta_data['key'], '_' );
                    if (is_callable(array($subscription, $meta_function))) {
                        $subscription->{$meta_function}($meta_data['value']);
                    }else{
                        $subscription->update_meta_data($meta_data['key'], $meta_data['value']);
                    }
                }
            }
            $order_operational_data = array();
            foreach ( $data['post_meta'] as $meta_data ) {
                switch ( $meta_data['key'] ) {
                    case '_order_shipping_tax':
                        $order_operational_data['shipping_tax_amount'] = $meta_data['value'];
                        break;
                    case '_order_shipping':
                        $order_operational_data['shipping_total_amount'] = $meta_data['value'];
                        break;
                    case '_cart_discount_tax':
                        $order_operational_data['discount_tax_amount'] = $meta_data['value'];
                        break;
                    case '_cart_discount':
                        $order_operational_data['discount_total_amount'] = $meta_data['value'];
                        break;
                    default:
                        break;
                }
            }
            if ( ! empty( $order_operational_data ) ) {
                $operational_data = array();
                $operational_data[] = $order_operational_data['shipping_tax_amount'] ? $order_operational_data['shipping_tax_amount'] : 0;
                $operational_data[] = $order_operational_data['shipping_total_amount'] ? $order_operational_data['shipping_total_amount'] : 0;
                $operational_data[] = $order_operational_data['discount_tax_amount'] ? $order_operational_data['discount_tax_amount'] : 0;
                $operational_data[] = $order_operational_data['discount_total_amount'] ? $order_operational_data['discount_total_amount'] : 0;
                $operational_data['order_id'] = ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id;
                $wpdb->query( $wpdb->prepare( " UPDATE {$wpdb->prefix}wc_order_operational_data SET shipping_tax_amount= %f,shipping_total_amount=%f,discount_tax_amount=%f,discount_total_amount=%f WHERE order_id=%d", $operational_data ) );
            }

            try {
                $subscription->update_dates($data['dates_to_update']);
				if(isset($data['subscription_status']))	{
                    $subscription->update_status($data['subscription_status']);
                }
            } catch (Exception $e) {
                unset($data);
                return new WP_Error( 'parse-error',  __($e->getMessage()) );
            }

            $result['items'] = isset($result['items']) ? $result['items'] : '';
            if (!empty($data['order_items'])) {
                $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items ON itemmeta.order_item_id = items.order_item_id WHERE items.order_id = %d and items.order_item_type = 'line_item'", (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id));
                if (is_numeric($data['order_items'])) {
                    $product_id = absint($data['order_items']);
                    $result['items'] = $this->parent_object::add_product($data, $subscription, array('product_id' => $product_id) ,$this->parent_object->link_product_using_sku);
                    if ($add_memberships) {
                        $this->parent_object::maybe_add_memberships($user_id, ( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id), $product_id);
                    }
                } else {
                    foreach ($data['order_items'] as $order_item) {
                        $result['items'] .= $this->parent_object::add_product($data, $subscription, $order_item, $this->parent_object->link_product_using_sku) . '<br/>';

                        if ($add_memberships) {
                            $this->parent_object::maybe_add_memberships($user_id, ( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id), $item_data['product_id']);
                        }
                    }
                }
            }

            if(!empty($data['shipping_method'])){
                $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items ON itemmeta.order_item_id = items.order_item_id WHERE items.order_id = %d and items.order_item_type = 'shipping'", (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id));
                $shipping_item = explode('|', $data['shipping_method']);
                $method_id = array_shift($shipping_item);
                $method_id = substr($method_id, strpos($method_id, ":") + 1);
                $method_title = array_shift($shipping_item);
                $method_title = substr($method_title, strpos($method_title, ":") + 1);
                $total = array_shift($shipping_item);
                $total = substr($total, strpos($total, ":") + 1);
                $total_tax     = array_shift( $shipping_item );
				$total_tax     = substr( $total_tax, ( strpos( $total_tax, ':' ) + 1 ) );
				$taxes         = array_shift( $shipping_item );
				$taxes         = substr( $taxes, ( strpos( $taxes, ':' ) + 1 ) );
				$tax_data = maybe_unserialize($taxes);
				if(isset($tax_data['total'])){
					$new_tax_data = array();
					foreach($tax_data['total'] as $t_key => $t_value){
						if(isset($data['tax_items'][$t_key])){
							$new_tax_data ['total'][$data['tax_items'][$t_key]['rate_id']] = $t_value ;
						}else{
							$new_tax_data ['total'][$t_key] = $t_value;
						}
					}
				}else{
					$new_tax_data = $tax_data;
				}
                $shipping_order_item = array(
                    'order_item_name' => ($method_title) ? $method_title : $method_id,
                    'order_item_type' => 'shipping',
                );

                $shipping_order_item_id = wc_add_order_item((WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id, $shipping_order_item);

                if ($shipping_order_item_id) {
                    wc_add_order_item_meta($shipping_order_item_id, 'method_id', $method_id);
                    wc_add_order_item_meta($shipping_order_item_id, 'cost', $total);
                    wc_add_order_item_meta( $shipping_order_item_id, 'total_tax', $total_tax );
					wc_add_order_item_meta( $shipping_order_item_id, 'taxes',  maybe_serialize($new_tax_data) );
                }
            }

            if(!empty($data['shipping_items']) && !empty($data['shipping_method'])){
                foreach ($data['shipping_items'] as $shipping_item){
                    if ($shipping_order_item_id) {
                        wc_add_order_item_meta($shipping_order_item_id,$shipping_item['item'], $shipping_item['value']);
                    }
                    else {
                        $shipping_order_item_id = wc_add_order_item((WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id, $shipping_order_item);
                        wc_add_order_item_meta($shipping_order_item_id,$shipping_item['item'], $shipping_item['value']);
                    }
                }
            }

            if(!empty($data['fee_items'])){
                $fee_str = 'fee';
                $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items WHERE itemmeta.order_item_id = items.order_item_id and items.order_id = %d and items.order_item_type = %s", (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id, $fee_str));
                $fee_items = explode(';', $data['fee_items']);
                foreach ($fee_items as $item){
                    $fee_item = explode('|', $item);
                    $name = array_shift($fee_item);
                    $name = substr($name, strpos($name, ":") + 1);
                    $total = array_shift($fee_item);
                    $total = substr($total, strpos($total, ":") + 1);
                    $tax = array_shift($fee_item);
                    $tax = substr($tax, strpos($tax, ":") + 1);
                    $tax_data       = array_shift( $fee_item );
					$tax_data       = substr( $tax_data, ( strpos( $tax_data, ':' ) + 1 ) );
                    $tax_class = array_shift($fee_item);
                    $tax_class = substr($tax_class, strpos($tax_class, ":") + 1);
                    $tax_data = maybe_unserialize($tax_data);
					if(isset($tax_data['total'])){
						foreach($tax_data['total'] as $t_key => $t_value){
							if(isset($data['tax_items'][$t_key])){
								$new_tax_data ['total'][$data['tax_items'][$t_key]['rate_id']] = $t_value ;
							}else{
								$new_tax_data ['total'][$t_key] = $t_value;
							}
						}
					}else{
						$new_tax_data = $tax_data;
					}
					$tax_data = maybe_serialize($new_tax_data);
                    $fee_order_item = array(
                        'order_item_name' => $name ? $name : '',
                        'order_item_type' => 'fee',
                    );
                    $fee_order_item_id = wc_add_order_item((WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id, $fee_order_item);
                    if($fee_order_item_id){
                        wc_add_order_item_meta($fee_order_item_id, '_line_total', $total);
                        wc_add_order_item_meta($fee_order_item_id, '_line_tax', $tax);
                        wc_add_order_item_meta( $fee_order_item_id, '_line_tax_data', $tax_data );
                        wc_add_order_item_meta($fee_order_item_id, '_tax_class', $tax_class);
                    }
                }
            }

            $chosen_tax_rate_id = 0;
            if (!empty($data['tax_items'])) {
                $tax_str = 'tax';
                $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items WHERE itemmeta.order_item_id = items.order_item_id and items.order_id = %d and items.order_item_type = %s", (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id, $tax_str));
                foreach ($data['tax_items'] as $tax_item) {
                    $tax_order_item = array(
                        'order_item_name' => $tax_item['title'],
                        'order_item_type' => "tax",
                    );
                    $tax_order_item_id = wc_add_order_item((WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id, $tax_order_item);
                    if ($tax_order_item_id) {
                        wc_add_order_item_meta($tax_order_item_id, 'rate_id', $tax_item['rate_id']);
                        wc_add_order_item_meta($tax_order_item_id, 'label', $tax_item['label']);
                        wc_add_order_item_meta($tax_order_item_id, 'compound', $tax_item['compound']);
                        wc_add_order_item_meta($tax_order_item_id, 'tax_amount', $tax_item['tax_amount']);
                        wc_add_order_item_meta($tax_order_item_id, 'shipping_tax_amount', $tax_item['shipping_tax_amount']);
						
                    }
                }
            }

            if (!empty($data['coupon_items'])) {
                if ($merge && $is_order_exist) {
                    $applied_coupons = $subscription->get_used_coupons();
                    if (!empty($applied_coupons)) {
                        foreach ($applied_coupons as $coupon) {
                            $subscription->remove_coupon($coupon);
                        }
                    }
                }
                $this->parent_object::add_coupons($subscription, $data);
            }
            // add order notes
            if(!empty($data['order_notes'])){
                add_filter('woocommerce_email_enabled_customer_note', '__return_false');
                $wpdb->query($wpdb->prepare("DELETE comments,meta FROM {$wpdb->prefix}comments comments LEFT JOIN {$wpdb->prefix}commentmeta meta ON comments.comment_ID = meta.comment_id WHERE comments.comment_post_ID = %d",(WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id));
                foreach ($data['order_notes'] as $order_note) {
                    $note = explode('|', $order_note);
                    $con = array_shift($note);
                    $con = substr($con, strpos($con, ":") + 1);
                    $date = array_shift($note);
                    $date = substr($date, strpos($date, ":") + 1);
                    $cus = array_shift($note);
                    $cus = substr($cus, strpos($cus, ":") + 1);
                    $system = array_shift($note);
                    $added_by = substr($system, strpos($system, ":") + 1);
                    if($added_by == 'system'){
                        $added_by_user = FALSE;
                    }else{
                        $added_by_user = TRUE;
                    }
                    if($cus == '1'){
                        $comment_id = $subscription->add_order_note($con,1,1);
                    } else {
                        $comment_id = $subscription->add_order_note($con,0,$added_by_user);
                    }
                    wp_update_comment(array('comment_ID' => $comment_id,'comment_date' => $date));
                }
            }
            
            // only show the following warnings on the import when the subscription requires shipping
            if (!$this->parent_object::$all_virtual) {
                if (!empty($missing_shipping_addresses)) {
                    $result['warning'][] = esc_html__('The following shipping address fields have been left empty: ' . rtrim(implode(', ', $missing_shipping_addresses), ',') . '. ');
                }
                if (!empty($missing_billing_addresses)) {
                    $result['warning'][] = esc_html__('The following billing address fields have been left empty: ' . rtrim(implode(', ', $missing_billing_addresses), ',') . '. ');
                }
                if (empty($shipping_method)) {
                    $result['warning'][] = esc_html__('Shipping method and title for the subscription have been left as empty. ');
                }
            }
            if ($merge && !$new_added)
                $out_msg = 'Subscription updated successfully';
            else
                $out_msg = 'Subscription Imported Successfully.';
            $this->parent_object->hf_log_data_change('hf-subscription-csv-import', sprintf(__('> &#8220;%s&#8221;' . $out_msg), esc_html($data['subscription_id'])), true);
            $this->parent_object->hf_log_data_change('hf-subscription-csv-import', sprintf(__('> Finished importing order %s'), $data['subscription_id']));
            $this->parent_object->hf_log_data_change('hf-subscription-csv-import', __('Finished processing orders.'));
			$data['is_subscription_exist'] = $is_order_exist;
			/**
			 * Filter the query arguments for a request.
			 *
			 * Enables adding extra arguments or setting defaults for the request.
			 *
			 * @since 1.1.1
			 *
			 * @param object $subscription    Subscription object.
			 * @param array  $data   Subscription created.
			 */
			do_action( 'wt_woocommerce_subscription_import_inserted_subscription_object', $subscription, $data );					
			
			unset($data);
            
            if($this->parent_object->delete_existing){
                $subscription->set_meta_data('_wt_delete_existing', 1);
            }
            $subscription->save();
            return array('id' => ($subscription->get_id()),
                        'order_exist' => $is_order_exist ) ;
            
        } catch (Exception $e) {
            return new WP_Error('woocommerce_product_importer_error', $e->getMessage(), array('status' => $e->getCode()));
        }
    
        }
        public static function wt_get_order_with_import_key($id){
            global $wpdb;
            
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT po.id FROM {$wpdb->prefix}wc_orders AS po
                INNER JOIN {$wpdb->prefix}wc_orders_meta AS pm
                ON po.id = pm.order_id
                WHERE po.type = 'shop_order'
                AND pm.meta_key = '_wt_import_key'
                AND pm.meta_value = %d",$id
            ));
            return $order_id;
        }


           /**
     * Create a new subscription
     *
     * Returns a new WC_Subscription object on success which can then be used to add additional data.
     *
     * @return WC_Subscription | WP_Error A WC_Subscription on success or WP_Error object on failure
     */
        public function wc_create_subscription($args = array(),$subscription_exist = false){
            $this->data_validation($args ,$subscription_exist);
            $subscription_status = $args['subscription_status'];
            $subscription_currency = $args['currency'];
            $subscription_total_amount = $args['order_total'];
            $subscription_customer_id = $args['customer_id'];
            $subscription_billing_email = $args['billing_email'];
            $subscription_date_created_gmt = $args['start_date'];
            $subscription_parent_order_id = $args['post_parent'];
            $subscription_payment_method = $args['payment_method']; 
            $subscription_payment_method_title = $args['payment_method_title'];
            $subscription_customer_note = $args['customer_note'];
            $subscription_billing_interval = $args['billing_interval'];
            $subscription_billing_period = $args['billing_period'];
            if($subscription_exist){
                $subscription_id = $args['subscription_id'];
                $subscription = new WC_Subscription($subscription_id); 
            }elseif(isset($args['subscription_id']) && !empty($args['subscription_id']) && is_numeric($args['subscription_id']) && $args['subscription_id']>0 ){
                global $wpdb;
                $import_id = $args['subscription_id'];
                $current_date = current_time('Y-m-d H:i:s');
                $sql_order_table = "INSERT INTO {$wpdb->prefix}wc_orders (id, status, date_created_gmt, type) VALUES (%d, 'pending', %s, 'shop_subscription');";
                $result_order_table = $wpdb->query($wpdb->prepare($sql_order_table, $import_id, $current_date));
                $sql_post_table = "INSERT INTO {$wpdb->posts} (ID, post_status, post_date, post_date_gmt, post_type) VALUES (%d, 'pending', %s, %s, 'shop_subscription');";
                $result_post_table = $wpdb->query($wpdb->prepare($sql_post_table, $import_id, $current_date, $current_date));
                if( $result_order_table && $result_post_table ){
                    $subscription = new WC_Subscription( $import_id );
                }else{
                    $subscription = new WC_Subscription();        
                }    
            }else{
                    $subscription = new WC_Subscription();        
            }
           $subscription->set_status($subscription_status);
           $subscription->set_currency($subscription_currency);
           $subscription->set_total( $subscription_total_amount );
           $subscription->set_customer_id( $subscription_customer_id );
           $subscription->set_billing_email( $subscription_billing_email );
           $subscription->set_date_created( $subscription_date_created_gmt);
           $subscription->set_parent_id($subscription_parent_order_id);
           $subscription->set_payment_method($subscription_payment_method);
           $subscription->set_payment_method_title($subscription_payment_method_title);
           $subscription->set_customer_note($subscription_customer_note);
           $subscription->set_billing_interval($subscription_billing_interval);
           $subscription->set_billing_period ( $subscription_billing_period);
           $subscription_id = $subscription->save();
           return  new WC_Subscription($subscription_id);
        }

        public function data_validation($args = array(),$subscription_exist = false){

            // validate the start_date field
            if (!is_string($args['start_date']) || false === $this->parent_object->hf_is_datetime_mysql_format($args['start_date'])) {
                if(!$subscription_exist){
                    return new WP_Error('woocommerce_subscription_invalid_start_date_format', _x('Invalid date. The date must be a string and of the format: "Y-m-d H:i:s".', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
                }
                
            } else if (strtotime($args['start_date']) > current_time('timestamp', true)) {
                if(!$subscription_exist){
                    return new WP_Error('woocommerce_subscription_invalid_start_date', _x('Subscription start date must be before current day.', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
                }
            }
            // check customer id is set
            if (empty($args['customer_id']) || !is_numeric($args['customer_id']) || $args['customer_id'] <= 0) {
                if(!$subscription_exist){
                    return new WP_Error('woocommerce_subscription_invalid_customer_id', _x('Invalid subscription customer_id.', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
                }
            }
            // check the billing period
            if (empty($args['billing_period']) || !in_array(strtolower($args['billing_period']), array_keys($this->parent_object->hf_get_subscription_period_strings()))) {
                if(!$subscription_exist){
                    return new WP_Error('woocommerce_subscription_invalid_billing_period', __('Invalid subscription billing period given.', 'woocommerce-subscriptions'));
                }
            }
            // check the billing interval
            if (empty($args['billing_interval']) || !is_numeric($args['billing_interval']) || absint($args['billing_interval']) <= 0) {
                if(!$subscription_exist){
                    return new WP_Error('woocommerce_subscription_invalid_billing_interval', __('Invalid subscription billing interval given. Must be an integer greater than 0.', 'woocommerce-subscriptions'));
                }
            }
        }
    }
}