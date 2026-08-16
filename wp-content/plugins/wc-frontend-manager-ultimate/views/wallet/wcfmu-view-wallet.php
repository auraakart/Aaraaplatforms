<?php
/**
 * WCFMu plugin views
 *
 * Wallet System List View
 *
 * @author      WC Lovers
 * @package     wcfmu/views/wallet
 * @version     1.0.1
 */

global $WCFM, $WCFMu;

if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wcfm_restriction_message_show( 'Wallet' );
    return;
}
?>
<div class="collapse wcfm-collapse" id="wcfm_wallet_listing">
    <div class="wcfm-page-headig">
        <span class="wcfmfa fa-wallet"></span>
        <span class="wcfm-page-heading-text"><?php _e( 'Wallet', 'wc-frontend-manager-ultimate' ); ?></span>
        <?php do_action( 'wcfm_page_heading' ); ?>
    </div>
    <div class="wcfm-collapse-content">
        <div id="wcfm_page_load"></div>

        <div class="wcfm-container wcfm-top-element-container" style="overflow:hidden;">
            <h2 style="float:left;margin:0;line-height:36px;"><?php _e( 'Wallet Management', 'wc-frontend-manager-ultimate' ); ?></h2>
            <div style="float:right;">
                <a href="#" id="wcfmu-wallet-add-btn" class="wcfm-btn btn-primary" style="display:inline-flex;align-items:center;gap:5px;">
                    <span class="wcfmfa fa-plus"></span> <?php _e( 'Add Wallet', 'wc-frontend-manager-ultimate' ); ?>
                </a>
            </div>
            <div class="wcfm-clearfix"></div>
        </div>
        <div class="wcfm-clearfix"></div><br />

        <!-- Tabs -->
        <div class="wcfmu-wallet-tabs" style="margin-bottom:0;">
            <a href="#" class="wcfmu-wallet-tab active" data-tab="users" style="display:inline-block;padding:8px 20px;background:#fff;border:1px solid #ddd;border-bottom:none;margin-right:4px;font-weight:600;color:#23282d;text-decoration:none;border-radius:3px 3px 0 0;"><?php _e( 'Wallet List', 'wc-frontend-manager-ultimate' ); ?></a>
            <a href="#" class="wcfmu-wallet-tab" data-tab="transactions" style="display:inline-block;padding:8px 20px;background:#f1f1f1;border:1px solid #ddd;border-bottom:none;margin-right:4px;color:#555;text-decoration:none;border-radius:3px 3px 0 0;"><?php _e( 'Wallet Transactions', 'wc-frontend-manager-ultimate' ); ?></a>
        </div>

        <!-- Tab: Wallet Users -->
        <div id="wcfmu-wallet-tab-users" class="wcfmu-wallet-tab-content wcfm-container" style="border-top:1px solid #ddd;">
            <div class="wcfm-content">
                <table id="wcfmu-wallet-users" class="display" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th><?php _e( 'User ID', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Name', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Email', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Wallet Balance', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Actions', 'wc-frontend-manager-ultimate' ); ?></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th><?php _e( 'User ID', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Name', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Email', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Wallet Balance', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Actions', 'wc-frontend-manager-ultimate' ); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <div class="wcfm-clearfix"></div>
            </div>
        </div>

        <!-- Tab: Wallet Transactions -->
        <div id="wcfmu-wallet-tab-transactions" class="wcfmu-wallet-tab-content wcfm-container" style="display:none;border-top:1px solid #ddd;">
            <div class="wcfm-content">
                <div id="wcfmu-wallet-user-filter" style="display:none;margin-bottom:10px;padding:8px 12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
                    <?php _e( 'Showing transactions for:', 'wc-frontend-manager-ultimate' ); ?>
                    <strong id="wcfmu-wallet-filter-name"></strong>
                    &nbsp;<a href="#" id="wcfmu-wallet-clear-filter" style="color:#dc3545;">[<?php _e( 'Clear Filter', 'wc-frontend-manager-ultimate' ); ?>]</a>
                </div>
                <table id="wcfmu-wallet-transactions" class="display" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th><?php _e( '#', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'User', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Amount', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Type', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Transaction Type', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Payment Method', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Note', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Date', 'wc-frontend-manager-ultimate' ); ?></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th><?php _e( '#', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'User', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Amount', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Type', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Transaction Type', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Payment Method', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Note', 'wc-frontend-manager-ultimate' ); ?></th>
                            <th><?php _e( 'Date', 'wc-frontend-manager-ultimate' ); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <div class="wcfm-clearfix"></div>
            </div>
        </div>

    </div><!-- .wcfm-collapse-content -->
</div><!-- .collapse -->

<!-- ===== Edit Wallet Modal ===== -->
<div id="wcfmu-wallet-edit-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:99998;"></div>
<div id="wcfmu-wallet-edit-modal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:480px;max-width:95%;background:#fff;border-radius:6px;overflow:hidden;z-index:99999;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <div style="background:#1e2d40;color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;">
        <h3 id="wcfmu-wallet-edit-title" style="margin:0;font-size:15px;font-weight:600;">Update Wallet</h3>
        <a href="#" class="wcfmu-modal-close" data-modal="edit" style="color:#fff;font-size:22px;text-decoration:none;line-height:1;">&times;</a>
    </div>
    <div style="padding:24px;">
        <input type="hidden" id="wcfmu-wallet-edit-user-id">
        <table style="width:100%;border-collapse:collapse;">
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:14px 10px 14px 0;width:38%;font-weight:600;vertical-align:top;">Amount (&#x20B9;)</td>
                <td style="padding:14px 0;">
                    <input type="number" id="wcfmu-wallet-edit-amount" min="0.01" step="0.01" placeholder="0.00"
                        style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;">
                    <small style="color:#9b59b6;display:block;margin-top:4px;">Certain amount want to add/deduct from all users wallet</small>
                </td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:14px 10px 14px 0;font-weight:600;">Action</td>
                <td style="padding:14px 0;">
                    <label style="margin-right:24px;cursor:pointer;">
                        <input type="radio" name="wcfmu_edit_action" value="credit" style="margin-right:5px;">
                        <span style="color:#9b59b6;font-weight:500;">Credit</span>
                    </label>
                    <label style="cursor:pointer;">
                        <input type="radio" name="wcfmu_edit_action" value="debit" style="margin-right:5px;">
                        <span style="color:#9b59b6;font-weight:500;">Debit</span>
                    </label>
                </td>
            </tr>
            <tr>
                <td style="padding:14px 10px 14px 0;font-weight:600;vertical-align:top;">Transaction Detail</td>
                <td style="padding:14px 0;">
                    <textarea id="wcfmu-wallet-edit-note" rows="3" placeholder=""
                        style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
                    <small style="color:#9b59b6;display:block;margin-top:4px;">Enter the details you want to show to user</small>
                </td>
            </tr>
        </table>
        <div id="wcfmu-wallet-edit-msg" style="display:none;margin-top:12px;padding:9px 12px;border-radius:4px;font-size:13px;"></div>
        <div style="text-align:center;margin-top:18px;">
            <button id="wcfmu-wallet-edit-submit" style="background:#1e2d40;color:#fff;border:none;padding:11px 36px;border-radius:4px;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;">
                UPDATE WALLET
            </button>
        </div>
    </div>
</div>

<!-- ===== Add Wallet Modal ===== -->
<div id="wcfmu-wallet-add-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:99998;"></div>
<div id="wcfmu-wallet-add-modal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:480px;max-width:95%;background:#fff;border-radius:6px;overflow:hidden;z-index:99999;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <div style="background:#1e2d40;color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;font-size:15px;font-weight:600;"><?php _e( 'Add Wallet', 'wc-frontend-manager-ultimate' ); ?></h3>
        <a href="#" class="wcfmu-modal-close" data-modal="add" style="color:#fff;font-size:22px;text-decoration:none;line-height:1;">&times;</a>
    </div>
    <div style="padding:24px;">
        <table style="width:100%;border-collapse:collapse;">
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:14px 10px 14px 0;width:38%;font-weight:600;vertical-align:top;">Customer</td>
                <td style="padding:14px 0;">
                    <div style="position:relative;">
                        <input type="text" id="wcfmu-wallet-user-search" placeholder="Search by name or email..."
                            style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;"
                            autocomplete="off">
                        <input type="hidden" id="wcfmu-wallet-add-user-id">
                        <div id="wcfmu-wallet-user-results"
                            style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;max-height:180px;overflow-y:auto;z-index:9999;border-radius:0 0 4px 4px;box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
                    </div>
                    <small style="color:#9b59b6;display:block;margin-top:4px;">Type at least 2 characters to search</small>
                </td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:14px 10px 14px 0;font-weight:600;vertical-align:top;">Amount (&#x20B9;)</td>
                <td style="padding:14px 0;">
                    <input type="number" id="wcfmu-wallet-add-amount" min="0.01" step="0.01" placeholder="0.00"
                        style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;">
                    <small style="color:#9b59b6;display:block;margin-top:4px;">Certain amount want to add/deduct from all users wallet</small>
                </td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:14px 10px 14px 0;font-weight:600;">Action</td>
                <td style="padding:14px 0;">
                    <label style="margin-right:24px;cursor:pointer;">
                        <input type="radio" name="wcfmu_add_action" value="credit" style="margin-right:5px;">
                        <span style="color:#9b59b6;font-weight:500;">Credit</span>
                    </label>
                    <label style="cursor:pointer;">
                        <input type="radio" name="wcfmu_add_action" value="debit" style="margin-right:5px;">
                        <span style="color:#9b59b6;font-weight:500;">Debit</span>
                    </label>
                </td>
            </tr>
            <tr>
                <td style="padding:14px 10px 14px 0;font-weight:600;vertical-align:top;">Transaction Detail</td>
                <td style="padding:14px 0;">
                    <textarea id="wcfmu-wallet-add-note" rows="3" placeholder=""
                        style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
                    <small style="color:#9b59b6;display:block;margin-top:4px;">Enter the details you want to show to user</small>
                </td>
            </tr>
        </table>
        <div id="wcfmu-wallet-add-msg" style="display:none;margin-top:12px;padding:9px 12px;border-radius:4px;font-size:13px;"></div>
        <div style="text-align:center;margin-top:18px;">
            <button id="wcfmu-wallet-add-submit" style="background:#1e2d40;color:#fff;border:none;padding:11px 36px;border-radius:4px;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;">
                UPDATE WALLET
            </button>
        </div>
    </div>
</div>
