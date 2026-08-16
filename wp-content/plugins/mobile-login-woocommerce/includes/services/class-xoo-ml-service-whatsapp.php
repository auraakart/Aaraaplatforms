<?php

class Xoo_Ml_Service_WhatsApp extends Xoo_Ml_Service{

	public $phoneID, $template, $language;

	public function __construct(){

		$this->id 			= 'whatsapp';
		$this->authToken 	= xoo_ml_helper()->get_service_option('whatsapp-token');
		$this->phoneID 		= xoo_ml_helper()->get_service_option('whatsapp-phoneid');
		$this->template 	= xoo_ml_helper()->get_service_option('whatsapp-template');
		$this->language 	= xoo_ml_helper()->get_service_option('whatsapp-language');
		$this->url 			= 'https://graph.facebook.com/v24.0/'.$this->phoneID.'/messages';
		$this->format 		= 'json';
			
	}

	public function sendSMS( $phone, $otp, $cc, $number ){



		$validate = $this->validate( array( $this->authToken, $this->phoneID, $this->template ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//HTTP POST
		$args = array(		
	 		'body' => array(
				'messaging_product' => 'whatsapp',
				"recipient_type" => "individual",
				'to'				=> str_replace('+', '', $cc).$number,
				'type'              => 'template',
				'template'          => array(
					'name' 		=> $this->template,
					'language' 	=> array(
						'code' 	=> $this->language,
					)
				)
	 		)
	    );

	    if( $this->template !== 'hello_world' ){
	    	$args['body']['template']['components'] = array(

				array(
					"type"       => "body",
					"parameters" => array(
						array(
							"type" => "text",
							"text" => $otp 
						)
					)
				),

				array(
			        "type" 			=> "button",
			        "sub_type" 		=> "url",
			        "index" 		=> 0,
			        "parameters" 	=> array(
			            array(
			                "type" => "text",
			                "text" => $otp // required for Copy Code button
			            )
			        )
			    )

			);
	    }

		$req = $this->http_request( $args );

		return $req;

	}

}

return new Xoo_Ml_Service_WhatsApp();

?>