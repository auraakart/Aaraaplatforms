<?php
/**
 * WCFM Delivery plugin
 *
 * Delivery Hub management + customer profile delivery assignment
 *
 * @package wcfmd/core
 */

class WCFMd_Delivery_Hub {

	public function __construct() {
		// Endpoints
		add_filter( 'wcfm_query_vars',    [ $this, 'hub_query_vars' ],    91 );
		add_filter( 'wcfm_endpoint_title', [ $this, 'hub_endpoint_title' ], 91, 2 );
		add_filter( 'wcfm_endpoints_slug', [ $this, 'hub_endpoints_slug' ] );
		add_action( 'init',               [ $this, 'hub_endpoint_init' ], 91 );

		// Views & Scripts
		add_action( 'wcfm_load_views',   [ $this, 'hub_load_views' ],  30 );
		add_action( 'wcfm_load_scripts', [ $this, 'hub_load_scripts' ], 30 );
		add_action( 'wcfm_load_styles',  [ $this, 'hub_load_styles' ],  30 );

		// AJAX
		add_action( 'wp_ajax_wcfmd_hub_list',   [ $this, 'ajax_hub_list' ] );
		add_action( 'wp_ajax_wcfmd_hub_get',    [ $this, 'ajax_hub_get' ] );
		add_action( 'wp_ajax_wcfmd_hub_save',   [ $this, 'ajax_hub_save' ] );
		add_action( 'wp_ajax_wcfmd_hub_delete', [ $this, 'ajax_hub_delete' ] );

		// Customer profile
		add_action( 'end_wcfm_customers_manage_form', [ $this, 'customer_delivery_fields' ] );
		add_action( 'wcfm_customers_manage',          [ $this, 'customer_delivery_fields_save' ], 10, 2 );

		// AJAX: delivery boys filtered by hub (used in customer profile)
		add_action( 'wp_ajax_wcfmd_get_hub_delivery_boys', [ $this, 'ajax_get_hub_delivery_boys' ] );

		// Auto-assign delivery person on new order if customer has hub+person mapped
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'auto_assign_customer_delivery_boy' ], 20, 3 );
	}

	/* ── Endpoints ──────────────────────────────────────────────── */

	function hub_query_vars( $query_vars ) {
		$query_vars['wcfm-delivery-hubs'] = 'delivery-hubs';
		return $query_vars;
	}

	function hub_endpoint_title( $title, $endpoint ) {
		if ( $endpoint === 'wcfm-delivery-hubs' ) {
			$title = __( 'Delivery Hub', 'wc-frontend-manager-delivery' );
		}
		return $title;
	}

	function hub_endpoints_slug( $endpoints ) {
		$endpoints['wcfm-delivery-hubs'] = 'delivery-hubs';
		return $endpoints;
	}

	function hub_endpoint_init() {
		global $WCFM_Query;
		$WCFM_Query->init_query_vars();
		$WCFM_Query->add_endpoints();
		if ( get_option( 'wcfmd_endpoint_delivery_hubs' ) !== '1.0.0' ) {
			flush_rewrite_rules();
			update_option( 'wcfmd_endpoint_delivery_hubs', '1.0.0' );
		}
	}

	/* ── Views & Scripts ────────────────────────────────────────── */

	function hub_load_views( $end_point ) {
		global $WCFMd;
		if ( $end_point === 'wcfm-delivery-hubs' ) {
			$WCFMd->template->get_template( 'wcfmd-view-delivery-hubs.php' );
		}
	}

	function hub_load_scripts( $end_point ) {
		global $WCFM, $WCFMd;
		if ( $end_point === 'wcfm-delivery-hubs' ) {
			$WCFM->library->load_datatable_lib();
			wp_enqueue_script(
				'wcfmd_delivery_hubs_js',
				$WCFMd->library->js_lib_url . 'wcfmd-script-delivery-hubs.js',
				[ 'jquery', 'dataTables_js' ],
				$WCFMd->version . '.2',
				true
			);
			wp_localize_script( 'wcfmd_delivery_hubs_js', 'wcfmd_hub_params', [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcfmd_hub_nonce' ),
				'list_url' => get_wcfmd_delivery_hubs_url(),
			] );
		}
	}

	function hub_load_styles( $end_point ) {
		global $WCFM;
		if ( $end_point === 'wcfm-delivery-hubs' ) {
			wp_enqueue_style( 'collapsible_css', $WCFM->library->css_lib_url . 'wcfm-style-collapsible.css', [], $WCFM->version );
		}
	}

	/* ── AJAX: List ─────────────────────────────────────────────── */

	function ajax_hub_list() {
		check_ajax_referer( 'wcfmd_hub_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wcfm_delivery_hubs ORDER BY ID DESC" );
		$data = [];
		foreach ( $rows as $row ) {
			$data[] = [
				'id'      => $row->ID,
				'name'    => esc_html( $row->name ),
				'address' => esc_html( $row->address ),
				'area'    => esc_html( $row->area ),
				'status'  => $row->status,
				'created' => date_i18n( get_option( 'date_format' ), strtotime( $row->created ) ),
			];
		}
		wp_send_json_success( $data );
	}

	/* ── AJAX: Get single ───────────────────────────────────────── */

	function ajax_hub_get() {
		check_ajax_referer( 'wcfmd_hub_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$id  = absint( $_POST['hub_id'] ?? 0 );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wcfm_delivery_hubs WHERE ID = %d", $id ) );
		if ( ! $row ) {
			wp_send_json_error( 'Hub not found.' );
		}
		wp_send_json_success( $row );
	}

	/* ── AJAX: Save ─────────────────────────────────────────────── */

	function ajax_hub_save() {
		check_ajax_referer( 'wcfmd_hub_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$id      = absint( $_POST['hub_id'] ?? 0 );
		$name    = sanitize_text_field( $_POST['hub_name'] ?? '' );
		$address = sanitize_textarea_field( $_POST['hub_address'] ?? '' );
		$area    = sanitize_text_field( $_POST['hub_area'] ?? '' );
		$status  = in_array( $_POST['hub_status'] ?? '', [ 'active', 'inactive' ], true )
			? sanitize_key( $_POST['hub_status'] )
			: 'active';

		if ( ! $name ) {
			wp_send_json_error( 'Hub name is required.' );
		}

		$data   = compact( 'name', 'address', 'area', 'status' );
		$format = [ '%s', '%s', '%s', '%s' ];

		if ( $id ) {
			$wpdb->update( "{$wpdb->prefix}wcfm_delivery_hubs", $data, [ 'ID' => $id ], $format, [ '%d' ] );
		} else {
			$wpdb->insert( "{$wpdb->prefix}wcfm_delivery_hubs", $data, $format );
			$id = $wpdb->insert_id;
		}

		wp_send_json_success( [
			'id'      => $id,
			'message' => __( 'Hub saved successfully.', 'wc-frontend-manager-delivery' ),
		] );
	}

	/* ── AJAX: Delete ───────────────────────────────────────────── */

	function ajax_hub_delete() {
		check_ajax_referer( 'wcfmd_hub_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$id = absint( $_POST['hub_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( 'Invalid hub.' );
		}
		$wpdb->delete( "{$wpdb->prefix}wcfm_delivery_hubs", [ 'ID' => $id ], [ '%d' ] );
		wp_send_json_success( [ 'message' => __( 'Hub deleted.', 'wc-frontend-manager-delivery' ) ] );
	}

	/* ── Customer Profile: show fields ─────────────────────────── */

	function customer_delivery_fields( $customer_id ) {
		global $wpdb, $WCFM;

		$saved_hub = (int) get_user_meta( $customer_id, '_wcfmd_delivery_hub', true );
		$saved_boy = (int) get_user_meta( $customer_id, '_wcfmd_delivery_boy', true );

		$hubs     = $wpdb->get_results( "SELECT ID, name FROM {$wpdb->prefix}wcfm_delivery_hubs WHERE status='active' ORDER BY name ASC" );
		$hub_opts = [ '' => __( '— Select Delivery Hub —', 'wc-frontend-manager-delivery' ) ];
		foreach ( $hubs as $h ) {
			$hub_opts[ $h->ID ] = $h->name;
		}

		// If a hub is already saved, show only that hub's delivery boys; otherwise show all
		if ( $saved_hub ) {
			$boys = get_users( [
				'role__in'   => [ apply_filters( 'wcfm_delivery_boy_user_role', 'wcfm_delivery_boy' ) ],
				'meta_query' => [ [ 'key' => '_wcfmd_delivery_hub', 'value' => $saved_hub, 'compare' => '=' ] ],
			] );
		} else {
			$boys = wcfm_get_delivery_boys();
		}
		$boy_opts = [ '' => __( '— Select Delivery Person —', 'wc-frontend-manager-delivery' ) ];
		foreach ( $boys as $b ) {
			$boy_opts[ $b->ID ] = $b->first_name . ' ' . $b->last_name . ' (' . $b->user_email . ')';
		}
		?>
		<div class="page_collapsible" id="wcfmd_customer_delivery_head" style="cursor:pointer;">
			<label class="wcfmfa fa-shipping-fast"></label>
			<?php _e( 'Delivery Assignment', 'wc-frontend-manager-delivery' ); ?><span></span>
		</div>
		<div class="wcfm-container">
			<div id="wcfmd_customer_delivery_expander" class="wcfm-content">
				<?php
				$WCFM->wcfm_fields->wcfm_generate_form_field( [
					'wcfmd_delivery_hub' => [
						'label'       => __( 'Delivery Hub', 'wc-frontend-manager-delivery' ),
						'type'        => 'select',
						'options'     => $hub_opts,
						'class'       => 'wcfm-select wcfm_ele',
						'label_class' => 'wcfm_title wcfm_ele',
						'value'       => $saved_hub,
					],
					'wcfmd_delivery_boy' => [
						'label'       => __( 'Delivery Person', 'wc-frontend-manager-delivery' ),
						'type'        => 'select',
						'options'     => $boy_opts,
						'class'       => 'wcfm-select wcfm_ele',
						'label_class' => 'wcfm_title wcfm_ele',
						'value'       => $saved_boy,
					],
				] );
				?>
			</div>
		</div>
		<div class="wcfm_clearfix"></div>
		<script>
		(function($) {
			'use strict';
			var $hubSelect = $('#wcfmd_delivery_hub');
			var $boySelect = $('#wcfmd_delivery_boy');
			var _nonce     = '<?php echo esc_js( wp_create_nonce( 'wcfmd_hub_nonce' ) ); ?>';

			function loadDeliveryBoys(hubId, selectBoyId) {
				$boySelect.prop('disabled', true).find('option').not(':first').remove();

				if ( ! hubId ) {
					$boySelect.prop('disabled', false);
					return;
				}

				$.post(wcfm_params.ajax_url, {
					action : 'wcfmd_get_hub_delivery_boys',
					hub_id : hubId,
					nonce  : _nonce
				}, function(resp) {
					$boySelect.prop('disabled', false);
					if (resp.success && resp.data.length) {
						$.each(resp.data, function(i, boy) {
							$boySelect.append($('<option>', { value: boy.id, text: boy.text }));
						});
					}
					if (selectBoyId) {
						$boySelect.val(selectBoyId);
					}
				});
			}

			$hubSelect.on('change', function() {
				loadDeliveryBoys($(this).val(), 0);
			});
		})(jQuery);
		</script>
		<?php
	}

	/* ── AJAX: Delivery boys for a hub ─────────────────────────── */

	function ajax_get_hub_delivery_boys() {
		check_ajax_referer( 'wcfmd_hub_nonce', 'nonce' );

		$hub_id  = absint( $_POST['hub_id'] ?? 0 );
		$options = [];

		if ( $hub_id ) {
			$boys = get_users( [
				'role__in'   => [ apply_filters( 'wcfm_delivery_boy_user_role', 'wcfm_delivery_boy' ) ],
				'meta_query' => [
					[
						'key'     => '_wcfmd_delivery_hub',
						'value'   => $hub_id,
						'compare' => '=',
					],
				],
			] );
			foreach ( $boys as $boy ) {
				$options[] = [
					'id'   => $boy->ID,
					'text' => trim( $boy->first_name . ' ' . $boy->last_name ) . ' (' . $boy->user_email . ')',
				];
			}
		}

		wp_send_json_success( $options );
	}

	/* ── Auto-assign delivery person on order creation ─────────── */

	function auto_assign_customer_delivery_boy( $order_id, $posted_data, $order ) {
		$customer_id = $order->get_user_id();
		if ( ! $customer_id ) {
			return;
		}

		$delivery_hub = (int) get_user_meta( $customer_id, '_wcfmd_delivery_hub', true );
		$delivery_boy = (int) get_user_meta( $customer_id, '_wcfmd_delivery_boy', true );

		if ( ! $delivery_hub || ! $delivery_boy ) {
			return;
		}

		// Confirm delivery person is still assigned to the same hub.
		if ( (int) get_user_meta( $delivery_boy, '_wcfmd_delivery_hub', true ) !== $delivery_hub ) {
			return;
		}

		$wcfm_tracking_data = [ 'wcfm_delivery_boy' => $delivery_boy ];

		foreach ( $order->get_items() as $item_id => $item ) {
			do_action( 'wcfmd_delivery_boy_assigned', $order_id, $item_id, $wcfm_tracking_data, $item->get_product_id() );
		}
	}

	/* ── Customer Profile: save fields ─────────────────────────── */

	function customer_delivery_fields_save( $customer_id, $form_data ) {
		if ( ! $customer_id ) {
			return;
		}
		if ( isset( $form_data['wcfmd_delivery_hub'] ) ) {
			update_user_meta( $customer_id, '_wcfmd_delivery_hub', absint( $form_data['wcfmd_delivery_hub'] ) );
		}
		if ( isset( $form_data['wcfmd_delivery_boy'] ) ) {
			update_user_meta( $customer_id, '_wcfmd_delivery_boy', absint( $form_data['wcfmd_delivery_boy'] ) );
		}
	}
}
