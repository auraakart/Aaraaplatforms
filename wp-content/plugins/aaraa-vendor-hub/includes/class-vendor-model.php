<?php
/**
 * `wp_vendors` table access: schema definition, migration, CRUD and the
 * app-config JSON assembly shared by the settings page and the REST API.
 *
 * Existing columns (id, subdomain, full_domain, db_host, db_port, db_name,
 * db_user, db_pass, status, created_at, updated_at) and existing rows are
 * never modified by this class — only the new app-config columns are read
 * or written.
 *
 * @package Aaraa\VendorHub
 */

namespace Aaraa\VendorHub;

defined( 'ABSPATH' ) || exit;

/**
 * `wp_vendors` data access.
 */
class Vendor_Model {

	/**
	 * Option flagging which migration version has been applied, so
	 * maybe_upgrade() only runs the ALTER TABLE once per version.
	 */
	const DB_VERSION_OPTION = 'aaraa_vh_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * The new JSON-column app-config sections. Each entry:
	 *   json_key  — the key this section is nested under in the API response.
	 *   label     — section heading on the settings page.
	 *   fields    — field_key => array( label, type ).
	 *
	 * Shared by the settings page (renders + sanitizes from this list) and
	 * the API (reads the same keys back out of the decoded JSON column), so
	 * the two can never drift apart.
	 *
	 * `build` and `banners` are handled separately (nested / repeating) —
	 * see Vendor_Settings_Page::render_build_fields() and
	 * ::render_banners_fields().
	 *
	 * @return array<string,array{json_key:string,label:string,fields:array<string,array{label:string,type:string}>}>
	 */
	public static function schema() {
		return array(
			'branding' => array(
				'json_key' => 'branding',
				'label'    => __( 'Branding', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'logoUrl'             => array( 'label' => __( 'Logo URL', 'aaraa-vendor-hub' ), 'type' => 'image' ),
					'logoDarkUrl'         => array( 'label' => __( 'Logo URL (dark)', 'aaraa-vendor-hub' ), 'type' => 'image' ),
					'splashLogoUrl'       => array( 'label' => __( 'Splash logo URL', 'aaraa-vendor-hub' ), 'type' => 'image' ),
					'loginLogoUrl'        => array( 'label' => __( 'Login logo URL', 'aaraa-vendor-hub' ), 'type' => 'image' ),
					'homeLogoUrl'         => array( 'label' => __( 'Home logo URL', 'aaraa-vendor-hub' ), 'type' => 'image' ),
					'successAnimationUrl' => array( 'label' => __( 'Success animation URL', 'aaraa-vendor-hub' ), 'type' => 'image' ),
					'placeholderImageUrl' => array( 'label' => __( 'Placeholder image URL', 'aaraa-vendor-hub' ), 'type' => 'image' ),
				),
			),
			'theme' => array(
				'json_key' => 'theme',
				'label'    => __( 'Theme Colors', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'primaryColor'       => array( 'label' => __( 'Primary color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'primaryDarkColor'   => array( 'label' => __( 'Primary dark color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'secondaryColor'     => array( 'label' => __( 'Secondary color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'backgroundColor'    => array( 'label' => __( 'Background color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'surfaceColor'       => array( 'label' => __( 'Surface color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'cardColor'          => array( 'label' => __( 'Card color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'textPrimaryColor'   => array( 'label' => __( 'Text primary color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'textSecondaryColor' => array( 'label' => __( 'Text secondary color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'buttonColor'        => array( 'label' => __( 'Button color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'buttonTextColor'    => array( 'label' => __( 'Button text color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'errorColor'         => array( 'label' => __( 'Error color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'successColor'       => array( 'label' => __( 'Success color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'warningColor'       => array( 'label' => __( 'Warning color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'borderColor'        => array( 'label' => __( 'Border color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
					'disabledColor'      => array( 'label' => __( 'Disabled color', 'aaraa-vendor-hub' ), 'type' => 'color' ),
				),
			),
			'api' => array(
				'json_key' => 'api',
				'label'    => __( 'API', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'baseUrl'        => array( 'label' => __( 'Base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'wcApiBaseUrl'   => array( 'label' => __( 'WooCommerce API base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'wpApiBaseUrl'   => array( 'label' => __( 'WordPress API base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'consumerKey'    => array( 'label' => __( 'Consumer key', 'aaraa-vendor-hub' ), 'type' => 'secret' ),
					'consumerSecret' => array( 'label' => __( 'Consumer secret', 'aaraa-vendor-hub' ), 'type' => 'secret' ),
				),
			),
			'wallet' => array(
				'json_key' => 'wallet',
				'label'    => __( 'Wallet', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'enabled'               => array( 'label' => __( 'Enabled', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'walletApiBaseUrl'      => array( 'label' => __( 'Wallet API base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'consumerKey'           => array( 'label' => __( 'Consumer key', 'aaraa-vendor-hub' ), 'type' => 'secret' ),
					'consumerSecret'        => array( 'label' => __( 'Consumer secret', 'aaraa-vendor-hub' ), 'type' => 'secret' ),
					'balanceEndpoint'       => array( 'label' => __( 'Balance endpoint', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'transactionsEndpoint'  => array( 'label' => __( 'Transactions endpoint', 'aaraa-vendor-hub' ), 'type' => 'text' ),
				),
			),
			'payment' => array(
				'json_key' => 'payment',
				'label'    => __( 'Payment', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'enabled'     => array( 'label' => __( 'Enabled', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'gateway'     => array( 'label' => __( 'Gateway', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'environment' => array( 'label' => __( 'Environment', 'aaraa-vendor-hub' ), 'type' => 'text' ),
				),
			),
			'maps' => array(
				'json_key' => 'maps',
				'label'    => __( 'Maps', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'placesApiKey' => array( 'label' => __( 'Places API key', 'aaraa-vendor-hub' ), 'type' => 'secret' ),
				),
			),
			'support' => array(
				'json_key' => 'support',
				'label'    => __( 'Support', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'email'              => array( 'label' => __( 'Email', 'aaraa-vendor-hub' ), 'type' => 'email' ),
					'phone'              => array( 'label' => __( 'Phone', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'customerCareNumber' => array( 'label' => __( 'Customer care number', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'whatsappNumber'     => array( 'label' => __( 'WhatsApp number', 'aaraa-vendor-hub' ), 'type' => 'text' ),
				),
			),
			'content' => array(
				'json_key' => 'content',
				'label'    => __( 'Content', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'welcomeTitle'       => array( 'label' => __( 'Welcome title', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'welcomeDescription' => array( 'label' => __( 'Welcome description', 'aaraa-vendor-hub' ), 'type' => 'textarea' ),
					'termsUrl'           => array( 'label' => __( 'Terms URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'privacyUrl'         => array( 'label' => __( 'Privacy URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'refundPolicyUrl'    => array( 'label' => __( 'Refund policy URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
				),
			),
			'features' => array(
				'json_key' => 'features',
				'label'    => __( 'Features', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'subscriptions'     => array( 'label' => __( 'Subscriptions', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'wallet'            => array( 'label' => __( 'Wallet', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'coupons'           => array( 'label' => __( 'Coupons', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'referral'          => array( 'label' => __( 'Referral', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'supportTickets'    => array( 'label' => __( 'Support tickets', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'notifications'     => array( 'label' => __( 'Notifications', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'deliveryCalendar'  => array( 'label' => __( 'Delivery calendar', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'scheduledDelivery' => array( 'label' => __( 'Scheduled delivery', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
				),
			),
			'notifications' => array(
				'json_key' => 'notifications',
				'label'    => __( 'Notifications', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'channelName' => array( 'label' => __( 'Channel name', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'topicPrefix' => array( 'label' => __( 'Topic prefix', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'guestTopic'  => array( 'label' => __( 'Guest topic', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'userTopic'   => array( 'label' => __( 'User topic', 'aaraa-vendor-hub' ), 'type' => 'text' ),
				),
			),
			'app_update' => array(
				'json_key' => 'appUpdate',
				'label'    => __( 'App Update', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'minimumAndroidVersion' => array( 'label' => __( 'Minimum Android version', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'minimumIosVersion'     => array( 'label' => __( 'Minimum iOS version', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'latestAndroidVersion'  => array( 'label' => __( 'Latest Android version', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'latestIosVersion'      => array( 'label' => __( 'Latest iOS version', 'aaraa-vendor-hub' ), 'type' => 'text' ),
					'forceUpdate'           => array( 'label' => __( 'Force update', 'aaraa-vendor-hub' ), 'type' => 'bool' ),
					'updateMessage'         => array( 'label' => __( 'Update message', 'aaraa-vendor-hub' ), 'type' => 'textarea' ),
					'androidStoreUrl'       => array( 'label' => __( 'Android store URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'iosStoreUrl'           => array( 'label' => __( 'iOS store URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
				),
			),
			'links' => array(
				'json_key' => 'links',
				'label'    => __( 'Links', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'website'   => array( 'label' => __( 'Website', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'instagram' => array( 'label' => __( 'Instagram', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'facebook'  => array( 'label' => __( 'Facebook', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'youtube'   => array( 'label' => __( 'YouTube', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'whatsapp'  => array( 'label' => __( 'WhatsApp number', 'aaraa-vendor-hub' ), 'type' => 'text' ),
				),
			),
			'deep_links' => array(
				'json_key' => 'deepLinks',
				'label'    => __( 'Deep Links', 'aaraa-vendor-hub' ),
				'fields'   => array(
					'productBaseUrl'      => array( 'label' => __( 'Product base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'categoryBaseUrl'     => array( 'label' => __( 'Category base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'orderBaseUrl'        => array( 'label' => __( 'Order base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
					'subscriptionBaseUrl' => array( 'label' => __( 'Subscription base URL', 'aaraa-vendor-hub' ), 'type' => 'url' ),
				),
			),
		);
	}

	/**
	 * Banner row field types — id/sortOrder are handled as int, isActive as
	 * bool, the rest as text/url.
	 *
	 * @return array<string,string> field => type.
	 */
	public static function banner_fields() {
		return array(
			'imageUrl'     => 'image',
			'title'        => 'text',
			'description'  => 'text',
			'actionType'   => 'text',
			'actionValue'  => 'text',
			'isActive'     => 'bool',
			'sortOrder'    => 'int',
		);
	}

	/* --------------------------------------------------------------------- *
	 * Table / migration.
	 * --------------------------------------------------------------------- */

	/**
	 * `wp_vendors` table name for this install's prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'vendors';
	}

	/**
	 * Whether the app-config columns already exist (checked before writing,
	 * so a save() never errors out just because the migration hasn't run).
	 *
	 * @return bool
	 */
	public static function is_migrated() {
		global $wpdb;
		$table = self::table();
		$col   = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'config_version' ) ); // phpcs:ignore WordPress.DB
		return null !== $col;
	}

	/**
	 * Run the same ADD COLUMN IF NOT EXISTS migration as wp_vendors_migration.sql.
	 * Idempotent — safe on every activation and every admin_init version check.
	 *
	 * @return void
	 */
	public static function migrate() {
		global $wpdb;
		$table = self::table();
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB
			return; // Not on the hub DB — nothing to migrate.
		}

		$columns = array(
			'brand_name'     => "VARCHAR(190) NULL COMMENT 'app-config: brand.brandName'",
			'app_name'       => "VARCHAR(190) NULL COMMENT 'app-config: brand.appName'",
			'environment'    => "VARCHAR(32) NULL COMMENT 'app-config: brand.environment'",
			'build'          => "JSON NULL COMMENT 'app-config: build'",
			'branding'       => "JSON NULL COMMENT 'app-config: branding'",
			'theme'          => "JSON NULL COMMENT 'app-config: theme'",
			'api'            => "JSON NULL COMMENT 'app-config: api'",
			'wallet'         => "JSON NULL COMMENT 'app-config: wallet'",
			'payment'        => "JSON NULL COMMENT 'app-config: payment'",
			'maps'           => "JSON NULL COMMENT 'app-config: maps'",
			'support'        => "JSON NULL COMMENT 'app-config: support'",
			'content'        => "JSON NULL COMMENT 'app-config: content'",
			'features'       => "JSON NULL COMMENT 'app-config: features'",
			'notifications'  => "JSON NULL COMMENT 'app-config: notifications'",
			'app_update'     => "JSON NULL COMMENT 'app-config: appUpdate'",
			'banners'        => "JSON NULL COMMENT 'app-config: banners'",
			'links'          => "JSON NULL COMMENT 'app-config: links'",
			'deep_links'     => "JSON NULL COMMENT 'app-config: deepLinks'",
			'config_version' => "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'app-config: configVersion'",
		);

		foreach ( $columns as $name => $def ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- $def is a fixed literal above, $name from the same fixed array; no user input.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS `{$name}` {$def}" );
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Re-run the migration once per version — covers an already-active install
	 * that only just received this plugin file update, same pattern as
	 * aaraa-white-label-admin's Plugin::maybe_upgrade().
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::migrate();
	}

	/* --------------------------------------------------------------------- *
	 * Reads.
	 * --------------------------------------------------------------------- */

	/**
	 * Every vendor row, existing columns only (list screen doesn't need the
	 * full JSON payload).
	 *
	 * @return array<int,object>
	 */
	public static function all() {
		global $wpdb;
		$table = self::table();
		return (array) $wpdb->get_results( "SELECT id, subdomain, full_domain, status, config_version, updated_at FROM {$table} ORDER BY subdomain ASC" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * One vendor row (all columns) by id.
	 *
	 * @param int $id Vendor id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * One vendor row by subdomain (brand_id) — used by the API.
	 *
	 * @param string $subdomain Subdomain / brand id.
	 * @return object|null
	 */
	public static function get_by_subdomain( $subdomain ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE subdomain = %s", $subdomain ) ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Whether a subdomain is already taken.
	 *
	 * @param string $subdomain Subdomain to check.
	 * @return bool
	 */
	public static function subdomain_exists( $subdomain ) {
		global $wpdb;
		$table = self::table();
		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE subdomain = %s", $subdomain ) ); // phpcs:ignore WordPress.DB
	}

	/* --------------------------------------------------------------------- *
	 * Vendor row creation — the ORIGINAL wp_vendors columns only (subdomain,
	 * full_domain, db connection, status). App-config columns are left at
	 * their column defaults (NULL / config_version=1) and filled in
	 * afterwards from the Edit App Config screen. Existing rows are never
	 * touched by this — it only ever INSERTs a brand new row.
	 * --------------------------------------------------------------------- */

	/**
	 * Create a new vendor row.
	 *
	 * @param array{subdomain:string,full_domain:string,db_host:string,db_port:int,db_name:string,db_user:string,db_pass:string,status:string} $data Sanitized fields.
	 * @return int|\WP_Error New vendor id, or WP_Error on failure / duplicate subdomain.
	 */
	public static function create( $data ) {
		global $wpdb;

		if ( '' === $data['subdomain'] ) {
			return new \WP_Error( 'missing_subdomain', __( 'Subdomain is required.', 'aaraa-vendor-hub' ) );
		}
		if ( self::subdomain_exists( $data['subdomain'] ) ) {
			return new \WP_Error( 'duplicate_subdomain', __( 'That subdomain is already in use.', 'aaraa-vendor-hub' ) );
		}

		$table = self::table();
		$now   = current_time( 'mysql', true );
		$ok    = $wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'subdomain'   => $data['subdomain'],
				'full_domain' => $data['full_domain'],
				'db_host'     => $data['db_host'],
				'db_port'     => $data['db_port'],
				'db_name'     => $data['db_name'],
				'db_user'     => $data['db_user'],
				'db_pass'     => $data['db_pass'],
				'status'      => $data['status'],
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new \WP_Error( 'insert_failed', __( 'Could not create the vendor row.', 'aaraa-vendor-hub' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/* --------------------------------------------------------------------- *
	 * Writes — app-config columns only. subdomain, full_domain, the db_
	 * connection columns and status are never touched here (no form field
	 * writes to them).
	 * --------------------------------------------------------------------- */

	/**
	 * Save the app-config columns for one vendor from sanitized section data.
	 *
	 * @param int                  $id       Vendor id.
	 * @param array<string,mixed>  $sections Section key (schema()/'build'/'banners') => array|null.
	 * @param string               $brand_name  Scalar field.
	 * @param string               $app_name    Scalar field.
	 * @param string               $environment Scalar field.
	 * @return bool
	 */
	public static function save( $id, $sections, $brand_name, $app_name, $environment ) {
		global $wpdb;
		$table = self::table();

		$data   = array(
			'brand_name'  => $brand_name,
			'app_name'    => $app_name,
			'environment' => $environment,
		);
		$format = array( '%s', '%s', '%s' );

		foreach ( self::schema() as $key => $section ) {
			$data[ $key ] = wp_json_encode( isset( $sections[ $key ] ) ? $sections[ $key ] : new \stdClass() );
			$format[]     = '%s';
		}
		$data['build']   = wp_json_encode( isset( $sections['build'] ) ? $sections['build'] : new \stdClass() );
		$format[]        = '%s';
		$data['banners'] = wp_json_encode( isset( $sections['banners'] ) ? array_values( $sections['banners'] ) : array() );
		$format[]        = '%s';

		// Bump so a client watching configVersion can tell something changed.
		$current            = self::get( $id );
		$data['config_version'] = $current ? ( (int) $current->config_version + 1 ) : 1;
		$format[]               = '%d';

		$data['updated_at'] = current_time( 'mysql', true );
		$format[]            = '%s';

		return false !== $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	/* --------------------------------------------------------------------- *
	 * app-config JSON assembly (shared by the settings page pre-fill and the
	 * REST API response).
	 * --------------------------------------------------------------------- */

	/**
	 * Decode a JSON column to an associative array, defensively.
	 *
	 * @param string|null $raw Raw column value.
	 * @return array<string,mixed>
	 */
	public static function decode( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Assemble the full app-config response for one vendor row, in the exact
	 * shape GET /wp-json/aaraakart/v1/app-config returns.
	 *
	 * @param object $row Vendor row (from get() / get_by_subdomain()).
	 * @return array<string,mixed>
	 */
	public static function to_app_config( $row ) {
		$build = self::decode( $row->build );
		$out   = array(
			'brand' => array(
				'brandId'     => $row->subdomain,
				'brandName'   => $row->brand_name ? $row->brand_name : $row->subdomain,
				'appName'     => $row->app_name ? $row->app_name : $row->subdomain,
				'status'      => $row->status,
				'environment' => $row->environment ? $row->environment : 'production',
			),
			'build' => array(
				'android' => array(
					'packageName'   => isset( $build['android']['packageName'] ) ? $build['android']['packageName'] : '',
					'applicationId' => isset( $build['android']['applicationId'] ) ? $build['android']['applicationId'] : '',
				),
				'ios' => array(
					'bundleId' => isset( $build['ios']['bundleId'] ) ? $build['ios']['bundleId'] : '',
				),
			),
		);

		foreach ( self::schema() as $key => $section ) {
			$decoded = self::decode( $row->{$key} );
			$values  = array();
			foreach ( $section['fields'] as $field => $meta ) {
				$values[ $field ] = isset( $decoded[ $field ] ) ? $decoded[ $field ] : self::empty_value( $meta['type'] );
			}
			$out[ $section['json_key'] ] = $values;
		}

		$out['banners']      = array_values( self::decode( $row->banners ) );
		$out['configVersion'] = (int) $row->config_version;
		$out['updatedAt']     = self::iso8601( $row->updated_at );

		return $out;
	}

	/**
	 * A sane empty value per field type, so a not-yet-configured field
	 * returns "" / false / 0 rather than being silently omitted.
	 *
	 * @param string $type Field type.
	 * @return mixed
	 */
	private static function empty_value( $type ) {
		return 'bool' === $type ? false : '';
	}

	/**
	 * MySQL "Y-m-d H:i:s" (stored UTC — wp_vendors' own dump sets
	 * time_zone = "+00:00") to an ISO-8601 UTC string.
	 *
	 * @param string $mysql_datetime Stored datetime.
	 * @return string
	 */
	private static function iso8601( $mysql_datetime ) {
		$ts = $mysql_datetime ? strtotime( $mysql_datetime . ' UTC' ) : false;
		return $ts ? gmdate( 'Y-m-d\TH:i:s\Z', $ts ) : '';
	}
}
