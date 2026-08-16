<?php

class WCFM_RnB_Inventory_Manage_Controller {
    public function __construct() {
        $this->processing();
    }

    public function processing() {
        $form_data = array();
        $status = '';

        // Parse the serialized form data into an array
        if (isset($_POST['form_data'])) {
            parse_str($_POST['form_data'], $form_data);

            $form_data = wc_clean($form_data);
        }

        if (isset($_POST['status']) && wc_clean($_POST['status']) === 'draft') {
            $status = 'draft';
        } else {
            $status = 'publish';
        }

        // Check if nonce field present
        if (!isset($form_data['wcfm_nonce']) || empty($form_data['wcfm_nonce'])) {
            wp_send_json([
                'status'    => false,
                'message'   => __('Nonce field missing. Please contact admin.', 'wc-frontend-manager-ultimate')
            ]);
        }

        // Nonce verification
        if (!wp_verify_nonce($form_data['wcfm_nonce'], 'wcfm_rnb_inventory_manage')) {
            wp_send_json([
                'status'    => false,
                'message'   => __('Invalid nonce! Refresh your page and try again.', 'wc-frontend-manager-ultimate')
            ]);
        }

        $title = isset($form_data['title']) ? $form_data['title'] : '';

        // Prepare Inventory Data
        $inventory_id = isset($form_data['inventory_id']) ? absint($form_data['inventory_id']) : 0;

        $inventory_data = [
            'post_title' => $title
        ];

        if ($status) $inventory_data['post_status'] = $status;

        if ($inventory_id) {
            $inventory_data['ID'] = $inventory_id;
        } else {
            $inventory_data['post_type'] = 'inventory';
        }

        // Update/Add Inventory
        $result = $inventory_id ? wp_update_post($inventory_data) : wp_insert_post($inventory_data);

        // Update/Add Inventory failed
        if (is_wp_error($result)) {
            wp_send_json([
                'status'    => false,
                'message'   => __('!!! Error. Can not Add/Update inventory. Please contact admin.', 'wc-frontend-manager-ultimate')
            ]);
        }

        $inventory_id = $result;

        // Save featured image
        if (isset($form_data['featured_img']) && !empty($form_data['featured_img'])) {
            $attachment_id = absint($form_data['featured_img']);
            set_post_thumbnail($inventory_id, $attachment_id);
        }

        // Save Inventory meta
        $this->save_post_meta($inventory_id, $form_data);

        // Save the taxonomies
        if (isset($form_data['product_custom_taxonomies']) && is_array($form_data['product_custom_taxonomies'])) {
            $this->save_custom_taxonomies($inventory_id, $form_data['product_custom_taxonomies']);
        }

        // Save product availabilities
        if (isset($form_data['product_date_availabilities_fields']) && is_array($form_data['product_date_availabilities_fields'])) {
            $this->save_product_availabilities($inventory_id, $form_data['product_date_availabilities_fields']);
        }

        wp_send_json([
            'status'    => true,
            'message'   => __('Inventory Successfully Saved.', 'wc-frontend-manager-ultimate'),
            'id'        => $inventory_id,
            'redirect'  => esc_url(get_wcfm_edit_rnb_inventory_url($inventory_id))
        ]);
    }

    protected function save_post_meta($post_id, $form_data) {
        if (isset($form_data['quantity'])) {
            update_post_meta($post_id, 'quantity', $form_data['quantity']);
        }

        if (isset($form_data['pricing_type'])) {
            update_post_meta($post_id, 'pricing_type', $form_data['pricing_type']);
        }

        if (isset($form_data['distance_unit_type'])) {
            update_post_meta($post_id, 'distance_unit_type', $form_data['distance_unit_type']);
        }

        if (isset($form_data['perkilo_price'])) {
            update_post_meta($post_id, 'perkilo_price', $form_data['perkilo_price']);
        }

        //Handle hourly pricing
        if (isset($form_data['hourly_pricing_type'])) {
            update_post_meta($post_id, 'hourly_pricing_type', $form_data['hourly_pricing_type']);
        }

        if (isset($form_data['hourly_price'])) {
            update_post_meta($post_id, 'hourly_price', $form_data['hourly_price']);
        }

        $hourly_range_cost = array();
        if (isset($form_data['hourly_range_field']) && is_array($form_data['hourly_range_field'])) {
            foreach ($form_data['hourly_range_field'] as $key => $value) {
                $hourly_range_cost[$key] = [
                    'min_hours' => $value['min_hours'],
                    'max_hours' => $value['max_hours'],
                    'range_cost' => $value['range_cost'],
                    'cost_applicable' => isset($value['cost_applicable']) && !empty($value['cost_applicable']) ? $value['cost_applicable'] : 'per_hour'
                ];
            }
        }

        if (isset($hourly_range_cost)) {
            update_post_meta($post_id, 'redq_hourly_ranges_cost', $hourly_range_cost);
        }

        if (isset($form_data['general_price'])) {
            update_post_meta($post_id, 'general_price', $form_data['general_price']);
        }

        $redq_daily_pricing = array();
        $redq_monthly_pricing = array();
        if (isset($form_data['friday_price'])) {
            $redq_daily_pricing['friday'] = $form_data['friday_price'];
        }

        if (isset($form_data['saturday_price'])) {
            $redq_daily_pricing['saturday'] = $form_data['saturday_price'];
        }

        if (isset($form_data['sunday_price'])) {
            $redq_daily_pricing['sunday'] = $form_data['sunday_price'];
        }

        if (isset($form_data['monday_price'])) {
            $redq_daily_pricing['monday'] = $form_data['monday_price'];
        }

        if (isset($form_data['tuesday_price'])) {
            $redq_daily_pricing['tuesday'] = $form_data['tuesday_price'];
        }

        if (isset($form_data['wednesday_price'])) {
            $redq_daily_pricing['wednesday'] = $form_data['wednesday_price'];
        }

        if (isset($form_data['thursday_price'])) {
            $redq_daily_pricing['thursday'] = $form_data['thursday_price'];
        }

        update_post_meta($post_id, 'redq_daily_pricing', $redq_daily_pricing);

        if (isset($form_data['january_price'])) {
            $redq_monthly_pricing['january'] = $form_data['january_price'];
        }

        if (isset($form_data['february_price'])) {
            $redq_monthly_pricing['february'] = $form_data['february_price'];
        }

        if (isset($form_data['march_price'])) {
            $redq_monthly_pricing['march'] = $form_data['march_price'];
        }

        if (isset($form_data['april_price'])) {
            $redq_monthly_pricing['april'] = $form_data['april_price'];
        }

        if (isset($form_data['may_price'])) {
            $redq_monthly_pricing['may'] = $form_data['may_price'];
        }

        if (isset($form_data['june_price'])) {
            $redq_monthly_pricing['june'] = $form_data['june_price'];
        }

        if (isset($form_data['july_price'])) {
            $redq_monthly_pricing['july'] = $form_data['july_price'];
        }

        if (isset($form_data['august_price'])) {
            $redq_monthly_pricing['august'] = $form_data['august_price'];
        }

        if (isset($form_data['september_price'])) {
            $redq_monthly_pricing['september'] = $form_data['september_price'];
        }

        if (isset($form_data['october_price'])) {
            $redq_monthly_pricing['october'] = $form_data['october_price'];
        }

        if (isset($form_data['november_price'])) {
            $redq_monthly_pricing['november'] = $form_data['november_price'];
        }

        if (isset($form_data['december_price'])) {
            $redq_monthly_pricing['december'] = $form_data['december_price'];
        }

        update_post_meta($post_id, 'redq_monthly_pricing', $redq_monthly_pricing);


        // Day ranges data save
        $days_range_cost = array();
        if (isset($form_data['days_range_field']) && is_array($form_data['days_range_field'])) {
            foreach ($form_data['days_range_field'] as $key => $value) {
                $days_range_cost[$key] = [
                    'min_days' => $value['min_days'],
                    'max_days' => $value['max_days'],
                    'range_cost' => $value['range_cost'],
                    'cost_applicable' => isset($value['cost_applicable']) && !empty($value['cost_applicable']) ? $value['cost_applicable'] : 'per_day'
                ];
            }
        }
        if (isset($days_range_cost)) {
            update_post_meta($post_id, 'redq_day_ranges_cost', $days_range_cost);
        }
    }

    protected function save_custom_taxonomies($post_id, $taxonomies) {
        if (apply_filters('wcfm_is_allow_rnb_inventory_custom_taxonomy', true)) {
            if (!empty($taxonomies)) {
                foreach ($taxonomies as $taxonomy => $terms) {
                    if (!empty($terms)) {
                        $is_first = true;
                        foreach ($terms as $term) {
                            if ($is_first) {
                                $is_first = false;
                                wp_set_object_terms($post_id, (int)$term, $taxonomy);
                            } else {
                                wp_set_object_terms($post_id, (int)$term, $taxonomy, true);
                            }
                        }
                    } else {
                        if (apply_filters('wcfm_is_allow_reset_' . $taxonomy, true)) {
                            wp_delete_object_term_relationships($post_id, $taxonomy);
                        }
                    }
                }
            }
        }
    }

    protected function save_product_availabilities($post_id, $availabilities) {
        $post_type = get_post_type($post_id);

        if ($post_type !== 'inventory') {
            return;
        }

        global $wpdb;

        $tablename       = $wpdb->prefix . 'rnb_availability';
        $update_payloads = [];
        $payloads        = [];

        $old_availabilities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tablename} WHERE inventory_id = %d AND block_by = %s",
                $post_id,
                'CUSTOM'
            ),
            ARRAY_A
        );

        $old_row_ids = wp_list_pluck($old_availabilities, 'id');
        $current_row_ids = [];

        foreach ( $availabilities as $availability ) {
            if (!empty($availability['row_id'])) {
                $current_row_ids[] = $availability['row_id'];
                $update_payloads[] = [
                    'id'                => $availability['row_id'],      
                    'block_by'          => $availability['block_by'],
                    'pickup_datetime'   => date("Y-m-d H:i", strtotime($availability['pickup_datetime'])),
                    'return_datetime'   => date("Y-m-d H:i", strtotime($availability['return_datetime'])),  
                    'rental_duration'   => rnb_calculate_date_difference($availability['pickup_datetime'],  $availability['return_datetime']),  
                ];
            } else {
                // CREATE
                $payloads[] = [
                    'block_by'          => $availability['block_by'],
                    'pickup_datetime'   => date("Y-m-d H:i", strtotime($availability['pickup_datetime'])),
                    'return_datetime'   => date("Y-m-d H:i", strtotime($availability['return_datetime'])),
                    'rental_duration'   => rnb_calculate_date_difference($availability['pickup_datetime'], $availability['return_datetime']),
                    'inventory_id'      => $post_id,
                ];
            }
        }

        //Update Existing Rows
        if (count($update_payloads)) {
            foreach ($update_payloads as $payload) {
                $row_id = $payload['id'];
                unset($payload['id']);
                $wpdb->update($tablename, $payload, ['id' => $row_id]);
            }
        }

        //Delete rows
        $row_to_delete = array_diff( $old_row_ids, $current_row_ids );
        if (!empty($row_to_delete) && is_array($row_to_delete) && count($row_to_delete) > 0) {
            foreach ($row_to_delete as $id) {
                $wpdb->delete($tablename, array('id' => $id));
            }
        }

        //Create rows
        $products_by_inventory = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rnb_inventory_product WHERE inventory = $post_id", ARRAY_A);
        if (isset($products_by_inventory) && !empty($products_by_inventory)) {
            foreach ($products_by_inventory as $key => $product_by_inventory) {
                $product_id = $product_by_inventory['product'];

                if (!count($payloads)) {
                    continue;
                }

                $values = $place_holders = [];

                foreach ($payloads as $data) {
                    array_push(
                        $values, 
                        $data['block_by'], 
                        $data['pickup_datetime'],
                        $data['return_datetime'],
                        $data['rental_duration'], 
                        $data['inventory_id'], 
                        $product_id, 
                        null, 
                        null
                    );
                    $place_holders[] = "( %s, %s, %s, %s, %d, %d, %d, %d)";
                }

                rnb_custom_date_insert($place_holders, $values);
            }
        }
    }
}
