<?php
/**
 * FTP adapter section. This adapter hook the FTP profile
 *
 * @link
 *
 * @package  Wt_Import_Export_For_Woo
 */
if (!defined('ABSPATH')) {
    exit;
}
class Wt_Import_Export_For_Woo_FtpAdapter extends Wt_Import_Export_For_Woo_RemoteAdapter
{
	public function __construct()
	{
		$this->id='ftp';
		$this->title=__('FTP');
	}

	/**
	*   Retrive FTP profile id from formdata
	*/
	private function get_ftp_profile_form_id($form_data)
	{
		return (isset($form_data['wt_iew_ftp_profile']) ? absint($form_data['wt_iew_ftp_profile']) : 0);
	}

	/**
	*   Retrive FTP server path from formadata/FTP profile
	*/
	private function prepare_remote_file($file_name, $form_data, $ftp_profile, $action)
	{
		$file_path = (isset($form_data['wt_iew_'.$action.'_path']) ? trim(Wt_Iew_Sh_Pro::sanitize_item($form_data['wt_iew_'.$action.'_path'])) : '');
		$file_path = ($file_path=="" ? $ftp_profile[$action.'_path'] : $file_path);

		return (substr($file_path, -1) != '/') ? ($file_path."/".basename($file_name)) : ($file_path.basename($file_name));
	}

	/**
	*	Test FTP connection. Via profile ID or Profile details
	*
	*/
	public function test_ftp($profile_id, $ftp_profile=array())
	{
		$out=array(
			'status'=>false,
			'msg'=>__('Error'),
		);
		$profile_id=absint($profile_id);
		if($profile_id>0) /* check an existing profile */
		{
			$ftp_profile=Wt_Import_Export_For_Woo_Ftp::get_ftp_data_by_id($profile_id);
			if(!$ftp_profile) //no FTP profile found so return false
			{
				$out['msg']=__('FTP profile not found.');
				return $out;
			}
		}

		if(isset($ftp_profile['is_sftp']) && $ftp_profile['is_sftp'] == 1) /* sftp */
		{
			include_once "class-sftp.php";
			$sftp=new Wt_Import_Export_For_Woo_Sftp();
			$out=$sftp->test_sftp($ftp_profile, $out);
			return $out;
		}else
		{
	        $ftp_conn = $this->connect_ftp($ftp_profile);
                if($ftp_conn == 'implicit_ftp_connection'){
                    $out['msg']=__('Successfully tested.');
                    $out['status'] = true;
					return $out;
                }elseif($ftp_conn && $ftp_conn != 'implicit_ftp_connection') /* successfully connected */
	        {
	        	$login = @ftp_login($ftp_conn, $ftp_profile['user_name'], $ftp_profile['password']);
	        	if($login) /* successfully logged in */
	        	{
	        		if($ftp_profile['passive_mode'] == 1)
	        		{
	        			if(!@ftp_pasv($ftp_conn, true)) //failed to enable passive mode
	        			{
	        				$out['msg']=__('Failed to enable passive mode.');
							@ftp_close($ftp_conn);
							return $out;
	        			}else
	        			{
	        				$out['msg']=__('Successfully tested.');
                			$out['status'] = true;
	        			}
	        		}else
	        		{
	        			$out['msg']=__('Success.');
                		$out['status'] = true;
	        		}
	        	}else
	        	{
	        		$out['msg']=__('Connected to host but could not login. Server UserID or Password may be wrong or try again with/without FTPS.');
	        	}
	        }else
	        {
	        	$out['msg']=__('Failed to establish FTP connection. Server host/IP or port specified may be wrong.');
				return $out;
	        }
	        @ftp_close($ftp_conn);
	        return $out;
		}

	}

	public function download($form_data, $out, $import_obj)
	{
		$out['response'] = false;

		/* checking file name */
		$remote_file_name=isset($form_data['wt_iew_import_file']) ? trim(Wt_Iew_Sh_Pro::sanitize_item($form_data['wt_iew_import_file'])) : '';
		if($remote_file_name=='')
		{
			//$out['msg']=__('File not found.');
			//return $out;
                                $ext = apply_filters('wt_iew_folder_import_file_extension', 'csv');
		}else{
                    		$ext_arr=explode('.', $remote_file_name);
                                $ext=strtolower(end($ext_arr));
                }

		/* checking file extension */

		if(!isset($import_obj->allowed_import_file_type[$ext])) /* file type is in allowed list */
		{
			$out['msg']=__('File type not allowed.');
			return $out;
		}

		$file_name=$import_obj->get_temp_file_name($ext);
		$file_path=$import_obj->get_file_path($file_name);
		if(!$file_path)
		{
			$out['msg']=__('Unable to create temp directory.');
			return $out;
		}

		/* retriving profile id from post data */
		$profile_id=$this->get_ftp_profile_form_id($form_data);
		if($profile_id==0) //no FTP profile found so return false
		{
			$out['msg']=__('FTP profile not found.');
			return $out;
		}

		$ftp_profile=Wt_Import_Export_For_Woo_Ftp::get_ftp_data_by_id($profile_id);
		if(!$ftp_profile) //no FTP profile found so return false
		{
			$out['msg']=__('FTP profile not found.');
			return $out;
		}

		$out['file_name']=$file_name;

		if($ftp_profile['is_sftp'] == 1) /* sftp */
		{
			//handle sftp download
			include_once "class-sftp.php";
			$sftp=new Wt_Import_Export_For_Woo_Sftp();

			/* preparing remote file path */
                        $remote_file=$this->prepare_remote_file($remote_file_name, $form_data, $ftp_profile, 'import');
			$out=$sftp->download($ftp_profile, $file_path, $remote_file, $out);

			return $out;
		}else
		{
	        $ftp_conn = $this->connect_ftp($ftp_profile);
                if($ftp_conn == 'implicit_ftp_connection'){

                   $download_status = $this->wt_implicit_ftp_file_download($remote_file_name,$ftp_profile['server'],$ftp_profile['user_name'],$ftp_profile['password'], $file_path);
                   if($download_status){
                      $out['msg']=__('Downloaded successfully.');
	        	$out['response'] = true;
                   }else{
                       $out['msg']=__('Failed to download file.');
                   }
                }elseif($ftp_conn != 'implicit_ftp_connection') /* successfully connected */
	        {
	        	$login = @ftp_login($ftp_conn, $ftp_profile['user_name'], $ftp_profile['password']);

            do_action('wt_ier_ftp_set_option', $ftp_conn);

	        	if($login) /* successfully logged in */
	        	{
	        		if($ftp_profile['passive_mode'] == 1)
	        		{
	        			if(!@ftp_pasv($ftp_conn, true)) //failed to enable passive mode
	        			{
	        				$out['msg']=__('Failed to enable passive mode.');
							@ftp_close($ftp_conn);
							return $out;
	        			}
	        		}

	        		/* preparing remote file path */
	        		$remote_file=$this->prepare_remote_file($remote_file_name, $form_data, $ftp_profile, 'import');

                                $file_name_or_path = basename($remote_file);
                                if (strpos($file_name_or_path, ".") !== false) {
                                    $is_folder = false;
                                }else{
                                    $is_folder = true;
                                }

                                if ($is_folder) {

                                //ftp_pasv($ftp_conn, true); //make connection to passive mode
                                putenv('TMPDIR=/tmp/'); /* before connection */

                                $server_csv_files = @ftp_nlist($ftp_conn, "-t $remote_file" );

                                $server_csv_files = apply_filters('wt_iew_folder_import_fetched_files',$server_csv_files,$ftp_conn,$remote_file);

                                if ($server_csv_files) {
                                    $f_count = 0;
                                    $first_row_size = 0;
                                    $buffer_data = '';
									$allowed_ext = apply_filters('wt_iew_folder_import_extensions', 'csv' );

                                    foreach ($server_csv_files as $key => $server_file) {

                                        if ( substr($server_file, -3) !== $allowed_ext ) {
                                            unset($server_csv_files[$key]);
                                            continue;
                                        }

                                        ob_start();
                                        if($f_count == 0){
                                            $result = @ftp_get($ftp_conn, "php://output", $server_file, FTP_BINARY);
                                            $buffer_data = ob_get_contents();
                                            $first_row = $this->get_first_row($buffer_data);
                                            $first_row_size = strlen($first_row);
                                        }else{
                                        $result = @ftp_get($ftp_conn, "php://output", $server_file, FTP_BINARY, $first_row_size);
                                        $buffer_data.= ob_get_contents();
                                        }
                                        ob_end_clean();

                                        $f_count++;
                                    }
                                    @file_put_contents($file_path, $buffer_data);
                                    $out['msg']=__('Downloaded successfully.');
                                    $out['response'] = true;
                                }else{
                                    $out['response'] = false;
                                    $out['msg'] = __('Could not get files from the specified path');
                                }
                            }else{


                    /* downloading file from FTP server */
	        		if(!@ftp_get($ftp_conn, $file_path, $remote_file, FTP_BINARY))
	        		{
	        			$out['msg']=__('Failed to download file.');
	        		}else
	        		{
	        			$out['msg']=__('Downloaded successfully.');
	        			$out['response'] = true;
	        		}
                            }
	        	}else
	        	{
	        		$out['msg']=__('FTP login failed.');
	        	}
	        }else
	        {
	        	$out['msg']=__('Failed to establish FTP connection.');
				return $out;
	        }
	        @ftp_close($ftp_conn);
	        return $out;
		}
	}

                function get_first_row($file) {

        $line = preg_split('#\r?\n#', $file, 0)[0];

        return $line;
    }
	public function connect_ftp($ftp_profile)
	{
		if($ftp_profile['ftps'] == 1) /* use ftps */
		{
        	$ftp_conn = @ftp_ssl_connect($ftp_profile['server'], $ftp_profile['port']);
                }else
                {
                    $ftp_conn = @ftp_connect($ftp_profile['server'], $ftp_profile['port']);
                }
                if(empty($ftp_conn)){
                     $curlhandle = curl_init();
                    curl_reset($curlhandle);
                    curl_setopt($curlhandle, CURLOPT_URL, 'ftps://' . $ftp_profile['server'] . '/');
                    curl_setopt($curlhandle, CURLOPT_USERPWD, $ftp_profile['user_name'] . ':' . $ftp_profile['password']);
                    curl_setopt($curlhandle, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($curlhandle, CURLOPT_SSL_VERIFYHOST, FALSE);
                    curl_setopt($curlhandle, CURLOPT_FTP_SSL, CURLFTPSSL_TRY);
                    curl_setopt($curlhandle, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
                    curl_setopt($curlhandle, CURLOPT_UPLOAD, 0);
                    curl_setopt($curlhandle, CURLOPT_FTPLISTONLY, 1);
                    curl_setopt($curlhandle, CURLOPT_RETURNTRANSFER, 1);
                    $result = curl_exec($curlhandle);
                    if (curl_error($curlhandle)) {
                        $ftp_conn = '';
                    } else {
                        $ftp_conn = 'implicit_ftp_connection';
                    }
                }
            return $ftp_conn;
	}

	/**
	*	@param $local_file string local file path
	*	@param $remote_file_name string remote file name
	*	@param $form_data array formdata of step that holds FTP related form fields
	*
	*/
	public function upload($local_file, $remote_file_name, $form_data, $out)
	{
		/* retriving profile id from post data */
		$profile_id=$this->get_ftp_profile_form_id($form_data);

		$out['response'] = false;

		if($profile_id==0) //no FTP profile found so return false
		{
			$out['msg']=__('FTP profile not found.');
			return $out;
		}
		$ftp_profile=Wt_Import_Export_For_Woo_Ftp::get_ftp_data_by_id($profile_id);
		if(!$ftp_profile) //no FTP profile found so return false
		{
			$out['msg']=__('FTP profile not found.');
			return $out;
		}

		if($ftp_profile['is_sftp'] == 1)/* sftp */
		{
			//handle sftp upload
			include_once "class-sftp.php";
			$sftp=new Wt_Import_Export_For_Woo_Sftp();

			/* preparing remote file path */
	        $remote_file=$this->prepare_remote_file($remote_file_name, $form_data, $ftp_profile, 'export');
			$out=$sftp->upload($ftp_profile, $local_file, $remote_file, $out);

			return $out;
		}else
		{
			$ftp_conn = $this->connect_ftp($ftp_profile);

                if($ftp_conn == 'implicit_ftp_connection'){

                   $remote_file_name = $this->prepare_remote_file($remote_file_name, $form_data, $ftp_profile, 'export');
                   $upload_status = $this->wt_implicit_ftp_file_upload($local_file,$remote_file_name,$ftp_profile['server'],$ftp_profile['user_name'],$ftp_profile['password']);

                   if($upload_status){
                       $out['msg']=__('Uploaded successfully.');
	               $out['response'] = true;
                   }else{
                       $out['msg']=__('Failed to upload file.');
                   }
				   return $out;
                }elseif($ftp_conn != 'implicit_ftp_connection') /* successfully connected */
	        {
	        	$login = @ftp_login($ftp_conn, $ftp_profile['user_name'], $ftp_profile['password']);

            do_action('wt_ier_ftp_set_option', $ftp_conn);
            
	        	if($login) /* successfully logged in */
	        	{
	        		if($ftp_profile['passive_mode'] == 1)
	        		{
	        			if(!@ftp_pasv($ftp_conn, true)) //failed to enable passive mode
	        			{
	        				$out['msg']=__('Failed to enable passive mode.');
							ftp_close($ftp_conn);
							return $out;
	        			}
	        		}

	        		/* preparing remote file path */
	        		$remote_file=$this->prepare_remote_file($remote_file_name, $form_data, $ftp_profile, 'export');

	        		/* uploading file to FTP server */
	        		if(!@ftp_put($ftp_conn, $remote_file, $local_file, FTP_ASCII))
	        		{
	        			$out['msg']=__('Failed to upload file.');
	        		}else
	        		{
	        			$out['msg']=__('Uploaded successfully.');
	        			$out['response'] = true;
	        		}
	        	}else
	        	{
	        		$out['msg']=__('FTP login failed.');
	        	}
	        }else
	        {
	        	$out['msg']=__('Failed to establish FTP connection.');
	        }
	        @ftp_close($ftp_conn);
	        return $out;
		}
	}
	public function delete()
	{

	}

        private function wt_implicit_ftp_file_upload($local, $remote, $server, $username, $password) {

        if ($fp = fopen($local, 'r')) {
            $ftp_server = 'ftps://' . $server . '/' . $remote;
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $ftp_server);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_FTP_SSL, CURLFTPSSL_TRY);
            curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
            curl_setopt($ch, CURLOPT_UPLOAD, 1);
            curl_setopt($ch, CURLOPT_INFILE, $fp);

            curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            return !$err;
        }
        return false;
    }

      private function wt_implicit_ftp_file_download($remote, $server, $username, $password,  $local = null) {
        if ($local === null) {
            $local = tempnam('/tmp', 'implicit_ftp');
        }

        if ($fp = fopen($local, 'w')) {
            $ftp_server = 'ftps://' . $server . '/' . ltrim($remote,"/") ;
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $ftp_server);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_FTP_SSL, CURLFTPSSL_TRY);
            curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
            curl_setopt($ch, CURLOPT_UPLOAD, 0);
            curl_setopt($ch, CURLOPT_FILE, $fp);

            curl_exec($ch);

            if (curl_error($ch)) {
                curl_close($ch);
                return false;
            } else {
                curl_close($ch);
                return $local;
            }
        }
        return false;
    }
}
return new Wt_Import_Export_For_Woo_FtpAdapter();
