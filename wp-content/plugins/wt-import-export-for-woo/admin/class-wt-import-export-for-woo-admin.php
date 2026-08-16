<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.webtoffee.com/
 * @since      1.0.0
 *
 * @package    Wt_Import_Export_For_Woo
 * @subpackage Wt_Import_Export_For_Woo/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wt_Import_Export_For_Woo
 * @subpackage Wt_Import_Export_For_Woo/admin
 * @author     Webtoffee <info@webtoffee.com>
 */
class Wt_Import_Export_For_Woo_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/*
	 * module list, Module folder and main file must be same as that of module name
	 * Please check the `register_modules` method for more details
	 */
	public static $modules=array(	
		'history',
		'export',
		'import',		
		'ftp',
		'cron',     
		'licence_manager',     
	);

	public static $existing_modules=array();

	public static $addon_modules=array(
		'product',
		'order',
		'product_review',
		'product_categories',
		'product_tags',            
                'user',
                'coupon',
                'subscription', 
	);
        
        /*
         * WebToffee data identifier, this variable used for identify that the data is belongs to WebToffee Import/Export.
         * Use1: used in evaluation operators prefix.
         * Use2: We can use this for identify WebToffee operations (@[]/+-*) etc
         * !!!important: Do not change this value frequently
         */                
        public static $wt_iew_prefix = 'wt_iew';

        /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
            
            if(Wt_Import_Export_For_Woo_Common_Helper::wt_is_screen_allowed()){
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wt-import-export-for-woo-admin.css', array(), $this->version, 'all' );
            }
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
            if(Wt_Import_Export_For_Woo_Common_Helper::wt_is_screen_allowed()){
		/* enqueue scripts */
		if(!function_exists('is_plugin_active'))
		{
			include_once(ABSPATH.'wp-admin/includes/plugin.php');
		}
		if(is_plugin_active('woocommerce/woocommerce.php'))
		{
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wt-import-export-for-woo-admin.js', array( 'jquery', 'jquery-tiptip'), $this->version, false );
		}else
		{
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wt-import-export-for-woo-admin.js', array( 'jquery'), $this->version, false );
			wp_enqueue_script(WT_IEW_PLUGIN_ID.'-tiptip', WT_IEW_PLUGIN_URL.'admin/js/tiptip.js', array('jquery'), WT_IEW_VERSION, false);
		}

		$params=array(
			'nonces' => array(
		        'main' => wp_create_nonce(WT_IEW_PLUGIN_ID),
		     ),
			'ajax_url' => admin_url('admin-ajax.php'),
			'plugin_id' =>WT_IEW_PLUGIN_ID,
			'msgs'=>array(
				'settings_success'=>__('Settings updated', 'wt-import-export-for-woo'),
				'all_fields_mandatory'=>__('All fields are mandatory', 'wt-import-export-for-woo'),
				'settings_error'=>__('Unable to update settings', 'wt-import-export-for-woo'),
                'template_del_error'=>__('Unable to delete template', 'wt-import-export-for-woo'),
                'template_del_loader'=>__('Deleting template...', 'wt-import-export-for-woo'),
				'value_empty'=>__('Value is empty', 'wt-import-export-for-woo'),
				'error'=>sprintf(__('An unknown error has occurred! Refer to our %s troubleshooting guide %s for assistance. You may also try increasing <b>maximum execution time</b> in advanced %s settings %s.', 'wt-import-export-for-woo'), '<a href="'.WT_IEW_DEBUG_PRO_TROUBLESHOOT.'" target="_blank">', '</a>', '<a href="'.admin_url('admin.php?page='.WT_IEW_PLUGIN_ID).'" target="blank">', '</a>'),
				'success'=>__('Success', 'wt-import-export-for-woo'),
				'loading'=>__('Loading...', 'wt-import-export-for-woo'),
				'no_results_found'=>__('No results found.', 'wt-import-export-for-woo'),
				'sure'=>__('Are you sure?', 'wt-import-export-for-woo'),
				'use_expression'=>__('Apply', 'wt-import-export-for-woo'),
				'cancel'=>__('Cancel', 'wt-import-export-for-woo'),
			),
			'pro_plugins' => array(
					'order' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2021/03/Order_SampleCSV.csv",
					),
					'coupon' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2016/09/Coupon_Sample_CSV.csv",
					),
					'product' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2021/04/Product_SampleCSV-.csv",
					),
					'product_review' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2021/04/product_review_SampleCSV.csv",
					),
					'product_categories' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2021/09/Sample-CSV-of-product-categories.csv",
					),
					'product_tags' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2021/09/Sample-CSV-with-product-tags.csv",
					),
					'user' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2020/10/Sample_Users.csv",
					),
					'subscription' => array(
						'sample_csv_url' => "https://www.webtoffee.com/wp-content/uploads/2021/04/Subscription_Sample_CSV.csv",
					)				
				)
			);
		wp_localize_script($this->plugin_name, 'wt_iew_params', $params);
            }

	}

	/**
	 * Registers menu options
	 * Hooked into admin_menu
	 *
	 * @since    1.0.0
	 */
        public function admin_menu()
	{
		$menus=array(
			'general-settings'=>array(
				'menu',
				__('General Settings'),
				__('General Settings'),
				apply_filters('wt_import_export_allowed_capability', 'import'),
				WT_IEW_PLUGIN_ID,
				array($this,'admin_settings_page'),
				'dashicons-controls-repeat',
				56
			)
		);
		$menus=apply_filters('wt_iew_admin_menu',$menus);

                $menu_order=array("export","export-sub","import","history","history_log","cron");
                $this->wt_menu_order_changer($menus,$menu_order);                                            

		$main_menu = reset($menus); //main menu must be first one

		$parent_menu_key=$main_menu ? $main_menu[4] : WT_IEW_PLUGIN_ID;

		/* adding general settings menu */
		$menus['general-settings-sub']=array(
			'submenu',
			$parent_menu_key,
			__('General Settings'),
			__('General Settings'), 
			apply_filters('wt_import_export_allowed_capability', 'import'),
			WT_IEW_PLUGIN_ID,
			array($this, 'admin_settings_page')
		);
		if(count($menus)>0)
		{
			foreach($menus as $menu)
			{
				if($menu[0]=='submenu')
				{
					/* currently we are only allowing one parent menu */
					add_submenu_page($parent_menu_key,$menu[2],$menu[3],$menu[4],$menu[5],$menu[6]);
				}else
				{
					add_menu_page($menu[1],$menu[2],$menu[3],$menu[4],$menu[5],$menu[6],$menu[7]);	
				}
			}
		}
		if(function_exists('remove_submenu_page')){
			//remove_submenu_page(WT_PIEW_POST_TYPE, WT_PIEW_POST_TYPE);
		}
	}
        
        
        function wt_menu_order_changer (&$arr, $index_arr) {
            $arr_t=array();
            foreach($index_arr as $i=>$v) {
                foreach($arr as $k=>$b) {
                    if ($k==$v) $arr_t[$k]=$b;
                }
            }
            $arr=$arr_t;
        }
        

	public function admin_settings_page()
	{	
		include(plugin_dir_path( __FILE__ ).'partials/wt-import-export-for-woo-admin-display.php');
	}

	/**
	* 	Save admin settings and module settings ajax hook
	*/
	public function save_settings()
	{
		$out=array(
			'status'=>false,
			'msg'=>__('Error'),
		);

		if(Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID)) 
    	{
    		$advanced_settings=Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings();
    		$advanced_fields=Wt_Import_Export_For_Woo_Common_Helper::get_advanced_settings_fields();
    		$validation_rule=Wt_Import_Export_For_Woo_Common_Helper::extract_validation_rules($advanced_fields);
    		$new_advanced_settings=array();
    		foreach($advanced_fields as $key => $value) 
	        {
                    $form_field_name = isset($value['field_name']) ? $value['field_name'] : '';
	            $field_name=(substr($form_field_name,0,8)!=='wt_iew_' ? 'wt_iew_' : '').$form_field_name;
	            $validation_key=str_replace('wt_iew_', '', $field_name);
	            if(isset($_POST[$field_name]))
	            {      	
	            	$new_advanced_settings[$field_name]=Wt_Iew_Sh_Pro::sanitize_data($_POST[$field_name], $validation_key, $validation_rule);
	            }
	        }
			$checkbox_items = array( 'wt_iew_enable_import_log', 'wt_iew_enable_history_auto_delete', 'wt_iew_enable_export_code', 'wt_iew_enable_speed_mode', 'wt_iew_default_time_zone' );
			foreach ( $checkbox_items as $checkbox_item ){
				$new_advanced_settings[$checkbox_item] = isset( $new_advanced_settings[$checkbox_item] ) ? $new_advanced_settings[$checkbox_item] : 0;
			}
			
	        Wt_Import_Export_For_Woo_Common_Helper::set_advanced_settings($new_advanced_settings);
	        $out['status']=true;
	        $out['msg']=__('Settings Updated');
	        do_action('wt_iew_after_advanced_setting_update', $new_advanced_settings);        
    	}
		echo json_encode($out);
		exit();
	}

        /**
	* 	Delete pre-saved temaplates entry from DB - ajax hook
	*/
        public function delete_template() {
            $out = array(
                'status' => false,
                'msg' => __('Error'),
            );

            if (Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID)) {
                if (isset($_POST['template_id'])) {

                    global $wpdb;
                    $template_id = absint($_POST['template_id']);
                    $tb = $wpdb->prefix . Wt_Import_Export_For_Woo::$template_tb;
                    $where = "=%d";
                    $where_data = array($template_id);
                    $wpdb->query($wpdb->prepare("DELETE FROM $tb WHERE id" . $where, $where_data));
                    $out['status'] = true;
                    $out['msg'] = __('Template deleted successfully', 'wt-import-export-for-woo');
                    $out['template_id'] = $template_id;
                }
            }
            wp_send_json($out);

        }

        /**
	 Registers modules: admin	 
	 */
	public function admin_modules()
	{ 
		$wt_iew_admin_modules=get_option('wt_iew_admin_modules');
		if($wt_iew_admin_modules===false)
		{
			$wt_iew_admin_modules=array();
		}
		foreach (self::$modules as $module) //loop through module list and include its file
		{
			$is_active=1;
			if(isset($wt_iew_admin_modules[$module]))
			{
				$is_active=$wt_iew_admin_modules[$module]; //checking module status
			}else
			{
				$wt_iew_admin_modules[$module]=1; //default status is active
			}
			$module_file=plugin_dir_path( __FILE__ )."modules/$module/$module.php";
			if(file_exists($module_file) && $is_active==1)
			{
				self::$existing_modules[]=$module; //this is for module_exits checking
				require_once $module_file;
			}else
			{
				$wt_iew_admin_modules[$module]=0;	
			}
		}
		$out=array();
		foreach($wt_iew_admin_modules as $k=>$m)
		{
			if(in_array($k, self::$modules))
			{
				$out[$k]=$m;
			}
		}
		update_option('wt_iew_admin_modules',$out);


		/**
		*	Add on modules 
		*/
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		foreach (self::$addon_modules as $module) //loop through module list and include its file
		{
			$plugin_file="wt-import-export-for-woo-$module/wt-import-export-for-woo-$module.php";
			if(is_plugin_active($plugin_file))
			{
				$module_file=WP_PLUGIN_DIR."/wt-import-export-for-woo-$module/$module/$module.php";
				if(file_exists($module_file))
				{
					self::$existing_modules[]=$module;
					require_once $module_file;
				}				
			}
		}

	}

	public static function module_exists($module)
	{
		return in_array($module, self::$existing_modules);
	}

	/**
	 * Envelope settings tab content with tab div.
	 * relative path is not acceptable in view file
	 */
	public static function envelope_settings_tabcontent($target_id,$view_file="",$html="",$variables=array(),$need_submit_btn=0)
	{
		extract($variables);
	?>
		<div class="wt-iew-tab-content" data-id="<?php echo $target_id;?>">
			<?php
			if($view_file!="" && file_exists($view_file))
			{
				include_once $view_file;
			}else
			{
				echo $html;
			}
			?>
			<?php 
			if($need_submit_btn==1)
			{
				include WT_IEW_PLUGIN_PATH."admin/views/admin-settings-save-button.php";
			}
			?>
		</div>
	<?php
	}

	/**
	*	Plugin page action links
	*/
	public function plugin_action_links($links)
	{
		$links[] = '<a href="'.admin_url('admin.php?page='.WT_IEW_PLUGIN_ID).'_export">'.__('Export').'</a>';
		$links[] = '<a href="'.admin_url('admin.php?page='.WT_IEW_PLUGIN_ID).'_import">'.__('Import').'</a>';
		$links[] = '<a href="'.admin_url('admin.php?page='.WT_IEW_PLUGIN_ID).'">'.__('Settings').'</a>';
		$links[] = '<a href="https://www.webtoffee.com/category/documentation/import-export-suite-woocommerce/" target="_blank">'.__('Documentation').'</a>';
		$links[] = '<a href="https://www.webtoffee.com/support/" target="_blank">'.__('Support').'</a>';
		return $links;
	}
	
		/**
		 * Search for users and return json.
		 */
		public static function ajax_user_search() {

			if (Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID)) {

				if (!current_user_can('export')) {
					wp_die(-1);
				}

				$term = isset($_POST['term']) ? (string) sanitize_text_field(wp_unslash($_POST['term'])) : '';
				$limit = 0;

				if (empty($term)) {
					wp_die();
				}

				// If search is smaller than 3 characters, limit result set to avoid
				// too many rows being returned.
				if (3 > strlen($term)) {
					$limit = 20;
				} else {
					$limit = 50;
				}


				$found_users = array();
				$users = new WP_User_Query( apply_filters( 'wt_iew_user_search_query_args', array(
					'search' => '*' . esc_attr($term) . '*',
					'number' => $limit,
					'search_columns' => array(
						'user_login',
						'user_email'
					))
				));
				$users_found = $users->get_results();

				foreach ($users_found as $user) {
					$the_customer = get_userdata($user->ID);
					/* translators: 1: user display name 2: user ID 3: user email */
					$found_users[] = array('id' => $the_customer->ID, 'text' => sprintf(
								/* translators: $1: user name, $2 user id, $3: user email */
								esc_html__('%1$s (#%2$s - %3$s)'),
								$the_customer->first_name . ' ' . $the_customer->last_name,
								$the_customer->ID,
								$the_customer->user_email
						)
					);
				}

				wp_send_json(apply_filters('wt_json_search_found_users', $found_users));
			}
		}
		
		
		/**
		 * Search for coupons and return json.
		 */
		public static function ajax_coupon_search() {

			if (Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID)) {

				if (!current_user_can('export')) {
					wp_die(-1);
				}

				$term = isset($_POST['term']) ? (string) sanitize_text_field(wp_unslash($_POST['term'])) : '';

				if (empty($term)) {
					wp_die();
				}


				global $wpdb;

				$like = $wpdb->esc_like($term);
				$query = "
                SELECT      post.post_title as id, post.post_title as text
                FROM        " . $wpdb->posts . " as post
                WHERE       post.post_title LIKE %s
                AND         post.post_type = 'shop_coupon'
                AND         post.post_status <> 'trash'
                ORDER BY    post.post_title
                LIMIT 0,10
				";

				$found_coupons = $wpdb->get_results($wpdb->prepare($query, '%' . $like . '%'));

				wp_send_json(apply_filters('wt_json_search_found_coupons', $found_coupons));
			}
		}
		
		/**
		 * Add more tools options under WC status > tools
		 * 
		 * @param array $wc_tools WC Tools items
		 */
		public function add_debug_tools($wc_tools) {

			$wc_tools = apply_filters('wt_add_woocommerce_debug_tools', $wc_tools);
			return $wc_tools;
		}

		
	/**
	 * 	Save admin settings and module settings ajax hook
	 */
	public function save_email_settings() {

		$out = array(
			'status' => false,
			'msg' => __('Error'),
		);

		if (Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID)) {

			$wt_iew_user_email_subject = sanitize_textarea_field(wp_unslash($_POST['wt_iew_email_subject']));

			$wt_iew_user_email_body = wp_kses_post(wp_unslash($_POST['wt_iew_user_email_body']));

			update_option('wt_iew_user_email_subject', $wt_iew_user_email_subject);
			update_option('wt_iew_user_email_body', $wt_iew_user_email_body);

			$out['status'] = true;
			$out['msg'] = __('Settings Updated');
		}
		echo json_encode($out);
		exit();
	}

}
