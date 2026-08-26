<?php
/**
 * AaraaNotifications: WhatsApp channel (WhatsApp Business / Meta Cloud API).
 *
 * A self-contained module that adds a tabbed WhatsApp settings screen under the
 * AaraaNotifications sidebar section:
 *
 *   - General         — Cloud API credentials (Domain, API Version, WABA ID,
 *                       Access Token, Phone Number ID, Admin WhatsApp Number)
 *                       and a global Enable toggle.
 *   - Template Manager — fetches the approved message templates from your WABA
 *                       so you know the exact names / languages to map.
 *   - Status Config   — maps each Order / Subscription status to an approved
 *                       template (name + language + ordered body parameters),
 *                       each with its own Enable toggle.
 *   - Logs            — every WhatsApp send attempt with the Graph API response.
 *
 * WhatsApp business-initiated messages must use an approved template, so the
 * per-status config stores a template name + language + a comma list of body
 * parameters (tokens, resolved in order). Delivery is through the Meta Graph
 * Cloud API: POST {domain}/{version}/{phone_number_id}/messages with a Bearer
 * access token.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp notifications controller.
 */
class WhatsApp_Admin {

	const PAGE = 'aaraa-notify-whatsapp';

	const OPTION     = 'aaraa_notify_whatsapp';
	const ACTION     = 'aaraa_whatsapp_save';
	const NONCE      = 'aaraa_whatsapp';
	const LOG_OPTION  = 'aaraa_notify_whatsapp_log';
	const LOG_LIMIT   = 300;
	const LOG_CLEAR   = 'aaraa_whatsapp_log_clear';
	const TPL_NONCE   = 'aaraa_whatsapp_templates';
	const DEFS_OPTION = 'aaraa_notify_whatsapp_defs';

	const LOG_PER_PAGE    = 50;
	const LOG_QUERY_NONCE = 'aaraa_whatsapp_log_query';
	const BULK_NONCE      = 'aaraa_whatsapp_bulk';
	const SUB_NONCE       = 'aaraa_whatsapp_sub';
	const CUST_NONCE      = 'aaraa_whatsapp_customers';

	// Per-day de-duplication of the bulk customer sender: option holding today's
	// date and the set of mobile|template keys already sent, so the same message
	// can't go to the same customer twice on the same day.
	const CUST_SENT_OPTION = 'aaraa_notify_whatsapp_cust_sent';

	// Wallet balance meta key (WP Swings Wallet System for WooCommerce).
	const WALLET_META = 'wps_wallet';

	/**
	 * Old wallet balance captured pre-update, keyed by user id, so the
	 * post-update hook can tell a credit from a debit and by how much.
	 *
	 * @var array<int,float>
	 */
	private $wallet_old = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_init', array( $this, 'handle_log_clear' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_status_assets' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bulk_assets' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_customers_assets' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_sub_reminder_assets' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_reminder_metabox' ), 30, 2 );
		add_action( 'wp_ajax_aaraa_whatsapp_fetch_templates', array( $this, 'ajax_fetch_templates' ) );
		add_action( 'wp_ajax_aaraa_whatsapp_log_query', array( $this, 'ajax_log_query' ) );
		add_action( 'wp_ajax_aaraa_whatsapp_bulk_send', array( $this, 'ajax_bulk_send' ) );
		add_action( 'wp_ajax_aaraa_whatsapp_customers_send', array( $this, 'ajax_customers_send' ) );
		add_action( 'wp_ajax_aaraa_whatsapp_sub_reminder', array( $this, 'ajax_sub_reminder_send' ) );

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 20, 4 );
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'on_subscription_status_changed' ), 20, 3 );

		// Wallet balance triggers — watch the wps_wallet user meta directly (the
		// wallet plugin fires no action of its own), mirroring the SMS channel.
		add_action( 'update_user_meta', array( $this, 'wallet_capture_old' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'wallet_after_update' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'wallet_after_add' ), 10, 4 );
	}

	/* --------------------------------------------------------------------- *
	 * Settings.
	 * --------------------------------------------------------------------- */

	/**
	 * WhatsApp settings, merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function settings() {
		$row      = array( 'enabled' => 0, 'template' => '', 'language' => 'en', 'params' => '', 'buttons' => '' );
		$defaults = array(
			'enabled'         => 0,
			'domain'          => 'https://graph.facebook.com',
			'api_version'     => 'v18.0',
			'waba_id'         => '',
			'access_token'    => '',
			'phone_number_id' => '',
			'admin_numbers'   => '',
			'order'           => array(),
			'subscription'    => array(),
			'otp'             => $row,
			'pause'           => $row,
			'wallet'          => array(
				'credit' => $row,
				'debit'  => $row,
				'low'    => array_merge( $row, array( 'threshold' => '100' ) ),
			),
		);

		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$s                 = array_merge( $defaults, $saved );
		$s['order']        = ( isset( $saved['order'] ) && is_array( $saved['order'] ) ) ? $saved['order'] : array();
		$s['subscription'] = ( isset( $saved['subscription'] ) && is_array( $saved['subscription'] ) ) ? $saved['subscription'] : array();
		$s['otp']          = array_merge( $defaults['otp'], ( isset( $saved['otp'] ) && is_array( $saved['otp'] ) ) ? $saved['otp'] : array() );
		$s['pause']        = array_merge( $defaults['pause'], ( isset( $saved['pause'] ) && is_array( $saved['pause'] ) ) ? $saved['pause'] : array() );

		$saved_wallet = ( isset( $saved['wallet'] ) && is_array( $saved['wallet'] ) ) ? $saved['wallet'] : array();
		$s['wallet']  = array();
		foreach ( $defaults['wallet'] as $key => $wrow ) {
			$s['wallet'][ $key ] = array_merge( $wrow, ( isset( $saved_wallet[ $key ] ) && is_array( $saved_wallet[ $key ] ) ) ? $saved_wallet[ $key ] : array() );
		}

		return $s;
	}

	/**
	 * The template mapped to a status in a group ('order'|'subscription').
	 *
	 * @param string $group  order|subscription.
	 * @param string $status Status key (with or without the wc- prefix).
	 * @return array{enabled:int,template:string,language:string,params:string}|null
	 */
	public static function status_template( $group, $status ) {
		$s      = self::settings();
		$status = str_replace( 'wc-', '', (string) $status );
		if ( isset( $s[ $group ][ $status ] ) && is_array( $s[ $group ][ $status ] ) ) {
			return $s[ $group ][ $status ];
		}
		return null;
	}

	/* --------------------------------------------------------------------- *
	 * Sending.
	 * --------------------------------------------------------------------- */

	/**
	 * Normalise a number to WhatsApp format: digits only, with country code.
	 *
	 * @param string $number Raw number.
	 * @return string Digits-only international number, or '' if unusable.
	 */
	public static function normalise_number( $number ) {
		$digits = preg_replace( '/\D+/', '', (string) $number );
		if ( '' === $digits ) {
			return '';
		}
		// Bare 10-digit local number → prepend the default country code (India).
		if ( 10 === strlen( $digits ) ) {
			$digits = '91' . $digits;
		}
		return $digits;
	}

	/**
	 * Send an approved WhatsApp template message via the Meta Cloud API.
	 *
	 * @param string        $to       Recipient number.
	 * @param string        $template Approved template name.
	 * @param string        $language Template language code (e.g. en, en_US).
	 * @param array<string> $params   Ordered body parameter values.
	 * @param array         $context  Log context: type, ref, status.
	 * @param array         $buttons  Resolved dynamic button params: [ { index, sub_type, param } ].
	 * @return bool True when the Graph API accepted the message.
	 */
	public static function send_template( $to, $template, $language, $params = array(), $context = array(), $buttons = array() ) {
		$s  = self::settings();
		$to = self::normalise_number( $to );

		$base = array(
			'type'     => isset( $context['type'] ) ? $context['type'] : 'manual',
			'ref'      => isset( $context['ref'] ) ? $context['ref'] : '',
			'status'   => isset( $context['status'] ) ? $context['status'] : '',
			'mobile'   => $to,
			'template' => $template,
			'language' => $language,
			'params'   => array_values( array_map( 'strval', (array) $params ) ),
			'message'  => trim( $template . ' [' . $language . '] ' . implode( ' | ', $params ) ),
			'body'     => self::rendered_body( $template, $language, $params ),
		);

		if ( empty( $s['access_token'] ) || empty( $s['phone_number_id'] ) || '' === $to || '' === (string) $template ) {
			$base['result']   = 'failed';
			$base['response'] = '' === $to ? 'No recipient number' : ( '' === (string) $template ? 'No template name' : 'WhatsApp API not configured (access token / phone number id)' );
			self::log( $base );
			return false;
		}

		$components = array();
		$params     = array_values( array_filter( array_map( 'strval', $params ), static function ( $v ) { return '' !== $v; } ) );
		if ( ! empty( $params ) ) {
			$body_params = array();
			foreach ( $params as $value ) {
				$body_params[] = array( 'type' => 'text', 'text' => $value );
			}
			$components[] = array( 'type' => 'body', 'parameters' => $body_params );
		}

		if ( ! empty( $buttons ) && is_array( $buttons ) ) {
			foreach ( $buttons as $b ) {
				$param = isset( $b['param'] ) ? (string) $b['param'] : '';
				if ( '' === $param ) {
					continue;
				}
				$components[] = array(
					'type'       => 'button',
					'sub_type'   => isset( $b['sub_type'] ) ? (string) $b['sub_type'] : 'url',
					'index'      => (string) (int) ( isset( $b['index'] ) ? $b['index'] : 0 ),
					'parameters' => array( array( 'type' => 'text', 'text' => $param ) ),
				);
			}
		}

		$template_payload = array(
			'name'     => $template,
			'language' => array( 'code' => $language ? $language : 'en' ),
		);
		if ( ! empty( $components ) ) {
			$template_payload['components'] = $components;
		}

		$payload = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'template',
			'template'          => $template_payload,
		);

		$url = self::api_base( $s ) . '/' . rawurlencode( $s['phone_number_id'] ) . '/messages';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $s['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$base['result']   = 'failed';
			$base['response'] = $response->get_error_message();
			self::log( $base );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		$ok   = $code >= 200 && $code < 300;

		$base['result']    = $ok ? 'sent' : 'failed';
		$base['http_code'] = $code;
		$base['response']  = $ok ? self::summarise_ok( $body ) : self::summarise_error( $body );
		self::log( $base );

		return $ok;
	}

	/**
	 * Pull the message id out of a successful Graph API response.
	 *
	 * @param string $body Raw JSON body.
	 * @return string
	 */
	private static function summarise_ok( $body ) {
		$data = json_decode( $body, true );
		if ( is_array( $data ) && ! empty( $data['messages'][0]['id'] ) ) {
			return 'id: ' . $data['messages'][0]['id'];
		}
		return $body;
	}

	/**
	 * Pull the human-readable error out of a Graph API error response.
	 *
	 * @param string $body Raw JSON body.
	 * @return string
	 */
	private static function summarise_error( $body ) {
		$data = json_decode( $body, true );
		if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
			$msg = $data['error']['message'];
			if ( ! empty( $data['error']['error_data']['details'] ) ) {
				$msg .= ' — ' . $data['error']['error_data']['details'];
			}
			return $msg;
		}
		return $body;
	}

	/* --------------------------------------------------------------------- *
	 * Triggers.
	 * --------------------------------------------------------------------- */

	/**
	 * Send the order-status WhatsApp when enabled.
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
		$tpl = self::status_template( 'order', $to );
		if ( ! $tpl || empty( $tpl['enabled'] ) || empty( $tpl['template'] ) ) {
			return;
		}
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		$phone = $order->get_billing_phone();
		if ( ! $phone ) {
			self::log(
				array(
					'type'     => 'order',
					'ref'      => $order->get_order_number(),
					'status'   => $to,
					'template' => $tpl['template'],
					'result'   => 'failed',
					'response' => 'No billing phone number on the order',
				)
			);
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

		$params  = self::resolve_params( $tpl['params'], $tokens );
		$buttons = self::resolve_buttons( isset( $tpl['buttons'] ) ? $tpl['buttons'] : '', $tokens );

		$sent = self::send_template(
			$phone,
			$tpl['template'],
			isset( $tpl['language'] ) ? $tpl['language'] : 'en',
			$params,
			array( 'type' => 'order', 'ref' => $order->get_order_number(), 'status' => $to ),
			$buttons
		);
		if ( $sent ) {
			$order->add_order_note( sprintf( 'WhatsApp sent for order status "%s".', $to ) );
			self::notify_admins( $s, $tpl, $params, array( 'type' => 'order', 'ref' => $order->get_order_number(), 'status' => $to ), $buttons );
		}
	}

	/**
	 * Send the subscription-status WhatsApp when enabled.
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
		$tpl = self::status_template( 'subscription', $to );
		if ( ! $tpl || empty( $tpl['enabled'] ) || empty( $tpl['template'] ) ) {
			return;
		}
		if ( ! is_a( $subscription, 'WC_Subscription' ) ) {
			return;
		}
		$phone = $subscription->get_billing_phone();
		if ( ! $phone ) {
			self::log(
				array(
					'type'     => 'subscription',
					'ref'      => $subscription->get_id(),
					'status'   => $to,
					'template' => $tpl['template'],
					'result'   => 'failed',
					'response' => 'No billing phone number on the subscription',
				)
			);
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

		$params  = self::resolve_params( $tpl['params'], $tokens );
		$buttons = self::resolve_buttons( isset( $tpl['buttons'] ) ? $tpl['buttons'] : '', $tokens );

		$sent = self::send_template(
			$phone,
			$tpl['template'],
			isset( $tpl['language'] ) ? $tpl['language'] : 'en',
			$params,
			array( 'type' => 'subscription', 'ref' => $subscription->get_id(), 'status' => $to ),
			$buttons
		);
		if ( $sent ) {
			$subscription->add_order_note( sprintf( 'WhatsApp sent for subscription status "%s".', $to ) );
			self::notify_admins( $s, $tpl, $params, array( 'type' => 'subscription', 'ref' => $subscription->get_id(), 'status' => $to ), $buttons );
		}
	}

	/**
	 * Send the Pause Subscription WhatsApp template when enabled.
	 *
	 * Called from Notifications_Admin::send_pause_notifications(). The tokens are
	 * pre-built there ({customer_name}, {pause_dates}, {resume_dates}, …); this
	 * maps them onto the approved template's numbered params.
	 *
	 * @param \WC_Subscription     $subscription Subscription.
	 * @param array<string,string> $tokens       {token} => value map.
	 * @return void
	 */
	public static function send_pause( $subscription, $tokens ) {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		$tpl = isset( $s['pause'] ) ? $s['pause'] : array();
		if ( empty( $tpl['enabled'] ) || empty( $tpl['template'] ) ) {
			return;
		}
		if ( ! is_a( $subscription, 'WC_Subscription' ) ) {
			return;
		}
		$phone = $subscription->get_billing_phone();
		if ( ! $phone ) {
			self::log(
				array(
					'type'     => 'pause',
					'ref'      => $subscription->get_id(),
					'status'   => 'pause',
					'template' => $tpl['template'],
					'result'   => 'failed',
					'response' => 'No billing phone number on the subscription',
				)
			);
			return;
		}

		$params  = self::resolve_params( isset( $tpl['params'] ) ? $tpl['params'] : '', $tokens );
		$buttons = self::resolve_buttons( isset( $tpl['buttons'] ) ? $tpl['buttons'] : '', $tokens );

		$sent = self::send_template(
			$phone,
			$tpl['template'],
			isset( $tpl['language'] ) ? $tpl['language'] : 'en',
			$params,
			array( 'type' => 'pause', 'ref' => $subscription->get_id(), 'status' => 'pause' ),
			$buttons
		);
		if ( $sent ) {
			self::notify_admins( $s, $tpl, $params, array( 'type' => 'pause', 'ref' => $subscription->get_id(), 'status' => 'pause' ), $buttons );
		}
	}

	/**
	 * Also send the same template to the configured admin WhatsApp number(s).
	 *
	 * @param array         $s       Settings.
	 * @param array         $tpl     Status template row.
	 * @param array<string> $params  Resolved body params.
	 * @param array         $context Base log context.
	 * @param array         $buttons Resolved dynamic button params.
	 * @return void
	 */
	private static function notify_admins( $s, $tpl, $params, $context, $buttons = array() ) {
		if ( empty( $s['admin_numbers'] ) ) {
			return;
		}
		$numbers = array_filter( array_map( 'trim', explode( ',', $s['admin_numbers'] ) ) );
		foreach ( $numbers as $number ) {
			$ctx         = $context;
			$ctx['type'] = 'admin';
			self::send_template( $number, $tpl['template'], isset( $tpl['language'] ) ? $tpl['language'] : 'en', $params, $ctx, $buttons );
		}
	}

	/* --------------------------------------------------------------------- *
	 * OTP + wallet triggers.
	 * --------------------------------------------------------------------- */

	/**
	 * Send the configured OTP WhatsApp template. Call from the login/OTP flow.
	 *
	 * @param string     $mobile Recipient mobile number.
	 * @param string|int $otp    One-time code.
	 * @return bool
	 */
	public static function send_otp( $mobile, $otp ) {
		$s   = self::settings();
		$cfg = isset( $s['otp'] ) ? $s['otp'] : array();
		if ( empty( $s['enabled'] ) || empty( $cfg['enabled'] ) || empty( $cfg['template'] ) ) {
			return false;
		}
		$tokens  = array( '{otp}' => (string) $otp );
		$params  = self::resolve_params( isset( $cfg['params'] ) ? $cfg['params'] : '', $tokens );
		$buttons = self::resolve_buttons( isset( $cfg['buttons'] ) ? $cfg['buttons'] : '', $tokens );

		return self::send_template(
			$mobile,
			$cfg['template'],
			isset( $cfg['language'] ) ? $cfg['language'] : 'en',
			$params,
			array( 'type' => 'otp' ),
			$buttons
		);
	}

	/**
	 * Capture the wallet balance before it is updated.
	 *
	 * @param int    $meta_id    Meta row id.
	 * @param int    $object_id  User id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value (unused).
	 * @return void
	 */
	public function wallet_capture_old( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::WALLET_META !== $meta_key ) {
			return;
		}
		$this->wallet_old[ (int) $object_id ] = (float) get_user_meta( $object_id, self::WALLET_META, true );
	}

	/**
	 * Fire the wallet WhatsApp after an existing balance is updated.
	 *
	 * @param int    $meta_id    Meta row id.
	 * @param int    $object_id  User id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 * @return void
	 */
	public function wallet_after_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::WALLET_META !== $meta_key ) {
			return;
		}
		$old = isset( $this->wallet_old[ (int) $object_id ] ) ? $this->wallet_old[ (int) $object_id ] : 0.0;
		unset( $this->wallet_old[ (int) $object_id ] );
		$this->handle_wallet_change( (int) $object_id, $old, (float) $meta_value );
	}

	/**
	 * Fire the wallet WhatsApp the first time a balance is set (old = 0).
	 *
	 * @param int    $meta_id    Meta row id.
	 * @param int    $object_id  User id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 * @return void
	 */
	public function wallet_after_add( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::WALLET_META !== $meta_key ) {
			return;
		}
		$this->handle_wallet_change( (int) $object_id, 0.0, (float) $meta_value );
	}

	/**
	 * Decide which wallet WhatsApp (credit / debit / low balance) to send.
	 *
	 * @param int   $user_id User id.
	 * @param float $old     Old balance.
	 * @param float $new     New balance.
	 * @return void
	 */
	private function handle_wallet_change( $user_id, $old, $new ) {
		if ( ! $user_id ) {
			return;
		}
		// Allow other code (e.g. a CSV user import) to suppress wallet
		// notifications while it bulk-updates balances.
		if ( apply_filters( 'aaraa_notify_suppress', false ) ) {
			return;
		}
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		$delta  = round( $new - $old, 2 );
		$wallet = isset( $s['wallet'] ) ? $s['wallet'] : array();

		if ( $delta > 0 && ! empty( $wallet['credit'] ) ) {
			$this->send_wallet_whatsapp( $user_id, 'wallet_credit', $wallet['credit'], $delta, $new );
		} elseif ( $delta < 0 && ! empty( $wallet['debit'] ) ) {
			$this->send_wallet_whatsapp( $user_id, 'wallet_debit', $wallet['debit'], abs( $delta ), $new );
		}

		// Low balance: fire once, only when the balance crosses down to/below the
		// threshold (so it is not repeated on every further debit while low).
		if ( ! empty( $wallet['low'] ) && ! empty( $wallet['low']['enabled'] ) ) {
			$threshold = isset( $wallet['low']['threshold'] ) ? (float) $wallet['low']['threshold'] : 0.0;
			if ( $threshold > 0 && $new <= $threshold && $old > $threshold ) {
				$this->send_wallet_whatsapp( $user_id, 'wallet_low', $wallet['low'], abs( $delta ), $new, $threshold );
			}
		}
	}

	/**
	 * Render and send one wallet WhatsApp template to a customer.
	 *
	 * @param int    $user_id   User id.
	 * @param string $ctx_type  Log context type.
	 * @param array  $cfg       Template row (enabled/template/language/params/buttons).
	 * @param float  $amount    Transaction amount (absolute).
	 * @param float  $balance   New balance.
	 * @param float  $threshold Low-balance threshold (for {threshold}).
	 * @return void
	 */
	private function send_wallet_whatsapp( $user_id, $ctx_type, $cfg, $amount, $balance, $threshold = 0.0 ) {
		if ( empty( $cfg['enabled'] ) || empty( $cfg['template'] ) ) {
			return;
		}
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( ! $phone ) {
			self::log(
				array(
					'type'     => $ctx_type,
					'ref'      => $user_id,
					'template' => $cfg['template'],
					'result'   => 'failed',
					'response' => 'No billing phone number for this customer',
				)
			);
			return;
		}

		$user     = get_userdata( $user_id );
		$first    = $user ? $user->first_name : '';
		$last     = $user ? $user->last_name : '';
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		$tokens = array(
			'{amount}'        => number_format_i18n( (float) $amount, 2 ),
			'{balance}'       => number_format_i18n( (float) $balance, 2 ),
			'{threshold}'     => number_format_i18n( (float) $threshold, 2 ),
			'{currency}'      => $currency,
			'{customer_name}' => trim( $first . ' ' . $last ),
			'{first_name}'    => $first,
			'{site}'          => get_bloginfo( 'name' ),
		);

		$params  = self::resolve_params( isset( $cfg['params'] ) ? $cfg['params'] : '', $tokens );
		$buttons = self::resolve_buttons( isset( $cfg['buttons'] ) ? $cfg['buttons'] : '', $tokens );

		self::send_template(
			$phone,
			$cfg['template'],
			isset( $cfg['language'] ) ? $cfg['language'] : 'en',
			$params,
			array( 'type' => $ctx_type, 'ref' => $user_id ),
			$buttons
		);
	}

	/**
	 * Resolve a comma list of tokens/text into ordered body parameter values.
	 *
	 * @param string               $params_str Comma-separated params (tokens or literals).
	 * @param array<string,string> $tokens     {token} => value map.
	 * @return array<string>
	 */
	private static function resolve_params( $params_str, $tokens ) {
		$params_str = (string) $params_str;
		if ( '' === trim( $params_str ) ) {
			return array();
		}
		$parts = array_map( 'trim', explode( ',', $params_str ) );
		$out   = array();
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			$out[] = strtr( $part, array_map( 'strval', $tokens ) );
		}
		return $out;
	}

	/**
	 * Resolve the saved buttons map (JSON) into send-ready button params.
	 *
	 * @param string               $buttons_json Stored JSON: [ { index, sub_type, param } ].
	 * @param array<string,string> $tokens       {token} => value map.
	 * @return array<int,array{index:int,sub_type:string,param:string}>
	 */
	private static function resolve_buttons( $buttons_json, $tokens ) {
		$out  = array();
		$data = json_decode( (string) $buttons_json, true );
		if ( ! is_array( $data ) ) {
			return $out;
		}
		foreach ( $data as $b ) {
			if ( ! is_array( $b ) || ! isset( $b['index'] ) ) {
				continue;
			}
			$param = isset( $b['param'] ) ? strtr( (string) $b['param'], array_map( 'strval', $tokens ) ) : '';
			if ( '' === $param ) {
				continue;
			}
			$out[] = array(
				'index'    => (int) $b['index'],
				'sub_type' => isset( $b['sub_type'] ) ? preg_replace( '/[^a-z_]/', '', strtolower( (string) $b['sub_type'] ) ) : 'url',
				'param'    => $param,
			);
		}
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * Template Manager (fetch approved templates from the WABA).
	 * --------------------------------------------------------------------- */

	/**
	 * Build the versioned API base URL, aware of the configured provider.
	 *
	 * Meta's Graph API is called directly ({domain}/{version}/…), while
	 * BChatNow-style proxies expose the same Graph endpoints under /api/meta
	 * ({domain}/api/meta/{version}/…). The host decides which shape is used.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return string e.g. https://graph.facebook.com/v18.0
	 */
	private static function api_base( $s ) {
		$domain = untrailingslashit( (string) $s['domain'] );
		$host   = (string) wp_parse_url( $domain, PHP_URL_HOST );
		$prefix = ( '' !== $host && ( 'facebook.com' === $host || 6 < strlen( $host ) && '.facebook.com' === substr( $host, -13 ) ) ) ? '' : '/api/meta';
		return $domain . $prefix . '/' . rawurlencode( $s['api_version'] );
	}

	/**
	 * Fetch the approved message templates for the configured WABA.
	 *
	 * @return array{ok:bool,items:array,error:string}
	 */
	public static function fetch_templates() {
		$s = self::settings();
		if ( empty( $s['access_token'] ) || empty( $s['waba_id'] ) ) {
			return array( 'ok' => false, 'items' => array(), 'error' => 'Enter the WABA ID and Access Token on the General tab first.' );
		}

		$url = add_query_arg(
			array(
				'limit'  => 200,
				'fields' => 'name,status,language,category,components',
			),
			self::api_base( $s ) . '/' . rawurlencode( $s['waba_id'] ) . '/message_templates'
		);

		$timeout  = (int) apply_filters( 'aaraa_whatsapp_api_timeout', 25 );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => array( 'Authorization' => 'Bearer ' . $s['access_token'] ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'items' => array(), 'error' => $response->get_error_message(), 'hint' => self::connectivity_hint( $response->get_error_message() ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['error']['message'] ) ) {
			return array( 'ok' => false, 'items' => array(), 'error' => $body['error']['message'], 'hint' => '' );
		}
		$items = ( is_array( $body ) && ! empty( $body['data'] ) ) ? $body['data'] : array();
		return array( 'ok' => true, 'items' => $items, 'error' => '', 'hint' => '' );
	}

	/**
	 * A human-readable hint for common connectivity failures (e.g. cURL 28).
	 *
	 * @param string $error The WP_Error message.
	 * @return string
	 */
	private static function connectivity_hint( $error ) {
		$error = (string) $error;
		if ( false !== stripos( $error, 'timed out' ) || false !== stripos( $error, 'timeout' ) || false !== stripos( $error, 'error 28' ) || false !== stripos( $error, 'could not resolve' ) || false !== stripos( $error, 'failed to connect' ) ) {
			$s = self::settings();
			return sprintf(
				/* translators: %s: API domain */
				__( 'The server could not reach %s in time. This is almost always the web host blocking outbound HTTPS — ask your host to allow outbound connections to graph.facebook.com and oauth2.googleapis.com. Also double-check the Domain and API Version on the General tab. You do not need this fetch to send messages — you can type the template name and language directly on the Status Config tab.', 'aaraa-white-label-admin' ),
				esc_html( $s['domain'] )
			);
		}
		return '';
	}

	/* --------------------------------------------------------------------- *
	 * Log (shared shape with the SMS log).
	 * --------------------------------------------------------------------- */

	/**
	 * Append an entry to the rolling WhatsApp log (newest first, capped).
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
				'time'      => current_time( 'mysql' ),
				'type'      => 'manual',
				'ref'       => '',
				'status'    => '',
				'mobile'    => '',
				'template'  => '',
				'language'  => '',
				'params'    => array(),
				'message'   => '',
				'body'      => '',
				'result'    => 'failed',
				'http_code' => '',
				'response'  => '',
			),
			$entry
		);

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
	 * The full WhatsApp log (newest first).
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
	 * Persist the WhatsApp settings form (General + Status Config tabs).
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
			// Only the Template Config tab was submitted — keep the credentials.
			$cur['order']        = $this->sanitize_group( $in['order'] ?? array() );
			$cur['subscription'] = $this->sanitize_group( $in['subscription'] ?? array() );
			$cur['otp']          = $this->sanitize_row( $in['otp'] ?? array() );
			$cur['pause']        = $this->sanitize_row( $in['pause'] ?? array() );
			$cur['wallet']       = array(
				'credit' => $this->sanitize_row( $in['wallet']['credit'] ?? array() ),
				'debit'  => $this->sanitize_row( $in['wallet']['debit'] ?? array() ),
				'low'    => $this->sanitize_row( $in['wallet']['low'] ?? array(), true ),
			);
			$settings            = $cur;
		} else {
			// General tab — keep the existing per-status / OTP / wallet config.
			$settings = array(
				'enabled'         => empty( $in['enabled'] ) ? 0 : 1,
				'domain'          => untrailingslashit( esc_url_raw( $in['domain'] ?? '' ) ),
				'api_version'     => sanitize_text_field( $in['api_version'] ?? 'v18.0' ),
				'waba_id'         => sanitize_text_field( $in['waba_id'] ?? '' ),
				'access_token'    => trim( sanitize_textarea_field( $in['access_token'] ?? '' ) ),
				'phone_number_id' => sanitize_text_field( $in['phone_number_id'] ?? '' ),
				'admin_numbers'   => sanitize_text_field( $in['admin_numbers'] ?? '' ),
				'order'           => $cur['order'],
				'subscription'    => $cur['subscription'],
				'otp'             => $cur['otp'],
				'wallet'          => $cur['wallet'],
			);
		}

		update_option( self::OPTION, $settings );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE, 'tab' => $tab, 'notice' => 'saved' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Clear the WhatsApp activity log.
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
	 * Sanitize a group of per-status template rows.
	 *
	 * @param array<string,mixed> $group Raw posted group.
	 * @return array<string,array{enabled:int,template:string,language:string,params:string}>
	 */
	private function sanitize_group( $group ) {
		$clean = array();
		if ( ! is_array( $group ) ) {
			return $clean;
		}
		foreach ( $group as $status => $row ) {
			$status           = sanitize_key( $status );
			$clean[ $status ] = $this->sanitize_row( $row );
		}
		return $clean;
	}

	/**
	 * Sanitize a single template row (OTP / wallet / a status entry).
	 *
	 * @param array<string,mixed> $row           Raw posted row.
	 * @param bool                $with_threshold Include a low-balance threshold.
	 * @return array<string,mixed>
	 */
	private function sanitize_row( $row, $with_threshold = false ) {
		$row   = is_array( $row ) ? $row : array();
		$clean = array(
			'enabled'  => empty( $row['enabled'] ) ? 0 : 1,
			'template' => sanitize_text_field( $row['template'] ?? '' ),
			'language' => sanitize_text_field( $row['language'] ?? 'en' ),
			'params'   => sanitize_text_field( $row['params'] ?? '' ),
			'buttons'  => self::sanitize_buttons_json( $row['buttons'] ?? '' ),
		);
		if ( $with_threshold ) {
			$clean['threshold'] = (string) max( 0, (float) ( $row['threshold'] ?? 0 ) );
		}
		return $clean;
	}

	/**
	 * Sanitize the buttons-map JSON posted by the Status Config UI.
	 *
	 * @param string $raw Raw JSON: [ { index, sub_type, param } ].
	 * @return string Clean JSON, or '' when empty/invalid.
	 */
	private static function sanitize_buttons_json( $raw ) {
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$out = array();
		foreach ( $data as $b ) {
			if ( ! is_array( $b ) || ! isset( $b['index'] ) ) {
				continue;
			}
			$param = isset( $b['param'] ) ? sanitize_text_field( (string) $b['param'] ) : '';
			if ( '' === $param ) {
				continue;
			}
			$out[] = array(
				'index'    => (int) $b['index'],
				'sub_type' => isset( $b['sub_type'] ) ? preg_replace( '/[^a-z_]/', '', strtolower( (string) $b['sub_type'] ) ) : 'url',
				'param'    => $param,
			);
		}
		return empty( $out ) ? '' : (string) wp_json_encode( $out );
	}

	/* --------------------------------------------------------------------- *
	 * Rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Load the Status Config enhancer (searchable picker, variable mapping,
	 * button mapping, live preview) on that tab only.
	 *
	 * @return void
	 */
	public function enqueue_status_assets() {
		if ( ! isset( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'status' !== $tab ) {
			return;
		}

		// Prefer WooCommerce's bundled selectWoo/select2 for the searchable picker.
		$deps = array( 'jquery' );
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			$deps[] = 'selectWoo';
		} elseif ( wp_script_is( 'select2', 'registered' ) ) {
			$deps[] = 'select2';
		}
		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
		} elseif ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		wp_enqueue_script(
			'aaraa-wa-status',
			AARAA_WLA_URL . 'assets/js/whatsapp-status-config.js',
			$deps,
			aaraa_asset_ver( 'assets/js/whatsapp-status-config.js' ),
			true
		);

		$rest = array(
			array( 't' => '{status}', 'label' => __( 'Status', 'aaraa-white-label-admin' ) ),
			array( 't' => '{customer_name}', 'label' => __( 'Customer name', 'aaraa-white-label-admin' ) ),
			array( 't' => '{first_name}', 'label' => __( 'First name', 'aaraa-white-label-admin' ) ),
			array( 't' => '{total}', 'label' => __( 'Total', 'aaraa-white-label-admin' ) ),
			array( 't' => '{currency}', 'label' => __( 'Currency', 'aaraa-white-label-admin' ) ),
			array( 't' => '{site}', 'label' => __( 'Site name', 'aaraa-white-label-admin' ) ),
		);

		$order_tokens = array_merge( array( array( 't' => '{order_id}', 'label' => __( 'Order ID', 'aaraa-white-label-admin' ) ) ), $rest );
		$sub_tokens   = array_merge( array( array( 't' => '{subscription_id}', 'label' => __( 'Subscription ID', 'aaraa-white-label-admin' ) ) ), $rest );

		$otp_tokens = array(
			array( 't' => '{otp}', 'label' => __( 'OTP code', 'aaraa-white-label-admin' ) ),
		);
		$wallet_tokens = array(
			array( 't' => '{amount}', 'label' => __( 'Amount', 'aaraa-white-label-admin' ) ),
			array( 't' => '{balance}', 'label' => __( 'Wallet balance', 'aaraa-white-label-admin' ) ),
			array( 't' => '{threshold}', 'label' => __( 'Low-balance threshold', 'aaraa-white-label-admin' ) ),
			array( 't' => '{currency}', 'label' => __( 'Currency', 'aaraa-white-label-admin' ) ),
			array( 't' => '{customer_name}', 'label' => __( 'Customer name', 'aaraa-white-label-admin' ) ),
			array( 't' => '{first_name}', 'label' => __( 'First name', 'aaraa-white-label-admin' ) ),
			array( 't' => '{site}', 'label' => __( 'Site name', 'aaraa-white-label-admin' ) ),
		);

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR';

		wp_localize_script(
			'aaraa-wa-status',
			'AaraaWAStatus',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::TPL_NONCE ),
				'action'  => 'aaraa_whatsapp_fetch_templates',
				'tokens'  => array(
					'order'        => $order_tokens,
					'subscription' => $sub_tokens,
					'otp'          => $otp_tokens,
					'wallet'       => $wallet_tokens,
				),
				'samples' => array(
					'{order_id}'        => '1234',
					'{subscription_id}' => '5678',
					'{status}'          => __( 'Completed', 'aaraa-white-label-admin' ),
					'{customer_name}'   => 'Ramesh Kumar',
					'{first_name}'      => 'Ramesh',
					'{total}'           => '499.00',
					'{currency}'        => $currency,
					'{site}'            => get_bloginfo( 'name' ),
					'{otp}'             => '123456',
					'{amount}'          => '100.00',
					'{balance}'         => '250.00',
					'{threshold}'       => '100.00',
				),
				'i18n'    => array(
					'searchTemplate'   => __( 'Search a template…', 'aaraa-white-label-admin' ),
					'selectPlaceholder' => __( '— Select a template —', 'aaraa-white-label-admin' ),
					'noMatch'          => __( 'No matching template', 'aaraa-white-label-admin' ),
					'language'       => __( 'Language', 'aaraa-white-label-admin' ),
					'template'       => __( 'Template', 'aaraa-white-label-admin' ),
					'variables'      => __( 'Message variables', 'aaraa-white-label-admin' ),
					'buttons'        => __( 'Buttons', 'aaraa-white-label-admin' ),
					'preview'        => __( 'Preview', 'aaraa-white-label-admin' ),
					'chooseToken'    => __( '— choose —', 'aaraa-white-label-admin' ),
					'customText'     => __( 'Custom text…', 'aaraa-white-label-admin' ),
					'customValue'    => __( 'Enter custom value', 'aaraa-white-label-admin' ),
					'noButtons'      => __( 'This template has no buttons that need a value.', 'aaraa-white-label-admin' ),
					'staticButton'   => __( 'no value needed', 'aaraa-white-label-admin' ),
					'unmapped'       => __( 'not set', 'aaraa-white-label-admin' ),
					'notApproved'    => __( 'This template is not approved yet.', 'aaraa-white-label-admin' ),
					'loadFailed'     => __( 'Could not load templates — you can still type the template name manually below.', 'aaraa-white-label-admin' ),
					'pickTemplate'   => __( 'Choose a template to map its variables and preview the message.', 'aaraa-white-label-admin' ),
					'notInList'      => __( 'Saved template “%s” is not in the approved list.', 'aaraa-white-label-admin' ),
					'urlButton'      => __( 'URL button', 'aaraa-white-label-admin' ),
				),
			)
		);
	}

	/**
	 * Load the "Send WhatsApp" bulk tool on the WooCommerce Orders list screen
	 * (HPOS and legacy). Adds a button + modal that maps an approved template to
	 * order data and sends it to the selected orders' customers.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_bulk_assets( $hook ) {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen && isset( $screen->id ) ? $screen->id : '';
		$is_orders = ( 'woocommerce_page_wc-orders' === $hook ) || ( 'woocommerce_page_wc-orders' === $screen_id ) || ( 'edit-shop_order' === $screen_id );
		if ( ! $is_orders ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$deps = array( 'jquery' );
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			$deps[] = 'selectWoo';
		} elseif ( wp_script_is( 'select2', 'registered' ) ) {
			$deps[] = 'select2';
		}
		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
		} elseif ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		wp_enqueue_script(
			'aaraa-wa-bulk',
			AARAA_WLA_URL . 'assets/js/whatsapp-bulk-orders.js',
			$deps,
			aaraa_asset_ver( 'assets/js/whatsapp-bulk-orders.js' ),
			true
		);

		$s        = self::settings();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR';

		wp_localize_script(
			'aaraa-wa-bulk',
			'AaraaWABulk',
			array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'fetchAction' => 'aaraa_whatsapp_fetch_templates',
				'fetchNonce'  => wp_create_nonce( self::TPL_NONCE ),
				'sendAction'  => 'aaraa_whatsapp_bulk_send',
				'sendNonce'   => wp_create_nonce( self::BULK_NONCE ),
				'enabled'     => empty( $s['enabled'] ) ? 0 : 1,
				'defs'        => get_option( self::DEFS_OPTION, array() ),
				'tokens'      => array(
					array( 't' => '{order_id}', 'label' => __( 'Order ID', 'aaraa-white-label-admin' ) ),
					array( 't' => '{status}', 'label' => __( 'Status', 'aaraa-white-label-admin' ) ),
					array( 't' => '{customer_name}', 'label' => __( 'Customer name', 'aaraa-white-label-admin' ) ),
					array( 't' => '{first_name}', 'label' => __( 'First name', 'aaraa-white-label-admin' ) ),
					array( 't' => '{total}', 'label' => __( 'Order total', 'aaraa-white-label-admin' ) ),
					array( 't' => '{currency}', 'label' => __( 'Currency', 'aaraa-white-label-admin' ) ),
					array( 't' => '{site}', 'label' => __( 'Site name', 'aaraa-white-label-admin' ) ),
				),
				'samples'     => array(
					'{order_id}'      => '1234',
					'{status}'        => __( 'Completed', 'aaraa-white-label-admin' ),
					'{customer_name}' => 'Ramesh Kumar',
					'{first_name}'    => 'Ramesh',
					'{total}'         => '499.00',
					'{currency}'      => $currency,
					'{site}'          => get_bloginfo( 'name' ),
				),
				'i18n'        => array(
					'button'          => __( 'Send WhatsApp', 'aaraa-white-label-admin' ),
					'title'           => __( 'Send WhatsApp to %d order(s)', 'aaraa-white-label-admin' ),
					'noSelection'     => __( 'Select one or more orders first.', 'aaraa-white-label-admin' ),
					'disabled'        => __( 'WhatsApp notifications are disabled. Enable them on AaraaNotifications → WhatsApp → General.', 'aaraa-white-label-admin' ),
					'searchTemplate'  => __( 'Search a template…', 'aaraa-white-label-admin' ),
					'selectTemplate'  => __( '— Select a template —', 'aaraa-white-label-admin' ),
					'template'        => __( 'Template', 'aaraa-white-label-admin' ),
					'language'        => __( 'Language', 'aaraa-white-label-admin' ),
					'variables'       => __( 'Message variables', 'aaraa-white-label-admin' ),
					'buttons'         => __( 'Buttons', 'aaraa-white-label-admin' ),
					'preview'         => __( 'Preview', 'aaraa-white-label-admin' ),
					'chooseToken'     => __( '— choose —', 'aaraa-white-label-admin' ),
					'customText'      => __( 'Custom text…', 'aaraa-white-label-admin' ),
					'customValue'     => __( 'Enter custom value', 'aaraa-white-label-admin' ),
					'noButtons'       => __( 'This template has no buttons that need a value.', 'aaraa-white-label-admin' ),
					'staticButton'    => __( 'no value needed', 'aaraa-white-label-admin' ),
					'urlButton'       => __( 'URL button', 'aaraa-white-label-admin' ),
					'notApproved'     => __( 'This template is not approved yet.', 'aaraa-white-label-admin' ),
					'pickTemplate'    => __( 'Choose a template to map its variables and preview the message.', 'aaraa-white-label-admin' ),
					'loading'         => __( 'Loading templates…', 'aaraa-white-label-admin' ),
					'loadFailed'      => __( 'Could not load templates. Check your WhatsApp connection.', 'aaraa-white-label-admin' ),
					'send'            => __( 'Send', 'aaraa-white-label-admin' ),
					'cancel'          => __( 'Cancel', 'aaraa-white-label-admin' ),
					'sending'         => __( 'Sending…', 'aaraa-white-label-admin' ),
					'chooseFirst'     => __( 'Choose a template first.', 'aaraa-white-label-admin' ),
					'done'            => __( 'Done: %1$d sent, %2$d failed, %3$d skipped (duplicate).', 'aaraa-white-label-admin' ),
					'requestFailed'   => __( 'Request failed. Please try again.', 'aaraa-white-label-admin' ),
				),
			)
		);
	}

	/**
	 * AJAX: send an approved template to the selected orders' customers,
	 * resolving tokens per order, adding an order note and logging each attempt.
	 *
	 * @return void
	 */
	public function ajax_bulk_send() {
		check_ajax_referer( self::BULK_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'aaraa-white-label-admin' ) ) );
		}

		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'WhatsApp notifications are disabled on the General tab.', 'aaraa-white-label-admin' ) ) );
		}

		$ids      = isset( $_POST['order_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['order_ids'] ) ) : array();
		$ids      = array_values( array_filter( array_unique( $ids ) ) );
		$template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'en';
		$params   = isset( $_POST['params'] ) ? sanitize_text_field( wp_unslash( $_POST['params'] ) ) : '';
		$buttons  = isset( $_POST['buttons'] ) ? self::sanitize_buttons_json( wp_unslash( $_POST['buttons'] ) ) : '';

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No orders selected.', 'aaraa-white-label-admin' ) ) );
		}
		if ( '' === $template ) {
			wp_send_json_error( array( 'message' => __( 'Choose a template.', 'aaraa-white-label-admin' ) ) );
		}

		$sent    = 0;
		$failed  = 0;
		$skipped = 0;
		$results = array();
		$seen    = array(); // Normalised phone => first order number, to avoid duplicates.

		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				$failed++;
				$results[] = array( 'id' => $id, 'ok' => false, 'msg' => __( 'Order not found', 'aaraa-white-label-admin' ) );
				continue;
			}
			$num   = $order->get_order_number();
			$phone = $order->get_billing_phone();
			if ( ! $phone ) {
				$failed++;
				$results[] = array( 'id' => $num, 'ok' => false, 'msg' => __( 'No billing phone', 'aaraa-white-label-admin' ) );
				self::log(
					array(
						'type'     => 'manual',
						'ref'      => $num,
						'status'   => $order->get_status(),
						'template' => $template,
						'result'   => 'failed',
						'response' => 'No billing phone number on the order',
					)
				);
				continue;
			}

			// One message per unique recipient — skip other orders that share the
			// same number so a customer isn't messaged multiple times.
			$norm = self::normalise_number( $phone );
			if ( '' !== $norm && isset( $seen[ $norm ] ) ) {
				$skipped++;
				$results[] = array(
					'id'  => $num,
					'ok'  => false,
					/* translators: %s: order number already messaged */
					'msg' => sprintf( __( 'Skipped: same number as #%s', 'aaraa-white-label-admin' ), $seen[ $norm ] ),
				);
				continue;
			}
			$seen[ $norm ] = $num;

			$tokens = array(
				'{order_id}'      => $num,
				'{status}'        => wc_get_order_status_name( $order->get_status() ),
				'{customer_name}' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'{first_name}'    => $order->get_billing_first_name(),
				'{total}'         => $order->get_total(),
				'{currency}'      => $order->get_currency(),
				'{site}'          => get_bloginfo( 'name' ),
			);

			$resolved_params  = self::resolve_params( $params, $tokens );
			$resolved_buttons = self::resolve_buttons( $buttons, $tokens );

			$ok = self::send_template(
				$phone,
				$template,
				$language,
				$resolved_params,
				array( 'type' => 'manual', 'ref' => $num, 'status' => $order->get_status() ),
				$resolved_buttons
			);

			if ( $ok ) {
				$sent++;
				$body = self::rendered_body( $template, $language, $resolved_params );
				$note = '' !== $body
					/* translators: %s: message body */
					? sprintf( __( 'WhatsApp sent:%s', 'aaraa-white-label-admin' ), "\n" . $body )
					/* translators: 1: template name, 2: phone */
					: sprintf( __( 'WhatsApp template "%1$s" sent to %2$s.', 'aaraa-white-label-admin' ), $template, $phone );
				$order->add_order_note( $note );
				$results[] = array( 'id' => $num, 'ok' => true, 'msg' => __( 'Sent', 'aaraa-white-label-admin' ) );
			} else {
				$failed++;
				$results[] = array( 'id' => $num, 'ok' => false, 'msg' => __( 'Send failed (see WhatsApp log)', 'aaraa-white-label-admin' ) );
			}
		}

		wp_send_json_success(
			array(
				'sent'    => $sent,
				'failed'  => $failed,
				'skipped' => $skipped,
				'results' => $results,
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Bulk sender for the Aaraa Customer 360 list.
	 * --------------------------------------------------------------------- */

	/**
	 * Load the "Send WhatsApp" bulk tool on the Aaraa Customer 360 list screen.
	 * Mirrors the Orders bulk tool but sends to the selected customers directly,
	 * resolving customer tokens and skipping same-day duplicates.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_customers_assets( $hook ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable
		if ( Customers_Admin::PAGE !== $page || '' !== $action ) {
			return; // Only the list view (not view/edit/new).
		}
		if ( ! Customers_Admin::current_user_can_manage() ) {
			return;
		}

		$deps = array( 'jquery' );
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			$deps[] = 'selectWoo';
		} elseif ( wp_script_is( 'select2', 'registered' ) ) {
			$deps[] = 'select2';
		}
		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
		} elseif ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		wp_enqueue_script(
			'aaraa-wa-customers',
			AARAA_WLA_URL . 'assets/js/whatsapp-bulk-customers.js',
			$deps,
			aaraa_asset_ver( 'assets/js/whatsapp-bulk-customers.js' ),
			true
		);

		$s        = self::settings();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR';

		wp_localize_script(
			'aaraa-wa-customers',
			'AaraaWABulk',
			array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'fetchAction' => 'aaraa_whatsapp_fetch_templates',
				'fetchNonce'  => wp_create_nonce( self::TPL_NONCE ),
				'sendAction'  => 'aaraa_whatsapp_customers_send',
				'sendNonce'   => wp_create_nonce( self::CUST_NONCE ),
				'enabled'     => empty( $s['enabled'] ) ? 0 : 1,
				'defs'        => get_option( self::DEFS_OPTION, array() ),
				'tokens'      => array(
					array( 't' => '{customer_name}', 'label' => __( 'Customer name', 'aaraa-white-label-admin' ) ),
					array( 't' => '{first_name}', 'label' => __( 'First name', 'aaraa-white-label-admin' ) ),
					array( 't' => '{mobile}', 'label' => __( 'Mobile', 'aaraa-white-label-admin' ) ),
					array( 't' => '{wallet_balance}', 'label' => __( 'Wallet balance', 'aaraa-white-label-admin' ) ),
					array( 't' => '{currency}', 'label' => __( 'Currency', 'aaraa-white-label-admin' ) ),
					array( 't' => '{site}', 'label' => __( 'Site name', 'aaraa-white-label-admin' ) ),
				),
				'samples'     => array(
					'{customer_name}'  => 'Ramesh Kumar',
					'{first_name}'     => 'Ramesh',
					'{mobile}'         => '9876543210',
					'{wallet_balance}' => '250.00',
					'{currency}'       => $currency,
					'{site}'           => get_bloginfo( 'name' ),
				),
				'i18n'        => array(
					'button'          => __( 'Send WhatsApp', 'aaraa-white-label-admin' ),
					'title'           => __( 'Send WhatsApp to %d customer(s)', 'aaraa-white-label-admin' ),
					'noSelection'     => __( 'Select one or more customers first.', 'aaraa-white-label-admin' ),
					'disabled'        => __( 'WhatsApp notifications are disabled. Enable them on AaraaNotifications → WhatsApp → General.', 'aaraa-white-label-admin' ),
					'searchTemplate'  => __( 'Search a template…', 'aaraa-white-label-admin' ),
					'selectTemplate'  => __( '— Select a template —', 'aaraa-white-label-admin' ),
					'template'        => __( 'Template', 'aaraa-white-label-admin' ),
					'language'        => __( 'Language', 'aaraa-white-label-admin' ),
					'variables'       => __( 'Message variables', 'aaraa-white-label-admin' ),
					'buttons'         => __( 'Buttons', 'aaraa-white-label-admin' ),
					'preview'         => __( 'Preview', 'aaraa-white-label-admin' ),
					'chooseToken'     => __( '— choose —', 'aaraa-white-label-admin' ),
					'customText'      => __( 'Custom text…', 'aaraa-white-label-admin' ),
					'customValue'     => __( 'Enter custom value', 'aaraa-white-label-admin' ),
					'noButtons'       => __( 'This template has no buttons that need a value.', 'aaraa-white-label-admin' ),
					'staticButton'    => __( 'no value needed', 'aaraa-white-label-admin' ),
					'urlButton'       => __( 'URL button', 'aaraa-white-label-admin' ),
					'notApproved'     => __( 'This template is not approved yet.', 'aaraa-white-label-admin' ),
					'pickTemplate'    => __( 'Choose a template to map its variables and preview the message.', 'aaraa-white-label-admin' ),
					'loading'         => __( 'Loading templates…', 'aaraa-white-label-admin' ),
					'loadFailed'      => __( 'Could not load templates. Check your WhatsApp connection.', 'aaraa-white-label-admin' ),
					'send'            => __( 'Send', 'aaraa-white-label-admin' ),
					'cancel'          => __( 'Cancel', 'aaraa-white-label-admin' ),
					'sending'         => __( 'Sending…', 'aaraa-white-label-admin' ),
					'chooseFirst'     => __( 'Choose a template first.', 'aaraa-white-label-admin' ),
					'done'            => __( 'Done: %1$d sent, %2$d failed, %3$d skipped (already sent today / duplicate).', 'aaraa-white-label-admin' ),
					'requestFailed'   => __( 'Request failed. Please try again.', 'aaraa-white-label-admin' ),
				),
			)
		);
	}

	/**
	 * AJAX: send an approved template to the selected customers, resolving tokens
	 * per customer. Skips any customer already sent this exact template today.
	 *
	 * @return void
	 */
	public function ajax_customers_send() {
		check_ajax_referer( self::CUST_NONCE, 'nonce' );
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'aaraa-white-label-admin' ) ) );
		}

		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'WhatsApp notifications are disabled on the General tab.', 'aaraa-white-label-admin' ) ) );
		}

		$ids      = isset( $_POST['customer_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['customer_ids'] ) ) : array();
		$ids      = array_values( array_filter( array_unique( $ids ) ) );
		$template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'en';
		$params   = isset( $_POST['params'] ) ? sanitize_text_field( wp_unslash( $_POST['params'] ) ) : '';
		$buttons  = isset( $_POST['buttons'] ) ? self::sanitize_buttons_json( wp_unslash( $_POST['buttons'] ) ) : '';

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No customers selected.', 'aaraa-white-label-admin' ) ) );
		}
		if ( '' === $template ) {
			wp_send_json_error( array( 'message' => __( 'Choose a template.', 'aaraa-white-label-admin' ) ) );
		}

		$sent    = 0;
		$failed  = 0;
		$skipped = 0;
		$results = array();
		$seen    = array(); // Normalised phone => first customer, to avoid in-request duplicates.

		$today = self::cust_sent_today(); // Today's sent-key set (auto-resets on a new day).

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		foreach ( $ids as $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				$failed++;
				$results[] = array( 'id' => $id, 'ok' => false, 'msg' => __( 'Customer not found', 'aaraa-white-label-admin' ) );
				continue;
			}

			$name  = trim( (string) $user->display_name );
			$phone = get_user_meta( $id, 'billing_phone', true );
			if ( '' === $phone ) {
				$phone = get_user_meta( $id, 'mobile', true );
			}
			if ( ! $phone ) {
				$failed++;
				$results[] = array( 'id' => $name ? $name : $id, 'ok' => false, 'msg' => __( 'No mobile number', 'aaraa-white-label-admin' ) );
				self::log(
					array(
						'type'     => 'manual',
						'ref'      => $id,
						'template' => $template,
						'result'   => 'failed',
						'response' => 'No mobile number for this customer',
					)
				);
				continue;
			}

			$norm = self::normalise_number( $phone );

			// (a) One message per unique recipient within this request.
			if ( '' !== $norm && isset( $seen[ $norm ] ) ) {
				$skipped++;
				$results[] = array(
					'id'  => $name ? $name : $id,
					'ok'  => false,
					/* translators: %s: customer already messaged in this run */
					'msg' => sprintf( __( 'Skipped: same number as %s', 'aaraa-white-label-admin' ), $seen[ $norm ] ),
				);
				continue;
			}

			// (b) Same message, same customer, same day — skip.
			$daily_key = self::cust_sent_key( $norm, $template );
			if ( '' !== $norm && isset( $today[ $daily_key ] ) ) {
				$skipped++;
				$results[] = array(
					'id'  => $name ? $name : $id,
					'ok'  => false,
					'msg' => __( 'Skipped: already sent today', 'aaraa-white-label-admin' ),
				);
				continue;
			}

			$seen[ $norm ] = $name ? $name : ( '#' . $id );

			$balance = class_exists( __NAMESPACE__ . '\\Customers_Admin' ) ? Customers_Admin::get_wallet_balance( $id ) : 0;

			$tokens = array(
				'{customer_name}'  => $name,
				'{first_name}'     => $user->first_name ? $user->first_name : $name,
				'{mobile}'         => $phone,
				'{wallet_balance}' => number_format_i18n( (float) $balance, 2 ),
				'{currency}'       => $currency,
				'{site}'           => get_bloginfo( 'name' ),
			);

			$resolved_params  = self::resolve_params( $params, $tokens );
			$resolved_buttons = self::resolve_buttons( $buttons, $tokens );

			$ok = self::send_template(
				$phone,
				$template,
				$language,
				$resolved_params,
				array( 'type' => 'manual', 'ref' => $id, 'status' => 'customer' ),
				$resolved_buttons
			);

			if ( $ok ) {
				$sent++;
				if ( '' !== $norm ) {
					$today[ $daily_key ] = time(); // Record so a repeat today is skipped.
				}
				$results[] = array( 'id' => $name ? $name : $id, 'ok' => true, 'msg' => __( 'Sent', 'aaraa-white-label-admin' ) );
			} else {
				$failed++;
				$results[] = array( 'id' => $name ? $name : $id, 'ok' => false, 'msg' => __( 'Send failed (see WhatsApp log)', 'aaraa-white-label-admin' ) );
			}
		}

		self::cust_sent_save( $today );

		wp_send_json_success(
			array(
				'sent'    => $sent,
				'failed'  => $failed,
				'skipped' => $skipped,
				'results' => $results,
			)
		);
	}

	/**
	 * A stable per-day de-dup key for a (mobile, template) pair.
	 *
	 * @param string $norm_mobile Normalised recipient number.
	 * @param string $template    Template name.
	 * @return string
	 */
	private static function cust_sent_key( $norm_mobile, $template ) {
		return md5( $norm_mobile . '|' . $template );
	}

	/**
	 * Today's set of already-sent keys. Resets automatically when the stored
	 * date is not today, so the guard only spans the current calendar day (site
	 * timezone).
	 *
	 * @return array<string,int> key => sent timestamp.
	 */
	private static function cust_sent_today() {
		$store = get_option( self::CUST_SENT_OPTION, array() );
		$date  = current_time( 'Y-m-d' );
		if ( ! is_array( $store ) || ! isset( $store['date'] ) || $store['date'] !== $date || ! isset( $store['keys'] ) || ! is_array( $store['keys'] ) ) {
			return array();
		}
		return $store['keys'];
	}

	/**
	 * Persist today's sent-key set (stamped with today's date).
	 *
	 * @param array<string,int> $keys key => sent timestamp.
	 * @return void
	 */
	private static function cust_sent_save( $keys ) {
		update_option(
			self::CUST_SENT_OPTION,
			array(
				'date' => current_time( 'Y-m-d' ),
				'keys' => is_array( $keys ) ? $keys : array(),
			),
			false
		);
	}

	/* --------------------------------------------------------------------- *
	 * Subscription payment reminder (single subscription edit screen).
	 * --------------------------------------------------------------------- */

	/**
	 * The subscription id being edited, from either the HPOS or legacy screen.
	 *
	 * @return int
	 */
	private function editing_subscription_id() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['post'] ) && 'shop_subscription' === get_post_type( absint( $_GET['post'] ) ) ) {
			return absint( $_GET['post'] );
		}
		if ( isset( $_GET['id'] ) ) {
			return absint( $_GET['id'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return 0;
	}

	/**
	 * Register the "Send Payment Reminder" metabox on the subscription edit
	 * screen. Uses the Subscriptions screen-id helper so it appears under both
	 * HPOS (woocommerce_page_wc-orders--shop_subscription) and legacy CPT storage.
	 *
	 * @param string                 $post_type Screen id / post type passed by WP/WC.
	 * @param \WP_Post|\WC_Order|null $object   Post or order object (HPOS).
	 * @return void
	 */
	public function add_reminder_metabox( $post_type = '', $object = null ) {
		if ( function_exists( 'wcs_get_page_screen_id' ) ) {
			$screen_id = wcs_get_page_screen_id( 'shop_subscription' );
		} elseif ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screen_id = wc_get_page_screen_id( 'shop-subscription' );
		} else {
			$screen_id = 'shop_subscription';
		}

		add_meta_box(
			'aaraa_sub_reminder',
			__( 'Send Payment Reminder (WhatsApp)', 'aaraa-white-label-admin' ),
			array( $this, 'render_reminder_metabox' ),
			$screen_id,
			'side',
			'high'
		);

		// Hide the theme's legacy metabox (functions.php) so it isn't duplicated
		// where it does render (CPT stores). Harmless when it isn't present.
		remove_meta_box( 'custom_subscription_info', $screen_id, 'side' );
	}

	/**
	 * Render the reminder metabox: a renewal-amount field and the button that
	 * opens the WhatsApp reminder popup (handled by whatsapp-subscription-reminder.js).
	 *
	 * @param \WP_Post|\WC_Order $object Post (CPT) or order/subscription (HPOS).
	 * @return void
	 */
	public function render_reminder_metabox( $object ) {
		$sub_id = 0;
		if ( is_a( $object, 'WP_Post' ) ) {
			$sub_id = (int) $object->ID;
		} elseif ( is_a( $object, 'WC_Abstract_Order' ) ) {
			$sub_id = (int) $object->get_id();
		}

		$default_amount = '';
		if ( $sub_id && function_exists( 'wcs_get_subscription' ) ) {
			$subscription = wcs_get_subscription( $sub_id );
			if ( $subscription ) {
				// Seed with roughly a monthly value (recurring total × 30), matching
				// the previous behaviour; the admin can edit it before sending.
				$default_amount = round( (float) $subscription->get_total() * 30 );
			}
		}

		echo '<p><label for="subscription_renewal_amount"><strong>' . esc_html__( 'Renewal amount', 'aaraa-white-label-admin' ) . '</strong></label></p>';
		echo '<input type="text" id="subscription_renewal_amount" value="' . esc_attr( $default_amount ) . '" style="width:100%;margin-bottom:10px;" />';
		echo '<button type="button" class="button button-primary" id="aaraa-sub-reminder-btn" data-subscription="' . esc_attr( $sub_id ) . '">' . esc_html__( 'Send Payment Reminder (WhatsApp)', 'aaraa-white-label-admin' ) . '</button>';
		echo '<p class="description" style="margin-top:8px;">' . esc_html__( 'Opens a popup to pick a template and send a payment link to the customer.', 'aaraa-white-label-admin' ) . '</p>';
		echo '<script>document.getElementById("subscription_renewal_amount").addEventListener("input",function(){this.value=this.value.replace(/\D/g,"");});</script>';
	}

	/**
	 * Enqueue the reminder modal on the subscription edit screen (HPOS + legacy).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_sub_reminder_assets( $hook ) {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen && isset( $screen->id ) ? $screen->id : '';

		$is_sub_edit = ( 'woocommerce_page_wc-orders--shop_subscription' === $screen_id )
			|| ( 'shop_subscription' === $screen_id )
			|| ( 'post.php' === $hook && isset( $_GET['post'] ) && 'shop_subscription' === get_post_type( absint( $_GET['post'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $is_sub_edit ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$sub_id = $this->editing_subscription_id();

		$deps = array( 'jquery' );
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			$deps[] = 'selectWoo';
		} elseif ( wp_script_is( 'select2', 'registered' ) ) {
			$deps[] = 'select2';
		}
		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
		} elseif ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		wp_enqueue_script(
			'aaraa-wa-sub',
			AARAA_WLA_URL . 'assets/js/whatsapp-subscription-reminder.js',
			$deps,
			aaraa_asset_ver( 'assets/js/whatsapp-subscription-reminder.js' ),
			true
		);

		$s        = self::settings();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR';

		wp_localize_script(
			'aaraa-wa-sub',
			'AaraaWASub',
			array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'fetchAction' => 'aaraa_whatsapp_fetch_templates',
				'fetchNonce'  => wp_create_nonce( self::TPL_NONCE ),
				'sendAction'  => 'aaraa_whatsapp_sub_reminder',
				'sendNonce'   => wp_create_nonce( self::SUB_NONCE ),
				'enabled'     => empty( $s['enabled'] ) ? 0 : 1,
				'subId'       => $sub_id,
				'defs'        => get_option( self::DEFS_OPTION, array() ),
				'tokens'      => array(
					array( 't' => '{payment_link}', 'label' => __( 'Payment link', 'aaraa-white-label-admin' ) ),
					array( 't' => '{renewal_amount}', 'label' => __( 'Renewal amount', 'aaraa-white-label-admin' ) ),
					array( 't' => '{customer_name}', 'label' => __( 'Customer name', 'aaraa-white-label-admin' ) ),
					array( 't' => '{first_name}', 'label' => __( 'First name', 'aaraa-white-label-admin' ) ),
					array( 't' => '{subscription_id}', 'label' => __( 'Subscription ID', 'aaraa-white-label-admin' ) ),
					array( 't' => '{currency}', 'label' => __( 'Currency', 'aaraa-white-label-admin' ) ),
					array( 't' => '{site}', 'label' => __( 'Site name', 'aaraa-white-label-admin' ) ),
				),
				'samples'     => array(
					'{payment_link}'    => home_url( '/' . Subscription_Renewal::ENDPOINT . '/?sub=' . $sub_id . '&amt=500.00&t=…' ),
					'{renewal_amount}'  => '500.00',
					'{customer_name}'   => 'Ramesh Kumar',
					'{first_name}'      => 'Ramesh',
					'{subscription_id}' => (string) $sub_id,
					'{currency}'        => $currency,
					'{site}'            => get_bloginfo( 'name' ),
				),
				'i18n'        => array(
					'title'          => __( 'Send Payment Reminder', 'aaraa-white-label-admin' ),
					'disabled'       => __( 'WhatsApp notifications are disabled. Enable them on AaraaNotifications → WhatsApp → General.', 'aaraa-white-label-admin' ),
					'amount'         => __( 'Renewal amount', 'aaraa-white-label-admin' ),
					'amountHint'     => __( 'The customer pays this amount; it is credited to their wallet and the subscription is reactivated.', 'aaraa-white-label-admin' ),
					'amountRequired' => __( 'Enter a renewal amount greater than zero.', 'aaraa-white-label-admin' ),
					'linkTip'        => __( 'Map a variable or a URL button to {payment_link} so the message carries the payment link.', 'aaraa-white-label-admin' ),
					'selectTemplate' => __( '— Select a template —', 'aaraa-white-label-admin' ),
					'template'       => __( 'Template', 'aaraa-white-label-admin' ),
					'language'       => __( 'Language', 'aaraa-white-label-admin' ),
					'variables'      => __( 'Message variables', 'aaraa-white-label-admin' ),
					'buttons'        => __( 'Buttons', 'aaraa-white-label-admin' ),
					'preview'        => __( 'Preview', 'aaraa-white-label-admin' ),
					'chooseToken'    => __( '— choose —', 'aaraa-white-label-admin' ),
					'customText'     => __( 'Custom text…', 'aaraa-white-label-admin' ),
					'customValue'    => __( 'Enter custom value', 'aaraa-white-label-admin' ),
					'noButtons'      => __( 'This template has no buttons that need a value.', 'aaraa-white-label-admin' ),
					'staticButton'   => __( 'no value needed', 'aaraa-white-label-admin' ),
					'urlButton'      => __( 'URL button', 'aaraa-white-label-admin' ),
					'notApproved'    => __( 'This template is not approved yet.', 'aaraa-white-label-admin' ),
					'pickTemplate'   => __( 'Choose a template to map its variables and preview the message.', 'aaraa-white-label-admin' ),
					'loading'        => __( 'Loading templates…', 'aaraa-white-label-admin' ),
					'loadFailed'     => __( 'Could not load templates. Check your WhatsApp connection.', 'aaraa-white-label-admin' ),
					'send'           => __( 'Send reminder', 'aaraa-white-label-admin' ),
					'cancel'         => __( 'Cancel', 'aaraa-white-label-admin' ),
					'sending'        => __( 'Sending…', 'aaraa-white-label-admin' ),
					'chooseFirst'    => __( 'Choose a template first.', 'aaraa-white-label-admin' ),
					'sent'           => __( 'Payment reminder sent.', 'aaraa-white-label-admin' ),
					'requestFailed'  => __( 'Request failed. Please try again.', 'aaraa-white-label-admin' ),
				),
			)
		);
	}

	/**
	 * AJAX: send a payment-reminder WhatsApp for a single subscription, injecting
	 * a signed payment link for the entered renewal amount, and note it on the
	 * subscription.
	 *
	 * @return void
	 */
	public function ajax_sub_reminder_send() {
		check_ajax_referer( self::SUB_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'aaraa-white-label-admin' ) ) );
		}

		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'WhatsApp notifications are disabled on the General tab.', 'aaraa-white-label-admin' ) ) );
		}

		$sub_id   = isset( $_POST['subscription_id'] ) ? absint( wp_unslash( $_POST['subscription_id'] ) ) : 0;
		$amount   = isset( $_POST['renewal_amount'] ) ? round( (float) wp_unslash( $_POST['renewal_amount'] ), 2 ) : 0.0;
		$template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'en';
		$params   = isset( $_POST['params'] ) ? sanitize_text_field( wp_unslash( $_POST['params'] ) ) : '';
		$buttons  = isset( $_POST['buttons'] ) ? self::sanitize_buttons_json( wp_unslash( $_POST['buttons'] ) ) : '';

		if ( '' === $template ) {
			wp_send_json_error( array( 'message' => __( 'Choose a template.', 'aaraa-white-label-admin' ) ) );
		}
		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Enter a renewal amount greater than zero.', 'aaraa-white-label-admin' ) ) );
		}

		$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $sub_id ) : false;
		if ( ! $subscription ) {
			wp_send_json_error( array( 'message' => __( 'Subscription not found.', 'aaraa-white-label-admin' ) ) );
		}

		$phone = $subscription->get_billing_phone();
		if ( ! $phone ) {
			wp_send_json_error( array( 'message' => __( 'This subscription has no billing phone number.', 'aaraa-white-label-admin' ) ) );
		}

		$link     = Subscription_Renewal::payment_link( $sub_id, $amount );
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		$tokens = array(
			'{payment_link}'    => $link,
			'{renewal_amount}'  => number_format_i18n( $amount, 2 ),
			'{customer_name}'   => trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ),
			'{first_name}'      => $subscription->get_billing_first_name(),
			'{subscription_id}' => $subscription->get_order_number(),
			'{currency}'        => $currency,
			'{site}'            => get_bloginfo( 'name' ),
		);

		$resolved_params  = self::resolve_params( $params, $tokens );
		$resolved_buttons = self::resolve_buttons( $buttons, $tokens );

		$ok = self::send_template(
			$phone,
			$template,
			$language,
			$resolved_params,
			array( 'type' => 'subscription', 'ref' => $subscription->get_order_number(), 'status' => $subscription->get_status() ),
			$resolved_buttons
		);

		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Send failed — see the WhatsApp log for details.', 'aaraa-white-label-admin' ) ) );
		}

		// Record the requested amount and note the reminder on the subscription.
		$subscription->update_meta_data( Subscription_Renewal::SUB_PENDING, self::amt_str_public( $amount ) );
		$subscription->save();

		$money = function_exists( 'wc_price' ) ? wp_strip_all_tags( html_entity_decode( wc_price( $amount ) ) ) : (string) $amount;
		$body  = self::rendered_body( $template, $language, $resolved_params );
		$note  = '' !== $body
			/* translators: 1: amount, 2: message body */
			? sprintf( __( 'Payment reminder WhatsApp sent (renewal %1$s):%2$s', 'aaraa-white-label-admin' ), $money, "\n" . $body )
			/* translators: 1: amount, 2: link */
			: sprintf( __( 'Payment reminder WhatsApp sent (renewal %1$s). Link: %2$s', 'aaraa-white-label-admin' ), $money, $link );
		$subscription->add_order_note( $note );

		wp_send_json_success( array( 'message' => __( 'Payment reminder sent.', 'aaraa-white-label-admin' ), 'link' => $link ) );
	}

	/**
	 * Public fixed-decimal formatter (mirrors Subscription_Renewal::amt_str).
	 *
	 * @param float|string $amount Amount.
	 * @return string
	 */
	private static function amt_str_public( $amount ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		return number_format( (float) $amount, $decimals, '.', '' );
	}

	/**
	 * Render the tabbed WhatsApp settings screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}

		$tabs = array(
			'general'   => __( 'General', 'aaraa-white-label-admin' ),
			'templates' => __( 'Template Manager', 'aaraa-white-label-admin' ),
			'status'    => __( 'Template Config', 'aaraa-white-label-admin' ),
			'logs'      => __( 'Logs', 'aaraa-white-label-admin' ),
		);
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'general';
		}
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap aaraa-notify aaraa-whatsapp">
			<h1><?php esc_html_e( 'WhatsApp Notifications', 'aaraa-white-label-admin' ); ?></h1>

			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'WhatsApp settings saved.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php elseif ( 'log_cleared' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'WhatsApp log cleared.', 'aaraa-white-label-admin' ); ?></p></div>
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
				case 'templates':
					$this->render_templates_tab();
					break;
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
			.aaraa-whatsapp .aaraa-tpl-table { margin: 8px 0 24px; background: #fff; border: 1px solid #c3c4c7; border-collapse: collapse; width: 100%; max-width: 1080px; }
			.aaraa-whatsapp .aaraa-tpl-table th, .aaraa-whatsapp .aaraa-tpl-table td { border: 1px solid #e2e4e7; padding: 8px 10px; vertical-align: top; text-align: left; }
			.aaraa-whatsapp .aaraa-tpl-table thead th { background: #f6f7f7; }
			.aaraa-whatsapp .aaraa-tpl-table td.status-col { white-space: nowrap; font-weight: 600; }
			.aaraa-whatsapp .aaraa-tpl-hint { color: #646970; font-size: 11px; }
			.aaraa-whatsapp .aaraa-log-toolbar { margin: 8px 0; display: flex; align-items: center; gap: 8px; }
			.aaraa-whatsapp .aaraa-log-table td { font-size: 12px; word-break: break-word; }
			.aaraa-whatsapp .aaraa-log-full { max-width: none; width: 100%; }
			.aaraa-whatsapp .aaraa-log-message { white-space: pre-wrap; min-width: 240px; line-height: 1.4; }
			.aaraa-whatsapp .aaraa-log-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
			.aaraa-whatsapp .aaraa-log-badge.is-sent { background: #e5f5ec; color: #1a7f45; }
			.aaraa-whatsapp .aaraa-log-badge.is-failed { background: #fbeaea; color: #b32d2e; }
			.aaraa-whatsapp .wa-status-pill { display:inline-block; padding:1px 7px; border-radius:9px; font-size:11px; font-weight:600; text-transform:capitalize; }
			.aaraa-whatsapp .wa-status-pill.approved { background:#e5f5ec; color:#1a7f45; }
			.aaraa-whatsapp .wa-status-pill.rejected { background:#fbeaea; color:#b32d2e; }
			.aaraa-whatsapp .wa-status-pill.pending { background:#fef8e7; color:#8a6d1a; }

			/* Status Config cards */
			.aaraa-whatsapp .wa-cfg-list { margin: 8px 0 26px; max-width: 1080px; }
			.aaraa-whatsapp .wa-cfg-card { background:#fff; border:1px solid #dcdcde; border-radius:6px; margin:0 0 12px; overflow:hidden; }
			.aaraa-whatsapp .wa-cfg-card.is-on { border-color:#8bd0aa; }
			.aaraa-whatsapp .wa-cfg-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#f6f7f7; border-bottom:1px solid #e2e4e7; }
			.aaraa-whatsapp .wa-cfg-enable { display:inline-flex; align-items:center; gap:6px; font-weight:600; white-space:nowrap; }
			.aaraa-whatsapp .wa-cfg-status { color:#1d2327; font-weight:600; }
			.aaraa-whatsapp .wa-cfg-status code { font-weight:400; }
			.aaraa-whatsapp .wa-cfg-body { padding:12px 14px; }
			.aaraa-whatsapp .wa-cfg-baseline p { margin:0 0 10px; }
			.aaraa-whatsapp .wa-cfg-rich { display:grid; grid-template-columns: 1fr 340px; gap:18px; }
			@media (max-width:960px){ .aaraa-whatsapp .wa-cfg-rich { grid-template-columns:1fr; } }
			.aaraa-whatsapp .wa-cfg-rich .wa-field { margin:0 0 12px; }
			.aaraa-whatsapp .wa-cfg-rich .wa-field > label { display:block; font-weight:600; margin:0 0 4px; }
			.aaraa-whatsapp .wa-cfg-rich .wa-hint { color:#646970; font-size:11px; }

			/* Searchable picker */
			.aaraa-whatsapp .wa-pick-holder { max-width:440px; }
			.aaraa-whatsapp .wa-cfg-extra { margin:0 0 12px; }
			.aaraa-whatsapp .wa-pick { position:relative; max-width:420px; }
			.aaraa-whatsapp .wa-pick-input { width:100%; }
			.aaraa-whatsapp .wa-pick-menu { position:absolute; z-index:20; left:0; right:0; top:100%; background:#fff; border:1px solid #8c8f94; border-top:none; max-height:240px; overflow:auto; box-shadow:0 6px 14px rgba(0,0,0,.12); }
			.aaraa-whatsapp .wa-pick-item { padding:7px 10px; cursor:pointer; display:flex; justify-content:space-between; gap:10px; }
			.aaraa-whatsapp .wa-pick-item:hover, .aaraa-whatsapp .wa-pick-item.is-active { background:#f0f6fc; }
			.aaraa-whatsapp .wa-pick-item code { font-size:12px; }
			.aaraa-whatsapp .wa-pick-item .wa-pick-meta { color:#646970; font-size:11px; white-space:nowrap; }
			.aaraa-whatsapp .wa-pick-empty { padding:8px 10px; color:#646970; }

			/* Variable / button mapping rows */
			.aaraa-whatsapp .wa-map-row { display:flex; align-items:center; gap:8px; margin:0 0 8px; }
			.aaraa-whatsapp .wa-map-row .wa-var-slot { flex:0 0 46px; font-weight:600; color:#2271b1; }
			.aaraa-whatsapp .wa-map-row select { min-width:190px; }
			.aaraa-whatsapp .wa-map-row .wa-var-custom { flex:1; }
			.aaraa-whatsapp .wa-btn-row { margin:0 0 8px; }
			.aaraa-whatsapp .wa-btn-row .wa-btn-label { display:block; font-size:12px; color:#1d2327; margin:0 0 3px; }
			.aaraa-whatsapp .wa-btn-row .wa-btn-static { color:#646970; font-style:italic; }

			/* Preview */
			.aaraa-whatsapp .wa-preview-wrap { background:#e6ddd3; border-radius:8px; padding:14px; }
			.aaraa-whatsapp .wa-bubble { background:#fff; border-radius:8px; padding:10px 12px; box-shadow:0 1px 1px rgba(0,0,0,.13); font-size:13px; line-height:1.5; white-space:pre-wrap; word-break:break-word; position:relative; }
			.aaraa-whatsapp .wa-bubble::after { content:""; position:absolute; top:0; left:-7px; border:7px solid transparent; border-top-color:#fff; border-right-color:#fff; }
			.aaraa-whatsapp .wa-bubble .wa-fill { background:#dff5e6; border-radius:3px; padding:0 3px; }
			.aaraa-whatsapp .wa-bubble .wa-miss { background:#fbeaea; color:#b32d2e; border-radius:3px; padding:0 3px; }
			.aaraa-whatsapp .wa-bubble-btns { margin-top:8px; border-top:1px solid #eee; }
			.aaraa-whatsapp .wa-bubble-btns .wa-bbtn { text-align:center; color:#0a7cff; padding:8px 4px 2px; font-weight:500; }
			.aaraa-whatsapp .wa-preview-empty { color:#5b5b5b; font-size:12px; }
		</style>
		<?php
	}

	/**
	 * General tab — Cloud API credentials.
	 *
	 * @return void
	 */
	private function render_general_tab() {
		$s = self::settings();
		?>
		<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'general' ), admin_url( 'admin.php' ) ) ); ?>">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTION ); ?>" value="1" />
			<input type="hidden" name="aaraa_tab" value="general" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Notification', 'aaraa-white-label-admin' ); ?></th>
					<td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Send WhatsApp on order / subscription status changes', 'aaraa-white-label-admin' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Service Provider', 'aaraa-white-label-admin' ); ?></th>
					<td>
						<label><input type="radio" checked disabled /> <?php esc_html_e( 'BChatNow.com', 'aaraa-white-label-admin' ); ?></label>
						<p class="description"><?php esc_html_e( 'More providers will be available in future updates.', 'aaraa-white-label-admin' ); ?></p>
					</td>
				</tr>
				<tr style="display:none;" >
					<th scope="row"><label for="wa-domain"><?php esc_html_e( 'Domain', 'aaraa-white-label-admin' ); ?></label></th>
					<td><input name="domain" id="wa-domain" type="text" class="regular-text code" value="<?php echo esc_attr( $s['domain'] ); ?>" placeholder="https://graph.facebook.com" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wa-version"><?php esc_html_e( 'API Version', 'aaraa-white-label-admin' ); ?></label></th>
					<td><input name="api_version" id="wa-version" type="text" class="small-text" value="<?php echo esc_attr( $s['api_version'] ); ?>" placeholder="v18.0" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wa-waba"><?php esc_html_e( 'WABA ID', 'aaraa-white-label-admin' ); ?></label></th>
					<td><input name="waba_id" id="wa-waba" type="text" class="regular-text" value="<?php echo esc_attr( $s['waba_id'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wa-token"><?php esc_html_e( 'Access Token', 'aaraa-white-label-admin' ); ?></label></th>
					<td><textarea name="access_token" id="wa-token" rows="3" class="large-text code" autocomplete="off"><?php echo esc_textarea( $s['access_token'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="wa-phone-id"><?php esc_html_e( 'Phone Number ID', 'aaraa-white-label-admin' ); ?></label></th>
					<td><input name="phone_number_id" id="wa-phone-id" type="text" class="regular-text" value="<?php echo esc_attr( $s['phone_number_id'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wa-admin"><?php esc_html_e( 'Admin WhatsApp Number', 'aaraa-white-label-admin' ); ?></label></th>
					<td>
						<input name="admin_numbers" id="wa-admin" type="text" class="regular-text" value="<?php echo esc_attr( $s['admin_numbers'] ); ?>" />
						<p class="description"><?php esc_html_e( 'For multiple numbers use comma and include ISD code. Example: 919874563210,919845612345', 'aaraa-white-label-admin' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save WhatsApp Settings', 'aaraa-white-label-admin' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Template Manager tab — list approved WABA templates.
	 *
	 * @return void
	 */
	private function render_templates_tab() {
		$s          = self::settings();
		$configured = ! empty( $s['access_token'] ) && ! empty( $s['waba_id'] );
		?>
		<p class="description"><?php esc_html_e( 'Approved message templates on your WhatsApp Business Account. Use these exact names and languages on the Template Config tab.', 'aaraa-white-label-admin' ); ?></p>

		<?php if ( ! $configured ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Enter the WABA ID and Access Token on the General tab first.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>

		<p>
			<button type="button" class="button button-secondary" id="wa-fetch-templates"><?php esc_html_e( 'Refresh templates', 'aaraa-white-label-admin' ); ?></button>
			<span class="spinner wa-tpl-spinner" style="float:none;margin:0 0 0 6px;"></span>
		</p>

		<div id="wa-templates-result">
			<p class="description"><?php esc_html_e( 'Loading approved templates…', 'aaraa-white-label-admin' ); ?></p>
		</div>

		<script>
		jQuery( function ( $ ) {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( wp_create_nonce( self::TPL_NONCE ) ); ?>;
			var busy    = false;

			function loadTemplates() {
				if ( busy ) { return; }
				busy = true;
				var $spin = $( '.wa-tpl-spinner' ).addClass( 'is-active' );
				$( '#wa-templates-result' ).html( '<p class="description"><?php echo esc_js( __( 'Fetching… this can take a few seconds.', 'aaraa-white-label-admin' ) ); ?></p>' );

				$.post( ajaxUrl, { action: 'aaraa_whatsapp_fetch_templates', nonce: nonce } )
					.done( function ( resp ) {
						if ( resp && resp.success ) {
							$( '#wa-templates-result' ).html( resp.data.html );
						} else {
							var msg  = ( resp && resp.data && resp.data.message ) ? resp.data.message : '<?php echo esc_js( __( 'Could not fetch templates.', 'aaraa-white-label-admin' ) ); ?>';
							var hint = ( resp && resp.data && resp.data.hint ) ? '<p>' + resp.data.hint + '</p>' : '';
							$( '#wa-templates-result' ).html( '<div class="notice notice-error inline"><p><strong>' + msg + '</strong></p>' + hint + '</div>' );
						}
					} )
					.fail( function ( xhr ) {
						$( '#wa-templates-result' ).html( '<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Request failed', 'aaraa-white-label-admin' ) ); ?> (' + xhr.status + ').</p></div>' );
					} )
					.always( function () {
						busy = false;
						$spin.removeClass( 'is-active' );
					} );
			}

			$( '#wa-fetch-templates' ).on( 'click', loadTemplates );

			// Auto-load on page load (non-blocking, so the tab never hangs).
			loadTemplates();
		} );
		</script>
		<?php
	}

	/**
	 * AJAX: fetch the WABA templates and return the rendered table (or an error).
	 *
	 * @return void
	 */
	public function ajax_fetch_templates() {
		check_ajax_referer( self::TPL_NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'aaraa-white-label-admin' ) ) );
		}

		$result = self::fetch_templates();
		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message' => $result['error'],
					'hint'    => isset( $result['hint'] ) ? $result['hint'] : '',
				)
			);
		}

		$defs = $this->template_defs( $result['items'] );
		// Cache so the log can render the real message body for each send.
		update_option( self::DEFS_OPTION, $defs, false );

		wp_send_json_success(
			array(
				'html' => $this->templates_table_html( $result['items'] ),
				'defs' => $defs,
			)
		);
	}

	/**
	 * Render the human-readable message body for a send, using the cached
	 * template definitions ({{n}} replaced by the ordered params). Returns ''
	 * when the template body is not cached (fetch the Template Manager once).
	 *
	 * @param string        $template Template name.
	 * @param string        $language Language code.
	 * @param array<string> $params   Ordered body params.
	 * @return string
	 */
	/**
	 * Make sure the template definitions are cached so the log can render real
	 * message bodies. Best-effort: fetches once when the cache is empty, then
	 * backs off for a while if the fetch fails (host connectivity), so the Logs
	 * screen never blocks on repeated slow calls.
	 *
	 * @return void
	 */
	private function ensure_defs() {
		$defs = get_option( self::DEFS_OPTION, array() );
		if ( is_array( $defs ) && ! empty( $defs ) ) {
			return;
		}
		if ( get_transient( 'aaraa_wa_defs_fetching' ) ) {
			return;
		}
		set_transient( 'aaraa_wa_defs_fetching', 1, 5 * MINUTE_IN_SECONDS );

		$result = self::fetch_templates();
		if ( ! empty( $result['ok'] ) && ! empty( $result['items'] ) ) {
			update_option( self::DEFS_OPTION, $this->template_defs( $result['items'] ), false );
			delete_transient( 'aaraa_wa_defs_fetching' );
		}
	}

	/**
	 * Render the human-readable message body for a send, using the cached
	 * template definitions ({{n}} replaced by the ordered params). Returns ''
	 * when the template body is not cached (fetch the Template Manager once).
	 *
	 * @param string        $template Template name.
	 * @param string        $language Language code.
	 * @param array<string> $params   Ordered body params.
	 * @return string
	 */
	private static function rendered_body( $template, $language, $params ) {
		$defs = get_option( self::DEFS_OPTION, array() );
		if ( ! is_array( $defs ) || empty( $defs[ $template ]['langs'] ) ) {
			return '';
		}
		$langs = $defs[ $template ]['langs'];
		$ldef  = isset( $langs[ $language ] ) ? $langs[ $language ] : reset( $langs );
		$body  = isset( $ldef['body'] ) ? (string) $ldef['body'] : '';
		if ( '' === $body ) {
			return '';
		}
		$params = array_values( $params );
		return preg_replace_callback(
			'/\{\{\s*(\d+)\s*\}\}/',
			static function ( $m ) use ( $params ) {
				$idx = (int) $m[1] - 1;
				return isset( $params[ $idx ] ) ? $params[ $idx ] : $m[0];
			},
			$body
		);
	}

	/**
	 * Reduce the raw Graph template list to a compact map the Status Config UI
	 * uses to drive its picker, variable mapping, button mapping and preview.
	 *
	 * Shape: [ name => { category, langs: { <lang>: { status, body, vars, buttons } } } ].
	 * A button entry is { index, type, text, url, has_var } where has_var marks a
	 * dynamic URL button (its URL contains a {{n}} placeholder to fill at send time).
	 *
	 * @param array $items Template rows from the Graph API.
	 * @return array<string,mixed>
	 */
	private function template_defs( $items ) {
		$defs = array();
		foreach ( (array) $items as $tpl ) {
			$name = isset( $tpl['name'] ) ? (string) $tpl['name'] : '';
			if ( '' === $name ) {
				continue;
			}
			$lang       = isset( $tpl['language'] ) ? (string) $tpl['language'] : 'en';
			$components = ( isset( $tpl['components'] ) && is_array( $tpl['components'] ) ) ? $tpl['components'] : array();

			$body    = '';
			$vars    = 0;
			$buttons = array();
			foreach ( $components as $c ) {
				$type = isset( $c['type'] ) ? strtoupper( $c['type'] ) : '';
				if ( 'BODY' === $type && isset( $c['text'] ) ) {
					$body = (string) $c['text'];
					preg_match_all( '/\{\{\s*\d+\s*\}\}/', $body, $m );
					$vars = count( $m[0] );
				} elseif ( 'BUTTONS' === $type && ! empty( $c['buttons'] ) && is_array( $c['buttons'] ) ) {
					foreach ( $c['buttons'] as $i => $b ) {
						$btype = isset( $b['type'] ) ? strtoupper( $b['type'] ) : '';
						$url   = isset( $b['url'] ) ? (string) $b['url'] : '';
						$buttons[] = array(
							'index'   => (int) $i,
							'type'    => $btype,
							'text'    => isset( $b['text'] ) ? (string) $b['text'] : '',
							'url'     => $url,
							'has_var' => ( 'URL' === $btype && preg_match( '/\{\{\s*\d+\s*\}\}/', $url ) ) ? 1 : 0,
						);
					}
				}
			}

			if ( ! isset( $defs[ $name ] ) ) {
				$defs[ $name ] = array(
					'category' => isset( $tpl['category'] ) ? strtolower( (string) $tpl['category'] ) : '',
					'langs'    => array(),
				);
			}
			$defs[ $name ]['langs'][ $lang ] = array(
				'status'  => isset( $tpl['status'] ) ? strtoupper( (string) $tpl['status'] ) : '',
				'body'    => $body,
				'vars'    => $vars,
				'buttons' => $buttons,
			);
		}
		return $defs;
	}

	/**
	 * Build the templates table HTML.
	 *
	 * @param array $items Template rows from the Graph API.
	 * @return string
	 */
	private function templates_table_html( $items ) {
		ob_start();
		?>
		<table class="aaraa-tpl-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Template name', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'Language', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Category', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'Body vars', 'aaraa-white-label-admin' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No templates found on this WABA.', 'aaraa-white-label-admin' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $items as $tpl ) : ?>
					<?php
					$status_class = strtolower( $tpl['status'] ?? '' );
					$vars         = $this->count_body_vars( $tpl['components'] ?? array() );
					?>
					<tr>
						<td><code><?php echo esc_html( $tpl['name'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $tpl['language'] ?? '' ); ?></td>
						<td><?php echo esc_html( strtolower( $tpl['category'] ?? '' ) ); ?></td>
						<td><span class="wa-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( strtolower( $tpl['status'] ?? '' ) ); ?></span></td>
						<td style="text-align:center;"><?php echo esc_html( $vars ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Count the {{n}} body variables declared on a template's BODY component.
	 *
	 * @param array $components Template components.
	 * @return int
	 */
	private function count_body_vars( $components ) {
		if ( ! is_array( $components ) ) {
			return 0;
		}
		foreach ( $components as $c ) {
			if ( isset( $c['type'] ) && 'BODY' === strtoupper( $c['type'] ) && isset( $c['text'] ) ) {
				preg_match_all( '/\{\{\s*\d+\s*\}\}/', $c['text'], $m );
				return count( $m[0] );
			}
		}
		return 0;
	}

	/**
	 * Template Config tab — map order / subscription statuses, OTP and wallet
	 * events to approved templates.
	 *
	 * @return void
	 */
	private function render_status_tab() {
		$s              = self::settings();
		$order_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$sub_statuses   = function_exists( 'wcs_get_subscription_statuses' ) ? wcs_get_subscription_statuses() : array();
		?>
		<p class="description">
			<?php esc_html_e( 'Pick an approved template for each event, then map its variables to your data. A live preview shows what the customer will receive.', 'aaraa-white-label-admin' ); ?>
		</p>
		<div id="wa-status-loading" class="notice notice-info inline" style="display:none;"><p><span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span><?php esc_html_e( 'Loading your approved templates…', 'aaraa-white-label-admin' ); ?></p></div>

		<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'status' ), admin_url( 'admin.php' ) ) ); ?>">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTION ); ?>" value="1" />
			<input type="hidden" name="aaraa_tab" value="status" />

			<?php
			$this->render_status_group(
				__( 'Order Status Templates', 'aaraa-white-label-admin' ),
				'order',
				$order_statuses,
				$s['order']
			);
			$this->render_status_group(
				__( 'Subscription Status Templates', 'aaraa-white-label-admin' ),
				'subscription',
				$sub_statuses,
				$s['subscription']
			);

			// OTP.
			echo '<h2 class="title">' . esc_html__( 'OTP Template', 'aaraa-white-label-admin' ) . '</h2>';
			echo '<div class="wa-cfg-list">';
			$this->render_config_card( 'otp', 'otp', 'otp', __( 'Login OTP', 'aaraa-white-label-admin' ), $s['otp'] );
			echo '</div>';

			// Pause Subscription.
			echo '<h2 class="title">' . esc_html__( 'Pause Subscription Template', 'aaraa-white-label-admin' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Sent when a pause is saved on the delivery calendar. Map the approved template params to: {customer_name}, {first_name}, {pause_dates}, {resume_dates}, {subscription_id}, {site}.', 'aaraa-white-label-admin' ) . '</p>';
			echo '<div class="wa-cfg-list">';
			$this->render_config_card( 'pause', 'pause', 'pause', __( 'Pause saved', 'aaraa-white-label-admin' ), $s['pause'] );
			echo '</div>';

			// Wallet.
			echo '<h2 class="title">' . esc_html__( 'Wallet Templates', 'aaraa-white-label-admin' ) . '</h2>';
			echo '<div class="wa-cfg-list">';
			$this->render_config_card( 'wallet', 'credit', 'wallet[credit]', __( 'Wallet credited', 'aaraa-white-label-admin' ), $s['wallet']['credit'] );
			$this->render_config_card( 'wallet', 'debit', 'wallet[debit]', __( 'Wallet debited', 'aaraa-white-label-admin' ), $s['wallet']['debit'] );
			$this->render_config_card( 'wallet', 'low', 'wallet[low]', __( 'Wallet low balance', 'aaraa-white-label-admin' ), $s['wallet']['low'], $this->threshold_field( $s['wallet']['low'] ) );
			echo '</div>';
			?>

			<?php submit_button( __( 'Save Template Config', 'aaraa-white-label-admin' ) ); ?>
		</form>
		<?php
	}

	/**
	 * The low-balance threshold field markup for the wallet "low" card.
	 *
	 * @param array<string,mixed> $row Wallet low row.
	 * @return string
	 */
	private function threshold_field( $row ) {
		$val = isset( $row['threshold'] ) ? $row['threshold'] : '100';
		return '<p class="wa-cfg-extra"><label><strong>' .
			esc_html__( 'Low balance threshold', 'aaraa-white-label-admin' ) . '</strong> ' .
			'<input type="number" min="0" step="0.01" class="small-text" name="wallet[low][threshold]" value="' . esc_attr( $val ) . '" /></label>' .
			' <span class="wa-hint">' . esc_html__( 'Send once when the balance drops to or below this amount.', 'aaraa-white-label-admin' ) . '</span></p>';
	}

	/**
	 * Render a per-status group of template-mapping cards (order / subscription).
	 *
	 * @param string               $title    Section title.
	 * @param string               $group    order|subscription.
	 * @param array<string,string> $statuses status key => label.
	 * @param array<string,mixed>  $saved    Saved rows for this group.
	 * @return void
	 */
	private function render_status_group( $title, $group, $statuses, $saved ) {
		?>
		<h2 class="title"><?php echo esc_html( $title ); ?></h2>
		<div class="wa-cfg-list" data-group="<?php echo esc_attr( $group ); ?>">
		<?php foreach ( $statuses as $key => $label ) : ?>
			<?php
			$key = str_replace( 'wc-', '', $key );
			$row = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
			$this->render_config_card( $group, $key, $group . '[' . $key . ']', $label, $row, '', $key );
			?>
		<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render one template-mapping card.
	 *
	 * Each card carries plain baseline inputs (template / language / body params /
	 * buttons) that submit the form and work with no JavaScript. When the template
	 * definitions load, the Template Config script hides those inputs and builds
	 * the searchable picker, variable mapping, button mapping and preview on top,
	 * keeping the same fields in sync so the save path is unchanged.
	 *
	 * @param string              $group       Token group (order|subscription|otp|wallet).
	 * @param string              $status_key  Data-status attribute.
	 * @param string              $name_prefix Form name prefix, e.g. "order[completed]".
	 * @param string              $label       Human label.
	 * @param array<string,mixed> $row         Saved row.
	 * @param string              $extra_html  Extra markup above the fields (e.g. threshold).
	 * @param string              $code        Optional code shown next to the label.
	 * @return void
	 */
	private function render_config_card( $group, $status_key, $name_prefix, $label, $row, $extra_html = '', $code = '' ) {
		$row = array_merge( array( 'enabled' => 0, 'template' => '', 'language' => 'en', 'params' => '', 'buttons' => '' ), is_array( $row ) ? $row : array() );
		?>
		<div class="wa-cfg-card" data-group="<?php echo esc_attr( $group ); ?>" data-status="<?php echo esc_attr( $status_key ); ?>">
			<div class="wa-cfg-head">
				<label class="wa-cfg-enable">
					<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Enable', 'aaraa-white-label-admin' ); ?></span>
				</label>
				<span class="wa-cfg-status"><?php echo esc_html( $label ); ?> <?php if ( '' !== $code ) : ?><code><?php echo esc_html( $code ); ?></code><?php endif; ?></span>
			</div>
			<div class="wa-cfg-body">
				<?php echo $extra_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above. ?>
				<div class="wa-cfg-baseline">
					<p>
						<label><strong><?php esc_html_e( 'Template name', 'aaraa-white-label-admin' ); ?></strong><br />
						<input type="text" class="regular-text wa-f-template" name="<?php echo esc_attr( $name_prefix ); ?>[template]" value="<?php echo esc_attr( $row['template'] ); ?>" placeholder="order_update" /></label>
						&nbsp;
						<label><strong><?php esc_html_e( 'Language', 'aaraa-white-label-admin' ); ?></strong><br />
						<input type="text" class="small-text wa-f-language" name="<?php echo esc_attr( $name_prefix ); ?>[language]" value="<?php echo esc_attr( $row['language'] ); ?>" placeholder="en" /></label>
					</p>
					<p>
						<label><strong><?php esc_html_e( 'Body parameters (comma separated, in order)', 'aaraa-white-label-admin' ); ?></strong><br />
						<input type="text" class="large-text wa-f-params" name="<?php echo esc_attr( $name_prefix ); ?>[params]" value="<?php echo esc_attr( $row['params'] ); ?>" placeholder="{customer_name}, {order_id}, {status}" /></label>
					</p>
				</div>
				<input type="hidden" class="wa-f-buttons" name="<?php echo esc_attr( $name_prefix ); ?>[buttons]" value="<?php echo esc_attr( $row['buttons'] ); ?>" />
				<div class="wa-cfg-rich" hidden></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Logs tab.
	 *
	 * @return void
	 */
	private function render_logs_tab() {
		$log = self::get_log();
		$this->ensure_defs();

		$types = array(
			''              => __( 'All types', 'aaraa-white-label-admin' ),
			'order'         => __( 'Order', 'aaraa-white-label-admin' ),
			'subscription'  => __( 'Subscription', 'aaraa-white-label-admin' ),
			'otp'           => __( 'OTP', 'aaraa-white-label-admin' ),
			'pause'         => __( 'Pause', 'aaraa-white-label-admin' ),
			'wallet_credit' => __( 'Wallet credit', 'aaraa-white-label-admin' ),
			'wallet_debit'  => __( 'Wallet debit', 'aaraa-white-label-admin' ),
			'wallet_low'    => __( 'Wallet low balance', 'aaraa-white-label-admin' ),
			'admin'         => __( 'Admin', 'aaraa-white-label-admin' ),
			'manual'        => __( 'Manual', 'aaraa-white-label-admin' ),
		);

		// Initial view: newest page, unfiltered. Type, mobile search and paging
		// are all driven by AJAX from here on.
		$initial   = $this->filter_log_rows( '', '' );
		$total     = count( $initial );
		$pages     = max( 1, (int) ceil( $total / self::LOG_PER_PAGE ) );
		$page_rows = array_slice( $initial, 0, self::LOG_PER_PAGE );
		?>
		<p class="description"><?php esc_html_e( 'Every WhatsApp send attempt with the Graph API response.', 'aaraa-white-label-admin' ); ?></p>

		<div class="aaraa-log-toolbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
			<select id="wa-log-type">
				<?php foreach ( $types as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" id="wa-log-search" placeholder="<?php esc_attr_e( 'Search mobile number…', 'aaraa-white-label-admin' ); ?>" style="min-width:220px;" />
			<button type="button" class="button" id="wa-log-search-btn"><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></button>
			<span class="spinner wa-log-spinner" style="float:none;margin:0;"></span>
			<span id="wa-log-count" class="aaraa-tpl-hint"><?php echo esc_html( $this->log_count_label( 1, $total ) ); ?></span>
			<?php if ( ! empty( $log ) ) : ?>
				<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'logs' ), admin_url( 'admin.php' ) ) ); ?>" style="margin-left:auto;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the entire WhatsApp log?', 'aaraa-white-label-admin' ) ); ?>');">
					<?php wp_nonce_field( self::LOG_CLEAR ); ?>
					<input type="hidden" name="<?php echo esc_attr( self::LOG_CLEAR ); ?>" value="1" />
					<?php submit_button( __( 'Clear Log', 'aaraa-white-label-admin' ), 'delete', '', false ); ?>
				</form>
			<?php endif; ?>
		</div>

		<table class="aaraa-tpl-table aaraa-log-table aaraa-log-full">
			<thead>
				<tr>
					<th style="width:150px;"><?php esc_html_e( 'Time', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Type', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Reference', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Number', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:170px;"><?php esc_html_e( 'Template / params', 'aaraa-white-label-admin' ); ?></th>
					<th><?php esc_html_e( 'Message', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Result', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:260px;"><?php esc_html_e( 'API response', 'aaraa-white-label-admin' ); ?></th>
				</tr>
			</thead>
			<tbody id="wa-log-body">
			<?php if ( empty( $page_rows ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No WhatsApp activity logged yet.', 'aaraa-white-label-admin' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $page_rows as $r ) { echo $this->log_row_html( $r ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ } ?>
			<?php endif; ?>
			</tbody>
		</table>

		<div id="wa-log-nav" class="aaraa-log-nav" style="margin-top:10px;"><?php echo $this->log_nav_html( 1, $pages, $total ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></div>

		<script>
		jQuery( function ( $ ) {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( wp_create_nonce( self::LOG_QUERY_NONCE ) ); ?>;
			var paged   = 1;

			function load( p ) {
				paged = p || 1;
				var $spin = $( '.wa-log-spinner' ).addClass( 'is-active' );
				$.post( ajaxUrl, {
					action:   'aaraa_whatsapp_log_query',
					nonce:    nonce,
					log_type: $( '#wa-log-type' ).val(),
					search:   $( '#wa-log-search' ).val(),
					paged:    paged
				}, function ( resp ) {
					$spin.removeClass( 'is-active' );
					if ( resp && resp.success ) {
						$( '#wa-log-body' ).html( resp.data.body );
						$( '#wa-log-nav' ).html( resp.data.nav );
						$( '#wa-log-count' ).text( resp.data.count_label );
						paged = resp.data.paged;
					}
				} ).fail( function () {
					$spin.removeClass( 'is-active' );
				} );
			}

			$( '#wa-log-type' ).on( 'change', function () { load( 1 ); } );
			$( '#wa-log-search-btn' ).on( 'click', function () { load( 1 ); } );
			$( '#wa-log-search' ).on( 'keydown', function ( e ) { if ( 13 === e.which ) { e.preventDefault(); load( 1 ); } } );
			$( document ).on( 'click', '#wa-log-nav .aaraa-log-page', function ( e ) {
				e.preventDefault();
				var p = parseInt( $( this ).data( 'page' ), 10 );
				if ( p ) { load( p ); }
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Filter the log by type and by mobile-number substring (digits only).
	 *
	 * @param string $type   Type key ('' = all).
	 * @param string $search Mobile search term.
	 * @return array<int,array<string,mixed>> Matching rows, newest first.
	 */
	private function filter_log_rows( $type, $search ) {
		$log    = self::get_log();
		$type   = sanitize_key( (string) $type );
		$digits = preg_replace( '/\D+/', '', (string) $search );

		$out = array();
		foreach ( $log as $r ) {
			if ( '' !== $type && ( ! isset( $r['type'] ) || $r['type'] !== $type ) ) {
				continue;
			}
			if ( '' !== $digits ) {
				$mobile = preg_replace( '/\D+/', '', (string) ( $r['mobile'] ?? '' ) );
				if ( '' === $mobile || false === strpos( $mobile, $digits ) ) {
					continue;
				}
			}
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Work out the human-readable message to show for a log row.
	 *
	 * Prefers the body rendered at send time; otherwise renders it now from the
	 * cached template definitions (so older rows and rows logged before the
	 * templates were cached still show the real text with values filled in);
	 * finally falls back to the template/params summary.
	 *
	 * @param array<string,mixed> $r Log entry.
	 * @return string
	 */
	private function resolve_display_message( $r ) {
		if ( ! empty( $r['body'] ) ) {
			return (string) $r['body'];
		}

		$template = isset( $r['template'] ) ? (string) $r['template'] : '';
		$message  = isset( $r['message'] ) ? (string) $r['message'] : '';
		if ( '' === $template ) {
			return $message;
		}

		$language = isset( $r['language'] ) ? (string) $r['language'] : '';
		$params   = ( isset( $r['params'] ) && is_array( $r['params'] ) ) ? $r['params'] : array();

		// Older rows didn't store language/params separately — recover them from
		// the "tpl [lang] a | b | c" summary.
		if ( '' === $language || empty( $params ) ) {
			if ( preg_match( '/\[([^\]]*)\]\s*(.*)$/s', $message, $mm ) ) {
				if ( '' === $language ) {
					$language = trim( $mm[1] );
				}
				if ( empty( $params ) && '' !== trim( $mm[2] ) ) {
					$params = array_map( 'trim', explode( '|', $mm[2] ) );
				}
			}
		}

		$rendered = self::rendered_body( $template, $language, $params );
		$out      = '' !== $rendered ? $rendered : $message;

		// Normalise line endings and collapse runs of blank lines so the cell
		// doesn't get needlessly tall.
		$out = str_replace( array( "\r\n", "\r" ), "\n", (string) $out );
		$out = preg_replace( "/\n{3,}/", "\n\n", $out );
		return trim( $out );
	}

	/**
	 * Build one WhatsApp log table row.
	 *
	 * @param array<string,mixed> $r Log entry.
	 * @return string HTML.
	 */
	private function log_row_html( $r ) {
		$is_sent = 'sent' === ( isset( $r['result'] ) ? $r['result'] : '' );
		$status  = isset( $r['status'] ) ? $r['status'] : '';
		$message = $this->resolve_display_message( $r );

		ob_start();
		?>
		<tr>
			<td><?php echo esc_html( $r['time'] ?? '' ); ?></td>
			<td><code><?php echo esc_html( $r['type'] ?? '' ); ?></code></td>
			<td>
				<?php echo ! empty( $r['ref'] ) ? esc_html( $r['ref'] ) : '&mdash;'; ?>
				<?php if ( $status ) : ?><br /><span class="aaraa-tpl-hint"><?php echo esc_html( $status ); ?></span><?php endif; ?>
			</td>
			<td><?php echo esc_html( $r['mobile'] ?? '' ); ?></td>
			<td>
				<?php echo ! empty( $r['template'] ) ? '<code>' . esc_html( $r['template'] ) . '</code>' : ''; ?>
				<?php if ( ! empty( $r['message'] ) ) : ?><br /><span class="aaraa-tpl-hint"><?php echo esc_html( $r['message'] ); ?></span><?php endif; ?>
			</td>
			<td class="aaraa-log-message"><?php echo '' !== $message ? esc_html( $message ) : '&mdash;'; ?></td>
			<td>
				<span class="aaraa-log-badge <?php echo $is_sent ? 'is-sent' : 'is-failed'; ?>"><?php echo esc_html( $is_sent ? __( 'Sent', 'aaraa-white-label-admin' ) : __( 'Failed', 'aaraa-white-label-admin' ) ); ?></span>
				<?php if ( isset( $r['http_code'] ) && '' !== $r['http_code'] ) : ?><br /><span class="aaraa-tpl-hint">HTTP <?php echo esc_html( $r['http_code'] ); ?></span><?php endif; ?>
			</td>
			<td><?php echo esc_html( $r['response'] ?? '' ); ?></td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Build the pagination nav (Prev / page x of y / Next).
	 *
	 * @param int $paged Current page.
	 * @param int $pages Total pages.
	 * @param int $total Total matching rows.
	 * @return string HTML.
	 */
	private function log_nav_html( $paged, $pages, $total ) {
		if ( $total <= self::LOG_PER_PAGE ) {
			return '';
		}
		$paged = max( 1, min( (int) $paged, (int) $pages ) );

		$prev = $paged > 1
			? '<a href="#" class="button aaraa-log-page" data-page="' . ( $paged - 1 ) . '">&laquo; ' . esc_html__( 'Prev', 'aaraa-white-label-admin' ) . '</a>'
			: '<span class="button disabled">&laquo; ' . esc_html__( 'Prev', 'aaraa-white-label-admin' ) . '</span>';

		$next = $paged < $pages
			? '<a href="#" class="button aaraa-log-page" data-page="' . ( $paged + 1 ) . '">' . esc_html__( 'Next', 'aaraa-white-label-admin' ) . ' &raquo;</a>'
			: '<span class="button disabled">' . esc_html__( 'Next', 'aaraa-white-label-admin' ) . ' &raquo;</span>';

		$mid = sprintf(
			/* translators: 1: current page, 2: total pages */
			esc_html__( 'Page %1$d of %2$d', 'aaraa-white-label-admin' ),
			$paged,
			$pages
		);

		return '<span style="display:inline-flex;gap:8px;align-items:center;">' . $prev . ' <span class="aaraa-tpl-hint">' . $mid . '</span> ' . $next . '</span>';
	}

	/**
	 * Count label for the toolbar, e.g. "Showing 1–50 of 214".
	 *
	 * @param int $paged Current page.
	 * @param int $total Total matching rows.
	 * @return string
	 */
	private function log_count_label( $paged, $total ) {
		if ( 0 === $total ) {
			return __( 'No matching entries', 'aaraa-white-label-admin' );
		}
		$from = ( ( $paged - 1 ) * self::LOG_PER_PAGE ) + 1;
		$to   = min( $paged * self::LOG_PER_PAGE, $total );
		return sprintf(
			/* translators: 1: from, 2: to, 3: total */
			__( 'Showing %1$d–%2$d of %3$d', 'aaraa-white-label-admin' ),
			$from,
			$to,
			$total
		);
	}

	/**
	 * AJAX: return a filtered, paginated slice of the WhatsApp log.
	 *
	 * @return void
	 */
	public function ajax_log_query() {
		check_ajax_referer( self::LOG_QUERY_NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'aaraa-white-label-admin' ) ) );
		}
		$this->ensure_defs();

		$type   = isset( $_POST['log_type'] ) ? sanitize_key( wp_unslash( $_POST['log_type'] ) ) : '';
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$paged  = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

		$rows  = $this->filter_log_rows( $type, $search );
		$total = count( $rows );
		$pages = max( 1, (int) ceil( $total / self::LOG_PER_PAGE ) );
		$paged = min( $paged, $pages );

		$slice = array_slice( $rows, ( $paged - 1 ) * self::LOG_PER_PAGE, self::LOG_PER_PAGE );

		$body = '';
		if ( empty( $slice ) ) {
			$body = '<tr><td colspan="8">' . esc_html__( 'No matching WhatsApp activity.', 'aaraa-white-label-admin' ) . '</td></tr>';
		} else {
			foreach ( $slice as $r ) {
				$body .= $this->log_row_html( $r );
			}
		}

		wp_send_json_success(
			array(
				'body'        => $body,
				'nav'         => $this->log_nav_html( $paged, $pages, $total ),
				'count_label' => $this->log_count_label( $paged, $total ),
				'paged'       => $paged,
				'pages'       => $pages,
				'total'       => $total,
			)
		);
	}
}
