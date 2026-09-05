<?php
/**
 * Trim the customer-facing subscription Actions row to just "Cancel".
 *
 * The My Account > View Subscription page ("Actions" row) can show several
 * buttons depending on the subscription's state and which gateway/renewal
 * features are active — Reactivate, Resubscribe, Renew now (early renewal),
 * Change payment method — all added by WooCommerce Subscriptions (and its
 * early-renewal/change-payment-method features) through the same filter. This
 * keeps only Cancel, whichever of those happen to be present on a given
 * subscription.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Cancel-only subscription actions on the customer's view-subscription page.
 */
class Subscription_Account_Actions {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Very late priority so this runs after every action is added — core's
		// Reactivate/Resubscribe/Cancel, the early-renewal "Renew now" button, and
		// the change-payment-method button — regardless of registration order.
		add_filter( 'wcs_view_subscription_actions', array( $this, 'cancel_only' ), 9999 );
	}

	/**
	 * Keep only the Cancel action, if present.
	 *
	 * @param array<string,array> $actions Action key => action data.
	 * @return array<string,array>
	 */
	public function cancel_only( $actions ) {
		if ( ! is_array( $actions ) ) {
			return $actions;
		}
		return isset( $actions['cancel'] ) ? array( 'cancel' => $actions['cancel'] ) : array();
	}
}
