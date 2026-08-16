<?php

/**
 * Coupon section of the plugin
 *
 * @link           
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}

class Wt_Import_Export_For_Woo_Coupon {

    public $module_id = '';
    public static $module_id_static = '';
    public $module_base = 'coupon';
    public $module_name = 'Coupon Import Export for WooCommerce';
    public $min_base_version= '1.0.0'; /* Minimum `Import export plugin` required to run this add on plugin */

    private $importer = null;
    private $exporter = null;
    private $all_meta_keys = array();
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
        
        add_filter('wt_iew_importer_alter_advanced_fields', array($this, 'importer_alter_advanced_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_meta_mapping_fields', array($this, 'exporter_alter_meta_mapping_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_meta_mapping_fields', array($this, 'importer_alter_meta_mapping_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);

        add_filter('wt_iew_exporter_do_export', array($this, 'exporter_do_export'), 10, 7);
        add_filter('wt_iew_importer_do_import', array($this, 'importer_do_import'), 10, 8);

        add_filter('wt_iew_importer_steps', array($this, 'importer_steps'), 10, 2);
		
		
		add_action('admin_footer-edit.php', array($this, 'add_coupons_bulk_actions'));
        add_action('load-edit.php', array($this, 'process_coupons_bulk_actions'));
    }


    public function add_coupons_bulk_actions() {
        global $post_type, $post_status;

        if ($post_type == 'shop_coupon' && $post_status != 'trash') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    var $downloadToCSV = $('<option>').val('wt_iew_download_coupons').text('<?php _e('Download as CSV', 'wt-import-export-for-woo') ?>');
                    $('select[name^="action"]').append($downloadToCSV);
                });
            </script>
            <?php
        }
    }

    public function process_coupons_bulk_actions() {
        global $typenow;
        if ($typenow == 'shop_coupon') {
            // get the action list
            $wp_list_table = _get_list_table('WP_Posts_List_Table');
            $action = $wp_list_table->current_action();
            if (!in_array($action, array('wt_iew_download_coupons'))) {
                return;
            }
            check_admin_referer('bulk-posts');

            if (isset($_REQUEST['post'])) {
                $coupon_ids = array_map('absint', $_REQUEST['post']);
            }
            if (empty($coupon_ids)) {
                return;
            }
            @set_time_limit(0);

            if ($action == 'wt_iew_download_coupons') {
                include_once( 'export/class-wt-cpnimpexpcsv-exporter.php' );
                Wt_Import_Export_For_Woo_Coupon_Bulk_Export::do_export('shop_coupon', $coupon_ids);
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
            $steps['advanced']['description'] = __('Use options from below to decide updates to existing coupons, batch import count or schedule an import. You can also save the template file for future imports.', 'wt-import-export-for-woo');
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
        $import = new Wt_Import_Export_For_Woo_Coupon_Import($this);
        
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
        $export = new Wt_Import_Export_For_Woo_Coupon_Export($this);

        $header_row = $export->prepare_header();

        $data_row = $export->prepare_data_to_export($form_data, $batch_offset);

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
    
    /*
     * Setting default export columns for quick export
     */

    public function set_export_columns_for_quick_export($form_data) {

        $post_columns = self::get_coupon_post_columns();

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

    
    /**
     * Adding current post type to export list
     *
     */
    public function wt_iew_exporter_post_types($arr) {
        $arr['coupon'] = __('Coupon');
        return $arr;
    }

    public static function get_coupon_types() {
        $coupon_types   = wc_get_coupon_types();
        return apply_filters('wt_iew_export_coupon_types',  $coupon_types);
        
    }

    public static function get_coupon_statuses() {
        $statuses = array('publish', 'private', 'draft', 'pending', 'future'); 
        return apply_filters('wt_iew_export_coupon_statuses', array_combine($statuses, $statuses));
    }

    public static function get_coupon_sort_columns() {                
        $sort_columns = array('ID', 'post_parent', 'post_title', 'post_date', 'post_modified', 'post_author', 'menu_order', 'comment_count');
        return apply_filters('wt_iew_export_coupon_sort_columns', array_combine($sort_columns, $sort_columns));
    }

    public static function get_coupon_post_columns() {
        return include plugin_dir_path(__FILE__) . 'data/data-coupon-post-columns.php';
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
            $mapping_enabled_fields['meta'] = array(__('Additional meta', 'wt-import-export-for-woo'), 1);
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


        $csv_columns = self::get_coupon_post_columns();


        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) == '_') || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
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
        $csv_columns = self::get_coupon_post_columns();
        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) != '_') || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
                continue;

            $found_hidden_meta[] = $meta;
        }

        $found_hidden_meta = array_diff($found_hidden_meta, array_keys($csv_columns));

        $this->found_hidden_meta = $found_hidden_meta;
        return $this->found_hidden_meta;
    }


    public function wt_get_all_meta_keys() {

        if (!empty($this->all_meta_keys)) {
            return $this->all_meta_keys;
        }

        $all_meta_keys = self::get_all_metakeys('shop_coupon');

        $this->all_meta_keys = $all_meta_keys;
        return $this->all_meta_keys;
    }

        
    public static function get_all_metakeys($post_type = 'shop_coupon') {
        global $wpdb;

        $meta = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'publish', 'pending', 'private', 'draft' ) LIMIT 2010", $post_type
                ));

        sort($meta);
		
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
            $fields = self::get_coupon_post_columns();
        }
        return $fields;
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
                    'help_text'=> __('The store is updated with the data from the input file only for matching/existing records from the file.', 'wt-import-export-for-woo'),
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
        
        $out['merge_with'] = array(
            'label' => __("Match coupons by their", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                'id' => __('ID'),
                'code' => __('Coupon Code'),
                
            ),
            'value' => 'id',
            'field_name' => 'merge_with',
            'help_text' => __('The products are either looked up based on their ID or coupon code as per the selection.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('If the post ID of the coupon being imported exists already(for any of the other post types like coupon, order, user, pages, media etc) skip the coupon from being inserted into the store.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_merge_with', 'value'=>'id'),
                        'AND',
                        array('field'=>'wt_iew_skip_new', 'value'=>1)
                    )
                )
            )
        );
        
        $out['found_action_merge'] = array(
            'label' => __("If the coupon exists in the store", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
//                'import' => __('Import as new item'),
                'skip' => __('Skip', 'wt-import-export-for-woo'),
                'update' => __('Update', 'wt-import-export-for-woo'),                
            ),
            'value' => 'skip',
            'field_name' => 'found_action',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Retains the coupon in the store as is and skips the matching coupon from the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('Update coupon as per data from the input file', 'wt-import-export-for-woo'),
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
            'help_text' => __('Updates the coupon data respectively even if some of the columns in the input file contains empty value.', 'wt-import-export-for-woo'),
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
            'help_text' => __('All the items within WooCommerce/WordPress are treated as posts and assigned a unique ID as and when they are created in the store. The post ID uniquely identifies an item irrespective of the post type be it coupon/product/pages/attachments/revisions etc.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('If the post ID of the coupon being imported exists already(for any of the posts like coupon, order, user, pages, media etc) skip the coupon from being inserted into the store.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_id_conflict', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('Insert the coupon into the store with a new coupon ID(next available post ID) different from the value in the input file.', 'wt-import-export-for-woo'),
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
//            'label' => __("Update coupon if exists"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                '1' => __('Yes'),
//                '0' => __('No')
//            ),
//            'value' => '0',
//            'field_name' => 'merge',
//            'help_text' => __('Existing coupons are identified by their IDs or Coupon Codes'),
//            'form_toggler'=>array(
//                'type'=>'parent',
//                'target'=>'wt_iew_merge'
//            )
//        );   
//        
//        $out['merge_with'] = array(
//            'label' => __("Update Coupon with ID or Coupon Code"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                'id' => __('Update with id'),
//                'coupon_code' => __('Update Coupon with Coupon Code'),
//                
//            ),
//            'value' => 'id',
//            'field_name' => 'merge_with',
//            'help_text' => __('Update Coupon with ID or Coupon Code.'),
//            'form_toggler'=>array(
//                'type'=>'child',
//                'id'=>'wt_iew_merge',
//                'val'=>'1',
//            )
//        );
//        
//        $out['skip_new'] = array(
//            'label' => __("Skip new coupon"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                '1' => __('Yes'),
//                '0' => __('No')
//            ),
//            'value' => '0',
//            'field_name' => 'skip_new',
//            'help_text' => __('While updating existing coupons, enable this to skip coupons which are not already present in the store.'),
//            'form_toggler'=>array(
//                'type'=>'child',
//                'id'=>'wt_iew_merge',
//                'val'=>'1',
//            )
//        );
//        
//        $out['found_action_merge'] = array(
//            'label' => __("Skip or import if Coupon not find"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                'import' => __('Import as new item'),
//                'skip' => __('Skip item'),
//                
//            ),
//            'value' => 'import',
//            'field_name' => 'found_action',
//            'help_text' => __('Skip or import if Coupon not find.'),
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
//            'help_text' => __('Skip or import if found a non coupon post type in given ID.'),
//            'form_toggler'=>array(
//                'type'=>'',
//                'id'=>'wt_iew_merge',
//                'val'=>'0',
//                'target'=>'wt_iew_use_same_id'
//            )
//        );
//        
//        $out['use_same_id'] = array(
//            'label' => __("Use the same ID for Coupon on import"),
//            'type' => 'radio',
//            'radio_fields' => array(
//                '1' => __('Yes'),
//                '0' => __('No')
//            ),
//            'value' => '0',
//            'field_name' => 'use_same_id',
//            'help_text' => __('Use the same ID for Coupon on import.'),
//            'form_toggler'=>array(
//                'type'=>'',
//                'id'=>'wt_iew_use_same_id',
//                'val'=>'import',
//                'depth'=>2,
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
        
        
        
        $out['delete_existing'] = array(
            'label' => __("Delete non-matching coupons from store", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'delete_existing',
            'help_text' => __('Select ‘Yes’ if you need to remove the coupons from your store which are not present in the input file. For e.g, if you have a coupon A in your store and your import file has coupons B, C; the coupon A is deleted from the store prior to importing B and C.', 'wt-import-export-for-woo'),
        );
        
        $out['use_sku'] = array(
            'label' => __("Use product SKU for coupon restriction settings", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'use_sku',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Link the products by their SKUs under coupon restrictions for the imported coupons.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_use_sku', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> __('Link the products by their product IDs under coupon restrictions for the imported coupons. In case of a conflict with IDs of other existing post types the link will be empty.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_use_sku', 'value'=>0)
                    )
                )
            ),
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
			$fields['limit']['label']=__('Total number of coupons to export', 'wt-import-export-for-woo'); 
			$fields['limit']['help_text']=__('Exports specified number of coupons. e.g. Entering 500 with a skip count of 10 will export coupons from 11th to 510th position.', 'wt-import-export-for-woo');
			$fields['offset']['label']=__('Skip first <i>n</i> coupons', 'wt-import-export-for-woo');
			$fields['offset']['help_text']=__('Skips specified number of coupons from the beginning. e.g. Enter 10 to skip first 10 coupons from export.', 'wt-import-export-for-woo');

            $fields['statuses'] = array(
                'label' => __('Coupon Statuses', 'wt-import-export-for-woo'),
                'placeholder' => __('All Statuses', 'wt-import-export-for-woo'),
                'field_name' => 'statuses',
                'sele_vals' => self::get_coupon_statuses(),
                'help_text' => __('Export coupons by their status. You can specify more than one status if required.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr')
            );
            $fields['types'] = array(
                'label' => __('Coupon Type', 'wt-import-export-for-woo'),
                'placeholder' => __('All Types', 'wt-import-export-for-woo'),
                'field_name' => 'types',
                'sele_vals' => self::get_coupon_types(),
                'help_text' => __('Select the coupon type e.g, fixed cart, recurring etc to export only coupon of a specific type.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr')
            );
//            $fields['products'] = array(
//                'label' => __('Product'),
//                'placeholder' => __('Search for a product&hellip;'),
//                'field_name' => 'products',
//                'sele_vals' => array(),
//                'help_text' => __('Export orders for the selected specific products'),
//                'type' => 'multi_select',
//                'css_class' => 'wc-product-search',
//            );
//            $fields['email'] = array(
//                'label' => __('Email'),
//                'placeholder' => __('Search for a Customer&hellip;'),
//                'field_name' => 'email',
//                'sele_vals' => array(),
//                'help_text' => __('Export orders based on email'),
//                'type' => 'multi_select',
//                'css_class' => 'wc-customer-search',
//            );
//            $fields['coupons'] = array(
//                'label' => __('Coupons'),
//                'placeholder' => __('Enter coupon codes separated by ,'),
//                'field_name' => 'coupons',
//                'sele_vals' => '',
//                'help_text' => __('Export orders based on coupons applied'),
//                'type' => 'text',
//                'css_class' => '',
//            );

//            $fields['amount'] = array(
//                'label' => __('Coupon amount'),
//                'placeholder' => __('Amount'),
//                'field_name' => 'amount',
//                'sele_vals' => '',
//                'help_text' => __('Export coupons by their discount amount. Specify the discount range for which the coupon will be levied.'),
//                'css_class' => '',
//                'type' => 'field_html',
//                'field_html'=>'<input style="width:48%; display: inline-block;" type="number" min="0" name="coupon_amount_from" id="c_amount" placeholder="'. __('From amount' ).'" class="input-text" /> -
//                     <input style="width:48%; float:right;" type="number" min="0" name="coupon_amount_to" id="c_amount" placeholder="'. __('To amount') .'" class="input-text" />',
//        
//            );
            
            
            $fields['coupon_amount_from'] = array(
                'label'=>__("Coupon amount: From", 'wt-import-export-for-woo'),
                'placeholder' => __('From amount', 'wt-import-export-for-woo'),
                'type'=>'number',
                'value' =>'',
                'attr' =>array(
                        'min'=>0,
                    ),
                'field_name'=>'coupon_amount_from',
                'help_text'=>__('Export coupons by their discount amount. Specify the minimum discount amount for which the coupon was levied.', 'wt-import-export-for-woo'),
                'validation_rule'=>array('type'=>'floatval'),
            
            );
            
            
            $fields['coupon_amount_to'] = array(
                'label'=>__("Coupon amount: To", 'wt-import-export-for-woo'),
                'placeholder' => __('To amount', 'wt-import-export-for-woo'),
                'type'=>'number',
                'value' =>'',
                'attr' =>array(
                        'min'=>0,
                    ),
                'field_name'=>'coupon_amount_to',
                'help_text'=>__('Export coupons by their discount amount. Specify the maximum discount amount for which the coupon was levied.', 'wt-import-export-for-woo'),
                'validation_rule'=>array('type'=>'floatval'),
            
            );
            

//            $fields['date'] = array(
//                'label' => __('Coupon expiry date'),
//                'placeholder' => __('Date'),
//                'field_name' => 'date',
//                'sele_vals' => '',
//                'help_text' => __('Date on which the coupon will expire. Export coupons with expiry date within the specified interval.'),
//                //            'type' => 'date',
//                'css_class' => '',
//                'type' => 'field_html',
//                'field_html'=>'<input style="width:48%; display: inline-block;" type="text" name="coupon_exp_date_from" class="wt_iew_datepicker" placeholder="'.__('From date').'" class="input-text" /> -
//                    <input style="width:48%; float:right;" type="text" name="coupon_exp_date_to" class="wt_iew_datepicker" placeholder="'. __('To date').'" class="input-text" />',
//                    );
            
            
            
            
            $fields['coupon_exp_date_from'] = array(
                'label' => __('Coupon Expiry Date: From', 'wt-import-export-for-woo'),
                'placeholder' => __('From date', 'wt-import-export-for-woo'),
                'field_name' => 'coupon_exp_date_from',
                'sele_vals' => '',
                'help_text' => __('Date on which the coupon will expire. Export coupons with expiry date equal to or greater than the specified date.', 'wt-import-export-for-woo'),
                'type' => 'text',
                'css_class' => 'wt_iew_datepicker',                
            );
            
            $fields['coupon_exp_date_to'] = array(
                'label' => __('Coupon Expiry Date: To', 'wt-import-export-for-woo'),
                'placeholder' => __('To date', 'wt-import-export-for-woo'),
                'field_name' => 'coupon_exp_date_to',
                'sele_vals' => '',
                'help_text' => __('Date on which the coupon will expire. Export coupons with expiry date equal to or less than the specified date.', 'wt-import-export-for-woo'),
                'type' => 'text',
                'css_class' => 'wt_iew_datepicker',                
            );

            $fields['sort_columns'] = array(
                'label' => __('Sort columns', 'wt-import-export-for-woo'),
                'placeholder' => __('ID'),
                'field_name' => 'sort_columns',
                'sele_vals' => self::get_coupon_sort_columns(),
                'help_text' => __('Sort the exported data based on the selected columns in order specified. Defaulted to ascending order.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr')
            );

            $fields['order_by'] = array(
                'label' => __('Sort By', 'wt-import-export-for-woo'),
                'placeholder' => __('ASC'),
                'field_name' => 'order_by',
                'sele_vals' => array('ASC' => 'Ascending', 'DESC' => 'Descending'),
                'help_text' => __('Defaulted to Ascending. Applicable to above selected columns in the order specified.', 'wt-import-export-for-woo'),
                'type' => 'select',
            );
        }
        return $fields;
    }
    public function get_item_by_id($id) {
        $post['edit_url']=get_edit_post_link($id);
        $post['title'] = get_the_title($id);
        return $post; 
    }
	public static function get_item_link_by_id($id) {
        $post['edit_url']=get_edit_post_link($id);
        $post['title'] = get_the_title($id);
        return $post; 
    }
}
new Wt_Import_Export_For_Woo_Coupon();
