<?php
/**
 * "Aaraa Customer 360" controller: list / view / edit / delete.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the customers admin page and its actions.
 */
class Customers_Admin {

	const PAGE = 'aaraa-customers';

	/**
	 * Capability required to edit/delete a customer.
	 *
	 * shop_manager has manage_woocommerce; administrators have edit_users.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( 'edit_users' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Read a customer's wallet balance. Prefers the wallet plugin key (wps_wallet),
	 * falls back to _wps_amount.
	 *
	 * @param int $user_id User ID.
	 * @return float
	 */
	public static function get_wallet_balance( $user_id ) {
		$balance = get_user_meta( $user_id, 'wps_wallet', true );
		if ( '' === $balance || null === $balance ) {
			$balance = get_user_meta( $user_id, '_wps_amount', true );
		}
		return (float) $balance;
	}

	/**
	 * Write a customer's wallet balance.
	 *
	 * Both meta keys are written and kept in sync: `wps_wallet` is what the
	 * wallet plugin (and its pro add-on) reads, while `_wps_amount` is the key
	 * this site's own storefront/app reads. update_user_meta() creates either
	 * key if it does not exist yet, so a customer with no wallet gets one.
	 *
	 * @param int   $user_id User ID.
	 * @param float $balance New balance.
	 * @return float The balance written.
	 */
	public static function set_wallet_balance( $user_id, $balance ) {
		$balance = (float) $balance;
		update_user_meta( (int) $user_id, 'wps_wallet', $balance );
		update_user_meta( (int) $user_id, '_wps_amount', $balance );
		return $balance;
	}

	/* --------------------------------------------------------------------- *
	 * Action processing (runs on admin_init, before output → can redirect).
	 * --------------------------------------------------------------------- */

	/**
	 * Dispatch delete / save actions for the customers page.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_REQUEST['page'] ) || self::PAGE !== $_REQUEST['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

		if ( 'delete' === $action ) {
			$this->process_delete();
		} elseif ( 'create' === $action && $is_post ) {
			$this->process_create();
		} elseif ( 'save' === $action && $is_post ) {
			$this->process_save();
		} elseif ( 'wallet' === $action && $is_post ) {
			$this->process_wallet();
		}
	}

	/**
	 * Credit or debit a customer's wallet, recording it in the wallet plugin's
	 * transaction table so it stays consistent with the plugin's own views.
	 *
	 * @return void
	 */
	private function process_wallet() {
		$id = isset( $_POST['customer'] ) ? absint( $_POST['customer'] ) : 0;
		check_admin_referer( 'aaraa_wallet_' . $id );

		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to adjust wallets.', 'aaraa-white-label-admin' ) );
		}

		$user = get_userdata( $id );
		$type = isset( $_POST['wtx_type'] ) ? sanitize_key( wp_unslash( $_POST['wtx_type'] ) ) : '';
		$amt  = isset( $_POST['wtx_amount'] ) ? round( (float) wp_unslash( $_POST['wtx_amount'] ), wc_get_price_decimals() ) : 0.0;
		$note = isset( $_POST['wtx_note'] ) ? sanitize_text_field( wp_unslash( $_POST['wtx_note'] ) ) : '';

		if ( ! $user || ! in_array( $type, array( 'credit', 'debit' ), true ) || $amt <= 0 ) {
			$this->redirect( array( 'action' => 'view', 'customer' => $id, 'error' => 'wallet' ) );
		}

		$current = self::get_wallet_balance( $id );
		if ( 'credit' === $type ) {
			$balance = $current + $amt;
		} else {
			// Match the wallet plugin: never go negative.
			$balance = ( $current < $amt ) ? 0 : $current - $amt;
		}
		$balance = round( $balance, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
		self::set_wallet_balance( $id, $balance );

		$this->record_transaction(
			$id,
			$amt,
			$type,
			'credit' === $type
				? __( 'Credited manually by admin', 'aaraa-white-label-admin' )
				: __( 'Debited manually by admin', 'aaraa-white-label-admin' ),
			$note
		);

		$formatted = function_exists( 'wc_price' ) ? html_entity_decode( wp_strip_all_tags( wc_price( $amt ) ) ) : (string) $amt;
		Customer_Logs::add(
			$id,
			'system',
			'wallet_' . $type,
			sprintf(
				/* translators: 1: Credit/Debit, 2: amount */
				__( 'Wallet %1$s: %2$s', 'aaraa-white-label-admin' ),
				'credit' === $type ? __( 'Credit', 'aaraa-white-label-admin' ) : __( 'Debit', 'aaraa-white-label-admin' ),
				$formatted
			),
			array( 'meta' => array( 'note' => $note ) )
		);

		$this->redirect( array( 'action' => 'view', 'customer' => $id, 'wallet_updated' => 1 ) );
	}

	/**
	 * Insert a row into the wallet plugin's transaction table.
	 *
	 * @param int    $user_id     Customer ID.
	 * @param float  $amount      Positive amount.
	 * @param string $type        'credit' or 'debit'.
	 * @param string $description Human description (transaction_type column).
	 * @param string $note        Optional note.
	 * @return void
	 */
	private function record_transaction( $user_id, $amount, $type, $description, $note = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
		if ( ! self::wallet_table_exists() ) {
			return;
		}
		$data = array(
			'user_id'            => (int) $user_id,
			'amount'             => (float) $amount,
			'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'transaction_type'   => $description,
			'transaction_type_1' => $type,
			'payment_method'     => __( 'Manually By Admin', 'aaraa-white-label-admin' ),
			'transaction_id'     => '',
			'note'               => $note,
			'date'               => gmdate( 'Y-m-d H:i:s' ),
		);
		$format = array( '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		// Only reference created_by when the column actually exists.
		if ( self::has_created_by_column() ) {
			$data['created_by'] = get_current_user_id();
			$format[]           = '%d';
		}

		$wpdb->insert( $table, $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Add a nullable created_by column to the wallet transaction table if missing.
	 * Purely additive — the wallet plugin's SELECT * keeps working.
	 *
	 * @return void
	 */
	public static function ensure_wallet_created_by() {
		self::has_created_by_column();
	}

	/**
	 * Whether the transaction table really has a `created_by` column, adding it
	 * if possible.
	 *
	 * The result is verified after the ALTER rather than assumed: on hosts where
	 * the DB user has no ALTER privilege the column will not appear, and inserts
	 * that reference it would fail with "Unknown column 'created_by'". Callers
	 * must omit the column when this returns false.
	 *
	 * @return bool
	 */
	public static function has_created_by_column() {
		global $wpdb;
		static $has = null;
		if ( null !== $has ) {
			return $has;
		}
		if ( ! self::wallet_table_exists() ) {
			$has = false;
			return $has;
		}
		$table = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
		$col   = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'created_by' ) ); // phpcs:ignore WordPress.DB
		if ( ! $col ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN created_by BIGINT(20) UNSIGNED NULL" ); // phpcs:ignore WordPress.DB
			// Re-check: the ALTER may have been denied.
			$col = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'created_by' ) ); // phpcs:ignore WordPress.DB
		}
		$has = (bool) $col;
		return $has;
	}

	/**
	 * Resolve who created a wallet transaction. Uses the created_by actor when
	 * present; otherwise infers from the payment method for pre-existing rows.
	 *
	 * @param object $row Transaction row.
	 * @return string
	 */
	private function transaction_creator( $row ) {
		if ( ! empty( $row->created_by ) ) {
			$user = get_userdata( (int) $row->created_by );
			return $user ? $user->display_name : '#' . (int) $row->created_by;
		}
		$method = strtolower( (string) $row->payment_method );
		if ( false !== strpos( $method, 'admin' ) || false !== strpos( $method, 'manual' ) ) {
			return __( 'Admin', 'aaraa-white-label-admin' );
		}
		return __( 'System', 'aaraa-white-label-admin' );
	}

	/**
	 * Whether the wallet transaction table exists.
	 *
	 * @return bool
	 */
	public static function wallet_table_exists() {
		global $wpdb;
		$table  = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
		$exists = wp_cache_get( 'aaraa_wtx_table' );
		if ( false === $exists ) {
			$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB
			$exists = ( $found === $table ) ? 'yes' : 'no';
			wp_cache_set( 'aaraa_wtx_table', $exists, '', HOUR_IN_SECONDS );
		}
		return 'yes' === $exists;
	}

	/**
	 * Fetch a page of a customer's wallet transactions.
	 *
	 * @param int $user_id  Customer ID.
	 * @param int $paged    Page number.
	 * @param int $per_page Rows per page.
	 * @return array{0:array,1:int,2:int} rows, total, total_pages.
	 */
	private function get_wallet_transactions( $user_id, $paged, $per_page ) {
		global $wpdb;
		if ( ! self::wallet_table_exists() ) {
			return array( array(), 0, 1 );
		}
		$table  = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB
		$pages  = (int) max( 1, ceil( $total / $per_page ) );
		$paged  = min( max( 1, $paged ), $pages );
		$offset = ( $paged - 1 ) * $per_page;
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset )
		);
		return array( (array) $rows, $total, $pages );
	}

	/**
	 * Delete a customer (customer-only users, guarded by nonce + capability).
	 *
	 * @return void
	 */
	private function process_delete() {
		$id = isset( $_GET['customer'] ) ? absint( $_GET['customer'] ) : 0;
		check_admin_referer( 'aaraa_delete_customer_' . $id );

		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to delete customers.', 'aaraa-white-label-admin' ) );
		}

		if ( $id && $this->is_deletable_customer( $id ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $id );
			$this->redirect( array( 'deleted' => 1 ) );
		}
		$this->redirect( array( 'error' => 'delete' ) );
	}

	/**
	 * Create a customer.
	 *
	 * Mobile is the required field, not email: this store identifies customers by
	 * phone (the theme's OTP login looks up billing_phone), so a customer with a
	 * mobile and no email is normal, and a duplicate mobile is a real conflict.
	 *
	 * @return void
	 */
	private function process_create() {
		check_admin_referer( 'aaraa_create_customer' );

		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to add customers.', 'aaraa-white-label-admin' ) );
		}

		$first  = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last   = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email  = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$mobile = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
		$wallet = isset( $_POST['wallet'] ) ? (float) wp_unslash( $_POST['wallet'] ) : 0.0;

		$mobile = preg_replace( '/[^0-9+]/', '', $mobile );

		// Hand the typed values back so a rejected form is not retyped.
		$back = array(
			'action'        => 'new',
			'first_name'    => $first,
			'last_name'     => $last,
			'user_email'    => $email,
			'billing_phone' => $mobile,
		);

		if ( '' === $mobile ) {
			$this->redirect( array_merge( $back, array( 'error' => 'nomobile' ) ) );
		}
		if ( $email && ! is_email( $email ) ) {
			$this->redirect( array_merge( $back, array( 'error' => 'bademail' ) ) );
		}
		if ( $email && email_exists( $email ) ) {
			$this->redirect( array_merge( $back, array( 'error' => 'dupemail' ) ) );
		}

		$existing = $this->find_user_by_mobile( $mobile );
		if ( $existing ) {
			$this->redirect( array_merge( $back, array( 'error' => 'dupmobile', 'existing' => $existing ) ) );
		}

		$login = $this->unique_login( $mobile, $email );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 20, true ),
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $login,
				'role'         => 'customer',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->redirect( array( 'action' => 'new', 'error' => 'create' ) );
		}

		update_user_meta( $user_id, 'billing_first_name', $first );
		update_user_meta( $user_id, 'billing_last_name', $last );
		update_user_meta( $user_id, 'billing_phone', $mobile );
		update_user_meta( $user_id, 'mobile', $mobile );
		if ( $email ) {
			update_user_meta( $user_id, 'billing_email', $email );
		}

		$delivery = Customer_Delivery::posted();
		if ( ! Customer_Delivery::combination_error( $delivery['hub'], $delivery['boy'] ) ) {
			Customer_Delivery::save_values( $user_id, $delivery );
		}

		if ( $wallet ) {
			self::set_wallet_balance( $user_id, $wallet );
			$this->record_transaction(
				$user_id,
				$wallet,
				'credit',
				__( 'Opening balance', 'aaraa-white-label-admin' )
			);
		}

		Customer_Logs::add( $user_id, 'system', 'profile_created', __( 'Customer created', 'aaraa-white-label-admin' ) );

		$this->redirect(
			array(
				'action'   => 'view',
				'customer' => $user_id,
				'created'  => 1,
			)
		);
	}

	/**
	 * Find an existing user by mobile number.
	 *
	 * Checks both keys the site writes, so a customer created through checkout
	 * is still matched.
	 *
	 * @param string $mobile Mobile number.
	 * @return int User id, 0 when none.
	 */
	private function find_user_by_mobile( $mobile ) {
		global $wpdb;

		$id = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ( 'billing_phone', 'mobile' ) AND meta_value = %s LIMIT 1",
				$mobile
			)
		);

		return (int) $id;
	}

	/**
	 * Build a free username from the mobile, falling back to the email.
	 *
	 * @param string $mobile Mobile number.
	 * @param string $email  Email address.
	 * @return string
	 */
	private function unique_login( $mobile, $email ) {
		$base = sanitize_user( $mobile, true );
		if ( '' === $base && $email ) {
			$base = sanitize_user( current( explode( '@', $email ) ), true );
		}
		if ( '' === $base ) {
			$base = 'customer';
		}

		$login = $base;
		$n     = 1;
		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . '-' . $n;
		}

		return $login;
	}

	/**
	 * Persist edits to a customer.
	 *
	 * @return void
	 */
	private function process_save() {
		$id = isset( $_POST['customer'] ) ? absint( $_POST['customer'] ) : 0;
		check_admin_referer( 'aaraa_save_customer_' . $id );

		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to edit customers.', 'aaraa-white-label-admin' ) );
		}

		$user = get_userdata( $id );
		if ( ! $user || ! array_intersect( array( 'customer', 'subscriber' ), (array) $user->roles ) ) {
			$this->redirect( array( 'error' => 'save' ) );
		}

		$first  = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last   = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email  = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$mobile = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
		$wallet = isset( $_POST['wallet'] ) ? (float) wp_unslash( $_POST['wallet'] ) : 0.0;

		if ( $email && is_email( $email ) ) {
			wp_update_user(
				array(
					'ID'         => $id,
					'user_email' => $email,
					'first_name' => $first,
					'last_name'  => $last,
				)
			);
		} else {
			wp_update_user(
				array(
					'ID'         => $id,
					'first_name' => $first,
					'last_name'  => $last,
				)
			);
		}

		update_user_meta( $id, 'first_name', $first );
		update_user_meta( $id, 'last_name', $last );
		update_user_meta( $id, 'billing_phone', $mobile );
		update_user_meta( $id, 'mobile', $mobile );
		// Keeps wps_wallet and _wps_amount in sync.
		self::set_wallet_balance( $id, $wallet );

		$delivery = Customer_Delivery::posted();
		$mismatch = Customer_Delivery::combination_error( $delivery['hub'], $delivery['boy'] );
		if ( $mismatch ) {
			// Everything else is already saved; report the one field that was not.
			$this->redirect(
				array(
					'action'   => 'edit',
					'customer' => $id,
					'error'    => 'delivery',
				)
			);
		}
		Customer_Delivery::save_values( $id, $delivery );

		Customer_Logs::add( $id, 'system', 'profile_updated', __( 'Customer profile updated', 'aaraa-white-label-admin' ) );

		$this->redirect( array( 'updated' => 1 ) );
	}

	/* --------------------------------------------------------------------- *
	 * Rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Route the page to list / view / edit.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$id     = isset( $_GET['customer'] ) ? absint( $_GET['customer'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'view' === $action && $id ) {
			$this->render_view( $id );
			return;
		}
		if ( 'edit' === $action && $id ) {
			$this->render_edit( $id );
			return;
		}
		if ( 'new' === $action ) {
			$this->render_new();
			return;
		}
		$this->render_list();
	}

	/**
	 * The customer list screen.
	 *
	 * @return void
	 */
	private function render_list() {
		$table = new Customers_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Aaraa Customer 360', 'aaraa-white-label-admin' ); ?></h1>
			<?php if ( self::current_user_can_manage() ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New Customer', 'aaraa-white-label-admin' ); ?>
				</a>
			<?php endif; ?>
			<hr class="wp-header-end" />

			<?php $this->notices(); ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search customer / order / subscription ID, name, email or mobile', 'aaraa-white-label-admin' ), 'aaraa-customer' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The add-customer form.
	 *
	 * @return void
	 */
	private function render_new() {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to add customers.', 'aaraa-white-label-admin' ) );
		}

		// Keep what was typed when validation bounced the submission back.
		// phpcs:disable WordPress.Security.NonceVerification
		$first  = isset( $_GET['first_name'] ) ? sanitize_text_field( wp_unslash( $_GET['first_name'] ) ) : '';
		$last   = isset( $_GET['last_name'] ) ? sanitize_text_field( wp_unslash( $_GET['last_name'] ) ) : '';
		$email  = isset( $_GET['user_email'] ) ? sanitize_email( wp_unslash( $_GET['user_email'] ) ) : '';
		$mobile = isset( $_GET['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_GET['billing_phone'] ) ) : '';
		// phpcs:enable
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Add New Customer', 'aaraa-white-label-admin' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>" class="page-title-action"><?php esc_html_e( '&larr; Back to list', 'aaraa-white-label-admin' ); ?></a>
			<hr class="wp-header-end" />

			<?php $this->notices(); ?>

			<form method="post" action="<?php echo esc_url( add_query_arg( array( 'action' => 'create' ), admin_url( 'admin.php?page=' . self::PAGE ) ) ); ?>">
				<?php wp_nonce_field( 'aaraa_create_customer' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="first_name"><?php esc_html_e( 'First name', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="first_name" id="first_name" type="text" class="regular-text" value="<?php echo esc_attr( $first ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="last_name"><?php esc_html_e( 'Last name', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="last_name" id="last_name" type="text" class="regular-text" value="<?php echo esc_attr( $last ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="billing_phone"><?php esc_html_e( 'Mobile', 'aaraa-white-label-admin' ); ?> <span style="color:#b32d2e">*</span></label></th>
						<td>
							<input name="billing_phone" id="billing_phone" type="text" class="regular-text" value="<?php echo esc_attr( $mobile ); ?>" required />
							<p class="description"><?php esc_html_e( 'Used as the login and to match the customer at checkout.', 'aaraa-white-label-admin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="user_email"><?php esc_html_e( 'Email', 'aaraa-white-label-admin' ); ?></label></th>
						<td>
							<input name="user_email" id="user_email" type="email" class="regular-text" value="<?php echo esc_attr( $email ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Leave blank for a mobile-only customer.', 'aaraa-white-label-admin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wallet"><?php esc_html_e( 'Opening wallet balance', 'aaraa-white-label-admin' ); ?></label></th>
						<td>
							<input name="wallet" id="wallet" type="number" step="0.01" class="regular-text" value="0" />
							<p class="description"><?php esc_html_e( 'Optional. Recorded as a wallet credit so it appears in the transaction history.', 'aaraa-white-label-admin' ); ?></p>
						</td>
					</tr>
					<?php Customer_Delivery::fields( 0 ); ?>
				</table>
				<?php submit_button( __( 'Create Customer', 'aaraa-white-label-admin' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Read-only customer detail view.
	 *
	 * @param int $id Customer ID.
	 * @return void
	 */
	private function render_view( $id ) {
		$user = get_userdata( $id );
		if ( ! $user ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Customer not found', 'aaraa-white-label-admin' ) . '</h1></div>';
			return;
		}

		$name   = trim( $user->first_name . ' ' . $user->last_name );
		$name   = '' !== $name ? $name : $user->display_name;
		$mobile = get_user_meta( $id, 'billing_phone', true );
		$mobile = '' !== $mobile ? $mobile : get_user_meta( $id, 'mobile', true );
		$wallet = self::get_wallet_balance( $id );

		$address = array_filter(
			array(
				get_user_meta( $id, 'billing_address_1', true ),
				get_user_meta( $id, 'billing_address_2', true ),
				get_user_meta( $id, 'billing_city', true ),
				get_user_meta( $id, 'billing_postcode', true ),
			)
		);

		$per_page = 10;

		// Subscriptions: fetch all for this customer (small set), count, then page in memory.
		$all_subs = function_exists( 'wcs_get_users_subscriptions' ) ? array_values( (array) wcs_get_users_subscriptions( $id ) ) : array();
		$active_subs = 0;
		foreach ( $all_subs as $sub ) {
			if ( is_object( $sub ) && $sub->has_status( 'active' ) ) {
				$active_subs++;
			}
		}
		$total_subs = count( $all_subs );
		$sub_pages  = (int) max( 1, (int) ceil( $total_subs / $per_page ) );
		$sub_paged  = isset( $_GET['sub_paged'] ) ? absint( wp_unslash( $_GET['sub_paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$sub_paged  = min( max( 1, $sub_paged ), $sub_pages );
		$subs       = array_slice( $all_subs, ( $sub_paged - 1 ) * $per_page, $per_page );

		// Orders: paginated at the query level (HPOS-safe).
		$order_paged  = isset( $_GET['order_paged'] ) ? max( 1, absint( wp_unslash( $_GET['order_paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$orders       = array();
		$total_orders = 0;
		$order_pages  = 1;
		if ( function_exists( 'wc_get_orders' ) ) {
			$order_query = wc_get_orders(
				array(
					'customer_id' => $id,
					'limit'       => $per_page,
					'paged'       => $order_paged,
					'orderby'     => 'date',
					'order'       => 'DESC',
					'paginate'    => true,
				)
			);
			if ( is_object( $order_query ) ) {
				$orders       = $order_query->orders;
				$total_orders = (int) $order_query->total;
				$order_pages  = (int) $order_query->max_num_pages;
			}
		}

		$revenue = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $id ) : 0.0;

		// Wallet transactions.
		$wtx_paged = isset( $_GET['wtx_paged'] ) ? max( 1, absint( wp_unslash( $_GET['wtx_paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		list( $wtx_rows, $wtx_total, $wtx_pages ) = $this->get_wallet_transactions( $id, $wtx_paged, $per_page );

		$list_url = admin_url( 'admin.php?page=' . self::PAGE );
		$edit_url = add_query_arg( array( 'action' => 'edit', 'customer' => $id ), $list_url );
		$money    = static function ( $value ) {
			return function_exists( 'wc_price' ) ? wc_price( $value ) : esc_html( number_format_i18n( (float) $value, 2 ) );
		};
		?>
		<div class="wrap">
			<div class="aac">

				<?php if ( isset( $_GET['wallet_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Wallet updated.', 'aaraa-white-label-admin' ); ?></p></div>
				<?php elseif ( isset( $_GET['error'] ) && 'wallet' === $_GET['error'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Enter a valid amount and choose Credit or Debit.', 'aaraa-white-label-admin' ); ?></p></div>
				<?php endif; ?>

				<?php /* Section 1 — Customer details */ ?>
				<div class="aac-id">
					<div class="aac-avatar"><?php echo esc_html( $this->initials( $name ) ); ?></div>
					<div class="aac-id__body">
						<h1 class="aac-id__name"><?php echo esc_html( $name ); ?></h1>
						<div class="aac-id__meta">
							<span class="dashicons dashicons-email-alt"></span><span><?php echo esc_html( $user->user_email ); ?></span>
							<span class="dashicons dashicons-phone"></span><span><?php echo $mobile ? esc_html( $mobile ) : '&mdash;'; ?></span>
							<span class="dashicons dashicons-admin-users"></span><span>#<?php echo (int) $id; ?></span>
						</div>
					</div>
					<div class="aac-id__actions">
						<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Back', 'aaraa-white-label-admin' ); ?></a>
						<?php if ( self::current_user_can_manage() ) : ?>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary"><?php esc_html_e( 'Edit', 'aaraa-white-label-admin' ); ?></a>
						<?php endif; ?>
					</div>
				</div>

				<dl class="aac-details">
					<div><dt><?php esc_html_e( 'Username', 'aaraa-white-label-admin' ); ?></dt><dd><?php echo esc_html( $user->user_login ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Address', 'aaraa-white-label-admin' ); ?></dt><dd><?php echo $address ? esc_html( implode( ', ', $address ) ) : '&mdash;'; ?></dd></div>
					<div><dt><?php esc_html_e( 'Registered', 'aaraa-white-label-admin' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></dd></div>
				</dl>

				<?php /* Section 2 — Stat cards */ ?>
				<div class="aac-stats">
					<div class="aac-card aac-card--accent">
						<span class="aac-card__icon dashicons dashicons-money-alt"></span>
						<span class="aac-card__value"><?php echo wp_kses_post( $money( $wallet ) ); ?></span>
						<span class="aac-card__label"><?php esc_html_e( 'Wallet Amount', 'aaraa-white-label-admin' ); ?></span>
					</div>
					<div class="aac-card">
						<span class="aac-card__icon dashicons dashicons-update"></span>
						<span class="aac-card__value"><?php echo esc_html( number_format_i18n( $active_subs ) ); ?></span>
						<span class="aac-card__label"><?php esc_html_e( 'Total Active Subscriptions', 'aaraa-white-label-admin' ); ?></span>
					</div>
					<div class="aac-card">
						<span class="aac-card__icon dashicons dashicons-cart"></span>
						<span class="aac-card__value"><?php echo esc_html( number_format_i18n( $total_orders ) ); ?></span>
						<span class="aac-card__label"><?php esc_html_e( 'Total Orders', 'aaraa-white-label-admin' ); ?></span>
					</div>
					<div class="aac-card aac-card--accent">
						<span class="aac-card__icon dashicons dashicons-chart-bar"></span>
						<span class="aac-card__value"><?php echo wp_kses_post( $money( $revenue ) ); ?></span>
						<span class="aac-card__label"><?php esc_html_e( 'Total Revenue', 'aaraa-white-label-admin' ); ?></span>
					</div>
				</div>

				<?php /* Section 3 — Subscriptions */ ?>
				<section class="aac-section">
					<header class="aac-section__head">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Subscriptions', 'aaraa-white-label-admin' ); ?>
						<span class="aac-badge"><?php echo esc_html( number_format_i18n( $total_subs ) ); ?></span>
					</header>
					<div class="aac-tablewrap">
						<?php if ( $subs ) : ?>
							<table class="aac-table">
								<thead><tr>
									<th><?php esc_html_e( 'ID', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Product', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Next payment', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Pause / Resume', 'aaraa-white-label-admin' ); ?></th>
									<th class="aac-num"><?php esc_html_e( 'Total', 'aaraa-white-label-admin' ); ?></th>
								</tr></thead>
								<tbody>
								<?php
								foreach ( $subs as $sub ) :
									if ( ! is_object( $sub ) ) {
										continue;
									}
									$sid  = $sub->get_id();
									$next = $sub->get_date_to_display( 'next_payment' );
									?>
									<tr>
										<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $sid . '&action=edit' ) ); ?>">#<?php echo (int) $sid; ?></a></td>
										<td><?php echo esc_html( $this->items_summary( $sub ) ); ?></td>
										<td><?php echo $this->status_pill( $sub->get_status() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
										<td><?php echo $next ? esc_html( $next ) : '&mdash;'; ?></td>
										<td><?php echo $this->pause_summary( $sub ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
										<td class="aac-num"><?php echo wp_kses_post( $sub->get_formatted_order_total() ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="aac-empty"><?php esc_html_e( 'No subscriptions yet.', 'aaraa-white-label-admin' ); ?></p>
						<?php endif; ?>
					</div>
					<?php $this->pager( 'sub_paged', $sub_paged, $sub_pages ); ?>
				</section>

				<?php /* Section 4 — Orders */ ?>
				<section class="aac-section">
					<header class="aac-section__head">
						<span class="dashicons dashicons-cart"></span>
						<?php esc_html_e( 'Orders', 'aaraa-white-label-admin' ); ?>
						<span class="aac-badge"><?php echo esc_html( number_format_i18n( $total_orders ) ); ?></span>
					</header>
					<div class="aac-tablewrap">
						<?php if ( $orders ) : ?>
							<table class="aac-table">
								<thead><tr>
									<th><?php esc_html_e( 'Order', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Product', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Type', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Date', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
									<th class="aac-num"><?php esc_html_e( 'Total', 'aaraa-white-label-admin' ); ?></th>
								</tr></thead>
								<tbody>
								<?php
								foreach ( $orders as $order ) :
									if ( ! is_object( $order ) ) {
										continue;
									}
									$created = $order->get_date_created();
									?>
									<tr>
										<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
										<td><?php echo esc_html( $this->items_summary( $order ) ); ?></td>
										<td><span class="aac-type"><?php echo esc_html( $this->order_type_label( $order ) ); ?></span></td>
										<td><?php echo $created ? esc_html( wc_format_datetime( $created ) ) : '&mdash;'; ?></td>
										<td><?php echo $this->status_pill( $order->get_status() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
										<td class="aac-num"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="aac-empty"><?php esc_html_e( 'No orders yet.', 'aaraa-white-label-admin' ); ?></p>
						<?php endif; ?>
					</div>
					<?php $this->pager( 'order_paged', $order_paged, $order_pages ); ?>
				</section>

				<?php /* Section 5 — Wallet transactions */ ?>
				<section class="aac-section">
					<header class="aac-section__head">
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Wallet Transactions', 'aaraa-white-label-admin' ); ?>
						<span class="aac-badge"><?php echo esc_html( number_format_i18n( $wtx_total ) ); ?></span>

						<?php if ( self::current_user_can_manage() && self::wallet_table_exists() ) : ?>
							<form method="post" class="aac-wallet-form" action="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'action' => 'wallet' ), admin_url( 'admin.php' ) ) ); ?>">
								<?php wp_nonce_field( 'aaraa_wallet_' . $id ); ?>
								<input type="hidden" name="customer" value="<?php echo (int) $id; ?>" />
								<select name="wtx_type" aria-label="<?php esc_attr_e( 'Transaction type', 'aaraa-white-label-admin' ); ?>">
									<option value="credit"><?php esc_html_e( 'Credit', 'aaraa-white-label-admin' ); ?></option>
									<option value="debit"><?php esc_html_e( 'Debit', 'aaraa-white-label-admin' ); ?></option>
								</select>
								<input type="number" name="wtx_amount" step="0.01" min="0.01" required placeholder="<?php esc_attr_e( 'Amount', 'aaraa-white-label-admin' ); ?>" />
								<input type="text" name="wtx_note" placeholder="<?php esc_attr_e( 'Note (optional)', 'aaraa-white-label-admin' ); ?>" />
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'aaraa-white-label-admin' ); ?></button>
							</form>
						<?php endif; ?>
					</header>
					<div class="aac-tablewrap">
						<?php if ( ! self::wallet_table_exists() ) : ?>
							<p class="aac-empty"><?php esc_html_e( 'Wallet system is not active.', 'aaraa-white-label-admin' ); ?></p>
						<?php elseif ( $wtx_rows ) : ?>
							<table class="aac-table">
								<thead><tr>
									<th><?php esc_html_e( 'Date', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Description', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Method', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Created by', 'aaraa-white-label-admin' ); ?></th>
									<th><?php esc_html_e( 'Type', 'aaraa-white-label-admin' ); ?></th>
									<th class="aac-num"><?php esc_html_e( 'Amount', 'aaraa-white-label-admin' ); ?></th>
								</tr></thead>
								<tbody>
								<?php
								foreach ( $wtx_rows as $row ) :
									$is_credit = ( 'credit' === $row->transaction_type_1 );
									$desc      = wp_kses( html_entity_decode( (string) $row->transaction_type ), array( 'a' => array( 'href' => array() ), 'br' => array() ) );
									$amount    = function_exists( 'wc_price' ) ? wc_price( abs( (float) $row->amount ) ) : number_format_i18n( abs( (float) $row->amount ), 2 );
									?>
									<tr>
										<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_date_from_gmt( $row->date ) ) ); ?></td>
										<td><?php echo $desc ? $desc : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $row->note ? ' <span class="aac-note">(' . esc_html( $row->note ) . ')</span>' : ''; ?></td>
										<td><?php echo esc_html( $row->payment_method ); ?></td>
										<td><?php echo esc_html( $this->transaction_creator( $row ) ); ?></td>
										<td><span class="aac-pill <?php echo $is_credit ? 'is-green' : 'is-red'; ?>"><?php echo $is_credit ? esc_html__( 'Credit', 'aaraa-white-label-admin' ) : esc_html__( 'Debit', 'aaraa-white-label-admin' ); ?></span></td>
										<td class="aac-num aac-amt <?php echo $is_credit ? 'is-green' : 'is-red'; ?>">
											<?php echo esc_html( $is_credit ? '+' : '−' ); ?> <?php echo wp_kses_post( $amount ); ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="aac-empty"><?php esc_html_e( 'No wallet transactions yet.', 'aaraa-white-label-admin' ); ?></p>
						<?php endif; ?>
					</div>
					<?php $this->pager( 'wtx_paged', $wtx_paged, $wtx_pages ); ?>
				</section>

				<?php /* Section 6 — Notifications */ ?>
				<?php $this->render_notifications( $id, $per_page ); ?>

			</div>
		</div>
		<?php
	}

	/**
	 * Notification section: tabbed logs (System / WhatsApp / SMS / E-Mail / In-App).
	 *
	 * @param int $id       Customer ID.
	 * @param int $per_page Rows per page.
	 * @return void
	 */
	private function render_notifications( $id, $per_page ) {
		$tabs = Customer_Logs::channels();
		$tab  = isset( $_GET['note_tab'] ) ? sanitize_key( wp_unslash( $_GET['note_tab'] ) ) : 'system'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'system';
		}
		$paged = isset( $_GET['log_paged'] ) ? max( 1, absint( wp_unslash( $_GET['log_paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'system' === $tab ) {
			list( $rows, $total, $pages ) = $this->build_system_timeline( $id, $paged, $per_page );
		} elseif ( in_array( $tab, array( 'sms', 'whatsapp', 'in_app' ), true ) ) {
			// SMS/WhatsApp/In-App sends live in their own global logs (keyed by
			// number or user id), not in Customer_Logs — pull this customer's
			// rows straight from them.
			list( $rows, $total, $pages ) = $this->get_channel_log( $id, $tab, $paged, $per_page );
		} else {
			list( $rows, $total, $pages ) = Customer_Logs::get( $id, $tab, $paged, $per_page );
		}
		?>
		<section class="aac-section">
			<header class="aac-section__head">
				<span class="dashicons dashicons-bell"></span>
				<?php esc_html_e( 'Notifications', 'aaraa-white-label-admin' ); ?>
			</header>

			<nav class="aac-tabs">
				<?php
				foreach ( $tabs as $slug => $label ) :
					$url = remove_query_arg( 'log_paged', add_query_arg( 'note_tab', $slug ) );
					?>
					<a class="aac-tab <?php echo $slug === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="aac-tablewrap">
				<?php if ( 'system' === $tab ) : ?>
					<?php $this->render_timeline( $rows ); ?>
				<?php elseif ( $rows ) : ?>
					<table class="aac-table">
						<thead><tr>
							<th><?php esc_html_e( 'Date', 'aaraa-white-label-admin' ); ?></th>
							<th><?php esc_html_e( 'Event', 'aaraa-white-label-admin' ); ?></th>
							<th><?php esc_html_e( 'Message', 'aaraa-white-label-admin' ); ?></th>
							<th><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
							<th><?php esc_html_e( 'By', 'aaraa-white-label-admin' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_date_from_gmt( $row->created_at ) ) ); ?></td>
								<td><?php echo esc_html( $row->event ); ?></td>
								<td><?php echo wp_kses_post( html_entity_decode( (string) $row->message ) ); ?></td>
								<td><?php echo $this->log_status_pill( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><?php echo esc_html( $this->log_creator( $row ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="aac-empty">
						<?php
						/* translators: %s: channel name */
						printf( esc_html__( 'No %s logs yet.', 'aaraa-white-label-admin' ), esc_html( $tabs[ $tab ] ) );
						?>
					</p>
				<?php endif; ?>
			</div>
			<?php $this->pager( 'log_paged', $paged, $pages ); ?>
		</section>
		<?php
	}

	/**
	 * Render the merged system activity timeline.
	 *
	 * @param array $events Timeline events.
	 * @return void
	 */
	private function render_timeline( $events ) {
		if ( ! $events ) {
			echo '<p class="aac-empty">' . esc_html__( 'No activity yet.', 'aaraa-white-label-admin' ) . '</p>';
			return;
		}
		echo '<ul class="aac-timeline">';
		foreach ( $events as $event ) {
			printf(
				'<li class="aac-tl"><span class="aac-tl__dot dashicons dashicons-%1$s"></span><div class="aac-tl__body"><span class="aac-tl__title">%2$s</span><span class="aac-tl__time">%3$s</span></div>%4$s</li>',
				esc_attr( $event['icon'] ),
				wp_kses_post( $event['title'] ),
				esc_html( $event['time'] ),
				$event['meta'] ? '<span class="aac-tl__meta">' . wp_kses_post( $event['meta'] ) . '</span>' : ''
			);
		}
		echo '</ul>';
	}

	/**
	 * Build a chronological activity feed from real data (registration, orders,
	 * wallet transactions, and recorded system logs), paginated in memory.
	 *
	 * @param int $id       Customer ID.
	 * @param int $paged    Page.
	 * @param int $per_page Rows per page.
	 * @return array{0:array,1:int,2:int} events, total, total_pages.
	 */
	private function build_system_timeline( $id, $paged, $per_page ) {
		$user   = get_userdata( $id );
		$events = array();

		if ( $user ) {
			$events[] = array(
				'ts'    => strtotime( $user->user_registered ),
				'icon'  => 'admin-users',
				'title' => __( 'Account created', 'aaraa-white-label-admin' ),
				'meta'  => '',
			);
		}

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders( array( 'customer_id' => $id, 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
			foreach ( (array) $orders as $order ) {
				if ( ! is_object( $order ) ) {
					continue;
				}
				$created  = $order->get_date_created();
				$events[] = array(
					'ts'    => $created ? $created->getTimestamp() : 0,
					'icon'  => 'cart',
					'title' => sprintf(
						/* translators: 1: order number, 2: status */
						__( 'Order #%1$s — %2$s', 'aaraa-white-label-admin' ),
						$order->get_order_number(),
						function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status()
					),
					'meta'  => $order->get_formatted_order_total(),
				);
			}
		}

		list( $wtx ) = $this->get_wallet_transactions( $id, 1, 50 );
		foreach ( $wtx as $row ) {
			$credit   = ( 'credit' === $row->transaction_type_1 );
			$amount   = function_exists( 'wc_price' ) ? wc_price( abs( (float) $row->amount ) ) : number_format_i18n( abs( (float) $row->amount ), 2 );
			$events[] = array(
				// $row->date is stored in GMT; use its true UTC epoch so wp_date()
				// applies the site offset exactly once (matching the orders row).
				'ts'    => strtotime( $row->date . ' GMT' ),
				'icon'  => 'money-alt',
				'title' => $credit ? __( 'Wallet credited', 'aaraa-white-label-admin' ) : __( 'Wallet debited', 'aaraa-white-label-admin' ),
				'meta'  => ( $credit ? '+ ' : '− ' ) . $amount,
			);
		}

		foreach ( Customer_Logs::recent( $id, 'system', 50 ) as $log ) {
			$events[] = array(
				// created_at is stored in GMT; use its true UTC epoch so wp_date()
				// applies the site offset exactly once.
				'ts'    => strtotime( $log->created_at . ' GMT' ),
				'icon'  => 'info-outline',
				'title' => $log->message,
				'meta'  => '',
			);
		}

		usort(
			$events,
			static function ( $a, $b ) {
				return $b['ts'] <=> $a['ts'];
			}
		);

		$total = count( $events );
		$pages = (int) max( 1, ceil( $total / $per_page ) );
		$paged = min( max( 1, $paged ), $pages );
		$slice = array_slice( $events, ( $paged - 1 ) * $per_page, $per_page );

		// Format the timestamp for display.
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		foreach ( $slice as &$event ) {
			$event['time'] = $event['ts'] ? wp_date( $format, $event['ts'] ) : '';
		}
		unset( $event );

		return array( $slice, $total, $pages );
	}

	/**
	 * Status pill for a channel log row.
	 *
	 * @param string $status Status.
	 * @return string Escaped HTML.
	 */
	private function log_status_pill( $status ) {
		$status = sanitize_key( (string) $status );
		if ( '' === $status ) {
			return '&mdash;';
		}
		$colors = array(
			'sent'      => 'is-green',
			'delivered' => 'is-green',
			'read'      => 'is-green',
			'queued'    => 'is-amber',
			'pending'   => 'is-amber',
			'failed'    => 'is-red',
			'error'     => 'is-red',
		);
		$class = isset( $colors[ $status ] ) ? $colors[ $status ] : 'is-gray';
		return '<span class="aac-pill ' . esc_attr( $class ) . '">' . esc_html( ucwords( str_replace( '_', ' ', $status ) ) ) . '</span>';
	}

	/**
	 * Display name of a log's creator.
	 *
	 * @param object $row Log row.
	 * @return string
	 */
	private function log_creator( $row ) {
		if ( empty( $row->created_by ) ) {
			return __( 'System', 'aaraa-white-label-admin' );
		}
		$user = get_userdata( (int) $row->created_by );
		return $user ? $user->display_name : '#' . (int) $row->created_by;
	}

	/**
	 * This customer's phone numbers as last-10-digit keys, so a log's recipient
	 * number matches regardless of country code / formatting.
	 *
	 * @param int $id Customer ID.
	 * @return array<string>
	 */
	private function customer_phone_keys( $id ) {
		$candidates = array();

		$billing = get_user_meta( $id, 'billing_phone', true );
		if ( $billing ) {
			$candidates[] = $billing;
		}
		foreach ( array( 'digits_phone', 'account_phone', 'phone', 'mobile' ) as $mk ) {
			$v = get_user_meta( $id, $mk, true );
			if ( $v ) {
				$candidates[] = $v;
			}
		}
		$user = get_userdata( $id );
		if ( $user && preg_match( '/^\+?\d[\d\s-]{6,}$/', (string) $user->user_login ) ) {
			$candidates[] = $user->user_login;
		}

		$keys = array();
		foreach ( $candidates as $c ) {
			$digits = preg_replace( '/\D+/', '', (string) $c );
			if ( '' === $digits ) {
				continue;
			}
			$keys[ strlen( $digits ) >= 10 ? substr( $digits, -10 ) : $digits ] = true;
		}
		return array_keys( $keys );
	}

	/**
	 * Pull this customer's rows from a channel's global send log (SMS or
	 * WhatsApp), filtered by the customer's number and paginated in memory.
	 * OTP rows are included automatically (they are just type "otp").
	 *
	 * @param int    $id       Customer ID.
	 * @param string $channel  'sms' | 'whatsapp'.
	 * @param int    $paged    Page number.
	 * @param int    $per_page Rows per page.
	 * @return array{0:array,1:int,2:int} rows, total, total_pages.
	 */
	private function get_channel_log( $id, $channel, $paged, $per_page ) {
		$entries  = array();
		$by_user  = ( 'in_app' === $channel );
		$phones   = array();

		if ( 'sms' === $channel && class_exists( __NAMESPACE__ . '\\Notifications_Admin' ) ) {
			$entries = Notifications_Admin::get_log();
		} elseif ( 'whatsapp' === $channel && class_exists( __NAMESPACE__ . '\\WhatsApp_Admin' ) ) {
			$entries = WhatsApp_Admin::get_log();
		} elseif ( 'in_app' === $channel && class_exists( __NAMESPACE__ . '\\Firebase_Admin' ) ) {
			$entries = Firebase_Admin::get_log();
		}

		if ( ! $by_user ) {
			$phones = $this->customer_phone_keys( $id );
		}

		$rows = array();
		foreach ( (array) $entries as $e ) {
			// Match the row to this customer — by user id for push, else by the
			// logged ref (which stores the customer id at send time) OR the
			// mobile number. Either match wins.
			if ( $by_user ) {
				if ( (int) ( isset( $e['user_id'] ) ? $e['user_id'] : 0 ) !== (int) $id ) {
					continue;
				}
			} else {
				$matched = false;

				// (a) Match by ref = customer id.
				$ref = isset( $e['ref'] ) ? trim( (string) $e['ref'] ) : '';
				if ( '' !== $ref && ctype_digit( $ref ) && (int) $ref === (int) $id ) {
					$matched = true;
				}

				// (b) Match by mobile number (last 10 digits).
				if ( ! $matched && ! empty( $phones ) ) {
					$mobile = isset( $e['mobile'] ) ? preg_replace( '/\D+/', '', (string) $e['mobile'] ) : '';
					if ( '' !== $mobile ) {
						$key = strlen( $mobile ) >= 10 ? substr( $mobile, -10 ) : $mobile;
						if ( in_array( $key, $phones, true ) ) {
							$matched = true;
						}
					}
				}

				if ( ! $matched ) {
					continue;
				}
			}

			$message = '';
			if ( 'in_app' === $channel ) {
				$title   = isset( $e['title'] ) ? trim( (string) $e['title'] ) : '';
				$body    = isset( $e['message'] ) ? trim( (string) $e['message'] ) : '';
				$message = trim( $title . ( ( '' !== $title && '' !== $body ) ? ' — ' : '' ) . $body );
			} elseif ( 'whatsapp' === $channel && ! empty( $e['body'] ) ) {
				$message = (string) $e['body'];
			} elseif ( ! empty( $e['message'] ) ) {
				$message = (string) $e['message'];
			}

			$time = isset( $e['time'] ) ? (string) $e['time'] : '';

			$row              = new \stdClass();
			$row->created_at  = ( '' !== $time ) ? get_gmt_from_date( $time ) : current_time( 'mysql', true );
			$row->event       = $this->channel_event_label( isset( $e['type'] ) ? (string) $e['type'] : '', isset( $e['ref'] ) ? (string) $e['ref'] : '' );
			$row->message     = $message;
			$row->status      = isset( $e['result'] ) ? (string) $e['result'] : '';
			$rows[]           = $row;
		}

		// Newest first.
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $b->created_at, $a->created_at );
			}
		);

		$total = count( $rows );
		$pages = (int) max( 1, ceil( $total / $per_page ) );
		$paged = min( max( 1, $paged ), $pages );
		$slice = array_slice( $rows, ( $paged - 1 ) * $per_page, $per_page );

		return array( $slice, $total, $pages );
	}

	/**
	 * Human label for a channel log row: the event type plus its reference.
	 *
	 * @param string $type Log type (order, subscription, otp, wallet_*, manual…).
	 * @param string $ref  Reference (order/subscription number, user id…).
	 * @return string
	 */
	private function channel_event_label( $type, $ref ) {
		$type  = trim( $type );
		$label = '';
		if ( 'otp' === strtolower( $type ) ) {
			$label = __( 'OTP', 'aaraa-white-label-admin' );
		} elseif ( '' !== $type ) {
			$label = ucwords( str_replace( array( '_', '-' ), ' ', $type ) );
		} else {
			$label = __( 'Message', 'aaraa-white-label-admin' );
		}
		if ( '' !== trim( (string) $ref ) ) {
			$label .= ' #' . $ref;
		}
		return $label;
	}

	/**
	 * Two-letter monogram for the avatar.
	 *
	 * @param string $name Customer name.
	 * @return string
	 */
	private function initials( $name ) {
		$parts    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';
		foreach ( (array) $parts as $part ) {
			if ( '' !== $part ) {
				$initials .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $part, 0, 1 ) ) : strtoupper( substr( $part, 0, 1 ) );
			}
			if ( strlen( $initials ) >= 2 ) {
				break;
			}
		}
		return '' !== $initials ? $initials : '?';
	}

	/**
	 * All line items of an order/subscription as "Product × qty, Product × qty".
	 *
	 * @param \WC_Abstract_Order $order Order or subscription.
	 * @return string
	 */
	private function items_summary( $order ) {
		$items = $order->get_items();
		if ( ! $items ) {
			return '—';
		}
		$parts = array();
		foreach ( $items as $item ) {
			$parts[] = $item->get_name() . ' × ' . (int) $item->get_quantity();
		}
		return implode( ', ', $parts );
	}

	/**
	 * Human label for an order's relationship to a subscription.
	 *
	 * @param WC_Order $order Order object.
	 * @return string One of: Renewal, Subscription, Switch, Resubscribe, One-time.
	 */
	private function order_type_label( $order ) {
		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
			return __( 'Renewal', 'aaraa-white-label-admin' );
		}
		if ( function_exists( 'wcs_order_contains_resubscribe' ) && wcs_order_contains_resubscribe( $order ) ) {
			return __( 'Resubscribe', 'aaraa-white-label-admin' );
		}
		if ( function_exists( 'wcs_order_contains_switch' ) && wcs_order_contains_switch( $order ) ) {
			return __( 'Switch', 'aaraa-white-label-admin' );
		}
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, 'parent' ) ) {
			return __( 'Subscription', 'aaraa-white-label-admin' );
		}
		return __( 'One-time', 'aaraa-white-label-admin' );
	}

	/**
	 * Pause / resume summary for a subscription, read HPOS-safely from the
	 * WCFMu pause meta ( _wcfmu_pause_dates / _wcfmu_pause_resume ).
	 *
	 * @param WC_Subscription $sub Subscription object.
	 * @return string Escaped HTML.
	 */
	private function pause_summary( $sub ) {
		$sub_id = $sub->get_id();

		// WCFMu writes these with update_post_meta() (→ wp_postmeta), so read from
		// post meta first; fall back to the CRUD object for any HPOS-stored value.
		$raw_dates  = get_post_meta( $sub_id, '_wcfmu_pause_dates', true );
		$raw_resume = get_post_meta( $sub_id, '_wcfmu_pause_resume', true );
		if ( '' === $raw_dates && '' === $raw_resume ) {
			$raw_dates  = $sub->get_meta( '_wcfmu_pause_dates' );
			$raw_resume = $sub->get_meta( '_wcfmu_pause_resume' );
		}

		$dates  = class_exists( '\\Aaraa\\Admin\\Subscription_API' )
			? \Aaraa\Admin\Subscription_API::parse_pause_dates( $raw_dates )
			: array();
		$resume = (string) $raw_resume;

		$is_paused = 'pause' === str_replace( 'wc-', '', (string) $sub->get_status() );

		if ( ! $is_paused && empty( $dates ) && '' === $resume ) {
			return '<span class="aac-muted">&mdash;</span>';
		}

		$fmt = static function ( $ymd ) {
			$ts = strtotime( $ymd );
			return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : $ymd;
		};

		sort( $dates );

		$parts = array();
		if ( $is_paused ) {
			$parts[] = '<span class="aac-pill is-amber">' . esc_html__( 'Paused', 'aaraa-white-label-admin' ) . '</span>';
		}
		if ( ! empty( $dates ) ) {
			/* translators: %d: number of paused delivery days. */
			$count = sprintf( _n( '%d day', '%d days', count( $dates ), 'aaraa-white-label-admin' ), count( $dates ) );
			$list  = esc_html( implode( ', ', array_map( $fmt, $dates ) ) );
			$parts[] = '<span class="aac-sub-line"><strong>' . esc_html__( 'Paused dates', 'aaraa-white-label-admin' ) . ' (' . esc_html( $count ) . '):</strong> ' . $list . '</span>';
		}
		if ( '' !== $resume ) {
			$parts[] = '<span class="aac-sub-line aac-sub-line--resume"><strong>' . esc_html__( 'Resume on:', 'aaraa-white-label-admin' ) . '</strong> ' . esc_html( $fmt( $resume ) ) . '</span>';
		}
		return implode( '<br>', $parts );
	}

	/**
	 * A coloured status pill.
	 *
	 * @param string $status Order/subscription status (with or without wc- prefix).
	 * @return string Escaped HTML.
	 */
	private function status_pill( $status ) {
		$status = str_replace( 'wc-', '', (string) $status );
		$colors = array(
			'active'         => 'is-green',
			'completed'      => 'is-green',
			'processing'     => 'is-blue',
			'on-hold'        => 'is-amber',
			'pending-cancel' => 'is-amber',
			'pending'        => 'is-gray',
			'expired'        => 'is-gray',
			'switched'       => 'is-gray',
			'cancelled'      => 'is-red',
			'failed'         => 'is-red',
			'refunded'       => 'is-red',
			'trash'          => 'is-red',
		);
		$class = isset( $colors[ $status ] ) ? $colors[ $status ] : 'is-gray';
		$label = ucwords( str_replace( '-', ' ', $status ) );
		return '<span class="aac-pill ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Render a table pager. Uses its own query arg so the two tables page
	 * independently while preserving the rest of the URL (including the other
	 * table's page).
	 *
	 * @param string $param       Query var to drive (e.g. 'order_paged').
	 * @param int    $current     Current page.
	 * @param int    $total_pages Total pages.
	 * @return void
	 */
	private function pager( $param, $current, $total_pages ) {
		if ( (int) $total_pages < 2 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'      => add_query_arg( $param, '%#%' ),
				'format'    => '',
				'current'   => max( 1, (int) $current ),
				'total'     => (int) $total_pages,
				'prev_text' => '&lsaquo;',
				'next_text' => '&rsaquo;',
				'type'      => 'plain',
			)
		);
		if ( $links ) {
			echo '<div class="aac-pager">' . wp_kses_post( $links ) . '</div>';
		}
	}

	/**
	 * Editable customer form.
	 *
	 * @param int $id Customer ID.
	 * @return void
	 */
	private function render_edit( $id ) {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to edit customers.', 'aaraa-white-label-admin' ) );
		}
		$user = get_userdata( $id );
		if ( ! $user ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Customer not found', 'aaraa-white-label-admin' ) . '</h1></div>';
			return;
		}

		$mobile = get_user_meta( $id, 'billing_phone', true );
		$mobile = '' !== $mobile ? $mobile : get_user_meta( $id, 'mobile', true );
		$wallet = self::get_wallet_balance( $id );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Edit Customer', 'aaraa-white-label-admin' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>" class="page-title-action"><?php esc_html_e( '&larr; Back to list', 'aaraa-white-label-admin' ); ?></a>
			<hr class="wp-header-end" />

			<?php $this->notices(); ?>

			<form method="post" action="<?php echo esc_url( add_query_arg( array( 'action' => 'save' ), admin_url( 'admin.php?page=' . self::PAGE ) ) ); ?>">
				<?php wp_nonce_field( 'aaraa_save_customer_' . $id ); ?>
				<input type="hidden" name="customer" value="<?php echo (int) $id; ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="first_name"><?php esc_html_e( 'First name', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="first_name" id="first_name" type="text" class="regular-text" value="<?php echo esc_attr( $user->first_name ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="last_name"><?php esc_html_e( 'Last name', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="last_name" id="last_name" type="text" class="regular-text" value="<?php echo esc_attr( $user->last_name ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="user_email"><?php esc_html_e( 'Email', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="user_email" id="user_email" type="email" class="regular-text" value="<?php echo esc_attr( $user->user_email ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="billing_phone"><?php esc_html_e( 'Mobile', 'aaraa-white-label-admin' ); ?></label></th>
						<td><input name="billing_phone" id="billing_phone" type="text" class="regular-text" value="<?php echo esc_attr( $mobile ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="wallet"><?php esc_html_e( 'Wallet balance', 'aaraa-white-label-admin' ); ?></label></th>
						<td>
							<input name="wallet" id="wallet" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $wallet ); ?>" />
							<p class="description"><?php esc_html_e( 'Stored in the wps_wallet meta key.', 'aaraa-white-label-admin' ); ?></p>
						</td>
					</tr>
					<?php Customer_Delivery::fields( $id ); ?>
				</table>
				<?php submit_button( __( 'Save Customer', 'aaraa-white-label-admin' ) ); ?>
			</form>

			<?php $this->render_address_book( $id ); ?>
		</div>
		<?php
	}

	/**
	 * The customer's delivery address book — the same set the customer manages in
	 * their dashboard (WCFMu `customer_addresses` table). Fully editable here:
	 * add / edit / delete each address and set the primary one (which syncs to the
	 * WooCommerce billing/shipping address). Reuses WCFMu's admin AJAX handlers
	 * (wcfmu_aca_save / wcfmu_aca_delete) so there is a single source of truth.
	 *
	 * @param int $id Customer ID.
	 * @return void
	 */
	private function render_address_book( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'customer_addresses';

		// The address book lives in a WCFMu table; if that plugin/table is absent,
		// silently show nothing rather than erroring.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB
			return;
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY is_primary DESC, id ASC", $id ),
			ARRAY_A
		);
		$rows   = is_array( $rows ) ? $rows : array();
		$labels = array( 'Home', 'Work', 'Office', 'Other' );
		?>
		<h2 style="margin-top:26px;"><?php esc_html_e( 'Delivery addresses', 'aaraa-white-label-admin' ); ?></h2>
		<p class="description" style="margin-top:-6px;"><?php esc_html_e( 'All addresses this customer has saved. Set one as primary — it becomes the default billing/shipping address for their orders.', 'aaraa-white-label-admin' ); ?></p>

		<?php wp_nonce_field( 'wcfmu_aca_nonce_action', 'wcfmu_aca_nonce' ); ?>
		<input type="hidden" id="aaraa-aca-customer" value="<?php echo (int) $id; ?>" />
		<input type="hidden" id="aaraa-aca-labels" value="<?php echo esc_attr( wp_json_encode( $labels ) ); ?>" />

		<div id="aaraa-aca-list" style="max-width:760px;">
			<?php foreach ( $rows as $addr ) { $this->render_address_card( $addr, $labels ); } ?>
		</div>
		<button type="button" class="button button-primary" id="aaraa-aca-add">+ <?php esc_html_e( 'Add New Address', 'aaraa-white-label-admin' ); ?></button>
		<span id="aaraa-aca-global" style="margin-left:10px;font-size:12.5px;"></span>

		<style>
			.aaraa-aca-card{border:1px solid #dcdcde;border-radius:10px;padding:16px;margin:14px 0;background:#fff;max-width:760px;}
			.aaraa-aca-card.is-primary{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1 inset;}
			.aaraa-aca-grid{display:flex;flex-wrap:wrap;gap:12px;}
			.aaraa-aca-f{display:flex;flex-direction:column;}
			.aaraa-aca-f label{font-size:12px;color:#50575e;margin-bottom:4px;}
			.aaraa-aca-f input,.aaraa-aca-f select{padding:6px 8px;border:1px solid #8c8f94;border-radius:4px;width:100%;box-sizing:border-box;}
			.aaraa-aca-f--full{flex:1 1 100%;}
			.aaraa-aca-f--half{flex:1 1 calc(50% - 6px);min-width:180px;}
			.aaraa-aca-f--third{flex:1 1 calc(33.33% - 8px);min-width:130px;}
			.aaraa-aca-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px;}
			.aaraa-aca-primary-wrap{margin-right:auto;font-size:13px;}
			.aaraa-aca-flag{color:#2271b1;font-weight:600;}
			.aaraa-aca-msg{font-size:12.5px;}
		</style>

		<script>
		( function ( $ ) {
			var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce    = $( '#wcfmu_aca_nonce' ).val();
			var customer = $( '#aaraa-aca-customer' ).val();
			var labels   = JSON.parse( $( '#aaraa-aca-labels' ).val() || '[]' );

			function esc( s ) { return $( '<div>' ).text( s == null ? '' : s ).html(); }

			function labelOptions( sel ) {
				var out = '';
				var seen = false;
				labels.forEach( function ( l ) {
					if ( l === sel ) { seen = true; }
					out += '<option value="' + esc( l ) + '"' + ( l === sel ? ' selected' : '' ) + '>' + esc( l ) + '</option>';
				} );
				if ( sel && ! seen ) { out += '<option value="' + esc( sel ) + '" selected>' + esc( sel ) + '</option>'; }
				return out;
			}

			function f( lbl, cls, val, size ) {
				return '<div class="aaraa-aca-f aaraa-aca-f--' + size + '"><label>' + lbl + '</label>' +
					'<input type="text" class="aaraa-aca-' + cls + '" value="' + esc( val ) + '" /></div>';
			}

			function cardHtml( a ) {
				a = a || {};
				var isPrimary = parseInt( a.is_primary ) === 1;
				return '<div class="aaraa-aca-card' + ( isPrimary ? ' is-primary' : '' ) + '" data-id="' + ( parseInt( a.id ) || 0 ) + '">' +
					'<div class="aaraa-aca-grid">' +
						f( 'Name', 'name', a.name, 'half' ) +
						f( 'Mobile Number', 'mobile_number', a.mobile_number, 'half' ) +
						'<div class="aaraa-aca-f aaraa-aca-f--third"><label>Label</label><select class="aaraa-aca-label">' + labelOptions( a.label || 'Home' ) + '</select></div>' +
						f( 'Address Line 1', 'address_1', a.address_1, 'full' ) +
						f( 'Address Line 2', 'address_2', a.address_2, 'full' ) +
						f( 'City', 'city', a.city, 'third' ) +
						f( 'State', 'state', a.state || 'TN', 'third' ) +
						f( 'Postcode', 'postcode', a.postcode, 'third' ) +
					'</div>' +
					'<input type="hidden" class="aaraa-aca-country" value="' + esc( a.country || 'IN' ) + '" />' +
					'<div class="aaraa-aca-actions">' +
						'<span class="aaraa-aca-primary-wrap">' + primaryHtml( isPrimary ) + '</span>' +
						'<button type="button" class="button button-primary aaraa-aca-save">Save</button>' +
						'<button type="button" class="button aaraa-aca-delete">Delete</button>' +
						'<span class="aaraa-aca-msg"></span>' +
					'</div>' +
				'</div>';
			}

			function primaryHtml( isPrimary ) {
				return isPrimary
					? '<span class="aaraa-aca-flag">&#10003; Primary address</span>'
					: '<label style="cursor:pointer;"><input type="radio" class="aaraa-aca-primary" name="aaraa_aca_primary" /> Set as primary</label>';
			}

			function collect( $c ) {
				return {
					address_id:    parseInt( $c.data( 'id' ) ) || 0,
					name:          $c.find( '.aaraa-aca-name' ).val(),
					mobile_number: $c.find( '.aaraa-aca-mobile_number' ).val(),
					label:         $c.find( '.aaraa-aca-label' ).val(),
					address_1:     $.trim( $c.find( '.aaraa-aca-address_1' ).val() ),
					address_2:     $c.find( '.aaraa-aca-address_2' ).val(),
					city:          $c.find( '.aaraa-aca-city' ).val(),
					state:         $c.find( '.aaraa-aca-state' ).val(),
					postcode:      $c.find( '.aaraa-aca-postcode' ).val(),
					country:       $c.find( '.aaraa-aca-country' ).val(),
					is_primary:    $c.find( '.aaraa-aca-primary' ).is( ':checked' ) ? 1 : 0
				};
			}

			$( '#aaraa-aca-add' ).on( 'click', function () {
				$( '#aaraa-aca-list' ).append( cardHtml( {} ) );
			} );

			$( document ).on( 'click', '.aaraa-aca-save', function () {
				var $c   = $( this ).closest( '.aaraa-aca-card' );
				var $msg = $c.find( '.aaraa-aca-msg' );
				var data = collect( $c );
				if ( ! data.address_1 ) { $msg.text( 'Address Line 1 is required.' ).css( 'color', '#d63638' ); return; }

				$( this ).prop( 'disabled', true );
				$msg.text( 'Saving…' ).css( 'color', '#646970' );
				$.post( ajaxUrl, $.extend( { action: 'wcfmu_aca_save', wcfmu_aca_nonce: nonce, customer_id: customer }, data ), function ( resp ) {
					$c.find( '.aaraa-aca-save' ).prop( 'disabled', false );
					if ( resp && resp.success ) {
						$msg.text( 'Saved.' ).css( 'color', '#008a20' );
						if ( 0 === ( parseInt( $c.data( 'id' ) ) || 0 ) && resp.data && resp.data.id ) { $c.data( 'id', resp.data.id ); }
						if ( data.is_primary ) { location.reload(); }
					} else {
						$msg.text( ( resp && resp.data ) ? resp.data : 'Error saving.' ).css( 'color', '#d63638' );
					}
				} ).fail( function () {
					$c.find( '.aaraa-aca-save' ).prop( 'disabled', false );
					$msg.text( 'Error saving.' ).css( 'color', '#d63638' );
				} );
			} );

			$( document ).on( 'click', '.aaraa-aca-delete', function () {
				var $c = $( this ).closest( '.aaraa-aca-card' );
				var id = parseInt( $c.data( 'id' ) ) || 0;
				if ( 0 === id ) { $c.remove(); return; }
				if ( ! window.confirm( 'Delete this address?' ) ) { return; }
				var $msg = $c.find( '.aaraa-aca-msg' );
				$msg.text( 'Deleting…' ).css( 'color', '#646970' );
				$.post( ajaxUrl, { action: 'wcfmu_aca_delete', wcfmu_aca_nonce: nonce, customer_id: customer, address_id: id }, function ( resp ) {
					if ( resp && resp.success ) { $c.slideUp( 150, function () { $c.remove(); } ); }
					else { $msg.text( ( resp && resp.data ) ? resp.data : 'Error deleting.' ).css( 'color', '#d63638' ); }
				} );
			} );
		} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Render one saved-address card (server side; JS clones for new ones).
	 *
	 * @param array $addr   Address row from the customer_addresses table.
	 * @param array $labels Available labels.
	 * @return void
	 */
	private function render_address_card( $addr, $labels ) {
		$is_primary = isset( $addr['is_primary'] ) && 1 === (int) $addr['is_primary'];
		?>
		<div class="aaraa-aca-card <?php echo $is_primary ? 'is-primary' : ''; ?>" data-id="<?php echo esc_attr( $addr['id'] ); ?>">
			<div class="aaraa-aca-grid">
				<div class="aaraa-aca-f aaraa-aca-f--half"><label><?php esc_html_e( 'Name', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" class="aaraa-aca-name" value="<?php echo esc_attr( $addr['name'] ); ?>" /></div>
				<div class="aaraa-aca-f aaraa-aca-f--half"><label><?php esc_html_e( 'Mobile Number', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" class="aaraa-aca-mobile_number" value="<?php echo esc_attr( $addr['mobile_number'] ); ?>" /></div>
				<div class="aaraa-aca-f aaraa-aca-f--third"><label><?php esc_html_e( 'Label', 'aaraa-white-label-admin' ); ?></label>
					<select class="aaraa-aca-label">
						<?php
						$found = false;
						foreach ( $labels as $l ) {
							if ( $l === $addr['label'] ) {
								$found = true;
							}
							printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $l ), selected( $addr['label'], $l, false ) );
						}
						if ( ! empty( $addr['label'] ) && ! $found ) {
							printf( '<option value="%1$s" selected>%1$s</option>', esc_attr( $addr['label'] ) );
						}
						?>
					</select>
				</div>
				<div class="aaraa-aca-f aaraa-aca-f--full"><label><?php esc_html_e( 'Address Line 1', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" class="aaraa-aca-address_1" value="<?php echo esc_attr( $addr['address_1'] ); ?>" /></div>
				<div class="aaraa-aca-f aaraa-aca-f--full"><label><?php esc_html_e( 'Address Line 2', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" class="aaraa-aca-address_2" value="<?php echo esc_attr( $addr['address_2'] ); ?>" /></div>
				<div class="aaraa-aca-f aaraa-aca-f--third"><label><?php esc_html_e( 'City', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" class="aaraa-aca-city" value="<?php echo esc_attr( $addr['city'] ); ?>" /></div>
				<div class="aaraa-aca-f aaraa-aca-f--third"><label><?php esc_html_e( 'State', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" class="aaraa-aca-state" value="<?php echo esc_attr( $addr['state'] ? $addr['state'] : 'TN' ); ?>" /></div>
				<div class="aaraa-aca-f aaraa-aca-f--third"><label><?php esc_html_e( 'Postcode', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" class="aaraa-aca-postcode" value="<?php echo esc_attr( $addr['postcode'] ); ?>" /></div>
			</div>
			<input type="hidden" class="aaraa-aca-country" value="<?php echo esc_attr( $addr['country'] ? $addr['country'] : 'IN' ); ?>" />
			<div class="aaraa-aca-actions">
				<span class="aaraa-aca-primary-wrap">
					<?php if ( $is_primary ) : ?>
						<span class="aaraa-aca-flag">&#10003; <?php esc_html_e( 'Primary address', 'aaraa-white-label-admin' ); ?></span>
					<?php else : ?>
						<label style="cursor:pointer;"><input type="radio" class="aaraa-aca-primary" name="aaraa_aca_primary" /> <?php esc_html_e( 'Set as primary', 'aaraa-white-label-admin' ); ?></label>
					<?php endif; ?>
				</span>
				<button type="button" class="button button-primary aaraa-aca-save"><?php esc_html_e( 'Save', 'aaraa-white-label-admin' ); ?></button>
				<button type="button" class="button aaraa-aca-delete"><?php esc_html_e( 'Delete', 'aaraa-white-label-admin' ); ?></button>
				<span class="aaraa-aca-msg"></span>
			</div>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * Whether a user may be deleted here: must exist and be a customer only
	 * (never an administrator or staff account — prevents privilege escalation).
	 *
	 * @param int $id User ID.
	 * @return bool
	 */
	private function is_deletable_customer( $id ) {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return false;
		}
		$roles = (array) $user->roles;
		if ( ! array_intersect( array( 'customer', 'subscriber' ), $roles ) ) {
			return false;
		}
		$staff = array( 'administrator', 'editor', 'author', 'contributor', 'shop_manager' );
		return array() === array_intersect( $roles, $staff );
	}

	/**
	 * Count a customer's orders (HPOS-safe, ids only).
	 *
	 * @param int $id Customer ID.
	 * @return int
	 */
	private function order_count( $id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		$result = wc_get_orders(
			array(
				'customer_id' => $id,
				'limit'       => 1,
				'return'      => 'ids',
				'paginate'    => true,
			)
		);
		return is_object( $result ) && isset( $result->total ) ? (int) $result->total : 0;
	}

	/**
	 * Redirect back to the customers list with a status arg.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return void
	 */
	private function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Render success/error notices.
	 *
	 * @return void
	 */
	private function notices() {
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Customer updated.', 'aaraa-white-label-admin' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Customer deleted.', 'aaraa-white-label-admin' ) . '</p></div>';
		}
		if ( isset( $_GET['created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Customer created.', 'aaraa-white-label-admin' ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$error = sanitize_key( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$map   = array(
				'nomobile'  => __( 'Enter a mobile number — it is how this store identifies a customer.', 'aaraa-white-label-admin' ),
				'bademail'  => __( 'That email address is not valid.', 'aaraa-white-label-admin' ),
				'dupemail'  => __( 'Another account already uses that email address.', 'aaraa-white-label-admin' ),
				'dupmobile' => __( 'A customer with that mobile number already exists.', 'aaraa-white-label-admin' ),
				'create'    => __( 'The customer could not be created.', 'aaraa-white-label-admin' ),
				'delivery'  => __( 'Saved, except the delivery mapping: that person is not assigned to the selected hub.', 'aaraa-white-label-admin' ),
			);
			$message = isset( $map[ $error ] ) ? $map[ $error ] : __( 'Action could not be completed.', 'aaraa-white-label-admin' );

			// Point straight at the clash rather than making them go and find it.
			$link = '';
			if ( 'dupmobile' === $error && ! empty( $_GET['existing'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$existing = absint( $_GET['existing'] ); // phpcs:ignore WordPress.Security.NonceVerification
				$link     = ' <a href="' . esc_url(
					add_query_arg(
						array(
							'page'     => self::PAGE,
							'action'   => 'view',
							'customer' => $existing,
						),
						admin_url( 'admin.php' )
					)
				) . '">' . esc_html__( 'Open that customer', 'aaraa-white-label-admin' ) . '</a>';
			}

			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
