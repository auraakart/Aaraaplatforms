<?php

class Xoo_Ml_Service_TextLocal extends Xoo_Ml_Service{

	public $apikey, $senderid;

	public function __construct(){

		$this->id 			= 'textlocal';
		$this->apikey 		= xoo_ml_helper()->get_service_option('txtlocal-apikey');
		$this->senderid 	= xoo_ml_helper()->get_service_option('txtlocal-sender');
		$this->test 		= xoo_ml_helper()->get_service_option('txtlocal-test') === "yes";
		$this->url 			= 'https://api.textlocal.in/send/';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->apikey ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'apiKey' 		=> $this->apikey,
	 			'sender' 		=> $this->senderid,
	 			'test' 			=> $this->test,
	 			'numbers' 		=> $phone,
	 			'message' 		=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_TextLocal();

?>