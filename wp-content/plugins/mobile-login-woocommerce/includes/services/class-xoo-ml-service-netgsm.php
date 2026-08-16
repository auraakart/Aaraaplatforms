<?php

class Xoo_Ml_Service_Netgsm extends Xoo_Ml_Service{

	public $sid, $token, $senderNumber;

	public function __construct(){

		$this->id 			= 'netgsm';
		$this->usercode 	= xoo_ml_helper()->get_service_option('netgsm-usercode');
		$this->password 	= xoo_ml_helper()->get_service_option('netgsm-password');
		$this->msgheader 	= xoo_ml_helper()->get_service_option('netgsm-msgheader');
		$this->url 			= 'https://api.netgsm.com.tr/sms/send/get';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->usercode, $this->password ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'usercode' 		=> $this->usercode,
	 			'password' 		=> $this->password,
	 			'gsmno' 		=> $phone,
	 			'message' 		=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_Netgsm();

?>