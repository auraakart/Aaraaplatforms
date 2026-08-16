<?php
/**
 * History tab cross-promotion banner — promotes Import Export Suite (IES).
 *
 * Single-variant Suite CTA at the top of the
 * shared History admin page. The same handler file is intended to be shipped
 * into the order and user basic plugins; the WT_IEW_HISTORY_BANNER_LOADED
 * define guard (set by the coordinator before require_once) ensures only the
 * first-loaded plugin's copy executes.
 *
 * @package Order_Import_Export_For_WooCommerce
 * @since   2.7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wbte_Iew_History_Banner' ) ) {

	/**
	 * History tab Suite CTA banner.
	 */
	class Wbte_Iew_History_Banner {

		/**
		 * Shared option key — stores the Suite-CTA dismiss timestamp.
		 * Reused by banners 1, 2 Suite variant, and 3 Suite variant.
		 *
		 * @var string
		 */
		const DISMISS_OPTION = 'wt_iew_hide_ies_cta_dismissed_at';

		/**
		 * AJAX action name.
		 *
		 * @var string
		 */
		const AJAX_ACTION = 'wt_iew_dismiss_history_cta_banner';

		/**
		 * Nonce action used for the dismiss AJAX call.
		 *
		 * @var string
		 */
		const NONCE_ACTION = 'wt_iew_history_cta_banner';

		/**
		 * Plugin file paths whose presence suppresses this banner.
		 * Currently: Import Export Suite (IES) — once the user has IES installed,
		 * the Suite CTA is irrelevant.
		 *
		 * @var string[]
		 */
		private static $suppressing_plugins = array(
			'import-export-suite-for-woocommerce/import-export-suite-for-woocommerce.php',
		);

		/**
		 * Hook everything up.
		 */
		public function __construct() {
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_dismiss_ajax' ) );

			if ( $this->is_suppressed() ) {
				return;
			}

			add_action( 'admin_notices', array( $this, 'render_banner' ) );
		}

		/**
		 * Determine whether to suppress the banner entirely.
		 * Suppress when any suppressing plugin is active OR when the user has dismissed it.
		 *
		 * @return bool
		 */
		private function is_suppressed() {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			foreach ( self::$suppressing_plugins as $plugin_file ) {
				if ( is_plugin_active( $plugin_file ) ) {
					return true;
				}
			}

			$dismissed_at = absint( get_option( self::DISMISS_OPTION, 0 ) );
			return $dismissed_at > 0;
		}

		/**
		 * Determine whether the current admin screen is the History page of any
		 * basic plugin. Lookup by screen id; when the file is later shipped to
		 * order / user basic plugins, those plugins' screen ids will also match.
		 *
		 * @return bool
		 */
		private function is_history_screen() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || empty( $screen->id ) ) {
				return false;
			}

			$history_screens = array(
				// product-import-export-for-woo (basic).
				'webtoffee-import-export-basic_page_wt_import_export_for_woo_basic_history',
				// order-import-export-for-woocommerce (basic) — populated when shipped there.
				'webtoffee-import-export-basic_page_wt_import_export_for_woo_order_basic_history',
				// users-customers-import-export-for-wp-woocommerce (basic) — populated when shipped there.
				'webtoffee-import-export-basic_page_wt_import_export_for_woo_user_basic_history',
			);

			return in_array( $screen->id, $history_screens, true );
		}

		/**
		 * Render the banner HTML on the History page only.
		 */
		public function render_banner() {
			if ( ! $this->is_history_screen() ) {
				return;
			}
			$accent_color = '#007CBA';
			$button_color = '#2270B1';
			$ajax_url     = admin_url( 'admin-ajax.php' );
			$nonce        = wp_create_nonce( self::NONCE_ACTION );
			$cta_url      = 'https://www.webtoffee.com/product/woocommerce-import-export-suite/?utm_source=free_plugin&utm_medium=import_export_history_tab&utm_campaign=Import_Export_Suite';

			$title = __( 'All your Woocommerce imports and exports, in one plugin', 'order-import-export-for-woocommerce' );
			$body  = __( 'Why use multiple tools when one plugin can handle it all? Upgrade to Import Export Suite to manage products, orders, users, coupons, subscriptions, and reviews with one plugin.', 'order-import-export-for-woocommerce' );
			$cta   = __( 'Get Import Export Suite', 'order-import-export-for-woocommerce' );

			?>
			<div id="wt-iew-history-cta-banner" class="notice" style="position: relative; padding: 20px 40px 25px 28px; background-color: #fff; border-left: 6px solid <?php echo esc_attr( $accent_color ); ?>; border-radius: 3px; margin: 15px 0;">
				<button type="button" class="wt-iew-history-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'order-import-export-for-woocommerce' ); ?>" style="position: absolute; top: 14px; right: 18px; border: none; background: none; color: #6E6E6E; cursor: pointer; font-size: 20px; line-height: 1; padding: 0;">&times;</button>
				<h2 style="margin: 0 0 12px; color: #000; font-weight: 500; font-size: 17px; line-height: 21px;"><?php echo esc_html( $title ); ?></h2>
				<p style="margin: 0 0 16px; font-size: 14px; color: #434343; line-height: 21px; max-width: 900px;"><?php echo esc_html( $body ); ?></p>
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block; background: <?php echo esc_attr( $button_color ); ?>; border: 0; color: #fff; padding: 7px 24px; border-radius: 3px; text-decoration: none; font-size: 13px; font-weight: 500; line-height: 18px; min-width: 135px; text-align: center; box-shadow: none;"><?php echo esc_html( $cta ); ?></a>
			</div>

			<script type="text/javascript">
				( function ( $ ) {
					$( document ).ready( function () {
						$( '.wt-iew-history-dismiss' ).on( 'click', function ( e ) {
							e.preventDefault();
							var banner = $( '#wt-iew-history-cta-banner' );
							$.ajax( {
								url:  '<?php echo esc_url( $ajax_url ); ?>',
								type: 'POST',
								data: {
									action:      '<?php echo esc_js( self::AJAX_ACTION ); ?>',
									option_name: '<?php echo esc_js( self::DISMISS_OPTION ); ?>',
									nonce:       '<?php echo esc_js( $nonce ); ?>'
								},
								success: function () {
									banner.fadeOut( 200 );
								}
							} );
						} );
					} );
				} )( jQuery );
			</script>
			<?php
		}

		/**
		 * AJAX dismiss handler. Applies the nonce + allowlist pattern:
		 * nonce check, then validate option_name against an explicit allowlist
		 * before writing. Rejects unknown option names with 400.
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

			$allowed_options = array( self::DISMISS_OPTION );

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

	new Wbte_Iew_History_Banner();
}
