<?php
/**
 * Uninstall handler. Removes options, transients and the .htaccess alias block.
 *
 * @package Aaraa\Admin
 */

// Only run from the WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin data for a single site.
 *
 * @return void
 */
function aaraa_wla_uninstall_site() {
	global $wpdb;
	delete_option( 'aaraa_settings' );
	delete_option( 'aaraa_db_version' );
	delete_transient( 'aaraa_dashboard_stats' );
	// Drop our own log table. The wallet plugin's table (and its added created_by
	// column) is left untouched — it isn't ours to remove.
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'aaraa_customer_logs' ); // phpcs:ignore WordPress.DB
	// Our own delivery slots table. WCFM's hubs table is left untouched.
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'aaraa_delivery_slots' ); // phpcs:ignore WordPress.DB
}

/**
 * Strip the plugin's .htaccess block if present.
 *
 * @return void
 */
function aaraa_wla_uninstall_htaccess() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$htaccess = get_home_path() . '.htaccess';
	if ( file_exists( $htaccess ) && is_writable( $htaccess ) ) {
		$content = (string) file_get_contents( $htaccess );
		$content = (string) preg_replace(
			'/\n?# BEGIN Aaraa White Label Admin.*?# END Aaraa White Label Admin\n?/s',
			"\n",
			$content
		);
		file_put_contents( $htaccess, $content );
	}
}

if ( is_multisite() ) {
	delete_site_option( 'aaraa_settings' );

	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		aaraa_wla_uninstall_site();
		restore_current_blog();
	}
} else {
	aaraa_wla_uninstall_site();
}

aaraa_wla_uninstall_htaccess();
