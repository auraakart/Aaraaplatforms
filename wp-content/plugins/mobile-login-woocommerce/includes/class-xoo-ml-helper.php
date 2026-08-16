<?php

class Xoo_Ml_Helper extends Xoo_Helper{

	protected static $_instance = null;

	public $whatsapp_enabled;

	public $sms_enabled;

	public $mergeCC;

	public function __construct(...$args){
		parent::__construct(...$args);
		$this->whatsapp_enabled = $this->get_phone_option('m-sms-channels') !== 'sms';
		$this->sms_enabled 		= $this->get_phone_option('m-sms-channels') !== 'whatsapp';
	}


	public static function get_instance( $slug, $path, $helperArgs = array() ){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $slug, $path, $helperArgs );
		}
		return self::$_instance;
	}

	public function get_phone_option( $subkey = '' ){
		return $this->get_option( 'xoo-ml-phone-options', $subkey );
	}

	public function get_service_option( $subkey = '' ){
		return $this->get_option( 'xoo-ml-services-options', $subkey );
	}

	public function mergeCC(){
		if( !$this->mergeCC ){
			$this->mergeCC = $this->get_phone_option('m-cc-merge') === "yes" && $this->canMergeCC(); 
		}
		return $this->mergeCC;
	}

	public function canMergeCC(){
		return $this->get_phone_option('r-enable-cc-field') === "yes" && $this->get_phone_option('m-show-country-code-as') === 'selectbox';
	}

}

function xoo_ml_helper(){
	return Xoo_Ml_Helper::get_instance( 'mobile-login-woocommerce', XOO_ML_PATH, array(
		'pluginFile' 	=> XOO_ML_PLUGIN_FILE,
		'pluginName' 	=> 'OTP Login Woocommerce',
		'sidebar' 		=> true
	) );
}
xoo_ml_helper();

?>