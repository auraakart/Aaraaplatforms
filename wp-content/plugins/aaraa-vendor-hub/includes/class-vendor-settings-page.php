<?php
/**
 * "Vendor App Config" admin screen: a list of vendors (from wp_vendors), an
 * "Add New Vendor" form that INSERTs a new row, and, per vendor, a form
 * covering every app-config section the mobile app reads via
 * GET /wp-json/aaraakart/v1/app-config.
 *
 * The Edit App Config screen only ever writes the new app-config columns —
 * subdomain, full_domain, db_host/db_port/db_name/db_user/db_pass and status
 * are read-only there and untouched by saving it. Those original columns are
 * only ever written by Add New Vendor, and only as a brand new row — no
 * existing row is ever modified by it.
 *
 * @package Aaraa\VendorHub
 */

namespace Aaraa\VendorHub;

defined( 'ABSPATH' ) || exit;

/**
 * Vendor App Config admin screen.
 */
class Vendor_Settings_Page {

	const PAGE           = 'aaraa-vendor-hub';
	const NONCE          = 'aaraa_vh_save';
	const ACTION         = 'aaraa_vh_save';
	const API_KEY_OPTION = 'aaraa_vh_app_key';
	const API_KEY_NONCE  = 'aaraa_vh_api_key';
	const API_KEY_ACTION = 'aaraa_vh_api_key_save';
	const CREATE_NONCE   = 'aaraa_vh_create';
	const CREATE_ACTION  = 'aaraa_vh_create_vendor';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_init', array( $this, 'handle_api_key_save' ) );
		add_action( 'admin_init', array( $this, 'handle_create_vendor' ) );
	}

	/**
	 * Register the admin menu page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Vendor App Config', 'aaraa-vendor-hub' ),
			__( 'Vendor App Config', 'aaraa-vendor-hub' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-admin-multisite',
			58
		);
	}

	/* --------------------------------------------------------------------- *
	 * Save handlers.
	 * --------------------------------------------------------------------- */

	/**
	 * Persist one vendor's app-config form.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( empty( $_POST[ self::ACTION ] ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'aaraa-vendor-hub' ) );
		}

		$id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
		if ( ! $id || ! Vendor_Model::get( $id ) ) {
			wp_die( esc_html__( 'Unknown vendor.', 'aaraa-vendor-hub' ) );
		}

		$raw = isset( $_POST['cfg'] ) ? wp_unslash( $_POST['cfg'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized field-by-field below.
		$raw = is_array( $raw ) ? $raw : array();

		$sections = array();
		foreach ( Vendor_Model::schema() as $key => $section ) {
			$sections[ $key ] = $this->sanitize_section( isset( $raw[ $key ] ) ? $raw[ $key ] : array(), $section['fields'] );
		}
		$sections['build'] = array(
			'android' => array(
				'packageName'   => sanitize_text_field( $raw['build']['android']['packageName'] ?? '' ),
				'applicationId' => sanitize_text_field( $raw['build']['android']['applicationId'] ?? '' ),
			),
			'ios' => array(
				'bundleId' => sanitize_text_field( $raw['build']['ios']['bundleId'] ?? '' ),
			),
		);
		$sections['banners'] = $this->sanitize_banners( isset( $_POST['banners'] ) ? wp_unslash( $_POST['banners'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in sanitize_banners().

		$brand_name  = isset( $_POST['brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_name'] ) ) : '';
		$app_name    = isset( $_POST['app_name'] ) ? sanitize_text_field( wp_unslash( $_POST['app_name'] ) ) : '';
		$environment = isset( $_POST['environment'] ) ? sanitize_text_field( wp_unslash( $_POST['environment'] ) ) : '';

		Vendor_Model::save( $id, $sections, $brand_name, $app_name, $environment );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE, 'action' => 'edit', 'id' => $id, 'notice' => 'saved' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize one schema section's posted values against its field types.
	 *
	 * @param array<string,mixed>               $raw    Posted values for this section.
	 * @param array<string,array{type:string}>  $fields Field key => meta.
	 * @return array<string,mixed>
	 */
	private function sanitize_section( $raw, $fields ) {
		$out = array();
		foreach ( $fields as $field => $meta ) {
			$val = isset( $raw[ $field ] ) ? $raw[ $field ] : '';
			$out[ $field ] = $this->sanitize_value( $val, $meta['type'] );
		}
		return $out;
	}

	/**
	 * Sanitize the repeating banners list.
	 *
	 * @param array $raw Posted banners[] rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function sanitize_banners( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$fields = Vendor_Model::banner_fields();
		$out    = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			// Skip a fully-empty trailing repeater row.
			if ( empty( $row['imageUrl'] ) && empty( $row['title'] ) ) {
				continue;
			}
			$clean = array( 'id' => isset( $row['id'] ) ? absint( $row['id'] ) : ( count( $out ) + 1 ) );
			foreach ( $fields as $field => $type ) {
				$clean[ $field ] = $this->sanitize_value( isset( $row[ $field ] ) ? $row[ $field ] : '', $type );
			}
			$out[] = $clean;
		}
		return $out;
	}

	/**
	 * Sanitize one scalar value by declared type.
	 *
	 * @param mixed  $val  Raw value.
	 * @param string $type text|url|email|color|bool|secret|textarea|int.
	 * @return mixed
	 */
	private function sanitize_value( $val, $type ) {
		switch ( $type ) {
			case 'url':
			case 'image':
				return esc_url_raw( trim( (string) $val ) );
			case 'email':
				return sanitize_email( (string) $val );
			case 'color':
				$val = trim( (string) $val );
				return preg_match( '/^#?[0-9A-Fa-f]{3,8}$/', $val ) ? ( '#' === substr( $val, 0, 1 ) ? $val : '#' . $val ) : sanitize_text_field( $val );
			case 'bool':
				return ! empty( $val ) && 'false' !== $val && '0' !== $val;
			case 'int':
				return absint( $val );
			case 'textarea':
				return sanitize_textarea_field( (string) $val );
			case 'secret':
			case 'text':
			default:
				return sanitize_text_field( (string) $val );
		}
	}

	/**
	 * Persist the shared App Key used to authorise the REST API.
	 *
	 * @return void
	 */
	public function handle_api_key_save() {
		if ( empty( $_POST[ self::API_KEY_ACTION ] ) ) {
			return;
		}
		check_admin_referer( self::API_KEY_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'aaraa-vendor-hub' ) );
		}
		$key = isset( $_POST['app_key'] ) ? sanitize_text_field( wp_unslash( $_POST['app_key'] ) ) : '';
		update_option( self::API_KEY_OPTION, $key, false );

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'notice' => 'key_saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Create a new wp_vendors row from the Add New Vendor form. Only ever
	 * INSERTs — no existing row is read or modified by this.
	 *
	 * @return void
	 */
	public function handle_create_vendor() {
		if ( empty( $_POST[ self::CREATE_ACTION ] ) ) {
			return;
		}
		check_admin_referer( self::CREATE_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'aaraa-vendor-hub' ) );
		}

		$subdomain = isset( $_POST['subdomain'] ) ? sanitize_title( wp_unslash( $_POST['subdomain'] ) ) : '';
		$data      = array(
			'subdomain'   => $subdomain,
			'full_domain' => isset( $_POST['full_domain'] ) ? sanitize_text_field( wp_unslash( $_POST['full_domain'] ) ) : '',
			'db_host'     => isset( $_POST['db_host'] ) ? sanitize_text_field( wp_unslash( $_POST['db_host'] ) ) : '',
			'db_port'     => isset( $_POST['db_port'] ) ? absint( $_POST['db_port'] ) : 3306,
			'db_name'     => isset( $_POST['db_name'] ) ? sanitize_text_field( wp_unslash( $_POST['db_name'] ) ) : '',
			'db_user'     => isset( $_POST['db_user'] ) ? sanitize_text_field( wp_unslash( $_POST['db_user'] ) ) : '',
			// Not sanitized with sanitize_text_field: a DB password may legitimately
			// contain characters that function strips/mangles (leading/trailing
			// spaces aside). Slashes added by WP's magic-quotes emulation are
			// removed; nothing else is altered.
			'db_pass'     => isset( $_POST['db_pass'] ) ? wp_unslash( $_POST['db_pass'] ) : '',
			'status'      => isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'active', 'suspended', 'inactive' ), true ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
		);

		$result = Vendor_Model::create( $data );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'page' => self::PAGE, 'action' => 'new', 'error' => rawurlencode( $result->get_error_message() ) ),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE, 'action' => 'edit', 'id' => $result, 'notice' => 'created' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Route to the list or edit view.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-vendor-hub' ) );
		}
		if ( ! Vendor_Model::is_migrated() ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' .
				esc_html__( 'The app-config columns are not present on wp_vendors yet. Run wp_vendors_migration.sql, or deactivate and reactivate this plugin.', 'aaraa-vendor-hub' ) .
				'</p></div></div>';
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'edit' === $action && $id ) {
			$this->render_edit( $id );
			return;
		}
		if ( 'new' === $action ) {
			$this->render_new_vendor();
			return;
		}
		$this->render_list();
	}

	/**
	 * Screen notices.
	 *
	 * @return void
	 */
	private function notices() {
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$map    = array(
			'saved'     => __( 'App config saved.', 'aaraa-vendor-hub' ),
			'key_saved' => __( 'App key saved.', 'aaraa-vendor-hub' ),
			'created'   => __( 'Vendor created. Fill in its app config below.', 'aaraa-vendor-hub' ),
		);
		if ( isset( $map[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	/**
	 * The vendor list + the shared App Key box.
	 *
	 * @return void
	 */
	private function render_list() {
		$vendors = Vendor_Model::all();
		$app_key = get_option( self::API_KEY_OPTION, '' );
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Vendor App Config', 'aaraa-vendor-hub' ); ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Vendor', 'aaraa-vendor-hub' ); ?></a>
			</h1>
			<?php $this->notices(); ?>

			<table class="widefat striped" style="max-width:960px;margin-top:12px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Subdomain', 'aaraa-vendor-hub' ); ?></th>
						<th><?php esc_html_e( 'Full Domain', 'aaraa-vendor-hub' ); ?></th>
						<th><?php esc_html_e( 'Status', 'aaraa-vendor-hub' ); ?></th>
						<th><?php esc_html_e( 'Config Version', 'aaraa-vendor-hub' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'aaraa-vendor-hub' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $vendors ) : ?>
						<?php foreach ( $vendors as $v ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $v->subdomain ); ?></strong></td>
								<td><?php echo esc_html( $v->full_domain ); ?></td>
								<td><?php echo esc_html( $v->status ); ?></td>
								<td><?php echo (int) $v->config_version; ?></td>
								<td><?php echo esc_html( $v->updated_at ); ?></td>
								<td>
									<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'action' => 'edit', 'id' => $v->id ), admin_url( 'admin.php' ) ) ); ?>">
										<?php esc_html_e( 'Edit App Config', 'aaraa-vendor-hub' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No vendors found.', 'aaraa-vendor-hub' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;"><?php esc_html_e( 'API Access', 'aaraa-vendor-hub' ); ?></h2>
			<p class="description" style="max-width:680px;">
				<?php esc_html_e( 'GET /wp-json/aaraakart/v1/app-config returns WooCommerce and wallet API credentials in plain text. Set an App Key here and the API will require it on every request (header: X-App-Key). Leave blank and the endpoint stays open to anyone who knows a brand_id — not recommended for production.', 'aaraa-vendor-hub' ); ?>
			</p>
			<form method="post" style="max-width:680px;">
				<?php wp_nonce_field( self::API_KEY_NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::API_KEY_ACTION ); ?>" value="1" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aaraa_vh_app_key"><?php esc_html_e( 'App Key', 'aaraa-vendor-hub' ); ?></label></th>
						<td>
							<input type="text" id="aaraa_vh_app_key" name="app_key" class="regular-text code" value="<?php echo esc_attr( $app_key ); ?>" placeholder="<?php esc_attr_e( 'leave blank to keep the endpoint open', 'aaraa-vendor-hub' ); ?>" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save App Key', 'aaraa-vendor-hub' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * The Add New Vendor form — creates the base wp_vendors row (subdomain,
	 * domain, DB connection, status). App-config fields are filled in
	 * afterwards from Edit App Config once the vendor exists.
	 *
	 * @return void
	 */
	private function render_new_vendor() {
		$back = esc_url( add_query_arg( array( 'page' => self::PAGE ), admin_url( 'admin.php' ) ) );
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Add New Vendor', 'aaraa-vendor-hub' ); ?>
				<a href="<?php echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="page-title-action"><?php esc_html_e( 'Back to vendors', 'aaraa-vendor-hub' ); ?></a>
			</h1>
			<?php $this->notices(); ?>

			<p class="description" style="max-width:680px;">
				<?php esc_html_e( 'Creates a new row in wp_vendors — the store\'s own database connection details, exactly as the existing rows have. App-config (branding, theme, API keys, etc.) is filled in on the next screen after this one is created.', 'aaraa-vendor-hub' ); ?>
			</p>

			<form method="post" style="max-width:640px;">
				<?php wp_nonce_field( self::CREATE_NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::CREATE_ACTION ); ?>" value="1" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="vh_subdomain"><?php esc_html_e( 'Subdomain', 'aaraa-vendor-hub' ); ?></label></th>
						<td><input type="text" id="vh_subdomain" name="subdomain" class="regular-text" required placeholder="acme" /> <p class="description"><?php esc_html_e( 'e.g. "acme" for acme.aaraakart.com. This becomes the brand_id the app-config API is queried with.', 'aaraa-vendor-hub' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="vh_full_domain"><?php esc_html_e( 'Full Domain', 'aaraa-vendor-hub' ); ?></label></th>
						<td><input type="text" id="vh_full_domain" name="full_domain" class="regular-text" placeholder="acme.aaraakart.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="vh_db_host"><?php esc_html_e( 'DB Host', 'aaraa-vendor-hub' ); ?></label></th>
						<td><input type="text" id="vh_db_host" name="db_host" class="regular-text" value="localhost" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="vh_db_port"><?php esc_html_e( 'DB Port', 'aaraa-vendor-hub' ); ?></label></th>
						<td><input type="number" id="vh_db_port" name="db_port" value="3306" min="1" max="65535" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="vh_db_name"><?php esc_html_e( 'DB Name', 'aaraa-vendor-hub' ); ?></label></th>
						<td><input type="text" id="vh_db_name" name="db_name" class="regular-text" required placeholder="admin_acme_proddb" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="vh_db_user"><?php esc_html_e( 'DB User', 'aaraa-vendor-hub' ); ?></label></th>
						<td><input type="text" id="vh_db_user" name="db_user" class="regular-text" required placeholder="admin_acme_proddb" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="vh_db_pass"><?php esc_html_e( 'DB Password', 'aaraa-vendor-hub' ); ?></label></th>
						<td>
							<input type="text" id="vh_db_pass" name="db_pass" class="regular-text code" required autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Stored as typed (the existing rows are not encrypted either). Treat this screen itself as sensitive.', 'aaraa-vendor-hub' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vh_status"><?php esc_html_e( 'Status', 'aaraa-vendor-hub' ); ?></label></th>
						<td>
							<select id="vh_status" name="status">
								<option value="active"><?php esc_html_e( 'active', 'aaraa-vendor-hub' ); ?></option>
								<option value="suspended"><?php esc_html_e( 'suspended', 'aaraa-vendor-hub' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'inactive', 'aaraa-vendor-hub' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Create Vendor', 'aaraa-vendor-hub' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * One vendor's full app-config edit form.
	 *
	 * @param int $id Vendor id.
	 * @return void
	 */
	private function render_edit( $id ) {
		$row = Vendor_Model::get( $id );
		if ( ! $row ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Vendor not found.', 'aaraa-vendor-hub' ) . '</p></div>';
			return;
		}
		$cfg     = Vendor_Model::to_app_config( $row );
		$back    = esc_url( add_query_arg( array( 'page' => self::PAGE ), admin_url( 'admin.php' ) ) );
		?>
		<div class="wrap aaraa-vh-edit">
			<h1>
				<?php echo esc_html( sprintf( /* translators: %s: vendor subdomain */ __( 'App Config — %s', 'aaraa-vendor-hub' ), $row->subdomain ) ); ?>
				<a href="<?php echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="page-title-action"><?php esc_html_e( 'Back to vendors', 'aaraa-vendor-hub' ); ?></a>
			</h1>
			<?php $this->notices(); ?>

			<form method="post">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::ACTION ); ?>" value="1" />
				<input type="hidden" name="vendor_id" value="<?php echo (int) $row->id; ?>" />

				<h2 class="title"><?php esc_html_e( 'Brand', 'aaraa-vendor-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Brand ID', 'aaraa-vendor-hub' ); ?></th>
						<td><code><?php echo esc_html( $row->subdomain ); ?></code> <span class="description"><?php esc_html_e( '(subdomain — read-only here)', 'aaraa-vendor-hub' ); ?></span></td>
					</tr>
					<?php $this->field_row( __( 'Brand Name', 'aaraa-vendor-hub' ), 'brand_name', $cfg['brand']['brandName'], 'text' ); ?>
					<?php $this->field_row( __( 'App Name', 'aaraa-vendor-hub' ), 'app_name', $cfg['brand']['appName'], 'text' ); ?>
					<?php $this->field_row( __( 'Environment', 'aaraa-vendor-hub' ), 'environment', $cfg['brand']['environment'], 'text', 'production' ); ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Build', 'aaraa-vendor-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $this->field_row( __( 'Android package name', 'aaraa-vendor-hub' ), 'cfg[build][android][packageName]', $cfg['build']['android']['packageName'], 'text', 'com.aaraakart.brand' ); ?>
					<?php $this->field_row( __( 'Android application id', 'aaraa-vendor-hub' ), 'cfg[build][android][applicationId]', $cfg['build']['android']['applicationId'], 'text', 'com.aaraakart.brand' ); ?>
					<?php $this->field_row( __( 'iOS bundle id', 'aaraa-vendor-hub' ), 'cfg[build][ios][bundleId]', $cfg['build']['ios']['bundleId'], 'text', 'com.aaraakart.brand' ); ?>
				</table>

				<?php foreach ( Vendor_Model::schema() as $key => $section ) : ?>
					<h2 class="title"><?php echo esc_html( $section['label'] ); ?></h2>
					<table class="form-table" role="presentation">
						<?php foreach ( $section['fields'] as $field => $meta ) : ?>
							<?php $this->field_row( $meta['label'], 'cfg[' . $key . '][' . $field . ']', $cfg[ $section['json_key'] ][ $field ], $meta['type'] ); ?>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>

				<h2 class="title"><?php esc_html_e( 'Banners', 'aaraa-vendor-hub' ); ?></h2>
				<div id="aaraa-vh-banners"></div>
				<p><button type="button" class="button" id="aaraa-vh-banner-add"><?php esc_html_e( 'Add Banner', 'aaraa-vendor-hub' ); ?></button></p>
				<script type="application/json" id="aaraa-vh-banners-data"><?php echo wp_json_encode( $cfg['banners'] ); ?></script>

				<?php submit_button( __( 'Save App Config', 'aaraa-vendor-hub' ) ); ?>
			</form>
		</div>

		<template id="aaraa-vh-banner-row-tpl">
			<div class="aaraa-vh-banner-row" style="border:1px solid #dcdcde;border-radius:6px;padding:12px;margin:0 0 12px;background:#fff;">
				<table class="form-table" role="presentation" style="margin:0;">
					<tr><th style="width:180px;"><?php esc_html_e( 'Image URL', 'aaraa-vendor-hub' ); ?></th><td>
						<div class="aaraa-vh-image-field" style="display:flex;gap:8px;align-items:center;">
							<input type="url" class="regular-text" data-field="imageUrl" />
							<button type="button" class="button aaraa-vh-media-btn"><?php esc_html_e( 'Choose Image', 'aaraa-vendor-hub' ); ?></button>
							<img class="aaraa-vh-media-preview" src="" style="max-height:36px;max-width:60px;display:none;" alt="" />
						</div>
					</td></tr>
					<tr><th><?php esc_html_e( 'Title', 'aaraa-vendor-hub' ); ?></th><td><input type="text" class="regular-text" data-field="title" /></td></tr>
					<tr><th><?php esc_html_e( 'Description', 'aaraa-vendor-hub' ); ?></th><td><input type="text" class="regular-text" data-field="description" /></td></tr>
					<tr><th><?php esc_html_e( 'Action Type', 'aaraa-vendor-hub' ); ?></th><td><input type="text" class="regular-text" data-field="actionType" placeholder="product | category | url | none" /></td></tr>
					<tr><th><?php esc_html_e( 'Action Value', 'aaraa-vendor-hub' ); ?></th><td><input type="text" class="regular-text" data-field="actionValue" /></td></tr>
					<tr><th><?php esc_html_e( 'Sort Order', 'aaraa-vendor-hub' ); ?></th><td><input type="number" class="small-text" data-field="sortOrder" value="1" /></td></tr>
					<tr><th><?php esc_html_e( 'Active', 'aaraa-vendor-hub' ); ?></th><td><label><input type="checkbox" data-field="isActive" checked /> <?php esc_html_e( 'Active', 'aaraa-vendor-hub' ); ?></label></td></tr>
				</table>
				<p style="margin:8px 0 0;"><button type="button" class="button-link-delete aaraa-vh-banner-remove"><?php esc_html_e( 'Remove banner', 'aaraa-vendor-hub' ); ?></button></p>
			</div>
		</template>
		<?php
		wp_enqueue_media();
		wp_enqueue_script( 'aaraa-vh-settings', AARAA_VH_URL . 'assets/js/vendor-settings.js', array( 'jquery' ), AARAA_VH_VERSION, true );
	}

	/**
	 * One form-table row.
	 *
	 * @param string $label       Field label.
	 * @param string $name        Input name attribute.
	 * @param mixed  $value       Current value.
	 * @param string $type        text|url|image|email|color|bool|secret|textarea.
	 * @param string $placeholder Optional placeholder.
	 * @return void
	 */
	private function field_row( $label, $name, $value, $type, $placeholder = '' ) {
		$id = 'f_' . sanitize_key( str_replace( array( '[', ']' ), '_', $name ) );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( 'bool' === $type ) : ?>
					<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( (bool) $value ); ?> /> <?php esc_html_e( 'Enabled', 'aaraa-vendor-hub' ); ?></label>
				<?php elseif ( 'textarea' === $type ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="large-text" rows="3"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php elseif ( 'image' === $type ) : ?>
					<div class="aaraa-vh-image-field" style="display:flex;gap:8px;align-items:center;">
						<input type="url" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="regular-text" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
						<button type="button" class="button aaraa-vh-media-btn"><?php esc_html_e( 'Choose Image', 'aaraa-vendor-hub' ); ?></button>
						<img class="aaraa-vh-media-preview" src="<?php echo esc_url( (string) $value ); ?>" style="max-height:36px;max-width:60px;<?php echo $value ? '' : 'display:none;'; ?>" alt="" />
					</div>
				<?php elseif ( 'color' === $type ) : ?>
					<div class="aaraa-vh-color-field" style="display:flex;gap:8px;align-items:center;">
						<input type="color" class="aaraa-vh-color-swatch" value="<?php echo esc_attr( preg_match( '/^#[0-9A-Fa-f]{6}$/', (string) $value ) ? $value : '#000000' ); ?>" />
						<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="regular-text code aaraa-vh-color-text" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="#RRGGBB" />
					</div>
				<?php else : ?>
					<?php
					$html_type = in_array( $type, array( 'url', 'email' ), true ) ? $type : 'text';
					$class     = 'secret' === $type ? 'regular-text code' : 'regular-text';
					?>
					<input type="<?php echo esc_attr( $html_type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $class ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
