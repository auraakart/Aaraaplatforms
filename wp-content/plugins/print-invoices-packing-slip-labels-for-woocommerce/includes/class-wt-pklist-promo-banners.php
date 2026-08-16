<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class Wt_Pklist_Promo_Banners
 *
 * Renders promotional CTA banners for free-plan users:
 *  - Milestone & Loyalty banners on the WooCommerce Orders List page.
 *  - Shipping Labels promo banner on WooCommerce Settings → Shipping tab.
 *
 * Priority (Orders page): Loyalty (6+ months installed) > Milestone (50+ invoices/day).
 * Only one Orders-page banner is shown at a time. Each banner is independently dismissible.
 *
 * @since 4.9.5
 */
class Wt_Pklist_Promo_Banners {

	const MILESTONE_DISMISSED_OPTION = 'wt_pklist_milestone_cta_dismissed';
	const LOYALTY_DISMISSED_OPTION   = 'wt_pklist_loyalty_cta_dismissed';
	const SHIPPING_PROMO_DISMISSED_OPTION = 'wt_pklist_shipping_promo_dismissed';
	const DAILY_COUNT_OPTION         = 'wt_pklist_daily_invoice_count';
	const MILESTONE_THRESHOLD        = 50;
	const LOYALTY_THRESHOLD_DAYS     = 180;

	/**
	 * Constructor — registers the init action.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_hooks' ) );
	}

	/**
	 * Register admin-only hooks.
	 */
	public function init_hooks() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_notices',         array( $this, 'render_banners' ) );
		add_action( 'admin_notices',         array( $this, 'render_shipping_promo_banner_maybe' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wt_pklist_dismiss_milestone_cta',  array( $this, 'dismiss_milestone_banner' ) );
		add_action( 'wp_ajax_wt_pklist_dismiss_loyalty_cta',    array( $this, 'dismiss_loyalty_banner' ) );
		add_action( 'wp_ajax_wt_pklist_dismiss_shipping_promo', array( $this, 'dismiss_shipping_promo_banner' ) );
	}

	/**
	 * Determine which promo banner should be shown.
	 *
	 * Returns 'loyalty', 'milestone', or null.
	 * Loyalty has highest priority — if it triggers, milestone is suppressed.
	 *
	 * @return string|null
	 */
	public static function get_active_promo_banner_type() {
		if ( self::is_pro_addon_active() ) {
			return null;
		}

		if ( ! get_option( self::LOYALTY_DISMISSED_OPTION ) && self::has_loyalty_triggered() ) {
			return 'loyalty';
		}

		if ( ! get_option( self::MILESTONE_DISMISSED_OPTION ) && self::has_milestone_triggered() ) {
			return 'milestone';
		}

		return null;
	}

	/**
	 * Returns true when any promo banner is currently active.
	 *
	 * @return bool
	 */
	public static function is_promo_banner_active() {
		return null !== self::get_active_promo_banner_type();
	}

	/**
	 * Render the appropriate promo banner on the Orders List page.
	 */
	public function render_banners() {
		if ( ! $this->is_on_orders_page() ) {
			return;
		}

		$type = self::get_active_promo_banner_type();

		if ( 'loyalty' === $type ) {
			$this->render_loyalty_banner();
		} elseif ( 'milestone' === $type ) {
			$this->render_milestone_banner();
		}
	}

	/**
	 * Render the Milestone CTA banner HTML.
	 */
	public function render_milestone_banner() {
		$utm_url = 'https://www.webtoffee.com/product/woocommerce-pdf-invoices-packing-slips/?utm_source=free_plugin&utm_medium=milestone_cta&utm_campaign=PDF_invoice';
		?>
		<div id="wt-pklist-milestone-cta" class="wt-pklist-promo-banner notice">
			<button class="wt-pklist-promo-dismiss" data-action="wt_pklist_dismiss_milestone_cta" aria-label="<?php esc_attr_e( 'Dismiss', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?>">&times;</button>
			<p class="wt-pklist-promo-title"><?php esc_html_e( "\xf0\x9f\x9a\x80 Your Store Is Growing! Power It With PRO", 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></p>
			<p class="wt-pklist-promo-body"><?php esc_html_e( 'Processing 50 or more orders daily? Upgrade to Pro for advanced invoice customization, automated credit notes and flexible invoice numbering formats with auto-reset option.', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></p>
			<a href="<?php echo esc_url( $utm_url ); ?>" class="wt-pklist-promo-btn button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Render the Loyalty CTA banner HTML.
	 */
	public function render_loyalty_banner() {
		$utm_url     = 'https://www.webtoffee.com/product/woocommerce-pdf-invoices-packing-slips/?utm_source=free_plugin&utm_medium=loyalty_discount&utm_campaign=PDF_invoice';
		$coupon_line = sprintf(
			/* translators: %s: coupon code */
			__( 'Use coupon code %s at checkout.', 'print-invoices-packing-slip-labels-for-woocommerce' ),
			'<strong>PSLOYAL50</strong>'
		);
		?>
		<div id="wt-pklist-loyalty-cta" class="wt-pklist-promo-banner notice">
			<button class="wt-pklist-promo-dismiss" data-action="wt_pklist_dismiss_loyalty_cta" aria-label="<?php esc_attr_e( 'Dismiss', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?>">&times;</button>
			<p class="wt-pklist-promo-title"><?php esc_html_e( "\xf0\x9f\x8e\x89 Congratulations! You've been using the free WebToffee PDF Invoices plugin for a while, and we'd love to reward you.", 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></p>
			<p class="wt-pklist-promo-body">
				<?php esc_html_e( 'Enjoy an exclusive 15% discount on the PRO plugin and unlock premium templates, advanced customization, bulk export tools, priority support, and a complete order document workflow.', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?>
				<br>
				<?php echo wp_kses_post( $coupon_line ); ?>
			</p>
			<a href="<?php echo esc_url( $utm_url ); ?>" class="wt-pklist-promo-btn button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Render the Shipping Labels promo banner on the WooCommerce Settings → Shipping tab.
	 */
	public function render_shipping_promo_banner_maybe() {
		if ( ! $this->is_on_shipping_settings_page() ) {
			return;
		}

		if ( self::is_shipping_pro_addon_active() ) {
			return;
		}

		if ( get_option( self::SHIPPING_PROMO_DISMISSED_OPTION ) ) {
			return;
		}

		$utm_url = 'https://www.webtoffee.com/product/woocommerce-shipping-labels-delivery-notes/?utm_source=free_plugin_shipping_label&utm_medium=woo_settings_shipping&utm_campaign=Shipping_Label';
		?>
		<div id="wt-pklist-shipping-promo" class="wt-pklist-promo-banner notice">
			<button class="wt-pklist-promo-dismiss" data-action="wt_pklist_dismiss_shipping_promo" aria-label="<?php esc_attr_e( 'Dismiss', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?>">&times;</button>
			<p class="wt-pklist-promo-title"><?php esc_html_e( 'Your Shipping Labels Are Basic. The Pro Add-on Makes Them Powerful.', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></p>
			<p class="wt-pklist-promo-body"><?php esc_html_e( 'The Shipping Pro add-on slots right in and unlocks everything your free shipping labels can\'t do - custom sizes, dispatch docs, multiple templates, and more.', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></p>
			<a href="<?php echo esc_url( $utm_url ); ?>" class="wt-pklist-promo-btn button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Checkout plugin', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></a>
		</div>
		<?php
	}

	/**
	 * AJAX handler: dismiss the Shipping Labels promo banner globally.
	 */
	public function dismiss_shipping_promo_banner() {
		check_ajax_referer( 'wt_pklist_shipping_promo_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		update_option( self::SHIPPING_PROMO_DISMISSED_OPTION, 1 );
		wp_send_json_success();
	}

	/**
	 * AJAX handler: dismiss the Milestone banner globally.
	 */
	public function dismiss_milestone_banner() {
		check_ajax_referer( 'wt_pklist_milestone_cta_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		update_option( self::MILESTONE_DISMISSED_OPTION, 1 );
		wp_send_json_success();
	}

	/**
	 * AJAX handler: dismiss the Loyalty banner globally.
	 */
	public function dismiss_loyalty_banner() {
		check_ajax_referer( 'wt_pklist_loyalty_cta_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		update_option( self::LOYALTY_DISMISSED_OPTION, 1 );
		wp_send_json_success();
	}

	/**
	 * Enqueue banner CSS and JS — only on relevant pages and only when a banner will show.
	 *
	 * Pages: WooCommerce Orders List (milestone/loyalty) and WC Settings Shipping tab (shipping promo).
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		$on_orders   = $this->is_on_orders_page();
		$on_shipping = $this->is_on_shipping_settings_page();

		if ( ! $on_orders && ! $on_shipping ) {
			return;
		}

		if ( $on_orders && null === self::get_active_promo_banner_type() ) {
			return;
		}

		if ( $on_shipping && ( self::is_shipping_pro_addon_active() || get_option( self::SHIPPING_PROMO_DISMISSED_OPTION ) ) ) {
			return;
		}

		$asset_url = WF_PKLIST_PLUGIN_URL . 'admin/modules/banner/assets/';

		wp_enqueue_style(
			'wt-pklist-promo-banners',
			$asset_url . 'css/wt-pklist-promo-banners.css',
			array(),
			WF_PKLIST_VERSION
		);

		wp_enqueue_script(
			'wt-pklist-promo-banners',
			$asset_url . 'js/wt-pklist-promo-banners.js',
			array( 'jquery' ),
			WF_PKLIST_VERSION,
			true
		);

		wp_localize_script(
			'wt-pklist-promo-banners',
			'wt_pklist_promo_banners',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'milestone_nonce' => wp_create_nonce( 'wt_pklist_milestone_cta_nonce' ),
				'loyalty_nonce'   => wp_create_nonce( 'wt_pklist_loyalty_cta_nonce' ),
				'shipping_nonce'  => wp_create_nonce( 'wt_pklist_shipping_promo_nonce' ),
			)
		);
	}

	/**
	 * Check whether the Pro addon is active.
	 *
	 * @return bool
	 */
	private static function is_pro_addon_active() {
		return is_plugin_active( 'wt-woocommerce-invoice-addon/wt-woocommerce-invoice-addon.php' );
	}

	/**
	 * Check whether the Shipping Labels Pro addon is active.
	 *
	 * Why: the shipping promo banner pitches that addon — when the user already
	 * has it installed, the banner has no upsell value and is just noise.
	 *
	 * @since 4.9.5
	 * @return bool
	 */
	private static function is_shipping_pro_addon_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'wt-woocommerce-shippinglabel-addon/wt-woocommerce-shippinglabel-addon.php' );
	}

	/**
	 * Check whether the current admin page is the WooCommerce Orders List.
	 *
	 * Supports both classic CPT (edit-shop_order) and HPOS (woocommerce_page_wc-orders).
	 *
	 * @return bool
	 */
	private function is_on_orders_page() {
		$screen = get_current_screen();
		return $screen && in_array( $screen->id, array( 'edit-shop_order', 'woocommerce_page_wc-orders' ), true );
	}

	/**
	 * Check whether the current admin page is the WooCommerce Settings → Shipping tab.
	 *
	 * @return bool
	 */
	private function is_on_shipping_settings_page() {
		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return 'shipping' === $tab;
	}

	/**
	 * Check whether the daily invoice milestone (50+) has been reached today.
	 *
	 * @return bool
	 */
	private static function has_milestone_triggered() {
		$daily = get_option( self::DAILY_COUNT_OPTION, array( 'date' => '', 'count' => 0 ) );
		return is_array( $daily )
			&& isset( $daily['date'], $daily['count'] )
			&& $daily['date'] === gmdate( 'Y-m-d' )
			&& (int) $daily['count'] >= self::MILESTONE_THRESHOLD;
	}

	/**
	 * Check whether the plugin has been installed for 6+ months.
	 *
	 * @return bool
	 */
	private static function has_loyalty_triggered() {
		$start = (int) get_option( 'wt_pklist_start_date', 0 );
		return $start > 0 && ( time() - $start ) >= ( self::LOYALTY_THRESHOLD_DAYS * DAY_IN_SECONDS );
	}
}
