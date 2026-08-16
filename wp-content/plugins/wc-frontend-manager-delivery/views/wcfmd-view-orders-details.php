<?php
use Automattic\WooCommerce\Utilities\OrderUtil;

global $wp, $WCFM, $WCFMu, $WCFMd, $wp_query, $wpdb;

$order_id = 0;
if ( isset( $wp->query_vars['wcfm-orders-details'] ) && ! empty( $wp->query_vars['wcfm-orders-details'] ) ) {
	$order_id = absint( $wp->query_vars['wcfm-orders-details'] );
} else {
	return;
}

$order = wc_get_order( $order_id );
if ( ! $order ) {
	return;
}

$line_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );
$line_items = apply_filters( 'wcfm_valid_line_items', $line_items, $order->get_id() );

if ( empty( $line_items ) ) {
	return;
}

// Collect all item/product IDs for order-level assignment.
$all_item_ids    = [];
$all_product_ids = [];
$delivery_boy_id = 0;

foreach ( $line_items as $item_id => $item ) {
	$all_item_ids[]    = $item_id;
	$all_product_ids[] = $item->get_product_id();
	if ( ! $delivery_boy_id ) {
		$delivery_boy_id = (int) wc_get_order_item_meta( $item_id, 'wcfm_delivery_boy', true );
	}
}

$hub_name          = '';
$delivery_boy_name = '';
$is_assigned       = false;

if ( $delivery_boy_id ) {
	$is_assigned = true;
	$hub_id      = (int) get_user_meta( $delivery_boy_id, '_wcfmd_delivery_hub', true );
	if ( $hub_id ) {
		$hub_name = $wpdb->get_var( $wpdb->prepare(
			"SELECT name FROM {$wpdb->prefix}wcfm_delivery_hubs WHERE ID = %d",
			$hub_id
		) );
	}
	$boy_data = get_userdata( $delivery_boy_id );
	if ( $boy_data ) {
		$delivery_boy_name = trim( $boy_data->first_name . ' ' . $boy_data->last_name );
		if ( ! $delivery_boy_name ) {
			$delivery_boy_name = $boy_data->display_name;
		}
	}
}

$all_item_ids_str    = implode( ',', $all_item_ids );
$all_product_ids_str = implode( ',', $all_product_ids );
?>
<div class="wcfm-clearfix"></div>
<br />
<div class="page_collapsible orders_details_shipment" id="sm_order_delivery_options">
	<label class="wcfmfa fa-shipping-fast"></label>
	<?php _e( 'Delivery Assignment', 'wc-frontend-manager-delivery' ); ?><span></span>
</div>
<div class="wcfm-container orders_details_shipment_expander_container">
	<div id="orders_details_shipment_expander" class="wcfm-content">

		<style>
		.wcfmd-delivery-card {
			background: #fff;
			border: 1px solid #e0e4ea;
			border-radius: 6px;
			max-width: 520px;
			overflow: hidden;
		}
		.wcfmd-delivery-card .wcfmd-card-row {
			display: flex;
			align-items: center;
			padding: 14px 20px;
			border-bottom: 1px solid #f0f2f5;
			gap: 12px;
		}
		.wcfmd-delivery-card .wcfmd-card-row:last-child {
			border-bottom: none;
		}
		.wcfmd-delivery-card .wcfmd-card-icon {
			width: 36px;
			height: 36px;
			border-radius: 50%;
			background: #f0f4ff;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			color: #4a6cf7;
			font-size: 15px;
		}
		.wcfmd-delivery-card .wcfmd-card-label {
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 0.6px;
			color: #8a94a6;
			font-weight: 600;
			margin-bottom: 2px;
		}
		.wcfmd-delivery-card .wcfmd-card-value {
			font-size: 14px;
			color: #1e2d40;
			font-weight: 500;
		}
		.wcfmd-delivery-card .wcfmd-card-value.unassigned {
			color: #b0bac9;
			font-style: italic;
			font-weight: 400;
		}
		.wcfmd-delivery-card .wcfmd-card-action {
			padding: 16px 20px;
			background: #f8f9fb;
			border-top: 1px solid #e0e4ea;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		.wcfmd-delivery-card .wcfmd-status-badge {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			padding: 3px 10px;
			border-radius: 20px;
			font-size: 12px;
			font-weight: 600;
		}
		.wcfmd-delivery-card .wcfmd-status-badge.assigned {
			background: #e6f4ea;
			color: #2e7d32;
		}
		.wcfmd-delivery-card .wcfmd-status-badge.unassigned {
			background: #fff3e0;
			color: #e65100;
		}
		.wcfmd-assign-btn {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 8px 16px;
			border-radius: 4px;
			font-size: 13px;
			font-weight: 600;
			cursor: pointer;
			text-decoration: none;
			transition: background 0.2s;
		}
		.wcfmd-assign-btn.btn-assign {
			background: #4a6cf7;
			color: #fff;
			border: none;
		}
		.wcfmd-assign-btn.btn-assign:hover {
			background: #3558e0;
			color: #fff;
		}
		.wcfmd-assign-btn.btn-reassign {
			background: #fff;
			color: #4a6cf7;
			border: 1px solid #4a6cf7;
		}
		.wcfmd-assign-btn.btn-reassign:hover {
			background: #f0f4ff;
			color: #3558e0;
		}
		</style>

		<div class="wcfmd-delivery-card">

			<!-- Delivery Hub row -->
			<div class="wcfmd-card-row">
				<div class="wcfmd-card-icon">
					<i class="wcfmfa fa-map-marker-alt"></i>
				</div>
				<div style="flex:1;">
					<div class="wcfmd-card-label"><?php _e( 'Delivery Hub', 'wc-frontend-manager-delivery' ); ?></div>
					<?php if ( $hub_name ) : ?>
						<div class="wcfmd-card-value"><?php echo esc_html( $hub_name ); ?></div>
					<?php else : ?>
						<div class="wcfmd-card-value unassigned"><?php _e( 'Not set', 'wc-frontend-manager-delivery' ); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Delivery Person row -->
			<div class="wcfmd-card-row">
				<div class="wcfmd-card-icon">
					<i class="wcfmfa fa-user"></i>
				</div>
				<div style="flex:1;">
					<div class="wcfmd-card-label"><?php _e( 'Delivery Person', 'wc-frontend-manager-delivery' ); ?></div>
					<?php if ( $delivery_boy_name ) : ?>
						<div class="wcfmd-card-value"><?php echo esc_html( $delivery_boy_name ); ?></div>
					<?php else : ?>
						<div class="wcfmd-card-value unassigned"><?php _e( 'Not assigned', 'wc-frontend-manager-delivery' ); ?></div>
					<?php endif; ?>
				</div>
				<?php if ( $is_assigned ) : ?>
					<span class="wcfmd-status-badge assigned">
						<i class="wcfmfa fa-check-circle"></i>
						<?php _e( 'Assigned', 'wc-frontend-manager-delivery' ); ?>
					</span>
				<?php else : ?>
					<span class="wcfmd-status-badge unassigned">
						<i class="wcfmfa fa-clock"></i>
						<?php _e( 'Pending', 'wc-frontend-manager-delivery' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<!-- Action row -->
			<div class="wcfmd-card-action">
				<span style="font-size:12px;color:#8a94a6;">
					<?php
					$count = count( $all_item_ids );
					printf(
						_n(
							'Applies to %d order item',
							'Applies to %d order items',
							$count,
							'wc-frontend-manager-delivery'
						),
						$count
					);
					?>
				</span>
				<a class="wcfmd-assign-btn wcfm_order_delivery_boy_assign <?php echo $is_assigned ? 'btn-reassign' : 'btn-assign'; ?>"
					href="#"
					data-shipped_action="wcfmd_delivery_boy_assign"
					data-productid="<?php echo esc_attr( $all_product_ids_str ); ?>"
					data-orderitemid="<?php echo esc_attr( $all_item_ids_str ); ?>"
					data-orderid="<?php echo esc_attr( $order_id ); ?>">
					<i class="wcfmfa fa-shipping-fast"></i>
					<?php echo $is_assigned
						? __( 'Reassign', 'wc-frontend-manager-delivery' )
						: __( 'Assign Delivery Person', 'wc-frontend-manager-delivery' );
					?>
				</a>
			</div>

		</div>

	</div>
</div>
