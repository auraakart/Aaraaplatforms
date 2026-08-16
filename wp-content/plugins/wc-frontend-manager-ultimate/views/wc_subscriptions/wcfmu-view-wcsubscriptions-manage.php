<?php
/**
 * WCFM plugin views
 *
 * Plugin WC Subscription Details Views
 *
 * @author      WC Lovers
 * @package     wcfmu/views
 * @version   2.2.1
 */

global $wp, $WCFM, $WCFMu, $wpdb;

if ( ! apply_filters('wcfm_is_allow_subscription_details', true) ) {
    wcfm_restriction_message_show('Subscription Manage');
    return;
}

if ( isset($wp->query_vars['wcfm-subscriptions-manage']) && ! empty($wp->query_vars['wcfm-subscriptions-manage']) ) {
    $the_subscription = wcs_get_subscription($wp->query_vars['wcfm-subscriptions-manage']);
} else {
    wcfm_restriction_message_show('No Subscription');
    return;
}

if ( ! $the_subscription instanceof WC_Subscription ) {
    wcfm_restriction_message_show('No Subscription');
    return;
}

$subscription_id      = $wp->query_vars['wcfm-subscriptions-manage'];
$subscripton_post     = get_post($subscription_id);
$current_sub_status   = str_replace('wc-', '', $subscripton_post->post_status);
$order            = wc_get_order($subscripton_post->post_parent);
// Imported/renewal-less subscriptions have no parent order (post_parent = 0).
// The subscription object itself carries the customer + billing address, so fall
// back to it; without this $order stays false and $order->get_user_id() fatals.
if ( ! $order instanceof WC_Order ) {
    $order = $the_subscription;
}

$line_items          = $the_subscription->get_items();
$line_items_fee      = $the_subscription->get_items('fee');
$line_items_shipping = $the_subscription->get_items('shipping');

$order_taxes = $classes_options = array();
if ( $is_wcfm_order_details_tax_line_item = apply_filters('wcfm_order_details_tax_line_item', true) ) {
    if ( wc_tax_enabled() ) {
        $order_taxes         = $the_subscription->get_taxes();
        $tax_classes         = WC_Tax::get_tax_classes();
        $classes_options[''] = __('Standard', 'wc-frontend-manager');

        if ( ! empty($tax_classes) ) {
            foreach ( $tax_classes as $class ) {
                $classes_options[ sanitize_title($class) ] = $class;
            }
        }

        // Older orders won't have line taxes so we need to handle them differently :(
        $tax_data = '';
        if ( $line_items ) {
            $check_item = current($line_items);
            $tax_data   = maybe_unserialize(isset($check_item['line_tax_data']) ? $check_item['line_tax_data'] : '');
        } elseif ( $line_items_shipping ) {
            $check_item = current($line_items_shipping);
            $tax_data   = maybe_unserialize(isset($check_item['taxes']) ? $check_item['taxes'] : '');
        } elseif ( $line_items_fee ) {
            $check_item = current($line_items_fee);
            $tax_data   = maybe_unserialize(isset($check_item['line_tax_data']) ? $check_item['line_tax_data'] : '');
        }

        $legacy_order     = ! empty($order_taxes) && empty($tax_data) && ! is_array($tax_data);
        $show_tax_columns = ! $legacy_order || sizeof($order_taxes) === 1;
    }
}

$WCFM->library->init_address_fields($order, 'view');

if ( $subscripton_post->post_status == 'wc-cancelled' ) {
    $statuses = apply_filters(
        'wcfmu_subscriptions_status',
        array(
            'cancelled' => __('Cancelled', 'wc-frontend-manager-ultimate'),
        )
    );
} elseif ( $subscripton_post->post_status == 'wc-expired' ) {
    $statuses = apply_filters(
        'wcfmu_subscriptions_status',
        array(
            'expired' => __('Expired', 'wc-frontend-manager-ultimate'),
        )
    );
} else {
    $statuses = apply_filters(
        'wcfmu_subscriptions_status',
        array(
            'active'    => __('Active', 'wc-frontend-manager-ultimate'),
            'on-hold'   => __('On Hold', 'wc-frontend-manager-ultimate'),
            'pause'     => __('Paused', 'wc-frontend-manager-ultimate'),
            'cancelled' => __('Cancelled', 'wc-frontend-manager-ultimate'),
            'expired'   => __('Expired', 'wc-frontend-manager-ultimate'),
        )
    );
}

do_action('before_wcfm_subscriptions_details');
?>

<div class="collapse wcfm-collapse" id="wcfm_subscription_details">

  <div class="wcfm-page-headig">
        <span class="wcfmfa fa-money-bill-alt"></span>
        <span class="wcfm-page-heading-text"><?php _e('Subscription Details', 'wc-frontend-manager-ultimate'); ?></span>
        <?php do_action('wcfm_page_heading'); ?>
    </div>
    <div class="wcfm-collapse-content">
        <div id="wcfm_page_load"></div>
        
        <div class="wcfm-container wcfm-top-element-container">
            <h2>
            <?php
            _e('Subscription #', 'wc-frontend-manager-ultimate');
            echo $subscription_id;
            ?>
            </h2>
            <span class="subscription-status subscription-status-<?php echo sanitize_title(str_replace('wc-', '', $subscripton_post->post_status)); ?>"><?php _e(ucfirst(str_replace('wc-', '', $subscripton_post->post_status)), 'wc-frontend-manager-ultimate'); ?></span>
            
            <?php
            if ( $allow_wp_admin_view = apply_filters('wcfm_allow_wp_admin_view', true) ) {
                ?>
                <a target="_blank" class="wcfm_wp_admin_view text_tip" href="<?php echo admin_url('post.php?post=' . $subscription_id . '&action=edit'); ?>" data-tip="<?php _e('WP Admin View', 'wc-frontend-manager-ultimate'); ?>"><span class="fab fa-wordpress fa-wordpress-simple"></span></a>
                <?php
            }

            echo '<a class="add_new_wcfm_ele_dashboard text_tip" href="' . get_wcfm_subscriptions_url() . '" data-tip="' . __('Subscriptions List', 'wc-frontend-manager-ultimate') . '"><span class="wcfmfa fa-money"></span></a>';
            ?>
            <div class="wcfm_clearfix"></div>
        </div>
      <div class="wcfm-clearfix"></div><br />
      
      <?php do_action('begin_wcfm_subscriptions_details', $subscription_id); ?>
        
        <!-- collapsible -->
        <div class="page_collapsible subscriptions_details_general" id="wcfm_general_options">
            <?php _e('Overview', 'wc-frontend-manager-ultimate'); ?><span></span>
        </div>
        <div class="wcfm-container">
            <div id="subscriptions_details_general_expander" class="wcfm-content">
    
                <p class="form-field form-field-wide">
                    <label for="subscription_date"><?php _e('Subscription Created:', 'wc-frontend-manager-ultimate'); ?></label>
                    <?php echo date_i18n(wc_date_format() . ' @' . wc_time_format(), strtotime($subscripton_post->date_created)); ?>
                </p>
                
                <p class="form-field form-field-wide">
                    <label for="subscription_date"><?php _e('Order Number:', 'wc-frontend-manager-ultimate'); ?></label>
                    <?php
                    if ( $order ) {
                        if ( apply_filters('wcfm_is_allow_order_details', true) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($order->get_id()) ) {
                            echo '<span class="subscription-orderno"><a href="' . get_wcfm_view_order_url($order->get_id(), $order) . '">#' . $order->get_id() . '</a></span> &ndash; ' . esc_html(wc_get_order_status_name($order->get_status())) . ' (' . date_i18n(wc_date_format(), strtotime($order->get_date_created())) . ')';
                        } else {
                            echo '<span class="subscription-orderno">#' . $order->get_id() . ' - ' . esc_html(wc_get_order_status_name($order->get_status())) . '</span>';
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </p>
                
                <?php if ( apply_filters('wcfm_is_allow_subscription_status_update', true) ) { ?>
                    <div id="wcfm_subscription_status_update_wrapper" class="wcfm_subscription_status_update_wrapper">
                        <p class="form-field form-field-wide">
                            <label for="wcfm_subscription_status"><?php _e('Subscription Status:', 'wc-frontend-manager-ultimate'); ?></label>
                            <select id="wcfm_subscription_status" name="subscription_status">
                                <?php
                                foreach ( $statuses as $key => $value ) {
                                    echo '<option value="' . esc_attr($key) . '" ' . selected($key, str_replace('wc-', '', $subscripton_post->post_status), false) . '>' . esc_html__($value, 'woocommerce-subscriptions') . '</option>';
                                }
                                ?>
                            </select>
                            <?php if ( count($statuses) > 1 ) { ?>
                                <button class="wcfm_modify_subscription_status button" id="wcfm_modify_subscription_status" data-subscriptionid="<?php echo $subscription_id; ?>"><?php _e('Update', 'wc-frontend-manager-ultimate'); ?></button>
                            <?php } ?>
                        </p>
                        <div class="wcfm-message" tabindex="-1"></div>
                    </div>

                    <?php if ( ! in_array($current_sub_status, ['cancelled','expired'], true) ) : ?>
                    <p class="form-field form-field-wide wcfmu-pause-actions">
                        <label><?php _e('Quick Actions:', 'wc-frontend-manager-ultimate'); ?></label>
                        <button type="button" class="button wcfmu-btn-pause" id="wcfm_sub_pause_btn"
                                data-subscriptionid="<?php echo esc_attr($subscription_id); ?>"
                                <?php if ( $current_sub_status === 'pause' ) echo 'style="display:none"'; ?>>
                            ⏸ <?php _e('Pause', 'wc-frontend-manager-ultimate'); ?>
                        </button>
                        <button type="button" class="button wcfmu-btn-resume" id="wcfm_sub_resume_btn"
                                data-subscriptionid="<?php echo esc_attr($subscription_id); ?>"
                                <?php if ( $current_sub_status !== 'pause' ) echo 'style="display:none"'; ?>>
                            ▶ <?php _e('Resume', 'wc-frontend-manager-ultimate'); ?>
                        </button>
                        <?php if ( $current_sub_status === 'pause' ) :
                            $resume_date = get_post_meta($subscription_id, '_wcfmu_pause_resume', true);
                        ?>
                            <span class="wcfmu-pause-resume-info">
                                <?php if ($resume_date) printf(__('Auto-resumes %s', 'wc-frontend-manager-ultimate'), esc_html($resume_date)); ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>

                <?php } else { ?>
                    <p class="form-field form-field-wide">
                        <label for="wcfm_subscription_status"><?php _e('Subscription Status:', 'wc-frontend-manager-ultimate'); ?></label>
                        <?php _e(ucfirst(str_replace('wc-', '', $subscripton_post->post_status)), 'wc-frontend-manager-ultimate'); ?>
                    </p>
                <?php } ?>

                <!-- Pause calendar modal -->
                <div id="wcfmu-pause-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;">
                    <div id="wcfmu-pause-modal">
                        <h3><?php _e('Select Pause Period', 'wc-frontend-manager-ultimate'); ?></h3>
                        <div class="wcfmu-mode-toggle">
                            <label class="wcfmu-mode-tab wcfmu-mode-active" data-mode="multiple"><?php _e('Specific Dates', 'wc-frontend-manager-ultimate'); ?></label>
                            <label class="wcfmu-mode-tab" data-mode="range"><?php _e('Date Range', 'wc-frontend-manager-ultimate'); ?></label>
                        </div>
                        <input type="text" id="wcfmu-pause-picker" placeholder="<?php esc_attr_e('Select dates…', 'wc-frontend-manager-ultimate'); ?>" readonly>
                        <p id="wcfmu-pause-hint" class="wcfmu-modal-hint"></p>
                        <div class="wcfmu-modal-actions">
                            <button type="button" class="button button-primary" id="wcfmu-pause-confirm"
                                    data-subscriptionid="<?php echo esc_attr($subscription_id); ?>">
                                <?php _e('Confirm Pause', 'wc-frontend-manager-ultimate'); ?>
                            </button>
                            <button type="button" class="button" id="wcfmu-pause-cancel"><?php _e('Cancel', 'wc-frontend-manager-ultimate'); ?></button>
                        </div>
                    </div>
                </div>
            
                <?php if ( $wcfm_is_allow_order_details = apply_filters('wcfm_allow_order_details', true) ) { ?>
                    <p class="form-field form-field-wide wc-customer-user">
                        <label for="customer_user"><?php _e('Customer:', 'wc-frontend-manager'); ?> <?php
                        if ( $order->get_user_id() ) {
                            $args = array(
                                'post_status'    => 'all',
                                'post_type'      => 'shop_order',
                                '_customer_user' => absint($order->get_user_id()),
                            );
                            /*
                            printf( '<a target="_blank" href="%s">%s &rarr;</a>',
                                esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ),
                                __( 'View other orders', 'wc-frontend-manager' )
                            );*/
                        }
                        ?>
                        </label>
                        <?php
                        $user_string = '';
                        $user_id     = '';
                        if ( $order->get_user_id() ) {
                            $user_id = absint($order->get_user_id());
                            $user    = get_user_by('id', $user_id);
                            if ( $user ) {
                                $user_string = esc_html($user->display_name) . ' (#' . absint($user->ID) . ' - ' . esc_html($user->user_email) . ')';
                            }
                        }
                        echo htmlspecialchars($user_string);
                        ?>
                    </p>
                <?php } ?>
            
                <?php if ( apply_filters('wcfm_allow_customer_billing_details', true) || apply_filters('wcfm_allow_customer_shipping_details', true) || apply_filters('wcfm_is_allow_view_customer', true) ) { ?>
                    <table>
                        <thead>
                            <tr>
                                <?php if ( apply_filters('wcfm_allow_customer_billing_details', true) || apply_filters('wcfm_is_allow_view_customer', true) ) { ?>
                                    <th>
                                        <?php _e('Billing Details', 'wc-frontend-manager'); ?>
                                    </th>
                                <?php } ?>
                                
                                <?php if ( apply_filters('wcfm_allow_customer_shipping_details', true) || apply_filters('wcfm_is_allow_view_customer', true) ) { ?>
                                    <th>
                                        <?php _e('Shipping Details', 'wc-frontend-manager'); ?>
                                    </th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php if ( apply_filters('wcfm_allow_customer_billing_details', true) || apply_filters('wcfm_is_allow_view_customer', true) ) { ?>
                                    <td>
                                        <?php
                                            // Display values
                                            echo '<div class="address">';

                                        if ( apply_filters('wcfm_allow_customer_billing_details', true) ) {
                                            if ( $the_subscription->get_formatted_billing_address() ) {
                                                echo '<p><strong>' . __('Address', 'wc-frontend-manager') . ':</strong>' . wp_kses($the_subscription->get_formatted_billing_address(), array( 'br' => array() )) . '</p>';
                                            } else {
                                                echo '<p class="none_set"><strong>' . __('Address', 'wc-frontend-manager') . ':</strong> ' . __('No billing address set.', 'wc-frontend-manager') . '</p>';
                                            }
                                        }

                                        if ( apply_filters('wcfm_is_allow_view_customer', true) ) {
                                            foreach ( $WCFM->library->billing_fields as $key => $field ) {
                                                if ( isset($field['show']) && false === $field['show'] ) {
                                                    continue;
                                                }

                                                $field_name = 'billing_' . $key;

                                                if ( is_callable(array( $order, 'get_' . $field_name )) ) {
                                                    $field_value = $order->{"get_$field_name"}('edit');
                                                } else {
                                                    $field_value = $order->get_meta('_' . $field_name);
                                                }

                                                echo '<p><strong>' . esc_html($field['label']) . ':</strong> ' . make_clickable(esc_html($field_value)) . '</p>';
                                            }
                                        }

                                            echo '<p' . ( ( '' != $the_subscription->get_payment_method() ) ? ' class="' . esc_attr($the_subscription->get_payment_method()) . '"' : '' ) . '><strong>' . esc_html__('Payment Method', 'woocommerce-subscriptions') . ':</strong> ' . wp_kses_post(nl2br($the_subscription->get_payment_method_to_display()));

                                            // Display help tip
                                        if ( '' != $the_subscription->get_payment_method() && ! $the_subscription->is_manual() ) {
                                            echo wcs_help_tip(sprintf(_x('Gateway ID: [%s]', 'The gateway ID displayed on the Edit Subscriptions screen when editing payment method.', 'woocommerce-subscriptions'), $the_subscription->get_payment_method()));
                                        }

                                            do_action('woocommerce_admin_order_data_after_billing_address', $the_subscription);

                                            echo '</div>';
                                        ?>
                                    </td>
                                <?php } ?>
                                
                                <?php if ( apply_filters('wcfm_allow_customer_shipping_details', true) || apply_filters('wcfm_is_allow_view_customer', true) ) { ?>
                                    <td>
                                        <?php
                                            // Display values
                                            echo '<div class="address">';

                                        if ( apply_filters('wcfm_allow_customer_shipping_details', true) ) {
                                            if ( $the_subscription->get_formatted_shipping_address() ) {
                                                echo '<p><strong>' . __('Address', 'wc-frontend-manager') . ':</strong>' . wp_kses($the_subscription->get_formatted_shipping_address(), array( 'br' => array() )) . '</p>';
                                            } else {
                                                echo '<p class="none_set"><strong>' . __('Address', 'wc-frontend-manager') . ':</strong> ' . __('No shipping address set.', 'wc-frontend-manager') . '</p>';
                                            }
                                        }

                                        if ( apply_filters('wcfm_is_allow_view_customer', true) ) {
                                            if ( ! empty($WCFM->library->shipping_fields) ) {
                                                foreach ( $WCFM->library->shipping_fields as $key => $field ) {
                                                    if ( isset($field['show']) && false === $field['show'] ) {
                                                        continue;
                                                    }

                                                    $field_name = 'shipping_' . $key;

                                                    if ( is_callable(array( $order, 'get_' . $field_name )) ) {
                                                        $field_value = $order->{"get_$field_name"}('edit');
                                                    } else {
                                                        $field_value = $order->get_meta('_' . $field_name);
                                                    }

                                                    echo '<p><strong>' . esc_html($field['label']) . ':</strong> ' . make_clickable(esc_html($field_value)) . '</p>';
                                                }
                                            }
                                        }

                                        if ( apply_filters('woocommerce_enable_order_notes_field', 'yes' == get_option('woocommerce_enable_order_comments', 'yes')) && $subscripton_post->post_excerpt ) {
                                            echo '<p><strong>' . esc_html__('Customer Provided Note', 'woocommerce-subscriptions') . ':</strong> ' . wp_kses_post(nl2br($subscripton_post->post_excerpt)) . '</p>';
                                        }

                                                do_action('woocommerce_admin_order_data_after_shipping_address', $the_subscription);

                                            echo '</div>';
                                        ?>
                                    </td>
                                <?php } ?>
                            </tbody>
                        </table>
                <?php } ?>
            </div>
        </div>
        <div class="wcfm_clearfix"></div>
        <br />
        <!-- collapsible End -->
        
        <?php
        /* ── Delivery Schedule section ───────────────────────── */
        $saved_schedule    = get_post_meta( $subscription_id, '_wcfm_delivery_schedule', true );
        $saved_custom_days = get_post_meta( $subscription_id, '_wcfm_delivery_days', true );
        if ( ! is_array($saved_custom_days) ) $saved_custom_days = [];
        $schedule_days_map = [
            1 => __('Monday',    'wc-frontend-manager-ultimate'),
            2 => __('Tuesday',   'wc-frontend-manager-ultimate'),
            3 => __('Wednesday', 'wc-frontend-manager-ultimate'),
            4 => __('Thursday',  'wc-frontend-manager-ultimate'),
            5 => __('Friday',    'wc-frontend-manager-ultimate'),
            6 => __('Saturday',  'wc-frontend-manager-ultimate'),
            0 => __('Sunday',    'wc-frontend-manager-ultimate'),
        ];
        ?>

        <!-- collapsible: Delivery Schedule -->
        <div class="page_collapsible subscriptions_details_schedule" id="wcfm_delivery_schedule_options">
            <?php _e('Delivery Schedule', 'wc-frontend-manager-ultimate'); ?><span></span>
        </div>
        <div class="wcfm-container">
            <div id="subscriptions_details_schedule_expander" class="wcfm-content">

                <p class="form-field form-field-wide">
                    <label><?php _e('Schedule Type:', 'wc-frontend-manager-ultimate'); ?> <span style="color:#e00;">*</span></label>

                    <?php foreach ([
                        'daily'     => [ 'label' => __('Every Day',          'wc-frontend-manager-ultimate'), 'note' => '' ],
                        'alternate' => [ 'label' => __('Alternative Day',     'wc-frontend-manager-ultimate'), 'note' => '' ],
                        'weekend'   => [ 'label' => __('Every Weekend',       'wc-frontend-manager-ultimate'), 'note' => __('(Sat &amp; Sun)', 'wc-frontend-manager-ultimate') ],
                        'custom'    => [ 'label' => __('Custom',             'wc-frontend-manager-ultimate'), 'note' => '' ],
                    ] as $val => $opt ) : ?>
                    <label style="display:block;margin-bottom:8px;cursor:pointer;">
                        <input type="radio" name="wcfmu_manage_schedule" value="<?php echo esc_attr($val); ?>"
                               style="margin-right:6px;"
                               <?php checked($val, $saved_schedule); ?>>
                        <strong><?php echo esc_html($opt['label']); ?></strong>
                        <?php if ($opt['note']) : ?><small style="color:#888;"><?php echo $opt['note']; ?></small><?php endif; ?>
                    </label>
                    <?php endforeach; ?>

                    <!-- Custom day checkboxes -->
                    <div id="wcfmu-schedule-custom-days"
                         style="display:<?php echo $saved_schedule === 'custom' ? 'block' : 'none'; ?>;margin-top:10px;padding:10px 12px;background:#f9f9f9;border:1px solid #e5e5e5;border-radius:4px;">
                        <small style="display:block;margin-bottom:8px;color:#555;"><?php _e('Select delivery days:', 'wc-frontend-manager-ultimate'); ?></small>
                        <?php foreach ( $schedule_days_map as $day_val => $day_label ) : ?>
                        <label style="display:inline-block;margin-right:14px;margin-bottom:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" class="wcfmu-schedule-day-check" value="<?php echo esc_attr($day_val); ?>"
                                   style="margin-right:4px;"
                                   <?php if (in_array($day_val, $saved_custom_days, false)) echo 'checked'; ?>>
                            <?php echo esc_html($day_label); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </p>

                <div class="wcfm-clearfix"></div>
                <div class="wcfm-message" id="wcfmu-schedule-msg" tabindex="-1"></div>
                <div class="wcfm-clearfix"></div>
                <div id="wcfm_messages_submit">
                    <button type="button" class="button button-primary" id="wcfmu-schedule-save"
                            data-subscriptionid="<?php echo esc_attr($subscription_id); ?>">
                        <?php _e('Update Schedule', 'wc-frontend-manager-ultimate'); ?>
                    </button>
                </div>
                <div class="wcfm-clearfix"></div>

            </div>
        </div>
        <div class="wcfm_clearfix"></div>
        <br />
        <!-- collapsible End -->

        <?php do_action('begin_wcfm_subscriptions_billing_details', $subscription_id); ?>

        <!-- collapsible -->
        <div class="page_collapsible subscriptions_details_customer" id="wcfm_customer_options">
            <?php _e('Billing Schedule', 'woocommerce-subscriptions'); ?><span></span>
        </div>
        <div class="wcfm-container">
            <div id="subscriptions_details_billing_schedule_expander" class="wcfm-content">
              <form id="wcfm_wcs_billing_schedule_update_form">
                <input type="hidden" name="subscription_id" id="subscription_id" value="<?php echo $subscription_id; ?>" />
                    <div id="billing-schedule">
                        <?php if ( $the_subscription->can_date_be_updated('next_payment') && apply_filters('wcfm_is_allow_subscription_schedule_update', true) ) : ?>
                            <div class="billing-schedule-edit wcs-date-input">
                            <?php
                                // Subscription Period Interval
                                $WCFM->wcfm_fields->wcfm_generate_form_field(
                                    array(
                                        '_billing_interval' => array(
                                            'id'          => '_billing_interval',
                                            'type'        => 'select',
                                            'class'       => 'wcfm-select billing_interval',
                                            'label'       => __('Recurring:', 'woocommerce-subscriptions'),
                                            'label_class' => 'wcfm-title wcfm_title',
                                            'value'       => $the_subscription->get_billing_interval(),
                                            'options'     => wcs_get_subscription_period_interval_strings(),
                                        ),
                                    )
                                );

                                                                              // Billing Period
                                                                            $WCFM->wcfm_fields->wcfm_generate_form_field(
                                                                                array(
                                                                                    '_billing_period' => array(
                                                                                        'id'          => '_billing_period',
                                                                                        'type'        => 'select',
                                                                                        'class'       => 'wcfm-select billing_period',
                                                                                        // 'label'       => __( 'Billing Period', 'woocommerce-subscriptions' ),
                                                                                        // 'label_class' => 'wcfm-title wcfm_title',
                                                                                        'value'       => $the_subscription->get_billing_period(),
                                                                                        'options'     => wcs_get_subscription_period_strings(),
                                                                                    ),
                                                                                )
                                                                            );
                            ?>
                                <input type="hidden" name="wcs-lengths" id="wcs-lengths" data-subscription_lengths="<?php echo esc_attr(wcs_json_encode(wcs_get_subscription_ranges())); ?>">
                            </div>
                        <?php else : ?>
                            <strong style="width: 50%; display: inline-block;"><?php esc_html_e('Recurring:', 'woocommerce-subscriptions'); ?></strong>
                            <?php printf('%s %s', esc_html(wcs_get_subscription_period_interval_strings($the_subscription->get_billing_interval())), esc_html(wcs_get_subscription_period_strings(1, $the_subscription->get_billing_period()))); ?>
                        <?php endif; ?>
                    </div>
                
                    <?php foreach ( wcs_get_subscription_date_types() as $date_key => $date_label ) : ?>
                        <?php $internal_date_key = wcs_normalise_date_type_key($date_key); ?>
                        <?php if ( false === wcs_display_date_type($date_key, $the_subscription) ) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                    <div id="subscription-<?php echo esc_attr($date_key); ?>-date" class="date-fields">
                        <strong><?php echo esc_html($date_label); ?>:</strong>
                        <input type="hidden" name="<?php echo esc_attr($date_key); ?>_timestamp_utc" id="<?php echo esc_attr($date_key); ?>_timestamp_utc" value="<?php echo esc_attr($the_subscription->get_time($internal_date_key, 'gmt')); ?>"/>
                        <?php if ( $the_subscription->can_date_be_updated($internal_date_key) && apply_filters('wcfm_is_allow_subscription_schedule_update', true) ) : ?>
                            <?php
                            echo wp_kses(
                                wcs_date_input($the_subscription->get_time($internal_date_key, 'site'), array( 'name_attr' => $date_key )),
                                array(
                                    'input' => array(
                                        'type'        => array(),
                                        'class'       => array(),
                                        'placeholder' => array(),
                                        'name'        => array(),
                                        'id'          => array(),
                                        'maxlength'   => array(),
                                        'size'        => array(),
                                        'value'       => array(),
                                        'patten'      => array(),
                                    ),
                                    'div'   => array( 'class' => array() ),
                                    'span'  => array(),
                                    'br'    => array(),
                                )
                            );
                            ?>
                        <?php else : ?>
                            <?php echo esc_html($the_subscription->get_date_to_display($internal_date_key)); ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <p><strong style="width: 50%; display: inline-block; margin-top: 15px;"><?php esc_html_e('Timezone:', 'woocommerce-subscriptions'); ?></strong> <span id="wcs-timezone"><?php esc_html_e('Error: unable to find timezone of your browser.', 'woocommerce-subscriptions'); ?></span></p>
                    
                    <?php if ( $the_subscription->can_date_be_updated('next_payment') && apply_filters('wcfm_is_allow_subscription_schedule_update', true) ) { ?>
                        <div class="wcfm-clearfix"></div>
                        <div class="wcfm-message" tabindex="-1"></div>
                        <div class="wcfm-clearfix"></div>
                        <div id="wcfm_messages_submit">
                            <input type="submit" name="save-data" value="<?php _e('Update', 'wc-frontend-manager'); ?>" id="wcfm_subscription_billing_button" class="wcfm_submit_button" />
                        </div>
                    <?php } ?>
                    <div class="wcfm-clearfix"></div>
                </form>
            </div>
        </div>
        <div class="wcfm_clearfix"></div>
        <br />
        <!-- collapsible End -->
        
        <?php do_action('begin_wcfm_subscriptions_items', $subscription_id); ?>
        
        <!-- collapsible -->
        <div class="page_collapsible subscriptions_details_subscription" id="wcfm_subscription_options">
            <?php _e('Subscription Item', 'wc-frontend-manager-ultimate'); ?><span></span>
        </div>
        <div class="wcfm-container">
            <div id="subscriptions_details_subscription_expander" class="wcfm-content">
                
                <table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
                    <thead>
                        <tr>
                            <th class="item-thumb no_mob" data-sort="string-ins"></th>
                            <th class="item sortable" data-sort="string-ins"><?php _e('Item', 'wc-frontend-manager'); ?></th>
                            <?php do_action('woocommerce_admin_order_item_headers', $order); ?>
                            <th class="item_cost sortable no_mob" data-sort="float"><?php _e('Cost', 'wc-frontend-manager'); ?></th>
                            <th class="item_quantity sortable no_mob" data-sort="int"><?php _e('Qty', 'wc-frontend-manager'); ?></th>
                            <?php if ( $is_wcfm_order_details_line_total_head = apply_filters('wcfm_order_details_line_total_head', true) ) { ?>
                                <th class="line_cost sortable" data-sort="float"><?php _e('Total', 'wc-frontend-manager'); ?></th>
                            <?php } ?>
                            <?php do_action('wcfm_order_details_after_line_total_head', $order); ?>
                            <?php if ( $is_wcfm_order_details_tax_line_item = apply_filters('wcfm_order_details_tax_line_item', true) ) { ?>
                                <?php
                                if ( empty($legacy_order) && ! empty($order_taxes) ) :
                                    foreach ( $order_taxes as $tax_id => $tax_item ) :
                                        $tax_class      = wc_get_tax_class_by_tax_id($tax_item['rate_id']);
                                        $tax_class_name = isset($classes_options[ $tax_class ]) ? $classes_options[ $tax_class ] : __('Tax', 'wc-frontend-manager');
                                        $column_label   = ! empty($tax_item['label']) ? $tax_item['label'] : __('Tax', 'wc-frontend-manager');
                                        $column_tip     = $tax_item['name'] . ' (' . $tax_class_name . ')';
                                        ?>
                                            <th class="line_tax text_tip no_ipad no_mob" data-tip="<?php echo esc_attr($column_tip); ?>">
                                            <?php echo esc_attr($column_label); ?>
                                                <input type="hidden" class="order-tax-id" name="order_taxes[<?php echo $tax_id; ?>]" value="<?php echo esc_attr($tax_item['rate_id']); ?>">
                                                <a class="delete-order-tax" href="#" data-rate_id="<?php echo $tax_id; ?>"></a>
                                            </th>
                                            <?php
                                    endforeach;
                                endif;
                                ?>
                            <?php } ?>
                            <th class="wc-order-edit-line-item" style="width:90px;text-align:center;"><?php _e('Actions', 'wc-frontend-manager-ultimate'); ?></th>
                        </tr>
                    </thead>
                  <tbody id="order_line_items">
                      <?php
                        $line_items = apply_filters('wcfm_valid_line_items', $line_items, $order->get_id());
                        foreach ( $line_items as $item_id => $item ) {
                            $_product = $item->get_product();

                            do_action('woocommerce_before_order_item_' . $item->get_type() . '_html', $item_id, $item, $order);

                            if ( apply_filters('wcfm_is_allow_edit_products', true) && apply_filters('wcfm_is_allow_edit_specific_products', true, $item->get_product_id()) ) {
                                $product_link = $_product ? get_wcfm_edit_product_url($item->get_product_id(), $_product) : '';
                            } else {
                                $product_link = $_product ? get_permalink($item->get_product_id()) : '';
                            }
                            $thumbnail = $_product ? apply_filters('woocommerce_admin_order_item_thumbnail', $_product->get_image('thumbnail', array( 'title' => '' ), false), $item_id, $item) : '';
                            $tax_data  = $item->get_taxes();
                            ?>
                            <tr class="item <?php echo apply_filters('woocommerce_admin_html_order_item_class', ( ! empty($class) ? $class : '' ), $item, $order); ?>" data-order_item_id="<?php echo $item_id; ?>">
                                <td class="thumb no_mob">
                                    <?php
                                        echo '<div class="wc-order-item-thumbnail no_ipad">' . wp_kses_post($thumbnail) . '</div>';
                                    ?>
                                </td>
                                <td class="name" data-sort-value="<?php echo esc_attr($item->get_name()); ?>">
                                    <?php
                                        echo $product_link ? '<a href="' . esc_url($product_link) . '" class="wc-order-item-name">' . esc_html($item->get_name()) . '</a>' : '<div class="class="wc-order-item-name"">' . esc_html($item->get_name()) . '</div>';

                                    if ( $_product && $_product->get_sku() ) {
                                        echo '<div class="wc-order-item-sku"><strong>' . __('SKU:', 'wc-frontend-manager') . '</strong> ' . esc_html($_product->get_sku()) . '</div>';
                                    }

                                    if ( ! empty($item->get_variation_id()) ) {
                                        echo '<div class="wc-order-item-variation"><strong>' . __('Variation ID:', 'wc-frontend-manager') . '</strong> ';
                                        if ( ! empty($item->get_variation_id()) && 'product_variation' === get_post_type($item->get_variation_id()) ) {
                                            echo esc_html($item->get_variation_id());
                                        } elseif ( ! empty($item->get_variation_id()) ) {
                                            echo esc_html($item->get_variation_id()) . ' (' . __('No longer exists', 'wc-frontend-manager') . ')';
                                        }
                                        echo '</div>';
                                    }
                                    ?>
                            
                                    <?php do_action('woocommerce_before_order_itemmeta', $item_id, $item, $_product); ?>
                                    
                                    <div class="view">
                                        <?php
                                            global $wpdb;

                                        if ( $metadata = $item->get_formatted_meta_data('') ) {
                                            echo '<table cellspacing="0" class="display_meta">';
                                            foreach ( $metadata as $meta_id => $meta ) {

                                                // Skip hidden core fields
                                                if ( in_array(
                                                    $meta->key,
                                                    apply_filters(
                                                        'woocommerce_hidden_order_itemmeta',
                                                        array(
                                                            '_qty',
                                                            '_tax_class',
                                                            '_product_id',
                                                            '_variation_id',
                                                            '_line_subtotal',
                                                            '_line_subtotal_tax',
                                                            '_line_total',
                                                            '_line_tax',
                                                            'method_id',
                                                            '_vendor_id',
                                                            'vendor_id',
                                                            '_fulfillment_status',
                                                            '_commission_status',
                                                            '_reduced_stock',
                                                            'cost',
                                                            'pickup_hidden_datetime',
                                                            'return_hidden_datetime',
                                                            'return_hidden_days',
                                                            'redq_google_cal_sync_id',
                                                        )
                                                    )
                                                ) ) {
                                                    continue;
                                                }

                                                // Skip serialised meta
                                                if ( is_serialized($meta->display_key) ) {
                                                    continue;
                                                }

                                                echo '<tr><th>' . wp_kses_post(rawurldecode($meta->display_key)) . ':</th><td>' . wp_kses_post(wpautop(make_clickable(rawurldecode($meta->display_value)))) . '</td></tr>';
                                            }
                                            echo '</table>';
                                        }
                                        ?>
                                    </div>
                                    
                                    <?php
                                    if ( ! class_exists('WC_Deposits_Order_Item_Manager') || ( class_exists('WC_Deposits_Order_Item_Manager') && ! WC_Deposits_Order_Item_Manager::is_deposit($item) ) ) {
                                        do_action('woocommerce_after_order_itemmeta', $item_id, $item, $_product);
                                    }
                                    ?>
                                </td>
                            
                                <?php do_action('woocommerce_admin_order_item_values', $_product, $item, absint($item_id)); ?>
                            
                                <td class="item_cost no_mob" width="1%" data-sort-value="<?php echo esc_attr($order->get_item_subtotal($item, false, true)); ?>">
                                    <div class="view">
                                        <?php
                                        if ( $item->get_total() ) {
                                            echo wc_price($order->get_item_total($item, false, true), array( 'currency' => $order->get_currency() ));

                                            if ( $item->get_subtotal() != $item->get_total() ) {
                                                echo '<span class="wc-order-item-discount">-' . wc_price(wc_format_decimal($order->get_item_subtotal($item, false, false) - $order->get_item_total($item, false, false), ''), array( 'currency' => $order->get_currency() )) . '</span>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="no_mob" width="1%">
                                    <div class="view">
                                        <?php
                                            echo '<small class="times">&times;</small> ' . ( $item->get_quantity() ? esc_html($item->get_quantity()) : '1' );

                                        if ( $refunded_qty = $order->get_qty_refunded_for_item($item_id) ) {
                                            echo '<small class="refunded">' . ( $refunded_qty * -1 ) . '</small>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                
                                <?php if ( $is_wcfm_order_details_line_total = apply_filters('wcfm_order_details_line_total', true) ) { ?>
                                    <td class="line_cost" width="1%" data-sort-value="<?php echo esc_attr(( $item->get_total() ) ? $item->get_total() : ''); ?>">
                                        <div class="view">
                                            <?php
                                            if ( $item->get_total() ) {
                                                echo wc_price($item->get_total(), array( 'currency' => $order->get_currency() ));
                                            }

                                            if ( $item->get_subtotal() !== $item->get_total() ) {
                                                echo '<span class="wc-order-item-discount">-' . wc_price(wc_format_decimal($item->get_subtotal() - $item->get_total(), ''), array( 'currency' => $order->get_currency() )) . '</span>';
                                            }

                                            if ( $refunded = $order->get_total_refunded_for_item($item_id) ) {
                                                echo '<small class="refunded">' . wc_price($refunded, array( 'currency' => $order->get_currency() )) . '</small>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                <?php } ?>
                                <?php do_action('wcfm_after_order_details_line_total', $item, $order); ?>
                            
                                <?php if ( $is_wcfm_order_details_tax_line_item = apply_filters('wcfm_order_details_tax_line_item', true) ) { ?>
                                    <?php
                                    if ( wc_tax_enabled() ) {
                                        if ( ! empty($tax_data) ) {
                                            foreach ( $order_taxes as $tax_item ) {
                                                $tax_item_id       = $tax_item['rate_id'];
                                                $tax_item_total    = isset($tax_data['total'][ $tax_item_id ]) ? $tax_data['total'][ $tax_item_id ] : '';
                                                $tax_item_subtotal = isset($tax_data['subtotal'][ $tax_item_id ]) ? $tax_data['subtotal'][ $tax_item_id ] : '';
                                                ?>
                                                    <td class="line_tax no_ipad no_mob" width="1%">
                                                        <div class="view">
                                                        <?php
                                                        if ( '' != $tax_item_total ) {
                                                            echo wc_price(wc_round_tax_total($tax_item_total), array( 'currency' => $order->get_currency() ));
                                                        } else {
                                                            echo '&ndash;';
                                                        }

                                                        if ( $item->get_subtotal() !== $item->get_total() ) {
                                                            echo '<span class="wc-order-item-discount">-' . wc_price(wc_round_tax_total($tax_item_subtotal - $tax_item_total), array( 'currency' => $order->get_currency() )) . '</span>';
                                                        }

                                                        if ( $refunded = $order->get_tax_refunded_for_item($item_id, $tax_item_id) ) {
                                                            echo '<small class="refunded">' . wc_price($refunded, array( 'currency' => $order->get_currency() )) . '</small>';
                                                        }
                                                        ?>
                                                        </div>
                                                    </td>
                                                    <?php
                                            }
                                        }
                                    }
                                    ?>
                                <?php } ?>
                            
                            <td class="wc-order-edit-line-item-actions" style="white-space:nowrap;vertical-align:middle;padding:6px 4px;text-align:center;">
                                <?php
                                $item_qty   = max( 1, (int) $item->get_quantity() );
                                $item_total = (float) $item->get_total();
                                if ( $item_total <= 0 ) {
                                    $item_total = (float) $item->get_subtotal();
                                }
                                $unit_price = number_format( $item_total / $item_qty, 2, '.', '' );
                                ?>
                                <button type="button" class="button wcfmu-sub-edit-toggle"
                                        data-itemid="<?php echo esc_attr($item_id); ?>"
                                        data-qty="<?php echo esc_attr($item_qty); ?>"
                                        data-price="<?php echo esc_attr($unit_price); ?>"
                                        style="font-size:11px;padding:2px 8px;display:block;width:100%;margin-bottom:3px;">
                                    &#9998; <?php _e('Edit', 'wc-frontend-manager-ultimate'); ?>
                                </button>
                                <button type="button" class="button wcfmu-sub-remove-item"
                                        data-itemid="<?php echo esc_attr($item_id); ?>"
                                        data-subscriptionid="<?php echo esc_attr($subscription_id); ?>"
                                        style="font-size:11px;padding:2px 8px;display:block;width:100%;background:#dc3545;color:#fff;border-color:#dc3545;">
                                    &times; <?php _e('Remove', 'wc-frontend-manager-ultimate'); ?>
                                </button>
                            </td>
                            </tr>
                            <tr class="wcfmu-sub-item-edit-row" id="wcfmu-edit-row-<?php echo esc_attr($item_id); ?>" style="display:none;">
                                <td colspan="10" style="padding:10px 14px;background:#eef3fb;border-left:3px solid #1e3a8a;">
                                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;margin:0;">
                                            <?php _e('Qty:', 'wc-frontend-manager-ultimate'); ?>
                                            <input type="number" class="wcfmu-edit-qty" min="1"
                                                   style="width:65px;padding:4px 8px;border:1px solid #c5cee0;border-radius:3px;">
                                        </label>
                                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;margin:0;">
                                            <?php _e('Unit Price:', 'wc-frontend-manager-ultimate'); ?>
                                            <input type="text" class="wcfmu-edit-price"
                                                   style="width:90px;padding:4px 8px;border:1px solid #c5cee0;border-radius:3px;">
                                        </label>
                                        <span class="wcfmu-edit-total-preview" style="font-size:12px;color:#374151;white-space:nowrap;"></span>
                                        <button type="button" class="button button-primary wcfmu-sub-save-item"
                                                data-itemid="<?php echo esc_attr($item_id); ?>"
                                                data-subscriptionid="<?php echo esc_attr($subscription_id); ?>"
                                                style="font-size:12px;">
                                            <?php _e('Save', 'wc-frontend-manager-ultimate'); ?>
                                        </button>
                                        <button type="button" class="button wcfmu-sub-cancel-edit"
                                                data-itemid="<?php echo esc_attr($item_id); ?>"
                                                style="font-size:12px;">
                                            <?php _e('Cancel', 'wc-frontend-manager-ultimate'); ?>
                                        </button>
                                        <span class="wcfmu-item-save-msg" id="wcfmu-item-msg-<?php echo esc_attr($item_id); ?>" style="font-size:12px;"></span>
                                    </div>
                                </td>
                            </tr>

                            <?php

                            do_action('woocommerce_order_item_' . $item->get_type() . '_html', $item_id, $item, $order);
                        }
                        do_action('woocommerce_admin_order_items_after_line_items', $order->get_id());
                        ?>
                    </tbody>
                    
                    <?php if ( $is_wcfm_order_details_shipping_line_item = apply_filters('wcfm_order_details_shipping_line_item', true) ) { ?>
                    <tbody id="order_shipping_line_items">
                        <?php
                        $shipping_methods = WC()->shipping() ? WC()->shipping->load_shipping_methods() : array();
                        foreach ( $line_items_shipping as $item_id => $item ) {
                            ?>
                            <tr class="shipping <?php echo ( ! empty($class) ) ? $class : ''; ?>" data-order_item_id="<?php echo $item_id; ?>">
                                <td class="thumb no_ipad no_mob"><span class="wcfmfa fa-truck"></span></td>
                            
                                <td class="name">
                                    <div class="view">
                                        <?php echo ! empty($item->get_name()) ? wc_clean($item->get_name()) : __('Shipping', 'wc-frontend-manager'); ?>
                                    </div>
                            
                                    <?php do_action('woocommerce_before_order_itemmeta', $item_id, $item, null); ?>
                                    <div class="view">
                                        <?php
                                            global $wpdb;

                                        if ( $metadata = $item->get_formatted_meta_data('') ) {
                                            echo '<table cellspacing="0" class="display_meta">';
                                            foreach ( $metadata as $meta_id => $meta ) {

                                                // Skip hidden core fields
                                                if ( in_array(
                                                    $meta->key,
                                                    apply_filters(
                                                        'woocommerce_hidden_order_itemmeta',
                                                        array(
                                                            '_qty',
                                                            '_tax_class',
                                                            '_product_id',
                                                            '_variation_id',
                                                            '_line_subtotal',
                                                            '_line_subtotal_tax',
                                                            '_line_total',
                                                            '_line_tax',
                                                            'method_id',
                                                            '_vendor_id',
                                                            'vendor_id',
                                                            '_fulfillment_status',
                                                            '_commission_status',
                                                            '_reduced_stock',
                                                            'cost',
                                                            'pickup_hidden_datetime',
                                                            'return_hidden_datetime',
                                                            'return_hidden_days',
                                                            'redq_google_cal_sync_id',
                                                        )
                                                    )
                                                ) ) {
                                                    continue;
                                                }

                                                // Skip serialised meta
                                                if ( is_serialized($meta->display_key) ) {
                                                    continue;
                                                }

                                                echo '<tr><th>' . wp_kses_post(rawurldecode($meta->display_key)) . ':</th><td>' . wp_kses_post(wpautop(make_clickable(rawurldecode($meta->display_value)))) . '</td></tr>';
                                            }
                                            echo '</table>';
                                        }
                                        ?>
                                    </div>
                                    <?php do_action('woocommerce_after_order_itemmeta', $item_id, $item, null); ?>
                                </td>
                            
                                <?php do_action('woocommerce_admin_order_item_values', null, $item, absint($item_id)); ?>
                            
                                <td class="item_cost no_mob" width="1%">&nbsp;</td>
                                <td class="quantity no_mob" width="1%">&nbsp;</td>
                            
                                <td class="line_cost" width="1%">
                                    <div class="view">
                                        <?php
                                            echo ( isset($item['cost']) ) ? wc_price(wc_round_tax_total($item['cost']), array( 'currency' => $order->get_currency() )) : '';

                                        if ( $refunded = $order->get_total_refunded_for_item($item_id, 'shipping') ) {
                                            echo '<small class="refunded">-' . wc_price($refunded, array( 'currency' => $order->get_currency() )) . '</small>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            
                                <?php if ( $is_wcfm_order_details_tax_line_item = apply_filters('wcfm_order_details_tax_line_item', true) ) { ?>
                                    <?php
                                    if ( ( $tax_data = $item->get_taxes() ) && wc_tax_enabled() ) {
                                        foreach ( $order_taxes as $tax_item ) {
                                            $tax_item_id    = $tax_item->get_rate_id();
                                            $tax_item_total = isset($tax_data['total'][ $tax_item_id ]) ? $tax_data['total'][ $tax_item_id ] : '';
                                            ?>
                                                    <td class="line_tax no_ipad no_mob" width="1%">
                                                        <div class="view">
                                                        <?php
                                                            echo ( '' != $tax_item_total ) ? wc_price(wc_round_tax_total($tax_item_total), array( 'currency' => $order->get_currency() )) : '&ndash;';

                                                        if ( $refunded = $order->get_tax_refunded_for_item($item_id, $tax_item_id, 'shipping') ) {
                                                            echo '<small class="refunded">-' . wc_price($refunded, array( 'currency' => $order->get_currency() )) . '</small>';
                                                        }
                                                        ?>
                                                        </div>
                                                    </td>
                                
                                                <?php
                                        }
                                    }
                                    ?>
                                <?php } ?>
                            
                            </tr>
                            <?php
                        }
                        do_action('woocommerce_admin_order_items_after_shipping', $order->get_id());
                        ?>
                    </tbody>
                    <?php } ?>
                    
                    <?php if ( $is_wcfm_order_details_fee_line_item = apply_filters('wcfm_order_details_fee_line_item', true) ) { ?>
                    <tbody id="order_fee_line_items">
                        <?php
                        foreach ( $line_items_fee as $item_id => $item ) {
                            ?>
                            <tr class="fee <?php echo ( ! empty($class) ) ? $class : ''; ?>" data-order_item_id="<?php echo $item_id; ?>">
                                <td class="thumb no_ipad no_mob"><span class="wcfmfa fa-plus-circle"></span></td>
                            
                                <td class="name">
                                    <div class="view">
                                        <?php echo ! empty($item->get_name()) ? esc_html($item->get_name()) : __('Fee', 'wc-frontend-manager'); ?>
                                    </div>
                                </td>
                            
                                <?php do_action('woocommerce_admin_order_item_values', null, $item, absint($item_id)); ?>
                            
                                <td class="item_cost no_mob" width="1%">&nbsp;</td>
                                <td class="quantity no_mob" width="1%">&nbsp;</td>
                            
                                <td class="line_cost" width="1%">
                                    <div class="view">
                                        <?php
                                            echo ( $item->get_total() ) ? wc_price(wc_round_tax_total($item->get_total()), array( 'currency' => $order->get_currency() )) : '';

                                        if ( $refunded = $order->get_total_refunded_for_item($item_id, 'fee') ) {
                                            echo '<small class="refunded">-' . wc_price($refunded, array( 'currency' => $order->get_currency() )) . '</small>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            
                                <?php if ( $is_wcfm_order_details_tax_line_item = apply_filters('wcfm_order_details_tax_line_item', true) ) { ?>
                                    <?php
                                    if ( empty($legacy_order) && wc_tax_enabled() ) :
                                        $line_tax_data = isset($item['line_tax_data']) ? $item['line_tax_data'] : '';
                                        $tax_data      = maybe_unserialize($line_tax_data);

                                        foreach ( $order_taxes as $tax_item ) :
                                            $tax_item_id    = $tax_item['rate_id'];
                                            $tax_item_total = isset($tax_data['total'][ $tax_item_id ]) ? $tax_data['total'][ $tax_item_id ] : '';
                                            ?>
                                                    <td class="line_tax no_ipad no_mob" width="1%">
                                                        <div class="view">
                                                        <?php
                                                            echo ( '' != $tax_item_total ) ? wc_price(wc_round_tax_total($tax_item_total), array( 'currency' => $order->get_currency() )) : '&ndash;';

                                                        if ( $refunded = $order->get_tax_refunded_for_item($item_id, $tax_item_id, 'fee') ) {
                                                            echo '<small class="refunded">-' . wc_price($refunded, array( 'currency' => $order->get_currency() )) . '</small>';
                                                        }
                                                        ?>
                                                        </div>
                                                    </td>
                                
                                                <?php
                                        endforeach;
                                    endif;
                                    ?>
                                <?php } ?>
                            
                            </tr>
                            <?php
                        }
                        do_action('woocommerce_admin_order_items_after_fees', $order->get_id());
                        ?>
                    </tbody>
                    <?php } ?>
                    
                    <?php if ( $is_wcfm_order_details_refund_line_item = apply_filters('wcfm_order_details_refund_line_item', true) ) { ?>
                    <tbody id="order_refunds">
                        <?php
                        if ( $refunds = $the_subscription->get_refunds() ) {
                            foreach ( $refunds as $refund ) {
                                /**
                                 * @var object $refund The refund object.
                                 */
                                $who_refunded = new WP_User($refund->get_refunded_by());
                                ?>
                            <tr class="refund <?php echo ( ! empty($class) ) ? $class : ''; ?>" data-order_refund_id="<?php echo $refund->get_id(); ?>">
                                <td class="thumb no_ipad no_mob"><span class="wcicon-status-refunded"></span></td>
                            
                                <td class="name">
                                    <?php
                                        /* translators: 1: refund id 2: date */
                                        printf(__('Refund #%1$s - %2$s', 'woocommerce'), $refund->get_id(), wc_format_datetime($order->get_date_created(), get_option('date_format') . ', ' . get_option('time_format')));

                                    if ( $who_refunded->exists() ) {
                                        echo ' ' . esc_attr_x('by', 'Ex: Refund - $date >by< $username', 'woocommerce') . ' ' . '<abbr class="refund_by" title="' . sprintf(esc_attr__('ID: %d', 'woocommerce'), absint($who_refunded->ID)) . '">' . esc_attr($who_refunded->display_name) . '</abbr>';
                                    }
                                    ?>
                                    <?php if ( $refund->get_reason() ) : ?>
                                        <p class="description"><?php echo esc_html($refund->get_reason()); ?></p>
                                    <?php endif; ?>
                                    <input type="hidden" class="order_refund_id" name="order_refund_id[]" value="<?php echo esc_attr($refund->get_id()); ?>" />
                                </td>
                            
                                <?php do_action('woocommerce_admin_order_item_values', null, $refund, $refund->get_id()); ?>
                            
                                <td class="item_cost no_mob" width="1%">&nbsp;</td>
                                <td class="quantity no_mob" width="1%">&nbsp;</td>
                            
                                <td class="line_cost" width="1%">
                                    <div class="view">
                                        <?php echo wc_price('-' . $refund->get_amount()); ?>
                                    </div>
                                </td>
                            
                                <?php
                                if ( wc_tax_enabled() ) :
                                    $total_taxes = count($order_taxes);
                                    ?>
                                    <?php for ( $i = 0;  $i < $total_taxes; $i++ ) : ?>
                                        <td class="line_tax no_ipad no_mob" width="1%"></td>
                                    <?php endfor; ?>
                                <?php endif; ?>
                                <?php
                            }
                            do_action('woocommerce_admin_order_items_after_refunds', $order->get_id());
                        }
                        ?>
                    </tbody>
                    <?php } ?>
                </table>

                <!-- ── Add Item ────────────────────────────────────────── -->
                <div id="wcfmu-sub-add-item-wrap" style="margin:14px 0 8px;padding:14px 16px;border:1px dashed #c5cee0;border-radius:4px;background:#fafbfc;">
                    <strong style="display:block;margin-bottom:10px;font-size:13px;"><?php _e('Add Item', 'wc-frontend-manager-ultimate'); ?></strong>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <select id="wcfmu-sub-add-product" style="min-width:240px;"
                                data-placeholder="<?php esc_attr_e('Search for a product…', 'wc-frontend-manager-ultimate'); ?>">
                        </select>
                        <label style="display:flex;align-items:center;gap:5px;font-size:13px;margin:0;">
                            <?php _e('Qty:', 'wc-frontend-manager-ultimate'); ?>
                            <input type="number" id="wcfmu-sub-add-qty" value="1" min="1"
                                   style="width:60px;padding:4px 8px;border:1px solid #c5cee0;border-radius:3px;">
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;font-size:13px;margin:0;">
                            <?php _e('Price (optional):', 'wc-frontend-manager-ultimate'); ?>
                            <input type="text" id="wcfmu-sub-add-price" placeholder="<?php esc_attr_e('Auto', 'wc-frontend-manager-ultimate'); ?>"
                                   style="width:90px;padding:4px 8px;border:1px solid #c5cee0;border-radius:3px;">
                        </label>
                        <button type="button" class="button button-primary" id="wcfmu-sub-add-item-btn"
                                data-subscriptionid="<?php echo esc_attr($subscription_id); ?>"
                                style="font-size:12px;">
                            + <?php _e('Add', 'wc-frontend-manager-ultimate'); ?>
                        </button>
                    </div>
                    <div id="wcfmu-sub-add-item-msg" style="margin-top:8px;display:none;font-size:13px;"></div>
                </div>

                <div class="wc-order-data-row wc-order-totals-items wc-order-items-editable">
                    <?php if ( $is_wcfm_order_details_coupon_line_item = apply_filters('wcfm_order_details_coupon_line_item', true) ) { ?>
                        <?php
                            $coupons = $the_subscription->get_items(array( 'coupon' ));
                        if ( $coupons ) {
                            ?>
                                <div class="wc-used-coupons">
                                    <ul class="wc_coupon_list">
                                    <?php
                                    echo '<li><strong>' . __('Coupon(s) Used', 'wc-frontend-manager') . '</strong></li>';
                                    foreach ( $coupons as $item_id => $item ) {
                                        $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' LIMIT 1;", $item->get_name()));

                                        $link = $post_id ? add_query_arg(
                                            array(
                                                'post'   => $post_id,
                                                'action' => 'edit',
                                            ),
                                            admin_url('post.php')
                                        ) : add_query_arg(
                                            array(
                                                's' => $item->get_name(),
                                                'post_status' => 'all',
                                                'post_type' => 'shop_coupon',
                                            ),
                                            admin_url('edit.php')
                                        );

                                        echo '<li class="code"><a href="' . esc_url($link) . '" class="img_tip" data-tip="' . esc_attr(wc_price($item['discount_amount'], array( 'currency' => $order->get_currency() ))) . '"><span>' . esc_html($item->get_name()) . '</span></a></li>';
                                    }
                                    ?>
                                    </ul>
                                </div>
                                <?php
                        }
                        ?>
                    <?php } ?>
                    <table class="wc-order-totals">
                        <?php if ( $is_wcfm_order_details_coupon_line_item = apply_filters('wcfm_order_details_coupon_line_item', true) ) { ?>
                            <tr>
                                <td class="label"><span class="wcfmfa fa-question-circle-o no_mob img_tip" data-tip="<?php _e('This is the total discount. Discounts are defined per line item.', 'wc-frontend-manager'); ?>"></span> <?php _e('Discount', 'wc-frontend-manager'); ?>:</td>
                                <td width="1%"></td>
                                <td class="total">
                                    <?php echo wc_price($the_subscription->get_total_discount(), array( 'currency' => $the_subscription->get_currency() )); ?>
                                </td>
                            </tr>
                        <?php } ?>
                
                        <?php do_action('woocommerce_admin_order_totals_after_discount', $order->get_id()); ?>
                
                        <?php if ( $is_wcfm_order_details_shipping_line_item = apply_filters('wcfm_order_details_shipping_line_item', true) ) { ?>
                            <tr>
                                <td class="label"><span class="wcfmfa fa-question-circle-o no_mob img_tip" data-tip="<?php _e('This is the shipping and handling total costs for the order.', 'wc-frontend-manager'); ?>"></span> <?php _e('Shipping', 'wc-frontend-manager'); ?>:</td>
                                <td width="1%"></td>
                                <td class="total">
                                <?php
                                if ( ( $refunded = $the_subscription->get_total_shipping_refunded() ) > 0 ) {
                                    echo '<del>' . strip_tags(wc_price($the_subscription->get_total_shipping(), array( 'currency' => $the_subscription->get_currency() ))) . '</del> <ins>' . wc_price($the_subscription->get_total_shipping() - $refunded, array( 'currency' => $the_subscription->get_currency() )) . '</ins>';
                                } else {
                                    echo wc_price($the_subscription->get_total_shipping(), array( 'currency' => $the_subscription->get_currency() ));
                                }
                                ?>
                                </td>
                            </tr>
                        <?php } ?>
                
                        <?php do_action('woocommerce_admin_order_totals_after_shipping', $order->get_id()); ?>
                
                        <?php if ( $is_wcfm_order_details_tax_total = apply_filters('wcfm_order_details_tax_total', true) ) { ?>
                            <?php if ( wc_tax_enabled() ) : ?>
                                <?php foreach ( $the_subscription->get_tax_totals() as $code => $tax ) : ?>
                                    <tr>
                                        <td class="label"><?php echo $tax->label; ?>:</td>
                                        <td width="1%"></td>
                                        <td class="total">
                                        <?php
                                        if ( ( $refunded = $the_subscription->get_total_tax_refunded_by_rate_id($tax->rate_id) ) > 0 ) {
                                            echo '<del>' . strip_tags($tax->formatted_amount) . '</del> <ins>' . wc_price(WC_Tax::round($tax->amount, wc_get_price_decimals()) - WC_Tax::round($refunded, wc_get_price_decimals()), array( 'currency' => $order->get_currency() )) . '</ins>';
                                        } else {
                                            echo $tax->formatted_amount;
                                        }
                                        ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php } ?>
                
                        <?php do_action('woocommerce_admin_order_totals_after_tax', $order->get_id()); ?>
                
                        <?php if ( $is_wcfm_order_details_total = apply_filters('wcfm_order_details_total', true) ) { ?>
                        <tr>
                            <td class="label"><?php _e('Order Total', 'wc-frontend-manager'); ?>:</td>
                            <td>
                                
                            </td>
                            <td class="total">
                                <div class="view"><?php echo $the_subscription->get_formatted_order_total(); ?></div>
                            </td>
                        </tr>
                        <?php } ?>
                
                        <?php do_action('wcfm_order_totals_after_total', $order->get_id()); ?>
                
                        <?php if ( $is_wcfm_order_details_refund_line_item = apply_filters('wcfm_order_details_refund_line_item', true) ) { ?>
                            <?php if ( $the_subscription->get_total_refunded() ) : ?>
                                <tr>
                                    <td class="label refunded-total"><?php _e('Refunded', 'wc-frontend-manager'); ?>:</td>
                                    <td width="1%"></td>
                                    <td class="total refunded-total">-<?php echo wc_price($the_subscription->get_total_refunded(), array( 'currency' => $the_subscription->get_currency() )); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php } ?>
                
                        <?php
                        // do_action( 'woocommerce_admin_order_totals_after_refunded', $order->get_id() );
                        ?>
                
                    </table>
                    <div class="wcfm-clearfix"></div>
                </div>
         </div>
        </div>
        <div class="wcfm_clearfix"></div>
        <br />
        <!-- collapsible End -->
        
        <?php do_action('begin_wcfm_subscriptions_related_orders', $subscription_id); ?>
        
        <!-- collapsible -->
        <div class="page_collapsible subscriptions_details_subscription" id="wcfm_subscription_options">
            <?php _e('Related Orders', 'wc-frontend-manager-ultimate'); ?><span></span>
        </div>
        <div class="wcfm-container">
            <div id="subscriptions_details_subscription_expander" class="wcfm-content">
                <?php WCS_Meta_Box_Related_Orders::output($subscripton_post); ?>
            </div>
        </div>
        <div class="wcfm_clearfix"></div>
        <br />
        <!-- collapsible End -->
        
        <?php do_action('end_wcfm_subscriptions_details', $subscription_id); ?>

    </div>
</div>
