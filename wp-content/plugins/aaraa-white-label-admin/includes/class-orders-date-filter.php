<?php
/**
 * Adds a date-range (From / To) filter to the HPOS Orders and Subscriptions
 * admin list tables, alongside WooCommerce's built-in month dropdown.
 *
 * WooCommerce only ships a month-granularity filter (?m=YYYYMM). This module
 * adds two <input type="date"> fields and translates them into the
 * `date_created` query arg using WC's documented shorthand:
 *   - both dates -> "YYYY-MM-DD...YYYY-MM-DD" (inclusive of the whole "to" day)
 *   - from only  -> ">=YYYY-MM-DD"
 *   - to only    -> "<=YYYY-MM-DD"
 *
 * Works on:
 *   - wp-admin/admin.php?page=wc-orders                    (shop_order)
 *   - wp-admin/admin.php?page=wc-orders--shop_subscription (shop_subscription)
 * because both use the same HPOS ListTable and hooks.
 *
 * @package Aaraa\Admin
 */

namespace Aaraa\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Date-range filter for the order/subscription list tables.
 */
class Orders_Date_Filter {

	const FROM     = 'order_date_from';
	const TO       = 'order_date_to';
	const PER_PAGE = 'aaraa_per_page';
	const CAT      = 'aaraa_cat';

	/**
	 * Allowed "per page" choices for the inline selector.
	 *
	 * @return int[]
	 */
	private function per_page_choices() {
		return array( 20, 50, 100, 200, 500 );
	}

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		// Render the date inputs + per-page selector inside the filter row (top only).
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'render_inputs' ), 5, 2 );
		// Translate the date inputs into a date_created query arg (runs after set_date_args()).
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'apply_filter' ) );
		// Drive the rows-per-page from the inline selector, for both order types.
		add_filter( 'edit_shop_order_per_page', array( $this, 'override_per_page' ) );
		add_filter( 'edit_shop_subscription_per_page', array( $this, 'override_per_page' ) );
		// Tidy the filter bar (native + custom controls) on the list screens.
		add_action( 'admin_head', array( $this, 'filter_bar_styles' ) );
	}

	/**
	 * Scoped CSS to align the Orders / Subscriptions filter bar into a clean,
	 * evenly-spaced row instead of a congested wrap.
	 *
	 * @return void
	 */
	public function filter_bar_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders--shop_subscription' ), true ) ) {
			return;
		}
		?>
		<style id="aaraa-filter-bar">
			/* Lay the filter controls out on a tidy, wrapping row. */
			.tablenav.top .actions {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 8px 10px;
				float: none;
				padding: 2px 0;
			}
			/* Uniform control height + alignment across native and custom fields. */
			.tablenav.top .actions select,
			.tablenav.top .actions input[type="text"],
			.tablenav.top .actions input[type="search"],
			.tablenav.top .actions input[type="date"] {
				height: 32px;
				line-height: normal;
				vertical-align: middle;
				margin: 0;
				box-sizing: border-box;
			}
			.tablenav.top .actions .button {
				height: 32px;
				line-height: 30px;
				margin: 0;
			}
			/* Group the custom date-range and per-page controls into pill-like blocks. */
			.tablenav.top .actions .aaraa-date-range-filter,
			.tablenav.top .actions .aaraa-per-page-filter {
				height: 34px;
				margin: 0;
				padding: 0 8px;
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 6px;
			}
			.tablenav.top .actions .aaraa-date-range-filter label,
			.tablenav.top .actions .aaraa-per-page-filter label {
				font-weight: 600;
				white-space: nowrap;
			}
			.tablenav.top .actions .aaraa-date-range-filter input[type="date"] {
				border: 1px solid #dcdcde;
				border-radius: 4px;
			}
			/* Keep the bulk-actions row aligned the same way. */
			.tablenav.top .bulkactions {
				display: inline-flex;
				align-items: center;
				gap: 8px;
			}
			/* Select2 (registered-customer) shouldn't stretch the row. */
			.tablenav.top .actions .select2-container {
				vertical-align: middle;
			}
		</style>
		<?php
	}

	/**
	 * Override the list-table rows-per-page from the inline selector.
	 *
	 * @param int $per_page Current per-page value.
	 * @return int
	 */
	public function override_per_page( $per_page ) {
		$req = isset( $_GET[ self::PER_PAGE ] ) ? (int) $_GET[ self::PER_PAGE ] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $req, $this->per_page_choices(), true ) ? $req : $per_page;
	}

	/**
	 * Output the From / To date inputs.
	 *
	 * @param string $order_type Current order type (unused; hook covers all types).
	 * @param string $which      'top' or 'bottom' tablenav.
	 * @return void
	 */
	public function render_inputs( $order_type = '', $which = 'top' ) {
		if ( 'bottom' === $which ) {
			return; // Filter button only exists on the top row.
		}

		$from = $this->clean_date( isset( $_GET[ self::FROM ] ) ? wp_unslash( $_GET[ self::FROM ] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = $this->clean_date( isset( $_GET[ self::TO ] ) ? wp_unslash( $_GET[ self::TO ] ) : '' );     // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// No `max` cap — future-dated orders/subscriptions must be filterable too.
		echo '<span class="aaraa-date-range-filter" style="display:inline-flex;align-items:center;gap:4px;margin:0 6px;vertical-align:middle;">';
		echo '<label style="font-size:12px;color:#50575e;">' . esc_html__( 'From', 'aaraa-white-label-admin' ) . '</label>';
		printf(
			'<input type="date" name="%1$s" value="%2$s" style="height:32px;line-height:1;padding:0 6px;" />',
			esc_attr( self::FROM ),
			esc_attr( $from )
		);
		echo '<label style="font-size:12px;color:#50575e;">' . esc_html__( 'To', 'aaraa-white-label-admin' ) . '</label>';
		printf(
			'<input type="date" name="%1$s" value="%2$s" style="height:32px;line-height:1;padding:0 6px;" />',
			esc_attr( self::TO ),
			esc_attr( $to )
		);
		echo '</span>';

		// Per-page selector. Default reflects the effective count for this screen:
		// the request value if present, else the saved screen-option, else 20.
		$effective = isset( $_GET[ self::PER_PAGE ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? (int) $_GET[ self::PER_PAGE ]
			: (int) get_user_option( 'edit_' . ( $order_type ? $order_type : 'shop_order' ) . '_per_page' );
		if ( $effective < 1 ) {
			$effective = 20;
		}

		echo '<span class="aaraa-per-page-filter" style="display:inline-flex;align-items:center;gap:4px;margin:0 6px;vertical-align:middle;">';
		echo '<label style="font-size:12px;color:#50575e;">' . esc_html__( 'Per page', 'aaraa-white-label-admin' ) . '</label>';
		echo '<select name="' . esc_attr( self::PER_PAGE ) . '" onchange="this.form.submit()" style="height:32px;">';
		foreach ( $this->per_page_choices() as $n ) {
			printf(
				'<option value="%1$d" %2$s>%1$d</option>',
				$n,
				selected( $effective, $n, false )
			);
		}
		echo '</select>';
		echo '</span>';

		$this->render_category_filter();
	}

	/**
	 * Output the multi-select Product Category filter (select2, searchable).
	 *
	 * @return void
	 */
	private function render_category_filter() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = isset( $_GET[ self::CAT ] ) ? array_map( 'absint', (array) wp_unslash( $_GET[ self::CAT ] ) ) : array();

		echo '<span class="aaraa-cat-filter" style="display:inline-flex;align-items:center;gap:4px;margin:0 6px;vertical-align:middle;">';
		echo '<select name="' . esc_attr( self::CAT ) . '[]" multiple="multiple" class="wc-enhanced-select aaraa-cat-select" data-placeholder="' . esc_attr__( 'All product categories', 'aaraa-white-label-admin' ) . '" style="min-width:240px;">';
		foreach ( $terms as $term ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $term->term_id,
				selected( in_array( (int) $term->term_id, $selected, true ), true, false ),
				esc_html( $term->name )
			);
		}
		echo '</select>';
		echo '</span>';

		// WooCommerce ships selectWoo/select2 on this screen (the registered-customer
		// filter uses it). The wc-enhanced-select class is auto-initialised, but init
		// explicitly too so a multi-select with search always renders.
		?>
		<script>
		( function () {
			function initAaraaCat() {
				if ( ! window.jQuery ) { return; }
				var $ = window.jQuery;
				var $el = $( '.aaraa-cat-select' );
				if ( ! $el.length ) { return; }
				var fn = $.fn.selectWoo || $.fn.select2;
				if ( ! fn ) { return; }
				if ( $el.hasClass( 'select2-hidden-accessible' ) ) { return; } // already enhanced
				fn.call( $el, {
					width: '240px',
					placeholder: $el.data( 'placeholder' ),
					allowClear: true,
					closeOnSelect: false
				} );
			}
			if ( window.jQuery ) {
				window.jQuery( initAaraaCat );
				window.jQuery( window ).on( 'load', initAaraaCat );
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Apply the From / To dates to the order query args.
	 *
	 * Overrides `date_created` (set earlier from the month dropdown) when a
	 * range is supplied, so the explicit range wins.
	 *
	 * @param array $args wc_get_orders() query args.
	 * @return array
	 */
	public function apply_filter( $args ) {
		$from = $this->clean_date( isset( $_GET[ self::FROM ] ) ? wp_unslash( $_GET[ self::FROM ] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = $this->clean_date( isset( $_GET[ self::TO ] ) ? wp_unslash( $_GET[ self::TO ] ) : '' );     // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $from && $to ) {
			if ( $from > $to ) {
				$tmp  = $from;
				$from = $to;
				$to   = $tmp;
			}
			$args['date_created'] = $from . '...' . $to;
		} elseif ( $from ) {
			$args['date_created'] = '>=' . $from;
		} elseif ( $to ) {
			$args['date_created'] = '<=' . $to;
		}

		// Product-category filter: constrain to orders containing a product in any
		// of the selected categories.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cats = isset( $_GET[ self::CAT ] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_GET[ self::CAT ] ) ) ) : array();
		if ( ! empty( $cats ) ) {
			$args = $this->restrict_ids( $args, $this->order_ids_in_categories( $cats ) );
		}

		return $args;
	}

	/**
	 * Order/subscription ids that contain at least one product in the given
	 * categories.
	 *
	 * Order line items live in woocommerce_order_items(+meta) under HPOS too, so a
	 * direct join is reliable and fast. Product ids are resolved from the category
	 * terms (children included); line items store the parent id in `_product_id`.
	 *
	 * @param int[] $cat_ids Product category term ids.
	 * @return int[] Order ids (empty when nothing matches).
	 */
	private function order_ids_in_categories( $cat_ids ) {
		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $cat_ids,
						'include_children' => true,
					),
				),
			)
		);

		if ( empty( $product_ids ) ) {
			return array();
		}

		global $wpdb;
		$in  = implode( ',', array_map( 'absint', $product_ids ) );
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
			"SELECT DISTINCT oi.order_id
			 FROM {$wpdb->prefix}woocommerce_order_items oi
			 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
			   ON oi.order_item_id = oim.order_item_id
			 WHERE oi.order_item_type = 'line_item'
			   AND oim.meta_key = '_product_id'
			   AND oim.meta_value IN ({$in})"
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Restrict the query to a set of ids via post__in (mapped to `id` by the HPOS
	 * query), intersecting with any set another filter already applied. An empty
	 * set forces zero results (array(0)).
	 *
	 * @param array $args wc_get_orders() args.
	 * @param int[] $ids  Allowed ids.
	 * @return array
	 */
	private function restrict_ids( $args, $ids ) {
		$ids = empty( $ids ) ? array( 0 ) : array_values( array_unique( array_map( 'intval', $ids ) ) );

		if ( empty( $args['post__in'] ) ) {
			$args['post__in'] = $ids;
			return $args;
		}

		$intersect        = array_intersect( (array) $args['post__in'], $ids );
		$args['post__in'] = empty( $intersect ) ? array( 0 ) : array_values( $intersect );
		return $args;
	}

	/**
	 * Validate a raw value to a Y-m-d date string (or '').
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	private function clean_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		// Reject impossible dates like 2026-13-40.
		$parts = explode( '-', $value );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return '';
		}
		return $value;
	}
}
