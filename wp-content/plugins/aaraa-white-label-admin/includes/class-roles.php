<?php
/**
 * Role customisation: rename the WooCommerce "Shop manager" role to "Shop Owner"
 * and blank out the admin sidebar for that role.
 *
 * The role slug (shop_manager) and its capabilities are left untouched — only the
 * display name changes, and it changes at runtime (no database mutation), so it is
 * fully reversible and survives WooCommerce updates.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renames shop_manager and empties its admin menu.
 */
class Roles {

	/**
	 * The WooCommerce role slug we target.
	 */
	const ROLE = 'shop_manager';

	/**
	 * Customers page controller.
	 *
	 * @var Customers_Admin|null
	 */
	private $customers = null;

	/**
	 * Wallet page controller (menu callback).
	 *
	 * @var Wallet_Admin|null
	 */
	private $wallet = null;

	/**
	 * Delivery pages controller (menu callbacks).
	 *
	 * @var Delivery_Admin|null
	 */
	private $delivery = null;

	/**
	 * Daily delivery report controller (menu callback).
	 *
	 * @var Delivery_Report|null
	 */
	private $report = null;

	/**
	 * Subscription pause report controller (menu callback).
	 *
	 * @var Subscription_Pause_Report|null
	 */
	private $pausereport = null;

	/**
	 * Subscription resume report controller (menu callback).
	 *
	 * @var Subscription_Resume_Report|null
	 */
	private $resumereport = null;

	/**
	 * Application (WooCommerce) log viewer (menu callback).
	 *
	 * @var Application_Log|null
	 */
	private $applog = null;

	/**
	 * Audit logs controller (menu callback).
	 *
	 * @var Audit_Logs|null
	 */
	private $auditlogs = null;

	/**
	 * Mobile banners controller (menu callback).
	 *
	 * @var Mobile_Banner|null
	 */
	private $mbanner = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->customers = new Customers_Admin();
		$this->wallet    = new Wallet_Admin();
		$this->delivery  = new Delivery_Admin();
		$this->report    = new Delivery_Report();
		$this->pausereport = new Subscription_Pause_Report();
		$this->resumereport = new Subscription_Resume_Report();
		$this->applog       = new Application_Log();
		$this->auditlogs = new Audit_Logs();
		$this->mbanner   = new Mobile_Banner();
		$this->notify    = new Notifications_Admin();

		// Send Shop Owners to the dashboard on login. Runs regardless of the
		// menu/rename options, since it is about where login lands, not the UI.
		//
		// Priority 99 so we run after WCFM's own login_redirect (priority 50),
		// which otherwise sends shop_manager to /store-manager/. wcfm_login_redirect
		// is WCFM's dedicated final hook — it fires for both the wp-login and the
		// My Account login paths, so hooking it covers what the priority alone
		// might miss (e.g. woocommerce_login_redirect on the account page).
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 99, 3 );
		add_filter( 'wcfm_login_redirect', array( $this, 'wcfm_login_redirect' ), 99, 2 );

		if ( aaraa_get_option( 'rename_shop_manager', 1 ) ) {
			// Override the in-memory role name early, before any admin UI reads it.
			add_action( 'init', array( $this, 'rename_role' ), 20 );
			add_filter( 'editable_roles', array( $this, 'filter_editable_roles' ) );
		}

		if ( aaraa_get_option( 'empty_shop_owner_menu', 1 ) ) {
			// Run last so nothing re-adds menus after us.
			add_action( 'admin_menu', array( $this, 'empty_menu' ), 99999 );
			add_filter( 'admin_body_class', array( $this, 'body_class' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			// Re-point the "current menu" highlight at our sections. Their children
			// use foreign slugs (edit.php?post_type=product, wc-admin paths), so
			// WordPress would otherwise mark the native menu current and leave
			// AaraaKart / Analytics collapsed even on their own pages.
			// Late priority so we win over WooCommerce's own parent_file filter
			// (its HPOS Orders screen re-highlights the WooCommerce menu).
			add_filter( 'parent_file', array( $this, 'highlight_parent' ), 9999 );
			add_filter( 'submenu_file', array( $this, 'highlight_submenu' ), 9999 );
			// Process customer view/edit/delete/save before any output.
			add_action( 'admin_init', array( $this->customers, 'handle_actions' ) );
		}
	}

	/**
	 * Add a body class on Shop Owner admin pages so the sidebar CSS can target them.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		if ( ! $this->is_shop_owner() ) {
			return $classes;
		}
		$classes .= ' aaraa-shop-owner';

		// Mark which section owns the current page so the CSS can expand exactly
		// that one by id. This does not rely on WordPress' own current-menu
		// detection, which fails for our foreign-slug children (Products, Orders,
		// the Analytics reports) and left those sections collapsed on their pages.
		$section = $this->current_section();
		if ( $section ) {
			$classes .= ' aaraa-open-' . $section;
		}

		return $classes;
	}

	/**
	 * Ensure the admin stylesheet is loaded for Shop Owners (it carries the
	 * always-open submenu rules), even if the admin theme option is off.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( $this->is_shop_owner() ) {
			wp_enqueue_style( 'aaraa-admin', AARAA_WLA_URL . 'assets/css/admin.css', array(), aaraa_asset_ver( 'assets/css/admin.css' ) );
		}
	}

	/**
	 * The configured display name for the role.
	 *
	 * @return string
	 */
	private function label() {
		$label = (string) aaraa_get_option( 'shop_manager_label', 'Shop Owner' );
		return '' !== $label ? $label : 'Shop Owner';
	}

	/**
	 * Rename the role's display name in the live WP_Roles singleton.
	 *
	 * Sets both `roles[slug]['name']` (used by role dropdowns / get_editable_roles)
	 * and `role_names[slug]` (used by the Users list column). No DB write.
	 *
	 * @return void
	 */
	public function rename_role() {
		$roles = wp_roles();
		if ( ! isset( $roles->roles[ self::ROLE ] ) ) {
			return;
		}
		$label = $this->label();
		$roles->roles[ self::ROLE ]['name'] = $label;
		$roles->role_names[ self::ROLE ]    = $label;
	}

	/**
	 * Ensure the renamed label appears in the "editable roles" list too.
	 *
	 * @param array<string, array<string, mixed>> $roles Editable roles.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_editable_roles( $roles ) {
		if ( isset( $roles[ self::ROLE ] ) ) {
			$roles[ self::ROLE ]['name'] = $this->label();
		}
		return $roles;
	}

	/**
	 * Empty the admin sidebar for Shop Owner users (administrators unaffected).
	 *
	 * Extension points for adding links later:
	 *   - filter `aaraa_shop_owner_menu_keep` — return an array of menu slugs to KEEP.
	 *   - action `aaraa_shop_owner_menu` — fires after emptying; call add_menu_page()
	 *     / add_submenu_page() here to build the Shop Owner sidebar from scratch.
	 *
	 * @return void
	 */
	public function empty_menu() {
		if ( ! $this->is_shop_owner() ) {
			return;
		}

		global $menu, $submenu;

		$keep = (array) apply_filters( 'aaraa_shop_owner_menu_keep', array() );

		if ( empty( $keep ) ) {
			/*
			 * Clear only the top-level ($menu) so nothing renders in the sidebar.
			 * $submenu is deliberately KEPT: WordPress' user_can_access_admin_page()
			 * reads it to authorise ?page= plugin screens (e.g. the HPOS Orders page
			 * admin.php?page=wc-orders). Wiping it would make those links throw
			 * "Sorry, you are not allowed to access this page." Orphaned submenus do
			 * not render without a visible parent, so the sidebar still looks empty.
			 */
			$menu = array();
		} else {
			foreach ( (array) $menu as $index => $item ) {
				$slug = isset( $item[2] ) ? $item[2] : '';
				if ( ! in_array( $slug, $keep, true ) ) {
					unset( $menu[ $index ] );
				}
			}
		}

		$this->build_shop_owner_menu();

		/**
		 * Build the Shop Owner sidebar further. Register additional menu items here.
		 *
		 * @param array $keep Slugs preserved by the keep filter.
		 */
		do_action( 'aaraa_shop_owner_menu', $keep );
	}

	/**
	 * Register the branded Shop Owner sidebar.
	 *
	 *   Dashboard
	 *   Aaraa Customer 360
	 *       Customers            (custom page)
	 *   Analytics
	 *       Overview / Products / Revenue / Orders / …   (WooCommerce Analytics)
	 *   AaraaKart
	 *       Products             (WooCommerce)
	 *       Orders               (WooCommerce, HPOS-aware)
	 *       Subscriptions        (WooCommerce Subscriptions)
	 *       Coupons              (WooCommerce)
	 *
	 * @return void
	 */
	private function build_shop_owner_menu() {
		global $submenu;

		// Dashboard.
		add_menu_page(
			__( 'Dashboard', 'aaraa-white-label-admin' ),
			__( 'Dashboard', 'aaraa-white-label-admin' ),
			'read',
			'index.php',
			'',
			'dashicons-dashboard',
			2
		);

		// Section: Aaraa Customer 360 → Customers (custom page).
		add_menu_page(
			__( 'Aaraa Customer 360', 'aaraa-white-label-admin' ),
			__( 'Aaraa Customer 360', 'aaraa-white-label-admin' ),
			'read',
			'aaraa-customers',
			array( $this->customers, 'render_page' ),
			'dashicons-groups',
			3
		);
		// Rename the auto-created first submenu ("Aaraa Customer 360") to "Customers".
		if ( isset( $submenu['aaraa-customers'][0][0] ) ) {
			$submenu['aaraa-customers'][0][0] = __( 'Customers', 'aaraa-white-label-admin' );
		}

		// Section: WooCommerce-dependent menus below.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->build_analytics_menu();

		add_menu_page(
			__( 'AaraaKart', 'aaraa-white-label-admin' ),
			__( 'AaraaKart', 'aaraa-white-label-admin' ),
			'edit_products',
			'aaraa-kart',
			'',
			'dashicons-store',
			5
		);

		add_submenu_page( 'aaraa-kart', __( 'Products', 'aaraa-white-label-admin' ), __( 'Products', 'aaraa-white-label-admin' ), 'edit_products', 'edit.php?post_type=product' );

		// Rearrange Products — links to the Rearrange WooCommerce Products plugin's
		// sort screen. Shown only when that plugin is active. Placed directly under
		// Products.
		if ( defined( 'RWPP_LOCATION' ) ) {
			add_submenu_page( 'aaraa-kart', __( 'Rearrange Products', 'aaraa-white-label-admin' ), __( 'Rearrange Products', 'aaraa-white-label-admin' ), 'edit_products', 'admin.php?page=rwpp-page' );
		}

		add_submenu_page( 'aaraa-kart', __( 'Orders', 'aaraa-white-label-admin' ), __( 'Orders', 'aaraa-white-label-admin' ), 'edit_shop_orders', $this->orders_slug() );

		if ( post_type_exists( 'shop_subscription' ) ) {
			add_submenu_page( 'aaraa-kart', __( 'Subscriptions', 'aaraa-white-label-admin' ), __( 'Subscriptions', 'aaraa-white-label-admin' ), 'manage_woocommerce', 'edit.php?post_type=shop_subscription' );
		}

		add_submenu_page( 'aaraa-kart', __( 'Coupons', 'aaraa-white-label-admin' ), __( 'Coupons', 'aaraa-white-label-admin' ), 'manage_woocommerce', 'edit.php?post_type=shop_coupon' );

		add_submenu_page(
			'aaraa-kart',
			__( 'Wallet', 'aaraa-white-label-admin' ),
			__( 'Wallet', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Wallet_Admin::PAGE,
			array( $this->wallet, 'render_page' )
		);

		// WooCommerce product taxonomy + reviews links, placed just above the
		// Mobile Banner link (which stays last). Access is authorised because
		// empty_menu() keeps $submenu, so these foreign-slug screens resolve.
		add_submenu_page( 'aaraa-kart', __( 'Categories', 'aaraa-white-label-admin' ), __( 'Categories', 'aaraa-white-label-admin' ), 'manage_product_terms', 'edit-tags.php?taxonomy=product_cat&post_type=product' );
		add_submenu_page( 'aaraa-kart', __( 'Attributes', 'aaraa-white-label-admin' ), __( 'Attributes', 'aaraa-white-label-admin' ), 'manage_product_terms', 'edit.php?post_type=product&page=product_attributes' );
		if ( taxonomy_exists( 'product_brand' ) ) {
			add_submenu_page( 'aaraa-kart', __( 'Brands', 'aaraa-white-label-admin' ), __( 'Brands', 'aaraa-white-label-admin' ), 'manage_product_terms', 'edit-tags.php?taxonomy=product_brand&post_type=product' );
		}
		add_submenu_page( 'aaraa-kart', __( 'Tags', 'aaraa-white-label-admin' ), __( 'Tags', 'aaraa-white-label-admin' ), 'manage_product_terms', 'edit-tags.php?taxonomy=product_tag&post_type=product' );
		add_submenu_page( 'aaraa-kart', __( 'Reviews', 'aaraa-white-label-admin' ), __( 'Reviews', 'aaraa-white-label-admin' ), 'moderate_comments', 'edit.php?post_type=product&page=product-reviews' );

		// Mobile Banner — last link under AaraaKart.
		add_submenu_page(
			'aaraa-kart',
			__( 'Mobile Banner', 'aaraa-white-label-admin' ),
			__( 'Mobile Banner', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Mobile_Banner::PAGE,
			array( $this->mbanner, 'render_page' )
		);

		// Drop the auto-created duplicate submenu; the top-level "AaraaKart" then
		// links to its first real child (Products).
		remove_submenu_page( 'aaraa-kart', 'aaraa-kart' );

		// Users — a top-level heading that opens the standard users list. Only
		// shown to accounts that may actually list users, so a shop owner without
		// the capability is not given a link that dead-ends in "permission denied".
		if ( current_user_can( 'list_users' ) ) {
			add_menu_page(
				__( 'Users', 'aaraa-white-label-admin' ),
				__( 'Users', 'aaraa-white-label-admin' ),
				'list_users',
				'users.php',
				'',
				'dashicons-groups',
				5.5
			);
		}

		$this->build_delivery_menu();

		$this->build_notifications_menu();

		// Audit Logs (read-only view of the WP Activity Log store).
		add_menu_page(
			__( 'Audit Logs', 'aaraa-white-label-admin' ),
			__( 'Audit Logs', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Audit_Logs::PAGE,
			array( $this->auditlogs, 'render_page' ),
			'dashicons-list-view',
			7
		);

		// Application Log — branded viewer for the WooCommerce logs.
		add_menu_page(
			__( 'Application Log', 'aaraa-white-label-admin' ),
			__( 'Application Log', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Application_Log::PAGE,
			array( $this->applog, 'render_page' ),
			'dashicons-media-text',
			7.5
		);
	}

	/**
	 * Register the "AaraaDelivery" section.
	 *
	 *   AaraaDelivery
	 *       Delivery Hubs
	 *       Delivery Boys
	 *       Delivery Slots
	 *       Daily Delivery Report
	 *
	 * @return void
	 */
	private function build_delivery_menu() {
		add_menu_page(
			__( 'AaraaDelivery', 'aaraa-white-label-admin' ),
			__( 'AaraaDelivery', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			'aaraa-delivery',
			'',
			'dashicons-car',
			6
		);

		add_submenu_page(
			'aaraa-delivery',
			__( 'Delivery Hubs', 'aaraa-white-label-admin' ),
			__( 'Delivery Hubs', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Delivery_Admin::PAGE_HUBS,
			array( $this->delivery, 'render_hubs_page' )
		);
		add_submenu_page(
			'aaraa-delivery',
			__( 'Delivery Boys', 'aaraa-white-label-admin' ),
			__( 'Delivery Boys', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Delivery_Admin::PAGE_BOYS,
			array( $this->delivery, 'render_boys_page' )
		);
		add_submenu_page(
			'aaraa-delivery',
			__( 'Delivery Slots', 'aaraa-white-label-admin' ),
			__( 'Delivery Slots', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Delivery_Admin::PAGE_SLOTS,
			array( $this->delivery, 'render_slots_page' )
		);
		add_submenu_page(
			'aaraa-delivery',
			__( 'Daily Delivery Report', 'aaraa-white-label-admin' ),
			__( 'Daily Delivery Report', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Delivery_Report::PAGE,
			array( $this->report, 'render_page' )
		);
		add_submenu_page(
			'aaraa-delivery',
			__( 'Subscription Pause Reports', 'aaraa-white-label-admin' ),
			__( 'Subscription Pause Reports', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Subscription_Pause_Report::PAGE,
			array( $this->pausereport, 'render_page' )
		);
		add_submenu_page(
			'aaraa-delivery',
			__( 'Subscription Resume Reports', 'aaraa-white-label-admin' ),
			__( 'Subscription Resume Reports', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Subscription_Resume_Report::PAGE,
			array( $this->resumereport, 'render_page' )
		);

		// Top-level "AaraaDelivery" links to its first real child (Delivery Hubs).
		remove_submenu_page( 'aaraa-delivery', 'aaraa-delivery' );
	}

	/**
	 * Register the "AaraaNotifications" section.
	 *
	 *   AaraaNotifications
	 *       SMS
	 *       WhatsApp
	 *       E-Mail
	 *       In-app
	 *
	 * @return void
	 */
	private function build_notifications_menu() {
		add_menu_page(
			__( 'AaraaNotifications', 'aaraa-white-label-admin' ),
			__( 'AaraaNotifications', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Notifications_Admin::PARENT,
			'',
			'dashicons-megaphone',
			6.5
		);

		add_submenu_page(
			Notifications_Admin::PARENT,
			__( 'SMS', 'aaraa-white-label-admin' ),
			__( 'SMS', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Notifications_Admin::PAGE_SMS,
			array( $this->notify, 'render_sms_page' )
		);
		add_submenu_page(
			Notifications_Admin::PARENT,
			__( 'WhatsApp', 'aaraa-white-label-admin' ),
			__( 'WhatsApp', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Notifications_Admin::PAGE_WHATSAPP,
			array( $this->notify, 'render_whatsapp_page' )
		);
		add_submenu_page(
			Notifications_Admin::PARENT,
			__( 'E-Mail', 'aaraa-white-label-admin' ),
			__( 'E-Mail', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Notifications_Admin::PAGE_EMAIL,
			array( $this->notify, 'render_email_page' )
		);
		add_submenu_page(
			Notifications_Admin::PARENT,
			__( 'In-app', 'aaraa-white-label-admin' ),
			__( 'In-app', 'aaraa-white-label-admin' ),
			'manage_woocommerce',
			Notifications_Admin::PAGE_INAPP,
			array( $this->notify, 'render_inapp_page' )
		);

		// Top-level "AaraaNotifications" links to its first real child (SMS).
		remove_submenu_page( Notifications_Admin::PARENT, Notifications_Admin::PARENT );
	}

	/**
	 * Register the Analytics section with the full WooCommerce Analytics report set.
	 *
	 * All reports are the wc-admin SPA (admin.php?page=wc-admin&path=…). Access is
	 * governed by WooCommerce's own wc-admin registration (kept in $submenu), so these
	 * links only need to render; the capability check resolves against 'wc-admin'.
	 *
	 * @return void
	 */
	private function build_analytics_menu() {
		// Analytics requires the wc-admin (WooCommerce Admin) SPA.
		if ( ! function_exists( 'wc_admin_url' ) && ! class_exists( '\Automattic\WooCommerce\Admin\PageController' ) ) {
			return;
		}

		add_menu_page(
			__( 'Analytics', 'aaraa-white-label-admin' ),
			__( 'Analytics', 'aaraa-white-label-admin' ),
			'view_woocommerce_reports',
			'aaraa-analytics',
			'',
			'dashicons-chart-bar',
			4
		);

		$reports = array(
			'overview'   => __( 'Overview', 'aaraa-white-label-admin' ),
			'products'   => __( 'Products', 'aaraa-white-label-admin' ),
			'revenue'    => __( 'Revenue', 'aaraa-white-label-admin' ),
			'orders'     => __( 'Orders', 'aaraa-white-label-admin' ),
			'categories' => __( 'Categories', 'aaraa-white-label-admin' ),
			'coupons'    => __( 'Coupons', 'aaraa-white-label-admin' ),
		);

		foreach ( $reports as $path => $label ) {
			add_submenu_page(
				'aaraa-analytics',
				$label,
				$label,
				'view_woocommerce_reports',
				'admin.php?page=wc-admin&path=/analytics/' . $path
			);
		}

		// Customers report lives outside the /analytics/ path.
		add_submenu_page(
			'aaraa-analytics',
			__( 'Customers', 'aaraa-white-label-admin' ),
			__( 'Customers', 'aaraa-white-label-admin' ),
			'view_woocommerce_reports',
			'admin.php?page=wc-admin&path=/customers'
		);

		// Subscriptions reports use WooCommerce's legacy reports (wc-reports),
		// not the wc-admin analytics SPA, so this link points at that tab.
		if ( post_type_exists( 'shop_subscription' ) ) {
			add_submenu_page(
				'aaraa-analytics',
				__( 'Subscriptions', 'aaraa-white-label-admin' ),
				__( 'Subscriptions', 'aaraa-white-label-admin' ),
				'view_woocommerce_reports',
				'admin.php?page=wc-reports&tab=subscriptions'
			);
		}

		add_submenu_page(
			'aaraa-analytics',
			__( 'Settings', 'aaraa-white-label-admin' ),
			__( 'Settings', 'aaraa-white-label-admin' ),
			'view_woocommerce_reports',
			'admin.php?page=wc-admin&path=/analytics/settings'
		);

		// Top-level "Analytics" links to its first child (Overview).
		remove_submenu_page( 'aaraa-analytics', 'aaraa-analytics' );
	}

	/* --------------------------------------------------------------------- *
	 * Current-menu highlight.
	 * --------------------------------------------------------------------- */

	/**
	 * Which Aaraa section each foreign-slug child belongs to.
	 *
	 * Keyed by the exact slug passed to add_submenu_page, so the value matches
	 * what WordPress compares against when highlighting. Only the children whose
	 * slug points outside our section need listing; real sub-pages (Wallet, the
	 * delivery pages) already resolve to the right parent on their own.
	 *
	 * @return array<string, string>
	 */
	private function child_parent_map() {
		$map = array(
			'edit.php?post_type=product'           => 'aaraa-kart',
			'admin.php?page=rwpp-page'             => 'aaraa-kart',
			'edit-tags.php?post_type=product'      => 'aaraa-kart', // Categories / Tags / Brands (taxonomy dropped by current_menu_slug).
			'admin.php?page=product_attributes'    => 'aaraa-kart',
			'admin.php?page=product-reviews'       => 'aaraa-kart',
			$this->orders_slug()                   => 'aaraa-kart',
			'edit.php?post_type=shop_subscription' => 'aaraa-kart',
			'edit.php?post_type=shop_coupon'       => 'aaraa-kart',
			'admin.php?page=wc-admin&path=/customers'          => 'aaraa-analytics',
			'admin.php?page=wc-admin&path=/analytics/settings' => 'aaraa-analytics',
			'admin.php?page=wc-reports&tab=subscriptions'      => 'aaraa-analytics',
		);

		foreach ( array( 'overview', 'products', 'revenue', 'orders', 'variations', 'categories', 'coupons', 'taxes', 'downloads', 'stock' ) as $report ) {
			$map[ 'admin.php?page=wc-admin&path=/analytics/' . $report ] = 'aaraa-analytics';
		}

		return $map;
	}

	/**
	 * Every child slug mapped to its top-level section, including the real
	 * sub-pages (Wallet, the delivery pages) so the body-class expansion covers
	 * all sections uniformly, not only the foreign-slug ones.
	 *
	 * @return array<string, string>
	 */
	private function section_map() {
		$map = $this->child_parent_map();

		$map[ 'admin.php?page=' . Wallet_Admin::PAGE ]         = 'aaraa-kart';
		$map[ 'admin.php?page=' . Delivery_Admin::PAGE_HUBS ]  = 'aaraa-delivery';
		$map[ 'admin.php?page=' . Delivery_Admin::PAGE_BOYS ]  = 'aaraa-delivery';
		$map[ 'admin.php?page=' . Delivery_Admin::PAGE_SLOTS ] = 'aaraa-delivery';
		$map[ 'admin.php?page=' . Delivery_Report::PAGE ]      = 'aaraa-delivery';

		$map[ 'admin.php?page=' . Notifications_Admin::PAGE_SMS ]      = Notifications_Admin::PARENT;
		$map[ 'admin.php?page=' . Notifications_Admin::PAGE_WHATSAPP ] = Notifications_Admin::PARENT;
		$map[ 'admin.php?page=' . Notifications_Admin::PAGE_EMAIL ]    = Notifications_Admin::PARENT;
		$map[ 'admin.php?page=' . Notifications_Admin::PAGE_INAPP ]    = Notifications_Admin::PARENT;

		return $map;
	}

	/**
	 * The top-level section slug that owns the current page, or an empty string.
	 *
	 * @return string
	 */
	private function current_section() {
		$current = $this->current_menu_slug();
		if ( '' === $current ) {
			return '';
		}
		$map = $this->section_map();
		return isset( $map[ $current ] ) ? $map[ $current ] : '';
	}

	/**
	 * Rebuild the current request as the slug it was registered under.
	 *
	 * Mirrors how the children are registered so a match in child_parent_map is
	 * possible: page params keep any wc-admin path, plain admin pages become
	 * admin.php?page=…, and post-type screens become edit.php?post_type=….
	 *
	 * @return string
	 */
	private function current_menu_slug() {
		global $pagenow;

		// phpcs:disable WordPress.Security.NonceVerification -- read-only, admin context.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'wc-admin' === $page ) {
			$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
			return 'admin.php?page=wc-admin&path=' . $path;
		}
		// Legacy reports are one page with a tab per report, so the tab is part of
		// the slug the Subscriptions link was registered under.
		if ( 'wc-reports' === $page ) {
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
			return 'admin.php?page=wc-reports' . ( '' !== $tab ? '&tab=' . $tab : '' );
		}
		if ( '' !== $page ) {
			return 'admin.php?page=' . $page;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:enable
		if ( '' !== $post_type ) {
			return $pagenow . '?post_type=' . $post_type;
		}

		return '';
	}

	/**
	 * Force the top-level highlight onto the owning Aaraa section.
	 *
	 * @param string $parent_file WordPress-computed parent.
	 * @return string
	 */
	public function highlight_parent( $parent_file ) {
		if ( ! $this->is_shop_owner() ) {
			return $parent_file;
		}
		$current = $this->current_menu_slug();
		$map     = $this->child_parent_map();
		return ( $current && isset( $map[ $current ] ) ) ? $map[ $current ] : $parent_file;
	}

	/**
	 * Force the sub-item highlight onto the current child slug.
	 *
	 * @param string $submenu_file WordPress-computed submenu file.
	 * @return string
	 */
	public function highlight_submenu( $submenu_file ) {
		if ( ! $this->is_shop_owner() ) {
			return $submenu_file;
		}
		$current = $this->current_menu_slug();
		$map     = $this->child_parent_map();
		return ( $current && isset( $map[ $current ] ) ) ? $current : $submenu_file;
	}

	/**
	 * The Orders admin URL, HPOS-aware.
	 *
	 * @return string
	 */
	private function orders_slug() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return 'admin.php?page=wc-orders';
		}
		return 'edit.php?post_type=shop_order';
	}

	/**
	 * Land Shop Owners on the dashboard after login.
	 *
	 * Only overrides when the user is a Shop Owner and has not been sent somewhere
	 * specific already (e.g. following a "you must log in to view this page" link),
	 * so a deliberate redirect_to is still honoured.
	 *
	 * @param string           $redirect_to           Where WordPress plans to send them.
	 * @param string           $requested_redirect_to The requested redirect, if any.
	 * @param \WP_User|\WP_Error $user                The logged-in user or an error.
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		// Respect an explicit destination the user was heading to.
		if ( ! empty( $requested_redirect_to ) ) {
			return $redirect_to;
		}
		return $this->is_shop_owner_user( $user ) ? admin_url( 'index.php' ) : $redirect_to;
	}

	/**
	 * WCFM's own final login-redirect hook.
	 *
	 * WCFM runs at priority 50 on login_redirect and rewrites shop_manager to
	 * /store-manager/, then passes the result through this filter. Overriding it
	 * here is the reliable way to send Shop Owners to wp-admin instead, covering
	 * both the wp-login and My Account login paths WCFM handles.
	 *
	 * @param string   $redirect_to Where WCFM decided to send them.
	 * @param \WP_User $user        The logged-in user.
	 * @return string
	 */
	public function wcfm_login_redirect( $redirect_to, $user ) {
		return $this->is_shop_owner_user( $user ) ? admin_url( 'index.php' ) : $redirect_to;
	}

	/**
	 * Whether a given user object is a Shop Owner (and not an administrator).
	 *
	 * @param mixed $user A WP_User, or anything else (returns false).
	 * @return bool
	 */
	private function is_shop_owner_user( $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( self::ROLE, $roles, true ) && ! in_array( 'administrator', $roles, true );
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
		return in_array( self::ROLE, $roles, true );
	}
}
