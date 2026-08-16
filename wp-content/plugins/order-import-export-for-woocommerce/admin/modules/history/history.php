<?php
/**
 * History of import/Export
 *
 * @link           
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}
if(!class_exists('Wt_Import_Export_For_Woo_Basic_History')){
class Wt_Import_Export_For_Woo_Basic_History
{
	public $module_id='';
	public static $module_id_static='';
	public $module_base='history';
	public static $status_arr=array();
	public static $status_label_arr=array();
	public static $action_label_arr=array();
	public static $order_post_types=array();
	public static $product_post_types=array();
	public static $user_post_types=array();
	public $max_records=50;
	
	public function __construct()
	{
		$this->module_id=Wt_Import_Export_For_Woo_Order_Basic::get_module_id($this->module_base);
		self::$module_id_static=$this->module_id;

		self::$status_arr=array(
			'pending'=>0, //running...
			'finished'=>1, //completed
			'failed'=>2, //failed
		);
		
		self::$status_label_arr=array(
			0=>__('Running/Incomplete', 'order-import-export-for-woocommerce'),
			1=>__('Finished', 'order-import-export-for-woocommerce'),
			2=>__('Failed', 'order-import-export-for-woocommerce'),
		);

		self::$action_label_arr=array(
			'export'=>__('Export', 'order-import-export-for-woocommerce'),
			'import'=>__('Import', 'order-import-export-for-woocommerce'),
			'export_image'=>__('Image Export', 'order-import-export-for-woocommerce'),
		);

		self::$order_post_types=array('order', 'coupon', 'subscription');
		self::$product_post_types=array('product', 'product_review', 'product_categories', 'product_tags');
		self::$user_post_types=array('user');

		/* Admin menu for hostory listing */
		add_filter('wt_iew_admin_menu_basic', array($this, 'add_admin_pages'), 10, 1);

		/* advanced plugin settings */
		add_filter('wt_iew_advanced_setting_fields_basic', array($this, 'advanced_setting_fields'), 12);

		/* main ajax hook. The callback function will decide which action is to execute. */
		add_action('wp_ajax_iew_history_ajax_basic', array($this, 'ajax_main'), 11);

		/* Hook to perform actions after advanced settings was updated */
		add_action('wt_iew_after_advanced_setting_update_basic', array($this, 'after_advanced_setting_update'), 11);

		/* Download log file via nonce URL */
		add_action('admin_init', array($this, 'download_file'), 11);

	}

	/**
	* Adding admin menus
	*/
	public function add_admin_pages($menus)
	{
		$menus[$this->module_base]=array(
			'submenu',
			WT_IEW_PLUGIN_ID_BASIC,
			__('History', 'order-import-export-for-woocommerce'),
			__('History', 'order-import-export-for-woocommerce'),
			apply_filters('wt_import_export_allowed_capability', 'import'),
			$this->module_id,
			array($this, 'admin_settings_page')
		);
		$menus[$this->module_base.'_log']=array(
			'submenu',
			WT_IEW_PLUGIN_ID_BASIC,
			__('Import Logs', 'order-import-export-for-woocommerce'),
			__('Import Logs', 'order-import-export-for-woocommerce'),
			apply_filters('wt_import_export_allowed_capability', 'import'),
			$this->module_id.'_log',
			array($this, 'admin_log_page')
		);
		return $menus;
	}

	public function ajax_main()
	{
		if(Wt_Iew_Sh::check_write_access(WT_IEW_PLUGIN_ID_BASIC))
		{
			$allowed_ajax_actions=array('view_log');

			$out=array(
				'status'=>0,
				'msg'=>__('Error', 'order-import-export-for-woocommerce'),
			);
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification done using the Wt_Iew_Sh::check_write_access method above.
			$history_action=isset($_POST['history_action']) ? sanitize_text_field(wp_unslash($_POST['history_action'])) : '';
			$data_type=isset($_POST['data_type']) ? sanitize_text_field(wp_unslash($_POST['data_type'])) : '';
			// phpcs:enable
			if(method_exists($this, $history_action) && in_array($history_action, $allowed_ajax_actions))
			{
				$out=$this->{$history_action}($out);
			}

			if('json' === $data_type)
			{
				echo wp_json_encode($out);
			}
		}
		exit();
	}

	/**
	*	Ajax sub function to display logs
	*
	*/
	private function view_log($out)
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification done using the ajax_main() method.
		$history_id=(isset($_POST['history_id']) ? absint(wp_unslash($_POST['history_id'])) : 0);
		$out['html']='';
	
		if($history_id>0)
		{
			$offset=(isset($_POST['offset']) ? absint(wp_unslash($_POST['offset'])) : 0);
			$out['offset']=$offset;
			$out['history_id']=$history_id;

			$history_item=$this->get_history_entry_by_id($history_id);
			if($history_item) //history item exists
			{
				$action_type=$history_item['template_type'];
				if($action_type=='import' && Wt_Import_Export_For_Woo_Order_Admin_Basic::module_exists($action_type))
				{
					$action_module_obj=Wt_Import_Export_For_Woo_Order_Basic::load_modules($action_type);
					$log_file_name=$action_module_obj->get_log_file_name($history_item['id']);
					$log_file_path=$action_module_obj->get_file_path($log_file_name);
					if(file_exists($log_file_path))
					{						
						include_once WT_O_IEW_PLUGIN_PATH.'admin/classes/class-logreader.php';
						$reader=new Wt_Import_Export_For_Woo_Basic_Logreader();
						$response=$reader->get_data_as_batch($log_file_path, $offset);
						if($response['response'])
						{
							$log_list=$response['data_arr'];
							$out['offset']=$response['offset'];
							$out['status']=1;
							$out['finished']=$response['finished'];

							$is_finished=$response['finished'];
							$new_offset=$response['offset'];

							$show_item_details=false;

							$item_type_module_obj = null;

							// Only try to load module if this plugin handles this item type
							if ( in_array($history_item['item_type'], self::$order_post_types ) ) {
								// Order plugin is always available, no check needed
								$item_type_module_obj=Wt_Import_Export_For_Woo_Order_Basic::load_modules($history_item['item_type']);
							} elseif ( in_array($history_item['item_type'], self::$product_post_types) ) {
								if ( class_exists( 'Wt_Import_Export_For_Woo_Product_Basic' ) ) {
									$item_type_module_obj=Wt_Import_Export_For_Woo_Product_Basic::load_modules($history_item['item_type']);
								} 
							} elseif ( in_array( $history_item['item_type'], self::$user_post_types )) {
								if ( class_exists( 'Wt_Import_Export_For_Woo_User_Basic' ) ) {
									$item_type_module_obj=Wt_Import_Export_For_Woo_User_Basic::load_modules($history_item['item_type']);
								}
							}

							// Compatibility for older versions
							if ( is_null( $item_type_module_obj ) && class_exists( 'Wt_Import_Export_For_Woo_Basic' ) && class_exists( 'Wt_Import_Export_For_Woo_Admin_Basic' ) ) {
								$item_type_module_obj = Wt_Import_Export_For_Woo_Basic::load_modules($history_item['item_type']);
							}

							if ( ! is_null( $item_type_module_obj ) && method_exists( $item_type_module_obj, 'get_item_by_id' ) ) {
								$show_item_details=true;
							}
							ob_start();
							include plugin_dir_path(__FILE__).'views/_log_table.php';
							$out['html']=ob_get_clean();
						}
					}else{
						$out['status']=1;  
						// translators: %s: Import export page link
						$out['html']= sprintf( esc_html__( 'Please check the Generate import log is enabled under <a target = "_blank" href="%s">settings</a>', 'order-import-export-for-woocommerce' ), esc_url(admin_url('admin.php?page=wt_import_export_for_woo_basic')));
					}
				}
			}
		}else /* raw log viewing */
		{
			$log_file_name=(isset($_POST['log_file']) ? sanitize_text_field(wp_unslash($_POST['log_file'])) : '');
			if($log_file_name!="")
			{
				$ext_arr=explode(".", $log_file_name);
				$ext=end($ext_arr);
				if($ext=='log')
				{
					$log_file_path=Wt_Import_Export_For_Woo_Basic_Log::get_file_path(basename($log_file_name));
					if(file_exists($log_file_path))
					{
						include_once WT_O_IEW_PLUGIN_PATH.'admin/classes/class-logreader.php';
						$reader=new Wt_Import_Export_For_Woo_Basic_Logreader();
						$response=$reader->get_full_data($log_file_path);

						$out['status']=1;
						$out['html']='<div class="wt_iew_raw_log">'.nl2br(esc_html($response['data_str'])).'</div>';
					}
				}
			}

		}
		// phpcs:enable
		return $out;
	}

	/**
	*	Fields for advanced settings
	*
	*/ 
	public function advanced_setting_fields($fields)
	{
		$fields['advanced_field_head'] =array(
				'type'=>'field_group_head', //field type
				'head'=>__('Advanced options', 'order-import-export-for-woocommerce'),
				'group_id'=>'advanced_field', //field group id
				'show_on_default'=>0,
			);
		$fields['enable_history_auto_delete']=array(
			'label'=>__("Auto delete history", 'order-import-export-for-woocommerce'),
			'type'=>'checkbox',
            'checkbox_fields' => array( 1 => __( 'Enable', 'order-import-export-for-woocommerce' ) ),
            'value' =>1,
			'field_name'=>'enable_history_auto_delete',
			'field_group'=>'advanced_field',
			'help_text'=>__('Enabling this option will delete records from the history section upon reaching the count mentioned in Maximum entries.', 'order-import-export-for-woocommerce'),
			'validation_rule'=>array('type'=>'absint'),
			'form_toggler'=>array(
				'type'=>'parent',
				'target'=>'wt_iew_enable_history_auto_delete',
			),
		);

		$fields['auto_delete_history_count']=array(
			'label'=>__("Maximum entries", 'order-import-export-for-woocommerce'),
			'type'=>'number',
            'value' =>100,
            'attr' =>array(
            	'style'=>'width:30%;',
            ),
			'field_name'=>'auto_delete_history_count',
			'field_group'=>'advanced_field',
			'help_text'=>__('Indicates the maximum records to retain in history. Limit the number of records with status \'Finished\'. E.g On giving an input of 50, the system will retain(not delete) the latest 50 records with status \'Finished\'. Any other record with a different status will not be retained.', 'order-import-export-for-woocommerce'),
			'validation_rule'=>array('type'=>'absint'),
			'form_toggler'=>array(
				'type'=>'child',
				'id'=>'wt_iew_enable_history_auto_delete',
				'val'=>1,
				'chk' => 'true'
			),
		);

		return $fields;
	}

	public function admin_log_page($args)
	{	
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification done using the Wt_Iew_Sh::check_write_access method.
		/* delete action */
		if(isset($_GET['wt_iew_delete_log'])) {
			if(Wt_Iew_Sh::check_write_access(WT_IEW_PLUGIN_ID_BASIC)) {
				$log_file_arr = isset($_GET['wt_iew_log_file']) ? explode(",", sanitize_text_field(wp_unslash($_GET['wt_iew_log_file']))) : array();
				$log_file_arr = Wt_Iew_Sh::sanitize_item($log_file_arr, 'text_arr');

				if (is_array($log_file_arr)) {
					foreach ($log_file_arr as $log_file_name) {
						// Sanitize filename and validate extension
						$safe_filename = basename(sanitize_file_name($log_file_name));  // Use basename() to remove any path components
						$ext_arr = explode(".", $safe_filename);
						$ext = strtolower(end($ext_arr));
						if ($ext == 'log') {
							$log_file_path = Wt_Import_Export_For_Woo_Basic_Log::get_file_path($log_file_name);
							
							// Validate the final path is within allowed directory
							$real_path = realpath($log_file_path);
							$log_dir = WP_CONTENT_DIR.'/webtoffee_iew_log';
							$real_log_dir = realpath($log_dir);
							
							// Validate the file is within allowed directory and is actually a file
							if($real_path && $real_log_dir && strpos($real_path, $real_log_dir) === 0 && file_exists($real_path) && is_file($real_path)) {
								@unlink($real_path);
							}
						}
					}
				}
			}
		}
		// phpcs:enable
		
		$delete_url_params['wt_iew_delete_log'] = 1;
		$delete_url_params['wt_iew_log_file'] = '_log_file_';
		$delete_url_params['page'] = $this->module_id.'_log';
		$delete_url = wp_nonce_url(admin_url('admin.php?'.http_build_query($delete_url_params)), WT_IEW_PLUGIN_ID_BASIC);

		$download_url = wp_nonce_url(admin_url('admin.php?wt_iew_log_download=1&file=_log_file_'), WT_IEW_PLUGIN_ID_BASIC);

		//enqueue script
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required.
		if(isset($_GET['page']) && $this->module_id.'_log' == sanitize_text_field(wp_unslash($_GET['page'])))
		{
			$this->enqueue_scripts($delete_url);
		}

		include plugin_dir_path(__FILE__).'views/settings.php';
	}

	/** 
	*  History list page
	*/
	public function admin_settings_page($args)
	{
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification not necessary but added for some operations using Wt_Iew_Sh::check_write_access() method.
		/* delete action */
		if(isset($_GET['wt_iew_delete_history'])) 
		{
			if(Wt_Iew_Sh::check_write_access(WT_IEW_PLUGIN_ID_BASIC))
			{
				$history_id_arr=isset($_GET['wt_iew_history_id']) ? explode(",", sanitize_text_field(wp_unslash($_GET['wt_iew_history_id']))) : array();
				$history_id_arr=Wt_Iew_Sh::sanitize_item($history_id_arr, 'absint_arr');
				if(count($history_id_arr)>0)
				{
					self::delete_history_by_id($history_id_arr);
				}			
			}
		}

		/** 
		*	Lising page section 
		*/
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo_Order_Basic::$history_tb;

		$post_type_arr=self::get_disticnt_items('item_type');
		$action_type_arr=self::get_disticnt_items('template_type');
		$status_arr=self::get_disticnt_items('status');

		$importer_post_types=apply_filters('wt_iew_importer_post_types_basic', array());
		$exporter_post_types=apply_filters('wt_iew_exporter_post_types_basic', array());
		$post_type_label_arr=array_merge($importer_post_types, $exporter_post_types);


		/**
		*	Get history entries by Schedule ID
		*/
		$cron_id=(isset($_GET['wt_iew_cron_id']) ? absint(wp_unslash($_GET['wt_iew_cron_id'])) : 0);
		$history_arr=array();
		$list_by_cron=false;
		if($cron_id>0)
		{
			$cron_module_obj = Wt_Import_Export_For_Woo_Order_Basic::load_modules('cron');

			if(!is_null($cron_module_obj))
			{
				$cron_data = $cron_module_obj->get_cron_by_id($cron_id);
				if($cron_data)
				{
					$form_data_raw = wp_unslash($cron_data['history_id_list']);
					$history_id_arr = is_array($form_data_raw) ? 
							array_map(function($item) {
								return is_string($item) ? json_decode($item, true) : $item;
							}, $form_data_raw) : 
							json_decode($form_data_raw, true);

					$history_id_arr = (is_array($history_id_arr) ? $history_id_arr : array());
					$list_by_cron = true;
				}else
				{
					$cron_id = 0; //invalid cron id
				} 
			}else
			{
				$cron_id = 0; //cron module not enabled	
			}
		}


		/**
		*	Filter by form fields
		*/ 
		$filter_by=array(
			'item_type'=>array(
				'label'=>__('Post type', 'order-import-export-for-woocommerce'),
				'values'=>$post_type_arr,
				'val_labels'=>$post_type_label_arr,
				'val_type'=>'%s',
				'selected_val'=>'',
			),
			'template_type'=>array(
				'label'=>__('Action type', 'order-import-export-for-woocommerce'),
				'values'=>$action_type_arr,
				'val_labels'=>self::$action_label_arr,
				'val_type'=>'%s',
				'selected_val'=>'',
			),
			'status'=>array(
				'label'=>__('Status', 'order-import-export-for-woocommerce'),
				'values'=>$status_arr,
				'val_labels'=>self::$status_label_arr,
				'validation_rule'=>array('type'=>'absint'),
				'val_type'=>'%d',
				'selected_val'=>'',
			),
		);

		if($list_by_cron) // no need of these filters in `cron by` listing
		{
			unset($filter_by['item_type']);
			unset($filter_by['template_type']);
		}


		/**
		*	Order by field vals
		*/
		$order_by=array(
			'date_desc'=>array('label'=>__('Date descending', 'order-import-export-for-woocommerce'), 'sql'=>'created_at DESC'),
			'date_asc'=>array('label'=>__('Date ascending', 'order-import-export-for-woocommerce'), 'sql'=>'created_at ASC'),
		);

		/* just applying a text validation */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$conf_arr=isset($_GET['wt_iew_history']) ? wp_unslash($_GET['wt_iew_history']) : array();
		// Recursively sanitize the array
		if (is_array($conf_arr)) {
			$conf_arr = Wt_Iew_Sh::sanitize_item($conf_arr, 'text_arr');
		}
		$url_params_allowed=array(); //this array will only include the allowed $_GET params. This will use in pagination section

		/**
		*	Filter by block
		*/
		$where_qry_val_arr=array(); //sql query WHERE clause val array
		$where_qry_format_arr=array(); //sql query  WHERE clause val format array
		if(isset($conf_arr['filter_by'])) /* filter by GET var exists */
		{		
			$url_params_allowed['filter_by']=array();/* for pagination purpose */

			$filter_by_conf=(is_array($conf_arr['filter_by']) ? $conf_arr['filter_by'] : array());
			$filter_by_validation_rule=Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::extract_validation_rules($filter_by);
			foreach ($filter_by as $filter_key => $filter_val)
			{
				if(isset($filter_by_conf[$filter_key]) && trim($filter_by_conf[$filter_key])!="") //current filter applied
				{
					$where_qry_format_arr[]=$filter_key.'='.$filter_val['val_type'];
					$filter_by[$filter_key]['selected_val']=Wt_Iew_Sh::sanitize_data($filter_by_conf[$filter_key], $filter_key, $filter_by_validation_rule);
					$where_qry_val_arr[]=$filter_by[$filter_key]['selected_val'];

					$url_params_allowed['filter_by'][$filter_key]=$filter_by[$filter_key]['selected_val']; /* for pagination purpose */
				}					
			}
		}

		/**
		*	Order by block
		*/
		$default_order_by=array_keys($order_by)[0];
		$order_by_val=$default_order_by;
		$order_qry_val_arr=array(); //sql query ORDER clause val array
		if(isset($conf_arr['order_by'])) /* order by GET var exists */
		{
			$order_by_val=(is_array($conf_arr['order_by']) ? $default_order_by : $conf_arr['order_by']);
		}
		if(isset($order_by[$order_by_val]))
		{
			$order_qry_val_arr[]=$order_by[$order_by_val]['sql'];
			$url_params_allowed['order_by']=$order_by_val; /* for pagination purpose */
		}

		/**
		*	Pagination block
		*/
		$max_data=(isset($conf_arr['max_data']) ? absint($conf_arr['max_data']) : $this->max_records);
		$this->max_records=($max_data>0 ? $max_data : $this->max_records);
		
		$offset=(isset($_GET['offset']) ? absint(wp_unslash($_GET['offset'])) : 0);
		$url_params_allowed['max_data']=$this->max_records;
		$pagination_url_params=array('wt_iew_history'=>$url_params_allowed, 'page'=>$this->module_id);
		$offset_qry_str=" LIMIT $offset, ".$this->max_records;
		$no_records=false;

		if($list_by_cron) /* list by cron */
		{
			$pagination_url_params['wt_iew_cron_id']=$cron_id; /* adding cron id to URL params */
			
			$total_history_ids=count($history_id_arr);
			if($total_history_ids>0)
			{
				$where_qry_format_arr[]='id IN('.implode(",", array_fill(0, $total_history_ids, "%d")).")";
				$where_qry_val_arr=array_merge($where_qry_val_arr, $history_id_arr);

			}else //reset all where, order by queries
			{
				$where_qry_format_arr=array();
				$where_qry_val_arr=array();
				//$order_qry_val_arr=array();
				$no_records=true;
			}			
		}

		$where_qry=(count($where_qry_format_arr)>0 ? ' WHERE '.implode(" AND ", $where_qry_format_arr) : '');
		$orderby_qry=(count($order_qry_val_arr)>0 ? ' ORDER BY '.implode(", ", $order_qry_val_arr) : '');

		/* prepare SQL */
		$num_sql="SELECT COUNT(id) AS total_records FROM $tb $where_qry";
		$list_sql="SELECT * FROM $tb $where_qry $orderby_qry ".$offset_qry_str;

		if(count($where_qry_format_arr)>0)
		{
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Its a generated query necessary for the plugin.
			$num_sql=$wpdb->prepare($num_sql, $where_qry_val_arr);
			$list_sql=$wpdb->prepare($list_sql, $where_qry_val_arr);
			// phpcs:enable
		}

		if($no_records) /* in list_by cron, history IDs are not available */
		{
			$total_records=0;
			$history_list=array();
		}else
		{
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
			$total_records=$wpdb->get_row($num_sql, ARRAY_A);
			// phpcs:enable
			$total_records=($total_records && isset($total_records['total_records']) ? $total_records['total_records'] : 0);

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Its necessary to use direct database query.
			$history_list=$wpdb->get_results($list_sql, ARRAY_A);
			// phpcs:enable
			$history_list=($history_list ? $history_list : array());
		}

		$delete_url_params=$pagination_url_params;
		$delete_url_params['wt_iew_delete_history']=1;
		$delete_url_params['wt_iew_history_id']='_history_id_';
		$delete_url_params['offset']=$offset;
		$delete_url=wp_nonce_url(admin_url('admin.php?'.http_build_query($delete_url_params)), WT_IEW_PLUGIN_ID_BASIC);

		//enqueue script
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required.
		if(isset($_GET['page']) && $this->module_id == sanitize_text_field(wp_unslash($_GET['page'])))
		{
			$this->enqueue_scripts($delete_url);
		}
		// phpcs:enable

		include plugin_dir_path(__FILE__).'views/settings.php';
	}	

	private function enqueue_scripts($delete_url)
	{
            if(Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::wt_is_screen_allowed()){
		wp_enqueue_script($this->module_id, plugin_dir_url(__FILE__).'assets/js/main.js', array('jquery'), WT_O_IEW_VERSION, false);

		$params=array(
			'delete_url'=>$delete_url,
			'msgs'=>array(
				'sure'=>__('Are you sure?', 'order-import-export-for-woocommerce'),
			)
		);
		wp_localize_script($this->module_id, 'wt_iew_history_basic_params', $params);
            }
	}

	public static function record_failure($history_id, $msg)
	{
		$update_data=array(
			'status'=>self::$status_arr['failed'],
			'status_text'=>$msg //no need to add translation function
		);
		$update_data_type=array(
			'%d',
			'%s',
		);
		self::update_history_entry($history_id, $update_data, $update_data_type);
	}

	/**
	*  Delete history entry from DB and also associated files (Export files only)
	*  @param array|int $id history entry IDs
	*/
	public static function delete_history_by_id($id)
	{
		global $wpdb;
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo_Order_Basic::$history_tb;
		if(is_array($id))
		{
			$where=" IN(".implode(",", array_fill(0, count($id), '%d')).")";
			$where_data=$id;
		}else
		{
			$where="=%d";
			$where_data=array($id);
		}

		//first remove files associated with it. give argument as array then no need to check the result array type
		$allowed_ext_arr=array('csv', 'xml'); //please update this array if new file types introduced
		$list=self::get_history_entry_by_id($where_data);
		if($list)
		{
			foreach($list as $listv)
			{
				if($listv['template_type']=='export') //history is for export action
				{
					$item_type = isset($listv['item_type']) ? $listv['item_type'] : '';
					
					// Determine which export class to use based on item_type
					$export_class = null;
					if (in_array($item_type, self::$order_post_types)) {
						// Use order plugin's export class
						$export_class = 'Wt_Import_Export_For_Woo_Order_Basic_Export';
					} elseif (in_array($item_type, self::$product_post_types)) {
						// Use product plugin's export class
						$export_class = 'Wt_Import_Export_For_Woo_Product_Basic_Export';
					} elseif (in_array($item_type, self::$user_post_types)) {
						// Use user plugin's export class
							$export_class = 'Wt_Import_Export_For_Woo_User_Basic_Export';
					}

					// Compatibility for older versions - fall back to old class if new class doesn't exist
					if ( ! is_null( $export_class ) && ! class_exists( $export_class ) && class_exists( 'Wt_Import_Export_For_Woo_Basic_Export' ) ) {
						$export_class = 'Wt_Import_Export_For_Woo_Basic_Export';
					}
					
					if ( ! is_null( $export_class ) && class_exists( $export_class ) ) {
						$ext_arr=explode('.', $listv['file_name']);
						$ext=end($ext_arr);
						if(in_array($ext, $allowed_ext_arr)) /* delete only allowed extensions */
						{
							$file_path = $export_class::get_file_path($listv['file_name']);
							if($file_path && file_exists($file_path))
							{
								wp_delete_file($file_path);
							}
						}
					}
				}elseif($listv['template_type']=='import')
				{
					$item_type = isset($listv['item_type']) ? $listv['item_type'] : '';
					
					// Determine which import module to use based on item_type
					$action_module_obj = null;
					if (in_array($item_type, self::$order_post_types)) {
						// Order plugin is always available, no check needed
						$action_module_obj = Wt_Import_Export_For_Woo_Order_Basic::load_modules('import');
					} elseif (in_array($item_type, self::$product_post_types)) {
						if (class_exists('Wt_Import_Export_For_Woo_Product_Basic')) {
							$action_module_obj = Wt_Import_Export_For_Woo_Product_Basic::load_modules('import');
						}
					} elseif (in_array($item_type, self::$user_post_types)) {
						if (class_exists('Wt_Import_Export_For_Woo_User_Basic')) {
							$action_module_obj = Wt_Import_Export_For_Woo_User_Basic::load_modules('import');
						}
					}

					// Compatibility for older versions - only try if new classes didn't work and old class exists
					if (is_null($action_module_obj) && class_exists('Wt_Import_Export_For_Woo_Basic') && class_exists('Wt_Import_Export_For_Woo_Admin_Basic')) {
						$action_module_obj = Wt_Import_Export_For_Woo_Basic::load_modules('import');
					}

					if (!is_null($action_module_obj)) {
						$log_file_name=$action_module_obj->get_log_file_name($listv['id']);
						$log_file_path=$action_module_obj->get_file_path($log_file_name);
						if(file_exists($log_file_path))
						{
							@unlink($log_file_path);
						}
						// All plugins use the shared log class
						$log_path = Wt_Import_Export_For_Woo_Basic_Log::$log_dir;
						$wt_log_path = glob($log_path.'/'.$listv['id'].'_*.log');
						if(isset($wt_log_path[0]) && !empty($wt_log_path[0]) && file_exists($wt_log_path[0]))
						{
							@unlink($wt_log_path[0]);
						}
					}
				}
			}	
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Its necessary to use direct database query.
		$wpdb->query( 
		    $wpdb->prepare("DELETE FROM {$tb} WHERE id".$where, $where_data)
		);
		// phpcs:enable
	}

	public static function update_history_entry($history_id, $update_data, $update_data_type)
	{
		global $wpdb; 
		//updating the data
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo_Order_Basic::$history_tb;
		$update_where=array(
			'id'=>$history_id
		);
		$update_where_type=array(
			'%d'
		);
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Its necessary to use direct database query.
		if($wpdb->update($tb, $update_data, $update_where, $update_data_type, $update_where_type)!==false)
		{
			return true;
		}
		// phpcs:enable
		return false;
	}

	/**
	*	Mathod perform actions after advanced settings was updated
	*
	*/
	public function after_advanced_setting_update($advanced_settings)
	{
		/* Check auto deletion enabled */
		if(isset($advanced_settings['wt_iew_enable_history_auto_delete']) && $advanced_settings['wt_iew_enable_history_auto_delete']==1)
		{
			$record_count=(isset($advanced_settings['wt_iew_auto_delete_history_count']) ? absint($advanced_settings['wt_iew_auto_delete_history_count']) : 0);
			if($record_count>0)
			{
				self::auto_delete_history_entry($record_count);
			}
		}
	}

	/**
	*	Check and delete history entry. If auto deletion enabled
	*
	*/
	public static function auto_delete_history_entry($record_count=0)
	{
		if($record_count==0) /* this condition is for, some requests will come from create section or some from advanced settings section */
		{
			if(Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::get_advanced_settings('enable_history_auto_delete')==1)
			{
			 	$record_count=absint(Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::get_advanced_settings('auto_delete_history_count'));	 	
			}
		}
		if($record_count>=1)
	 	{
	 		global $wpdb;
			$tb=$wpdb->prefix.Wt_Import_Export_For_Woo_Order_Basic::$history_tb;
			
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Its necessary to use direct database query.
			$data=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$tb} WHERE status=%d AND id<(SELECT id FROM {$tb} ORDER BY id DESC LIMIT %d,1)", self::$status_arr['finished'], ($record_count-1)), ARRAY_A);
			// phpcs:enable
			
			if($data && is_array($data))
			{
				$id_arr=array_column($data, 'id');
				self::delete_history_by_id($id_arr);
			}
	 	}
	}

	/**
	* 	create a history entry before starting export/import
	*	@param 	$file_name String export/import file name
	*	@param 	$form_data Array export/import formdata
	*	@param 	$action String export or import
	*	@return $history_id Int DB id if success otherwise zero
	*/
	public static function create_history_entry($file_name, $form_data, $to_process, $action)
	{
		global $wpdb;

		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo_Order_Basic::$history_tb;
		$insert_data=array(
			'template_type'=>$action,
			'item_type'=>$to_process, //item type Eg: product
			'file_name'=>$file_name, //export/import file name
			'created_at'=>time(), //craeted time
			'data'=>wp_json_encode($form_data), //formadata
			'status'=>self::$status_arr['pending'], //pending
			'status_text'=>'Pending', //pending, No need to add translate function. we can add this on printing page
			'offset'=>0, //current offset, its always 0 on start
			'total'=>0, //total records, not available now
		);
		$insert_data_type=array(
			'%s','%s','%s','%d','%s','%d','%d','%d'
		);
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Its necessary to use direct database query.
		$insert_response=$wpdb->insert($tb, $insert_data, $insert_data_type);
		// phpcs:enable
		/* check for auto delete */
		self::auto_delete_history_entry();

		if($insert_response) //success
		{
			return $wpdb->insert_id;
		}
		return 0;
	}

	/**
	* 	Get distinct column values from history table
	*	@param string $column table column name
	*	@return array array of distinct column values
	*/
	private static function get_disticnt_items($column)
	{
		global $wpdb;
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo_Order_Basic::$history_tb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Its necessary to use direct database query.
		$data=$wpdb->get_results("SELECT DISTINCT {$column} FROM {$tb} ORDER BY {$column} ASC", ARRAY_A);
		// phpcs:enable
		$data=is_array($data) ? $data : array();
		return array_column($data, $column);
	}

	/**
	* 	Taking history entry by ID
	*/
	public static function get_history_entry_by_id($id)
	{
		global $wpdb;
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo_Order_Basic::$history_tb;
		if(is_array($id))
		{
			$where=" IN(".implode(",", array_fill(0, count($id), '%d')).")";
			$where_data=$id;
		}else
		{
			$where="=%d";
			$where_data=array($id);
		}
		
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Its necessary to use direct database query.
		$qry=$wpdb->prepare("SELECT * FROM {$tb} WHERE id".$where, $where_data);

		if(!is_array($id)) {
			return $wpdb->get_row($qry, ARRAY_A);
		}else {
			return $wpdb->get_results($qry, ARRAY_A);
		} 
		// phpcs:enable
	}


	/**
	*  	Download log file via a nonce URL
	*/
	public function download_file()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce done in the Wt_Iew_Sh::check_write_access method.
		if(isset($_GET['wt_iew_log_download'])) { 

			if(Wt_Iew_Sh::check_write_access(WT_IEW_PLUGIN_ID_BASIC)) { /* check nonce and role */
			
				$file_name = (isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '');
				// phpcs:enable
				if($file_name != "") {

					$file_arr = explode(".", $file_name);
					$file_ext = strtolower(end($file_arr));
					if($file_ext=='log') { /* Only allowed files. */
					
						// Get base log directory path
						$file_path = Wt_Import_Export_For_Woo_Basic_Log::get_file_path($file_name);
						$file_path = realpath($file_path);
						
						// Get the allowed log directory and its real path
						$log_dir = WP_CONTENT_DIR.'/webtoffee_iew_log';
						$real_log_dir = realpath($log_dir);
						
						// Validate the file is within allowed directory
						if($file_path && $real_log_dir && strpos($file_path, $real_log_dir) === 0 && file_exists($file_path)) {
							while ( ob_get_level() > 0 ) {
								@ob_end_clean();
							}
							@ob_start();
							header('Pragma: public');
						    header('Expires: 0');
						    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						    header('Cache-Control: private', false);
						    header('Content-Transfer-Encoding: binary');
						    header('Content-Disposition: attachment; filename="'.$file_name.'";');
						    header('Content-Description: File Transfer');
						    header('Content-Type: application/octet-stream');
						    //header('Content-Length: '.filesize($file_path));

						    $chunk_size = 1024 * 1024;
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
						    $handle=@fopen($file_path, 'rb');
						    while(!feof($handle)) {
								// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
						        $buffer = fread($handle, $chunk_size);
						        echo $buffer; // @codingStandardsIgnoreLine
						        ob_flush();
						        flush();
						    }
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
						    fclose($handle);
						    exit();
						}
					}
				}	
			}
		}
	}


	/**
	* 
	* 	Generate pagination HTML
    */
	public static function gen_pagination_html($total, $limit, $offset, $url, $url_params=array(), $mxnav=6)
	{
		if($total<=0){
 			return '';
 		}
 		/* taking current page */
 		$crpage=($offset+$limit)/$limit;

		$limit=$limit<=0 ? 1 : $limit;
 		$ttpg=ceil($total/$limit);
 		if($ttpg<$crpage){
 			//return '';
 		}

 		//calculations
		$mxnav=$ttpg<$mxnav ? $ttpg : $mxnav;

		$mxnav_mid=floor($mxnav/2);
		$pgstart=$mxnav_mid>=$crpage ? 1 : $crpage-$mxnav_mid; 
		$mxnav_mid+=$mxnav_mid>=$crpage ? ($mxnav_mid-$crpage) : 0;  //adjusting other half with first half balance
		$pgend=$crpage+$mxnav_mid;
		if($pgend>$ttpg)
		{
			$pgend=$ttpg;
		}

		$html='<span class="wt_iew_pagination_total_info">'.$total.__(' record(s)', 'order-import-export-for-woocommerce').'</span>';
		$url_params_string=http_build_query($url_params);
		$url_params_string=$url_params_string!="" ? '&'.$url_params_string : '';
		$url=(strpos($url, '?')!==false ? $url.'&' : $url.'?');
		$href_attr=' href="'.$url.'offset={offset}'.$url_params_string.'"';
		
		$prev_onclick='';
		if($crpage>1)
		{
			$offset=(($crpage-2)*$limit);
			$prev_onclick=str_replace('{offset}', $offset, $href_attr);
		}

		$html.='<a class="'.($crpage>1 ? 'wt_iew_page' : 'wt_iew_pagedisabled').'"'.$prev_onclick.'>‹</a>';
		for($i=$pgstart; $i<=$pgend; $i++)
		{
			$page_offset='';
			$onclick='';
			$offset=($i*$limit)-$limit;
			if($i!=$crpage)
			{
				$onclick=str_replace('{offset}', $offset, $href_attr);
			}			
			$html.='<a class="'.($i==$crpage ? 'wt_iew_pageactive' : 'wt_iew_page').'" '.$onclick.'>'.$i.'</a>';
		}

		$next_onclick='';
		if($crpage<$ttpg)
		{
			$offset=($crpage*$limit);
			$next_onclick=str_replace('{offset}', $offset, $href_attr);
		}

		$html.='<a class="'.($crpage<$ttpg ? 'wt_iew_page' : 'wt_iew_pagedisabled').'"'.$next_onclick.'>›</a>';
		return '<div class="wt_iew_pagination"><span>'.$html.'</div>';
	}
}
}
Wt_Import_Export_For_Woo_Order_Basic::$loaded_modules['history']=new Wt_Import_Export_For_Woo_Basic_History();