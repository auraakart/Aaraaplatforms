<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_columns = array(
    'ID'                    => 'ID',
    'post_title'            => 'Coupon code',
    'post_excerpt'          => 'Description',
    'post_status'           => 'Status',
    'post_date'             => 'Post date',
    'post_author'           => 'Post author',

    // // Meta
    'discount_type'         => 'Discount type',
    'coupon_amount'         => 'Coupon amount',
    'individual_use'        => 'Individual use only',
    'product_ids'           => 'Product IDs',
    'product_SKUs'          => 'Product SKUs',
    'exclude_product_ids'   => 'Exclude product IDs',
    'exclude_product_SKUs'  => 'Exclude product SKUs',
    'usage_count'           => 'No of times used',
    'usage_limit'           => 'Usage limit per coupon',
    'usage_limit_per_user'  => 'Usage limit per user',
    'limit_usage_to_x_items' => 'Limit usage to X items',
    'date_expires'          => 'Expiry date',
    'free_shipping'         => 'Allow free shipping',
    'exclude_sale_items'    => 'Exclude sale items',
    'product_categories'    => 'Product categories',
    'exclude_product_categories' => 'Exclude categories',
    'minimum_amount'        => 'Minimum amount',
    'maximum_amount'        => 'Maximum amount',
    'customer_email'        => 'Allowed emails',
) ;

if (is_plugin_active('wt-woocommerce-gift-cards/wt-woocommerce-gift-cards.php')): 
    $base_columns['meta:_wt_gc_auto_generated_store_credit_coupon'] = 'Auto generated store credit coupon';
    $base_columns['meta:_wt_gc_store_credit_coupon'] = 'Store credit coupon';
    $base_columns['meta:_wt_smart_coupon_credit_activated'] = 'Smart coupon credit activated';
    $base_columns['meta:_wt_smart_coupon_initial_credit'] = 'Smart coupon initial credit';
    $base_columns['meta:_wt_sc_send_date_gmt'] = 'Send date';
    $base_columns['meta:wt_auto_generated_store_credit_coupon'] = 'Auto generated store credit coupon';
    $base_columns['meta:wt_credit_history'] = 'Credit history';
    $base_columns['meta:_wt_sc_send_the_generated_credit'] = 'Generated credit';
    $base_columns['meta:_wt_gc_suggest_product_ids'] = 'Suggested product IDs';
    //wallet meta
    $base_columns['meta:_used_store_credit_expiry_data'] = 'Store credit data';
    $base_columns['meta:_wt_gc_user_wallet_coupon'] = 'User wallet coupon';
    $base_columns['meta:_wt_gc_wallet_balance'] = 'Wallet balance';
    $base_columns['meta:_wt_gc_wallet_expired'] = 'Wallet expired';
    $base_columns['meta:_wt_gc_wallet_used'] = 'Wallet used';
endif;

return apply_filters('coupon_csv_coupon_post_columns', $base_columns);