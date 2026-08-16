<?php
/**
 * AaraaNotifications: admin-managed notification channels.
 *
 * Sidebar section (AaraaNotifications):
 *   - SMS       — fully configurable (this file): gateway credentials, OTP
 *                 template, and per-status Order / Subscription templates each
 *                 with an Enable / Disable toggle. When an order or subscription
 *                 status changes, an SMS is sent only if that status template is
 *                 enabled.
 *   - WhatsApp  — placeholder page (channel to be configured later).
 *   - E-Mail    — placeholder page.
 *   - In-app    — placeholder page.
 *
 * SMS is delivered through the mysmarthost.in gateway, matching the parameter
 * shape already used elsewhere on the site (user/apikey/mobile/message/senderid/
 * type/tid). Credentials and templates live in the `aaraa_notify_sms` option so
 * they are editable from the admin without touching code.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Notifications controller (SMS + placeholder channels).
 */
class Notifications_Admin {

	const PARENT       = 'aaraa-notifications';
	const PAGE_SMS     = 'aaraa-notify-sms';
	const PAGE_WHATSAPP = 'aaraa-notify-whatsapp';
	const PAGE_EMAIL   = 'aaraa-notify-email';
	const PAGE_INAPP   = 'aaraa-notify-inapp';

	const OPTION = 'aaraa_notify_sms';
	const ACTION = 'aaraa_notify_save';
	const NONCE  = 'aaraa_notify';

	// SMS activity log. Historically kept in an option (capped at 300, so older
	// entries were lost); now stored in a dedicated table so full history is
	// retained. LOG_OPTION is read once to migrate the old entries across.
	const LOG_OPTION   = 'aaraa_notify_sms_log';
	const LOG_LIMIT    = 300;
	const LOG_CLEAR    = 'aaraa_notify_log_clear';
	const LOG_PER_PAGE = 50;
	const LOG_QUERY_NONCE = 'aaraa_log_query';

	// Log table + schema version + how many rows to retain.
	const LOG_DB_OPTION = 'aaraa_sms_log_db_version';
	const LOG_DB_VER    = '1';
	const LOG_MAX_ROWS  = 100000;

	// Wallet balance meta key (WP Swings Wallet System for WooCommerce).
	const WALLET_META = 'wps_wallet';

	/**
	 * Old wallet balance captured pre-update, keyed by user id, so the post-update
	 * hook can compute the credit/debit delta.
	 *
	 * @var array<int,float>
	 */
	private $wallet_old = array();

	const SMTP_OPTION = 'aaraa_smtp';
	const EMAIL_ACTION = 'aaraa_email_save';
	const EMAIL_NONCE  = 'aaraa_email';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_init', array( $this, 'handle_email_save' ) );
		add_action( 'admin_init', array( $this, 'handle_log_clear' ) );
		add_action( 'wp_ajax_aaraa_sms_log_query', array( $this, 'ajax_log_query' ) );

		// Status-change triggers.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 20, 4 );
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'on_subscription_status_changed' ), 20, 3 );

		// Wallet balance triggers. Watch the `wps_wallet` user meta directly so we
		// catch every credit/debit path (manual, gateway, purchase, refund,
		// referral) — the wallet plugin fires no action of its own.
		add_action( 'update_user_meta', array( $this, 'wallet_capture_old' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'wallet_after_update' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'wallet_after_add' ), 10, 4 );

		// Apply the configured SMTP account to every outgoing email.
		add_action( 'phpmailer_init', array( $this, 'apply_smtp' ) );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );

		// Login OTP SMS is sent by the mobile-login plugin through its own gateway,
		// so it never passes through send_sms(). Record it in the same log via the
		// hook that plugin fires right after each OTP SMS attempt.
		add_action( 'xoo_ml_otp_sms_sent', array( $this, 'log_external_otp' ), 10, 2 );
	}

	/**
	 * Resolve a phone number to a customer id, for the log Reference column.
	 *
	 * Matches on the last 10 digits so a stored `billing_phone` works whether or
	 * not it carries a country code or spacing.
	 *
	 * @param string $phone Phone number in any format.
	 * @return int Customer id, or 0 when none matches.
	 */
	public static function user_id_for_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( strlen( $digits ) < 6 ) {
			return 0;
		}
		$last10 = substr( $digits, -10 );

		global $wpdb;
		foreach ( array( 'billing_phone', 'digits_phone', 'phone' ) as $key ) {
			$uid = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta}
					 WHERE meta_key = %s
					   AND REPLACE( REPLACE( REPLACE( meta_value, '+', '' ), ' ', '' ), '-', '' ) LIKE %s
					 LIMIT 1",
					$key,
					'%' . $wpdb->esc_like( $last10 )
				)
			);
			if ( $uid ) {
				return (int) $uid;
			}
		}
		return 0;
	}

	/**
	 * Log an OTP SMS sent by the mobile-login plugin into the Aaraa SMS log.
	 *
	 * @param array $params The plugin's send params: [country_code, number, message, otp].
	 * @param mixed $result The gateway result — a wp_remote_post() response array,
	 *                       a WP_Error, or false when SMS was not attempted.
	 * @return void
	 */
	public function log_external_otp( $params, $result ) {
		// Nothing attempted (SMS disabled / firebase / whatsapp-only) — skip.
		if ( false === $result ) {
			return;
		}

		$params  = (array) $params;
		$cc      = isset( $params[0] ) ? (string) $params[0] : '';
		$number  = isset( $params[1] ) ? (string) $params[1] : '';
		$message = isset( $params[2] ) ? (string) $params[2] : '';

		$http_code = '';
		$response  = '';
		$ok        = false;

		if ( is_wp_error( $result ) ) {
			$response = $result->get_error_message();
		} elseif ( is_array( $result ) ) {
			$http_code = (string) wp_remote_retrieve_response_code( $result );
			$response  = trim( wp_strip_all_tags( (string) wp_remote_retrieve_body( $result ) ) );
			$ok        = ( (int) $http_code >= 200 && (int) $http_code < 300 );
		} else {
			// Some gateways return a truthy scalar on success.
			$ok       = ! empty( $result );
			$response = is_scalar( $result ) ? (string) $result : '';
		}

		$uid = self::user_id_for_phone( $cc . $number );

		self::log(
			array(
				'type'      => 'otp',
				'ref'       => $uid ? (string) $uid : '',
				'mobile'    => $cc . $number,
				'message'   => $message,
				'result'    => $ok ? 'sent' : 'failed',
				'http_code' => $http_code,
				'response'  => $response,
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * SMTP.
	 * --------------------------------------------------------------------- */

	/**
	 * SMTP settings, merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function smtp_settings() {
		$defaults = array(
			'enabled'    => 0,
			'host'       => '',
			'port'       => '587',
			'encryption' => 'tls', // none | ssl | tls.
			'auth'       => 1,
			'username'   => '',
			'password'   => '',
			'from_email' => '',
			'from_name'  => '',
		);
		$saved = get_option( self::SMTP_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * Route wp_mail() through the configured SMTP server.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function apply_smtp( $phpmailer ) {
		$s = self::smtp_settings();
		if ( empty( $s['enabled'] ) || empty( $s['host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host = $s['host'];
		$phpmailer->Port = (int) $s['port'];

		if ( ! empty( $s['auth'] ) ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $s['username'];
			$phpmailer->Password = $s['password'];
		} else {
			$phpmailer->SMTPAuth = false;
		}

		if ( 'none' === $s['encryption'] ) {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		} else {
			$phpmailer->SMTPSecure = $s['encryption']; // 'ssl' | 'tls'.
		}

		if ( ! empty( $s['from_email'] ) && is_email( $s['from_email'] ) ) {
			$phpmailer->From = $s['from_email'];
		}
		if ( ! empty( $s['from_name'] ) ) {
			$phpmailer->FromName = $s['from_name'];
		}
	}

	/**
	 * Override the From address when SMTP is configured with one.
	 *
	 * @param string $from Current from address.
	 * @return string
	 */
	public function mail_from( $from ) {
		$s = self::smtp_settings();
		if ( ! empty( $s['enabled'] ) && ! empty( $s['from_email'] ) && is_email( $s['from_email'] ) ) {
			return $s['from_email'];
		}
		return $from;
	}

	/**
	 * Override the From name when SMTP is configured with one.
	 *
	 * @param string $name Current from name.
	 * @return string
	 */
	public function mail_from_name( $name ) {
		$s = self::smtp_settings();
		if ( ! empty( $s['enabled'] ) && ! empty( $s['from_name'] ) ) {
			return $s['from_name'];
		}
		return $name;
	}

	/* --------------------------------------------------------------------- *
	 * Settings.
	 * --------------------------------------------------------------------- */

	/**
	 * SMS settings, merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function settings() {
		$defaults = array(
			'api_url'  => 'http://sms.mysmarthost.in/api/sendsms.php',
			'user'     => 'madrasmilk',
			'apikey'   => 'atWhzca1M62UI1Ze9om9',
			'senderid' => 'MAMILK',
			'type'     => 'txt',
			'otp'      => array(
				'enabled'     => 1,
				'message'     => 'Your OTP for logging in to the Madras Milk application is {otp}.',
				'template_id' => '1207160701072793529',
			),
			'order'        => array(),
			'subscription' => array(),
			'wallet'       => array(
				'credit' => array(
					'enabled'     => 0,
					'message'     => 'Dear {first_name}, {currency}{amount} has been credited to your Madras Milk wallet. Balance: {currency}{balance}.',
					'template_id' => '',
				),
				'debit'  => array(
					'enabled'     => 0,
					'message'     => 'Dear {first_name}, {currency}{amount} has been debited from your Madras Milk wallet. Balance: {currency}{balance}.',
					'template_id' => '',
				),
				'low'    => array(
					'enabled'     => 0,
					'threshold'   => '100',
					'message'     => 'Dear {first_name}, your Madras Milk wallet balance is low: {currency}{balance}. Please recharge to avoid delivery interruption.',
					'template_id' => '',
				),
			),
		);

		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$s          = array_merge( $defaults, $saved );
		$s['otp']   = array_merge( $defaults['otp'], ( isset( $saved['otp'] ) && is_array( $saved['otp'] ) ) ? $saved['otp'] : array() );
		$s['order']        = ( isset( $saved['order'] ) && is_array( $saved['order'] ) ) ? $saved['order'] : array();
		$s['subscription'] = ( isset( $saved['subscription'] ) && is_array( $saved['subscription'] ) ) ? $saved['subscription'] : array();

		// Wallet templates, each key merged over defaults so partial saves keep shape.
		$saved_wallet   = ( isset( $saved['wallet'] ) && is_array( $saved['wallet'] ) ) ? $saved['wallet'] : array();
		$s['wallet']    = array();
		foreach ( $defaults['wallet'] as $key => $row ) {
			$s['wallet'][ $key ] = array_merge( $row, ( isset( $saved_wallet[ $key ] ) && is_array( $saved_wallet[ $key ] ) ) ? $saved_wallet[ $key ] : array() );
		}

		return $s;
	}

	/**
	 * The template configured for a status in a group ('order'|'subscription').
	 *
	 * @param string $group  order|subscription.
	 * @param string $status Status key (with or without the wc- prefix).
	 * @return array{enabled:int,message:string,template_id:string}|null
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
	 * Send an SMS through the configured gateway.
	 *
	 * @param string $mobile      Recipient mobile number.
	 * @param string $message     Message body (already rendered).
	 * @param string $template_id DLT template id (tid), optional.
	 * @param array  $context     Log context: type (otp|order|subscription|
	 *                            manual|test), ref, status.
	 * @return bool True when the gateway request succeeded (HTTP 2xx).
	 */
	public static function send_sms( $mobile, $message, $template_id = '', $context = array() ) {
		$s      = self::settings();
		$mobile = preg_replace( '/\D+/', '', (string) $mobile );

		if ( empty( $s['api_url'] ) || '' === $mobile || '' === trim( (string) $message ) ) {
			self::log(
				array(
					'type'     => isset( $context['type'] ) ? $context['type'] : 'manual',
					'ref'      => isset( $context['ref'] ) ? $context['ref'] : '',
					'status'   => isset( $context['status'] ) ? $context['status'] : '',
					'mobile'   => $mobile,
					'message'  => $message,
					'result'   => 'failed',
					'response' => '' === $mobile ? 'No mobile number' : ( empty( $s['api_url'] ) ? 'Gateway API URL not configured' : 'Empty message' ),
				)
			);
			return false;
		}

		$body = array(
			'user'     => $s['user'],
			'apikey'   => $s['apikey'],
			'senderid' => $s['senderid'],
			'mobile'   => $mobile,
			'message'  => $message,
			'type'     => ! empty( $s['type'] ) ? $s['type'] : 'txt',
		);
		if ( '' !== (string) $template_id ) {
			$body['tid'] = $template_id;
		}

		$response = wp_remote_post(
			$s['api_url'],
			array(
				'timeout' => 15,
				'body'    => $body,
			)
		);

		$entry = array(
			'type'     => isset( $context['type'] ) ? $context['type'] : 'manual',
			'ref'      => isset( $context['ref'] ) ? $context['ref'] : '',
			'status'   => isset( $context['status'] ) ? $context['status'] : '',
			'mobile'   => $mobile,
			'message'  => $message,
		);

		if ( is_wp_error( $response ) ) {
			$entry['result']   = 'failed';
			$entry['response'] = $response->get_error_message();
			self::log( $entry );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$ok   = $code >= 200 && $code < 300;

		$entry['result']    = $ok ? 'sent' : 'failed';
		$entry['http_code'] = $code;
		// The gateway returns its real verdict (message id / error) in the body,
		// even on HTTP 200 — record it so failures are diagnosable.
		$entry['response']  = trim( wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ) ) );
		self::log( $entry );

		return $ok;
	}

	/* --------------------------------------------------------------------- *
	 * Activity log.
	 * --------------------------------------------------------------------- */

	/**
	 * The SMS log table name.
	 *
	 * @return string
	 */
	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaraa_sms_log';
	}

	/**
	 * Create the log table on first use and migrate the legacy option-based log
	 * across (one time). Cheap no-op once the schema version is current.
	 *
	 * @return void
	 */
	public static function ensure_log_table() {
		if ( self::LOG_DB_VER === get_option( self::LOG_DB_OPTION ) ) {
			return;
		}

		global $wpdb;
		$table   = self::log_table();
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				sent_at DATETIME NOT NULL,
				type VARCHAR(32) NOT NULL DEFAULT 'manual',
				ref VARCHAR(64) NOT NULL DEFAULT '',
				status VARCHAR(32) NOT NULL DEFAULT '',
				mobile VARCHAR(32) NOT NULL DEFAULT '',
				message TEXT NULL,
				result VARCHAR(16) NOT NULL DEFAULT 'failed',
				http_code VARCHAR(8) NOT NULL DEFAULT '',
				response TEXT NULL,
				PRIMARY KEY (id),
				KEY type (type),
				KEY mobile (mobile),
				KEY sent_at (sent_at)
			) {$charset};"
		);

		// One-time import of the old option log (stored newest-first). Insert
		// oldest-first so row ids ascend with time.
		$old = get_option( self::LOG_OPTION, array() );
		if ( is_array( $old ) && ! empty( $old ) ) {
			foreach ( array_reverse( $old ) as $e ) {
				if ( ! is_array( $e ) ) {
					continue;
				}
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array(
						'sent_at'   => isset( $e['time'] ) ? $e['time'] : current_time( 'mysql' ),
						'type'      => isset( $e['type'] ) ? substr( (string) $e['type'], 0, 32 ) : 'manual',
						'ref'       => isset( $e['ref'] ) ? substr( (string) $e['ref'], 0, 64 ) : '',
						'status'    => isset( $e['status'] ) ? substr( (string) $e['status'], 0, 32 ) : '',
						'mobile'    => isset( $e['mobile'] ) ? substr( (string) $e['mobile'], 0, 32 ) : '',
						'message'   => isset( $e['message'] ) ? (string) $e['message'] : '',
						'result'    => isset( $e['result'] ) ? substr( (string) $e['result'], 0, 16 ) : 'failed',
						'http_code' => isset( $e['http_code'] ) ? substr( (string) $e['http_code'], 0, 8 ) : '',
						'response'  => isset( $e['response'] ) ? (string) $e['response'] : '',
					)
				);
			}
		}

		update_option( self::LOG_DB_OPTION, self::LOG_DB_VER, false );
	}

	/**
	 * Append an entry to the SMS log table.
	 *
	 * @param array<string,mixed> $entry Partial entry; time is added here.
	 * @return void
	 */
	public static function log( $entry ) {
		self::ensure_log_table();
		global $wpdb;

		$entry = array_merge(
			array(
				'time'      => current_time( 'mysql' ),
				'type'      => 'manual',
				'ref'       => '',
				'status'    => '',
				'mobile'    => '',
				'message'   => '',
				'result'    => 'failed',
				'http_code' => '',
				'response'  => '',
			),
			$entry
		);

		// Keep response text bounded.
		if ( strlen( (string) $entry['response'] ) > 500 ) {
			$entry['response'] = substr( (string) $entry['response'], 0, 500 ) . '…';
		}

		$table = self::log_table();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'sent_at'   => (string) $entry['time'],
				'type'      => substr( (string) $entry['type'], 0, 32 ),
				'ref'       => substr( (string) $entry['ref'], 0, 64 ),
				'status'    => substr( (string) $entry['status'], 0, 32 ),
				'mobile'    => substr( (string) $entry['mobile'], 0, 32 ),
				'message'   => (string) $entry['message'],
				'result'    => substr( (string) $entry['result'], 0, 16 ),
				'http_code' => substr( (string) $entry['http_code'], 0, 8 ),
				'response'  => (string) $entry['response'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Occasionally trim to the retention cap so the table can't grow forever.
		if ( 1 === wp_rand( 1, 200 ) ) {
			$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" ); // phpcs:ignore WordPress.DB
			$cutoff = $max_id - self::LOG_MAX_ROWS;
			if ( $cutoff > 0 ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id <= %d", $cutoff ) ); // phpcs:ignore WordPress.DB
			}
		}
	}

	/**
	 * Query the log table, filtered by type and mobile-number substring.
	 *
	 * @param string $type     Type key ('' = all).
	 * @param string $search   Mobile search term (digits matched anywhere).
	 * @param int    $paged    Page number (1-based).
	 * @param int    $per_page Rows per page.
	 * @return array{rows:array<int,array<string,mixed>>,total:int,pages:int,paged:int}
	 */
	public static function query_log( $type = '', $search = '', $paged = 1, $per_page = self::LOG_PER_PAGE ) {
		self::ensure_log_table();
		global $wpdb;
		$table = self::log_table();

		$where = array( '1=1' );
		$args  = array();

		$type = sanitize_key( (string) $type );
		if ( '' !== $type ) {
			$where[] = 'type = %s';
			$args[]  = $type;
		}

		$digits = preg_replace( '/\D+/', '', (string) $search );
		if ( '' !== $digits ) {
			$where[] = "REPLACE( REPLACE( REPLACE( mobile, '+', '' ), ' ', '' ), '-', '' ) LIKE %s";
			$args[]  = '%' . $wpdb->esc_like( $digits ) . '%';
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $args
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) // phpcs:ignore WordPress.DB
			: $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB

		$per_page = max( 1, (int) $per_page );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = min( max( 1, (int) $paged ), $pages );
		$offset   = ( $paged - 1 ) * $per_page;

		$rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$raw      = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $args, array( $per_page, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB

		$rows = array();
		foreach ( (array) $raw as $r ) {
			$rows[] = array(
				'time'      => $r['sent_at'],
				'type'      => $r['type'],
				'ref'       => $r['ref'],
				'status'    => $r['status'],
				'mobile'    => $r['mobile'],
				'message'   => $r['message'],
				'result'    => $r['result'],
				'http_code' => $r['http_code'],
				'response'  => $r['response'],
			);
		}

		return array(
			'rows'  => $rows,
			'total' => $total,
			'pages' => $pages,
			'paged' => $paged,
		);
	}

	/**
	 * Recent SMS log rows, newest first. Bounded for callers that scan in PHP
	 * (e.g. the per-customer timeline); the SMS Log screen uses query_log().
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_log() {
		$result = self::query_log( '', '', 1, 2000 );
		return $result['rows'];
	}

	/**
	 * Send the configured OTP SMS. Call this from the login/OTP flow.
	 *
	 * @param string     $mobile Recipient mobile number.
	 * @param string|int $otp    One-time code.
	 * @return bool
	 */
	public static function send_otp( $mobile, $otp ) {
		$s   = self::settings();
		$otp_cfg = $s['otp'];
		if ( empty( $otp_cfg['enabled'] ) ) {
			return false;
		}
		$message = self::render( $otp_cfg['message'], array( '{otp}' => $otp ) );
		return self::send_sms(
			$mobile,
			$message,
			isset( $otp_cfg['template_id'] ) ? $otp_cfg['template_id'] : '',
			array( 'type' => 'otp' )
		);
	}

	/**
	 * Replace {tokens} in a template message.
	 *
	 * @param string               $message Template.
	 * @param array<string,string> $tokens  {token} => value.
	 * @return string
	 */
	private static function render( $message, $tokens ) {
		return strtr( (string) $message, array_map( 'strval', $tokens ) );
	}

	/* --------------------------------------------------------------------- *
	 * Triggers.
	 * --------------------------------------------------------------------- */

	/**
	 * Send the order-status SMS when that status is enabled.
	 *
	 * @param int       $order_id Order id.
	 * @param string    $from     Old status.
	 * @param string    $to       New status.
	 * @param \WC_Order $order    Order.
	 * @return void
	 */
	public function on_order_status_changed( $order_id, $from, $to, $order ) {
		$tpl = self::status_template( 'order', $to );
		if ( ! $tpl || empty( $tpl['enabled'] ) ) {
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
					'result'   => 'failed',
					'response' => 'No billing phone number on the order',
				)
			);
			return;
		}

		$message = self::render(
			$tpl['message'],
			array(
				'{order_id}'      => $order->get_order_number(),
				'{status}'        => wc_get_order_status_name( $to ),
				'{customer_name}' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'{first_name}'    => $order->get_billing_first_name(),
				'{total}'         => $order->get_total(),
				'{currency}'      => $order->get_currency(),
				'{site}'          => get_bloginfo( 'name' ),
			)
		);

		$sent = self::send_sms(
			$phone,
			$message,
			isset( $tpl['template_id'] ) ? $tpl['template_id'] : '',
			array( 'type' => 'order', 'ref' => $order->get_order_number(), 'status' => $to )
		);
		if ( $sent ) {
			$order->add_order_note( sprintf( 'SMS sent for order status "%s".', $to ) );
		}
	}

	/**
	 * Send the subscription-status SMS when that status is enabled.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param string           $to           New status.
	 * @param string           $from         Old status.
	 * @return void
	 */
	public function on_subscription_status_changed( $subscription, $to, $from ) {
		$tpl = self::status_template( 'subscription', $to );
		if ( ! $tpl || empty( $tpl['enabled'] ) ) {
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
					'result'   => 'failed',
					'response' => 'No billing phone number on the subscription',
				)
			);
			return;
		}

		$message = self::render(
			$tpl['message'],
			array(
				'{subscription_id}' => $subscription->get_id(),
				'{status}'          => $to,
				'{customer_name}'   => trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ),
				'{first_name}'      => $subscription->get_billing_first_name(),
				'{total}'           => $subscription->get_total(),
				'{currency}'        => $subscription->get_currency(),
				'{site}'            => get_bloginfo( 'name' ),
			)
		);

		$sent = self::send_sms(
			$phone,
			$message,
			isset( $tpl['template_id'] ) ? $tpl['template_id'] : '',
			array( 'type' => 'subscription', 'ref' => $subscription->get_id(), 'status' => $to )
		);
		if ( $sent ) {
			$subscription->add_order_note( sprintf( 'SMS sent for subscription status "%s".', $to ) );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Wallet triggers.
	 * --------------------------------------------------------------------- */

	/**
	 * Capture the wallet balance before it is updated, so the after-hook can
	 * work out whether it was a credit or a debit and by how much.
	 *
	 * @param int    $meta_id    Meta row id.
	 * @param int    $object_id  User id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value (unused here).
	 * @return void
	 */
	public function wallet_capture_old( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::WALLET_META !== $meta_key ) {
			return;
		}
		// Cache still holds the old value at this point (DB not yet written).
		$this->wallet_old[ (int) $object_id ] = (float) get_user_meta( $object_id, self::WALLET_META, true );
	}

	/**
	 * Fire wallet SMS after an existing balance is updated.
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
	 * Fire wallet SMS the first time a balance is set (old balance = 0).
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
	 * Decide which wallet SMS (credit / debit / low balance) to send for a
	 * balance change and send it.
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
		$delta = round( $new - $old, 2 );

		$s      = self::settings();
		$wallet = isset( $s['wallet'] ) ? $s['wallet'] : array();

		if ( $delta > 0 && ! empty( $wallet['credit'] ) ) {
			$this->send_wallet_sms( $user_id, 'wallet_credit', $wallet['credit'], $delta, $new );
		} elseif ( $delta < 0 && ! empty( $wallet['debit'] ) ) {
			$this->send_wallet_sms( $user_id, 'wallet_debit', $wallet['debit'], abs( $delta ), $new );
		}

		// Low balance: fire once, only when the balance crosses down to/below the
		// threshold (was above before this change), so it is not repeated on every
		// further debit while already low.
		if ( ! empty( $wallet['low'] ) && ! empty( $wallet['low']['enabled'] ) ) {
			$threshold = isset( $wallet['low']['threshold'] ) ? (float) $wallet['low']['threshold'] : 0.0;
			if ( $threshold > 0 && $new <= $threshold && $old > $threshold ) {
				$this->send_wallet_sms( $user_id, 'wallet_low', $wallet['low'], abs( $delta ), $new, $threshold );
			}
		}
	}

	/**
	 * Render and send one wallet SMS to a customer.
	 *
	 * @param int   $user_id   User id.
	 * @param string $ctx_type Log context type.
	 * @param array $cfg       Template row (enabled/message/template_id).
	 * @param float $amount    Transaction amount (absolute).
	 * @param float $balance   New balance.
	 * @param float $threshold Low-balance threshold (for {threshold}).
	 * @return void
	 */
	private function send_wallet_sms( $user_id, $ctx_type, $cfg, $amount, $balance, $threshold = 0.0 ) {
		if ( empty( $cfg['enabled'] ) ) {
			return;
		}
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( ! $phone ) {
			self::log(
				array(
					'type'     => $ctx_type,
					'ref'      => $user_id,
					'result'   => 'failed',
					'response' => 'No billing phone number for this customer',
				)
			);
			return;
		}

		$user     = get_userdata( $user_id );
		$first    = $user ? $user->first_name : '';
		$last     = $user ? $user->last_name : '';
		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';

		$message = self::render(
			$cfg['message'],
			array(
				'{amount}'        => number_format_i18n( (float) $amount, 2 ),
				'{balance}'       => number_format_i18n( (float) $balance, 2 ),
				'{threshold}'     => number_format_i18n( (float) $threshold, 2 ),
				'{currency}'      => $currency,
				'{customer_name}' => trim( $first . ' ' . $last ),
				'{first_name}'    => $first,
				'{site}'          => get_bloginfo( 'name' ),
			)
		);

		self::send_sms(
			$phone,
			$message,
			isset( $cfg['template_id'] ) ? $cfg['template_id'] : '',
			array( 'type' => $ctx_type, 'ref' => $user_id )
		);
	}

	/* --------------------------------------------------------------------- *
	 * Save.
	 * --------------------------------------------------------------------- */

	/**
	 * Persist the SMS settings form.
	 *
	 * @return void
	 */
	public function handle_save() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE_SMS !== $page ) {
			return;
		}
		if ( empty( $_POST[ self::ACTION ] ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage notifications.', 'aaraa-white-label-admin' ) );
		}

		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		$settings = array(
			'api_url'  => esc_url_raw( $in['api_url'] ?? '' ),
			'user'     => sanitize_text_field( $in['user'] ?? '' ),
			'apikey'   => sanitize_text_field( $in['apikey'] ?? '' ),
			'senderid' => sanitize_text_field( $in['senderid'] ?? '' ),
			'type'     => sanitize_text_field( $in['type'] ?? 'txt' ),
			'otp'      => array(
				'enabled'     => empty( $in['otp']['enabled'] ) ? 0 : 1,
				'message'     => sanitize_textarea_field( $in['otp']['message'] ?? '' ),
				'template_id' => sanitize_text_field( $in['otp']['template_id'] ?? '' ),
			),
			'order'        => $this->sanitize_group( $in['order'] ?? array() ),
			'subscription' => $this->sanitize_group( $in['subscription'] ?? array() ),
			'wallet'       => array(
				'credit' => array(
					'enabled'     => empty( $in['wallet']['credit']['enabled'] ) ? 0 : 1,
					'message'     => sanitize_textarea_field( $in['wallet']['credit']['message'] ?? '' ),
					'template_id' => sanitize_text_field( $in['wallet']['credit']['template_id'] ?? '' ),
				),
				'debit'  => array(
					'enabled'     => empty( $in['wallet']['debit']['enabled'] ) ? 0 : 1,
					'message'     => sanitize_textarea_field( $in['wallet']['debit']['message'] ?? '' ),
					'template_id' => sanitize_text_field( $in['wallet']['debit']['template_id'] ?? '' ),
				),
				'low'    => array(
					'enabled'     => empty( $in['wallet']['low']['enabled'] ) ? 0 : 1,
					'threshold'   => (string) max( 0, (float) ( $in['wallet']['low']['threshold'] ?? 0 ) ),
					'message'     => sanitize_textarea_field( $in['wallet']['low']['message'] ?? '' ),
					'template_id' => sanitize_text_field( $in['wallet']['low']['template_id'] ?? '' ),
				),
			),
		);

		update_option( self::OPTION, $settings );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SMS, 'notice' => 'saved' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Clear the SMS activity log.
	 *
	 * @return void
	 */
	public function handle_log_clear() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE_SMS !== $page ) {
			return;
		}
		if ( empty( $_POST[ self::LOG_CLEAR ] ) ) {
			return;
		}
		check_admin_referer( self::LOG_CLEAR );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage notifications.', 'aaraa-white-label-admin' ) );
		}

		self::ensure_log_table();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::log_table() ); // phpcs:ignore WordPress.DB
		delete_option( self::LOG_OPTION );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SMS, 'notice' => 'log_cleared' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Persist the SMTP settings form (and handle "send test email").
	 *
	 * @return void
	 */
	public function handle_email_save() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE_EMAIL !== $page ) {
			return;
		}
		if ( empty( $_POST[ self::EMAIL_ACTION ] ) ) {
			return;
		}
		check_admin_referer( self::EMAIL_NONCE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage notifications.', 'aaraa-white-label-admin' ) );
		}

		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		$encryption = in_array( ( $in['encryption'] ?? 'tls' ), array( 'none', 'ssl', 'tls' ), true ) ? $in['encryption'] : 'tls';

		$settings = array(
			'enabled'    => empty( $in['enabled'] ) ? 0 : 1,
			'host'       => sanitize_text_field( $in['host'] ?? '' ),
			'port'       => absint( $in['port'] ?? 587 ),
			'encryption' => $encryption,
			'auth'       => empty( $in['auth'] ) ? 0 : 1,
			'username'   => sanitize_text_field( $in['username'] ?? '' ),
			'password'   => (string) ( $in['password'] ?? '' ),
			'from_email' => sanitize_email( $in['from_email'] ?? '' ),
			'from_name'  => sanitize_text_field( $in['from_name'] ?? '' ),
		);

		update_option( self::SMTP_OPTION, $settings );

		// Optional: send a test email to the given address.
		$notice = 'saved';
		$test   = isset( $in['test_email'] ) ? sanitize_email( $in['test_email'] ) : '';
		if ( '' !== $test && is_email( $test ) ) {
			$sent   = wp_mail(
				$test,
				sprintf( /* translators: %s: site name */ __( 'SMTP test email from %s', 'aaraa-white-label-admin' ), get_bloginfo( 'name' ) ),
				__( 'This is a test email confirming your SMTP settings are working.', 'aaraa-white-label-admin' )
			);
			$notice = $sent ? 'test_ok' : 'test_fail';
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_EMAIL, 'notice' => $notice ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize a group of per-status templates.
	 *
	 * @param array<string,mixed> $group Raw posted group.
	 * @return array<string,array{enabled:int,message:string,template_id:string}>
	 */
	private function sanitize_group( $group ) {
		$clean = array();
		if ( ! is_array( $group ) ) {
			return $clean;
		}
		foreach ( $group as $status => $row ) {
			$status = sanitize_key( $status );
			$clean[ $status ] = array(
				'enabled'     => empty( $row['enabled'] ) ? 0 : 1,
				'message'     => sanitize_textarea_field( $row['message'] ?? '' ),
				'template_id' => sanitize_text_field( $row['template_id'] ?? '' ),
			);
		}
		return $clean;
	}

	/* --------------------------------------------------------------------- *
	 * Rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Render the SMS settings screen.
	 *
	 * @return void
	 */
	public function render_sms_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}
		$s = self::settings();

		$order_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$sub_statuses   = function_exists( 'wcs_get_subscription_statuses' ) ? wcs_get_subscription_statuses() : array();
		?>
		<div class="wrap aaraa-notify">
			<h1><?php esc_html_e( 'SMS Notifications', 'aaraa-white-label-admin' ); ?></h1>

			<?php $mds_notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php if ( 'saved' === $mds_notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'SMS settings saved.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php elseif ( 'log_cleared' === $mds_notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'SMS log cleared.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SMS ), admin_url( 'admin.php' ) ) ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::ACTION ); ?>" value="1" />

				<h2 class="title"><?php esc_html_e( 'Gateway Configuration', 'aaraa-white-label-admin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aaraa-sms-url"><?php esc_html_e( 'API URL', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="api_url" id="aaraa-sms-url" type="text" class="regular-text code" value="<?php echo esc_attr( $s['api_url'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-sms-user"><?php esc_html_e( 'User', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="user" id="aaraa-sms-user" type="text" class="regular-text" value="<?php echo esc_attr( $s['user'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-sms-key"><?php esc_html_e( 'API Key', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="apikey" id="aaraa-sms-key" type="text" class="regular-text" value="<?php echo esc_attr( $s['apikey'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-sms-sender"><?php esc_html_e( 'Sender ID', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="senderid" id="aaraa-sms-sender" type="text" class="regular-text" value="<?php echo esc_attr( $s['senderid'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-sms-type"><?php esc_html_e( 'Type', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="type" id="aaraa-sms-type" type="text" class="small-text" value="<?php echo esc_attr( $s['type'] ); ?>" /></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'OTP Template', 'aaraa-white-label-admin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
						<td>
							<label><input type="checkbox" name="otp[enabled]" value="1" <?php checked( ! empty( $s['otp']['enabled'] ) ); ?> /> <?php esc_html_e( 'Enable OTP SMS', 'aaraa-white-label-admin' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-otp-msg"><?php esc_html_e( 'Message', 'aaraa-white-label-admin' ); ?></label></th>
						<td>
							<textarea name="otp[message]" id="aaraa-otp-msg" rows="2" class="large-text"><?php echo esc_textarea( $s['otp']['message'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Use {otp} for the code.', 'aaraa-white-label-admin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-otp-tid"><?php esc_html_e( 'Template ID', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="otp[template_id]" id="aaraa-otp-tid" type="text" class="regular-text" value="<?php echo esc_attr( $s['otp']['template_id'] ); ?>" /></td>
					</tr>
				</table>

				<?php
				$this->render_status_group(
					__( 'Order Status Templates', 'aaraa-white-label-admin' ),
					'order',
					$order_statuses,
					$s['order'],
					'{order_id}, {status}, {customer_name}, {first_name}, {total}, {currency}, {site}'
				);
				$this->render_status_group(
					__( 'Subscription Status Templates', 'aaraa-white-label-admin' ),
					'subscription',
					$sub_statuses,
					$s['subscription'],
					'{subscription_id}, {status}, {customer_name}, {first_name}, {total}, {currency}, {site}'
				);
				$this->render_wallet_templates( $s['wallet'] );
				?>

				<?php submit_button( __( 'Save SMS Settings', 'aaraa-white-label-admin' ) ); ?>
			</form>

			<?php $this->render_log(); ?>
		</div>

		<style>
			.aaraa-notify .aaraa-tpl-table { margin: 8px 0 24px; background: #fff; border: 1px solid #c3c4c7; border-collapse: collapse; width: 100%; max-width: 100%; }
			.aaraa-notify .aaraa-tpl-table th, .aaraa-notify .aaraa-tpl-table td { border: 1px solid #e2e4e7; padding: 8px 10px; vertical-align: top; text-align: left; }
			.aaraa-notify .aaraa-tpl-table thead th { background: #f6f7f7; }
			.aaraa-notify .aaraa-tpl-table td.status-col { white-space: nowrap; font-weight: 600; }
			.aaraa-notify .aaraa-tpl-table textarea { width: 100%; }
			.aaraa-notify .aaraa-tpl-hint { color: #646970; font-size: 11px; }
			.aaraa-notify .aaraa-log-toolbar { margin: 8px 0; display: flex; align-items: center; gap: 8px; }
			.aaraa-notify .aaraa-log-table td { font-size: 12px; word-break: break-word; }
			.aaraa-notify .aaraa-log-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
			.aaraa-notify .aaraa-log-badge.is-sent { background: #e5f5ec; color: #1a7f45; }
			.aaraa-notify .aaraa-log-badge.is-failed { background: #fbeaea; color: #b32d2e; }
		</style>
		<?php
	}

	/**
	 * Render the wallet SMS templates: credit, debit and low-balance (with a
	 * configurable low-balance threshold amount).
	 *
	 * @param array<string,mixed> $wallet Saved wallet templates.
	 * @return void
	 */
	private function render_wallet_templates( $wallet ) {
		$credit = isset( $wallet['credit'] ) ? $wallet['credit'] : array();
		$debit  = isset( $wallet['debit'] ) ? $wallet['debit'] : array();
		$low    = isset( $wallet['low'] ) ? $wallet['low'] : array();
		?>
		<h2 class="title"><?php esc_html_e( 'Wallet Templates', 'aaraa-white-label-admin' ); ?></h2>
		<p class="aaraa-tpl-hint description">
			<?php printf( esc_html__( 'Available variables : %s', 'aaraa-white-label-admin' ), '{amount}, {balance}, {currency}, {customer_name}, {first_name}, {site}' ); ?>
		</p>
		<table class="aaraa-tpl-table">
			<thead>
				<tr>
					<th style="width:60px;"><?php esc_html_e( 'Enable', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:150px;"><?php esc_html_e( 'Event', 'aaraa-white-label-admin' ); ?></th>
					<th><?php esc_html_e( 'Message', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:220px;"><?php esc_html_e( 'Template ID', 'aaraa-white-label-admin' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td style="text-align:center;">
						<input type="checkbox" name="wallet[credit][enabled]" value="1" <?php checked( ! empty( $credit['enabled'] ) ); ?> />
					</td>
					<td class="status-col"><?php esc_html_e( 'Wallet Credited', 'aaraa-white-label-admin' ); ?><br /><code>credit</code></td>
					<td><textarea name="wallet[credit][message]" rows="2"><?php echo esc_textarea( isset( $credit['message'] ) ? $credit['message'] : '' ); ?></textarea></td>
					<td><input type="text" class="widefat" name="wallet[credit][template_id]" value="<?php echo esc_attr( isset( $credit['template_id'] ) ? $credit['template_id'] : '' ); ?>" /></td>
				</tr>
				<tr>
					<td style="text-align:center;">
						<input type="checkbox" name="wallet[debit][enabled]" value="1" <?php checked( ! empty( $debit['enabled'] ) ); ?> />
					</td>
					<td class="status-col"><?php esc_html_e( 'Wallet Debited', 'aaraa-white-label-admin' ); ?><br /><code>debit</code></td>
					<td><textarea name="wallet[debit][message]" rows="2"><?php echo esc_textarea( isset( $debit['message'] ) ? $debit['message'] : '' ); ?></textarea></td>
					<td><input type="text" class="widefat" name="wallet[debit][template_id]" value="<?php echo esc_attr( isset( $debit['template_id'] ) ? $debit['template_id'] : '' ); ?>" /></td>
				</tr>
				<tr>
					<td style="text-align:center;">
						<input type="checkbox" name="wallet[low][enabled]" value="1" <?php checked( ! empty( $low['enabled'] ) ); ?> />
					</td>
					<td class="status-col">
						<?php esc_html_e( 'Low Balance', 'aaraa-white-label-admin' ); ?><br /><code>low</code>
						<div style="margin-top:8px;">
							<label style="display:block;font-size:11px;color:#646970;margin-bottom:2px;"><?php esc_html_e( 'Threshold amount', 'aaraa-white-label-admin' ); ?></label>
							<input type="number" step="0.01" min="0" style="width:100px;" name="wallet[low][threshold]" value="<?php echo esc_attr( isset( $low['threshold'] ) ? $low['threshold'] : '100' ); ?>" />
						</div>
					</td>
					<td>
						<textarea name="wallet[low][message]" rows="2"><?php echo esc_textarea( isset( $low['message'] ) ? $low['message'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Sent once when the balance drops to or below the threshold. You can also use {threshold}.', 'aaraa-white-label-admin' ); ?></p>
					</td>
					<td><input type="text" class="widefat" name="wallet[low][template_id]" value="<?php echo esc_attr( isset( $low['template_id'] ) ? $low['template_id'] : '' ); ?>" /></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render a per-status template table (order / subscription).
	 *
	 * @param string               $title    Section title.
	 * @param string               $group    order|subscription.
	 * @param array<string,string> $statuses status key => label.
	 * @param array<string,mixed>  $saved    Saved templates for this group.
	 * @param string               $tokens   Tokens hint string.
	 * @return void
	 */
	private function render_status_group( $title, $group, $statuses, $saved, $tokens ) {
		?>
		<h2 class="title"><?php echo esc_html( $title ); ?></h2>
		<p class="aaraa-tpl-hint description"><?php printf( esc_html__( 'Available variables : %s', 'aaraa-white-label-admin' ), esc_html( $tokens ) ); ?></p>
		<table class="aaraa-tpl-table">
			<thead>
				<tr>
					<th style="width:60px;"><?php esc_html_e( 'Enable', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'Event', 'aaraa-white-label-admin' ); ?></th>
					<th><?php esc_html_e( 'Message', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:220px;"><?php esc_html_e( 'Template ID', 'aaraa-white-label-admin' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<?php
				$key = str_replace( 'wc-', '', $key );
				$row = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array( 'enabled' => 0, 'message' => '', 'template_id' => '' );
				?>
				<tr>
					<td style="text-align:center;">
						<input type="checkbox" name="<?php echo esc_attr( $group ); ?>[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> />
					</td>
					<td class="status-col"><?php echo esc_html( $label ); ?><br /><code><?php echo esc_html( $key ); ?></code></td>
					<td><textarea name="<?php echo esc_attr( $group ); ?>[<?php echo esc_attr( $key ); ?>][message]" rows="2"><?php echo esc_textarea( $row['message'] ); ?></textarea></td>
					<td><input type="text" class="widefat" name="<?php echo esc_attr( $group ); ?>[<?php echo esc_attr( $key ); ?>][template_id]" value="<?php echo esc_attr( $row['template_id'] ); ?>" /></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the SMS activity log, with a type filter and a clear button.
	 *
	 * @return void
	 */
	private function render_log() {
		$types = array(
			''              => __( 'All types', 'aaraa-white-label-admin' ),
			'otp'           => __( 'OTP', 'aaraa-white-label-admin' ),
			'order'         => __( 'Order', 'aaraa-white-label-admin' ),
			'subscription'  => __( 'Subscription', 'aaraa-white-label-admin' ),
			'wallet_credit' => __( 'Wallet Credit', 'aaraa-white-label-admin' ),
			'wallet_debit'  => __( 'Wallet Debit', 'aaraa-white-label-admin' ),
			'wallet_low'    => __( 'Wallet Low Balance', 'aaraa-white-label-admin' ),
			'test'          => __( 'Test', 'aaraa-white-label-admin' ),
			'manual'        => __( 'Manual', 'aaraa-white-label-admin' ),
		);

		// Initial view: newest page, unfiltered. Type, mobile search and paging are
		// all driven by AJAX from here on.
		$initial   = self::query_log( '', '', 1 );
		$total     = $initial['total'];
		$pages     = $initial['pages'];
		$page_rows = $initial['rows'];
		?>
		<hr />
		<h2 class="title"><?php esc_html_e( 'SMS Log', 'aaraa-white-label-admin' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Every SMS send attempt is recorded here, including the gateway response. Use this to confirm whether an order/subscription status SMS was actually delivered.', 'aaraa-white-label-admin' ); ?></p>

		<div class="aaraa-log-toolbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
			<select id="aaraa-log-type">
				<?php foreach ( $types as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" id="aaraa-log-search" placeholder="<?php esc_attr_e( 'Search mobile number…', 'aaraa-white-label-admin' ); ?>" style="min-width:220px;" />
			<button type="button" class="button" id="aaraa-log-search-btn"><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></button>
			<span class="spinner aaraa-log-spinner" style="float:none;margin:0;"></span>
			<span id="aaraa-log-count" class="aaraa-tpl-hint"><?php echo esc_html( $this->log_count_label( 1, $total ) ); ?></span>
			<?php if ( $total > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SMS ), admin_url( 'admin.php' ) ) ); ?>" style="margin-left:auto;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the entire SMS log?', 'aaraa-white-label-admin' ) ); ?>');">
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
					<th style="width:110px;"><?php esc_html_e( 'Type', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Reference', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Mobile', 'aaraa-white-label-admin' ); ?></th>
					<th><?php esc_html_e( 'Message', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Result', 'aaraa-white-label-admin' ); ?></th>
					<th style="width:220px;"><?php esc_html_e( 'Gateway response', 'aaraa-white-label-admin' ); ?></th>
				</tr>
			</thead>
			<tbody id="aaraa-sms-log-body">
			<?php if ( empty( $page_rows ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No SMS activity logged yet.', 'aaraa-white-label-admin' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $page_rows as $r ) { echo $this->log_row_html( $r ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ } ?>
			<?php endif; ?>
			</tbody>
		</table>

		<div id="aaraa-sms-log-nav" class="aaraa-log-nav" style="margin-top:10px;"><?php echo $this->log_nav_html( 1, $pages, $total ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></div>

		<script>
		jQuery( function ( $ ) {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( wp_create_nonce( self::LOG_QUERY_NONCE ) ); ?>;
			var paged   = 1;

			function load( p ) {
				paged = p || 1;
				var $spin = $( '.aaraa-log-spinner' ).addClass( 'is-active' );
				$.post( ajaxUrl, {
					action:   'aaraa_sms_log_query',
					nonce:    nonce,
					log_type: $( '#aaraa-log-type' ).val(),
					search:   $( '#aaraa-log-search' ).val(),
					paged:    paged
				}, function ( resp ) {
					$spin.removeClass( 'is-active' );
					if ( resp && resp.success ) {
						$( '#aaraa-sms-log-body' ).html( resp.data.body );
						$( '#aaraa-sms-log-nav' ).html( resp.data.nav );
						$( '#aaraa-log-count' ).text( resp.data.count_label );
						paged = resp.data.paged;
					}
				} ).fail( function () {
					$spin.removeClass( 'is-active' );
				} );
			}

			$( '#aaraa-log-type' ).on( 'change', function () { load( 1 ); } );
			$( '#aaraa-log-search-btn' ).on( 'click', function () { load( 1 ); } );
			$( '#aaraa-log-search' ).on( 'keydown', function ( e ) { if ( 13 === e.which ) { e.preventDefault(); load( 1 ); } } );
			$( document ).on( 'click', '#aaraa-sms-log-nav .aaraa-log-page', function ( e ) {
				e.preventDefault();
				var p = parseInt( $( this ).data( 'page' ), 10 );
				if ( p ) { load( p ); }
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Build one log table row.
	 *
	 * @param array<string,mixed> $r Log entry.
	 * @return string HTML.
	 */
	private function log_row_html( $r ) {
		$type    = isset( $r['type'] ) ? $r['type'] : '';
		$ref     = isset( $r['ref'] ) ? $r['ref'] : '';
		$status  = isset( $r['status'] ) ? $r['status'] : '';
		$is_sent = 'sent' === ( isset( $r['result'] ) ? $r['result'] : '' );

		ob_start();
		?>
		<tr>
			<td><?php echo esc_html( $r['time'] ?? '' ); ?></td>
			<td><code><?php echo esc_html( $type ); ?></code></td>
			<td>
				<?php echo $ref ? esc_html( $ref ) : '&mdash;'; ?>
				<?php if ( $status ) : ?><br /><span class="aaraa-tpl-hint"><?php echo esc_html( $status ); ?></span><?php endif; ?>
			</td>
			<td><?php echo esc_html( $r['mobile'] ?? '' ); ?></td>
			<td><?php echo esc_html( $r['message'] ?? '' ); ?></td>
			<td>
				<span class="aaraa-log-badge <?php echo $is_sent ? 'is-sent' : 'is-failed'; ?>">
					<?php echo esc_html( $is_sent ? __( 'Sent', 'aaraa-white-label-admin' ) : __( 'Failed', 'aaraa-white-label-admin' ) ); ?>
				</span>
				<?php if ( isset( $r['http_code'] ) && '' !== $r['http_code'] ) : ?>
					<br /><span class="aaraa-tpl-hint">HTTP <?php echo esc_html( $r['http_code'] ); ?></span>
				<?php endif; ?>
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
	 * AJAX: return a filtered, paginated slice of the SMS log.
	 *
	 * @return void
	 */
	public function ajax_log_query() {
		check_ajax_referer( self::LOG_QUERY_NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'aaraa-white-label-admin' ) ) );
		}

		$type   = isset( $_POST['log_type'] ) ? sanitize_key( wp_unslash( $_POST['log_type'] ) ) : '';
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$paged  = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

		$result = self::query_log( $type, $search, $paged );
		$total  = $result['total'];
		$pages  = $result['pages'];
		$paged  = $result['paged'];

		$body = '';
		if ( empty( $result['rows'] ) ) {
			$body = '<tr><td colspan="7">' . esc_html__( 'No matching SMS activity.', 'aaraa-white-label-admin' ) . '</td></tr>';
		} else {
			foreach ( $result['rows'] as $r ) {
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

	/**
	 * Placeholder screen for a channel that is not configured yet.
	 *
	 * @param string $channel Channel label.
	 * @return void
	 */
	private function render_placeholder( $channel ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( sprintf( /* translators: %s: channel name */ __( '%s Notifications', 'aaraa-white-label-admin' ), $channel ) ) . '</h1>';
		echo '<p>' . esc_html__( 'This channel is not configured yet. Configuration will be added here.', 'aaraa-white-label-admin' ) . '</p>';
		echo '</div>';
	}

	/**
	 * WhatsApp screen — delegated to the WhatsApp module (Cloud API config,
	 * template manager, per-status mapping and logs).
	 *
	 * @return void
	 */
	public function render_whatsapp_page() {
		if ( class_exists( __NAMESPACE__ . '\\WhatsApp_Admin' ) ) {
			( new WhatsApp_Admin() )->render_page();
			return;
		}
		$this->render_placeholder( __( 'WhatsApp', 'aaraa-white-label-admin' ) );
	}

	/**
	 * E-Mail screen: SMTP settings + a link to the WooCommerce email settings
	 * (where the Order and Subscription status-change emails are configured).
	 *
	 * @return void
	 */
	public function render_email_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}
		$s        = self::smtp_settings();
		$wc_email = admin_url( 'admin.php?page=wc-settings&tab=email' );

		$notice = isset( $_GET['notice'] ) ? sanitize_key( $_GET['notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap aaraa-notify">
			<h1><?php esc_html_e( 'Email Notifications', 'aaraa-white-label-admin' ); ?></h1>

			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'SMTP settings saved.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php elseif ( 'test_ok' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved. Test email sent successfully.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php elseif ( 'test_fail' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Settings saved, but the test email could not be sent. Check the SMTP details.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_EMAIL ), admin_url( 'admin.php' ) ) ); ?>">
				<?php wp_nonce_field( self::EMAIL_NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::EMAIL_ACTION ); ?>" value="1" />

				<h2 class="title"><?php esc_html_e( 'SMTP Settings', 'aaraa-white-label-admin' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Route all outgoing WordPress/WooCommerce email through your SMTP server.', 'aaraa-white-label-admin' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable SMTP', 'aaraa-white-label-admin' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Send email via SMTP', 'aaraa-white-label-admin' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-host"><?php esc_html_e( 'SMTP Host', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="host" id="aaraa-smtp-host" type="text" class="regular-text" value="<?php echo esc_attr( $s['host'] ); ?>" placeholder="smtp.example.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-port"><?php esc_html_e( 'SMTP Port', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="port" id="aaraa-smtp-port" type="number" class="small-text" value="<?php echo esc_attr( $s['port'] ); ?>" /> <span class="description"><?php esc_html_e( '587 (TLS) or 465 (SSL)', 'aaraa-white-label-admin' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-enc"><?php esc_html_e( 'Encryption', 'aaraa-white-label-admin' ); ?></label></th>
						<td>
							<select name="encryption" id="aaraa-smtp-enc">
								<option value="none" <?php selected( 'none', $s['encryption'] ); ?>><?php esc_html_e( 'None', 'aaraa-white-label-admin' ); ?></option>
								<option value="ssl" <?php selected( 'ssl', $s['encryption'] ); ?>>SSL</option>
								<option value="tls" <?php selected( 'tls', $s['encryption'] ); ?>>TLS</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Authentication', 'aaraa-white-label-admin' ); ?></th>
						<td><label><input type="checkbox" name="auth" value="1" <?php checked( ! empty( $s['auth'] ) ); ?> /> <?php esc_html_e( 'Use username and password', 'aaraa-white-label-admin' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-user"><?php esc_html_e( 'Username', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="username" id="aaraa-smtp-user" type="text" class="regular-text" value="<?php echo esc_attr( $s['username'] ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-pass"><?php esc_html_e( 'Password', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="password" id="aaraa-smtp-pass" type="password" class="regular-text" value="<?php echo esc_attr( $s['password'] ); ?>" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-from"><?php esc_html_e( 'From Email', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="from_email" id="aaraa-smtp-from" type="email" class="regular-text" value="<?php echo esc_attr( $s['from_email'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-fromname"><?php esc_html_e( 'From Name', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="from_name" id="aaraa-smtp-fromname" type="text" class="regular-text" value="<?php echo esc_attr( $s['from_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aaraa-smtp-test"><?php esc_html_e( 'Send Test Email To', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="test_email" id="aaraa-smtp-test" type="email" class="regular-text" placeholder="you@example.com" /> <span class="description"><?php esc_html_e( 'Optional — fill in to send a test on save.', 'aaraa-white-label-admin' ); ?></span></td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Email Settings', 'aaraa-white-label-admin' ) ); ?>
			</form>

			<hr />

			<h2 class="title"><?php esc_html_e( 'Order & Subscription Status Emails', 'aaraa-white-label-admin' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'The emails sent when an order or subscription status changes. Enable/disable, edit recipients, subjects and content there.', 'aaraa-white-label-admin' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $wc_email ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Open Email Settings', 'aaraa-white-label-admin' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * In-app screen — delegated to the Firebase module (Cloud Messaging config,
	 * per-status push mapping, device registration and logs).
	 *
	 * @return void
	 */
	public function render_inapp_page() {
		if ( class_exists( __NAMESPACE__ . '\\Firebase_Admin' ) ) {
			( new Firebase_Admin() )->render_page();
			return;
		}
		$this->render_placeholder( __( 'In-app', 'aaraa-white-label-admin' ) );
	}
}
