<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}
if ( !class_exists( 'Wt_Non_Apache_Info' ) ) {

	class Wt_Non_Apache_Info {

		/**
		 * config options 
		 */
		public $plugin			         = '';
		public $banner_message		     = '';
		public $sholud_show_server_info	 = '';
        public $ajax_action_name         = '';
        public $plugin_title             = '';
		public $banner_css_class         = "";


		public function __construct( $plugin ) {
			$this->plugin = $plugin;
			$this->sholud_show_server_info	 = 'wt_' . $this->plugin . '_show_server_info';

			if ( ! $this->wt_get_display_server_info() ) {
				if ( 
				    class_exists( 'Wt_Import_Export_For_Woo_Order_Basic_Common_Helper' ) 
				    && method_exists( 'Wt_Import_Export_For_Woo_Order_Basic_Common_Helper', 'wt_is_screen_allowed' ) 
				    && Wt_Import_Export_For_Woo_Order_Basic_Common_Helper::wt_is_screen_allowed() 
				) {
					$this->banner_css_class = 'wt_' . $this->plugin . '_show_server_info';
					add_action( 'admin_notices', array( $this, 'show_banner' ) );
					add_action( 'admin_print_footer_scripts', array( $this, 'add_banner_scripts' ) ); /* add banner scripts */
				}
			}
			$this->ajax_action_name = $this->plugin . '_process_show_server_info_action';
			add_action( 'wp_ajax_' . $this->ajax_action_name, array( $this, 'process_server_info__action' ) ); /* process banner user action */
		}

		/**
		 * 	Prints the banner 
		 */
		public function show_banner() {
			?>
			<div class="<?php echo esc_attr( $this->banner_css_class ); ?> notice-warning notice is-dismissible">

				<p>
					<?php 
					// translators: %s: Plugin title.
					echo wp_kses_post( sprintf(__('The %s plugin uploads the imported file into <b>wp-content/webtoffee_import</b> folder. Please ensure that public access restrictions are set in your server for this folder.', 'order-import-export-for-woocommerce' ), '<b>'.$this->plugin_title.'</b>') ); ?>				
				</p>
				<p>
					<?php if ( isset( $_SERVER[ 'SERVER_SOFTWARE' ] ) && (strpos( sanitize_text_field(wp_unslash($_SERVER[ 'SERVER_SOFTWARE' ])), 'nginx' ) !== false ) ): ?>
					<h4><?php esc_html_e( 'Incase of Nginx server, copy the below code into your server config file to restrict public access to the wp-content folder or contact the server team to assist accordingly.', 'order-import-export-for-woocommerce' ); ?></h4>
					<code>
						#Deny access to wp-content folders<br/>
						location ~* ^/(wp-content)/(.*?)\.(zip|gz|tar|csv|bzip2|7z)\$ { deny all; }<br/>
						location ~ ^/wp-content/webtoffee_import { deny all; }
					</code>
				<?php endif; ?>
			</p>
			</div>
			<?php
		}

		/**
		 * 	Ajax hook to process user action on the banner.
		 */
		public function process_server_info__action() {
			check_ajax_referer( $this->plugin );
			if ( isset( $_POST[ 'wt_action_type' ] ) && 'dismiss' == $_POST[ 'wt_action_type' ] ) {
				$this->wt_set_display_server_info( 1 );
			}
			exit();
		}

		/**
		 * 	Add banner JS to admin footer
		 */
		public function add_banner_scripts() {
			$ajax_url	 = admin_url( 'admin-ajax.php' );
			$nonce		 = wp_create_nonce( $this->plugin );
			?>
			<script type="text/javascript">
			( function ( $ ) {
			"use strict";

			/* prepare data object */
			var data_obj = {
			_wpnonce: '<?php echo esc_js( $nonce ); ?>',
			action: '<?php echo esc_js( $this->ajax_action_name ); ?>',
			wt_action_type: 'dismiss',
			};

			$( document ).on( 'click', '.<?php echo esc_js( $this->banner_css_class ); ?> .notice-dismiss', function ( e )
			{
			e.preventDefault();
			$.ajax( {
				url: '<?php echo esc_url( $ajax_url ); ?>',
				data: data_obj,
				type: 'POST',
			} );

			} );

			} )( jQuery )
			</script>
			<?php
		}

		public function wt_get_display_server_info() {

			if ( isset( $_SERVER[ 'SERVER_SOFTWARE' ] ) 
				&& (
					strpos( sanitize_text_field(wp_unslash($_SERVER[ 'SERVER_SOFTWARE' ])), 'Apache' ) !== false
					|| strpos( sanitize_text_field(wp_unslash($_SERVER[ 'SERVER_SOFTWARE' ])), 'LiteSpeed' ) !== false
				)
			) {
				return true;
			} else {
				return (bool) get_option( $this->sholud_show_server_info );
			}
		}

		public function wt_set_display_server_info( $display = false ) {
			update_option( $this->sholud_show_server_info, $display ? 1 : 0  );
		}

	}

}
