<?php
/**
 * Suppresses specific WCFM notification emails without touching WCFM's own
 * (third-party, update-managed) plugin files.
 *
 * WC_Frontend_Manager's notification system sends an email for every
 * message type unless `wcfm_is_allow_notification_email` returns false for
 * it (class-wcfm-notification.php, wcfm_send_direct_message()). That filter
 * only gates the EMAIL — the in-app notification bell/message is still
 * created either way, so disabling an email here doesn't remove the
 * in-app notification for it.
 *
 * Currently suppressed:
 *   - delivery_boy_assign — "Notification - Delivery Person Assigned",
 *     sent to the delivery person's account email every time an order is
 *     assigned to them (wc-frontend-manager-delivery,
 *     wcfmd_delivery_boy_assigned()).
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Filters WCFM's own notification-email gate.
 */
class Wcfm_Notification_Filters {

	/**
	 * WCFM message types whose email is suppressed (in-app notification
	 * still fires as normal).
	 *
	 * @var string[]
	 */
	const DISABLED_TYPES = array( 'delivery_boy_assign' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wcfm_is_allow_notification_email', array( $this, 'maybe_disable' ), 20, 3 );
	}

	/**
	 * @param bool   $allow               Whether WCFM would send the email.
	 * @param string $wcfm_messages_type  The message type (e.g. 'delivery_boy_assign').
	 * @param mixed  $message_to          Recipient (unused).
	 * @return bool
	 */
	public function maybe_disable( $allow, $wcfm_messages_type, $message_to ) {
		if ( in_array( $wcfm_messages_type, self::DISABLED_TYPES, true ) ) {
			return false;
		}
		return $allow;
	}
}
