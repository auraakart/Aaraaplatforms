<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Wt_Iew_Licence_Manager
{
	public $module_id='';
	public $module_base='licence_manager';
	public $api_url='https://www.webtoffee.com/';
	public $main_plugin_slug='';
	public $tab_icons=array(
		'active'=>'<span class="dashicons dashicons-yes" style="color:#03da01; font-size:25px;"></span>',   
	    'inactive'=>'<span class="dashicons dashicons-warning" style="color:#ff1515; font-size:25px;"></span>'
	);

	public $products=array();
	public $plguin_id_slugs  =array(
		'196725' => 'wt-import-export-for-woo',
		'196721' => 'wt-import-export-for-woo-user',
		'214146' => 'wt-import-export-for-woo-product',
		'203058' => 'wt-import-export-for-woo-order'
	);
	public $my_account_url;
	public function __construct()
	{
		$this->module_id 			=Wt_Import_Export_For_Woo::get_module_id($this->module_base);
		$this->my_account_url		=$this->api_url.'my-account';
		$this->main_plugin_slug		=dirname(WT_IEW_PLUGIN_BASENAME);

		require_once plugin_dir_path(__FILE__).'classes/class-edd.php';	
		require_once plugin_dir_path(__FILE__).'classes/class-wc.php';	

		$this->products=array(
			$this->main_plugin_slug=>array(
				'product_id'			=>	WT_IEW_ACTIVATION_ID,
				'product_edd_id'		=>	'196725',
				'plugin_settings_url'	=>	admin_url('admin.php?page='.WT_IEW_PLUGIN_ID.'#wt-licence'),
				'product_version'		=>	WT_IEW_VERSION,
				'product_name'			=>	WT_IEW_PLUGIN_BASENAME, 
				'product_slug'			=>	$this->main_plugin_slug,
				'product_display_name'	=>	$this->get_display_name($this->main_plugin_slug), 
			)
		);

		add_action('plugins_loaded', array($this, 'init'), 1);

		/**
		*	Add tab to settings section
		*/
		add_filter('wt_iew_plugin_settings_tabhead', array($this, 'licence_tabhead'));
		add_action('wt_iew_plugin_out_settings_form', array($this, 'licence_content'));

		/**
		*	 Main Ajax hook to handle all ajax requests 
		*/
		add_action('wp_ajax_iew_licence_manager_ajax', array($this, 'ajax_main'),11);

		/**
		*	 Check for plugin updates
		*/
		add_filter( 'pre_set_site_transient_update_plugins',array($this, 'update_check'));

		/** 
		*	Check For Plugin Information to display on the update details page
		*/
		add_filter('plugins_api', array( $this, 'update_details'), 10, 3);
	}

	public function init()
	{
		/**
		*	Add products to licence manager
		*/
		$this->products=apply_filters('wt_iew_add_licence_manager', $this->products);
	}

	/**
	*	Fetch the details of the new update.
	*	This will show in the plugins page as a popup
	*/
	public function update_details($false, $action, $args)
	{		
		if(!isset($args->slug))
		{
			return $false;
		}

		/**
		*	Get all licence info
		*/
		$licence_data=$this->get_licence_data();

		$is_found=false;
		$licence_info=array();
		$product_data=$this->products[$this->main_plugin_slug]; /* main product data as default value */
		
		/**
		*	Loop through the licence info and check the slug is matching on which key
		*/
		foreach ($licence_data as $key => $value)
		{
			if($value['status']=='active')
			{
				$products=explode(",", $value['products']);
				if(in_array($args->slug, $products))
				{
					$licence_info=$value;
					$is_found=true;

					/**
					*	This is the slug of main product which is used to activate the licence. If slug not present then use default product data of main suite product  
					*/
					if(isset($licence_info['product_slug']) && isset($this->products[$licence_info['product_slug']])) 
					{
						$product_data=$this->products[$licence_info['product_slug']];
					}

					break;
				}
			}
		}
		if(!$is_found)
		{
			return $false;
		}


		return $this->get_license_type_obj($licence_info)->update_details($this, $product_data, $licence_info, $false, $action, $args);

	}


	/**
	* 	Check for plugin updates 
	*/
	public function update_check($transient)
	{
		if(empty( $transient->checked ))
		{
			return $transient;
		}

		$home_url=urlencode(home_url());

		/**
		*	Get all licence info
		*/
		$licence_data=$this->get_licence_data();

		$main_product_slug=$this->main_plugin_slug;

		/**
		*	Main product data
		*/
		$main_product_data=$this->products[$main_product_slug];

		/* This is for WC type licenese */
		include_once "classes/class-wt-response-error-messages.php";
		$error_message_obj=new Wt_licence_manager_error_messages($main_product_data['plugin_settings_url'], $main_product_data['product_display_name'], $this->my_account_url);

		
		if(!function_exists('get_plugin_data')) /* this function is required for fetching current plugin version */
		{
		    require_once ABSPATH.'wp-admin/includes/plugin.php';
		}

		$timestamp=time(); //current timestamp

		/**
		*	Taking the last update check time. Always use main product slug
		*/
		$last_check=get_option($main_product_slug.'-last-update-check');
		if($last_check==false) //first time so add a four hour back time.
		{ 
			$last_check=$timestamp-14402;
			update_option($main_product_slug.'-last-update-check', $last_check);
		}

		/**
		* 	Previous check is before 4 hours or Force check
		*/
		if(($timestamp-$last_check)>14400 || (isset($_GET['force-check']) && $_GET['force-check']==1)) 
		{
			foreach ($licence_data as $licence_key => $value)
			{
				if($value['status']=='active')
				{					
					$license_type=$this->get_license_type($value);

					/**
					*	This is the slug of main product which is used to activate the licence. If slug not present then use default product data of main suite product  
					*/
					if(isset($value['product_slug']) && isset($this->products[$value['product_slug']])) 
					{
						$product_slug=$value['product_slug'];
						$product_data=$this->products[$product_slug];
					}else
					{
						$product_slug=$main_product_slug;
						$product_data=$main_product_data;
					}

					if($license_type=='WC')
					{
						$args = array(
							'request'			=>	'pluginupdatecheck',
							'slug'				=>	'',
							'plugin_name'		=>	'',
							'version'			=>	'',
							'product_id'		=>	'',
							'domain'			=>	$home_url,
							'software_version'	=>	'',
							'extra'				=> 	'',
							'wc-api'			=>	'upgrade-api',

							/* product details */
							'slug'				=>	$product_data['product_slug'],
							'plugin_name'		=>	$product_data['product_name'],
							'version'			=>	$product_data['product_version'],
							'product_id'		=>	$product_data['product_id'],
							'software_version'	=>	$product_data['product_version'],
							
							/* licence details */
							'api_key'			=>	$value['key'],
							'activation_email'	=>	$value['email'],
							'instance'			=>	$value['instance_id'],
						);

					}else
					{
						$args = array(
							'edd_action'		=> 	'get_version',
							'url' 				=> 	$home_url,
							
							/* product details */
							'item_id' 			=> 	(isset($product_data['product_edd_id']) ? $product_data['product_edd_id'] : 0),
							'license' 			=> 	$value['key'],
						);
					}


					/* fetch plugin response */
					$response = $this->fetch_plugin_info($args);
										
					
					if(isset($response) && is_object($response) && $response!== false )
					{
						if($license_type=='WC')
						{
							if(!property_exists($response, 'errors'))
							{
								$transient=$this->add_update_availability($transient, $product_slug, $response);
							}else
							{
								/**
								*	Displays an admin error message in the WordPress dashboard
								*/
								$products=explode(",", $value['products']);
								foreach ($products as $key => $product_slug) /* loop through the products associated with the current licence key */
								{
									$error_message_obj->product_display_name=$this->get_display_name($product_slug);
									$error_message_obj->check_response_for_errors($response);
								}
							}

						}else
						{
							$transient=$this->add_update_availability($transient, $product_slug, $response);
						}
					}		
				}
			}

			/**
			*	Update last check time with current time. Always use main product slug
			*/
			update_option($main_product_slug.'-last-update-check', $timestamp);	
		}

		return $transient;
	}

	/**
	*	Add plugin update availability to transient 
	*/
	public function add_update_availability($transient, $plugin_slug, $response)
	{

		$package=$response->package;
		if(is_array($package) || is_object($package)) /* multiple packages available */
		{
			$package=(array) $package;
			foreach($package as $plugin_slug=>$plugin_data)
			{
				$plugin_data=(array) $plugin_data;
				$plugin_base_path="$plugin_slug/$plugin_slug.php";
				if(is_plugin_active($plugin_base_path)) /* checks the plugin is active */
				{
					$current_plugin_data=get_plugin_data(WP_PLUGIN_DIR."/$plugin_base_path");
					$current_version=$current_plugin_data['Version'];
					$new_version=$plugin_data['version'];
					if(version_compare($new_version, $current_version, '>')) /* new version available */
					{
						$obj=new stdClass();
						$obj->slug=$plugin_slug;
						$obj->plugin=$plugin_base_path;
						$obj->new_version=$new_version;
						$obj->url=$response->url;
						$icons = maybe_unserialize($response->icons);
						if(isset($icons['1x'])){
							$obj->icons['default']=$icons['1x'];
						}
						if( 'wt-import-export-for-woo-product' === $plugin_slug || 'wt-import-export-for-woo-product_review' === $plugin_slug ){
							$obj->icons['default']= 'https://www.webtoffee.com/wp-content/uploads/2018/09/product-import-export-128x128.png';
						}
						if( 'wt-import-export-for-woo-order' === $plugin_slug || 'wt-import-export-for-woo-coupon' === $plugin_slug || 'wt-import-export-for-woo-subscription' === $plugin_slug ){
							$obj->icons['default']= 'https://www.webtoffee.com/wp-content/uploads/2018/09/order-import-export-128x128.png';
						}
						if( 'wt-import-export-for-woo-user' === $plugin_slug ){
							$obj->icons['default']= 'https://www.webtoffee.com/wp-content/uploads/2020/10/user-import-export-128x128.png';
						}
						$obj->package=$plugin_data['url'];										
						$transient->response[$plugin_base_path]=$obj;
					}
				}
			}

		}else /* if single item in the licence key */
		{
			$plugin_base_path="$plugin_slug/$plugin_slug.php";
			if(is_plugin_active($plugin_base_path)) /* checks the plugin is active */
			{
				$current_plugin_data=get_plugin_data(WP_PLUGIN_DIR."/$plugin_base_path");
				$current_version=$current_plugin_data['Version'];
				$new_version=$response->new_version;
				if(version_compare($new_version, $current_version, '>')) /* new version available */
				{
					$obj=new stdClass();
					$obj->slug=$plugin_slug;
					$obj->plugin=$plugin_base_path;
					$obj->new_version=$new_version;
					$obj->url=$response->url;
					$obj->package=$response->package;
					$transient->response[$plugin_base_path]=$obj;
				}
			}
		}

		return $transient;
	}

	/**
	*	Fetch plugin info for update check and update info
	*/
	public function fetch_plugin_info($args)
	{
		$request=$this->remote_get($args);

		if(is_wp_error($request) || wp_remote_retrieve_response_code($request)!=200)
		{
			return false;
		}

		if(isset($args['api_key'])) //WC type. In EDD `license` instead of `api_key`
		{
			$response=maybe_unserialize(wp_remote_retrieve_body($request));
		}else
		{
			$response=json_decode(wp_remote_retrieve_body($request));
		}
				
		if(is_object($response))
		{
			return $response;
		}else
		{
			return false;
		}
	}

	/**
	* Main Ajax hook to handle all ajax requests. 
	*/
	public function ajax_main()
	{
		$allowed_actions=array('activate', 'deactivate', 'delete', 'licence_list', 'check_status');
		$action=(isset($_POST['iew_licence_manager_action']) ? sanitize_text_field($_POST['iew_licence_manager_action']) : '');
		$out=array('status'=>true, 'msg'=>'');
		if(!Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID))
		{
			$out['status']=false;

		}else
		{
			if(in_array($action,$allowed_actions))
			{
				if(method_exists($this,$action))
				{
					$out=$this->{$action}($out);
				}
			}
		}
		echo json_encode($out);
		exit();	
	}

	/**
	*	Ajax sub function to check licence status
	*/
	public function check_status($out)
	{
		$licence_data_arr=$this->get_licence_data();
		
		/**
		*	Get product info
		*/
		$main_product_data=$this->products[$this->main_plugin_slug];

		$is_update_needed=false;
		foreach ($licence_data_arr as $key => $licence_data)
		{
			$product_data=$this->get_product_data($licence_data);

			$response=$this->fetch_status($product_data, $licence_data);
			$response_arr=json_decode($response, true);
			
			$new_status=$this->get_license_type_obj($licence_data)->check_status($licence_data, $response_arr);

			/* check update needed */
			if($licence_data['status']!=$new_status)
			{
				$licence_data_arr[$key]['status']=$new_status;
				$is_update_needed=true;
			}
		}
		if($is_update_needed)
		{
			$this->update_licence_data($licence_data_arr);
		}

		$out['status']=true;
		return $out;		
	}

	/**
	*	Fetch licence status
	*/
	public function fetch_status($product_data, $licence_data)
	{
		if($this->get_license_type($licence_data)=='WC')
		{
			$args = array(
				'request' 		=> 'status',
				'email'			=> $licence_data['email'],
				'licence_key'	=> $licence_data['key'], 
				'product_id' 	=> $product_data['product_id'],
				'instance' 		=> $licence_data['instance_id'],
				'platform' 		=> home_url(),
				'wc-api'		=> 'am-software-api', //End point
			);
		}else
		{

			$args = array(
				'edd_action' 	=> 'check_license',
				'license'		=> $licence_data['key'], 
				'item_id' 		=> (isset($product_data['product_edd_id']) ? $product_data['product_edd_id'] : 0),
				'url' 			=> urlencode(home_url()),
			);
		}

		$request=$this->remote_get($args);
		
		$response = wp_remote_retrieve_body($request);

		return $response;
	}

	/**
	*	Ajax sub function to delete licence
	*/
	public function delete($out)
	{
		$out['status']=false;
		$er=0;

		$licence_key=trim(isset($_POST['wt_iew_licence_key']) ? sanitize_text_field($_POST['wt_iew_licence_key']) : '');
		if($licence_key=="")
		{
			$er=1;
			$out['msg']=__('Error !!!', 'wt-import-export-for-woo');
		}

		if($er==0)
		{
			$licence_data=$this->get_licence_data($licence_key);
			if(!$licence_data)
			{
				$er=1;
				$out['msg']=__('Error !!!', 'wt-import-export-for-woo');
			}
		}


		if($er==0)
		{
			$this->remove_licence_data($licence_key);
            $out['status']=true;
			$out['msg']=__("Successfully deleted.", 'wt-import-export-for-woo');
		}

		return $out;
	}

	/**
	*	Ajax sub function to activate licence
	*/
	public function deactivate($out)
	{

		$out['status']=false;
		$er=0;

		$licence_key=trim(isset($_POST['wt_iew_licence_key']) ? sanitize_text_field($_POST['wt_iew_licence_key']) : '');
		if($licence_key=="")
		{
			$er=1;
			$out['msg']=__('Error !!!');
		}

		if($er==0)
		{
			$licence_data=$this->get_licence_data($licence_key);
			if(!$licence_data)
			{
				$er=1;
				$out['msg']=__('Error !!!');
			}
		}

		$product_data=$this->get_product_data($licence_data);

		if($er==0)
		{
			$license_type=$this->get_license_type($licence_data);

			if($license_type=='WC')
			{
				$args=array(
					'request' 		=> 'deactivation',
					'email'			=> $licence_data['email'],
					'licence_key'	=> $licence_data['key'],
					'product_id' 	=> $product_data['product_id'],
					'instance' 		=> $licence_data['instance_id'],
					'platform' 		=> home_url(),
					'wc-api'		=> 'am-software-api', //Endpoint
				);
			}else
			{
				$args=array(
					'edd_action'	=> 'deactivate_license',
					'license'		=> $licence_data['key'],
					//'item_name' 	=> $product_data['product_display_name'], //name in EDD
					'item_id' 		=> (isset($product_data['product_edd_id']) ? $product_data['product_edd_id'] : 0), //ID in EDD
					'url' 			=> urlencode(home_url()),
				);
			}
			$response=$this->remote_get($args);		
			
			if(is_wp_error($response) || wp_remote_retrieve_response_code($response)!=200)
			{
				$out['msg']=__("Request failed, Please try again", 'wt-import-export-for-woo');
			}else
	        {
	        	$response=json_decode(wp_remote_retrieve_body($response), true);
	        	$success=false;
	        	if($license_type=='WC')
				{					
		        	if(!isset($response['error']))
		        	{
		        		$success=true;
		        	}
				}else
				{
		        	if(isset($response['success']) && $response['success']===true)
		        	{
		        		$success=true;
		        	}
		        }

		        if($success)
		        {
		        	$this->remove_licence_data($licence_data['key']);
		            $out['status']=true;
					$out['msg']=__("Successfully deactivated.", 'wt-import-export-for-woo'); 
		        }else
		        {
		        	$out['msg']=__('Error', 'wt-import-export-for-woo');
		        }

	        }
		}

		return $out;
	}

	public function remote_get($args)
	{
		global $wp_version;
		$target_url=esc_url_raw($this->create_api_url($args));

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
		return wp_remote_get($target_url, $def_args);
	}

	/**
	*	Ajax sub function to activate licence
	*/
	public function activate($out)
	{
		global $wp_version;

		$out['status']=false;
		$er=0;
		$licence_product=trim(isset($_POST['wt_iew_licence_product']) ? sanitize_text_field($_POST['wt_iew_licence_product']) : '');
		$licence_key=trim(isset($_POST['wt_iew_licence_key']) ? sanitize_text_field($_POST['wt_iew_licence_key']) : '');
		$licence_email=trim(isset($_POST['wt_iew_licence_email']) ? sanitize_text_field($_POST['wt_iew_licence_email']) : '');

		if($er==0 && $licence_key=="")
		{
			$er=1;
			$out['msg']=__('Please enter license key', 'wt-import-export-for-woo');
		}
		$license_parts = explode('WT', $licence_key);

		if($er==0 && $license_parts[0]=="")
		{			
			$er=1;
			$out['msg']=__('Please enter a valid license key', 'wt-import-export-for-woo');
		}
		if($licence_product=="" && empty($license_parts[1]))
		{
			$er=1;
			$out['msg']=__('Please select a product', 'wt-import-export-for-woo');
		}		
		if( $er==0 && !empty($license_parts[1])){
			$license_plugin_slug = $this->plguin_id_slugs[$license_parts[0]];	
			$pliugin_file_path = WP_PLUGIN_DIR . "/{$license_plugin_slug}/{$license_plugin_slug}.php";
			if($license_plugin_slug==null){
				$er=1;
				$out['msg']=__("Please enter a valid license key", 'wt-import-export-for-woo');
			}else if (!file_exists($pliugin_file_path )){
				$er=1;
				$out['msg']=__("Add-on is not installed on the site. Please install " . $license_plugin_slug . ".zip before license activation.", 'wt-import-export-for-woo');
			}
			else if( !is_plugin_active( "$license_plugin_slug/$license_plugin_slug.php" ) ){
				$plguin_id_slugs_name  =array(
					'wt-import-export-for-woo-user' => 'User Import Export for WooCommerce Add-on',
					'wt-import-export-for-woo-product' => 'Product Import Export for WooCommerce Add-on',
					'wt-import-export-for-woo-order' => 'Order Import Export for WooCommerce Add-on'
				);
				$er=1;
				$out['msg']=__("Add-on is not activated on the site. Please activate " . $plguin_id_slugs_name[$license_plugin_slug] . " before license activation.", 'wt-import-export-for-woo');
			}
		}	
		if($er==0 && $licence_key!="")
		{
			/* check the licence key already applied */
			$licence_data=$this->get_licence_data();
			foreach ($licence_data as $key => $licence_info)
			{
				$product_slug=$this->get_product_slug($licence_info);
				if($product_slug==$licence_product) /* already one licence exists */
				{
					if($licence_info['status']=='active')
					{
						$er=1;
						$out['msg']=__('The chosen plugin already have an active license. Please activate after expiring the current license.', 'wt-import-export-for-woo');
						break;
					}
				}

				/* current licence key matches with another product */
				if($licence_key==$licence_info['key'] && $product_slug!=$licence_product && $licence_info['status']=='active')
				{
					$er=1;
					$out['msg']=__('The given license key was already activated for another product. Please provide another license key.', 'wt-import-export-for-woo');
					break;
				}
			}
		}
		
		if($er==0) /* check the entered license belongs to which type */
		{
			$license_type=$this->get_license_type( array('key'=>$licence_key) );
			if($license_type=='WC')
			{
				if($licence_email=="")
				{
					$er=1;
					$out['msg']=__('Please enter Email', 'wt-import-export-for-woo');
				}
			}
		}

		if($er==0)
		{
			$product_data=$this->products[$licence_product];

			if($license_type=='WC')
			{
				require_once plugin_dir_path(__FILE__).'classes/class-wc-api-manager-passwords.php';	
				$password_management = new API_Manager_Password_Management();

				// Generate a unique installation $instance id
				$instance = $password_management->generate_password(12, false);

				$args = array(
					'email'				=> $licence_email,
					'licence_key'		=> $licence_key,
					'request' 			=> 'activation',
					'product_id' 		=> $product_data['product_id'],
					'instance' 			=> $instance,
					'platform' 			=> home_url(),
					'software_version' 	=> $product_data['product_version'],
					'wc-api'			=> 'am-software-api', //End point
				);

			}else
			{			
				$prod_id = (isset($product_data['product_edd_id']) ? $product_data['product_edd_id'] : 0);
				if(!empty($license_parts[1])){
					$prod_id = $license_parts[0];
				}
				$args = array(
					'edd_action'		=> 'activate_license',
					'license'			=> $licence_key,
					//'item_name' 		=> $product_data['product_display_name'], //name in EDD
					//'item_id' 		=> (isset($product_data['product_edd_id']) ? $product_data['product_edd_id'] : 0), //ID in EDD
					'item_id'			=> $prod_id,
					'url' 				=> urlencode(home_url()),
				);
			}
			$response=$this->remote_get($args);
			// Request failed
			if(is_wp_error($response))
			{
				$out['msg']=$response->get_error_message();
			}
			elseif( wp_remote_retrieve_response_code( $response ) != 200 )
			{
				$out['msg']=__("Request failed, Please try again", 'wt-import-export-for-woo');
			}
	        else
	        {	        	
	        	$response_arr=json_decode($response['body'], true);
		        if($license_type=='WC')
				{
		        	if(!isset($response_arr['error']) && isset($response_arr['activated']) && $response_arr['activated']===true)
		        	{
		        		$licence_data=array(
							'key'			=> $licence_key,
							'email'			=> $licence_email,
							'status'		=> 'active',
							'products'		=> (isset($response_arr['package_info']) ? sanitize_text_field($response_arr['package_info']) : ''), 
							'instance_id'	=> $instance,
							'product_slug'	=> $licence_product,
						);
						$out['status']=true;
		        	}else
		        	{	
		        		$out['msg']=$response_arr['error'];
		        	}

				}else
				{	
		        	if(isset($response_arr['success']) && $response_arr['success']===true) /* success */
		        	{
	        			$licence_data=array(
							'key'			=> $licence_key,
							'email'			=> (isset($response_arr['customer_email']) ? sanitize_text_field($response_arr['customer_email']) : ''), //from EDD
							'status'		=> 'active',
							'products'		=> (isset($response_arr['package_info']) ? sanitize_text_field($response_arr['package_info']) : ''), 
							'instance_id'	=> (isset($response_arr['checksum']) ? sanitize_text_field($response_arr['checksum']) : ''), //from EDD
							'product_slug'	=> (isset($this->plguin_id_slugs[$response_arr['item_id']]) ? $this->plguin_id_slugs[$response_arr['item_id']] : ''), //from EDD,
						);						
						$out['status']=true;	        		
		        	}

		        	if(!$out['status']) /* error */
		        	{	
		        		$out['msg']=$this->process_error_keys( (isset($response_arr['error']) ? $response_arr['error'] : '') );
		        	}

		        }

		        if($out['status']===true) /* success. Save license info */
		        {
		        	$this->add_new_licence_data($licence_data);
		        	$out['msg']=__("Successfully activated.", 'wt-import-export-for-woo');
		        }

	        }

			
		}
		return $out;
	}

	/**
	*	Ajax sub function to get licence list
	*/
	public function licence_list($out)
	{
		$licence_data_arr=$this->get_licence_data(); 
		ob_start();
		include plugin_dir_path(__FILE__).'views/_licence_list.php';
		$out['html']=ob_get_clean();
		return $out;
	}

	/**
	*	Mask licence key
	*/
	public function mask_licence_key($key)
	{
		$total_length=strlen($key);
		$non_mask_length=6; //including both side
		$mask_length=$total_length-$non_mask_length;
		
		if($mask_length>=1) //atleast one character
		{
			$key=substr_replace($key, str_repeat("*", $mask_length), floor($non_mask_length/2), ($total_length-$non_mask_length));
		}else
		{
			$key=str_repeat("*", $total_length); //replace all character
		}
		return $key;		
	}

	/**
	*	Licence tab head
	*/
	public function licence_tabhead($arr)
	{	
		$status=true;
		$licence_data=$this->get_licence_data();
		if(!$licence_data)
		{
			$status=false; //no licence found
		}

		if($status && count($licence_data)!=count($this->products))
		{
			$licenced_product_arr=array();
			foreach($licence_data as $key => $licence_info)
			{
				if(isset($licence_info['products']) && $licence_info['products']!="" && $licence_info['status']=="active")
				{
					/* taking all licenced products */
					$licenced_product_arr=array_merge(explode(",", $licence_info['products']), $licenced_product_arr);
				}				
			}

			$licenced_product_arr=array_unique($licenced_product_arr);

			if(count($licenced_product_arr)<count($this->products)) //licence misisng for some products
			{
				$status=false; 
			}			
		}

		if($status)
	    {
	        $activate_icon=$this->tab_icons['active'];   
	    }else
	    {
	        $activate_icon=$this->tab_icons['inactive'];
	    }
		$arr['wt-licence']=array(__('License', 'wt-import-export-for-woo'),$activate_icon);
		return $arr;
	}


	/**
	*	Licence tab content
	*/
	public function licence_content()
	{
		wp_enqueue_script($this->module_id, plugin_dir_url( __FILE__ ).'assets/js/main.js', array('jquery'), WT_IEW_VERSION);

		$params=array(
	        'ajax_url' => admin_url('admin-ajax.php'),
	        'nonce' => wp_create_nonce(WT_IEW_PLUGIN_ID),
	        'tab_icons'=>$this->tab_icons,
	        'msgs'=>array(
	        	'key_mandatory'=>__('Please enter Licence key', 'wt-import-export-for-woo'),
	        	'email_mandatory'=>__('Please enter Email', 'wt-import-export-for-woo'),
	        	'product_mandatory'=>__('Please select a product', 'wt-import-export-for-woo'),
	        	'please_wait'=>__('Please wait...', 'wt-import-export-for-woo'),
	        	'error'=>__('Error', 'wt-import-export-for-woo'),
	        	'success'=>__('Success', 'wt-import-export-for-woo'),
	        	'unable_to_fetch'=>__('Unable to fetch Licence details', 'wt-import-export-for-woo'),
	        	'no_licence_details'=>__('No Licence details found.', 'wt-import-export-for-woo'),
	        	'sure'=>__('Are you sure?', 'wt-import-export-for-woo'),
	        )
		);
		wp_localize_script($this->module_id, 'wt_iew_licence_params', $params);


		$view_file=plugin_dir_path(__FILE__).'views/licence-settings.php';	
		$params=array(
			'products'=>$this->products
		);
		Wt_Import_Export_For_Woo_Admin::envelope_settings_tabcontent('wt-licence', $view_file, '', $params, 0);
	}

	public function get_status_label($status)
	{
		$color_arr=array(
			'active'=>'#5cb85c',
			'inactive'=>'#ccc',
		);
		$color_css=(isset($color_arr[$status]) ? 'background:'.$color_arr[$status].';' : '');
		return '<span class="wt_iew_badge" style="'.$color_css.'">'.ucfirst($status).'</span>';
	}

	/**
	*	Get plugin display name by plugin slug/package name
	*/
	public function get_display_name($plugin_slug)
	{
		$arr=array(
			'wt-import-export-for-woo'=>'Import Export for WooCommerce',
			'wt-import-export-for-woo-product'=>'Product Import Export for WooCommerce',
			'wt-import-export-for-woo-order'=>'Order Import Export for WooCommerce',
			'wt-import-export-for-woo-user'=>'User Import Export for WooCommerce',
			'wt-import-export-for-woo-subscription'=>'Subscription Import Export for WooCommerce',
			'wt-import-export-for-woo-product_review'=>'Product review Import Export for WooCommerce',
			'wt-import-export-for-woo-coupon'=>'Coupon Import Export for WooCommerce',
		);
		return (isset($arr[$plugin_slug]) ? $arr[$plugin_slug] : $plugin_slug);
	}

	private function create_api_url( $args ) {
		if ( isset( $args[ 'email' ] ) ) {
			$args[ 'email' ] = rawurlencode( $args[ 'email' ] ); //Issue activating license "The email provided is invalid. Activation error" error (IER-329)   
		}
		return add_query_arg( $args, $this->api_url );
	}

	/**
	*	Add new licence info
	*/
	private function add_new_licence_data($new_licence_data)
	{
		$licence_data=$this->get_licence_data();
		$licence_data[$new_licence_data['key']]=$new_licence_data;
		$this->update_licence_data($licence_data);
	}

	private function remove_licence_data($licence_key)
	{
		$licence_data=$this->get_licence_data();
		unset($licence_data[$licence_key]);
		$this->update_licence_data($licence_data);
	}

	private function update_licence_data($licence_data)
	{
		update_option($this->products[$this->main_plugin_slug]['product_id'].'_licence_data', $licence_data);
	}

	private function get_licence_data($key="")
	{
		$licence_data=get_option($this->products[$this->main_plugin_slug]['product_id'].'_licence_data');
		$licence_data=($licence_data && is_array($licence_data) ? $licence_data : array());
		if($key!="")
		{
			if(isset($licence_data[$key]))
			{
				return $licence_data[$key];
			}else
			{
				return false;
			}
		}
		return $licence_data;
	}

	/**
	*	Check the licence type is EDD or WC
	*/
	private function get_license_type_obj($licence_data)
	{
		if($this->get_license_type($licence_data)=='WC')
		{
			return Wt_Iew_Licence_Manager_Wc::get_instance();
		}
		return Wt_Iew_Licence_Manager_Edd::get_instance();
	}

	/**
	*	Check the licence type is EDD or WC
	*/
	private function get_license_type($licence_data)
	{
		$key=$licence_data['key'];
		if(strpos($key, 'wc_order_')===0)
		{
			return 'WC';
		}
		return 'EDD';
	}

	private function process_error_keys($key)
	{
		$msg_arr=array(
			"missing" => __("License doesn't exist", 'wt-import-export-for-woo'),
			"missing_url" => __("URL not provided", 'wt-import-export-for-woo'),
			"license_not_activable" => __("Attempting to activate a bundle's parent license", 'wt-import-export-for-woo'),
			"disabled" => __("License key revoked", 'wt-import-export-for-woo'),
			"no_activations_left" => __("No activations left", 'wt-import-export-for-woo'),
			"expired" => __("License has expired", 'wt-import-export-for-woo'),
			"key_mismatch" => __("License is not valid for this product", 'wt-import-export-for-woo'),
			"invalid_item_id" => __("Invalid Product", 'wt-import-export-for-woo'),
			"item_name_mismatch" => __("License is not valid for this product", 'wt-import-export-for-woo'),
		);
		return (isset($msg_arr[$key]) ? $msg_arr[$key] : __("Error", 'wt-import-export-for-woo'));
	}

	/**
	*	Get product slug with licence data.
	*/
	private function get_product_slug($licence_data)
	{
		/**
		*	This is the slug of main product which is used to activate the licence. If slug not present then use main suite product  slug 
		*/
		if(isset($licence_data['product_slug']) && isset($this->products[$licence_data['product_slug']])) 
		{
			$product_slug=$licence_data['product_slug'];
		}else
		{
			$product_slug=$this->main_plugin_slug;
		}
		return $product_slug;
	}

	/**
	*	Get product data with licence data.
	*/
	private function get_product_data($licence_data)
	{
		/**
		*	This is the slug of main product which is used to activate the licence. If slug not present then use default product data of main suite product  
		*/
		if(isset($licence_data['product_slug']) && isset($this->products[$licence_data['product_slug']])) 
		{
			$product_data=$this->products[$licence_data['product_slug']];
		}else
		{
			$product_data=$this->products[$this->main_plugin_slug];
		}
		return $product_data;
	}
}
new Wt_Iew_Licence_Manager();
