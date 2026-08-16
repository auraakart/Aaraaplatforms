<?php
/**
 * WCFMu plugin core
 *
 * Wallet System Integration - menu, view, AJAX handlers
 *
 * @author      WC Lovers
 * @package     wcfmu/core
 * @version     1.0.0
 */

class WCFMu_Wallet {

    public function __construct() {
        global $WCFM, $WCFMu;

        if ( ! class_exists( 'Wallet_System_For_Woocommerce' ) ) {
            return;
        }

        add_filter( 'wcfm_query_vars',                    [ $this, 'wallet_wcfm_query_vars' ],   20 );
        add_filter( 'wcfm_endpoints_slug',                [ $this, 'wallet_wcfm_endpoints_slug' ] );
        add_filter( 'wcfm_menus',                         [ $this, 'wallet_wcfm_menus' ],        25 );
        add_filter( 'wcfm_dashboard_modified_endpoint_slug', [ $this, 'wallet_fix_endpoint_slug' ] );
        add_action( 'init',               [ $this, 'wallet_wcfm_init' ],         20 );
        add_action( 'wcfm_load_views',    [ $this, 'wallet_load_views' ],        30 );
        add_action( 'wcfm_load_scripts',  [ $this, 'wallet_load_scripts' ],      30 );

        add_action( 'wp_ajax_wcfm_wallet_list',         [ $this, 'ajax_wallet_list' ] );
        add_action( 'wp_ajax_wcfm_wallet_transactions', [ $this, 'ajax_wallet_transactions' ] );
        add_action( 'wp_ajax_wcfm_wallet_update',        [ $this, 'ajax_wallet_update' ] );
        add_action( 'wp_ajax_wcfm_wallet_search_users',  [ $this, 'ajax_wallet_search_users' ] );
    }

    function wallet_wcfm_query_vars( $query_vars ) {
        $query_vars['wcfm-wallet'] = 'walletlist';
        return $query_vars;
    }

    function wallet_wcfm_endpoints_slug( $endpoints ) {
        $endpoints['wcfm-wallet'] = 'walletlist';
        return $endpoints;
    }

    function wallet_fix_endpoint_slug( $endpoint ) {
        if ( $endpoint === 'wallet' ) {
            return 'walletlist';
        }
        return $endpoint;
    }

    function wallet_wcfm_init() {
        global $WCFM_Query;
        $WCFM_Query->init_query_vars();
        $WCFM_Query->add_endpoints();
        if ( get_option( 'wcfm_updated_end_point_wallet' ) !== '1.0.2' ) {
            flush_rewrite_rules();
            update_option( 'wcfm_updated_end_point_wallet', '1.0.2' );
        }
    }

    function wallet_wcfm_menus( $menus ) {
        if ( ! apply_filters( 'wcfm_is_allow_wallet', true ) ) {
            return $menus;
        }

        $wallet_menu = [
            'wcfm-wallet' => [
                'label'    => __( 'Wallet', 'wc-frontend-manager-ultimate' ),
                'url'      => get_wcfm_wallet_url(),
                'icon'     => 'wallet',
                'priority' => 47,
            ],
        ];

        // Insert right after 'wcfm-customers'
        $new_menus = [];
        $inserted  = false;
        foreach ( $menus as $key => $menu ) {
            $new_menus[ $key ] = $menu;
            if ( $key === 'wcfm-customers' && ! $inserted ) {
                $new_menus = array_merge( $new_menus, $wallet_menu );
                $inserted  = true;
            }
        }

        if ( ! $inserted ) {
            $new_menus = array_merge( $new_menus, $wallet_menu );
        }

        return $new_menus;
    }

    function wallet_load_scripts( $end_point ) {
        global $WCFM, $WCFMu;

        if ( $end_point !== 'wcfm-wallet' ) {
            return;
        }

        $WCFM->library->load_datatable_lib();
        wp_enqueue_script(
            'wcfmu_wallet_js',
            $WCFMu->library->js_lib_url . 'wallet/wcfmu-script-wallet.js',
            [ 'jquery', 'dataTables_js' ],
            time(),
            true
        );
        wp_localize_script( 'wcfmu_wallet_js', 'wcfm_wallet_params', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wcfm_wallet_nonce' ),
        ] );
    }

    function wallet_load_views( $end_point ) {
        global $WCFM, $WCFMu;

        if ( $end_point === 'wcfm-wallet' ) {
            $WCFMu->template->get_template( 'wallet/wcfmu-view-wallet.php' );
        }
    }

    function ajax_wallet_list() {
        check_ajax_referer( 'wcfm_wallet_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $users = get_users( [
            'meta_key'     => 'wps_wallet',
            'meta_compare' => 'EXISTS',
            'fields'       => [ 'ID', 'display_name', 'user_email' ],
            'orderby'      => 'ID',
            'order'        => 'DESC',
            'number'       => 1000,
        ] );

        $data = [];
        foreach ( $users as $user ) {
            $balance = (float) get_user_meta( $user->ID, 'wps_wallet', true );
            $data[]  = [
                'id'          => $user->ID,
                'name'        => esc_html( $user->display_name ),
                'email'       => esc_html( $user->user_email ),
                'balance'     => html_entity_decode( strip_tags( wc_price( $balance ) ) ),
                'balance_raw' => $balance,
            ];
        }

        wp_send_json_success( $data );
    }

    function ajax_wallet_transactions() {
        global $wpdb;

        check_ajax_referer( 'wcfm_wallet_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $table   = $wpdb->prefix . 'wps_wsfw_wallet_transaction';

        if ( $user_id ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.*, u.display_name, u.user_email
                 FROM {$table} t
                 LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                 WHERE t.user_id = %d
                 ORDER BY t.id DESC
                 LIMIT 1000",
                $user_id
            ) );
        } else {
            $rows = $wpdb->get_results(
                "SELECT t.*, u.display_name, u.user_email
                 FROM {$table} t
                 LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                 ORDER BY t.id DESC
                 LIMIT 2000"
            );
        }

        $data = [];
        foreach ( $rows as $row ) {
            $data[] = [
                'id'             => (int) $row->id,
                'user_id'        => (int) $row->user_id,
                'user_name'      => esc_html( $row->display_name ?: 'User #' . $row->user_id ),
                'user_email'     => esc_html( $row->user_email ?: '' ),
                'amount'         => html_entity_decode( strip_tags( wc_price( $row->amount ) ) ),
                'amount_raw'     => (float) $row->amount,
                'type'           => esc_html( $row->transaction_type ?: '' ),
                'direction'      => esc_html( $row->transaction_type_1 ?: '' ),
                'payment_method' => esc_html( $row->payment_method ?: '-' ),
                'transaction_id' => esc_html( $row->transaction_id ?: '-' ),
                'note'           => esc_html( $row->note ?: '-' ),
                'date'           => esc_html( $row->date ?: '' ),
            ];
        }

        wp_send_json_success( $data );
    }

    function ajax_wallet_update() {
        check_ajax_referer( 'wcfm_wallet_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $user_id     = absint( $_POST['user_id'] ?? 0 );
        $amount      = floatval( $_POST['amount'] ?? 0 );
        $action_type = sanitize_text_field( $_POST['action_type'] ?? '' );
        $note        = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( ! $user_id || $amount <= 0 ) {
            wp_send_json_error( 'Please enter a valid amount.' );
        }
        if ( ! in_array( $action_type, [ 'credit', 'debit' ], true ) ) {
            wp_send_json_error( 'Please select Credit or Debit.' );
        }

        $current = (float) get_user_meta( $user_id, 'wps_wallet', true );

        if ( $action_type === 'credit' ) {
            $new_balance = $current + $amount;
        } else {
            $new_balance = $current - $amount;
            if ( $new_balance < 0 ) {
                wp_send_json_error( 'Insufficient wallet balance for debit.' );
            }
        }

        update_user_meta( $user_id, 'wps_wallet', $new_balance );

        global $wpdb;
        $table = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
        $wpdb->insert( $table, [
            'user_id'            => $user_id,
            'amount'             => $amount,
            'transaction_type'   => 'wallet_' . $action_type . '_amount',
            'transaction_type_1' => $action_type,
            'payment_method'     => 'Admin Adjustment',
            'transaction_id'     => 'WCFM-' . uniqid(),
            'note'               => $note ?: 'Admin wallet adjustment',
            'date'               => current_time( 'mysql' ),
        ] );

        wp_send_json_success( [
            'new_balance' => $new_balance,
            'formatted'   => html_entity_decode( strip_tags( wc_price( $new_balance ) ) ),
        ] );
    }

    function ajax_wallet_search_users() {
        check_ajax_referer( 'wcfm_wallet_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $search = sanitize_text_field( $_POST['q'] ?? '' );

        $users = get_users( [
            'search'         => '*' . $search . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'fields'         => [ 'ID', 'display_name', 'user_email' ],
            'number'         => 30,
        ] );

        $results = [];
        foreach ( $users as $user ) {
            $results[] = [
                'id'   => $user->ID,
                'text' => $user->display_name . ' (' . $user->user_email . ')',
            ];
        }

        wp_send_json( [ 'results' => $results ] );
    }

} // end class WCFMu_Wallet
