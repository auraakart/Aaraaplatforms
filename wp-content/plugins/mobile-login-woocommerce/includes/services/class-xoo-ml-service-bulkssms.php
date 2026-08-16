<?php

class Xoo_Ml_Service_Bulkssms extends Xoo_Ml_Service{

	public $sid, $token, $senderNumber;

	public function __construct(){

		$this->id 			= 'bulkssms';
		$this->user 		= xoo_ml_helper()->get_service_option('bulksms-user');
		$this->key 			= xoo_ml_helper()->get_service_option('bulksms-key');
		$this->senderid 	= xoo_ml_helper()->get_service_option('bulksms-senderid');
		$this->templateid 	= xoo_ml_helper()->get_service_option('bulksms-templateid');
		$this->entityid 	= xoo_ml_helper()->get_service_option('bulksms-entityid');
		$this->url 			= 'http://sms.bulkssms.com/submitsms.jsp';
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->user, $this->key, $this->senderid ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
	 			'accusage' 		=> 1,
	 			'user' 			=> $this->user,
	 			'key' 			=> $this->key,
	 			'senderid' 		=> $this->senderid,
	 			'tempid' 		=> $this->templateid,
	 			'entityid' 		=> $this->entityid,
	 			'mobile' 		=> $phone,
	 			'message' 		=> $message,
	 		)
	    );

		return $this->http_request( $args );

	}

}

return new Xoo_Ml_Service_Bulkssms();

?>