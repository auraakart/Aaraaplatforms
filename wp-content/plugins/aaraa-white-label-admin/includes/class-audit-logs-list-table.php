<?php
/**
 * Audit Logs list table.
 *
 * Renders the WP Activity Log (wp-security-audit-log / WSAL) event store in the
 * branded admin, reusing WSAL's own entities and helpers so the data, messages
 * and labels match its native viewer exactly:
 *
 *   - \WSAL\Entities\Occurrences_Entity   query + per-row metadata + message.
 *   - \WSAL\Controllers\Constants          severity code -> label.
 *   - \WSAL\Controllers\Alert_Manager      object / event-type code -> label.
 *   - \WSAL\Helpers\User_Utils             actor name.
 *   - \WSAL\Helpers\DateTime_Formatter_Helper  localised date/time.
 *
 * Read-only: no bulk actions or deletion, so nothing in the audit trail can be
 * altered from here.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists WSAL audit-log events.
 */
class Audit_Logs_List_Table extends \WP_List_Table {

	const OCCURRENCES = '\WSAL\Entities\Occurrences_Entity';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'audit_log',
				'plural'   => 'audit_logs',
				'ajax'     => false,
				'screen'   => 'aaraa-audit-logs',
			)
		);
	}

	/**
	 * Rebrand WSAL's stored labels: "WooCommerce" -> "Aaraa" for display only.
	 *
	 * @param string $text Text to filter.
	 * @return string
	 */
	private static function brand( $text ) {
		return str_replace( 'WooCommerce', 'Aaraa', (string) $text );
	}

	/**
	 * Whether the WSAL data layer is available.
	 *
	 * @return bool
	 */
	public static function wsal_available() {
		return class_exists( self::OCCURRENCES )
			&& class_exists( '\WSAL\Controllers\Constants' )
			&& class_exists( '\WSAL\Controllers\Alert_Manager' );
	}

	/**
	 * Column definitions (mirrors WSAL's viewer).
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'id'         => __( 'ID', 'aaraa-white-label-admin' ),
			'severity'   => __( 'Severity', 'aaraa-white-label-admin' ),
			'date'       => __( 'Date', 'aaraa-white-label-admin' ),
			'user'       => __( 'User', 'aaraa-white-label-admin' ),
			'ip'         => __( 'IP', 'aaraa-white-label-admin' ),
			'object'     => __( 'Object', 'aaraa-white-label-admin' ),
			'event_type' => __( 'Event Type', 'aaraa-white-label-admin' ),
			'message'    => __( 'Message', 'aaraa-white-label-admin' ),
			'details'    => '',
		);
	}

	/**
	 * "More details…" toggle for the per-row metadata inspector.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_details( $item ) {
		$id = isset( $item['id'] ) ? (int) $item['id'] : 0;
		return sprintf(
			'<button type="button" class="button aaraa-auditlog-more" data-target="aaraa-auditlog-%1$d" data-more="%2$s" data-close="%3$s" aria-expanded="false">%2$s</button>',
			$id,
			esc_attr__( 'More details…', 'aaraa-white-label-admin' ),
			esc_attr__( 'Close inspector', 'aaraa-white-label-admin' )
		);
	}

	/**
	 * Render each event row followed by its hidden full-metadata inspector row.
	 *
	 * @param array $item Row.
	 * @return void
	 */
	public function single_row( $item ) {
		parent::single_row( $item );

		$id   = isset( $item['id'] ) ? (int) $item['id'] : 0;
		$cols = count( $this->get_columns() );
		$meta = \WSAL\Entities\Occurrences_Entity::get_alert_meta( $item );

		printf(
			'<tr class="aaraa-auditlog-detailrow" id="aaraa-auditlog-%1$d" style="display:none;"><td colspan="%2$d"><pre class="aaraa-auditlog-detail">%3$s</pre></td></tr>',
			$id,
			(int) $cols,
			wp_kses_post( self::brand( (string) $meta ) )
		);
	}

	/**
	 * Query the events with pagination and a simple search.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		if ( ! self::wsal_available() ) {
			$this->items = array();
			$this->set_pagination_args( array( 'total_items' => 0, 'per_page' => 20, 'total_pages' => 0 ) );
			return;
		}

		$occ      = self::OCCURRENCES;
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$offset   = ( $paged - 1 ) * $per_page;

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( '' !== $search ) {
			global $wpdb;
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			// Message text is generated at render time, so search the stored
			// columns (event id, IP, user, object, event type).
			$cond = '( alert_id LIKE %s OR client_ip LIKE %s OR username LIKE %s OR object LIKE %s OR event_type LIKE %s )';
			$args = array( $like, $like, $like, $like, $like );
		} else {
			$cond = '%d';
			$args = array( 1 );
		}

		$total = (int) $occ::count( $cond, $args );

		$extra = ' ORDER BY created_on DESC LIMIT ' . (int) $per_page . ' OFFSET ' . (int) $offset;
		$rows  = $occ::load_array( $cond, $args, null, $extra );

		if ( ! empty( $rows ) ) {
			$rows = $occ::get_multi_meta_array( $rows );
		}

		$this->items = array_values( (array) $rows );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * ID column: the padded WSAL event (alert) id.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_id( $item ) {
		$alert_id = isset( $item['alert_id'] ) ? (int) $item['alert_id'] : 0;
		return '<strong>' . esc_html( str_pad( (string) $alert_id, 4, '0', STR_PAD_LEFT ) ) . '</strong>';
	}

	/**
	 * Severity column: coloured icon + label from WSAL's severity map.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_severity( $item ) {
		$code = isset( $item['severity'] ) ? (int) $item['severity'] : 0;
		$sev  = \WSAL\Controllers\Constants::get_severity_by_code( $code );
		$text = isset( $sev['text'] ) ? $sev['text'] : __( 'Unknown', 'aaraa-white-label-admin' );
		$val  = isset( $sev['value'] ) ? (int) $sev['value'] : 0;

		if ( $val >= 500 ) {
			$icon  = 'warning';
			$color = '#b32d2e';
		} elseif ( $val >= 300 ) {
			$icon  = 'warning';
			$color = '#dba617';
		} elseif ( $val >= 250 ) {
			$icon  = 'flag';
			$color = '#dba617';
		} else {
			$icon  = 'info-outline';
			$color = '#2271b1';
		}

		return sprintf(
			'<span class="dashicons dashicons-%1$s" style="color:%2$s;font-size:22px;width:22px;height:22px" title="%3$s"></span><span class="screen-reader-text">%3$s</span>',
			esc_attr( $icon ),
			esc_attr( $color ),
			esc_attr( $text )
		);
	}

	/**
	 * Date column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_date( $item ) {
		$ts = isset( $item['created_on'] ) ? $item['created_on'] : 0;
		if ( ! $ts ) {
			return '<em>' . esc_html__( 'Unknown', 'aaraa-white-label-admin' ) . '</em>';
		}
		return wp_kses_post( \WSAL\Helpers\DateTime_Formatter_Helper::get_formatted_date_time( $ts, 'datetime', true, true ) );
	}

	/**
	 * User column: actor name and role(s).
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_user( $item ) {
		$meta = isset( $item['meta_values'] ) ? (array) $item['meta_values'] : array();

		$user = \WSAL\Helpers\User_Utils::get_user_object_from_meta( $meta );
		if ( $user instanceof \WP_User ) {
			$name = \WSAL\Helpers\User_Utils::get_display_label( $user );
		} else {
			$name = \WSAL\Helpers\User_Utils::get_username( $meta );
		}
		if ( '' === (string) $name ) {
			$name = __( 'System', 'aaraa-white-label-admin' );
		}
		$name = self::brand( $name );

		$roles = isset( $item['user_roles'] ) ? trim( (string) $item['user_roles'] ) : '';
		$out   = '<strong>' . esc_html( $name ) . '</strong>';
		if ( '' !== $roles ) {
			$out .= '<br><span class="description">' . esc_html( $roles ) . '</span>';
		}
		return $out;
	}

	/**
	 * IP column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_ip( $item ) {
		$ip = isset( $item['client_ip'] ) ? trim( (string) $item['client_ip'] ) : '';
		return $ip ? esc_html( $ip ) : '&mdash;';
	}

	/**
	 * Object column: friendly label for the WSAL object code.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_object( $item ) {
		$object = '';
		if ( ! empty( $item['object'] ) ) {
			$object = \WSAL\Controllers\Alert_Manager::get_event_objects_data( $item['object'] );
		} elseif ( ! empty( $item['meta_values']['Object'] ) ) {
			$object = \WSAL\Controllers\Alert_Manager::get_event_objects_data( $item['meta_values']['Object'] );
		}

		// Branded labels: drop the "WooCommerce " prefix (e.g. "WooCommerce Order"
		// -> "Order", "WooCommerce Product" -> "Product").
		if ( 0 === strpos( (string) $object, 'WooCommerce ' ) ) {
			$object = substr( $object, strlen( 'WooCommerce ' ) );
		}

		return $object ? esc_html( $object ) : '&mdash;';
	}

	/**
	 * Event Type column: friendly label for the WSAL event-type code.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_event_type( $item ) {
		$type = '';
		if ( ! empty( $item['event_type'] ) ) {
			$type = \WSAL\Controllers\Alert_Manager::get_event_type_data( $item['event_type'] );
		} elseif ( ! empty( $item['meta_values']['EventType'] ) ) {
			$type = \WSAL\Controllers\Alert_Manager::get_event_type_data( $item['meta_values']['EventType'] );
		}
		return $type ? esc_html( $type ) : '&mdash;';
	}

	/**
	 * Message column: WSAL's fully-rendered event message.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_message( $item ) {
		$message = \WSAL\Entities\Occurrences_Entity::get_alert_message( $item );
		return '<div class="aaraa-auditlog__msg">' . wp_kses_post( self::brand( (string) $message ) ) . '</div>';
	}

	/**
	 * Fallback renderer.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) && is_scalar( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	/**
	 * Empty-state message.
	 *
	 * @return void
	 */
	public function no_items() {
		if ( ! self::wsal_available() ) {
			esc_html_e( 'The WP Activity Log plugin is not active, so there are no audit logs to show.', 'aaraa-white-label-admin' );
			return;
		}
		esc_html_e( 'No audit log events found.', 'aaraa-white-label-admin' );
	}
}
