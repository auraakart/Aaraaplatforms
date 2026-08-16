<?php

use Twilio\Rest\Client;

class Xoo_Ml_Service_Twilio extends Xoo_Ml_Service{

	public $sid, $token, $senderNumber, $whatsappnumber;

	public function __construct(){

		$this->id 				= 'twilio';
		$this->sid 				= $this->username = xoo_ml_helper()->get_service_option('twilio-account-sid');
		$this->token 			= $this->password = xoo_ml_helper()->get_service_option('twilio-auth-token');
		$this->senderNumber 	= xoo_ml_helper()->get_service_option('twilio-sender-number');
		$this->whatsappnumber 	= xoo_ml_helper()->get_service_option('twilio-wp-number');
		$this->url 				= 'https://api.twilio.com/2010-04-01/Accounts/'.$this->sid.'/Messages.json';

		$this->hasSDK 			= $this->get_operator_data('hasSDK');

		$this->include_sdk( 'twilio/src/Twilio/autoload.php' );
			
	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->sid, $this->token, $this->senderNumber ) );

		if( is_wp_error( $validate ) ){
			return $validate;
		}

		//Use SDK for older versions
		if( $this->hasSDK ){

			$client = new Client(
				$this->sid,
				$this->token
			);


			try {
			    $client->messages->create(
			    // Where to send a text message (your cell phone?)
				    $phone,
				    array(
				        'from' => $this->senderNumber,
				        'body' => $message
				    )
				);

				if( $this->whatsappnumber ){
					$client->messages->create(
						'whatsapp:' . $phone, // to
						array(
							"from" => 'whatsapp:'.$this->whatsappnumber,
							"body" => $message
						)
					);
				}

			} catch (Exception $e) {
			    // output error message if fails
			    return new WP_Error( 'operator-error', $e->getMessage() );
			}

		}
		else{ //HTTP POST

			$args = array(		
		 		'body' => array(
		 			'From' 		=> $this->senderNumber,
		 			'To' 		=> $phone,
		 			'Body' 		=> $message
		 		)
		    );

		    if( $this->whatsappnumber ){

		    	$whatsappArgs = array(		
			 		'body' => array(
			 			'From' 		=> 'whatsapp:'.$this->whatsappnumber,
			 			'To' 		=> 'whatsapp:'.$phone,
			 			'Body' 		=> $message
			 		)
			    );

		    	$this->http_request( $whatsappArgs );
		    }

			return $this->http_request( $args );

		}

	}

}

return new Xoo_Ml_Service_Twilio();

?>