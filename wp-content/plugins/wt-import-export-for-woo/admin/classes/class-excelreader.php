<?php
/**
 * CSV reading section of the plugin
 *
 * @link       
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__. '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class Wt_Import_Export_For_Woo_Excelreader
{
	public $delimiter=',';
	public $fgetcsv_esc_check=0;
        public $csv_escape = '"';
        public function __construct($delimiter=',')
	{
		$this->delimiter=$delimiter;
		$this->delimiter=($this->delimiter=='tab' ?  "\t" : $this->delimiter);
        $this->enclosure= apply_filters('wt_csv_reader_enclosure', '"');
        $this->csv_escape= apply_filters('wt_csv_reader_csv_escape', '"');
		
		/* version 5.3.0 onwards 5th escaping argument introduced in `fgetcsv` function */
		$this->fgetcsv_esc_check = (version_compare(PHP_VERSION, '5.3.0') >= 0);
	}

	/**
	*	Taking sample data for mapping screen preparation
	*	This function skip empty rows and take first two non empty rows
	*/
	public function get_sample_data($file, $grouping=false)
	{
	    $use_mb = function_exists('mb_detect_encoding');
            // Set locale
        $enc = ($use_mb) ? mb_detect_encoding( $file, 'UTF-8, ISO-8859-1', true ) : false;
	    if($enc)
		{
		    setlocale(LC_ALL, 'en_US.'.$enc);
		}
		@ini_set('auto_detect_line_endings', true);

		$sample_data_key=array();
		$sample_data_val=array();
		$sample_data=array();
		$arr_file = explode('.',$file);
        $extension = end($arr_file);
		if('xls' === $extension) {     
			$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
		}else
		{
		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
		}
		$reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
		$sheet = $spreadsheet->getSheet(0);
		$data_rows = $sheet->toArray();
		if($spreadsheet!== false) 
		{
			$row_count=0;
			foreach ($data_rows as $row)
	    	{
	    		if(count(array_filter($row))==0)
	    		{
	    			continue;
	    		}else
	    		{
	    			$row_count++;
	    		}

	    		if($row_count==1) //taking heading row
	    		{
	    			$sample_data_key=$row;
	    		}else //taking data row
	    		{
	    			$sample_data_val=$row;
	    			break; //only single data row needed
	    		}
	    	}

	    	foreach($sample_data_key as $k => $key) 
	        {
	            if(!$key)
	            {
	                continue;
	            }

	            $val=(isset($sample_data_val[$k]) ? $this->format_data_from_csv($sample_data_val[$k], $enc) : '');
	            
	            /* removing BOM like non characters */
                    $wt_remove_bom = apply_filters('wt_import_csv_parser_keep_bom', true);
                    if ($wt_remove_bom) {
                        $key = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $key);
                    } else {
                        $key = wt_removeBomUtf8($key);
                    }
                    if($grouping)
				{
					if(strrpos($key, ':')!==false)
	            	{
	            		$key_arr=explode(":", $key);
	            		if(count($key_arr)>1)
	            		{
	            			$meta_key=$key_arr[0];
	            			if(!isset($sample_data[$meta_key]))
	            			{
	            				$sample_data[$meta_key]=array();
	            			}
	            			$sample_data[$meta_key][$key]=$val;
	            		}else
						{
							$sample_data[$key]=$val;
						}
					}else
					{
						$sample_data[$key]=$val;
					}
				}else
				{
					$sample_data[$key]=$val;
				}
	    	}
  	
		}

		return $sample_data;
	}

	/**
	*	Get data from CSV as batch
	*/
	public function get_data_as_batch($file, $offset, $batch_count, $module_obj, $form_data)
	{
                $use_mb = function_exists('mb_detect_encoding');
                // Set locale
		$enc = ($use_mb) ? mb_detect_encoding( $file, 'UTF-8, ISO-8859-1', true ) : false;
		if($enc)
		{
			setlocale( LC_ALL, 'en_US.' . $enc );
		}
		@ini_set('auto_detect_line_endings', true);

		$out=array(
			'response'=>false,
			'offset'=>$offset,
			'data_arr'=>array(),
		);

		$arr_file  = explode('.',$file);
        $extension = end($arr_file);
		if('xls' === $extension) {     
			$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
		} else 
		{    
		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
		}
		$reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
		$sheet = $spreadsheet->getSheet(0);
		$data_rows = $sheet->toArray();
		if($spreadsheet!== false) 
		{
			/**
			*	taking head
			*/
			$head_arr=array();
			foreach ($data_rows as $row)
	    	{
	    		if(count(array_filter($row))!=0) /* first non empty array */
	    		{
	    			$head_arr=$row;    			
	    			break;
	    		}
	    	}

	    	$empty_head_columns=array();
	    	foreach($head_arr as $head_key=>$head_val)
	    	{
	    		if(trim($head_val)=='')
	    		{
	    			$empty_head_columns[]=$head_key;
	    			unset($head_arr[$head_key]);
	    		}else
	    		{
	    			/* removing BOM like non characters */
                                $wt_remove_bom = apply_filters('wt_import_csv_parser_keep_bom', true);
                                if ($wt_remove_bom) {
                                    $head_arr[$head_key] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $head_val);
                                } else {
                                    $head_arr[$head_key] = wt_removeBomUtf8($head_val);
                                }                            

	    		}
	    	}

	    	/* moving the pointer to corresponding batch. If not first batch */
	    	if($offset!=0)
	    	{
				$data_rows = array_slice($data_rows, $offset, $batch_count);
	    	}
	    	if($offset == 0){
				unset($data_rows[0]);
			}
	    	$out_arr=array();

	    	$row_count=0;
	    	/* taking data */
	    	foreach ($data_rows as $row) 
	    	{
	    		
	    		/* 
	    		*	Skipping empty rows
	    		*/
	    		if(count(array_filter($row))==0)
	    		{
	    			continue;
	    		}

	    		/* 
	    		*	Remove values of empty head 
	    		*/
	    		foreach($empty_head_columns as $key)
	    		{
	    			unset($row[$key]);
	    		}

	    		/* 
	    		* 	Creating associative array with heading and data 
	    		*/
	    		$row_column_count=count($row);
	    		$head_column_count=count($head_arr);
	    		if($row_column_count<$head_column_count)
	    		{
	    			$empty_row=array_fill($row_column_count, ($head_column_count-$row_column_count), '');
					$row=array_merge($row, $empty_row);
					$empty_row=null;
					unset($empty_row);
	    		}
	    		elseif($row_column_count>$head_column_count)
	    		{
					$row = array_slice($row, 0, $head_column_count); //IER-209
					//continue;
	    		}
	    		
	    		/* clearing temp variables */
	    		$row_column_count=$head_column_count=null;
	    		unset($row_column_count, $head_column_count);

				$head_arr = array_map('trim', $head_arr); //WUWCIEP-132
	    		/* preparing associative array */
	    		$data_row=array_combine($head_arr, $row);	    		

	    		$out_arr[]=$module_obj->process_column_val($data_row, $form_data);
	    		//$out_arr[]=$data_row;

	    		unset($data_row);
				$offset++;
	    		$row_count++;
	    		if($row_count==$batch_count)
	    		{
	    			break;
	    		}
	    	}

	    	$out=array(
				'response'=>true,
				'offset'=>$offset,
				'rows_processed'=>$row_count,
				'data_arr'=>$out_arr,
			);
 
	    	$head_arr=$form_data=$row=$out_arr=null;
	    	unset($head_arr, $form_data, $row, $out_arr);
		}

		return $out;
	}

	protected function format_data_from_csv($data, $enc) 
	{
		//return sanitize_text_field(( $enc == 'UTF-8' ) ? trim($data) : utf8_encode(trim($data)));  sanitize_text_field stripping html content
	    return (( $enc == 'UTF-8' ) ? trim($data) : utf8_encode(trim($data)));
	}
	public function get_file_row_count($file_path){
		
		$arr_file = explode('.',$file_path);
        $extension = end($arr_file);
		if('xls' === $extension) {     
			$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
		} else	
		{
		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
		}
        $spreadsheet = $reader->load($file_path);
		$highest_row = $spreadsheet->getActiveSheet()->getHighestDataRow();
		return $highest_row;

	}
}
