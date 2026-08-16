<?php

class Xoo_Ml_Services{

	protected static $_instance;

	public $activeServiceObj;

	public $activeOperator;

	public $wa_service;

	public $operators = array();

	public static $sdkDir;

	public static function get_instance(){
		if( !isset( self::$_instance ) ){
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct(){
		self::$sdkDir 			= wp_get_upload_dir()['basedir'] .'/xootix-sms-sdks';
		$this->activeOperator 	= xoo_ml_helper()->get_phone_option('m-operator');
		$this->set_operators_data();
	}



	public function operator(){

		if( !isset( $this->activeServiceObj ) ){

			$operatorData = $this->get_operator_data();
			
			require_once XOO_ML_PATH.'/includes/services/class-xoo-ml-service.php';
			$this->activeServiceObj = require_once $operatorData['service'];
			
		}

		return $this->activeServiceObj;
	}

	public function get_whatsapp_service() {

		if ( ! $this->wa_service ) {

			$operatorData = $this->get_operator_data( 'whatsapp' );

			require_once XOO_ML_PATH.'/includes/services/class-xoo-ml-service.php';

			$this->wa_service = require_once $operatorData['service'];

		}

		return $this->wa_service;
	}

	public function set_operators_data(){

		$operators = array(
			'whatsapp' 	=> array(
				'title' 	=> 'WhatsApp',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-whatsapp.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/whatsapp/'
			),
			'twilio' => array(
				'title' 	=> 'Twilio',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/twilio/',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-twilio.php',
				'hasSDK' 	=> xoo_ml()->isSDKVersion
			),
			'aws' => array(
				'title' 	=> 'Amazon',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/amazon-sns/',		
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-aws.php',
				'hasSDK' 	=> true
			),
			'firebase' => array(
				'title' 	=> 'Google Firebase',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/google-firebase/',
				'desc' 		=> ''
			),
			'blksms' 	=> array(
				'title' 	=> 'Bulk SMS',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-bulksms.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/bulksms/'
			),
			'bulksms' 	=> array(
				'title' 	=> 'Bulk(s) SMS',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-bulkssms.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/bulkssms/'
			),
			'netgsm' 	=> array(
				'title' 	=> 'Net GSM (Turkey)',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-netgsm.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/netgsm/'
			),
			'oursms' 	=> array(
				'title' 	=> 'OurSMS',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-oursms.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/oursms/'
			),
			'smsalert' 	=> array(
				'title' 	=> 'SMS Alert',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-smsalert.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/smsalert/'
			),
			'unifonic' 	=> array(
				'title' 	=> 'Unifonic',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-unifonic.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/unifonic/'
			),
			'msg91' 	=> array(
				'title' 	=> 'MSG91',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-msg91.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/msg91/'
			),
			'textlocal' 	=> array(
				'title' 	=> 'TextLocal',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-textlocal.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/textlocal/'
			),

			'smslane' 	=> array(
				'title' 	=> 'SMSLane',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-smslane.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/smslane/'
			),
			'semaphore' 	=> array(
				'title' 	=> 'Semaphore',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-semaphore.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/semaphore/'
			),
			'msegat' 	=> array(
				'title' 	=> 'Msegat',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-msegat.php',
				'doc' 		=> 'http://docs.xootix.com/mobile-login-for-woocommerce/msegat/'
			),
			'custom' 	=> array(
				'title' 	=> 'Custom',
				'desc' 		=> '',
				'service' 	=> XOO_ML_PATH.'/includes/services/class-xoo-ml-service-custom.php',
				'doc' 		=> ''
			)

		);

		foreach ( $operators as $operator => $data ) {
			if( is_dir( self::$sdkDir.'/'.$operator ) ){
				$operators[ $operator ][ 'location' ] = self::$sdkDir.'/'.$operator ;
			}
		}

		$this->operators = $operators;
	}

	

	public function get_operator_data( $operator = '', $key = '' ){

		$data = array();

		if( !$operator ){
			$operator = $this->activeOperator;
		}

		if( isset( $this->operators[ $operator ] ) ){
			$data =  $this->operators[ $operator ];
		}

		if( $key ){
			return isset( $data[ $key ] ) ? $data[ $key ] : '';
		}

		return $data;
	}


}

function xoo_ml_services(){
	return Xoo_Ml_Services::get_instance();
}
