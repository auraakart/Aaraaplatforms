<?php
/**
 * Export section of the plugin
 *
 * @link            
 *
 * @package  Wt_Import_Export_For_Woo
 */
if (!defined('ABSPATH')) {
    exit;
}

class Wt_Import_Export_For_Woo_Export
{
	public $module_id='';
	public static $module_id_static='';
	public $module_base='export';

	public static $export_dir=WP_CONTENT_DIR.'/webtoffee_export';
	public static $export_dir_name='/webtoffee_export';
	public $steps=array();
	public $allowed_export_file_type=array();
	
	public $to_export='';
	private $to_export_id='';
	private $rerun_id=0;
	private $cron_edit_id = 0;
	public $export_method='';
	public $export_methods=array();
	public $selected_template=0;
	public $default_batch_count=0; /* configure this value in `advanced_setting_fields` method */
        public $enable_export_code_editor=0; /* Enable code editor for export functions */
	public $selected_template_data=array();
	public $default_export_method='';  /* configure this value in `advanced_setting_fields` method */
	public $form_data=array();
        public $validation_rule = array();
        public $step_need_validation_filter = array();

	public function __construct()
	{
		$this->module_id=Wt_Import_Export_For_Woo::get_module_id($this->module_base);
		self::$module_id_static=$this->module_id;

		/* allowed file types */
		$this->allowed_export_file_type=array(
			'csv'=>__('CSV'),
			'xml'=>__('XML'),
			'tsv'=>__('TSV'),			
			'xls'=>__('XLS'),
			'xlsx'=>__('XLSX')			
		);

		/* default step list */
		$this->steps=array
		(
			'post_type'=>array(
				'title'=>__('Select a post type', 'wt-import-export-for-woo'),
				'description'=>__('Export and download the respective post type into a CSV or XML. This file can also be used to import data related to the specific post type back into your WooCommerce shop. As a first step you need to choose the post type to start the export.', 'wt-import-export-for-woo'),
			),
			'method_export'=>array(
				'title'=>__('Select an export method', 'wt-import-export-for-woo'),
				'description'=>__('Choose from the options below to continue with your export: quick export from DB, based on a pre-saved template or a new export with advanced options.', 'wt-import-export-for-woo'),
			),
			'filter'=>array(
				'title'=>__('Filter data', 'wt-import-export-for-woo'),
				'description'=>__('Filter data that needs to be exported using the below criteria.', 'wt-import-export-for-woo'),
			), 
			'mapping'=>array(
				'title'=>__('Map and reorder export columns', 'wt-import-export-for-woo'),
				'description'=>__('Here, you can edit the default export column names, if needed. You can also drag the columns to reorder them in the output file. If you have chosen a pre-saved template, you can see the preferred names and choices that were last saved.', 'wt-import-export-for-woo'),
			),
			'advanced'=>array(
				'title'=>__('Advanced options/Batch export/Scheduling', 'wt-import-export-for-woo'),
				'description'=>__('In this screen, you can specify export file’s name and format along with other advanced options such as: scheduling, batch export and much more. You can also save the template file for future exports.', 'wt-import-export-for-woo'),
			),
		);


		$this->validation_rule=array(
			'post_type'=>array(), /* no validation rule. So default sanitization text */
			'method_export'=>array(
				'mapping_enabled_fields' => array('type'=>'text_arr') //in case of quick export
			)
		);

		$this->step_need_validation_filter=array('filter', 'advanced');


		$this->export_methods=array(
			'quick'=>array('title'=>__('Quick export', 'wt-import-export-for-woo'), 'description'=> __('Exports data by mapping the default columns automatically and the column headers are not customizable in this option.', 'wt-import-export-for-woo')),
                        'template'=>array('title'=>__('Pre-saved template', 'wt-import-export-for-woo'), 'description'=> __('Exports data according to the pre-saved configuration (filters,selective column,mapping, etc).', 'wt-import-export-for-woo')),
			'new'=>array('title'=>__('Advanced export', 'wt-import-export-for-woo'), 'description'=> __('Exports data after a detailed process of data filtration/column selection/advanced options. You can also save this file for future use.', 'wt-import-export-for-woo')),
		);

		/* advanced plugin settings */
		add_filter('wt_iew_advanced_setting_fields', array($this, 'advanced_setting_fields'), 11);

		/* setting default values, this method must be below of advanced setting filter */
		$this->get_defaults();

		/* main ajax hook. The callback function will decide which is to execute. */
		add_action('wp_ajax_iew_export_ajax', array($this, 'ajax_main'), 11);
                
        add_action('wp_ajax_save_export_functions', array($this, 'wt_save_export_functions'), 10);

		/* Admin menu for export */
		add_filter('wt_iew_admin_menu', array($this, 'add_admin_pages'), 10, 1);

		/* Download export file via nonce URL */
		add_action('admin_init', array($this, 'download_file'), 11);
	}

	public function get_defaults()
	{	
		$this->default_export_method=Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('default_export_method');
		$this->default_batch_count=Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('default_export_batch');
                $this->enable_export_code_editor=Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings('enable_export_code');
	}

	/**
	*	Fields for advanced settings
	*
	*/
	public function advanced_setting_fields($fields)
	{
		$export_methods=array_map(function($vl){ return $vl['title']; }, $this->export_methods);
		$fields['default_export_method']=array(
			'label'=>__("Default Export method", 'wt-import-export-for-woo'),
			'type'=>'select',
			'sele_vals'=>$export_methods,
            'value' =>'new',
			'field_name'=>'default_export_method',
			'field_group'=>'advanced_field',
			'help_text'=>__('Select the default method of export.', 'wt-import-export-for-woo'),
		);
        $fields['enable_export_code'] = array(
            'label' => __("PHP code editor in advanced export", 'wt-import-export-for-woo'),
            'type'=>'checkbox',
			'checkbox_fields' => array( 1 => __( 'Enable', 'wt-import-export-for-woo' ) ),
            'value' => 0,
			'field_name'=>'enable_export_code',
			'field_group'=>'advanced_field',                        
            'help_text' => __("Enable code editor for advnaced PHP custom functions to be applied on the exporting data while exporting.", 'wt-import-export-for-woo'),
			'validation_rule'=>array('type'=>'absint'),
                );
		$fields['default_export_batch']=array(
			'label'=>__("Default Export batch count", 'wt-import-export-for-woo'),
			'type'=>'number',
            'value' =>100,
			'field_name'=>'default_export_batch',
			'help_text'=>__('Provide the default count for the records to be exported in a batch.', 'wt-import-export-for-woo'),
			'validation_rule'=>array('type'=>'absint'),
			'attr' => array('min' => 1, 'max' => 200),			
		);
		
		$fields['enable_chatgpt'] = array(
			'label'=>__( "Enable ChatGPT" ),
			'value'=>0,
			'checkbox_fields' => array( 1 => __( 'Enable' ) ),
			'type'=>'checkbox',
			'field_name'=>'enable_chatgpt',
			'field_group'=>'advanced_field',
			'help_text'=>__( 'Automatically generate product descriptions from product titles using ChatGPT API for products without descriptions in the importing CSV.' ),
            'form_toggler'=>array(
				'type'=>'parent',
				'target'=>'wt_iew_enable_chatgpt',
			),
		);                
		$fields['chatgpt_api_key'] = array(
            'label' => __("ChatGPT API key"),
            'type' => 'text',
            'value' => '',
            'field_name' => 'chatgpt_api_key',
            'field_group' => 'advanced_field',
            'help_text' => sprintf(__('Input the ChatGPT API key to enable the automatic generation of product descriptions. %s Where do I get my API Keys? %s '), '<a href="https://help.openai.com/en/articles/4936850-where-do-i-find-my-secret-api-key" target="_blank">', '</a>'),                        
            'validation_rule' => array('type' => 'text'),
            'form_toggler'=>array(
				'type'=>'child',
				'id'=>'wt_iew_enable_chatgpt',
				'val'=>1,
				'chk'=>'true'
			),
        );
		$select_tone=array("formal"=>"Formal","casual"=>"Casual","flowery"=>"Flowery","convincing"=>"Convincing");
		$fields['chatgpt_tone'] = array(
            'label' => __("ChatGPT Tone"),
            'type' => 'select',
			'sele_vals'=>$select_tone,
            'value' => 'formal',
            'field_name' => 'chatgpt_tone',
            'field_group' => 'advanced_field',
            'help_text' => __('Select the tone for ChatGPT. Choose the tone that best suits your product descriptions.'),
            'validation_rule' => array('type' => 'text'),
            'form_toggler'=>array(
				'type'=>'child',
				'id'=>'wt_iew_enable_chatgpt',
				'val'=>1,
				'chk'=>'true'
			),
        );
		return $fields;
	}

	/**
	* Adding admin menus
	*/
        public function add_admin_pages($menus)
	{		
		$menu_temp=array(
			$this->module_base=>array(
				'menu',
				__('Export', 'wt-import-export-for-woo'),
				__('WebToffee Import Export (Pro)', 'wt-import-export-for-woo'),
				apply_filters('wt_import_export_allowed_capability', 'import'),
				$this->module_id,
				array($this,'admin_settings_page'),
				'dashicons-controls-repeat',
				56
			),
			$this->module_base.'-sub'=>array(
				'submenu',
				$this->module_id,
				__('Export', 'wt-import-export-for-woo'),
				__('Export', 'wt-import-export-for-woo'), 
				apply_filters('wt_import_export_allowed_capability', 'import'),
				$this->module_id,
				array($this, 'admin_settings_page')
			),
		);
		unset($menus['general-settings']);
		$menus=array_merge($menu_temp, $menus);
		return $menus;
	}

	/**
	* 	Export page
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

		$this->enqueue_assets();
		include plugin_dir_path(__FILE__).'views/main.php';
	}

	/**
	* 	Main ajax hook to handle all export related requests
	*/
	public function ajax_main()
	{
		include_once plugin_dir_path(__FILE__).'classes/class-export-ajax.php';
		if(Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID))
		{
			
			$this->cron_edit_id=(isset($_POST['cron_edit_id']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['cron_edit_id'], 'int') : 0);
			/**
			*	Check it is a rerun call
			*/
			if(!$this->_process_rerun((isset($_POST['rerun_id']) ? absint($_POST['rerun_id']) : 0)))
			{	
				$this->export_method=(isset($_POST['export_method']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['export_method'], 'text') : '');
				$this->to_export=(isset($_POST['to_export']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['to_export'], 'text') : '');
				$this->selected_template=(isset($_POST['selected_template']) ? Wt_Iew_Sh_Pro::sanitize_item($_POST['selected_template'], 'int') : 0);
			}		
			
			$this->get_steps();

			$ajax_obj=new Wt_Import_Export_For_Woo_Export_Ajax($this, $this->to_export, $this->steps, $this->export_method, $this->selected_template, $this->rerun_id, $this->cron_edit_id);
			
			$export_action=Wt_Iew_Sh_Pro::sanitize_item($_POST['export_action'], 'text');
			$data_type=Wt_Iew_Sh_Pro::sanitize_item($_POST['data_type'], 'text');
			
			$allowed_ajax_actions=array('get_steps', 'get_meta_mapping_fields', 'save_template', 'save_template_as', 'update_template', 'upload', 'export', 'export_image', 'get_order_billing_values', 'get_order_shipping_values');

			$out=array(
				'status'=>0,
				'msg'=>__('Error'),
			);

			if(method_exists($ajax_obj, $export_action) && in_array($export_action, $allowed_ajax_actions))
			{
				$out=$ajax_obj->{$export_action}($out);
			}

			if($data_type=='json')
			{
				echo json_encode($out);
			}
		}
		exit();
	}

	public function get_filter_screen_fields($filter_form_data)
	{
		$filter_screen_fields = array(
			'limit' => array(
				'label' => __("Limit", 'wt-import-export-for-woo'),
				'value' => '',
				'type' => 'number',
				'attr' => array('step' => 1, 'min' => 0),
				'field_name' => 'limit',
				'placeholder' => 'Unlimited',
				'help_text' => __('The actual number of records you want to export. e.g. A limit of 500 with an offset 10 will export records from 11th to 510th position.', 'wt-import-export-for-woo'),
			//'validation_rule'=>array('type'=>'int'),
			),
			'offset' => array(
				'label' => __("Offset", 'wt-import-export-for-woo'),
				'value' => '',
				'field_name' => 'offset',
				'placeholder' => __('0'),
				'help_text' => __('Specify the number of records that should be skipped from the beginning. e.g. An offset of 10 skips the first 10 records.', 'wt-import-export-for-woo'),
				'type' => 'number',
				'attr' => array('step' => 1, 'min' => 0),
				'validation_rule' => array('type' => 'absint')
			),
		);
		$filter_screen_fields=apply_filters('wt_iew_exporter_alter_filter_fields', $filter_screen_fields, $this->to_export, $filter_form_data);
		return $filter_screen_fields;
	}

	public function get_advanced_screen_fields($advanced_form_data)
	{
		$file_into_arr=array('local'=>__('Local'));

		/* taking available remote adapters */
		$remote_adapter_names=array();
		$remote_adapter_names=apply_filters('wt_iew_exporter_remote_adapter_names', $remote_adapter_names);
		if($remote_adapter_names && is_array($remote_adapter_names))
		{
			foreach($remote_adapter_names as $remote_adapter_key => $remote_adapter_vl)
			{
				$file_into_arr[$remote_adapter_key]=$remote_adapter_vl;
			}
		}

		//prepare file into field type based on remote type adapters
		$file_int_field_arr=array(
			'label'=>__("Download the file into my", 'wt-import-export-for-woo'),
			'type'=>'select',
			'sele_vals'=>$file_into_arr,
			'field_name'=>'file_into',
			'default_value'=>'local',
			'form_toggler'=>array(
				'type'=>'parent',
				'target'=>'wt_iew_file_into'
			)
		);
		if(count($file_into_arr)==1)
		{
			$file_int_field_arr['label']=__("Download the file into", 'wt-import-export-for-woo');
			$file_int_field_arr['type']='radio';
			$file_int_field_arr['radio_fields']=array(
				'local'=>__('Local')
			);
		}elseif(count($file_into_arr)==2)
		{
			$end_vl=end($file_into_arr);
			$end_ky=key($file_into_arr);

			//$file_int_field_arr['label']=__("Enable ".ucfirst($end_vl)." export?");
                        $file_int_field_arr['label']=__("Download the file into");
			$file_int_field_arr['type']='radio';
			$file_int_field_arr['radio_fields']=array(
				'local'=>__('Local', 'wt-import-export-for-woo'),
				$end_ky=>__('FTP/SFTP', 'wt-import-export-for-woo')
			);				
		}
		$delimiter_default = isset($advanced_form_data['wt_iew_delimiter']) ? $advanced_form_data['wt_iew_delimiter'] : ",";

		$this->allowed_export_file_type = apply_filters('wt_ier_allowed_export_file_types', $this->allowed_export_file_type);
                //add `is_advanced` field to group it as advanced tab section
		$advanced_screen_fields=array(
			'file_name'=>array(
				'label'=>__("Export file name"),
				'type'=>'text',
				'field_name'=>'file_name',
				'help_text'=>__('Specify a filename for the exported file. If left blank, the system generates the name automatically.', 'wt-import-export-for-woo'),
				'after_form_field_html'=>'<div class="wt_iew_file_ext_info"></div>',
				'td_class3'=>'wt_iew_file_ext_info_td',
				'validation_rule'=>array('type'=>'file_name'),
			),
			'file_as'=>array(
				'label'=>__("Export file format", 'wt-import-export-for-woo'),
				'type'=>'select',
				'sele_vals'=>$this->allowed_export_file_type,
				'field_name'=>'file_as',
				'form_toggler'=>array(
					'type'=>'parent',
					'target'=>'wt_iew_file_as'
				)
			),
			'delimiter'=>array(
				'label'=>__("Delimiter", 'wt-import-export-for-woo'),
				'type'=>'select',
				'value'=>",",
				'css_class'=>"wt_iew_delimiter_preset",
				'tr_id'=>'delimiter_tr',
				'field_name'=>'delimiter_preset',
				'sele_vals'=>Wt_Iew_IE_Helper::_get_csv_delimiters(),
				'form_toggler'=>array(
					'type'=>'child',
					'id'=>'wt_iew_file_as',
					'val'=>'csv'
				),
				'help_text'=>__('Separator for differentiating the columns in the CSV file. Assumes ‘,’ by default.', 'wt-import-export-for-woo'),
				'validation_rule'=>array('type'=>'skip'),
				'after_form_field'=>'<input type="text" class="wt_iew_custom_delimiter" name="wt_iew_delimiter" value="'.$delimiter_default.'" maxlength="1" />',
			),
			'file_into'=>$file_int_field_arr,
			'advanced_field_head'=>array(
				'type'=>'field_group_head', //field type
				'head'=>__('Advanced options', 'wt-import-export-for-woo'),
				'group_id'=>'advanced_field', //field group id
				'show_on_default'=>0,
			),
			'export_shortcode_tohtml'=>array(
				'label'=>__("Convert shortcodes to HTML", 'wt-import-export-for-woo'),
				'value'=>0,
                                'type'=>'checkbox',
				'checkbox_fields' => array( 1 => __( 'Enable' ) ),
				'field_name'=>'export_shortcode_tohtml',
				'field_group'=>'advanced_field',
				//'help_text'=>__("Option 'Yes' converts the shortcode to HTML within the exported CSV.", 'wt-import-export-for-woo'),
                                'tip_description' => __('Convert shortcodes to HTML within the exported CSV', 'wt-import-export-for-woo'),
			),
			'include_bom'=>array(
				'label'=>__("Include BOM in export file", 'wt-import-export-for-woo'),
				'value'=>0,
				'checkbox_fields' => array( 1 => __( 'Enable' ) ),
				'type'=>'checkbox',
				'field_name'=>'include_bom',
				'field_group'=>'advanced_field',
				//'help_text'=>__("The BOM will help some programs like Microsoft Excel read your export file if it includes non-English characters.", 'wt-import-export-for-woo'),
                                'tip_description' => __('This feature  adds a  special Unicode character called BOM(Byte order mark) whose appearance as a magic number at the start of a text stream can signal several things to a program reading the text. The BOM will help some programs like Microsoft Excel to read your export file if it includes non-English characters.', 'wt-import-export-for-woo'),
                            
			),                    
			'batch_count'=>array(
				'label'=>__("Export in batches of", 'wt-import-export-for-woo'),
				'type'=>'text',
				'value'=>$this->default_batch_count,
				'field_name'=>'batch_count',
				'is_advanced'=>1,
                                //'help_text'=>__('The number of records that the server will process for every iteration within the configured timeout interval. If the export fails due to timeout you can lower this number accordingly and try again', 'wt-import-export-for-woo'),
				'field_group'=>'advanced_field',
				'validation_rule'=>array('type'=>'absint'),
                                'tip_description' => __('The number of records that the server will process for every iteration within the configured timeout interval.', 'wt-import-export-for-woo'),
			)                    
		);
                if($this->enable_export_code_editor){
		
                    $functions_file = self::get_file_path('functions.php');
                    $export_functions = file_exists($functions_file) ? file_get_contents($functions_file): "<?php\n\n\n\n?>";
                                    
                    $advanced_screen_fields['export_alter_code'] = array(
				'label'=>__("Custom PHP code", 'wt-import-export-for-woo'),
				'type'=>'textarea',
                                'merge_right'=>true,
                                'html_id' => 'wt_iew_export_alter_code',
				'value'=>$export_functions,
				'field_name'=>'export_alter_code',
				'is_advanced'=>1,
                                'help_text'=>__('The export data will be passed through the PHP function defined. The functions file can be seen at ', 'wt-import-export-for-woo').$functions_file,
				'field_group'=>'advanced_field',
				'validation_rule'=>array('type'=>'esc_textarea'),
                                'after_form_field'=>'<input type="button" id="export_alter_code_save" class="wt_iew_export_functions button button-primary" name="wt_iew_export_functions" value="'.__('Save functions').'" />'
			);
                }
		/* taking advanced fields from post type modules */
		$advanced_screen_fields=apply_filters('wt_iew_exporter_alter_advanced_fields', $advanced_screen_fields, $this->to_export, $advanced_form_data);
		return $advanced_screen_fields;
	}

	/**
	* Get steps
	*
	*/
	public function get_steps()
	{
		if($this->export_method=='quick') /* if quick export then remove some steps */
		{
			$out=array(
				'post_type'=>$this->steps['post_type'],
				'method_export'=>$this->steps['method_export'],
				'advanced'=>$this->steps['advanced'],
			);
			$this->steps=$out;
		}
		// Disable error display for the export process - https://stackoverflow.com/a/6491024/1117368
		if (strpos(@ini_get('disable_functions'), 'error_reporting') === false) {
			@ini_set('error_reporting', 0);
		}
		if (strpos(@ini_get('disable_functions'), 'display_errors') === false) {
			@ini_set('display_errors','Off');
		}			
		$this->steps=apply_filters('wt_iew_exporter_steps', $this->steps, $this->to_export);
		return $this->steps;
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
						$this->to_export=(isset($form_data['post_type_form_data']) && isset($form_data['post_type_form_data']['item_type']) ? $form_data['post_type_form_data']['item_type'] : '');
						if($this->to_export!="")
						{
							$this->export_method=(isset($form_data['method_export_form_data']) && isset($form_data['method_export_form_data']['method_export']) && $form_data['method_export_form_data']['method_export']!="" ?  $form_data['method_export_form_data']['method_export'] : $this->default_export_method);
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
						$this->to_export=(isset($form_data['post_type_form_data']) && isset($form_data['post_type_form_data']['item_type']) ? $form_data['post_type_form_data']['item_type'] : '');
						if($this->to_export!="")
						{
							$this->export_method=(isset($form_data['method_export_form_data']) && isset($form_data['method_export_form_data']['method_export']) && $form_data['method_export_form_data']['method_export']!="" ?  $form_data['method_export_form_data']['method_export'] : $this->default_export_method);
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
		
		
		if( $rerun_id>0 &&  0 === $this->cron_edit_id )
		{
			/* check the history module is available */
			$history_module_obj=Wt_Import_Export_For_Woo::load_modules('history');
			if(!is_null($history_module_obj))
			{
				/* check the history entry is for export and also has form_data */
				$history_data=$history_module_obj->get_history_entry_by_id($rerun_id);
				if($history_data && $history_data['template_type']==$this->module_base)
				{
					$form_data=maybe_unserialize($history_data['data']);
					if($form_data && is_array($form_data))
					{
						$this->to_export=(isset($form_data['post_type_form_data']) && isset($form_data['post_type_form_data']['item_type']) ? $form_data['post_type_form_data']['item_type'] : '');
						if($this->to_export!="")
						{
							$this->export_method=(isset($form_data['method_export_form_data']) && isset($form_data['method_export_form_data']['method_export']) && $form_data['method_export_form_data']['method_export']!="" ?  $form_data['method_export_form_data']['method_export'] : $this->default_export_method);
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
                if($this->enable_export_code_editor){
                    wp_enqueue_script($this->module_id.'-codemirror', plugin_dir_url(__FILE__).'assets/js/codemirror/codemirror.js');
                    wp_enqueue_script($this->module_id.'-cm-matchbrackets', plugin_dir_url(__FILE__).'assets/js/codemirror/matchbrackets.js');
                    wp_enqueue_script($this->module_id.'-cm-htmlmixed', plugin_dir_url(__FILE__).'assets/js/codemirror/htmlmixed.js');
                    wp_enqueue_script($this->module_id.'-cm-xml', plugin_dir_url(__FILE__).'assets/js/codemirror/xml.js');
                    wp_enqueue_script($this->module_id.'-cm-javascript', plugin_dir_url(__FILE__).'assets/js/codemirror/javascript.js');
                    wp_enqueue_script($this->module_id.'-cm-css', plugin_dir_url(__FILE__).'assets/js/codemirror/css.js');
                    wp_enqueue_script($this->module_id.'-cm-clike', plugin_dir_url(__FILE__).'assets/js/codemirror/clike.js');
                    wp_enqueue_script($this->module_id.'-cm-php', plugin_dir_url(__FILE__).'assets/js/codemirror/php.js');
                    wp_enqueue_script($this->module_id.'-cm-autorefresh', plugin_dir_url(__FILE__).'assets/js/codemirror/autorefresh.js');                
                }
                wp_enqueue_script($this->module_id, plugin_dir_url(__FILE__).'assets/js/main.js', array('jquery', 'jquery-ui-sortable', 'jquery-ui-datepicker'), WT_IEW_VERSION);                
		wp_enqueue_style('jquery-ui-datepicker');
		wp_enqueue_style(WT_IEW_PLUGIN_ID.'-jquery-ui', WT_IEW_PLUGIN_URL.'admin/css/jquery-ui.css', array(), WT_IEW_VERSION, 'all');
                if($this->enable_export_code_editor){
                    wp_enqueue_style(WT_IEW_PLUGIN_ID.'-codemirror', WT_IEW_PLUGIN_URL.'admin/css/codemirror.css', array(), WT_IEW_VERSION, 'all');
                }
                $params=array(
			'item_type'=>'',
			'steps'=>$this->steps,
			'rerun_id'=>$this->rerun_id,
			'cron_edit_id' => $this->cron_edit_id,		
			'to_export'=> isset( $_GET['wt_to_export'] ) ? sanitize_text_field( $_GET['wt_to_export'] ) : $this->to_export,
			'export_method'=>$this->export_method,
			'msgs'=>array(
				'select_post_type'=>__('Please select a post type', 'wt-import-export-for-woo'),
				'choosed_template'=>__('Choosed template: ', 'wt-import-export-for-woo'),
				'choose_export_method'=>__('Please select an export method.', 'wt-import-export-for-woo'),
				'choose_template'=>__('Please select an export template.', 'wt-import-export-for-woo'),
				'step'=>__('Step', 'wt-import-export-for-woo'),
				'choose_ftp_profile'=>__('Please select an FTP profile.', 'wt-import-export-for-woo'),
				'export_cancel_warn'=>__('Are you sure to stop the export?', 'wt-import-export-for-woo'),
				'export_canceled'=>__('Export canceled', 'wt-import-export-for-woo'),
			),
		);
		wp_localize_script($this->module_id, 'wt_iew_export_params', $params);

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
	* Upload data to the user choosed remote method (Eg: FTP)
	* @param   string   $step       the action to perform, here 'upload'
	*
	* @return array 
	*/
	public function process_upload($step, $export_id, $to_export)
	{
		$out=array(
			'response'=>false,
			'export_id'=>0,
			'history_id'=>0, //same as that of export id
			'finished'=>0,
			'file_url'=>'',
			'msg'=>'',
		);

		if($export_id==0) //it may be an error
		{
			return $out;
		}

		//take history data by export_id
		$export_data=Wt_Import_Export_For_Woo_History::get_history_entry_by_id($export_id);
		if(is_null($export_data)) //no record found so it may be an error
		{
			return $out;
		}

		$form_data=maybe_unserialize($export_data['data']);

		//taking file name
		$file_name=(isset($export_data['file_name']) ? $export_data['file_name'] : '');
		
		$file_path=$this->get_file_path($file_name);
		if($file_path===false)
		{
			$update_data=array(
				'status'=>Wt_Import_Export_For_Woo_History::$status_arr['failed'],
				'status_text'=>'File not found.' //no need to add translation function
			);
			$update_data_type=array(
				'%d',
				'%s',
			);
			Wt_Import_Export_For_Woo_History::update_history_entry($export_id, $update_data, $update_data_type);

            return $out;
		}

		/* updating output parameters */
		$out['export_id']=$export_id;
		$out['history_id']=$export_id;
		$out['file_url']='';

		//check where to copy the files
		$file_into='local';
		if(isset($form_data['advanced_form_data']))
		{
			$file_into=(isset($form_data['advanced_form_data']['wt_iew_file_into']) ? $form_data['advanced_form_data']['wt_iew_file_into'] : 'local');
		}

		if('local' != $file_into) /* file not save to local. Initiate the choosed remote profile */
		{
			$remote_adapter=Wt_Import_Export_For_Woo::get_remote_adapters('export', $file_into);
			if(is_null($remote_adapter)) /* adapter object not found */
			{
				$msg=sprintf('Unable to initailize %s', $file_into);
				Wt_Import_Export_For_Woo_History::record_failure($export_id, $msg);
				$out['msg']=__($msg);
				return $out;
			}

			/* upload the file */
			$upload_out_format = array('response'=>true, 'msg'=>'');

			$advanced_form_data=(isset($form_data['advanced_form_data']) ? $form_data['advanced_form_data'] : array());
                        
                        if(apply_filters('wt_iew_before_upload_remote_file', true, $file_path, $file_name, $advanced_form_data, $upload_out_format)){
			  $upload_data = $remote_adapter->upload($file_path, $file_name, $advanced_form_data, $upload_out_format);
                        }
			$out['response'] = (isset($upload_data['response']) ? $upload_data['response'] : false);
			$out['msg'] = (isset($upload_data['msg']) ? $upload_data['msg'] : __('Error'));

			//unlink the local file - commented unlik for local download even the file uploaded to FTP
			//@unlink($file_path);
		}else
		{
			$out['response']=true;
			$out['file_url']=html_entity_decode($this->get_file_url($file_name));
		}

		$out['finished']=1;  //if any error then also its finished, but with errors
		if($out['response'] === true) //success
		{
			
			
			$msg		 = __( '<div>File exported successfully to the selected FTP server.</div>' );
			$msg		 .= '<span class="wt_iew_info_box_finished_text" style="font-size: 10px;">';
			$msg		 .= '<a class="button button-secondary" style="margin-top:5px;" onclick="wt_iew_export.hide_export_info_box();">' . __( 'Close' ) . '</a>';
			$msg		 .= '</span>';
			$out[ 'msg' ]	 = $msg;


			/* updating finished status */
			$update_data=array(
				'status'=>1  //success
			);
			$update_data_type=array(
				'%d'
			);
			Wt_Import_Export_For_Woo_History::update_history_entry($export_id, $update_data, $update_data_type);

		}else //failed
		{
			//no need to add translation function in message
			Wt_Import_Export_For_Woo_History::record_failure($export_id, 'Failed while uploading');
		}
		return $out;
	}

	/**
	* 	Do the image export process
	*
	*/
	public function process_image_export($form_data, $step, $to_process, $file_name='', $export_id=0, $offset=0)
	{
		$out=array(
			'response'=>false,
			'new_offset'=>0,
			'export_id'=>0,
			'history_id'=>0, //same as that of export id
			'total_records'=>0,
			'finished'=>0,
			'file_url'=>'',
			'msg'=>'',
		);

		/* prepare form_data, If this was not first batch */
		if($export_id>0)
		{
			//take history data by export_id
			$export_data=Wt_Import_Export_For_Woo_History::get_history_entry_by_id($export_id);
			if(is_null($export_data)) //no record found so it may be an error
			{
				return $out;
			}

			//processing form data
			$form_data=(isset($export_data['data']) ? maybe_unserialize($export_data['data']) : array());
		}
		$this->to_export=$to_process;
		$default_batch_count=$this->_get_default_batch_count($form_data);
		$batch_count=$default_batch_count;
		$total_records=0;

		if(isset($form_data['advanced_form_data']))
		{
			$batch_count=(isset($form_data['advanced_form_data']['wt_iew_batch_count']) && (int)$form_data['advanced_form_data']['wt_iew_batch_count'] > 0  ? $form_data['advanced_form_data']['wt_iew_batch_count'] : $batch_count);
		}


		if($export_id==0) //first batch then create a history entry
		{
			$file_name=$this->to_export.'_export_image_'.date('Y-m-d-h-i-s').'.zip';
			$export_id=Wt_Import_Export_For_Woo_History::create_history_entry($file_name, $form_data, $this->to_export, $step);
			$offset=0;
		}else
		{
			//taking file name from export data
			$file_name=(isset($export_data['file_name']) ? $export_data['file_name'] : '');		
			$total_records=(isset($export_data['total']) ? $export_data['total'] : 0);
		}

		/* setting history_id in Log section */
		Wt_Import_Export_For_Woo_Log::$history_id=$export_id;

		$file_path=$this->get_file_path($file_name);
		if($file_path===false)
		{
			$msg='Unable to create backup directory. Please grant write permission for `wp-content` folder.';		
			
			//no need to add translation function in message
			Wt_Import_Export_For_Woo_History::record_failure($export_id, $msg);

            $out['msg']=__($msg);
            return $out;
		}

		/* giving full data */
		$form_data=apply_filters('wt_iew_export_full_form_data', $form_data, $to_process, $step, $this->selected_template_data);

		/* hook to get data from corresponding module. Eg: product, order */
		$export_data=array(
			'total'=>100,
			'images'=>array(),
		); 		
		$export_data=apply_filters('wt_iew_exporter_do_image_export', $export_data, $to_process, $step, $form_data, $this->selected_template_data, $this->export_method, $offset);
		
		if($offset==0)
		{
			$total_records=intval(isset($export_data['total']) ? $export_data['total'] : 0);
		}
		$this->_update_history_after_export($export_id, $offset, $total_records, $export_data);

		/* checking action is finshed */
		$is_last_offset=false;
		$new_offset=$offset+$batch_count; //increase the offset
		if($new_offset>=$total_records) //finished
		{
			$is_last_offset=true;
		}

		/* no data from corresponding module */
		if(!$export_data) //error !!!
		{
			//return $out;
		}else
		{
			$image_arr=(isset($export_data['images']) ? $export_data['images'] : array());
			include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-zipwriter.php';
			Wt_Import_Export_For_Woo_Zipwriter::write_to_file($file_path, $image_arr, $offset);
			
		}

		/* updating output parameters */
		$out['total_records']=$total_records;
		$out['export_id']=$export_id;
		$out['history_id']=$export_id;
		$out['file_url']='';
		$out['response']=true;

		/* updating action is finshed */	
		if($is_last_offset) //finished
		{
			$out['file_url']=html_entity_decode($this->get_file_url($file_name));
			$out['finished']=1; //finished

            if ( $total_records > 0 ) {
				$msg = __( '<div>Images exported successfully as separate zip file.</div>' );
				$msg .= '<span class="wt_iew_info_box_finished_text" style="font-size: 10px;">';
				$msg .= '<a class="button button-secondary" style="margin-top:5px;margin-right:5px;" onclick="wt_iew_export.hide_export_info_box();">' . __( 'Close' ) . '</a>';
				$msg .= '<a class="button button-secondary" style="margin-top:5px;" onclick="wt_iew_export.hide_export_info_box();" target="_blank" href="' . $out[ 'file_url' ] . '" >' . __( 'Download file' ) . '</a></span>';
			} else {
				$msg = __( '<div>There is no images to export.</div>' );
				$msg .= '<span class="wt_iew_info_box_finished_text" style="font-size: 10px;">';
				$msg .= '<a class="button button-secondary" style="margin-top:5px;" onclick="wt_iew_export.hide_export_info_box();">' . __( 'Close' ) . '</a>';
				$msg .= '</span>';
			}

			$out[ 'msg' ] = $msg;

			/* updating finished status */
			$update_data=array(
				'status'=>Wt_Import_Export_For_Woo_History::$status_arr['finished'],
				'status_text'=>'Finished' //translation function not needed
			);
			$update_data_type=array(
				'%d',
				'%s',
			);
			Wt_Import_Export_For_Woo_History::update_history_entry($export_id,$update_data,$update_data_type);

		}else
		{
			$out['new_offset']=$new_offset;
			$out['msg']=sprintf(__('Exporting...(%d out of %d)'), $new_offset, $total_records);
		}
		return $out;

	}

	/**
	* 	Do the export process
	*/
	public function process_action($form_data, $step, $to_process, $file_name='', $export_id=0, $offset=0)
	{
		$out=array(
			'response'=>false,
			'new_offset'=>0,
			'export_id'=>0,
			'history_id'=>0, //same as that of export id
			'total_records'=>0,
			'finished'=>0,
			'file_url'=>'',
			'msg'=>'',
		);

		/* prepare form_data, If this was not first batch */
		if($export_id>0)
		{
			//take history data by export_id
			$export_data=Wt_Import_Export_For_Woo_History::get_history_entry_by_id($export_id);
			if(is_null($export_data)) //no record found so it may be an error
			{
				return $out;
			}

			//processing form data
			$form_data=(isset($export_data['data']) ? maybe_unserialize($export_data['data']) : array());
		}
		$this->to_export=$to_process;
		$default_batch_count=$this->_get_default_batch_count($form_data);
		$batch_count=$default_batch_count;		
		$file_as='csv';
		$csv_delimiter=',';
        $use_bom=false;
		$total_records=0;
		if(isset($form_data['advanced_form_data']))
		{

			$batch_count=(isset($form_data['advanced_form_data']['wt_iew_batch_count']) && (int)$form_data['advanced_form_data']['wt_iew_batch_count'] > 0 ? $form_data['advanced_form_data']['wt_iew_batch_count'] : $batch_count);
			$file_as=(isset($form_data['advanced_form_data']['wt_iew_file_as']) ? $form_data['advanced_form_data']['wt_iew_file_as'] : 'csv');
			$csv_delimiter=(isset($form_data['advanced_form_data']['wt_iew_delimiter']) ? $form_data['advanced_form_data']['wt_iew_delimiter'] : ',');
			$csv_delimiter=($csv_delimiter=="" ? ',' : $csv_delimiter);
            $use_bom= (isset($form_data['advanced_form_data']['wt_iew_include_bom']) && ($form_data['advanced_form_data']['wt_iew_include_bom'] == "Yes" || $form_data['advanced_form_data']['wt_iew_include_bom'] == 1)) ? true : $use_bom;
                        
		}
             
		//for Quick method exicuted from step 2(method of export)
		if(empty($form_data['advanced_form_data']['wt_iew_batch_count'])){
			$form_data['advanced_form_data']['wt_iew_batch_count'] = $batch_count;
		}
        
		$this->allowed_export_file_type = apply_filters('wt_ier_allowed_export_file_types', $this->allowed_export_file_type);
		$file_as=(isset($this->allowed_export_file_type[$file_as]) ? $file_as : 'csv');

		
		$generated_file_name=$this->to_export.'_export_'.date('Y-m-d-h-i-s').'.'.$file_as;
                
        $args = array('file_name'=>$file_name, 'to_export'=>$this->to_export, 'step'=>$step, 'file_as'=> $file_as, 'form_data'=>$form_data);
        $generated_file_name = apply_filters('wt_iew_export_filename',$generated_file_name,$args);
        $file_name = apply_filters('wt_iew_export_input_filename',$file_name);

		if($export_id==0) //first batch then create a history entry
		{
			$file_name=($file_name=="" ? $generated_file_name : sanitize_file_name($file_name.'.'.$file_as));
			$export_id=Wt_Import_Export_For_Woo_History::create_history_entry($file_name, $form_data, $this->to_export, $step);
			$offset=0;
		}else
		{
			//taking file name from export data
			$file_name=(isset($export_data['file_name']) ? $export_data['file_name'] : $generated_file_name);		
			$total_records=(isset($export_data['total']) ? $export_data['total'] : 0);
		}
		/* setting history_id in Log section */
		Wt_Import_Export_For_Woo_Log::$history_id=$export_id;


		$file_path=$this->get_file_path($file_name);
		if($file_path===false)
		{
			$msg='Unable to create backup directory. Please grant write permission for `wp-content` folder.';		
			
			//no need to add translation function in message
			Wt_Import_Export_For_Woo_History::record_failure($export_id, $msg);

            $out['msg']=__($msg);
            return $out;
		}

		/* giving full data */
		$form_data=apply_filters('wt_iew_export_full_form_data', $form_data, $to_process, $step, $this->selected_template_data);

		/* hook to get data from corresponding module. Eg: product, order */
		$export_data=array(
			'total'=>100,
			'head_data'=>array("abc"=>"hd1", "bcd"=>"hd2", "cde"=>"hd3", "def"=>"hd4"),
			'body_data'=>array(
				array("abc"=>"Abc1", "bcd"=>"Bcd1", "cde"=>"Cde1", "def"=>"Def1"),
  				array("abc"=>"Abc2", "bcd"=>"Bcd2", "cde"=>"Cde2", "def"=>"Def2")
			),
		); 		

		/* in scheduled export. The export method will not available so we need to take it from form_data */
		$form_data_export_method=(isset($form_data['method_export_form_data']) && isset($form_data['method_export_form_data']['method_export']) ?  $form_data['method_export_form_data']['method_export'] : $this->default_export_method);
		$this->export_method=($this->export_method=="" ? $form_data_export_method : $this->export_method);

                if($this->enable_export_code_editor){
                    $functions_file = self::get_file_path('functions.php');

                    if (@file_exists($functions_file)) {
                            require_once $functions_file;
                    }
                }
                
		$export_data=apply_filters('wt_iew_exporter_do_export', $export_data, $to_process, $step, $form_data, $this->selected_template_data, $this->export_method, $offset);
		
                
                
                if($offset==0)
		{
			$total_records=intval(isset($export_data['total']) ? $export_data['total'] : 0);
		}
		$this->_update_history_after_export($export_id, $offset, $total_records, $export_data);

		/* checking action is finshed */
		$is_last_offset=false;
		$new_offset=$offset+$batch_count; //increase the offset
		if($new_offset>=$total_records) //finished
		{
			$is_last_offset=true;
		}

		/* no data from corresponding module */
		if(!$export_data) //error !!!
		{
			//return $out;
		}else
		{
			if( 'xml' == $file_as )
			{
				include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-xmlwriter.php';
				$writer=new Wt_Import_Export_For_Woo_Xmlwriter($file_path);
			}elseif( 'xls' == $file_as || 'xlsx' == $file_as ){
				include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-excelwriter.php';
				$writer=new Wt_Import_Export_For_Woo_Excelwriter($file_path, $offset, $csv_delimiter, $use_bom);

			}else
			{
				include_once WT_IEW_PLUGIN_PATH.'admin/classes/class-csvwriter.php';
				if( 'tsv' == $file_as ){
					$csv_delimiter = "\t";
				}
				$writer=new Wt_Import_Export_For_Woo_Csvwriter($file_path, $offset, $csv_delimiter, $use_bom);
			}
			$args = array('file_path'=>$file_path,'offset'=>$offset,'csv_delimiter'=> $csv_delimiter);
			$writer = apply_filters('wt_iew_custom_file_writer',$writer,$args);
                                                
                        /**
			*	Alter export data before writing to file.
			*	@param 	array 	$export_data  		data to export
			*	@param 	int 	$offset  			current offset
			*	@param 	boolean $is_last_offset 	is current offset is last one
			*	@param 	string 	$file_as 			file type to write Eg: XML, CSV
			*	@param 	string 	$to_export 			Post type
			*	@param 	string 	$csv_delimiter 		CSV delimiter. In case of CSV export
			*	@return array 	$export_data 		Altered export data
			*/
			$export_data=apply_filters('wt_iew_alter_export_data', $export_data, $offset, $is_last_offset, $file_as, $this->to_export, $csv_delimiter);                          
			$writer->write_to_file($export_data, $offset, $is_last_offset, $this->to_export);
		}

		/* updating output parameters */
		$out['total_records']=$total_records;
		$out['export_id']=$export_id;
		$out['history_id']=$export_id;
		$out['file_url']='';
		$out['response']=true;

		/* updating action is finshed */	
		if($is_last_offset) //finished
		{
			//check where to copy the files
			$file_into='local';
			if(isset($form_data['advanced_form_data']))
			{
				$file_into=(isset($form_data['advanced_form_data']['wt_iew_file_into']) ? $form_data['advanced_form_data']['wt_iew_file_into'] : 'local');
			}
			if('local' != $file_into) /* file not save to local. Initiate the choosed remote profile */
			{
				$out['finished']=2; //file created, next upload it

				$out['msg']=sprintf(__('Uploading to %s'), $file_into);
                                if( 0 == $total_records && isset( $export_data['no_post'] ) ){	
					$out['no_post'] = true;
					$out['msg'] = $export_data['no_post'];                    
				}
			}else
			{
				$out['file_url']=html_entity_decode($this->get_file_url($file_name));
				$out['finished']=1; //finished
								$msg = '<span class="wt_iew_popup_close dashicons dashicons-dismiss" style="line-height:10px;width:auto" onclick="wt_iew_export.hide_export_info_box();"></span>'; 
                                $msg.= '<h2><span class="dashicons dashicons-yes" style="background:#329D63;color:#fff;border-radius:15px; margin-right:10px;padding:5px;"></span><span style="font-weight:400">' . __('File ready for download!') . '</span></h2>';
                                
                                $msg.='<span class="wt_iew_info_box_finished_text" style="font-size: 10px; display:block">';
                                
                                $msg.='<a class="button button-primary" style="margin-top:10px;font-weight:bold;padding:5px 25px" onclick="wt_iew_export.hide_export_info_box();" target="_blank" href="'.$out['file_url'].'" ><span style="margin-top: 7px;margin-right: 4px;" class="dashicons dashicons-download"></span>'.__('Download').'</a></span>';
                               
								if(Wt_Import_Export_For_Woo_Admin::module_exists('history'))
                                {
                                        $history_module_id=Wt_Import_Export_For_Woo::get_module_id('history');
                                        $history_page_url=admin_url('admin.php?page='.$history_module_id);
                                        $msg.= '<p style="font-size: 10px;font-weight:normal;" >' . sprintf( __('Manage your exports from %1$s History %2$s'), '<a style="color:#2271b1;" href="'.$history_page_url.'" target="_blank">', '</a>' ) . '</p>';
//                                        $msg.=sprintf(__('You can manage exports from %s History %s section.'), '<a href="'.$history_page_url.'" target="_blank">', '</a>');
                                }
				if( 0 == $total_records && isset( $export_data['no_post'] ) ){	
					$out['no_post'] = true;
					$msg = $export_data['no_post'];                    
				}
				$out['msg']=$msg;
				
				/* updating finished status */
				$update_data=array(
					'status'=>Wt_Import_Export_For_Woo_History::$status_arr['finished'],
					'status_text'=>'Finished' //translation function not needed
				);
				$update_data_type=array(
					'%d',
					'%s',
				);
				Wt_Import_Export_For_Woo_History::update_history_entry($export_id,$update_data,$update_data_type);
			}
			do_action('wt_ier_export_process_finished',$file_path,$file_into, $to_process);


		}else
		{
			$export_percentage = (int)(( $new_offset / $total_records ) * 100);
			$out['new_offset']=$new_offset;
            $out['msg']=sprintf(__('Exporting...(%d out of %d)'), $new_offset, $total_records);
			$out['total_percent'] = $export_percentage;
			$out['total_done'] = $new_offset;
		}
		return $out;
	}


	public static function get_file_path($file_name)
	{
		if(!is_dir(self::$export_dir))
        {
            if(!mkdir(self::$export_dir, 0700))
            {
            	return false;
            }else
            {
            	$files_to_create=array('.htaccess' => 'deny from all', 'index.php'=>'<?php // Silence is golden');
		        foreach($files_to_create as $file=>$file_content)
		        {
		        	if(!file_exists(self::$export_dir.'/'.$file))
			        {
			            $fh=@fopen(self::$export_dir.'/'.$file, "w");
			            if(is_resource($fh))
			            {
			                fwrite($fh, $file_content);
			                fclose($fh);
			            }
			        }
		        } 
            }
        }
        return self::$export_dir.'/'.$file_name;
	}

	/**
	*  	Download file via a nonce URL
	*/
	public function download_file()
	{
		if(isset($_GET['wt_iew_export_download']))
		{ 
			if(Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID)) /* check nonce and role */
			{
				$file_name=(isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '');
				if($file_name!="")
				{
					$file_arr=explode(".", $file_name);
					$file_ext=end($file_arr);
					$this->allowed_export_file_type = apply_filters('wt_ier_allowed_export_file_types', $this->allowed_export_file_type);
					if(isset($this->allowed_export_file_type[$file_ext]) || $file_ext=='zip') /* Only allowed files. Zip file in image export */
					{
						$file_path=self::$export_dir.'/'.$file_name;
						if(file_exists($file_path) && is_file($file_path)) /* check existence of file */
						{	
							header('Pragma: public');
						    header('Expires: 0');
						    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						    header('Cache-Control: private', false);
						    header('Content-Transfer-Encoding: binary');
						    header('Content-Disposition: attachment; filename="'.$file_name.'";');
						    header('Content-Description: File Transfer');
						    header('Content-Type: application/octet-stream');
						    //header('Content-Length: '.filesize($file_path));

						    $chunk_size=1024 * 1024;
						    $handle=@fopen($file_path, 'rb');
						    while(!feof($handle))
						    {
						        $buffer = fread($handle, $chunk_size);
						        echo $buffer;
						        ob_flush();
						        flush();
						    }
						    fclose($handle);
						    exit();

						}
					}
				}	
			}
		}
	}

	private function _update_history_after_export($export_id, $offset, $total_records, $export_data)
	{
		/* we need to update total record count on first batch */
		if($offset==0)
		{
			$update_data=array(
				'total'=>$total_records
			);			
		}else
		{
			/* updating completed offset */
			$update_data=array(
				'offset'=>$offset
			);
		}
		$update_data_type=array(
			'%d'
		);
		Wt_Import_Export_For_Woo_History::update_history_entry($export_id, $update_data, $update_data_type);
	}

	private function _get_default_batch_count($form_data)
	{
		$default_batch_count=absint(apply_filters('wt_iew_exporter_alter_default_batch_count', $this->default_batch_count, $this->to_export, $form_data));
		$form_data=null;
		unset($form_data);
		return ($default_batch_count==0 ? $this->default_batch_count : $default_batch_count);
	}

	/**
	*	Generating downloadable URL for a file
	*/
	private function get_file_url($file_name)
	{
		return wp_nonce_url(admin_url('admin.php?wt_iew_export_download=true&file='.$file_name), WT_IEW_PLUGIN_ID);
	}
        
        function wt_save_export_functions(){


	if(Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID))
		{

            
	$functions_file = self::get_file_path('functions.php');

        		        	if(!file_exists($functions_file))
			        {
			            $fh=@fopen($functions_file, "w");
			            if(is_resource($fh))
			            {
			                fwrite($fh, '');
			                fclose($fh);
			            }
			        }
                                
        
        $post = (stripslashes(html_entity_decode(esc_textarea($_POST['export_function']), ENT_QUOTES)));

	$response = wp_remote_post('http://phpcodechecker.com/api', array(
		'body' => array(
			'code' => $post
		)
	));

	if (is_wp_error($response))
	{
		$error_message = $response->get_error_message();   		
   		exit(json_encode(array('result' => false, 'msg' => $error_message))); die;
	}
	else
	{
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if ($body['errors'] === 'TRUE')
		{			
			exit(json_encode(array('result' => false, 'msg' => $body['syntax']['message']))); die;	
		}
		elseif($body['errors'] === 'FALSE')
		{
			if (strpos($post, "<?php") === false || strpos($post, "?>") === false)
			{
				exit(json_encode(array('result' => false, 'msg' => __('PHP code must be wrapped in "&lt;?php" and "?&gt;"', 'wt-import-export-for-woo')))); die;	
			}	
			else
			{
				file_put_contents($functions_file, $post);
			}					
		}
        elseif(empty($body)){
            file_put_contents($functions_file, $post);
        }
	}	

	exit(json_encode(array('result' => true, 'msg' => __('File has been successfully updated.', 'wt-import-export-for-woo')))); die;
                }
}
        
}
Wt_Import_Export_For_Woo::$loaded_modules['export']=new Wt_Import_Export_For_Woo_Export();