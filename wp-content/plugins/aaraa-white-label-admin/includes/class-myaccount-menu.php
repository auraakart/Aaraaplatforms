<?php
/**
 * Reorder the My Account sidebar navigation.
 *
 * The Farmart theme, WooCommerce Subscriptions and the wallet plugin each add or
 * relabel My Account menu items on their own, so the final on-screen order is
 * whatever their combined hook registration order happens to produce. This pins
 * the order explicitly instead of relying on that, regardless of which of those
 * items are present on a given request.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * My Account navigation order.
 */
class MyAccount_Menu {

	/**
	 * The requested order, by endpoint key. Any item not listed here (e.g. a
	 * future addition from another plugin) is kept, appended after these in its
	 * original relative order, so nothing is ever silently hidden.
	 *
	 * @var string[]
	 */
	const ORDER = array(
		'subscriptions',
		'orders',
		'wps-wallet',      // "Wallet" — added by the wallet-system-for-woocommerce plugin.
		'edit-address',    // "Addresses".
		'edit-account',    // "Account details".
		'customer-logout', // "Log out" — always last.
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Very late priority so this runs after every other plugin's/theme's own
		// woocommerce_account_menu_items filter, whatever order those register in.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'reorder' ), 9999 );
	}

	/**
	 * Reorder the incoming menu items to the requested sequence.
	 *
	 * @param array<string,string> $items Endpoint key => label.
	 * @return array<string,string>
	 */
	public function reorder( $items ) {
		if ( ! is_array( $items ) || empty( $items ) ) {
			return $items;
		}

		$logout  = null;
		$ordered = array();

		foreach ( self::ORDER as $key ) {
			if ( ! array_key_exists( $key, $items ) ) {
				continue;
			}
			if ( 'customer-logout' === $key ) {
				$logout = $items[ $key ]; // Placed last, after any leftovers below.
				continue;
			}
			$ordered[ $key ] = $items[ $key ];
			unset( $items[ $key ] );
		}

		// Anything left over (e.g. Dashboard, Downloads, Payment methods, or an
		// item a future plugin adds) keeps its original relative order here,
		// still ahead of Log out.
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				continue; // Already captured above.
			}
			$ordered[ $key ] = $label;
		}

		if ( null !== $logout ) {
			$ordered['customer-logout'] = $logout;
		}

		return $ordered;
	}
}
