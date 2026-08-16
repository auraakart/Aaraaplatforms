<?php

if (!defined('WPINC')) {
    exit;
}

if(!class_exists('Wt_Import_Export_For_Woo_User_Basic_User_Export')){
class Wt_Import_Export_For_Woo_User_Basic_User_Export {

    public $parent_module = null;
	

	public function __construct($parent_object) {

        $this->parent_module = $parent_object;
    }

    public function prepare_header() {

        $export_columns = $this->parent_module->get_selected_column_names();

        return apply_filters('hf_csv_customer_post_columns', $export_columns);
    }

    /**
     * Prepare data that will be exported.
     */
    public function prepare_data_to_export($form_data, $batch_offset) {
        
        $export_user_roles = !empty($form_data['filter_form_data']['wt_iew_roles']) ? $form_data['filter_form_data']['wt_iew_roles'] : array();
        $export_sortby = !empty($form_data['filter_form_data']['wt_iew_sort_columns']) ? $form_data['filter_form_data']['wt_iew_sort_columns'] : array('user_login');
        $export_sort_order = !empty($form_data['filter_form_data']['wt_iew_order_by']) ? $form_data['filter_form_data']['wt_iew_order_by'] : 'ASC';
        $user_ids = !empty($form_data['filter_form_data']['wt_iew_email']) ? $form_data['filter_form_data']['wt_iew_email'] : array(); // user email fields return user ids
        $export_start_date = !empty($form_data['filter_form_data']['wt_iew_date_from']) ? $form_data['filter_form_data']['wt_iew_date_from'] : '';
        $export_end_date = !empty($form_data['filter_form_data']['wt_iew_date_to']) ? $form_data['filter_form_data']['wt_iew_date_to'] : '';

		$v_export_guest_user = ( !empty( $form_data['advanced_form_data']['wt_iew_export_guest_user'] ) && ( 'Yes' === $form_data['advanced_form_data']['wt_iew_export_guest_user'] || $form_data['advanced_form_data']['wt_iew_export_guest_user'] == 1 ) ) ? true : false;
		
        $export_limit = !empty($form_data['filter_form_data']['wt_iew_limit']) ? intval($form_data['filter_form_data']['wt_iew_limit']) : 999999999; //user limit
        $current_offset = !empty($form_data['filter_form_data']['wt_iew_offset']) ? intval($form_data['filter_form_data']['wt_iew_offset']) : 0; //user offset
        $batch_count = !empty($form_data['advanced_form_data']['wt_iew_batch_count']) ? $form_data['advanced_form_data']['wt_iew_batch_count'] : Wt_Import_Export_For_Woo_User_Basic_Common_Helper::get_advanced_settings('default_export_batch');
        

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

            $sortby_check = array_intersect($export_sortby, array('ID', 'user_registered', 'user_email', 'user_login', 'user_nicename'));

            // Build common args array
            $args = array(
                'fields' => 'ID', // exclude standard wp_users fields from get_users query -> get Only ID##
                'role__in' => $export_user_roles, //An array of role names. Matched users must have at least one of these roles. Default empty array.
                'number' => $limit,
                'offset' => $real_offset,
                'order' => $export_sort_order,
                'date_query' => array(
                    array(
                        'after' => $export_start_date,
                        'before' => $export_end_date,
                        'inclusive' => true
                    )),
            );

            if ( empty( $sortby_check ) ) {
                $args['orderby'] = 'meta_value';
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                $args['meta_key'] = $export_sortby[0];
            } else {
                $args['orderby'] = $export_sortby; 
            }

            if (!empty($user_ids)) {
                $args['include'] = $user_ids;
            }

            $is_woocommerce_active = class_exists( 'WooCommerce' );
            $user_export_cache_key = 'wt_iew_user_export_ids_' . md5( wp_json_encode( $form_data ) );
            $cached_user_ids = get_transient( $user_export_cache_key );
            $total_item_args = null;
            $total_record_count = null;

            if ( false !== $cached_user_ids && is_array( $cached_user_ids ) ) {
                $batch_ids = array_slice( $cached_user_ids, $batch_offset, $limit );
                $users = ! empty( $batch_ids ) ? $batch_ids : array();
            } else {
                // Only replace $args when the filter returns a valid array (avoids wiping query args on null/false).
                $args_filtered = apply_filters( 'wt_iew_user_export_args', $args, $form_data );
                if ( is_array( $args_filtered ) ) {
                    $args = $args_filtered;
                }
                $total_item_args = array_merge(
                    $args,
                    array(
                        'fields' => 'ids',
                        'number' => $export_limit,
                        'offset' => $current_offset,
                    )
                );
                $total_record_count = get_users( $total_item_args );
                set_transient( $user_export_cache_key, $total_record_count, HOUR_IN_SECONDS );
                $batch_ids = array_slice( $total_record_count, $batch_offset, $limit );
                $users = ! empty( $batch_ids ) ? $batch_ids : array();
            }

            // Registered-user total for this export (same every batch while ID cache exists; recomputed on cache miss).
            $total_records = 0;
            if ( false !== $cached_user_ids && is_array( $cached_user_ids ) ) {
                $total_records = count( $cached_user_ids );
            } elseif ( isset( $total_record_count ) && is_array( $total_record_count ) ) {
                $total_records = count( $total_record_count );
            }

            $users_by_id = array();
            $user_ids_for_load = array();
            if ( ! empty( $users ) ) {
                $user_ids_for_load = array_values(
                    array_map(
                        function( $u ) {
                            return is_object( $u ) && isset( $u->ID ) ? (int) $u->ID : (int) $u;
                        },
                        $users
                    )
                );
                $user_ids_for_load = array_filter( array_unique( $user_ids_for_load ) );
                $bulk_load_threshold = 200;
                if ( ! empty( $user_ids_for_load ) && count( $user_ids_for_load ) <= $bulk_load_threshold ) {
                    $loaded_users = get_users(
                        array(
                            'include' => $user_ids_for_load,
                            'orderby' => 'include',
                        )
                    );
                    foreach ( $loaded_users as $u ) {
                        $users_by_id[ (int) $u->ID ] = $u;
                    }
                }
            }

            $order_stats = array();
            if ( $is_woocommerce_active && ! empty( $user_ids_for_load ) ) {
                $order_stats = $this->get_customer_order_stats_batch( $user_ids_for_load );
            }

            // Loop users (use pre-loaded $users_by_id and $order_stats when available).
            foreach ( $users as $user ) {
                $user_id = is_object( $user ) ? (int) $user->ID : (int) $user;
                $user_obj = isset( $users_by_id[ $user_id ] ) ? $users_by_id[ $user_id ] : null;
                $user_stats = isset( $order_stats[ $user_id ] ) ? $order_stats[ $user_id ] : null;
                $data = self::get_customers_csv_row( $user_id, $user_obj, $user_stats );
                $data_array[] = apply_filters( 'hf_customer_csv_exclude_admin', $data );
            }
			
			$is_last_offset = false;
			$last_batch_count = $real_offset + $batch_count;
			// $total_records is derived from the ID list every batch — no separate progress transient needed.
			if ( $last_batch_count >= $total_records ) {
				$is_last_offset = true;
				delete_transient( $user_export_cache_key );
			}
			if($is_last_offset) //last batch
			{
			if ($v_export_guest_user) {
                $query_args = array(
                    'fields' => 'ids',
                    'post_type' => 'shop_order',
                    'post_status' => 'any',
                    'posts_per_page' => -1,
                );
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                $query_args['meta_query'] = array(array( // @codingStandardsIgnoreLine
                        'key' => '_customer_user',
                        'value' => 0,
                        'compare' => '',
                ));
                $query = new WP_Query($query_args);
                $order_ids = $query->posts;

                $guest_orders = wc_get_orders(array(
                    'type' => 'shop_order', 
                    'customer_id' => 0, 
                    'return' => 'ids'
                ));			
                $order_ids = array_merge($order_ids,  $guest_orders );
                
                $guest_email_list = array();
                foreach ($order_ids as $order_id) {
                    $order = new WC_Order($order_id);
					if($order->get_billing_email()){
						$user = get_user_by('email', $order->get_billing_email());
						if (!isset($user->ID)) {
							if(!in_array($order->get_billing_email(), $guest_email_list)){
								$data = self::get_guest_customers_csv_row($order);
								$data_array[] = apply_filters('hf_customer_csv_exclude_admin', $data);
								$guest_email_list[] = $order->get_billing_email();
							}
						}
					}
                }
            }
			}
			

            $return['total'] = $total_records;
            $return['data'] = $data_array;
            if( 0 == $batch_offset && 0 == $total_records ){
				$return['no_post'] = __( 'Nothing to export under the selected criteria.', 'users-customers-import-export-for-wp-woocommerce' );
		    }
            return $return;
        }
        return array( 'total' => 0, 'data' => array() );
    }

    /**
     * Get order count and total spent for a batch of user IDs (one or two queries instead of N).
     *
     * @param int[] $user_ids User IDs.
     * @return array<int, array{order_count: int, total_spent: float}>
     */
    protected function get_customer_order_stats_batch( array $user_ids ) {
        global $wpdb;
        $user_ids = array_map( 'absint', array_filter( $user_ids ) );
        if ( empty( $user_ids ) ) {
            return array();
        }

        $stats = array();
        foreach ( $user_ids as $id ) {
            $stats[ $id ] = array( 'order_count' => 0, 'total_spent' => 0.0 );
        }

        $placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

        $use_hpos = $this->is_hpos_in_use();

        if ( $use_hpos ) {
            $this->fill_order_stats_from_hpos( $stats, $user_ids, $placeholders );
        } else {
            $this->fill_order_stats_from_posts( $stats, $user_ids, $placeholders );
        }

        return $stats;
    }

    /**
     * @return bool
     */
    protected function is_hpos_in_use() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return false;
        }
        if ( ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
            return false;
        }
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * @param array  $stats        Stats array to fill (by reference).
     * @param int[]  $user_ids     User IDs.
     * @param string $placeholders Prepared statement placeholders for user_ids.
     */
    protected function fill_order_stats_from_hpos( &$stats, $user_ids, $placeholders ) {
        global $wpdb;

        if ( ! class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
            return;
        }

        $orders_table = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $order_count_sql = $wpdb->prepare(
            "SELECT customer_id AS user_id, COUNT(*) AS order_count
             FROM {$orders_table}
             WHERE type = 'shop_order'
               AND customer_id IN ({$placeholders})
             GROUP BY customer_id",
            $user_ids
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $counts = $wpdb->get_results( $order_count_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $counts ) {
            foreach ( $counts as $row ) {
                $uid = (int) $row['user_id'];
                if ( isset( $stats[ $uid ] ) ) {
                    $stats[ $uid ]['order_count'] = (int) $row['order_count'];
                }
            }
        }

        $paid_statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'completed', 'processing' );
        if ( empty( $paid_statuses ) ) {
            return;
        }
        $paid_statuses = array_map(
            static function( $s ) {
                return ( 0 === strpos( $s, 'wc-' ) ) ? $s : 'wc-' . $s;
            },
            $paid_statuses
        );
        $status_placeholders = implode( ',', array_fill( 0, count( $paid_statuses ), '%s' ) );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $total_spent_sql = $wpdb->prepare(
            "SELECT customer_id AS user_id, SUM(total_amount) AS total_spent
             FROM {$orders_table}
             WHERE type = 'shop_order'
               AND customer_id IN ({$placeholders})
               AND status IN ({$status_placeholders})
             GROUP BY customer_id",
            array_merge( $user_ids, $paid_statuses )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $totals = $wpdb->get_results( $total_spent_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $totals ) {
            foreach ( $totals as $row ) {
                $uid = (int) $row['user_id'];
                if ( isset( $stats[ $uid ] ) ) {
                    $stats[ $uid ]['total_spent'] = (float) $row['total_spent'];
                }
            }
        }
    }

    /**
     * @param array  $stats        Stats array to fill (by reference).
     * @param int[]  $user_ids     User IDs.
     * @param string $placeholders Prepared statement placeholders for user_ids.
     */
    protected function fill_order_stats_from_posts( &$stats, $user_ids, $placeholders ) {
        global $wpdb;

        $posts_table = $wpdb->posts;
        $meta_table  = $wpdb->postmeta;

        $order_count_from_stats = false;

        /**
         * Use wp_wc_order_stats for batched order counts.
         *
         * @param bool $use_stats Default true.
         */
        $use_wc_order_stats = apply_filters( 'wt_iew_use_wc_order_stats_for_export_order_count', true );

        if ( $use_wc_order_stats ) {

            $order_stats_table = $wpdb->prefix . 'wc_order_stats';

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $order_count_sql = $wpdb->prepare(
                "SELECT customer_id AS user_id, COUNT(*) AS order_count
                 FROM {$order_stats_table}
                 WHERE customer_id IN ({$placeholders})
                   AND parent_id = 0
                 GROUP BY customer_id",
                $user_ids
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

            $counts = $wpdb->get_results( $order_count_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ( null !== $counts ) {
                foreach ( $counts as $row ) {
                    $uid = (int) $row['user_id'];
                    if ( isset( $stats[ $uid ] ) ) {
                        $stats[ $uid ]['order_count'] = (int) $row['order_count'];
                    }
                }
                $order_count_from_stats = true;
            }
        }

        if ( ! $order_count_from_stats ) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $order_count_sql = $wpdb->prepare(
                "SELECT pm.meta_value AS user_id, COUNT(DISTINCT pm.post_id) AS order_count
                 FROM {$meta_table} pm
                 INNER JOIN {$posts_table} p
                    ON p.ID = pm.post_id
                   AND p.post_type IN ('shop_order','shop_order_placehold')
                 WHERE pm.meta_key = '_customer_user'
                   AND pm.meta_value IN ({$placeholders})
                 GROUP BY pm.meta_value",
                $user_ids
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $counts = $wpdb->get_results( $order_count_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $counts ) {
                foreach ( $counts as $row ) {
                    $uid = (int) $row['user_id'];
                    if ( isset( $stats[ $uid ] ) ) {
                        $stats[ $uid ]['order_count'] = (int) $row['order_count'];
                    }
                }
            }
        }

        $paid_statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'completed', 'processing' );
        if ( empty( $paid_statuses ) ) {
            return;
        }
        $paid_statuses = array_map(
            static function( $s ) {
                return ( 0 === strpos( $s, 'wc-' ) ) ? $s : 'wc-' . $s;
            },
            $paid_statuses
        );
        $status_placeholders = implode( ',', array_fill( 0, count( $paid_statuses ), '%s' ) );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $total_spent_sql = $wpdb->prepare(
            "SELECT pm_customer.meta_value AS user_id,
                    SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) AS total_spent
             FROM {$meta_table} pm_customer
             INNER JOIN {$posts_table} p
                ON p.ID = pm_customer.post_id
               AND p.post_type IN ('shop_order','shop_order_placehold')
             INNER JOIN {$meta_table} pm_total
                ON pm_total.post_id = p.ID
               AND pm_total.meta_key = '_order_total'
             WHERE pm_customer.meta_key = '_customer_user'
               AND pm_customer.meta_value IN ({$placeholders})
               AND p.post_status IN ({$status_placeholders})
             GROUP BY pm_customer.meta_value",
            array_merge( $user_ids, $paid_statuses )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $totals = $wpdb->get_results( $total_spent_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $totals ) {
            foreach ( $totals as $row ) {
                $uid = (int) $row['user_id'];
                if ( isset( $stats[ $uid ] ) ) {
                    $stats[ $uid ]['total_spent'] = (float) $row['total_spent'];
                }
            }
        }
    }

    public function get_customers_csv_row( $id, $user = null, $order_stats = null ) {
        global $wpdb;
        $csv_columns = $this->parent_module->get_selected_column_names();
        $is_woocommerce_active = class_exists( 'WooCommerce' );

        if ( null === $user || ! ( $user instanceof WP_User ) ) {
            $user = get_user_by( 'id', $id );
        }
        if ( ! $user ) {
            return array();
        }

        $row_order_count = null;
        $row_total_spent = null;
        if ( $is_woocommerce_active ) {
            $use_default_wc_functions = apply_filters( 'wt_iew_use_woocommerce_order_stats_functions', false );
            if ( is_array( $order_stats ) && isset( $order_stats['order_count'], $order_stats['total_spent'] ) && ! $use_default_wc_functions ) {
                $row_order_count = (int) $order_stats['order_count'];
                $row_total_spent = (float) $order_stats['total_spent'];
            } elseif ( ! empty( $user->ID ) ) {
                $row_order_count = wc_get_customer_order_count( $user->ID );
                $row_total_spent = (float) wc_get_customer_total_spent( $user->ID );
            } else {
                $row_order_count = 0;
                $row_total_spent = 0.0;
            }
        }

        $customer_data = array();

        foreach ($csv_columns as $key => $value) {

            $key = trim(str_replace('meta:', '', $key));
            if ($key == 'roles') {
                $user_roles = (!empty($user->roles)) ? $user->roles : array();
                $customer_data['roles'] = implode(', ', $user_roles);
                continue;
            }            
            if ($key == 'customer_id') {
                $customer_data[$key] = !empty($user->ID) ? $user->ID : '';
                continue;
            }             
            if ($key == 'session_tokens') {
                $raw_data = wp_unslash($user->{$key});
			    $unserialized_data = is_array($raw_data) ? 
				array_map(function($item) {
					return is_string($item) ? json_decode($item, true) : $item;
				}, $raw_data) :  json_decode($raw_data, true); 
                
                $customer_data[$key] = !empty($user->{$key}) ? base64_encode(json_encode($unserialized_data)) : '';
                continue;
            }
            if ( $is_woocommerce_active ) {
                if ( 'orders' === $key ) {
                    $customer_data[ $key ] = null !== $row_order_count ? $row_order_count : 0;
                    continue;
                }
                if ( 'total_spent' === $key ) {
                    $customer_data[ $key ] = null !== $row_total_spent ? $row_total_spent : 0.00;
                    continue;
                }
                if ( 'aov' === $key ) {
                    $cnt = null !== $row_order_count ? $row_order_count : 0;
                    $sum = null !== $row_total_spent ? $row_total_spent : 0.0;
                    $customer_data[ $key ] = $cnt ? round( $sum / $cnt, 2 ) : 0.00;
                    continue;
                }
            } else {
                if ( in_array( $key, array( 'orders', 'total_spent', 'aov' ), true ) ) {
                    $customer_data[ $key ] = 0;
                    continue;
                }
            }	            
            if($key == $wpdb->prefix.'user_level'){
                $customer_data[$key] = (!empty($user->{$key})) ? $user->{$key} : 0;
                continue;
            }
            if( $key == 'last_update'){
                $date_in_timestamp = (!empty($user->{$key})) ? $user->{$key} : 0;
                if($date_in_timestamp == 0){
                    $customer_data[$key] = '';
                }
                elseif(strtotime($date_in_timestamp) == false){
                    $customer_data[$key] = gmdate('Y-m-d H:i:s', $date_in_timestamp);
                }else{
                    $customer_data[$key] = $date_in_timestamp ? gmdate( 'Y-m-d', $date_in_timestamp ) : $date_in_timestamp;
                }
                continue;
            }
            if($key == 'wc_last_active'){
                $date_in_timestamp = (!empty($user->{$key})) ? $user->{$key} : 0;
                if($date_in_timestamp == 0){
                    $customer_data[$key] = '';
                }
                elseif(strtotime($date_in_timestamp) ==false){
                    $customer_data[$key] = gmdate('Y-m-d', $date_in_timestamp);
                }else{
                    $customer_data[$key] = $date_in_timestamp ? gmdate( 'Y-m-d', $date_in_timestamp ) : $date_in_timestamp;
                }
                continue;
            }

            if($key == 'is_guest_user'){
                $customer_data[$key] = 0;
                continue;
            }
            $customer_data[$key] = isset($user->{$key}) ? $user->{$key} : '';
        }
        /*
         * CSV Customer Export Row.
         * Filter the individual row data for the customer export
         * @since 3.0
         * @param array $customer_data 
         */
        return apply_filters('hf_customer_csv_export_data', $customer_data, $csv_columns);
    }

	/**
	 * CSV Guest Customer Export Row
	 *
	 * @param WC_Order $order Order object.
	 * @return array $customer_data
	 */
    public function get_guest_customers_csv_row($order) {
        $customer_data = array();
        $csv_columns = $this->parent_module->get_selected_column_names();
        $key_array = array('user_email', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_email', 'billing_phone', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_state', 'billing_country', 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_phone', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_method', 'is_guest_user', 'roles');
        foreach ( $csv_columns as $key ) {
			$data = '';
            if ( in_array( $key, $key_array ) ) {
                if ( 'user_email' === $key ) {
                    $customer_data[$key] = $order->get_billing_email();
                    continue;
                }
                if ( 'is_guest_user' === $key ) {
                    $customer_data['is_guest_user'] = 1;
                    continue;
                }
                if ( 'roles' === $key ) {
                    $customer_data['roles'] = 'customer';
                    continue;
                }
                
                $method_name = "get_{$key}";
				if( is_callable( array( $order, $method_name ) ) ){
					$data = $order->$method_name();
				}
                if ( !empty( $data ) ) {
                    $data =  $order->$method_name() ;
                } else {
                    $data = '';
                }
                $customer_data[$key] = $data;
            } else {
                $customer_data[$key] = '';
            }
        }

        /*
         * CSV Guest Customer Export Row.
         * Filter the individual row data for the Guest customer export
         * @since 3.0
         * @param array $customer_data 
         */
        return apply_filters('wt_guest_customer_export_data', $customer_data, $csv_columns);
    }

}
}
