<?php

if (!defined('ABSPATH')) {
    exit;
}

$base_reserved_columns = array(
    'order_id' => array('title' => 'ID ', 'description' => 'Order ID '),
    'order_number' => array('title' => 'Order number', 'description' => 'Order Number'),
    'order_date' => array('title' => 'Order date', 'description' => 'Order Date', 'type' => 'date'),
    'paid_date' => array('title' => 'Paid date', 'description' => 'Paid Date', 'type' => 'date'),
    'status' => array('title' => 'Status', 'description' => 'Order Status ( processing , pending ...) '),
    'shipping_total' => array('title' => 'Shipping total', 'description' => 'Shipping Total amount'),
    'shipping_tax_total' => array('title' => 'Shipping tax total', 'description' => 'Shipping Tax Total'),
    'fee_total' => array('title' => 'Total fee', 'description' => 'Total Fee'),
    'fee_tax_total' => array('title' => 'Total tax fee', 'description' => 'Total Tax Fee'),
    'tax_total' => array('title' => 'Total tax', 'description' => 'Total Tax'),
    'cart_discount' => array('title' => 'Cart discount', 'description' => 'Cart Discount'),
    'order_discount' => array('title' => 'Order discount', 'description' => 'Order Discount'),
    'discount_total' => array('title' => 'Discount total', 'description' => 'Discount Total'),
    'order_total' => array('title' => 'Order total', 'description' => 'Order Total'),
    //'refunded_total' => array('title'=>'refunded_total','description'=>'refunded_total'),
	'order_key' => array('title' => 'order_key', 'description' => 'Order key'),
    'order_currency' => array('title' => 'Order currency', 'description' => 'Order Currency'),
    'payment_method' => array('title' => 'Payment method', 'description' => 'Payment Method'),
    'payment_method_title' => array('title' => 'Payment method title', 'description' => 'Payment Method Title'),
    'transaction_id' => array('title' => 'Transaction ID', 'description' => 'Payment transaction id'),
    'customer_ip_address' => array('title' => 'Customer IP address', 'description' => 'Customer ip address'),
    'customer_user_agent' => array('title' => 'Customer user agent', 'description' => 'Customer user agent'),
    'shipping_method' => array('title' => 'Shipping method', 'description' => 'Shipping Method'),
    'customer_email' => array('title' => 'Customer email', 'description' => 'Customer Email ( if not provided order will be created as Guest)'),
    'customer_user' => array('title' => 'Customer user', 'description' => 'Customer id ( if not provided order will be created as Guest)'),
    'billing_first_name' => array('title' => 'Billing first name', 'description' => 'billing_first_name'),
    'billing_last_name' => array('title' => 'Billing last name', 'description' => 'billing_last_name'),
    'billing_company' => array('title' => 'Billing company', 'description' => 'billing_company'),
    'billing_email' => array('title' => 'Billing email', 'description' => 'billing_email'),
    'billing_phone' => array('title' => 'Billing phone', 'description' => 'billing_phone'),
    'billing_address_1' => array('title' => 'Billing address 1', 'description' => 'billing_address_1'),
    'billing_address_2' => array('title' => 'Billing address 2', 'description' => 'billing_address_2'),
    'billing_postcode' => array('title' => 'Billing postcode', 'description' => 'billing_postcode'),
    'billing_city' => array('title' => 'Billing city', 'description' => 'billing_city'),
    'billing_state' => array('title' => 'Billing state', 'description' => 'billing_state'),
    'billing_country' => array('title' => 'Billing country', 'description' => 'billing_country'),
    'shipping_first_name' => array('title' => 'Shipping first name', 'description' => 'shipping_first_name'),
    'shipping_last_name' => array('title' => 'Shipping last name', 'description' => 'shipping_last_name'),
    'shipping_company' => array('title' => 'Shipping company', 'description' => 'shipping_company'),
    'shipping_phone' => array('title' => 'Shipping phone', 'description' => 'shipping_phone'),
    'shipping_address_1' => array('title' => 'Shipping address 1', 'description' => 'shipping_address_1'),
    'shipping_address_2' => array('title' => 'Shipping address 2', 'description' => 'shipping_address_2'),
    'shipping_postcode' => array('title' => 'Shipping postcode', 'description' => 'shipping_postcode'),
    'shipping_city' => array('title' => 'Shipping city', 'description' => 'shipping_city'),
    'shipping_state' => array('title' => 'Shipping state', 'description' => 'shipping_state'),
    'shipping_country' => array('title' => 'Shipping country', 'description' => 'shipping_country'),
    'customer_note' => array('title' => 'Customer note', 'description' => 'customer_note'),
    'wt_import_key' => array('title' => 'wt_import_key', 'description' => 'wt_import_key'),
    'tax_items' => array('title' => 'Tax items', 'description' => 'tax_items'),
    'shipping_items' => array('title' => 'Shipping items', 'description' => 'shipping_items'),
    'fee_items' => array('title' => 'Fee items', 'description' => 'fee_items'),
    'coupon_items' => array('title' => 'Coupon items', 'description' => 'coupons'),
    'refund_items' => array('title' => 'Refund items', 'description' => 'refund_items'),
    'order_notes' => array('title' => 'Order notes', 'description' => 'Order notes'),
    'line_item_' => array('title' => 'Line_item_', 'description' => 'Line Items', 'field_type' => 'start_with'),
    'download_permissions' => array('title' => 'Downloadable product permissions ', 'description' => 'Permissions for order items will automatically be granted when the order status changes to processing or completed.'),
);
if( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.5', '>=' ) ):
 $base_reserved_columns['meta:_wc_order_attribution_device_type'] = array('title' => 'wc order attribution device type', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_referrer'] = array('title' => 'wc order attribution referrer', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_session_count'] = array('title' => 'wc order attribution session count', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_session_entry'] = array('title' => 'wc order attribution session entry', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_session_pages'] = array('title' => 'wc order attribution session pages', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_session_start_time'] = array('title' => 'wc order attribution session start time', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_source_type'] = array('title' => 'wc order attribution source type', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_user_agent'] = array('title' => 'wc order attribution user agent', 'description' => '');
 $base_reserved_columns['meta:_wc_order_attribution_utm_source'] = array('title' => 'wc order attribution utm source', 'description' => '');
endif;

if (!function_exists('is_plugin_active'))
    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

if (is_plugin_active('print-invoices-packing-slip-labels-for-woocommerce/print-invoices-packing-slip-labels-for-woocommerce.php')):
    $base_reserved_columns['meta:wf_invoice_number'] = array('title' => 'WT Invoice number ', 'description' => 'WebToffee Invoice number');
    $base_reserved_columns['meta:_wf_invoice_date'] = array('title' => 'WT Invoice date ', 'description' => 'WebToffee Invoice date');
endif;

if (is_plugin_active('yith-woocommerce-order-tracking-premium/init.php')):
    $base_reserved_columns['meta:ywot_tracking_code'] = array('title' => 'Tracking code', 'description' => 'YITH Tracking code');
    $base_reserved_columns['meta:ywot_tracking_postcode'] = array('title' => 'Tracking postcode', 'description' => 'YITH Tracking postcode');
    $base_reserved_columns['meta:ywot_carrier_id'] = array('title' => 'Carrier name', 'description' => 'YITH Tracking carrier');
    $base_reserved_columns['meta:ywot_pick_up_date'] = array('title' => 'Pickup date', 'description' => 'YITH pickup date');    
    $base_reserved_columns['meta:ywot_picked_up'] = array('title' => 'Order picked up?', 'description' => 'YITH Is Order picked up?');
endif;

if (class_exists('Zorem_Woocommerce_Advanced_Shipment_Tracking') || class_exists('WC_Shipment_Tracking')):
    $base_reserved_columns['meta:_wc_shipment_tracking_items'] = array('title' => 'Shipment Tracking', 'description' => 'Shipment tracking');
endif;

if (class_exists('WPO_WCPDF')):
    $base_reserved_columns['meta:_wcpdf_invoice_number'] = array('title' => 'WCPDF Invoice number', 'description' => 'WCPDF Invoice number');
	$base_reserved_columns['meta:_wcpdf_invoice_date'] = array('title' => 'WCPDF Invoice date', 'description' => 'WCPDF Invoice date');
	$base_reserved_columns['meta:_wcpdf_invoice_number_data'] = array('title' => 'WCPDF Invoice number details', 'description' => 'WCPDF Invoice number details');
	$base_reserved_columns['meta:_wcpdf_invoice_date_formatted'] = array('title' => 'WCPDF Invoice date formatted', 'description' => 'WCPDF Invoice date formatted');
	$base_reserved_columns['meta:_wcpdf_invoice_settings'] = array('title' => 'WCPDF Invoice settings', 'description' => 'WCPDF Invoice settings');
endif;
if (is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php')):
    $base_reserved_columns['meta:_ppcp_paypal_fees'] = array('title' => 'Paypal fees', 'description' => 'Paypal fees');
endif;

if (is_plugin_active('eh-stripe-payment-gateway/stripe-payment-gateway.php')):
    $base_reserved_columns['meta:eh_stripe_fees'] = array('title' => 'eh_stripe_fees', 'description' => 'stripe fees');
endif;

if (is_plugin_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php')):
    $base_reserved_columns['meta:_stripe_currency'] = array('title' => 'stripe_currency', 'description' => 'stripe currency');
    $base_reserved_columns['meta:_stripe_fee'] = array('title' => 'Stripe fee', 'description' => 'stripe fee');
    $base_reserved_columns['meta:_stripe_net'] = array('title' => 'stripe_net', 'description' => ' stripe net');
endif;

// Reserved column names
return apply_filters('woocommerce_csv_order_reserved_fields_pair', $base_reserved_columns);
