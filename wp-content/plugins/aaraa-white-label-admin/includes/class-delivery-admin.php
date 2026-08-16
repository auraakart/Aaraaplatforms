<?php
/**
 * Delivery management screens: Hubs, Delivery Boys and Delivery Slots.
 *
 * Hubs and Delivery Boys read/write the data owned by wc-frontend-manager-delivery:
 *   - hubs:          table `{prefix}wcfm_delivery_hubs` (ID, name, address, area, status)
 *   - delivery boys: users in the `wcfm_delivery_boy` role, with
 *                    `_wcfmd_delivery_hub` (hub id) and `billing_phone` meta
 *
 * Delivery Slots have no equivalent in that plugin, so they live in our own
 * `{prefix}aaraa_delivery_slots` table.
 *
 * Forms post to the current URL and carry their intent in a hidden
 * `aaraa_delivery_action` field — never an admin_url()-built action, which this
 * plugin filters and which can redirect a POST into a body-less GET.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Hubs / Delivery Boys / Slots controller.
 */
class Delivery_Admin {

	const PAGE_HUBS  = 'aaraa-delivery-hubs';
	const PAGE_BOYS  = 'aaraa-delivery-boys';
	const PAGE_SLOTS = 'aaraa-delivery-slots';

	const ROLE_BOY = 'wcfm_delivery_boy';

	/**
	 * User meta linking a delivery person to their hub.
	 *
	 * Owned by WCFM Delivery — the underscore prefix is part of the key.
	 */
	const META_BOY_HUB = '_wcfmd_delivery_hub';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Tables / helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * The WCFM delivery hubs table.
	 *
	 * @return string
	 */
	public static function hubs_table() {
		global $wpdb;
		return $wpdb->prefix . 'wcfm_delivery_hubs';
	}

	/**
	 * Our delivery slots table.
	 *
	 * @return string
	 */
	public static function slots_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaraa_delivery_slots';
	}

	/**
	 * Create the slots table (idempotent).
	 *
	 * @return void
	 */
	/**
	 * All delivery slots, ordered for display.
	 *
	 * Shared by every screen that offers a slot picker so the list, and the way
	 * it is ordered, only exists in one place.
	 *
	 * @return array
	 */
	public static function slots_list() {
		global $wpdb;
		$table = self::slots_table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB
			return array();
		}
		return (array) $wpdb->get_results( "SELECT id, name, start_time, end_time, status FROM {$table} ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * All delivery hubs.
	 *
	 * @return array
	 */
	public static function hubs_list() {
		global $wpdb;
		$table = self::hubs_table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB
			return array();
		}
		return (array) $wpdb->get_results( "SELECT ID, name, status FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delivery people, optionally restricted to one hub.
	 *
	 * @param int $hub_id Hub id, 0 for all.
	 * @return array<int, string> id => label.
	 */
	public static function people_list( $hub_id = 0 ) {
		$args = array(
			'role'    => self::ROLE_BOY,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => 200,
		);
		if ( $hub_id ) {
			$args['meta_key']   = self::META_BOY_HUB; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = $hub_id; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$out = array();
		foreach ( get_users( $args ) as $user ) {
			$phone            = get_user_meta( $user->ID, 'billing_phone', true );
			$name             = $user->display_name ? $user->display_name : $user->user_login;
			$out[ $user->ID ] = $phone ? $name . ' (' . $phone . ')' : $name;
		}
		return $out;
	}

	/**
	 * Every assignable delivery person, id => label.
	 *
	 * Merges the wcfm_delivery_boy role list with anyone actually assigned as a
	 * delivery boy on a customer profile, so imported/migrated boys that lack the
	 * role still appear in filters. When narrowed to a hub, only role users are
	 * returned (migrated boys carry no hub meta).
	 *
	 * @param int $hub_id Hub id, 0 for all.
	 * @return array<int, string> id => label.
	 */
	public static function all_delivery_people( $hub_id = 0 ) {
		$people = self::people_list( $hub_id );

		if ( $hub_id ) {
			return $people;
		}

		$key = class_exists( __NAMESPACE__ . '\\Customer_Delivery' ) ? Customer_Delivery::META_BOY : '_wcfmd_delivery_boy';

		global $wpdb;
		$assigned = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> '' AND meta_value <> '0'",
				$key
			)
		);

		foreach ( (array) $assigned as $bid ) {
			$bid = (int) $bid;
			if ( ! $bid || isset( $people[ $bid ] ) ) {
				continue;
			}
			$u = get_userdata( $bid );
			if ( ! $u ) {
				continue;
			}
			$phone          = get_user_meta( $bid, 'billing_phone', true );
			$name           = $u->display_name ? $u->display_name : $u->user_login;
			$people[ $bid ] = $phone ? $name . ' (' . $phone . ')' : $name;
		}

		natcasesort( $people );
		return $people;
	}

	/**
	 * Ensure a user carries the delivery-boy role (self-heals imported boys who
	 * were assigned without it). Returns false only when the user doesn't exist.
	 *
	 * @param int $user_id User id.
	 * @return bool Whether the user exists (and now has the role).
	 */
	public static function ensure_delivery_role( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		if ( ! in_array( self::ROLE_BOY, (array) $user->roles, true ) ) {
			$user->add_role( self::ROLE_BOY );
		}
		return true;
	}

	public static function install() {
		global $wpdb;
		$table   = self::slots_table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			start_time varchar(20) NOT NULL DEFAULT '',
			end_time varchar(20) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Whether the WCFM hubs table exists.
	 *
	 * @return bool
	 */
	private function hubs_table_exists() {
		global $wpdb;
		$table = self::hubs_table();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table; // phpcs:ignore WordPress.DB
	}

	/**
	 * All hubs, for dropdowns.
	 *
	 * @return array
	 */
	private function all_hubs() {
		global $wpdb;
		if ( ! $this->hubs_table_exists() ) {
			return array();
		}
		$table = self::hubs_table();
		return (array) $wpdb->get_results( "SELECT ID, name FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Page URL with query args.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra args.
	 * @return string
	 */
	private function url( $page, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Enqueue the shared admin styles/script on our delivery pages.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $page, array( self::PAGE_HUBS, self::PAGE_BOYS, self::PAGE_SLOTS ), true ) ) {
			return;
		}
		wp_enqueue_style( 'aaraa-admin', AARAA_WLA_URL . 'assets/css/admin.css', array(), aaraa_asset_ver( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'aaraa-wallet', AARAA_WLA_URL . 'assets/js/wallet.js', array( 'jquery' ), aaraa_asset_ver( 'assets/js/wallet.js' ), true );
	}

	/* --------------------------------------------------------------------- *
	 * Write path.
	 * --------------------------------------------------------------------- */

	/**
	 * Dispatch all delivery save/delete actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		$posted = isset( $_POST['aaraa_delivery_action'] ) ? sanitize_key( wp_unslash( $_POST['aaraa_delivery_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $posted ) {
			if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'aaraa_delivery' ) ) {
				$this->bail( self::PAGE_HUBS, 'nonce' );
			}
			if ( ! Customers_Admin::current_user_can_manage() ) {
				wp_die( esc_html__( 'You are not allowed to manage delivery settings.', 'aaraa-white-label-admin' ) );
			}
			if ( 'hub_save' === $posted ) {
				$this->save_hub();
			} elseif ( 'boy_save' === $posted ) {
				$this->save_boy();
			} elseif ( 'slot_save' === $posted ) {
				$this->save_slot();
			}
			return;
		}

		// Delete links (query string).
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $page, array( self::PAGE_HUBS, self::PAGE_BOYS, self::PAGE_SLOTS ), true ) ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'delete' !== $action ) {
			return;
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'aaraa_delivery_delete_' . $id );
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage delivery settings.', 'aaraa-white-label-admin' ) );
		}
		$this->delete_record( $page, $id );
	}

	/**
	 * Insert/update a hub.
	 *
	 * @return void
	 */
	private function save_hub() {
		global $wpdb;
		if ( ! $this->hubs_table_exists() ) {
			$this->bail( self::PAGE_HUBS, 'nohubtable' );
		}
		$id      = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$address = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
		$area    = isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '';
		$status  = isset( $_POST['status'] ) && 'inactive' === $_POST['status'] ? 'inactive' : 'active';

		if ( '' === $name ) {
			$this->bail( self::PAGE_HUBS, 'name' );
		}

		// Same columns/formats the WCFM plugin writes.
		$data   = compact( 'name', 'address', 'area', 'status' );
		$format = array( '%s', '%s', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( self::hubs_table(), $data, array( 'ID' => $id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( self::hubs_table(), $data, $format ); // phpcs:ignore WordPress.DB
		}
		$this->bail( self::PAGE_HUBS, 'saved' );
	}

	/**
	 * Create/update a delivery boy user.
	 *
	 * @return void
	 */
	private function save_boy() {
		$id    = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$hub   = isset( $_POST['hub'] ) ? absint( $_POST['hub'] ) : 0;

		if ( $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				$this->bail( self::PAGE_BOYS, 'error' );
			}
			$update = array( 'ID' => $id );
			if ( $email && $email !== $user->user_email ) {
				$update['user_email'] = $email;
			}
			$update['first_name']   = $first;
			$update['last_name']    = $last;
			$update['display_name'] = trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $user->display_name;
			wp_update_user( $update );
		} else {
			if ( ! $email || ! is_email( $email ) ) {
				$this->bail( self::PAGE_BOYS, 'email' );
			}
			if ( '' === $login ) {
				$login = sanitize_user( current( explode( '@', $email ) ), true );
			}
			if ( username_exists( $login ) ) {
				$login .= '_' . wp_rand( 100, 999 );
			}
			if ( email_exists( $email ) ) {
				$this->bail( self::PAGE_BOYS, 'dupe' );
			}
			$id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 16 ),
					'first_name'   => $first,
					'last_name'    => $last,
					'display_name' => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $login,
					'role'         => self::ROLE_BOY,
				)
			);
			if ( is_wp_error( $id ) ) {
				$this->bail( self::PAGE_BOYS, 'error' );
			}
		}

		// Ensure the WCFM delivery-boy role is applied.
		$user = get_userdata( $id );
		if ( $user && ! in_array( self::ROLE_BOY, (array) $user->roles, true ) ) {
			$user->add_role( self::ROLE_BOY );
		}

		update_user_meta( $id, 'billing_phone', $phone );
		update_user_meta( $id, 'mobile', $phone );

		// The key WCFM reads to link a delivery boy to a hub.
		if ( $hub ) {
			update_user_meta( $id, '_wcfmd_delivery_hub', $hub );
		} else {
			delete_user_meta( $id, '_wcfmd_delivery_hub' );
		}

		$this->bail( self::PAGE_BOYS, 'saved' );
	}

	/**
	 * Insert/update a delivery slot.
	 *
	 * @return void
	 */
	private function save_slot() {
		global $wpdb;
		self::install();

		$id     = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$start  = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '';
		$end    = isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '';
		$order  = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;
		$status = isset( $_POST['status'] ) && 'inactive' === $_POST['status'] ? 'inactive' : 'active';

		if ( '' === $name ) {
			$this->bail( self::PAGE_SLOTS, 'name' );
		}

		$data   = array(
			'name'       => $name,
			'start_time' => $start,
			'end_time'   => $end,
			'sort_order' => $order,
			'status'     => $status,
		);
		$format = array( '%s', '%s', '%s', '%d', '%s' );

		if ( $id ) {
			$wpdb->update( self::slots_table(), $data, array( 'id' => $id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$format[]           = '%s';
			$wpdb->insert( self::slots_table(), $data, $format ); // phpcs:ignore WordPress.DB
		}
		$this->bail( self::PAGE_SLOTS, 'saved' );
	}

	/**
	 * Delete a hub / delivery boy / slot.
	 *
	 * @param string $page Page slug.
	 * @param int    $id   Record ID.
	 * @return void
	 */
	private function delete_record( $page, $id ) {
		global $wpdb;
		if ( ! $id ) {
			$this->bail( $page, 'error' );
		}
		if ( self::PAGE_HUBS === $page ) {
			$wpdb->delete( self::hubs_table(), array( 'ID' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		} elseif ( self::PAGE_SLOTS === $page ) {
			$wpdb->delete( self::slots_table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		} elseif ( self::PAGE_BOYS === $page ) {
			$user = get_userdata( $id );
			// Only ever delete an actual delivery boy.
			if ( $user && in_array( self::ROLE_BOY, (array) $user->roles, true ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $id );
			} else {
				$this->bail( $page, 'error' );
			}
		}
		$this->bail( $page, 'deleted' );
	}

	/**
	 * Redirect back to a page with a notice flag.
	 *
	 * @param string $page   Page slug.
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function bail( $page, $notice ) {
		wp_safe_redirect( $this->url( $page, array( 'notice' => $notice ) ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Notices shared by all three screens.
	 *
	 * @return void
	 */
	private function notices() {
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$map    = array(
			'saved'      => array( 'success', __( 'Saved.', 'aaraa-white-label-admin' ) ),
			'deleted'    => array( 'success', __( 'Deleted.', 'aaraa-white-label-admin' ) ),
			'name'       => array( 'error', __( 'A name is required.', 'aaraa-white-label-admin' ) ),
			'email'      => array( 'error', __( 'A valid email address is required.', 'aaraa-white-label-admin' ) ),
			'dupe'       => array( 'error', __( 'A user with that email already exists.', 'aaraa-white-label-admin' ) ),
			'nonce'      => array( 'error', __( 'Security check failed (the form expired). Reload and try again.', 'aaraa-white-label-admin' ) ),
			'nohubtable' => array( 'error', __( 'The WCFM delivery hubs table was not found. Activate WCFM Delivery first.', 'aaraa-white-label-admin' ) ),
			'error'      => array( 'error', __( 'Could not complete the request.', 'aaraa-white-label-admin' ) ),
		);
		if ( isset( $map[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $map[ $notice ][0] ),
				esc_html( $map[ $notice ][1] )
			);
		}
	}

	/**
	 * Shared page header with an Add button.
	 *
	 * @param string $title  Page title.
	 * @param string $button Add-button label.
	 * @return void
	 */
	private function header( $title, $button ) {
		?>
		<div class="aaraa-wallet__bar">
			<h1 class="aaraa-wallet__title"><?php echo esc_html( $title ); ?></h1>
			<a href="#" class="button button-primary aaraa-wallet__add"><?php echo esc_html( $button ); ?></a>
		</div>
		<?php
	}

	/* ---------------------------- Delivery Hubs --------------------------- */

	/**
	 * Delivery Hubs screen.
	 *
	 * @return void
	 */
	public function render_hubs_page() {
		global $wpdb;
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'aaraa-white-label-admin' ) );
		}
		echo '<div class="wrap aaraa-wallet">';
		$this->header( __( 'Delivery Hubs', 'aaraa-white-label-admin' ), __( '+ Add Hub', 'aaraa-white-label-admin' ) );
		$this->notices();

		if ( ! $this->hubs_table_exists() ) {
			echo '<div class="aaraa-wallet__panel"><p>' . esc_html__( 'The WCFM Delivery hubs table was not found. Activate the WCFM Delivery plugin first.', 'aaraa-white-label-admin' ) . '</p></div></div>';
			return;
		}

		$table = self::hubs_table();
		$rows  = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY ID DESC" ); // phpcs:ignore WordPress.DB
		?>
		<div class="aaraa-wallet__panel">
			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Name', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Address', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Area', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No hubs yet.', 'aaraa-white-label-admin' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->ID ); ?></td>
							<td><strong><?php echo esc_html( $row->name ); ?></strong></td>
							<td><?php echo esc_html( $row->address ); ?></td>
							<td><?php echo esc_html( $row->area ); ?></td>
							<td><span class="aaraa-wallet__pill <?php echo 'active' === $row->status ? 'is-green' : 'is-red'; ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
							<td class="aaraa-wallet__actions">
								<a href="#" class="button button-small aaraa-wallet__edit"
									data-id="<?php echo esc_attr( $row->ID ); ?>"
									data-name="<?php echo esc_attr( $row->name ); ?>"
									data-address="<?php echo esc_attr( $row->address ); ?>"
									data-area="<?php echo esc_attr( $row->area ); ?>"
									data-status="<?php echo esc_attr( $row->status ); ?>"><?php esc_html_e( 'Edit', 'aaraa-white-label-admin' ); ?></a>
								<a class="button button-small aaraa-wallet__del" href="<?php echo esc_url( wp_nonce_url( $this->url( self::PAGE_HUBS, array( 'action' => 'delete', 'id' => $row->ID ) ), 'aaraa_delivery_delete_' . $row->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this hub?', 'aaraa-white-label-admin' ) ); ?>');"><?php esc_html_e( 'Delete', 'aaraa-white-label-admin' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		$this->hub_modal();
		echo '</div>';
	}

	/**
	 * Add/Edit hub popup.
	 *
	 * @return void
	 */
	private function hub_modal() {
		?>
		<div class="aaraa-modal" id="aaraa-modal-add" aria-hidden="true">
			<div class="aaraa-modal__backdrop" data-close></div>
			<div class="aaraa-modal__box" role="dialog" aria-modal="true">
				<div class="aaraa-modal__head">
					<h2 data-modal-title><?php esc_html_e( 'Add Hub', 'aaraa-white-label-admin' ); ?></h2>
					<button type="button" class="aaraa-modal__x" data-close aria-label="<?php esc_attr_e( 'Close', 'aaraa-white-label-admin' ); ?>">&times;</button>
				</div>
				<form method="post" class="aaraa-modal__body aaraa-wallet__form">
					<?php wp_nonce_field( 'aaraa_delivery' ); ?>
					<input type="hidden" name="aaraa_delivery_action" value="hub_save" />
					<input type="hidden" name="record_id" value="" data-record-id />

					<p class="aaraa-wallet__field">
						<label for="hub-name"><?php esc_html_e( 'Hub name', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="hub-name" name="name" required class="regular-text" data-f-name />
					</p>
					<p class="aaraa-wallet__field">
						<label for="hub-address"><?php esc_html_e( 'Address', 'aaraa-white-label-admin' ); ?></label>
						<textarea id="hub-address" name="address" rows="2" class="regular-text" data-f-address></textarea>
					</p>
					<p class="aaraa-wallet__field">
						<label for="hub-area"><?php esc_html_e( 'Area', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="hub-area" name="area" class="regular-text" data-f-area />
					</p>
					<p class="aaraa-wallet__field">
						<label for="hub-status"><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></label>
						<select id="hub-status" name="status" data-f-status>
							<option value="active"><?php esc_html_e( 'Active', 'aaraa-white-label-admin' ); ?></option>
							<option value="inactive"><?php esc_html_e( 'Inactive', 'aaraa-white-label-admin' ); ?></option>
						</select>
					</p>

					<div class="aaraa-modal__foot">
						<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'aaraa-white-label-admin' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Hub', 'aaraa-white-label-admin' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/* --------------------------- Delivery Boys ---------------------------- */

	/**
	 * Delivery Boys screen.
	 *
	 * @return void
	 */
	public function render_boys_page() {
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'aaraa-white-label-admin' ) );
		}
		$per_page = 25;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$args = array(
			'role'    => self::ROLE_BOY,
			'number'  => $per_page,
			'offset'  => ( $paged - 1 ) * $per_page,
			'orderby' => 'ID',
			'order'   => 'DESC',
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		$query = new \WP_User_Query( $args );
		$boys  = (array) $query->get_results();
		$total = (int) $query->get_total();
		$pages = (int) max( 1, ceil( $total / $per_page ) );
		$hubs  = $this->all_hubs();

		$hub_names = array();
		foreach ( $hubs as $h ) {
			$hub_names[ (int) $h->ID ] = $h->name;
		}

		echo '<div class="wrap aaraa-wallet">';
		$this->header( __( 'Delivery Boys', 'aaraa-white-label-admin' ), __( '+ Add Delivery Boy', 'aaraa-white-label-admin' ) );
		$this->notices();
		?>
		<div class="aaraa-wallet__panel">
			<form method="get" class="aaraa-wallet__toolbar">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_BOYS ); ?>" />
				<span></span>
				<span class="aaraa-wallet__search">
					<label><?php esc_html_e( 'Search:', 'aaraa-white-label-admin' ); ?>
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name or email', 'aaraa-white-label-admin' ); ?>" />
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'aaraa-white-label-admin' ); ?></button>
				</span>
			</form>

			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Username', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Name', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Email', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Mobile', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Hub', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $boys ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No delivery boys yet.', 'aaraa-white-label-admin' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $boys as $boy ) : ?>
						<?php
						$phone  = get_user_meta( $boy->ID, 'billing_phone', true );
						$hub_id = (int) get_user_meta( $boy->ID, '_wcfmd_delivery_hub', true );
						$first  = get_user_meta( $boy->ID, 'first_name', true );
						$last   = get_user_meta( $boy->ID, 'last_name', true );
						?>
						<tr>
							<td><?php echo esc_html( $boy->ID ); ?></td>
							<td><strong><?php echo esc_html( $boy->user_login ); ?></strong></td>
							<td><?php echo esc_html( trim( $first . ' ' . $last ) ); ?></td>
							<td><?php echo esc_html( $boy->user_email ); ?></td>
							<td><?php echo esc_html( $phone ); ?></td>
							<td><?php echo esc_html( isset( $hub_names[ $hub_id ] ) ? $hub_names[ $hub_id ] : '—' ); ?></td>
							<td class="aaraa-wallet__actions">
								<a href="#" class="button button-small aaraa-wallet__edit"
									data-id="<?php echo esc_attr( $boy->ID ); ?>"
									data-login="<?php echo esc_attr( $boy->user_login ); ?>"
									data-first="<?php echo esc_attr( $first ); ?>"
									data-last="<?php echo esc_attr( $last ); ?>"
									data-email="<?php echo esc_attr( $boy->user_email ); ?>"
									data-phone="<?php echo esc_attr( $phone ); ?>"
									data-hub="<?php echo esc_attr( $hub_id ); ?>"><?php esc_html_e( 'Edit', 'aaraa-white-label-admin' ); ?></a>
								<a class="button button-small aaraa-wallet__del" href="<?php echo esc_url( wp_nonce_url( $this->url( self::PAGE_BOYS, array( 'action' => 'delete', 'id' => $boy->ID ) ), 'aaraa_delivery_delete_' . $boy->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this delivery boy?', 'aaraa-white-label-admin' ) ); ?>');"><?php esc_html_e( 'Delete', 'aaraa-white-label-admin' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<?php $this->pager( self::PAGE_BOYS, $paged, $pages, $total, $search ); ?>
		</div>
		<?php
		$this->boy_modal( $hubs );
		echo '</div>';
	}

	/**
	 * Add/Edit delivery boy popup.
	 *
	 * @param array $hubs Hub rows.
	 * @return void
	 */
	private function boy_modal( $hubs ) {
		?>
		<div class="aaraa-modal" id="aaraa-modal-add" aria-hidden="true">
			<div class="aaraa-modal__backdrop" data-close></div>
			<div class="aaraa-modal__box" role="dialog" aria-modal="true">
				<div class="aaraa-modal__head">
					<h2 data-modal-title><?php esc_html_e( 'Add Delivery Boy', 'aaraa-white-label-admin' ); ?></h2>
					<button type="button" class="aaraa-modal__x" data-close aria-label="<?php esc_attr_e( 'Close', 'aaraa-white-label-admin' ); ?>">&times;</button>
				</div>
				<form method="post" class="aaraa-modal__body aaraa-wallet__form">
					<?php wp_nonce_field( 'aaraa_delivery' ); ?>
					<input type="hidden" name="aaraa_delivery_action" value="boy_save" />
					<input type="hidden" name="record_id" value="" data-record-id />

					<p class="aaraa-wallet__field">
						<label for="boy-first"><?php esc_html_e( 'First name', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="boy-first" name="first_name" class="regular-text" data-f-first />
					</p>
					<p class="aaraa-wallet__field">
						<label for="boy-last"><?php esc_html_e( 'Last name', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="boy-last" name="last_name" class="regular-text" data-f-last />
					</p>
					<p class="aaraa-wallet__field">
						<label for="boy-email"><?php esc_html_e( 'Email', 'aaraa-white-label-admin' ); ?></label>
						<input type="email" id="boy-email" name="email" required class="regular-text" data-f-email />
					</p>
					<p class="aaraa-wallet__field" data-only-new>
						<label for="boy-login"><?php esc_html_e( 'Username (optional — derived from email)', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="boy-login" name="user_login" class="regular-text" data-f-login />
					</p>
					<p class="aaraa-wallet__field">
						<label for="boy-phone"><?php esc_html_e( 'Mobile', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="boy-phone" name="phone" class="regular-text" data-f-phone />
					</p>
					<p class="aaraa-wallet__field">
						<label for="boy-hub"><?php esc_html_e( 'Delivery hub', 'aaraa-white-label-admin' ); ?></label>
						<select id="boy-hub" name="hub" data-f-hub>
							<option value="0"><?php esc_html_e( '— None —', 'aaraa-white-label-admin' ); ?></option>
							<?php foreach ( $hubs as $hub ) : ?>
								<option value="<?php echo esc_attr( $hub->ID ); ?>"><?php echo esc_html( $hub->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<div class="aaraa-modal__foot">
						<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'aaraa-white-label-admin' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Delivery Boy', 'aaraa-white-label-admin' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/* --------------------------- Delivery Slots --------------------------- */

	/**
	 * Delivery Slots screen.
	 *
	 * @return void
	 */
	public function render_slots_page() {
		global $wpdb;
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'aaraa-white-label-admin' ) );
		}
		self::install();

		$table = self::slots_table();
		$rows  = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore WordPress.DB

		echo '<div class="wrap aaraa-wallet">';
		$this->header( __( 'Delivery Slots', 'aaraa-white-label-admin' ), __( '+ Add Slot', 'aaraa-white-label-admin' ) );
		$this->notices();
		?>
		<div class="aaraa-wallet__panel">
			<table class="widefat striped aaraa-wallet__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Slot name', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'From', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'To', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Order', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No delivery slots yet.', 'aaraa-white-label-admin' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
							<td><strong><?php echo esc_html( $row->name ); ?></strong></td>
							<td><?php echo esc_html( $row->start_time ); ?></td>
							<td><?php echo esc_html( $row->end_time ); ?></td>
							<td><?php echo esc_html( $row->sort_order ); ?></td>
							<td><span class="aaraa-wallet__pill <?php echo 'active' === $row->status ? 'is-green' : 'is-red'; ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
							<td class="aaraa-wallet__actions">
								<a href="#" class="button button-small aaraa-wallet__edit"
									data-id="<?php echo esc_attr( $row->id ); ?>"
									data-name="<?php echo esc_attr( $row->name ); ?>"
									data-start="<?php echo esc_attr( $row->start_time ); ?>"
									data-end="<?php echo esc_attr( $row->end_time ); ?>"
									data-order="<?php echo esc_attr( $row->sort_order ); ?>"
									data-status="<?php echo esc_attr( $row->status ); ?>"><?php esc_html_e( 'Edit', 'aaraa-white-label-admin' ); ?></a>
								<a class="button button-small aaraa-wallet__del" href="<?php echo esc_url( wp_nonce_url( $this->url( self::PAGE_SLOTS, array( 'action' => 'delete', 'id' => $row->id ) ), 'aaraa_delivery_delete_' . $row->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this slot?', 'aaraa-white-label-admin' ) ); ?>');"><?php esc_html_e( 'Delete', 'aaraa-white-label-admin' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		$this->slot_modal();
		echo '</div>';
	}

	/**
	 * Add/Edit slot popup.
	 *
	 * @return void
	 */
	private function slot_modal() {
		?>
		<div class="aaraa-modal" id="aaraa-modal-add" aria-hidden="true">
			<div class="aaraa-modal__backdrop" data-close></div>
			<div class="aaraa-modal__box" role="dialog" aria-modal="true">
				<div class="aaraa-modal__head">
					<h2 data-modal-title><?php esc_html_e( 'Add Slot', 'aaraa-white-label-admin' ); ?></h2>
					<button type="button" class="aaraa-modal__x" data-close aria-label="<?php esc_attr_e( 'Close', 'aaraa-white-label-admin' ); ?>">&times;</button>
				</div>
				<form method="post" class="aaraa-modal__body aaraa-wallet__form">
					<?php wp_nonce_field( 'aaraa_delivery' ); ?>
					<input type="hidden" name="aaraa_delivery_action" value="slot_save" />
					<input type="hidden" name="record_id" value="" data-record-id />

					<p class="aaraa-wallet__field">
						<label for="slot-name"><?php esc_html_e( 'Slot name', 'aaraa-white-label-admin' ); ?></label>
						<input type="text" id="slot-name" name="name" required class="regular-text" placeholder="<?php esc_attr_e( 'Morning', 'aaraa-white-label-admin' ); ?>" data-f-name />
					</p>
					<p class="aaraa-wallet__field">
						<label for="slot-start"><?php esc_html_e( 'From', 'aaraa-white-label-admin' ); ?></label>
						<input type="time" id="slot-start" name="start_time" class="regular-text" data-f-start />
					</p>
					<p class="aaraa-wallet__field">
						<label for="slot-end"><?php esc_html_e( 'To', 'aaraa-white-label-admin' ); ?></label>
						<input type="time" id="slot-end" name="end_time" class="regular-text" data-f-end />
					</p>
					<p class="aaraa-wallet__field">
						<label for="slot-order"><?php esc_html_e( 'Display order', 'aaraa-white-label-admin' ); ?></label>
						<input type="number" id="slot-order" name="sort_order" min="0" value="0" class="regular-text" data-f-order />
					</p>
					<p class="aaraa-wallet__field">
						<label for="slot-status"><?php esc_html_e( 'Status', 'aaraa-white-label-admin' ); ?></label>
						<select id="slot-status" name="status" data-f-status>
							<option value="active"><?php esc_html_e( 'Active', 'aaraa-white-label-admin' ); ?></option>
							<option value="inactive"><?php esc_html_e( 'Inactive', 'aaraa-white-label-admin' ); ?></option>
						</select>
					</p>

					<div class="aaraa-modal__foot">
						<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'aaraa-white-label-admin' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Slot', 'aaraa-white-label-admin' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Simple pagination bar.
	 *
	 * @param string $page    Page slug.
	 * @param int    $current Current page.
	 * @param int    $pages   Total pages.
	 * @param int    $total   Total items.
	 * @param string $search  Active search term.
	 * @return void
	 */
	private function pager( $page, $current, $pages, $total, $search = '' ) {
		echo '<div class="aaraa-wallet__pager tablenav">';
		echo '<span class="displaying-num">' . esc_html( sprintf( _n( '%s item', '%s items', $total, 'aaraa-white-label-admin' ), number_format_i18n( $total ) ) ) . '</span>';
		if ( $pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg(
						array(
							'page'  => $page,
							's'     => $search,
							'paged' => '%#%',
						),
						admin_url( 'admin.php' )
					),
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
