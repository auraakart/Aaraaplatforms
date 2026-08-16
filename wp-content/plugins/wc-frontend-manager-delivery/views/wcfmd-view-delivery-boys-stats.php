<?php
/**
 * WCFM plugin view
 *
 * WCFM Delivery Boy Stats View
 *
 * @author 		WC Lovers
 * @package 	wcfmd/views/
 * @version   1.0.0
 */
 
global $WCFM, $WCFMd, $wp;

$wcfm_is_allow_delivery = apply_filters( 'wcfm_is_allow_delivery_stats', true );
if( !$wcfm_is_allow_delivery ) {
	wcfm_restriction_message_show( "Delivery Boys" );
	return;
}

$delivery_boy_id = 0; 
if( wcfm_is_delivery_boy() ) {
	$delivery_boy_id = get_current_user_id();
} elseif( isset( $wp->query_vars['wcfm-delivery-boys-stats'] ) && !empty( $wp->query_vars['wcfm-delivery-boys-stats'] ) ) {
	$delivery_boy_id = $wp->query_vars['wcfm-delivery-boys-stats'];
}

if( !$delivery_boy_id ) {
	wcfm_restriction_message_show( "Restricted Access" );
	return;
}

if( wcfm_is_vendor() ) {
	$is_ticket_for_vendor = $WCFM->wcfm_vendor_support->wcfm_is_component_for_vendor( $delivery_boy_id, 'delivery' );
	if( !$is_ticket_for_vendor ) {
		if( apply_filters( 'wcfm_is_show_delivery_restrict_message', true, $delivery_boy_id ) ) {
			wcfm_restriction_message_show( "Restricted Delivery Boy" );
		} else {
			echo apply_filters( 'wcfm_show_custom_delivery_restrict_message', '', $delivery_boy_id );
		}
		return;
	}
}

$delivery_boy_id = absint($delivery_boy_id);

$delivery_boy_label     = '';
$wcfm_delivery_boy_user = get_userdata( absint( $delivery_boy_id ) );
if( $wcfm_delivery_boy_user ) {
	if ( !in_array( 'wcfm_delivery_boy', (array) $wcfm_delivery_boy_user->roles ) ) {
		wcfm_restriction_message_show( "Invalid Delivery Person" );
		return;
	}
	
	$delivery_boy_label     = apply_filters( 'wcfm_delivery_boy_display', $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name, $delivery_boy_id );
} else {
	wcfm_restriction_message_show( "Invalid Delivery Person" );
	return;
}

?>
<div class="collapse wcfm-collapse" id="wcfm_delivery_boy_stats_listing">
  <div class="wcfm-page-headig">
		<span class="wcfmfa fa-shipping-fast"></span>
		<span class="wcfm-page-heading-text"><?php _e( 'Delivery Stats', 'wc-frontend-manager-delivery' ); ?></span>
		<?php do_action( 'wcfm_page_heading' ); ?>
	</div>
	<div class="wcfm-collapse-content">
	  <div id="wcfm_page_load"></div>
	  
	  <?php if( !wcfm_is_delivery_boy() ) { ?>
			<div class="wcfm-container wcfm-top-element-container">
				<h2>
					<?php echo __( 'Delivery Stats', 'wc-frontend-manager-delivery' ) . ' - ' . $delivery_boy_label; ?>
				</h2>
				<div class="wcfm-clearfix"></div>
			</div>
			<div class="wcfm-clearfix"></div><br />
		<?php } ?>
		
		<script>
		window.wcfmdFilterTab = function(btn) {
			document.querySelectorAll('.wcfmd-filter-tab').forEach(function(t) {
				t.classList.remove('wcfmd-tab-active');
			});
			btn.classList.add('wcfmd-tab-active');
			var status = btn.getAttribute('data-status');
			window.$status_type = (status !== null) ? String(status) : '';
			if (window.$wcfm_delivery_boy_stats_table) {
				window.$wcfm_delivery_boy_stats_table.ajax.reload();
			}
		};
		</script>

		<div class="wcfmd-status-filter">
			<button type="button" class="wcfmd-filter-tab" data-status="" onclick="wcfmdFilterTab(this)">
				<i class="wcfmfa fa-list-ul"></i>
				<span><?php _e( 'All', 'wc-frontend-manager' ); ?></span>
			</button>
			<button type="button" class="wcfmd-filter-tab wcfmd-tab-active" data-status="pending" onclick="wcfmdFilterTab(this)">
				<i class="wcfmfa fa-shipping-fast"></i>
				<span><?php _e( 'Pending', 'wc-frontend-manager' ); ?></span>
			</button>
			<button type="button" class="wcfmd-filter-tab" data-status="delivered" onclick="wcfmdFilterTab(this)">
				<i class="wcfmfa fa-check-circle"></i>
				<span><?php _e( 'Delivered', 'wc-frontend-manager-delivery' ); ?></span>
			</button>
		</div>
		<select name="status_type" id="dropdown_status_type" style="display:none;">
			<option value=""><?php _e( 'Show all ..', 'wc-frontend-manager' ); ?></option>
			<option value="delivered"><?php _e( 'Delivered', 'wc-frontend-manager-delivery' ); ?></option>
			<option value="pending" selected><?php _e( 'Pending', 'wc-frontend-manager' ); ?></option>
		</select>
	  
	  <?php do_action( 'before_wcfm_delivery_boy_stats' ); ?>

		<!-- Route Optimizer ─────────────────────────────────────── -->
		<style>
		.wcfmd-route-section{margin-bottom:20px;border:1px solid #d0d7e4;border-radius:6px;overflow:hidden}
		.wcfmd-route-header{display:flex;align-items:center;gap:10px;padding:13px 18px;background:#1e2d40;color:#fff;cursor:pointer;user-select:none;font-size:14px;font-weight:600}
		.wcfmd-route-header i{font-size:16px;opacity:.85}
		.wcfmd-toggle-arrow{margin-left:auto;transition:transform .25s;font-style:normal;font-size:13px}
		.wcfmd-arrow-up{transform:rotate(180deg)}
		.wcfmd-route-body{display:none}
		.wcfmd-route-toolbar{padding:14px 18px;background:#f7f8fa;border-bottom:1px solid #e0e4ea;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
		#wcfmd-optimize-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:4px;background:#1e3a8a;color:#fff;border:none;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s}
		#wcfmd-optimize-btn:hover{background:#1e40af}
		#wcfmd-optimize-btn:disabled{background:#6b7280;cursor:not-allowed}
		.wcfmd-route-hint{font-size:12px;color:#6b7280;flex:1}
		.wcfmd-route-panel{display:flex;height:460px}
		#wcfmd-route-sidebar{width:300px;flex-shrink:0;border-right:1px solid #e0e4ea;overflow-y:auto;background:#fafbfc}
		#wcfmd-route-map{flex:1;min-width:0}
		.wcfmd-stops-list{padding:8px 0}
		.wcfmd-stop-item{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-bottom:1px solid #eef0f4}
		.wcfmd-stop-item:last-child{border-bottom:none}
		.wcfmd-stop-num{width:26px;height:26px;border-radius:50%;background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:2px}
		.wcfmd-stop-body{flex:1;min-width:0}
		.wcfmd-stop-name{font-weight:600;font-size:13px;color:#1e2d40;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.wcfmd-stop-order{font-size:11px;color:#1e3a8a;font-weight:600;margin-top:1px}
		.wcfmd-stop-items{font-size:11px;color:#1e3a8a;margin-top:2px;line-height:1.4}
		.wcfmd-stop-addr{font-size:11px;color:#6b7280;margin-top:3px;line-height:1.4}
		.wcfmd-stop-phone{font-size:11px;color:#374151;margin-top:3px}
		.wcfmd-route-msg{padding:20px 16px;color:#6b7280;font-size:13px;margin:0}
		.wcfmd-route-err{color:#dc2626}
		.wcfmd-route-summary-bar{padding:0;background:#f7f8fa;border-top:1px solid #e0e4ea}
		.wcfmd-route-stats{display:flex;border-bottom:1px solid #e0e4ea}
		.wcfmd-route-stat{flex:1;display:flex;flex-direction:column;align-items:center;padding:12px 8px;text-align:center;border-right:1px solid #e0e4ea}
		.wcfmd-route-stat:last-child{border-right:none}
		.wcfmd-route-stat i{font-size:18px;color:#1e3a8a;margin-bottom:4px}
		.wcfmd-route-stat strong{font-size:16px;color:#111827}
		.wcfmd-route-stat span{font-size:11px;color:#6b7280;margin-top:2px;text-transform:uppercase;letter-spacing:.4px}
		.wcfmd-nav-wrap{padding:12px 18px}
		.wcfmd-nav-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:4px;background:#16a34a;color:#fff;font-size:13px;font-weight:600;text-decoration:none;transition:background .2s}
		.wcfmd-nav-btn:hover{background:#15803d;color:#fff}
		@media(max-width:640px){.wcfmd-route-panel{flex-direction:column;height:auto}#wcfmd-route-sidebar{width:100%;height:220px;border-right:none;border-bottom:1px solid #e0e4ea}#wcfmd-route-map{height:280px}.wcfmd-route-toolbar{flex-direction:column;align-items:stretch}#wcfmd-optimize-btn{justify-content:center;padding:11px 20px;font-size:14px}.wcfmd-route-hint{text-align:center}.wcfmd-route-stats{flex-wrap:wrap}.wcfmd-route-stat{min-width:calc(33.33% - 2px)}.wcfmd-nav-wrap{text-align:center}.wcfmd-nav-btn{justify-content:center;width:100%;box-sizing:border-box}}
		</style>

		<div class="wcfmd-route-section">
			<div class="wcfmd-route-header" id="wcfmd-route-toggle">
				<i class="wcfmfa fa-map-marked-alt"></i>
				<?php _e( 'Route Optimizer', 'wc-frontend-manager-delivery' ); ?>
				<span class="wcfmd-toggle-arrow">▼</span>
			</div>
			<div class="wcfmd-route-body" id="wcfmd-route-body">
				<div class="wcfmd-route-toolbar">
					<button id="wcfmd-optimize-btn">
						<i class="wcfmfa fa-route"></i>
						<?php _e( 'Optimize Route', 'wc-frontend-manager-delivery' ); ?>
					</button>
					<span class="wcfmd-route-hint">
						<?php _e( 'Finds the most efficient delivery sequence for all pending orders. Allow location access for best results.', 'wc-frontend-manager-delivery' ); ?>
					</span>
				</div>
				<div class="wcfmd-route-panel">
					<div id="wcfmd-route-sidebar">
						<p class="wcfmd-route-msg"><?php _e( 'Click "Optimize Route" to calculate the best delivery sequence.', 'wc-frontend-manager-delivery' ); ?></p>
					</div>
					<div id="wcfmd-route-map"></div>
				</div>
				<div class="wcfmd-route-summary-bar">
					<div id="wcfmd-route-summary"></div>
				</div>
			</div>
		</div>
		<!-- /Route Optimizer ────────────────────────────────────── -->

	  <div class="wcfm_dashboard_stats">
			<div class="wcfm_dashboard_stats_block">
			  <a href="#" onclick="return false;">
					<span class="wcfmfa fa-ambulance"></span>
					<div>
						<strong><?php echo wcfm_get_delivery_boy_delivery_stat( $delivery_boy_id, 'delivered' ); ?></strong><br />
						<?php _e( 'delivered', 'wc-frontend-manager-delivery' ); ?>
					</div>
				</a>
			</div>
			<div class="wcfm_dashboard_stats_block">
			  <a href="#" onclick="return false;">
					<span class="wcfmfa fa-shipping-fast"></span>
					<div>
						<strong><?php echo wcfm_get_delivery_boy_delivery_stat( $delivery_boy_id, 'pending' ); ?></strong><br />
						<?php _e( 'pending', 'wc-frontend-manager-delivery' ); ?>
					</div>
				</a>
			</div>
		</div>
		<div class="wcfm-clearfix"></div>
	  
		<div class="wcfm-container">
			<div id="wcfm_delivery_boy_stats_listing_expander" class="wcfm-content">
				<table id="wcfm_delivery_boy_stats" class="display" cellspacing="0" width="100%">
					<thead>
						<tr>
						  <th><span class="wcicon-status-processing text_tip" data-tip="<?php _e( 'Status', 'wc-frontend-manager-delivery' ); ?>"></span></th>
						  <th><?php _e( 'Order', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Item', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Store', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Customer', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Delivery Address', 'wc-frontend-manager-delivery' ); ?></th>
							<?php do_action( 'wcfm_delivery_stats_columns_before' ); ?>
							<th><?php _e( 'Actions', 'wc-frontend-manager-delivery' ); ?></th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<th><span class="wcicon-status-processing text_tip" data-tip="<?php _e( 'Status', 'wc-frontend-manager-delivery' ); ?>"></span></th>
							<th><?php _e( 'Order', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Item', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Store', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Customer', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Delivery Address', 'wc-frontend-manager-delivery' ); ?></th>
							<?php do_action( 'wcfm_delivery_stats_columns_before' ); ?>
							<th><?php _e( 'Actions', 'wc-frontend-manager-delivery' ); ?></th>
						</tr>
					</tfoot>
				</table>
				<div class="wcfm-clearfix"></div>
			</div>
		</div>
		<?php
		do_action( 'after_wcfm_delivery_boy_stats' );
		?>
		<input type="hidden" name="wcfm_delivery_boy_id" id="wcfm_delivery_boy_id" value="<?php echo $delivery_boy_id; ?>" />
	</div>
</div>