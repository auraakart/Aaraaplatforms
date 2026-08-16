<?php
/**
 * Customer activity / notification log store.
 *
 * One table backs every notification channel (system, whatsapp, sms, email,
 * in_app). Other code records a log with Customer_Logs::add(); the Customer 360
 * screen reads them back per channel.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight per-customer log table.
 */
class Customer_Logs {

	/**
	 * Supported channels => label.
	 *
	 * @return array<string, string>
	 */
	public static function channels() {
		return array(
			'system'   => __( 'System', 'aaraa-white-label-admin' ),
			'whatsapp' => __( 'WhatsApp', 'aaraa-white-label-admin' ),
			'sms'      => __( 'SMS', 'aaraa-white-label-admin' ),
			'email'    => __( 'E-Mail', 'aaraa-white-label-admin' ),
			'in_app'   => __( 'In-App', 'aaraa-white-label-admin' ),
		);
	}

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aaraa_customer_logs';
	}

	/**
	 * Create the table (idempotent). Safe to call on every activation.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			channel varchar(20) NOT NULL DEFAULT 'system',
			event varchar(100) NOT NULL DEFAULT '',
			message text NULL,
			status varchar(20) NOT NULL DEFAULT '',
			meta longtext NULL,
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_channel (user_id, channel),
			KEY created_at (created_at)
		) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		wp_cache_delete( 'aaraa_logs_table' );
	}

	/**
	 * Record a log entry.
	 *
	 * @param int    $user_id Customer ID.
	 * @param string $channel One of channels().
	 * @param string $event   Machine event key (e.g. 'wallet_credit', 'message_sent').
	 * @param string $message Human-readable message (basic HTML allowed).
	 * @param array  $args    Optional: status, meta (array), created_by, created_at (GMT).
	 * @return int|false Inserted id or false.
	 */
	public static function add( $user_id, $channel, $event, $message = '', $args = array() ) {
		global $wpdb;
		$user_id = (int) $user_id;
		$channel = sanitize_key( $channel );
		if ( ! $user_id || ! array_key_exists( $channel, self::channels() ) ) {
			return false;
		}

		$defaults = array(
			'status'     => '',
			'meta'       => array(),
			'created_by' => get_current_user_id(),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		$args = wp_parse_args( $args, $defaults );

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'user_id'    => $user_id,
				'channel'    => $channel,
				'event'      => substr( sanitize_text_field( $event ), 0, 100 ),
				'message'    => wp_kses_post( (string) $message ),
				'status'     => sanitize_key( $args['status'] ),
				'meta'       => is_array( $args['meta'] ) && $args['meta'] ? wp_json_encode( $args['meta'] ) : '',
				'created_by' => $args['created_by'] ? (int) $args['created_by'] : null,
				'created_at' => $args['created_at'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a paginated page of logs for a user + channel.
	 *
	 * @param int    $user_id  Customer ID.
	 * @param string $channel  Channel.
	 * @param int    $paged    Page.
	 * @param int    $per_page Rows per page.
	 * @return array{0:array,1:int,2:int} rows, total, total_pages.
	 */
	public static function get( $user_id, $channel, $paged, $per_page ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array( array(), 0, 1 );
		}
		$table   = self::table();
		$user_id = (int) $user_id;
		$channel = sanitize_key( $channel );

		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND channel = %s", $user_id, $channel ) ); // phpcs:ignore WordPress.DB
		$pages  = (int) max( 1, ceil( $total / $per_page ) );
		$paged  = min( max( 1, $paged ), $pages );
		$offset = ( $paged - 1 ) * $per_page;
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND channel = %s ORDER BY id DESC LIMIT %d OFFSET %d", $user_id, $channel, $per_page, $offset )
		);
		return array( (array) $rows, $total, $pages );
	}

	/**
	 * Most recent N logs for a user + channel (used by the system timeline merge).
	 *
	 * @param int    $user_id Customer ID.
	 * @param string $channel Channel.
	 * @param int    $limit   Max rows.
	 * @return array
	 */
	public static function recent( $user_id, $channel, $limit = 50 ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array();
		}
		$table = self::table();
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND channel = %s ORDER BY id DESC LIMIT %d", (int) $user_id, sanitize_key( $channel ), (int) $limit )
		);
	}

	/**
	 * Whether the log table exists (cached).
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$exists = wp_cache_get( 'aaraa_logs_table' );
		if ( false === $exists ) {
			$table  = self::table();
			$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB
			$exists = ( $found === $table ) ? 'yes' : 'no';
			wp_cache_set( 'aaraa_logs_table', $exists, '', HOUR_IN_SECONDS );
		}
		return 'yes' === $exists;
	}
}
