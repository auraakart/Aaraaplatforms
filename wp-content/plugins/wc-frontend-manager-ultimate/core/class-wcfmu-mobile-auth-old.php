<?php
/**
 * WCFMu plugin core
 *
 * Mobile OTP Login & Register — REST API + frontend shortcodes.
 *
 * Provider: custom_sms (server-side OTP via HTTP) or firebase (client-side
 * Firebase Phone Auth with server-side idToken verification).
 * JWT: HS256 signed with JWT_AUTH_SECRET_KEY from wp-config.php.
 *
 * Settings per vendor stored as user_meta (wcfmu_otp_*); fall back to
 * global wp_options when vendor meta is empty.
 *
 * REST endpoints (namespace aaraakart/v1):
 *   POST /auth/otp/send    — send OTP or return Firebase config
 *   POST /auth/login       — verify OTP / Firebase token → JWT
 *   POST /auth/register    — verify OTP / Firebase token → create customer → JWT
 *   GET  /auth/me          — return authenticated user profile
 *
 * Shortcodes:
 *   [wcfmu_mobile_login    vendor_id="0" redirect="/my-account/"]
 *   [wcfmu_mobile_register vendor_id="0" redirect="/my-account/"]
 *
 * @author  Aaraakart
 * @package wcfmu/core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WCFMu_Mobile_Auth {

	const NS          = 'aaraakart/v1';
	const OTP_PREFIX  = 'wcfmu_otp_';
	const RATE_PREFIX = 'wcfmu_rate_';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_shortcode( 'wcfmu_mobile_login',    [ $this, 'shortcode_login' ] );
		add_shortcode( 'wcfmu_mobile_register', [ $this, 'shortcode_register' ] );

		// Vendor profile panel + save
		add_action( 'end_wcfm_vendors_manage_form',          [ $this, 'vendor_otp_settings_panel' ], 10, 2 );
		add_action( 'wp_ajax_wcfmu_vendor_otp_settings_save', [ $this, 'ajax_save_vendor_otp_settings' ] );

		// Global admin settings page
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_post_wcfmu_otp_global_save', [ $this, 'save_global_settings' ] );
	}

	// ── Settings ─────────────────────────────────────────────────────────────

	/**
	 * Read a setting. Vendor user_meta takes priority over global wp_option.
	 * Pass vendor_id = 0 to use global only.
	 */
	private function get_setting( $key, $default = '', $vendor_id = 0 ) {
		if ( $vendor_id > 0 ) {
			$val = get_user_meta( (int) $vendor_id, 'wcfmu_otp_' . $key, true );
			if ( $val !== '' && $val !== false ) {
				return $val;
			}
		}
		return get_option( 'wcfmu_otp_' . $key, $default );
	}

	private function provider( $vendor_id = 0 ) {
		return $this->get_setting( 'provider', 'custom_sms', $vendor_id );
	}

	// ── Phone normalisation ──────────────────────────────────────────────────

	private function normalize_phone( $raw ) {
		return preg_replace( '/\D/', '', (string) $raw );
	}

	private function validate_phone( $phone ) {
		// 10–15 digits (covers Indian 10-digit and international E.164 without +).
		return (bool) preg_match( '/^\d{10,15}$/', $phone );
	}

	// ── OTP management ───────────────────────────────────────────────────────

	private function generate_otp( $vendor_id = 0 ) {
		$len = max( 4, min( 8, (int) $this->get_setting( 'length', 6, $vendor_id ) ) );
		$max = (int) str_repeat( '9', $len );
		$min = (int) ( '1' . str_repeat( '0', $len - 1 ) );
		return str_pad( (string) random_int( $min, $max ), $len, '0', STR_PAD_LEFT );
	}

	private function otp_key( $phone ) {
		return self::OTP_PREFIX . md5( $phone );
	}

	private function store_otp( $phone, $otp, $action, $vendor_id = 0 ) {
		$expiry = (int) $this->get_setting( 'expiry', 300, $vendor_id );
		set_transient( $this->otp_key( $phone ), wp_json_encode( [
			'otp'     => $otp,
			'action'  => $action,
			'created' => time(),
		] ), $expiry );
	}

	private function validate_otp( $phone, $otp, $vendor_id = 0 ) {
		$raw = get_transient( $this->otp_key( $phone ) );
		if ( ! $raw ) return false;

		$payload = json_decode( $raw, true );
		if ( ! isset( $payload['otp'], $payload['created'] ) ) return false;

		$expiry = (int) $this->get_setting( 'expiry', 300, $vendor_id );
		if ( time() - (int) $payload['created'] > $expiry ) {
			delete_transient( $this->otp_key( $phone ) );
			return false;
		}

		if ( hash_equals( (string) $payload['otp'], (string) $otp ) ) {
			delete_transient( $this->otp_key( $phone ) ); // single-use
			return true;
		}

		return false;
	}

	private function rate_allowed( $phone, $vendor_id = 0 ) {
		$limit = (int) $this->get_setting( 'rate_limit', 3, $vendor_id );
		$count = (int) get_transient( self::RATE_PREFIX . md5( $phone ) );
		return $count < $limit;
	}

	private function rate_increment( $phone ) {
		$key   = self::RATE_PREFIX . md5( $phone );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, 600 ); // 10-minute window
	}

	// ── OTP delivery ─────────────────────────────────────────────────────────

	private function send_via_custom_sms( $phone, $otp, $vendor_id = 0 ) {
		$url    = $this->get_setting( 'sms_url', '', $vendor_id );
		$method = strtoupper( $this->get_setting( 'sms_method', 'POST', $vendor_id ) );
		$hdrs   = json_decode( $this->get_setting( 'sms_headers', '{}', $vendor_id ), true ) ?: [];
		$body   = $this->get_setting( 'sms_body', '', $vendor_id );

		$body = str_replace( [ '{phone}', '{otp}' ], [ $phone, $otp ], $body );
		$url  = str_replace( [ '{phone}', '{otp}' ], [ $phone, $otp ], $url );

		if ( empty( $url ) ) return false;

		$args = [
			'method'  => $method,
			'headers' => $hdrs,
			'timeout' => 15,
		];

		if ( $method === 'POST' ) {
			$args['body'] = $body;
		}

		$resp = wp_remote_request( $url, $args );
		return ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) < 400;
	}

	private function verify_firebase_token( $id_token, $vendor_id = 0 ) {
		$api_key = $this->get_setting( 'firebase_api_key', '', $vendor_id );
		if ( empty( $api_key ) ) return null;

		$resp = wp_remote_post(
			'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode( $api_key ),
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'idToken' => $id_token ] ),
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $resp ) ) return null;
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		return isset( $data['users'][0]['phoneNumber'] ) ? $data['users'][0]['phoneNumber'] : null;
	}

	// ── JWT (HS256) ──────────────────────────────────────────────────────────

	private function b64u( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private function jwt_encode( $payload ) {
		$header = $this->b64u( wp_json_encode( [ 'typ' => 'JWT', 'alg' => 'HS256' ] ) );
		$body   = $this->b64u( wp_json_encode( $payload ) );
		$sig    = $this->b64u( hash_hmac( 'sha256', "{$header}.{$body}", JWT_AUTH_SECRET_KEY, true ) );
		return "{$header}.{$body}.{$sig}";
	}

	private function jwt_decode( $token ) {
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 3 ) return null;

		list( $header, $body, $sig ) = $parts;
		$expected = $this->b64u( hash_hmac( 'sha256', "{$header}.{$body}", JWT_AUTH_SECRET_KEY, true ) );
		if ( ! hash_equals( $expected, $sig ) ) return null;

		$payload = json_decode( base64_decode( strtr( $body, '-_', '+/' ) . '==' ) );
		if ( ! $payload || ! isset( $payload->exp ) || time() > $payload->exp ) return null;

		return $payload;
	}

	private function generate_jwt( $user_id ) {
		$issued = time();
		return $this->jwt_encode( [
			'iss'  => get_bloginfo( 'url' ),
			'iat'  => $issued,
			'nbf'  => $issued,
			'exp'  => $issued + DAY_IN_SECONDS,
			'data' => [ 'user' => [ 'id' => (int) $user_id ] ],
		] );
	}

	// ── User helpers ─────────────────────────────────────────────────────────

	private function find_user_by_phone( $phone ) {
		$users = get_users( [
			'meta_key'     => 'billing_phone',
			'meta_value'   => $phone,
			'meta_compare' => '=',
			'number'       => 1,
			'count_total'  => false,
		] );
		return ! empty( $users ) ? $users[0] : null;
	}

	private function user_data( $user_id, $phone ) {
		$user  = get_userdata( $user_id );
		$fname = get_user_meta( $user_id, 'billing_first_name', true )
			  ?: get_user_meta( $user_id, 'first_name', true );
		$lname = get_user_meta( $user_id, 'billing_last_name', true )
			  ?: get_user_meta( $user_id, 'last_name', true );
		return [
			'token'      => $this->generate_jwt( $user_id ),
			'user_id'    => $user_id,
			'email'      => $user->user_email,
			'first_name' => (string) $fname,
			'last_name'  => (string) $lname,
			'phone'      => $phone,
			'roles'      => $user->roles,
		];
	}

	// ── REST routes ──────────────────────────────────────────────────────────

	public function register_rest_routes() {
		$vendor_arg = [
			'required'          => false,
			'sanitize_callback' => 'absint',
			'default'           => 0,
			'description'       => 'Vendor user ID — uses that vendor\'s SMS API settings when set.',
		];

		register_rest_route( self::NS, '/auth/otp/send', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'rest_send_otp' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'phone'     => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
				'action'    => [
					'required'          => false,
					'sanitize_callback' => 'sanitize_key',
					'default'           => 'login',
					'enum'              => [ 'login', 'register' ],
				],
				'vendor_id' => $vendor_arg,
			],
		] );

		register_rest_route( self::NS, '/auth/login', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'rest_login' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'phone'             => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
				'otp'               => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
				'firebase_id_token' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
				'vendor_id'         => $vendor_arg,
			],
		] );

		register_rest_route( self::NS, '/auth/register', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'rest_register' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'phone'             => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
				'otp'               => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
				'firebase_id_token' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
				'first_name'        => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
				'last_name'         => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
				'email'             => [ 'required' => false, 'sanitize_callback' => 'sanitize_email',       'default' => '' ],
				'vendor_id'         => $vendor_arg,
			],
		] );

		register_rest_route( self::NS, '/auth/me', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'rest_me' ],
			'permission_callback' => [ $this, 'jwt_permission' ],
		] );
	}

	/**
	 * POST /auth/otp/send
	 */
	public function rest_send_otp( $req ) {
		$phone     = $this->normalize_phone( $req->get_param( 'phone' ) );
		$vendor_id = (int) $req->get_param( 'vendor_id' );

		if ( ! $this->validate_phone( $phone ) ) {
			return new WP_Error( 'invalid_phone', 'Invalid phone number.', [ 'status' => 400 ] );
		}

		if ( ! $this->rate_allowed( $phone, $vendor_id ) ) {
			return new WP_Error( 'rate_limited', 'Too many OTP requests. Please wait 10 minutes.', [ 'status' => 429 ] );
		}

		$provider = $this->provider( $vendor_id );

		if ( $provider === 'firebase' ) {
			return new WP_REST_Response( [
				'success'          => true,
				'mode'             => 'firebase',
				'firebase_api_key' => $this->get_setting( 'firebase_api_key', '', $vendor_id ),
				'expires_in'       => (int) $this->get_setting( 'expiry', 300, $vendor_id ),
			], 200 );
		}

		$otp    = $this->generate_otp( $vendor_id );
		$action = $req->get_param( 'action' ) ?: 'login';
		$this->store_otp( $phone, $otp, $action, $vendor_id );
		$this->rate_increment( $phone );

		$sent = $this->send_via_custom_sms( $phone, $otp, $vendor_id );

		$response = [
			'success'    => true,
			'mode'       => 'custom_sms',
			'expires_in' => (int) $this->get_setting( 'expiry', 300, $vendor_id ),
			'sent'       => $sent,
		];

		// Expose OTP only in debug mode — never in production.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response['debug_otp'] = $otp;
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * POST /auth/login
	 */
	public function rest_login( $req ) {
		$phone     = $this->normalize_phone( $req->get_param( 'phone' ) );
		$otp       = $req->get_param( 'otp' );
		$fb_token  = $req->get_param( 'firebase_id_token' );
		$vendor_id = (int) $req->get_param( 'vendor_id' );

		if ( ! $this->validate_phone( $phone ) ) {
			return new WP_Error( 'invalid_phone', 'Invalid phone number.', [ 'status' => 400 ] );
		}

		if ( $this->provider( $vendor_id ) === 'firebase' ) {
			if ( empty( $fb_token ) ) {
				return new WP_Error( 'missing_token', 'firebase_id_token is required.', [ 'status' => 400 ] );
			}
			if ( ! $this->verify_firebase_token( $fb_token, $vendor_id ) ) {
				return new WP_Error( 'invalid_token', 'Firebase token is invalid or expired.', [ 'status' => 401 ] );
			}
		} else {
			if ( empty( $otp ) ) {
				return new WP_Error( 'missing_otp', 'otp is required.', [ 'status' => 400 ] );
			}
			if ( ! $this->validate_otp( $phone, $otp, $vendor_id ) ) {
				return new WP_Error( 'invalid_otp', 'Invalid or expired OTP.', [ 'status' => 401 ] );
			}
		}

		$user = $this->find_user_by_phone( $phone );
		if ( ! $user ) {
			return new WP_Error( 'needs_registration',
				'No account found for this phone number. Please register.',
				[ 'status' => 401, 'needs_registration' => true ] );
		}

		return new WP_REST_Response( $this->user_data( $user->ID, $phone ), 200 );
	}

	/**
	 * POST /auth/register
	 */
	public function rest_register( $req ) {
		$phone      = $this->normalize_phone( $req->get_param( 'phone' ) );
		$otp        = $req->get_param( 'otp' );
		$fb_token   = $req->get_param( 'firebase_id_token' );
		$first_name = trim( (string) $req->get_param( 'first_name' ) );
		$last_name  = trim( (string) $req->get_param( 'last_name' ) );
		$email      = trim( (string) $req->get_param( 'email' ) );
		$vendor_id  = (int) $req->get_param( 'vendor_id' );

		if ( ! $this->validate_phone( $phone ) ) {
			return new WP_Error( 'invalid_phone', 'Invalid phone number.', [ 'status' => 400 ] );
		}

		if ( empty( $first_name ) ) {
			return new WP_Error( 'missing_name', 'first_name is required.', [ 'status' => 400 ] );
		}

		if ( $this->provider( $vendor_id ) === 'firebase' ) {
			if ( empty( $fb_token ) ) {
				return new WP_Error( 'missing_token', 'firebase_id_token is required.', [ 'status' => 400 ] );
			}
			if ( ! $this->verify_firebase_token( $fb_token, $vendor_id ) ) {
				return new WP_Error( 'invalid_token', 'Firebase token is invalid or expired.', [ 'status' => 401 ] );
			}
		} else {
			if ( empty( $otp ) ) {
				return new WP_Error( 'missing_otp', 'otp is required.', [ 'status' => 400 ] );
			}
			if ( ! $this->validate_otp( $phone, $otp, $vendor_id ) ) {
				return new WP_Error( 'invalid_otp', 'Invalid or expired OTP.', [ 'status' => 401 ] );
			}
		}

		if ( $this->find_user_by_phone( $phone ) ) {
			return new WP_Error( 'already_registered',
				'This phone number is already registered. Please log in.',
				[ 'status' => 409 ] );
		}

		if ( empty( $email ) ) {
			$email = $phone . '@' . parse_url( get_bloginfo( 'url' ), PHP_URL_HOST );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Invalid email address.', [ 'status' => 400 ] );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', 'An account with this email already exists.', [ 'status' => 409 ] );
		}

		$username = $phone;
		if ( username_exists( $username ) ) {
			$username = $phone . '_' . substr( (string) time(), -4 );
		}

		$password = wp_generate_password( 16, true );
		$user_id  = wc_create_new_customer( $email, $username, $password, [
			'first_name' => $first_name,
			'last_name'  => $last_name,
		] );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Save phone fields — compatible with xoo-ml plugin.
		update_user_meta( $user_id, 'billing_phone',      $phone );
		update_user_meta( $user_id, 'billing_first_name', $first_name );
		update_user_meta( $user_id, 'billing_last_name',  $last_name );
		update_user_meta( $user_id, 'xoo_ml_phone_no',    $phone );

		return new WP_REST_Response( $this->user_data( $user_id, $phone ), 201 );
	}

	/**
	 * GET /auth/me
	 */
	public function rest_me( $req ) {
		$user_id = get_current_user_id();
		$phone   = get_user_meta( $user_id, 'billing_phone', true );
		return new WP_REST_Response( $this->user_data( $user_id, (string) $phone ), 200 );
	}

	public function jwt_permission( $req ) {
		$auth = $req->get_header( 'authorization' );
		if ( ! $auth || substr( $auth, 0, 7 ) !== 'Bearer ' ) {
			return new WP_Error( 'missing_token', 'Authorization Bearer token required.', [ 'status' => 401 ] );
		}

		$token   = substr( $auth, 7 );
		$payload = $this->jwt_decode( $token );

		if ( ! $payload || empty( $payload->data->user->id ) ) {
			return new WP_Error( 'invalid_token', 'Token is invalid or expired.', [ 'status' => 401 ] );
		}

		wp_set_current_user( (int) $payload->data->user->id );
		return true;
	}

	// ── Vendor OTP Settings Panel ────────────────────────────────────────────

	/**
	 * Renders the OTP/SMS API collapsible section on the vendor manage page.
	 * Hooked to end_wcfm_vendors_manage_form.
	 */
	public function vendor_otp_settings_panel( $vendor_admin_id, $vendor_id ) {
		if ( ! $vendor_id ) return;
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'shop_staff' ) ) return;

		$provider         = get_user_meta( $vendor_id, 'wcfmu_otp_provider',         true );
		$sms_url          = get_user_meta( $vendor_id, 'wcfmu_otp_sms_url',          true );
		$sms_method       = get_user_meta( $vendor_id, 'wcfmu_otp_sms_method',       true ) ?: 'POST';
		$sms_headers      = get_user_meta( $vendor_id, 'wcfmu_otp_sms_headers',      true );
		$sms_body         = get_user_meta( $vendor_id, 'wcfmu_otp_sms_body',         true );
		$firebase_api_key = get_user_meta( $vendor_id, 'wcfmu_otp_firebase_api_key', true );
		$otp_length       = get_user_meta( $vendor_id, 'wcfmu_otp_length',           true );
		$otp_expiry       = get_user_meta( $vendor_id, 'wcfmu_otp_expiry',           true );
		$nonce            = wp_create_nonce( 'wcfmu_vendor_otp_settings_' . $vendor_id );
		?>

		<!-- OTP / SMS API Settings collapsible -->
		<div class="page_collapsible vendor_manage_otp_api" id="wcfmu_vendor_otp_settings_head">
			<label class="wcfmfa fa-mobile-alt"></label><?php _e( 'OTP / SMS API Settings', 'wc-frontend-manager-ultimate' ); ?><span></span>
		</div>
		<div class="wcfm-container">
			<div id="wcfmu_vendor_otp_settings_expander" class="wcfm-content">
				<form id="wcfmu_vendor_otp_settings_form" class="wcfm">
					<input type="hidden" id="wcfmu_otp_vendor_id" value="<?php echo esc_attr( $vendor_id ); ?>" />
					<input type="hidden" id="wcfmu_otp_nonce"     value="<?php echo esc_attr( $nonce ); ?>" />

					<p class="wcfm_ele wcfm_title"><strong><?php _e( 'OTP Provider', 'wc-frontend-manager-ultimate' ); ?></strong>
					<small style="font-weight:normal;color:#6b7280">&nbsp;<?php _e( '(leave blank to use global default)', 'wc-frontend-manager-ultimate' ); ?></small></p>
					<select name="wcfmu_otp_provider" id="wcfmu_otp_provider" class="wcfm-select wcfm_ele" style="width:220px">
						<option value=""          <?php selected( $provider, '' ); ?>><?php _e( '— Global default —', 'wc-frontend-manager-ultimate' ); ?></option>
						<option value="custom_sms"<?php selected( $provider, 'custom_sms' ); ?>><?php _e( 'Custom SMS API', 'wc-frontend-manager-ultimate' ); ?></option>
						<option value="firebase"  <?php selected( $provider, 'firebase' ); ?>><?php _e( 'Firebase Phone Auth', 'wc-frontend-manager-ultimate' ); ?></option>
					</select>
					<div class="wcfm_clearfix"></div><br />

					<!-- Custom SMS fields -->
					<div id="wcfmu_otp_custom_sms_fields">
						<h3 style="margin:0 0 10px;font-size:1em"><?php _e( 'Custom SMS API', 'wc-frontend-manager-ultimate' ); ?></h3>

						<p class="wcfm_ele wcfm_title"><?php _e( 'API Endpoint URL', 'wc-frontend-manager-ultimate' ); ?>
						<small style="color:#6b7280"><?php _e( 'Use {phone} and {otp} as placeholders', 'wc-frontend-manager-ultimate' ); ?></small></p>
						<input type="text" name="wcfmu_otp_sms_url" class="wcfm-text wcfm_ele" style="width:100%;max-width:480px"
							placeholder="https://api.yourprovider.com/send?to={phone}&msg={otp}"
							value="<?php echo esc_attr( $sms_url ); ?>" />
						<div class="wcfm_clearfix"></div>

						<p class="wcfm_ele wcfm_title" style="margin-top:12px"><?php _e( 'HTTP Method', 'wc-frontend-manager-ultimate' ); ?></p>
						<select name="wcfmu_otp_sms_method" class="wcfm-select wcfm_ele" style="width:120px">
							<option value="POST" <?php selected( $sms_method, 'POST' ); ?>>POST</option>
							<option value="GET"  <?php selected( $sms_method, 'GET' ); ?>>GET</option>
						</select>
						<div class="wcfm_clearfix"></div>

						<p class="wcfm_ele wcfm_title" style="margin-top:12px"><?php _e( 'Request Headers', 'wc-frontend-manager-ultimate' ); ?>
						<small style="color:#6b7280"><?php _e( 'JSON object, e.g. {"Authorization":"Bearer TOKEN"}', 'wc-frontend-manager-ultimate' ); ?></small></p>
						<input type="text" name="wcfmu_otp_sms_headers" class="wcfm-text wcfm_ele" style="width:100%;max-width:480px"
							placeholder='{"Authorization":"Bearer YOUR_TOKEN","Content-Type":"application/json"}'
							value="<?php echo esc_attr( $sms_headers ); ?>" />
						<div class="wcfm_clearfix"></div>

						<p class="wcfm_ele wcfm_title" style="margin-top:12px"><?php _e( 'Request Body', 'wc-frontend-manager-ultimate' ); ?>
						<small style="color:#6b7280"><?php _e( 'JSON string or query-string. Use {phone} and {otp}.', 'wc-frontend-manager-ultimate' ); ?></small></p>
						<textarea name="wcfmu_otp_sms_body" class="wcfm-textarea wcfm_ele" rows="3" style="width:100%;max-width:480px;font-family:monospace"
							placeholder='{"mobile":"{phone}","message":"Your OTP is {otp}"}'><?php echo esc_textarea( $sms_body ); ?></textarea>
						<div class="wcfm_clearfix"></div>
					</div>

					<!-- Firebase fields -->
					<div id="wcfmu_otp_firebase_fields">
						<h3 style="margin:0 0 10px;font-size:1em"><?php _e( 'Firebase Phone Auth', 'wc-frontend-manager-ultimate' ); ?></h3>

						<p class="wcfm_ele wcfm_title"><?php _e( 'Firebase Web API Key', 'wc-frontend-manager-ultimate' ); ?>
						<small style="color:#6b7280"><?php _e( 'Found in Firebase Console → Project Settings → Web API Key', 'wc-frontend-manager-ultimate' ); ?></small></p>
						<input type="text" name="wcfmu_otp_firebase_api_key" class="wcfm-text wcfm_ele" style="width:100%;max-width:400px"
							placeholder="AIzaSy..."
							value="<?php echo esc_attr( $firebase_api_key ); ?>" />
						<div class="wcfm_clearfix"></div>
					</div>

					<!-- General OTP settings -->
					<h3 style="margin:18px 0 10px;font-size:1em"><?php _e( 'OTP Settings', 'wc-frontend-manager-ultimate' ); ?></h3>

					<p class="wcfm_ele wcfm_title"><?php _e( 'OTP Length', 'wc-frontend-manager-ultimate' ); ?>
					<small style="color:#6b7280"><?php _e( '4 – 8 digits, leave blank to use global (6)', 'wc-frontend-manager-ultimate' ); ?></small></p>
					<input type="number" name="wcfmu_otp_length" class="wcfm-text wcfm_ele" style="width:80px"
						min="4" max="8" placeholder="6"
						value="<?php echo esc_attr( $otp_length ); ?>" />
					<div class="wcfm_clearfix"></div>

					<p class="wcfm_ele wcfm_title" style="margin-top:12px"><?php _e( 'OTP Expiry', 'wc-frontend-manager-ultimate' ); ?>
					<small style="color:#6b7280"><?php _e( 'Seconds. Leave blank to use global (300 = 5 min)', 'wc-frontend-manager-ultimate' ); ?></small></p>
					<input type="number" name="wcfmu_otp_expiry" class="wcfm-text wcfm_ele" style="width:100px"
						min="60" max="900" placeholder="300"
						value="<?php echo esc_attr( $otp_expiry ); ?>" />
					<div class="wcfm_clearfix"></div><br />

					<div class="wcfm-message" tabindex="-1" id="wcfmu_otp_settings_msg" style="display:none"></div>
					<div class="wcfm_clearfix"></div>
					<input type="button" id="wcfmu_vendor_otp_settings_save_btn"
						value="<?php esc_attr_e( 'Save OTP Settings', 'wc-frontend-manager-ultimate' ); ?>"
						class="wcfm_submit_button" />
					<div class="wcfm_clearfix"></div>
				</form>
			</div>
		</div>
		<div class="wcfm_clearfix"></div><br />
		<!-- end OTP settings collapsible -->

		<script>
		(function($){
			// Toggle visible panel based on provider select
			function wcfmuOtpToggle(){
				var val = $('#wcfmu_otp_provider').val();
				$('#wcfmu_otp_custom_sms_fields').toggle( val !== 'firebase' );
				$('#wcfmu_otp_firebase_fields').toggle( val === 'firebase' );
			}
			wcfmuOtpToggle();
			$('#wcfmu_otp_provider').on('change', wcfmuOtpToggle);

			// Save via AJAX
			$('#wcfmu_vendor_otp_settings_save_btn').on('click', function(){
				var $btn  = $(this).prop('disabled', true).val('<?php echo esc_js( __( 'Saving…', 'wc-frontend-manager-ultimate' ) ); ?>');
				var $msg  = $('#wcfmu_otp_settings_msg');
				var $form = $('#wcfmu_vendor_otp_settings_form');

				$msg.hide().removeClass('wcfm-success wcfm-error');

				$.post(
					(typeof wcfm_params !== 'undefined' ? wcfm_params.ajax_url : ajaxurl),
					{
						action            : 'wcfmu_vendor_otp_settings_save',
						vendor_id         : $('#wcfmu_otp_vendor_id').val(),
						nonce             : $('#wcfmu_otp_nonce').val(),
						wcfmu_otp_provider         : $form.find('[name="wcfmu_otp_provider"]').val(),
						wcfmu_otp_sms_url          : $form.find('[name="wcfmu_otp_sms_url"]').val(),
						wcfmu_otp_sms_method       : $form.find('[name="wcfmu_otp_sms_method"]').val(),
						wcfmu_otp_sms_headers      : $form.find('[name="wcfmu_otp_sms_headers"]').val(),
						wcfmu_otp_sms_body         : $form.find('[name="wcfmu_otp_sms_body"]').val(),
						wcfmu_otp_firebase_api_key : $form.find('[name="wcfmu_otp_firebase_api_key"]').val(),
						wcfmu_otp_length           : $form.find('[name="wcfmu_otp_length"]').val(),
						wcfmu_otp_expiry           : $form.find('[name="wcfmu_otp_expiry"]').val()
					},
					function(resp){
						if( resp && resp.success ){
							$msg.addClass('wcfm-success').text(resp.data.message).show();
						} else {
							var msg = (resp && resp.data && resp.data.message) ? resp.data.message : '<?php echo esc_js( __( 'Save failed.', 'wc-frontend-manager-ultimate' ) ); ?>';
							$msg.addClass('wcfm-error').text(msg).show();
						}
						$btn.prop('disabled', false).val('<?php echo esc_js( __( 'Save OTP Settings', 'wc-frontend-manager-ultimate' ) ); ?>');
					},
					'json'
				);
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * AJAX: save vendor OTP settings (wp_ajax_wcfmu_vendor_otp_settings_save).
	 */
	public function ajax_save_vendor_otp_settings() {
		$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;

		if ( ! $vendor_id ) {
			wp_send_json_error( [ 'message' => 'Invalid vendor.' ] );
		}

		if ( ! check_ajax_referer( 'wcfmu_vendor_otp_settings_' . $vendor_id, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Refresh and try again.' ] );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'shop_staff' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to do this.' ] );
		}

		$fields = [
			'wcfmu_otp_provider',
			'wcfmu_otp_sms_url',
			'wcfmu_otp_sms_method',
			'wcfmu_otp_sms_headers',
			'wcfmu_otp_sms_body',
			'wcfmu_otp_firebase_api_key',
			'wcfmu_otp_length',
			'wcfmu_otp_expiry',
		];

		foreach ( $fields as $field ) {
			$raw = isset( $_POST[ $field ] ) ? $_POST[ $field ] : '';

			switch ( $field ) {
				case 'wcfmu_otp_provider':
					$val = sanitize_key( $raw );
					if ( ! in_array( $val, [ '', 'custom_sms', 'firebase' ], true ) ) $val = '';
					break;
				case 'wcfmu_otp_sms_method':
					$val = in_array( strtoupper( $raw ), [ 'GET', 'POST' ], true ) ? strtoupper( $raw ) : 'POST';
					break;
				case 'wcfmu_otp_length':
					$val = $raw !== '' ? (string) max( 4, min( 8, absint( $raw ) ) ) : '';
					break;
				case 'wcfmu_otp_expiry':
					$val = $raw !== '' ? (string) max( 60, min( 900, absint( $raw ) ) ) : '';
					break;
				case 'wcfmu_otp_sms_headers':
					// Must be valid JSON or empty
					$val = sanitize_text_field( $raw );
					if ( $val !== '' && json_decode( $val ) === null ) {
						wp_send_json_error( [ 'message' => 'Request Headers must be a valid JSON object.' ] );
					}
					break;
				case 'wcfmu_otp_sms_body':
					$val = wp_unslash( $raw ); // allow JSON content
					$val = sanitize_textarea_field( $val );
					break;
				case 'wcfmu_otp_sms_url':
					$val = esc_url_raw( trim( $raw ) );
					break;
				default:
					$val = sanitize_text_field( $raw );
			}

			if ( $val !== '' ) {
				update_user_meta( $vendor_id, $field, $val );
			} else {
				delete_user_meta( $vendor_id, $field ); // empty = use global default
			}
		}

		wp_send_json_success( [ 'message' => __( 'OTP settings saved successfully.', 'wc-frontend-manager-ultimate' ) ] );
	}

	// ── Global Admin Settings Page ───────────────────────────────────────────

	public function register_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Mobile OTP Settings', 'wc-frontend-manager-ultimate' ),
			__( 'Mobile OTP', 'wc-frontend-manager-ultimate' ),
			'manage_woocommerce',
			'wcfmu-mobile-otp',
			[ $this, 'render_admin_settings_page' ]
		);
	}

	public function render_admin_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wc-frontend-manager-ultimate' ) );
		}

		$provider         = get_option( 'wcfmu_otp_provider', 'custom_sms' );
		$firebase_api_key = get_option( 'wcfmu_otp_firebase_api_key', '' );
		$sms_url          = get_option( 'wcfmu_otp_sms_url', '' );
		$sms_method       = get_option( 'wcfmu_otp_sms_method', 'POST' );
		$sms_headers      = get_option( 'wcfmu_otp_sms_headers', '{}' );
		$sms_body         = get_option( 'wcfmu_otp_sms_body', '' );
		$otp_length       = get_option( 'wcfmu_otp_length', '6' );
		$otp_expiry       = get_option( 'wcfmu_otp_expiry', '300' );
		$rate_limit       = get_option( 'wcfmu_otp_rate_limit', '3' );

		$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
		?>
		<div class="wrap">
			<h1><?php _e( 'Mobile OTP Settings', 'wc-frontend-manager-ultimate' ); ?></h1>
			<p style="color:#6b7280"><?php _e( 'Global defaults for mobile OTP login/register. These apply to all vendors unless overridden on the vendor profile page.', 'wc-frontend-manager-ultimate' ); ?></p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php _e( 'Settings saved.', 'wc-frontend-manager-ultimate' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wcfmu_otp_global_save" />
				<?php wp_nonce_field( 'wcfmu_otp_global_save', 'wcfmu_otp_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcfmu_otp_provider"><?php _e( 'OTP Provider', 'wc-frontend-manager-ultimate' ); ?></label></th>
						<td>
							<select name="wcfmu_otp_provider" id="wcfmu_otp_provider" class="regular-text">
								<option value="firebase"   <?php selected( $provider, 'firebase' ); ?>><?php _e( 'Firebase Phone Auth', 'wc-frontend-manager-ultimate' ); ?></option>
								<option value="custom_sms" <?php selected( $provider, 'custom_sms' ); ?>><?php _e( 'Custom SMS API', 'wc-frontend-manager-ultimate' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<!-- Firebase section -->
				<div id="wcfmu_admin_firebase_fields" style="<?php echo $provider !== 'firebase' ? 'display:none' : ''; ?>">
					<h2><?php _e( 'Firebase Phone Auth', 'wc-frontend-manager-ultimate' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wcfmu_otp_firebase_api_key"><?php _e( 'Firebase Web API Key', 'wc-frontend-manager-ultimate' ); ?></label></th>
							<td>
								<input type="text" name="wcfmu_otp_firebase_api_key" id="wcfmu_otp_firebase_api_key"
									class="regular-text" value="<?php echo esc_attr( $firebase_api_key ); ?>"
									placeholder="AIzaSy..." />
								<p class="description"><?php _e( 'Firebase Console → Project Settings → General → Web API Key', 'wc-frontend-manager-ultimate' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Custom SMS section -->
				<div id="wcfmu_admin_sms_fields" style="<?php echo $provider === 'firebase' ? 'display:none' : ''; ?>">
					<h2><?php _e( 'Custom SMS API', 'wc-frontend-manager-ultimate' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wcfmu_otp_sms_url"><?php _e( 'API URL', 'wc-frontend-manager-ultimate' ); ?></label></th>
							<td>
								<input type="url" name="wcfmu_otp_sms_url" id="wcfmu_otp_sms_url"
									class="large-text" value="<?php echo esc_attr( $sms_url ); ?>"
									placeholder="https://api.smsprovider.com/send?key=KEY&to={phone}&msg={otp}" />
								<p class="description"><?php _e( 'Use {phone} and {otp} as placeholders.', 'wc-frontend-manager-ultimate' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wcfmu_otp_sms_method"><?php _e( 'HTTP Method', 'wc-frontend-manager-ultimate' ); ?></label></th>
							<td>
								<select name="wcfmu_otp_sms_method" id="wcfmu_otp_sms_method">
									<option value="POST" <?php selected( $sms_method, 'POST' ); ?>>POST</option>
									<option value="GET"  <?php selected( $sms_method, 'GET' ); ?>>GET</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wcfmu_otp_sms_headers"><?php _e( 'Request Headers', 'wc-frontend-manager-ultimate' ); ?></label></th>
							<td>
								<textarea name="wcfmu_otp_sms_headers" id="wcfmu_otp_sms_headers"
									rows="3" class="large-text code"
									placeholder='{"Authorization":"Bearer TOKEN","Content-Type":"application/json"}'><?php echo esc_textarea( $sms_headers ); ?></textarea>
								<p class="description"><?php _e( 'JSON object. Leave as {} if no custom headers needed.', 'wc-frontend-manager-ultimate' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wcfmu_otp_sms_body"><?php _e( 'Request Body', 'wc-frontend-manager-ultimate' ); ?></label></th>
							<td>
								<textarea name="wcfmu_otp_sms_body" id="wcfmu_otp_sms_body"
									rows="3" class="large-text code"
									placeholder='{"mobile":"{phone}","message":"Your OTP is {otp}"}'><?php echo esc_textarea( $sms_body ); ?></textarea>
								<p class="description"><?php _e( 'For POST requests only. Use {phone} and {otp} as placeholders.', 'wc-frontend-manager-ultimate' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- General OTP settings -->
				<h2><?php _e( 'OTP Settings', 'wc-frontend-manager-ultimate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcfmu_otp_length"><?php _e( 'OTP Length', 'wc-frontend-manager-ultimate' ); ?></label></th>
						<td>
							<input type="number" name="wcfmu_otp_length" id="wcfmu_otp_length"
								min="4" max="8" value="<?php echo esc_attr( $otp_length ); ?>" style="width:80px" />
							<p class="description"><?php _e( '4 – 8 digits. Default: 6', 'wc-frontend-manager-ultimate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcfmu_otp_expiry"><?php _e( 'OTP Expiry (seconds)', 'wc-frontend-manager-ultimate' ); ?></label></th>
						<td>
							<input type="number" name="wcfmu_otp_expiry" id="wcfmu_otp_expiry"
								min="60" max="900" value="<?php echo esc_attr( $otp_expiry ); ?>" style="width:100px" />
							<p class="description"><?php _e( '60 – 900 seconds. Default: 300 (5 minutes)', 'wc-frontend-manager-ultimate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcfmu_otp_rate_limit"><?php _e( 'Rate Limit', 'wc-frontend-manager-ultimate' ); ?></label></th>
						<td>
							<input type="number" name="wcfmu_otp_rate_limit" id="wcfmu_otp_rate_limit"
								min="1" max="10" value="<?php echo esc_attr( $rate_limit ); ?>" style="width:80px" />
							<p class="description"><?php _e( 'Max OTP requests per phone number per 10 minutes. Default: 3', 'wc-frontend-manager-ultimate' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'wc-frontend-manager-ultimate' ) ); ?>
			</form>
		</div>

		<script>
		(function($){
			$('#wcfmu_otp_provider').on('change', function(){
				var val = $(this).val();
				$('#wcfmu_admin_firebase_fields').toggle( val === 'firebase' );
				$('#wcfmu_admin_sms_fields').toggle( val !== 'firebase' );
			});
		})(jQuery);
		</script>
		<?php
	}

	public function save_global_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'wc-frontend-manager-ultimate' ) );
		}

		check_admin_referer( 'wcfmu_otp_global_save', 'wcfmu_otp_nonce' );

		$provider = sanitize_key( $_POST['wcfmu_otp_provider'] ?? '' );
		if ( ! in_array( $provider, [ 'custom_sms', 'firebase' ], true ) ) {
			$provider = 'custom_sms';
		}
		update_option( 'wcfmu_otp_provider', $provider );

		update_option( 'wcfmu_otp_firebase_api_key', sanitize_text_field( $_POST['wcfmu_otp_firebase_api_key'] ?? '' ) );
		update_option( 'wcfmu_otp_sms_url',          esc_url_raw( trim( $_POST['wcfmu_otp_sms_url'] ?? '' ) ) );

		$sms_method = strtoupper( $_POST['wcfmu_otp_sms_method'] ?? 'POST' );
		update_option( 'wcfmu_otp_sms_method', in_array( $sms_method, [ 'GET', 'POST' ], true ) ? $sms_method : 'POST' );

		$sms_headers = sanitize_text_field( wp_unslash( $_POST['wcfmu_otp_sms_headers'] ?? '{}' ) );
		if ( $sms_headers !== '' && json_decode( $sms_headers ) === null ) {
			$sms_headers = '{}';
		}
		update_option( 'wcfmu_otp_sms_headers', $sms_headers );
		update_option( 'wcfmu_otp_sms_body',    sanitize_textarea_field( wp_unslash( $_POST['wcfmu_otp_sms_body'] ?? '' ) ) );

		$length = max( 4, min( 8, absint( $_POST['wcfmu_otp_length'] ?? 6 ) ) );
		$expiry = max( 60, min( 900, absint( $_POST['wcfmu_otp_expiry'] ?? 300 ) ) );
		$rate   = max( 1, min( 10, absint( $_POST['wcfmu_otp_rate_limit'] ?? 3 ) ) );

		update_option( 'wcfmu_otp_length',     (string) $length );
		update_option( 'wcfmu_otp_expiry',     (string) $expiry );
		update_option( 'wcfmu_otp_rate_limit', (string) $rate );

		wp_redirect( admin_url( 'admin.php?page=wcfmu-mobile-otp&saved=1' ) );
		exit;
	}

	// ── Shortcodes ───────────────────────────────────────────────────────────

	public function shortcode_login( $atts ) {
		$atts = shortcode_atts( [
			'redirect'  => wc_get_page_permalink( 'myaccount' ),
			'vendor_id' => 0,
		], $atts );
		return $this->render_form( 'login', $atts['redirect'], (int) $atts['vendor_id'] );
	}

	public function shortcode_register( $atts ) {
		$atts = shortcode_atts( [
			'redirect'  => wc_get_page_permalink( 'myaccount' ),
			'vendor_id' => 0,
		], $atts );
		return $this->render_form( 'register', $atts['redirect'], (int) $atts['vendor_id'] );
	}

	private function render_form( $type, $redirect, $vendor_id = 0 ) {
		static $assets_printed = false;

		$rest_url = rest_url( self::NS );
		$is_reg   = $type === 'register';
		$uid      = 'wcfmu-' . $type . '-' . substr( md5( $redirect . $type . $vendor_id ), 0, 6 );

		ob_start();

		if ( ! $assets_printed ) :
			$assets_printed = true;
?>
<style>
.wcfmu-otp-wrap{font-family:inherit;max-width:400px;margin:0 auto}
.wcfmu-otp-wrap p{margin-bottom:14px}
.wcfmu-otp-wrap label{display:block;font-weight:600;margin-bottom:5px;font-size:.95em}
.wcfmu-otp-wrap input[type=tel],.wcfmu-otp-wrap input[type=text],.wcfmu-otp-wrap input[type=email],.wcfmu-otp-wrap input[type=number]{width:100%;max-width:100%}
.wcfmu-otp-wrap .wcfmu-otp-input{letter-spacing:6px;font-size:1.5em;width:160px!important;text-align:center}
.wcfmu-otp-wrap .wcfmu-msg{display:none;padding:9px 14px;border-radius:4px;margin-top:8px;font-size:.9em}
.wcfmu-otp-wrap .wcfmu-msg.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
.wcfmu-otp-wrap .wcfmu-msg.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.wcfmu-otp-wrap .wcfmu-countdown{font-size:.85em;color:#6b7280;margin-left:8px}
.wcfmu-otp-wrap .wcfmu-resend{font-size:.9em;margin-left:8px}
</style>
<?php
		endif; ?>
<div class="wcfmu-otp-wrap" id="<?php echo esc_attr($uid); ?>">

    <div class="wcfmu-step" id="<?php echo esc_attr($uid); ?>-s1">
        <p>
            <label for="<?php echo esc_attr($uid); ?>-phone"><?php esc_html_e( 'Mobile Number', 'wc-frontend-manager-ultimate' ); ?></label>
            <input type="tel" id="<?php echo esc_attr($uid); ?>-phone"
                   class="woocommerce-Input input-text"
                   placeholder="<?php esc_attr_e( 'Enter 10-digit mobile number', 'wc-frontend-manager-ultimate' ); ?>"
                   maxlength="15" autocomplete="tel" />
        </p>
        <p>
            <button type="button" class="button woocommerce-Button wcfmu-send-btn">
                <?php esc_html_e( 'Send OTP', 'wc-frontend-manager-ultimate' ); ?>
            </button>
        </p>
        <p class="wcfmu-msg"></p>
    </div>

    <div class="wcfmu-step" id="<?php echo esc_attr($uid); ?>-s2" style="display:none">
        <?php if ( $is_reg ) : ?>
        <p>
            <label for="<?php echo esc_attr($uid); ?>-fname"><?php esc_html_e( 'First Name', 'wc-frontend-manager-ultimate' ); ?> <span style="color:#e00">*</span></label>
            <input type="text" id="<?php echo esc_attr($uid); ?>-fname" class="woocommerce-Input input-text"
                   placeholder="<?php esc_attr_e( 'First name', 'wc-frontend-manager-ultimate' ); ?>" />
        </p>
        <p>
            <label for="<?php echo esc_attr($uid); ?>-lname"><?php esc_html_e( 'Last Name', 'wc-frontend-manager-ultimate' ); ?></label>
            <input type="text" id="<?php echo esc_attr($uid); ?>-lname" class="woocommerce-Input input-text"
                   placeholder="<?php esc_attr_e( 'Last name', 'wc-frontend-manager-ultimate' ); ?>" />
        </p>
        <p>
            <label for="<?php echo esc_attr($uid); ?>-email"><?php esc_html_e( 'Email (optional)', 'wc-frontend-manager-ultimate' ); ?></label>
            <input type="email" id="<?php echo esc_attr($uid); ?>-email" class="woocommerce-Input input-text"
                   placeholder="<?php esc_attr_e( 'Email address', 'wc-frontend-manager-ultimate' ); ?>" autocomplete="email" />
        </p>
        <?php endif; ?>
        <p>
            <label for="<?php echo esc_attr($uid); ?>-otp"><?php esc_html_e( 'Enter OTP', 'wc-frontend-manager-ultimate' ); ?></label>
            <input type="number" id="<?php echo esc_attr($uid); ?>-otp"
                   class="woocommerce-Input input-text wcfmu-otp-input"
                   placeholder="——————" maxlength="6" autocomplete="one-time-code" />
        </p>
        <p>
            <button type="button" class="button woocommerce-Button wcfmu-verify-btn">
                <?php echo $is_reg ? esc_html__( 'Register', 'wc-frontend-manager-ultimate' ) : esc_html__( 'Login', 'wc-frontend-manager-ultimate' ); ?>
            </button>
            <a href="#" class="wcfmu-resend" style="display:none"><?php esc_html_e( 'Resend OTP', 'wc-frontend-manager-ultimate' ); ?></a>
            <span class="wcfmu-countdown"></span>
        </p>
        <p class="wcfmu-msg"></p>
    </div>

</div>
<script>
(function($){
    var uid      = <?php echo wp_json_encode( $uid ); ?>;
    var REST     = <?php echo wp_json_encode( esc_url_raw( $rest_url ) ); ?>;
    var redirect = <?php echo wp_json_encode( esc_url_raw( $redirect ) ); ?>;
    var isReg    = <?php echo $is_reg ? 'true' : 'false'; ?>;
    var vendorId = <?php echo (int) $vendor_id; ?>;
    var $wrap    = $('#' + uid);
    var $s1      = $('#' + uid + '-s1');
    var $s2      = $('#' + uid + '-s2');
    var phone    = '';
    var timer    = null;

    function showMsg($step, text, cls){
        $step.find('.wcfmu-msg').attr('class','wcfmu-msg ' + cls).text(text).show();
    }
    function hideMsg($step){ $step.find('.wcfmu-msg').hide().text(''); }

    function startCountdown(sec){
        clearInterval(timer);
        $wrap.find('.wcfmu-resend').hide();
        var $cd = $wrap.find('.wcfmu-countdown');
        (function tick(){
            $cd.text('(' + sec + 's)');
            if(--sec < 0){
                clearInterval(timer);
                $cd.text('');
                $wrap.find('.wcfmu-resend').show();
                return;
            }
            timer = setTimeout(tick, 1000);
        })();
    }

    function sendOtp(onSuccess){
        var payload = { phone: phone, action: isReg ? 'register' : 'login' };
        if(vendorId) payload.vendor_id = vendorId;
        $.ajax({
            url: REST + '/auth/otp/send',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: onSuccess,
            error: function(xhr){
                var r = xhr.responseJSON || {};
                showMsg($s1, r.message || 'Failed to send OTP.', 'error');
                $s1.find('.wcfmu-send-btn').prop('disabled',false).text('Send OTP');
            }
        });
    }

    $wrap.on('click', '.wcfmu-send-btn', function(){
        phone = ($('#' + uid + '-phone').val() || '').replace(/\D/g,'');
        if(!phone || phone.length < 10){ showMsg($s1,'Enter a valid mobile number.','error'); return; }
        hideMsg($s1);
        $(this).prop('disabled',true).text('Sending…');

        sendOtp(function(){
            $s1.hide();
            $s2.show();
            startCountdown(60);
        });
    });

    $wrap.on('click', '.wcfmu-resend', function(e){
        e.preventDefault();
        hideMsg($s2);
        $wrap.find('.wcfmu-resend').hide();
        sendOtp(function(){ startCountdown(60); });
    });

    $wrap.on('click', '.wcfmu-verify-btn', function(){
        var otp = ($('#' + uid + '-otp').val() || '').trim();
        if(!otp){ showMsg($s2,'Enter the OTP.','error'); return; }
        hideMsg($s2);

        var $btn = $(this).prop('disabled',true).text('Please wait…');
        var payload = { phone: phone, otp: otp };
        if(vendorId) payload.vendor_id = vendorId;

        if(isReg){
            payload.first_name = ($('#' + uid + '-fname').val() || '').trim();
            payload.last_name  = ($('#' + uid + '-lname').val() || '').trim();
            payload.email      = ($('#' + uid + '-email').val() || '').trim();
            if(!payload.first_name){ showMsg($s2,'First name is required.','error'); $btn.prop('disabled',false).text('Register'); return; }
        }

        $.ajax({
            url: REST + '/auth/' + (isReg ? 'register' : 'login'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(r){
                if(r.token){ try{ localStorage.setItem('wcfmu_auth_token', r.token); }catch(e){} }
                showMsg($s2, isReg ? 'Registration successful! Redirecting…' : 'Login successful! Redirecting…', 'success');
                setTimeout(function(){ window.location.href = redirect; }, 1000);
            },
            error: function(xhr){
                var r   = xhr.responseJSON || {};
                var msg = r.message || 'Something went wrong. Please try again.';
                showMsg($s2, msg, 'error');
                $btn.prop('disabled',false).text(isReg ? 'Register' : 'Login');
            }
        });
    });
})(jQuery);
</script>
<?php
		return ob_get_clean();
	}
}
