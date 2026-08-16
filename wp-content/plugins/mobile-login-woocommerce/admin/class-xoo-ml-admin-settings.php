<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Xoo_Ml_Admin_Settings{


	protected static $_instance = null;

	public $wpuploadDir,$sdkDir;


	public static function get_instance(){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct(){

		$this->wpuploadDir 	= wp_get_upload_dir()['basedir'];
		$this->sdkDir 		= $this->wpuploadDir. '/xootix-sms-sdks';
		
		if( current_user_can( 'manage_options' ) ){
			add_action( 'init', array( $this, 'generate_settings' ), 0 );
			add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		}



		add_action( 'xoo_tab_page_end', array( $this, 'tab_html' ), 10, 2 );

		add_action( 'xoo_tab_page_start', array( $this, 'notices' ), 5 );

		add_action('xoo_as_enqueue_scripts',array($this,'enqueue_scripts'));

		add_filter( 'plugin_action_links_' . XOO_ML_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );


		add_filter( 'xoo_admin_setting_field_callback_html', array( $this, 'operator_documentation_links_html' ), 10, 4 );

		add_filter( 'xoo_admin_setting_field_callback_html', array( $this, 'whatsapp_number_fetch' ), 10, 4 );


		if( current_user_can( 'manage_options' ) ){
			add_action( 'admin_init', array( $this, 'check_sdks' ) );
		}

		add_action( 'admin_head', array( $this, 'customCSS' ) );

		add_filter( 'pre_update_option', array( $this, 'fetch_sdks_on_save' ), 10, 3 );

		add_action( 'xoo_as_setting_sidebar_mobile-login-woocommerce', array( $this, 'sidebar_html' ) );

		add_action( 'wp_ajax_xoo_ml_admin_whatsapp_fetch', array( $this, 'whatsapp_fetch' ) );
		add_action( 'wp_ajax_xoo_ml_admin_whatsapp_register', array( $this, 'whatsapp_register' ) );
	}


	public function sidebar_html(){
		?>

		

		<ol>
			<?php if( get_option( 'woocommerce_enable_myaccount_registration' ) !== "yes" ): ?>
				<li class="xoo-as-info-warning"><b>Account creation on my account page</b> is disabled under woocommerce settings. You will not be able to register and login users with OTP. <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=account' ) ?>">Please enable</a></li>
			<?php endif; ?>
			<?php if( !class_exists('woocommerce') ): ?>
				<li class="xoo-as-info-warning">The free version works only with woocommerce login/registration forms. You do not have woocommerce installed.</li>
			<?php else: ?>
				<li><code>[woocommerce_my_account]</code> shortcode to display login and register forms or visit <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ) ?>">my account page </a>  without being signed in.</li>
			<?php endif; ?>
			<li>To start sending SMS messages, please setup one of the SMS operator services. <a href="https://docs.xootix.com/mobile-login-for-woocommerce/">Documentation</a></li>
		</ol>

		<?php
	}

	public function whatsapp_register(){

		try {

			$token 		= sanitize_text_field( $_POST['token'] );
			$waba_id 	= sanitize_text_field( $_POST['waba_id'] );
			$phone_id 	= sanitize_text_field( trim( $_POST['phone_id'] ) );
			$pin 		= sanitize_text_field( trim( $_POST['pin'] ) );

			$register_url = "https://graph.facebook.com/v24.0/{$phone_id}/register";

			$response = wp_remote_post( $register_url, array(
			    'timeout' => 20,
			    'headers' => array(
			        'Authorization' => "Bearer {$token}",
			        'Content-Type'  => 'application/json'
			    ),
			    'body' => wp_json_encode(array(
			        'messaging_product' => 'whatsapp',
			        'pin'               => $pin
			    ))
			) );

			$body      = wp_remote_retrieve_body( $response );
			$json      = json_decode( $body, true );

			if( !isset( $json['success'] ) ){
				throw new Xoo_Exception( $body );	
			}

			wp_send_json( array(
				'success' 	=> 1,
				'message' 	=> xoo_ml_add_notice( 'Phone number registered succesfully', 'success' )
			) );
			
		} catch (Exception $e) {
			wp_send_json( array(
				'error' => 1,
				'message' => xoo_ml_add_notice( $e->getMessage(), 'error' )
			) );
		}
	}


	public function whatsapp_fetch(){

		try {

			$token 		= sanitize_text_field( $_POST['token'] );
			$waba_id 	= sanitize_text_field( $_POST['waba_id'] );
			$phone_no 	= sanitize_text_field( trim( $_POST['phone_no'] ) );

			$fetch_numbers_url = "https://graph.facebook.com/v24.0/{$waba_id}/phone_numbers";

			$response = wp_remote_get( $fetch_numbers_url, array(
				'headers' => array(
					'Authorization' => "Bearer {$token}"
				)
			) );

			$body = json_decode( $response['body'], true );

			if( !isset( $body['data'] ) ){
				throw new Exception( $response['body'] );
			}

			$number_found = $request_ok = $number_id = false;

			foreach ( $body['data'] as $index => $number_data ) {

				if( isset( $number_data['display_phone_number'] ) ){

					$request_ok = true; // number data exists

					if( $number_data['display_phone_number'] == $phone_no ){
						$number_found = true;
						$number_id = $number_data['id'];
					}

				}

			}

			if( !$request_ok ){
				throw new Exception( $response['body'] );
			}

			if( !$number_found ){
				throw new Exception( 'Provided number not found in your whatsapp account' );
			}

			wp_send_json( array(
				'success' 	=> 1,
				'number_id' => $number_id,
				'message' 	=> xoo_ml_add_notice( 'Phone number ID fetched succesfully. Please enter the PIN and register your phone number.', 'success' )
			) );

			
		} catch (Exception $e) {
			wp_send_json( array(
				'error' => 1,
				'message' =>xoo_ml_add_notice( $e->getMessage(), 'error' )
			) );
		}

		
		exit;

	}


	public function fetch_sdks_on_save( $value, $option, $old_value ){
		if( $option !== 'xoo-ml-services-options' || !$value || empty( $value ) ) return $value;

		$fetchOperatorsSDK = array();

		if( $value !== $old_value ){

			foreach ($value as $optionKey => $optionValue ) {
				if( strpos( $optionKey , '-phpsdk') !== false && ( !isset( $old_value[ $optionKey ] ) || $optionValue !== $old_value[ $optionKey ] ) ){
					$fetchOperatorsSDK[] = $optionValue;
				}
			}
		}


		if( !empty($fetchOperatorsSDK) ){
			foreach ( $fetchOperatorsSDK as $sdk ) {
				$this->unzip_sdk( $sdk );
			}
		}

		return $value;
	}


	public function customCSS(){
		if( !xoo_ml_helper()->admin->is_settings_page() ) return;
		?>
		<style type="text/css">
			<?php if( !xoo_ml()->isSDKVersion ): ?>
			.xoo-as-setting:has(input[name="xoo-ml-services-options[twilio-phpsdk]"]){
				display: none;
			}
			<?php endif; ?>
		</style>
		<?php
	}

	public function notices($tab_id){

		if( !xoo_ml_helper()->admin->is_settings_page() ) return;

		?>

		<?php if( $tab_id !== 'pro' ): ?>

			<div class="xoo-ml-service-info">To start sending SMS messages, please setup one of the SMS operator services. <a href="https://docs.xootix.com/mobile-login-for-woocommerce/">Documentation</a></div>

			<?php if( function_exists('xoo_el') ): ?>

				<div class="xoo-ml-free-popup">The <a href="https://xootix.com/plugins/mobile-login-for-woocommerce/" target="_blank">pro version</a> is required to integrate OTP login with the Login/signup popup plugin<br> The free version of OTP Login works with the Woocommerce Login/Register form</a></div>

			<?php endif; ?>

		<?php endif; ?>

		<?php
	}


	public function tab_html( $tab_id, $tab_data ){

		if( !xoo_ml_helper()->admin->is_settings_page() ) return;
		
		if( $tab_id === 'pro' ){
			xoo_ml_helper()->get_template( 'xoo-ml-tab-pro.php', array(), XOO_ML_PATH.'/admin/templates/' );
		}

		if( $tab_id === 'info' ){
			xoo_ml_helper()->get_template( 'xoo-ml-tab-info.php', array(), XOO_ML_PATH.'/admin/templates/' );
		}
		
	}


	public function generate_settings(){
		xoo_ml_helper()->admin->auto_generate_settings();
	}


	public function add_menu_pages(){

		$args = array(
			'menu_title' 	=> 'OTP Login',
			'icon' 			=> 'dashicons-smartphone',
		);

		xoo_ml_helper()->admin->register_menu_page( $args );

	}



	public function enqueue_scripts($slug) {

		if( $slug !== 'mobile-login-woocommerce' ) return;

		wp_enqueue_style( 'xoo-ml-select2', XOO_ML_URL.'/library/select2/select2.css');
		
		wp_enqueue_script( 'xoo-ml-select2', XOO_ML_URL.'/library/select2/select2.js', array('jquery'), XOO_ML_VERSION, true); // Main JS

		wp_enqueue_style( 'xoo-ml-flags', XOO_ML_URL.'/countries/flags.css', array(), XOO_ML_VERSION );

		wp_enqueue_style( 'xoo-ml-admin-style', XOO_ML_URL . '/admin/assets/css/xoo-ml-admin-style.css', array(), XOO_ML_VERSION, 'all' );

		wp_enqueue_script( 'xoo-ml-admin-js', XOO_ML_URL . '/admin/assets/js/xoo-ml-admin-js.js', array( 'jquery','wp-color-picker'), XOO_ML_VERSION, false );


		$hasSDKs = array();

		foreach ( xoo_ml_services()->operators as $id => $data ) {
			if( isset( $data['hasSDK'] ) && $data['hasSDK'] ){
				$hasSDKs[] = $id;
			}
		}

		wp_localize_script('xoo-ml-admin-js','xoo_ml_admin_localize',array(
			'adminurl'  => admin_url().'admin-ajax.php',
			'isSDKVer' 	=> xoo_ml()->isSDKVersion
		));

	}


	/**
	 * Show action links on the plugin screen.
	 *
	 * @param	mixed $links Plugin Action links
	 * @return	array
	 */
	public function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=mobile-login-woocommerce-settings' ) . '">' . __('Settings', 'mobile-login-woocommerce' ) . '</a>',
			'support' 	=> '<a href="https://xootix.com/contact" target="__blank">Support</a>',
		);

		return array_merge( $action_links, $links );
	}

	public function whatsapp_number_fetch( $field, $field_id, $value, $args ){

		if( $field_id !== 'xoo-ml-services-options[whatsapp-phoneid]' ) return $field;

		$html = '';

		ob_start();

		?>

		<span class="xoo-admin-ml-verifyno xoo-admin-ml-link">Fetch ID</span>
		<div class="xoo-admin-ml-register">
			<input type="number" placeholder="Enter 6 digit pin">
			<span class="xoo-admin-ml-link">Register Number</span>
		</div>

		<?php
		$html .= ob_get_clean();

		return $field.$html;

	}


	public function operator_documentation_links_html( $field, $field_id, $value, $args ){

		if( $field_id !== 'xoo-ml-phone-options[m-operator]' ) return $field;

		$operator_data = xoo_ml_services()->operators;

		$html = '';

		ob_start();

		?>

		<ul class="xoo-ml-opt-links">
		
			<?php foreach( $operator_data as $operator => $data ): ?>
				<li data-operator="<?php echo esc_attr( $operator ); ?>" style="display: none;">

					<a href="<?php echo esc_url( $data['doc'] ); ?>" target="_blank">Documentation</a>

					<?php if( isset( $data['desc'] ) ): ?>
						<i><?php echo esc_html( $data['desc'] ) ?></i>
					<?php endif; ?>

				</li>
			<?php endforeach; ?>

		</ul>
		<span class="xoo-ml-notice"></span>

		<?php
		$html .= ob_get_clean();

		return $field.$html;

	}


	public function check_sdks(){

		if( !xoo_ml_helper()->admin->is_settings_page() ) return;

		$operator = xoo_ml_helper()->get_phone_option('m-operator');

		$this->fetch_sdk( $operator );
	}



	public function fetch_sdk( $operator, $replace = false ){

		//Check if SDK folder exists
		if( !is_dir( $this->sdkDir ) ){
			mkdir( $this->sdkDir );
		}

		//Check if SDK already installed
		if( is_dir( $this->sdkDir.'/'.$operator ) && !$replace ){
			return;
		}

		return $this->unzip_sdk( xoo_ml_helper()->get_service_option( $operator.'-phpsdk' ) );
		
	}

	public function unzip_sdk( $fileurl, $toabs = true ){

		if( !$fileurl || !trim($fileurl) ) return;

		$sdk = get_attached_file( attachment_url_to_postid( $fileurl) );

		if( $sdk && file_exists( $sdk ) ){
			//Unzip
			WP_Filesystem();
			$unzipfile = unzip_file( $sdk, $this->sdkDir );
			return $unzipfile;
		}

	}
	

}

function xoo_ml_admin_settings(){
	return Xoo_Ml_Admin_Settings::get_instance();
}

xoo_ml_admin_settings();

?>