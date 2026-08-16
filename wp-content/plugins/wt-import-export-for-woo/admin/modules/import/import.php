<?php
/**
 * Import section of the plugin
 *
 * @link 
 *
 * @package  Wt_Import_Export_For_Woo
 */
if (!defined('ABSPATH')) {
    exit;
}

class Wt_Import_Export_For_Woo_Import
{	
	public $module_id='';
	public static $module_id_static='';
	public $module_base='import';
	
	public static $import_dir=WP_CONTENT_DIR.'/webtoffee_import';
        public static $import_dir_url=WP_CONTENT_URL.'/webtoffee_import';
	public static $import_dir_name='/webtoffee_import';
	public $steps=array();
	public $allowed_import_file_type=array();
	public $max_import_file_size=10;  //in MB

	private $to_import_id='';
	public $to_import='';
	private $rerun_id=0;
	private $cron_edit_id = 0;
	public $import_method='';
	public $import_methods=array();
	public $selected_template=0;
	public $default_batch_count=0; /* configure this value in `advanced_setting_fields` method */
    public $fast_import_mode=0; /* Enable this in general settings for removing do_action while wp_insert_post */
    public $delete_file_after_import=0; /* Enable this while import will delete the file from FTP after import */
	public $selected_template_data=array();
	public $default_import_method=''; /* configure this value in `advanced_setting_fields` method */
	public $form_data=array();
	public $temp_import_file='';
	private $to_process='';
	public $validation_rule = array();
        
        
        private $skip_from_evaluation_array = array();
		private $meta_evaluation_list = array();
        private $decimal_columns = array();
        public $allowed_import_file_type_mime=array();
        public $step_need_validation_filter=array();

        public function __construct()
	{
		$this->module_id=Wt_Import_Export_For_Woo::get_module_id($this->module_base);
		self::$module_id_static=$this->module_id;

		if(wp_max_upload_size()){
			$this->max_import_file_size=wp_max_upload_size()/1000000; //in MB
		}
		
		/* allowed file types */
		$this->allowed_import_file_type=array(
			'csv'=>__('CSV'),
			'xml'=>__('XML'),
            'txt'=>__('TXT'),
			'tsv'=>__('TSV'),
			'xls'=>__('XLS'),
			'xlsx'=>__('XLSX'),
			'json' => __('JSON')
		);
		$this->allowed_import_file_type_mime=array(
			'csv' =>'text/csv',
			'xml' =>'text/xml',
            'txt' =>'text/plain',
			'tsv' =>'text/tsv',
			'xls' =>'application/vnd.ms-excel',
			'xlsx'=>'application/vnd.ms-excel',
			'json' => 'application/json'
		);

		/* default step list */
		$this->steps=array(
			'post_type'=>array(
				'title'=>__('Select a post type', 'wt-import-export-for-woo'),
				'description'=>__('Import the respective post type from a CSV/XML/XLSX. As a first step you need to choose the post type to start the import.', 'wt-import-export-for-woo'),
			),
			'method_import'=>array(
				'title'=>__('Select an import method', 'wt-import-export-for-woo'),
				'description'=>__('Choose from the options below to continue with your import: quick import, based on a pre-saved template or a new import with advanced options.', 'wt-import-export-for-woo'),
			), 
			'mapping'=>array(
				'title'=>__('Map import columns', 'wt-import-export-for-woo'),
				'description'=>__('Map the standard/meta/attributes/taxonomies and hidden meta columns with your CSV/XML/XLSX column names.', 'wt-import-export-for-woo'),
			),
			'advanced'=>array(
				'title'=>__('Advanced options/Batch import/Scheduling', 'wt-import-export-for-woo'),
				'description'=>__('Use advanced options from below to decide on the delimiter options, updates to existing products, batch import count or schedule an import. You can also save the template file for future imports.', 'wt-import-export-for-woo'),
			),
		);

                
                $this->validation_rule=array(
			'post_type'=>array(), /* no validation rule. So default sanitization text */
		);
		$this->step_need_validation_filter=array('method_import', 'mapping', 'advanced');
                
		$this->import_methods=array(
                        'quick'=>array('title'=>__('Quick import', 'wt-import-export-for-woo'), 'description'=> __('Use this option primarily when your input file was exported using the same plugin.', 'wt-import-export-for-woo')),
                        'template'=>array('title'=>__('Pre-saved template', 'wt-import-export-for-woo'), 'description'=> __('Using a pre-saved template retains the previous filter criteria and other column specifications as per the chosen file and imports data accordingly', 'wt-import-export-for-woo')),
                        'new'=>array('title'=>__('Advanced Import', 'wt-import-export-for-woo'), 'description'=> __('This option will take you through the entire process of filtering/column selection/advanced options that may be required for your import. You can also save your selections as a template for future use.', 'wt-import-export-for-woo')),
		);

		/* advanced plugin settings */
		add_filter('wt_iew_advanced_setting_fields', array($this, 'advanced_setting_fields'));
		
		/* setting default values this method must be below of advanced setting filter */
		$this->get_defaults();

		/* main ajax hook. The callback function will decide which is to execute. */
		add_action('wp_ajax_iew_import_ajax', array($this, 'ajax_main'), 11);

		/* Admin menu for import */
		add_filter('wt_iew_admin_menu', array($this, 'add_admin_pages'), 10, 1);
	
	}

	public function get_defaults()
	{	
		$this->default_import_method=Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('default_import_method');
		$this->default_batch_count=Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('default_import_batch');
		$import_speed_mode = Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('enable_speed_mode');
        $this->fast_import_mode = ( $import_speed_mode == 'Yes' || $import_speed_mode == 1 ) ? true : false;
	}

	/**
	*	Fields for advanced settings
	*
	*/
	public function advanced_setting_fields($fields)
	{
            
        $fields['maximum_execution_time'] = array(
			'label' => __("Maximum execution time", 'wt-import-export-for-woo'),
			'type' => 'number',
			'value' => ini_get('max_execution_time'), /* Default max_execution_time settings value */
			'field_name' => 'maximum_execution_time',
			'field_group' => 'advanced_field',
			'help_text' => __('The maximum execution time, in seconds(eg:- 300, 600, 1800, 3600). If set to zero, no time limit is imposed. Increasing this will reduce the chance of export/import timeouts.', 'wt-import-export-for-woo'),
			'validation_rule' => array('type' => 'int'),
		);

		$fields['enable_speed_mode'] = array(
			'label' => __("Block third party plugin hooks while importing", 'wt-import-export-for-woo'),
			'type' => 'checkbox',
			'checkbox_fields' => array( 1 => __( 'Enable', 'wt-import-export-for-woo' ) ),
			'value' => 0,
			'field_name' => 'enable_speed_mode',
			'field_group' => 'advanced_field',
			//'help_text' => __("This option is for advanced users with knowledge of WordPress development. Your theme or plugins may require these calls when posts are created. Next action will be disabled: 'transition_post_status', 'save_post', 'pre_post_update', 'add_attachment', 'edit_attachment', 'edit_post', 'post_updated', 'wp_insert_post'. Verify your created posts work properly if you check this box", 'wt-import-export-for-woo'),
			'help_text' => __("Increase speed by disabling do_action calls in wp_insert_post during import. Verify your imported posts work properly if you enabled this.", 'wt-import-export-for-woo'),
			'validation_rule' => array('type' => 'absint'),
		);

		$fields['enable_import_log']=array(
			'label'=>__("Generate Import log", 'wt-import-export-for-woo'),
			'type'=>'checkbox',
			'checkbox_fields' => array( 1 => __( 'Enable' ) ),
            'value' =>1,
			'field_name'=>'enable_import_log',
			'field_group'=>'advanced_field',
			'help_text'=>__('Generate import log as text file and make it available in the log section for debugging purposes.', 'wt-import-export-for-woo'),
			'validation_rule'=>array('type'=>'absint'),
		);
		$import_methods=array_map(function($vl){ return $vl['title']; }, $this->import_methods);
		$fields['default_import_method']=array(
			'label'=>__("Default Import method", 'wt-import-export-for-woo'),
			'type'=>'select',
			'sele_vals'=>$import_methods,
                        'value' =>'new',
			'field_name'=>'default_import_method',
			'field_group'=>'advanced_field',
			'help_text'=>__('Select the default method of import.', 'wt-import-export-for-woo'),
		);
		$fields['default_import_batch']=array(
			'label'=>__("Default Import batch count", 'wt-import-export-for-woo'),
			'type'=>'number',
            'value' =>10, /* If altering then please also change batch count field help text section */
			'field_name'=>'default_import_batch',
			'help_text'=>__('Provide the default number of records to be imported in a batch.', 'wt-import-export-for-woo'),
			'validation_rule'=>array('type'=>'absint'),
			'attr' => array('min' => 1, 'max' => 50),
		);
                		                
        return $fields;
	}

	/**
	*	Fields for Import advanced step
	*/
	public function get_advanced_screen_fields($advanced_form_data)
	{
		$advanced_screen_fields=array(
			
			'advanced_field_head'=>array(
				'type'=>'field_group_head', //field type
				'head'=>__('Advanced options', 'wt-import-export-for-woo'),
				'group_id'=>'advanced_field', //field group id
				'show_on_default'=>0,
			),
			'batch_count'=>array(
				'label'=>__("Import in batches of", 'wt-import-export-for-woo'),
				'type'=>'text',
				'value'=>$this->default_batch_count,
				'field_name'=>'batch_count',
                                'help_text'=>__('If import fails due to timeout, you can lower this number and try again.', 'wt-import-export-for-woo'),
				'tip_description' => __('The number of records that the server will process for every iteration within the configured timeout interval.', 'wt-import-export-for-woo'),
                                'field_group'=>'advanced_field',
				'validation_rule'=>array('type'=>'absint'),                                
			),
			'enable_speed_mode' => array(
					'label' => __("Block third party plugin hooks while importing", 'wt-import-export-for-woo'),
					'type' => 'checkbox',
					'checkbox_fields' => array( 1 => __( 'Enable', 'wt-import-export-for-woo' ) ),
					'value' =>$this->fast_import_mode,
					'field_name'=>'enable_speed_mode',
					'field_group'=>'advanced_field',
					'help_text' => __("Increase speed by disabling do_action calls in wp_insert_post during import. Verify your imported posts work properly if you enabled this.", 'wt-import-export-for-woo'),
					'validation_rule'=>array('type'=>'absint'),
			)
		);

		/* taking advanced fields from post type modules */
		$advanced_screen_fields=apply_filters('wt_iew_importer_alter_advanced_fields', $advanced_screen_fields, $this->to_import, $advanced_form_data);
		return $advanced_screen_fields;
	}

	/**
	*	Fields for Import method step
	*/
	public function get_method_import_screen_fields($method_import_form_data)
	{
		$file_from_arr=array(
			'local'=>__('Local'),
			'url'=>__('URL'),
                        'existing'=>__('Existing files'),
                    
		);

		/* taking available remote adapters */
		$remote_adapter_names=array();
		$remote_adapter_names=apply_filters('wt_iew_importer_remote_adapter_names', $remote_adapter_names);
		if($remote_adapter_names && is_array($remote_adapter_names))
		{
			foreach($remote_adapter_names as $remote_adapter_key => $remote_adapter_vl)
			{
				$file_from_arr[$remote_adapter_key]=$remote_adapter_vl;
			}
		}

		//prepare file from field type based on remote type adapters
		$file_from_field_arr=array(
			'label'=>__("Import from", 'wt-import-export-for-woo').' [<a href"#" target="_blank" id="sample-csv-file">'. __('Sample CSV', 'wt-import-export-for-woo').'</a>]',
			'type'=>'select',
			'tr_class'=>'wt-iew-import-method-options wt-iew-import-method-options-quick wt-iew-import-method-options-new wt-iew-import-method-options-template',
			'sele_vals'=>$file_from_arr,
			'field_name'=>'file_from',
			'default_value'=>'local',
			'form_toggler'=>array(
				'type'=>'parent',
				'target'=>'wt_iew_file_from'
			)
		);
		if(count($file_from_arr)==1)
		{
			$file_from_field_arr['label']=__("Enable FTP import?", 'wt-import-export-for-woo');
			$file_from_field_arr['type']='radio';
			$file_from_field_arr['radio_fields']=array(
				'local'=>__('No')
			);
		}elseif(count($file_from_arr)==2)
		{
			$end_vl=end($file_from_arr);
			$end_ky=key($file_from_arr);

			$file_from_field_arr['label']=__("Enable ".$end_vl." import?");
			$file_from_field_arr['type']='radio';
			$file_from_field_arr['radio_fields']=array(
				'local'=>__('No'),
				$end_ky=>__('Yes')
			);				
		}

		$method_import_screen_fields=array(
			'file_from'=>$file_from_field_arr,
			'local_file'=>array(
				'label'=>__("Select a file"),
				'type'=>'dropzone',
				'merge_left'=>true,
				'merge_right'=>true,
				'tr_id'=>'local_file_tr',
				'tr_class'=>$file_from_field_arr['tr_class'], //add tr class from parent.Because we need to toggle the tr when parent tr toggles.
				'field_name'=>'local_file',
				'html_id'=>'local_file',
				'form_toggler'=>array(
					'type'=>'child',
					'id'=>'wt_iew_file_from',
					'val'=>'local',
				),
			),
			'url_file'=>array(
				'label'=>__("Enter file URL"),
				'type'=>'text',
				'tr_id'=>'url_file_tr',
				'tr_class'=>$file_from_field_arr['tr_class'], //add tr class from parent.Because we need to toggle the tr when parent tr toggles.
				'field_name'=>'url_file',
				'form_toggler'=>array(
					'type'=>'child',
					'id'=>'wt_iew_file_from',
					'val'=>'url',
				),
                                'validation_rule'=>array('type'=>'url'),
			),
                        'existing_file'=>array(
				'label'=>__("Select a previously uploaded file"),
				'type'=>'select',
				'tr_id'=>'existing_file_tr',
				'tr_class'=>$file_from_field_arr['tr_class'], //add tr class from parent.Because we need to toggle the tr when parent tr toggles.
				'field_name'=>'existing_file',
                                'sele_vals'=>Wt_Iew_IE_Helper::wt_get_existing_files(self::$import_dir,self::$import_dir_url ),
				'form_toggler'=>array(
					'type'=>'child',
					'id'=>'wt_iew_file_from',
					'val'=>'existing',
				),
                                'help_text'=>sprintf(__('Upload files to %s and they will appear in this list.', 'wt-import-export-for-woo'), self::$import_dir.'/'),
			)
		);

		/* taking import_method fields from other modules */
		$method_import_screen_fields=apply_filters('wt_iew_importer_alter_method_import_fields', $method_import_screen_fields, $this->to_import, $method_import_form_data);


		$method_import_screen_fields['delimiter']=array(
			'label'=>__("Delimiter", 'wt-import-export-for-woo'),
			'type'=>'select',
			'value'=>",",
			'css_class'=>"wt_iew_delimiter_preset",
			'tr_id'=>'delimiter_tr',
			'tr_class'=>$file_from_field_arr['tr_class'], //add tr class from parent.Because we need to toggle the tr when parent tr toggles.
			'field_name'=>'delimiter_preset',
			'sele_vals'=>Wt_Iew_IE_Helper::_get_csv_delimiters(),
			'help_text'=>__('The character used to separate columns in the CSV file. Takes comma (,) by default.', 'wt-import-export-for-woo'),
			'validation_rule'=>array('type'=>'skip'),
			'after_form_field'=>'<input type="text" class="wt_iew_custom_delimiter" name="wt_iew_delimiter" value="'.(!empty($method_import_form_data['wt_iew_delimiter']) ? $method_import_form_data['wt_iew_delimiter'] : ",").'" />',
		);

		$method_import_screen_fields['date_format']=array(
			'label'=>__("Date format", 'wt-import-export-for-woo'),
			'type'=>'select',
			'value'=>"Y-m-d",
			'css_class'=>"wt_iew_date_format_preset",
			'tr_class'=>$file_from_field_arr['tr_class'], //add tr class from parent.Because we need to toggle the tr when parent tr toggles.
			'field_name'=>'date_format',
			'sele_vals'=>array(				
				'Y-m-d H:i:s'	=>	array('value'=>'Y-m-d H:i:s ('.date('Y-m-d H:i:s').')', 'val'=>'Y-m-d H:i:s'),
				'd-m-Y H:i:s'	=>	array('value'=>'d-m-Y H:i:s ('.date('d-m-Y H:i:s').')', 'val'=>'d-m-Y H:i:s'),
				'd/m/y h:i:s A'	=>	array('value'=>'d/m/y h:i:s A ('.date('d/m/y h:i:s A').')', 'val'=>'d/m/y h:i:s A'),
				'Y/m/d H:i:s'	=>	array('value'=>'Y/m/d H:i:s ('.date('Y/m/d H:i:s').')', 'val'=>'Y/m/d H:i:s'),
				'other'			=>	array('value'=>__('Other', 'wt-import-export-for-woo'), 'val'=>''),
			),
			'help_text'=>sprintf(__('Date format in the input file. Click %s here %s for more info about the date formats.', 'wt-import-export-for-woo'), '<a href="https://www.php.net/manual/en/function.date.php" target="_blank">', '</a>'),
                        'validation_rule'=>array('type'=>'skip'),
			'after_form_field'=>'<input type="text" class="wt_iew_custom_date_format" name="wt_iew_date_format" value="'.(!empty($method_import_form_data['wt_iew_date_format'][1]) ? $method_import_form_data['wt_iew_date_format'][1] : "Y-m-dd").'" />',
		);
		return $method_import_screen_fields;
	}

	/**
	* Adding admin menus
	*/
	public function add_admin_pages($menus)
	{
		$first = array_slice($menus, 0, 3, true);
    	$last=array_slice($menus, 3, (count($menus)-1), true);
     	
		$menu=array(
			$this->module_base=>array(
				'submenu',
				WT_IEW_PLUGIN_ID,
				__('Import', 'wt-import-export-for-woo'),
				__('Import', 'wt-import-export-for-woo'),
				apply_filters('wt_import_export_allowed_capability', 'import'),
				$this->module_id,
				array($this, 'admin_settings_page')
			)
		);

		$menus=array_merge($first, $menu, $last);
		return $menus;
	}

	/**
	* 	Import page
	*/
	public function admin_settings_page()
	{		
		if(isset($_GET['wt_iew_cron_edit_id']) && absint($_GET['wt_iew_cron_edit_id'])>0 ){
			$requested_cron_edit_id=(isset($_GET['wt_iew_cron_edit_id']) ? absint($_GET['wt_iew_cron_edit_id']) : 0);
			$this->_process_edit_cron($requested_cron_edit_id);
			$this->cron_edit_id = $requested_cron_edit_id;
		}
            
                /**
		*	Check it is a rerun call
		*/
               
		$requested_rerun_id=(isset($_GET['wt_iew_rerun']) ? absint($_GET['wt_iew_rerun']) : 0);
		$this->_process_rerun($requested_rerun_id);

		if($this->rerun_id>0) /* this is a rerun request. Then validate the file */
		{
			$response=$this->download_remote_file($this->form_data);
			if($response['response']) /* temp file created. */
			{
				$this->temp_import_file=$response['file_name'];

				/* delete temp files other than the current temp file of same rerun id, if exists */
				$file_path=$this->get_file_path();
   				$temp_files = glob($file_path.'/rerun_'.$this->rerun_id.'_*');
   				if(count($temp_files)>1) /* Other than the current temp file */
   				{
   					foreach($temp_files as $key => $temp_file)
   					{
   						if(basename($temp_file)!=$this->temp_import_file)
   						{
   							@unlink($temp_file); //delete it
   						}
   					}
   				} 
   				
			}else /* unable to create temp file, then abort the rerun request */
			{
				$this->rerun_id=0;
				$this->form_data=array();
			}

		}
		$this->enqueue_assets();		
		include plugin_dir_path(__FILE__).'views/main.php';
	}


	/**
	*	Validating and Processing rerun action
	*/
	protected function _process_rerun($rerun_id)
	{
		
		if($this->cron_edit_id > 0){
						/* check the cron module is available */
			$cron_module_obj=Wt_Import_Export_For_Woo::load_modules('cron');
			if(!is_null($cron_module_obj))
			{
				/* check the cron entry is for export and also has form_data */
                $cron_data=$cron_module_obj->get_cron_by_id($rerun_id);

				if($cron_data && $cron_data['action_type']==$this->module_base)
				{
					$form_data=maybe_unserialize($cron_data['data']);
					if($form_data && is_array($form_data))
					{
						$this->to_import=(isset($form_data['post_type_form_data']) && isset($form_data['post_type_form_data']['item_type']) ? $form_data['post_type_form_data']['item_type'] : '');
						if($this->to_import!="")
						{
							$this->import_method=(isset($form_data['method_import_form_data']) && isset($form_data['method_import_form_data']['method_import']) && $form_data['method_import_form_data']['method_import']!="" ?  $form_data['method_import_form_data']['method_import'] : $this->default_import_method);
							$this->rerun_id=$rerun_id;
							$this->form_data=$form_data;
							$this->cron_edit_id = $this->cron_edit_id;
							//process steps based on the export method in the history entry
							$this->get_steps();
							return true;
						}
					}
				}
			}
		}
		if($rerun_id>0 &&  0 === $this->cron_edit_id )
		{
			/* check the history module is available */
			$history_module_obj=Wt_Import_Export_For_Woo::load_modules('history');
			if(!is_null($history_module_obj))
			{
                          
				/* check the history entry is for import and also has form_data */
				$history_data=$history_module_obj->get_history_entry_by_id($rerun_id);
				if($history_data && $history_data['template_type']==$this->module_base)
				{
					$form_data=maybe_unserialize($history_data['data']);
					if($form_data && is_array($form_data))
					{
						$this->to_import=(isset($form_data['post_type_form_data']) && isset($form_data['post_type_form_data']['item_type']) ? $form_data['post_type_form_data']['item_type'] : '');
						if($this->to_import!="")
						{
							$this->import_method=(isset($form_data['method_import_form_data']) && isset($form_data['method_import_form_data']['method_import']) && $form_data['method_import_form_data']['method_import']!="" ?  $form_data['method_import_form_data']['method_import'] : $this->default_import_method);
							$this->rerun_id=$rerun_id;
							$this->form_data=$form_data;
							//process steps based on the import method in the history entry
							$this->get_steps();

							return true;
						}
					}
				}
			}
		}
		return false;
	}
        
        protected function _process_edit_cron($rerun_id)
	{
		if($rerun_id>0)
		{
			/* check the cron module is available */
			$cron_module_obj=Wt_Import_Export_For_Woo::load_modules('cron');
			if(!is_null($cron_module_obj))
			{
				/* check the cron entry is for export and also has form_data */
                                $cron_data=$cron_module_obj->get_cron_by_id($rerun_id);

				if($cron_data && $cron_data['action_type']==$this->module_base)
				{
					$form_data=maybe_unserialize($cron_data['data']);
					if($form_data && is_array($form_data))
					{
						$this->to_import=(isset($form_data['post_type_form_data']) && isset($form_data['post_type_form_data']['item_type']) ? $form_data['post_type_form_data']['item_type'] : '');
						if($this->to_import!="")
						{
							$this->import_method=(isset($form_data['method_import_form_data']) && isset($form_data['method_import_form_data']['method_import']) && $form_data['method_import_form_data']['method_import']!="" ?  $form_data['method_import_form_data']['method_import'] : $this->default_import_method);
							$this->rerun_id=$rerun_id;
							$this->form_data=$form_data;
							//process steps based on the export method in the history entry
							$this->get_steps();
							return true;
						}
					}
				}
			}
		}
		return false;
	}

	protected function enqueue_assets()
	{
            
            if(Wt_Import_Export_For_Woo_Common_Helper::wt_is_screen_allowed()){
		/* adding dropzone JS */
		wp_enqueue_script(WT_IEW_PLUGIN_ID.'-dropzone', WT_IEW_PLUGIN_URL.'admin/js/dropzone.min.js', array('jquery'), WT_IEW_VERSION);

		wp_enqueue_script($this->module_id, plugin_dir_url(__FILE__).'assets/js/main.js', array('jquery', 'jquery-ui-sortable', 'jquery-ui-datepicker'), WT_IEW_VERSION);
		wp_enqueue_style('jquery-ui-datepicker');
		//wp_enqueue_media();

		wp_enqueue_style(WT_IEW_PLUGIN_ID.'-jquery-ui', WT_IEW_PLUGIN_URL.'admin/css/jquery-ui.css', array(), WT_IEW_VERSION, 'all');
                
                /* check the history module is available */
                $history_module_obj=Wt_Import_Export_For_Woo::load_modules('history');
                if(!is_null($history_module_obj))
                {
                    wp_enqueue_script(Wt_Import_Export_For_Woo::get_module_id('history'),WT_IEW_PLUGIN_URL.'admin/modules/history/assets/js/main.js', array('jquery'), WT_IEW_VERSION, false);
                }
		
		$file_extensions=array_keys($this->allowed_import_file_type_mime);
		$file_extensions=array_map(function($vl){
			return '.'.$vl;
		}, $file_extensions);
		if(is_plugin_active('woocommerce/woocommerce.php')){
			if(WC()->version < '6.7.0'){
				$review_url = admin_url('edit-comments.php');
			} else {
				$review_url= admin_url('edit.php?post_type=product&page=product-reviews');
			}
		}else{
			$review_url = admin_url('edit-comments.php');
		}

		$params=array(
			'item_type'=>'',
			'steps'=>$this->steps,
			'rerun_id'=>$this->rerun_id,
			'cron_edit_id' => $this->cron_edit_id,
			'to_import'=>$this->to_import,
			'import_method'=>$this->import_method,
			'temp_import_file'=>$this->temp_import_file,
			'allowed_import_file_type_mime'=>$file_extensions,
			'max_import_file_size'=>$this->max_import_file_size,
            'wt_iew_prefix'=>Wt_Import_Export_For_Woo_Admin::$wt_iew_prefix,
			'msgs'=>array(
				'choosed_template'=>__('Choosed template: ', 'wt-import-export-for-woo'),
				'choose_import_method'=>__('Please select an import method.', 'wt-import-export-for-woo'),
				'choose_template'=>__('Please select an import template.', 'wt-import-export-for-woo'),
				'step'=>__('Step', 'wt-import-export-for-woo'),
				'choose_ftp_profile'=>__('Please select an FTP profile.', 'wt-import-export-for-woo'),
				'choose_import_from'=>__('Please choose import from.', 'wt-import-export-for-woo'),
				'select_post_type'=>__('Please select a post type.', 'wt-import-export-for-woo'),
				'choose_a_file'=>__('Please choose an import file.', 'wt-import-export-for-woo'),
				'select_an_import_template'=>__('Please select an import template.', 'wt-import-export-for-woo'),
				'validating_file'=>__('Creating temp file and validating.', 'wt-import-export-for-woo'),
				'processing_file'=>__('Processing input file...', 'wt-import-export-for-woo'), 
				'column_not_in_the_list'=>__('This column is not present in the import list. Please tick the checkbox to include.', 'wt-import-export-for-woo'),
				'uploading'=>__('Uploading...', 'wt-import-export-for-woo'),
				'outdated'=>__('You are using an outdated browser. Please upgarde your browser.', 'wt-import-export-for-woo'),
				'server_error'=>__('An error occured.', 'wt-import-export-for-woo'),
				'invalid_file'=>sprintf(__('Invalid file type. Only %s are allowed', 'wt-import-export-for-woo'), implode(", ", array_values($this->allowed_import_file_type))),
				'drop_upload'=>__('Drag and Drop or click to upload', 'wt-import-export-for-woo'),
				'upload_done'=>sprintf(__('%s Done.', 'wt-import-export-for-woo'), '<span class="dashicons dashicons-yes-alt" style="color:#3fa847;"></span>'),
				'remove'=>__('Remove', 'wt-import-export-for-woo'),
				'deleted_success'=>__('Deleted successfully', 'wt-import-export-for-woo'),
				'import_cancel_warn'=>__('Are you sure to stop the import?', 'wt-import-export-for-woo'),
				'import_canceled'=>__('Import canceled', 'wt-import-export-for-woo'),
			),
			'addons' => array(
				'product' => array(
					'text' => __('View Products', 'wt-import-export-for-woo'),
					'page_link' => admin_url('edit.php?post_type=product')
				),
				'product_categories' => array(
					'text' => __('View Product categories', 'wt-import-export-for-woo'),
					'page_link' => admin_url('edit-tags.php?taxonomy=product_cat&post_type=product')
				),
				'product_tags' => array(
					'text' => __('View Product tags', 'wt-import-export-for-woo'),
					'page_link' => admin_url('edit-tags.php?taxonomy=product_tag&post_type=product')
				),
				'product_review' => array(
					'text' => __('View Product reviews', 'wt-import-export-for-woo'),
					'page_link' => $review_url
				),
				'order' => array(
					'text' => __('View Orders', 'wt-import-export-for-woo'),
					'page_link' => admin_url('edit.php?post_type=shop_order')
				),
				'coupon' => array(					
					'text' => __('View Coupons', 'wt-import-export-for-woo'),
					'page_link' => admin_url('edit.php?post_type=shop_coupon')
				),
				'user' => array(
					'text' => __('View Users', 'wt-import-export-for-woo'),
					'page_link' => admin_url('users.php')
				),
				'subscription' => array(
					'text' => __('View Subscriptions', 'wt-import-export-for-woo'),
					'page_link' => admin_url('edit.php?post_type=shop_subscription')
				)				
			)
                        
		);
		wp_localize_script($this->module_id, 'wt_iew_import_params', $params);

		$this->add_select2_lib(); //adding select2 JS, It checks the availibility of woocommerce
            }
	}

	/**
	* 
	* Enqueue select2 library, if woocommerce available use that
	*/
	protected function add_select2_lib()
	{
		/* enqueue scripts */
		if(!function_exists('is_plugin_active'))
		{
			include_once(ABSPATH.'wp-admin/includes/plugin.php');
		}
		if(is_plugin_active('woocommerce/woocommerce.php'))
		{ 
			wp_enqueue_script('wc-enhanced-select');
			wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url().'/assets/css/admin.css');
		}else
		{
			wp_enqueue_style(WT_IEW_PLUGIN_ID.'-select2', WT_IEW_PLUGIN_URL. 'admin/css/select2.css', array(), WT_IEW_VERSION, 'all' );
			wp_enqueue_script(WT_IEW_PLUGIN_ID.'-select2', WT_IEW_PLUGIN_URL.'admin/js/select2.js', array('jquery'), WT_IEW_VERSION, false );
		}
	}

	/**
	* Get steps
	*
	*/
	public function get_steps()
	{
		if($this->import_method=='quick') /* if quick import then remove some steps */
		{
			$out=array(
				'post_type'=>$this->steps['post_type'],
				'method_import'=>$this->steps['method_import'],
				'advanced'=>$this->steps['advanced'],
			);
			$this->steps=$out;
		}
		// Disable error display for the import process - https://stackoverflow.com/a/6491024/1117368
		if (strpos(@ini_get('disable_functions'), 'error_reporting') === false) {
			@ini_set('error_reporting', 0);
		}
		if (strpos(@ini_get('disable_functions'), 'display_errors') === false) {
			@ini_set('display_errors','Off');
		}		
		$this->steps=apply_filters('wt_iew_importer_steps', $this->steps, $this->to_import);
		return $this->steps;
	}

	/**
	* Download and save file into web server
	*
	*/
	public function download_remote_file($form_data)
	{
            
		$out=array(
			'response'=>false,
			'file_name'=>'',
			'msg'=>'',
		);

		$method_import_form_data=(isset($form_data['method_import_form_data']) ? $form_data['method_import_form_data'] : array());
		$file_from=(isset($method_import_form_data['wt_iew_file_from']) ? Wt_Iew_Sh_Pro::sanitize_item($method_import_form_data['wt_iew_file_from']) : '');
                
		if($file_from=="")
		{
			return $out;
		}

                if($file_from=='local')
		{

                        $file_url=(isset($method_import_form_data['wt_iew_local_file']) ? Wt_Iew_Sh_Pro::sanitize_item($method_import_form_data['wt_iew_local_file'], 'url') : '');
                        $local_file_path=Wt_Iew_IE_Helper::_get_local_file_path($file_url);
                        if(!$local_file_path) /* no local file found */
                        {
                                $file_url='';
                        }

			if($file_url!="") /* file URL not empty */
			{
				if($this->is_extension_allowed($file_url)) /* file type is in allowed list */ 
				{
					$ext_arr=explode('.', $file_url);
					$ext=end($ext_arr);
					if('json' == $ext){
						$ext = 'csv';
					}
					$file_name=$this->get_temp_file_name($ext);
					$file_path=$this->get_file_path($file_name);
					if($file_path)
					{
						if (strpos($file_url, '.json') !== false) {
							$file_dat = file_get_contents($local_file_path);
							if(Wt_Iew_IE_Helper::wt_string_is_json($file_dat)){
								$file_data = $this->json_to_csv($file_dat);
								@file_put_contents($local_file_path, $file_data);
							}

						}

						if(@copy($local_file_path, $file_path))
						{
								$out=array(
										'response'=>true,
										'file_name'=>$file_name,
										'msg'=>'',
								);
						}else
						{
								$out['msg']=__('Unable to create temp file.');
						}
												
					}else
					{
						$out['msg']=__('Unable to create temp directory.');
					}				
				}else
				{
					$out['msg']=__('File type not allowed.');
				}
			}else
			{
				$out['msg']=__('File not found.');
			}		
		}elseif($file_from=='existing')
		{

                        $file_url=(isset($method_import_form_data['wt_iew_existing_file']) ? Wt_Iew_Sh_Pro::sanitize_item($method_import_form_data['wt_iew_existing_file'], 'url') : '');
                        $local_file_path=Wt_Iew_IE_Helper::_get_local_file_path($file_url);
                        if(!$local_file_path) /* no local file found */
                        {
                                $file_url='';
                        }

			if($file_url!="") /* file URL not empty */
			{
				if($this->is_extension_allowed($file_url)) /* file type is in allowed list */ 
				{
					$ext_arr=explode('.', $file_url);
					$ext=end($ext_arr);

					$file_name=$this->get_temp_file_name($ext);
					$file_path=$this->get_file_path($file_name);
					if($file_path)
					{

                                                if(@copy($local_file_path, $file_path))
                                                {
                                                        $out=array(
                                                                'response'=>true,
                                                                'file_name'=>$file_name,
                                                                'msg'=>'',
                                                        );
                                                }else
                                                {
                                                        $out['msg']=__('Unable to create temp file.');
                                                }
												
					}else
					{
						$out['msg']=__('Unable to create temp directory.');
					}				
				}else
				{
					$out['msg']=__('File type not allowed.');
				}
			}else
			{
				$out['msg']=__('File not found.');
			}		
		}elseif($file_from=='url'){
                    
                   
                    $file_url=(isset($method_import_form_data['wt_iew_url_file']) ? Wt_Iew_Sh_Pro::sanitize_item($method_import_form_data['wt_iew_url_file'], 'url') : '');
                    
                    if($file_url!="") /* file URL not empty */ //wt_get_mime_content_type
			{
                        
                        $ext_arr=explode('.', $file_url); /* if extension specified */ 

						$ext=end($ext_arr);
                        
                        if($ext && $this->is_extension_allowed($file_url) ){
							if('json' == $ext){
								$ext = 'csv';
							}
                            $file_name=$this->get_temp_file_name($ext);
                            $file_path=$this->get_file_path($file_name);

                            if($file_path)
                            {
                                $file_data=$this->remote_get($file_url);
                                if(!is_wp_error($file_data) && wp_remote_retrieve_response_code($file_data)==200)
                                {

                                        $file_data=wp_remote_retrieve_body($file_data);
										if (strpos($file_url, '.json') !== false) {
											$file_data = $this->json_to_csv($file_data);
										}

                                        if(@file_put_contents($file_path, $file_data))
                                        {
                                                $out=array(
                                                        'response'=>true,
                                                        'file_name'=>$file_name,
                                                        'msg'=>'',
                                                );
                                        }else
                                        {
                                                $out['msg']=__('Unable to create temp file.');
                                        }
                                }else
                                {
                                        $out['msg']=__('Unable to fetch file data.');
                                }

                            }else
                            {
                                    $out['msg']=__('Unable to create temp directory.');
                            }
                            
                        }else{  // if extension not provided in the url eg: Gdrive 
                            
                            $file_path = '';
                            $file_name=$this->get_temp_file_name('txt');
                            $local_file=$this->get_file_path($file_name);   
							// To Do: Check and update all 3 functions below
                            $get_data_from_url = Wt_Iew_IE_Helper::wt_wpie_download_file_from_url($file_url,$local_file);
                            if($get_data_from_url['status']==0){       
                                $out['msg']=$get_data_from_url['error'];
								
                                $get_data_from_url = Wt_Iew_IE_Helper::get_data_from_url_method_2($file_url,$local_file); 
                                if(@$get_data_from_url['status']==0){
                                    $out['msg']=$get_data_from_url['error'];
                                }else{
                                    $file_path = $get_data_from_url['path'];
                                } 
								
                            }else{
                                $file_path = $get_data_from_url['path'];
                            }
                            
                            if($file_path){
                                
                                $content_type = Wt_Iew_IE_Helper::wt_get_mime_content_type($file_path);
                                
                                if(in_array($content_type, array('application/xml','text/xml'))){
                                    $ext = 'xml';
                                    
                                }else{
                                    $ext = 'csv';
                                }
                                                                
                                if(file_exists($file_path)){
                                    $file_name = str_replace('.txt', '.'.$ext, $file_name);
                                    $new_name = str_replace('.txt', '.'.$ext, $file_path);
                                    rename($file_path, $new_name);
                                }
                                                                
                                $out=array(
                                        'response'=>true,
                                        'file_name'=>$file_name,
                                        'msg'=>'',
                                );
                            }
                        }
                    }else
                    {
                            $out['msg']=__('The specified URL is not valid.');
                    }  

                    
                }else
		{
			$out['response']=true;
			$out=apply_filters('wt_iew_validate_file', $out, $file_from, $method_import_form_data);

			if(is_array($out) && isset($out['response']) && $out['response']) /* a form validation hook for remote modules */
			{
				$remote_adapter=Wt_Import_Export_For_Woo::get_remote_adapters('import', $file_from);
				
				if(is_null($remote_adapter)) /* adapter object not found */
				{
					$msg=sprintf('Unable to initailize %s', $file_from);
					$out['msg']=__($msg);
					$out['response']=false;
				}else
				{
					/* download the file */
					$out = $remote_adapter->download($method_import_form_data, $out, $this);
				}
			}
		}
		if($out['response']!==false)
		{
			$file_path=self::get_file_path($out['file_name']);
			/**
			*	Filter to modify the import file before processing.
			*	@param 	string $file_name name of the file
			*	@param 	string $file_path path of the file
			*	@return  string $file_name name of the new altered file 
			*/
			$out['file_name']=apply_filters('wt_iew_alter_import_file', $out['file_name'], $file_path);
		}
                
		return $out;
	}

	
	public function json_to_csv($file_data) {

		$json_data = json_decode($file_data, true);
		$header = false;
		$new_json = '';
		foreach ($json_data as $row) {
			if (empty($header)) {
				$header = array_keys($row);
				$new_json .= implode(",", $header) . "\n";
				$header = array_flip($header);
			}
			$new_json .= implode(",", array_merge($header, $row)) . "\n";
		}
		return apply_filters( 'wt_iew_parsed_json_data', $new_json, $file_data );
	}

	public function remote_get($target_url)
	{
		global $wp_version;

		$def_args = array(
		    'timeout'     => 5,
		    'redirection' => 5,
		    'httpversion' => '1.0',
		    'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
		    'blocking'    => true,
		    'headers'     => array(),
		    'cookies'     => array(),
		    'body'        => null,
		    'compress'    => false,
		    'decompress'  => true,
		    'sslverify'   => false,
		    'stream'      => false,
		    'filename'    => null
		);
                
                $def_args = apply_filters('wt_iew_alter_url_import_remote_get_args', $def_args);                 
                return wp_remote_get($target_url, $def_args);
	}

	public function get_log_file_name($history_id)
	{
		return 'log_'.$history_id.'.log';
	}

	public function get_temp_file_name($ext)
	{
		/* adding rerun prefix is to easily identify rerun temp files */
		$rerun_prefix=($this->rerun_id>0 ? 'rerun_'.$this->rerun_id.'_' : '');
		return $rerun_prefix.'temp_'.$this->to_import.'_'.time().'.'.$ext;
	}

	/**
	* 	Get given file url.
	*	If file name is empty then URL will return
	*/
	public static function get_file_url($file_name="")
	{
		return WP_CONTENT_URL.self::$import_dir_name.'/'.$file_name;
	}

	/**
	*	Checks the file extension is in allowed list
	*	@param string File name/ URL
	*	@return boolean 
	*/
	public function is_extension_allowed($file_url)
	{
		$ext_arr=explode('.', $file_url);
		$ext=strtolower(end($ext_arr));
		if(isset($this->allowed_import_file_type[$ext])) /* file type is in allowed list */ 
		{
			return true;
		}
		return false;
	}

	/**
	*	Delete import file
	*	@param string File path/ URL
	*	@return boolean
	*/
	public function delete_import_file($file_url)
	{
		$file_path_arr=explode("/", $file_url);
		$file_name=end($file_path_arr);
		$file_path=$this->get_file_path($file_name);
		if(file_exists($file_path))
		{	
			if($this->is_extension_allowed($file_url))/* file type is in allowed list */ 
			{
				@unlink($file_path);
				return true;
			}
		}
		return false;
	}

	/**
	* 	Get given temp file path.
	*	If file name is empty then file path will return
	*/
	public static function get_file_path($file_name="")
	{
		if(!is_dir(self::$import_dir))
        {
            if(!mkdir(self::$import_dir, 0700))
            {
            	return false;
            }else
            {
            	$files_to_create=array('.htaccess' => 'deny from all', 'index.php'=>'<?php // Silence is golden');
		        foreach($files_to_create as $file=>$file_content)
		        {
		        	if(!file_exists(self::$import_dir.'/'.$file))
			        {
			            $fh=@fopen(self::$import_dir.'/'.$file, "w");
			            if(is_resource($fh))
			            {
			                fwrite($fh, $file_content);
			                fclose($fh);
			            }
			        }
		        } 
            }
        }
        return self::$import_dir.'/'.$file_name;
	}

	/**
	* Download and create a temp file. And create a history entry
	* @param   string   $step       the action to perform, here 'download'
	*
	* @return array 
	*/
	public function process_download($form_data, $step, $to_process, $import_id=0, $offset=0)
	{
		$out=array(
			'response'=>false,
			'new_offset'=>0,
			'import_id'=>0,
			'history_id'=>0, //same as that of import id
			'total_records'=>0,
			'finished'=>0,
			'msg'=>'',
		);
		$this->to_import=$to_process;

		if($import_id>0)
		{
			//take history data by import_id
			$import_data=Wt_Import_Export_For_Woo_History::get_history_entry_by_id($import_id);
			if(is_null($import_data)) //no record found so it may be an error
			{
				return $out;
			}else
			{
				$file_name=(isset($import_data['file_name']) ? $import_data['file_name'] : '');	
				$file_path=$this->get_file_path($file_name);
				if($file_path && file_exists($file_path))
				{
					$this->temp_import_file=$file_name;
				}else
				{
					$msg='Error occurred while processing the file';
					Wt_Import_Export_For_Woo_History::record_failure($import_id, $msg);
		            $out['msg']=__($msg);
					return $out;
				}
			}
		}else
		{
			if($offset==0)
			{
				if($this->temp_import_file!="") /* its a non schedule import */
				{
					$file_path=$this->get_file_path($this->temp_import_file);
					if($file_path && file_exists($file_path))
					{
						if($this->is_extension_allowed($this->temp_import_file)) /* file type is in allowed list */ 
						{
							$import_id=Wt_Import_Export_For_Woo_History::create_history_entry('', $form_data, $to_process, 'import');
						}else
						{
							return $out;
						}
					}else
					{
						$out['msg']=__('Temp file missing.');
						return $out;
					}
				}else /* in scheduled import need to prepare the temp file */
				{	
					$import_id=Wt_Import_Export_For_Woo_History::create_history_entry('', $form_data, $to_process, 'import');
					$response=$this->download_remote_file($form_data);

					if(!$response['response']) /* not validated successfully */
					{					
						Wt_Import_Export_For_Woo_History::record_failure($import_id, $response['msg']);
			            $out['msg']=$response['msg'];
						return $out;
					}else
					{
						$file_path=$this->get_file_path($response['file_name']);
						$this->temp_import_file=$response['file_name'];
					}
				}				
			}
		}

		/**
		* In XML import we need to convert the file into CSV before processing 
		* It may be a batched processing for larger files
		*/
		$ext_arr=explode('.', $this->temp_import_file);
		if(end($ext_arr)=='xml')
		{
			include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-xmlreader.php';
			$reader=new Wt_Import_Export_For_Woo_Xmlreader();
			$xml_file=$this->get_file_path($this->temp_import_file);
			$csv_file_name=str_replace('.xml', '.csv', $this->temp_import_file);
			$csv_file=$this->get_file_path($csv_file_name);
			$response=$reader->xml_to_csv($xml_file, $csv_file, $offset);

			if($offset==0) /* setting default CSV delimiter */
			{
				$form_data=$this->_set_csv_delimiter($form_data, $import_id);	
			}

			if($response['response'])
			{
				$out['finished']=$response['finished'];
				if($out['finished']==1)
				{
					/**
					* 	Remove the XML file 
					*	And set the CSV file as temp file
					*/
					@unlink($xml_file);
					$this->temp_import_file=$csv_file_name;
					$out=$this->_set_import_file_processing_finished($csv_file, $import_id);
				}else
				{
					/** 
					*	Update the existing XML file name to DB. This is necessary for scheduled imports 
					*/
					if($offset==0)
					{
						$update_data=array(
							'file_name'=>$this->temp_import_file,
						);
						$update_data_type=array(
							'%s',
						);
						Wt_Import_Export_For_Woo_History::update_history_entry($import_id, $update_data, $update_data_type);
					}

					/**
					*	Prepare response for next batch processing
					*/
					$out=array(
						'response'=>true,
						'finished'=>0,
						'new_offset'=>$response['new_offset'],
						'import_id'=>$import_id,
						'history_id'=>$import_id, //same as that of import id
						'total_records'=>0,
						'msg'=>__('Processing input file...'),
					);
				}
			}else
			{
				$msg='Error occurred while processing XML';
				Wt_Import_Export_For_Woo_History::record_failure($import_id, $msg);
	            $out['msg']=__($msg);
				return $out;
			}
		}else
		{
			$row_count = 0;
			if( 'xls' == end($ext_arr) || 'xlsx' == end($ext_arr)){
				include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-excelreader.php';
				$xlreader = new Wt_Import_Export_For_Woo_Excelreader();
				$excel_file=$this->get_file_path($this->temp_import_file);
				$row_count = $xlreader->get_file_row_count($excel_file);
				$row_count = ( $row_count > 0 ) ? ( $row_count-1 ) : $row_count;
			}
			$out=$this->_set_import_file_processing_finished($file_path, $import_id, $row_count);
		}		
		return $out;
	}

	/**
	*	If the file type is not CSV (Eg: XML) Then the delimiter must be ",". 
	*	Because we are converting XML to CSV
	*
	*/
	protected function _set_csv_delimiter($form_data, $import_id)
	{
		$form_data['method_import_form_data']['wt_iew_delimiter']=",";
		
		$update_data=array(
			'data'=>maybe_serialize($form_data), //formadata
		);
		$update_data_type=array(
			'%s',
		);
		Wt_Import_Export_For_Woo_History::update_history_entry($import_id, $update_data, $update_data_type);

		return $form_data;
	}

	protected function _set_import_file_processing_finished($file_path, $import_id, $row_count=0)
	{
		/* update total records, temp file name in history table */
		$total_records= ($row_count > 0) ? $row_count : filesize($file_path); /* in this case we cannot count number of rows */
		$update_data=array(
			'total'=>$total_records,
			'file_name'=>$this->temp_import_file,
		);
		$update_data_type=array(
			'%d',
			'%s',
		);
		Wt_Import_Export_For_Woo_History::update_history_entry($import_id, $update_data, $update_data_type);

		return array(
			'response'=>true,
			'finished'=>3,
			'import_id'=>$import_id,
			'history_id'=>$import_id, //same as that of import id
			'total_records'=>$total_records,
			'temp_import_file'=>$this->temp_import_file,
			'msg'=>sprintf(__('Importing...(%d processed)'), 0),
		);
	}


	/**
	* 	Do the import process
	*/
	public function process_action($form_data, $step, $to_process, $file_name='', $import_id=0, $offset=0)
	{
		$out=array(
			'response'=>false,
			'new_offset'=>0,
			'import_id'=>0,
			'history_id'=>0, //same as that of import id
			'total_records'=>0,
			'offset_count'=>0,
			'finished'=>0,
			'msg'=>'',
			'total_success'=>0,
			'total_failed'=>0,
		);

		$this->to_import=$to_process;
		$this->to_process=$to_process;

		/* prepare formdata, If this was not first batch */
		if($import_id>0)
		{
			//take history data by import_id
			$import_data=Wt_Import_Export_For_Woo_History::get_history_entry_by_id($import_id);
			if(is_null($import_data)) //no record found so it may be an error
			{
				return $out;
			}

			//processing form data
			$form_data=(isset($import_data['data']) ? maybe_unserialize($import_data['data']) : array());

		}
		else // No import id so it may be an error
		{
			return $out;
		}

		/* setting history_id in Log section */
		Wt_Import_Export_For_Woo_Log::$history_id=$import_id;

		$file_name=(isset($import_data['file_name']) ? $import_data['file_name'] : '');	
		$file_path=$this->get_file_path($file_name);
		if($file_path)
		{
			if(!file_exists($file_path))
			{
				$msg='Temp file missing';					
				//no need to add translation function in message
				Wt_Import_Export_For_Woo_History::record_failure($import_id, $msg);
	            $out['msg']=__($msg);
	            return $out;
        	}
		}else
		{
			$msg='Temp file missing';					
			//no need to add translation function in message
			Wt_Import_Export_For_Woo_History::record_failure($import_id, $msg);
            $out['msg']=__($msg);
            return $out;
		} 

		$default_batch_count=absint(apply_filters('wt_iew_importer_alter_default_batch_count', $this->default_batch_count, $to_process, $form_data));
		$default_batch_count=($default_batch_count==0 ? $this->default_batch_count : $default_batch_count);

		$batch_count=$default_batch_count;
		$csv_delimiter=',';	
		$total_records=(isset($import_data['total']) ? $import_data['total'] : 0);
		$file_ext_arr=explode('.', $file_name);
		$file_type= strtolower(end($file_ext_arr));
		$file_type=(isset($this->allowed_import_file_type[$file_type]) ? $file_type : 'csv');

		if(isset($form_data['advanced_form_data']))
		{
			$batch_count=(isset($form_data['advanced_form_data']['wt_iew_batch_count']) ? $form_data['advanced_form_data']['wt_iew_batch_count'] : $batch_count);
		}
		if(isset($form_data['method_import_form_data']) && ($file_type=='csv' || $file_type=== 'txt'))
		{
			$csv_delimiter=(isset($form_data['method_import_form_data']['wt_iew_delimiter']) ? $form_data['method_import_form_data']['wt_iew_delimiter'] : $csv_delimiter);
			$csv_delimiter=($csv_delimiter=="" ? ',' : $csv_delimiter);
		}		
		
		
		if( 'xml' == $file_type )
		{
			include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-xmlreader.php';
			$reader=new Wt_Import_Export_For_Woo_Xmlreader();
		}elseif( 'xls' == $file_type || 'xlsx' == $file_type )
		{
			include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-excelreader.php';
			$reader=new Wt_Import_Export_For_Woo_Excelreader();
		}else
		{
			include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-csvreader.php';
			if( 'tsv' == $file_type ){
				$csv_delimiter = "\t";
			}
			$reader=new Wt_Import_Export_For_Woo_Csvreader($csv_delimiter);
		}
 
		/* important: prepare deafult mapping formdata for quick import */
		$input_data=$reader->get_data_as_batch($file_path, $offset, $batch_count, $this, $form_data);

		$input_data= apply_filters('wt_ier_import_file_data',$input_data);
 
		if(empty($input_data['data_arr'])){			
			$out['msg']=__('CSV is empty');
            return $out;
		}
                
		if(!$input_data || !is_array($input_data))
		{
			$msg='Unable to process the file';					
			//no need to add translation function in message
			Wt_Import_Export_For_Woo_History::record_failure($import_id, $msg);
            $out['msg']=__($msg);
            return $out;
		}

		/* checking action is finshed */
		$is_last_offset=false;
		$new_offset=$input_data['offset']; //increase the offset
		$out['total_percent']= ceil(($new_offset/$total_records)*100);
		if($new_offset>=$total_records) //finished
		{
			$is_last_offset=true;
		}

		/**
		* 	In case of non schedule import. Offset row count. 
		*	The real internal offset is in bytes, This offset is total row processed.
		*/
		$offset_count=(isset($_POST['offset_count']) ? absint($_POST['offset_count']) : 0);

		/* giving full data */
		$form_data=apply_filters('wt_iew_import_full_form_data', $form_data, $to_process, $step, $this->selected_template_data);
		
		/* in scheduled import. The import method will not available so we need to take it from formdata */
		$formdata_import_method=(isset($formdata['method_import_form_data']) && isset($formdata['method_import_form_data']['method_import']) ?  $formdata['method_import_form_data']['method_import'] : 'quick');
		$this->import_method=($this->import_method=="" ? $formdata_import_method : $this->import_method);

                /* no form data to process/Couldnt get any data from csv */
//                if($this->import_method == 'quick'){  
                    if(empty($form_data['mapping_form_data']['mapping_fields'])){
                        $msg='Please verify the data/delimiter in the CSV and try again.';					
			//no need to add translation function in message
			Wt_Import_Export_For_Woo_History::record_failure($import_id, $msg);
                        $out['msg']=__($msg);
                        return $out;
                        
                    }                    
//                }

		/**
		*	Import response format
		*/
		$import_response=array(
			'total_success'=>$batch_count,
			'total_failed'=>0,
			'log_data'=>array(
				array('row'=>$offset_count, 'message'=>'', 'status'=>true, 'post_id'=>''),
			),
		);
                        
                $fast_mode = (isset($form_data['advanced_form_data']['wt_iew_enable_speed_mode']) && ( $form_data['advanced_form_data']['wt_iew_enable_speed_mode'] == 1 || $form_data['advanced_form_data']['wt_iew_enable_speed_mode'] == 'Yes') ) ? true : $this->fast_import_mode;

                if ($fast_mode) {
                    $actions = apply_filters('wt_disable_post_update_actions', array(
                        'save_post_product', // WooCommerce product insert
                        'wp_insert_post',
                        'save_post',
                        'add_attachment',
                        'transition_post_status',
                        'pre_post_update',
                        'edit_attachment',
                        'edit_post',
                        'post_updated',
                    ));
                    foreach ($actions as $action) {
                        remove_all_actions($action);
                    }
                }

                $import_response=apply_filters('wt_iew_importer_do_import', $input_data['data_arr'], $to_process, $step, $form_data, $this->selected_template_data, $this->import_method, $offset_count, $is_last_offset); 		 		
				$out['log_data'] = $import_response['log_data'];

		/**
		*	Writing import log to file
		*/
		if(!empty($import_response) && is_array($import_response))
		{
			$log_writer=new Wt_Import_Export_For_Woo_Logwriter();
			$log_file_name=$this->get_log_file_name($import_id);
			$log_file_path=$this->get_file_path($log_file_name);
			$log_data=(isset($import_response['log_data']) && is_array($import_response['log_data']) ? $import_response['log_data'] : array());
			$log_writer->write_import_log($log_data, $log_file_path);
		}


		/* updating completed offset */
		$update_data=array(
			'offset'=>$offset
		);
		$update_data_type=array(
			'%d'
		);
		Wt_Import_Export_For_Woo_History::update_history_entry($import_id, $update_data, $update_data_type);


		/* updating output parameters */
		$out['total_records']=$total_records;
		$out['import_id']=$import_id;
		$out['history_id']=$import_id;
		$out['response']=true;
		
		/* In case of non schedule import. total success, totla failed count */
		$total_success=(isset($_POST['total_success']) ? absint($_POST['total_success']) : 0);
		$total_failed=(isset($_POST['total_failed']) ? absint($_POST['total_failed']) : 0);

		$out['total_success']=(isset($import_response['total_success']) ? $import_response['total_success'] : 0)+$total_success;
		$out['total_failed']=(isset($import_response['total_failed']) ? $import_response['total_failed'] : 0)+$total_failed;

		/* updating action is finshed */	
		if($is_last_offset) //finished
		{
                        /* filter after import done */
                        apply_filters('wt_iew_importer_done_import',  $to_process, $step, $form_data, $this->selected_template_data, $this->import_method); 		 		
                        
                        $delete_file_after_import = (!empty($form_data['method_import_form_data']['wt_iew_delete_file_after_import']) && 'Yes' === $form_data['method_import_form_data']['wt_iew_delete_file_after_import'] ) ? true : $this->delete_file_after_import;
                        if($delete_file_after_import ){
                            $this->delete_file_from_ftp_after_import($to_process, $step, $form_data, $this->selected_template_data, $this->import_method);
                        }
			/* delete the temp file */
			@unlink($file_path);

			$log_summary_msg=$this->generate_log_summary($out, $is_last_offset);

			$out['finished']=1; //finished
			$log_file_name = '';
			$log_path=Wt_Import_Export_For_Woo_Log::$log_dir;
			$log_files = glob($log_path.'/'.$out['history_id'].'_*'.'.log');  
			if(is_array($log_files) && count($log_files)>0)
			{
				$log_file_name=basename($log_files[0]);				
			}
			$out['log_file'] = $log_file_name;
			$out['msg']=$log_summary_msg;
			
			/* updating finished status */
			$update_data=array(
				'status'=>Wt_Import_Export_For_Woo_History::$status_arr['finished'],
				'status_text'=>'Finished' //translation function not needed
			);
			$update_data_type=array(
				'%d',
				'%s',
			);
			Wt_Import_Export_For_Woo_History::update_history_entry($import_id, $update_data, $update_data_type);
		}else
		{
			$rows_processed=$input_data['rows_processed'];
			$total_processed=$rows_processed+$offset_count;

			$out['offset_count']=$total_processed;
			$out['new_offset']=$new_offset;

			$log_summary_msg=$this->generate_log_summary($out, $is_last_offset);

			$out['msg']=$log_summary_msg;
		}

		return $out;
	}

	protected function generate_log_summary($data, $is_last_offset)
	{
		if($is_last_offset)
		{
			$msg='<span class="wt_iew_info_box_title">'.__('Finished').'</span>';
                        $msg.='<span class="wt_iew_popup_close" style="line-height:10px;width:auto" onclick="wt_iew_import.hide_import_info_box();">X</span>';
		}else
		{
			$msg='<span class="wt_iew_info_box_title">'.sprintf(__('Importing...(%d processed)'), $data['offset_count']).'</span>';
		}
		$msg.='<br />'.__('Total success: ').$data['total_success'].'<br />'.__('Total failed: ').$data['total_failed'];
		if($is_last_offset)
		{
			$msg.='<span class="wt_iew_info_box_finished_text" style="display:block">';
			if(Wt_Import_Export_For_Woo_Admin::module_exists('history'))
			{
                                $msg.='<a class="button button-secondary wt_iew_view_log_btn" style="margin-top:10px;" data-history-id="'. $data['history_id'] .'" onclick="wt_iew_import.hide_import_info_box();">'.__('View Details').'</a></span>';
                        }
		}
		return $msg;
	}

	/**
	* 	Main ajax hook to handle all import related requests
	*/
	public function ajax_main()
	{
		include_once plugin_dir_path(__FILE__).'classes/class-import-ajax.php';
		if(Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID))
		{
			$this->cron_edit_id=(isset($_POST['cron_edit_id']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['cron_edit_id'], 'int') : 0);
			/**
			*	Check it is a rerun call
			*/
			if(!$this->_process_rerun((isset($_POST['rerun_id']) ? absint($_POST['rerun_id']) : 0)))
			{
				$this->import_method=(isset($_POST['import_method']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['import_method'], 'text') : '');
				$this->to_import=(isset($_POST['to_import']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['to_import'], 'text') : '');
				$this->selected_template=(isset($_POST['selected_template']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['selected_template'], 'int') : 0);				
			}
			
			$this->get_steps();

			$ajax_obj=new Wt_Import_Export_For_Woo_Import_Ajax($this, $this->to_import, $this->steps, $this->import_method, $this->selected_template, $this->rerun_id, $this->cron_edit_id);
			
			$import_action=Wt_Iew_Sh_Pro::sanitize_item($_POST['import_action'], 'text');
			$data_type=Wt_Iew_Sh_Pro::sanitize_item($_POST['data_type'], 'text');
			
			$allowed_ajax_actions=array('get_steps', 'validate_file', 'get_meta_mapping_fields', 'save_template', 'save_template_as', 'update_template', 'download', 'import', 'upload_import_file', 'delete_import_file');

			$out=array(
				'status'=>0,
				'msg'=>__('Error'),
			);

			if(method_exists($ajax_obj, $import_action) && in_array($import_action, $allowed_ajax_actions))
			{
				$out=$ajax_obj->{$import_action}($out);
			}

			if($data_type=='json')
			{
				echo json_encode($out);
			}
		}
		exit();
	}

	public function process_column_val($input_file_data_row, $form_data)
	{
		$out=array(
			'mapping_fields'=>array(),
			'meta_mapping_fields'=>array()
		);
                                
                $this->skip_from_evaluation_array = $this->get_skip_from_evaluation();
                $this->decimal_columns = $this->get_decimal_columns();   
				$this->meta_evaluation_list = $this->add_to_evaluation_meta();
		
		/**
		*  	Default columns
		*/
		$mapping_form_data=(isset($form_data['mapping_form_data']) ? $form_data['mapping_form_data'] : array());
		$mapping_selected_fields=(isset($mapping_form_data['mapping_selected_fields']) ? $mapping_form_data['mapping_selected_fields'] : array());		
		$mapping_fields=(isset($mapping_form_data['mapping_fields']) ? $mapping_form_data['mapping_fields'] : array());
			
		/**
		*	Input date format. 
		*	This will be taken as the global date format for all date fields in the input file.
		*	If date format is specified in the evaluation section. Then this value will be overriden.
		*/
		$method_import_form_data=(isset($form_data['method_import_form_data']) ? $form_data['method_import_form_data'] : array());
		$input_date_format=(isset($method_import_form_data['wt_iew_date_format']) ? $method_import_form_data['wt_iew_date_format'] : ''); 

		foreach ($mapping_selected_fields as $key => $value)
		{			
			$out['mapping_fields'][$key]=$this->evaluate_data($key, $value, $input_file_data_row, $mapping_fields, $input_date_format);
		}
		$mapping_form_data=$mapping_fields=$mapping_selected_fields=null;
		unset($mapping_form_data, $mapping_fields, $mapping_selected_fields);

		/**
		*  	Meta columns
		*/
		$meta_step_form_data=(isset($form_data['meta_step_form_data']) ? $form_data['meta_step_form_data'] : array());
		$mapping_selected_fields=(isset($meta_step_form_data['mapping_selected_fields']) ? $meta_step_form_data['mapping_selected_fields'] : array());
		$mapping_fields=(isset($meta_step_form_data['mapping_fields']) ? $meta_step_form_data['mapping_fields'] : array());
		foreach ($mapping_selected_fields as $meta_key => $meta_val_arr)
		{
			$out['meta_mapping_fields'][$meta_key]=array();
			$meta_fields_arr=(isset($mapping_fields[$meta_key]) ? $mapping_fields[$meta_key] : array());
			foreach ($meta_val_arr as $key => $value)
			{
				$out['meta_mapping_fields'][$meta_key][$key]=$this->evaluate_data($key, $value, $input_file_data_row, $meta_fields_arr, $input_date_format);
			}
		}
		$meta_step_form_data=$mapping_fields=$mapping_selected_fields=$input_file_data_row=$form_data=null;
		unset($meta_step_form_data, $mapping_fields, $mapping_selected_fields, $input_file_data_row, $form_data);
		do_action( 'wt_iew_process_column_value', $out );
		return apply_filters( 'wt_iew_get_processed_column_value', $out );
	}        
        protected function evaluate_data($key, $value, $data_row, $mapping_fields, $input_date_format)
	{
		if (preg_match('/{(.*?)}/', $value, $match) == 1) {
                   $maping_key = $match[1] ? $match[1]:'';
        }
		$value = $this->add_input_file_data($key, $value, $data_row, $mapping_fields, $input_date_format,true);  
    	if(isset($maping_key) && (!empty($data_row[$maping_key]) || $data_row[$maping_key] == 0 )){
            $value = $this->do_arithmetic($value);
        }

        if(!Wt_Iew_IE_Helper::wt_string_is_json($value) && !is_serialized($value)){
            $value = $this->add_input_file_data($key, $value, $data_row, $mapping_fields, $input_date_format);
        }
                
		$data_row=null;
		unset($data_row);
		return $value;
	}
	protected function do_arithmetic($str)
	{
		$re = '/\[([0-9()+\-*\/. ]+)\]/m';
		$matches=array();
		$find=array();
		$replace=array();
		if(preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0))
		{

			foreach ($matches as $key => $value) 
			{
				if(is_array($value) && count($value)>1)
				{
					$synatx=$this->validate_syntax($value[1]);
					if($synatx)
					{
						$replace[]=eval('return '.$synatx.';');
					}else
					{
						$replace[]='';
					}
					$find[]=$value[0];
					unset($synatx);
				}
			}
		}
		return str_replace($find, $replace, $str);
	}
	protected function validate_syntax($val)
	{
		$open_bracket=substr_count($val, '(');
		$close_bracket=substr_count($val, ')');
		if($close_bracket!=$open_bracket)
		{
			return false; //invalid
		}

		//remove whitespaces 
		$val=str_replace(' ', '', $val);
		$re_after='/\b[\+|*|\-|\/]([^0-9\+\-\(])/m';
		$re_before='/([^0-9\+\-\)])[\+|*|\-|\/]/m';
		
		$match_after=array();
		$match_before=array();
		if(preg_match_all($re_after, $val, $match_after, PREG_SET_ORDER, 0) || preg_match_all($re_before, $val, $match_before, PREG_SET_ORDER, 0))
		{
			return false; //invalid
		}

		unset($match_after, $match_before, $re_after, $re_before);

		/* process + and - symbols */
		$val=preg_replace(array('/\+{2,}/m', '/\-{2,}/m'), array('+', '- -'), $val);

		return $val;
	}
	protected function add_input_file_data($key, $str, $data_row, $mapping_fields, $input_date_format,$skip_from_evaluation=false)                                         
	{
		@set_time_limit(0);
		$re = '/\{([^}]+)\}/m';
		$matches=array();
		preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
		$find=array();
		$replace=array();
		foreach ($matches as $key => $value)
		{
			if(is_array($value) && count($value)>1)
			{
				$data_key=trim($value[1]);

				/* Check for date formatting */
				$data_key_arr=explode(Wt_Import_Export_For_Woo_Admin::$wt_iew_prefix."@", $data_key);
				$data_format='';
				if(count($data_key_arr)==2) /* date format field given while on import */
				{
					$data_key=$data_key_arr[0]; //first value is the field key
					$data_format=$data_key_arr[1]; //second value will be the format
				}

				/* Pre-defined date field */
				if(isset($mapping_fields[$data_key]) && isset($mapping_fields[$data_key][2]) && $mapping_fields[$data_key][2]=='date') 
				{
					/** 
					*	Always give preference to evaluation section
					*	If not specified in evaluation section. Use default format
					*/
					if($data_format=="") 
					{
						$data_format=$input_date_format;
					}
				}

				$output_val='';
				if(isset($data_row[$data_key]))
				{
                   $output_val=($data_row[$data_key]);   
				}

				/**
				* 	This is a date field 
				*/
				$data_format = ( is_array($data_format) )? $data_format[1] : $data_format;
				if(trim($data_format)!="" && trim($output_val)!="")
				{
					if(version_compare(PHP_VERSION, '5.6.0', '>='))
					{
						$date_obj=DateTime::createFromFormat($data_format, $output_val);
						if($date_obj)
						{
							$output_val=$date_obj->format('Y-m-d H:i:s');
						}
					}else
					{
						$output_val=date("Y-m-d H:i:s", strtotime(trim(str_replace('/', '-', str_replace('-', '', $output_val)))));
					}
				}
                                
                                $is_need_to_replace = false;
                                
                                if($skip_from_evaluation){   /* check whether skip or not */  

                                    if (strpos($value[1], 'line_item_') !== false) {   /* line item content gets trimmed when ';' occurred in serialized data */
                                        $value[1] = 'line_item_';//substr($value[1], 10,10);                                       
                                    }
                                    
                                    if(!in_array($value[1], $this->skip_from_evaluation_array)){      /*  current item dont skip */                                   
                                        $is_need_to_replace = true;
                                    }                                    
                                } else { /* no needed to skip, so add all items to find and replace list */
									$is_need_to_replace = true;
								}

                                if($is_need_to_replace){
                                    if(in_array($value[1],$this->decimal_columns)){ /* check if it is a decimal column , if yes, format it */
                                        $output_val =  wt_format_decimal($output_val);               
                                    }
                                    $replace[]=$output_val;
                                    $find[]=$value[0];                                    
                                }
				unset($data_key);
			}		
		}
		$data_row=null;
		unset($data_row);

		return str_replace($find, $replace, $str);
	}

        /*
         * 
         */
        public function get_skip_from_evaluation(){
            return apply_filters('wt_iew_importer_skip_from_evaluation', array('post_title', 'description','post_content','short_description','post_excerpt','line_item_','shipping_items', 'fee_items', 'customer_note', 'meta:wc_productdata_options', 'order_items', 'shipping_method'));             
        }
		
        /*
         * 
         */
        public function add_to_evaluation_meta(){
            return apply_filters('wt_iew_add_to_evaluation_meta', array());             
        }		
        /*
         * 
         */
        public function get_decimal_columns(){
            return apply_filters('wt_iew_importer_decimal_columns', array('price','regular_price','_regular_price','sale_price','_sale_price'));             
        }
        /*
         * 
         */
        public function format_decimal_columns($value,$key,$decimal_columns){
            if(in_array($key, $decimal_columns)){
                return wt_format_decimal($value);
            }
            return $value;                        
        }   
			
	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import
	 * @return int 60
	 */
	public function bump_request_timeout( $val ) {
		return 60;
	}
        
        /**
         * Delete file from FTP server after the import is completed
         * @param type $to_process
         * @param type $step
         * @param type $form_data
         * @param type $selected_template_data
         * @param type $import_method
         * @return type string $to_process
         */
        public function delete_file_from_ftp_after_import($to_process, $step, $form_data, $selected_template_data, $import_method) {


        $method_import_form_data = (isset($form_data['method_import_form_data']) ? $form_data['method_import_form_data'] : array());
        $file_from = (isset($method_import_form_data['wt_iew_file_from']) ? Wt_Iew_Sh_Pro::sanitize_item($method_import_form_data['wt_iew_file_from']) : '');
        if ($file_from == 'ftp') {
            $remote_adapter = Wt_Import_Export_For_Woo::get_remote_adapters('import', $file_from);

            $ftp_profile = Wt_Import_Export_For_Woo_Ftp::get_ftp_data_by_id($method_import_form_data['wt_iew_ftp_profile']);

            $ftp_user = $ftp_profile['user_name'];
            $ftp_password = $ftp_profile['password'];
            if($ftp_profile['is_sftp']==1){
                include_once WT_IEW_PLUGIN_PATH.'admin/modules/ftp/classes/class-sftp.php';
                $sftp_import =new Wt_Import_Export_For_Woo_Sftp();
                $server_file = $method_import_form_data['wt_iew_import_path'] . '/' . $method_import_form_data['wt_iew_import_file'];
                $multi_csv_import_enabled = false;
                if (strpos(basename($method_import_form_data['wt_iew_import_file']), ".") == false) {
                    $multi_csv_import_enabled = true;
                }
                if ($sftp_import->connect($ftp_profile['server'], $ftp_profile['port'])) {
                    if ($sftp_import->login($ftp_user, $ftp_password))
                    {
                        if ($multi_csv_import_enabled == true) {
                            $file_list = $sftp_import->nlist($server_file, array('xml', 'csv'), false);  
                            foreach ($file_list as $file_path) {
                                $server_file_path = $server_file . '/' . $file_path;
                                $sftp_import->delete_file($server_file_path);
                            }
                        }else{
                            $sftp_import->delete_file($server_file);
                        }
                    }
                }
            }else{

                $ftp_adapter = new Wt_Import_Export_For_Woo_FtpAdapter();
                $ftp_conn = $ftp_adapter->connect_ftp($ftp_profile);

                if (@ftp_login($ftp_conn, $ftp_user, $ftp_password) == false) {
    //                        echo __("Connected to host but could not login. Server UserID or Password may be wrong or Try with / without FTPS .\n");
                }

                $multi_csv_import_enabled = false;
                if ($method_import_form_data['wt_iew_import_path'] != '' && $method_import_form_data['wt_iew_import_file'] == '') {
                    $multi_csv_import_enabled = true;
                }

                if ($multi_csv_import_enabled == true) {
                    $ftp_server_path = $method_import_form_data['wt_iew_import_path'];
                    $server_csv_files = @ftp_nlist($ftp_conn, $ftp_server_path . "/");
					$server_csv_files = apply_filters('wt_iew_delete_folder_import_fetched_files',$server_csv_files,$ftp_conn,$method_import_form_data['wt_iew_import_path']);

                    if ($server_csv_files) {
                        $s_count = $f_count = 0;
                        foreach ($server_csv_files as $key => $server_file1) {

                            if (substr($server_file1, -1) == '.' || !in_array(substr($server_file1, -3), array('xml', 'csv'))) {
                                continue;
                            }

                            if (@ftp_delete($ftp_conn, $server_file1)) {
                                $s_count++;
                            } else {
                                $f_count++;
                            }
                        }

                        if ($s_count > 0) {
                            $success = true;
                        }
                        if ($f_count > 0) {
    //                                echo __("Failed to Delete Specified file in FTP Server File Path.");
                        }
                    }
                } else {
                    $server_file = $method_import_form_data['wt_iew_import_path'] . '/' . $method_import_form_data['wt_iew_import_file'];
                    if (@ftp_delete($ftp_conn, $server_file)) {
                        $success = true;
                    } else {
    //                           echo __("Could not delete $server_file");
                    }
                }
            }
        }

        return $to_process;
    }

}
Wt_Import_Export_For_Woo::$loaded_modules['import']=new Wt_Import_Export_For_Woo_Import();
