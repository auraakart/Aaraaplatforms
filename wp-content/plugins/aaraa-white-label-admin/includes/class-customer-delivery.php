<?php
/**
 * Customer delivery mapping.
 *
 * Adds Delivery Slot / Delivery Hub / Delivery Person to both customer edit
 * screens — the WordPress user editor and Aaraa Customer 360 — writing the meta
 * WCFM Delivery already reads, so a mapping set here drives its checkout
 * auto-assignment rather than sitting in a private copy.
 *
 * Meta keys:
 *   aaraa_delivery_slot   the customer's usual slot (this plugin's own key)
 *   _wcfmd_delivery_hub   WCFM Delivery's hub key
 *   _wcfmd_delivery_boy   WCFM Delivery's delivery person key
 *
 * The two WCFM keys carry a leading underscore. That is what
 * class-wcfmd-delivery-hub.php reads when it auto-assigns an order, and what
 * Order_Delivery reads to pre-fill a new order — writing the unprefixed names
 * would store a value nothing on this site ever looks at.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery mapping on the customer profile.
 */
class Customer_Delivery {

	const META_SLOT = 'aaraa_delivery_slot';
	const META_HUB  = '_wcfmd_delivery_hub';
	const META_BOY  = '_wcfmd_delivery_boy';

	// Friendly meta keys the CSV importer writes to. WCFM Delivery (and the
	// customer editor) read the underscored META_HUB / META_BOY keys, so an
	// import that only sets these would never surface. They are mirrored across.
	const IMPORT_HUB = 'aaraa_delivery_hub';
	const IMPORT_BOY = 'aaraa_delivery_boy';

	// Bumped when the one-time backfill of already-imported meta must run.
	const SYNC_FLAG = 'aaraa_deliv_meta_sync_v1';

	const NONCE = 'aaraa_customer_delivery';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'show_user_profile', array( $this, 'render_profile' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile' ) );
		add_action( 'user_profile_update_errors', array( $this, 'validate_profile' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		// Keep the importer's friendly keys in sync with WCFM's real keys, both
		// live (future imports) and as a one-time backfill (already-imported rows).
		add_action( 'added_user_meta', array( $this, 'mirror_imported_meta' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'mirror_imported_meta' ), 10, 4 );
		add_action( 'admin_init', array( $this, 'maybe_backfill_imported_meta' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Import key mirroring.
	 * --------------------------------------------------------------------- */

	/**
	 * When a CSV import writes aaraa_delivery_hub / aaraa_delivery_boy, copy the
	 * value into the underscored WCFM keys the rest of the site reads.
	 *
	 * @param int    $meta_id   Meta row id (unused).
	 * @param int    $user_id   User id.
	 * @param string $meta_key  Meta key written.
	 * @param mixed  $meta_value Value written.
	 * @return void
	 */
	public function mirror_imported_meta( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( self::IMPORT_HUB === $meta_key ) {
			$v = absint( $meta_value );
			if ( $v ) {
				update_user_meta( $user_id, self::META_HUB, $v );
			}
		} elseif ( self::IMPORT_BOY === $meta_key ) {
			$v = absint( $meta_value );
			if ( $v ) {
				update_user_meta( $user_id, self::META_BOY, $v );
			}
		}
	}

	/**
	 * One-time backfill: copy any previously-imported aaraa_delivery_hub /
	 * aaraa_delivery_boy meta into the WCFM keys so existing customers reflect
	 * their imported assignment. Runs once, guarded by an option flag.
	 *
	 * @return void
	 */
	public function maybe_backfill_imported_meta() {
		if ( '1' === get_option( self::SYNC_FLAG ) ) {
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s)",
				self::IMPORT_HUB,
				self::IMPORT_BOY
			)
		);
		foreach ( (array) $rows as $r ) {
			$v = absint( $r->meta_value );
			if ( ! $v ) {
				continue;
			}
			$target = ( self::IMPORT_HUB === $r->meta_key ) ? self::META_HUB : self::META_BOY;
			update_user_meta( (int) $r->user_id, $target, $v );
		}
		update_option( self::SYNC_FLAG, '1', false );
	}

	/* --------------------------------------------------------------------- *
	 * Assets.
	 * --------------------------------------------------------------------- */

	/**
	 * Load the hub → person cascade on the profile and Customer 360 screens.
	 *
	 * Reuses the order panel's script and its aaraa_hub_boys endpoint: the field
	 * ids match, so there is one cascade implementation rather than two.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$is_profile = in_array( $hook, array( 'profile.php', 'user-edit.php' ), true );

		// phpcs:ignore WordPress.Security.NonceVerification
		$page       = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$is_customer = ( Customers_Admin::PAGE === $page );

		if ( ! $is_profile && ! $is_customer ) {
			return;
		}

		wp_enqueue_script(
			'aaraa-order-delivery',
			AARAA_WLA_URL . 'assets/js/order-delivery.js',
			array( 'jquery' ),
			aaraa_asset_ver( 'assets/js/order-delivery.js' ),
			true
		);
		wp_localize_script(
			'aaraa-order-delivery',
			'AaraaOrderDelivery',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'aaraa_hub_boys' ),
				'none'  => __( '— Select person —', 'aaraa-white-label-admin' ),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Fields.
	 * --------------------------------------------------------------------- */

	/**
	 * The three mapped values for a customer.
	 *
	 * @param int $user_id User id.
	 * @return array{slot:int, hub:int, boy:int}
	 */
	public static function values( $user_id ) {
		return array(
			'slot' => (int) get_user_meta( $user_id, self::META_SLOT, true ),
			'hub'  => (int) get_user_meta( $user_id, self::META_HUB, true ),
			'boy'  => (int) get_user_meta( $user_id, self::META_BOY, true ),
		);
	}

	/**
	 * Render the three selects as form-table rows.
	 *
	 * Both the WordPress profile screen and the Customer 360 editor use a
	 * `form-table`, so the same rows drop into either one.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function fields( $user_id ) {
		$current = self::values( $user_id );
		$slots   = Delivery_Admin::slots_list();
		$hubs    = Delivery_Admin::hubs_list();
		$people  = Delivery_Admin::all_delivery_people( 0 );

		wp_nonce_field( self::NONCE, 'aaraa_customer_delivery_nonce', false );
		?>
		<tr>
			<th><label for="aaraa_delivery_slot"><?php esc_html_e( 'Delivery Slot', 'aaraa-white-label-admin' ); ?></label></th>
			<td>
				<select name="aaraa_delivery_slot" id="aaraa_delivery_slot" class="regular-text">
					<option value="0"><?php esc_html_e( '— Select slot —', 'aaraa-white-label-admin' ); ?></option>
					<?php foreach ( $slots as $slot ) : ?>
						<?php
						$label = $slot->name;
						if ( $slot->start_time || $slot->end_time ) {
							$label .= ' (' . $slot->start_time . '–' . $slot->end_time . ')';
						}
						if ( isset( $slot->status ) && 'active' !== $slot->status ) {
							$label .= ' — ' . __( 'inactive', 'aaraa-white-label-admin' );
						}
						?>
						<option value="<?php echo esc_attr( $slot->id ); ?>" <?php selected( $current['slot'], (int) $slot->id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $slots ) ) : ?>
					<p class="description"><?php esc_html_e( 'No slots yet — add them under AaraaDelivery → Delivery Slots.', 'aaraa-white-label-admin' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="aaraa_delivery_hub"><?php esc_html_e( 'Delivery Hub', 'aaraa-white-label-admin' ); ?></label></th>
			<td>
				<select name="aaraa_delivery_hub" id="aaraa_delivery_hub" class="regular-text">
					<option value="0"><?php esc_html_e( '— Select hub —', 'aaraa-white-label-admin' ); ?></option>
					<?php foreach ( $hubs as $hub ) : ?>
						<option value="<?php echo esc_attr( $hub->ID ); ?>" <?php selected( $current['hub'], (int) $hub->ID ); ?>><?php echo esc_html( $hub->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="aaraa_delivery_boy"><?php esc_html_e( 'Delivery Person', 'aaraa-white-label-admin' ); ?></label></th>
			<td>
				<select name="aaraa_delivery_boy" id="aaraa_delivery_boy" class="regular-text">
					<option value="0"><?php esc_html_e( '— Select person —', 'aaraa-white-label-admin' ); ?></option>
					<?php
					// Always show the currently-assigned person, even if they are not
					// (yet) linked to this hub, so an imported mapping stays visible.
					if ( $current['boy'] && ! isset( $people[ $current['boy'] ] ) ) {
						$boy_user                  = get_userdata( $current['boy'] );
						$people[ $current['boy'] ] = $boy_user ? $boy_user->display_name : ( '#' . $current['boy'] );
					}
					?>
					<?php foreach ( $people as $id => $label ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $current['boy'], (int) $id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'All delivery persons are listed. New orders inherit this mapping.', 'aaraa-white-label-admin' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Save.
	 * --------------------------------------------------------------------- */

	/**
	 * Read the posted mapping.
	 *
	 * @return array{slot:int, hub:int, boy:int}
	 */
	public static function posted() {
		// phpcs:disable WordPress.Security.NonceVerification -- callers verify.
		return array(
			'slot' => isset( $_POST['aaraa_delivery_slot'] ) ? absint( $_POST['aaraa_delivery_slot'] ) : 0,
			'hub'  => isset( $_POST['aaraa_delivery_hub'] ) ? absint( $_POST['aaraa_delivery_hub'] ) : 0,
			'boy'  => isset( $_POST['aaraa_delivery_boy'] ) ? absint( $_POST['aaraa_delivery_boy'] ) : 0,
		);
		// phpcs:enable
	}

	/**
	 * Why a hub/person combination cannot be saved, or an empty string.
	 *
	 * WCFM's checkout auto-assignment bails silently when the person does not
	 * belong to the customer's hub, so a mismatch is rejected here instead of
	 * being stored and quietly doing nothing.
	 *
	 * @param int $hub Hub id.
	 * @param int $boy Delivery person id.
	 * @return string
	 */
	public static function combination_error( $hub, $boy ) {
		if ( ! $boy ) {
			return '';
		}

		// Any existing user may be assigned as a delivery person; saving grants
		// them the delivery role (see save_values). Hub membership is not required.
		if ( ! get_userdata( $boy ) ) {
			return __( 'That delivery person no longer exists.', 'aaraa-white-label-admin' );
		}

		return '';
	}

	/**
	 * Write the mapping to a user.
	 *
	 * @param int   $user_id User id.
	 * @param array $values  slot/hub/boy.
	 * @return void
	 */
	public static function save_values( $user_id, $values ) {
		$map = array(
			self::META_SLOT => $values['slot'],
			self::META_HUB  => $values['hub'],
			self::META_BOY  => $values['boy'],
		);

		foreach ( $map as $key => $value ) {
			if ( $value ) {
				update_user_meta( $user_id, $key, $value );
			} else {
				// Clearing the field must remove the meta, not store a 0 that
				// WCFM would read as "mapped to hub 0".
				delete_user_meta( $user_id, $key );
			}
		}

		// Assigning a delivery person self-heals their role so WCFM recognises them.
		if ( ! empty( $values['boy'] ) ) {
			Delivery_Admin::ensure_delivery_role( (int) $values['boy'] );
		}

		// Push the mapping onto the customer's open orders / active subscriptions
		// that don't already have a delivery person of their own.
		self::propagate_to_open( $user_id, $values );
	}

	/**
	 * Fill the customer's open orders and active subscriptions with this mapping,
	 * but only where each record has no value yet (never overwrites a manual
	 * per-order assignment). Runs when the Aaraa 360 mapping is saved.
	 *
	 * @param int   $user_id Customer id.
	 * @param array $values  slot/hub/boy.
	 * @return void
	 */
	public static function propagate_to_open( $user_id, $values ) {
		$user_id = (int) $user_id;
		$boy     = (int) $values['boy'];
		$hub     = (int) $values['hub'];
		$slot    = (int) $values['slot'];

		if ( ! $user_id || ( ! $boy && ! $hub && ! $slot ) || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$ids = wc_get_orders(
			array(
				'customer' => $user_id,
				'type'     => array( 'shop_order', 'shop_subscription' ),
				'status'   => array( 'wc-processing', 'wc-pending', 'wc-on-hold', 'wc-active' ),
				'limit'    => 200,
				'return'   => 'ids',
			)
		);

		foreach ( (array) $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				continue;
			}

			$changed = false;

			// Delivery person (+ hub) only where the order has no person yet.
			if ( $boy && ! (int) $order->get_meta( Order_Delivery::META_BOY ) ) {
				$order->update_meta_data( Order_Delivery::META_BOY, (string) $boy );
				if ( $hub ) {
					$order->update_meta_data( Order_Delivery::META_HUB, (string) $hub );
				}
				$changed = true;
			}
			// Hub on its own, where empty.
			if ( $hub && ! (int) $order->get_meta( Order_Delivery::META_HUB ) ) {
				$order->update_meta_data( Order_Delivery::META_HUB, (string) $hub );
				$changed = true;
			}
			// Slot where empty.
			if ( $slot && ! (int) $order->get_meta( Order_Delivery::META_SLOT ) ) {
				$order->update_meta_data( Order_Delivery::META_SLOT, (string) $slot );
				$changed = true;
			}

			if ( $changed ) {
				$order->save();
			}
		}
	}

	/* --------------------------------------------------------------------- *
	 * WordPress profile screen.
	 * --------------------------------------------------------------------- */

	/**
	 * Render the section on the WordPress user editor.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public function render_profile( $user ) {
		if ( ! Customers_Admin::current_user_can_manage() ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Aaraa Delivery', 'aaraa-white-label-admin' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php self::fields( $user->ID ); ?>
		</table>
		<?php
	}

	/**
	 * Block the save when the hub and person do not match.
	 *
	 * @param \WP_Error $errors Errors.
	 * @param bool      $update Whether this is an update.
	 * @param \stdClass $user   User data.
	 * @return void
	 */
	public function validate_profile( $errors, $update, $user ) {
		if ( ! $update || ! isset( $_POST['aaraa_customer_delivery_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aaraa_customer_delivery_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		$values  = self::posted();
		$message = self::combination_error( $values['hub'], $values['boy'] );
		if ( $message ) {
			$errors->add( 'aaraa_delivery', $message );
		}
	}

	/**
	 * Persist the mapping from the WordPress user editor.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function save_profile( $user_id ) {
		if ( ! isset( $_POST['aaraa_customer_delivery_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aaraa_customer_delivery_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! Customers_Admin::current_user_can_manage() || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		self::save_values( $user_id, self::posted() );
	}
}
