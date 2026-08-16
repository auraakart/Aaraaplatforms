<?php

/**
 * WCFM plugin views
 *
 * Plugin WC Rental Calendar Views
 *
 * @author  Squiz Pty Ltd <products@squiz.net>
 * @package wcfmu/views/thirdparty
 * @version 2.3.10
 */
global $WCFM, $WCFMu;

if (!$wcfm_allow_rental = apply_filters('wcfm_is_allow_rental', true)) {
    wcfm_restriction_message_show('Rental');
    return;
}

$args = [
    'post_type'      => 'shop_order',
    'post_status'    => 'any',
    'posts_per_page' => -1,
];

$orders = wc_get_orders($args);

$rnb_calendar = new REDQ_RnB\Integration\FullCalendarIntegration();
$calendarItems = [];

if (isset($orders) && !empty($orders)) {
    foreach ($orders as $order) {
        $order_id = $order->get_id();

        $line_items = $order->get_items();
        $line_items = apply_filters('wcfm_valid_line_items', $line_items, $order_id);
        
        if (empty($line_items)) {
            continue;
        }

        $has_item = rnb_has_order_items($order_id);
        if (empty($has_item)) {
            continue;
        }

        $meta_exist = rnb_check_order_item_meta_exists($order_id, 'rnb_hidden_order_meta');

        if ($meta_exist) {
            $data = [];

            $data['order_details'] = $order->get_data();
            $data['order_details']['customer_name'] = rnb_customer_name($order);

            $pickup_period = '';
            $return_period = '';
            $duration      = '';
            $deposit       = 0;
            $discount      = 0;
            $extras        = 0;
            $total         = 0;
            $deposit_fee_total = 0;

            $line_items = $order->get_items();
            $line_items = apply_filters('wcfm_valid_line_items', $line_items, $order_id);
            if (empty($line_items)) {
                continue;
            }

            foreach ($line_items as $item_id => $item) {

                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();

                $data['item_details'][$item_id] = [
                    'item_data'      => $item->get_data(),
                    'formatted_data' => $item->get_all_formatted_meta_data(),
                ];

                $rental_data = $item->get_meta('rnb_hidden_order_meta', true);
                if (empty($rental_data)) {
                    continue;
                }
                $rental_meta = $rnb_calendar->format_rental_item_data($product_id, $rental_data, $quantity);

                $data['item_details'][$item_id]['rental_meta'] = $rental_meta;
                $data['item_details'][$item_id]['rental_data'] = $rental_data;

                $pickup_period .= isset($rental_meta['pickup_datetime']['data']) ? $rental_meta['pickup_datetime']['data']['name'] : '';
                $return_period .=  isset($rental_meta['return_datetime']['data']) ? $rental_meta['return_datetime']['data']['name'] : '';
                $duration .= isset($rental_meta['duration']['data']) ? $rental_meta['duration']['data']['name'] : '';

                $rdc = $rental_data['rental_days_and_costs'];

                $price_breakdown = $rdc['price_breakdown'];

                $deposit += $price_breakdown['deposit_total'];
                $discount += $price_breakdown['discount_total'];
                $extras += $price_breakdown['extras_total'];
                $total += $price_breakdown['total'];
                $deposit_fee_total += $price_breakdown['deposit_free_total'];
            }

            $data['order_details']['pickup_period'] = $pickup_period;
            $data['order_details']['return_period'] = $return_period;
            $data['order_details']['duration'] = $duration;
            $data['order_details']['deposit'] = $deposit;
            $data['order_details']['extras'] = $extras;
            $data['order_details']['total'] = $total;
            $data['order_details']['deposit_fee_total'] = $deposit_fee_total;

            $order_details = $data['order_details'];
            if (!isset($data['item_details'])) {
                continue;
            }

            $items = $data['item_details'];

            foreach ($items as $item_id => $item) {
                $item_data   = $item['item_data'];
                $rental_data = $item['rental_data'];
                $item_id     = $item_data['id'];
                $product_id  = $item_data['product_id'];
                $quantity   = $item_data['quantity'];

                $calendarItems[$item_id] = [
                    'post_status' => 'wc-' . $order_details['status'],
                    'title'       => html_entity_decode(get_the_title($product_id)) . ' ×' . $quantity,
                    'link'        => get_the_permalink($product_id),
                    'id'          => $order_id,
                    'color'       => rnb_get_status_to_color_map($order_details['status']),
                    'start'       => $rental_data['pickup_date'],
                    'start_time'  => $rental_data['pickup_time'],
                    'end'         => $rental_data['dropoff_date'],
                    'return_date' => $rental_data['dropoff_date'],
                    'return_time' => $rental_data['dropoff_time'],
                    'url'         => get_wcfm_view_order_url(absint($order_id)),
                    'description' => wcfm_rnb_prepare_popup_content($order_id, $order_details, $item)
                ];
            }
        }
    } //end foreach
} //end if

$calendar_data = [];

foreach ($calendarItems as $key => $item) {

    if (array_key_exists('start', $item) && array_key_exists('end', $item)) {
        $calendar_data[$key] = $item;
    }

    if (array_key_exists('start', $item) && !array_key_exists('end', $item)) {
        $start_info = isset($item['start_time']) && !empty($item['start_time']) ? $item['start'] . 'T' . $item['start_time'] : $item['start'];
        $return_info = isset($item['return_time']) && !empty($item['return_time']) ? $item['start'] . 'T' . $item['return_time'] : $item['start'];

        $item['start'] = rnb_format_date_time($start_info);
        $item['end'] = rnb_format_date_time($return_info);

        $calendar_data[$key] = $item;
    }

    if (array_key_exists('end', $item) && !array_key_exists('start', $item)) {
        $start_info = isset($item['start_time']) && !empty($item['start_time']) ? $item['end'] . 'T' . $item['start_time'] : $item['end'];
        $return_info = isset($item['return_time']) && !empty($item['return_time']) ? $item['end'] . 'T' . $item['return_time'] : $item['end'];

        $item['start'] = rnb_format_date_time($start_info);
        $item['end'] = rnb_format_date_time($return_info);

        $calendar_data[$key] = $item;
    }

    if (array_key_exists('start', $item) && array_key_exists('end', $item)) {
        $start_info = isset($item['start_time']) && !empty($item['start_time']) ? $item['start'] . 'T' . $item['start_time'] : $item['start'];
        $return_info = isset($item['return_time']) && !empty($item['return_time']) ? $item['end'] . 'T' . $item['return_time'] : $item['end'];

        $item['start'] = rnb_format_date_time($start_info);
        $item['end'] = rnb_format_date_time($return_info);

        $calendar_data[$key] = $item;
    }
}

$loc_data = [
    'calendar_data'     => $calendar_data,
    'lang_domain'       => get_option('rnb_lang_domain', 'en'),
    'day_of_week_start' => (int) get_option('rnb_day_of_week_start', 1) - 1,
];

wp_localize_script('wcfmu_rental_calendar_js', 'WCFM_RNB_CALENDAR', $loc_data);

?>
<div class="collapse wcfm-collapse" id="wcfm_wcrental_listing">
    <div class="wcfm-page-headig">
        <span class="wcfmfa fa-calendar-alt"></span>
        <span class="wcfm-page-heading-text"><?php _e('Rental Calendar', 'wc-frontend-manager-ultimate'); ?></span>
        <?php do_action('wcfm_page_heading'); ?>
    </div>
    <div class="wcfm-collapse-content">
        <div id="wcfm_page_load"></div>

        <div class="wcfm-container wcfm-top-element-container">
            <h2><?php _e('Calendar View', 'wc-frontend-manager-ultimate'); ?></h2>

            <?php
            if ($allow_wp_admin_view = apply_filters('wcfm_allow_wp_admin_view', true)) {
            ?>
                <a target="_blank" class="wcfm_wp_admin_view text_tip" href="<?php echo admin_url('admin.php?page=rnb_admin'); ?>" data-tip="<?php _e('WP Admin View', 'wc-frontend-manager-ultimate'); ?>"><span class="fab fa-wordpress fa-wordpress-simple"></span></a>
            <?php
            }

            echo '<a class="add_new_wcfm_ele_dashboard text_tip" href="' . get_wcfm_rental_quote_url() . '" data-tip="' . __('Quote Requests', 'wc-frontend-manager-ultimate') . '"><span class="wcfmfa fa-snowflake"></span></a>';

            if ($has_new = apply_filters('wcfm_add_new_product_sub_menu', true)) {
                echo '<a class="add_new_wcfm_ele_dashboard text_tip" href="' . get_wcfm_edit_product_url() . '" data-tip="' . __('Add New Product', 'wc-frontend-manager-ultimate') . '"><span class="wcfmfa fa-cube"></span><span class="text">' . __('Add New', 'wc-frontend-manager') . '</span></a>';
            }
            ?>
            <div class="wcfm-clearfix"></div>
        </div>
        <div class="wcfm-clearfix"></div><br />

        <?php do_action('before_wcfm_wcrental_calendar'); ?>

        <div class="wcfm-container">
            <div id="wwcfm_wcrental_listing_expander" class="wcfm-content">

                <div class="wrap">
                    <div id="wcfm-rental-calendar"></div>
                </div>

                <div id="eventContent" class="popup-modal white-popup-block mfp-hide">
                    <div class="white-popup wcfm_popup_wrapper">
                        <div style="margin-bottom: 15px;">
                            <h2 style="float: none;"><span id="eventProduct"></span></h2>
                        </div>
                        <!-- <strong><?php esc_html_e('Start:', 'redq-rental'); ?></strong> <span id="startTime"></span><br> -->
                        <!-- <strong><?php esc_html_e('End:', 'redq-rental'); ?></strong> <span id="endTime"></span><br><br> -->
                        <div id="eventInfo"></div>
                        <p><strong><a id="eventLink" class="wcfm_popup_button" href="" target="_blank"><?php esc_html_e('View Order', 'redq-rental'); ?></a></strong></p>
                        <div class="wcfm-clearfix"></div><br />
                    </div>
                </div>

                <div class="wcfm-clearfix"></div>
            </div>
        </div>
        <?php
        do_action('after_wcfm_wcrental_calendar');
        ?>
    </div>
</div>