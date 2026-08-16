<?php

class Xoo_Ml_Service_Msg91 extends Xoo_Ml_Service{

	public $authkey, $senderid, $route, $templateid;

	public function __construct(){

		$this->id 			= 'msg91';
		$this->authkey 		= xoo_ml_helper()->get_service_option('msg91-authkey');
		$this->senderid 	= xoo_ml_helper()->get_service_option('msg91-senderid');
		$this->route 		= xoo_ml_helper()->get_service_option('msg91-route');
		$this->templateid 	= xoo_ml_helper()->get_service_option('msg91-tmpid');
		$this->url 			= 'http://api.msg91.com/api/sendhttp.php?';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->authkey ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'authkey' 		=> $this->authkey,
	 			'sender' 		=> $this->senderid,
	 			'route' 		=> '4',
	 			'DLT_TE_ID' 	=> $this->templateid,
	 			'mobiles' 		=> $phone,
	 			'message' 		=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_Msg91();

?>