<?php
/**
 * Admin bar customisation.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Aaraa logo first; strips WP logo, search, comments, "+ New" and "Howdy,".
 */
class Admin_Bar {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Priority 1 renders the brand before core nodes (site name is 30).
		add_action( 'admin_bar_menu', array( $this, 'add_brand' ), 1 );

		// Quick-jump links on the right side, just before the username.
		add_action( 'admin_bar_menu', array( $this, 'add_jump_links' ), 8 );

		// Remove nodes late, after everything (core + plugins) has registered.
		add_action( 'admin_bar_menu', array( $this, 'cleanup_nodes' ), 99999 );

		// Drop the "Howdy," greeting at the translation source — reliable no matter
		// which plugin/theme (re)builds the account node.
		add_filter( 'gettext', array( $this, 'remove_howdy' ), 10, 3 );

		add_action( 'wp_before_admin_bar_render', array( $this, 'render_styles' ) );
	}

	/**
	 * Add the branded Aaraa node as the first item in the bar.
	 *
	 * @param \WP_Admin_Bar $bar The admin bar instance.
	 * @return void
	 */
	public function add_brand( $bar ) {
		$logo_id  = (int) aaraa_get_option( 'admin_logo_id', 0 );
		$logo_src = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
		if ( ! $logo_src ) {
			$logo_src = AARAA_WLA_URL . 'assets/images/aaraa-platforms-web.png';
		}

		$title = sprintf(
			'<span class="ab-item aaraa-ab-brand"><img src="%1$s" alt="%2$s" class="aaraa-ab-logo" /></span>',
			esc_url( $logo_src ),
			esc_attr__( 'Aaraa', 'aaraa-white-label-admin' )
		);

		$bar->add_node(
			array(
				'id'    => 'aaraa-brand',
				'title' => $title,
				'href'  => admin_url(),
				'meta'  => array( 'title' => __( 'Aaraa Dashboard', 'aaraa-white-label-admin' ) ),
			)
		);
	}

	/**
	 * Add "Go to Marketplace" and "Go to AdminPro" links on the right of the bar,
	 * immediately before the username / account node.
	 *
	 * Both live in the 'top-secondary' group (right side). They are added after the
	 * account node is created (priority 8 > core's 0), so they render to its left.
	 * AdminPro is built from site_url() so it always resolves to the real /wp-admin/,
	 * even when the plugin's URL rewriter relocates admin_url().
	 *
	 * @param \WP_Admin_Bar $bar The admin bar instance.
	 * @return void
	 */
	public function add_jump_links( $bar ) {
		// Added first → floats furthest right of the two → appears left of AdminPro.
		$bar->add_node(
			array(
				'id'     => 'aaraa-go-marketplace',
				'parent' => 'top-secondary',
				'title'  => __( 'Go to Marketplace', 'aaraa-white-label-admin' ),
				'href'   => home_url( '/store-manager/' ),
				'meta'   => array( 'title' => __( 'Open the vendor marketplace dashboard', 'aaraa-white-label-admin' ) ),
			)
		);

		$bar->add_node(
			array(
				'id'     => 'aaraa-go-adminpro',
				'parent' => 'top-secondary',
				'title'  => __( 'Go to AdminPro', 'aaraa-white-label-admin' ),
				'href'   => site_url( '/wp-admin/' ),
				'meta'   => array( 'title' => __( 'Open the WordPress admin', 'aaraa-white-label-admin' ) ),
			)
		);
	}

	/**
	 * Remove the WP logo, search (Ctrl+K), comments and "+ New" nodes.
	 *
	 * @param \WP_Admin_Bar $bar The admin bar instance.
	 * @return void
	 */
	public function cleanup_nodes( $bar ) {
		if ( aaraa_get_option( 'remove_wp_logo', 1 ) ) {
			$bar->remove_node( 'wp-logo' );
			$bar->remove_node( 'wp-logo-external' );
		}

		// Known core nodes.
		$bar->remove_node( 'search' );        // core front-end search.
		$bar->remove_node( 'comments' );      // comments bubble.
		$bar->remove_node( 'new-content' );   // "+ New".

		// Shop Owners: hide theme/appearance shortcuts they should not manage —
		// Customize, Edit Page/Post, Widgets and Menus.
		if ( $this->is_shop_owner() ) {
			$bar->remove_node( 'customize' );     // Appearance → Customize.
			$bar->remove_node( 'edit' );          // "Edit Page" / "Edit Post".
			$bar->remove_node( 'widgets' );       // Appearance → Widgets.
			$bar->remove_node( 'menus' );         // Appearance → Menus.
		}

		// The "Ctrl+K" search is plugin-provided (unknown id) — remove any
		// top-level node whose text contains "Ctrl+K".
		foreach ( (array) $bar->get_nodes() as $node_id => $node ) {
			if ( ! empty( $node->parent ) ) {
				continue; // only top-level nodes.
			}
			$plain = isset( $node->title ) ? trim( wp_strip_all_tags( (string) $node->title ) ) : '';
			if ( '' !== $plain && ( false !== stripos( $plain, 'ctrl+k' ) || false !== stripos( $plain, 'ctrl + k' ) ) ) {
				$bar->remove_node( $node_id );
			}
		}
	}

	/**
	 * Whether the current user is a Shop Owner (and not an administrator).
	 *
	 * @return bool
	 */
	private function is_shop_owner() {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$roles = (array) $user->roles;
		if ( in_array( 'administrator', $roles, true ) ) {
			return false;
		}
		return in_array( Roles::ROLE, $roles, true );
	}

	/**
	 * Replace the "Howdy, %s" greeting with just the name.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function remove_howdy( $translation, $text, $domain ) {
		if ( 'default' === $domain && 'Howdy, %s' === $text ) {
			return '%s';
		}
		return $translation;
	}

	/**
	 * Inline CSS to size the brand logo.
	 *
	 * @return void
	 */
	public function render_styles() {
		?>
		<style id="aaraa-adminbar-css">
			#wpadminbar #wp-admin-bar-aaraa-brand > .ab-item { padding: 0 12px; }
			#wpadminbar .aaraa-ab-logo { height: 22px; width: auto; margin-top: 5px; vertical-align: top; }

			/* Keep the jump links visible on mobile. Core hides every top-secondary
			 * item below 782px except the account and toggle, which drops these two. */
			@media screen and (max-width: 782px) {
				#wpadminbar li#wp-admin-bar-aaraa-go-marketplace,
				#wpadminbar li#wp-admin-bar-aaraa-go-adminpro {
					display: block !important;
				}
				#wpadminbar li#wp-admin-bar-aaraa-go-marketplace > .ab-item,
				#wpadminbar li#wp-admin-bar-aaraa-go-adminpro > .ab-item {
					height: 46px;
					line-height: 46px;
					padding: 0 10px;
					font-size: 13px;
				}
			}
		</style>
		<?php
	}
}
