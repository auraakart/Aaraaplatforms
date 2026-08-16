<?php
/**
 * Login screen branding.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Applies logo, colours and background to wp-login.php and removes WP branding.
 */
class Login_Branding {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'login_headerurl', array( $this, 'logo_url' ) );
		add_filter( 'login_headertext', array( $this, 'logo_text' ) );
		add_filter( 'login_message', array( $this, 'maybe_wrap_message' ) );
	}

	/**
	 * Enqueue the login stylesheet and inject dynamic colours.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( 'aaraa-login', AARAA_WLA_URL . 'assets/css/login.css', array(), aaraa_asset_ver( 'assets/css/login.css' ) );

		$bg_color = $this->sanitize_color( aaraa_get_option( 'login_bg_color', '#F5FEFF' ), '#F5FEFF' );
		$btn      = $this->sanitize_color( aaraa_get_option( 'login_btn_color', '#10B7D4' ), '#10B7D4' );
		$btn_hov  = $this->sanitize_color( aaraa_get_option( 'login_btn_hover', '#0A9AB5' ), '#0A9AB5' );

		// Use the uploaded logo if set, otherwise the bundled Aaraa logo.
		$logo_id  = (int) aaraa_get_option( 'login_logo_id', 0 );
		$logo_src = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		if ( ! $logo_src ) {
			$logo_src = AARAA_WLA_URL . 'assets/images/aaraa-platforms-web.png';
		}
		$logo_css = sprintf(
			'#login h1 a{background-image:url(%s);background-size:contain;background-repeat:no-repeat;background-position:center;width:100%%;height:66px;margin-bottom:12px;}',
			esc_url( $logo_src )
		);

		$bg_image_css = '';
		$bg_id        = (int) aaraa_get_option( 'login_bg_image_id', 0 );
		if ( $bg_id > 0 ) {
			$src = wp_get_attachment_image_url( $bg_id, 'full' );
			if ( $src ) {
				$bg_image_css = sprintf( 'body.login{background-image:url(%s);background-size:cover;background-position:center;}', esc_url( $src ) );
			}
		}

		$inline = sprintf(
			':root{--aaraa-login-bg:%1$s;--aaraa-btn:%2$s;--aaraa-btn-hover:%3$s;}%4$s%5$s',
			$bg_color,
			$btn,
			$btn_hov,
			$logo_css,
			$bg_image_css
		);

		wp_add_inline_style( 'aaraa-login', $inline );
	}

	/**
	 * Point the login logo at the site home.
	 *
	 * @return string
	 */
	public function logo_url() {
		return home_url( '/' );
	}

	/**
	 * Replace the logo alt/title text with the site name.
	 *
	 * @return string
	 */
	public function logo_text() {
		return get_bloginfo( 'name' );
	}

	/**
	 * Pass the login message through untouched (hook kept for extensibility).
	 *
	 * @param string $message Existing message HTML.
	 * @return string
	 */
	public function maybe_wrap_message( $message ) {
		return $message;
	}

	/**
	 * Validate a hex colour, falling back to a default.
	 *
	 * @param mixed  $value    Candidate colour.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	private function sanitize_color( $value, $fallback ) {
		$color = sanitize_hex_color( (string) $value );
		return $color ? $color : $fallback;
	}
}
