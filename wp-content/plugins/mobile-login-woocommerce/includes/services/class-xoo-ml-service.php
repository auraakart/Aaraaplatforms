<?php

class Xoo_Ml_Service{

	public $id, $username, $password, $url, $authToken, $format;

	public $hasSDK = false; //Needs PHP SDK to run

	public $notsetupError;


	public function sendSMS( $phone, $message, $cc, $number ){

	}


    public function http_request( $args ) {

		$headers = isset( $args['headers'] ) ? $args['headers'] : array();

		// Basic Authorization
		if ( isset( $this->username ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $this->username . ':' . $this->password );
		}

		if ( isset( $this->authToken ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->authToken;
		}

		$args['headers'] = $headers;

		$args = apply_filters( 'xoo_ml_service_http_args', $args, $this );
		$url  = apply_filters( 'xoo_ml_service_http_url', $this->url, $this );

		// Request formatting (API expects JSON)
		if ( $this->format === 'json' ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = json_encode( $args['body'], JSON_UNESCAPED_SLASHES );
		}

		$response = wp_remote_post( $url, $args );

		/* --------------------
		   DEBUG MODE
		-------------------- */
		if ( xoo_ml_helper()->get_phone_option('m-en-debug') === 'yes' ) {

			/** --------------------
			 * FORMAT RESPONSE BODY
			 ---------------------- */
			$body = $response['body'];

			if ( is_string( $body ) ) {
				$decoded = json_decode( $body, true );

				if ( json_last_error() === JSON_ERROR_NONE ) {
					$body = "<pre>" . json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "</pre>";
				}
			}

			/** --------------------
			 * FORMAT SENT ARGS
			 * (decode JSON body for output)
			 ---------------------- */
			$formatted_args = $args;

			if ( isset( $args['body'] ) && is_string( $args['body'] ) ) {
				$inner = json_decode( $args['body'], true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$formatted_args['body'] = $inner;
				}
			}

			$args_pretty = "<pre>" . json_encode( $formatted_args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "</pre>";

			return xoo_ml_add_notice(
				'<b><u>Operator: '.$this->id.'</u></b><br>'.
				'<b><u>Response Received:</u></b> '.$body.'<br>'.
				'<b><u>Data Passed:</u></b> '.$args_pretty,
				'success'
			) . '<br>';
		}

		/* --------------------
		   NORMAL MODE
		-------------------- */
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}




	public function include_sdk( $location ){
		if( $this->hasSDK && $this->has_sdk_folder() ){
			require_once xoo_ml_services()::$sdkDir . '/'.$location;
		}
	}


	public function get_operator_data( $key = '' ){
		return xoo_ml_services()->get_operator_data( $this->id, $key );
	}


	public function get_incomplete_setup_notice(){

		if( !isset( $this->notsetupError ) ){

			$operatorData 	= xoo_ml_services()->get_operator_data( $this->id );
			$docLink 		= sanitize_text_field( $this->get_operator_data('doc') );
			$title 			= sanitize_text_field( $this->get_operator_data('title') );

			$this->notsetupError = new WP_Error( 'incomplete', 'Your SMS operator '.$title.' setup is incomplete. Please check your settings and follow the <a href="'.$docLink.'" target="__blank">documentation</a>' );

		}

		return $this->notsetupError;
	}


	public function validate_incomplete_settings( $settings = array() ){

		$empty = false;

		foreach ( $settings as $setting => $value) {
			if( !$value ){
				$empty = true;
				break;
			}
		}

		if( $empty ){
			return $this->get_incomplete_setup_notice();
		}
	}

	public function validate_sdk(){
		if( $this->hasSDK && !$this->has_sdk_folder() ){
			return new WP_Error( 'nosdk', 'SDK Missing. Please upload the SDK file in your settings for your SMS operator. <a href="'.esc_attr( $this->get_operator_data( 'doc' ) ).'" target="__blank">Documentation.</a>' );
		}
	}

	public function validate( $settings = array(), $sdk = true ){

		if( !empty( $settings ) ){
			$settingsValidation = $this->validate_incomplete_settings( $settings );
			if( is_wp_error( $settingsValidation ) ){
				return $settingsValidation;
			}
		}

		if( $sdk ){
			$sdkValidation = $this->validate_sdk();
			if( is_wp_error( $sdkValidation ) ){
				return $sdkValidation;
			}
		}

	}

	public function has_sdk_folder(){
		$operatorData 	= xoo_ml_services()->get_operator_data( $this->id );
		return isset( $operatorData['location'] ) && $operatorData['location'];
	}
}