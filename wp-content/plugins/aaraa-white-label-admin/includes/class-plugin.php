<?php
/**
 * Singleton loader that wires up every module.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin bootstrap.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Loaded modules.
	 *
	 * @var array<string, object>
	 */
	private $modules = array();

	/**
	 * Retrieve (and lazily create) the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire modules on construction.
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->boot_modules();
	}

	/**
	 * Prevent cloning / unserialising the singleton.
	 */
	private function __clone() {}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		add_action(
			'init',
			static function () {
				load_plugin_textdomain( 'aaraa-white-label-admin', false, dirname( AARAA_WLA_BASENAME ) . '/languages' );
			}
		);
	}

	/**
	 * Instantiate and initialise each module.
	 *
	 * The URL rewriter is booted first and unconditionally so its
	 * plugins_loaded:1 interceptor is registered before that action fires.
	 *
	 * @return void
	 */
	private function boot_modules() {
		$this->modules['url']      = new Admin_URL_Rewriter();
		$this->modules['login']    = new Login_Branding();
		$this->modules['theme']    = new Admin_Theme();
		$this->modules['adminbar'] = new Admin_Bar();
		$this->modules['dashboard'] = new Dashboard();
		$this->modules['mbanner']  = new Mobile_Banner();
		$this->modules['notify']   = new Notifications_Admin();
		$this->modules['whatsapp'] = new WhatsApp_Admin();
		$this->modules['firebase'] = new Firebase_Admin();
		$this->modules['roles']    = new Roles();
		$this->modules['wallet']   = new Wallet_Admin();
		$this->modules['delivery'] = new Delivery_Admin();
		$this->modules['orderdel'] = new Order_Delivery();
		$this->modules['odatefilter'] = new Orders_Date_Filter();
		$this->modules['exports']  = new Exports();
		$this->modules['slotsapi'] = new Delivery_Slots_API();
		$this->modules['subdel']   = new Subscription_Delivery();
		$this->modules['subfront'] = new Subscription_Frontend();
		$this->modules['schedapi'] = new Delivery_Schedule_API();
		$this->modules['substatus'] = new Subscription_Status();
		$this->modules['subadv']   = new Subscription_Advance();
		$this->modules['subrenew'] = new Subscription_Renewal();
		$this->modules['renwallet'] = new Renewal_Wallet();
		$this->modules['subapi']   = new Subscription_API();
		$this->modules['custdel']  = new Customer_Delivery();
		$this->modules['report']   = new Delivery_Report();
		$this->modules['pausereport'] = new Subscription_Pause_Report();
		$this->modules['resumereport'] = new Subscription_Resume_Report();
		$this->modules['applog']   = new Application_Log();
		$this->modules['security'] = new Security();
		$this->modules['settings'] = new Settings();

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'init' ) ) {
				$module->init();
			}
		}

		// Create/upgrade custom tables on existing installs without reactivation.
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Run install routines once per version (covers already-active installs).
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( get_option( 'aaraa_db_version' ) === AARAA_WLA_VERSION ) {
			return;
		}
		Customer_Logs::install();
		Delivery_Admin::install();
		Mobile_Banner::install();
		update_option( 'aaraa_db_version', AARAA_WLA_VERSION );
	}

	/**
	 * Access a loaded module.
	 *
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function module( $key ) {
		return $this->modules[ $key ] ?? null;
	}

	/* --------------------------------------------------------------------- *
	 * Lifecycle: activation / deactivation.
	 * --------------------------------------------------------------------- */

	/**
	 * Activation handler. Seeds defaults, writes the rewrite alias and flushes rules.
	 *
	 * @param bool $network_wide Whether activation is network-wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			foreach ( $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single();
				restore_current_blog();
			}
			if ( false === get_site_option( AARAA_OPTION, false ) ) {
				update_site_option( AARAA_OPTION, aaraa_default_settings() );
			}
		} else {
			self::activate_single();
		}
	}

	/**
	 * Per-site activation work.
	 *
	 * @return void
	 */
	private static function activate_single() {
		if ( false === get_option( AARAA_OPTION, false ) ) {
			add_option( AARAA_OPTION, aaraa_default_settings() );
		}

		Customer_Logs::install();
		Delivery_Admin::install();
		Mobile_Banner::install();

		$slug = aaraa_get_option( 'admin_slug', 'aaraa-admin' );
		Admin_URL_Rewriter::add_rewrite_tag( $slug );
		Admin_URL_Rewriter::write_server_alias( $slug );

		flush_rewrite_rules( false );
	}

	/**
	 * Deactivation handler. Removes the server alias and flushes rules.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Admin_URL_Rewriter::remove_server_alias();
		Delivery_Report::unschedule();
		flush_rewrite_rules( false );
	}
}
