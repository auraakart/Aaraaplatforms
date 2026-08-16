<?php

class Xoo_Ml_Service_Msegat extends Xoo_Ml_Service{

	public $user, $apikey, $usersender, $encoding;

	public function __construct(){

		$this->id 			= 'msegat';
		$this->user 		= xoo_ml_helper()->get_service_option('msegat-username');
		$this->apikey 		= xoo_ml_helper()->get_service_option('msegat-apikey');
		$this->usersender 	= xoo_ml_helper()->get_service_option('msegat-usersender');
		$this->encoding 	= xoo_ml_helper()->get_service_option('msegat-msgencoding');

		$this->url 			= 'https://www.msegat.com/gw/sendsms.php';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->user, $this->apikey, $this->usersender ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => wp_json_encode( array(
	 			'userName' 		=> $this->user,
	 			'apiKey' 		=> $this->apikey,
	 			'userSender' 	=> $this->usersender,
	 			'msgEncoding' 	=> $this->encoding,
	 			'numbers' 	 	=> $phone,
	 			'msg' 			=> $message,
	 		) ),
	 		'headers' => array(
				'Content-Type' => 'application/json', // Indicates that the data is URL-encoded
			),
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_Msegat();

?>