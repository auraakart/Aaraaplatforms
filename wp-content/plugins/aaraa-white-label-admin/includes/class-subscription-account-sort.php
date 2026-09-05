<?php
/**
 * List active subscriptions first on the customer's My Account > Subscriptions page.
 *
 * WooCommerce Subscriptions builds that list from wcs_get_users_subscriptions(),
 * which returns the customer's subscriptions in whatever order the underlying
 * store query gives them (not grouped by status), then paginates the result.
 * This reorders the full list — active subscriptions first, everything else
 * after — before pagination is applied, so the ordering holds across every page.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Active-first ordering for the customer-facing subscriptions list.
 */
class Subscription_Account_Sort {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wcs_get_users_subscriptions', array( $this, 'active_first' ), 10, 1 );
	}

	/**
	 * Move active subscriptions to the front, keeping every other status in its
	 * existing relative order (a stable partition, not a full status sort).
	 *
	 * @param array<int,\WC_Subscription> $subscriptions Subscription id => object.
	 * @return array<int,\WC_Subscription>
	 */
	public function active_first( $subscriptions ) {
		if ( ! is_array( $subscriptions ) || count( $subscriptions ) < 2 ) {
			return $subscriptions;
		}

		$active = array();
		$rest   = array();

		foreach ( $subscriptions as $id => $subscription ) {
			if ( is_object( $subscription ) && method_exists( $subscription, 'has_status' ) && $subscription->has_status( 'active' ) ) {
				$active[ $id ] = $subscription;
			} else {
				$rest[ $id ] = $subscription;
			}
		}

		return $active + $rest;
	}
}
