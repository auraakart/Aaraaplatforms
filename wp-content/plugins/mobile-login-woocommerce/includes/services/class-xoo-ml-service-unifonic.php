<?php

class Xoo_Ml_Service_Unifonic extends Xoo_Ml_Service{

	public $appid, $senderid;

	public function __construct(){

		$this->id 			= 'unifonic';
		$this->appid 		= xoo_ml_helper()->get_service_option('unifonic-appid');
		$this->senderid 	= xoo_ml_helper()->get_service_option('unifonic-senderid');

		$this->url 			= 'https://basic.unifonic.com/rest/SMS/Messages/Send';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->appid, $this->senderid ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'AppSid' 		=> $this->appid,
	 			'SenderID' 		=> $this->senderid,
	 			'Recipient' 	=> str_replace("+", "", $phone),
	 			'Body' 			=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_Unifonic();

?>