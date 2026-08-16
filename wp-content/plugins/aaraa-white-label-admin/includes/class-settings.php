<?php
/**
 * Settings screen (tabbed) + save handler for single site and network.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and persists the Aaraa settings.
 */
class Settings {

	const PAGE_SLUG = 'aaraa-settings';
	const NONCE     = 'aaraa_save_settings';
	const ACTION    = 'aaraa_save_settings';

	/**
	 * Tab definitions: slug => label.
	 *
	 * @return array<string, string>
	 */
	private function tabs() {
		return array(
			'general'   => __( 'General', 'aaraa-white-label-admin' ),
			'branding'  => __( 'Branding', 'aaraa-white-label-admin' ),
			'admin_url' => __( 'Admin URL', 'aaraa-white-label-admin' ),
			'login'     => __( 'Login', 'aaraa-white-label-admin' ),
			'dashboard' => __( 'Dashboard', 'aaraa-white-label-admin' ),
			'advanced'  => __( 'Advanced', 'aaraa-white-label-admin' ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'register_network_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . AARAA_WLA_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add the single-site menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( aaraa_is_network_mode() ) {
			return; // Managed at network level.
		}
		add_menu_page(
			__( 'Aaraa Settings', 'aaraa-white-label-admin' ),
			__( 'Aaraa Settings', 'aaraa-white-label-admin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-admin-generic',
			80
		);
	}

	/**
	 * Add the network menu.
	 *
	 * @return void
	 */
	public function register_network_menu() {
		if ( ! aaraa_is_network_mode() ) {
			return;
		}
		add_menu_page(
			__( 'Aaraa Settings', 'aaraa-white-label-admin' ),
			__( 'Aaraa Settings', 'aaraa-white-label-admin' ),
			'manage_network_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-admin-generic',
			80
		);
	}

	/**
	 * "Settings" link on the plugins list.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function action_links( $links ) {
		$url  = aaraa_is_network_mode()
			? network_admin_url( 'admin.php?page=' . self::PAGE_SLUG )
			: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'aaraa-white-label-admin' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Enqueue settings assets only on our page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'aaraa-settings', AARAA_WLA_URL . 'assets/css/settings.css', array( 'wp-color-picker' ), aaraa_asset_ver( 'assets/css/settings.css' ) );
		wp_enqueue_script(
			'aaraa-settings',
			AARAA_WLA_URL . 'assets/js/settings.js',
			array( 'jquery', 'wp-color-picker' ),
			aaraa_asset_ver( 'assets/js/settings.js' ),
			true
		);
		wp_localize_script(
			'aaraa-settings',
			'aaraaSettings',
			array(
				'chooseImage' => __( 'Select image', 'aaraa-white-label-admin' ),
				'useImage'    => __( 'Use this image', 'aaraa-white-label-admin' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( aaraa_manage_cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}

		$tabs    = $this->tabs();
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'general';
		}
		$schema   = aaraa_schema();
		$settings = aaraa_get_settings();
		$base_url = aaraa_is_network_mode()
			? network_admin_url( 'admin.php?page=' . self::PAGE_SLUG )
			: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap aaraa-settings-wrap">
			<h1 class="aaraa-settings-title">
				<img src="<?php echo esc_url( AARAA_WLA_URL . 'assets/images/logo.svg' ); ?>" alt="" class="aaraa-settings-logo" />
				<?php esc_html_e( 'Aaraa White Label Admin', 'aaraa-white-label-admin' ); ?>
			</h1>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'aaraa-white-label-admin' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper aaraa-tabs">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
						class="nav-tab <?php echo $current === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aaraa-settings-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="tab" value="<?php echo esc_attr( $current ); ?>" />
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tbody>
					<?php
					foreach ( $schema as $key => $field ) {
						if ( $field['tab'] !== $current ) {
							continue;
						}
						$this->render_field( $key, $field, $settings );
					}
					?>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Changes', 'aaraa-white-label-admin' ) ); ?>
			</form>

			<?php if ( 'admin_url' === $current ) : ?>
				<div class="aaraa-callout">
					<h3><?php esc_html_e( 'Transparent admin path (recommended)', 'aaraa-white-label-admin' ); ?></h3>
					<p><?php esc_html_e( 'Admin links already show the branded slug. For fully transparent sub-pages, a webserver alias is used. On Apache it is written to .htaccess automatically. For nginx add:', 'aaraa-white-label-admin' ); ?></p>
					<pre class="aaraa-code">location ~ ^/<?php echo esc_html( aaraa_get_option( 'admin_slug', 'aaraa-admin' ) ); ?>/(.+)$ { rewrite ^/<?php echo esc_html( aaraa_get_option( 'admin_slug', 'aaraa-admin' ) ); ?>/(.+)$ /wp-admin/$1 last; }</pre>
					<p class="description"><?php esc_html_e( 'Emergency recovery: define AARAA_DISABLE_URL_REWRITE as true in wp-config.php to restore /wp-admin/ and /wp-login.php instantly.', 'aaraa-white-label-admin' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single field row.
	 *
	 * @param string               $key      Field key.
	 * @param array<string, mixed> $field    Field schema entry.
	 * @param array<string, mixed> $settings Current settings.
	 * @return void
	 */
	private function render_field( $key, $field, $settings ) {
		$value = $settings[ $key ] ?? $field['default'];
		$id    = 'aaraa_' . $key;
		$name  = 'aaraa_settings[' . $key . ']';
		// Labels are resolved here (render time) rather than in the schema, which
		// is read before `init` — see aaraa_field_labels().
		$labels = aaraa_field_labels();
		$label  = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php
				switch ( $field['type'] ) {
					case 'checkbox':
						printf(
							'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
							esc_attr( $id ),
							esc_attr( $name ),
							checked( (int) $value, 1, false ),
							esc_html__( 'Enabled', 'aaraa-white-label-admin' )
						);
						break;

					case 'color':
						printf(
							'<input type="text" class="aaraa-color-field" id="%1$s" name="%2$s" value="%3$s" data-default-color="%4$s" />',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $value ),
							esc_attr( $field['default'] )
						);
						break;

					case 'media':
						$src = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
						?>
						<div class="aaraa-media-field">
							<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (int) $value ); ?>" />
							<div class="aaraa-media-preview">
								<?php if ( $src ) : ?>
									<img src="<?php echo esc_url( $src ); ?>" alt="" />
								<?php endif; ?>
							</div>
							<button type="button" class="button aaraa-media-select"><?php esc_html_e( 'Select', 'aaraa-white-label-admin' ); ?></button>
							<button type="button" class="button aaraa-media-clear"><?php esc_html_e( 'Clear', 'aaraa-white-label-admin' ); ?></button>
						</div>
						<?php
						break;

					case 'slug':
						printf(
							'<code>%5$s/</code> <input type="text" class="regular-text code" id="%1$s" name="%2$s" value="%3$s" pattern="[a-z0-9\-]+" />%4$s',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $value ),
							'<p class="description">' . esc_html__( 'Lowercase letters, numbers and hyphens. Rewrite rules flush on save.', 'aaraa-white-label-admin' ) . '</p>',
							esc_html( untrailingslashit( home_url() ) )
						);
						break;

					case 'textarea':
						printf(
							'<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_textarea( $value )
						);
						break;

					default: // text.
						printf(
							'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" />',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $value )
						);
				}

				if ( ! empty( $field['description'] ) ) {
					echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Handle the settings form submission (single site + network).
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( aaraa_manage_cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'aaraa-white-label-admin' ) );
		}
		check_admin_referer( self::NONCE );

		$raw    = isset( $_POST['aaraa_settings'] ) && is_array( $_POST['aaraa_settings'] ) ? wp_unslash( $_POST['aaraa_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$schema = aaraa_schema();
		$old    = aaraa_get_settings();
		$clean  = array();

		foreach ( $schema as $key => $field ) {
			$clean[ $key ] = $this->sanitize_field( $field, $raw[ $key ] ?? null, $field['default'] );
		}

		aaraa_update_settings( $clean );

		// If the slug changed, refresh the rewrite alias + rules.
		if ( ( $old['admin_slug'] ?? '' ) !== $clean['admin_slug'] ) {
			Admin_URL_Rewriter::write_server_alias( $clean['admin_slug'] );
			Admin_URL_Rewriter::add_rewrite_tag( $clean['admin_slug'] );
			flush_rewrite_rules( false );
		}

		delete_transient( 'aaraa_dashboard_stats' );

		$tab      = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		$base_url = aaraa_is_network_mode()
			? network_admin_url( 'admin.php?page=' . self::PAGE_SLUG )
			: admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'updated' => '1' ), $base_url ) );
		exit;
	}

	/**
	 * Sanitize one field by its declared type.
	 *
	 * @param array<string, mixed> $field   Schema entry.
	 * @param mixed                $value   Raw submitted value.
	 * @param mixed                $default Default value.
	 * @return mixed
	 */
	private function sanitize_field( $field, $value, $default ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				return ( '1' === (string) $value || 1 === $value ) ? 1 : 0;

			case 'color':
				$color = sanitize_hex_color( (string) $value );
				return $color ? $color : $default;

			case 'media':
				return absint( $value );

			case 'slug':
				$slug = sanitize_title( (string) $value );
				// Never allow reserved words that would break core routing.
				$reserved = array( 'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'admin', 'login' );
				if ( '' === $slug || in_array( $slug, $reserved, true ) ) {
					return $default;
				}
				return $slug;

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			default: // text.
				return sanitize_text_field( (string) $value );
		}
	}
}
