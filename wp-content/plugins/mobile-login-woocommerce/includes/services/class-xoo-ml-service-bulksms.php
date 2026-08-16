<?php

class Xoo_Ml_Service_Bulksms extends Xoo_Ml_Service{

	public $username, $password, $senderNumber;

	public function __construct(){

		$this->id 			= 'bulksms';
		$this->username 	= xoo_ml_helper()->get_service_option('blksms-username');
		$this->password 	= xoo_ml_helper()->get_service_option('blksms-password');
		$this->url 			= 'https://api.bulksms.com/v1/messages?auto-unicode=true&longMessageMaxParts=30';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->username, $this->password ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'to' 			=> $phone,
	 			'body' 			=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_Bulksms();

?>