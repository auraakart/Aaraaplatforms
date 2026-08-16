<?php

/**
*	EDD specific methods
*
*/


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wt_Iew_Licence_Manager_Edd
{

	private static $instance;

	public static function get_instance()
    {
        if(!self::$instance)
        {
            self::$instance=new Wt_Iew_Licence_Manager_Edd();
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
			'edd_action'		=> 	'get_version',
			'item_id'			=>	(isset($product_data['product_edd_id']) ? $product_data['product_edd_id'] : 0),
			'license'			=>	$licence_data['key'],
			'url' 				=> 	urlencode(home_url()),
		);
		$response = $license_manager->fetch_plugin_info($url_args);		

		if(isset($response) && is_object($response) && $response!==false)
		{
			if(!property_exists($response, 'errors')) /* no errors */
			{
				$plugin_slug=$args->slug;
				$response->name=$license_manager->get_display_name($plugin_slug);
				
				if(isset($response->download_link))
				{
					$response->version=isset($response->new_version) ? $response->new_version : 0;
					$response->slug=$plugin_slug;
					$response->download_link=isset($response->download_link) ? $response->download_link : '';
					$response->sections=isset($response->sections) ? maybe_unserialize($response->sections) : array();				
					$response->banners=isset($response->banners) ? maybe_unserialize($response->banners) : array();				
					$response->icons=isset($response->icons) ? maybe_unserialize($response->icons) : array();				
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

		if(isset($response_arr['success']))
		{
			if($response_arr['success']===true)
			{
				if(isset($response_arr['license']) && $response_arr['license']=='valid')
				{
					$new_status='active';
				}else
				{
					$new_status='inactive';
				}
			}else
			{
				if(isset($response_arr['license']) && $response_arr['license']!="valid")
				{
					$new_status='inactive';
				}
			}
		}
		return $new_status;
	}
}