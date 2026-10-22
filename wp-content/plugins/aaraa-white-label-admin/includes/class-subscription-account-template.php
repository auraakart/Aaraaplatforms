<?php
/**
 * Swaps in this plugin's `myaccount/my-subscriptions.php` template (Product
 * + Delivery Schedule columns) without requiring a theme override.
 *
 * WooCommerce's template loader (wc_get_template()) always checks the active
 * theme for `woocommerce/{template}` first; only when the theme has no such
 * file does it fall back to the shipping plugin's own copy (here,
 * WooCommerce Subscriptions' templates/myaccount/my-subscriptions.php).
 *
 * Two filters are hooked, not one:
 *  - `wc_get_template` fires unconditionally on every wc_get_template() call
 *    (the very last step, after WooCommerce's own resolved-path object cache
 *    is read), so it is the reliable place to redirect this lookup.
 *  - `woocommerce_locate_template` covers any caller that goes through
 *    wc_locate_template()/wc_locate_template() directly instead.
 * The redirect only ever applies when the theme hasn't already provided its
 * own override — a real theme override, if one is ever added, still wins.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects the my-subscriptions template lookup to this plugin.
 */
class Subscription_Account_Template {

	const TEMPLATE = 'myaccount/my-subscriptions.php';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_locate_template', array( $this, 'use_plugin_template' ), 20, 2 );
		// wc_get_template()'s resolved path is read from an object cache keyed
		// without regard to this filter, so a cached hit would otherwise skip
		// woocommerce_locate_template entirely on a persistent-object-cache
		// site. This filter runs unconditionally after that cache read.
		add_filter( 'wc_get_template', array( $this, 'use_plugin_template' ), 20, 2 );
	}

	/**
	 * Point the lookup at this plugin's template when the theme has no
	 * override of its own.
	 *
	 * @param string $template      The already-resolved template path (a theme
	 *                               override if the theme has one, otherwise
	 *                               WooCommerce Subscriptions' own template).
	 * @param string $template_name Template name being looked up.
	 * @return string
	 */
	public function use_plugin_template( $template, $template_name ) {
		if ( self::TEMPLATE !== $template_name ) {
			return $template;
		}

		// A path under the active (child or parent) theme means the theme
		// already overrides this file — leave that in place.
		if ( 0 === strpos( $template, get_stylesheet_directory() ) || 0 === strpos( $template, get_template_directory() ) ) {
			return $template;
		}

		$plugin_template = AARAA_WLA_DIR . 'templates/' . self::TEMPLATE;

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}
}
