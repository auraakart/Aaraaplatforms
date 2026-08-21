<?php
/**
 * Procedural helpers: the settings schema and option accessors.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The option key everything is stored under.
 */
const AARAA_OPTION = 'aaraa_settings';

/**
 * Field schema. Drives defaults, rendering, sanitisation and the tab layout.
 *
 * Each entry: [ type, default, tab, label, description ].
 * Types: text, textarea, checkbox, color, slug, media (attachment ID).
 *
 * @return array<string, array<string, mixed>>
 */
function aaraa_schema() {
	return array(
		// General.
		'enable_admin_theme'    => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'general' ),
		'custom_footer'         => array( 'type' => 'text', 'default' => 'Powered by Aaraa Platforms', 'tab' => 'general' ),
		'rename_shop_manager'   => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'general' ),
		'shop_manager_label'    => array( 'type' => 'text', 'default' => 'Shop Owner', 'tab' => 'general' ),
		'empty_shop_owner_menu' => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'general' ),

		// Branding (admin theme colours).
		'color_primary'         => array( 'type' => 'color', 'default' => '#10B7D4', 'tab' => 'branding' ),
		'color_secondary'       => array( 'type' => 'color', 'default' => '#0A9AB5', 'tab' => 'branding' ),
		'color_background'      => array( 'type' => 'color', 'default' => '#F5FEFF', 'tab' => 'branding' ),
		'color_sidebar'         => array( 'type' => 'color', 'default' => '#0F172A', 'tab' => 'branding' ),
		'admin_logo_id'         => array( 'type' => 'media', 'default' => 0, 'tab' => 'branding' ),

		// Admin URL.
		'enable_admin_url'      => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'admin_url' ),
		'admin_slug'            => array( 'type' => 'slug', 'default' => 'aaraa-admin', 'tab' => 'admin_url' ),

		// Login.
		'enable_login_url'      => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'login' ),
		'login_logo_id'         => array( 'type' => 'media', 'default' => 0, 'tab' => 'login' ),
		'login_bg_color'        => array( 'type' => 'color', 'default' => '#F5FEFF', 'tab' => 'login' ),
		'login_bg_image_id'     => array( 'type' => 'media', 'default' => 0, 'tab' => 'login' ),
		'login_btn_color'       => array( 'type' => 'color', 'default' => '#10B7D4', 'tab' => 'login' ),
		'login_btn_hover'       => array( 'type' => 'color', 'default' => '#0A9AB5', 'tab' => 'login' ),

		// Dashboard.
		'dashboard_title'       => array( 'type' => 'text', 'default' => 'Aaraa Dashboard', 'tab' => 'dashboard' ),
		'enable_welcome_widget' => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'dashboard' ),
		'welcome_title'         => array( 'type' => 'text', 'default' => 'Welcome to Aaraa Platforms', 'tab' => 'dashboard' ),
		'welcome_subtitle'      => array( 'type' => 'text', 'default' => 'Vibrant E-Commerce | Seamless App Platform', 'tab' => 'dashboard' ),

		// Advanced (security + branding removal).
		'remove_wp_logo'        => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'advanced' ),
		'remove_help_tab'       => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'advanced' ),
		'hide_wp_version'       => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'advanced' ),
		'disable_xmlrpc'        => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'advanced' ),
		'disable_file_editor'   => array( 'type' => 'checkbox', 'default' => 1, 'tab' => 'advanced' ),
	);
}

/**
 * Human labels for the settings fields.
 *
 * Deliberately kept out of aaraa_schema(): the schema is read at plugin-load
 * time (module init() -> aaraa_get_option()), and calling __() that early trips
 * WordPress 6.7's "_load_textdomain_just_in_time was called incorrectly" notice.
 * This function runs only when the settings screen renders - well after `init`.
 *
 * @return array<string, string>
 */
function aaraa_field_labels() {
	return array(
		'enable_admin_theme'    => __( 'Enable Aaraa admin theme', 'aaraa-white-label-admin' ),
		'custom_footer'         => __( 'Admin footer text', 'aaraa-white-label-admin' ),
		'rename_shop_manager'   => __( 'Rename "Shop manager" role', 'aaraa-white-label-admin' ),
		'shop_manager_label'    => __( 'Shop manager display name', 'aaraa-white-label-admin' ),
		'empty_shop_owner_menu' => __( 'Empty admin sidebar for Shop Owner', 'aaraa-white-label-admin' ),
		'color_primary'         => __( 'Primary', 'aaraa-white-label-admin' ),
		'color_secondary'       => __( 'Secondary (hover)', 'aaraa-white-label-admin' ),
		'color_background'      => __( 'Admin background', 'aaraa-white-label-admin' ),
		'color_sidebar'         => __( 'Sidebar', 'aaraa-white-label-admin' ),
		'admin_logo_id'         => __( 'Admin bar logo', 'aaraa-white-label-admin' ),
		'enable_admin_url'      => __( 'Enable custom admin URL', 'aaraa-white-label-admin' ),
		'admin_slug'            => __( 'Admin / login slug', 'aaraa-white-label-admin' ),
		'enable_login_url'      => __( 'Hide wp-login.php (use slug)', 'aaraa-white-label-admin' ),
		'login_logo_id'         => __( 'Login logo', 'aaraa-white-label-admin' ),
		'login_bg_color'        => __( 'Login background colour', 'aaraa-white-label-admin' ),
		'login_bg_image_id'     => __( 'Login background image', 'aaraa-white-label-admin' ),
		'login_btn_color'       => __( 'Button colour', 'aaraa-white-label-admin' ),
		'login_btn_hover'       => __( 'Button hover colour', 'aaraa-white-label-admin' ),
		'dashboard_title'       => __( 'Dashboard menu label', 'aaraa-white-label-admin' ),
		'enable_welcome_widget' => __( 'Show welcome widget', 'aaraa-white-label-admin' ),
		'welcome_title'         => __( 'Welcome title', 'aaraa-white-label-admin' ),
		'welcome_subtitle'      => __( 'Welcome subtitle', 'aaraa-white-label-admin' ),
		'remove_wp_logo'        => __( 'Remove WordPress logo (admin bar)', 'aaraa-white-label-admin' ),
		'remove_help_tab'       => __( 'Remove Help tab', 'aaraa-white-label-admin' ),
		'hide_wp_version'       => __( 'Hide WordPress version', 'aaraa-white-label-admin' ),
		'disable_xmlrpc'        => __( 'Disable XML-RPC', 'aaraa-white-label-admin' ),
		'disable_file_editor'   => __( 'Disable theme/plugin file editor', 'aaraa-white-label-admin' ),
	);
}

/**
 * Default settings, derived from the schema.
 *
 * @return array<string, mixed>
 */
function aaraa_default_settings() {
	$defaults = array();
	foreach ( aaraa_schema() as $key => $field ) {
		$defaults[ $key ] = $field['default'];
	}
	return $defaults;
}

/**
 * Get all settings merged over defaults.
 *
 * Network-activated multisite stores options network-wide; otherwise per-site.
 *
 * @return array<string, mixed>
 */
function aaraa_get_settings() {
	$stored = aaraa_is_network_mode()
		? get_site_option( AARAA_OPTION, array() )
		: get_option( AARAA_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return wp_parse_args( $stored, aaraa_default_settings() );
}

/**
 * Get a single setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Fallback if the key is absent.
 * @return mixed
 */
function aaraa_get_option( $key, $default = null ) {
	$settings = aaraa_get_settings();
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/**
 * Persist the full settings array.
 *
 * @param array<string, mixed> $settings Settings to store.
 * @return void
 */
function aaraa_update_settings( array $settings ) {
	if ( aaraa_is_network_mode() ) {
		update_site_option( AARAA_OPTION, $settings );
	} else {
		update_option( AARAA_OPTION, $settings );
	}
}

/**
 * Whether the plugin operates network-wide (network-activated on multisite).
 *
 * @return bool
 */
function aaraa_is_network_mode() {
	if ( ! is_multisite() ) {
		return false;
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active_for_network( AARAA_WLA_BASENAME );
}

/**
 * The capability required to manage settings (network vs single).
 *
 * @return string
 */
function aaraa_manage_cap() {
	return aaraa_is_network_mode() ? 'manage_network_options' : 'manage_options';
}

/**
 * Cache-busting version for a bundled asset: its file modification time, so any
 * edit invalidates the browser cache. Falls back to the plugin version.
 *
 * @param string $relative Asset path relative to the plugin root (e.g. 'assets/css/admin.css').
 * @return string
 */
function aaraa_asset_ver( $relative ) {
	$path = AARAA_WLA_DIR . ltrim( $relative, '/' );
	$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	return $mtime ? (string) $mtime : AARAA_WLA_VERSION;
}

/**
 * When a subscription RESUMES, end only the current pause without discarding any
 * still-UPCOMING pause dates the customer scheduled.
 *
 * The pause engine used to delete the whole `_wcfmu_pause_dates` meta on resume,
 * which wiped future pause blocks too. This keeps every date AFTER today (the
 * upcoming pauses, which must still take effect and must stay visible in the
 * pause reports / renewal skip) and removes only today + past dates (the current
 * pause being ended). If nothing upcoming remains, the meta is removed.
 *
 * Note: this is for the "resume now" paths only. An explicit "cancel scheduled
 * pause" (clear_pause_schedule) still deletes the dates outright, because there
 * the customer is deliberately cancelling those future dates.
 *
 * @param int $sub_id Subscription id.
 * @return void
 */
function aaraa_retain_future_pause_dates( $sub_id ) {
	/*
	 * Deliberately a no-op on `_wcfmu_pause_dates`.
	 *
	 * Ending a pause (resume / window-passed / renewal skip) must NOT delete or
	 * prune the subscription's pause dates — they have to persist for the pause /
	 * resume reports, the renewal-skip guard and the panel's "Scheduled pause
	 * dates" list. The callers still delete `_wcfmu_pause_resume` separately,
	 * which is what stops the reconciler from re-scanning a finished window, so
	 * keeping the dates here has no side effect on status handling.
	 *
	 * The old behaviour dropped today + past dates (and deleted the meta when no
	 * strictly-future date remained), which is exactly what wiped a pause after a
	 * renewal ran or the next payment was updated. We keep every date instead.
	 */
	unset( $sub_id );
}
