<?php

/**
 * Order section of the plugin
 *
 * @link            
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;
class Wt_Import_Export_For_Woo_Order {

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
        if(!Wt_Import_Export_For_Woo_Common_Helper::check_base_version($this->module_base, $this->module_name, $this->min_base_version))
        {
            return;
        }
        if(!function_exists('is_plugin_active'))
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        if(!is_plugin_active('woocommerce/woocommerce.php'))
        {
            return;
        }


       
        $this->module_id = Wt_Import_Export_For_Woo::get_module_id($this->module_base);
        self::$module_id_static = $this->module_id;
                
        add_filter('wt_iew_exporter_post_types', array($this, 'wt_iew_exporter_post_types'), 10, 1);
        add_filter('wt_iew_importer_post_types', array($this, 'wt_iew_exporter_post_types'), 10, 1);

        add_filter('wt_iew_exporter_alter_filter_fields', array($this, 'exporter_alter_filter_fields'), 10, 3);
        
        add_filter('wt_iew_exporter_alter_mapping_fields', array($this, 'exporter_alter_mapping_fields'), 10, 3);        
        add_filter('wt_iew_importer_alter_mapping_fields', array($this, 'get_importer_post_columns'), 10, 3);  
        
        add_filter('wt_iew_exporter_alter_advanced_fields', array($this, 'exporter_alter_advanced_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_advanced_fields', array($this, 'importer_alter_advanced_fields'), 10, 3);
        
        add_filter('wt_iew_exporter_alter_meta_mapping_fields', array($this, 'exporter_alter_meta_mapping_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_meta_mapping_fields', array($this, 'importer_alter_meta_mapping_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);

        add_filter('wt_iew_exporter_do_export', array($this, 'exporter_do_export'), 10, 7);
        add_filter('wt_iew_importer_do_import', array($this, 'importer_do_import'), 10, 8);

        add_filter('wt_iew_importer_steps', array($this, 'importer_steps'), 10, 2);
        
        
        add_action('admin_footer-edit.php', array($this, 'wt_add_order_bulk_actions'));
        add_action('load-edit.php', array($this, 'wt_process_order_bulk_actions'));
		
		add_filter('wt_add_woocommerce_debug_tools', array($this, 'wt_order_debug_tools'));
    }

	/**
	 * Add more tools options under WC status > tools
	 * 
	 * @param array $tools WC Tools items
	 */
	public function wt_order_debug_tools($wc_tools) {

		$wc_tools['wt_delete_trashed_orders'] = array(
			'name' => __('Remove all trashed orders', 'wt-import-export-for-woo'),
			'button' => __('Delete all trashed orders', 'wt-import-export-for-woo'),
			'desc' => __('This tool will delete all trashed orders.', 'wt-import-export-for-woo'),
			'callback' => array($this, 'wt_remove_all_trashed_orders')
		);

		$wc_tools['wt_delete_all_orders'] = array(
			'name' => __('Remove all orders', 'wt-import-export-for-woo'),
			'button' => __('Delete all orders', 'wt-import-export-for-woo'),
			'desc' => __('This tool will delete all orders allowing you to start fresh.', 'wt-import-export-for-woo'),
			'callback' => array($this, 'wt_remove_all_orders')
		);

		return $wc_tools;
	}

	public function wt_remove_all_trashed_orders() {
		global $wpdb;
		$result = absint($wpdb->delete($wpdb->posts, array('post_type' => 'shop_order', 'post_status' => 'trash')));

		$wpdb->query("DELETE pm
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
			WHERE wp.ID IS NULL");

		// Delete order items with no post
		$wpdb->query("DELETE oi
                        FROM {$wpdb->prefix}woocommerce_order_items oi
                        LEFT JOIN {$wpdb->posts} wp ON wp.ID = oi.order_id
                        WHERE wp.ID IS NULL");

		// Delete order item meta with no post
		$wpdb->query("DELETE om
                        FROM {$wpdb->prefix}woocommerce_order_itemmeta om
                        LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = om.order_item_id
                        WHERE oi.order_item_id IS NULL");
		echo '<div class="updated"><p>' . sprintf(__('%d Orders Deleted', 'wt-import-export-for-woo'), ( $result)) . '</p></div>';
	}

	public function wt_remove_all_orders() {
		global $wpdb;

		$result = absint($wpdb->delete($wpdb->posts, array('post_type' => 'shop_order')));

		$wpdb->query("DELETE pm
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
			WHERE wp.ID IS NULL");

		// Delete order items with no post
		$wpdb->query("DELETE oi
                        FROM {$wpdb->prefix}woocommerce_order_items oi
                        LEFT JOIN {$wpdb->posts} wp ON wp.ID = oi.order_id
                        WHERE wp.ID IS NULL");

		// Delete order item meta with no post
		$wpdb->query("DELETE om
                        FROM {$wpdb->prefix}woocommerce_order_itemmeta om
                        LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = om.order_item_id
                        WHERE oi.order_item_id IS NULL");
		echo '<div class="updated"><p>' . sprintf(__('%d Orders Deleted', 'wt-import-export-for-woo'), $result) . '</p></div>';
	}

	public function wt_add_order_bulk_actions() {
        global $post_type, $post_status;

        if ($post_type == 'shop_order' && $post_status != 'trash') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    var $downloadOrders = $('<option>').val('wt_iew_download_orders').text('<?php _e('Export as CSV', 'wt-import-export-for-woo') ?>');
                    $('select[name^="action"]').append($downloadOrders);
                });
            </script>
            <?php
        }
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
            if (!in_array($action, array('wt_iew_download_orders'))) {
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
            @set_time_limit(0);

            if ($action == 'wt_iew_download_orders') {
                
                
                include_once( 'export/class-wt-orderimpexpcsv-exporter.php' );
                Wt_Import_Export_For_Woo_Order_Bulk_Export::do_export('shop_order', $order_ids);
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
            $steps['advanced']['description']=__('Use options from below to decide updates to existing orders, batch import count or schedule an import. You can also save the template file for future imports.', 'wt-import-export-for-woo');
        }
        return $steps;
    }
    
    public function importer_do_import($import_data, $base, $step, $form_data, $selected_template_data, $method_import, $batch_offset, $is_last_batch) {        
        if ($this->module_base != $base) {
            return $import_data;
        }             
        
        if(0 == $batch_offset){                        
            $memory = size_format(wt_let_to_num(ini_get('memory_limit')));
            $wp_memory = size_format(wt_let_to_num(WP_MEMORY_LIMIT));                      
            Wt_Import_Export_For_Woo_Logwriter::write_log($this->module_base, 'import', '---[ New import started at '.date('Y-m-d H:i:s').' ] PHP Memory: ' . $memory . ', WP Memory: ' . $wp_memory);
        }
                
        include plugin_dir_path(__FILE__) . 'import/import.php';
        $import = new Wt_Import_Export_For_Woo_Order_Import($this);
        
        $response = $import->prepare_data_to_import($import_data,$form_data,$batch_offset,$is_last_batch);
        
        if($is_last_batch){
            Wt_Import_Export_For_Woo_Logwriter::write_log($this->module_base, 'import', '---[ Import ended at '.date('Y-m-d H:i:s').']---');
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
        $export = new Wt_Import_Export_For_Woo_Order_Export($this);
                      
        $data_row = $export->prepare_data_to_export($form_data, $batch_offset);
        
        $header_row = $export->prepare_header(); 

        $export_data = array(
            'head_data' => $header_row,
            'body_data' => isset($data_row['data']) ? $data_row['data'] : array(),
            'total' => isset($data_row['total']) ? $data_row['total'] : array(),
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
    public function wt_iew_exporter_post_types($arr) {
        $arr['order'] = __('Order', 'wt-import-export-for-woo');
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
            $mapping_enabled_fields['meta'] = array(__('Custom meta', 'wt-import-export-for-woo'), 1);
            $mapping_enabled_fields['hidden_meta'] = array(__('Hidden meta', 'wt-import-export-for-woo'), 0);
        }
        return $mapping_enabled_fields;
    }

    public function exporter_alter_meta_mapping_fields($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }

        foreach ($fields as $key => $value) {
            switch ($key) {
                
                case 'meta':
                    $meta_attributes = array();
                    $found_meta = $this->wt_get_found_meta();
                    foreach ($found_meta as $meta) {
                        $fields[$key]['fields']['meta:' . $meta] = 'meta:' . $meta;
                    }

                    break;               

                case 'hidden_meta':
                    $found_hidden_meta = $this->wt_get_found_hidden_meta();
                    foreach ($found_hidden_meta as $hidden_meta) {
                        $fields[$key]['fields']['meta:' . $hidden_meta] = 'meta:' . $hidden_meta;
                    }
                    break;
                default:
                    break;
            }
        }

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

        if (!empty($this->found_hidden_meta)) {
            return $this->found_hidden_meta;
        }

        // Loop products and load meta data
        $found_hidden_meta = array();
        // Some of the values may not be usable (e.g. arrays of arrays) but the worse
        // that can happen is we get an empty column.

        $all_meta_keys = $this->wt_get_all_meta_keys();
        $csv_columns = self::get_order_post_columns();
        $exclude_hidden_meta_columns = $this->wt_get_exclude_hidden_meta_columns();
        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) != '_') || ((substr((string) $meta, 0, 1) == '_') && in_array(substr((string) $meta,1), array_keys($csv_columns)) ) || in_array($meta, $exclude_hidden_meta_columns) || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
                continue;

            $found_hidden_meta[] = $meta;
        }

        $found_hidden_meta = array_diff($found_hidden_meta, array_keys($csv_columns));

        $this->found_hidden_meta = $found_hidden_meta;
        return $this->found_hidden_meta;
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

        $all_meta_keys = self::get_all_metakeys();

        $this->all_meta_keys = $all_meta_keys;
        return $this->all_meta_keys;
    }

    /**
     * Get a list of all the meta keys for a post type. This includes all public, private,
     * used, no-longer used etc. They will be sorted once fetched.
     */
    public static function get_all_metakeys() {
        global $wpdb;
        $limit =  apply_filters( 'wt_ier_limit_order_meta', 2000);
        if(self::wt_get_order_table_name()){
            $meta = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key
                                    FROM {$wpdb->prefix}wc_orders_meta AS pm
                                    LEFT JOIN {$wpdb->prefix}wc_orders AS p ON p.id = pm.order_id
                                    WHERE p.type = 'shop_order'
                                    ORDER BY pm.meta_key LIMIT %d", $limit)         
                                    );
        }else{
            $meta = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key
                                    FROM {$wpdb->postmeta} AS pm
                                    LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
                                    WHERE p.post_type = 'shop_order'
                                    ORDER BY pm.meta_key LIMIT %d", $limit)         
                                    );
        }
        //sort($meta);
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
        $out['exclude_already_exported'] = array(
            'label' => __("Exclude already exported", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                'Yes' => __('Yes', 'wt-import-export-for-woo'),
                'No' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => 'No',
            'field_name' => 'exclude_already_exported',
            'help_text' => __("Option 'Yes' excludes the previously exported orders.", 'wt-import-export-for-woo'),
        );
        $out['exclude_line_items'] = array(
            'label' => __("Exclude line items", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                'Yes' => __('Yes', 'wt-import-export-for-woo'),
                'No' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => 'No',
            'field_name' => 'exclude_line_items',
            'help_text' => __("Option 'Yes' excludes the line items", 'wt-import-export-for-woo'),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_exclude_line_items',
            )
        );
        $out['export_to_separate'] = array(
            'label' => __("Export line items in", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                'default' => __('Migration mode', 'wt-import-export-for-woo'),
                'column' => __('Separate columns', 'wt-import-export-for-woo'),
                'row' => __('Separate rows', 'wt-import-export-for-woo')                
            ),
            'value' => 'default',
            'field_name' => 'export_to_separate',
            //'help_text' => __("Option 'Yes' exports the line items within the order into separate columns in the exported file.", 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('This option will export each line item details into a single column. This option is mainly used for the order migration purpose.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_export_to_separate', 'value'=>'default')
                    )
                ),
                array(
                    'help_text'=> __('This option will export each line item details into a separate column.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_export_to_separate', 'value'=>'column')
                    )
                ),
                array(
                    'help_text'=> __('This option will export each line item details into a separate row.', 'wt-import-export-for-woo'),
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
        
        $out['skip_new'] = array(
            'label' => __("Update Only", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'skip_new',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('The store is updated with the data from the input file only for matching/existing records from the file. If the post ID of the order being imported exists already(for any of the other post types like coupon, product, user, pages, media etc) skip the order from being inserted into the store.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_skip_new', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> __('The entire data from the input file is processed for an update or insert as the case maybe.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_skip_new', 'value'=>0)
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_skip_new',
            )
        ); 

        $out['found_action_merge'] = array(
            'label' => __("If order exists in the store", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
//                'import' => __('Import as new item'),
                'skip' => __('Skip', 'wt-import-export-for-woo'),
                'update' => __('Update', 'wt-import-export-for-woo'),                
            ),
            'value' => 'skip',
            'field_name' => 'found_action',
            'help_text' => __('Orders are matched by their order IDs.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Retains the order in the store as is and skips the matching order from the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('Update order as per data from the input file', 'wt-import-export-for-woo'),
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
        
        $out['merge_empty_cells'] = array(
            'label' => __("Update even if empty values", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'merge_empty_cells',
            'help_text' => __('Updates the order data respectively even if some of the columns in the input file contains empty value. <p>Note: This is not applicable for line_items, tax_items, fee _items and shipping_items</p>', 'wt-import-export-for-woo'),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'wt_iew_found_action',
                'val'=>'update',
            )
        );
        
        $out['conflict_with_existing_post'] = array(
            'label' => __("If conflict with an existing Post ID", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(                
                'skip' => __('Skip item', 'wt-import-export-for-woo'),
                'import' => __('Import as new item', 'wt-import-export-for-woo'),
            ),
            'value' => 'skip',
            'field_name' => 'id_conflict',
            'help_text' => __('All the items within woocommerce/wordpress are treated as posts and assigned a unique ID as and when they are created in the store. The post ID uniquely identifies an item irrespective of the post type be it order/product/pages/attachments/revisions etc.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('If the post ID of the order being imported exists already(for any of the other post types like coupon, product, user, pages, media etc) skip the order from being inserted into the store.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_id_conflict', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('Insert the order into the store with a new order ID(next available post ID) different from the value in the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_id_conflict', 'value'=>'import')
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'wt_iew_skip_new',
                'val'=>'0',
                'depth'=>0,
            )
        );

        
//        $out['merge'] = array(
//            'label' => __("Update order if exists"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                '1' => __('Yes'),
//                '0' => __('No')
//            ),
//            'value' => '0',
//            'field_name' => 'merge',
//            'help_text' => __('Existing orders are identified by their IDs.'),
//            'form_toggler'=>array(
//                'type'=>'parent',
//                'target'=>'wt_iew_merge'
//            )
//        );  
//        
//        $out['skip_new'] = array(
//            'label' => __("Skip New Order"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                '1' => __('Yes'),
//                '0' => __('No')
//            ),
//            'value' => '0',
//            'field_name' => 'skip_new',
//            'help_text' => __('While updating existing order, enable this to skip order which are not already present in the store.'),
//            'form_toggler'=>array(
//                'type'=>'child',
//                'id'=>'wt_iew_merge',
//                'val'=>'1',
//            )
//        );
//        
//        $out['found_action_merge'] = array(
//            'label' => __("Skip or import if Order not found"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                'import' => __('Import as new item'),
//                'skip' => __('Skip item'),
//                
//            ),
//            'value' => 'import',
//            'field_name' => 'found_action',
//            'help_text' => __('Skip or import if Order not found.'),
//            'form_toggler'=>array(
//                'type'=>'',
//                'id'=>'wt_iew_merge',
//                'val'=>'1',
//                'target'=>'wt_iew_use_same_id'
//            )
//        );
//        
//        $out['found_action_import'] = array(
//            'label' => __("Skip or import"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                'import' => __('Import as new item'),
//                'skip' => __('Skip item'),
//                
//            ),
//            'value' => 'import',
//            'field_name' => 'found_action',
//            'help_text' => __('Skip or import if found a non Order post type in given ID.'),
//            'form_toggler'=>array(
//                'type'=>'',
//                'id'=>'wt_iew_merge',
//                'val'=>'0',
//                'target'=>'wt_iew_use_same_id'
//            )
//        );
//        
//        $out['use_same_id'] = array(
//            'label' => __("Use the same ID for Order on import"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                '1' => __('Yes'),
//                '0' => __('No')
//            ),
//            'value' => '0',
//            'field_name' => 'use_same_id',
//            'help_text' => __('Use the same ID for Order on import.'),
//            'form_toggler'=>array(
//                'type'=>'',
//                'id'=>'wt_iew_use_same_id',
//                'val'=>'import',
//            )
//        );
//        
//        $out['merge_empty_cells'] = array(
//            'label' => __("Merge empty cells"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                '1' => __('Yes'),
//                '0' => __('No')
//            ),
//            'value' => '0',
//            'field_name' => 'merge_empty_cells',
//            'help_text' => __('Check to merge the empty cells in CSV, otherwise empty cells will be ignored.'),
//            'form_toggler'=>array(
//                'type'=>'child',
//                'id'=>'wt_iew_merge',
//                'val'=>'1',
//            )
//        );
//        

        
      /* Temparay commented */
        $out['status_mail'] = array(
            'label' => __("Email customer on order status change", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'status_mail',
            'help_text' => __('Select ‘Yes’ if an email is to be sent to the customer of the corresponding order when the status is changed during import.', 'wt-import-export-for-woo'),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'wt_iew_merge',
                'val'=>'1',
            )
        );
        
        $out['create_user'] = array(
            'label' => __("Create user", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'create_user',
            'help_text' => __('Select ‘Yes’ if you want the application to create the customer associated with the order being imported if the customer does not exist in the store. Only the username(extracted from the email id), email id and address( shipping address) is added to the profile.', 'wt-import-export-for-woo'),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'notify_customer',
            )
        );
        
        $out['notify_customer'] = array(
            'label' => __("Notify the customer", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'notify_customer',
            'help_text' => __('Notify the customer by email when created successfully. Customer will have to use the forgot password link to access the account.', 'wt-import-export-for-woo'),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'notify_customer',
                'val'=>'1',
            )
        );
 
        $out['ord_link_using_sku'] = array(
            'label' => __("Link products using SKU instead of Product ID", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'ord_link_using_sku',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Link the products associated with the imported orders by their SKU.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_ord_link_using_sku', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> sprintf(__('Link the products associated with the imported orders by their Product ID. In case of a conflict with %sIDs of other existing post types%s the link cannot be established.', 'wt-import-export-for-woo'), '<b>', '</b>'),
                    'condition'=>array(
                        array('field'=>'wt_iew_ord_link_using_sku', 'value'=>0)
                    )
                )
            ),
        );
        
        
        $out['delete_existing'] = array(
            'label' => __( 'Delete non-matching orders from store', 'wt-import-export-for-woo' ),
            'type' => 'checkbox',
            'checkbox_fields' => array(
                1 => __( 'Enable', 'wt-import-export-for-woo' ),
            ),
            'value' => 0,
            'field_name' => 'delete_existing',
            'tip_description' => __( 'For e.g: if you have an order #123 in your store and your import file has orders #234, #345; the order #123 is deleted from the store prior to importing orders #234, #345.', 'wt-import-export-for-woo' ),
        );
		
	$out['update_stock_details'] = array(
            'label' => __( 'Update stock details', 'wt-import-export-for-woo' ),
            'type' => 'checkbox',
            'checkbox_fields' => array( 1 => __( 'Enable' ) ),
            'value' => 0,
            'field_name' => 'update_stock_details',
            'help_text' => __( 'Updates the sale count and stock quantity of a product associated with the order.', 'wt-import-export-for-woo' ),
            'tip_description' => __( 'Note: Ensure the manage stock option is enabled. This feature is not meant to work for the refunded, cancelled or failed order statuses.', 'wt-import-export-for-woo' ),
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

        if ($base === $this->module_base)
        {
            /* altering help text of default fields */
            $fields['limit']['label']=__('Total number of orders to export', 'wt-import-export-for-woo'); 
            $fields['limit']['help_text']=__('Exports specified number of orders. e.g. Entering 500 with a skip count of 10 will export orders from 11th to 510th position.', 'wt-import-export-for-woo');
            $fields['offset']['label']=__('Skip first <i>n</i> orders', 'wt-import-export-for-woo');
            $fields['offset']['help_text']=__('Skips specified number of orders from the beginning. e.g. Enter 10 to skip first 10 orders from export.', 'wt-import-export-for-woo');

			
			
			$fields['orderfrom_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3"> '.__( 'Filter by date', 'wt-import-export-for-woo' ).' <span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);
			
			$fields['order_date_options'] = array(
				'tr_html' => '<tr class="orderfilter orderfilter-first">
                <td colspan="3">
					<select name="wt_iew_date_option" id="wt_iew_date_option" style="width:150px;float:left;margin-right:10px;">
                            <option value="post_date">'.__('Order date', 'wt-import-export-for-woo').'</option>
							<option value="post_modified">'.__('Modified date', 'wt-import-export-for-woo').'</option>	
							<option value="date_paid">'.__('Paid date', 'wt-import-export-for-woo').'</option>
                            <option value="date_completed">'.__('Completed date', 'wt-import-export-for-woo').'</option>
                        </select>
				</td>
				<td colspan="3" style="margin-top: 10px;">
				<label for="date_type" style="padding: 7px;background-color: #cccc;margin-right: 7px;"><input type="radio" id="wt_iew_date_type" name="wt_iew_date_type" value="custom" checked> '.__('Custom', 'wt-import-export-for-woo').'  </label>
				<label for="date_type" style="padding: 7px;background-color: #cccc;margin-right: 7px;"><input type="radio" id="wt_iew_date_type" name="wt_iew_date_type" value="last_week" > '.__('Last week', 'wt-import-export-for-woo').'  </label>
				<label for="date_type" style="padding: 7px;background-color: #cccc;margin-right: 7px;"><input type="radio" id="wt_iew_date_type" name="wt_iew_date_type" value="last_month" > '.__('Last month', 'wt-import-export-for-woo').'  </label>
				<label for="date_type" style="padding: 7px;background-color: #cccc;margin-right: 7px;"><input type="radio" id="wt_iew_date_type" name="wt_iew_date_type" value="last_year" > '.__('Last year', 'wt-import-export-for-woo').'  </label><br/><br/>
				<input placeholder="'.__('Date from', 'wt-import-export-for-woo').'" type="text" class="wt_iew_datepicker" name="wt_iew_date_from" value=""> <br/>
				<input placeholder="'.__('Date to', 'wt-import-export-for-woo').'" type="text" class="wt_iew_datepicker" name="wt_iew_date_to" value="">
				</td>
				</tr>'
				
			);
			
			$fields['orderids_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by order ID', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);
			
            $fields['orders'] = array(
                'label' => __('Order IDs', 'wt-import-export-for-woo'),
                'placeholder' => __('Enter order IDs separated by comma', 'wt-import-export-for-woo'),
                'field_name' => 'orders',
                'sele_vals' => '',
                'help_text' => __('Enter order IDs separated by comma to export specific orders.', 'wt-import-export-for-woo'),
                'type' => 'text',
                'css_class' => '',
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
            $fields['orderstatus_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by order status', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);            
            $fields['order_status'] = array(
                'label' => __('Order Statuses', 'wt-import-export-for-woo'),
                'placeholder' => __('All Order', 'wt-import-export-for-woo'),
                'field_name' => 'order_status',
                'sele_vals' => self::get_order_statuses(),
                'help_text' => __('Filter orders by their status type. You can specify more than one type for export.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
			$fields['orderproducts_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by product', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);			
            $fields['products'] = array(
                'label' => __('Product', 'wt-import-export-for-woo'),
                'placeholder' => __('Search for a product&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'products',
                'sele_vals' => array(),
                'help_text' => __('Export orders containing specific products. Enter the product name or SKU or ID to export orders containing specified products.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-product-search',
                'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
			
			$fields['orderproductscat_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by product category', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);			
            $fields['order_productscat'] = array(
                'label' => __('Product category', 'wt-import-export-for-woo'),
                'placeholder' => __('Search for a category&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'order_productscat',
                'sele_vals' => $this->get_product_categories(),
                'help_text' => __('Export orders that belong to specific product categories. Search and select one or multiple categories.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );	
			
			$fields['orderemail_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by customer', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);		
            $fields['email'] = array(
                'label' => __('Email'),
                'placeholder' => __('Search for a customer&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'email',
                'sele_vals' => array(),
                'help_text' => __('Input the customer email to export orders pertaining to only these customers.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-customer-search',
                'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
						
			$fields['ordervendor_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by vendor', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);		
            $fields['vendor'] = array(
                'label' => __('Vendor'),
                'placeholder' => __('Search for a vendor&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'vendor',
                'sele_vals' => array(),
                'help_text' => __('Input the vendor email to export orders pertaining to only these vendors.', 'wt-import-export-for-woo'),
                'type' => 'select',
                'css_class' => 'wc-customer-search',
                'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
			
			$fields['ordercoupons_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by coupons', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);	
			
            $fields['coupons'] = array(
                'label' => __('Coupons', 'wt-import-export-for-woo'),
                'placeholder' => __('Search for a coupon&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'coupons',
                'sele_vals' => array(),
                'help_text' => __('Exports orders redeemed with specific coupon codes. Multiple coupon codes can be selected.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wt-coupon-search',
				'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
			
			
			$fields['orderpaymentmethods_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by payment methods', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);
			
			$payment_method_list = array();
			$payment_methods = WC()->payment_gateways->payment_gateways();
			foreach ($payment_methods as $payment_method) {
				$payment_method_list[$payment_method->id] = $payment_method->title;
			}
            $fields['order_payment_method'] = array(
                'label' => __('Payment methods', 'wt-import-export-for-woo'),
                'placeholder' => __('Search for a payment method&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'order_payment_method',
                'sele_vals' => $payment_method_list,
                'help_text' => __('Exports orders placed with specific payment methods.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
				'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
			
			$fields['ordershippingmethods_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by shipping methods', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);	
			
			$shipping_method_list = array();
			$shipping_methods = WC()->shipping->load_shipping_methods();
			foreach ($shipping_methods as $shipping_method) {
				$shipping_method_list[$shipping_method->id] = $shipping_method->method_title;
			}
            $fields['shipping_method'] = array(
                'label' => __('Shipping methods', 'wt-import-export-for-woo'),
                'placeholder' => __('Search for a shipping method&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'shipping_method',
                'sele_vals' => $shipping_method_list,
                'help_text' => __('Exports orders placed with specific shipping method.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
				'validation_rule' => array('type'=>'text_arr'),
				'tr_class' => 'orderfilter',
				'merge_right' => true				
            );
			
			$fields['orderbillingaddr_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by billing address', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);	
			

			$fields['billing_addr_options'] = array(
				
				'tr_html' => '<tr class="orderfilter">
                <th class="">
                    <label>'.__('Billing address', 'wt-import-export-for-woo').'</label>
                </th>
                <td colspan="3">
					<select name="wt_billing_locations" id="wt_billing_locations" style="width:150px;float:left;margin-right:10px;">
                            <option>'.__('--Select--', 'wt-import-export-for-woo').'</option>
							<option>'.__('Country', 'wt-import-export-for-woo').'</option>
                            <option>'.__('State', 'wt-import-export-for-woo').'</option>
                        </select>
                        <select name="wt_billing_compare" id="wt_billing_compare" style="width:130px;float:left;margin-right:10px;">
                            <option value="=">is equal to</option>
                            <option value="&lt;&gt;">not equal to</option>
                        </select>
						<button id="add_billing_locations" class="button-secondary">'.__('Select', 'wt-import-export-for-woo').'</button>
                                <select id="wt_iew_billing_locations_check" class="wc-enhanced-select" multiple
                        name="wt_iew_billing_locations_check"
                        style="width: 100%; max-width: 25%;">
                </select>
				</td></tr>'
				
			);
			
			
			$fields['ordershippingaddr_empty_row'] = array(
				'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__( 'Filter by shipping address', 'wt-import-export-for-woo' ).'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
			);	
			
			$fields['shipping_addr_options'] = array(
				
				'tr_html' => '<tr class="orderfilter">
                <th class="">
                    <label>'.__('Shipping address', 'wt-import-export-for-woo').'</label>
                </th>
                <td colspan="3">
					<select name="wt_shipping_locations" id="wt_shipping_locations" style="width:150px;float:left;margin-right:10px;">
                            <option>'.__('--Select--', 'wt-import-export-for-woo').'</option>
							<option>'.__('Country', 'wt-import-export-for-woo').'</option>
                            <option>'.__('State', 'wt-import-export-for-woo').'</option>
                        </select>
                        <select name="wt_shipping_compare" id="wt_shipping_compare" style="width:130px;float:left;margin-right:10px;">
                            <option value="=">is equal to</option>
                            <option value="&lt;&gt;">not equal to</option>
                        </select>
						<button id="add_shipping_locations" class="button-secondary">'.__('Select', 'wt-import-export-for-woo').'</button>
                                <select id="wt_iew_shipping_locations_check" class="wc-enhanced-select" multiple
                        name="wt_iew_shipping_locations_check"
                        style="width: 100%; max-width: 25%;">
                </select>
				</td></tr>'
				
			);
			
			

		// Move the limit and offset filters to the bottom ( less priority ).
		$limit_options = $fields['limit'];
		$offset_options = $fields['offset'];
		unset( $fields['limit'], $fields['offset'] );
		$fields['orderlimit_empty_row'] = array(
			'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__('Total number of orders to export', 'wt-import-export-for-woo').'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
		);
		$fields['limit'] = $limit_options;
		$fields['limit']['tr_class'] = 'orderfilter';
		$fields['limit']['merge_right'] = true;
		$fields['orderoffset_empty_row'] = array(
			'tr_html' => '<tr class="orderfilterby"><td colspan="3">'.__('Number of orders to skip from the beginning', 'wt-import-export-for-woo').'<span style="float:right;" class="dashicons dashicons-arrow-down-alt2"></span></td></tr>'
		);
		$fields['offset'] = $offset_options;
		$fields['offset']['tr_class'] = 'orderfilter';
		$fields['offset']['merge_right'] = true;
		}
        return $fields;
    }

    public static function wt_get_product_id_by_sku($sku) {
        global $wpdb;
        $post_exists_sku = $wpdb->get_var($wpdb->prepare("
	    		SELECT $wpdb->posts.ID
	    		FROM $wpdb->posts
	    		LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )
	    		WHERE $wpdb->posts.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )
	    		AND $wpdb->postmeta.meta_key = '_sku' AND $wpdb->postmeta.meta_value = '%s'
	    		", $sku));
        if ($post_exists_sku) {
            return $post_exists_sku;
        }
        return false;
    }
    
    public function get_item_by_id($id) {
        $post['edit_url']=get_edit_post_link($id);
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
	 * Gets the product categories.
	 * 
	 * @return array
	 */
	public function get_product_categories() {


        if(class_exists("WP_Term_Query")){
            $term_query = new \WP_Term_Query( [
                'taxonomy'	 => 'product_cat',
                'hide_empty' => true,
            ] );
    
            $product_categories_arr = array();
    
            $product_categories = $term_query->get_terms();
        }else{
            $sql = "SELECT t.name , t.slug 
            FROM wp_terms AS t
            INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'product_cat'
            AND tt.count > 0;
            ";
             global $wpdb;
            $product_categories = $wpdb->get_results($sql);
        }
        foreach ($product_categories as $key => $product_category ) {
            $product_categories_arr[$product_category->slug] = $product_category->name;
        }
		return $product_categories_arr;
	}
	
	/**
	 * 
	 * @global type $wpdb
	 * @param string $type
	 * @param string $key
	 * @return array
	 */
	public static function get_order_meta_values( $type, $key ) {
		global $wpdb;

		$order_ids   = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' ORDER BY ID DESC LIMIT 1000" );

		if( empty($order_ids) )
			return array();

		$order_ids   = implode( ",", $order_ids );

		$query   = $wpdb->prepare( 'SELECT DISTINCT meta_value FROM ' . $wpdb->postmeta . " WHERE meta_key = %s AND post_id IN($order_ids)",
			array( $type . strtolower( $key ) ) );
		$results = $wpdb->get_col( $query );
		$data    = array_filter( $results );
		sort( $data );

		echo json_encode($data);
	}
    /**
	  * Is HPOS table enabled.
	  *
	  * @return bool
	  */
    public static function wt_get_order_table_name() {
	    if(get_option('woocommerce_custom_orders_table_enabled') === 'yes'){
            return true;
		}else{
            return false;
        }
	}
}

new Wt_Import_Export_For_Woo_Order();

// Add category/tag import export addon
include_once( __DIR__ . "/../coupon/coupon.php" );
include_once( __DIR__ . "/../subscription/subscription.php" );
