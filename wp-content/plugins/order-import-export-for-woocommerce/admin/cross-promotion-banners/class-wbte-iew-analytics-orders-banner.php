<?php
/**
 * Analytics → Orders tab cross-promotion banner with rotation.
 *
 * Mirror of the Products banner but for orders.
 * Variants:
 *   - "Premium (Order)" — upgrade-to-Order-Premium CTA
 *   - "Suite (IES)"     — switch-to-suite cross-promo CTA
 *
 * See get_variant_to_show() for the variant + 30-day rotation decision matrix.
 * Same handler shipped in all three basic plugins; the
 * WT_IEW_ANALYTICS_ORDERS_BANNER_LOADED define guard ensures only the
 * first-loaded copy executes.
 *
 * @package Order_Import_Export_For_WooCommerce
 * @since   2.7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wbte_Iew_Analytics_Orders_Banner' ) ) {

	/**
	 * Banner 3 — WC Analytics → Orders tab (Premium-Order / Suite rotation).
	 */
	class Wbte_Iew_Analytics_Orders_Banner {

		/** Suite (IES) dismiss timestamp — shared across all Suite banners. */
		const DISMISS_SUITE = 'wt_iew_hide_ies_cta_dismissed_at';

		/** Premium-Order dismiss timestamp — local to this banner. */
		const DISMISS_PREMIUM = 'wt_iew_hide_analytics_orders_premium_cta_dismissed_at';

		const AJAX_ACTION  = 'wt_iew_dismiss_analytics_orders_banner';
		const NONCE_ACTION = 'wt_iew_analytics_orders_banner';

		const ROTATION_DELAY = 30 * DAY_IN_SECONDS;

		const PLUGIN_PRODUCT_FREE  = 'product-import-export-for-woo/product-import-export-for-woo.php';
		const PLUGIN_ORDER_FREE    = 'order-import-export-for-woocommerce/order-import-export-for-woocommerce.php';
		const PLUGIN_USER_FREE     = 'users-customers-import-export-for-wp-woocommerce/users-customers-import-export-for-wp-woocommerce.php';
		const PLUGIN_ORDER_PREMIUM = 'wt-import-export-for-woo-order/wt-import-export-for-woo-order.php';
		const PLUGIN_IES           = 'import-export-suite-for-woocommerce/import-export-suite-for-woocommerce.php';

		/**
		 * Hook everything up.
		 */
		public function __construct() {
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_dismiss_ajax' ) );
			add_action( 'admin_footer', array( $this, 'maybe_inject_banner' ) );
		}

		/**
		 * Is the given plugin active on the site?
		 *
		 * @param string $plugin_file Plugin folder/file path.
		 * @return bool
		 */
		private function is_plugin_active( $plugin_file ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			return is_plugin_active( $plugin_file );
		}

		/**
		 * Decide which variant (if any) should render right now.
		 *
		 * Rotation rules:
		 *   - Premium-Order or IES active → suppress.
		 *   - order-free not active → Suite only (no Premium-Order without an order plugin to upgrade from).
		 *   - order-free + (user-free OR product-free) → Suite first, Premium-Order after 30 days.
		 *   - order-free only                          → Premium-Order first, Suite after 30 days.
		 *
		 * @return string|null 'suite' | 'premium' | null
		 */
		private function get_variant_to_show() {
			if ( $this->is_plugin_active( self::PLUGIN_ORDER_PREMIUM ) || $this->is_plugin_active( self::PLUGIN_IES ) ) {
				return null;
			}

			$has_order   = $this->is_plugin_active( self::PLUGIN_ORDER_FREE );
			$has_user    = $this->is_plugin_active( self::PLUGIN_USER_FREE );
			$has_product = $this->is_plugin_active( self::PLUGIN_PRODUCT_FREE );

			$suite_dismissed_at   = absint( get_option( self::DISMISS_SUITE, 0 ) );
			$premium_dismissed_at = absint( get_option( self::DISMISS_PREMIUM, 0 ) );
			$now                  = time();

			if ( ! $has_order ) {
				return 0 === $suite_dismissed_at ? 'suite' : null;
			}

			$multi_plugin = ( $has_user || $has_product );

			if ( $multi_plugin ) {
				if ( 0 === $suite_dismissed_at ) {
					return 'suite';
				}
				if ( ( $now - $suite_dismissed_at ) >= self::ROTATION_DELAY && 0 === $premium_dismissed_at ) {
					return 'premium';
				}
				return null;
			}

			if ( 0 === $premium_dismissed_at ) {
				return 'premium';
			}
			if ( ( $now - $premium_dismissed_at ) >= self::ROTATION_DELAY && 0 === $suite_dismissed_at ) {
				return 'suite';
			}
			return null;
		}

		/**
		 * Current admin screen check — WC Analytics → Orders page.
		 *
		 * @return bool
		 */
		private function is_analytics_orders_screen() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 'woocommerce_page_wc-admin' !== $screen->id ) {
				return false;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
			if ( ! isset( $_GET['path'] ) ) {
				return false;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
			return '/analytics/orders' === sanitize_text_field( wp_unslash( $_GET['path'] ) );
		}

		/**
		 * Build a copy bundle for the chosen variant.
		 *
		 * @param string $variant 'suite' | 'premium'.
		 * @return array
		 */
		private function get_variant_copy( $variant ) {
			if ( 'premium' === $variant ) {
				return array(
					'title'          => __( 'Go Beyond Basic Order Imports Exports', 'order-import-export-for-woocommerce' ),
					'body'           => __( 'Import, export, and back up orders, subscriptions, and coupons with advanced filters, mapping, and scheduling.', 'order-import-export-for-woocommerce' ),
					'cta'            => __( 'Upgrade to Premium', 'order-import-export-for-woocommerce' ),
					'cta_url'        => 'https://www.webtoffee.com/product/order-import-export-plugin-for-woocommerce/?utm_source=free_plugin_order_import&utm_medium=analytics_orders_tab&utm_campaign=Order_Import_Export',
					'dismiss_option' => self::DISMISS_PREMIUM,
				);
			}

			// 'suite' default.
			return array(
				'title'          => __( "Your store's grown past basic imports.", 'order-import-export-for-woocommerce' ),
				'body'           => __( 'Import Export Suite lets you import, export, migrate, and bulk update every WooCommerce data type — products, orders, users, coupons, reviews, and subscriptions — in one place.', 'order-import-export-for-woocommerce' ),
				'cta'            => __( 'Get Import Export Suite', 'order-import-export-for-woocommerce' ),
				'cta_url'        => 'https://www.webtoffee.com/product/woocommerce-import-export-suite/?utm_source=free_plugin_product_user_import&utm_medium=analytics_orders_tab&utm_campaign=Import_Export_Suite',
				'dismiss_option' => self::DISMISS_SUITE,
			);
		}

		/**
		 * Render the banner if eligible. Hooked to admin_footer because the WC
		 * Analytics page is a React SPA — admin_notices doesn't surface there.
		 */
		public function maybe_inject_banner() {
			if ( ! $this->is_analytics_orders_screen() ) {
				return;
			}

			$variant = $this->get_variant_to_show();
			if ( null === $variant ) {
				return;
			}

			$copy        = $this->get_variant_copy( $variant );
			$accent      = '#007CBA';
			$button_bg   = '#2270B1';
			$ajax_url    = admin_url( 'admin-ajax.php' );
			$nonce       = wp_create_nonce( self::NONCE_ACTION );
			$banner_id   = 'wt-iew-analytics-orders-cta-banner';
			$dismiss_btn = esc_attr__( 'Dismiss', 'order-import-export-for-woocommerce' );

			ob_start();
			?>
			<div id="<?php echo esc_attr( $banner_id ); ?>" class="wt-iew-cta-banner" style="position: relative; padding: 20px 40px 25px 28px; background-color: #fff; border-left: 6px solid <?php echo esc_attr( $accent ); ?>; border-radius: 3px; margin: 15px 24px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<button type="button" class="wt-iew-cta-dismiss" aria-label="<?php echo esc_attr( $dismiss_btn ); ?>" style="position: absolute; top: 14px; right: 18px; border: none; background: none; color: #6E6E6E; cursor: pointer; font-size: 20px; line-height: 1; padding: 0;">&times;</button>
				<h2 style="margin: 0 0 12px; color: #000; font-weight: 500; font-size: 17px; line-height: 21px;"><?php echo esc_html( $copy['title'] ); ?></h2>
				<p style="margin: 0 0 16px; font-size: 14px; color: #434343; line-height: 21px; max-width: 900px;"><?php echo esc_html( $copy['body'] ); ?></p>
				<a href="<?php echo esc_url( $copy['cta_url'] ); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block; background: <?php echo esc_attr( $button_bg ); ?>; border: 0; color: #fff; padding: 7px 24px; border-radius: 3px; text-decoration: none; font-size: 13px; font-weight: 500; line-height: 18px; min-width: 135px; text-align: center; box-shadow: none;"><?php echo esc_html( $copy['cta'] ); ?></a>
			</div>
			<?php
			$html = ob_get_clean();
			if ( empty( trim( $html ) ) ) {
				return;
			}
			?>
			<script type="text/javascript">
				( function () {
					var inject = function () {
						if ( document.getElementById( '<?php echo esc_js( $banner_id ); ?>' ) ) {
							return;
						}
						var html = <?php echo wp_json_encode( $html ); ?>;
						var wrap = document.createElement( 'div' );
						wrap.innerHTML = html;
						var node = wrap.firstElementChild;
						var header = document.querySelector( '.woocommerce-layout__header' );
						if ( header && header.parentNode ) {
							header.parentNode.insertBefore( node, header.nextSibling );
						} else {
							var layout = document.querySelector( '.woocommerce-layout' );
							if ( layout ) {
								layout.insertBefore( node, layout.firstChild );
							}
						}
						var btn = node.querySelector( '.wt-iew-cta-dismiss' );
						if ( btn ) {
							btn.addEventListener( 'click', function ( e ) {
								e.preventDefault();
								var fd = new FormData();
								fd.append( 'action',      '<?php echo esc_js( self::AJAX_ACTION ); ?>' );
								fd.append( 'option_name', '<?php echo esc_js( $copy['dismiss_option'] ); ?>' );
								fd.append( 'nonce',       '<?php echo esc_js( $nonce ); ?>' );
								fetch( '<?php echo esc_url_raw( $ajax_url ); ?>', { method: 'POST', body: fd, credentials: 'same-origin' } )
									.finally( function () { node.style.display = 'none'; } );
							} );
						}
					};
					var attempts = 0;
					var maxAttempts = 30;
					var tick = setInterval( function () {
						attempts++;
						if ( document.querySelector( '.woocommerce-layout__header' ) || attempts >= maxAttempts ) {
							clearInterval( tick );
							inject();
						}
					}, 300 );
				} )();
			</script>
			<?php
		}

		/**
		 * AJAX dismiss handler — nonce + explicit allowlist.
		 */
		public function handle_dismiss_ajax() {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			if ( ! isset( $_POST['option_name'] ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Missing option name.', 'order-import-export-for-woocommerce' ) ),
					400
				);
			}

			$option_name = sanitize_text_field( wp_unslash( $_POST['option_name'] ) );

			$allowed_options = array(
				self::DISMISS_SUITE,
				self::DISMISS_PREMIUM,
			);

			if ( ! in_array( $option_name, $allowed_options, true ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Invalid option.', 'order-import-export-for-woocommerce' ) ),
					400
				);
			}

			update_option( $option_name, time() );
			wp_send_json_success();
		}
	}

	new Wbte_Iew_Analytics_Orders_Banner();
}
