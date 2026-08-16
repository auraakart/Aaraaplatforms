<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u293817202_madm_aaraakart' );

/** Database username */
define( 'DB_USER', 'u293817202_madmaaraaadmin' );

/** Database password */
define( 'DB_PASSWORD', 'F6g>T45ITJXtryUipEdF' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
