<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals,WordPress.DB.DirectDatabaseQuery

defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin activation
 *
 * @link       https://www.webtoffee.com/
 * @since      2.5.0
 *
 * @package    Wf_Woocommerce_Packing_List
 * @subpackage Wf_Woocommerce_Packing_List/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      2.5.0
 * @package    Wf_Woocommerce_Packing_List
 * @subpackage Wf_Woocommerce_Packing_List/includes
 * @author     WebToffee <info@webtoffee.com>
 */
class Wf_Woocommerce_Packing_List_Activator {

	/**
	 *  Plugin activation hook
	 *
	 *  @since   2.5.0
	 *  @since   2.6.3 Added option to secure directory with htaccess
	 *  @since   2.7.0 Added option to update Store address from Woo
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		include plugin_dir_path( WF_PKLIST_PLUGIN_FILENAME ) . 'admin/modules/migrator/migrator.php';
		if ( is_multisite() ) {
			if ( is_network_admin() ) {
				// Get all blogs in the network and activate plugin on each one
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching @codingStandardsIgnoreLine -- This is a safe use of SELECT
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
				foreach ( $blog_ids as $blog_id ) {
					self::install_tables_multi_site( $blog_id );
				}
			} else {
				$current_blog_id = get_current_blog_id();
				self::install_tables_multi_site( $current_blog_id );
			}
		} else {
			self::do_update();
			self::copy_address_from_woo();
			self::save_plugin_version();
			Wf_Woocommerce_Packing_List_Migrator::migrate();
		}
		self::secure_upload_dir();
		self::cleanup_legacy_pdf_files();
		$site_token = self::generate_site_token();
		self::migrate_files_to_token_dir( $site_token );
		do_action( 'wt_pklist_activate' );
	}

	/**
	 *   @since 2.8.4
	 *   Fix the issue of inserting the table in all the site when its multisite
	 */
	public static function install_tables_multi_site( $blog_id ) {
		switch_to_blog( $blog_id );
		self::do_update();
		self::copy_address_from_woo();
		self::save_plugin_version();
		Wf_Woocommerce_Packing_List_Migrator::migrate();
		$site_token = self::generate_site_token();
		self::migrate_files_to_token_dir( $site_token );
		restore_current_blog();
	}

	public static function do_update() {
		self::install_tables();
		self::update_cross_promo_banner_version();
	}

	/**
	 *  Check and update the cross promotion banner version.
	 */
	public static function update_cross_promo_banner_version() {
		$current_latest = get_option( 'wbfte_promotion_banner_version' );

		if ( false === $current_latest || // User is installing the plugin first time.
			version_compare( $current_latest, WBTE_PKLIST_CROSS_PROMO_BANNER_VERSION, '<' ) // $current_latest is lesser than the installed version in this plugin.
		) {
			update_option( 'wbfte_promotion_banner_version', WBTE_PKLIST_CROSS_PROMO_BANNER_VERSION );
		}
	}

	/**
	 *   @since 2.7.0
	 *   Update store address from Woo
	 */
	public static function copy_address_from_woo() {
		if ( class_exists( 'Wf_Woocommerce_Packing_List' ) ) {
			/* all fields are empty. */
			if (
				'' === Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_companyname' ) &&
				'' === Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_sender_name' ) &&
				'' === Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_sender_address_line1' ) &&
				'' === Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_sender_address_line2' ) &&
				'' === Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_sender_city' ) &&
				'' === Wf_Woocommerce_Packing_List::get_option( 'wf_country' ) &&
				'' === Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_sender_postalcode' )
			) {
				Wf_Woocommerce_Packing_List::update_option( 'woocommerce_wf_packinglist_companyname', get_bloginfo( 'name' ) );
				Wf_Woocommerce_Packing_List::update_option( 'woocommerce_wf_packinglist_sender_name', get_bloginfo( 'name' ) );
				Wf_Woocommerce_Packing_List::update_option( 'woocommerce_wf_packinglist_sender_address_line1', get_option( 'woocommerce_store_address' ) );
				Wf_Woocommerce_Packing_List::update_option( 'woocommerce_wf_packinglist_sender_address_line2', get_option( 'woocommerce_store_address_2' ) );
				Wf_Woocommerce_Packing_List::update_option( 'woocommerce_wf_packinglist_sender_city', get_option( 'woocommerce_store_city' ) );
				Wf_Woocommerce_Packing_List::update_option( 'wf_country', get_option( 'woocommerce_default_country' ) );
				Wf_Woocommerce_Packing_List::update_option( 'woocommerce_wf_packinglist_sender_postalcode', get_option( 'woocommerce_store_postcode' ) );
				Wf_Woocommerce_Packing_List::update_option( 'woocommerce_wf_packinglist_sender_email', get_option( 'admin_email' ) );
			}
		}
	}

	/**
	 *   @since 2.6.3
	 *   Secure directory with htaccess
	 */
	public static function secure_upload_dir() {
		global $wp_filesystem;

		// Initialize the WordPress filesystem
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$upload_dir = Wf_Woocommerce_Packing_List::get_temp_dir( 'path' );
		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$files_to_create = array(
			'.htaccess' => 'deny from all',
			'index.php' => '<?php // Silence is golden',
		);
		foreach ( $files_to_create as $file => $file_content ) {
			if ( ! file_exists( $upload_dir . '/' . $file ) ) {
				$wp_filesystem->put_contents( $upload_dir . '/' . $file, $file_content );
			}
		}

		// Auto-discover every document subdirectory (core + addon-created)
		// instead of hardcoding the document type list.
		$sub_paths = glob( $upload_dir . '/*', GLOB_ONLYDIR );
		if ( is_array( $sub_paths ) ) {
			foreach ( $sub_paths as $sub_path ) {
				foreach ( $files_to_create as $file => $file_content ) {
					if ( ! file_exists( $sub_path . '/' . $file ) ) {
						$wp_filesystem->put_contents( $sub_path . '/' . $file, $file_content );
					}
				}
			}
		}
	}

	/**
	 * Delete legacy predictable-name PDF files on plugin update.
	 *
	 * PDFs cached before 4.9.5 use sequential order IDs in their filenames
	 * (e.g. Invoice_1001.pdf), making them enumerable on non-Apache servers
	 * where .htaccess directives are not processed. Deleting them ensures no
	 * exploitable files remain after the update; they regenerate with hashed
	 * names on the next print or email send.
	 *
	 * @since 4.9.5
	 */
	public static function cleanup_legacy_pdf_files() {
		$upload_dir = Wf_Woocommerce_Packing_List::get_temp_dir( 'path' );
		if ( ! is_dir( $upload_dir ) ) {
			return;
		}
		// Auto-discover every document subdirectory so addon-installed
		// document types (proformainvoice, addresslabel, picklist, creditnote,
		// ubl, etc.) are cleaned alongside the core ones.
		$sub_paths = glob( $upload_dir . '/*', GLOB_ONLYDIR );
		if ( ! is_array( $sub_paths ) ) {
			return;
		}
		foreach ( $sub_paths as $sub_path ) {
			$files = glob( $sub_path . '/*.pdf' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	public static function install_tables() {
		global $wpdb;
		// install necessary tables
		// creating table for saving template data================
		$charset_collate = $wpdb->get_charset_collate();
		// $tb=Wf_Woocommerce_Packing_List::$template_data_tb;
		$tb         = 'wfpklist_template_data';
		$like       = '%' . $wpdb->prefix . $tb . '%';
		$table_name = $wpdb->prefix . $tb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin activation requires checking table existence
		if ( ! $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ), ARRAY_N ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin activation requires table creation
			$sql_settings = "CREATE TABLE IF NOT EXISTS `$table_name` (
			  `id_wfpklist_template_data` int(11) NOT NULL AUTO_INCREMENT,
			  `template_name` varchar(200) NOT NULL,
			  `template_html` text NOT NULL,
			  `template_from` varchar(200) NOT NULL,
              `is_dc_compatible` int(11) NOT NULL DEFAULT '0',
			  `is_active` int(11) NOT NULL DEFAULT '0',
			  `template_type` varchar(200) NOT NULL,
			  `created_at` int(11) NOT NULL DEFAULT '0',
			  `updated_at` int(11) NOT NULL DEFAULT '0',
			  PRIMARY KEY(`id_wfpklist_template_data`)
			) DEFAULT CHARSET=utf8;";
			dbDelta( $sql_settings );
		} else {
	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin activation requires checking column existence
			if ( ! $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}wfpklist_template_data` LIKE %s", 'is_dc_compatible' ), ARRAY_N ) ) {
	        	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin activation requires schema modification
				$wpdb->query( "ALTER TABLE `{$wpdb->prefix}wfpklist_template_data` ADD `is_dc_compatible` int(11) NOT NULL DEFAULT '0' AFTER `template_from`" );
			}
		}
		// creating table for saving template data================
	}

	/**
	 * Generate and persist the site-level PDF storage token.
	 * Called on activation; skipped if a valid token already exists.
	 *
	 * @since 4.9.5
	 * @return string The site token.
	 */
	public static function generate_site_token() {
		$token = get_option( 'wt_pklist_site_token', '' );
		if ( empty( $token ) || ! preg_match( '/^[a-zA-Z0-9_-]{32,}$/', $token ) ) {
			$token = wp_generate_password( 32, false );
			update_option( 'wt_pklist_site_token', $token, false );
		}
		return $token;
	}

	/**
	 * Move any flat files in each doc-type subdirectory into the token subdirectory.
	 * Runs on activation; safe to call repeatedly (skips files already in token dir or already migrated).
	 *
	 * @since 4.9.5
	 * @param string $token Site PDF token.
	 */
	public static function migrate_files_to_token_dir( $token ) {
		if ( empty( $token ) ) {
			return;
		}

		if ( ! class_exists( 'Wf_Woocommerce_Packing_List' ) ) {
			return;
		}

		$base_dir = Wf_Woocommerce_Packing_List::get_temp_dir( 'path' );
		if ( ! is_dir( $base_dir ) ) {
			return;
		}

		$skip_files = array( '.htaccess', 'index.php' );

		// Scan each immediate subdirectory (one per doc type)
		$doc_type_dirs = glob( trailingslashit( $base_dir ) . '*', GLOB_ONLYDIR );
		if ( empty( $doc_type_dirs ) ) {
			return;
		}

		foreach ( $doc_type_dirs as $doc_type_dir ) {
			self::migrate_flat_files_to_token_subdir( $doc_type_dir, $token, $skip_files );

			// UBL XML files live in {doc_type}/ubl/ and are written to {doc_type}/ubl/{token}/
			// by class-ubl-xml-file-handler.php. Legacy flat XMLs in {doc_type}/ubl/ need the same migration.
			$ubl_dir = trailingslashit( $doc_type_dir ) . 'ubl';
			if ( is_dir( $ubl_dir ) ) {
				self::migrate_flat_files_to_token_subdir( $ubl_dir, $token, $skip_files );
			}
		}
	}

	/**
	 * Move flat (non-directory, non-protected) files inside $dir into $dir/{token}/.
	 *
	 * @since 4.9.5
	 * @param string $dir        Absolute directory path to scan.
	 * @param string $token      Site PDF token.
	 * @param array  $skip_files Filenames to leave in place (e.g. .htaccess, index.php).
	 */
	private static function migrate_flat_files_to_token_subdir( $dir, $token, $skip_files ) {
		$entries = glob( trailingslashit( $dir ) . '*' );
		if ( empty( $entries ) ) {
			return;
		}

		$flat_files = array();
		foreach ( $entries as $entry ) {
			if ( is_file( $entry ) && ! in_array( basename( $entry ), $skip_files, true ) ) {
				$flat_files[] = $entry;
			}
		}

		if ( empty( $flat_files ) ) {
			return;
		}

		$token_dir = trailingslashit( $dir ) . $token;
		if ( ! is_dir( $token_dir ) ) {
			wp_mkdir_p( $token_dir );
		}
		Wf_Woocommerce_Packing_List::ensure_secure_subdir( $token_dir );

		foreach ( $flat_files as $file ) {
			$dest = trailingslashit( $token_dir ) . basename( $file );
			if ( ! file_exists( $dest ) ) {
				rename( $file, $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			}
		}
	}

	/**
	 * To save the details upon the first installation
	 *
	 * @since 4.2.0 - Added a flag to know if it is a new installation or update
	 * @return void
	 */
	public static function save_plugin_version() {
		if ( false === get_option( 'wt_pklist_new_install' ) ) {
			update_option( 'wt_pklist_new_install', 1 );
		}

		if ( false === get_option( 'wt_pklist_installation_date' ) ) {
			if ( get_option( 'wt_pklist_start_date' ) ) {
				$install_date = get_option( 'wt_pklist_start_date', time() );
			} else {
				$install_date = time();
			}
			update_option( 'wt_pklist_installation_date', $install_date );
		}

		if ( false === get_option( 'invoice_empty_count' ) ) {
			update_option( 'invoice_empty_count', 1 );
		}
	}
}
