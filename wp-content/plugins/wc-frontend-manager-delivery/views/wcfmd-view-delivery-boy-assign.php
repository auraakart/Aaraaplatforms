<?php
/**
 * WCFM plugin view
 *
 * WCfM Delivery Boy Assign popup View
 *
 * @author 		WC Lovers
 * @package 	wcfmd/views/orders
 * @version   1.0.0
 */

global $wp, $WCFM, $WCFMd, $_POST, $wpdb;

$order_id      = absint( $_POST['orderid'] );
$product_id    = sanitize_text_field( $_POST['productid'] ?? '' );
$order_item_id = sanitize_text_field( $_POST['orderitemid'] ?? '' );

// Use first item ID to look up current assignment for pre-selection.
$first_item_id   = (int) explode( ',', $order_item_id )[0];
$delivery_boy_id = $first_item_id ? (int) wc_get_order_item_meta( $first_item_id, 'wcfm_delivery_boy', true ) : 0;

// Derive hub from current delivery person.
$current_hub_id = $delivery_boy_id ? (int) get_user_meta( $delivery_boy_id, '_wcfmd_delivery_hub', true ) : 0;

// Hub dropdown.
$hubs     = $wpdb->get_results( "SELECT ID, name FROM {$wpdb->prefix}wcfm_delivery_hubs WHERE status='active' ORDER BY name ASC" );
$hub_opts = [ '' => __( '— Select Delivery Hub —', 'wc-frontend-manager-delivery' ) ];
foreach ( $hubs as $hub ) {
	$hub_opts[ $hub->ID ] = $hub->name;
}

// Delivery person dropdown (pre-filtered to current hub if set, else all).
if ( $current_hub_id ) {
	$boys = get_users( [
		'role__in'   => [ apply_filters( 'wcfm_delivery_boy_user_role', 'wcfm_delivery_boy' ) ],
		'meta_query' => [ [ 'key' => '_wcfmd_delivery_hub', 'value' => $current_hub_id, 'compare' => '=' ] ],
	] );
} else {
	$boys = wcfm_get_delivery_boys();
}

$delivery_users = [ '' => __( '— Select Delivery Person —', 'wc-frontend-manager-delivery' ) ];
foreach ( $boys as $boy ) {
	$name = trim( $boy->first_name . ' ' . $boy->last_name );
	$delivery_users[ $boy->ID ] = ( $name ?: $boy->display_name ) . ' (' . $boy->user_email . ')';
}

$nonce = wp_create_nonce( 'wcfmd_hub_nonce' );
?>
<div class="wcfm-collapse-content wcfm_popup_wrapper">
	<form id="wcfm_shipping_tracking_form">
		<div style="margin-bottom:15px;">
			<h2 style="float:none;"><?php _e( 'Assign Delivery Person', 'wc-frontend-manager-delivery' ); ?></h2>
		</div>
		<?php
		$WCFM->wcfm_fields->wcfm_generate_form_field( [
			'wcfmd_popup_hub' => [
				'label'       => __( 'Delivery Hub', 'wc-frontend-manager-delivery' ),
				'type'        => 'select',
				'options'     => $hub_opts,
				'class'       => 'wcfm-select wcfm_popup_input',
				'label_class' => 'shipment_tracking_input wcfm_popup_label',
				'value'       => $current_hub_id,
			],
			'wcfm_delivery_boy' => [
				'label'       => __( 'Delivery Person', 'wc-frontend-manager-delivery' ),
				'type'        => 'select',
				'options'     => $delivery_users,
				'class'       => 'wcfm-select wcfm_popup_input',
				'label_class' => 'shipment_tracking_input wcfm_popup_label',
				'value'       => $delivery_boy_id,
			],
			'wcfm_tracking_order_id'      => [ 'type' => 'hidden', 'value' => $order_id ],
			'wcfm_tracking_product_id'    => [ 'type' => 'hidden', 'value' => $product_id ],
			'wcfm_tracking_order_item_id' => [ 'type' => 'hidden', 'value' => $order_item_id ],
		] );
		?>
		<div class="wcfm-message"></div>
		<input type="submit" id="wcfm_tracking_button" name="wcfm_tracking_button"
			class="wcfm_submit_button wcfm_popup_button"
			value="<?php esc_attr_e( 'Assign', 'wc-frontend-manager-delivery' ); ?>" />
	</form>
</div>

<script>
(function($) {
	'use strict';
	var _nonce = '<?php echo esc_js( $nonce ); ?>';

	$('#wcfmd_popup_hub').on('change', function() {
		var hubId     = $(this).val();
		var $boySelect = $('#wcfm_delivery_boy');
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
			if ( resp.success && resp.data.length ) {
				$.each(resp.data, function(i, boy) {
					$boySelect.append($('<option>', { value: boy.id, text: boy.text }));
				});
			}
		});
	});
})(jQuery);
</script>
