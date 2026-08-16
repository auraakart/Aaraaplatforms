<?php
/**
 * Dashboard: rename the menu, remove the default widgets, and render an
 * Aaraa card overview (Total Customers, Total Active Subscriptions, Total Orders).
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the default dashboard content with a branded card overview.
 */
class Dashboard {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'rename_dashboard' ), 999 );
		add_action( 'wp_dashboard_setup', array( $this, 'setup_dashboard' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		// Hide the "Welcome" panel that sits above the widgets.
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	/**
	 * Rename the "Dashboard" top-level and submenu labels.
	 *
	 * @return void
	 */
	public function rename_dashboard() {
		global $menu, $submenu;

		$title = (string) aaraa_get_option( 'dashboard_title', 'Aaraa Dashboard' );
		if ( '' === $title ) {
			return;
		}

		if ( is_array( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[2] ) && 'index.php' === $item[2] ) {
					$menu[ $key ][0] = esc_html( $title );
				}
			}
		}
		if ( isset( $submenu['index.php'][0][0] ) ) {
			$submenu['index.php'][0][0] = esc_html( $title );
		}
	}

	/**
	 * Enqueue the dashboard styles (only on the dashboard screen).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'aaraa-admin', AARAA_WLA_URL . 'assets/css/admin.css', array(), aaraa_asset_ver( 'assets/css/admin.css' ) );
	}

	/**
	 * Remove every default dashboard widget and register the Aaraa overview.
	 *
	 * @return void
	 */
	public function setup_dashboard() {
		global $wp_meta_boxes;

		// Remove all existing dashboard widgets (core + other plugins).
		$wp_meta_boxes['dashboard'] = array();

		wp_add_dashboard_widget(
			'aaraa_overview',
			esc_html( (string) aaraa_get_option( 'welcome_title', 'Welcome to Aaraa Platforms' ) ),
			array( $this, 'render_overview' )
		);
	}

	/**
	 * Render the overview: subtitle + three metric cards.
	 *
	 * @return void
	 */
	public function render_overview() {
		$stats    = $this->get_stats();
		$subtitle = (string) aaraa_get_option( 'welcome_subtitle', 'Vibrant E-Commerce | Seamless App Platform' );

		$cards = array(
			array(
				'label' => __( 'Total Customers', 'aaraa-white-label-admin' ),
				'value' => number_format_i18n( $stats['customers'] ),
				'icon'  => 'dashicons-groups',
			),
			array(
				'label' => __( 'Total Active Subscriptions', 'aaraa-white-label-admin' ),
				'value' => number_format_i18n( $stats['subscriptions'] ),
				'icon'  => 'dashicons-update',
			),
			array(
				'label' => __( 'Total Orders', 'aaraa-white-label-admin' ),
				'value' => number_format_i18n( $stats['orders'] ),
				'icon'  => 'dashicons-cart',
			),
		);
		?>
		<div class="aaraa-welcome">
			<?php if ( '' !== $subtitle ) : ?>
				<p class="aaraa-welcome__subtitle"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>

			<div class="aaraa-stats aaraa-stats--cards">
				<?php foreach ( $cards as $card ) : ?>
					<div class="aaraa-stat">
						<span class="aaraa-stat__icon dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
						<span class="aaraa-stat__value"><?php echo esc_html( $card['value'] ); ?></span>
						<span class="aaraa-stat__label"><?php echo esc_html( $card['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Gather the three metrics, cached for 10 minutes. All queries are lightweight
	 * (counts only) and HPOS-safe.
	 *
	 * @return array{customers:int,subscriptions:int,orders:int}
	 */
	private function get_stats() {
		$cached = get_transient( 'aaraa_dashboard_stats' );
		if ( is_array( $cached ) && isset( $cached['customers'], $cached['subscriptions'], $cached['orders'] ) ) {
			return $cached;
		}

		$stats = array(
			'customers'     => $this->count_customers(),
			'subscriptions' => $this->count_active_subscriptions(),
			'orders'        => $this->count_orders(),
		);

		set_transient( 'aaraa_dashboard_stats', $stats, 10 * MINUTE_IN_SECONDS );
		return $stats;
	}

	/**
	 * Count users with the customer role.
	 *
	 * @return int
	 */
	private function count_customers() {
		$roles = count_users();
		return isset( $roles['avail_roles']['customer'] ) ? (int) $roles['avail_roles']['customer'] : 0;
	}

	/**
	 * Count active subscriptions.
	 *
	 * Subscriptions are the shop_subscription CPT; wp_count_posts() gives the
	 * per-status counts from an indexed query.
	 *
	 * @return int
	 */
	private function count_active_subscriptions() {
		if ( ! post_type_exists( 'shop_subscription' ) ) {
			return 0;
		}
		$counts = wp_count_posts( 'shop_subscription' );
		return isset( $counts->{'wc-active'} ) ? (int) $counts->{'wc-active'} : 0;
	}

	/**
	 * Count all orders (any status), ids-only and paginated — no objects hydrated.
	 *
	 * @return int
	 */
	private function count_orders() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		$result = wc_get_orders(
			array(
				'status'   => array_keys( wc_get_order_statuses() ),
				'limit'    => 1,
				'return'   => 'ids',
				'paginate' => true,
			)
		);
		return is_object( $result ) && isset( $result->total ) ? (int) $result->total : 0;
	}
}
