<?php

class Xoo_Ml_Service_SMSLane extends Xoo_Ml_Service{

	public $apikey, $senderid, $clientid, $templateid;

	public function __construct(){

		$this->id 			= 'smslane';
		$this->apikey 		= xoo_ml_helper()->get_service_option('smslane-apikey');
		$this->senderid 	= xoo_ml_helper()->get_service_option('smslane-senderid');
		$this->clientid 	= xoo_ml_helper()->get_service_option('smslane-clientid');
		$this->url 			= 'https://api.smslane.com/api/v2/SendSMS';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->apikey, $this->senderid, $this->clientid ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'APIKey' 		=> $this->apikey,
	 			'ClientId' 		=> $this->clientid,
	 			'SenderId' 		=> $this->senderid,
	 			'MobileNumbers' => $phone,
	 			'Message' 		=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_SMSLane();

?>