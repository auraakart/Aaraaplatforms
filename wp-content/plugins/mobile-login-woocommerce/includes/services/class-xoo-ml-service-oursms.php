<?php

class Xoo_Ml_Service_OurSMS extends Xoo_Ml_Service{

	public $username, $apikey, $senderid;

	public function __construct(){

		$this->id 			= 'oursms';
		$this->username 	= xoo_ml_helper()->get_service_option('oursms-username');
		$this->apikey 		= xoo_ml_helper()->get_service_option('oursms-apikey');
		$this->senderid 	= xoo_ml_helper()->get_service_option('oursms-senderid');
		$this->url 			= 'https://api.oursms.com/api-a/msgs';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->username, $this->apikey ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'username' 		=> $this->username,
	 			'token' 		=> $this->apikey,
	 			'src' 			=> $this->senderid,
	 			'dests' 		=> $phone,
	 			'body' 			=> $message,
	 			'msgClass' 		=> 'transactional',
	 			'Priority' 		=> 1
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_OurSMS();

?>