<?php
/**
 * WCFMu plugin controllers
 *
 * WCFMu Integrations Plugin Products Manage Controller
 *
 * @author  WC Lovers
 * @package wcfmu/controllers
 * @version 2.2.3
 */

class WCFMu_Integrations_Products_Manage_Controller
{


    public function __construct()
    {
        global $WCFM, $WCFMu;

        // Product Manage Third Party Variaton Date Save
        add_filter('wcfm_product_variation_data_factory', [ &$this, 'wcfmu_thirdparty_product_variation_save' ], 100, 5);

        // WP Job Manager - Resume Manager Support - 2.3.4
        if ($wcfm_allow_resume_manager = apply_filters('wcfm_is_allow_resume_manager', true)) {
            if (WCFMu_Dependencies::wcfm_resume_manager_active_check()) {
                // Resume Manager Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wpjrm_product_meta_save' ], 60, 2);
            }
        }

        // YITH Auction Support - 2.3.8
        if ($wcfm_allow_auction = apply_filters('wcfm_is_allow_auction', true)) {
            if (WCFMu_Dependencies::wcfm_yith_auction_active_check()) {
                // YITH Auction Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_yithauction_product_meta_save' ], 70, 2);
            }
        }

        // WooCommerce Simple Auction Support - 2.3.10
        if ($wcfm_allow_auction = apply_filters('wcfm_is_allow_auction', true)) {
            if (WCFMu_Dependencies::wcfm_wcs_auction_active_check()) {
                // WooCommerce Simple Auction Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wcsauction_product_meta_save' ], 70, 2);
            }
        }

        // WC Rental & Booking Support - 2.3.10
        if ($wcfm_allow_rental = apply_filters('wcfm_is_allow_rental', true)) {
            if (WCFMu_Dependencies::wcfm_wc_rental_pro_active_check()) {
                // WC Rental Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wcrental_pro_product_meta_save' ], 80, 2);
            }
        }

        // Woocommerce Box Office Support - 3.3.3
        if ($wcfm_is_allow_wc_box_office = apply_filters('wcfm_is_allow_wc_box_office', true)) {
            if (WCFMu_Dependencies::wcfm_wc_box_office_active_check()) {
                // WC Box Office Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_box_office_product_meta_save' ], 80, 2);
            }
        }

        // WooCommerce Lottery Support - 3.5.0
        if (apply_filters('wcfm_is_allow_lottery', true)) {
            if (WCFMu_Dependencies::wcfm_wc_lottery_active_check()) {
                // WooCommerce Lottery Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_lottery_product_meta_save' ], 70, 2);
            }
        }

        // WooCommerce Deposits Support - 3.5.0
        if (apply_filters('wcfm_is_allow_wc_deposits', true)) {
            if (WCFMu_Dependencies::wcfm_wc_deposits_active_check()) {
                // WooCommerce Deposits Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_deposits_product_meta_save' ], 90, 2);
            }
        }

        // WooCommerce Advanced Product Labels - 6.0.0
        if (apply_filters('wcfm_is_allow_wc_advanced_product_labels', true)) {
            if (WCFMu_Dependencies::wcfm_wc_advanced_product_labels_active_check()) {
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_advanced_product_labels_product_meta_save' ], 120, 2);
            }
        }

        // WooCommerce Whole Sale Support - 6.0.2
        if (WCFMu_Dependencies::wcfm_wholesale_active_check()) {
            // Whole Sale Product Meta Data Save
            add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wholesale_product_meta_save' ], 130, 2);
            add_filter('wcfm_product_variation_data_factory', [ &$this, 'wcfm_product_variation_wholesale_save' ], 130, 5);
        }

        // WC Min/Max Quantities Support - 6.0.2
        if (apply_filters('wcfm_is_allow_wc_min_max_quantities', true)) {
            if (WCFMu_Dependencies::wcfm_wc_min_max_quantities_active_check()) {
                // Whole Sale Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_min_max_quantities_product_meta_save' ], 130, 2);
                add_filter('wcfm_product_variation_data_factory', [ &$this, 'wcfm_product_variation_wc_min_max_quantities_save' ], 130, 5);
            }
        }

        // WooCommerce Variation Swatch - 6.2.7
        if (apply_filters('wcfm_is_allow_wc_variaton_swatch', true)) {
            if (WCFMu_Dependencies::wcfm_wc_variaton_swatch_active_check() && WCFMu_Dependencies::wcfm_wc_variaton_swatch_pro_active_check()) {
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_variaton_swatch_product_meta_save' ], 130, 2);
            }
        }

        // WooCommerce Quotation - 6.2.7
        if (apply_filters('wcfm_is_allow_wc_quotation', true)) {
            if (WCFMu_Dependencies::wcfm_wc_quotation_active_check()) {
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_quotation_product_meta_save' ], 130, 2);
            }
        }

        // WooCommerce Dynamic Pricing - 6.2.9
        if (apply_filters('wcfm_is_allow_wc_dynamic_pricing', true)) {
            if (WCFMu_Dependencies::wcfm_wc_dynamic_pricing_active_check()) {
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_dynamic_pricing_product_meta_save' ], 130, 2);
            }
        }

        // MSRP for WooCommerce (Algoritmika) Support - 6.2.9
        if (apply_filters('wcfm_is_allow_wc_msrp_pricing', true)) {
            if (WCFMu_Dependencies::wcfm_msrp_for_wc_plugin_active_check()) {
                // MSRP for WooCommerce Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_msrp_for_wc_product_meta_save' ], 130, 2);
                add_filter('wcfm_product_variation_data_factory', [ &$this, 'wcfm_product_variation_msrp_for_wc_save' ], 130, 5);
            }
        }

        // Cost of Goods for WooCommerce (Algoritmika) Support - 6.2.9
        if (apply_filters('wcfm_is_allow_wc_cost_of_goods', true)) {
            if (WCFMu_Dependencies::wcfm_wc_cost_of_goods_plugin_active_check()) {
                // Cost of Goods for WooCommerce Product Meta Data Save
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_cost_of_goods_product_meta_save' ], 130, 2);
                add_filter('wcfm_product_variation_data_factory', [ &$this, 'wcfm_product_variation_wc_cost_of_goods_save' ], 130, 5);
            }
        }

        // WC License Manager Support - 6.4.0
        if (apply_filters('wcfm_is_allow_wc_license_manager', true)) {
            if (WCFMu_Dependencies::wcfm_wc_license_manager_plugin_active_check()) {
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_license_manager_product_meta_save' ], 130, 2);
                add_filter('wcfm_product_variation_data_factory', [ &$this, 'wcfm_product_variation_wc_license_manager_save' ], 130, 5);
            }
        }

        // ELEX WooCommerce Role-based Pricing Plugin & WooCommerce Catalog Mode - 6.0.4
        if (apply_filters('wcfm_is_allow_elex_rolebased_price', true)) {
            if (WCFMu_Dependencies::wcfm_elex_rolebased_price_plugin_active_check()) {
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_elex_rolebased_price_product_meta_save' ], 130, 2);
                add_filter('wcfm_product_variation_data_factory', [ &$this, 'wcfm_product_variation_elex_rolebased_price_save' ], 130, 5);
            }
        }

        // PW Gift Cards - 6.4.5
        if (apply_filters('wcfm_is_allow_wc_pw_gift_cards', true)) {
            if (WCFMu_Dependencies::wcfm_wc_pw_gift_cards_plugin_active_check()) {
                add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfm_wc_pw_gift_cards_product_meta_save' ], 130, 2);
            }
        }

        // Third Party Product Meta Data Save
        add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcfmu_thirdparty_products_manage_meta_save' ], 120, 2);

    }//end __construct()


    /**
     * Product Manage Third Party Variation Data Save
     */
    function wcfmu_thirdparty_product_variation_save($wcfm_variation_data, $new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $WCFMu;

        // WooCommerce Barcode & ISBN Support
        if ($allow_barcode_isbn = apply_filters('wcfm_is_allow_barcode_isbn', true)) {
            if (WCFM_Dependencies::wcfm_wc_barcode_isbn_plugin_active_check()) {
                update_post_meta($variation_id, 'barcode', $variations['barcode']);
                update_post_meta($variation_id, 'ISBN', $variations['ISBN']);
            }
        }

        // WooCommerce MSRP Pricing Support
        if ($allow_msrp_pricing = apply_filters('wcfm_is_allow_msrp_pricing', true)) {
            if (WCFM_Dependencies::wcfm_wc_msrp_pricing_plugin_active_check()) {
                update_post_meta($variation_id, '_msrp', $variations['_msrp']);
            }
        }

        // WooCommerce Product Fees Support
        if ($allow_product_fees = apply_filters('wcfm_is_allow_product_fees', true)) {
            if (WCFM_Dependencies::wcfm_wc_product_fees_plugin_active_check()) {
                update_post_meta($variation_id, 'product-fee-name', $variations['product-fee-name']);
                update_post_meta($variation_id, 'product-fee-amount', $variations['product-fee-amount']);
                $product_fee_multiplier = ( $variations['product-fee-multiplier'] ) ? 'yes' : 'no';
                update_post_meta($variation_id, 'product-fee-multiplier', $product_fee_multiplier);
            }
        }

        // WooCOmmerce Role Based Price Suport
        if (apply_filters('wcfm_is_allow_role_based_price', true)) {
            if (WCFM_Dependencies::wcfm_wc_role_based_price_active_check()) {
                $role_based_price = [];
                foreach ($variations as $variations_key => $variations_value) {
                    $pos = strpos($variations_key, '-rolebased');
                    if ($pos !== false) {
                        $rolebased_parts = explode('-', $variations_key);
                        if (count($rolebased_parts) == 3) {
                            $role_based_price[$rolebased_parts[0]][str_replace('price', '_price', $rolebased_parts[1])] = $variations_value;
                        }
                    }
                }

                update_post_meta($variation_id, '_role_based_price', $role_based_price);
                update_post_meta($variation_id, '_enable_role_based_price', 1);
            }
        }

        return $wcfm_variation_data;

    }//end wcfmu_thirdparty_product_variation_save()


    /**
     * WP Job Manager - Resume Manager Product Meta data save
     */
    function wcfm_wpjrm_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if ($wcfm_products_manage_form_data['product_type'] == 'resume_package') {
            $resume_package_fields = [
                '_resume_package_subscription_type',
                '_resume_limit',
                '_resume_duration',
            ];

            foreach ($resume_package_fields as $field_name) {
                if (isset($wcfm_products_manage_form_data[$field_name])) {
                    update_post_meta($new_product_id, $field_name, stripslashes($wcfm_products_manage_form_data[$field_name]));
                }
            }

            // Featured
            $is_featured = ( isset($wcfm_products_manage_form_data['_resume_featured']) ) ? 'yes' : 'no';

            update_post_meta($new_product_id, '_resume_featured', $is_featured);
        }

    }//end wcfm_wpjrm_product_meta_save()


    /**
     * YITH Auction Product Meta data save
     */
    function wcfm_yithauction_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if ($wcfm_products_manage_form_data['product_type'] == 'auction') {
            $product = wc_get_product($new_product_id);

            if ($product->is_type('auction')) {
                try {
                    /**
                     *  Data store object for auction product.
                     *
                     * @var YITH_WCACT_Product_Auction_Data_Store_CPT $data_store
                     */
                    $data_store        = WC_Data_Store::load( 'product-auction' );
                    $meta_key_to_props = $data_store->get_auction_meta_key_to_props();

                    foreach ($meta_key_to_props as $key => $prop) {
                        $setter = "set_{$prop}";

                        if (is_callable([ $product, $setter ])) {
                            if ($data_store->is_date_prop($prop)) {
                                $gmt_date = ( isset($wcfm_products_manage_form_data[$key]) ? strtotime(get_gmt_from_date($wcfm_products_manage_form_data[$key])) : '' );
                                $product->$setter($gmt_date);
                            } else if ($data_store->is_decimal_prop($prop)) {
                                if (isset($wcfm_products_manage_form_data[$key])) {
                                    $product->$setter(wc_format_decimal(wc_clean($wcfm_products_manage_form_data[$key])));
                                }
                            } else if ($data_store->is_yes_no_prop($prop)) {
                                $value = isset($wcfm_products_manage_form_data[$key]) && ! empty($wcfm_products_manage_form_data[$key]) ? 'yes' : 'no';

                                $product->$setter($value);
                            } else if (isset($wcfm_products_manage_form_data[$key])) {
                                $product->$setter($wcfm_products_manage_form_data[$key]);
                            }
                        }//end if
                    }//end foreach
                } catch (Exception $e) {
                    $message = sprintf( 'Error when trying to set product meta before saving for auction product with id %s1. Exception: %s2', $product->get_id(), $e->getMessage() );
                }//end try
            }//end if

            $product->save();

            $bids           = YITH_Auctions()->bids;
            $exist_auctions = $bids->get_max_bid($new_product_id);

            if (isset($wcfm_products_manage_form_data['_yith_auction_to'])) {
                // Clear all Product CronJob
                if (wp_next_scheduled('yith_wcact_send_emails', [ $new_product_id ])) {
                        wp_clear_scheduled_hook('yith_wcact_send_emails', [ $new_product_id ]);
                }

                // Create the CronJob //when the auction is about to end
                do_action('yith_wcact_register_cron_email', $new_product_id);

                // Clear all Product CronJob
                if (wp_next_scheduled('yith_wcact_send_emails_auction', [ $new_product_id ])) {
                        wp_clear_scheduled_hook('yith_wcact_send_emails_auction', [ $new_product_id ]);
                }

                // Create the CronJob //when the auction end, winner and vendors
                do_action('yith_wcact_register_cron_email_auction', $new_product_id);
            }

            $show_bidup = isset($wcfm_products_manage_form_data['_yith_wcact_upbid_checkbox']) ? 'yes' : 'no';
            yit_save_prop($product, '_yith_wcact_upbid_checkbox', $show_bidup);

            $show_overtime = isset($wcfm_products_manage_form_data['_yith_wcact_overtime_checkbox']) ? 'yes' : 'no';
            yit_save_prop($product, '_yith_wcact_overtime_checkbox', $show_overtime);

            // Prevent issues with orderby in shop loop
            if (! $exist_auctions) {
                    yit_save_prop($product, '_price', $wcfm_products_manage_form_data['_yith_auction_start_price']);
            }

            yit_save_prop($product, 'stock_status', 'instock');
            yit_save_prop($product, '_manage_stock', 'yes');
            yit_save_prop($product, '_stock', '1');
            yit_save_prop($product, '_backorders', 'no');
            yit_save_prop($product, '_sold_individually', 'yes');
        }//end if

    }//end wcfm_yithauction_product_meta_save()


    /**
     * WooCommerce Simple Auction Product Meta data save
     */
    function wcfm_wcsauction_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if ($wcfm_products_manage_form_data['product_type'] == 'auction') {
            $aution_fields = [
                '_auction_item_condition',
                '_auction_type',
                '_auction_start_price',
                '_auction_bid_increment',
                '_auction_reserved_price',
                '_regular_price',
                '_auction_dates_from',
                '_auction_dates_to',
                '_auction_relist_fail_time',
                '_auction_relist_not_paid_time',
                '_auction_relist_duration',
            ];

            foreach ($aution_fields as $field_name) {
                if (isset($wcfm_products_manage_form_data[$field_name])) {
                    $rental_fields[$field_name] = $wcfm_products_manage_form_data[$field_name];
                    update_post_meta($new_product_id, $field_name, $wcfm_products_manage_form_data[$field_name]);
                }
            }

            if (isset($wcfm_products_manage_form_data['_auction_proxy'])) {
                update_post_meta($new_product_id, '_auction_proxy', 'yes');
            } else {
                update_post_meta($new_product_id, '_auction_proxy', '0');
            }

            if (isset($wcfm_products_manage_form_data['_auction_sealed']) && ! isset($wcfm_products_manage_form_data['_auction_proxy'])) {
                update_post_meta($new_product_id, '_auction_sealed', stripslashes($wcfm_products_manage_form_data['_auction_sealed']));
            } else {
                update_post_meta($new_product_id, '_auction_sealed', 'no');
            }

            if (isset($wcfm_products_manage_form_data['_auction_automatic_relist'])) {
                update_post_meta($new_product_id, '_auction_automatic_relist', 'yes');
            } else {
                delete_post_meta($new_product_id, '_auction_automatic_relist');
            }

            if (isset($wcfm_products_manage_form_data['_regular_price'])) {
                update_post_meta($new_product_id, '_price', wc_format_decimal(wc_clean($wcfm_products_manage_form_data['_regular_price'])));
            } else {
                update_post_meta($new_product_id, '_price', '');
            }

            // Relist
            if (isset($wcfm_products_manage_form_data['_relist_auction_dates_from']) && isset($wcfm_products_manage_form_data['_relist_auction_dates_to']) && ! empty($wcfm_products_manage_form_data['_relist_auction_dates_from']) && ! empty($wcfm_products_manage_form_data['_relist_auction_dates_to'])) {
                $this->wcfm_wcsauction_do_relist($new_product_id, $wcfm_products_manage_form_data['_relist_auction_dates_from'], $wcfm_products_manage_form_data['_relist_auction_dates_to']);
            }

            // Stock Update
            update_post_meta($new_product_id, '_manage_stock', 'yes');
            update_post_meta($new_product_id, '_stock', '1');
            update_post_meta($new_product_id, '_backorders', 'no');
            update_post_meta($new_product_id, '_sold_individually', 'yes');
        }//end if

    }//end wcfm_wcsauction_product_meta_save()


    function wcfm_wcsauction_do_relist($post_id, $relist_from, $relist_to)
    {
        global $wpdb;

        update_post_meta($post_id, '_auction_dates_from', stripslashes($relist_from));
        update_post_meta($post_id, '_auction_dates_to', stripslashes($relist_to));
        update_post_meta($post_id, '_auction_relisted', current_time('mysql'));
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_stock', '1');
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, '_backorders', 'no');
        update_post_meta($post_id, '_sold_individually', 'yes');
        delete_post_meta($post_id, '_auction_closed');
        delete_post_meta($post_id, '_auction_started');
        delete_post_meta($post_id, '_auction_fail_reason');
        delete_post_meta($post_id, '_auction_current_bid');
        delete_post_meta($post_id, '_auction_current_bider');
        delete_post_meta($post_id, '_auction_max_bid');
        delete_post_meta($post_id, '_auction_max_current_bider');
        delete_post_meta($post_id, '_stop_mails');
        delete_post_meta($post_id, '_stop_mails');
        delete_post_meta($post_id, '_auction_bid_count');
        delete_post_meta($post_id, '_auction_sent_closing_soon');
        delete_post_meta($post_id, '_auction_sent_closing_soon2');
        delete_post_meta($post_id, '_auction_fail_email_sent');
        delete_post_meta($post_id, '_Reserve_fail_email_sent');
        delete_post_meta($post_id, '_auction_win_email_sent');
        delete_post_meta($post_id, '_auction_finished_email_sent');
        delete_post_meta($post_id, '_auction_has_started');

        $order_id = get_post_meta($post_id, '_order_id', true);
        // check if the custom field has a value
        if (! empty($order_id)) {
            $order = wc_get_order($order_id);
            $order->update_status('failed', __('Failed because off relisting', 'wc_simple_auctions'));
            delete_post_meta($post_id, '_order_id');
        }

        $wpdb->delete(
            $wpdb->usermeta,
            [
                'meta_key'   => 'wsa_my_auctions',
                'meta_value' => $post_id,
            ],
            [
                '%s',
                '%s',
            ]
        );

        do_action('woocommerce_simple_auction_do_relist', $post_id, $relist_from, $relist_to);

    } //end wcfm_wcsauction_do_relist()


    /**
     * WC Rental Pro Product Meta data save
     */
    function wcfm_wcrental_pro_product_meta_save($new_product_id, $wcfm_products_manage_form_data) {
        if ($wcfm_products_manage_form_data['product_type'] == 'redq_rental' && isset($wcfm_products_manage_form_data['_redq_product_inventory'])) {
            global $wpdb;
            $pivot_table = $wpdb->prefix . 'rnb_inventory_product';
    
            $wpdb->delete($pivot_table, array('product' => $new_product_id), array('%d'));
    
            $values = array();
            $fields = array();
    
            foreach ($wcfm_products_manage_form_data['_redq_product_inventory'] as $pvi) {
                $values[] = "(%d, %d)";
                $fields[] = $pvi;
                $fields[] = $new_product_id;
            }
    
            $values = implode(",", $values);
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $pivot_table ( inventory, product ) VALUES $values",
                $fields
            ));
    
            update_post_meta($new_product_id, '_redq_product_inventory', $wcfm_products_manage_form_data['_redq_product_inventory']); //Required for demo importing
        }
    }//end wcfm_wcrental_pro_product_meta_save()


    /**
     * WC Box Office Product Meta data save
     */
    function wcfm_wc_box_office_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        $is_ticket = isset($wcfm_products_manage_form_data['_ticket']) ? 'yes' : 'no';
        update_post_meta($new_product_id, '_ticket', $is_ticket);

        if (apply_filters('wcfm_is_allow_wc_box_office_ticket', true)) {
            $ticket_fields = [];
            foreach ($wcfm_products_manage_form_data['_ticket_fields'] as $ticket_field_key => $ticket_field_value) {
                $ticket_field_key                 = md5($ticket_field_value['label'].$ticket_field_value['type']);
                $ticket_fields[$ticket_field_key] = $ticket_field_value;
            }

            update_post_meta($new_product_id, '_ticket_fields', $ticket_fields);
        }

        // Ticket printing options
        if (apply_filters('wcfm_is_allow_wc_box_office_ticket_printing', true)) {
            if (isset($wcfm_products_manage_form_data['_print_tickets'])) {
                update_post_meta($new_product_id, '_print_tickets', $wcfm_products_manage_form_data['_print_tickets']);
            } else {
                delete_post_meta($new_product_id, '_print_tickets');
            }

            if (isset($wcfm_products_manage_form_data['_print_barcode'])) {
                update_post_meta($new_product_id, '_print_barcode', $wcfm_products_manage_form_data['_print_barcode']);
            } else {
                delete_post_meta($new_product_id, '_print_barcode');
            }

            if (isset($_POST['ticket_content'])) {
                update_post_meta($new_product_id, '_ticket_content', stripslashes(html_entity_decode($_POST['ticket_content'], ENT_QUOTES, 'UTF-8')));
            }
        }

        // Ticket email options
        if (apply_filters('wcfm_is_allow_wc_box_office_ticket_email', true)) {
            if (isset($wcfm_products_manage_form_data['_email_tickets'])) {
                update_post_meta($new_product_id, '_email_tickets', $wcfm_products_manage_form_data['_email_tickets']);
            } else {
                delete_post_meta($new_product_id, '_email_tickets');
            }

            if (isset($wcfm_products_manage_form_data['_email_ticket_subject'])) {
                update_post_meta($new_product_id, '_email_ticket_subject', $wcfm_products_manage_form_data['_email_ticket_subject']);
            }

            if (isset($_POST['ticket_email_html'])) {
                update_post_meta($new_product_id, '_ticket_email_html', stripslashes(html_entity_decode($_POST['ticket_email_html'], ENT_QUOTES, 'UTF-8')));
                update_post_meta($new_product_id, '_ticket_email_plain', stripslashes(html_entity_decode($_POST['ticket_email_html'], ENT_QUOTES, 'UTF-8')));
            }
        }

    }//end wcfm_wc_box_office_product_meta_save()


    /**
     * WC Lottery Product Meta data save
     */
    function wcfm_wc_lottery_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if ($wcfm_products_manage_form_data['product_type'] == 'lottery') {
            $lottery_fields = [
                '_min_tickets',
                '_max_tickets',
                '_max_tickets_per_user',
                '_lottery_num_winners',
                '_lottery_multiple_winner_per_user',
                '_lottery_price',
                '_lottery_sale_price',
                '_lottery_dates_from',
                '_lottery_dates_to',
            ];

            // $wcfm_products_manage_form_data['_yith_auction_for'] = ( $wcfm_products_manage_form_data[ '_yith_auction_for' ] ) ? strtotime( $wcfm_products_manage_form_data[ '_yith_auction_for' ] ) : '';
            // $wcfm_products_manage_form_data['_yith_auction_to'] = ( $wcfm_products_manage_form_data[ '_yith_auction_to' ] ) ? strtotime( $wcfm_products_manage_form_data[ '_yith_auction_to' ] ) : '';
            $wcfm_products_manage_form_data['_lottery_multiple_winner_per_user'] = ( $wcfm_products_manage_form_data['_lottery_multiple_winner_per_user'] ) ? 'yes' : 'no';

            foreach ($lottery_fields as $field_name) {
                if (isset($wcfm_products_manage_form_data[$field_name])) {
                    $rental_fields[$field_name] = $wcfm_products_manage_form_data[$field_name];
                    update_post_meta($new_product_id, $field_name, $wcfm_products_manage_form_data[$field_name]);
                }
            }

            // Set Product Price
            if (isset($wcfm_products_manage_form_data['_lottery_price'])) {
                update_post_meta($new_product_id, '_regular_price', $wcfm_products_manage_form_data['_lottery_price']);
            }

            if (isset($wcfm_products_manage_form_data['_lottery_sale_price']) && ( $wcfm_products_manage_form_data['_lottery_sale_price'] != '' )) {
                update_post_meta($new_product_id, '_sale_price', $wcfm_products_manage_form_data['_lottery_sale_price']);
                update_post_meta($new_product_id, '_price', $wcfm_products_manage_form_data['_lottery_sale_price']);
            } else {
                update_post_meta($new_product_id, '_sale_price', '');
                update_post_meta($new_product_id, '_price', $wcfm_products_manage_form_data['_lottery_price']);
            }
        }//end if

    }//end wcfm_wc_lottery_product_meta_save()


    /**
     * WC Deposits Product Meta data save
     */
    function wcfm_wc_deposits_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        $deposit_fields = [
            '_wc_deposit_enabled',
            '_wc_deposit_type',
            '_wc_deposit_multiple_cost_by_booking_persons',
            '_wc_deposit_amount',
            '_wc_deposit_payment_plans',
            '_wc_deposit_selected_type',
        ];

        foreach ($deposit_fields as $field_name) {
            if (isset($wcfm_products_manage_form_data[$field_name])) {
                update_post_meta($new_product_id, $field_name, $wcfm_products_manage_form_data[$field_name]);
            }
        }

    }//end wcfm_wc_deposits_product_meta_save()


    /**
     * WC Advanced Product Labels Product Meta data save
     */
    function wcfm_wc_advanced_product_labels_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        $label_fields = [
            '_wapl_label_type',
            '_wapl_label_text',
            '_wapl_label_style',
            '_wapl_label_align',
            '_wapl_custom_bg_color',
            '_wapl_custom_text_color',
        ];

        foreach ($label_fields as $field_name) {
            if (isset($wcfm_products_manage_form_data[$field_name])) {
                update_post_meta($new_product_id, $field_name, $wcfm_products_manage_form_data[$field_name]);
            }
        }

    }//end wcfm_wc_advanced_product_labels_product_meta_save()


    /**
     * Whole Sale price Save
     */
    function wcfm_wholesale_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if (apply_filters('wcfm_is_allow_wholesale', true)) {
            $label_fields = [
                'wholesale_price',
                'wholesale_minimum_order_quantity',
                'wholesale_order_quantity_step',
                // '_wapl_label_align',
                // '_wapl_custom_bg_color',
                // '_wapl_custom_text_color'
            ];

            foreach ($label_fields as $field_name) {
                if (isset($wcfm_products_manage_form_data[$field_name])) {
                    foreach ($wcfm_products_manage_form_data[$field_name] as $wholesale_field_key => $wholesale_field_val) {
                        update_post_meta($new_product_id, $wholesale_field_key, $wholesale_field_val);
                    }
                }
            }

            if (defined('WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE')) {
                if (isset($wcfm_products_manage_form_data['pqbwp-enable'])) {
                    update_post_meta($new_product_id, WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, 'yes');
                } else {
                    update_post_meta($new_product_id, WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, 'no');
                }

                if (isset($wcfm_products_manage_form_data['wholesale_quantity_based_rules'])) {
                    update_post_meta($new_product_id, WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, $wcfm_products_manage_form_data['wholesale_quantity_based_rules']);
                } else {
                    // update_post_meta( $new_product_id, WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, $wcfm_products_manage_form_data['wholesale_quantity_based_rules'] );
                }
            }
        }//end if

        if (defined('WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER')) {
            update_post_meta($new_product_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, 'all');
        }

    }//end wcfm_wholesale_product_meta_save()


    /**
     * Whole Sale variation price Save
     */
    function wcfm_product_variation_wholesale_save($wcfm_variation_data, $new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $WCFM, $WCFMmp;

        update_post_meta($variation_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, 'all');

        if (apply_filters('wcfm_is_allow_wholesale', true)) {
            $all_wholesale_roles = unserialize(get_option(WWP_OPTIONS_REGISTERED_CUSTOM_ROLES));
            if (! is_array($all_wholesale_roles)) {
                $all_wholesale_roles = [];
            }

            if (! empty($all_wholesale_roles)) {
                foreach ($all_wholesale_roles as $role_key => $role) {
                    $field_id = $role_key.'_wholesale_price';
                    if (isset($variations[$field_id])) {
                        update_post_meta($variation_id, $field_id, $variations[$field_id]);
                    }
                }
            }

            if (WCFMu_Dependencies::wcfm_wholesale_premium_active_check()) {
                if (! empty($all_wholesale_roles)) {
                    foreach ($all_wholesale_roles as $role_key => $role) {
                        $field_id = $role_key.'_wholesale_minimum_order_quantity';
                        if (isset($variations[$field_id])) {
                            update_post_meta($variation_id, $field_id, $variations[$field_id]);
                        }
                    }
                }

                if (! empty($all_wholesale_roles)) {
                    foreach ($all_wholesale_roles as $role_key => $role) {
                        $field_id = $role_key.'_wholesale_order_quantity_step';
                        if (isset($variations[$field_id])) {
                            update_post_meta($variation_id, $field_id, $variations[$field_id]);
                        }
                    }

                    if (isset($variations['pqbwp-enable'])) {
                        update_post_meta($variation_id, WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, 'yes');
                    } else {
                        update_post_meta($variation_id, WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, 'no');
                    }

                    if (isset($variations['wholesale_quantity_based_rules'])) {
                        update_post_meta($variation_id, WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, $variations['wholesale_quantity_based_rules']);
                    } else {
                        // update_post_meta( $variation_id, WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, $variations['wholesale_quantity_based_rules'] );
                    }
                }
            }//end if
        }//end if

        return $wcfm_variation_data;

    }//end wcfm_product_variation_wholesale_save()


    /**
     * WC Min/Max Quantities Save
     */
    function wcfm_wc_min_max_quantities_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        $label_fields = [
            'minimum_allowed_quantity',
            'maximum_allowed_quantity',
            'group_of_quantity',
        ];

        foreach ($label_fields as $field_name) {
            if (isset($wcfm_products_manage_form_data[$field_name])) {
                update_post_meta($new_product_id, $field_name, $wcfm_products_manage_form_data[$field_name]);
            }
        }

        if (isset($wcfm_products_manage_form_data['allow_combination'])) {
            update_post_meta($new_product_id, 'allow_combination', 'yes');
        } else {
            update_post_meta($new_product_id, 'allow_combination', 'no');
        }

        if (isset($wcfm_products_manage_form_data['minmax_do_not_count'])) {
            update_post_meta($new_product_id, 'minmax_do_not_count', 'yes');
        } else {
            update_post_meta($new_product_id, 'minmax_do_not_count', 'no');
        }

        if (isset($wcfm_products_manage_form_data['minmax_cart_exclude'])) {
            update_post_meta($new_product_id, 'minmax_cart_exclude', 'yes');
        } else {
            update_post_meta($new_product_id, 'minmax_cart_exclude', 'no');
        }

        if (isset($wcfm_products_manage_form_data['minmax_category_group_of_exclude'])) {
            update_post_meta($new_product_id, 'minmax_category_group_of_exclude', 'yes');
        } else {
            update_post_meta($new_product_id, 'minmax_category_group_of_exclude', 'no');
        }

    }//end wcfm_wc_min_max_quantities_product_meta_save()


    /**
     * WC Min/Max Quantities variation price Save
     */
    function wcfm_product_variation_wc_min_max_quantities_save($wcfm_variation_data, $new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $WCFM, $WCFMmp;

        $label_fields = [
            'variation_minimum_allowed_quantity',
            'variation_maximum_allowed_quantity',
            'variation_group_of_quantity',
        ];

        foreach ($label_fields as $field_name) {
            if (isset($variations[$field_name])) {
                update_post_meta($variation_id, $field_name, $variations[$field_name]);
            }
        }

        if (isset($variations['min_max_rules'])) {
            update_post_meta($variation_id, 'min_max_rules', 'yes');
        } else {
            update_post_meta($variation_id, 'min_max_rules', 'no');
        }

        if (isset($variations['variation_minmax_do_not_count'])) {
            update_post_meta($variation_id, 'variation_minmax_do_not_count', 'yes');
        } else {
            update_post_meta($variation_id, 'variation_minmax_do_not_count', 'no');
        }

        if (isset($variations['variation_minmax_cart_exclude'])) {
            update_post_meta($variation_id, 'variation_minmax_cart_exclude', 'yes');
        } else {
            update_post_meta($variation_id, 'variation_minmax_cart_exclude', 'no');
        }

        if (isset($variations['variation_minmax_category_group_of_exclude'])) {
            update_post_meta($variation_id, 'variation_minmax_category_group_of_exclude', 'yes');
        } else {
            update_post_meta($variation_id, 'variation_minmax_category_group_of_exclude', 'no');
        }

        return $wcfm_variation_data;

    }//end wcfm_product_variation_wc_min_max_quantities_save()


    /**
     * WC Variation Swatches Meta Data Save
     */
    function wcfm_wc_variaton_swatch_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if (isset($wcfm_products_manage_form_data['_wvs_pro_swatch_option'])) {
            update_post_meta($new_product_id, '_wvs_product_attributes', $wcfm_products_manage_form_data['_wvs_pro_swatch_option']);
        }

    }//end wcfm_wc_variaton_swatch_product_meta_save()


    /**
     * WC Quotation Meta Data Save
     */
    function wcfm_wc_quotation_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        $product_id = (int) $new_product_id;

        $adq_visibility_quote = [];
        if (isset($wcfm_products_manage_form_data['adq_visibility_quote']) && $wcfm_products_manage_form_data['adq_visibility_quote'] != '') {
                $adq_visibility_quote = $wcfm_products_manage_form_data['adq_visibility_quote'];
        }

        update_post_meta($product_id, 'adq_visibility_quote', $adq_visibility_quote);

        $adq_visibility_price = [];
        if (isset($wcfm_products_manage_form_data['adq_visibility_price']) && $wcfm_products_manage_form_data['adq_visibility_price'] != '') {
                $adq_visibility_price = $wcfm_products_manage_form_data['adq_visibility_price'];
        }

        update_post_meta($product_id, 'adq_visibility_price', $adq_visibility_price);

        $adq_visibility_cart = [];
        if (isset($wcfm_products_manage_form_data['adq_visibility_cart']) && $wcfm_products_manage_form_data['adq_visibility_cart'] != '') {
                $adq_visibility_cart = $wcfm_products_manage_form_data['adq_visibility_cart'];
        }

        update_post_meta($product_id, 'adq_visibility_cart', $adq_visibility_cart);

        $adq_product_force_button['active'] = ( isset($wcfm_products_manage_form_data['adq_product_force_button_check']) && $wcfm_products_manage_form_data['adq_product_force_button_check'] === 'yes' );
        $adq_product_force_button['roles']  = ( isset($wcfm_products_manage_form_data['adq_product_force_button']) && $wcfm_products_manage_form_data['adq_product_force_button'] != '' ) ? $wcfm_products_manage_form_data['adq_product_force_button'] : [];
        update_post_meta($product_id, 'adq_product_force_button', $adq_product_force_button);

        if (isset($wcfm_products_manage_form_data['_adq_inherit_visibility_quote'])) {
                update_post_meta($product_id, '_adq_inherit_visibility_quote', $wcfm_products_manage_form_data['_adq_inherit_visibility_quote']);
        } else {
                update_post_meta($product_id, '_adq_inherit_visibility_quote', 'no');
        }

        if (isset($wcfm_products_manage_form_data['_adq_inherit_visibility_price'])) {
                update_post_meta($product_id, '_adq_inherit_visibility_price', $wcfm_products_manage_form_data['_adq_inherit_visibility_price']);
        } else {
                update_post_meta($product_id, '_adq_inherit_visibility_price', 'no');
        }

        if (isset($wcfm_products_manage_form_data['_adq_inherit_visibility_cart'])) {
                update_post_meta($product_id, '_adq_inherit_visibility_cart', $wcfm_products_manage_form_data['_adq_inherit_visibility_cart']);
        } else {
                update_post_meta($product_id, '_adq_inherit_visibility_cart', 'no');
        }

        if (isset($wcfm_products_manage_form_data['adq_allow_product_comments'])) {
                update_post_meta($product_id, 'adq_allow_product_comments', $wcfm_products_manage_form_data['adq_allow_product_comments']);
        } else {
                update_post_meta($product_id, 'adq_allow_product_comments', 'no');
        }

        if (isset($wcfm_products_manage_form_data['_adq_inherit_allow_product_comments'])) {
                update_post_meta($product_id, '_adq_inherit_allow_product_comments', $wcfm_products_manage_form_data['_adq_inherit_allow_product_comments']);
        } else {
                update_post_meta($product_id, '_adq_inherit_allow_product_comments', 'no');
        }

        if (isset($wcfm_products_manage_form_data['adq_enable_button'])) {
                update_post_meta($product_id, 'adq_enable_button', $wcfm_products_manage_form_data['adq_enable_button']);
        }

        if (isset($wcfm_products_manage_form_data['adq_enable_payment'])) {
                update_post_meta($product_id, 'adq_enable_payment', $wcfm_products_manage_form_data['adq_enable_payment']);
        }

    }//end wcfm_wc_quotation_product_meta_save()


    /**
     * WC Dynamic Pricing Meta data Save
     */
    function wcfm_wc_dynamic_pricing_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        $product = wc_get_product($new_product_id);
        if (isset($wcfm_products_manage_form_data['pricing_rules'])) {
            WC_Dynamic_Pricing_Compatibility::update_product_meta($product, '_pricing_rules', $wcfm_products_manage_form_data['pricing_rules']);
        } else {
            WC_Dynamic_Pricing_Compatibility::delete_product_meta($product, '_pricing_rules');
        }

        if (WC_Dynamic_Pricing_Compatibility::is_wc_version_gte_2_7()) {
            $product->save_meta_data();
        }

    }//end wcfm_wc_dynamic_pricing_product_meta_save()


    /**
     * MSRP for WooCommerce price Save
     */
    function wcfm_msrp_for_wc_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if (isset($wcfm_products_manage_form_data['_alg_msrp'])) {
            update_post_meta($new_product_id, '_alg_msrp', $wcfm_products_manage_form_data['_alg_msrp']);
        }

        if (isset($wcfm_products_manage_form_data['_alg_msrp_by_country'])) {
            update_post_meta($new_product_id, '_alg_msrp_by_country', $wcfm_products_manage_form_data['_alg_msrp_by_country']);
        }

        if (isset($wcfm_products_manage_form_data['_alg_msrp_by_currency'])) {
            update_post_meta($new_product_id, '_alg_msrp_by_currency', $wcfm_products_manage_form_data['_alg_msrp_by_currency']);
        }

    }//end wcfm_msrp_for_wc_product_meta_save()


    /**
     * MSRP for WooCommerce variation price Save
     */
    function wcfm_product_variation_msrp_for_wc_save($wcfm_variation_data, $new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $WCFM, $WCFMmp;

        if (isset($variations['_alg_msrp'])) {
            update_post_meta($variation_id, '_alg_msrp', $variations['_alg_msrp']);
        }

        if (isset($variations['_alg_msrp_by_country'])) {
            update_post_meta($variation_id, '_alg_msrp_by_country', $variations['_alg_msrp_by_country']);
        }

        if (isset($wcfm_products_manage_form_data['_alg_msrp_by_currency'])) {
            update_post_meta($variation_id, '_alg_msrp_by_currency', $variations['_alg_msrp_by_currency']);
        }

        return $wcfm_variation_data;

    }//end wcfm_product_variation_msrp_for_wc_save()


    /**
     * Cost of Goods for WooCommerce price Save
     */
    function wcfm_wc_cost_of_goods_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if (isset($wcfm_products_manage_form_data['_alg_wc_cog_cost'])) {
            update_post_meta($new_product_id, '_alg_wc_cog_cost', $wcfm_products_manage_form_data['_alg_wc_cog_cost']);
        }

    }//end wcfm_wc_cost_of_goods_product_meta_save()


    /**
     * Cost of Goods for WooCommerce variation price Save
     */
    function wcfm_product_variation_wc_cost_of_goods_save($wcfm_variation_data, $new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $WCFM, $WCFMmp;

        if (isset($variations['_alg_wc_cog_cost'])) {
            update_post_meta($variation_id, '_alg_wc_cog_cost', $variations['_alg_wc_cog_cost']);
        }

        return $wcfm_variation_data;

    }//end wcfm_product_variation_wc_cost_of_goods_save()


    /**
     * WC License Manager Product meta save
     */
    function wcfm_wc_license_manager_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        // Update licensed product flag, according to checkbox.
        if (array_key_exists('lmfwc_licensed_product', $wcfm_products_manage_form_data)) {
            update_post_meta($new_product_id, 'lmfwc_licensed_product', 1);
        } else {
            update_post_meta($new_product_id, 'lmfwc_licensed_product', 0);
        }

        // Update delivered quantity, according to field.
        $deliveredQuantity = absint($wcfm_products_manage_form_data['lmfwc_licensed_product_delivered_quantity']);

        update_post_meta(
            $new_product_id,
            'lmfwc_licensed_product_delivered_quantity',
            $deliveredQuantity ? $deliveredQuantity : 1
        );

        // Update the use stock flag, according to checkbox.
        if (array_key_exists('lmfwc_licensed_product_use_stock', $wcfm_products_manage_form_data)) {
            update_post_meta($new_product_id, 'lmfwc_licensed_product_use_stock', 1);
        } else {
            update_post_meta($new_product_id, 'lmfwc_licensed_product_use_stock', 0);
        }

        // Update the assigned generator id, according to select field.
        update_post_meta(
            $new_product_id,
            'lmfwc_licensed_product_assigned_generator',
            intval($wcfm_products_manage_form_data['lmfwc_licensed_product_assigned_generator'])
        );

        // Update the use generator flag, according to checkbox.
        if (array_key_exists('lmfwc_licensed_product_use_generator', $wcfm_products_manage_form_data)) {
            // You must select a generator if you wish to assign it to the product.
            if (! $wcfm_products_manage_form_data['lmfwc_licensed_product_assigned_generator']) {
                $error = new WP_Error(2, __('Assign a generator if you wish to sell automatically generated licenses for this product.', 'lmfwc'));

                set_transient('lmfwc_error', $error, 45);
                update_post_meta($new_product_id, 'lmfwc_licensed_product_use_generator', 0);
                update_post_meta($new_product_id, 'lmfwc_licensed_product_assigned_generator', 0);
            } else {
                update_post_meta($new_product_id, 'lmfwc_licensed_product_use_generator', 1);
            }
        } else {
            update_post_meta($new_product_id, 'lmfwc_licensed_product_use_generator', 0);
            update_post_meta($new_product_id, 'lmfwc_licensed_product_assigned_generator', 0);
        }

    }//end wcfm_wc_license_manager_product_meta_save()


    /**
     * License Manager for WooCommerce variation data Save
     */
    function wcfm_product_variation_wc_license_manager_save($wcfm_variation_data, $new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $WCFM, $WCFMmp;

        // Update licensed product flag, according to checkbox.
        if (array_key_exists('lmfwc_licensed_product', $variations)) {
            update_post_meta($variation_id, 'lmfwc_licensed_product', 1);
        } else {
            update_post_meta($variation_id, 'lmfwc_licensed_product', 0);
        }

        // Update delivered quantity, according to field.
        $deliveredQuantity = absint($variations['lmfwc_licensed_product_delivered_quantity']);

        update_post_meta(
            $variation_id,
            'lmfwc_licensed_product_delivered_quantity',
            $deliveredQuantity ? $deliveredQuantity : 1
        );

        // Update the use stock flag, according to checkbox.
        if (array_key_exists('lmfwc_licensed_product_use_stock', $variations)) {
            update_post_meta($variation_id, 'lmfwc_licensed_product_use_stock', 1);
        } else {
            update_post_meta($variation_id, 'lmfwc_licensed_product_use_stock', 0);
        }

        // Update the assigned generator id, according to select field.
        update_post_meta(
            $variation_id,
            'lmfwc_licensed_product_assigned_generator',
            intval($variations['lmfwc_licensed_product_assigned_generator'])
        );

        // Update the use generator flag, according to checkbox.
        if (array_key_exists('lmfwc_licensed_product_use_generator', $variations)) {
            // You must select a generator if you wish to assign it to the product.
            if (! $variations['lmfwc_licensed_product_assigned_generator']) {
                $error = new WP_Error(2, __('Assign a generator if you wish to sell automatically generated licenses for this product.', 'lmfwc'));

                set_transient('lmfwc_error', $error, 45);
                update_post_meta($variation_id, 'lmfwc_licensed_product_use_generator', 0);
                update_post_meta($variation_id, 'lmfwc_licensed_product_assigned_generator', 0);
            } else {
                update_post_meta($variation_id, 'lmfwc_licensed_product_use_generator', 1);
            }
        } else {
            update_post_meta($variation_id, 'lmfwc_licensed_product_use_generator', 0);
            update_post_meta($variation_id, 'lmfwc_licensed_product_assigned_generator', 0);
        }

        return $wcfm_variation_data;

    }//end wcfm_product_variation_wc_license_manager_save()


    /**
     * WC License Manager Product meta save
     */
    function wcfm_elex_rolebased_price_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        $woocommerce_price_field = ( isset($wcfm_products_manage_form_data['product_role_based_price']) ) ? $wcfm_products_manage_form_data['product_role_based_price'] : '';
        update_post_meta($new_product_id, 'product_role_based_price', $woocommerce_price_field);
        if ($woocommerce_price_field) {
            foreach ($woocommerce_price_field as $key => $val) {
                update_post_meta($new_product_id, 'product_role_based_price_'.$key, $woocommerce_price_field[$key]['role_price']);
            }
        }

    }//end wcfm_elex_rolebased_price_product_meta_save()


    /**
     * License Manager for WooCommerce variation data Save
     */
    function wcfm_product_variation_elex_rolebased_price_save($wcfm_variation_data, $new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $WCFM, $WCFMmp;

        $all_wholesale_roles      = get_option('eh_pricing_discount_product_price_user_role');
        $product_adjustment_price = [];
        if (is_array($all_wholesale_roles) && ! empty($all_wholesale_roles)) {
            foreach ($all_wholesale_roles as $id => $role_key) {
                $field_id = $role_key.'_elex_rolebased_price';

                if (isset($variations[$field_id])) {
                    update_post_meta($variation_id, 'product_role_based_price_'.$role_key, $variations[$field_id]);

                    $product_adjustment_price[$role_key]['role_price'] = $variations[$field_id];
                }
            }
        }

        update_post_meta($variation_id, 'product_role_based_price', $product_adjustment_price);

        return $wcfm_variation_data;

    }//end wcfm_product_variation_elex_rolebased_price_save()


    /**
     * Third Party PW Gift Cards Meta Data Save
     */
    function wcfm_wc_pw_gift_cards_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        if (class_exists('WC_Product_PW_Gift_Card') && isset($wcfm_products_manage_form_data['product_type']) && ( $wcfm_products_manage_form_data['product_type'] == 'pw-gift-card' )) {
            if (isset($wcfm_products_manage_form_data['pw_gift_card_amounts'])) {
                if ($product = new WC_Product_PW_Gift_Card($new_product_id)) {
                    $pw_gift_card_amounts_map     = (array) get_post_meta($new_product_id, 'wcfm_pw_gift_card_amounts_map', true);
                    $new_pw_gift_card_amounts_map = [];

                    $pw_gift_card_amounts = explode(',', $wcfm_products_manage_form_data['pw_gift_card_amounts']);
                    if (! empty($pw_gift_card_amounts)) {
                        foreach ($pw_gift_card_amounts as $pw_gift_card_amount) {
                            $new_amount = wc_clean($pw_gift_card_amount);
                            if (! isset($pw_gift_card_amounts_map[$new_amount])) {
                                $result = $product->add_amount($new_amount);
                                if (is_numeric($result)) {
                                    $new_pw_gift_card_amounts_map[$new_amount] = $result;
                                }
                            } else if (isset($pw_gift_card_amounts_map[$new_amount])) {
                                $new_pw_gift_card_amounts_map[$new_amount] = $pw_gift_card_amounts_map[$new_amount];
                            }
                        }
                    }

                    foreach ($pw_gift_card_amounts_map as $amount => $variation_id) {
                        if (! isset($new_pw_gift_card_amounts_map[$amount])) {
                            $product->delete_amount($variation_id);
                        }
                    }
                }//end if

                update_post_meta($new_product_id, 'wcfm_pw_gift_card_amounts_map', $new_pw_gift_card_amounts_map);
            }//end if
        }//end if

    }//end wcfm_wc_pw_gift_cards_product_meta_save()


    /**
     * Third Party Product Meta data save
     */
    function wcfmu_thirdparty_products_manage_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $_POST;

        // WooCommerce Product Badge Manager Support
        if (WCFMu_Dependencies::wcfm_wc_product_badge_manager_active_check()) {
            if (apply_filters('wcfm_is_allow_wc_product_badge', true)) {
                if (isset($wcfm_products_manage_form_data['woo_pro_badge_meta_box'])) {
                    update_post_meta($new_product_id, 'woo_pro_badge_meta_box', $wcfm_products_manage_form_data['woo_pro_badge_meta_box']);
                } else {
                    delete_post_meta($new_product_id, 'woo_pro_badge_meta_box');
                }
            }
        }

        // WC 360 Images Support
        if (function_exists('woodmart_360_metabox_output')) {
            if (isset($wcfm_products_manage_form_data['wcfm_360_images']) && ! empty($wcfm_products_manage_form_data['wcfm_360_images'])) {
                $updated_gallery_ids = [];
                foreach ($wcfm_products_manage_form_data['wcfm_360_images'] as $gallery_imgs) {
                    if (isset($gallery_imgs['image360']) && ! empty($gallery_imgs['image360'])) {
                        $gallery_img_id = $WCFM->wcfm_get_attachment_id($gallery_imgs['image360']);
                        wp_update_post(
                            [
                                'ID'          => $gallery_img_id,
                                'post_parent' => $new_product_id,
                            ]
                        );
                        $updated_gallery_ids[] = $gallery_img_id;
                    }
                }

                update_post_meta($new_product_id, '_product_360_image_gallery', implode(',', $updated_gallery_ids));
            }
        }

        if (WCFMu_Dependencies::wcfm_wc_360_images_active_check()) {
            if (isset($wcfm_products_manage_form_data['wcfm_enable_360_images'])) {
                update_post_meta($new_product_id, 'wc360_enable', 'yes');
            } else {
                update_post_meta($new_product_id, 'wc360_enable', 'no');
            }
        }

        // WP Job Manager Support
        if (apply_filters('wcfm_is_allow_listings', true) && apply_filters('wcfm_is_allow_associate_listings_for_products', true)) {
            if (WCFM_Dependencies::wcfm_wp_job_manager_plugin_active_check() && ! WCFM_Dependencies::wcfm_products_mylistings_active_check()) {
                if (isset($wcfm_products_manage_form_data['wpjm_listings'])) {
                    $old_listings = (array) get_post_meta($new_product_id, '_wpjm_listings', true);
                    $new_listings = (array) $wcfm_products_manage_form_data['wpjm_listings'];

                    // Remove Product from Old Listings
                    if ($old_listings) {
                        foreach ($old_listings as $old_listing) {
                            $listing_products = (array) get_post_meta($old_listing, '_products', true);
                            if (( $key = array_search($new_product_id, $listing_products) ) !== false) {
                                unset($listing_products[$key]);
                            }

                            update_post_meta($old_listing, '_products', $listing_products);
                        }
                    }

                    // Associate Product to New Listings
                    if ($new_listings) {
                        foreach ($new_listings as $new_listing) {
                            $listing_products   = (array) get_post_meta($new_listing, '_products', true);
                            $listing_products[] = $new_product_id;
                            update_post_meta($new_listing, '_products', $listing_products);
                        }
                    }

                    update_post_meta($new_product_id, '_wpjm_listings', $new_listings);
                }//end if
            }//end if
        }//end if

        // WC Smart Coupons Support
        if (apply_filters('wcfm_is_allow_wc_smart_coupons', true)) {
            if (WCFMu_Dependencies::wcfm_wc_smart_coupons_plugin_active_check()) {
                if (isset($wcfm_products_manage_form_data['wcfm_coupon_title'])) {
                    update_post_meta($new_product_id, '_coupon_title', $wcfm_products_manage_form_data['wcfm_coupon_title']);
                }
            }
        }

    }//end wcfmu_thirdparty_products_manage_meta_save()


}//end class
