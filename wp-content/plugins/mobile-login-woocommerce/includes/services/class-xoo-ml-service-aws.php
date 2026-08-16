<?php

use Aws\Sns\SnsClient; 
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;

class Xoo_Ml_Service_AWS extends Xoo_Ml_Service{

	public $accessKey, $secretKey, $credentials, $region;

	public function __construct(){

		$this->id 		= 'aws';
		$this->hasSDK 	= $this->get_operator_data('hasSDK');

		$this->accessKey = xoo_ml_helper()->get_service_option('asns-access-key');
		$this->secretKey = xoo_ml_helper()->get_service_option('asns-secret-key');
		$this->region 	 = xoo_ml_helper()->get_service_option('asns-region');

		$this->include_sdk( 'aws/aws-autoloader.php' );

	}

	public function set_credentials(){

		if( !isset( $this->credentials ) ){

			$this->credentials = new Credentials(
				xoo_ml_helper()->get_service_option('asns-access-key'),
				xoo_ml_helper()->get_service_option('asns-secret-key')
			);

		}

		return $this->credentials;

	}

	public function sendSMS( $phone, $message, $cc, $number ){

		$validate = $this->validate( array( $this->accessKey, $this->secretKey ) );
		
		if( is_wp_error( $validate ) ){
			return $validate;
		}


		$this->set_credentials();

		$SnSclient = new SnsClient([
		    'credentials' 	=> $this->credentials,
		    'region' 		=> $this->region,
		    'version' 		=> 'latest'
		]);

		try {
		    $result = $SnSclient->publish([
		        'Message' => $message,
		        'PhoneNumber' => $phone,
		    ]);
		} catch (AwsException $e) {
		    // output error message if fails
		    return new WP_Error( 'operator-error', $e->getMessage() );
		} 

	}

}

return new Xoo_Ml_Service_AWS();

?>
