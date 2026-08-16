<?php
/**
 * "Audit Logs" admin screen.
 *
 * A read-only view of the WP Activity Log (wp-security-audit-log) event store,
 * surfaced in the branded Shop Owner sidebar at admin.php?page=audit-logs.
 * All data comes from {@see Audit_Logs_List_Table}, which reuses WSAL's own
 * entities and helpers.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Audit Logs page controller.
 */
class Audit_Logs {

	const PAGE = 'audit-logs';

	/**
	 * No hooks of its own — the menu entry is registered by {@see Roles} and this
	 * class only provides the render callback.
	 *
	 * @return void
	 */
	public function init() {}

	/**
	 * Render the Audit Logs screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! Customers_Admin::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaraa-white-label-admin' ) );
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Audit Logs', 'aaraa-white-label-admin' ); ?></h1>
			<hr class="wp-header-end" />

			<?php if ( ! Audit_Logs_List_Table::wsal_available() ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'The WP Activity Log plugin (wp-security-audit-log) is not active. Activate it to record and view audit logs here.', 'aaraa-white-label-admin' ); ?></p>
				</div>
			<?php endif; ?>

			<style>
				.aaraa-auditlog__msg { line-height: 1.5; }
				.column-severity { width: 90px; text-align: center; }
				.column-id { width: 70px; }
				.column-ip { width: 120px; }
				.column-object, .column-event_type { width: 120px; }
				.column-date, .column-user { width: 160px; }
				.column-details { width: 130px; text-align: right; }
				.aaraa-auditlog-detailrow > td { background: #f6f7f7; box-shadow: inset 0 2px 4px rgba(0,0,0,.04); }
				.aaraa-auditlog-detail { margin: 0; padding: 12px 4px; white-space: pre-wrap; word-break: break-word; font-size: 12px; line-height: 1.7; }
				.aaraa-auditlog-detail strong { color: #1d2327; }
			</style>

			<?php
			$table = new Audit_Logs_List_Table();
			$table->prepare_items();
			?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search logs', 'aaraa-white-label-admin' ), 'aaraa-audit-log' );
				$table->display();
				?>
			</form>

			<script>
			( function () {
				document.addEventListener( 'click', function ( e ) {
					var btn = e.target.closest ? e.target.closest( '.aaraa-auditlog-more' ) : null;
					if ( ! btn ) {
						return;
					}
					e.preventDefault();
					var row = document.getElementById( btn.getAttribute( 'data-target' ) );
					if ( ! row ) {
						return;
					}
					var open = row.style.display !== 'none';
					row.style.display = open ? 'none' : 'table-row';
					btn.textContent = open ? btn.getAttribute( 'data-more' ) : btn.getAttribute( 'data-close' );
					btn.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				} );
			} )();
			</script>
		</div>
		<?php
	}
}
