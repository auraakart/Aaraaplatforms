<?php

class Xoo_Ml_Service_SMSAlert extends Xoo_Ml_Service{

	public $apikey, $senderid;

	public function __construct(){

		$this->id 			= 'smsalert';
		$this->apikey 		= xoo_ml_helper()->get_service_option('smsalert-apikey');
		$this->senderid 	= xoo_ml_helper()->get_service_option('smsalert-senderid');
		$this->url 			= 'https://www.smsalert.co.in/api/push.json';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->apikey, $this->senderid ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'apikey' 		=> $this->apikey,
	 			'sender' 		=> $this->senderid,
	 			'mobileno' 		=> $phone,
	 			'text' 			=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_SMSAlert();

?>