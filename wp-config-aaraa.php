<?php
define( 'WP_CACHE', true );

define('WP_MEMORY_LIMIT', '256M');

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ── Main site DB — also holds the vendors routing table ─────────────────
define( 'MAIN_DB_HOST', getenv( 'MAIN_DB_HOST' ) ?: '' );
define( 'MAIN_DB_PORT',     3306 );
define( 'MAIN_DB_NAME', getenv( 'MAIN_DB_NAME' ) ?: '' );
define( 'MAIN_DB_USER', getenv( 'MAIN_DB_USER' ) ?: '' );
define( 'MAIN_DB_PASSWORD', getenv( 'MAIN_DB_PASSWORD' ) ?: '' );

// Fail closed if credentials have not been injected by the deployment environment.
foreach ( [ 'MAIN_DB_HOST', 'MAIN_DB_NAME', 'MAIN_DB_USER', 'MAIN_DB_PASSWORD' ] as $required_db_constant ) {
	if ( ! constant( $required_db_constant ) ) {
		http_response_code( 503 );
		exit( 'Database configuration unavailable.' );
	}
}
foreach ( [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ] as $required_wp_secret ) {
	if ( ! getenv( 'WP_' . $required_wp_secret ) ) {
		http_response_code( 503 );
		exit( 'Authentication configuration unavailable.' );
	}
}
unset( $required_db_constant, $required_wp_secret );

// ── Resolve subdomain → vendor database config ───────────────────────────
// Cache file TTL: 5 minutes. Delete cache file when vendor DB credentials change.
function aaraakart_get_vendor_db( string $subdomain ): ?array {
	$cache = sys_get_temp_dir() . '/vdb_' . md5( $subdomain ) . '.php';
	if ( file_exists( $cache ) && ( time() - filemtime( $cache ) ) < 300 ) {
		return include $cache;
	}
	try {
		$dsn = sprintf(
			'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
			MAIN_DB_HOST, MAIN_DB_PORT, MAIN_DB_NAME
		);
		$pdo = new PDO( $dsn, MAIN_DB_USER, MAIN_DB_PASSWORD, [
			PDO::ATTR_TIMEOUT    => 2,
			PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
		] );
		$stmt = $pdo->prepare(
			"SELECT db_host, db_port, db_name, db_user, db_pass
			   FROM vendors WHERE subdomain = ? AND status = 'active' LIMIT 1"
		);
		$stmt->execute( [ $subdomain ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC ) ?: null;
	} catch ( Throwable $e ) {
		$row = null; // master DB unavailable — fall through to main site DB
	}
	if ( $row ) {
		file_put_contents( $cache, '<?php return ' . var_export( $row, true ) . ';' );
	}
	return $row;
}

// ── Route DB_* constants based on HTTP host ──────────────────────────────
$_aa_host_parts = explode( '.', strtolower( $_SERVER['HTTP_HOST'] ?? '' ) );
$_aa_subdomain  = count( $_aa_host_parts ) >= 3 ? $_aa_host_parts[0] : null;

if ( $_aa_subdomain && ! in_array( $_aa_subdomain, [ 'www', 'api' ], true ) ) {
	$_aa_vendor = aaraakart_get_vendor_db( $_aa_subdomain );
	if ( ! $_aa_vendor ) {
		// Unknown or suspended subdomain
		http_response_code( 404 );
		exit( 'Store not found.' );
	}
	define( 'DB_HOST',     $_aa_vendor['db_host'] . ':' . $_aa_vendor['db_port'] );
	define( 'DB_NAME',     $_aa_vendor['db_name'] );
	define( 'DB_USER',     $_aa_vendor['db_user'] );
	define( 'DB_PASSWORD', $_aa_vendor['db_pass'] );
	unset( $_aa_vendor );
} else {
	// Main / admin site
	define( 'DB_HOST',     MAIN_DB_HOST );
	define( 'DB_NAME',     MAIN_DB_NAME );
	define( 'DB_USER',     MAIN_DB_USER );
	define( 'DB_PASSWORD', MAIN_DB_PASSWORD );
}
unset( $_aa_host_parts, $_aa_subdomain );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY', getenv( 'WP_AUTH_KEY' ) ?: '' );
define( 'SECURE_AUTH_KEY', getenv( 'WP_SECURE_AUTH_KEY' ) ?: '' );
define( 'LOGGED_IN_KEY', getenv( 'WP_LOGGED_IN_KEY' ) ?: '' );
define( 'NONCE_KEY', getenv( 'WP_NONCE_KEY' ) ?: '' );
define( 'AUTH_SALT', getenv( 'WP_AUTH_SALT' ) ?: '' );
define( 'SECURE_AUTH_SALT', getenv( 'WP_SECURE_AUTH_SALT' ) ?: '' );
define( 'LOGGED_IN_SALT', getenv( 'WP_LOGGED_IN_SALT' ) ?: '' );
define( 'NONCE_SALT', getenv( 'WP_NONCE_SALT' ) ?: '' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false ); // Keep debug output out of HTML responses

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
