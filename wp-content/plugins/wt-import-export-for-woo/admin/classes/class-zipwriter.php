<?php
/**
 * Zip writing section of the plugin
 *
 * @link       
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}
class Wt_Import_Export_For_Woo_Zipwriter
{

	/**
	* 	Create Zip 
	*
	*/
	public static function write_to_file($file_path, $file_arr, $offset)
	{	
		if(is_array($file_arr))
		{	
			$zip=new ZipArchive();
			if($offset==0) //offset is zero then create or overwrite the file(if exists)
	        {      	
	            $zip->open($file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	        }else
	        {
	            $zip->open($file_path);
	        } 
	        
			foreach($file_arr as $file_url)
			{
				$local_file_path=Wt_Iew_IE_Helper::_get_local_file_path($file_url);				
				if($local_file_path) /* local file exists */
				{
					$added=$zip->addFile($local_file_path, basename($local_file_path));					
				}
			}
			$zip->close();
		}
	}
}