<?php
require __DIR__ . '/vendor/autoload.php';

class Wt_Import_Export_For_Woo_Sftp
{
	private $link = false;

    /**
    *   Test SFTP connection
    *   @param array $profile Profile details
    */
    public function test_sftp($profile, $out)
    {
        if($this->connect($profile['server'], $profile['port']))
        {
            if($this->login($profile['user_name'], $profile['password']))
            {
                $out['msg']=__('Successfully tested.');
                $out['status'] = true;
            }else
            {
                $out['msg']=__('SFTP connection failed.');
            }
        }else
        {
            $out['msg']=__('Failed to establish SFTP connection.');
        }
        return $out;
    }

	public function download($profile, $local_file, $remote_file, $out)
	{
		$out['response'] = false;
		if($this->connect($profile['server'], $profile['port']))
		{
			if($this->login($profile['user_name'], $profile['password']))
			{
                                $file_name_or_path = basename($remote_file);
                                if (strpos($file_name_or_path, ".") !== false) {
                                    $is_folder = false;
                                }else{
                                    $is_folder = true;
                                }

                            if($is_folder){
                            $ext = apply_filters('wt_iew_folder_import_file_extension', 'csv');
                            $file_list = $this->nlist($remote_file, array($ext), false);     

                $temp_file_data = '';

                $file_list = apply_filters('wt_iew_folder_import_fetched_files',$file_list,$this,$remote_file);
                
                $count = 0;
                $first_row_size = 0;
                foreach ($file_list as $file_path) {

                    if ($count == 0) {
                        $temp_file_data .= trim($this->get_contents($remote_file . $file_path));
                        $first_row = $this->get_first_row($temp_file_data);
                        $first_row_size = strlen($first_row);

                    } else {
                        $temp_file_data .= ($this->get_contents($remote_file . $file_path, false, $first_row_size));
                    }
                    $count++;
                }
                        $file_data = $temp_file_data;
                            }else{


                $file_data=$this->get_contents($remote_file);
                            }
                if(!empty($file_data))
                {
                    if(@file_put_contents($local_file, $file_data))
                    {
                        $out['msg']=__('Downloaded successfully.');
                        $out['response'] = true;
                    }else
                    {
                        $out['msg']=__('Unable to create temp file.');
                    }                       
                }else
                {
                    $out['msg']=__('Failed to download file.');
                }
			}else
			{
				$out['msg']=__('SFTP connection failed.');
			}
		}else
		{
			$out['msg']=__('Failed to establish SFTP connection.');
		}
		return $out;
	}
        
        function get_first_row($file) {
        
        $line = preg_split('#\r?\n#', $file, 0)[0];
    
        return $line;
    }

    public function upload($profile, $local_file, $remote_file, $out)
    {
        $out['response'] = false;
        if($this->connect($profile['server'], $profile['port']))
        {
            if($this->login($profile['user_name'], $profile['password']))
            {
                if($this->put_contents($remote_file, $local_file))
                {
                    $out['msg']=__('Uploaded successfully.');
                    $out['response'] = true;
                }else
                {
                    $out['msg']=__('Failed to upload file.');
                }
            }else
            {
                $out['msg']=__('SFTP login failed.');
            }
        }else
        {
            $out['msg']=__('Failed to establish SFTP connection.');
        }
        return $out;
    }
	public function login($username, $password)
	{
		return $this->link->login($username, $password) ? true : false;
	} 
    public function connect($hostname, $port = 22)
    {
        $this->link=new \phpseclib3\Net\SFTP($hostname, $port);
        return ($this->link ? true : false);
    }

    private function put_contents($file, $local_file)
    {
        $ret = $this->link->put($file, $local_file, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE);
        return false !== $ret;
    }

    private function chmod($file, $mode = false, $recursive = false)
    {
        return $mode === false ? false : $this->link->chmod($mode, $file, $recursive);
    }

    private function get_contents($file, $local_file = false, $offset = 0, $length = -1)
    {
        return $this->link->get($file, $local_file, $offset, $length);
    }

    private function size($file) {
        $result = $this->link->stat($file);
        return $result['size'];
    }

    function get_contents_array($file) {
        $lines = preg_split('#(\r\n|\r|\n)#', $this->link->get($file), -1, PREG_SPLIT_DELIM_CAPTURE);
        $newLines = array();
        for ($i = 0; $i < count($lines); $i+= 2)
            $newLines[] = $lines[$i] . $lines[$i + 1];
        return $newLines;
    }
    
    public function delete_file($file){
        return $this->link->delete($file);
    }
    
    function getErrors($when = '') {
        if (!empty($when) && $when == 'last') {
            return $this->link->getLastSFTPError();
        }
        return $this->link->getSFTPErrors();
    }
    
    function getLog(){
        return $this->link->getSFTPLog();
    }
    
    
    public function nlist($dir = '.', $file_types = array(), $recursive = false){                
        $list = $this->link->nlist($dir, $recursive);
        if(empty($file_types)){
            return $list; //return all items if not specifying any file types
        }
        $collection = array();
        foreach ($list as $item => $value) {

            $item_pathinfo = pathinfo($dir . DIRECTORY_SEPARATOR . $value);

            $item_extension = isset($item_pathinfo['extension']) ? $item_pathinfo['extension'] : '';

            if (!empty($file_types) && !in_array($item_extension, $file_types)) {
                continue;
            }

            $collection[$item] = $value;
        }
        return $collection;
    }
    
    
    function rawlist($dir = '.', $file_types = array(), $recursive = false) {
        $list = $this->link->rawlist($dir, $recursive);
        if(empty($file_types)){
            return $list; //return all items if not specifying any file types
        }
        $collection = array();
        foreach ($list as $item => $value) {

            $item_pathinfo = pathinfo($dir . DIRECTORY_SEPARATOR . $item);

            $item_extension = isset($item_pathinfo['extension']) ? $item_pathinfo['extension'] : '';

            if (!empty($file_types) && !in_array($item_extension, $file_types)) {
                continue;
            }

            $collection[$item] = $value;
        }
        return $collection;
    }
}
