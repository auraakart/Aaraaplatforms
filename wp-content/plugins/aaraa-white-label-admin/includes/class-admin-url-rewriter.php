<?php
/**
 * Custom admin + login URL engine.
 *
 * Design (see readme.txt "Admin URL & lock-out safety"):
 *   - Login relocation is done fully in PHP and is robust.
 *   - Admin *links* are rewritten to /{slug}/ so the UI never shows /wp-admin/.
 *   - Transparent /{slug}/foo.php -> /wp-admin/foo.php needs a webserver alias,
 *     which activation writes into .htaccess on Apache (nginx snippet in readme).
 *   - When no server alias is present, /{slug}/foo.php falls back to a safe 302
 *     to the real /wp-admin/foo.php. Graceful degradation, never a lock-out.
 *   - Define AARAA_DISABLE_URL_REWRITE (true) in wp-config.php to disable the
 *     whole engine for emergency recovery.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites admin and login URLs to a branded slug.
 */
class Admin_URL_Rewriter {

	/**
	 * The active slug (e.g. "aaraa-admin").
	 *
	 * @var string
	 */
	private $slug = 'aaraa-admin';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->slug = aaraa_get_option( 'admin_slug', 'aaraa-admin' );

		if ( $this->is_disabled() ) {
			return;
		}

		/*
		 * Intercept on wp_loaded, NOT plugins_loaded. wp-login.php's login_header()
		 * needs constants (AUTOSAVE_INTERVAL, wp-settings.php:625) and $wp_rewrite
		 * (:663) that do not exist at plugins_loaded (:622). wp_loaded (:793) runs
		 * after those and after init (:771, textdomains), yet before the front-end
		 * query is parsed and before wp-admin's auth_redirect() — early enough to
		 * serve the gateway, late enough to be safe.
		 */
		add_action( 'wp_loaded', array( $this, 'intercept_request' ), 1 );

		/*
		 * Rewrite generated links so the UI shows the branded slug — but ONLY when
		 * the server alias is actually installed.
		 *
		 * Without the alias, /{slug}/… does not serve wp-admin; it redirects. A
		 * redirect on a POST makes the browser re-issue it as a GET and discard the
		 * body, so every admin form silently saves nothing — order status updates,
		 * metabox fields, admin-ajax calls. Filtering admin_url() while the alias is
		 * missing breaks the whole admin, so we leave URLs untouched instead.
		 */
		if ( aaraa_get_option( 'enable_admin_url', 1 ) && $this->alias_active() ) {
			add_filter( 'admin_url', array( $this, 'filter_admin_url' ), 10, 2 );
			add_filter( 'network_admin_url', array( $this, 'filter_admin_url' ), 10, 2 );
			add_filter( 'self_admin_url', array( $this, 'filter_admin_url' ), 10, 2 );
		}

		if ( aaraa_get_option( 'enable_login_url', 1 ) ) {
			add_filter( 'site_url', array( $this, 'filter_login_in_url' ), 10, 2 );
			add_filter( 'network_site_url', array( $this, 'filter_login_in_url' ), 10, 2 );
			add_filter( 'wp_redirect', array( $this, 'filter_login_in_url' ), 10, 1 );
			add_filter( 'login_url', array( $this, 'filter_login_in_url' ), 10, 1 );
			add_filter( 'logout_url', array( $this, 'filter_login_in_url' ), 10, 1 );
			add_filter( 'lostpassword_url', array( $this, 'filter_login_in_url' ), 10, 1 );
			add_filter( 'register_url', array( $this, 'filter_login_in_url' ), 10, 1 );
		}

		// Register the rewrite tag so /{slug}/ is a known query var.
		add_action( 'init', array( $this, 'register_rewrite' ) );
	}

	/**
	 * Whether our .htaccess alias block is actually present.
	 *
	 * The alias is what makes /{slug}/… serve wp-admin. If it was never written
	 * (or was removed to clear a redirect loop), rewriting admin URLs points every
	 * link and form at a path that only redirects — which drops POST bodies. So the
	 * rewrite is gated on this check, cached for an hour.
	 *
	 * @return bool
	 */
	private function alias_active() {
		$cached = get_transient( 'aaraa_alias_active' );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$active   = 'no';
		$htaccess = ABSPATH . '.htaccess';
		if ( is_readable( $htaccess ) ) {
			$content = (string) file_get_contents( $htaccess ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false !== strpos( $content, '# BEGIN Aaraa White Label Admin' ) ) {
				$active = 'yes';
			}
		}

		set_transient( 'aaraa_alias_active', $active, HOUR_IN_SECONDS );
		return 'yes' === $active;
	}

	/**
	 * Whether the engine is switched off via constant.
	 *
	 * @return bool
	 */
	private function is_disabled() {
		return defined( 'AARAA_DISABLE_URL_REWRITE' ) && AARAA_DISABLE_URL_REWRITE;
	}

	/* --------------------------------------------------------------------- *
	 * Request interception.
	 * --------------------------------------------------------------------- */

	/**
	 * Inspect the incoming request and route login / admin gateway traffic.
	 *
	 * @return void
	 */
	public function intercept_request() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || defined( 'WP_CLI' ) ) {
			return;
		}
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$path   = $this->current_path();
		$base   = $this->slug_path();
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		// 1. The branded gateway base (/{slug}). Never aliased to wp-admin, so it is
		//    always a clean front-end request here: login for guests, dashboard for
		//    logged-in users.
		if ( aaraa_get_option( 'enable_login_url', 1 ) && ( $path === $base || ( ! get_option( 'permalink_structure' ) && isset( $_GET[ $this->slug ] ) ) ) ) {
			if ( is_user_logged_in() && '' === $action ) {
				wp_safe_redirect( admin_url( 'index.php' ), 302 ); // filtered to /{slug}/index.php.
				exit;
			}
			$this->serve_gateway();
			return;
		}

		// 2. Hide the default login form (plain login only; utility actions pass through).
		if ( aaraa_get_option( 'enable_login_url', 1 ) && $this->is_default_login_request() && ! $this->is_passthrough_action( $action ) ) {
			$this->block_default_login();
			return;
		}

		// 3. No server alias present: /{slug}/foo.php arrived as a front-end URL
		//    (is_admin() is false). Fall back to a safe 302 to the real admin file.
		//    When the webserver alias IS present, /{slug}/foo.php executes wp-admin
		//    directly (is_admin() true) and this branch is skipped.
		if ( aaraa_get_option( 'enable_admin_url', 1 ) && ! is_admin() && 0 === strpos( $path, $base . '/' ) ) {
			$target = ltrim( substr( $path, strlen( $base ) ), '/' );
			if ( $this->is_valid_admin_target( $target ) ) {
				$query = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
				$url   = $this->real_admin_url( $target ) . ( $query ? '?' . $query : '' );
				wp_safe_redirect( $url, 302 );
				exit;
			}
		}
	}

	/**
	 * Serve the branded login form (guests only; runs in front-end context).
	 *
	 * @return void
	 */
	private function serve_gateway() {
		// wp-login.php reads these from the global scope; declare them so our
		// include behaves exactly as a normal request to wp-login.php would.
		global $pagenow, $error, $interim_login, $action, $user_login; // phpcs:ignore
		$pagenow = 'wp-login.php';

		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Block the exposed default login endpoint.
	 *
	 * Guests get a 404 so the endpoint stays hidden; logged-in users are sent to
	 * the dashboard. Utility actions (reset, register, logout…) never reach here.
	 *
	 * @return void
	 */
	private function block_default_login() {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( admin_url(), 302 ); // filtered to /{slug}/.
			exit;
		}
		$this->render_404();
	}

	/* --------------------------------------------------------------------- *
	 * Link filters.
	 * --------------------------------------------------------------------- */

	/**
	 * Replace /wp-admin/ with /{slug}/ in generated admin URLs.
	 *
	 * @param string $url  The admin URL.
	 * @param string $path Optional path passed to admin_url().
	 * @return string
	 */
	public function filter_admin_url( $url, $path = '' ) {
		if ( false === strpos( $url, '/wp-admin/' ) ) {
			return $url;
		}

		/*
		 * Never relocate the request endpoints. admin-ajax.php and admin-post.php
		 * are called directly by core and by third-party scripts; if the relocated
		 * path redirects, the browser re-issues the POST as a GET and drops the
		 * body, so the handler receives no `action` and admin-ajax answers 400.
		 * Only real admin *pages* get the custom slug.
		 */
		foreach ( array( 'admin-ajax.php', 'admin-post.php' ) as $endpoint ) {
			if ( false !== strpos( $url, $endpoint ) ) {
				return $url;
			}
		}

		return str_replace( '/wp-admin/', '/' . $this->slug . '/', $url );
	}

	/**
	 * Replace wp-login.php occurrences with the branded slug.
	 *
	 * @param string $url The URL (site_url / login_url / redirect target).
	 * @return string
	 */
	public function filter_login_in_url( $url ) {
		if ( is_string( $url ) && false !== strpos( $url, 'wp-login.php' ) ) {
			return $this->swap_login( $url );
		}
		return $url;
	}

	/**
	 * Swap a wp-login.php URL for the slug, preserving query args.
	 *
	 * @param string $url URL containing wp-login.php.
	 * @return string
	 */
	private function swap_login( $url ) {
		$scheme = is_ssl() ? 'https' : null;
		$parts  = explode( '?', $url, 2 );
		$new    = get_option( 'permalink_structure' )
			? home_url( user_trailingslashit( $this->slug ), $scheme )
			: home_url( '/', $scheme ) . '?' . rawurlencode( $this->slug );

		if ( isset( $parts[1] ) && '' !== $parts[1] ) {
			// parse_str() decodes values; add_query_arg() re-encodes them once.
			parse_str( $parts[1], $args );
			$new = add_query_arg( $args, $new );
		}
		return $new;
	}

	/* --------------------------------------------------------------------- *
	 * Rewrite registration + server alias.
	 * --------------------------------------------------------------------- */

	/**
	 * Register the rewrite tag on init.
	 *
	 * @return void
	 */
	public function register_rewrite() {
		self::add_rewrite_tag( $this->slug );
	}

	/**
	 * Add the rewrite rule for the slug base.
	 *
	 * @param string $slug Slug to register.
	 * @return void
	 */
	public static function add_rewrite_tag( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return;
		}
		add_rewrite_rule( '^' . preg_quote( $slug, '#' ) . '/?$', 'index.php?' . $slug . '=1', 'top' );
		add_rewrite_tag( '%' . $slug . '%', '1' );
	}

	/**
	 * Write the transparent alias into .htaccess (Apache only).
	 *
	 * @param string $slug Slug to alias to wp-admin.
	 * @return bool True on success.
	 */
	public static function write_server_alias( $slug ) {
		delete_transient( 'aaraa_alias_active' );
		$slug = sanitize_title( $slug );
		if ( '' === $slug || ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess = get_home_path() . '.htaccess';
		if ( ! $slug || ( ! file_exists( $htaccess ) && ! is_writable( get_home_path() ) ) || ( file_exists( $htaccess ) && ! is_writable( $htaccess ) ) ) {
			return false;
		}

		/*
		 * Only sub-paths are aliased to wp-admin. The bare base (/{slug}/) is left
		 * for WordPress to handle so the plugin can serve the login form there —
		 * aliasing the base would run wp-admin and loop guests back to login.
		 */
		$rules   = array(
			'# BEGIN Aaraa White Label Admin',
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule ^' . $slug . '/(.+)$ wp-admin/$1 [QSA,L]',
			'</IfModule>',
			'# END Aaraa White Label Admin',
		);
		$existing = file_exists( $htaccess ) ? file_get_contents( $htaccess ) : '';
		$existing = self::strip_alias_block( $existing );

		return (bool) file_put_contents( $htaccess, implode( "\n", $rules ) . "\n" . $existing );
	}

	/**
	 * Remove the alias block from .htaccess.
	 *
	 * @return void
	 */
	public static function remove_server_alias() {
		delete_transient( 'aaraa_alias_active' );
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess = get_home_path() . '.htaccess';
		if ( file_exists( $htaccess ) && is_writable( $htaccess ) ) {
			file_put_contents( $htaccess, self::strip_alias_block( file_get_contents( $htaccess ) ) );
		}
	}

	/**
	 * Strip our marked block from an .htaccess body.
	 *
	 * @param string $content File contents.
	 * @return string
	 */
	private static function strip_alias_block( $content ) {
		$pattern = '/\n?# BEGIN Aaraa White Label Admin.*?# END Aaraa White Label Admin\n?/s';
		return (string) preg_replace( $pattern, "\n", (string) $content );
	}

	/* --------------------------------------------------------------------- *
	 * Path helpers.
	 * --------------------------------------------------------------------- */

	/**
	 * The current request path, without query string or trailing slash.
	 *
	 * @return string
	 */
	private function current_path() {
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		return untrailingslashit( $path );
	}

	/**
	 * The site's home path prefix (for subdirectory installs).
	 *
	 * @return string
	 */
	private function home_path() {
		$path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		return untrailingslashit( $path );
	}

	/**
	 * Absolute path (from web root) of the slug gateway.
	 *
	 * @return string
	 */
	private function slug_path() {
		return $this->home_path() . '/' . $this->slug;
	}

	/**
	 * Build the real (unbranded) wp-admin URL for a target file.
	 *
	 * home_url() is not filtered by this class, so this always yields /wp-admin/.
	 *
	 * @param string $target Admin file (e.g. "plugins.php").
	 * @return string
	 */
	private function real_admin_url( $target = '' ) {
		return home_url( '/wp-admin/' . ltrim( $target, '/' ) );
	}

	/**
	 * Whether the request targets the default wp-login.php.
	 *
	 * @return bool
	 */
	private function is_default_login_request() {
		$path = $this->current_path();
		$raw  = strtolower( rawurldecode( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		return ( $path === $this->home_path() . '/wp-login.php' ) || false !== strpos( $raw, '/wp-login.php' );
	}

	/**
	 * Actions permitted to keep using wp-login.php directly (reset, logout, register…).
	 *
	 * @param string $action The requested action.
	 * @return bool
	 */
	private function is_passthrough_action( $action ) {
		return in_array( $action, array( 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'confirmaction' ), true );
	}

	/**
	 * Validate an admin target path (no traversal, must be an admin .php file).
	 *
	 * @param string $target Requested target after the slug.
	 * @return bool
	 */
	private function is_valid_admin_target( $target ) {
		if ( '' === $target || false !== strpos( $target, '..' ) || false !== strpos( $target, "\0" ) ) {
			return false;
		}
		// Allow foo.php or network/foo.php style admin scripts only.
		return (bool) preg_match( '#^([a-z0-9\-]+/)?[a-z0-9\-]+\.php$#i', $target );
	}

	/**
	 * Emit a hardened 404 to hide an endpoint.
	 *
	 * @return void
	 */
	private function render_404() {
		if ( ! function_exists( 'status_header' ) ) {
			require_once ABSPATH . WPINC . '/functions.php';
		}
		status_header( 404 );
		nocache_headers();
		if ( function_exists( 'wp_die' ) ) {
			wp_die(
				esc_html__( 'Page not found.', 'aaraa-white-label-admin' ),
				esc_html__( 'Not Found', 'aaraa-white-label-admin' ),
				array( 'response' => 404 )
			);
		}
		exit;
	}
}
