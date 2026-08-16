<?php

class Xoo_Ml_Service_Semaphore extends Xoo_Ml_Service{

	public $apikey, $senderid;

	public function __construct(){

		$this->id 			= 'semaphore';
		$this->apikey 		= xoo_ml_helper()->get_service_option('semaphore-apikey');
		$this->senderid 	= xoo_ml_helper()->get_service_option('semaphore-senderid');
		$this->url 			= 'https://api.semaphore.co/api/v4/otp';
			
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
	 			'sendername' 	=> $this->senderid,
	 			'number' 		=> $phone,
	 			'message' 		=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_Semaphore();

?>