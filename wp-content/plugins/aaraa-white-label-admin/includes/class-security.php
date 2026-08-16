<?php
/**
 * Security hardening toggles.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Version hiding, XML-RPC control and file-editor lockdown.
 */
class Security {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( aaraa_get_option( 'hide_wp_version', 1 ) ) {
			add_filter( 'the_generator', '__return_empty_string' );
			remove_action( 'wp_head', 'wp_generator' );
			add_action( 'init', array( $this, 'strip_version_query' ) );
		}

		if ( aaraa_get_option( 'disable_xmlrpc', 1 ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
			add_filter( 'xmlrpc_methods', array( $this, 'strip_pingback_methods' ) );
		}

		if ( aaraa_get_option( 'disable_file_editor', 1 ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
	}

	/**
	 * Remove the ?ver= WordPress version from enqueued asset URLs.
	 *
	 * @return void
	 */
	public function strip_version_query() {
		add_filter( 'style_loader_src', array( $this, 'remove_version_arg' ), 9999 );
		add_filter( 'script_loader_src', array( $this, 'remove_version_arg' ), 9999 );
	}

	/**
	 * Strip a version query arg that equals the current WP version.
	 *
	 * @param string $src Asset URL.
	 * @return string
	 */
	public function remove_version_arg( $src ) {
		global $wp_version;
		if ( $src && false !== strpos( $src, 'ver=' . $wp_version ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Remove the X-Pingback header.
	 *
	 * @param array<string, string> $headers Response headers.
	 * @return array<string, string>
	 */
	public function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Remove pingback XML-RPC methods.
	 *
	 * @param array<string, mixed> $methods XML-RPC methods.
	 * @return array<string, mixed>
	 */
	public function strip_pingback_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}
}
