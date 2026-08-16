<?php
/**
 * AaraaNotifications: In-app push (Firebase Cloud Messaging, HTTP v1 API).
 *
 * Adds a tabbed "In-app" settings screen under AaraaNotifications and delivers
 * push notifications to the mobile app through the Firebase Cloud Messaging
 * HTTP v1 API:
 *
 *   POST https://fcm.googleapis.com/v1/projects/{project_id}/messages:send
 *
 * The v1 API is authenticated with a short-lived OAuth2 access token that is
 * minted locally by signing a JWT with the service-account private key (the
 * legacy "server key" API was retired in 2024). The service-account JSON is
 * pasted on the General tab; the access token is cached in a transient.
 *
 * Because push targets a device, the mobile app must register each signed-in
 * user's FCM device token with the site. Two REST routes handle that:
 *
 *   POST /wp-json/aaraa/v1/fcm/register    { token, platform }
 *   POST /wp-json/aaraa/v1/fcm/unregister  { token }
 *
 * Tokens are stored in the user meta `aaraa_fcm_tokens`. When an order or
 * subscription status changes and that status is enabled, a push (title + body,
 * tokens resolved) is sent to every device the order's customer has registered.
 * Tokens Firebase reports as stale (UNREGISTERED / invalid) are pruned.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Firebase in-app push controller.
 */
class Firebase_Admin {

	const PAGE = 'aaraa-notify-inapp';

	const OPTION     = 'aaraa_notify_fcm';
	const ACTION     = 'aaraa_fcm_save';
	const NONCE      = 'aaraa_fcm';
	const LOG_OPTION = 'aaraa_notify_fcm_log';
	const LOG_LIMIT  = 300;
	const LOG_CLEAR  = 'aaraa_fcm_log_clear';

	const USER_TOKENS_META = 'aaraa_fcm_tokens';
	const TOKEN_TRANSIENT  = 'aaraa_fcm_access_token';

	const REST_NS = 'aaraa/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_init', array( $this, 'handle_log_clear' ) );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 20, 4 );
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'on_subscription_status_changed' ), 20, 3 );
	}

	/* --------------------------------------------------------------------- *
	 * Settings.
	 * --------------------------------------------------------------------- */

	/**
	 * FCM settings, merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function settings() {
		$defaults = array(
			'enabled'         => 0,
			'project_id'      => '',
			'service_account' => '',
			'order'           => array(),
			'subscription'    => array(),
		);

		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$s                 = array_merge( $defaults, $saved );
		$s['order']        = ( isset( $saved['order'] ) && is_array( $saved['order'] ) ) ? $saved['order'] : array();
		$s['subscription'] = ( isset( $saved['subscription'] ) && is_array( $saved['subscription'] ) ) ? $saved['subscription'] : array();

		return $s;
	}

	/**
	 * The notification mapped to a status in a group ('order'|'subscription').
	 *
	 * @param string $group  order|subscription.
	 * @param string $status Status key (with or without the wc- prefix).
	 * @return array{enabled:int,title:string,body:string}|null
	 */
	public static function status_message( $group, $status ) {
		$s      = self::settings();
		$status = str_replace( 'wc-', '', (string) $status );
		if ( isset( $s[ $group ][ $status ] ) && is_array( $s[ $group ][ $status ] ) ) {
			return $s[ $group ][ $status ];
		}
		return null;
	}

	/**
	 * Decode the pasted service-account JSON.
	 *
	 * @return array{project_id:string,client_email:string,private_key:string}|null
	 */
	public static function service_account() {
		$s = self::settings();
		if ( empty( $s['service_account'] ) ) {
			return null;
		}
		$data = json_decode( (string) $s['service_account'], true );
		if ( ! is_array( $data ) || empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
			return null;
		}
		$project_id = ! empty( $s['project_id'] ) ? $s['project_id'] : ( isset( $data['project_id'] ) ? $data['project_id'] : '' );
		return array(
			'project_id'   => $project_id,
			'client_email' => $data['client_email'],
			'private_key'  => $data['private_key'],
		);
	}

	/* --------------------------------------------------------------------- *
	 * OAuth2 access token (service-account JWT → token exchange).
	 * --------------------------------------------------------------------- */

	/**
	 * Get a cached (or freshly minted) OAuth2 access token for FCM.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array{token:string,error:string}
	 */
	public static function get_access_token( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TOKEN_TRANSIENT );
			if ( $cached ) {
				return array( 'token' => $cached, 'error' => '' );
			}
		}

		$sa = self::service_account();
		if ( ! $sa ) {
			return array( 'token' => '', 'error' => 'Service account JSON is missing or invalid.' );
		}
		if ( ! function_exists( 'openssl_sign' ) ) {
			return array( 'token' => '', 'error' => 'OpenSSL is not available on this server (required to sign the Firebase token).' );
		}

		$now    = time();
		$header = self::base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claim  = self::base64url(
			wp_json_encode(
				array(
					'iss'   => $sa['client_email'],
					'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);

		$signing_input = $header . '.' . $claim;
		$signature     = '';
		$signed        = openssl_sign( $signing_input, $signature, $sa['private_key'], 'SHA256' );
		if ( ! $signed ) {
			return array( 'token' => '', 'error' => 'Could not sign the JWT — check the private_key in the service account JSON.' );
		}
		$jwt = $signing_input . '.' . self::base64url( $signature );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'token' => '', 'error' => $response->get_error_message() );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = isset( $body['error_description'] ) ? $body['error_description'] : ( isset( $body['error'] ) ? $body['error'] : 'Unknown token error' );
			return array( 'token' => '', 'error' => $err );
		}

		$expires = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
		set_transient( self::TOKEN_TRANSIENT, $body['access_token'], max( 60, $expires - 60 ) );

		return array( 'token' => $body['access_token'], 'error' => '' );
	}

	/**
	 * URL-safe base64 (no padding).
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	private static function base64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/* --------------------------------------------------------------------- *
	 * Sending.
	 * --------------------------------------------------------------------- */

	/**
	 * Send a push to a single device token via the FCM v1 API.
	 *
	 * @param string $token   Device registration token.
	 * @param string $title   Notification title.
	 * @param string $body    Notification body.
	 * @param array  $data    Optional data payload (string values).
	 * @param array  $context Log context: type, ref, status, user_id.
	 * @return array{ok:bool,stale:bool,response:string}
	 */
	public static function send_to_token( $token, $title, $body, $data = array(), $context = array() ) {
		$sa = self::service_account();
		if ( ! $sa || empty( $sa['project_id'] ) ) {
			return array( 'ok' => false, 'stale' => false, 'response' => 'FCM not configured (project id / service account).' );
		}

		$auth = self::get_access_token();
		if ( '' === $auth['token'] ) {
			return array( 'ok' => false, 'stale' => false, 'response' => 'Access token: ' . $auth['error'] );
		}

		$message = array(
			'token'        => $token,
			'notification' => array(
				'title' => $title,
				'body'  => $body,
			),
			'android'      => array( 'priority' => 'high' ),
			'apns'         => array( 'headers' => array( 'apns-priority' => '10' ) ),
		);
		if ( ! empty( $data ) ) {
			$message['data'] = array_map( 'strval', $data );
		}

		$response = wp_remote_post(
			'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $sa['project_id'] ) . '/messages:send',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $auth['token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'message' => $message ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'stale' => false, 'response' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = trim( (string) wp_remote_retrieve_body( $response ) );
		$ok   = $code >= 200 && $code < 300;

		if ( $ok ) {
			return array( 'ok' => true, 'stale' => false, 'response' => self::summarise_ok( $raw ) );
		}

		$decoded = json_decode( $raw, true );
		$status  = isset( $decoded['error']['status'] ) ? $decoded['error']['status'] : '';
		$stale   = 404 === $code || in_array( $status, array( 'UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT' ), true );
		$msg     = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : $raw;

		return array( 'ok' => false, 'stale' => $stale, 'response' => 'HTTP ' . $code . ': ' . $msg );
	}

	/**
	 * Extract the message name from a successful FCM response.
	 *
	 * @param string $raw Raw JSON.
	 * @return string
	 */
	private static function summarise_ok( $raw ) {
		$data = json_decode( $raw, true );
		return ( is_array( $data ) && ! empty( $data['name'] ) ) ? $data['name'] : ( '' !== $raw ? $raw : 'sent' );
	}

	/**
	 * Send a push to every device registered to a user, pruning stale tokens.
	 *
	 * @param int    $user_id User id.
	 * @param string $title   Title.
	 * @param string $body    Body.
	 * @param array  $data    Data payload.
	 * @param array  $context Log context.
	 * @return bool True if at least one device accepted the push.
	 */
	public static function send_to_user( $user_id, $title, $body, $data = array(), $context = array() ) {
		$context['user_id'] = $user_id;

		if ( ! $user_id ) {
			self::log( array_merge( $context, array( 'result' => 'failed', 'response' => 'Order/subscription has no customer account (guest).', 'title' => $title ) ) );
			return false;
		}

		$tokens = self::get_user_tokens( $user_id );
		if ( empty( $tokens ) ) {
			self::log( array_merge( $context, array( 'result' => 'failed', 'response' => 'No registered devices for this customer.', 'title' => $title ) ) );
			return false;
		}

		$any_ok = false;
		$stale  = array();
		foreach ( $tokens as $token ) {
			$res = self::send_to_token( $token, $title, $body, $data, $context );
			self::log(
				array_merge(
					$context,
					array(
						'title'    => $title,
						'message'  => $body,
						'token'    => $token,
						'result'   => $res['ok'] ? 'sent' : 'failed',
						'response' => $res['response'],
					)
				)
			);
			if ( $res['ok'] ) {
				$any_ok = true;
			} elseif ( $res['stale'] ) {
				$stale[] = $token;
			}
		}

		if ( ! empty( $stale ) ) {
			self::remove_user_tokens( $user_id, $stale );
		}

		return $any_ok;
	}

	/* --------------------------------------------------------------------- *
	 * Device token store (per user).
	 * --------------------------------------------------------------------- */

	/**
	 * All registration tokens for a user.
	 *
	 * @param int $user_id User id.
	 * @return array<string>
	 */
	public static function get_user_tokens( $user_id ) {
		$stored = get_user_meta( $user_id, self::USER_TOKENS_META, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		return array_values( array_keys( $stored ) );
	}

	/**
	 * Register (upsert) a device token for a user.
	 *
	 * @param int    $user_id  User id.
	 * @param string $token    Device token.
	 * @param string $platform android|ios|web.
	 * @return void
	 */
	public static function add_user_token( $user_id, $token, $platform = '' ) {
		$token = trim( (string) $token );
		if ( ! $user_id || '' === $token ) {
			return;
		}
		$stored = get_user_meta( $user_id, self::USER_TOKENS_META, true );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored[ $token ] = array(
			'platform' => sanitize_text_field( $platform ),
			'updated'  => current_time( 'mysql' ),
		);
		// A token belongs to one device/user — drop it from any other user.
		self::detach_token_from_others( $user_id, $token );
		update_user_meta( $user_id, self::USER_TOKENS_META, $stored );
	}

	/**
	 * Remove specific tokens from a user.
	 *
	 * @param int           $user_id User id.
	 * @param array<string> $tokens  Tokens to remove.
	 * @return void
	 */
	public static function remove_user_tokens( $user_id, $tokens ) {
		$stored = get_user_meta( $user_id, self::USER_TOKENS_META, true );
		if ( ! is_array( $stored ) ) {
			return;
		}
		foreach ( (array) $tokens as $token ) {
			unset( $stored[ $token ] );
		}
		update_user_meta( $user_id, self::USER_TOKENS_META, $stored );
	}

	/**
	 * Ensure a token is not simultaneously registered to a different user.
	 *
	 * @param int    $keep_user_id The user that should keep the token.
	 * @param string $token        Token.
	 * @return void
	 */
	private static function detach_token_from_others( $keep_user_id, $token ) {
		$others = get_users(
			array(
				'meta_key'   => self::USER_TOKENS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'exclude'    => array( (int) $keep_user_id ),
				'fields'     => 'ID',
				'number'     => 50,
			)
		);
		foreach ( $others as $uid ) {
			$stored = get_user_meta( $uid, self::USER_TOKENS_META, true );
			if ( is_array( $stored ) && isset( $stored[ $token ] ) ) {
				unset( $stored[ $token ] );
				update_user_meta( $uid, self::USER_TOKENS_META, $stored );
			}
		}
	}

	/* --------------------------------------------------------------------- *
	 * REST: device registration.
	 * --------------------------------------------------------------------- */

	/**
	 * Register the device token REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NS,
			'/fcm/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_register' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/fcm/unregister',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_unregister' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
	}

	/**
	 * Only signed-in users may register/unregister their own device.
	 *
	 * @return bool
	 */
	public function rest_permission() {
		return is_user_logged_in();
	}

	/**
	 * Resolve the target user: the current user, or an explicit customer_id if
	 * the caller can manage customers (e.g. an admin registering on behalf).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	private function resolve_user( $request ) {
		$requested = (int) $request->get_param( 'customer_id' );
		if ( $requested && current_user_can( 'manage_woocommerce' ) ) {
			return $requested;
		}
		return get_current_user_id();
	}

	/**
	 * POST /fcm/register — store the caller's device token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_register( $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		if ( '' === $token ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'token is required' ), 400 );
		}
		$user_id  = $this->resolve_user( $request );
		$platform = sanitize_text_field( (string) $request->get_param( 'platform' ) );
		self::add_user_token( $user_id, $token, $platform );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'user_id' => $user_id,
				'devices' => count( self::get_user_tokens( $user_id ) ),
			),
			200
		);
	}

	/**
	 * POST /fcm/unregister — remove the caller's device token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_unregister( $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		if ( '' === $token ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'token is required' ), 400 );
		}
		$user_id = $this->resolve_user( $request );
		self::remove_user_tokens( $user_id, array( $token ) );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'user_id' => $user_id,
				'devices' => count( self::get_user_tokens( $user_id ) ),
			),
			200
		);
	}

	/* --------------------------------------------------------------------- *
	 * Triggers.
	 * --------------------------------------------------------------------- */

	/**
	 * Push on order status change when enabled.
	 *
	 * @param int       $order_id Order id.
	 * @param string    $from     Old status.
	 * @param string    $to       New status.
	 * @param \WC_Order $order    Order.
	 * @return void
	 */
	public function on_order_status_changed( $order_id, $from, $to, $order ) {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		$msg = self::status_message( 'order', $to );
		if ( ! $msg || empty( $msg['enabled'] ) ) {
			return;
		}
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$tokens = array(
			'{order_id}'      => $order->get_order_number(),
			'{status}'        => wc_get_order_status_name( $to ),
			'{customer_name}' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'{first_name}'    => $order->get_billing_first_name(),
			'{total}'         => $order->get_total(),
			'{currency}'      => $order->get_currency(),
			'{site}'          => get_bloginfo( 'name' ),
		);

		$title = strtr( (string) $msg['title'], array_map( 'strval', $tokens ) );
		$body  = strtr( (string) $msg['body'], array_map( 'strval', $tokens ) );

		$sent = self::send_to_user(
			$order->get_customer_id(),
			$title,
			$body,
			array(
				'type'      => 'order',
				'entity_id' => (string) $order->get_id(),
				'status'    => $to,
			),
			array( 'type' => 'order', 'ref' => $order->get_order_number(), 'status' => $to )
		);
		if ( $sent ) {
			$order->add_order_note( sprintf( 'In-app push sent for order status "%s".', $to ) );
		}
	}

	/**
	 * Push on subscription status change when enabled.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param string           $to           New status.
	 * @param string           $from         Old status.
	 * @return void
	 */
	public function on_subscription_status_changed( $subscription, $to, $from ) {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		$msg = self::status_message( 'subscription', $to );
		if ( ! $msg || empty( $msg['enabled'] ) ) {
			return;
		}
		if ( ! is_a( $subscription, 'WC_Subscription' ) ) {
			return;
		}

		$tokens = array(
			'{subscription_id}' => $subscription->get_id(),
			'{status}'          => $to,
			'{customer_name}'   => trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ),
			'{first_name}'      => $subscription->get_billing_first_name(),
			'{total}'           => $subscription->get_total(),
			'{currency}'        => $subscription->get_currency(),
			'{site}'            => get_bloginfo( 'name' ),
		);

		$title = strtr( (string) $msg['title'], array_map( 'strval', $tokens ) );
		$body  = strtr( (string) $msg['body'], array_map( 'strval', $tokens ) );

		$sent = self::send_to_user(
			$subscription->get_customer_id(),
			$title,
			$body,
			array(
				'type'      => 'subscription',
				'entity_id' => (string) $subscription->get_id(),
				'status'    => $to,
			),
			array( 'type' => 'subscription', 'ref' => $subscription->get_id(), 'status' => $to )
		);
		if ( $sent ) {
			$subscription->add_order_note( sprintf( 'In-app push sent for subscription status "%s".', $to ) );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Log.
	 * --------------------------------------------------------------------- */

	/**
	 * Append an entry to the rolling FCM log (newest first, capped).
	 *
	 * @param array<string,mixed> $entry Partial entry.
	 * @return void
	 */
	public static function log( $entry ) {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$entry = array_merge(
			array(
				'time'     => current_time( 'mysql' ),
				'type'     => 'manual',
				'ref'      => '',
				'status'   => '',
				'user_id'  => '',
				'token'    => '',
				'title'    => '',
				'message'  => '',
				'result'   => 'failed',
				'response' => '',
			),
			$entry
		);

		// Store only a short fingerprint of the (long) device token.
		if ( ! empty( $entry['token'] ) ) {
			$entry['token'] = substr( $entry['token'], 0, 12 ) . '…';
		}
		if ( strlen( $entry['response'] ) > 500 ) {
			$entry['response'] = substr( $entry['response'], 0, 500 ) . '…';
		}

		array_unshift( $log, $entry );
		if ( count( $log ) > self::LOG_LIMIT ) {
			$log = array_slice( $log, 0, self::LOG_LIMIT );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * The full FCM log (newest first).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_log() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/* --------------------------------------------------------------------- *
	 * Save handlers.
	 * --------------------------------------------------------------------- */

	/**
	 * Persist the FCM settings form (General + Status Config tabs).
	 *
	 * @return void
	 */
	public function handle_save() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE !== $page ) {
			return;
		}
		if ( empty( $_POST[ self::ACTION ] ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage notifications.', 'aaraa-white-label-admin' ) );
		}

		$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$cur = self::settings();
		$tab = isset( $in['aaraa_tab'] ) ? sanitize_key( $in['aaraa_tab'] ) : 'general';

		if ( 'status' === $tab ) {
			$cur['order']        = $this->sanitize_group( $in['order'] ?? array() );
			$cur['subscription'] = $this->sanitize_group( $in['subscription'] ?? array() );
			$settings            = $cur;
		} else {
			$settings = array(
				'enabled'         => empty( $in['enabled'] ) ? 0 : 1,
				'project_id'      => sanitize_text_field( $in['project_id'] ?? '' ),
				// Keep the JSON verbatim (newlines in private_key matter); only strip slashes.
				'service_account' => trim( (string) ( $in['service_account'] ?? '' ) ),
				'order'           => $cur['order'],
				'subscription'    => $cur['subscription'],
			);
			// New credentials → drop any cached access token.
			delete_transient( self::TOKEN_TRANSIENT );
		}

		update_option( self::OPTION, $settings );

		$notice = 'saved';

		// Optional: send a test push to a device token from the General tab.
		if ( 'status' !== $tab ) {
			$test_token = isset( $in['test_token'] ) ? sanitize_text_field( $in['test_token'] ) : '';
			if ( '' !== $test_token ) {
				$res = self::send_to_token(
					$test_token,
					__( 'Test notification', 'aaraa-white-label-admin' ),
					sprintf( /* translators: %s: site name */ __( 'Firebase is connected for %s.', 'aaraa-white-label-admin' ), get_bloginfo( 'name' ) ),
					array( 'type' => 'test' ),
					array( 'type' => 'test' )
				);
				self::log(
					array(
						'type'     => 'test',
						'token'    => $test_token,
						'title'    => 'Test notification',
						'result'   => $res['ok'] ? 'sent' : 'failed',
						'response' => $res['response'],
					)
				);
				$notice = $res['ok'] ? 'test_ok' : 'test_fail';
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE, 'tab' => $tab, 'notice' => $notice ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Clear the FCM activity log.
	 *
	 * @return void
	 */
	public function handle_log_clear() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE !== $page ) {
			return;
		}
		if ( empty( $_POST[ self::LOG_CLEAR ] ) ) {
			return;
		}
		check_admin_referer( self::LOG_CLEAR );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage notifications.', 'aaraa-white-label-admin' ) );
		}

		delete_option( self::LOG_OPTION );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE, 'tab' => 'logs', 'notice' => 'log_cleared' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize a group of per-status notifications.
	 *
	 * @param array<string,mixed> $group Raw posted group.
	 * @return array<string,array{enabled:int,title:string,body:string}>
	 */
	private function sanitize_group( $group ) {
		$clean = array();
		if ( ! is_array( $group ) ) {
			return $clean;
		}
		foreach ( $group as $status => $row ) {
			$status           = sanitize_key( $status );
			$clean[ $status ] = array(
				'enabled' => empty( $row['enabled'] ) ? 0 : 1,
				'title'   => sanitize_text_field( $row['title'] ?? '' ),
				'body'    => sanitize_textarea_field( $row['body'] ?? '' ),
			);
		}
		return $clean;
	}

	/* --------------------------------------------------------------------- *
	 * Rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Render the tabbed In-app (Firebase) settings screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}

		$tabs = array(
			'general' => __( 'General', 'aaraa-white-label-admin' ),
			'status'  => __( 'Status Config', 'aaraa-white-label-admin' ),
			'logs'    => __( 'Logs', 'aaraa-white-label-admin' ),
		);
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'general';
		}
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap aaraa-notify aaraa-fcm">
			<h1><?php esc_html_e( 'In-app Notifications (Firebase)', 'aaraa-white-label-admin' ); ?></h1>

			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php elseif ( 'test_ok' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved. Test push sent successfully.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php elseif ( 'test_fail' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Settings saved, but the test push failed — see the Logs tab for the reason.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php elseif ( 'log_cleared' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Log cleared.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $active === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php
			switch ( $active ) {
				case 'status':
					$this->render_status_tab();
					break;
				case 'logs':
					$this->render_logs_tab();
					break;
				default:
					$this->render_general_tab();
			}
			?>
		</div>

		<style>
			.aaraa-fcm .aaraa-tpl-table { margin: 8px 0 24px; background: #fff; border: 1px solid #c3c4c7; border-collapse: collapse; width: 100%; max-width: 1080px; }
			.aaraa-fcm .aaraa-tpl-table th, .aaraa-fcm .aaraa-tpl-table td { border: 1px solid #e2e4e7; padding: 8px 10px; vertical-align: top; text-align: left; }
			.aaraa-fcm .aaraa-tpl-table thead th { background: #f6f7f7; }
			.aaraa-fcm .aaraa-tpl-table td.status-col { white-space: nowrap; font-weight: 600; }
			.aaraa-fcm .aaraa-tpl-table textarea { width: 100%; }
			.aaraa-fcm .aaraa-tpl-hint { color: #646970; font-size: 11px; }
			.aaraa-fcm .aaraa-log-toolbar { margin: 8px 0; display: flex; align-items: center; gap: 8px; }
			.aaraa-fcm .aaraa-log-table td { font-size: 12px; word-break: break-word; }
			.aaraa-fcm .aaraa-log-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
			.aaraa-fcm .aaraa-log-badge.is-sent { background: #e5f5ec; color: #1a7f45; }
			.aaraa-fcm .aaraa-log-badge.is-failed { background: #fbeaea; color: #b32d2e; }
			.aaraa-fcm code.rest-url { background:#f6f7f7; padding:2px 6px; border-radius:4px; }
		</style>
		<?php
	}

	/**
	 * General tab — Firebase credentials + connection test.
	 *
	 * @return void
	 */
	private function render_general_tab() {
		$s       = self::settings();
		$sa      = self::service_account();
		$rest_reg = rest_url( self::REST_NS . '/fcm/register' );
		?>
		<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'general' ), admin_url( 'admin.php' ) ) ); ?>">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTION ); ?>" value="1" />
			<input type="hidden" name="aaraa_tab" value="general" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Notification', 'aaraa-white-label-admin' ); ?></th>
					<td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Send in-app push on order / subscription status changes', 'aaraa-white-label-admin' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Service Provider', 'aaraa-white-label-admin' ); ?></th>
					<td>
						<label><input type="radio" checked disabled /> <?php esc_html_e( 'Firebase Cloud Messaging (HTTP v1)', 'aaraa-white-label-admin' ); ?></label>
						<?php if ( $sa && ! empty( $sa['project_id'] ) ) : ?>
							<p class="description"><?php printf( esc_html__( 'Service account: %1$s (project %2$s)', 'aaraa-white-label-admin' ), '<code>' . esc_html( $sa['client_email'] ) . '</code>', '<code>' . esc_html( $sa['project_id'] ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fcm-project"><?php esc_html_e( 'Firebase Project ID', 'aaraa-white-label-admin' ); ?></label></th>
					<td>
						<input name="project_id" id="fcm-project" type="text" class="regular-text" value="<?php echo esc_attr( $s['project_id'] ); ?>" placeholder="my-firebase-project" />
						<p class="description"><?php esc_html_e( 'Optional — taken from the service account JSON if left blank.', 'aaraa-white-label-admin' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fcm-sa"><?php esc_html_e( 'Service Account JSON', 'aaraa-white-label-admin' ); ?></label></th>
					<td>
						<textarea name="service_account" id="fcm-sa" rows="8" class="large-text code" autocomplete="off" placeholder='{ "type": "service_account", "project_id": "...", "private_key": "-----BEGIN PRIVATE KEY-----\n...", "client_email": "...@....iam.gserviceaccount.com" }'><?php echo esc_textarea( $s['service_account'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Firebase Console → Project settings → Service accounts → Generate new private key. Paste the whole JSON file here.', 'aaraa-white-label-admin' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fcm-test"><?php esc_html_e( 'Send Test To Device Token', 'aaraa-white-label-admin' ); ?></label></th>
					<td>
						<input name="test_token" id="fcm-test" type="text" class="large-text code" placeholder="<?php esc_attr_e( 'Paste a device FCM token to send a test on save', 'aaraa-white-label-admin' ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional — fill in to send a test push when you save.', 'aaraa-white-label-admin' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save In-app Settings', 'aaraa-white-label-admin' ) ); ?>
		</form>

		<hr />
		<h2 class="title"><?php esc_html_e( 'Device Registration (for the mobile app)', 'aaraa-white-label-admin' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The app must send each signed-in user\'s FCM token to this endpoint (WooCommerce/JWT auth). Stale tokens are pruned automatically.', 'aaraa-white-label-admin' ); ?></p>
		<p>
			<?php esc_html_e( 'Register:', 'aaraa-white-label-admin' ); ?>
			<code class="rest-url">POST <?php echo esc_html( $rest_reg ); ?></code>
			<?php esc_html_e( 'body:', 'aaraa-white-label-admin' ); ?> <code>{ "token": "&lt;fcm_token&gt;", "platform": "android|ios" }</code>
		</p>
		<p>
			<?php esc_html_e( 'Unregister:', 'aaraa-white-label-admin' ); ?>
			<code class="rest-url">POST <?php echo esc_html( rest_url( self::REST_NS . '/fcm/unregister' ) ); ?></code>
			<?php esc_html_e( 'body:', 'aaraa-white-label-admin' ); ?> <code>{ "token": "&lt;fcm_token&gt;" }</code>
		</p>
		<?php
	}

	/**
	 * Status Config tab.
	 *
	 * @return void
	 */
	private function render_status_tab() {
		$s              = self::settings();
		$order_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$sub_statuses   = function_exists( 'wcs_get_subscription_statuses' ) ? wcs_get_subscription_statuses() : array();
		?>
		<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'status' ), admin_url( 'admin.php' ) ) ); ?>">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTION ); ?>" value="1" />
			<input type="hidden" name="aaraa_tab" value="status" />

			<?php
			$this->render_status_group(
				__( 'Order Status Notifications', 'aaraa-white-label-admin' ),
				'order',
				$order_statuses,
				$s['order'],
				'{order_id}, {status}, {customer_name}, {first_name}, {total}, {currency}, {site}'
			);
			$this->render_status_group(
				__( 'Subscription Status Notifications', 'aaraa-white-label-admin' ),
				'subscription',
				$sub_statuses,
				$s['subscription'],
				'{subscription_id}, {status}, {customer_name}, {first_name}, {total}, {currency}, {site}'
			);
			?>

			<?php submit_button( __( 'Save Status Config', 'aaraa-white-label-admin' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render a per-status title/body table (order / subscription).
	 *
	 * @param string               $title    Section title.
	 * @param string               $group    order|subscription.
	 * @param array<string,string> $statuses status key => label.
	 * @param array<string,mixed>  $saved    Saved rows for this group.
	 * @param string               $tokens   Tokens hint string.
	 * @return void
	 */
	private function render_status_group( $title, $group, $statuses, $saved, $tokens ) {
		?>
		<h2 class="title"><?php echo esc_html( $title ); ?></h2>
		<p class="aaraa-tpl-hint description"><?php printf( esc_html__( 'Available tokens: %s', 'aaraa-white-label-admin' ), esc_html( $tokens ) ); ?></p>
		<table class="aaraa-tpl-table">
			<thead>
				<tr>
					<th style="width:60px;"><?php esc_html_e( 'Enable', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:280px;"><?php esc_html_e( 'Title', 'aaraa-white-label-admin' ); ?></th>
					<th><?php esc_html_e( 'Body', 'aaraa-white-label-admin' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<?php
				$key = str_replace( 'wc-', '', $key );
				$row = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array( 'enabled' => 0, 'title' => '', 'body' => '' );
				?>
				<tr>
					<td style="text-align:center;">
						<input type="checkbox" name="<?php echo esc_attr( $group ); ?>[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> />
					</td>
					<td class="status-col"><?php echo esc_html( $label ); ?><br /><code><?php echo esc_html( $key ); ?></code></td>
					<td><input type="text" class="widefat" name="<?php echo esc_attr( $group ); ?>[<?php echo esc_attr( $key ); ?>][title]" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="<?php esc_attr_e( 'Order {order_id} update', 'aaraa-white-label-admin' ); ?>" /></td>
					<td><textarea name="<?php echo esc_attr( $group ); ?>[<?php echo esc_attr( $key ); ?>][body]" rows="2"><?php echo esc_textarea( $row['body'] ); ?></textarea></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Logs tab.
	 *
	 * @return void
	 */
	private function render_logs_tab() {
		$log    = self::get_log();
		$filter = isset( $_GET['log_type'] ) ? sanitize_key( wp_unslash( $_GET['log_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$types = array(
			''             => __( 'All types', 'aaraa-white-label-admin' ),
			'order'        => __( 'Order', 'aaraa-white-label-admin' ),
			'subscription' => __( 'Subscription', 'aaraa-white-label-admin' ),
			'test'         => __( 'Test', 'aaraa-white-label-admin' ),
		);

		$rows = $log;
		if ( '' !== $filter ) {
			$rows = array_values(
				array_filter(
					$log,
					static function ( $r ) use ( $filter ) {
						return isset( $r['type'] ) && $r['type'] === $filter;
					}
				)
			);
		}
		?>
		<p class="description"><?php esc_html_e( 'Every push attempt with the Firebase response. Device tokens are shown truncated.', 'aaraa-white-label-admin' ); ?></p>

		<div class="aaraa-log-toolbar">
			<form method="get" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<input type="hidden" name="tab" value="logs" />
				<select name="log_type" onchange="this.form.submit()">
					<?php foreach ( $types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filter ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</form>
			<?php if ( ! empty( $log ) ) : ?>
				<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'logs' ), admin_url( 'admin.php' ) ) ); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the entire push log?', 'aaraa-white-label-admin' ) ); ?>');">
					<?php wp_nonce_field( self::LOG_CLEAR ); ?>
					<input type="hidden" name="<?php echo esc_attr( self::LOG_CLEAR ); ?>" value="1" />
					<?php submit_button( __( 'Clear Log', 'aaraa-white-label-admin' ), 'delete', '', false ); ?>
				</form>
			<?php endif; ?>
		</div>

		<table class="aaraa-tpl-table aaraa-log-table">
			<thead>
				<tr>
					<th style="width:150px;"><?php esc_html_e( 'Time', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Type', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'Reference', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'User', 'aaraa-white-label-admin' ); ?></th>
					<th><?php esc_html_e( 'Title / body', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'Device', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Result', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:240px;"><?php esc_html_e( 'Response', 'aaraa-white-label-admin' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No push activity logged yet.', 'aaraa-white-label-admin' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $r ) : ?>
					<?php
					$is_sent = 'sent' === ( $r['result'] ?? '' );
					$status  = isset( $r['status'] ) ? $r['status'] : '';
					?>
					<tr>
						<td><?php echo esc_html( $r['time'] ?? '' ); ?></td>
						<td><code><?php echo esc_html( $r['type'] ?? '' ); ?></code></td>
						<td>
							<?php echo ! empty( $r['ref'] ) ? esc_html( $r['ref'] ) : '&mdash;'; ?>
							<?php if ( $status ) : ?><br /><span class="aaraa-tpl-hint"><?php echo esc_html( $status ); ?></span><?php endif; ?>
						</td>
						<td><?php echo ! empty( $r['user_id'] ) ? esc_html( $r['user_id'] ) : '&mdash;'; ?></td>
						<td>
							<?php echo ! empty( $r['title'] ) ? '<strong>' . esc_html( $r['title'] ) . '</strong>' : ''; ?>
							<?php if ( ! empty( $r['message'] ) ) : ?><br /><span class="aaraa-tpl-hint"><?php echo esc_html( $r['message'] ); ?></span><?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $r['token'] ?? '' ); ?></code></td>
						<td><span class="aaraa-log-badge <?php echo $is_sent ? 'is-sent' : 'is-failed'; ?>"><?php echo esc_html( $is_sent ? __( 'Sent', 'aaraa-white-label-admin' ) : __( 'Failed', 'aaraa-white-label-admin' ) ); ?></span></td>
						<td><?php echo esc_html( $r['response'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}
}
