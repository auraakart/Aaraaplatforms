<?php
/**
 * WCFM Delivery plugin view
 *
 * Delivery Hub Listing View
 *
 * @package wcfmd/views
 */

global $WCFM;

if ( ! apply_filters( 'wcfm_is_allow_delivery', true ) ) {
	wcfm_restriction_message_show( 'Delivery Hubs' );
	return;
}
?>

<div class="collapse wcfm-collapse" id="wcfm_delivery_hubs_listing">
	<div class="wcfm-page-headig">
		<span class="wcfmfa fa-map-marker-alt"></span>
		<span class="wcfm-page-heading-text"><?php _e( 'Delivery Hub', 'wc-frontend-manager-delivery' ); ?></span>
		<?php do_action( 'wcfm_page_heading' ); ?>
	</div>
	<div class="wcfm-collapse-content">
		<div id="wcfm_page_load"></div>

		<div class="wcfm-container wcfm-top-element-container">
			<h2><?php _e( 'Manage Delivery Hubs', 'wc-frontend-manager-delivery' ); ?></h2>
			<a href="#" id="wcfmd-hub-add-btn" class="add_new_wcfm_ele_dashboard text_tip" data-tip="<?php esc_attr_e( 'Add New Hub', 'wc-frontend-manager-delivery' ); ?>">
				<span class="wcfmfa fa-plus-circle"></span>
				<span class="text"><?php _e( 'Add New', 'wc-frontend-manager' ); ?></span>
			</a>
			<div class="wcfm-clearfix"></div>
		</div>
		<div class="wcfm-clearfix"></div><br />

		<div id="wcfmd-hub-page-msg" style="display:none;padding:10px 14px;border-radius:4px;margin-bottom:14px;font-size:13px;"></div>

		<div class="wcfm-container">
			<div id="wcfm_delivery_hubs_expander" class="wcfm-content">
				<table id="wcfmd-delivery-hubs" class="display" cellspacing="0" width="100%">
					<thead>
						<tr>
							<th><?php _e( '#', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Hub Name', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Area / Zone', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Address', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Status', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Created', 'wc-frontend-manager-delivery' ); ?></th>
							<th><?php _e( 'Actions', 'wc-frontend-manager-delivery' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>

		<!-- Overlay -->
		<div id="wcfmd-hub-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99998;"></div>

		<!-- Add / Edit Modal -->
		<div id="wcfmd-hub-modal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:520px;max-width:96%;background:#fff;border-radius:6px;z-index:99999;box-shadow:0 8px 32px rgba(0,0,0,0.25);overflow:hidden;">
			<div style="background:#1e2d40;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:1;">
				<h3 id="wcfmd-hub-modal-title" style="margin:0;font-size:15px;font-weight:600;color:#fff !important;"><?php _e( 'Add Delivery Hub', 'wc-frontend-manager-delivery' ); ?></h3>
				<a href="#" id="wcfmd-hub-modal-close" style="color:#fff !important;font-size:22px;text-decoration:none;line-height:1;">&times;</a>
			</div>
			<div style="padding:24px;max-height:80vh;overflow-y:auto;">
				<input type="hidden" id="wcfmd-hub-id" value="" />

				<div id="wcfmd-hub-modal-msg" style="display:none;padding:8px 12px;border-radius:4px;margin-bottom:14px;font-size:13px;"></div>

				<table style="width:100%;border-collapse:collapse;">
					<tr>
						<td style="width:130px;font-weight:600;padding:8px 0;vertical-align:middle;"><?php _e( 'Hub Name', 'wc-frontend-manager-delivery' ); ?> <span style="color:red;">*</span></td>
						<td style="padding:6px 0;">
							<input type="text" id="wcfmd-hub-name" class="wcfm-text" style="width:100%;box-sizing:border-box;" placeholder="<?php esc_attr_e( 'e.g. North Zone Hub', 'wc-frontend-manager-delivery' ); ?>" />
						</td>
					</tr>
					<tr>
						<td style="font-weight:600;padding:8px 0;vertical-align:middle;"><?php _e( 'Area / Zone', 'wc-frontend-manager-delivery' ); ?></td>
						<td style="padding:6px 0;">
							<input type="text" id="wcfmd-hub-area" class="wcfm-text" style="width:100%;box-sizing:border-box;" placeholder="<?php esc_attr_e( 'e.g. North Chennai', 'wc-frontend-manager-delivery' ); ?>" />
						</td>
					</tr>
					<tr>
						<td style="font-weight:600;padding:8px 0;vertical-align:top;padding-top:14px;"><?php _e( 'Address', 'wc-frontend-manager-delivery' ); ?></td>
						<td style="padding:6px 0;">
							<textarea id="wcfmd-hub-address" class="wcfm-textarea" style="width:100%;height:70px;box-sizing:border-box;" placeholder="<?php esc_attr_e( 'Full address of the hub', 'wc-frontend-manager-delivery' ); ?>"></textarea>
						</td>
					</tr>
					<tr>
						<td style="font-weight:600;padding:8px 0;vertical-align:middle;"><?php _e( 'Status', 'wc-frontend-manager-delivery' ); ?></td>
						<td style="padding:6px 0;">
							<select id="wcfmd-hub-status" class="wcfm-select" style="width:100%;">
								<option value="active"><?php _e( 'Active', 'wc-frontend-manager-delivery' ); ?></option>
								<option value="inactive"><?php _e( 'Inactive', 'wc-frontend-manager-delivery' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<div style="margin-top:20px;text-align:right;">
					<button id="wcfmd-hub-save" style="background:#1e2d40;color:#fff;border:none;padding:8px 22px;border-radius:4px;font-size:12px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;cursor:pointer;">
						<?php _e( 'SAVE HUB', 'wc-frontend-manager-delivery' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div>
</div>
