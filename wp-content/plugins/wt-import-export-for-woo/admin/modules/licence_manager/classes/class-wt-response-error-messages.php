<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Wt_licence_manager_error_messages
{
	public $plugin_settings_url='';
	public $product_display_name=''; 
	public $my_account_url='';

	public function __construct($plugin_settings_url, $product_display_name, $my_account_url)
	{
		$this->plugin_settings_url=$plugin_settings_url;
		$this->product_display_name=$product_display_name;
		$this->my_account_url=$my_account_url;
	}

	/**
	 * Displays an admin error message in the WordPress dashboard
	 * @param  array $response
	 * @return string
	 */
	public function check_response_for_errors( $response )
	{
		if ( ! empty( $response ) ) {

			if ( isset( $response->errors['no_key'] ) && $response->errors['no_key'] == 'no_key' && isset( $response->errors['no_subscription'] ) && $response->errors['no_subscription'] == 'no_subscription' ) {

				add_action('admin_notices', array( $this, 'no_key_error_notice') );
				add_action('admin_notices', array( $this, 'no_subscription_error_notice') );

			} else if ( isset( $response->errors['exp_license'] ) && $response->errors['exp_license'] == 'exp_license' ) {

				add_action('admin_notices', array( $this, 'expired_license_error_notice') );

			}  else if ( isset( $response->errors['hold_subscription'] ) && $response->errors['hold_subscription'] == 'hold_subscription' ) {

				add_action('admin_notices', array( $this, 'on_hold_subscription_error_notice') );

			} else if ( isset( $response->errors['cancelled_subscription'] ) && $response->errors['cancelled_subscription'] == 'cancelled_subscription' ) {

				add_action('admin_notices', array( $this, 'cancelled_subscription_error_notice') );

			} else if ( isset( $response->errors['exp_subscription'] ) && $response->errors['exp_subscription'] == 'exp_subscription' ) {

				add_action('admin_notices', array( $this, 'expired_subscription_error_notice') );

			} else if ( isset( $response->errors['suspended_subscription'] ) && $response->errors['suspended_subscription'] == 'suspended_subscription' ) {

				add_action('admin_notices', array( $this, 'suspended_subscription_error_notice') );

			} else if ( isset( $response->errors['pending_subscription'] ) && $response->errors['pending_subscription'] == 'pending_subscription' ) {

				add_action('admin_notices', array( $this, 'pending_subscription_error_notice') );

			} else if ( isset( $response->errors['trash_subscription'] ) && $response->errors['trash_subscription'] == 'trash_subscription' ) {

				add_action('admin_notices', array( $this, 'trash_subscription_error_notice') );

			} else if ( isset( $response->errors['no_subscription'] ) && $response->errors['no_subscription'] == 'no_subscription' ) {

				add_action('admin_notices', array( $this, 'no_subscription_error_notice') );

			} else if ( isset( $response->errors['no_activation'] ) && $response->errors['no_activation'] == 'no_activation' ) {

				add_action('admin_notices', array( $this, 'no_activation_error_notice') );

			} else if ( isset( $response->errors['no_key'] ) && $response->errors['no_key'] == 'no_key' ) {

				add_action('admin_notices', array( $this, 'no_key_error_notice') );

			} else if ( isset( $response->errors['download_revoked'] ) && $response->errors['download_revoked'] == 'download_revoked' ) {

				add_action('admin_notices', array( $this, 'download_revoked_error_notice') );

			} else if ( isset( $response->errors['switched_subscription'] ) && $response->errors['switched_subscription'] == 'switched_subscription' ) {

				add_action('admin_notices', array( $this, 'switched_subscription_error_notice') );

			}

		}

	}

	/**
	 * Display license expired error notice
	 * @param  string $message
	 * @return void
	 */
	public function expired_license_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'The license key for %s has expired. You can reactivate or purchase a license key from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display subscription on-hold error notice
	 * @param  string $message
	 * @return void
	 */
	public function on_hold_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for %s is on-hold. You can reactivate the subscription from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display subscription cancelled error notice
	 * @param  string $message
	 * @return void
	 */
	public function cancelled_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for %s has been cancelled. You can renew the subscription from your account <a href="%s" target="_blank">dashboard</a>. A new license key will be emailed to you after your order has been completed.' ) . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display subscription expired error notice
	 * @param  string $message
	 * @return void
	 */
	public function expired_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for %s has expired. You can reactivate the subscription from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display subscription expired error notice
	 * @param  string $message
	 * @return void
	 */
	public function suspended_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for %s has been suspended. You can reactivate the subscription from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display subscription expired error notice
	 * @param  string $message
	 * @return void
	 */
	public function pending_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for %s is still pending. You can check on the status of the subscription from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display subscription expired error notice
	 * @param  string $message
	 * @return void
	 */
	public function trash_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for %s has been placed in the trash and will be deleted soon. You can purchase a new subscription from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display subscription expired error notice
	 * @param  string $message
	 * @return void
	 */
	public function no_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'A subscription for %s could not be found. You can purchase a subscription from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display missing key error notice
	 * @param  string $message
	 * @return void
	 */
	public function no_key_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'A license key for %s could not be found. Maybe you forgot to enter a license key when setting up %s, or the key was deactivated in your account. You can reactivate license key at your plugin <a href="%s" target="_blank">settings page</a>.') . '</p></div>', $this->product_display_name, $this->product_display_name, $this->plugin_settings_url ) ;

	}

	/**
	 * Display missing download permission revoked error notice
	 * @param  string $message
	 * @return void
	 */
	public function download_revoked_error_notice( $message ){ 

		echo sprintf( '<div id="message" class="error"><p>' . __( 'Download permission for %s has been revoked possibly due to a license key or subscription expiring. You can reactivate or purchase a license key from your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}

	/**
	 * Display no activation error notice
	 * @param  string $message
	 * @return void
	 */
	public function no_activation_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( '%s has not been activated. Go to the plugin <a href="%s" target="_blank">settings page</a> and enter the license key and license email to activate %s.') . '</p></div>', $this->product_display_name, $this->plugin_settings_url, $this->product_display_name ) ;

	}

	/**
	 * Display switched activation error notice
	 * @param  string $message
	 * @return void
	 */
	public function switched_subscription_error_notice( $message ){

		echo sprintf( '<div id="message" class="error"><p>' . __( 'You changed the subscription for %s, so you will need to enter your new API License Key in the settings page. The License Key should have arrived in your email inbox, if not you can get it by logging into your account <a href="%s" target="_blank">dashboard</a>.') . '</p></div>', $this->product_display_name, $this->my_account_url ) ;

	}
}