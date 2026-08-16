<?php
/**
 * Application Log.
 *
 * A branded viewer for the WooCommerce logs (WooCommerce → Status → Logs) so
 * Shop Owners can read them from the Aaraa sidebar without the full WC Status
 * screen. Supports both WooCommerce log handlers:
 *   - File handler  → reads *.log files from the WC log directory.
 *   - DB handler    → reads the {prefix}woocommerce_log table.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Application (WooCommerce) log viewer.
 */
class Application_Log {

	const PAGE = 'aaraa-application-log';

	/**
	 * Max lines shown from a file (tail).
	 */
	const MAX_LINES = 2000;

	/**
	 * Log files listed per page (production log folders can hold thousands).
	 */
	const PER_PAGE = 100;

	/**
	 * No hooks needed — rendered from the menu callback.
	 *
	 * @return void
	 */
	public function init() {}

	/**
	 * The WooCommerce log directory (trailing-slashed).
	 *
	 * @return string
	 */
	private function log_dir() {
		if ( defined( 'WC_LOG_DIR' ) ) {
			return trailingslashit( WC_LOG_DIR );
		}
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . 'wc-logs/';
	}

	/**
	 * Available .log files, newest first (basenames only).
	 *
	 * @return string[]
	 */
	private function log_files() {
		$dir = $this->log_dir();
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$files = glob( $dir . '*.log' );
		if ( empty( $files ) ) {
			return array();
		}
		usort(
			$files,
			static function ( $a, $b ) {
				return filemtime( $b ) <=> filemtime( $a );
			}
		);
		return array_map( 'basename', $files );
	}

	/**
	 * Parse a WooCommerce log filename into its source + created date.
	 *
	 * WooCommerce names file-handler logs "{source}-{YYYY-MM-DD}-{32-hex-hash}.log".
	 * The source itself may contain hyphens (e.g. "failed-scheduled-actions").
	 *
	 * @param string $file Basename.
	 * @return array{source:string,created:string}
	 */
	private function parse_log_name( $file ) {
		$name = preg_replace( '/\.log$/', '', $file );
		if ( preg_match( '/^(.*)-(\d{4}-\d{2}-\d{2})-[0-9a-f]{32}$/', $name, $m ) ) {
			return array(
				'source'  => $m[1],
				'created' => $m[2],
			);
		}
		return array(
			'source'  => $name,
			'created' => '',
		);
	}

	/**
	 * The DB log table name if the DB handler's table exists, else ''.
	 *
	 * @return string
	 */
	private function db_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'woocommerce_log';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB
		return ( $found === $table ) ? $table : '';
	}

	/**
	 * Render the admin screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'aaraa-white-label-admin' ) );
		}

		$files = $this->log_files();
		$base  = admin_url( 'admin.php' );

		echo '<div class="wrap aaraa-wallet">';
		?>
		<div class="aaraa-wallet__bar">
			<h1 class="aaraa-wallet__title"><?php esc_html_e( 'Application Log', 'aaraa-white-label-admin' ); ?></h1>
		</div>
		<div class="aaraa-wallet__panel" style="border-top:1px solid #E2E8F0;border-radius:10px;">
			<?php
			if ( ! empty( $files ) ) {
				$this->render_file_viewer( $files, $base );
			} elseif ( '' !== $this->db_table() ) {
				$this->render_db_viewer();
			} else {
				echo '<p class="aac-empty">' . esc_html__( 'No WooCommerce logs found.', 'aaraa-white-label-admin' ) . '</p>';
			}
			?>
		</div>
		<?php
		echo '</div>';
	}

	/**
	 * File-handler viewer.
	 *
	 * With no ?log_file it lists every log file (Source / Created / Modified /
	 * Size / View), mirroring WooCommerce → Status → Logs. With ?log_file it
	 * shows that one file's contents.
	 *
	 * @param string[] $files Available log basenames.
	 * @param string   $base  admin.php URL.
	 * @return void
	 */
	private function render_file_viewer( $files, $base ) {
		$selected = isset( $_GET['log_file'] ) ? basename( sanitize_file_name( wp_unslash( $_GET['log_file'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( '' !== $selected && in_array( $selected, $files, true ) ) {
			$this->render_single_file( $selected, $base );
			return;
		}

		$this->render_file_list( $files, $base );
	}

	/**
	 * List view: every log file as a row.
	 *
	 * @param string[] $files Available log basenames.
	 * @param string   $base  admin.php URL.
	 * @return void
	 */
	private function render_file_list( $files, $base ) {
		$dir  = $this->log_dir();
		$fmt  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$total = count( $files );
		$pages = (int) max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = isset( $_GET['log_page'] ) ? (int) $_GET['log_page'] : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$page  = max( 1, min( $pages, $page ) );
		$slice = array_slice( $files, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		$first = ( $page - 1 ) * self::PER_PAGE + 1;
		$last  = $first + count( $slice ) - 1;

		echo '<p class="description" style="margin:0 0 10px;">';
		printf(
			/* translators: 1: first row, 2: last row, 3: total files. */
			esc_html__( 'Showing %1$s–%2$s of %3$s log files (newest first). Click a source to read it.', 'aaraa-white-label-admin' ),
			esc_html( number_format_i18n( $first ) ),
			esc_html( number_format_i18n( $last ) ),
			esc_html( number_format_i18n( $total ) )
		);
		echo '</p>';

		echo '<table class="widefat striped aaraa-wallet__table"><thead><tr>'
			. '<th>' . esc_html__( 'Source', 'aaraa-white-label-admin' ) . '</th>'
			. '<th>' . esc_html__( 'Created', 'aaraa-white-label-admin' ) . '</th>'
			. '<th>' . esc_html__( 'Modified', 'aaraa-white-label-admin' ) . '</th>'
			. '<th>' . esc_html__( 'File size', 'aaraa-white-label-admin' ) . '</th>'
			. '<th>' . esc_html__( 'Action', 'aaraa-white-label-admin' ) . '</th>'
			. '</tr></thead><tbody>';

		foreach ( $slice as $f ) {
			$meta = $this->parse_log_name( $f );
			$path = $dir . $f;
			$size = is_readable( $path ) ? size_format( (int) filesize( $path ) ) : '—';
			$mod  = is_readable( $path ) ? wp_date( $fmt, (int) filemtime( $path ) ) : '—';
			$view = add_query_arg(
				array(
					'page'     => self::PAGE,
					'log_file' => rawurlencode( $f ),
				),
				$base
			);
			echo '<tr>'
				. '<td><strong>' . esc_html( $meta['source'] ) . '</strong><br /><span class="description" style="word-break:break-all;">' . esc_html( $f ) . '</span></td>'
				. '<td style="white-space:nowrap;">' . esc_html( '' !== $meta['created'] ? $meta['created'] : '—' ) . '</td>'
				. '<td style="white-space:nowrap;">' . esc_html( $mod ) . '</td>'
				. '<td style="white-space:nowrap;">' . esc_html( $size ) . '</td>'
				. '<td><a class="button button-small" href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'aaraa-white-label-admin' ) . '</a></td>'
				. '</tr>';
		}

		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<p style="margin:12px 0 0;display:flex;gap:8px;align-items:center;">';
			if ( $page > 1 ) {
				$prev = add_query_arg( array( 'page' => self::PAGE, 'log_page' => $page - 1 ), $base );
				echo '<a class="button" href="' . esc_url( $prev ) . '">&larr; ' . esc_html__( 'Newer', 'aaraa-white-label-admin' ) . '</a>';
			}
			echo '<span class="description">';
			printf(
				/* translators: 1: current page, 2: total pages. */
				esc_html__( 'Page %1$s of %2$s', 'aaraa-white-label-admin' ),
				esc_html( number_format_i18n( $page ) ),
				esc_html( number_format_i18n( $pages ) )
			);
			echo '</span>';
			if ( $page < $pages ) {
				$next = add_query_arg( array( 'page' => self::PAGE, 'log_page' => $page + 1 ), $base );
				echo '<a class="button" href="' . esc_url( $next ) . '">' . esc_html__( 'Older', 'aaraa-white-label-admin' ) . ' &rarr;</a>';
			}
			echo '</p>';
		}
	}

	/**
	 * Single-file view: the selected file's tail.
	 *
	 * @param string $selected Basename (already validated against the file list).
	 * @param string $base     admin.php URL.
	 * @return void
	 */
	private function render_single_file( $selected, $base ) {
		$back = add_query_arg( array( 'page' => self::PAGE ), $base );

		$path = $this->log_dir() . $selected;
		// Guard: the resolved path must stay inside the log directory.
		$real_dir  = realpath( $this->log_dir() );
		$real_file = realpath( $path );
		if ( ! $real_file || ! $real_dir || 0 !== strpos( $real_file, $real_dir ) || ! is_readable( $real_file ) ) {
			echo '<p><a class="button" href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'All logs', 'aaraa-white-label-admin' ) . '</a></p>';
			echo '<p class="aac-empty">' . esc_html__( 'That log file could not be read.', 'aaraa-white-label-admin' ) . '</p>';
			return;
		}

		$lines = file( $real_file, FILE_IGNORE_NEW_LINES );
		if ( false === $lines ) {
			$lines = array();
		}
		$total = count( $lines );
		if ( $total > self::MAX_LINES ) {
			$lines = array_slice( $lines, - self::MAX_LINES );
		}

		echo '<p style="margin:0 0 10px;"><a class="button" href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'All logs', 'aaraa-white-label-admin' ) . '</a></p>';

		echo '<p class="description" style="margin:0 0 8px;">';
		printf(
			/* translators: 1: shown line count, 2: total lines, 3: file name. */
			esc_html__( 'Showing last %1$s of %2$s lines from %3$s.', 'aaraa-white-label-admin' ),
			esc_html( number_format_i18n( count( $lines ) ) ),
			esc_html( number_format_i18n( $total ) ),
			'<code>' . esc_html( $selected ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '</p>';

		echo '<pre style="max-height:640px;overflow:auto;background:#0b1020;color:#d6e2ff;padding:14px 16px;border-radius:8px;font:12px/1.6 ui-monospace,Menlo,Consolas,monospace;white-space:pre-wrap;word-break:break-word;">';
		echo esc_html( implode( "\n", $lines ) );
		echo '</pre>';
	}

	/**
	 * DB-handler viewer: recent rows from the woocommerce_log table.
	 *
	 * @return void
	 */
	private function render_db_viewer() {
		global $wpdb;
		$table = $this->db_table();
		$rows  = $wpdb->get_results( "SELECT log_id, timestamp, level, source, message FROM {$table} ORDER BY log_id DESC LIMIT 200" ); // phpcs:ignore WordPress.DB
		if ( empty( $rows ) ) {
			echo '<p class="aac-empty">' . esc_html__( 'No log entries.', 'aaraa-white-label-admin' ) . '</p>';
			return;
		}
		echo '<p class="description" style="margin:0 0 8px;">' . esc_html__( 'Most recent 200 entries.', 'aaraa-white-label-admin' ) . '</p>';
		echo '<table class="widefat striped aaraa-wallet__table"><thead><tr>'
			. '<th>' . esc_html__( 'Time', 'aaraa-white-label-admin' ) . '</th>'
			. '<th>' . esc_html__( 'Level', 'aaraa-white-label-admin' ) . '</th>'
			. '<th>' . esc_html__( 'Source', 'aaraa-white-label-admin' ) . '</th>'
			. '<th>' . esc_html__( 'Message', 'aaraa-white-label-admin' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>'
				. '<td style="white-space:nowrap;">' . esc_html( $r->timestamp ) . '</td>'
				. '<td>' . esc_html( ucfirst( (string) $r->level ) ) . '</td>'
				. '<td>' . esc_html( (string) $r->source ) . '</td>'
				. '<td><code style="white-space:pre-wrap;">' . esc_html( (string) $r->message ) . '</code></td>'
				. '</tr>';
		}
		echo '</tbody></table>';
	}
}
