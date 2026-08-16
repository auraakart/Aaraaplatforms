<?php
/**
 * Mobile Banners: manage the app/storefront banner images and expose them
 * through a public read-only API.
 *
 * Admin screen (sidebar → Mobile Banner, below Dashboard):
 *   - list table of banners (ID, Image, Actions),
 *   - "Add Banner" opens the WordPress media library,
 *   - each row can replace its image (Edit, via media) or Delete.
 *
 * Banners live in `{prefix}aaraa_mobile_banners` and store a WordPress media
 * attachment id, so the image itself is managed by the media library.
 *
 * API:
 *   GET /wp-json/mobile-banners   → the banner images (public, read-only).
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Mobile banners controller + API.
 */
class Mobile_Banner {

	const PAGE     = 'aaraa-mobile-banners';
	const ACTION   = 'aaraa_mb_action';
	const NONCE    = 'aaraa_mb';
	const REST_NS  = 'mobile-banners';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Table.
	 * --------------------------------------------------------------------- */

	/**
	 * The banners table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aaraa_mobile_banners';
	}

	/**
	 * Create the table (idempotent).
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * All banners, ordered for display / API.
	 *
	 * @param string $status 'active', 'inactive' or 'all'.
	 * @return array
	 */
	public static function banners_list( $status = 'all' ) {
		global $wpdb;
		$table = self::table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB
			return array();
		}
		if ( 'all' === $status ) {
			return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore WordPress.DB
		}
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY sort_order ASC, id ASC", $status )
		);
	}

	/* --------------------------------------------------------------------- *
	 * Assets.
	 * --------------------------------------------------------------------- */

	/**
	 * Load the media library + picker script on our page only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE !== $page ) {
			return;
		}
		wp_enqueue_media();
	}

	/* --------------------------------------------------------------------- *
	 * Write path.
	 * --------------------------------------------------------------------- */

	/**
	 * Handle add/edit (save an attachment id) and delete.
	 *
	 * @return void
	 */
	public function handle_actions() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::PAGE !== $page ) {
			return;
		}

		// Save (add or edit).
		$posted = isset( $_POST[ self::ACTION ] ) ? sanitize_key( wp_unslash( $_POST[ self::ACTION ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'save' === $posted ) {
			check_admin_referer( self::NONCE );
			if ( ! Customers_Admin::current_user_can_manage() ) {
				wp_die( esc_html__( 'You are not allowed to manage banners.', 'aaraa-white-label-admin' ) );
			}
			$this->save();
			return;
		}

		// Delete (query string).
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'delete' === $action ) {
			$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'aaraa_mb_delete_' . $id );
			if ( ! Customers_Admin::current_user_can_manage() ) {
				wp_die( esc_html__( 'You are not allowed to manage banners.', 'aaraa-white-label-admin' ) );
			}
			$this->delete( $id );
		}
	}

	/**
	 * Insert or update a banner.
	 *
	 * @return void
	 */
	private function save() {
		global $wpdb;
		self::install();

		$id            = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			$this->redirect( 'error' );
		}

		if ( $id ) {
			$wpdb->update( self::table(), array( 'attachment_id' => $attachment_id ), array( 'id' => $id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB
				self::table(),
				array(
					'attachment_id' => $attachment_id,
					'sort_order'    => 0,
					'status'        => 'active',
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
		$this->redirect( 'saved' );
	}

	/**
	 * Delete a banner row (the media file is left in the library).
	 *
	 * @param int $id Banner id.
	 * @return void
	 */
	private function delete( $id ) {
		global $wpdb;
		if ( $id ) {
			$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		}
		$this->redirect( 'deleted' );
	}

	/**
	 * Redirect back to the list with a notice flag.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Rendering.
	 * --------------------------------------------------------------------- */

	/**
	 * Render the Mobile Banners screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}
		self::install();

		$rows = self::banners_list( 'all' );
		?>
		<div class="wrap">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Mobile Banners', 'aaraa-white-label-admin' ); ?></h1>
				<button type="button" class="button button-primary" id="aaraa-mb-add"><?php esc_html_e( '+ Add Banner', 'aaraa-white-label-admin' ); ?></button>
			</div>
			<hr class="wp-header-end" />

			<?php $this->notices(); ?>

			<table class="widefat striped" style="margin-top:12px;">
				<thead>
					<tr>
						<th style="width:80px;"><?php esc_html_e( 'ID', 'aaraa-white-label-admin' ); ?></th>
						<th><?php esc_html_e( 'Image', 'aaraa-white-label-admin' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Action', 'aaraa-white-label-admin' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No banners yet. Click “Add Banner” to upload one.', 'aaraa-white-label-admin' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$img    = wp_get_attachment_image( (int) $row->attachment_id, array( 200, 90 ), false, array( 'style' => 'max-width:200px;height:auto;border-radius:4px;' ) );
						$delete = wp_nonce_url(
							add_query_arg( array( 'page' => self::PAGE, 'action' => 'delete', 'id' => $row->id ), admin_url( 'admin.php' ) ),
							'aaraa_mb_delete_' . $row->id
						);
						?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
							<td><?php echo $img ? $img : '<em>' . esc_html__( 'Image missing', 'aaraa-white-label-admin' ) . '</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td>
								<button type="button" class="button button-small aaraa-mb-edit" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Edit', 'aaraa-white-label-admin' ); ?></button>
								<a class="button button-small button-link-delete" href="<?php echo esc_url( $delete ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this banner?', 'aaraa-white-label-admin' ) ); ?>');"><?php esc_html_e( 'Delete', 'aaraa-white-label-admin' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<form method="post" id="aaraa-mb-form" style="display:none;">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::ACTION ); ?>" value="save" />
				<input type="hidden" name="record_id" id="aaraa-mb-record" value="" />
				<input type="hidden" name="attachment_id" id="aaraa-mb-attachment" value="" />
			</form>
		</div>

		<script>
		( function () {
			var frame;
			var form       = document.getElementById( 'aaraa-mb-form' );
			var recordEl   = document.getElementById( 'aaraa-mb-record' );
			var attachEl   = document.getElementById( 'aaraa-mb-attachment' );

			function openPicker( recordId ) {
				if ( ! window.wp || ! wp.media ) {
					return;
				}
				frame = wp.media( {
					title: '<?php echo esc_js( __( 'Select banner image', 'aaraa-white-label-admin' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Use this image', 'aaraa-white-label-admin' ) ); ?>' },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					recordEl.value = recordId || '';
					attachEl.value = attachment.id;
					form.submit();
				} );
				frame.open();
			}

			var addBtn = document.getElementById( 'aaraa-mb-add' );
			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () { openPicker( '' ); } );
			}
			document.querySelectorAll( '.aaraa-mb-edit' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () { openPicker( btn.getAttribute( 'data-id' ) ); } );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Admin notices.
	 *
	 * @return void
	 */
	private function notices() {
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$map    = array(
			'saved'   => array( 'success', __( 'Banner saved.', 'aaraa-white-label-admin' ) ),
			'deleted' => array( 'success', __( 'Banner deleted.', 'aaraa-white-label-admin' ) ),
			'error'   => array( 'error', __( 'Please choose a valid image.', 'aaraa-white-label-admin' ) ),
		);
		if ( isset( $map[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $map[ $notice ][0] ),
				esc_html( $map[ $notice ][1] )
			);
		}
	}

	/* --------------------------------------------------------------------- *
	 * REST API.
	 * --------------------------------------------------------------------- */

	/**
	 * Register the public GET route at /wp-json/mobile-banners.
	 *
	 * @return void
	 */
	public function register_routes() {
		add_filter( 'rest_endpoints', array( $this, 'alias_base_route' ) );

		register_rest_route(
			self::REST_NS,
			'/',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_list' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Serve the listing at the bare namespace root (with or without a trailing slash).
	 *
	 * @param array $endpoints All registered REST endpoints.
	 * @return array
	 */
	public function alias_base_route( $endpoints ) {
		$slashed = '/' . self::REST_NS . '/';
		$bare    = '/' . self::REST_NS;
		if ( isset( $endpoints[ $slashed ] ) ) {
			$endpoints[ $bare ] = $endpoints[ $slashed ];
		}
		return $endpoints;
	}

	/**
	 * Return the active banner images.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function rest_list( \WP_REST_Request $request ) {
		$banners = array();
		foreach ( self::banners_list( 'active' ) as $row ) {
			$attachment_id = (int) $row->attachment_id;
			$url           = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'full' ) : '';
			if ( ! $url ) {
				continue; // Skip banners whose media was deleted.
			}
			$banners[] = array(
				'id'            => (int) $row->id,
				'attachment_id' => $attachment_id,
				'image'         => $url,
				'thumbnail'     => wp_get_attachment_image_url( $attachment_id, 'medium' ),
				'alt'           => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'count'   => count( $banners ),
				'banners' => $banners,
			),
			200
		);
	}
}
