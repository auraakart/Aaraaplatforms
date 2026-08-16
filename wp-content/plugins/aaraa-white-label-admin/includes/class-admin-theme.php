<?php
/**
 * Aaraa SaaS admin theme + WordPress branding removal.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the admin colour theme and strips core WordPress branding.
 */
class Admin_Theme {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( aaraa_get_option( 'enable_admin_theme', 1 ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_theme' ) );
		}

		// Branding removal.
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ), 99 );
		add_filter( 'update_footer', array( $this, 'footer_version' ), 99 );

		if ( aaraa_get_option( 'remove_help_tab', 1 ) ) {
			add_action( 'admin_head', array( $this, 'remove_help_tab' ) );
		}

		// Screen Options tab is hidden as a white-label default.
		add_filter( 'screen_options_show_screen', '__return_false' );
	}

	/**
	 * Enqueue the admin stylesheet and inject the palette from settings.
	 *
	 * @return void
	 */
	public function enqueue_theme() {
		wp_enqueue_style( 'aaraa-admin', AARAA_WLA_URL . 'assets/css/admin.css', array(), aaraa_asset_ver( 'assets/css/admin.css' ) );

		$primary    = $this->color( 'color_primary', '#10B7D4' );
		$secondary  = $this->color( 'color_secondary', '#0A9AB5' );
		$background  = $this->color( 'color_background', '#F5FEFF' );
		$sidebar     = $this->color( 'color_sidebar', '#0F172A' );

		$inline = sprintf(
			':root{--aaraa-primary:%1$s;--aaraa-secondary:%2$s;--aaraa-bg:%3$s;--aaraa-sidebar:%4$s;}',
			$primary,
			$secondary,
			$background,
			$sidebar
		);
		wp_add_inline_style( 'aaraa-admin', $inline );
	}

	/**
	 * Replace the admin footer credit.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function footer_text( $text ) {
		$custom = (string) aaraa_get_option( 'custom_footer', 'Powered by Aaraa Platforms' );
		return '' !== $custom ? esc_html( $custom ) : $text;
	}

	/**
	 * Hide the WordPress version string in the footer.
	 *
	 * @param string $content Existing version text.
	 * @return string
	 */
	public function footer_version( $content ) {
		return aaraa_get_option( 'hide_wp_version', 1 ) ? '' : $content;
	}

	/**
	 * Remove the contextual Help tab across admin screens.
	 *
	 * @return void
	 */
	public function remove_help_tab() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen instanceof \WP_Screen ) {
			$screen->remove_help_tabs();
		}
	}

	/**
	 * Validate a colour setting.
	 *
	 * @param string $key      Setting key.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	private function color( $key, $fallback ) {
		$color = sanitize_hex_color( (string) aaraa_get_option( $key, $fallback ) );
		return $color ? $color : $fallback;
	}
}
