<?php
/**
 * Wallet Management screen.
 *
 * A self-contained admin page (registered under the AaraaKart menu) that lists
 * customer wallets, their transactions, and lets an operator add / edit / delete
 * a wallet balance. It reads and writes the exact data the wallet plugin uses:
 *   - balance:      user meta `wps_wallet`
 *   - transactions: table `{prefix}_wps_wsfw_wallet_transaction`
 * so everything stays consistent with wallet-system-for-woocommerce (+ pro).
 *
 * Every credit/debit records the acting operator's login username via the
 * additive `created_by` column (see Customers_Admin::ensure_wallet_created_by()).
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wallet list / transactions / add / edit / delete controller.
 */
class Wallet_Admin {

	const PAGE = 'aaraa-wallet';

	/**
	 * Register hooks. Actions run on admin_init so they can redirect before output.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_aaraa_wallet_user_search', array( $this, 'user_search' ) );
	}

	/**
	 * Load the modal script (and stylesheet) on the wallet page only.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! isset( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		wp_enqueue_style( 'aaraa-admin', AARAA_WLA_URL . 'assets/css/admin.css', array(), aaraa_asset_ver( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'aaraa-wallet', AARAA_WLA_URL . 'assets/js/wallet.js', array( 'jquery' ), aaraa_asset_ver( 'assets/js/wallet.js' ), true );
		wp_localize_script(
			'aaraa-wallet',
			'AaraaWallet',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'aaraa_wallet_search' ),
			)
		);
	}

	/**
	 * AJAX: search customers by name, mobile or email for the Add-wallet field.
	 *
	 * @return void
	 */
	public function user_search() {
		check_ajax_referer( 'aaraa_wallet_search', 'nonce' );
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_send_json( array() );
		}
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json( array() );
		}

		$ids = array();

		// Match login / email / display name.
		$q1 = new \WP_User_Query(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
				'number'         => 15,
				'fields'         => 'ID',
			)
		);
		foreach ( (array) $q1->get_results() as $id ) {
			$ids[ (int) $id ] = true;
		}

		// Match phone / first / last name stored in meta.
		$q2 = new \WP_User_Query(
			array(
				'number'     => 15,
				'fields'     => 'ID',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'OR',
					array( 'key' => 'billing_phone', 'value' => $term, 'compare' => 'LIKE' ),
					array( 'key' => 'mobile', 'value' => $term, 'compare' => 'LIKE' ),
					array( 'key' => 'first_name', 'value' => $term, 'compare' => 'LIKE' ),
					array( 'key' => 'last_name', 'value' => $term, 'compare' => 'LIKE' ),
					array( 'key' => 'billing_first_name', 'value' => $term, 'compare' => 'LIKE' ),
					array( 'key' => 'billing_last_name', 'value' => $term, 'compare' => 'LIKE' ),
				),
			)
		);
		foreach ( (array) $q2->get_results() as $id ) {
			$ids[ (int) $id ] = true;
		}

		$out = array();
		foreach ( array_keys( $ids ) as $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}
			$phone = get_user_meta( $id, 'billing_phone', true );
			if ( ! $phone ) {
				$phone = get_user_meta( $id, 'mobile', true );
			}
			$label = $user->display_name . ' — ' . $user->user_email . ( $phone ? ' — ' . $phone : '' );
			$out[] = array(
				'id'   => $id,
				'text' => $label,
			);
			if ( count( $out ) >= 15 ) {
				break;
			}
		}
		wp_send_json( $out );
	}

	/* --------------------------------------------------------------------- *
	 * Helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * The wallet transaction table name.
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'wps_wsfw_wallet_transaction';
	}

	/**
	 * Base page URL with optional extra query args.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	private function url( $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Format a money amount using WooCommerce if available.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private function money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}
		return esc_html( number_format_i18n( (float) $amount, 2 ) );
	}

	/**
	 * The login username of whoever created a transaction row.
	 *
	 * @param object $row Transaction row.
	 * @return string
	 */
	private function creator_login( $row ) {
		if ( ! empty( $row->created_by ) ) {
			$user = get_userdata( (int) $row->created_by );
			if ( $user ) {
				return $user->user_login;
			}
			return '#' . (int) $row->created_by;
		}
		// Pre-existing rows have no actor — infer from the payment method.
		$method = strtolower( (string) $row->payment_method );
		if ( false !== strpos( $method, 'admin' ) || false !== strpos( $method, 'manual' ) ) {
			return __( 'admin', 'aaraa-white-label-admin' );
		}
		return __( 'system', 'aaraa-white-label-admin' );
	}

	/* --------------------------------------------------------------------- *
	 * Write path.
	 * --------------------------------------------------------------------- */

	/**
	 * Dispatch add / edit (adjust) and delete actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		/*
		 * The submit path is driven by a dedicated hidden field posted inside the
		 * form (`aaraa_wallet_action`) rather than by $_REQUEST['action'] + the
		 * request method. Relying on those meant that if the request ever arrived
		 * as a GET — or if $_REQUEST was not composed from GET on this host
		 * (php.ini request_order) — the handler silently skipped and the page just
		 * re-rendered at ?action=wallet_adjust with nothing saved.
		 */
		$posted = isset( $_POST['aaraa_wallet_action'] ) ? sanitize_key( wp_unslash( $_POST['aaraa_wallet_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'wallet_adjust' === $posted ) {
			$this->process_adjust();
			return;
		}

		// Remaining (link-driven) actions still come through the query string.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE !== $page ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'delete' === $action ) {
			$this->process_delete();
		}
	}

	/**
	 * Credit or debit a wallet (handles both "Add Wallet" and "Edit").
	 *
	 * @return void
	 */
	private function process_adjust() {
		// Verify without wp_nonce_ays()'s dead-end "link expired" screen so a
		// stale form reports back on the page instead.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aaraa_wallet_adjust' ) ) {
			$this->redirect( array( 'notice' => 'nonce' ) );
		}

		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage wallets.', 'aaraa-white-label-admin' ) );
		}

		// Resolve the target user: fixed id (Edit) or a lookup string (Add).
		$user = null;
		if ( ! empty( $_POST['user_id'] ) ) {
			$user = get_userdata( absint( $_POST['user_id'] ) );
		} elseif ( ! empty( $_POST['user_query'] ) ) {
			$user = $this->resolve_user( sanitize_text_field( wp_unslash( $_POST['user_query'] ) ) );
		}

		$type = isset( $_POST['wtx_type'] ) ? sanitize_key( wp_unslash( $_POST['wtx_type'] ) ) : '';
		$amt  = isset( $_POST['wtx_amount'] ) ? round( (float) wp_unslash( $_POST['wtx_amount'] ), function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 ) : 0.0;
		$note = isset( $_POST['wtx_note'] ) ? sanitize_text_field( wp_unslash( $_POST['wtx_note'] ) ) : '';

		if ( ! $user ) {
			$this->redirect( array( 'notice' => 'nouser' ) );
		}
		if ( ! in_array( $type, array( 'credit', 'debit' ), true ) || $amt <= 0 ) {
			$this->redirect( array( 'notice' => 'amount' ) );
		}

		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$current  = Customers_Admin::get_wallet_balance( $user->ID );

		if ( 'credit' === $type ) {
			$balance = $current + $amt;
		} else {
			// Match the wallet plugin: a debit larger than the balance empties it
			// rather than going negative.
			$balance = ( $current < $amt ) ? 0 : $current - $amt;
		}
		$balance = round( $balance, $decimals );
		// Writes both wps_wallet and _wps_amount, creating either if absent.
		Customers_Admin::set_wallet_balance( $user->ID, $balance );

		$logged = $this->write_transaction(
			$user->ID,
			$amt,
			$type,
			'credit' === $type
				? __( 'Credited manually by admin', 'aaraa-white-label-admin' )
				: __( 'Debited manually by admin', 'aaraa-white-label-admin' ),
			$note
		);

		if ( ! $logged ) {
			// Balance changed but the ledger row did not save — say so rather
			// than reporting a clean success.
			$this->redirect( array( 'notice' => 'dberror' ) );
		}

		if ( class_exists( __NAMESPACE__ . '\\Customer_Logs' ) ) {
			$formatted = html_entity_decode( wp_strip_all_tags( $this->money( $amt ) ) );
			Customer_Logs::add(
				$user->ID,
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
		}

		$this->redirect(
			array(
				'notice' => 'saved',
				'bal'    => $balance,
				'uid'    => $user->ID,
			)
		);
	}

	/**
	 * Delete a wallet: zero the balance (recording the removal) and drop the meta.
	 *
	 * @return void
	 */
	private function process_delete() {
		$id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		check_admin_referer( 'aaraa_wallet_delete_' . $id );

		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage wallets.', 'aaraa-white-label-admin' ) );
		}

		$user = get_userdata( $id );
		if ( ! $user ) {
			$this->redirect( array( 'notice' => 'error' ) );
		}

		$balance = Customers_Admin::get_wallet_balance( $id );
		if ( $balance > 0 ) {
			// Record the emptying so the ledger stays auditable.
			$this->write_transaction(
				$id,
				$balance,
				'debit',
				__( 'Wallet deleted by admin', 'aaraa-white-label-admin' ),
				''
			);
		}
		delete_user_meta( $id, 'wps_wallet' );
		delete_user_meta( $id, '_wps_amount' );

		if ( class_exists( __NAMESPACE__ . '\\Customer_Logs' ) ) {
			Customer_Logs::add( $id, 'system', 'wallet_deleted', __( 'Wallet deleted', 'aaraa-white-label-admin' ) );
		}

		$this->redirect( array( 'notice' => 'deleted' ) );
	}

	/**
	 * Resolve a user from an email, login, numeric ID, mobile number or name.
	 *
	 * @param string $query Lookup string.
	 * @return \WP_User|false
	 */
	private function resolve_user( $query ) {
		$query = trim( $query );
		if ( '' === $query ) {
			return false;
		}
		if ( is_email( $query ) ) {
			$u = get_user_by( 'email', $query );
			if ( $u ) {
				return $u;
			}
		}
		// The autocomplete puts "Name — email — phone" in the field; pull the email out.
		if ( preg_match( '/[\w.+-]+@[\w-]+\.[\w.-]+/', $query, $m ) ) {
			$u = get_user_by( 'email', $m[0] );
			if ( $u ) {
				return $u;
			}
		}
		if ( ctype_digit( $query ) ) {
			$u = get_userdata( (int) $query );
			if ( $u ) {
				return $u;
			}
		}
		$u = get_user_by( 'login', $query );
		if ( $u ) {
			return $u;
		}

		// Mobile number (billing_phone / mobile meta).
		$ids = get_users(
			array(
				'number'     => 1,
				'fields'     => 'ID',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'OR',
					array( 'key' => 'billing_phone', 'value' => $query, 'compare' => '=' ),
					array( 'key' => 'mobile', 'value' => $query, 'compare' => '=' ),
				),
			)
		);
		if ( ! empty( $ids ) ) {
			return get_userdata( (int) $ids[0] );
		}

		// Name (display name).
		$q = new \WP_User_Query(
			array(
				'search'         => '*' . $query . '*',
				'search_columns' => array( 'display_name' ),
				'number'         => 1,
				'fields'         => 'ID',
			)
		);
		$r = $q->get_results();
		if ( ! empty( $r ) ) {
			return get_userdata( (int) $r[0] );
		}
		return false;
	}

	/**
	 * Insert a transaction row (with the acting operator recorded in created_by).
	 *
	 * @param int    $user_id     Customer ID.
	 * @param float  $amount      Positive amount.
	 * @param string $type        'credit' or 'debit'.
	 * @param string $description transaction_type column text.
	 * @param string $note        Optional note.
	 * @return void
	 */
	private function write_transaction( $user_id, $amount, $type, $description, $note = '' ) {
		global $wpdb;
		if ( ! Customers_Admin::wallet_table_exists() ) {
			return false;
		}

		// Mirrors the wallet plugin's own insert_transaction_data_in_table().
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

		// Only send created_by when the column exists — otherwise MySQL rejects
		// the whole insert with "Unknown column 'created_by' in 'field list'".
		if ( Customers_Admin::has_created_by_column() ) {
			$data['created_by'] = get_current_user_id();
			$format[]           = '%d';
		}

		$ok = $wpdb->insert( $this->table(), $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $ok && ! empty( $wpdb->last_error ) ) {
			// Surface the cause instead of failing silently.
			error_log( 'Aaraa Wallet: transaction insert failed — ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}

		return (bool) $ok;
	}

	/**
	 * Redirect back to the page with a notice flag.
	 *
	 * @param array $args Query args.
	 * @return void
	 */
	private function redirect( $args = array() ) {
		wp_safe_redirect( $this->url( $args ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Read path / rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Main page controller.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'aaraa-white-label-admin' ) );
		}

		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		echo '<div class="wrap aaraa-wallet">';
		$this->render_header( $tab, $action );
		$this->render_notices();

		if ( 'add' === $action ) {
			$this->render_form();
		} elseif ( 'edit' === $action ) {
			$this->render_form( isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( 'view' === $action ) {
			$this->render_user_transactions( isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( 'transactions' === $tab ) {
			$this->render_transactions();
		} else {
			$this->render_list();
		}

		// Add / Edit popups live on the list & transactions views (where the
		// "+ Add Wallet" and per-row "Edit" triggers are shown).
		if ( ! in_array( $action, array( 'add', 'edit', 'view' ), true ) ) {
			$this->render_modals();
		}

		echo '</div>';
	}

	/**
	 * Page title, "+ Add Wallet" button and the two tabs.
	 *
	 * @param string $tab    Active tab.
	 * @param string $action Active action (hides tabs on sub-screens).
	 * @return void
	 */
	private function render_header( $tab, $action ) {
		?>
		<div class="aaraa-wallet__bar">
			<h1 class="aaraa-wallet__title"><?php esc_html_e( 'Wallet Management', 'aaraa-white-label-admin' ); ?></h1>
			<a href="<?php echo esc_url( $this->url( array( 'action' => 'add' ) ) ); ?>" class="button button-primary aaraa-wallet__add">
				<?php esc_html_e( '+ Add Wallet', 'aaraa-white-label-admin' ); ?>
			</a>
		</div>
		<?php
		// Hide the tabs only on the dedicated sub-screens — never for an
		// unrecognised action, which would otherwise strand the user with no
		// way back to the list.
		if ( ! in_array( $action, array( 'add', 'edit', 'view' ), true ) ) :
			$list_active = ( 'transactions' !== $tab ) ? ' is-active' : '';
			$tx_active   = ( 'transactions' === $tab ) ? ' is-active' : '';
			?>
			<nav class="aaraa-wallet__tabs">
				<a class="aaraa-wallet__tab<?php echo esc_attr( $list_active ); ?>" href="<?php echo esc_url( $this->url( array( 'tab' => 'list' ) ) ); ?>"><?php esc_html_e( 'Wallet List', 'aaraa-white-label-admin' ); ?></a>
				<a class="aaraa-wallet__tab<?php echo esc_attr( $tx_active ); ?>" href="<?php echo esc_url( $this->url( array( 'tab' => 'transactions' ) ) ); ?>"><?php esc_html_e( 'Wallet Transactions', 'aaraa-white-label-admin' ); ?></a>
			</nav>
			<?php
		endif;
	}

	/**
	 * Success / error notices from the redirect flags.
	 *
	 * @return void
	 */
	private function render_notices() {
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'saved' === $notice ) {
			$msg = esc_html__( 'Wallet updated.', 'aaraa-white-label-admin' );
			if ( isset( $_GET['uid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				// Read the balance back from the database so the message reflects
				// what was actually stored, not what we intended to store.
				$stored = Customers_Admin::get_wallet_balance( absint( $_GET['uid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				$msg   .= ' ' . sprintf(
					/* translators: %s: new wallet balance */
					esc_html__( 'New balance: %s', 'aaraa-white-label-admin' ),
					wp_kses_post( $this->money( $stored ) )
				);
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $msg ) . '</p></div>';
		} elseif ( 'deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Wallet deleted.', 'aaraa-white-label-admin' ) . '</p></div>';
		} elseif ( 'nouser' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No matching customer found. Search by name, mobile or email and pick one from the list.', 'aaraa-white-label-admin' ) . '</p></div>';
		} elseif ( 'nonce' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Security check failed (the form expired). Reload the page and try again.', 'aaraa-white-label-admin' ) . '</p></div>';
		} elseif ( 'dberror' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The balance was updated but the transaction could not be saved. Check the wallet transaction table exists and is writable (see the PHP error log for the database error).', 'aaraa-white-label-admin' ) . '</p></div>';
		} elseif ( 'amount' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Enter an amount greater than zero and choose Credit or Debit.', 'aaraa-white-label-admin' ) . '</p></div>';
		} elseif ( 'error' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not complete the request. Check the user and amount.', 'aaraa-white-label-admin' ) . '</p></div>';
		}
	}

	/**
	 * The usermeta keys searched for name / mobile.
	 *
	 * @return string[]
	 */
	private function search_meta_keys() {
		return array(
			'first_name',
			'last_name',
			'nickname',
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
			'mobile',
			'shipping_phone',
			'wps_wallet_id',
		);
	}

	/**
	 * Find user IDs matching a term across name, mobile and email.
	 *
	 * WP_User_Query's search_columns only covers the wp_users table, so mobile
	 * numbers and first/last names (which live in usermeta) can never match
	 * through it. This searches both tables in one query.
	 *
	 * @param string $term Search term.
	 * @return int[]
	 */
	private function search_user_ids( $term ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$uid  = ctype_digit( trim( (string) $term ) ) ? (int) $term : 0; // exact user-ID match when numeric.
		$keys = "'" . implode( "','", array_map( 'esc_sql', $this->search_meta_keys() ) ) . "'";
		$sql  = "SELECT DISTINCT u.ID FROM {$wpdb->users} u
			LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key IN ({$keys})
			WHERE u.ID = %d
				OR u.user_login LIKE %s
				OR u.user_email LIKE %s
				OR u.display_name LIKE %s
				OR u.user_nicename LIKE %s
				OR m.meta_value LIKE %s";
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $uid, $like, $like, $like, $like, $like ) ); // phpcs:ignore WordPress.DB
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Fetch a page of wallet holders with their balances.
	 *
	 * Reads both wallet meta keys (wps_wallet preferred, _wps_amount fallback) so
	 * the list is correct regardless of which one the site populated, and orders
	 * numerically by balance.
	 *
	 * @param string $search    Search term.
	 * @param int    $per_page  Rows per page.
	 * @param int    $paged     Page number.
	 * @param string $order_sql Safe, whitelisted ORDER BY expression (no "ORDER BY").
	 * @return array{0:array,1:int,2:int,3:int} rows, total, total_pages, paged.
	 */
	private function get_wallet_rows( $search, $per_page, $paged, $order_sql = '( balance + 0 ) DESC, u.ID ASC' ) {
		global $wpdb;

		$cap_key = $wpdb->get_blog_prefix() . 'capabilities';

		/*
		 * Include every customer, not just users that already have a wallet meta
		 * row — otherwise customers who have never been credited are missing from
		 * the list (and can't be found to top up). Anyone holding a wallet is also
		 * included even if they aren't in the customer role.
		 */
		$where = array( "( cap.meta_value LIKE '%\"customer\"%' OR mw.meta_value IS NOT NULL OR m2.meta_value IS NOT NULL )" );

		if ( '' !== $search ) {
			$ids = $this->search_user_ids( $search );
			if ( empty( $ids ) ) {
				return array( array(), 0, 1, 1 );
			}
			$where[] = 'u.ID IN (' . implode( ',', $ids ) . ')';
		}

		$from = $wpdb->prepare(
			"FROM {$wpdb->users} u
			LEFT JOIN {$wpdb->usermeta} mw ON mw.user_id = u.ID AND mw.meta_key = 'wps_wallet'
			LEFT JOIN {$wpdb->usermeta} m2 ON m2.user_id = u.ID AND m2.meta_key = '_wps_amount'
			LEFT JOIN {$wpdb->usermeta} wid ON wid.user_id = u.ID AND wid.meta_key = 'wps_wallet_id'
			LEFT JOIN {$wpdb->usermeta} cap ON cap.user_id = u.ID AND cap.meta_key = %s
			WHERE " . implode( ' AND ', $where ),
			$cap_key
		);

		$total  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT u.ID) {$from}" ); // phpcs:ignore WordPress.DB
		$pages  = (int) max( 1, ceil( $total / $per_page ) );
		$paged  = min( max( 1, $paged ), $pages );
		$offset = ( $paged - 1 ) * $per_page;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT u.ID, u.display_name, u.user_email,
					MAX( wid.meta_value ) AS wallet_id,
					COALESCE( NULLIF( mw.meta_value, '' ), NULLIF( m2.meta_value, '' ), '0' ) AS balance
				{$from}
				GROUP BY u.ID
				ORDER BY {$order_sql}
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		return array( (array) $rows, $total, $pages, $paged );
	}

	/**
	 * "Wallet List" tab — users that have a wallet, with balances and actions.
	 *
	 * @return void
	 */
	private function render_list() {
		$per_page = isset( $_GET['per_page'] ) ? max( 1, absint( $_GET['per_page'] ) ) : 25; // phpcs:ignore WordPress.Security.NonceVerification
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		// Sortable columns: request key => safe ORDER BY expression.
		$columns = array(
			'id'      => 'u.ID',
			'wallet'  => 'wallet_id',
			'name'    => 'u.display_name',
			'email'   => 'u.user_email',
			'balance' => '( balance + 0 )',
		);
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'balance'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $columns[ $orderby ] ) ) {
			$orderby = 'balance';
		}
		$order     = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification
		$order_sql = $columns[ $orderby ] . ' ' . $order . ', u.ID ' . $order;

		list( $users, $total, $pages, $paged ) = $this->get_wallet_rows( $search, $per_page, $paged, $order_sql );

		$sort = array(
			'orderby' => $orderby,
			'order'   => $order,
			'base'    => array( 'tab' => 'list', 'per_page' => $per_page, 's' => $search ),
		);
		?>
		<div class="aaraa-wallet__panel">
			<form method="get" class="aaraa-wallet__toolbar">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<input type="hidden" name="tab" value="list" />
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
				<input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $order ) ); ?>" />
				<label class="aaraa-wallet__show">
					<?php esc_html_e( 'Show', 'aaraa-white-label-admin' ); ?>
					<select name="per_page" onchange="this.form.submit()">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $per_page, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php esc_html_e( 'entries', 'aaraa-white-label-admin' ); ?>
				</label>
				<span class="aaraa-wallet__search">
					<label><?php esc_html_e( 'Search:', 'aaraa-white-label-admin' ); ?>
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'User ID, wallet ID, name, mobile or email', 'aaraa-white-label-admin' ); ?>" />
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></button>
					<?php if ( '' !== $search ) : ?>
						<a class="button" href="<?php echo esc_url( $this->url( array( 'tab' => 'list' ) ) ); ?>"><?php esc_html_e( 'Clear', 'aaraa-white-label-admin' ); ?></a>
					<?php endif; ?>
				</span>
			</form>

			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<?php
						$this->sort_th( __( 'User ID', 'aaraa-white-label-admin' ), 'id', $sort );
						$this->sort_th( __( 'Wallet ID', 'aaraa-white-label-admin' ), 'wallet', $sort );
						$this->sort_th( __( 'Name', 'aaraa-white-label-admin' ), 'name', $sort );
						$this->sort_th( __( 'Email', 'aaraa-white-label-admin' ), 'email', $sort );
						$this->sort_th( __( 'Wallet Balance', 'aaraa-white-label-admin' ), 'balance', $sort );
						?>
						<th><?php esc_html_e( 'Actions', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No wallets found.', 'aaraa-white-label-admin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $users as $user ) : ?>
							<?php
							$balance   = (float) $user->balance;
							$wallet_id = ! empty( $user->wallet_id ) ? $user->wallet_id : __( 'Not Generated', 'aaraa-white-label-admin' );
							?>
							<tr>
								<td><?php echo esc_html( $user->ID ); ?></td>
								<td><?php echo esc_html( $wallet_id ); ?></td>
								<td><?php echo esc_html( $user->display_name ); ?></td>
								<td><?php echo esc_html( $user->user_email ); ?></td>
								<td class="aaraa-wallet__amt"><?php echo wp_kses_post( $this->money( $balance ) ); ?></td>
								<td class="aaraa-wallet__actions">
									<a class="button button-small aaraa-wallet__edit"
										href="<?php echo esc_url( $this->url( array( 'action' => 'edit', 'user' => $user->ID ) ) ); ?>"
										data-id="<?php echo esc_attr( $user->ID ); ?>"
										data-name="<?php echo esc_attr( $user->display_name . ' — ' . $user->user_email ); ?>"
										data-balance="<?php echo esc_attr( html_entity_decode( wp_strip_all_tags( $this->money( $balance ) ) ) ); ?>"><?php esc_html_e( 'Edit', 'aaraa-white-label-admin' ); ?></a>
									<a class="button button-small" href="<?php echo esc_url( $this->url( array( 'action' => 'view', 'user' => $user->ID ) ) ); ?>"><?php esc_html_e( 'View', 'aaraa-white-label-admin' ); ?></a>
									<a class="button button-small aaraa-wallet__del" href="<?php echo esc_url( wp_nonce_url( $this->url( array( 'action' => 'delete', 'user' => $user->ID ) ), 'aaraa_wallet_delete_' . $user->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this wallet? The balance will be cleared.', 'aaraa-white-label-admin' ) ); ?>');"><?php esc_html_e( 'Delete', 'aaraa-white-label-admin' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php $this->pager( $paged, $pages, array( 'tab' => 'list', 'per_page' => $per_page, 's' => $search, 'orderby' => $orderby, 'order' => strtolower( $order ) ), $total ); ?>
		</div>
		<?php
	}

	/**
	 * "Wallet Transactions" tab — every transaction, newest first, with the login
	 * username of whoever created it.
	 *
	 * @return void
	 */
	private function render_transactions() {
		global $wpdb;
		$per_page = isset( $_GET['per_page'] ) ? max( 1, absint( $_GET['per_page'] ) ) : 25; // phpcs:ignore WordPress.Security.NonceVerification
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$from     = $this->clean_date( isset( $_GET['from'] ) ? wp_unslash( $_GET['from'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$to       = $this->clean_date( isset( $_GET['to'] ) ? wp_unslash( $_GET['to'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! Customers_Admin::wallet_table_exists() ) {
			echo '<div class="aaraa-wallet__panel"><p>' . esc_html__( 'The wallet plugin transaction table was not found.', 'aaraa-white-label-admin' ) . '</p></div>';
			return;
		}
		Customers_Admin::ensure_wallet_created_by();

		// Sortable columns: request key => safe ORDER BY expression.
		$columns = array(
			'date'       => 't.date',
			'user_id'    => 't.user_id',
			'customer'   => 'u.display_name',
			'type'       => 't.transaction_type_1',
			'amount'     => 't.amount+0',
			'details'    => 't.transaction_type',
			'note'       => 't.note',
			'created_by' => 't.created_by',
		);
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $columns[ $orderby ] ) ) {
			$orderby = 'date';
		}
		$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification
		// Stable tiebreak by id in the same direction.
		$order_sql = $columns[ $orderby ] . ' ' . $order . ', t.id ' . $order;

		$table = $this->table();

		// Build WHERE from search AND an optional date range, combined with AND.
		$clauses = array();
		$args    = array();

		// Optional search across customer id, wallet id, name, email and mobile.
		if ( '' !== $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$conds = array( 'u.display_name LIKE %s', 'u.user_email LIKE %s' );
			$args[] = $like;
			$args[] = $like;
			if ( ctype_digit( $search ) ) {
				$conds[] = 't.user_id = %d';
				$args[]  = (int) $search;
			}
			$conds[] = "t.user_id IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ( 'billing_phone', 'mobile', 'shipping_phone' ) AND meta_value LIKE %s )";
			$args[]  = $like;
			// Wallet ID (wps_wallet_id) match.
			$conds[] = "t.user_id IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wps_wallet_id' AND meta_value LIKE %s )";
			$args[]  = $like;
			$clauses[] = '( ' . implode( ' OR ', $conds ) . ' )';
		}

		/*
		 * Date range. The `date` column is stored in UTC (the wallet plugin writes
		 * gmdate()), while the picker reflects the site timezone (IST). Convert the
		 * chosen local day boundaries to UTC so the range matches what the admin sees.
		 */
		if ( '' !== $from ) {
			$clauses[] = 't.date >= %s';
			$args[]    = get_gmt_from_date( $from . ' 00:00:00' );
		}
		if ( '' !== $to ) {
			$clauses[] = 't.date <= %s';
			$args[]    = get_gmt_from_date( $to . ' 23:59:59' );
		}

		$where = $clauses ? ( 'WHERE ' . implode( ' AND ', $clauses ) ) : '';

		$count_sql = "SELECT COUNT(*) FROM {$table} t LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID {$where}";
		$total     = (int) ( empty( $args )
			? $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB
			: $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) ); // phpcs:ignore WordPress.DB

		$pages  = (int) max( 1, ceil( $total / $per_page ) );
		$paged  = min( $paged, $pages );
		$offset = ( $paged - 1 ) * $per_page;

		$list_sql = "SELECT t.*, u.display_name, u.user_email FROM {$table} t LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID {$where} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $args, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB

		$this->render_transaction_search( $search, $per_page, $from, $to );

		$this->render_transaction_table(
			(array) $rows,
			true,
			array(
				'orderby' => $orderby,
				'order'   => $order,
				'base'    => array( 'tab' => 'transactions', 'per_page' => $per_page, 's' => $search, 'from' => $from, 'to' => $to ),
			)
		);
		$this->pager( $paged, $pages, array( 'tab' => 'transactions', 'per_page' => $per_page, 's' => $search, 'from' => $from, 'to' => $to, 'orderby' => $orderby, 'order' => strtolower( $order ) ), $total );
	}

	/**
	 * Validate a Y-m-d date string, returning '' if it is not a real date.
	 *
	 * @param string $raw Raw input.
	 * @return string Y-m-d or ''.
	 */
	private function clean_date( $raw ) {
		$raw = trim( sanitize_text_field( (string) $raw ) );
		if ( '' === $raw || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return '';
		}
		$parts = explode( '-', $raw );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $raw : '';
	}

	/**
	 * Search box for the transactions tab (customer id, name, email or mobile)
	 * plus a From/To date range on the transaction date.
	 *
	 * @param string $search   Current search term.
	 * @param int    $per_page Rows per page (preserved across searches).
	 * @param string $from     From date (Y-m-d) or ''.
	 * @param string $to       To date (Y-m-d) or ''.
	 * @return void
	 */
	private function render_transaction_search( $search, $per_page, $from = '', $to = '' ) {
		// Preserve the active sort when changing entries-per-page or searching.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<form method="get" class="aaraa-wallet__search" style="margin:0 0 12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
			<input type="hidden" name="tab" value="transactions" />
			<?php if ( '' !== $orderby ) : ?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
			<?php endif; ?>
			<?php if ( '' !== $order ) : ?>
				<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
			<?php endif; ?>
			<label class="aaraa-wallet__show" style="display:flex;gap:6px;align-items:center;">
				<?php esc_html_e( 'Show', 'aaraa-white-label-admin' ); ?>
				<select name="per_page" onchange="this.form.submit()">
					<?php foreach ( array( 25, 50, 100, 200, 500 ) as $opt ) : ?>
						<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $per_page, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php esc_html_e( 'entries', 'aaraa-white-label-admin' ); ?>
			</label>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" class="regular-text" style="min-width:280px;" placeholder="<?php esc_attr_e( 'Search user id, wallet id, name, email or mobile', 'aaraa-white-label-admin' ); ?>" />
			<label class="aaraa-wallet__show" style="display:flex;gap:6px;align-items:center;">
				<?php esc_html_e( 'From', 'aaraa-white-label-admin' ); ?>
				<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" max="<?php echo esc_attr( $to ); ?>" />
			</label>
			<label class="aaraa-wallet__show" style="display:flex;gap:6px;align-items:center;">
				<?php esc_html_e( 'To', 'aaraa-white-label-admin' ); ?>
				<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" min="<?php echo esc_attr( $from ); ?>" />
			</label>
			<button type="submit" class="button"><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></button>
			<?php if ( '' !== $search || '' !== $from || '' !== $to ) : ?>
				<a class="button" href="<?php echo esc_url( $this->url( array( 'tab' => 'transactions', 'per_page' => $per_page ) ) ); ?>"><?php esc_html_e( 'Clear', 'aaraa-white-label-admin' ); ?></a>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * A single user's transactions (the "View" action).
	 *
	 * @param int $user_id Customer ID.
	 * @return void
	 */
	private function render_user_transactions( $user_id ) {
		global $wpdb;
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<div class="aaraa-wallet__panel"><p>' . esc_html__( 'User not found.', 'aaraa-white-label-admin' ) . '</p></div>';
			return;
		}
		$balance = Customers_Admin::get_wallet_balance( $user_id );
		?>
		<p><a class="button" href="<?php echo esc_url( $this->url() ); ?>">&larr; <?php esc_html_e( 'Back to Wallet List', 'aaraa-white-label-admin' ); ?></a></p>
		<div class="aaraa-wallet__panel">
			<h2 class="aaraa-wallet__uhead">
				<?php echo esc_html( $user->display_name ); ?>
				<span class="aaraa-wallet__ubal"><?php echo wp_kses_post( $this->money( $balance ) ); ?></span>
			</h2>
			<p class="aaraa-wallet__umeta"><?php echo esc_html( $user->user_email ); ?> &middot; <?php echo esc_html( 'ID ' . $user->ID ); ?>
				&middot; <a href="<?php echo esc_url( $this->url( array( 'action' => 'edit', 'user' => $user->ID ) ) ); ?>"><?php esc_html_e( 'Edit wallet', 'aaraa-white-label-admin' ); ?></a>
			</p>
			<?php
			if ( Customers_Admin::wallet_table_exists() ) {
				Customers_Admin::ensure_wallet_created_by();
				$table = $this->table();
				$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB
					$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 200", $user_id )
				);
				$this->render_transaction_table( (array) $rows, false );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render a transaction table.
	 *
	 * @param array      $rows      Transaction rows.
	 * @param bool       $with_user Whether to show the customer column.
	 * @param array|null $sort      Sorting context: orderby, order, base args. Null = no sort UI.
	 * @return void
	 */
	private function render_transaction_table( $rows, $with_user, $sort = null ) {
		?>
		<div class="aaraa-wallet__panel">
			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<?php
						$this->sort_th( __( 'Date', 'aaraa-white-label-admin' ), 'date', $sort );
						if ( $with_user ) {
							$this->sort_th( __( 'User ID', 'aaraa-white-label-admin' ), 'user_id', $sort );
							$this->sort_th( __( 'Customer', 'aaraa-white-label-admin' ), 'customer', $sort );
						}
						$this->sort_th( __( 'Type', 'aaraa-white-label-admin' ), 'type', $sort );
						$this->sort_th( __( 'Amount', 'aaraa-white-label-admin' ), 'amount', $sort );
						$this->sort_th( __( 'Details', 'aaraa-white-label-admin' ), 'details', $sort );
						$this->sort_th( __( 'Note', 'aaraa-white-label-admin' ), 'note', $sort );
						$this->sort_th( __( 'Created by', 'aaraa-white-label-admin' ), 'created_by', $sort );
						?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="<?php echo $with_user ? 8 : 6; ?>"><?php esc_html_e( 'No transactions yet.', 'aaraa-white-label-admin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$type    = (string) $row->transaction_type_1;
							$is_cred = 'credit' === $type;
							// The wallet plugin stores `date` in UTC (gmdate). Convert to
							// the site timezone (IST) for display.
							$date    = $row->date ? get_date_from_gmt( $row->date, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
							?>
							<tr>
								<td><?php echo esc_html( $date ); ?></td>
								<?php if ( $with_user ) : ?>
									<td><?php echo (int) $row->user_id; ?></td>
									<td><?php echo esc_html( $row->display_name ? $row->display_name : ( '#' . (int) $row->user_id ) ); ?></td>
								<?php endif; ?>
								<td>
									<span class="aaraa-wallet__pill <?php echo $is_cred ? 'is-green' : 'is-red'; ?>">
										<?php echo $is_cred ? esc_html__( 'Credit', 'aaraa-white-label-admin' ) : esc_html__( 'Debit', 'aaraa-white-label-admin' ); ?>
									</span>
								</td>
								<td class="aaraa-wallet__amt"><?php echo wp_kses_post( $this->money( (float) $row->amount ) ); ?></td>
								<td><?php echo esc_html( $row->transaction_type ); ?></td>
								<td><?php echo esc_html( $row->note ); ?></td>
								<td><?php echo esc_html( $this->creator_login( $row ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render a sortable table header cell with an asc/desc arrow.
	 *
	 * When $sort is null (e.g. the single-customer view) a plain, unlinked
	 * header is rendered instead.
	 *
	 * @param string     $label Column label.
	 * @param string     $key   Sort key (matches the orderby whitelist).
	 * @param array|null $sort  Sorting context: orderby, order, base args.
	 * @return void
	 */
	private function sort_th( $label, $key, $sort ) {
		if ( empty( $sort ) || ! is_array( $sort ) ) {
			echo '<th>' . esc_html( $label ) . '</th>';
			return;
		}

		$active   = ( $sort['orderby'] === $key );
		$cur_dir  = strtoupper( $sort['order'] );
		$next_dir = ( $active && 'ASC' === $cur_dir ) ? 'desc' : 'asc';
		// Arrow: active shows the current direction; inactive shows a faint both-ways hint.
		$arrow = $active ? ( 'ASC' === $cur_dir ? '▲' : '▼' ) : '<span style="opacity:.35">↕</span>';

		$url = $this->url(
			array_merge(
				(array) $sort['base'],
				array( 'orderby' => $key, 'order' => $next_dir, 'paged' => 1 )
			)
		);

		echo '<th' . ( $active ? ' class="sorted ' . esc_attr( strtolower( $cur_dir ) ) . '"' : '' ) . '>'
			. '<a href="' . esc_url( $url ) . '" style="text-decoration:none;white-space:nowrap;">'
			. esc_html( $label ) . ' ' . $arrow // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</a></th>';
	}

	/**
	 * Add / Edit wallet form. When $user_id is set the user is fixed (Edit).
	 *
	 * @param int $user_id Customer ID for Edit, 0 for Add.
	 * @return void
	 */
	private function render_form( $user_id = 0 ) {
		$user    = $user_id ? get_userdata( $user_id ) : null;
		$is_edit = (bool) $user;
		$balance = $is_edit ? Customers_Admin::get_wallet_balance( $user->ID ) : 0.0;
		?>
		<p><a class="button" href="<?php echo esc_url( $this->url() ); ?>">&larr; <?php esc_html_e( 'Back to Wallet List', 'aaraa-white-label-admin' ); ?></a></p>
		<div class="aaraa-wallet__panel aaraa-wallet__formwrap">
			<h2><?php echo $is_edit ? esc_html__( 'Edit Wallet', 'aaraa-white-label-admin' ) : esc_html__( 'Add Wallet', 'aaraa-white-label-admin' ); ?></h2>

			<form method="post" class="aaraa-wallet__form">
				<?php wp_nonce_field( 'aaraa_wallet_adjust' ); ?>
					<input type="hidden" name="aaraa_wallet_action" value="wallet_adjust" />
					<input type="hidden" name="page" value="aaraa-wallet" />

				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
					<p class="aaraa-wallet__field">
						<label><?php esc_html_e( 'Customer', 'aaraa-white-label-admin' ); ?></label>
						<span class="aaraa-wallet__static"><?php echo esc_html( $user->display_name . ' — ' . $user->user_email ); ?></span>
					</p>
					<p class="aaraa-wallet__field">
						<label><?php esc_html_e( 'Current balance', 'aaraa-white-label-admin' ); ?></label>
						<span class="aaraa-wallet__static"><?php echo wp_kses_post( $this->money( $balance ) ); ?></span>
					</p>
				<?php else : ?>
					<p class="aaraa-wallet__field">
						<label for="aaraa-user-query"><?php esc_html_e( 'Customer (email, username or user ID)', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="aaraa-user-query" name="user_query" required class="regular-text" placeholder="customer@example.com" />
					</p>
				<?php endif; ?>

				<p class="aaraa-wallet__field">
					<label><?php esc_html_e( 'Transaction type', 'aaraa-white-label-admin' ); ?></label>
					<label class="aaraa-wallet__radio"><input type="radio" name="wtx_type" value="credit" checked /> <?php esc_html_e( 'Credit (add)', 'aaraa-white-label-admin' ); ?></label>
					<label class="aaraa-wallet__radio"><input type="radio" name="wtx_type" value="debit" /> <?php esc_html_e( 'Debit (subtract)', 'aaraa-white-label-admin' ); ?></label>
				</p>

				<p class="aaraa-wallet__field">
					<label for="aaraa-wtx-amount"><?php esc_html_e( 'Amount', 'aaraa-white-label-admin' ); ?></label>
					<input type="number" id="aaraa-wtx-amount" name="wtx_amount" step="0.01" min="0.01" required class="regular-text" />
				</p>

				<p class="aaraa-wallet__field">
					<label for="aaraa-wtx-note"><?php esc_html_e( 'Note (optional)', 'aaraa-white-label-admin' ); ?></label>
					<input type="text" id="aaraa-wtx-note" name="wtx_note" class="regular-text" />
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Update Wallet', 'aaraa-white-label-admin' ) : esc_html__( 'Add Wallet', 'aaraa-white-label-admin' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Add and Edit wallet modals (hidden until triggered by JS).
	 *
	 * Both post to the same `wallet_adjust` handler as the fallback pages, so the
	 * behaviour is identical whether the popup or the full page is used.
	 *
	 * @return void
	 */
	private function render_modals() {
		?>
		<div class="aaraa-modal" id="aaraa-modal-add" aria-hidden="true">
			<div class="aaraa-modal__backdrop" data-close></div>
			<div class="aaraa-modal__box" role="dialog" aria-modal="true" aria-labelledby="aaraa-modal-add-title">
				<div class="aaraa-modal__head">
					<h2 id="aaraa-modal-add-title"><?php esc_html_e( 'Add Wallet', 'aaraa-white-label-admin' ); ?></h2>
					<button type="button" class="aaraa-modal__x" data-close aria-label="<?php esc_attr_e( 'Close', 'aaraa-white-label-admin' ); ?>">&times;</button>
				</div>
				<form method="post" class="aaraa-modal__body aaraa-wallet__form" data-add-form>
					<?php wp_nonce_field( 'aaraa_wallet_adjust' ); ?>
					<input type="hidden" name="aaraa_wallet_action" value="wallet_adjust" />
					<input type="hidden" name="page" value="aaraa-wallet" />
					<input type="hidden" name="user_id" value="" data-user-id />

					<p class="aaraa-wallet__field aaraa-wallet__ac">
						<label for="aaraa-add-user-search"><?php esc_html_e( 'Customer (search by name, mobile or email)', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="aaraa-add-user-search" name="user_query" autocomplete="off" class="regular-text" data-user-search placeholder="<?php esc_attr_e( 'Start typing a name, number or email…', 'aaraa-white-label-admin' ); ?>" />
						<span class="aaraa-wallet__chosen" data-chosen hidden></span>
						<ul class="aaraa-wallet__results" data-results hidden></ul>
					</p>

					<p class="aaraa-wallet__field">
						<label><?php esc_html_e( 'Transaction type', 'aaraa-white-label-admin' ); ?></label>
						<span>
							<label class="aaraa-wallet__radio"><input type="radio" name="wtx_type" value="credit" checked /> <?php esc_html_e( 'Credit (add)', 'aaraa-white-label-admin' ); ?></label>
							<label class="aaraa-wallet__radio"><input type="radio" name="wtx_type" value="debit" /> <?php esc_html_e( 'Debit (subtract)', 'aaraa-white-label-admin' ); ?></label>
						</span>
					</p>
					<p class="aaraa-wallet__field">
						<label for="aaraa-add-amount"><?php esc_html_e( 'Amount', 'aaraa-white-label-admin' ); ?></label>
						<input type="number" id="aaraa-add-amount" name="wtx_amount" step="0.01" min="0.01" required class="regular-text" />
					</p>
					<p class="aaraa-wallet__field">
						<label for="aaraa-add-note"><?php esc_html_e( 'Note (optional)', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="aaraa-add-note" name="wtx_note" class="regular-text" />
					</p>

					<div class="aaraa-modal__foot">
						<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'aaraa-white-label-admin' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Wallet', 'aaraa-white-label-admin' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<div class="aaraa-modal" id="aaraa-modal-edit" aria-hidden="true">
			<div class="aaraa-modal__backdrop" data-close></div>
			<div class="aaraa-modal__box" role="dialog" aria-modal="true" aria-labelledby="aaraa-modal-edit-title">
				<div class="aaraa-modal__head">
					<h2 id="aaraa-modal-edit-title"><?php esc_html_e( 'Edit Wallet', 'aaraa-white-label-admin' ); ?></h2>
					<button type="button" class="aaraa-modal__x" data-close aria-label="<?php esc_attr_e( 'Close', 'aaraa-white-label-admin' ); ?>">&times;</button>
				</div>
				<form method="post" class="aaraa-modal__body aaraa-wallet__form">
					<?php wp_nonce_field( 'aaraa_wallet_adjust' ); ?>
					<input type="hidden" name="aaraa_wallet_action" value="wallet_adjust" />
					<input type="hidden" name="page" value="aaraa-wallet" />
					<input type="hidden" name="user_id" value="" data-user-id />

					<p class="aaraa-wallet__field">
						<label><?php esc_html_e( 'Customer', 'aaraa-white-label-admin' ); ?></label>
						<span class="aaraa-wallet__static" data-edit-name></span>
					</p>
					<p class="aaraa-wallet__field">
						<label><?php esc_html_e( 'Current balance', 'aaraa-white-label-admin' ); ?></label>
						<span class="aaraa-wallet__static" data-edit-balance></span>
					</p>
					<p class="aaraa-wallet__field">
						<label><?php esc_html_e( 'Transaction type', 'aaraa-white-label-admin' ); ?></label>
						<span>
							<label class="aaraa-wallet__radio"><input type="radio" name="wtx_type" value="credit" checked /> <?php esc_html_e( 'Credit (add)', 'aaraa-white-label-admin' ); ?></label>
							<label class="aaraa-wallet__radio"><input type="radio" name="wtx_type" value="debit" /> <?php esc_html_e( 'Debit (subtract)', 'aaraa-white-label-admin' ); ?></label>
						</span>
					</p>
					<p class="aaraa-wallet__field">
						<label for="aaraa-edit-amount"><?php esc_html_e( 'Amount', 'aaraa-white-label-admin' ); ?></label>
						<input type="number" id="aaraa-edit-amount" name="wtx_amount" step="0.01" min="0.01" required class="regular-text" />
					</p>
					<p class="aaraa-wallet__field">
						<label for="aaraa-edit-note"><?php esc_html_e( 'Note (optional)', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="aaraa-edit-note" name="wtx_note" class="regular-text" />
					</p>

					<div class="aaraa-modal__foot">
						<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'aaraa-white-label-admin' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Update Wallet', 'aaraa-white-label-admin' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a pagination bar.
	 *
	 * @param int   $current Current page.
	 * @param int   $pages   Total pages.
	 * @param array $base    Base query args to preserve.
	 * @param int   $total   Total items (for the count label).
	 * @return void
	 */
	private function pager( $current, $pages, $base, $total ) {
		echo '<div class="aaraa-wallet__pager tablenav">';
		echo '<span class="displaying-num">' . esc_html( sprintf( _n( '%s item', '%s items', $total, 'aaraa-white-label-admin' ), number_format_i18n( $total ) ) ) . '</span>';
		if ( $pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg( array_merge( array( 'page' => self::PAGE ), $base, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
					'format'    => '',
					'current'   => $current,
					'total'     => $pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			);
			if ( $links ) {
				echo '<span class="pagination-links">' . wp_kses_post( $links ) . '</span>';
			}
		}
		echo '</div>';
	}
}
