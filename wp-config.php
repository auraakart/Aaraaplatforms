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
define( 'MAIN_DB_HOST',     'localhost' );
define( 'MAIN_DB_PORT',     3306 );
define( 'MAIN_DB_NAME',     'u293817202_akartmaster_db' );
define( 'MAIN_DB_USER',     'u293817202_akartmrdbadmin' );
define( 'MAIN_DB_PASSWORD', 'F6g>T45ITJXtryUipEdF' );

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
define( 'AUTH_KEY',         'V08^1_]D#Z#e~iGv@pJfIH@c@%C0@M^2M9R.?_u&8*A|r7{l=PI_7tjrTzahKr#g' );
define( 'SECURE_AUTH_KEY',  '7tc;>k+<;GRRlvS80xqPodTR6Heo`FWtOq)mCaTHR+0P!u;^E0I<IiHFhEJdy3`S' );
define( 'LOGGED_IN_KEY',    'u;IgU;|qXWI54l@@MF{; P-`9^R`siZa@5$kY(;e4J~XF$TvX2rJUXF#eIk}L y{' );
define( 'NONCE_KEY',        '5hm9gwi#Ir>F=M2*Bh!oJH*Hlx_YcbS&%4EJtxU3T@^K~*?h5&i B]2n(!2eqzG9' );
define( 'AUTH_SALT',        'J&JvE$nXP#Jx[%.b=2wGWA0z xG4eRp:msB-~.Q.QmL<7-o_*^&snc}Sf/kL6CpN' );
define( 'SECURE_AUTH_SALT', '*JXs!^[:/Qo*RC4DR1:{}2~cAD36KlDj;>f2%v.5ftyI@/Iu(@r0HRXfTV5n=r*N' );
define( 'LOGGED_IN_SALT',   '5bWq`O@z1zok,wS]YX=3_f8mMA5nXWbBzyp.} :$bko!{3e~,}k@fI-e_=w}=_z0' );
define( 'NONCE_SALT',       '}P4oa:y.yThV*.%d.Ld>~48`.%zUE1Iu8,5WOx7~mnZA~WM{;70(s~2Kt ]l~dbp' );

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
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false ); // Keep debug output out of HTML responses

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
