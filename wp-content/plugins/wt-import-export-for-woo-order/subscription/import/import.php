<?php

if (!defined('WPINC')) {
    exit;
}
use Automattic\WooCommerce\Utilities\OrderUtil;


class Wt_Import_Export_For_Woo_Subscription_Import {

    public $post_type = 'shop_subscription';
    public $parent_module = null;
    public $parsed_data = array();
    public $import_columns = array();
    public $merge;
    public $skip_new;
    public $merge_empty_cells;
    public $delete_existing;
    public $id_conflict;
    public $order_meta_fields;
    
    public $link_wt_import_key;
    public $link_product_using_sku;

    // Results
    var $import_results = array();
    public $is_order_exist = false;
    
    
    public static $membership_plans = null;
    public static $all_virtual = true;
    public static $user_meta_fields = array(
        '_billing_first_name', // Billing Address Info
        '_billing_last_name',
        '_billing_company',
        '_billing_address_1',
        '_billing_address_2',
        '_billing_city',
        '_billing_state',
        '_billing_postcode',
        '_billing_country',
        '_billing_email',
        '_billing_phone',
        '_shipping_first_name', // Shipping Address Info
        '_shipping_last_name',
        '_shipping_company',
        '_shipping_phone',        
        '_shipping_address_1',
        '_shipping_address_2',
        '_shipping_city',
        '_shipping_state',
        '_shipping_postcode',
        '_shipping_country',
    );

    public function __construct($parent_object) {
        $this->parent_module = $parent_object;
        $this->order_meta_fields = array(
            'subscription_status',
            'billing_period',
            'billing_interval',
            'order_shipping',
            'order_shipping_tax',
            'order_tax',
            'cart_discount',
            'cart_discount_tax',
            'order_total',
            'order_currency',
            'payment_method',
            'payment_method_title',
            'billing_first_name',
            'billing_last_name',
            'billing_email',
            'billing_phone',
            'billing_address_1',
            'billing_address_2',
            'billing_postcode',
            'billing_city',
            'billing_state',
            'billing_country',
            'billing_company',
            'shipping_first_name',
            'shipping_last_name',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_postcode',
            'shipping_city',
            'shipping_state',
            'shipping_country',
            'shipping_company',
            'shipping_phone',            
            'download_permissions'
        );
    }

    /* WC object based import  */

    public function prepare_data_to_import($import_data, $form_data, $batch_offset, $is_last_batch) {

        $this->merge = isset($form_data['advanced_form_data']['wt_iew_merge']) ? $form_data['advanced_form_data']['wt_iew_merge'] : 0;  //wt_iew_wtcreateuser  wt_iew_status_mail wt_iew_ord_link_using_sku
        $this->skip_new = isset($form_data['advanced_form_data']['wt_iew_skip_new']) ? $form_data['advanced_form_data']['wt_iew_skip_new'] : 0;
        $this->id_conflict = !empty($form_data['advanced_form_data']['wt_iew_id_conflict']) ? $form_data['advanced_form_data']['wt_iew_id_conflict'] : 'skip'; 
        $this->delete_existing = isset($form_data['advanced_form_data']['wt_iew_delete_existing']) ? $form_data['advanced_form_data']['wt_iew_delete_existing'] : 0;
        
        $this->link_wt_import_key = isset($form_data['advanced_form_data']['wt_iew_link_wt_import_key']) ? $form_data['advanced_form_data']['wt_iew_link_wt_import_key'] : 0;        
        $this->link_product_using_sku = isset($form_data['advanced_form_data']['wt_iew_link_product_using_sku']) ? $form_data['advanced_form_data']['wt_iew_link_product_using_sku'] : 0;

        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
        wp_suspend_cache_invalidation(true);
        
        Wt_Import_Export_For_Woo_Logwriter::write_log($this->parent_module->module_base, 'import', "Preparing for import.");
        
        $success = 0;
        $failed = 0;
        $msg = 'Subscription order imported successfully.';  
        foreach ($import_data as $key => $data) {
            $row = $batch_offset+$key+1;
            Wt_Import_Export_For_Woo_Logwriter::write_log($this->parent_module->module_base, 'import', "Row :$row - Parsing item.");
                $parsed_data = $this->parse_subscription_orders($data, $this->merge);
            if (!is_wp_error($parsed_data)){   
                Wt_Import_Export_For_Woo_Logwriter::write_log($this->parent_module->module_base, 'import', "Row :$row - Processing item.");
                if($this->is_hpos_enabled() && class_exists('WC_Subscription')){
                    require plugin_dir_path( __FILE__ ).'import-hpos.php'; 
                    $hpos_class=new Wt_Import_Export_For_Woo_Subscription_Import_Hpos($this);
                    $result = $hpos_class->process_subscription_orders($parsed_data);

                }else{
                    $result =  $this->process_subscription_orders($parsed_data); 
                   
                }
                if(!is_wp_error($result)){
                    if($this->is_order_exist){
                        $msg = 'Subscription Order updated successfully.';
                    }
                   
                    $this->import_results[$row] = array('row'=>$row, 'message'=>$msg, 'status'=>true, 'status_msg' => __('Success', 'wt-import-export-for-woo'), 'post_id'=>$result['id'], 'post_link' => Wt_Import_Export_For_Woo_Subscription::get_item_link_by_id($result['id'])); 
                    Wt_Import_Export_For_Woo_Logwriter::write_log($this->parent_module->module_base, 'import', "Row :$row - ".$msg);
                    $success++;                     
                }else{
                    $this->import_results[$row] = array('row'=>$row, 'message'=>$result->get_error_message(), 'status'=>false, 'status_msg' => __('Failed/Skipped', 'wt-import-export-for-woo'), 'post_id'=>'', 'post_link' => array( 'title' => __( 'Untitled', 'wt-import-export-for-woo' ), 'edit_url' => false ) );
                    Wt_Import_Export_For_Woo_Logwriter::write_log($this->parent_module->module_base, 'import', "Row :$row - Processing failed. Reason: ".$result->get_error_message());
                    $failed++;
                } 
                                             
            }else{
                $this->import_results[$row] = array('row'=>$row, 'message'=>$parsed_data->get_error_message(), 'status'=>false, 'status_msg' => __('Failed/Skipped', 'wt-import-export-for-woo'), 'post_id'=>'', 'post_link' => array( 'title' => __( 'Untitled', 'wt-import-export-for-woo' ), 'edit_url' => false ) );
                Wt_Import_Export_For_Woo_Logwriter::write_log($this->parent_module->module_base, 'import', "Row :$row - Parsing failed. Reason: ".$parsed_data->get_error_message());
                $failed++; 

            }            
            unset($data, $parsed_data);
           
        }       
        wp_suspend_cache_invalidation(false);
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
        
        if($is_last_batch && $this->delete_existing){
            $this->delete_existing();                        
        }

        $import_response=array(
                'total_success'=>$success,
                'total_failed'=>$failed,
                'log_data'=>$this->import_results,
            );
        
        return $import_response;        
    }
    
    
    /**
     * Parse orders
     * @param  array  $item
     * @param  integer $merge_empty_cells
     * @return array
     */
    public function parse_subscription_orders($item, $merge) {
        try{
            global $wpdb;

            $data = $item['mapping_fields'];
            foreach ($item['meta_mapping_fields'] as $value) {
                $data = array_merge($data,$value);            
            }
            $data = apply_filters('wt_subscription_order_importer_pre_parse_data', $data);
            if($this->id_conflict == 'import'){
                unset($data['last_order_date_created']);
            }

            $post_meta = array();
            $result = array();
            $merging = false;
			$billing_and_shipping_addr = array();

            $result['customer_id'] = $data['customer_id'];
            $result['subscription_id'] = !empty($data['subscription_id']) ? $data['subscription_id'] : 0;
            $result['customer_username'] = $data['customer_username'];
            $result['customer_email'] = $data['customer_email'];
            $result['payment_method'] = $data['payment_method'];
            $result['payment_method'] =  (!empty($data['payment_method']) ) ? strtolower($data['payment_method']) : '';
            $result['payment_method_title'] = (!empty($data['payment_method_title']) ) ? $data['payment_method_title'] :  $result['payment_method'];
            $result['order_total'] = !empty($data['order_total']) ? $data['order_total']: 0;
            $result['billing_email'] =  (!empty($data['billing_email']) ) ? $data['billing_email'] : '';

                        
            $this->is_order_exist = false;
            $subscription_id = $result['subscription_id'];
            
            if($subscription_id){                
                $this->is_order_exist = $this->subscription_order_exists($subscription_id);                                                                
            }
            
            if (!$merge && $this->is_order_exist) {
                $usr_msg = 'Subscription with same ID already exists.';
                $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__('> &#8220;%s&#8221;' . $usr_msg), esc_html($subscription_id)), true);
                unset($data);
                return new WP_Error( 'parse-error',  sprintf(__('> &#8220;%s&#8221;' . $usr_msg), esc_html($subscription_id)) );
            }            
            
            if(!$this->is_order_exist && $this->skip_new){                
                $this->hf_log_data_change( 'review-csv-import', '> > Skipping new item.' );
                return new WP_Error( 'parse-error',  'Skipping new item on merge.' );                                            
            }
            
            if($this->is_order_exist){
                $merging = true;                                
            }                                               
            
            if ('skip' == $this->id_conflict && $subscription_id && is_string(get_post_status($subscription_id)) && (get_post_type($subscription_id) !== 'shop_subscription') && (get_post_type($subscription_id) !== 'hf_shop_subscription')) {
                    $usr_msg = 'Importing subscription(ID) conflicts with an existing post.';
                    $this->hf_log_data_change('hf-subscription-csv-import', __('> &#8220;%s&#8221;' . $usr_msg), esc_html($subscription_id), true);
                    unset($data);
                    return new WP_Error( 'parse-error',  sprintf(__('> &#8220;%s&#8221;' . $usr_msg), esc_html($subscription_id)) );
            }
            if($this->is_hpos_enabled()){
                if('skip' == $this->id_conflict && $subscription_id ){
                    $usr_msg = 'Importing subscription(ID) conflicts with an existing post.';
                    $sql="SELECT id FROM {$wpdb->prefix}wc_orders WHERE type NOT IN ('shop_subscription','hf_shop_subscription') AND id=%d";
                    $result_query = $wpdb->query($wpdb->prepare($sql,$subscription_id ));
                    if($result_query){
                        $this->hf_log_data_change('hf-subscription-csv-import', __('> &#8220;%s&#8221;' . $usr_msg), esc_html($subscription_id), true);
                        unset($data);
                        return new WP_Error( 'parse-error',  sprintf(__('> &#8220;%s&#8221;' . $usr_msg), esc_html($subscription_id)) ); 
                    }
                }
            }
                
            
            $missing_shipping_addresses = $missing_billing_addresses = array();

            $tax_rates = array();

            foreach ($wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates") as $_row) {
                $tax_rates[$_row->tax_rate_id] = $_row;
            }

            foreach ($this->order_meta_fields as $column) {
                switch ($column) {
                    case 'cart_discount':
                    case 'cart_discount_tax':
                    case 'order_shipping':
                    case 'order_shipping_tax':
                    case 'order_total':
                        $value = (!empty($data[$column])) ? $data[$column] : 0;
                        $post_meta[] = array('key' => '_' . $column, 'value' => $value);
                        break;

                    case 'payment_method':
                        $payment_method = (!empty($data[$column]) ) ? strtolower($data[$column]) : '';
                        $title = (!empty($data['payment_method_title']) ) ? $data['payment_method_title'] : $payment_method;

                        if (!empty($payment_method) && 'manual' != $payment_method) {
                            $post_meta[] = array('key' => '_' . $column, 'value' => $payment_method);
                            $post_meta[] = array('key' => '_payment_method_title', 'value' => $title);
                        }
                        break;

                    case 'shipping_address_1':
                    case 'shipping_city':
                    case 'shipping_postcode':
                    case 'shipping_state':
                    case 'shipping_country':
                    case 'shipping_phone':                        
                    case 'billing_address_1':
                    case 'billing_city':
                    case 'billing_postcode':
                    case 'billing_state':
                    case 'billing_country':
                    case 'billing_phone':
                    case 'billing_company':
                    case 'billing_email':
                        $value = (!empty($data[$column]) ) ? $data[$column] : '';
                        if('billing_phone' == $column || 'shipping_phone' == $column){
                            $value = trim($value,'\'');
                        }
                        if (empty($value)) {
                            if (0 === strpos($column, 'billing_')) {
                                $missing_billing_addresses[] = $column;
                            } else {
                                $missing_shipping_addresses[] = $column;
                            }
                        }

                        $post_meta[] = array('key' => '_' . $column, 'value' => $value);
						$billing_and_shipping_addr[$column] = $value;
                        break;
                    case 'billing_first_name':
                    case 'billing_last_name':
                    case 'billing_address_2':
                    case 'shipping_first_name':
                    case 'shipping_last_name':
                    case 'shipping_address_2':
                    case 'shipping_company':
                        $value = (!empty($data[$column]) ) ? $data[$column] : '';
                        $post_meta[] = array('key' => '_' . $column, 'value' => $value);
						$billing_and_shipping_addr[$column] = $value;
                        break;
                        
                    default:
                        $value = (!empty($data[$column]) ) ? $data[$column] : '';
                        $post_meta[] = array('key' => '_' . $column, 'value' => $value);
                }
            }
            
            // Get any custom meta fields
            foreach ($data as $key => $value) {
                // Handle meta: columns - import as custom fields
                if (strstr($key, 'meta:')) {

                    $meta_key = trim(str_replace('meta:', '', $key));
                    // Add to postmeta array
                    $post_meta[] = array(
                        'key' => esc_attr($meta_key),
                        'value' => $value,
                    );                    
                }
            }

            if (empty($data['subscription_status'])) {
                $status = 'pending';
                $this->hf_log_data_change('hf-subscription-csv-import', __('No subscription status was specified. The subscription will be created with the status "pending". '));
            } else {
                $status = $data['subscription_status'];
            }
            $result['subscription_status'] = $status;
            $dates_to_update = array('start' => (!empty($data['date_created'])) ? gmdate('Y-m-d H:i:s', strtotime($data['date_created'])) : '');
            foreach (array('last_order_date_created', 'trial_end_date', 'next_payment_date', 'end_date') as $date_type) {
                $dates_to_update[$date_type] = (!empty($data[$date_type]) ) ? gmdate('Y-m-d H:i:s', strtotime($data[$date_type])) : '';
                $result[$date_type] = $dates_to_update[$date_type];
            }
            foreach ($dates_to_update as $date_type => $datetime) {
                if (empty($datetime)) {
                    continue;
                }
                switch ($date_type) {
                    case 'end_date' :
                        if (!empty($dates_to_update['last_order_date_created']) && strtotime($datetime) <= strtotime($dates_to_update['last_order_date_created'])) {
                            $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__('The %s date must occur after the last payment date.'), $date_type));
							unset($data);
							return new WP_Error( 'parse-error',  sprintf(__('The %s date must occur after the last payment date.'), $date_type) );
                        }
                        if (!empty($dates_to_update['next_payment_date']) && strtotime($datetime) <= strtotime($dates_to_update['next_payment_date'])) {
                            $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__('The %s date must occur after the next payment date.'), $date_type));
							unset($data);
							return new WP_Error( 'parse-error',  sprintf(__('The %s date must occur after the next payment date.'), $date_type) );
                        }
                    case 'next_payment_date' :
                        if (!empty($dates_to_update['trial_end_date']) && strtotime($datetime) < strtotime($dates_to_update['trial_end_date'])) {
                            $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__('The %s date must occur after the trial end date.'), $date_type));
							unset($data);
							return new WP_Error( 'parse-error',  sprintf(__('The %s date must occur after the trial end date.'), $date_type) );
                        }
                    case 'trial_end_date' :
                        if (strtotime($datetime) <= strtotime($dates_to_update['start'])) {
                            $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__('The %s must occur after the start date.'), $date_type));
							unset($data);
							return new WP_Error( 'parse-error',  sprintf(__('The %s must occur after the start date.'), $date_type) );
                        }
                }
            }
            $result['start_date'] = $dates_to_update['start'];
            $result['dates_to_update'] = $dates_to_update;
            $result['post_parent'] = isset($data['post_parent']) ? $data['post_parent'] : 0;
            $result['billing_interval'] = (!empty($data['billing_interval']) ) ? $data['billing_interval'] : 1;
            $result['billing_period'] = (!empty($data['billing_period']) ) ? $data['billing_period'] : '';
            $result['created_via'] = 'importer';
            $result['customer_note'] = (!empty($data['customer_note']) ) ? $data['customer_note'] : '';
            $result['currency'] = (!empty($data['order_currency']) ) ? $data['order_currency'] : '';
            $result['post_meta'] = $post_meta;
			$result['billing_and_shipping_addr'] = $billing_and_shipping_addr;

            if (!empty($data['order_notes'])) {
                
                $order_notes = array();
                if (!empty($data['order_notes'])) {
                    $order_notes = explode("||", $data['order_notes']);
                }
                $result['order_notes'] = $order_notes;
            }

            if (!empty($data['renewal_orders'])) {
                $result['renewal_orders'] = $data['renewal_orders'];
            }

            if (!empty($data['coupon_items'])) {
                $result['coupon_items'] = $data['coupon_items'];
            }

            if ( ! empty( $data['tax_items'] ) ) {
				$tax_item = explode( ';', $data['tax_items'] );
				$tax_items = array();
				foreach ( $tax_item as $tax ) {

					$tax_item_data = array();
					// turn "label: Tax | tax_amount: 10" into an associative array.
					foreach ( explode( '|', $tax ) as $piece ) {
						list( $name, $value ) = array_pad( explode( ':', $piece ), 2, null );
						if ( isset( $name ) ) {
							$tax_item_data[ trim( $name ) ] = trim( $value );
						}
					}
					// default rate id to 0 if not set.
					if ( ! isset( $tax_item_data['rate_id'] ) ) {
						$tax_item_data['rate_id'] = 0;
					}
					$old_rate_id = $tax_item_data['rate_id'];
					if ( ! isset( $tax_item_data['rate_percent'] ) ) {
						$tax_item_data['rate_percent'] = '';
					}
					// default rate id to 0 if rate id is not present in $tax_rates.
					if ( $tax_item_data['rate_id'] && ! isset( $tax_rates[ $tax_item_data['rate_id'] ] ) || 0 == $tax_item_data['rate_id'] ) {
						$tax_item_data['rate_id'] = 0;
					} else if ( isset( $tax_item_data['label'] ) && 0 !== strcasecmp( $tax_rates[ $tax_item_data['rate_id'] ]->tax_rate_name, $tax_item_data['label'] ) || $tax_rates[ $tax_item_data['rate_id'] ]->tax_rate != $tax_item_data['rate_percent'] ) {
						// label or rate percent not match.
						$tax_item_data['rate_id'] = 0;
					}
					// have a tax amount or shipping tax amount.
					if ( ( isset( $tax_item_data['total'] ) || isset( $tax_item_data['shipping_tax_amount'] ) ) && wc_tax_enabled() ) {
						// try and look up rate id by label if needed.
						if ( isset( $tax_item_data['label'] ) && $tax_item_data['label'] && ! $tax_item_data['rate_id'] ) {
							foreach ( $tax_rates as $tax_rate ) {
								// if label match check rate id is matching.
								if ( 0 === strcasecmp( $tax_rate->tax_rate_name, $tax_item_data['label'] ) && $tax_rate->tax_rate == $tax_item_data['rate_percent'] ) {
									// found the tax by label.
									$tax_item_data['rate_id'] = $tax_rate->tax_rate_id;
									break;
								}
							}
						}
						// default label of 'Tax' if not provided.
						if ( ! isset( $tax_item_data['label'] ) || ! $tax_item_data['label'] ) {
							$tax_item_data['label'] = 'Tax';
						}

						// default tax amounts to 0 if not set.
						if ( ! isset( $tax_item_data['total'] ) ) {
							$tax_item_data['total'] = 0;
						}
						if ( ! isset( $tax_item_data['shipping_tax_amount'] ) ) {
							$tax_item_data['shipping_tax_amount'] = 0;
						}

						// handle compound flag by using the defined tax rate value (if any).
						if ( ! isset( $tax_item_data['tax_rate_compound'] ) ) {
							$tax_item_data['tax_rate_compound'] = '';
							if ( $tax_item_data['rate_id'] ) {
								$tax_item_data['tax_rate_compound'] = $tax_rates[ $tax_item_data['rate_id'] ]->tax_rate_compound;
							}
						}

						$tax_items[ $old_rate_id ] = array(
							'title' => $tax_item_data['code'],
							'rate_id' => $tax_item_data['rate_id'],
							'label' => $tax_item_data['label'],
							'compound' => $tax_item_data['tax_rate_compound'],
							'tax_amount' => $tax_item_data['total'],
							'shipping_tax_amount' => $tax_item_data['shipping_tax_amount'],
							'rate_percent' => $tax_item_data['rate_percent'],
						);
					}
				}

				$result['tax_items'] = $tax_items;
			}

            if (!empty($data['order_items'])) {
                $_order_items = explode('||', $data['order_items']);
                foreach ($_order_items as $item) {
                    if(!empty($item)){
                        $_item_meta = explode(apply_filters('wt_subscription_change_item_separator','|'), $item);
                    }

                    // get any additional item meta
                    $item_meta = array();
                    foreach ($_item_meta as $pair) {

                        // replace any escaped pipes
                        $pair = str_replace('\|', '|', $pair);

                        // find the first ':' and split into name-value
                        $split = strpos($pair, ':');

                        $name = substr($pair, 0, $split);

                        $value = substr($pair, $split + 1);

                        switch ($name) {
                            case 'name':
                                $product_name = $value;
                                break;
                            case 'product_id':
                                $product_id = $value;
                                break;
                            case 'sku':
                                $sku = $value;
                                break;
                            case 'quantity':
                                $qty = $value;
                                break;
                            case 'total':
                                $total = $value;
                                break;
                            case 'sub_total':
                                $sub_total = $value;
                                break;
                            case 'tax':
                                $tax = $value;
                                break;
                            case 'tax_data':
                                $tax_data = $value;
                                break;
                            default :
                                $item_meta[$name] = $value;
                        }

                    }
                    $tax_data = maybe_unserialize( $tax_data );
					if ( ! empty( $tax_data ) ) {
						$tax_data = maybe_unserialize( $tax_data );
						$new_tax_data = array();
						if ( isset( $tax_data['total'] ) ) {
							foreach ( $tax_data['total'] as $t_key => $t_value ) {
								if ( isset( $result['tax_items'][ $t_key ] ) ) {
									$new_tax_data ['total'][ $result['tax_items'][ $t_key ]['rate_id'] ] = $t_value;
								} else {
									$new_tax_data ['total'][ $t_key ] = $t_value;
								}
							}
						}
						if ( isset( $tax_data['subtotal'] ) ) {
							foreach ( $tax_data['subtotal'] as $st_key => $st_value ) {
								if ( isset( $result['tax_items'][ $st_key ] ) ) {
									$new_tax_data ['subtotal'][ $result['tax_items'][ $st_key ]['rate_id'] ] = $st_value;
								} else {
									$new_tax_data ['subtotal'][ $st_key ] = $st_value;
								}
							}
						}
					}
        
                    $tax_data = maybe_serialize( $new_tax_data );

                    $order_items[] = array(
						'product_id' => $product_id,
						'sku' => $sku,
						'qty' => $qty,
						'total' => $total,
						'sub_total' => $sub_total,
						'tax' => isset($tax) ? $tax : '',
						'tax_data' => $tax_data,
						'meta' => $item_meta,
						'name' => $product_name,
						);
                }
                $result['order_items'] = $order_items;
            }

            if (!empty($data['order_currency'])) {
                $result['order_currency'] = $data['order_currency'];
            }

            if (!empty($data['fee_items'])) {
                $result['fee_items'] = $data['fee_items'];
            }

            if (!empty($data['shipping_method'])) {
                $result['shipping_method'] = $data['shipping_method'];
            }
            if(!empty($data['payment_token_data'])) {
                $payment_token_data = unserialize($data['payment_token_data']);
                $result['payment_token_data'] = $payment_token_data['token_data'];
                $result['payment_token_meta_data'] = $payment_token_data['token_meta_data'];



            }

            $shipping_items = $shipping_line_items = array();
            if(!empty($data['shipping_items'])){
                $shipping_line_items = explode(';', $data['shipping_items']);
                $shipping_item_data = array();
                foreach ($shipping_line_items as $shipping_line_item) {
                    foreach (explode('|', $shipping_line_item) as $piece) {
                        list( $name, $value ) = explode(':', $piece);
                        $shipping_item_data[trim($name)] = trim($value);
                    }
                    if(!isset($shipping_item_data['item'])){
                        $shipping_item_data['item'] = '';
                    }
                    if(!isset($shipping_item_data['value'])){
                        $shipping_item_data['value'] = 0;
                    }
                    $shipping_items[] = array(
                        'item' => $shipping_item_data['item'],
                        'value' => $shipping_item_data['value']
                    ); 
                }
                $result['shipping_items'] = $shipping_items;
            }
            
            $result['merging'] = $merging;            
            return $result;
        } catch (Exception $e) {
            return new WP_Error('woocommerce_product_importer_error', $e->getMessage(), array('status' => $e->getCode()));
        }
    }
                 
    function hf_currency_formatter($price) {
        $decimal_seperator = wc_get_price_decimal_separator();
        return preg_replace("[^0-9\\'.$decimal_seperator.']", "", $price);
    }
    
    /**
     * Create new posts based on import information
     */
    private function process_subscription_orders($data) {
        try{
            do_action('wt_subscription_order_import_before_process_item', $data);
            global $wpdb;
            $merge = !empty($data['merging']);
            $is_order_exist = $this->is_order_exist;
            $add_memberships = ( isset($_POST['add_memberships']) ) ? sanitize_text_field($_POST['add_memberships']) : FALSE;
            $this->hf_log_data_change('hf-subscription-csv-import', __('Process start..'));
            $this->hf_log_data_change('hf-subscription-csv-import', __('Processing subscriptions...'));
            $email_customer = false; // set this as settings for choosing weather to mail details for newly created customers.
            $user_id = $this->hf_check_customer($data, $email_customer);
            if (is_wp_error($user_id)) {
                $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__($user_id->get_error_message())));
                unset($data);
                return new WP_Error( 'parse-error',  sprintf(__($user_id->get_error_message())) );
            } elseif (empty($user_id)) {
                unset($data);
                return new WP_Error( 'parse-error',  __('An error occurred with the customer information provided.') );
            }
            //check whether download permissions need to be granted
            $add_download_permissions = false;
            // Check if post exists when importing
            $new_added = false;
            if ((!empty($data['post_parent'])) && $this->link_wt_import_key) { //Check whether post_parent (Parent order ID) is an order or not
                $data['post_parent'] = self::wt_get_order_with_import_key($data['post_parent']);
            }else{ //Check whether post_parent (Parent order ID) is an order or not
                $temp_parent_order_exist = wc_get_order($data['post_parent']);
                $data['post_parent'] = ( $temp_parent_order_exist && $temp_parent_order_exist->get_type() == 'shop_order' ) ? $data['post_parent'] : '';
            }

            
            
            $subscription_data = array(
                    'customer_id' => $user_id,
                    'order_id' => $data['post_parent'], //If order id is 0 it won't affect the existing parent order for particular subscription
                    'import_id' => $data['subscription_id'], //Suggest import to keep the given ID
                    'start_date' => $data['dates_to_update']['start'],
                    'billing_interval' => (!empty($data['billing_interval']) ) ? $data['billing_interval'] : 1,
                    'billing_period' => (!empty($data['billing_period']) ) ? $data['billing_period'] : '',
                    'created_via' => 'importer',
                    'customer_note' => (!empty($data['customer_note']) ) ? $data['customer_note'] : '',
                    'currency' => (!empty($data['order_currency']) ) ? $data['order_currency'] : '',
                        );
                        
			if(!empty($data['subscription_status'])){
				$subscription_data['status'] = $data['subscription_status'];
			}
            
            if ($is_order_exist) {   //Execute this when subscription already exist 
                $subscription_data['ID'] = $data['subscription_id'];
				
                if(class_exists('HF_Subscription')){
                    $subscription_data['status'] = $data['subscription_status'];
                }                 
                if(empty($subscription_data['start_date'])){                    
                    $subscription_data['start_date'] = get_post($subscription_data['ID'])->post_date;
                }
                               
                $subscription = $this->hf_create_subscription($subscription_data, TRUE);
                
                $new_added = false;
                if (is_wp_error($subscription)) {
                    $this->errored++;
                    $new_added = false;
                    $order_number = $data['order_number'];
                    unset($data);
                    return new WP_Error( 'parse-error',  sprintf(__('> Error inserting %s: %s'), $order_number, $subscription->get_error_message()) );
                }
            } else {
                if(class_exists('HF_Subscription')){
                    $subscription_data['status'] = $data['subscription_status'];
                }    
                
                $subscription = $this->hf_create_subscription($subscription_data); 
                
                $new_added = true;
                if (is_wp_error($subscription)) {
                    $this->errored++;
                    $new_added = false;
                    unset($data);
                    return new WP_Error( 'parse-error',  __($subscription->get_error_message()) );
                }
            }           

			$current_order_ids = array();
            if (!empty($data['renewal_orders'])) {
                $renewal_orders = explode('|', $data['renewal_orders']);
                if($this->link_wt_import_key){
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
                        update_post_meta($id, '_subscription_renewal', (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id);
                    }
                } else {
                    foreach ($current_order_ids as $id){
                        update_post_meta($id, '_subscription_renewal', (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id);
                    }
                }
            }
            foreach ($data['post_meta'] as $meta_data) {
                switch ($meta_data['key']){
                    case '_billing_email':
                        $_billing_email = $meta_data['value']; // keep _billing_email for update _billing_email after update $subscription->update_dates and $subscription->update_status
                    
                    case '_coupon_items':
                        break;
                    case '_download_permissions':
                        $add_download_permissions = TRUE;
                        $data['download_permissions'] = $meta_data['value'];
                        update_post_meta(( (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id),'_download_permissions_granted' , $meta_data['value']);
                        break;
					case '_requires_manual_renewal';
						if($meta_data['value'] == 0){
							$meta_data['value'] = false;
						}
						update_post_meta(( (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id), $meta_data['key'], $meta_data['value']);
						break;
                    default:
                        update_post_meta(( (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id), $meta_data['key'], $meta_data['value']);
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
                if($token_id){
                    $subscription->get_data_store()->update_payment_token_ids( $subscription,array($token_id) );				
                }
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

            try {
                $subscription->update_dates($data['dates_to_update']);
				if(isset($data['subscription_status']))	{
                    $subscription->update_status($data['subscription_status']);
                }
            } catch (Exception $e) {
                unset($data);
                return new WP_Error( 'parse-error',  __($e->getMessage()) );
            }
            
            if(isset($_billing_email) && !empty($_billing_email)){ // Updating _billing_email after update $subscription->update_dates and $subscription->update_status
                update_post_meta(( (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id), '_billing_email', $_billing_email);
            }


            $result['items'] = isset($result['items']) ? $result['items'] : '';
            if (!empty($data['order_items'])) {
                $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items ON itemmeta.order_item_id = items.order_item_id WHERE items.order_id = %d and items.order_item_type = 'line_item'", $subscription_data['ID']));
                if (is_numeric($data['order_items'])) {
                    $product_id = absint($data['order_items']);
                    $result['items'] = self::add_product($data, $subscription, array('product_id' => $product_id) ,$this->link_product_using_sku);
                    if ($add_memberships) {
                        self::maybe_add_memberships($user_id, ( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id), $product_id);
                    }
                } else {
                    foreach ($data['order_items'] as $order_item) {
                        $result['items'] .= self::add_product($data, $subscription, $order_item, $this->link_product_using_sku) . '<br/>';

                        if ($add_memberships) {
                            self::maybe_add_memberships($user_id, ( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id), $item_data['product_id']);
                        }
                    }
                }
            }

            if(!empty($data['shipping_method'])){
                $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items ON itemmeta.order_item_id = items.order_item_id WHERE items.order_id = %d and items.order_item_type = 'shipping'", $subscription_data['ID']));
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
                $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items WHERE itemmeta.order_item_id = items.order_item_id and items.order_id = %d and items.order_item_type = %s", $subscription_data['ID'], $fee_str));
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
                if ($merge && $is_order_exist) {
                    $tax_str = 'tax';
                    $wpdb->query($wpdb->prepare("DELETE items,itemmeta FROM {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items WHERE itemmeta.order_item_id = items.order_item_id and items.order_id = %d and items.order_item_type = %s", $subscription_data['ID'], $tax_str));
                }

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
                self::add_coupons($subscription, $data);
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
            if (!self::$all_virtual) {
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
            if (( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id)) {
                $this->processed_posts[( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id)] = ( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id);
                $data['subscription_id'] = ( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id);
            }
            if (!empty($data['subscription_id'])) {
                $this->processed_posts[$data['subscription_id']] = $data['subscription_id'];
            }
            if ($merge && !$new_added)
                $out_msg = 'Subscription updated successfully';
            else
                $out_msg = 'Subscription Imported Successfully.';
            $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__('> &#8220;%s&#8221;' . $out_msg), esc_html($data['subscription_id'])), true);
            $this->hf_log_data_change('hf-subscription-csv-import', sprintf(__('> Finished importing order %s'), $data['subscription_id']));
            $this->hf_log_data_change('hf-subscription-csv-import', __('Finished processing orders.'));
			$data['is_subscription_exist'] = $this->is_order_exist;
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
            
            if($this->delete_existing){
                update_post_meta(( (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id), '_wt_delete_existing', 1);
            }
             $subscription->save();
            return array('id'=>( (WC()->version >= '2.7.0') ? $subscription->get_id() : $subscription->id));
            
        } catch (Exception $e) {
            return new WP_Error('woocommerce_product_importer_error', $e->getMessage(), array('status' => $e->getCode()));
        }
    }
    
    
    public function subscription_order_exists($orderID) {
        global $wpdb;
        if(class_exists('HF_Subscription')){
            $args = 'hf_shop_subscription';
        } else {
            $args = 'shop_subscription';
        }
        if($this->is_hpos_enabled()){
            $posts_are_exist = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = %s AND status IN ( 'wc-pending-cancel','wc-expired','wc-switched','wc-cancelled','wc-on-hold','wc-active','wc-pending')", $args));
        }else{
            $posts_are_exist = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_status IN ( 'wc-pending-cancel','wc-expired','wc-switched','wc-cancelled','wc-on-hold','wc-active','wc-pending')", $args));
        }
        if ($posts_are_exist) {
            foreach ($posts_are_exist as $exist_id) {
                $found = false;
                if ($exist_id == $orderID) {
                    $found = TRUE;
                }
                if ($found)
                    return TRUE;
            }
        } else {
            return FALSE;
        }
    }
    
    public function hf_check_customer($data, $email_customer = false) {
        $customer_email = (!empty($data['customer_email']) ) ? $data['customer_email'] : '';
        $username = (!empty($data['customer_username']) ) ? $data['customer_username'] : '';
        $customer_id = (!empty($data['customer_id']) ) ? $data['customer_id'] : '';
        if (!empty($data['_customer_password'])) {
            $password = $data['_customer_password'];
            $password_generated = false;
        } else {
            $password = wp_generate_password(12, true);
            $password_generated = true;
        }
        $found_customer = false;
        $order_exist = $this->subscription_order_exists($data['subscription_id']);
        if($order_exist && empty($customer_email) && $this->merge== 1){
            $order = new WC_Order( $data['subscription_id'] );
            $customer_id = $order->get_customer_id(); 
            $user_info = get_userdata($customer_id);
            $customer_email = $user_info->user_email;
        }
        if (!empty($customer_email)) {
            if (is_email($customer_email) && false !== email_exists($customer_email)) {
                $found_customer = email_exists($customer_email);
            } elseif (!empty($username) && false !== username_exists($username)) {
                $found_customer = username_exists($username);
            } elseif (is_email($customer_email)) {
                // Not in test mode, create a user account for this email
                if (empty($username)) {
                    $maybe_username = explode('@', $customer_email);
                    $maybe_username = sanitize_user($maybe_username[0]);
                    $counter = 1;
                    $username = $maybe_username;
                    while (username_exists($username)) {
                        $username = $maybe_username . $counter;
                        $counter++;
                    }
                }
                $found_customer = wp_create_user($username, $password, $customer_email);
                if (!is_wp_error($found_customer)) {
                    // update user meta data
                    foreach (self::$user_meta_fields as $key) {
                        switch ($key) {
                            case '_billing_email':
                                // user billing email if set in csv otherwise use the user's account email
                                $meta_value = (!empty($data['post_meta'][$key]) ) ? $data['post_meta'][$key] : $customer_email;
                                $key = substr($key, 1);
                                update_user_meta($found_customer, $key, $meta_value);
                                break;
                            case '_billing_first_name':
                                $meta_value = (!empty($data['post_meta'][$key]) ) ? $data['post_meta'][$key] : $username;
                                $key = substr($key, 1);
                                update_user_meta($found_customer, $key, $meta_value);
                                update_user_meta($found_customer, 'first_name', $meta_value);
                                break;
                            case '_billing_last_name':
                                $meta_value = (!empty($data['post_meta'][$key]) ) ? $data['post_meta'][$key] : '';
                                $key = substr($key, 1);
                                update_user_meta($found_customer, $key, $meta_value);
                                update_user_meta($found_customer, 'last_name', $meta_value);
                                break;
                            case '_shipping_first_name':
                            case '_shipping_last_name':
                            case '_shipping_address_1':
                            case '_shipping_address_2':
                            case '_shipping_city':
                            case '_shipping_postcode':
                            case '_shipping_state':
                            case '_shipping_country':
                                // Set the shipping address fields to match the billing fields if not specified in CSV
                                $meta_value = (!empty($data['post_meta'][$key]) ) ? $data['post_meta'][$key] : '';

                                if (empty($meta_value)) {
                                    $n_key = str_replace('shipping', 'billing', $key);
                                    $meta_value = (!empty($data['post_meta'][$n_key]) ) ? $data['post_meta'][$n_key] : '';
                                }
                                $key = substr($key, 1);
                                update_user_meta($found_customer, $key, $meta_value);
                                break;

                            default:
                                $meta_value = (!empty($data['post_meta'][$key]) ) ? $data['post_meta'][$key] : '';
                                $key = substr($key, 1);
                                update_user_meta($found_customer, $key, $meta_value);
                        }
                    }
                    $this->hf_make_user_active($found_customer);
                    // send user registration email if admin as chosen to do so
                    if ($email_customer && function_exists('wp_new_user_notification')) {
                        $previous_option = get_option('woocommerce_registration_generate_password');
                        // force the option value so that the password will appear in the email
                        update_option('woocommerce_registration_generate_password', 'yes');

                        do_action('woocommerce_created_customer', $found_customer, array('user_pass' => $password), true);

                        update_option('woocommerce_registration_generate_password', $previous_option);
                    }
                }
            }
        } else {
            $found_customer = new WP_Error('hf_invalid_customer', sprintf(__('User could not be created without Email.'), $customer_id));
        }
        return $found_customer;
    }

    public function hf_make_user_active($user_id) {
        $this->hf_update_users_role($user_id, 'default_subscriber_role');
    }

    /**
     * Update a user's role to a special subscription's role
     * @param int $user_id The ID of a user
     * @param string $role_new The special name assigned to the role by Subscriptions,
     * one of 'default_subscriber_role', 'default_inactive_role' or 'default_cancelled_role'
     * @return WP_User The user with the new role.
     * @since 2.0
     */
    public function hf_update_users_role($user_id, $role_new) {
        $user = new WP_User($user_id);
        // Never change an admin's role to avoid locking out admins testing the plugin
        if (!empty($user->roles) && in_array('administrator', $user->roles)) {
            return;
        }
        // Allow plugins to prevent Subscriptions from handling roles
        if (!apply_filters('woocommerce_subscriptions_update_users_role', true, $user, $role_new)) {
            return;
        }
        $roles = $this->hf_get_new_user_role_names($role_new);
        $role_new = $roles['new'];
        $role_old = $roles['old'];
        if (!empty($role_old)) {
            $user->remove_role($role_old);
        }
        $user->add_role($role_new);
        do_action('woocommerce_subscriptions_updated_users_role', $role_new, $user, $role_old);
        return $user;
    }

    /**
     * Gets default new and old role names if the new role is 'default_subscriber_role'. Otherwise returns role_new and an
     * empty string.
     *
     * @param $role_new string the new role of the user
     * @return array with keys 'old' and 'new'.
     */
    public function hf_get_new_user_role_names($role_new) {
        $default_subscriber_role = get_option(WC_Subscriptions_Admin::$option_prefix . '_subscriber_role');
        $default_cancelled_role = get_option(WC_Subscriptions_Admin::$option_prefix . '_cancelled_role');
        $role_old = '';
        if ('default_subscriber_role' == $role_new) {
            $role_old = $default_cancelled_role;
            $role_new = $default_subscriber_role;
        } elseif (in_array($role_new, array('default_inactive_role', 'default_cancelled_role'))) {
            $role_old = $default_subscriber_role;
            $role_new = $default_cancelled_role;
        }
        return array(
            'new' => $role_new,
            'old' => $role_old,
        );
    }

    /**
     * Create a new subscription
     *
     * Returns a new WC_Subscription object on success which can then be used to add additional data.
     *
     * @return WC_Subscription | WP_Error A WC_Subscription on success or WP_Error object on failure
     * @since  2.0
     */
    function hf_create_subscription($args = array(), $subscription_exist = false) {
        $order = ( isset($args['order_id']) ) ? wc_get_order($args['order_id']) : null;
        if (!empty($args['order_id']) && ( WC()->version > '2.7' )) {
            $order_wp_post = get_post($args['order_id']);
        } elseif (!empty($order)) {
            $order_wp_post = $order->post;
        }
        if (!empty($order_wp_post) && isset($order_wp_post->post_date)) {
            $default_start_date = ( '0000-00-00 00:00:00' != $order_wp_post->post_date_gmt ) ? $order_wp_post->post_date_gmt : get_gmt_from_date($order_wp_post->post_date);
        } else {
            $default_start_date = current_time('mysql', true);
        }

        $subscription_data = array();
        // validate the start_date field
        if (!is_string($args['start_date']) || false === $this->hf_is_datetime_mysql_format($args['start_date'])) {
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
        if (empty($args['billing_period']) || !in_array(strtolower($args['billing_period']), array_keys($this->hf_get_subscription_period_strings()))) {
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
        $subscription_data['import_id'] = $args['import_id'];
        $subscription_data['customer_id'] = $args['customer_id']; // handle here perfectly-need discuss
        if(class_exists('HF_Subscription')){
            $subscription_data['post_type'] = 'hf_shop_subscription';
        } else {
            $subscription_data['post_type'] = 'shop_subscription';
        }
        $subscription_data['post_status'] = 'wc-' . apply_filters('woocommerce_default_subscription_status', 'pending');
        $subscription_data['ping_status'] = 'closed';
        $subscription_data['post_author'] = 1;
        $subscription_data['post_password'] = uniqid('order_');
        // translators: Order date parsed by strftime
        $post_title_date = strftime(_x('%b %d, %Y @ %I:%M %p', 'Used in subscription post title. "Subscription renewal order - <this>"', 'woocommerce-subscriptions'));
        // translators: placeholder is order date parsed by strftime
        $subscription_data['post_title'] = sprintf(_x('Subscription &ndash; %s', 'The post title for the new subscription', 'woocommerce-subscriptions'), $post_title_date);
        $subscription_data['post_date_gmt'] = $args['start_date'];
        $subscription_data['post_date'] = get_date_from_gmt($args['start_date']);
        if ($args['order_id'] > 0) {
            $subscription_data['post_parent'] = ($args['order_id']);
        }
        if (!is_null($args['customer_note']) && !empty($args['customer_note'])) {
            $subscription_data['post_excerpt'] = $args['customer_note'];
        }
        if (!empty($args['status'])) {
            if (!in_array('wc-' . $args['status'], array_keys($this->hf_get_subscription_statuses()))) {
                return new WP_Error('woocommerce_invalid_subscription_status', __('Invalid subscription status given.', 'woocommerce-subscriptions'));
            }
            $subscription_data['post_status'] = 'wc-' . $args['status'];
        }
        if ($subscription_exist) {
            $subscription_data['ID'] = $args['ID'];
			
			if(isset($args['import_id']))
            $subscription_data['import_id'] = $args['import_id'];
			
			
            unset($subscription_data['post_status']);
			
			if(isset($args['post_status']))
            $subscription_data['ping_status'] = $args['post_status'];
			
			if(isset($args['customer_id']))
            $subscription_data['post_author'] = $subscription_data['customer_id'];
			
			unset($subscription_data['post_password'], $subscription_data['post_title'], $subscription_data['post_date_gmt'], $subscription_data['post_date']);

            $subscription_id = wp_update_post(apply_filters('woocommerce_update_subscription_data', $subscription_data, $args), true);
        } else {
            $subscription_id = wp_insert_post(apply_filters('woocommerce_new_subscription_data', $subscription_data, $args), true);
        }
        if (is_wp_error($subscription_id)) {
            return $subscription_id;
        }
        // Default order meta data.
        update_post_meta($subscription_id, '_order_currency', $args['currency']);
        update_post_meta($subscription_id, '_created_via', sanitize_text_field($args['created_via']));
        // add/update the billing
        update_post_meta($subscription_id, '_billing_period', $args['billing_period']);
        update_post_meta($subscription_id, '_billing_interval', absint($args['billing_interval']));
        update_post_meta($subscription_id, '_customer_user', $args['customer_id']);
        if(class_exists('HF_Subscription')){
            return new HF_Subscription($subscription_id);
        }else{
            return new WC_Subscription($subscription_id);
        }
    }

    /**
     * Return an array statuses used to describe when a subscriptions has been marked as ending or has ended.
     *
     * @return array
     * @since 2.0
     */
    public function hf_get_subscription_ended_statuses() {
        return apply_filters('hf_subscription_ended_statuses', array('cancelled', 'trash', 'expired', 'switched', 'pending-cancel'));
    }


    /**
     * Add membership plans to imported subscriptions if applicable
     *
     * @since 1.0
     * @param int $user_id
     * @param int $subscription_id
     * @param int $product_id
     */
    public static function maybe_add_memberships($user_id, $subscription_id, $product_id) {
        if (function_exists('wc_memberships_get_membership_plans')) {
            if (!self::$membership_plans) {
                self::$membership_plans = wc_memberships_get_membership_plans();
            }
            foreach (self::$membership_plans as $plan) {
                if ($plan->has_product($product_id)) {
                    $plan->grant_access_from_purchase($user_id, $product_id, $subscription_id);
                }
            }
        }
    }

    /**
     * Adds the line item to the subscription
     *
     * @since 1.0
     * @param WC_Subscription $subscription
     * @param array $data
     * @return string
     */
    public static function add_product($details, $subscription, $data,$link_product_using_sku) {
        $item_args = array();
        $item_args['qty'] = isset($data['qty']) ? $data['qty'] : 1;
        if($link_product_using_sku || empty($data['product_id'])){            
            $product_id = wc_get_product_id_by_sku($data['sku']);
            $data['product_id'] = $product_id;
        }
        if (!isset($data['product_id'])) {
            throw new Exception(__('The product is not found.'));
        }
        $_product = wc_get_product($data['product_id']);
        if (!$_product) {
            $order_item = array(
                'order_item_name' => (!empty($data['name']) ) ? $data['name'] : __('Unknown Product'),
                'order_item_type' => 'line_item',
            );
            $_order_item_meta = array(
                '_qty' => $item_args['qty'] ,
                '_tax_class' => '', // Tax class (adjusted by filters)
                '_product_id' => '',
                '_variation_id' => '',
                '_line_subtotal' => !empty($data['total']) ? $data['total'] : 0, // Line subtotal (before discounts)
                '_line_subtotal_tax' => 0, // Line tax (before discounts)
                '_line_total' => !empty($data['sub_total']) ? $data['sub_total'] : 0, // Line total (after discounts)
                '_line_tax' => 0, // Line Tax (after discounts)
            );
            if(isset($data['meta']) && !empty($data['meta'])){
               $_order_item_meta = array_merge($_order_item_meta, $data['meta']);
            }
            $line_item_name = (!empty($data['name']) ) ? $data['name'] : __('Unknown Product');
            $product_string = $line_item_name;
        } else {

            $line_item_name = (!empty($data['name']) ) ? $data['name'] : $_product->get_title();

           $product_id = (WC()->version >= '2.7.0') ? $_product->get_id() : $_product->id;// solve issue with the hyperlink when the variation product present in the subscription and linked using the Link using the SKU option.
             if( get_post_type($product_id) == 'product_variation'){    
               $product_id = wp_get_post_parent_id($product_id);
               $data['product_id'] = $product_id;//parent id added
            }

            $product_string = sprintf('<a href="%s">%s</a>', get_edit_post_link((WC()->version >= '2.7.0') ? $product_id : $_product->id), $line_item_name);
    
            $order_item = array(
                'order_item_name' => $line_item_name,
                'order_item_type' => 'line_item',
            );
            $var_id = 0;
            if (WC()->version < '2.7.0') {
                $var_id = ($_product->product_type === 'variation') ? $_product->variation_id : 0;
            } else {
                $var_id = $_product->is_type('variation') ? $_product->get_id() : 0;
            }
            
            $_order_item_meta = array(
                '_qty' => $item_args['qty'] ,
                '_tax_class' => '', // Tax class (adjusted by filters)
                '_product_id' => $data['product_id'],
                '_variation_id' => $var_id,
                '_line_subtotal' => !empty($data['total']) ? $data['total'] : 0, // Line subtotal (before discounts)
                '_line_subtotal_tax' => !empty($data['tax']) ? $data['tax'] : 0, // Line tax (before discounts)
                '_line_total' => !empty($data['sub_total']) ? $data['sub_total'] : 0, // Line total (after discounts)
                '_line_tax' => !empty($data['tax']) ? $data['tax'] : 0, // Line Tax (after discounts)
                '_line_tax_data' => $data['tax_data']
            );
            if (self::$all_virtual && !$_product->is_virtual()) {
                self::$all_virtual = false;
            }
            if(isset($data['meta']) && !empty($data['meta'])){
               $_order_item_meta = array_merge($_order_item_meta, $data['meta']);
            }
            if (!empty($details['download_permissions']) && ( 'true' == $details['download_permissions'] || 1 == (int) $details['download_permissions'] )) {
                self::save_download_permissions($subscription, $_product, $item_args['qty']);
            }
        }
        
        $order_item_id = wc_add_order_item(( ( WC()->version >= '2.7.0' ) ? $subscription->get_id() : $subscription->id), $order_item);
        
        if ($order_item_id) {
            foreach ($_order_item_meta as $meta_key => $meta_value) {
                wc_add_order_item_meta($order_item_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
        return $product_string;
    }

    /**
     * Save download permission to the subscription.
     *
     * @since 1.0
     * @param WC_Subscription $subscription
     * @param WC_Product $product
     * @param int $quantity
     */
    public static function save_download_permissions($subscription, $product, $quantity = 1) {
        if ($product && $product->exists() && $product->is_downloadable()) {
            $downloads = $product->get_downloads();
            $product_id = isset($product->variation_id) ? $product->variation_id : ((WC()->version >= '2.7.0') ? $product->get_id() : $product->id);
            foreach (array_keys($downloads) as $download_id) {
                wc_downloadable_file_permission($download_id, $product_id, $subscription, $quantity);
            }
        }
    }

    /**
     * Add coupon line item to the subscription. The discount amount used is based on priority list.
     *
     * @since 1.0
     * @param WC_Subscription $subscription
     * @param array $data
     */
    public static function add_coupons($subscription, $data) {
        $coupon_items = explode(';', $data['coupon_items']);
        if (!empty($coupon_items)) {
            foreach ($coupon_items as $coupon_item) {
                $coupon_data = array();
                foreach (explode('|', $coupon_item) as $item) {
                    list( $name, $value ) = explode(':', $item);
                    $coupon_data[trim($name)] = trim($value);
                }
                $coupon_code = isset($coupon_data['code']) ? $coupon_data['code'] : '';
                $coupon = new WC_Coupon($coupon_code);
                if (!$coupon) {
                    throw new Exception(sprintf(esc_html__('Could not find coupon with code "%s" in your store.'), $coupon_code));
                } elseif (isset($coupon_data['amount'])) {
                    $discount_amount = floatval($coupon_data['amount']);
                } else {
                    $discount_amount = ( WC()->version >= '2.7.0' ) ? $coupon->get_amount() : $coupon->discount_amount;
                }
                if (WC()->version >= '2.7.0') {
                    $cpn = new WC_Order_Item_Coupon();
                    $cpn->set_code($coupon_code);
                    $cpn->set_discount($discount_amount);
                    $cpn->save();
                    $subscription->add_item($cpn);
                    $coupon_id = $cpn->get_id();
                } else {
                    $coupon_id = $subscription->add_coupon($coupon_code, $discount_amount);
                }
                if (!$coupon_id) {
                    throw new Exception(sprintf(esc_html__('Coupon "%s" could not be added to subscription.'), $coupon_code));
                }
            }
        }
    }

    /**
     * PHP on Windows does not have strptime function. Therefore this is what we're using to check
     * whether the given time is of a specific format.
     * @param  string $time the mysql time string
     * @return boolean      true if it matches our mysql pattern of YYYY-MM-DD HH:MM:SS
     */
    public function hf_is_datetime_mysql_format($time) {
        if (!is_string($time)) {
            return false;
        }
        if (function_exists('strptime')) {
            $valid_time = $match = ( false !== strptime($time, '%Y-%m-%d %H:%M:%S') ) ? true : false;
        } else {
            // parses for the pattern of YYYY-MM-DD HH:MM:SS, but won't check whether it's a valid timedate
            $match = preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $time);
            // parses time, returns false for invalid dates
            $valid_time = strtotime($time);
        }
        // magic number -2209078800 is strtotime( '1900-01-00 00:00:00' ). Needed to achieve parity with strptime
        return ( $match && false !== $valid_time && -2209078800 <= $valid_time ) ? true : false;
    }
    /**
     * Return translated associative array of all possible subscription periods.
     * @param int (optional) An interval in the range 1-6
     * @param string (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
     */
    public function hf_get_subscription_period_strings($number = 1, $period = '') {
        $translated_periods = apply_filters('woocommerce_subscription_periods', array(
            // translators: placeholder is number of days. (e.g. "Bill this every day / 4 days")
            'day' => sprintf(_nx('day', '%s days', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number),
            // translators: placeholder is number of weeks. (e.g. "Bill this every week / 4 weeks")
            'week' => sprintf(_nx('week', '%s weeks', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number),
            // translators: placeholder is number of months. (e.g. "Bill this every month / 4 months")
            'month' => sprintf(_nx('month', '%s months', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number),
            // translators: placeholder is number of years. (e.g. "Bill this every year / 4 years")
            'year' => sprintf(_nx('year', '%s years', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number),
                )
        );

        return (!empty($period) ) ? $translated_periods[$period] : $translated_periods;
    }
    
    public static function wt_get_order_with_import_key($id){
        global $wpdb;
        
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT po.ID FROM {$wpdb->posts} AS po
            INNER JOIN {$wpdb->postmeta} AS pm
            ON po.ID = pm.post_id
            WHERE po.post_type = 'shop_order'
            AND pm.meta_key = '_wt_import_key'
            AND pm.meta_value = %d",$id
        ));
        return $order_id;
    }

    /**
     * Return an array of subscription status types, similar to @see wc_get_order_statuses()
     * @return array
     */
    public function hf_get_subscription_statuses() {
        $subscription_statuses = array(
            'wc-pending' => _x('Pending', 'Subscription status', 'woocommerce-subscriptions'),
            'wc-active' => _x('Active', 'Subscription status', 'woocommerce-subscriptions'),
            'wc-on-hold' => _x('On hold', 'Subscription status', 'woocommerce-subscriptions'),
            'wc-cancelled' => _x('Cancelled', 'Subscription status', 'woocommerce-subscriptions'),
            'wc-switched' => _x('Switched', 'Subscription status', 'woocommerce-subscriptions'),
            'wc-expired' => _x('Expired', 'Subscription status', 'woocommerce-subscriptions'),
            'wc-pending-cancel' => _x('Pending Cancellation', 'Subscription status', 'woocommerce-subscriptions'),
        );
        return apply_filters('hf_subscription_statuses', $subscription_statuses);
    }

    /**
     * Import tax lines
     * @param WC_Subscription $subscription
     * @param array $data
     */
    public static function add_taxes($subscription, $data) {
        global $wpdb;
        $tax_items = explode(';', $data['tax_items']);
        $chosen_tax_rate_id = 0;
        if (!empty($tax_items)) {
            foreach ($tax_items as $tax_item) {
                $tax_data = array();

                if (false !== strpos($tax_item, ':')) {
                    foreach (explode('|', $tax_item) as $item) {
                        list( $name, $value ) = explode(':', $item);
                        $tax_data[trim($name)] = trim($value);
                    }
                } elseif (1 == count($tax_items)) {
                    if (is_numeric($tax_item)) {
                        $tax_data['rate_id'] = $tax_item;
                    } else {
                        $tax_data['code'] = $tax_item;
                    }
                }

                if (!empty($tax_data['rate_id'])) {
                    $tax_rate = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %s", $tax_data['rate_id']));
                } elseif (!empty($tax_data['code'])) {
                    $tax_rate = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_name = %s ORDER BY tax_rate_priority LIMIT 1", $tax_data['code']));
                } else {
                    $result['warning'][] = esc_html__(sprintf('Missing tax code or ID from column: %s', $data['tax_items']));
                }

                if (!empty($tax_rate)) {

                    $tax_rate = array_pop($tax_rate);
                    if (WC()->version > '2.7.0') {
                        foreach ($data['post_meta'] as $key_main => $valuemain) {
                            if ($valuemain['key'] == '_order_shipping_tax')
                                $temp_order_shipping_tax = $valuemain['value'];
                            if ($valuemain['key'] == '_order_tax')
                                $temp_order_tax_total = $valuemain['value'];
                        }
                        $tax = new WC_Order_Item_Tax();
                        $tax->set_props(array(
                            'rate_id' => $tax_rate->tax_rate_id,
                            'tax_total' => (!empty($temp_order_tax_total) ? $temp_order_tax_total : 0 ),
                            'shipping_tax_total' => (!empty($temp_order_shipping_tax) ? $temp_order_shipping_tax : 0 ),
                        ));
                        $tax->set_rate($tax_rate->tax_rate_id);
                        $tax->set_order_id($subscription->get_id());
                        $tax->save();
                        $subscription->add_item($tax);
                        $tax_id = $tax->get_id();
                    }
                    else {
                        $tax_id = $subscription->add_tax($tax_rate->tax_rate_id, (!empty($data['order_shipping_tax']) ) ? $data['order_shipping_tax'] : 0, (!empty($data['order_tax']) ) ? $data['order_tax'] : 0 );
                    }
                    if (!$tax_id) {
                        $result['warning'][] = esc_html__('Tax line item could not properly be added to this subscription. Please review this subscription.');
                    } else {
                        $chosen_tax_rate_id = $tax_rate->tax_rate_id;
                    }
                } else {
                    $result['warning'][] = esc_html__(sprintf('The tax code "%s" could not be found in your store.', $tax_data['code']));
                }
            }
        }
        return $chosen_tax_rate_id;
    }
    
    /**
     * Function to write in the woocommerce log file
     */
    public function hf_log_data_change($content = 'hf-subscription-csv-import', $data = '') {

        Wt_Import_Export_For_Woo_Logwriter::write_log($this->parent_module->module_base, 'import', $data);
    }
    
    public function delete_existing() {
    
        $posts = new WP_Query([
            'post_type' => $this->post_type,
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            'meta_query' => [
                [
                    'key' => '_wt_delete_existing',
                    'compare' => 'NOT EXISTS',
                ]
            ]
        ]);
               
        foreach ($posts->posts as $post) {
            $this->import_results['detele_results'][$post] = wp_trash_post($post);
        }
        
        
        $posts = new WP_Query([
            'post_type' => $this->post_type,
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            'meta_query' => [
                [
                    'key' => '_wt_delete_existing',
                    'compare' => 'EXISTS',
                ]
            ]
        ]);        
        foreach ($posts->posts as $post) {
            delete_post_meta($post,'_wt_delete_existing');
        }
                               
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
