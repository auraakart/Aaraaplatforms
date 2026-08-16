<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

add_action('admin_init', 'wt_order_welcome');

function wt_order_activation_check() {
    set_transient('_order_welcome_screen_activation_redirect', true, 30);
}

function wt_order_welcome() {

    if (!get_transient('_order_welcome_screen_activation_redirect')) {
        return;
    }
    delete_transient('_order_welcome_screen_activation_redirect');
    wp_safe_redirect(add_query_arg(array('page' => 'wt_import_export_for_woo_basic_export'), admin_url('admin.php')));
}