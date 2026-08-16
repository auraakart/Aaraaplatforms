<?php
/**
 * Admin banner shown on non-Apache servers (NGINX, IIS, etc.) where the
 * bundled .htaccess rules are not honored, advising the admin to manually
 * restrict public access to the plugin's PDF uploads directory.
 *
 * @package Wf_Woocommerce_Packing_List
 * @since   4.9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wt_Pklist_Inform_Server_Secure' ) ) {

	class Wt_Pklist_Inform_Server_Secure {

		public $plugin                  = '';
		public $banner_message          = '';
		public $banner_css_class        = '';
		public $should_show_server_info = '';
		public $ajax_action_name        = '';
		public $plugin_title            = 'WooCommerce PDF Invoices, Packing Slips, Delivery Notes & Shipping Labels';

		public function __construct( $plugin ) {

			$this->plugin                  = $plugin;
			$this->should_show_server_info = 'wt_' . $this->plugin . '_show_server_info';

			if ( ! $this->wt_get_display_server_info() && $this->is_screen_allowed() ) {
				$this->banner_css_class = 'wt_' . $this->plugin . '_show_server_info';
				add_action( 'admin_notices', array( $this, 'show_banner' ) );
				add_action( 'admin_print_footer_scripts', array( $this, 'add_banner_scripts' ) );
			}

			$this->ajax_action_name = $this->plugin . '_process_show_server_info_action';
			add_action( 'wp_ajax_' . $this->ajax_action_name, array( $this, 'process_server_info_action' ) );
			add_action( 'init', array( $this, 'load_messages' ) );
		}

		/**
		 * Only show the banner on this plugin's own admin screens.
		 */
		private function is_screen_allowed() {
			if ( ! is_admin() ) {
				return false;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			if ( '' === $page ) {
				return false;
			}
			$slug = defined( 'WF_PKLIST_POST_TYPE' ) ? WF_PKLIST_POST_TYPE : 'wf_woocommerce_packing_list';
			return ( 0 === strpos( $page, $slug ) );
		}

		public function load_messages() {
			$uploads_path = 'wp-content/uploads/' . ( defined( 'WF_PKLIST_PLUGIN_NAME' ) ? WF_PKLIST_PLUGIN_NAME : 'print-invoices-packing-slip-labels-for-woocommerce' );
			$this->banner_message = sprintf(
				/* translators: 1: plugin title, 2: uploads folder path */
				__( 'The <b>%1$s</b> plugin stores generated PDF files inside the <b>%2$s</b> folder. Please ensure that public access restrictions are set in your server for this folder.', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				$this->plugin_title,
				$uploads_path
			);
		}

		/**
		 * Prints the banner.
		 */
		public function show_banner() {
			$uploads_path = '/wp-content/uploads/' . ( defined( 'WF_PKLIST_PLUGIN_NAME' ) ? WF_PKLIST_PLUGIN_NAME : 'print-invoices-packing-slip-labels-for-woocommerce' );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
			$is_nginx        = ( false !== strpos( $server_software, 'nginx' ) );
			?>
			<style>.wp-core-ui .<?php echo esc_attr( $this->banner_css_class ); ?>.notice.is-dismissible { padding-bottom: 15px; }</style>
			<div class="<?php echo esc_attr( $this->banner_css_class ); ?> notice-warning notice is-dismissible">
				<p><?php echo wp_kses_post( $this->banner_message ); ?></p>
				<?php if ( $is_nginx ) : ?>
					<h4><?php esc_html_e( 'In case of an Nginx server, copy the below snippet into your server config to restrict public access to the plugin uploads folder, or contact your server team to assist accordingly.', 'print-invoices-packing-slip-labels-for-woocommerce' ); ?></h4>
					<code>
						#Deny access to plugin PDF uploads folder<br />
						location ~ ^<?php echo esc_html( $uploads_path ); ?> { deny all; }
					</code>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * AJAX hook to process the dismiss action on the banner.
		 */
		public function process_server_info_action() {
			check_ajax_referer( $this->plugin );
			if ( isset( $_POST['wt_action_type'] ) && 'dismiss' === $_POST['wt_action_type'] ) {
				$this->wt_set_display_server_info( 1 );
			}
			exit();
		}

		/**
		 * Add banner dismiss JS to admin footer.
		 */
		public function add_banner_scripts() {
			$ajax_url = admin_url( 'admin-ajax.php' );
			$nonce    = wp_create_nonce( $this->plugin );
			?>
			<script type="text/javascript">
				(function($) {
					"use strict";
					var data_obj = {
						_wpnonce: '<?php echo esc_attr( $nonce ); ?>',
						action: '<?php echo esc_attr( $this->ajax_action_name ); ?>',
						wt_action_type: 'dismiss'
					};
					$(document).on('click', '.<?php echo esc_attr( $this->banner_css_class ); ?> .notice-dismiss', function(e) {
						e.preventDefault();
						$.ajax({
							url: '<?php echo esc_url( $ajax_url ); ?>',
							data: data_obj,
							type: 'POST'
						});
					});
				})(jQuery);
			</script>
			<?php
		}

		public function wt_get_display_server_info() {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
			if ( ( false !== strpos( $server_software, 'Apache' ) ) || ( false !== strpos( $server_software, 'LiteSpeed' ) ) ) {
				return true;
			}
			return (bool) get_option( $this->should_show_server_info );
		}

		public function wt_set_display_server_info( $display = false ) {
			update_option( $this->should_show_server_info, $display ? 1 : 0 );
		}
	}
}
