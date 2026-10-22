<?php
/**
 * "Aaraa Delivery" panel on the order edit screen.
 *
 * Adds Delivery Slot / Delivery Hub / Delivery Person selectors to
 * admin.php?page=wc-orders&action=edit&id=… (HPOS) and the legacy post editor.
 * The person list is filtered by the chosen hub, mirroring how WCFM Delivery
 * links a delivery boy to a hub through `_wcfmd_delivery_hub` user meta.
 *
 * On save the assignment is also pushed into WCFM by firing
 * `wcfmd_delivery_boy_assigned` for every order item — the same action
 * WCFM's own auto-assignment uses — so its delivery tables and stats stay
 * in sync rather than us keeping a private copy.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Order delivery mapping metabox.
 */
class Order_Delivery {

	const META_SLOT = '_aaraa_delivery_slot';
	const META_HUB  = '_aaraa_delivery_hub';
	const META_BOY  = '_aaraa_delivery_boy';

	const NONCE = 'aaraa_order_delivery';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		// Fires for both HPOS and the legacy post editor (orders + subscriptions).
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ), 20, 2 );
		add_action( 'woocommerce_process_shop_subscription_meta', array( $this, 'save' ), 20, 2 );
		add_action( 'wp_ajax_aaraa_hub_boys', array( $this, 'ajax_hub_boys' ) );
		add_action( 'wp_ajax_aaraa_save_order_delivery', array( $this, 'ajax_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		// Orders list filters — HPOS (admin.php?page=wc-orders).
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'render_filters' ), 20, 2 );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'filter_query_args' ) );

		// Orders list filters — legacy posts table (edit.php?post_type=shop_order).
		add_action( 'restrict_manage_posts', array( $this, 'render_filters_legacy' ) );
		add_filter( 'request', array( $this, 'filter_request_legacy' ) );

		// Auto-assign delivery slot/hub/person from the customer profile whenever
		// an order, subscription or renewal order is created. A field the profile
		// doesn't set is left as-is.
		add_action( 'woocommerce_new_order', array( $this, 'auto_on_new_order' ), 20, 1 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'auto_on_checkout_order' ), 20, 1 );
		add_action( 'woocommerce_new_subscription', array( $this, 'auto_on_new_subscription' ), 20, 1 );
		add_filter( 'wcs_renewal_order_created', array( $this, 'auto_on_renewal_order' ), 20, 2 );
	}

	/* --------------------------------------------------------------------- *
	 * Auto-assignment from the customer profile at creation.
	 * --------------------------------------------------------------------- */

	/**
	 * Any new order (checkout, API, app, admin). Renewal orders are shop_orders
	 * too, so this also covers them; the renewal filter is a belt-and-braces run
	 * once WCS has copied the line items across.
	 *
	 * @param int $order_id New order id.
	 * @return void
	 */
	public function auto_on_new_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && 'shop_order' === $order->get_type() ) {
			$this->maybe_autofill_delivery( $order, true );
		}
	}

	/**
	 * Checkout order — fires after line items are attached, so WCFM's per-item
	 * assignment has products to work with (woocommerce_new_order fires earlier,
	 * before items exist on a classic checkout).
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function auto_on_checkout_order( $order ) {
		$this->maybe_autofill_delivery( $order, true );
	}

	/**
	 * A newly created subscription. Meta only — a subscription is a template, not
	 * a delivery run, so nothing is pushed into WCFM's per-item tables here.
	 *
	 * @param int $subscription_id Subscription id.
	 * @return void
	 */
	public function auto_on_new_subscription( $subscription_id ) {
		$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : wc_get_order( $subscription_id );
		if ( $subscription ) {
			$this->maybe_autofill_delivery( $subscription, false );
		}
	}

	/**
	 * A renewal order (wcs_renewal_order_created is a filter — return the order).
	 *
	 * @param \WC_Order        $renewal_order Renewal order.
	 * @param \WC_Subscription $subscription  Source subscription.
	 * @return \WC_Order
	 */
	public function auto_on_renewal_order( $renewal_order, $subscription ) {
		if ( $renewal_order instanceof \WC_Order ) {
			$this->maybe_autofill_delivery( $renewal_order, true );
		}
		return $renewal_order;
	}

	/**
	 * Copy the customer's profile delivery mapping onto the order/subscription.
	 *
	 * A profile value fills the matching order field only when the order does not
	 * already have one; an unset profile value leaves the order field untouched.
	 * Idempotent, so it is safe to fire from several creation hooks.
	 *
	 * @param \WC_Order $order      Order or subscription.
	 * @param bool      $sync_items Whether to push the person into WCFM's per-item
	 *                              delivery tables (orders only, not subscriptions).
	 * @return void
	 */
	public function maybe_autofill_delivery( $order, $sync_items = true ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$customer_id = $order->get_user_id();
		if ( ! $customer_id ) {
			return;
		}

		// Wallet recharge / renewal top-up orders are not deliveries — never tag
		// them, or they would show up on the delivery sheets and filters.
		if ( $order->get_meta( '_aaraa_renewal_sub_id' ) || 'yes' === $order->get_meta( 'wps_wallet_recharge_order' ) ) {
			return;
		}
		$recharge_pid = (int) get_option( 'wps_wsfw_rechargeable_product_id', 0 );
		if ( $recharge_pid ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $recharge_pid ) {
					return;
				}
			}
		}

		$p_slot = (int) get_user_meta( $customer_id, Customer_Delivery::META_SLOT, true );
		$p_hub  = (int) get_user_meta( $customer_id, Customer_Delivery::META_HUB, true );
		$p_boy  = (int) get_user_meta( $customer_id, Customer_Delivery::META_BOY, true );
		if ( ! $p_slot && ! $p_hub && ! $p_boy ) {
			return; // Nothing mapped on the profile — leave the order alone.
		}

		$o_slot = (int) $order->get_meta( self::META_SLOT );
		$o_hub  = (int) $order->get_meta( self::META_HUB );
		$o_boy  = (int) $order->get_meta( self::META_BOY );

		$slot = $o_slot ? $o_slot : $p_slot;
		$hub  = $o_hub ? $o_hub : $p_hub;
		$boy  = $o_boy ? $o_boy : $p_boy;

		// Never write a delivery person who no longer exists; self-heal the role
		// for existing users imported without it.
		if ( $boy && ! Delivery_Admin::ensure_delivery_role( $boy ) ) {
			$boy = 0;
		}

		if ( $slot !== $o_slot || $hub !== $o_hub || $boy !== $o_boy ) {
			$order->update_meta_data( self::META_SLOT, $slot ? (string) $slot : '' );
			$order->update_meta_data( self::META_HUB, $hub );
			$order->update_meta_data( self::META_BOY, $boy );
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: 1: slot, 2: hub, 3: delivery person */
					__( 'Aaraa Delivery set from customer profile — Slot: %1$s | Hub: %2$s | Person: %3$s', 'aaraa-white-label-admin' ),
					$this->slot_name( $slot ),
					$this->hub_name( $hub ),
					$boy ? $this->person_name( $boy ) : __( 'none', 'aaraa-white-label-admin' )
				)
			);
		}

		if ( $sync_items && $boy ) {
			$this->sync_delivery_person( $order, $boy, $o_boy );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Orders list filters.
	 * --------------------------------------------------------------------- */

	/**
	 * The three filter values currently in the request.
	 *
	 * @return array{slot:int, hub:int, boy:int}
	 */
	private function current_filters() {
		// phpcs:disable WordPress.Security.NonceVerification
		// Person filter: 0 = all, -1 = not assigned, positive = a specific person.
		$boy = isset( $_GET['aaraa_boy'] ) ? (int) $_GET['aaraa_boy'] : 0;
		if ( $boy < -1 ) {
			$boy = 0;
		}
		return array(
			'slot' => isset( $_GET['aaraa_slot'] ) ? absint( $_GET['aaraa_slot'] ) : 0,
			'hub'  => isset( $_GET['aaraa_hub'] ) ? absint( $_GET['aaraa_hub'] ) : 0,
			'boy'  => $boy,
		);
		// phpcs:enable
	}

	/**
	 * Render the Slot / Hub / Person dropdowns above the orders list.
	 *
	 * The Person list is narrowed to the selected hub, so picking a hub and
	 * filtering leaves only that hub's people to choose from.
	 *
	 * @param string $order_type Order type (HPOS) — unused for the legacy caller.
	 * @param string $which      'top' or 'bottom'.
	 * @return void
	 */
	public function render_filters( $order_type = 'shop_order', $which = 'top' ) {
		if ( 'top' !== $which ) {
			return;
		}
		if ( ! in_array( $order_type, array( 'shop_order', 'shop_subscription' ), true ) ) {
			return;
		}

		$cur    = $this->current_filters();
		$slots  = $this->slots();
		$hubs   = $this->hubs();
		// Filter dropdown lists every assignable person (role + assigned-on-profile),
		// not just the selected hub's, so no migrated boy is hidden.
		$people = $this->all_delivery_people();
		?>
		<select name="aaraa_slot" id="aaraa_slot_filter">
			<option value="0"><?php esc_html_e( 'All delivery slots', 'aaraa-white-label-admin' ); ?></option>
			<?php foreach ( $slots as $slot ) : ?>
				<?php
				$label = $slot->name;
				if ( $slot->start_time || $slot->end_time ) {
					$label .= ' (' . $slot->start_time . '–' . $slot->end_time . ')';
				}
				?>
				<option value="<?php echo esc_attr( $slot->id ); ?>" <?php selected( $cur['slot'], (int) $slot->id ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>

		<select name="aaraa_hub" id="aaraa_hub_filter">
			<option value="0"><?php esc_html_e( 'All delivery hubs', 'aaraa-white-label-admin' ); ?></option>
			<?php foreach ( $hubs as $hub ) : ?>
				<option value="<?php echo esc_attr( $hub->ID ); ?>" <?php selected( $cur['hub'], (int) $hub->ID ); ?>><?php echo esc_html( $hub->name ); ?></option>
			<?php endforeach; ?>
		</select>

		<select name="aaraa_boy" id="aaraa_boy_filter">
			<option value="0"><?php esc_html_e( 'All delivery persons', 'aaraa-white-label-admin' ); ?></option>
			<option value="-1" <?php selected( $cur['boy'], -1 ); ?>><?php esc_html_e( 'Not assigned', 'aaraa-white-label-admin' ); ?></option>
			<?php foreach ( $people as $id => $label ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $cur['boy'], (int) $id ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Legacy posts-table variant of the filter row.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function render_filters_legacy( $post_type ) {
		if ( ! in_array( $post_type, array( 'shop_order', 'shop_subscription' ), true ) ) {
			return;
		}
		$this->render_filters( $post_type, 'top' );
	}

	/**
	 * Build the meta_query clauses for whichever filters are active.
	 *
	 * @return array
	 */
	private function filter_meta_query() {
		$cur     = $this->current_filters();
		$clauses = array();

		if ( $cur['slot'] ) {
			$clauses[] = array(
				'key'     => self::META_SLOT,
				'value'   => $cur['slot'],
				'compare' => '=',
			);
		}
		if ( $cur['hub'] ) {
			$clauses[] = array(
				'key'     => self::META_HUB,
				'value'   => $cur['hub'],
				'compare' => '=',
			);
		}

		if ( -1 === $cur['boy'] ) {
			// "Not assigned" — no delivery person meta, or empty/zero. Two clauses
			// only (NOT EXISTS + IN): mixing three same-key clauses in one OR
			// produces unreliable SQL under both HPOS and WP_Meta_Query.
			$clauses[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_BOY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_BOY,
					'value'   => array( '', '0' ),
					'compare' => 'IN',
				),
			);
		} elseif ( $cur['boy'] ) {
			$clauses[] = array(
				'key'     => self::META_BOY,
				'value'   => $cur['boy'],
				'compare' => '=',
			);
		}

		return $clauses;
	}

	/**
	 * Apply the filters to the HPOS orders query.
	 *
	 * @param array $args Order query args.
	 * @return array
	 */
	public function filter_query_args( $args ) {
		$clauses = $this->filter_meta_query();
		if ( empty( $clauses ) ) {
			return $args;
		}
		if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		// Nest our clauses in their own AND group so we never overwrite a
		// `relation` WooCommerce (or another plugin) already set.
		$args['meta_query'][] = array_merge( array( 'relation' => 'AND' ), $clauses );
		return $args;
	}

	/**
	 * Apply the filters to the legacy posts-table orders query.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function filter_request_legacy( $vars ) {
		global $typenow;
		if ( ! is_admin() || 'shop_order' !== $typenow ) {
			return $vars;
		}
		$clauses = $this->filter_meta_query();
		if ( empty( $clauses ) ) {
			return $vars;
		}
		if ( empty( $vars['meta_query'] ) || ! is_array( $vars['meta_query'] ) ) {
			$vars['meta_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$vars['meta_query'][] = array_merge( array( 'relation' => 'AND' ), $clauses );
		return $vars;
	}

	/**
	 * Load the hub → person cascade script on the order edit and list screens.
	 *
	 * Enqueued with an explicit jQuery dependency rather than printed inline in
	 * the metabox: an inline block can execute before jQuery is defined, which
	 * throws and silently kills the cascade.
	 *
	 * @return void
	 */
	public function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$ids = $this->screen_ids();
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$ids[] = wc_get_page_screen_id( 'shop-order' );
			$ids[] = wc_get_page_screen_id( 'shop-subscription' );
		}
		$ids[] = 'edit-shop_order';
		$ids[] = 'edit-shop_subscription';
		$ids[] = 'woocommerce_page_wc-orders';
		$ids[] = 'woocommerce_page_wc-orders--shop_subscription';

		if ( ! in_array( $screen->id, array_unique( array_filter( $ids ) ), true ) ) {
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

	/**
	 * Screen ids for the order edit page (HPOS + legacy).
	 *
	 * @return string[]
	 */
	private function screen_ids() {
		// Legacy post-type screens (orders + subscriptions).
		$ids = array( 'shop_order', 'shop_subscription' );

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			if ( function_exists( 'wc_get_page_screen_id' ) ) {
				$ids[] = wc_get_page_screen_id( 'shop-order' );
				$ids[] = wc_get_page_screen_id( 'shop-subscription' );
			}
			// Literal HPOS screen ids as a fallback.
			$ids[] = 'woocommerce_page_wc-orders';
			$ids[] = 'woocommerce_page_wc-orders--shop_subscription';
		}
		return array_unique( array_filter( $ids ) );
	}

	/**
	 * Register the metabox on every order edit screen.
	 *
	 * @return void
	 */
	public function add_box() {
		foreach ( $this->screen_ids() as $screen ) {
			add_meta_box(
				'aaraa-order-delivery',
				__( 'Aaraa Delivery', 'aaraa-white-label-admin' ),
				array( $this, 'render' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/* --------------------------------------------------------------------- *
	 * Data.
	 * --------------------------------------------------------------------- */

	/**
	 * Active delivery slots.
	 *
	 * @return array
	 */
	private function slots() {
		return Delivery_Admin::slots_list();
	}

	/**
	 * All delivery hubs.
	 *
	 * @return array
	 */
	private function hubs() {
		return Delivery_Admin::hubs_list();
	}

	/**
	 * Delivery people, optionally restricted to one hub.
	 *
	 * @param int $hub_id Hub id, 0 for all.
	 * @return array<int, string> id => label.
	 */
	private function delivery_people( $hub_id = 0 ) {
		return Delivery_Admin::people_list( $hub_id );
	}

	/**
	 * Every assignable delivery person for the list filter.
	 *
	 * Merges the wcfm_delivery_boy role list with anyone actually assigned as a
	 * delivery boy on a customer profile, so imported/migrated boys that lack the
	 * role (and whose orders would otherwise be unfilterable) still appear.
	 *
	 * @return array<int, string> id => label.
	 */
	private function all_delivery_people() {
		return class_exists( __NAMESPACE__ . '\\Delivery_Admin' ) ? Delivery_Admin::all_delivery_people( 0 ) : array();
	}

	/* --------------------------------------------------------------------- *
	 * Render.
	 * --------------------------------------------------------------------- */

	/**
	 * Metabox body.
	 *
	 * @param mixed $post_or_order Post object (legacy) or WC_Order (HPOS).
	 * @return void
	 */
	public function render( $post_or_order ) {
		$order = ( $post_or_order instanceof \WC_Order )
			? $post_or_order
			: wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : $post_or_order );

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not available.', 'aaraa-white-label-admin' ) . '</p>';
			return;
		}

		$cur_slot = (string) $order->get_meta( self::META_SLOT );
		$cur_hub  = (int) $order->get_meta( self::META_HUB );
		$cur_boy  = (int) $order->get_meta( self::META_BOY );

		// On subscriptions the slot is managed with the delivery schedule, so the
		// box hides its own slot dropdown to avoid two identical controls.
		$is_sub = ( 'shop_subscription' === $order->get_type() );

		// Fall back to whatever the customer is normally assigned to, so a new
		// order opens pre-filled with the sensible default instead of blank.
		$customer_id = $order->get_user_id();
		if ( ! $cur_hub && $customer_id ) {
			$cur_hub = (int) get_user_meta( $customer_id, '_wcfmd_delivery_hub', true );
		}
		if ( ! $cur_boy && $customer_id ) {
			$cur_boy = (int) get_user_meta( $customer_id, '_wcfmd_delivery_boy', true );
		}

		$slots  = $this->slots();
		$hubs   = $this->hubs();
		$people = $this->all_delivery_people();

		wp_nonce_field( self::NONCE, 'aaraa_delivery_nonce', false );
		?>
		<div class="aaraa-orddel">
			<?php if ( $is_sub ) : ?>
				<?php // Slot is edited with the subscription's delivery schedule; keep the value intact on save. ?>
				<input type="hidden" id="aaraa_delivery_slot" name="aaraa_delivery_slot" value="<?php echo esc_attr( $cur_slot ); ?>" />
			<?php else : ?>
			<p>
				<label for="aaraa_delivery_slot"><strong><?php esc_html_e( 'Delivery Slot', 'aaraa-white-label-admin' ); ?></strong></label>
				<select id="aaraa_delivery_slot" name="aaraa_delivery_slot" style="width:100%">
					<option value=""><?php esc_html_e( '— Select slot —', 'aaraa-white-label-admin' ); ?></option>
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
						<option value="<?php echo esc_attr( $slot->id ); ?>" <?php selected( $cur_slot, (string) $slot->id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $slots ) ) : ?>
					<span class="description"><?php esc_html_e( 'No active slots yet — add them under AaraaKart → Delivery Slots.', 'aaraa-white-label-admin' ); ?></span>
				<?php endif; ?>
			</p>
			<?php endif; ?>

			<p>
				<label for="aaraa_delivery_hub"><strong><?php esc_html_e( 'Delivery Hub', 'aaraa-white-label-admin' ); ?></strong></label>
				<select id="aaraa_delivery_hub" name="aaraa_delivery_hub" style="width:100%">
					<option value="0"><?php esc_html_e( '— Select hub —', 'aaraa-white-label-admin' ); ?></option>
					<?php foreach ( $hubs as $hub ) : ?>
						<option value="<?php echo esc_attr( $hub->ID ); ?>" <?php selected( $cur_hub, (int) $hub->ID ); ?>><?php echo esc_html( $hub->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="aaraa_delivery_boy"><strong><?php esc_html_e( 'Delivery Person', 'aaraa-white-label-admin' ); ?></strong></label>
				<select id="aaraa_delivery_boy" name="aaraa_delivery_boy" style="width:100%">
					<option value="0"><?php esc_html_e( '— Select person —', 'aaraa-white-label-admin' ); ?></option>
					<?php foreach ( $people as $id => $label ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $cur_boy, (int) $id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="description aaraa-orddel__hint"><?php esc_html_e( 'All delivery persons are listed.', 'aaraa-white-label-admin' ); ?></span>
			</p>

			<input type="hidden" id="aaraa_delivery_order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
			<p class="aaraa-orddel__actions">
				<?php // type="button" — must not submit the surrounding order form. ?>
				<button type="button" class="button button-primary" id="aaraa_save_delivery">
					<?php esc_html_e( 'Save Delivery', 'aaraa-white-label-admin' ); ?>
				</button>
				<span class="spinner aaraa-orddel__spinner" style="float:none;margin:0 0 0 6px;"></span>
				<span class="aaraa-orddel__msg" role="status" aria-live="polite"></span>
			</p>
		</div>

		<?php
	}

	/**
	 * AJAX: delivery people for a hub.
	 *
	 * @return void
	 */
	public function ajax_hub_boys() {
		check_ajax_referer( 'aaraa_hub_boys', 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json( array() );
		}
		// List every delivery person regardless of hub (imported boys carry no hub
		// meta, so hub-narrowing would hide them).
		$out = array();
		foreach ( $this->all_delivery_people() as $id => $label ) {
			$out[] = array(
				'id'   => $id,
				'text' => $label,
			);
		}
		wp_send_json( $out );
	}

	/* --------------------------------------------------------------------- *
	 * Save.
	 * --------------------------------------------------------------------- */

	/**
	 * Persist the mapping and sync the assignment into WCFM.
	 *
	 * @param int   $order_id Order ID.
	 * @param mixed $order    Order object (HPOS) or post (legacy).
	 * @return void
	 */
	public function save( $order_id, $order = null ) {
		if ( ! isset( $_POST['aaraa_delivery_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aaraa_delivery_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$order = ( $order instanceof \WC_Order ) ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$slot = isset( $_POST['aaraa_delivery_slot'] ) ? absint( $_POST['aaraa_delivery_slot'] ) : 0;
		$hub  = isset( $_POST['aaraa_delivery_hub'] ) ? absint( $_POST['aaraa_delivery_hub'] ) : 0;
		$boy  = isset( $_POST['aaraa_delivery_boy'] ) ? absint( $_POST['aaraa_delivery_boy'] ) : 0;

		$boy = $this->validate_person( $boy, $hub );

		$this->persist( $order, $slot, $hub, $boy );
	}

	/**
	 * Write the mapping to the order and sync the assignment into WCFM.
	 *
	 * Shared by the order-form save and the panel's own Save button.
	 *
	 * @param \WC_Order $order Order.
	 * @param int       $slot  Slot id.
	 * @param int       $hub   Hub id.
	 * @param int       $boy   Delivery person user id.
	 * @return void
	 */
	private function persist( $order, $slot, $hub, $boy ) {
		$previous_slot = (int) $order->get_meta( self::META_SLOT );
		$previous_hub  = (int) $order->get_meta( self::META_HUB );
		$previous_boy  = (int) $order->get_meta( self::META_BOY );

		$order->update_meta_data( self::META_SLOT, $slot ? (string) $slot : '' );
		$order->update_meta_data( self::META_HUB, $hub );
		$order->update_meta_data( self::META_BOY, $boy );
		$order->save();

		// Leave a visible trail whenever the mapping changes, so it is obvious
		// from the order screen whether the save actually took effect.
		if ( $slot !== $previous_slot || $hub !== $previous_hub || $boy !== $previous_boy ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: slot, 2: hub, 3: delivery person */
					__( 'Aaraa Delivery updated — Slot: %1$s | Hub: %2$s | Person: %3$s', 'aaraa-white-label-admin' ),
					$this->slot_name( $slot ),
					$this->hub_name( $hub ),
					$boy ? $this->person_name( $boy ) : __( 'none', 'aaraa-white-label-admin' )
				)
			);
		}

		// Hand the assignment to WCFM so its delivery tables/stats stay correct.
		return $this->sync_delivery_person( $order, $boy, $previous_boy );
	}

	/**
	 * Apply the order-level delivery person to every line item.
	 *
	 * The panel maps a whole order, not individual products, so one person owns
	 * all of its items. WCFM stores the assignment per item, so this asserts it
	 * across the order and clears the previous person from it first.
	 *
	 * Run on every save rather than only when the person changes: WCFM's insert
	 * is ON DUPLICATE KEY UPDATE, so re-firing is harmless and re-saving repairs
	 * an order whose items were only partly assigned.
	 *
	 * @param \WC_Order $order        Order.
	 * @param int       $boy          Delivery person user id (0 to unassign).
	 * @param int       $previous_boy Previously assigned person.
	 * @return array{assigned:int, total:int, skipped:string[]}
	 */
	private function sync_delivery_person( $order, $boy, $previous_boy ) {
		$result = array(
			'assigned' => 0,
			'total'    => 0,
			'skipped'  => array(),
		);

		if ( $previous_boy && $previous_boy !== $boy ) {
			$this->clear_delivery_person( $order, $previous_boy );
		}

		if ( ! $boy ) {
			return $result;
		}

		$order_id = $order->get_id();
		$tracking = array( 'wcfm_delivery_boy' => $boy );

		foreach ( $order->get_items() as $item_id => $item ) {
			++$result['total'];

			// WCFM's handler calls $line_item->get_product()->get_price() without
			// checking the product still exists. On a deleted product that throws
			// and would abort the loop, leaving the rest of the order unassigned
			// — which is how an order ends up half-assigned and short on the
			// delivery sheet. Skip those lines and report them instead.
			if ( method_exists( $item, 'get_product' ) && ! $item->get_product() ) {
				$result['skipped'][] = $item->get_name();
				continue;
			}

			try {
				do_action( 'wcfmd_delivery_boy_assigned', $order_id, $item_id, $tracking, $item->get_product_id() );
				++$result['assigned'];
			} catch ( \Throwable $e ) {
				$result['skipped'][] = $item->get_name();
			}
		}

		return $result;
	}

	/**
	 * Remove a delivery person from every item of an order.
	 *
	 * Without this a re-assigned order stays on the old person's run sheet,
	 * because WCFM only ever inserts.
	 *
	 * @param \WC_Order $order Order.
	 * @param int       $boy   Delivery person user id.
	 * @return void
	 */
	private function clear_delivery_person( $order, $boy ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wcfm_delivery_orders';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { // phpcs:ignore WordPress.DB
			$wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare( "DELETE FROM {$table} WHERE order_id = %d AND delivery_boy = %d", $order->get_id(), $boy ) // phpcs:ignore WordPress.DB.PreparedSQL
			);
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( (int) wc_get_order_item_meta( $item_id, 'wcfm_delivery_boy', true ) === $boy ) {
				wc_delete_order_item_meta( $item_id, 'wcfm_delivery_boy' );
			}
		}

		if ( function_exists( 'wcfm_update_order_delivery_boys_meta' ) ) {
			wcfm_update_order_delivery_boys_meta( $order->get_id() );
		}
	}

	/**
	 * Validate a delivery person against the chosen hub.
	 *
	 * @param int $boy Candidate user id.
	 * @param int $hub Chosen hub id.
	 * @return int Validated user id (0 when not a delivery person).
	 */
	private function validate_person( $boy, $hub ) {
		if ( ! $boy ) {
			return 0;
		}
		// Accept any existing user and self-heal the delivery role if missing.
		if ( ! Delivery_Admin::ensure_delivery_role( $boy ) ) {
			return 0;
		}
		// Honour the admin's explicit choice: move the person to the chosen hub
		// rather than silently discarding the selection.
		if ( $hub && (int) get_user_meta( $boy, '_wcfmd_delivery_hub', true ) !== $hub ) {
			update_user_meta( $boy, '_wcfmd_delivery_hub', $hub );
		}
		return $boy;
	}

	/**
	 * AJAX: save the panel on its own, without submitting the whole order form.
	 *
	 * @return void
	 */
	public function ajax_save() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'aaraa-white-label-admin' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'aaraa-white-label-admin' ) ) );
		}

		$slot = isset( $_POST['slot'] ) ? absint( $_POST['slot'] ) : 0;
		$hub  = isset( $_POST['hub'] ) ? absint( $_POST['hub'] ) : 0;
		$boy  = $this->validate_person( isset( $_POST['boy'] ) ? absint( $_POST['boy'] ) : 0, $hub );

		$sync = $this->persist( $order, $slot, $hub, $boy );

		// Read back from a fresh copy so the response proves what was stored.
		$saved = wc_get_order( $order_id );
		wp_send_json_success(
			array(
				'message' => $this->save_message( $sync ),
				'slot'    => (string) $saved->get_meta( self::META_SLOT ),
				'hub'     => (string) $saved->get_meta( self::META_HUB ),
				'boy'     => (string) $saved->get_meta( self::META_BOY ),
			)
		);
	}

	/**
	 * Confirmation text that states how much of the order was assigned.
	 *
	 * A partial assignment is the failure that matters here — it puts a driver
	 * on the road short a product — so it is never reported as a plain success.
	 *
	 * @param array $sync Result from sync_delivery_person().
	 * @return string
	 */
	private function save_message( $sync ) {
		if ( empty( $sync['skipped'] ) ) {
			if ( $sync['assigned'] ) {
				return sprintf(
					/* translators: %d: number of order items */
					_n(
						'Saved and assigned to the order (%d item).',
						'Saved and assigned to all %d items.',
						$sync['assigned'],
						'aaraa-white-label-admin'
					),
					$sync['assigned']
				);
			}
			return __( 'Delivery details saved.', 'aaraa-white-label-admin' );
		}

		return sprintf(
			/* translators: 1: assigned count, 2: total items, 3: item names */
			__( 'Saved, but only %1$d of %2$d items could be assigned. Check these products: %3$s', 'aaraa-white-label-admin' ),
			$sync['assigned'],
			$sync['total'],
			implode( ', ', $sync['skipped'] )
		);
	}

	/**
	 * Display name for a delivery person.
	 *
	 * @param int $id User ID.
	 * @return string
	 */
	private function person_name( $id ) {
		$user = get_userdata( $id );
		return $user ? $user->display_name : '#' . (int) $id;
	}

	/**
	 * Display name for a slot.
	 *
	 * @param int $id Slot id.
	 * @return string
	 */
	private function slot_name( $id ) {
		if ( ! $id ) {
			return __( 'none', 'aaraa-white-label-admin' );
		}
		foreach ( $this->slots() as $slot ) {
			if ( (int) $slot->id === (int) $id ) {
				return $slot->name;
			}
		}
		return '#' . (int) $id;
	}

	/**
	 * Display name for a hub.
	 *
	 * @param int $id Hub id.
	 * @return string
	 */
	private function hub_name( $id ) {
		if ( ! $id ) {
			return __( 'none', 'aaraa-white-label-admin' );
		}
		foreach ( $this->hubs() as $hub ) {
			if ( (int) $hub->ID === (int) $id ) {
				return $hub->name;
			}
		}
		return '#' . (int) $id;
	}
}
