<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class Xoo_Ml{

	protected static $_instance = null;

	public $updated = false;

	public $operator;

	public $isSDKVersion = false;

	public static function get_instance(){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	
	public function __construct(){
		$this->set_is_sdk_version();
		$this->includes();
		$this->hooks();
	}

	/**
	 * File Includes
	*/
	public function includes(){

		//xootix framework
		require_once XOO_ML_PATH.'/includes/xoo-framework/xoo-framework.php';
		require_once XOO_ML_PATH.'/includes/class-xoo-ml-helper.php';

		require_once XOO_ML_PATH.'includes/xoo-ml-functions.php';
		require_once XOO_ML_PATH.'includes/class-xoo-ml-geolocation.php';

		require_once XOO_ML_PATH.'includes/class-xoo-ml-services.php';

		if($this->is_request('frontend')){
			require_once XOO_ML_PATH.'includes/class-xoo-ml-frontend.php';
		}
		
		if($this->is_request('admin')) {
			require_once XOO_ML_PATH.'admin/class-xoo-ml-admin-settings.php';
			require_once XOO_ML_PATH.'admin/class-xoo-ml-users-table.php';
		}

		//Gravity forms support
		if ( class_exists( 'GFCommon' ) ) {
			require_once XOO_ML_PATH.'includes/class-xoo-ml-gravity-form.php';
		}


		require_once XOO_ML_PATH.'includes/class-xoo-ml-verification.php';
		require_once XOO_ML_PATH.'includes/class-xoo-ml-otp-handler.php';

	}



	/**
	 * Hooks
	*/
	public function hooks(){
		$this->on_install();
	}


	/**
	 * What type of request is this?
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}


	/**
	* On install
	*/
	public function on_install(){

		$version_option = 'xoo-ml-version';
		$db_version 	= get_option( $version_option );

		$phoneOptions 	= xoo_ml_helper()->get_phone_option();
		$services 		= xoo_ml_helper()->get_service_option();

		//If first time installed
		if( !$db_version ){
			update_option('woocommerce_enable_myaccount_registration', 'yes');
		}

		if( $db_version ){

			if( version_compare( $db_version, '2.6.3', '<') ){
				$services['asns-region'] = 'us-east-1';
				
			}

			if( version_compare( $db_version, '2.6.6', '<') ){
				$services['cus-format'] = 'url';
			}

			if( version_compare( $db_version, '2.6.7', '<') ){
				$services['cus-json'] =  $services['cus-format'] === 'json' && !empty( $services['cus-params'] ) ? json_encode( wp_parse_args( html_entity_decode( $services['cus-params'] ) ) ) : '';
			}

			if( version_compare( $db_version, '2.6.8', '<') ){
				$services['cus-numberformat'] = '+[country_code][number]';
				update_option('xoo_tracking_consent_mobile-login-woocommerce', 'no' );
			}

			if( version_compare( $db_version, '2.7.0', '<') ){
				$phoneOptions['m-sms-channels'] 	= 'sms';
			}

			update_option( 'xoo-ml-services-options', $services );
			update_option( 'xoo-ml-phone-options', $phoneOptions );

		}

		if( version_compare( $db_version, XOO_ML_VERSION, '<') ){
			//Update to current version
			update_option( $version_option, XOO_ML_VERSION);
			$this->updated = true;
		}
	}



	public function set_is_sdk_version(){

		if( apply_filters( 'xoo_ml_force_nosdk', false ) ){
			return;
		}

		if( get_option( 'xoo-ml-sdk-dependant' ) === "yes" ){
			$this->isSDKVersion = true;
		}

		$dbversion = get_option( 'xoo-ml-version' );

		if( $dbversion && version_compare( $dbversion, '2.4', '<' ) ){

			update_option( 'xoo-ml-sdk-dependant', 'yes' );

			$this->isSDKVersion = true;
		}


	}

}

?>