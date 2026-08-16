<?php
/**
 * Per-plan "Advance Amount" for All Products for Subscriptions (APFS).
 *
 * Adds an Advance Amount to every subscription plan (scheme) on the product
 * edit screen (Product data -> Subscriptions). The amount is charged once,
 * upfront, on the first order only, on top of the recurring price, and never on
 * a one-time purchase of the same product.
 *
 * Charge and display are decoupled from WooCommerce Subscriptions' own sign-up
 * fee: the theme layers several conflicting price-string filters that made the
 * sign-up-fee display unreliable. Instead:
 *
 *   - wcsatt_subscription_scheme_product_content  renders the field (admin).
 *   - wcsatt_processed_scheme_data                sanitises + saves it into the
 *                                                 product's _wcsatt_schemes meta.
 *   - woocommerce_cart_calculate_fees             charges it as a one-time cart
 *                                                 fee for subscribe items only.
 *   - wcsatt_single_product_subscription_option_description
 *                                                 prints it on the subscribe
 *                                                 plan option only.
 *   - the app's custom order endpoint calls add_order_advance_fee() directly.
 *
 * The custom value lives on each scheme under the key
 * `subscription_advance_amount`, alongside APFS's own `subscription_*` keys.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Advance Amount per APFS subscription plan.
 */
class Subscription_Advance {

	/**
	 * Scheme meta key that stores the per-plan advance amount.
	 */
	const SCHEME_KEY = 'subscription_advance_amount';

	/**
	 * Order meta: the advance amount charged on this order, stamped at creation so
	 * it can be credited to the wallet on completion without re-deriving it.
	 */
	const ORDER_ADVANCE_META = '_aaraa_advance_amount';

	/**
	 * Order meta guard: set once the advance has been credited to the wallet, so a
	 * re-completion (completed -> refunded -> completed) never credits twice.
	 */
	const CREDITED_META = '_aaraa_advance_credited';

	/**
	 * Register hooks.
	 *
	 * The plugin boots before plugins_loaded (see main plugin file), so APFS/WCS
	 * classes are not available yet — hooks are therefore registered
	 * unconditionally. Each only ever fires if APFS/WCS themselves fire it, and
	 * the sole direct use of an APFS class is guarded at the runtime callsite.
	 *
	 * Charge and display are deliberately decoupled from WooCommerce
	 * Subscriptions' own sign-up-fee machinery: the theme layers several
	 * conflicting `woocommerce_subscriptions_product_price_string` filters (one
	 * even strips all tags and slashes), which made the sign-up-fee display
	 * unreliable. Instead the advance is charged as an explicit one-time cart fee
	 * and printed onto the subscribe plan option directly.
	 *
	 * @return void
	 */
	public function init() {
		// Admin: render + save the field on each plan row.
		add_action( 'wcsatt_subscription_scheme_product_content', array( $this, 'render_field' ), 20, 3 );
		add_filter( 'wcsatt_processed_scheme_data', array( $this, 'save_field' ), 10, 2 );

		// Charge + display: feed the active plan's advance to WCS as the product's
		// sign-up fee. WCS bakes it into the initial order total only (excluded
		// from renewals) and every APFS price string reads from it, so this single
		// hook drives both the money and the on-page text.
		add_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'apply_advance' ), 10, 2 );

		// Keep the advance text on the interactive subscribe surfaces
		// (prompt/options) only — never on the plain product-price heading. This
		// affects display strings only; the charge routes through WCS directly and
		// is unaffected.
		add_filter( 'wcsatt_price_html_args', array( $this, 'scope_price_html_advance' ), 20, 3 );

		// Wording: WCS's "sign-up fee" -> "advance" on the storefront only.
		add_filter( 'gettext', array( $this, 'relabel' ), 10, 3 );

		// Cart: hide the one-time / subscription price switcher and show only the
		// selected plan's price.
		add_filter( 'wcsatt_cart_item_options', array( $this, 'hide_cart_scheme_options' ), 100, 4 );

		// Product API: expose plans (incl. advance) on the standard wc/v3/products endpoint.
		add_action( 'rest_api_init', array( $this, 'register_rest_field' ) );
		add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'filter_product_response' ), 10, 3 );

		// Frontend: relabel the add-to-cart button to "Subscribe" when the
		// subscribe option is selected on the single product page.
		add_action( 'wp_footer', array( $this, 'subscribe_button_script' ) );

		// Wallet: record the advance charged on the first order at creation, then
		// credit it to the customer's wallet when that order is completed.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_advance_on_checkout' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'credit_advance_on_complete' ), 20, 2 );
	}

	/* --------------------------------------------------------------------- *
	 * Wallet credit: advance paid up-front -> wallet on order completion.
	 * --------------------------------------------------------------------- */

	/**
	 * Record the exact advance charged on a checkout-created order.
	 *
	 * WCS bakes the advance (its sign-up fee) into the first order's product line
	 * rather than a separate fee line, so there is nothing to read back later.
	 * Here we sum the advance for each subscribe cart item — using the very value
	 * that was charged (per-unit advance x quantity) — and stamp it on the order so
	 * completion can credit it without re-deriving. The app path bypasses checkout
	 * and stamps its own value in add_order_advance_fee().
	 *
	 * @param \WC_Order $order The order being created.
	 * @param array     $data  Posted checkout data (unused).
	 * @return void
	 */
	public function stamp_advance_on_checkout( $order, $data ) {
		if ( ! is_a( $order, 'WC_Order' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! class_exists( 'WCS_ATT_Cart' ) || ! class_exists( 'WCS_ATT_Product_Schemes' ) ) {
			return;
		}

		$total = 0.0;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$key = \WCS_ATT_Cart::get_subscription_scheme( $cart_item ); // active scheme key, or null/false for one-time.
			if ( empty( $key ) ) {
				continue;
			}

			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$schemes = \WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
			if ( empty( $schemes[ $key ] ) ) {
				continue;
			}

			$scheme  = $schemes[ $key ];
			$advance = self::advance_for_plan( $product, $scheme->get_interval(), $scheme->get_period(), $scheme->get_length() );
			if ( $advance <= 0 ) {
				continue;
			}

			$qty    = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
			$total += $advance * $qty;
		}

		if ( $total > 0 ) {
			$order->update_meta_data( self::ORDER_ADVANCE_META, wc_format_decimal( $total ) );
		}
	}

	/**
	 * Credit the recorded advance to the customer's wallet when the order that
	 * carried it is completed.
	 *
	 * Only the first/parent order carries the advance meta (renewals and the
	 * subscription itself do not), so renewals reaching this hook credit nothing.
	 * The credit runs at most once per order, guarded by CREDITED_META.
	 *
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Order object (WooCommerce passes it on this hook).
	 * @return void
	 */
	public function credit_advance_on_complete( $order_id, $order = null ) {
		$order = is_a( $order, 'WC_Order' ) ? $order : wc_get_order( $order_id );
		if ( ! $order || 'shop_order' !== $order->get_type() ) {
			return;
		}
		if ( 'yes' === $order->get_meta( self::CREDITED_META ) ) {
			return; // already credited
		}

		$advance = (float) $order->get_meta( self::ORDER_ADVANCE_META );
		if ( $advance <= 0 ) {
			// App-path / older orders: fall back to any explicit "Advance" fee lines.
			$advance = self::advance_from_fee_items( $order );
		}
		if ( $advance <= 0 ) {
			return;
		}

		$user_id = $order->get_customer_id();
		if ( ! $user_id || ! class_exists( __NAMESPACE__ . '\\Customers_Admin' ) ) {
			return;
		}

		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$advance  = round( $advance, $decimals );
		$balance  = (float) Customers_Admin::get_wallet_balance( $user_id );
		$new      = round( $balance + $advance, $decimals );

		// Writes wps_wallet; the wallet-change SMS/WhatsApp (if enabled) fires off this.
		Customers_Admin::set_wallet_balance( $user_id, $new );
		self::record_credit( $user_id, $advance, $order );

		$order->update_meta_data( self::CREDITED_META, 'yes' );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: 1: advance credited, 2: new wallet balance */
				__( 'Advance %1$s credited to wallet on order completion. Wallet balance now %2$s.', 'aaraa-white-label-admin' ),
				self::money( $advance ),
				self::money( $new )
			)
		);
	}

	/**
	 * Sum any explicit "Advance" fee line items on an order (the app path adds one).
	 *
	 * @param \WC_Order $order Order.
	 * @return float
	 */
	public static function advance_from_fee_items( $order ) {
		$total = 0.0;
		$label = __( 'Advance', 'aaraa-white-label-admin' );

		foreach ( $order->get_items( 'fee' ) as $fee ) {
			if ( 0 === strcasecmp( trim( (string) $fee->get_name() ), $label ) ) {
				$total += (float) $fee->get_total();
			}
		}

		return $total;
	}

	/**
	 * Record the wallet credit in the WP Swings transaction ledger.
	 *
	 * @param int       $user_id Customer id.
	 * @param float     $amount  Amount credited (positive).
	 * @param \WC_Order $order   Source order.
	 * @return void
	 */
	private static function record_credit( $user_id, $amount, $order ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Customers_Admin' ) || ! Customers_Admin::wallet_table_exists() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wps_wsfw_wallet_transaction';
		$data  = array(
			'user_id'            => (int) $user_id,
			'amount'             => (float) $amount,
			'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'transaction_type'   => sprintf(
				/* translators: %s: order number */
				__( 'Subscription advance credited from order #%s', 'aaraa-white-label-admin' ),
				$order->get_order_number()
			),
			'transaction_type_1' => 'credit',
			'payment_method'     => __( 'Subscription advance', 'aaraa-white-label-admin' ),
			'transaction_id'     => (string) $order->get_id(),
			'note'               => sprintf(
				/* translators: %s: order number */
				__( 'Advance from order #%s', 'aaraa-white-label-admin' ),
				$order->get_order_number()
			),
			'date'               => gmdate( 'Y-m-d H:i:s' ),
		);
		$format = array( '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( Customers_Admin::has_created_by_column() ) {
			$data['created_by'] = (int) $user_id;
			$format[]           = '%d';
		}

		$wpdb->insert( $table, $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Currency-formatted, tag-free amount for order notes.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( html_entity_decode( wc_price( $amount ) ) );
		}
		return number_format( (float) $amount, 2 );
	}

	/* --------------------------------------------------------------------- *
	 * Frontend: add-to-cart button label.
	 * --------------------------------------------------------------------- */

	/**
	 * On the single product page, swap the add-to-cart button text between
	 * "Add to cart" (one-time) and "Subscribe" (subscribe option) as the APFS
	 * option changes. Purely cosmetic — the cart action is unchanged.
	 *
	 * @return void
	 */
	public function subscribe_button_script() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
		if ( ! $product ) {
			return;
		}

		// The theme/WooCommerce default label, captured from PHP so it is never
		// polluted by whatever APFS may have already written into the button.
		$default_label   = esc_js( $product->single_add_to_cart_text() );
		$subscribe_label = esc_js( __( 'Subscribe', 'aaraa-white-label-admin' ) );
		?>
		<script>
		( function () {
			var form = document.querySelector( 'form.cart' );
			if ( ! form ) {
				return;
			}
			var btn = form.querySelector( '.single_add_to_cart_button' );
			if ( ! btn ) {
				return;
			}
			var subscribeText = '<?php echo $subscribe_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>';
			var defaultText   = '<?php echo $default_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>';

			function isSubscribeSelected() {
				// APFS "prompt" layout: the visible radios/checkbox drive the choice;
				// value="yes" (or a checked checkbox) means subscribe.
				var prompt = document.querySelector( '.wcsatt-options-prompt-action-input' );
				if ( prompt ) {
					var checked = document.querySelector( '.wcsatt-options-prompt-action-input:checked' );
					if ( ! checked ) {
						return false;
					}
					return 'no' !== checked.value; // "yes" radio or checkbox value.
				}
				// Radio/dropdown layouts: a non-"0" value means a subscription plan.
				var opt = document.querySelector( '[name^="convert_to_sub_dropdown"], [name^="convert_to_sub_"]:checked' );
				if ( opt ) {
					return '0' !== String( opt.value );
				}
				return false;
			}

			function sync() {
				btn.textContent = isSubscribeSelected() ? subscribeText : defaultText;
			}

			// Fire for any change to the subscription option controls, wherever
			// they sit in the markup.
			document.addEventListener( 'change', function ( e ) {
				var t = e.target;
				if ( ! t ) {
					return;
				}
				if (
					( t.classList && t.classList.contains( 'wcsatt-options-prompt-action-input' ) ) ||
					( t.name && ( 0 === t.name.indexOf( 'convert_to_sub_' ) || 0 === t.name.indexOf( 'convert_to_sub_dropdown' ) ) )
				) {
					sync();
				}
			} );

			sync();
		} )();
		</script>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Admin field.
	 * --------------------------------------------------------------------- */

	/**
	 * Render the Advance Amount input beneath the plan's price fields.
	 *
	 * Fires once per plan row (existing rows and the AJAX-rendered "Add Plan"
	 * row), so the correct $index is always supplied by APFS.
	 *
	 * @param int   $index       Zero-based scheme index in the wcsatt_schemes[] array.
	 * @param array $scheme_data Saved scheme data, or empty for a new row.
	 * @param int   $post_id     Product ID.
	 * @return void
	 */
	public function render_field( $index, $scheme_data, $post_id ) {
		$value = isset( $scheme_data[ self::SCHEME_KEY ] ) ? $scheme_data[ self::SCHEME_KEY ] : '';

		woocommerce_wp_text_input(
			array(
				'id'            => '_subscription_advance_amount_' . $index,
				'name'          => 'wcsatt_schemes[' . $index . '][' . self::SCHEME_KEY . ']',
				'value'         => $value,
				'wrapper_class' => 'subscription_advance_amount',
				'label'         => __( 'Advance Amount', 'aaraa-white-label-admin' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'description'   => __( 'One-time amount charged upfront on the first order for this plan, on top of the recurring price. Leave blank for none.', 'aaraa-white-label-admin' ),
				'desc_tip'      => true,
				'data_type'     => 'price',
			)
		);
	}

	/**
	 * Normalise the posted advance amount before APFS stores it in _wcsatt_schemes.
	 *
	 * The value has already been wc_clean()'d by APFS; here it is formatted to a
	 * decimal and blanked when zero/empty so it contributes nothing at runtime.
	 *
	 * @param array       $posted_scheme The scheme data being saved.
	 * @param \WC_Product $product        The product being saved.
	 * @return array
	 */
	public function save_field( $posted_scheme, $product ) {
		if ( isset( $posted_scheme[ self::SCHEME_KEY ] ) ) {
			$amount                          = wc_format_decimal( $posted_scheme[ self::SCHEME_KEY ] );
			$posted_scheme[ self::SCHEME_KEY ] = ( '' === $amount || (float) $amount <= 0 ) ? '' : $amount;
		}

		return $posted_scheme;
	}

	/* --------------------------------------------------------------------- *
	 * Storefront: charge + display via WCS's sign-up fee.
	 * --------------------------------------------------------------------- */

	/**
	 * Feed the active plan's advance to WCS as the product's sign-up fee.
	 *
	 * This drives BOTH the charge and the display:
	 *
	 *  - Charge: during cart calculation WCS's set_subscription_prices_for_
	 *    calculation() adds the sign-up fee to the item price on the initial
	 *    ("none") pass only, and 0 on the recurring pass — so the advance lands on
	 *    the first order and is excluded from renewals, natively. A one-time item
	 *    has no active subscription scheme, so it gets nothing.
	 *  - Display: every APFS price string (heading, prompt, plan options) is built
	 *    from the sign-up fee, so this one value reaches them all. Which surfaces
	 *    actually show it is governed by scope_price_html_advance().
	 *
	 * @param mixed       $sign_up_fee The product's current sign-up fee.
	 * @param \WC_Product $product      The product being priced.
	 * @return mixed
	 */
	public function apply_advance( $sign_up_fee, $product ) {
		if ( ! is_a( $product, 'WC_Product' ) || ! class_exists( 'WCS_ATT_Product_Schemes' ) ) {
			return $sign_up_fee;
		}

		$scheme = \WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object' );

		if ( ! $scheme ) {
			return $sign_up_fee; // One-time / no active plan.
		}

		$advance = self::advance_for_plan(
			$product,
			$scheme->get_interval(),
			$scheme->get_period(),
			$scheme->get_length()
		);

		if ( $advance <= 0 ) {
			return $sign_up_fee;
		}

		$base = is_numeric( $sign_up_fee ) ? (float) $sign_up_fee : 0.0;

		return $base + $advance;
	}

	/**
	 * Show the advance only on the interactive subscribe surfaces — the prompt
	 * ("Subscribe for …") and the plan options — never on the plain product-price
	 * heading or catalog listings.
	 *
	 * @param array           $args       Price-string args passed to WCS.
	 * @param \WC_Product     $product    The product.
	 * @param string|int|null $scheme_key The scheme being rendered.
	 * @return array
	 */
	public function scope_price_html_advance( $args, $product, $scheme_key ) {
		$context = isset( $args['context'] ) ? $args['context'] : 'catalog';

		if ( ! in_array( $context, array( 'prompt', 'radio', 'dropdown' ), true ) ) {
			$args['sign_up_fee'] = false;
		}

		return $args;
	}

	/**
	 * Rename WooCommerce Subscriptions' customer-facing "sign-up fee" wording to
	 * "advance". Storefront only; the admin keeps its original label.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original msgid.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function relabel( $translation, $text, $domain ) {
		if ( 'woocommerce-subscriptions' !== $domain || is_admin() ) {
			return $translation;
		}

		switch ( $text ) {
			case '%1$s and a %2$s sign-up fee':
				return '%1$s and a %2$s advance';
			case 'Sign up fee':
				return __( 'Advance', 'aaraa-white-label-admin' );
		}

		return $translation;
	}

	/**
	 * Hide the one-time / subscription price switcher in the cart, leaving only
	 * the selected plan's price.
	 *
	 * APFS renders a radio option per available scheme (one-time + each plan) in
	 * the cart-item price cell. Collapsing the list to a single entry makes APFS
	 * fall back to printing the plain price string for the active plan (see
	 * WCS_ATT_Display_Cart::show_cart_item_subscription_options()), so the
	 * customer sees the price only — the choice made on the product page stands.
	 *
	 * @param array  $options              Cart-item scheme options.
	 * @param array  $subscription_schemes Available schemes.
	 * @param array  $cart_item            Cart item.
	 * @param string $cart_item_key        Cart item key.
	 * @return array
	 */
	public function hide_cart_scheme_options( $options, $subscription_schemes, $cart_item, $cart_item_key ) {
		$options = (array) $options;

		if ( count( $options ) > 1 ) {
			return array( reset( $options ) );
		}

		return $options;
	}

	/* --------------------------------------------------------------------- *
	 * Product API + custom order creation (app path).
	 *
	 * The mobile app reads plans from the REST API and builds subscription
	 * orders through a custom endpoint that bypasses the WooCommerce cart, so it
	 * cannot use the cart fee above. These public helpers let that endpoint read
	 * the advance and charge it as an order fee.
	 * --------------------------------------------------------------------- */

	/**
	 * Resolve the advance amount for a plan identified by its billing schedule.
	 *
	 * Used by the custom order endpoint, which knows the chosen plan only by the
	 * interval + period (+ optional length) posted by the app, not by the APFS
	 * scheme key. When several plans share an interval/period, the one that
	 * actually carries an advance wins.
	 *
	 * @param \WC_Product|int $product  Product or product ID.
	 * @param int|string      $interval Billing interval (e.g. 1).
	 * @param string          $period   Billing period (day|week|month|year).
	 * @param int|string      $length   Optional plan length to disambiguate.
	 * @return float The advance amount, or 0.0 when there is none.
	 */
	public static function advance_for_plan( $product, $interval, $period, $length = '' ) {
		$product = is_a( $product, 'WC_Product' ) ? $product : wc_get_product( $product );

		if ( ! $product ) {
			return 0.0;
		}

		$schemes = $product->get_meta( '_wcsatt_schemes', true );

		if ( ! is_array( $schemes ) ) {
			return 0.0;
		}

		$interval = (string) (int) $interval;
		$period   = strtolower( (string) $period );
		$fallback = 0.0;

		foreach ( $schemes as $scheme ) {
			if ( ! is_array( $scheme ) ) {
				continue;
			}

			$s_interval = (string) (int) ( isset( $scheme['subscription_period_interval'] ) ? $scheme['subscription_period_interval'] : '' );
			$s_period   = strtolower( (string) ( isset( $scheme['subscription_period'] ) ? $scheme['subscription_period'] : '' ) );

			if ( $s_interval !== $interval || $s_period !== $period ) {
				continue;
			}

			// Match length exactly when one is supplied (including 0 = "until
			// cancelled"). An empty string means "length not provided" (app path),
			// so match on interval + period alone.
			if ( '' !== (string) $length ) {
				$s_length = (int) ( isset( $scheme['subscription_length'] ) ? $scheme['subscription_length'] : 0 );
				if ( $s_length !== (int) $length ) {
					continue;
				}
			}

			$amount = isset( $scheme[ self::SCHEME_KEY ] ) ? (float) $scheme[ self::SCHEME_KEY ] : 0.0;

			if ( $amount > 0 ) {
				return $amount; // Best match: a plan with an advance set.
			}
		}

		return $fallback;
	}

	/**
	 * Add the plan's advance to an order as a one-time "Advance" fee.
	 *
	 * Intended for hand-built orders (custom app endpoint). The fee lands on the
	 * first/parent order only; the recurring subscription is created separately
	 * and is left untouched, mirroring sign-up-fee behaviour. The caller must
	 * run $order->calculate_totals() afterwards.
	 *
	 * @param \WC_Order       $order    The parent order being built.
	 * @param \WC_Product|int $product  The subscription product.
	 * @param int|string      $interval Billing interval posted by the app.
	 * @param string          $period   Billing period posted by the app.
	 * @param int|string      $length   Optional plan length.
	 * @return float The advance amount added (0.0 when none).
	 */
	public static function add_order_advance_fee( $order, $product, $interval, $period, $length = '' ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return 0.0;
		}

		$advance = self::advance_for_plan( $product, $interval, $period, $length );

		if ( $advance <= 0 ) {
			return 0.0;
		}

		$fee = new \WC_Order_Item_Fee();
		$fee->set_name( __( 'Advance', 'aaraa-white-label-admin' ) );
		$fee->set_total( (string) $advance );
		$fee->set_tax_status( 'none' );
		$fee->set_tax_class( '' );

		$order->add_item( $fee );

		// Record the advance so completion can credit it to the wallet. Accumulate
		// in case the caller adds several subscription products to one order.
		$existing = (float) $order->get_meta( self::ORDER_ADVANCE_META );
		$order->update_meta_data( self::ORDER_ADVANCE_META, wc_format_decimal( $existing + $advance ) );

		return $advance;
	}

	/**
	 * Build a clean plan list (including advance_amount) for API responses.
	 *
	 * @param \WC_Product|int $product Product or product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function plans_for_api( $product ) {
		$product = is_a( $product, 'WC_Product' ) ? $product : wc_get_product( $product );

		if ( ! $product ) {
			return array();
		}

		$schemes = $product->get_meta( '_wcsatt_schemes', true );

		if ( ! is_array( $schemes ) ) {
			return array();
		}

		$plans = array();

		foreach ( $schemes as $scheme ) {
			if ( ! is_array( $scheme ) ) {
				continue;
			}

			$plans[] = array(
				'interval'       => (int) ( isset( $scheme['subscription_period_interval'] ) ? $scheme['subscription_period_interval'] : 1 ),
				'period'         => (string) ( isset( $scheme['subscription_period'] ) ? $scheme['subscription_period'] : '' ),
				'length'         => (int) ( isset( $scheme['subscription_length'] ) ? $scheme['subscription_length'] : 0 ),
				'pricing_method' => (string) ( isset( $scheme['subscription_pricing_method'] ) ? $scheme['subscription_pricing_method'] : 'inherit' ),
				'regular_price'  => isset( $scheme['subscription_regular_price'] ) ? $scheme['subscription_regular_price'] : '',
				'sale_price'     => isset( $scheme['subscription_sale_price'] ) ? $scheme['subscription_sale_price'] : '',
				'price'          => isset( $scheme['subscription_price'] ) ? $scheme['subscription_price'] : '',
				'discount'       => isset( $scheme['subscription_discount'] ) ? $scheme['subscription_discount'] : '',
				'advance_amount' => isset( $scheme[ self::SCHEME_KEY ] ) ? (float) $scheme[ self::SCHEME_KEY ] : 0.0,
			);
		}

		return $plans;
	}

	/**
	 * The highest advance amount across a product's plans.
	 *
	 * Exposed as a convenience top-level `advance_amount` on the product API so
	 * clients that don't iterate plans still see a value; per-plan amounts live
	 * in `subscription_plans[].advance_amount`.
	 *
	 * @param array<int, array<string, mixed>> $plans Output of plans_for_api().
	 * @return float
	 */
	public static function top_level_advance( array $plans ) {
		$max = 0.0;

		foreach ( $plans as $plan ) {
			if ( isset( $plan['advance_amount'] ) && (float) $plan['advance_amount'] > $max ) {
				$max = (float) $plan['advance_amount'];
			}
		}

		return $max;
	}

	/**
	 * Declare the `subscription_plans` / `advance_amount` fields on the product
	 * schema so they appear in API discovery. Output is guaranteed by
	 * filter_product_response(); this only advertises the fields.
	 *
	 * @return void
	 */
	public function register_rest_field() {
		if ( ! function_exists( 'register_rest_field' ) ) {
			return;
		}

		register_rest_field(
			'product',
			'subscription_plans',
			array(
				'get_callback' => static function ( $response ) {
					$id = is_array( $response ) && isset( $response['id'] ) ? (int) $response['id'] : 0;
					return self::plans_for_api( $id );
				},
				'schema'       => array(
					'description' => __( 'Subscription plans with per-plan advance amount.', 'aaraa-white-label-admin' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}

	/**
	 * Guaranteed exposure of the advance on the standard product REST response.
	 *
	 * Runs for both the list and single-product endpoints, after WooCommerce has
	 * built the response, so it does not depend on the additional-fields context
	 * filtering. Adds `subscription_plans` (per-plan) and a top-level
	 * `advance_amount` (max across plans).
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WC_Product       $product   The product.
	 * @param \WP_REST_Request  $request   The request.
	 * @return \WP_REST_Response
	 */
	public function filter_product_response( $response, $product, $request ) {
		if ( ! is_a( $response, 'WP_REST_Response' ) ) {
			return $response;
		}

		$plans = self::plans_for_api( $product );
		$data  = $response->get_data();

		if ( is_array( $data ) ) {
			$data['subscription_plans'] = $plans;
			$data['advance_amount']     = self::top_level_advance( $plans );
			$response->set_data( $data );
		}

		return $response;
	}

}
