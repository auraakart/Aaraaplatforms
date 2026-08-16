<?php
/**
 * XML reading section of the plugin
 *
 * @link           
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}
class Wt_Import_Export_For_Woo_Xmlreader
{

	public $csv_writer=null;
	public $module_obj=null;
	public $form_data=array();
	/**
	*	Taking sample data for mapping screen preparation
	*
	*/
	public function get_sample_data($file, $grouping=false)
	{
		$sample_data=$this->get_xml_data($file, 0, 1);	

		$sample_data=(is_array($sample_data) && isset($sample_data[0]) ? $sample_data[0] : array());

                if($grouping)
		{
			$out=array();
			foreach($sample_data as $key => $val) 
			{
                                                                                    
                            if(strrpos($key, ':')!==false){
	            		$key_arr=explode(":", $key);
	            		if(count($key_arr)>1)
	            		{
	            			$meta_key=$key_arr[0];
	            			if(!isset($out[$meta_key]))
	            			{
	            				$out[$meta_key]=array();
	            			}
	            			$out[$meta_key][$key]=$val;
	            		}else{
                                        $out[$key]=$val;
                                    }
                            }else{
                                    $out[$key]=$val;
                            }
                                                        
			}
			$sample_data=$out;	
		}
                
		return $sample_data;
	}

	/**
	* 	Conver XML file to CSV. 
	*	To avoid multiple looping we are calling CSV reader sub functions here also
	*
	*/
	public function xml_to_csv($xml_file, $csv_file, $offset=0, $batch=30000)
	{
		$out=array(
			'response'=>false,
			'msg'=>'',
			'finished'=>0,
		);

		$csv_writer_file=WT_IEW_PLUGIN_PATH.'admin/classes/class-csvwriter.php';
		if(file_exists($csv_writer_file))
		{
			include_once $csv_writer_file;
			$this->csv_writer=new Wt_Import_Export_For_Woo_Csvwriter($csv_file, $offset);
			
			/* this method will write data to CSV */
			return $this->get_xml_data($xml_file, $offset, $batch, 'xml_to_csv');
		}else
		{
			return $out;
		}
	}
	protected function _process_to_csv_data($val,$node_name = '')
	{
		return $this->csv_writer->format_data($val,$node_name);
	}
	protected function _write_csv_row($row_data, $offset, $is_last_offset)
	{
		$this->csv_writer->write_row($row_data, $offset, $is_last_offset);
	}


	public function get_xml_data($xml_file, $offset=0, $batch=30000, $action='')
	{
		$out=array();
		$node_name=$node_val='';
		$p=0;
		$row_count=0;

		$reader = new XMLReader();
		$reader->open($xml_file);
		while($reader->read()) 
		{
			if($reader->nodeType == XMLReader::ELEMENT) //main parent Eg: orders, products
			{
				$depth_1_name= apply_filters('wt_iew_xml_reader_parent_node',$reader->name);
				while($reader->read()) 
				{
					if($reader->nodeType == XMLReader::ELEMENT) //each nodes Eg: order, product
					{
						$depth_2_name= apply_filters('wt_iew_xml_reader_child_node',$reader->name);
						$temp_arr=array();
						if($offset>0){
							while($reader->read()) //skip the nodes before the offset
								{	
									if($p<$offset) //moves pointer
									{
										$reader->next($depth_2_name); //closing node
										$reader->next($depth_2_name); //opening node
										$p++;
										continue;
									}else
									{
										break;
									}
								}
						}

						while($reader->read()) 
						{
							if($reader->nodeType == XMLReader::ELEMENT)
							{
								$node_name=$reader->name;
								if ($reader->isEmptyElement) {
									$temp_arr[$node_name]="";
									$node_name=$node_val='';
									continue;
								}
							}elseif($reader->nodeType == XMLReader::TEXT || $reader->nodeType == XMLReader::CDATA || $reader->nodeType == XMLReader::WHITESPACE || $reader->nodeType == XMLReader::SIGNIFICANT_WHITESPACE) 
							{
								$node_val=trim($reader->value);

							}elseif($reader->nodeType==XMLReader::END_ELEMENT)
							{
								if($reader->name==$depth_2_name)
								{
									break;

								}else
								{
									if($action=='xml_to_csv')
									{
										$node_val=$this->_process_to_csv_data($node_val,$node_name);
									}else
									{
										$node_val=sanitize_text_field($node_val);
									}
									$temp_arr[$node_name]=$node_val;
									$node_name=$node_val='';
								}
							}
						}
						if($action=='xml_to_csv')
						{
							$this->_write_csv_row($temp_arr, ($offset+$row_count), false);
						}
						elseif($action=='get_data_as_batch')
						{
							$out[]=$this->_process_column_val($temp_arr);
						}
						else
						{
							$out[]=$temp_arr;
						}

						$row_count++;
						if($row_count==$batch)
						{
							if($action=='xml_to_csv')
							{
								/* just close the file pointer */
								$this->_write_csv_row(array(), $offset, true);
							}
							break 2;
						}

					}elseif($reader->nodeType==XMLReader::END_ELEMENT && $reader->name==$depth_1_name)
					{
						break;
					}
				}
			}
		}
		if($action=='xml_to_csv')
		{
			$finished=0;
			if(isset($depth_2_name) && $depth_2_name!="")
			{
				if(!$reader->next($depth_2_name))
				{
					$finished=1;
				}
			}
			$new_offset=$offset+$batch;
			$out=array('response'=>true, 'msg'=>'', 'rows_processed'=>$row_count, 'finished'=>$finished, 'new_offset'=>$new_offset);
		}
		$reader->close();
		return $out;
	}

	/**
	*	Get data from XML as batch.
	*	This method is not using. But keeping here for a backup
	*/
	public function get_data_as_batch($file, $offset, $batch_count, $module_obj, $form_data)
	{
		$out=array(
			'response'=>false,
			'offset'=>$offset,
			'data_arr'=>array(),
		);
		
		$this->module_obj=$module_obj;
		$this->form_data=$form_data;

		$out['response']=true;
		$out['data_arr']=$this->get_xml_data($file, $offset, $batch_count, 'get_data_as_batch');

		return $out;
	}

	protected function _process_column_val($data_row)
	{
		return $this->module_obj->process_column_val($data_row, $this->form_data);
	}
}