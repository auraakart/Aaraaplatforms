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
class Wt_Import_Export_For_Woo_Subscription {

    public $module_id = '';
    public static $module_id_static = '';
    public $module_base = 'subscription';
    public $module_name = 'Subscription Import Export for WooCommerce';
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
        
        add_filter('wt_iew_exporter_alter_advanced_fields', array($this, 'exporter_alter_advanced_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_advanced_fields', array($this, 'importer_alter_advanced_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_meta_mapping_fields', array($this, 'exporter_alter_meta_mapping_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_meta_mapping_fields', array($this, 'importer_alter_meta_mapping_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);

        add_filter('wt_iew_exporter_do_export', array($this, 'exporter_do_export'), 10, 7);
        add_filter('wt_iew_importer_do_import', array($this, 'importer_do_import'), 10, 8);

        add_filter('wt_iew_importer_steps', array($this, 'importer_steps'), 10, 2);
    }

    /**
    *   Altering advanced step description
    */
    public function importer_steps($steps, $base)
    {
        if($this->module_base==$base)
        {
            $steps['advanced']['description']=__('Use options from below to decide updates to existing subscriptions, batch import count or schedule an import. You can also save the template file for future imports.', 'wt-import-export-for-woo');
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
        $import = new Wt_Import_Export_For_Woo_Subscription_Import($this);
        
        $response = $import->prepare_data_to_import($import_data,$form_data, $batch_offset, $is_last_batch);
        
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
        $export = new Wt_Import_Export_For_Woo_Subscription_Export($this);
        
        $data_row = $export->prepare_data_to_export($form_data, $batch_offset);
        
        $header_row = $export->prepare_header();
        
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

        $post_columns = self::get_subscription_post_columns();

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
        if(class_exists('WC_Subscription') || class_exists('HF_Subscription')){
            $arr['subscription'] = __('Subscription');
        }
        return $arr;
    }
    
    public static function get_payment_gateways() {  
        $payment_gateways = array();
        
        foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) { 
           $payment_gateways[$gateway->id] = $gateway->get_title() ;
        } 
        return $payment_gateways;
    }

    public static function get_subscription_statuses() {                
        $subscription_statuses = array(
            'wc-pending' => 'Pending',
            'wc-active' => 'Active', 
            'wc-on-hold' => 'On hold',
            'wc-cancelled' => 'Cancelled',
            'wc-switched' => 'Switched',
            'wc-expired' => 'Expired',
            'wc-pending-cancel' => 'Pending Cancellation',
        );
        $subscription_statuses =  apply_filters('hf_subscription_statuses', $subscription_statuses);  
        return apply_filters('wt_iew_export_subscription_statuses', $subscription_statuses);
    }
    
    public static function get_subscription_sort_columns() {        
        $sort_columns = array('ID', 'post_parent', 'post_title', 'post_date', 'post_modified', 'post_author', 'menu_order', 'comment_count');
        return apply_filters('wt_iew_export_subscription_sort_columns', array_combine($sort_columns, $sort_columns));
    }

    public static function get_subscription_post_columns() {
        return include plugin_dir_path(__FILE__) . 'data/data-subscription-post-columns.php';
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
        $csv_columns = self::get_subscription_post_columns();
        
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
        $csv_columns = self::get_subscription_post_columns();

        foreach ($all_meta_keys as $meta) {

            $temp_meta = $meta;
            if (!$meta || (substr((string) $meta, 0, 1) != '_') || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)) || (substr((string) $temp_meta, 0, 1) == '_' &&  in_array( substr((string) $temp_meta, 1), array_keys($csv_columns)) ))
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

        $all_meta_keys = self::get_all_metakeys();

        $this->all_meta_keys = $all_meta_keys;
        return $this->all_meta_keys;
    }

        
    public static function get_all_metakeys() {
        global $wpdb;
        
        
        
        if(class_exists('HF_Subscription')){
            $post_type = 'hf_shop_subscription';
        } else {
            $post_type = 'shop_subscription';
        }

        if(self::wt_get_order_table_name()){
            $meta_keys = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT pm.meta_key
                                        FROM {$wpdb->prefix}wc_orders_meta AS pm
                                        LEFT JOIN {$wpdb->prefix}wc_orders AS p ON p.id = pm.order_id
                                        WHERE p.type = %s
                                        AND pm.meta_key NOT IN ('_schedule_next_payment','_schedule_start','_schedule_end','_schedule_trial_end','_download_permissions_granted','_subscription_renewal_order_ids_cache','_subscription_resubscribe_order_ids_cache','_subscription_switch_order_ids_cache','_created_via','_customer_user')
                                        ORDER BY pm.meta_key",$post_type)
                                        );
        }else{
            $meta_keys = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT pm.meta_key
                                        FROM {$wpdb->postmeta} AS pm
                                        LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
                                        WHERE p.post_type = %s
                                        AND pm.meta_key NOT IN ('_schedule_next_payment','_schedule_start','_schedule_end','_schedule_trial_end','_download_permissions_granted','_subscription_renewal_order_ids_cache','_subscription_resubscribe_order_ids_cache','_subscription_switch_order_ids_cache','_created_via','_customer_user')
                                        ORDER BY pm.meta_key",$post_type)
                                        );
        }
        
        return $meta_keys;       
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
            $fields = self::get_subscription_post_columns();
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
                'default' => __('Default mode', 'wt-import-export-for-woo'),
                'column' => __('Separate columns', 'wt-import-export-for-woo'),
                'row' => __('Separate rows', 'wt-import-export-for-woo')                
            ),
            'value' => 'default',
            'field_name' => 'export_to_separate',
            //'help_text' => __("Option 'Yes' exports the line items within the order into separate columns in the exported file.", 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('The default option will export each line item details into a single column. This option is mainly used for the order migration purpose.', 'wt-import-export-for-woo'),
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
        unset($fields['export_shortcode_tohtml']);
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
                    'help_text'=> __('The store is updated with the data from the input file only for matching/existing records from the file. If the post ID of the subscription being imported exists already(for any of the other post types like coupon, order, user, pages, media etc) skip the subscription from being inserted into the store.', 'wt-import-export-for-woo'),
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
        
        $out['merge'] = array(
            'label' => __("If the subscription exists in the store", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(                
                '0' => __('Skip', 'wt-import-export-for-woo'),
                '1' => __('Update', 'wt-import-export-for-woo'),
            ),
            'value' => '0',
            'field_name' => 'merge',
            'help_text' => __('Subscriptions are matched by their IDs.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Retains the subscription in the store as is and skips the matching subscription from the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_merge', 'value'=>0)
                    )
                ),
                array(
                    'help_text'=> __('Update subscription as per data from the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_merge', 'value'=>1)
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_found_action'
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
            'help_text' => __('All the items within WooCommerce/WordPress are treated as posts and assigned a unique ID as and when they are created in the store. The post ID uniquely identifies an item irrespective of the post type be it subscription/coupon/product/pages/attachments/revisions etc.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('If the post ID of the subscription being imported exists already(for any of the posts like coupon, order, user, pages, media etc) skip the subscription from being inserted into the store.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_id_conflict', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('Insert the subscription into the store with a new subscription ID(next available post ID) different from the value in the input file.', 'wt-import-export-for-woo'),
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
                       
        $out['link_wt_import_key'] = array(
            'label' => __("Link related orders using _wt_import_key"),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'link_wt_import_key',
            'help_text' => __('Link underlying orders related to the imported subscriptions by the key _wt_import_key.', 'wt-import-export-for-woo').'<a href="https://www.webtoffee.com/steps-to-import-subscription-order-with-parent-order/" target="_blank">'.__('Read more.').'</a>',
        );
        
        $out['link_product_using_sku'] = array(
            'label' => __("Link products using SKU instead of Product ID", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'link_product_using_sku',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Link the products associated with the imported subscriptions by their SKU.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_link_product_using_sku', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> sprintf(__('Link the products associated with the imported subscriptions by their Product ID. In case of a conflict with %sIDs of other existing post types%s the link cannot be established.', 'wt-import-export-for-woo'),'<b>','</b>'),
                    'condition'=>array(
                        array('field'=>'wt_iew_link_product_using_sku', 'value'=>0)
                    )
                )
            ),
        );
        
        $out['delete_existing'] = array(
            'label' => __("Delete non-matching subscriptions from store", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'delete_existing',
            'help_text' => __('Select ‘Yes’ if you need to remove the subscriptions from your store which are not present in the input file. For e.g, if you have a subscription #123 in your store and your import file has subscriptions #234, #345; the subscription #123 is deleted from the store prior to importing #234 and #345.', 'wt-import-export-for-woo'),
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
			$fields['limit']['label']=__('Total number of subscriptions to export', 'wt-import-export-for-woo'); 
			$fields['limit']['help_text']=__('Exports specified number of subscriptions. e.g. Entering 500 with a skip count of 10 will export subscriptions from 11th to 510th position.', 'wt-import-export-for-woo');
			$fields['offset']['label']=__('Skip first <i>n</i> subscriptions', 'wt-import-export-for-woo');
			$fields['offset']['help_text']=__('Skips specified number of subscriptions from the beginning. e.g. Enter 10 to skip first 10 subscriptions from export.', 'wt-import-export-for-woo');

            $fields['statuses'] = array(
                'label' => __('Statuses', 'wt-import-export-for-woo'),
                'placeholder' => __('All Statuses', 'wt-import-export-for-woo'),
                'field_name' => 'statuses',
                'sele_vals' => self::get_subscription_statuses(),
                'help_text' => __('Export subscriptions by their status. You can specify more than one status if required.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr')
            );
            
            $fields['start_date'] = array(
                'label' => __('Order Date: From', 'wt-import-export-for-woo'),
                'placeholder' => __('From date', 'wt-import-export-for-woo'),
                'field_name' => 'start_date',
                'sele_vals' => '',
                'help_text' => __('Date on which the subscription was placed. Export subscriptions within the specified date interval.', 'wt-import-export-for-woo'),
                'type' => 'text',
                'css_class' => 'wt_iew_datepicker',                
            );
            
            $fields['end_date'] = array(
                'label' => __('Order Date: To', 'wt-import-export-for-woo'),
                'placeholder' => __('To date', 'wt-import-export-for-woo'),
                'field_name' => 'end_date',
                'sele_vals' => '',
                'help_text' => __('Date on which the subscription was placed. Export subscriptions within the specified date interval.', 'wt-import-export-for-woo'),
                'type' => 'text',
                'css_class' => 'wt_iew_datepicker',                
            );
            
            
            $fields['next_pay_date'] = array(
                'label' => __('Next Payment Date', 'wt-import-export-for-woo'),
                'placeholder' => __('Date', 'wt-import-export-for-woo'),
                'field_name' => 'next_pay_date',
                'sele_vals' => '',
                'help_text' =>  __('Export Subscription orders based on Next Payment Date.', 'wt-import-export-for-woo'),
                'css_class' => 'wt_iew_datepicker', 
                'type' => 'text',                
            );
            
            $fields['payment_methods'] = array(
                'label' => __('Payment methods', 'wt-import-export-for-woo'),
                'placeholder' => __('All', 'wt-import-export-for-woo'),
                'field_name' => 'payment_methods',
                'sele_vals' => self::get_payment_gateways(),
                'help_text' => __('Export subscriptions orders by their Payment methods. You can specify more than one status if required.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr')
            );
            
            $fields['email'] = array(
                'label' => __('Email', 'wt-import-export-for-woo'),
                'placeholder' => __('Search for a Customer&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'email',
                'sele_vals' => array(),
                'help_text' => __('Export Subscription orders based on email', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-customer-search',
                'validation_rule' => array('type'=>'text_arr')
            );
            
            $fields['products'] = array(
                'label' => __('Product', 'wt-import-export-for-woo'),
                'placeholder' => __('Search for a product&hellip;', 'wt-import-export-for-woo'),
                'field_name' => 'products',
                'sele_vals' => array(),
                'help_text' => __('Export Subscription orders for the selected specific products', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-product-search',
                'validation_rule' => array('type'=>'text_arr')
            );
            
            $fields['coupons'] = array(
                'label' => __('Coupons', 'wt-import-export-for-woo'),
                'placeholder' => __('Enter coupon codes separated by ,', 'wt-import-export-for-woo'),
                'field_name' => 'coupons',
                'sele_vals' => '',
                'help_text' => __('Export Subscription orders based on coupons applied.', 'wt-import-export-for-woo'),
                'type' => 'text',
                'css_class' => '',
            );

            
            

            $fields['sort_columns'] = array(
                'label' => __('Sort Columns', 'wt-import-export-for-woo'),
                'placeholder' => __('ID'),
                'field_name' => 'sort_columns',
                'sele_vals' => self::get_subscription_sort_columns(),
                'help_text' => __('Sort the exported data based on the selected columns in order specified. Defaulted to sort by ID.', 'wt-import-export-for-woo'),
                'type' => 'multi_select',
                'css_class' => 'wc-enhanced-select',
                'validation_rule' => array('type'=>'text_arr')
            );

            $fields['order_by'] = array(
                'label' => __('Sort By', 'wt-import-export-for-woo'),
                'placeholder' => __('ASC'),
                'field_name' => 'order_by',
                'sele_vals' => array('ASC' => 'Ascending', 'DESC' => 'Descending'),
                'value' => 'DESC',
                'help_text' => __('Defaulted to Ascending. Applicable to above selected columns in the order specified.', 'wt-import-export-for-woo'),
                'type' => 'select',
            );
        }
        return $fields;
    }
    
    public function get_item_by_id($id) {
        $post['edit_url']=get_edit_post_link($id);
        $post['title'] = $id;
        return $post; 
    }
    public static function get_item_link_by_id($id) {
        if(self::wt_get_order_table_name()){
            if ( class_exists( 'HF_Subscription' ) ) {
                $post['edit_url'] = admin_url( "admin.php?page=wc-orders--hf_shop_subscription&action=edit&id={$id}" );
            } else {
                $post['edit_url'] = admin_url( "admin.php?page=wc-orders--shop_subscription&action=edit&id={$id}" );
            }
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
		if(get_option('woocommerce_custom_orders_table_enabled') === 'yes'){
            return true;
		}else{
            return false;
        }
	}	

}

new Wt_Import_Export_For_Woo_Subscription();
