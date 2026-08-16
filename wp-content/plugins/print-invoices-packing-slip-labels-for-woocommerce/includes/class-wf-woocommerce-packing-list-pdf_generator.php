<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * PDF generator
 *
 * @link
 * @since 2.5.0
 *
 * @package  Wf_Woocommerce_Packing_List
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Wf_Woocommerce_Packing_List_Pdf_generator {

	public static $pdf_libs        = array();
	public static $active_pdf_lib  = '';
	public static $default_pdf_lib = 'dompdf';

	public function __construct() {
	}

	/**
	 *   @since 2.5.0
	 *   @since 2.6.6 Added compatiblity to handle multiple PDF libraries
	 *   @since 2.7.6 If current active PDF library not found then automatically switch default one.
	 *   @param string $html HTML to convert to PDF
	 *   @param string $basedir Module name ($template_type)
	 *   @param string $name File name
	 *   @param string $action Print/Download/Save the file
	 */
	public static function generate_pdf( $html, $basedir, $name, $action = '' ) {
		self::$pdf_libs = Wf_Woocommerce_Packing_List::get_pdf_libraries();

		if ( ! is_array( self::$pdf_libs ) ) {
			return;
		}
		if ( 0 === count( self::$pdf_libs ) ) {
			return;
		}

		self::$active_pdf_lib = Wf_Woocommerce_Packing_List::get_option( 'active_pdf_library' );

		/* filter to alter active PDF lib */
		self::$active_pdf_lib = apply_filters( 'wt_pklist_alter_active_pdf_library', self::$active_pdf_lib, self::$pdf_libs, $basedir );

		if ( ! isset( self::$pdf_libs[ self::$active_pdf_lib ] ) ) {
			self::$active_pdf_lib = self::$default_pdf_lib;
		}
		$active_lib = self::$pdf_libs[ self::$active_pdf_lib ];

		if ( ! is_array( $active_lib ) ) {
			$active_lib = self::$pdf_libs[ self::$default_pdf_lib ];
		}

		$lib_file  = ( isset( $active_lib['file'] ) ? $active_lib['file'] : '' );
		$lib_class = ( isset( $active_lib['class'] ) ? $active_lib['class'] : '' );
		if ( '' === $lib_file || '' === $lib_class ) {
			return;
		}
		if ( ! file_exists( $lib_file ) ) {
			return;
		}

		include_once $lib_file;
		if ( ! class_exists( $lib_class ) ) {
			return;
		}

		$upload_loc = Wf_Woocommerce_Packing_List::get_temp_dir();
		$upload_dir = $upload_loc['path'];
		$upload_url = $upload_loc['url'];

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		// document type specific subfolder
		$upload_dir = $upload_dir . '/' . $basedir;
		$upload_url = $upload_url . '/' . $basedir;
		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		// Site-level token subdirectory (WPPS-483: unpredictable path on all web servers)
		$site_token  = Wf_Woocommerce_Packing_List::get_site_pdf_token();
		$upload_dir .= '/' . $site_token;
		$upload_url .= '/' . $site_token;
		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
			Wf_Woocommerce_Packing_List::ensure_secure_subdir( $upload_dir );
		}

		// if directory successfully created
		if ( is_dir( $upload_dir ) ) {

			$pdf_obj = new $lib_class(); /* initialize PDF library */

			$file_path  = $upload_dir . '/' . $name . '.pdf';
			$file_url   = $upload_url . '/' . $name . '.pdf';
			$is_preview = false;
			if ( 'download' === $action || 'preview' === $action ) {
				$is_preview = ( ( 'preview' === $action || isset( $_GET['debug'] ) ) ? true : false ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a safe use of isset.
			}

			$args = array();

			$pdf_obj->generate( $upload_dir, $html, $action, $is_preview, $file_path, $args );

			if ( 'download' === $action || 'preview' === $action ) {
				exit();
			} elseif ( 'preview_url' === $action ) {
				return $file_url;
			} else {
				return $file_path;
			}
		}
	}
}
