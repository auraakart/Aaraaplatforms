<?php
/**
 * Customer listing table for the "Aaraa Customer 360" page.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists customers with ID, Name, Email, Mobile, Wallet and row actions.
 */
class Customers_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'customer',
				'plural'   => 'customers',
				'ajax'     => false,
				'screen'   => 'aaraa-customers',
			)
		);
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'id'      => __( 'ID', 'aaraa-white-label-admin' ),
			'name'    => __( 'Name', 'aaraa-white-label-admin' ),
			'email'   => __( 'Email', 'aaraa-white-label-admin' ),
			'mobile'  => __( 'Mobile', 'aaraa-white-label-admin' ),
			'wallet'  => __( 'Wallet', 'aaraa-white-label-admin' ),
			'actions' => __( 'Action', 'aaraa-white-label-admin' ),
		);
	}

	/**
	 * Checkbox column — lets rows be selected for the bulk "Send WhatsApp" tool.
	 *
	 * @param \WP_User $item User row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="customer_ids[]" value="%1$d" data-mobile="%2$s" />',
			(int) $item->ID,
			esc_attr( $this->row_mobile( $item->ID ) )
		);
	}

	/**
	 * A customer's best-known mobile number (billing_phone, then mobile meta).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function row_mobile( $user_id ) {
		$mobile = get_user_meta( $user_id, 'billing_phone', true );
		if ( '' === $mobile ) {
			$mobile = get_user_meta( $user_id, 'mobile', true );
		}
		return (string) $mobile;
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0:string,1:bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'    => array( 'ID', false ),
			'name'  => array( 'display_name', false ),
			'email' => array( 'user_email', false ),
		);
	}

	/**
	 * Meta keys a search term is matched against, alongside the users table.
	 *
	 * @return string[]
	 */
	public static function search_meta_keys() {
		return array(
			'first_name',
			'last_name',
			'nickname',
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
			'mobile',
			'shipping_phone',
		);
	}

	/**
	 * User ids matching a term across name, email and mobile.
	 *
	 * @param string $term Search term.
	 * @return int[]
	 */
	public static function search_user_ids( $term ) {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$uid  = ctype_digit( trim( (string) $term ) ) ? (int) $term : 0; // exact customer/user ID match when numeric.
		$keys = "'" . implode( "','", array_map( 'esc_sql', self::search_meta_keys() ) ) . "'";

		$sql = "SELECT DISTINCT u.ID FROM {$wpdb->users} u
			LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key IN ({$keys})
			WHERE u.ID = %d
				OR u.user_login LIKE %s
				OR u.user_email LIKE %s
				OR u.display_name LIKE %s
				OR u.user_nicename LIKE %s
				OR m.meta_value LIKE %s";

		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $uid, $like, $like, $like, $like, $like ) ) ); // phpcs:ignore WordPress.DB

		// A numeric term may also be an order or subscription ID — include that
		// order/subscription's customer. wc_get_order() returns both types and is
		// HPOS-aware, returning false for an ID that is neither.
		if ( $uid && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $uid );
			if ( $order && method_exists( $order, 'get_customer_id' ) ) {
				$cust = (int) $order->get_customer_id();
				if ( $cust ) {
					$ids[] = $cust;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * The wallet balance thresholds offered by the "low balance" filter.
	 *
	 * @return float[]
	 */
	public static function wallet_thresholds() {
		return array( 100, 250, 500, 1000 );
	}

	/**
	 * Customer ids that have at least one subscription in the given status.
	 *
	 * Queried directly (customer ids only) rather than through
	 * wcs_get_subscriptions(), which would hydrate every matching subscription
	 * into a WC_Subscription object — far too heavy on a store with thousands of
	 * daily subscriptions. Handles both HPOS and legacy post storage, and the
	 * custom `wc-pause` status.
	 *
	 * @param string $status Status key with or without the wc- prefix (e.g. 'active', 'pause').
	 * @return int[]
	 */
	public static function subscription_customer_ids( $status ) {
		global $wpdb;

		if ( '' === $status ) {
			return array();
		}
		// Match the status regardless of whether storage keeps the wc- prefix.
		$wc_status = ( 0 === strpos( $status, 'wc-' ) ) ? $status : 'wc-' . $status;
		$bare      = preg_replace( '/^wc-/', '', $wc_status );

		$ids = array();

		// Use the HPOS orders table when it exists and actually holds
		// subscriptions; that store is then authoritative, so we do not also read
		// the (possibly stale) legacy post rows.
		$orders_table = $wpdb->prefix . 'wc_orders';
		$has_hpos     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $orders_table ) ) ) === $orders_table; // phpcs:ignore WordPress.DB
		$hpos_in_use  = false;

		if ( $has_hpos ) {
			$hpos_in_use = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
				$wpdb->prepare( "SELECT COUNT(*) FROM {$orders_table} WHERE type = %s", 'shop_subscription' )
			) > 0;
		}

		if ( $hpos_in_use ) {
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"SELECT DISTINCT customer_id FROM {$orders_table} WHERE type = %s AND status IN ( %s, %s ) AND customer_id > 0",
					'shop_subscription',
					$wc_status,
					$bare
				)
			);
		} else {
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_customer_user'
					WHERE p.post_type = 'shop_subscription' AND p.post_status IN ( %s, %s )",
					$wc_status,
					$bare
				)
			);
		}

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	}

	/**
	 * Query customers with pagination, search, filters and ordering.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = $this->per_page();
		$paged    = $this->get_pagenum();

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'ID'; // phpcs:ignore WordPress.Security.NonceVerification
		$order   = ( isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification
		$allowed = array( 'ID', 'display_name', 'user_email' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'ID';
		}

		$built = $this->build_query( $per_page, ( $paged - 1 ) * $per_page, $orderby, $order );

		if ( $built['empty'] ) {
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => $per_page,
					'total_pages' => 0,
				)
			);
			return;
		}

		$query = new \WP_User_Query( $built['args'] );
		$items = (array) $query->get_results();

		// Prime the user + meta cache for the whole page in one pass.
		if ( $items ) {
			cache_users( wp_list_pluck( $items, 'ID' ) );
		}

		$this->items = $items;

		$total = (int) $query->get_total();
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Build the WP_User_Query args for the current request filters.
	 *
	 * Shared by prepare_items() (paginated) and export_ids() (all rows), so the
	 * CSV export always matches exactly what the list is showing.
	 *
	 * @param int    $number  Rows to fetch (-1 for all).
	 * @param int    $offset  Query offset.
	 * @param string $orderby Order-by field.
	 * @param string $order   ASC|DESC.
	 * @return array{args:array,empty:bool} 'empty' true means the filters resolved to no users.
	 */
	private function build_query( $number, $offset, $orderby = 'ID', $order = 'DESC' ) {
		$args = array(
			'role__in' => array( 'customer', 'subscriber' ),
			'number'   => $number,
			'offset'   => $offset,
			'orderby'  => $orderby,
			'order'    => $order,
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		// Slot / hub / boy: 0 = all, -1 = not assigned, positive = a specific one.
		$slot       = $this->filter_int( 'aaraa_slot' );
		$hub        = $this->filter_int( 'aaraa_hub' );
		$boy        = $this->filter_int( 'aaraa_boy' );
		$wallet_max = ( isset( $_REQUEST['aaraa_wallet_max'] ) && '' !== $_REQUEST['aaraa_wallet_max'] )
			? (float) wp_unslash( $_REQUEST['aaraa_wallet_max'] )
			: null;
		$sub_status = isset( $_REQUEST['aaraa_sub_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['aaraa_sub_status'] ) ) : '';
		$search     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:enable

		// Slot / hub / person / wallet map onto user meta.
		$meta_query = array();
		foreach ( array(
			Customer_Delivery::META_SLOT => $slot,
			Customer_Delivery::META_HUB  => $hub,
			Customer_Delivery::META_BOY  => $boy,
		) as $meta_key => $value ) {
			$clause = self::meta_clause( $meta_key, $value );
			if ( $clause ) {
				$meta_query[] = $clause;
			}
		}
		if ( null !== $wallet_max ) {
			// A customer with no wallet meta is treated as balance 0, i.e. low.
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'wps_wallet',
					'value'   => $wallet_max,
					'type'    => 'DECIMAL(20,4)',
					'compare' => '<',
				),
				array(
					'key'     => 'wps_wallet',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		/*
		 * Search and subscription-status both constrain to a set of user ids.
		 * Resolve each to ids, then intersect so all active filters apply together.
		 */
		$include = null;
		if ( '' !== $search ) {
			$include = self::search_user_ids( $search );
		}
		if ( '' !== $sub_status ) {
			$sub_ids = self::subscription_customer_ids( $sub_status );
			$include = ( null === $include ) ? $sub_ids : array_values( array_intersect( $include, $sub_ids ) );
		}

		if ( null !== $include ) {
			if ( empty( $include ) ) {
				return array( 'args' => $args, 'empty' => true );
			}
			$args['include'] = $include;
			// Subscription holders may lack the exact role; query the ids directly.
			if ( '' !== $sub_status ) {
				unset( $args['role__in'] );
			}
		}

		return array( 'args' => $args, 'empty' => false );
	}

	/**
	 * All user IDs matching the current filters (no pagination) — for CSV export.
	 *
	 * @return int[]
	 */
	public function export_ids() {
		$built = $this->build_query( -1, 0, 'ID', 'ASC' );
		if ( $built['empty'] ) {
			return array();
		}
		$built['args']['fields'] = 'ID';
		$query                   = new \WP_User_Query( $built['args'] );
		return array_map( 'intval', (array) $query->get_results() );
	}

	/**
	 * Allowed rows-per-page choices.
	 *
	 * @return int[]
	 */
	private function per_page_choices() {
		return array( 20, 50, 100, 200, 500 );
	}

	/**
	 * Effective rows-per-page from the request (validated), default 20.
	 *
	 * @return int
	 */
	private function per_page() {
		$req = isset( $_REQUEST['aaraa_per_page'] ) ? (int) $_REQUEST['aaraa_per_page'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $req, $this->per_page_choices(), true ) ? $req : 20;
	}

	/**
	 * Read a slot/hub/boy filter value: 0 = all, -1 = not assigned, positive = id.
	 *
	 * @param string $key Request key.
	 * @return int
	 */
	private function filter_int( $key ) {
		$val = isset( $_REQUEST[ $key ] ) ? (int) wp_unslash( $_REQUEST[ $key ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $val < -1 ? 0 : $val;
	}

	/**
	 * Build a meta_query clause for a slot/hub/boy filter value.
	 *
	 * @param string $key   Meta key.
	 * @param int    $value 0 = none, -1 = not assigned, positive = equals.
	 * @return array|null
	 */
	private static function meta_clause( $key, $value ) {
		if ( -1 === $value ) {
			// Two clauses only (NOT EXISTS + IN). Mixing three same-key clauses in
			// one OR generates unreliable SQL in WP_Meta_Query.
			return array(
				'relation' => 'OR',
				array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $key,
					'value'   => array( '', '0' ),
					'compare' => 'IN',
				),
			);
		}
		if ( $value > 0 ) {
			return array(
				'key'   => $key,
				'value' => $value,
			);
		}
		return null;
	}

	/**
	 * Filter controls above the table: Delivery Hub, Delivery Person, Wallet
	 * balance and Subscription status. All combine with the search box (AND).
	 *
	 * @param string $which Table nav position ('top' or 'bottom').
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$slot       = $this->filter_int( 'aaraa_slot' );
		$hub        = $this->filter_int( 'aaraa_hub' );
		$boy        = $this->filter_int( 'aaraa_boy' );
		$wallet_max = isset( $_REQUEST['aaraa_wallet_max'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['aaraa_wallet_max'] ) ) : '';
		$sub_status = isset( $_REQUEST['aaraa_sub_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['aaraa_sub_status'] ) ) : '';
		// phpcs:enable

		$slots    = Delivery_Admin::slots_list();
		$hubs     = Delivery_Admin::hubs_list();
		$people   = Delivery_Admin::all_delivery_people( 0 );
		$symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '';
		$statuses = function_exists( 'wcs_get_subscription_statuses' ) ? wcs_get_subscription_statuses() : array();
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="aaraa_slot"><?php esc_html_e( 'Filter by delivery slot', 'aaraa-white-label-admin' ); ?></label>
			<select name="aaraa_slot" id="aaraa_slot">
				<option value="0"><?php esc_html_e( 'All delivery slots', 'aaraa-white-label-admin' ); ?></option>
				<option value="-1" <?php selected( $slot, -1 ); ?>><?php esc_html_e( 'Not assigned', 'aaraa-white-label-admin' ); ?></option>
				<?php
				foreach ( $slots as $s ) :
					$slabel = $s->name;
					if ( $s->start_time || $s->end_time ) {
						$slabel .= ' (' . $s->start_time . '–' . $s->end_time . ')';
					}
					?>
					<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $slot, (int) $s->id ); ?>><?php echo esc_html( $slabel ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="aaraa_hub"><?php esc_html_e( 'Filter by delivery hub', 'aaraa-white-label-admin' ); ?></label>
			<select name="aaraa_hub" id="aaraa_hub">
				<option value="0"><?php esc_html_e( 'All delivery hubs', 'aaraa-white-label-admin' ); ?></option>
				<option value="-1" <?php selected( $hub, -1 ); ?>><?php esc_html_e( 'Not assigned', 'aaraa-white-label-admin' ); ?></option>
				<?php foreach ( $hubs as $h ) : ?>
					<option value="<?php echo esc_attr( $h->ID ); ?>" <?php selected( $hub, (int) $h->ID ); ?>><?php echo esc_html( $h->name ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="aaraa_boy"><?php esc_html_e( 'Filter by delivery person', 'aaraa-white-label-admin' ); ?></label>
			<select name="aaraa_boy" id="aaraa_boy">
				<option value="0"><?php esc_html_e( 'All delivery persons', 'aaraa-white-label-admin' ); ?></option>
				<option value="-1" <?php selected( $boy, -1 ); ?>><?php esc_html_e( 'Not assigned', 'aaraa-white-label-admin' ); ?></option>
				<?php foreach ( $people as $pid => $label ) : ?>
					<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $boy, (int) $pid ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="aaraa_wallet_max"><?php esc_html_e( 'Filter by wallet balance', 'aaraa-white-label-admin' ); ?></label>
			<select name="aaraa_wallet_max" id="aaraa_wallet_max">
				<option value=""><?php esc_html_e( 'Any wallet balance', 'aaraa-white-label-admin' ); ?></option>
				<?php foreach ( self::wallet_thresholds() as $threshold ) : ?>
					<option value="<?php echo esc_attr( $threshold ); ?>" <?php selected( $wallet_max, (string) $threshold ); ?>>
						<?php
						/* translators: 1: currency symbol, 2: amount */
						echo esc_html( sprintf( __( 'Wallet < %1$s%2$s', 'aaraa-white-label-admin' ), $symbol, number_format_i18n( $threshold ) ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php if ( $statuses ) : ?>
				<label class="screen-reader-text" for="aaraa_sub_status"><?php esc_html_e( 'Filter by subscription status', 'aaraa-white-label-admin' ); ?></label>
				<select name="aaraa_sub_status" id="aaraa_sub_status">
					<option value=""><?php esc_html_e( 'Any subscription status', 'aaraa-white-label-admin' ); ?></option>
					<?php foreach ( $statuses as $key => $label ) : ?>
						<?php $key = preg_replace( '/^wc-/', '', $key ); ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sub_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<label class="screen-reader-text" for="aaraa_per_page"><?php esc_html_e( 'Rows per page', 'aaraa-white-label-admin' ); ?></label>
			<select name="aaraa_per_page" id="aaraa_per_page" onchange="this.form.submit()">
				<?php foreach ( $this->per_page_choices() as $n ) : ?>
					<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $this->per_page(), $n ); ?>>
						<?php /* translators: %d: rows per page */ printf( esc_html__( '%d / page', 'aaraa-white-label-admin' ), $n ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'aaraa-white-label-admin' ), '', 'aaraa_filter', false ); ?>

			<?php if ( $slot || $hub || $boy || '' !== $wallet_max || '' !== $sub_status ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Customers_Admin::PAGE ) ); ?>"><?php esc_html_e( 'Clear', 'aaraa-white-label-admin' ); ?></a>
			<?php endif; ?>

			<a class="button button-primary" href="<?php echo esc_url( Exports::export_url( 'customers' ) ); ?>"><?php esc_html_e( 'Export CSV', 'aaraa-white-label-admin' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param \WP_User $item        User row.
	 * @param string   $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return (string) $item->ID;
			case 'email':
				return esc_html( $item->user_email );
			default:
				return '';
		}
	}

	/**
	 * Name column with the customer's display name.
	 *
	 * @param \WP_User $item User row.
	 * @return string
	 */
	public function column_name( $item ) {
		$name = trim( (string) $item->display_name );
		if ( '' === $name ) {
			$name = trim( get_user_meta( $item->ID, 'first_name', true ) . ' ' . get_user_meta( $item->ID, 'last_name', true ) );
		}
		if ( '' === $name ) {
			$name = $item->user_login;
		}
		return esc_html( $name );
	}

	/**
	 * Mobile column (billing_phone, falling back to the mobile meta).
	 *
	 * @param \WP_User $item User row.
	 * @return string
	 */
	public function column_mobile( $item ) {
		$mobile = get_user_meta( $item->ID, 'billing_phone', true );
		if ( '' === $mobile ) {
			$mobile = get_user_meta( $item->ID, 'mobile', true );
		}
		return $mobile ? esc_html( $mobile ) : '&mdash;';
	}

	/**
	 * Wallet column. Reads wps_wallet (the wallet plugin's key), then _wps_amount.
	 *
	 * @param \WP_User $item User row.
	 * @return string
	 */
	public function column_wallet( $item ) {
		$balance = Customers_Admin::get_wallet_balance( $item->ID );
		return function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $balance ) ) : esc_html( number_format_i18n( $balance, 2 ) );
	}

	/**
	 * Actions column: View / Edit / Delete.
	 *
	 * @param \WP_User $item User row.
	 * @return string
	 */
	public function column_actions( $item ) {
		$base = admin_url( 'admin.php?page=aaraa-customers' );

		$view = add_query_arg(
			array( 'action' => 'view', 'customer' => $item->ID ),
			$base
		);
		$edit = add_query_arg(
			array( 'action' => 'edit', 'customer' => $item->ID ),
			$base
		);
		$delete = wp_nonce_url(
			add_query_arg( array( 'action' => 'delete', 'customer' => $item->ID ), $base ),
			'aaraa_delete_customer_' . $item->ID
		);

		$links   = array();
		$links[] = sprintf( '<a class="button button-small" href="%s">%s</a>', esc_url( $view ), esc_html__( 'View', 'aaraa-white-label-admin' ) );

		if ( Customers_Admin::current_user_can_manage() ) {
			$links[] = sprintf( '<a class="button button-small" href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'aaraa-white-label-admin' ) );
			$links[] = sprintf(
				'<a class="button button-small button-link-delete" href="%s" onclick="return confirm(%s);">%s</a>',
				esc_url( $delete ),
				esc_js( __( 'Delete this customer permanently?', 'aaraa-white-label-admin' ) ),
				esc_html__( 'Delete', 'aaraa-white-label-admin' )
			);
		}

		return implode( ' ', $links );
	}

	/**
	 * Message shown when there are no customers.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No customers found.', 'aaraa-white-label-admin' );
	}
}
