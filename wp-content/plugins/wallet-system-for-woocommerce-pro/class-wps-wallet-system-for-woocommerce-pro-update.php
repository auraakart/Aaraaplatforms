<?php
/**
 * The update file of plugin.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Wps_Wallet_System_For_Woocommerce_Pro_Update' ) ) {
	/**
	 * Class for plugin update.
	 */
	class Wps_Wallet_System_For_Woocommerce_Pro_Update {

		/**
		 * Constructor of class.
		 */
		public function __construct() {
			register_activation_hook( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_BASE_FILE, array( $this, 'wps_check_activation' ) );
			add_action( 'wps_wsfwp_check_event', array( $this, 'wps_check_update' ) );
			add_filter( 'http_request_args', array( $this, 'wps_updates_exclude' ), 5, 2 );
			register_deactivation_hook( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_BASE_FILE, array( $this, 'wps_check_deactivation' ) );

			$plugin_update = get_option( 'wps_wsfwp_plugin_update', 'false' );
			if ( 'true' === $plugin_update ) {
				// To add view details content in plugin update notice on plugins page.
				add_action( 'install_plugins_pre_plugin-information', array( $this, 'wps_wsfwp_details' ) );
				// To add plugin update notice after plugin update message.
				add_action( 'in_plugin_update_message-wallet-system-for-woocommerce-pro/wallet-system-for-woocommerce-pro.php', array( $this, 'wps_wsfwp_in_plugin_update_notice' ), 10, 2 );
			}
		}
		/**
		 * Unschedule event hook.
		 *
		 * @return void
		 */
		public function wps_check_deactivation() {
			wp_clear_scheduled_hook( 'wps_wsfwp_check_event' );
		}

		/**
		 * Schedule event hook.
		 *
		 * @return void
		 */
		public function wps_check_activation() {
			wp_schedule_event( time(), 'daily', 'wps_wsfwp_check_event' );
		}

		/**
		 * Show plugin deatils.
		 *
		 * @return void
		 */
		public function wps_wsfwp_details() {

			global $tab;

			// change $_REQUEST['plugin] to your plugin slug name.
			if ( 'plugin-information' === $tab && ( isset( $_REQUEST['plugin'] ) && 'wallet-system-for-woocommerce-pro' === $_REQUEST['plugin'] ) ) {

				$data = $this->get_plugin_update_data();

				if ( is_wp_error( $data ) || empty( $data ) ) {

					return;
				}

				if ( ! empty( $data['body'] ) ) {

					$all_data = json_decode( $data['body'], true );

					if ( ! empty( $all_data ) && is_array( $all_data ) ) {

						$this->create_html_data( $all_data );

						wp_die();
					}
				}
			}
		}

		/**
		 * Returns plugin updated data.
		 *
		 * @return array
		 */
		public function get_plugin_update_data() {

			// replace with your plugin url.
			$url      = 'https://wpswings.com/pluginupdates/wallet-system-for-woocommerce-pro/update.php';
			$postdata = array(
				'action' => 'check_update',
				'license_code' => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_LICENSE_KEY,
			);

			$args = array(
				'method' => 'POST',
				'body' => $postdata,
			);

			$data = wp_remote_post( $url, $args );

			return $data;
		}

		/**
		 * Render HTML content.
		 *
		 * @param array $all_data plugin data.
		 * @return void
		 */
		public function create_html_data( $all_data ) {
			?>
			<style>
				#TB_window{
					top : 4% !important;
				}
				.wps_wsfwp_banner > img {
					width: 50%;
				}
				.wps_wsfwp_banner > h1 {
					margin-top: 0px;
				}
				.wps_wsfwp_banner {
					text-align: center;
				}
				.wps_wsfwp_description > h4 {
					background-color: #3779B5;
					padding: 5px;
					color: #ffffff;
					border-radius: 5px;
				}
				.wps_wsfwp_changelog_details > h4 {
					background-color: #3779B5;
					padding: 5px;
					color: #ffffff;
					border-radius: 5px;
				}
			</style>
			<div class="wps_wsfwp_details_wrapper">
				<div class="wps_wsfwp_banner">
					<h1><?php echo esc_html( $all_data['name'] . ' ' . $all_data['version'] ); ?></h1>
					<img src="<?php echo esc_attr( $all_data['banners']['logo'] ); ?>">
				</div>

				<div class="wps_wsfwp_description">
					<h4><?php esc_html_e( 'Plugin Description', 'wallet-system-for-woocommerce-pro' ); ?></h4>
					<span><?php echo esc_html( $all_data['sections']['description'] ); ?></span>
				</div>
				<div class="wps_wsfwp_changelog_details">
					<h4><?php esc_html_e( 'Plugin Change Log', 'wallet-system-for-woocommerce-pro' ); ?></h4>
					<span><?php echo wp_kses_post( $all_data['sections']['changelog'] ); ?></span>
				</div> 
			</div>
			<?php
		}

		/**
		 * Show notice if plugin has to bo update.
		 *
		 * @return void
		 */
		public function wps_wsfwp_in_plugin_update_notice() {

			$data = $this->get_plugin_update_data();

			if ( is_wp_error( $data ) || empty( $data ) ) {

				return;
			}

			if ( isset( $data['body'] ) ) {

				$all_data = json_decode( $data['body'], true );

				if ( is_array( $all_data ) && ! empty( $all_data['sections']['update_notice'] ) ) {

					?>

					<style type="text/css">
						#wps-standard-plugin-update .dummy {
							display: none;
						}

						#wps_wsfwp_in_plugin_update_div p:before {
							content: none;
						}

						#wps_wsfwp_in_plugin_update_div {
							border-top: 1px solid #ffb900;
							margin-left: -13px;
							padding-left: 20px;
							padding-top: 10px;
							padding-bottom: 5px;
						}

						#wps_wsfwp_in_plugin_update_div ul {
							list-style-type: decimal;
							padding-left: 20px;
						}

					</style>

					<?php

					echo '</p><div id="wps_wsfwp_in_plugin_update_div">' . wp_kses_post( $all_data['sections']['update_notice'] ) . '</div><p class="dummy">';
				}
			}
		}

		/**
		 * Check for plugin updation.
		 *
		 * @return bool
		 */
		public function wps_check_update() {
			global $wp_version;
			$update_check_wws = 'https://wpswings.com/pluginupdates/wallet-system-for-woocommerce-pro/update.php';
			$plugin_folder    = plugin_basename( dirname( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_BASE_FILE ) );
			$plugin_file      = basename( ( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_BASE_FILE ) );
			if ( defined( 'WP_INSTALLING' ) ) {
				return false;
			}
			$postdata = array(
				'action' => 'check_update',
				'license_key' => WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_LICENSE_KEY,
			);

			$args = array(
				'method' => 'POST',
				'body' => $postdata,
			);

			$response = wp_remote_post( $update_check_wws, $args );

			if ( empty( $response['response']['code'] ) || 200 !== (int) $response['response']['code'] ) {

				$plugin_transient  = get_site_transient( 'update_plugins' );
				unset( $plugin_transient->response[ $plugin_folder . '/' . $plugin_file ] );
				set_site_transient( 'update_plugins', $plugin_transient );
				return;
			}

			list( $version, $url ) = explode( '~', $response['body'] );

			if ( $this->wps_plugin_get( 'Version' ) >= $version ) {

				update_option( 'wps_wsfwp_plugin_update', 'false' );

				return false;
			}

			update_option( 'wps_wsfwp_plugin_update', 'true' );

			$plugin_transient = get_site_transient( 'update_plugins' );
			$a                = array(
				'slug' => $plugin_folder,
				'new_version' => $version,
				'url' => $this->wps_plugin_get( 'AuthorURI' ),
				'package' => $url,
			);
			$o = (object) $a;
			$plugin_transient->response[ $plugin_folder . '/' . $plugin_file ] = $o;
			set_site_transient( 'update_plugins', $plugin_transient );
		}

		/**
		 * This function is used to update.
		 *
		 * @param [type] $r r.
		 * @param [type] $url url.
		 * @return string
		 */
		public function wps_updates_exclude( $r, $url ) {
			if ( 0 !== strpos( $url, 'http://api.wordpress.org/plugins/update-check' ) ) {
				return $r;
			}
			$plugins = unserialize( $r['body']['plugins'] );
			if ( ! empty( $plugins->plugins ) ) {
				unset( $plugins->plugins[ plugin_basename( __FILE__ ) ] );
			}
			if ( ! empty( $plugins->active ) ) {
				unset( $plugins->active[ array_search( plugin_basename( __FILE__ ), $plugins->active ) ] );
			}
			$r['body']['plugins'] = serialize( $plugins );
			return $r;
		}

		/**
		 * Returns current plugin info.
		 *
		 * @param string $i data to be return.
		 * @return string
		 */
		public function wps_plugin_get( $i ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_folder = get_plugins( '/' . plugin_basename( dirname( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_BASE_FILE ) ) );
			$plugin_file = basename( ( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_BASE_FILE ) );
			return $plugin_folder[ $plugin_file ][ $i ];
		}
	}
	new Wps_Wallet_System_For_Woocommerce_Pro_Update();
}
