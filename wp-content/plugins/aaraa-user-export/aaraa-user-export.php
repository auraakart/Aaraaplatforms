<?php
/**
 * Plugin Name: Aaraa User Export
 * Description: Export and import WordPress users as CSV, filterable by role on export, with every core profile field and every usermeta key — including ones other export tools gate behind a "hidden meta" upsell — as its own column.
 * Version: 1.0.0
 * Author: Aaraa
 * Text Domain: aaraa-user-export
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * Self-contained: no dependency on any other plugin, so this folder can be
 * copied as-is to any WordPress site.
 *
 * @package Aaraa\UserExport
 */

defined( 'ABSPATH' ) || exit;

/**
 * "Export Users" / "Import Users" admin pages + CSV streaming.
 */
class Aaraa_User_Export {

	const PAGE  = 'aaraa-export-users';
	const QVAR  = 'aaraa_export_users';
	const NONCE = 'aaraa_export_users';
	const FIELD = '_aaraa_ue_nonce';

	const IMPORT_PAGE  = 'aaraa-import-users';
	const IMPORT_NONCE = 'aaraa_import_users';
	const IMPORT_FIELD = '_aaraa_ui_nonce';
	const IMPORT_FLAG  = 'aaraa_ui_submit';

	/**
	 * Core wp_users columns, in the exact order the exporter writes them —
	 * everything else in an imported row's header is treated as usermeta.
	 *
	 * @var string[]
	 */
	const CORE_FIELDS = array( 'ID', 'user_login', 'user_pass', 'user_email', 'user_url', 'user_nicename', 'display_name', 'user_registered', 'user_activation_key', 'user_status', 'roles' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_export' ) );
		add_action( 'admin_init', array( $this, 'maybe_import' ) );
	}

	/**
	 * Add the pages under the native Users menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( current_user_can( 'list_users' ) ) {
			add_submenu_page(
				'users.php',
				__( 'Export Users', 'aaraa-user-export' ),
				__( 'Export Users', 'aaraa-user-export' ),
				'list_users',
				self::PAGE,
				array( $this, 'render_page' )
			);
		}
		if ( current_user_can( 'create_users' ) ) {
			add_submenu_page(
				'users.php',
				__( 'Import Users', 'aaraa-user-export' ),
				__( 'Import Users', 'aaraa-user-export' ),
				'create_users',
				self::IMPORT_PAGE,
				array( $this, 'render_import_page' )
			);
		}
	}

	/**
	 * The filter form.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'aaraa-user-export' ) );
		}

		$roles = wp_roles()->roles;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export Users', 'aaraa-user-export' ); ?></h1>
			<p>
				<?php esc_html_e( 'Exports a CSV of users with every core profile field plus every usermeta key found among them, each as its own column — nothing held back.', 'aaraa-user-export' ); ?>
			</p>
			<form method="get" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php wp_nonce_field( self::NONCE, self::FIELD ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aaraa_ue_role"><?php esc_html_e( 'Role', 'aaraa-user-export' ); ?></label></th>
						<td>
							<select name="aaraa_ue_role" id="aaraa_ue_role">
								<option value=""><?php esc_html_e( 'Any role', 'aaraa-user-export' ); ?></option>
								<?php foreach ( $roles as $slug => $role ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<input type="hidden" name="<?php echo esc_attr( self::QVAR ); ?>" value="1" />
				<?php submit_button( __( 'Export CSV', 'aaraa-user-export' ) ); ?>
			</form>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Export.
	 * --------------------------------------------------------------------- */

	/**
	 * Detect an export request and stream the CSV.
	 *
	 * @return void
	 */
	public function maybe_export() {
		if ( empty( $_GET[ self::QVAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to export.', 'aaraa-user-export' ) );
		}
		check_admin_referer( self::NONCE, self::FIELD );

		$this->stream_export();
	}

	/**
	 * Build and stream the CSV, then exit.
	 *
	 * @return void
	 */
	private function stream_export() {
		$role = isset( $_GET['aaraa_ue_role'] ) ? sanitize_key( wp_unslash( $_GET['aaraa_ue_role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'number'  => -1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		);
		if ( '' !== $role ) {
			$query_args['role'] = $role;
		}

		$query = new WP_User_Query( $query_args );
		$ids   = array_map( 'intval', (array) $query->get_results() );

		// Every usermeta key present across the selected users, so the export
		// carries all of it — nothing held back behind a "hidden meta" gate.
		$meta_keys = $this->meta_keys_for( $ids );

		$out = $this->open_csv( 'users-' . gmdate( 'Ymd-His' ) . '.csv' );

		fputcsv( $out, array_merge( self::CORE_FIELDS, $meta_keys ) );

		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			cache_users( $chunk ); // Primes both the user objects and their meta in one batched query.
			foreach ( $chunk as $id ) {
				$u = get_userdata( $id );
				if ( ! $u ) {
					continue;
				}
				$row = array(
					$u->ID,
					$u->user_login,
					$u->user_pass,
					$u->user_email,
					$u->user_url,
					$u->user_nicename,
					$u->display_name,
					$u->user_registered,
					$u->user_activation_key,
					$u->user_status,
					implode( '|', (array) $u->roles ),
				);
				foreach ( $meta_keys as $key ) {
					$row[] = $this->meta_cell( $id, $key );
				}
				fputcsv( $out, $row );
			}
		}

		fclose( $out );
		exit;
	}

	/**
	 * Every distinct usermeta key present across the given users.
	 *
	 * @param int[] $ids User ids.
	 * @return string[]
	 */
	private function meta_keys_for( array $ids ) {
		global $wpdb;
		if ( empty( $ids ) ) {
			return array();
		}
		// Ids are all cast through intval()/array_map('intval', ...) by the
		// caller, so this interpolation carries no injection risk.
		$id_list = implode( ',', array_map( 'absint', $ids ) );
		$keys    = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE user_id IN ({$id_list}) ORDER BY meta_key ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Session tokens are internal login bookkeeping, not profile data —
		// never useful in an export and can be a very large serialized blob.
		$skip = array( 'session_tokens' );
		return array_values( array_diff( (array) $keys, $skip ) );
	}

	/**
	 * A single meta cell, CSV-safe. Arrays/objects (serialized meta) are
	 * JSON-encoded so the value stays legible and round-trippable instead of
	 * dumping a raw PHP serialize() string.
	 *
	 * @param int    $user_id User id.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private function meta_cell( $user_id, $key ) {
		$value = get_user_meta( $user_id, $key, true );
		if ( is_array( $value ) || is_object( $value ) ) {
			return (string) wp_json_encode( $value );
		}
		return (string) $value;
	}

	/**
	 * Send CSV download headers and return the output stream.
	 *
	 * @param string $filename Download file name.
	 * @return resource
	 */
	private function open_csv( $filename ) {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads unicode correctly.
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * Import.
	 * --------------------------------------------------------------------- */

	/**
	 * The upload form, plus the previous run's result summary when present.
	 *
	 * @return void
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'create_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'aaraa-user-export' ) );
		}

		$result = null;
		if ( isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key    = 'aaraa_ui_result_' . get_current_user_id();
			$result = get_transient( $key );
			delete_transient( $key );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Users', 'aaraa-user-export' ); ?></h1>
			<p>
				<?php esc_html_e( 'Import a CSV in the same format "Export Users" produces — same header row, same columns. A row matches an existing user by ID, then by user_login, then by user_email (in that order) and updates it; otherwise a new user is created. Every column that is not a core profile field is written as user meta.', 'aaraa-user-export' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'user_pass is written exactly as given (it is already a password hash from the export, not plain text) so logins keep working after the move. When a row\'s ID is free on this site, the new user is created with that exact ID, so references in other imported tables (orders, etc.) keep lining up.', 'aaraa-user-export' ); ?>
			</p>

			<?php if ( is_array( $result ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $result['skipped'] ? 'warning' : 'success' ); ?>">
					<p>
						<?php
						printf(
							/* translators: 1: total rows, 2: created, 3: updated, 4: skipped */
							esc_html__( 'Processed %1$d row(s): %2$d created, %3$d updated, %4$d skipped.', 'aaraa-user-export' ),
							(int) $result['total'],
							(int) $result['created'],
							(int) $result['updated'],
							(int) $result['skipped']
						);
						?>
					</p>
					<?php if ( ! empty( $result['errors'] ) ) : ?>
						<ul style="list-style: disc; margin-left: 2em;">
							<?php foreach ( $result['errors'] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( self::IMPORT_NONCE, self::IMPORT_FIELD ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aaraa_ui_csv"><?php esc_html_e( 'CSV file', 'aaraa-user-export' ); ?></label></th>
						<td><input type="file" name="aaraa_ui_csv" id="aaraa_ui_csv" accept=".csv,text/csv" required="required" /></td>
					</tr>
				</table>
				<input type="hidden" name="<?php echo esc_attr( self::IMPORT_FLAG ); ?>" value="1" />
				<?php submit_button( __( 'Import CSV', 'aaraa-user-export' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Detect an upload submission, process it, and redirect back with a result.
	 *
	 * @return void
	 */
	public function maybe_import() {
		if ( empty( $_POST[ self::IMPORT_FLAG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! current_user_can( 'create_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to import users.', 'aaraa-user-export' ) );
		}
		check_admin_referer( self::IMPORT_NONCE, self::IMPORT_FIELD );

		$result = $this->handle_upload();

		set_transient( 'aaraa_ui_result_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::IMPORT_PAGE, 'imported' => '1' ),
				admin_url( 'users.php' )
			)
		);
		exit;
	}

	/**
	 * Validate the upload and hand it off for processing.
	 *
	 * @return array{created:int,updated:int,skipped:int,total:int,errors:string[]}
	 */
	private function handle_upload() {
		$empty = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'total'   => 0,
			'errors'  => array(),
		);

		if ( empty( $_FILES['aaraa_ui_csv']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['aaraa_ui_csv']['error'] ) {
			$empty['errors'][] = __( 'No file was uploaded, or the upload failed.', 'aaraa-user-export' );
			return $empty;
		}

		$tmp_name = $_FILES['aaraa_ui_csv']['tmp_name'];
		if ( ! is_uploaded_file( $tmp_name ) ) {
			$empty['errors'][] = __( 'The uploaded file could not be read.', 'aaraa-user-export' );
			return $empty;
		}

		$name = isset( $_FILES['aaraa_ui_csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['aaraa_ui_csv']['name'] ) ) : '';
		if ( 'csv' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			$empty['errors'][] = __( 'Please upload a .csv file.', 'aaraa-user-export' );
			return $empty;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return $this->process_csv( $tmp_name );
	}

	/**
	 * Read the CSV and import each row.
	 *
	 * @param string $path Path to the uploaded (temp) CSV file.
	 * @return array{created:int,updated:int,skipped:int,total:int,errors:string[]}
	 */
	private function process_csv( $path ) {
		$created = 0;
		$updated = 0;
		$skipped = 0;
		$total   = 0;
		$errors  = array();

		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( ! $handle ) {
			return array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'total'   => 0,
				'errors'  => array( __( 'Could not open the uploaded file.', 'aaraa-user-export' ) ),
			);
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			return array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'total'   => 0,
				'errors'  => array( __( 'The file is empty.', 'aaraa-user-export' ) ),
			);
		}
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] ); // Strip a UTF-8 BOM if present.
		$header    = array_map( 'trim', $header );

		$line = 1;
		while ( ( $cols = fgetcsv( $handle ) ) !== false ) {
			++$line;
			if ( 1 === count( $cols ) && null === $cols[0] ) {
				continue; // Blank line.
			}
			++$total;

			if ( count( $cols ) !== count( $header ) ) {
				++$skipped;
				if ( count( $errors ) < 50 ) {
					$errors[] = sprintf(
						/* translators: %d: line number */
						__( 'Line %d: column count does not match the header row — skipped.', 'aaraa-user-export' ),
						$line
					);
				}
				continue;
			}

			$row    = array_combine( $header, $cols );
			$result = $this->import_row( $row );

			if ( 'created' === $result['status'] ) {
				++$created;
			} elseif ( 'updated' === $result['status'] ) {
				++$updated;
			} else {
				++$skipped;
				if ( count( $errors ) < 50 ) {
					$errors[] = sprintf( 'Line %1$d: %2$s', $line, $result['message'] );
				}
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'total'   => $total,
			'errors'  => $errors,
		);
	}

	/**
	 * Create or update the one user a CSV row describes.
	 *
	 * Matches an existing user, in priority order, by ID, then user_login,
	 * then user_email. When none match, a new user is created — with the
	 * row's exact ID preserved when that ID is free, so a cross-site import
	 * keeps lining up with any other imported table that references it by id.
	 *
	 * @param array<string,string> $row One CSV row, keyed by header.
	 * @return array{status:string,message:string} status is created|updated|skipped.
	 */
	private function import_row( array $row ) {
		$id    = isset( $row['ID'] ) ? absint( $row['ID'] ) : 0;
		$login = isset( $row['user_login'] ) ? sanitize_user( trim( (string) $row['user_login'] ), true ) : '';
		$email = isset( $row['user_email'] ) ? sanitize_email( trim( (string) $row['user_email'] ) ) : '';

		if ( '' === $login ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'missing user_login.', 'aaraa-user-export' ),
			);
		}

		$target = null;
		if ( $id && get_userdata( $id ) ) {
			$target = get_userdata( $id );
		} elseif ( get_user_by( 'login', $login ) ) {
			$target = get_user_by( 'login', $login );
		} elseif ( '' !== $email && get_user_by( 'email', $email ) ) {
			$target = get_user_by( 'email', $email );
		}

		$is_new = ! $target;

		if ( $is_new ) {
			if ( $id && ! get_userdata( $id ) ) {
				$user_id = $this->insert_with_id( $id, $login, $email );
			} else {
				$user_id = wp_insert_user(
					array(
						'user_login' => $login,
						'user_email' => $email,
						'user_pass'  => wp_generate_password( 20 ),
					)
				);
			}
			if ( is_wp_error( $user_id ) || ! $user_id ) {
				return array(
					'status'  => 'skipped',
					'message' => sprintf(
						/* translators: 1: user_login, 2: error message */
						__( '%1$s: %2$s', 'aaraa-user-export' ),
						$login,
						is_wp_error( $user_id ) ? $user_id->get_error_message() : __( 'could not be created.', 'aaraa-user-export' )
					),
				);
			}
			$user_id = (int) $user_id;
		} else {
			$user_id = (int) $target->ID;
		}

		$this->sync_profile_fields( $user_id, $row, $email );
		$this->sync_exact_fields( $user_id, $row );
		$this->sync_roles( $user_id, $row );
		$this->sync_meta( $user_id, $row );

		return array(
			'status'  => $is_new ? 'created' : 'updated',
			'message' => $login,
		);
	}

	/**
	 * Create a user row with an exact, caller-chosen ID — the row is only
	 * reached when that ID is confirmed free. WordPress's own wp_insert_user()
	 * always auto-assigns an ID for a new user, so this writes the row
	 * directly; every other field here is a throwaway placeholder that
	 * {@see sync_profile_fields()} / {@see sync_exact_fields()} immediately
	 * overwrite with the CSV's real values.
	 *
	 * @param int    $id    The id to create the user with.
	 * @param string $login user_login.
	 * @param string $email user_email.
	 * @return int|false The id on success.
	 */
	private function insert_with_id( $id, $login, $email ) {
		global $wpdb;
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->users,
			array(
				'ID'                  => $id,
				'user_login'          => $login,
				'user_pass'           => wp_hash_password( wp_generate_password( 20 ) ),
				'user_nicename'       => sanitize_title( $login ),
				'user_email'          => $email,
				'user_registered'     => current_time( 'mysql', true ),
				'user_activation_key' => '',
				'user_status'         => 0,
				'display_name'        => $login,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( ! $ok ) {
			return false;
		}
		clean_user_cache( $id );
		return $id;
	}

	/**
	 * Sync the profile fields WordPress's own update path can carry safely.
	 *
	 * @param int                   $user_id User id.
	 * @param array<string,string>  $row     CSV row.
	 * @param string                $email   Already-sanitized user_email.
	 * @return void
	 */
	private function sync_profile_fields( $user_id, array $row, $email ) {
		$update = array( 'ID' => $user_id );
		if ( '' !== $email ) {
			$update['user_email'] = $email;
		}
		if ( isset( $row['user_url'] ) && '' !== trim( (string) $row['user_url'] ) ) {
			$update['user_url'] = esc_url_raw( trim( (string) $row['user_url'] ) );
		}
		if ( isset( $row['user_nicename'] ) && '' !== trim( (string) $row['user_nicename'] ) ) {
			$update['user_nicename'] = sanitize_title( trim( (string) $row['user_nicename'] ) );
		}
		if ( isset( $row['display_name'] ) && '' !== trim( (string) $row['display_name'] ) ) {
			$update['display_name'] = trim( (string) $row['display_name'] );
		}
		if ( count( $update ) > 1 ) {
			wp_update_user( $update ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort field sync; row still reports as imported either way.
		}
	}

	/**
	 * Write the fields that must land exactly as given — never through
	 * WordPress's own update path, which would re-hash user_pass or stamp
	 * user_registered with "now" instead of the CSV's original value.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string,string> $row     CSV row.
	 * @return void
	 */
	private function sync_exact_fields( $user_id, array $row ) {
		global $wpdb;

		$direct  = array();
		$formats = array();

		if ( isset( $row['user_pass'] ) && '' !== $row['user_pass'] ) {
			$direct['user_pass'] = $row['user_pass'];
			$formats[]            = '%s';
		}
		if ( isset( $row['user_registered'] ) && '' !== trim( (string) $row['user_registered'] ) ) {
			$direct['user_registered'] = $row['user_registered'];
			$formats[]                  = '%s';
		}
		if ( isset( $row['user_activation_key'] ) && '' !== $row['user_activation_key'] ) {
			$direct['user_activation_key'] = $row['user_activation_key'];
			$formats[]                      = '%s';
		}
		if ( isset( $row['user_status'] ) && '' !== trim( (string) $row['user_status'] ) ) {
			$direct['user_status'] = (int) $row['user_status'];
			$formats[]              = '%d';
		}

		if ( $direct ) {
			$wpdb->update( $wpdb->users, $direct, array( 'ID' => $user_id ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			clean_user_cache( $user_id );
		}
	}

	/**
	 * Replace the user's roles with exactly the ones the row lists.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string,string> $row     CSV row.
	 * @return void
	 */
	private function sync_roles( $user_id, array $row ) {
		if ( ! isset( $row['roles'] ) ) {
			return;
		}
		$roles = array_filter( array_map( 'sanitize_key', explode( '|', (string) $row['roles'] ) ) );

		$user = new WP_User( $user_id );
		$user->set_role( '' ); // Strip whatever roles the user currently has.
		$known = wp_roles()->roles;
		foreach ( $roles as $role ) {
			if ( isset( $known[ $role ] ) ) {
				$user->add_role( $role );
			}
		}
	}

	/**
	 * Write every non-core column as user meta.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string,string> $row     CSV row.
	 * @return void
	 */
	private function sync_meta( $user_id, array $row ) {
		foreach ( $row as $key => $value ) {
			if ( '' === $key || in_array( $key, self::CORE_FIELDS, true ) ) {
				continue;
			}
			update_user_meta( $user_id, $key, $this->decode_meta_value( $value ) );
		}
	}

	/**
	 * Reverse {@see meta_cell()}: a JSON-looking value round-trips back to the
	 * array/object the exporter encoded; anything else is stored as-is.
	 *
	 * @param string $value Raw CSV cell.
	 * @return mixed
	 */
	private function decode_meta_value( $value ) {
		$value = (string) $value;
		$first = substr( ltrim( $value ), 0, 1 );
		if ( '{' !== $first && '[' !== $first ) {
			return $value;
		}
		$decoded = json_decode( $value, true );
		return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $value;
	}
}

add_action(
	'plugins_loaded',
	static function () {
		( new Aaraa_User_Export() )->init();
	}
);
