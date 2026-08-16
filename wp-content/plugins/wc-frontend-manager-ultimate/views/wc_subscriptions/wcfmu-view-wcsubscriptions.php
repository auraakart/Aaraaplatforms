<?php
/**
 * WCFM plugin views
 *
 * Plugin WC Subscriptions List Views
 *
 * @author      WC Lovers
 * @package     wcfmu/views/wc_subscriptions
 * @version   4.0.7
 */

global $WCFM, $WCFMu;

if ( ! apply_filters('wcfm_is_allow_subscriptions', true) || ! apply_filters('wcfm_is_allow_subscription_list', true) ) {
    wcfm_restriction_message_show('Subscription');
    return;
}

$wcfmu_subscriptions_menus = apply_filters(
    'wcfmu_subscriptions_menus',
    array(
        'all'       => __('All', 'wc-frontend-manager-ultimate'),
        'active'    => __('Active', 'wc-frontend-manager-ultimate'),
        'on-hold'   => __('On Hold', 'wc-frontend-manager-ultimate'),
        'cancelled' => __('Cancelled', 'wc-frontend-manager-ultimate'),
        'expired'   => __('Expired', 'wc-frontend-manager-ultimate'),
    )
);

$subscription_status = ! empty($_GET['subscription_status']) ? sanitize_text_field($_GET['subscription_status']) : 'all';

?>
<div class="collapse wcfm-collapse" id="wcfm_subscriptions_listing">
  <div class="wcfm-page-headig">
        <span class="wcfmfa fa-money-bill-alt"></span>
        <span class="wcfm-page-heading-text"><?php _e('Subscriptions List', 'wc-frontend-manager-ultimate'); ?></span>
        <?php do_action('wcfm_page_heading'); ?>
    </div>
    <div class="wcfm-collapse-content">
      <div id="wcfm_page_load"></div>
      
      <div class="wcfm-container wcfm-top-element-container">
            <ul class="wcfm_subscriptions_menus">
                <?php
                $is_first = true;
                foreach ( $wcfmu_subscriptions_menus as $wcfmu_subscriptions_menu_key => $wcfmu_subscriptions_menu ) {
                    ?>
                    <li class="wcfm_subscriptions_menu_item">
                        <?php
                        if ( $is_first ) {
                            $is_first = false;
                        } else {
                            echo ' | ';
                        }
                        ?>
                        <a class="<?php echo ( $wcfmu_subscriptions_menu_key == $subscription_status ) ? 'active' : ''; ?>" href="<?php echo get_wcfm_subscriptions_url($wcfmu_subscriptions_menu_key); ?>"><?php echo $wcfmu_subscriptions_menu; ?></a>
                    </li>
                    <?php
                }
                ?>
            </ul>
            
            <?php
            if ( $allow_wp_admin_view = apply_filters('wcfm_allow_wp_admin_view', true) ) {
                ?>
                <a class="wcfm_screen_manager text_tip" href="#" data-screen="subscription" data-tip="<?php _e('Screen Manager', 'wc-frontend-manager-ultimate'); ?>"><span class="wcfmfa fa-tv"></span></a>
                <a target="_blank" class="wcfm_wp_admin_view text_tip" href="<?php echo admin_url('edit.php?post_type=shop_subscription'); ?>" data-tip="<?php _e('WP Admin View', 'wc-frontend-manager'); ?>"><span class="fab fa-wordpress fa-wordpress-simple"></span></a>
                <?php
            }
            ?>
            <?php if ( apply_filters( 'wcfm_is_allow_subscription_create', current_user_can( 'manage_woocommerce' ) ) ) : ?>
            <a href="#" id="wcfmu-sub-create-btn" class="wcfm-btn btn-primary" style="float:right;display:inline-flex;align-items:center;gap:5px;margin-top:2px;">
                <span class="wcfmfa fa-plus"></span> <?php _e( 'Create Subscription', 'wc-frontend-manager-ultimate' ); ?>
            </a>
            <?php endif; ?>
            <div class="wcfm-clearfix"></div>
        </div>
      <div class="wcfm-clearfix"></div><br />
        
        <?php do_action('before_wcfm_subscriptions'); ?>
        
        <div class="wcfm_subscription_filter_wrap wcfm_filters_wrap">
          <?php
            $WCFM->wcfm_fields->wcfm_generate_form_field(
                array(
                    'subscription_product' => array(
                        'type'        => 'select',
                        'attributes'  => array( 'style' => 'width: 250px; margin-left: 25px;' ),
                        'class'       => 'wcfm-select wcfm_ele',
                        'label_class' => 'wcfm_title',
                        'options'     => array(),
                    ),
                )
            );
            ?>
          <?php $WCFM->library->wcfm_date_range_picker_field(); ?>
        </div>
    
        <style>
        #wcfm-subscriptions td:first-child input[type="checkbox"],
        #wcfm-subscriptions th input[type="checkbox"] {
            -webkit-appearance: checkbox !important;
            appearance: checkbox !important;
            display: inline-block !important;
            width: 16px !important;
            height: 16px !important;
            cursor: pointer !important;
            opacity: 1 !important;
            position: static !important;
            margin: 0 !important;
        }
        </style>

        <div id="wcfm-subs-bulk-bar" style="display:none;margin-bottom:10px;padding:8px 12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;align-items:center;gap:10px;flex-wrap:wrap;">
            <span id="wcfm-subs-selected-count" style="font-weight:600;">0 <?php _e( 'selected', 'wc-frontend-manager-ultimate' ); ?></span>
            <select id="wcfm-subs-bulk-status" style="padding:4px 8px;border-radius:3px;border:1px solid #ccc;">
                <option value=""><?php _e( '— Change Status —', 'wc-frontend-manager-ultimate' ); ?></option>
                <option value="active"><?php _e( 'Active', 'wc-frontend-manager-ultimate' ); ?></option>
                <option value="on-hold"><?php _e( 'On Hold', 'wc-frontend-manager-ultimate' ); ?></option>
                <option value="cancelled"><?php _e( 'Cancelled', 'wc-frontend-manager-ultimate' ); ?></option>
                <option value="expired"><?php _e( 'Expired', 'wc-frontend-manager-ultimate' ); ?></option>
                <option value="pending-cancel"><?php _e( 'Pending Cancellation', 'wc-frontend-manager-ultimate' ); ?></option>
            </select>
            <button id="wcfm-subs-bulk-apply" class="wcfm-btn btn-success" style="padding:4px 14px;"><?php _e( 'Apply', 'wc-frontend-manager-ultimate' ); ?></button>
            <span id="wcfm-subs-bulk-result" style="margin-left:8px;"></span>
        </div>

        <div class="wcfm-container">
            <div id="wwcfm_subscriptions_listing_expander" class="wcfm-content">
                <table id="wcfm-subscriptions" class="display" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="wcfm-subs-check-all" style="vertical-align:middle;"></th>
                            <th><span class="wcicon-status-processing text_tip" data-tip="<?php _e('Status', 'wc-frontend-manager-ultimate'); ?>"></span></th>
                            <th><?php _e('Subscription', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Order', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Items', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Total', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Start Date', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Trial End', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Next Payment', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Last Order', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('End Date', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e(apply_filters('wcfm_subscriptions_additional_info_column_label', __('Additional Info', 'wc-frontend-manager-ultimate'))); ?></th>
                            <th><?php _e('Actions', 'wc-frontend-manager-ultimate'); ?></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th></th>
                            <th><span class="wcicon-status-processing text_tip" data-tip="<?php _e('Status', 'wc-frontend-manager-ultimate'); ?>"></span></th>
                            <th><?php _e('Subscription', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Order', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Items', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Total', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Start Date', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Trial End', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Next Payment', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('Last Order', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e('End Date', 'wc-frontend-manager-ultimate'); ?></th>
                            <th><?php _e(apply_filters('wcfm_subscriptions_additional_info_column_label', __('Additional Info', 'wc-frontend-manager-ultimate'))); ?></th>
                            <th><?php _e('Actions', 'wc-frontend-manager-ultimate'); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <div class="wcfm-clearfix"></div>
            </div>
        </div>
        <?php
        do_action('after_wcfm_subscriptions');
        ?>
    </div>
</div>

<?php if ( apply_filters( 'wcfm_is_allow_subscription_create', current_user_can( 'manage_woocommerce' ) ) ) : ?>

<!-- ===== Create Subscription Overlay ===== -->
<div id="wcfmu-sub-create-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:99998;"></div>

<!-- ===== Create Subscription Modal ===== -->
<div id="wcfmu-sub-create-modal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:640px;max-width:96%;background:#fff;border-radius:6px;overflow:hidden;z-index:99999;box-shadow:0 10px 40px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;">
    <div style="background:#1e2d40;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:1;">
        <h3 style="margin:0;font-size:15px;font-weight:600;color:#fff !important;"><?php _e( 'Create Subscription', 'wc-frontend-manager-ultimate' ); ?></h3>
        <a href="#" class="wcfmu-sub-modal-close" style="color:#fff !important;font-size:22px;text-decoration:none;line-height:1;">&times;</a>
    </div>
    <div style="padding:24px;">
        <table style="width:100%;border-collapse:collapse;">

            <!-- Customer -->
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px 10px 12px 0;width:36%;font-weight:600;vertical-align:top;">
                    <?php _e( 'Customer', 'wc-frontend-manager-ultimate' ); ?>
                    <span style="color:#e00;">*</span>
                </td>
                <td style="padding:12px 0;">
                    <div style="position:relative;">
                        <input type="text" id="wcfmu-sub-customer-search" autocomplete="off" placeholder="<?php _e( 'Search by name or email...', 'wc-frontend-manager-ultimate' ); ?>"
                            style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box;">
                        <input type="hidden" id="wcfmu-sub-customer-id">
                        <div id="wcfmu-sub-customer-results" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;max-height:180px;overflow-y:auto;z-index:9999;border-radius:0 0 4px 4px;box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
                    </div>
                    <small style="color:#888;display:block;margin-top:4px;"><?php _e( 'Type at least 2 characters to search', 'wc-frontend-manager-ultimate' ); ?></small>
                </td>
            </tr>

            <!-- Product -->
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px 10px 12px 0;font-weight:600;vertical-align:top;">
                    <?php _e( 'Product', 'wc-frontend-manager-ultimate' ); ?>
                    <span style="color:#e00;">*</span>
                </td>
                <td style="padding:12px 0;">
                    <div style="position:relative;">
                        <input type="text" id="wcfmu-sub-product-search" autocomplete="off" placeholder="<?php _e( 'Search subscription product...', 'wc-frontend-manager-ultimate' ); ?>"
                            style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box;">
                        <input type="hidden" id="wcfmu-sub-product-id">
                        <div id="wcfmu-sub-product-results" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;max-height:180px;overflow-y:auto;z-index:9999;border-radius:0 0 4px 4px;box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
                    </div>
                </td>
            </tr>

            <!-- Quantity -->
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px 10px 12px 0;font-weight:600;"><?php _e( 'Quantity', 'wc-frontend-manager-ultimate' ); ?></td>
                <td style="padding:12px 0;">
                    <input type="number" id="wcfmu-sub-quantity" value="1" min="1" step="1"
                        style="width:100px;padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                </td>
            </tr>

            <!-- Schedule Type -->
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px 10px 12px 0;font-weight:600;vertical-align:top;">
                    <?php _e( 'Schedule', 'wc-frontend-manager-ultimate' ); ?>
                    <span style="color:#e00;">*</span>
                </td>
                <td style="padding:12px 0;">
                    <label style="display:block;margin-bottom:8px;cursor:pointer;">
                        <input type="radio" name="wcfmu_sub_schedule" value="daily" style="margin-right:6px;">
                        <strong><?php _e( 'Every Day', 'wc-frontend-manager-ultimate' ); ?></strong>
                    </label>
                    <label style="display:block;margin-bottom:8px;cursor:pointer;">
                        <input type="radio" name="wcfmu_sub_schedule" value="alternate" style="margin-right:6px;">
                        <strong><?php _e( 'Alternative Day', 'wc-frontend-manager-ultimate' ); ?></strong>
                    </label>
                    <label style="display:block;margin-bottom:8px;cursor:pointer;">
                        <input type="radio" name="wcfmu_sub_schedule" value="weekend" style="margin-right:6px;">
                        <strong><?php _e( 'Every Weekend', 'wc-frontend-manager-ultimate' ); ?></strong>
                        <small style="color:#888;"><?php _e( '(Sat &amp; Sun)', 'wc-frontend-manager-ultimate' ); ?></small>
                    </label>
                    <label style="display:block;cursor:pointer;">
                        <input type="radio" name="wcfmu_sub_schedule" value="custom" style="margin-right:6px;">
                        <strong><?php _e( 'Custom', 'wc-frontend-manager-ultimate' ); ?></strong>
                    </label>

                    <!-- Custom day checkboxes (hidden until Custom selected) -->
                    <div id="wcfmu-sub-custom-days" style="display:none;margin-top:10px;padding:10px 12px;background:#f9f9f9;border:1px solid #e5e5e5;border-radius:4px;">
                        <small style="display:block;margin-bottom:8px;color:#555;"><?php _e( 'Select delivery days:', 'wc-frontend-manager-ultimate' ); ?></small>
                        <?php
                        $days = [
                            1 => __( 'Monday', 'wc-frontend-manager-ultimate' ),
                            2 => __( 'Tuesday', 'wc-frontend-manager-ultimate' ),
                            3 => __( 'Wednesday', 'wc-frontend-manager-ultimate' ),
                            4 => __( 'Thursday', 'wc-frontend-manager-ultimate' ),
                            5 => __( 'Friday', 'wc-frontend-manager-ultimate' ),
                            6 => __( 'Saturday', 'wc-frontend-manager-ultimate' ),
                            0 => __( 'Sunday', 'wc-frontend-manager-ultimate' ),
                        ];
                        foreach ( $days as $val => $label ) :
                        ?>
                        <label style="display:inline-block;margin-right:14px;margin-bottom:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" class="wcfmu-sub-day-check" value="<?php echo $val; ?>" style="margin-right:4px;-webkit-appearance:checkbox !important;appearance:checkbox !important;display:inline-block !important;width:15px !important;height:15px !important;opacity:1 !important;position:static !important;vertical-align:middle;">
                            <?php echo $label; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>

            <!-- Start Date -->
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px 10px 12px 0;font-weight:600;"><?php _e( 'Start Date', 'wc-frontend-manager-ultimate' ); ?></td>
                <td style="padding:12px 0;">
                    <input type="date" id="wcfmu-sub-start-date"
                        style="padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                    <small style="color:#888;display:block;margin-top:4px;"><?php _e( 'Leave blank for today', 'wc-frontend-manager-ultimate' ); ?></small>
                </td>
            </tr>

            <!-- Payment Method -->
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px 10px 12px 0;font-weight:600;"><?php _e( 'Payment Method', 'wc-frontend-manager-ultimate' ); ?></td>
                <td style="padding:12px 0;">
                    <select id="wcfmu-sub-payment-method" style="padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;width:100%;">
                        <option value=""><?php _e( '— Select —', 'wc-frontend-manager-ultimate' ); ?></option>
                        <?php
                        $gateways = WC()->payment_gateways ? WC()->payment_gateways->get_available_payment_gateways() : [];
                        foreach ( $gateways as $gw ) {
                            echo '<option value="' . esc_attr( $gw->id ) . '">' . esc_html( $gw->get_title() ) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <!-- Status -->
            <tr>
                <td style="padding:12px 10px 12px 0;font-weight:600;"><?php _e( 'Status', 'wc-frontend-manager-ultimate' ); ?></td>
                <td style="padding:12px 0;">
                    <select id="wcfmu-sub-status" style="padding:9px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                        <option value="active"><?php _e( 'Active', 'wc-frontend-manager-ultimate' ); ?></option>
                        <option value="pending"><?php _e( 'Pending', 'wc-frontend-manager-ultimate' ); ?></option>
                        <option value="on-hold"><?php _e( 'On Hold', 'wc-frontend-manager-ultimate' ); ?></option>
                    </select>
                </td>
            </tr>

        </table>

        <div id="wcfmu-sub-create-msg" style="display:none;margin-top:14px;padding:9px 12px;border-radius:4px;font-size:13px;"></div>

        <div style="text-align:center;margin-top:20px;">
            <button id="wcfmu-sub-create-submit" style="background:#1e2d40;color:#fff;border:none;padding:8px 22px;border-radius:4px;font-size:12px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;cursor:pointer;">
                <?php _e( 'CREATE SUBSCRIPTION', 'wc-frontend-manager-ultimate' ); ?>
            </button>
        </div>
    </div>
</div>

<?php endif; ?>
