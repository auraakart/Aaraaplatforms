<?php

/**
*	WC licence manager specific methods
*
*/


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wt_Iew_Licence_Manager_Wc
{

	private static $instance;

	public static function get_instance()
    {
        if(!self::$instance)
        {
            self::$instance=new Wt_Iew_Licence_Manager_Wc();
        }
        return self::$instance;
    }

	/**
	*	Fetch the details of the new update.
	*	This will show in the plugins page as a popup
	*/
	public function update_details($license_manager, $product_data, $licence_data, $false, $action, $args)
	{
		$url_args = array(
			'request'			=> 'plugininformation',
			'plugin_name'		=>	$product_data['product_name'],
			'version'			=>	$product_data['product_version'],
			'product_id'		=>	$product_data['product_id'],
			'api_key'			=>	$licence_data['key'],
			'activation_email'	=>	$licence_data['email'],
			'instance'			=>	$licence_data['instance_id'],
			'domain'			=>	home_url(),
			'software_version'	=>	$product_data['product_version'],
			'extra'				=> 	'',
			'wc-api'			=>	'upgrade-api',
		);
		$response = $license_manager->fetch_plugin_info($url_args);		

		if(isset($response) && is_object($response) && $response!==false)
		{
			if(!property_exists($response, 'errors')) /* no errors */
			{
				$plugin_slug=$args->slug;
				$response->name=$license_manager->get_display_name($plugin_slug);
				if(isset($response->package) && is_array($response->package) && isset($response->package[$plugin_slug]))
				{
					$response->version=$response->package[$plugin_slug]['version'];
					$response->slug=$plugin_slug;
					$response->download_link=$response->package[$plugin_slug]['url'];
				}
				return $response;
			}
		}
		return $false;
	}

	/**
	*	Check licence status
	*/
	public function check_status($licence_data, $response_arr)
	{
		$new_status=$licence_data['status'];
				
		/* case of plugin acivated once */
		if(isset($response_arr['status_check']))
		{
			$new_status=$response_arr['status_check'];
		}
		elseif(isset($response_arr['activated']))
		{
			$new_status=$response_arr['activated'];
		}

		/* status is boolean convert it to string */
		if(is_bool($new_status))
		{
			$new_status=($new_status===false ? 'inactive' : 'active');
		}

		return $new_status;
	}

}