<?php

/**
 * Order section of the plugin
 *
 * @link            
 *
 * @package  Wt_Import_Export_For_Woo_Order_Basic 
 */
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

if(!class_exists('Wt_Import_Export_For_Woo_Order_Basic_Order')){
class Wt_Import_Export_For_Woo_Order_Basic_Order {

    public $module_id = '';
    public static $module_id_static = '';
    public $module_base = 'order';
    public $module_name = 'Order Import Export for WooCommerce';
    public $min_base_version= '1.0.0'; /* Minimum `Import export plugin` required to run this add on plugin */

    private $importer = null;
    private $exporter = null;
    private $all_meta_keys = array();
    private $exclude_hidden_meta_columns = array();
    private $found_meta = array();
    private $found_hidden_meta = array();
    private $selected_column_names = null;

    public function __construct()
    {      
        /**
        *   Checking the minimum required version of `Import export plugin` plugin available
        */
        if(!Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::check_base_version($this->module_base, $this->module_name, $this->min_base_version))
        {
            return;
        }
        if(!function_exists('is_plugin_active'))
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        if ( ! class_exists( 'WooCommerce' ) )
        {
            return;
        }
       
        $this->module_id = Wt_Import_Export_For_Woo_Order_Basic::get_module_id($this->module_base);
        self::$module_id_static = $this->module_id;
                
        add_filter('wt_iew_exporter_post_types_basic', array($this, 'wt_iew_exporter_post_types_basic'), 10, 1);
        add_filter('wt_iew_importer_post_types_basic', array($this, 'wt_iew_exporter_post_types_basic'), 10, 1);

        
        add_filter('wt_iew_exporter_alter_mapping_fields_basic', array($this, 'exporter_alter_mapping_fields'), 10, 3);        
        add_filter('wt_iew_importer_alter_mapping_fields_basic', array($this, 'get_importer_post_columns'), 10, 3);  
        
		add_filter('wt_iew_exporter_alter_filter_fields_basic', array($this, 'exporter_alter_filter_fields'), 10, 3);
		
        add_filter('wt_iew_exporter_alter_advanced_fields_basic', array($this, 'exporter_alter_advanced_fields'), 10, 3);        
        add_filter('wt_iew_importer_alter_advanced_fields_basic', array($this, 'importer_alter_advanced_fields'), 10, 3);
        
        add_filter('wt_iew_exporter_alter_meta_mapping_fields_basic', array($this, 'exporter_alter_meta_mapping_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_meta_mapping_fields_basic', array($this, 'importer_alter_meta_mapping_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_mapping_enabled_fields_basic', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_mapping_enabled_fields_basic', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);

        add_filter('wt_iew_exporter_do_export_basic', array($this, 'exporter_do_export'), 10, 7);
        add_filter('wt_iew_importer_do_import_basic', array($this, 'importer_do_import'), 10, 8);

        add_filter('wt_iew_importer_steps_basic', array($this, 'importer_steps'), 10, 2);
		
		
        // Legacy (post-based) bulk actions
        // Use filter for WordPress 3.1.0+ (filter was added in WordPress 3.1.0)
        // Fallback to JavaScript for WordPress 3.0.1.
        global $wp_version;
        if ( version_compare( $wp_version, '3.1.0', '>=' ) ) {
            add_filter( 'bulk_actions-edit-shop_order', array( $this, 'wt_add_legacy_bulk_actions' ) );
        } else {
            // Fallback for very old WordPress versions (3.0.1)
            add_action( 'admin_footer-edit.php', array( $this, 'wt_add_order_bulk_actions_js_fallback' ) );
        }        
        add_action('load-edit.php', array($this, 'wt_process_order_bulk_actions'));

        // HPOS support for bulk actions
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'wt_add_hpos_bulk_actions'));
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'wt_process_hpos_order_bulk_actions'), 10, 3);
		
    }

    /**
     * Add bulk action for legacy (post-based) orders page
     * Uses WordPress filter (available since WordPress 3.1.0)
     * 
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function wt_add_legacy_bulk_actions($actions) {
        global $post_status;
        
        // Only add if not in trash
        if ( 'trash'  !== $post_status ) {
            $actions['wt_ier_download_orders'] = __('Export as CSV', 'order-import-export-for-woocommerce');
        }
        
        return $actions;
    }
    
    /**
     * Fallback JavaScript method for very old WordPress versions (3.0.1)
     * Only used if WordPress version is below 3.1.0 (when filter was introduced)
     * 
     * @return void
     */
    public function wt_add_order_bulk_actions_js_fallback() {
        global $post_type, $post_status;

        if ( 'shop_order' === $post_type && 'trash' !== $post_status && !is_plugin_active( 'wt-import-export-for-woo/wt-import-export-for-woo.php' )) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    var $downloadOrders = $('<option>').val('wt_ier_download_orders').text('<?php esc_html_e('Export to CSV', 'order-import-export-for-woocommerce') ?>');
                    $('select[name^="action"]').append($downloadOrders);
                });
            </script>
            <?php
        }
    }

     /**
     * Add bulk action for HPOS orders page
     * 
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function wt_add_hpos_bulk_actions($actions) {
        $actions['wt_ier_download_orders'] = __('Export as CSV', 'order-import-export-for-woocommerce');
        return $actions;
    }
      
    /**
     * Process HPOS order bulk export action
     * 
     * @param string $redirect_url Redirect URL after processing
     * @param string $action Action being performed
     * @param array $order_ids Array of order IDs
     * @return string Modified redirect URL
     */
    public function wt_process_hpos_order_bulk_actions($redirect_url, $action, $order_ids) {
        if ( 'wt_ier_download_orders' !== $action  || empty( $order_ids ) || ! is_array( $order_ids ) ) {
            return $redirect_url;
        }
                
        // Security check
        check_admin_referer('bulk-orders');
        
        // Convert order IDs to integers
        $order_ids = array_map( 'absint', $order_ids );
        
        if ( empty( $order_ids) ) {
            return $redirect_url;
        }
        
        // Give an unlimited timeout if possible
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Its necessary to set time limit.
        @set_time_limit(0);
        
        include_once( 'export/class-wt-orderimpexpcsv-basic-exporter.php' );
        Wt_Import_Export_For_Woo_Order_Basic_Order_Bulk_Export::do_export( 'shop_order', $order_ids );
        
        // Return empty to prevent redirect (export will handle it)
        return '';
    }

    
    /**
     * Order page bulk export action
     * 
     */
    public function wt_process_order_bulk_actions() {
        global $typenow;
        if ($typenow == 'shop_order') {
            // get the action list
            $wp_list_table = _get_list_table('WP_Posts_List_Table');
            $action = $wp_list_table->current_action();
            if (!in_array($action, array('wt_ier_download_orders'))) {
                return;
            }
            // security check
            check_admin_referer('bulk-posts');

            if (isset($_REQUEST['post'])) {
                $order_ids = array_map('absint', $_REQUEST['post']);
            }
            if (empty($order_ids)) {
                return;
            }
            // give an unlimited timeout if possible
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Its necessary to set time limit.
            @set_time_limit(0);

            if ($action == 'wt_ier_download_orders') {
                
                
                include_once( 'export/class-wt-orderimpexpcsv-basic-exporter.php' );
                Wt_Import_Export_For_Woo_Order_Basic_Order_Bulk_Export::do_export('shop_order', $order_ids);
            }
        }
    }
	
    /**
    *   Altering advanced step description
    */
    public function importer_steps($steps, $base)
    {
        if($this->module_base==$base)
        {
            $steps['advanced']['description']=__('Use options from below to decide updates to existing orders, batch import count. You can also save the template file for future imports.', 'order-import-export-for-woocommerce');
        }
        return $steps;
    }
    
    public function importer_do_import($import_data, $base, $step, $form_data, $selected_template_data, $method_import, $batch_offset, $is_last_batch) {        
        if ($this->module_base != $base) {
            return $import_data;
        }             

        if(0 == $batch_offset){                        
            $memory = size_format(wt_let_to_num_basic(ini_get('memory_limit')));
            $wp_memory = size_format(wt_let_to_num_basic(WP_MEMORY_LIMIT));                      
            Wt_Import_Export_For_Woo_Basic_Logwriter::write_log($this->module_base, 'import', '---[ New import started at '.wp_date('Y-m-d H:i:s').' ] PHP Memory: ' . $memory . ', WP Memory: ' . $wp_memory);
        }
                
        include plugin_dir_path(__FILE__) . 'import/import.php';
        $import = new Wt_Import_Export_For_Woo_Order_Basic_Order_Import($this);
        
        $response = $import->prepare_data_to_import($import_data,$form_data,$batch_offset,$is_last_batch);
        
        if($is_last_batch){
            Wt_Import_Export_For_Woo_Basic_Logwriter::write_log($this->module_base, 'import', '---[ Import ended at '.wp_date('Y-m-d H:i:s').']---');
        }
        
        return $response;
    }

    public function exporter_do_export($export_data, $base, $step, $form_data, $selected_template_data, $method_export, $batch_offset) {
        if ($this->module_base != $base) {
            return $export_data;
        }
        
        switch ($method_export) {
            case 'quick':
                $this->set_export_columns_for_quick_export($form_data);
                break;

            case 'template':
            case 'new':
                $this->set_selected_column_names($form_data);
                break;
            
            default:
                break;
        }

        include plugin_dir_path(__FILE__) . 'export/export.php';
        $export = new Wt_Import_Export_For_Woo_Order_Basic_Order_Export($this);
                      
        $data_row = $export->prepare_data_to_export($form_data, $batch_offset);
        
        $header_row = $export->get_header_row();

        $export_data = array(
            'head_data' => $header_row,
            'body_data' => $data_row['data'],
            'total' => $data_row['total'],
        ); 
        if(isset($data_row['no_post'])){
            $export_data['no_post'] = $data_row['no_post'];
        }

        return $export_data;
    }

    /**
     * Adding current post type to export list
     *
     */
    public function wt_iew_exporter_post_types_basic($arr) {
            if (is_plugin_active('order-import-export-for-woocommerce/order-import-export-for-woocommerce.php')) {
                $arr['order'] = __('Order', 'order-import-export-for-woocommerce');
                $arr['coupon'] = __('Coupon', 'order-import-export-for-woocommerce');
            }
            if (is_plugin_active('users-customers-import-export-for-wp-woocommerce/users-customers-import-export-for-wp-woocommerce.php')) {
                $arr['user'] = __('User/Customer', 'order-import-export-for-woocommerce');
            }
            if (is_plugin_active('product-import-export-for-woo/product-import-export-for-woo.php')) {
                $arr['product'] = __('Product', 'order-import-export-for-woocommerce');
                $arr['product_review'] = __('Product Review', 'order-import-export-for-woocommerce');
                $arr['product_categories'] = __('Product Categories', 'order-import-export-for-woocommerce');
                $arr['product_tags'] = __('Product Tags', 'order-import-export-for-woocommerce');
            }

            $arr['order'] = __('Order', 'order-import-export-for-woocommerce');
            $arr['coupon'] = __('Coupon', 'order-import-export-for-woocommerce');
            $arr['product'] = __('Product', 'order-import-export-for-woocommerce');
            $arr['product_review'] = __('Product Review', 'order-import-export-for-woocommerce');
            $arr['product_categories'] = __('Product Categories', 'order-import-export-for-woocommerce');
            $arr['product_tags'] = __('Product Tags', 'order-import-export-for-woocommerce');
            $arr['user'] = __('User/Customer', 'order-import-export-for-woocommerce');
            $arr['subscription'] = __('Subscription', 'order-import-export-for-woocommerce');
            return $arr;
        }

    /*
     * Setting default export columns for quick export
     */

    public function set_export_columns_for_quick_export($form_data) {

        $post_columns = self::get_order_post_columns();

        $this->selected_column_names = array_combine(array_keys($post_columns), array_keys($post_columns));

        if (isset($form_data['method_export_form_data']['mapping_enabled_fields']) && !empty($form_data['method_export_form_data']['mapping_enabled_fields'])) {
            foreach ($form_data['method_export_form_data']['mapping_enabled_fields'] as $value) {
                $additional_quick_export_fields[$value] = array('fields' => array());
            }

            $export_additional_columns = $this->exporter_alter_meta_mapping_fields($additional_quick_export_fields, $this->module_base, array());
            foreach ($export_additional_columns as $value) {
                $this->selected_column_names = array_merge($this->selected_column_names, $value['fields']);
            }
        }
    }

    public static function get_order_statuses() {
        $statuses = wc_get_order_statuses();
        return apply_filters('wt_iew_allowed_order_statuses',  $statuses);
    }

    public static function get_order_sort_columns() {
        $sort_columns = array('post_parent', 'ID', 'post_author', 'post_date', 'post_title', 'post_name', 'post_modified', 'menu_order', 'post_modified_gmt', 'rand', 'comment_count');
        return apply_filters('wt_iew_allowed_order_sort_columns', array_combine($sort_columns, $sort_columns));
    }

    public static function get_order_post_columns() {
        return include plugin_dir_path(__FILE__) . 'data/data-order-post-columns.php';
    }
    
    public function get_importer_post_columns($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        $colunm = include plugin_dir_path(__FILE__) . 'data/data/data-wf-reserved-fields-pair.php';
//        $colunm = array_map(function($vl){ return array('title'=>$vl, 'description'=>$vl); }, $arr); 
        return $colunm;
    }

    public function exporter_alter_mapping_enabled_fields($mapping_enabled_fields, $base, $form_data_mapping_enabled_fields) {
        if ($base == $this->module_base) {
            $mapping_enabled_fields = array();

            $mapping_enabled_fields['hidden_meta'] = array(__('Hidden meta', 'order-import-export-for-woocommerce'), 0);

            if ( ! function_exists( 'is_plugin_active' ) ) {
                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            }

            // Check if premium plugin is active.
            if ( ! is_plugin_active( 'wt-import-export-for-woo-order/wt-import-export-for-woo-order.php' ) ) {
                if ( $this->wt_get_found_hidden_meta() ) {
                    $mapping_enabled_fields['hidden_meta']['banner_html'] = $this->get_upgrade_banner_html();
                }
            }
        }
        return $mapping_enabled_fields;
    }

    /**
     * Get upgrade banner HTML for premium features
     */
    public function get_upgrade_banner_html() {
        return '<div id="product-type-notice" style="margin-top: 10px; display: block; width: 850px; height: auto; top: 210px; left: 117px;">
    <div class="notice notice-warning" style="width: 92.5%; max-width: 810px; margin-left: 0px; display: inline-flex; padding: 16px 18px 16px 26px; justify-content: flex-end; align-items: center; border-radius: 8px; border: 1px solid #F5F9FF; background-color: #F5F9FF; box-sizing: border-box;">
        <div style="display: flex; flex: 1 1 0; flex-direction: column; justify-content: flex-start; align-items: flex-start; width: 100%;">
            <!-- Title -->
            <div style="padding-bottom: 10px; align-self: stretch; color: #2A3646; font-size: 14px; font-family: Inter; font-weight: 600; line-height: 16px; word-wrap: break-word;">
                ' . __('Upgrade to premium 💎', 'order-import-export-for-woocommerce') . '
            </div>

            <!-- Description -->
            <div style="width: 100%; max-width: 679px; padding-bottom: 10px;">
                <span style="color: #2A3646; font-size: 13px; font-family: Inter; font-weight: 400; ">
                        ' . __('We\'ve detected hidden WooCommerce metadata & custom order fields in your store. Unlock full access to export them seamlessly.', 'order-import-export-for-woocommerce') . '
                    </span>
                </div>

            <!-- Button -->
                <a href="//www.webtoffee.com/product/order-import-export-plugin-for-woocommerce/?utm_source=free_plugin&utm_medium=export_hidden_meta_tab&utm_campaign=Order_Import_Export" target="_blank" style="
                    width: auto;
                    height: 18px;
                font-family:  \'Inter\', sans-serif;
                    font-weight: 600;
                    font-size: 12px;
                    line-height: 100%;
                    color: #2B28E9;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                    gap: 5px;
                    text-decoration: none;
                margin-top: 0px;
                ">
                    ' . __('Upgrade now', 'order-import-export-for-woocommerce') . ' <span style="font-size: 14px;">→</span>
                </a>
            </div>
        </div>
    </div>
';
    }

    public function exporter_alter_meta_mapping_fields($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
		/*
        foreach ($fields as $key => $value) {
            switch ($key) {
                

                default:
                    break;
            }
        }
		 * 
		 */

        return $fields;
    }
    
    
    public function importer_alter_meta_mapping_fields($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        $fields=$this->exporter_alter_meta_mapping_fields($fields, $base, $step_page_form_data);
        $out=array();
        foreach ($fields as $key => $value) 
        {
            $value['fields']=array_map(function($vl){ return array('title'=>$vl, 'description'=>$vl); }, $value['fields']);
            $out[$key]=$value;
        }
        return $out;
    }
    
    public function wt_get_found_meta() {   

        if (!empty($this->found_meta)) {
            return $this->found_meta;
        }

        // Loop products and load meta data
        $found_meta = array();
        // Some of the values may not be usable (e.g. arrays of arrays) but the worse
        // that can happen is we get an empty column.

        $all_meta_keys = $this->wt_get_all_meta_keys();

        $csv_columns = self::get_order_post_columns();

        $exclude_hidden_meta_columns = $this->wt_get_exclude_hidden_meta_columns();

        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) == '_') || in_array($meta, $exclude_hidden_meta_columns) || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
                continue;

            $found_meta[] = $meta;
        }
        
        $found_meta = array_diff($found_meta, array_keys($csv_columns));
        $this->found_meta = $found_meta;
        return $this->found_meta;
    }

    public function wt_get_found_hidden_meta() {
        global $wpdb;
    
        $order_table_enabled = false;
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            $order_table_enabled = OrderUtil::custom_orders_table_usage_is_enabled();
        }
    
        if ($order_table_enabled) {
            // HPOS enabled 
            $table_name = $wpdb->prefix . 'wc_orders_meta';
            $order_table = $wpdb->prefix . 'wc_orders';
    
            $query = "
                SELECT 1
                FROM {$table_name} wm
                JOIN {$order_table} wo ON wo.id = wm.order_id
                WHERE wm.meta_key LIKE '_%'
                LIMIT 1
            ";
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
            $result = $wpdb->get_var($query);
            // phpcs:enable
            return $result !== null;
        } else {
            // Legacy post-based orders
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Its necessary to use direct database query.
            $result = $wpdb->get_var("
                SELECT 1
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key LIKE '_%'
                AND p.post_type IN ('shop_order', 'shop_order_placehold')
                LIMIT 1
            ");
            // phpcs:enable
            return $result !== null;
        }
    }

    public function wt_get_exclude_hidden_meta_columns() {

        if (!empty($this->exclude_hidden_meta_columns)) {
            return $this->exclude_hidden_meta_columns;
        }

        $exclude_hidden_meta_columns = include( plugin_dir_path(__FILE__) . 'data/data-wf-exclude-hidden-meta-columns.php' );

        $this->exclude_hidden_meta_columns = $exclude_hidden_meta_columns;
        return $this->exclude_hidden_meta_columns;
    }

    public function wt_get_all_meta_keys() {

        if (!empty($this->all_meta_keys)) {
            return $this->all_meta_keys;
        }

        $all_meta_keys = self::get_all_metakeys('shop_order');

        $this->all_meta_keys = $all_meta_keys;
        return $this->all_meta_keys;
    }

    /**
     * Get a list of all the meta keys for a post type. This includes all public, private,
     * used, no-longer used etc. They will be sorted once fetched.
     */
    public static function get_all_metakeys($post_type = 'shop_order') {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Its necessary to use direct database query.
        $meta = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed' ) ORDER BY pm.meta_key", $post_type
        ));
        // phpcs:enable
        return $meta;
    }

    public function set_selected_column_names($full_form_data) {
        if (is_null($this->selected_column_names)) {
            if (isset($full_form_data['mapping_form_data']['mapping_selected_fields']) && !empty($full_form_data['mapping_form_data']['mapping_selected_fields'])) {
                $this->selected_column_names = $full_form_data['mapping_form_data']['mapping_selected_fields'];
            }
            if (isset($full_form_data['meta_step_form_data']['mapping_selected_fields']) && !empty($full_form_data['meta_step_form_data']['mapping_selected_fields'])) {
                $export_additional_columns = $full_form_data['meta_step_form_data']['mapping_selected_fields'];
                foreach ($export_additional_columns as $value) {
                    $this->selected_column_names = array_merge($this->selected_column_names, $value);
                }
            }
        }

        return $full_form_data;
    }

    public function get_selected_column_names() {

        return $this->selected_column_names;
    }

    public function exporter_alter_mapping_fields($fields, $base, $mapping_form_data) {
        if ($base == $this->module_base) {
            $fields = self::get_order_post_columns();
        }
        return $fields;
    }

    public function exporter_alter_advanced_fields($fields, $base, $advanced_form_data) {
        if ($this->module_base != $base) {
            return $fields;
        }      
        unset($fields['export_shortcode_tohtml']);
        
        $out = array();
				
		$out['header_empty_row'] = array(
			'tr_html' => '<tr id="header_empty_row"><th></th><td></td></tr>'
		);
        $out['exclude_already_exported'] = array(
            'label' => __("Exclude already exported", 'order-import-export-for-woocommerce'),
            'type' => 'checkbox',
			'checkbox_fields' => array( 1 => __( 'Enable', 'order-import-export-for-woocommerce' ) ),
            'value' => 0,
            'field_name' => 'exclude_already_exported',
            'help_text' => __('Enable this to exclude the previously exported orders.', 'order-import-export-for-woocommerce'),
        );

        $out['exclude_line_items'] = array(
            'label' => __("Exclude line items", 'order-import-export-for-woocommerce'),
            'type' => 'radio',
            'radio_fields' => array(
                'Yes' => __('Yes', 'order-import-export-for-woocommerce'),
                'No' => __('No', 'order-import-export-for-woocommerce')
            ),
            'value' => 'No',
            'field_name' => 'exclude_line_items',
            'help_text' => __("Option 'Yes' excludes the line items", 'order-import-export-for-woocommerce'),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_exclude_line_items',
            )
        );
        
        $out['export_to_separate'] = array(
            'label' => __("Export line items in", 'order-import-export-for-woocommerce'),
            'type' => 'radio',
            'radio_fields' => array(
                'default' => __('Migration mode', 'order-import-export-for-woocommerce'),
                'column' => __('Separate columns', 'order-import-export-for-woocommerce'),
                'row' => __('Separate rows', 'order-import-export-for-woocommerce')    
            ),
            'value' => 'column',
            'merge_right'=>true,
            'field_name' => 'export_to_separate',
            //'help_text' => __("Option 'Yes' exports the line items within the order into separate columns in the exported file."),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('This option will export each line item details into a single column. This option is mainly used for the order migration purpose.', 'order-import-export-for-woocommerce'),
                    'condition'=>array(
                        array('field'=>'wt_iew_export_to_separate', 'value'=>'default')
                    )
                ),
                array(
                    'help_text'=> __('This option will export each line item details into a separate column.', 'order-import-export-for-woocommerce'),
                    'condition'=>array(
                        array('field'=>'wt_iew_export_to_separate', 'value'=>'column')
                    )
                ),
                array(
                    'help_text'=> __('This option will export each line item details into a separate row.', 'order-import-export-for-woocommerce'),
                    'condition'=>array(
                        array('field'=>'wt_iew_export_to_separate', 'value'=>'row')
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'wt_iew_exclude_line_items',
                'val'=>'No',
                'depth'=>1, /* indicates the left margin of fields */                
            )
        );

        foreach ($fields as $fieldk => $fieldv) {
            $out[$fieldk] = $fieldv;
        }
        return $out;
    }
    
    public function importer_alter_advanced_fields($fields, $base, $advanced_form_data) {
        if ($this->module_base != $base) {
            return $fields;
        }
        $out = array();
        

		$out['header_empty_row'] = array(
			'tr_html' => '<tr id="header_empty_row"><th></th><td></td></tr>'
		);
        $out['found_action_merge'] = array(
            'label' => __("If order exists in the store", 'order-import-export-for-woocommerce'),
            'type' => 'radio',
            'radio_fields' => array(
                'skip' => __('Skip', 'order-import-export-for-woocommerce'),
                'update' => __('Update', 'order-import-export-for-woocommerce'),                
            ),
            'value' => 'skip',
            'field_name' => 'found_action',
            'help_text' => __('Orders are matched by their order IDs.', 'order-import-export-for-woocommerce'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('This option will not update the existing orders and keeps the order as is.', 'order-import-export-for-woocommerce'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('This option will update the existing orders as per the data from the input file.', 'order-import-export-for-woocommerce'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'update')
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_found_action'
            )
        );       
        
        $out['ord_link_using_sku'] = array(
            'label' => __('Link order items using', 'order-import-export-for-woocommerce'),
            'type' => 'radio',
            'radio_fields' => array(
                '0' => __('Product ID', 'order-import-export-for-woocommerce'),				
                '1' => __('Product SKU', 'order-import-export-for-woocommerce')
            ),
            'value' => '0',
            'field_name' => 'ord_link_using_sku',
			'merge_right' => true,
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Links the products of the imported orders by SKU.', 'order-import-export-for-woocommerce'),
                    'condition'=>array(
                        array('field'=>'wt_iew_ord_link_using_sku', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> __('Links the products of the imported orders by Product ID. However, the products will not get linked if the Product ID conflicts with the IDs of an existing post type.', 'order-import-export-for-woocommerce'),
                    'condition'=>array(
                        array('field'=>'wt_iew_ord_link_using_sku', 'value'=>0)
                    )
                )
            ),
        );
		if( !is_plugin_active( 'product-import-export-for-woo/product-import-export-for-woo.php' ) ){
					$out['ord_link_using_sku']['help_text'] = sprintf(
						/* translators: %s: Product Import Export for WooCommerce plugin  URL */
						__( 'If you do not already have corresponding products added in your store, we recommend that you import them first using <a href="%s" target="_blank">Product Import Export for WooCommerce</a>.', 'order-import-export-for-woocommerce' ),
						admin_url('plugin-install.php?tab=plugin-information&plugin=product-import-export-for-woo')
					);
		 }
        
		$out['update_stock_details'] = array(
            'label' => __("Update stock details", 'order-import-export-for-woocommerce'),
            'type' => 'checkbox',
			'checkbox_fields' => array( 1 => __( 'Enable', 'order-import-export-for-woocommerce' ) ),
            'value' => 0,
            'field_name' => 'update_stock_details',
            'help_text' => __('Select to update the sale count and stock quantity of a product associated with the order.<br/>Note: Ensure the manage stock option is enabled. This feature is not meant to work for the refunded, cancelled or failed order statuses.', 'order-import-export-for-woocommerce'),
        );
        
        foreach ($fields as $fieldk => $fieldv) {
            $out[$fieldk] = $fieldv;
        }
        return $out;
    }
    
    /**
     *  Customize the items in filter export page
     */
    public function exporter_alter_filter_fields($fields, $base, $filter_form_data) {

        if ($base == $this->module_base)
        {
            /* altering help text of default fields */
            $fields['limit']['label']=__('Total number of orders to export', 'order-import-export-for-woocommerce'); 
            $fields['limit']['help_text']=__( 'Provide the number of orders you want to export. e.g. Entering 500 with a skip count of 10 will export orders from 11th to 510th position.', 'order-import-export-for-woocommerce' );
            $fields['offset']['label']=__('Skip first <i>n</i> orders', 'order-import-export-for-woocommerce');
            $fields['offset']['help_text']=__('Skips specified number of orders from the beginning of the database. e.g. Enter 10 to skip first 10 orders from export.', 'order-import-export-for-woocommerce');

            $fields['orders'] = array(
                'label' => __('Order IDs', 'order-import-export-for-woocommerce'),
                'placeholder' => __('Enter order IDs separated by ,', 'order-import-export-for-woocommerce'),
                'field_name' => 'orders',
                'sele_vals' => '',
                'help_text' => __('Enter order IDs separated by comma to export specific orders.', 'order-import-export-for-woocommerce'),
                'type' => 'text',
                'css_class' => '',
            );
            
            $fields['order_status'] = array(
                'label' => __('Order status', 'order-import-export-for-woocommerce'),
                'placeholder' => __('Any status', 'order-import-export-for-woocommerce'),
                'field_name' => 'order_status',
                'sele_vals' => self::get_order_statuses(),
                'help_text' => __( 'Filter orders on the basis of status. Multiple statuses can be selected.', 'order-import-export-for-woocommerce' ),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr')
            );
            $fields['products'] = array(
                'label' => __('Product', 'order-import-export-for-woocommerce'),
                'placeholder' => __('Search for a product&hellip;', 'order-import-export-for-woocommerce'),
                'field_name' => 'products',
                'sele_vals' => array(),
                'help_text' => __( 'Export orders containing specific products. Input name, ID or SKU of the products contained in the orders you want to export.', 'order-import-export-for-woocommerce' ),
                'type' => 'multi_select',
                'css_class' => 'wc-product-search',
                'validation_rule' => array('type'=>'text_arr')
            );
            $fields['email'] = array(
                'label' => __('Customer', 'order-import-export-for-woocommerce'),
                'placeholder' => __('Search for a customer&hellip;', 'order-import-export-for-woocommerce'),
                'field_name' => 'email',
                'sele_vals' => array(),
                'help_text' => __( 'Export orders of specific customers. Input the customer name or email to specify the customers.', 'order-import-export-for-woocommerce' ),
                'type' => 'multi_select',
                'css_class' => 'wc-customer-search',
                'validation_rule' => array('type'=>'text_arr')
            );
            $fields['coupons'] = array(
                'label' => __('Coupons', 'order-import-export-for-woocommerce'),
                'placeholder' => __('Search for a coupon&hellip;', 'order-import-export-for-woocommerce'),
                'field_name' => 'coupons',
                'sele_vals' => array(),
                'help_text' => __( 'Exports orders redeemed with specific coupon codes. Multiple coupon codes can be selected.', 'order-import-export-for-woocommerce' ),
                'type' => 'multi_select',
                'css_class' => 'wt-coupon-search',
				'validation_rule' => array('type'=>'text_arr')
            );

            $fields['date_from'] = array(
                'label' => __('Date from', 'order-import-export-for-woocommerce'),
                'placeholder' => __('Date from', 'order-import-export-for-woocommerce'),
                'field_name' => 'date_from',
                'sele_vals' => '',
                'help_text' => __( 'Export orders placed on and after the specified date.', 'order-import-export-for-woocommerce' ),
                'type' => 'text',
                'css_class' => 'wt_iew_datepicker',
//                'type' => 'field_html',
//                'field_html' => '<input type="text" name="date_from" class="wt_iew_datepicker" placeholder="'.__('From date').'" class="input-text" />',
            );

            $fields['date_to'] = array(
                'label' => __('Date to', 'order-import-export-for-woocommerce'),
                'placeholder' => __('Date to', 'order-import-export-for-woocommerce'),
                'field_name' => 'date_to',
                'sele_vals' => '',
                'help_text' => __( 'Export orders placed upto the specified date.', 'order-import-export-for-woocommerce' ),
                'type' => 'text',
                'css_class' => 'wt_iew_datepicker',
//                'type' => 'field_html',
//                'field_html' => '<input type="text" name="date_to" class="wt_iew_datepicker" placeholder="'.__('To date').'" class="input-text" />',
            );


        }
        return $fields;
    }

    public static function wt_get_product_id_by_sku($sku) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Its necessary to use direct database query.
        $post_exists_sku = $wpdb->get_var($wpdb->prepare("
	    		SELECT $wpdb->posts.ID
	    		FROM $wpdb->posts
	    		LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )
	    		WHERE $wpdb->posts.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )
	    		AND $wpdb->postmeta.meta_key = '_sku' AND $wpdb->postmeta.meta_value = %s
	    		", $sku));
        // phpcs:enable
        if ($post_exists_sku) {
            return $post_exists_sku;
        }
        return false;
    }
    
    public function get_item_by_id($id) {
		$post = array();
        $post['edit_url'] = get_edit_post_link($id);
        $post['title'] = $id;
        return $post; 
    }
	public static function get_item_link_by_id($id) {
		if (self::wt_get_order_table_name()) {
			$post['edit_url'] = admin_url( "admin.php?page=wc-orders&action=edit&id={$id}" );
		} else {
			$post['edit_url'] = get_edit_post_link( $id );
		}
		$post['title']    = $id;
		return $post;
    }
     /**
	  * Is HPOS table enabled.
	  *
	  * @return bool
	  */
      public static function wt_get_order_table_name() {
		global $wpdb;
		$order_table_enabled =false;
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				$order_table_name = $wpdb->prefix . 'wc_orders';
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Its necessary to use direct database query.
				$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wc_orders'" );
				// phpcs:enable
			if ( $table_exists === $order_table_name ) {
				require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
				$order_table_enabled = OrderUtil::custom_orders_table_usage_is_enabled() ? true : false;
			}
		}
		return $order_table_enabled;

	}
}
}

new Wt_Import_Export_For_Woo_Order_Basic_Order();
