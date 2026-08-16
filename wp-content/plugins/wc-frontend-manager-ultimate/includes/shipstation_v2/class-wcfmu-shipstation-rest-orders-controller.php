<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined('ABSPATH')) {
    exit;
}

class WCFMu_ShipStation_v2_REST_Orders_Controller extends WP_REST_Controller {
    /**
     * Namespace for the ShipStation REST API.
     *
     * @var string
     */
    protected $namespace = 'wc-shipstation/v1';

    /**
     * REST base for order routes.
     *
     * @var string
     */
    protected $rest_base = 'orders';

    /**
     * Register ShipStation order routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_orders'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'                => [
                        'modified_after' => [
                            'type'              => 'string',
                            'required'          => false,
                            'validate_callback' => function ($value) {
                                return empty($value) || false !== strtotime($value);
                            },
                        ],
                        'page'           => [
                            'type'              => 'integer',
                            'default'           => 1,
                            'sanitize_callback' => function ($value) {
                                return max(1, absint($value));
                            },
                        ],
                        'per_page'       => [
                            'type'              => 'integer',
                            'default'           => 100,
                            'sanitize_callback' => function ($value) {
                                return min(max(1, absint($value)), 500);
                            },
                        ],
                        'status_mapping' => [
                            'required' => false,
                        ],
                        'vendor_id'      => [
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/shipments',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'update_orders_shipments'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'                => [
                        'vendor_id' => [
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Basic permission callback for vendor-scoped ShipStation routes.
     *
     * @return bool
     */
    public function check_permission() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        if (current_user_can('manage_woocommerce') || current_user_can('shop_staff')) {
            return true;
        }

        return function_exists('wcfm_is_vendor') && wcfm_is_vendor($user_id);
    }

    /**
     * Return vendor orders in ShipStation's REST shape.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_orders($request) {
        $vendor_id = $this->resolve_vendor_id($request);

        if (is_wp_error($vendor_id)) {
            return $vendor_id;
        }

        wcfmu_shipstation_v2_bootstrap_marketplace($vendor_id);

        $page         = max(1, absint($request->get_param('page')));
        $per_page     = min(max(1, absint($request->get_param('per_page'))), 500);
        $modified_raw = $request->get_param('modified_after');
        $statuses     = $this->get_requested_statuses($vendor_id, $request->get_param('status_mapping'));
        $query_args   = [
            'start_date' => $modified_raw && false !== strtotime($modified_raw) ? gmdate('Y-m-d H:i:s', strtotime($modified_raw)) : '1970-01-01 00:00:00',
            'end_date'   => current_time('mysql', true),
            'status'     => $statuses,
            'page'       => $page,
            'per_page'   => $per_page,
            'limit'      => ($per_page * ($page - 1)),
            'offset'     => $per_page,
            'date_type'  => $modified_raw ? 'modified' : 'created',
            'order_by'   => $modified_raw ? 'modified' : 'created',
            'order'      => $modified_raw ? 'DESC' : 'ASC',
        ];

        $orders = wcfmu_shipstation_v2_get_orders($vendor_id, $query_args);

        $count_args          = $query_args;
        $count_args['count'] = true;
        $count_result        = wcfmu_shipstation_v2_get_orders($vendor_id, $count_args);
        $count_object        = !empty($count_result) ? array_pop($count_result) : null;
        $total_orders        = $count_object && isset($count_object->count) ? absint($count_object->count) : 0;
        $total_pages         = $total_orders > 0 ? (int) ceil($total_orders / $per_page) : 0;

        $response_data = [
            'sales_orders' => [],
            'pagination'   => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total_orders,
                'total_pages' => $total_pages,
                'has_more'    => $page < $total_pages,
            ],
        ];

        if (empty($orders)) {
            return new WP_REST_Response($response_data, 200);
        }

        $id_field = OrderUtil::custom_orders_table_usage_is_enabled() ? 'id' : 'ID';
        $order_ids = wp_list_pluck($orders, $id_field);

        foreach ($order_ids as $order_id) {
            if (!apply_filters('wcfmu_shipstation_export_order', true, $order_id)) {
                continue;
            }

            $order = wc_get_order($order_id);

            if (!$order instanceof WC_Order) {
                continue;
            }

            $response_data['sales_orders'][] = $this->get_order_data($order, $vendor_id);
            $this->mark_order_exported($order, $vendor_id);
        }

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Handle shipment notifications from ShipStation.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function update_orders_shipments($request) {
        $vendor_id = $this->resolve_vendor_id($request);

        if (is_wp_error($vendor_id)) {
            return $vendor_id;
        }

        wcfmu_shipstation_v2_bootstrap_marketplace($vendor_id);

        $payload       = $request->get_json_params();
        $notifications = isset($payload['notifications']) && is_array($payload['notifications']) ? $payload['notifications'] : [];

        if (empty($notifications)) {
            return new WP_REST_Response('Invalid request format.', 400);
        }

        $results = [];

        foreach ($notifications as $notification) {
            $notification_id = !empty($notification['notification_id']) ? sanitize_text_field($notification['notification_id']) : '';
            $order_id        = !empty($notification['order_id']) ? absint($notification['order_id']) : 0;

            if (!$order_id && !empty($notification['order_number'])) {
                $order_id = $this->get_order_id($notification['order_number']);
            }

            if (!$notification_id || !$order_id) {
                $results[] = [
                    'notification_id' => $notification_id,
                    'status'          => 'failure',
                    'failure_reason'  => __('Empty notification ID or order ID.', 'wc-frontend-manager-ultimate'),
                ];
                continue;
            }

            if (!$this->vendor_has_order($vendor_id, $order_id)) {
                $results[] = [
                    'notification_id' => $notification_id,
                    'status'          => 'failure',
                    'failure_reason'  => __('Order does not belong to this vendor.', 'wc-frontend-manager-ultimate'),
                ];
                continue;
            }

            $shipstation_data = [
                'tracking_number' => !empty($notification['tracking_number']) ? sanitize_text_field($notification['tracking_number']) : '',
                'carrier'         => !empty($notification['carrier_code']) ? sanitize_text_field($notification['carrier_code']) : '',
                'ship_date'       => !empty($notification['ship_date']) && false !== strtotime($notification['ship_date']) ? strtotime($notification['ship_date']) : current_time('timestamp'),
                'xml'             => '',
            ];

            shipstation_v2_order_mark_shipped($order_id, $vendor_id, $shipstation_data, false);

            $results[] = [
                'notification_id' => $notification_id,
                'status'          => 'success',
                'order_id'        => $order_id,
            ];
        }

        return new WP_REST_Response(
            [
                'notification_results' => $results,
            ],
            200
        );
    }

    /**
     * Resolve which vendor the REST request should operate on.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return int|WP_Error
     */
    protected function resolve_vendor_id($request) {
        $current_user_id = get_current_user_id();

        if ($current_user_id && function_exists('wcfm_is_vendor') && wcfm_is_vendor($current_user_id)) {
            return absint($current_user_id);
        }

        if (current_user_can('manage_woocommerce') || current_user_can('shop_staff')) {
            $vendor_id = absint($request->get_param('vendor_id'));

            if ($vendor_id && function_exists('wcfm_is_vendor') && wcfm_is_vendor($vendor_id)) {
                return $vendor_id;
            }
        }

        return new WP_Error(
            'wcfmu_shipstation_v2_rest_vendor_required',
            __('A vendor-scoped API key is required for ShipStation.', 'wc-frontend-manager-ultimate'),
            ['status' => 403]
        );
    }

    /**
     * Get statuses that should be exported for this request.
     *
     * @param int              $vendor_id       Vendor user ID.
     * @param array|string|nil $status_mappings Raw status mapping request value.
     *
     * @return array
     */
    protected function get_requested_statuses($vendor_id, $status_mappings) {
        $export_statuses = $this->get_export_statuses($vendor_id);

        if (empty($status_mappings)) {
            return $export_statuses;
        }

        $status_mappings = is_array($status_mappings) ? $status_mappings : [$status_mappings];
        $requested       = [];

        foreach ($status_mappings as $mapping) {
            $parts = explode(':', wc_clean($mapping));

            if (count($parts) !== 2 || empty($parts[1])) {
                continue;
            }

            $requested = array_merge($requested, $this->get_wc_statuses_from_shipstation_status($parts[1]));
        }

        $requested = array_unique(array_filter($requested));

        if (empty($requested)) {
            return $export_statuses;
        }

        $intersected = array_values(array_intersect($requested, $export_statuses));

        return !empty($intersected) ? $intersected : ['shipstation-unknown'];
    }

    /**
     * Get vendor export statuses from the saved settings.
     *
     * @param int $vendor_id Vendor user ID.
     *
     * @return array
     */
    protected function get_export_statuses($vendor_id) {
        $settings = get_user_meta($vendor_id, 'wcfm_shipstation_setting', true);
        $settings = is_array($settings) ? $settings : [];
        $statuses = !empty($settings['export_statuses']) && is_array($settings['export_statuses']) ? $settings['export_statuses'] : [
            'processing',
            'on-hold',
            'completed',
            'cancelled',
        ];

        return array_values(array_unique(array_map(function ($status) {
            return str_replace('wc-', '', $status);
        }, $statuses)));
    }

    /**
     * Convert a ShipStation status into the matching WooCommerce statuses.
     *
     * @param string $shipstation_status ShipStation status.
     *
     * @return array
     */
    protected function get_wc_statuses_from_shipstation_status($shipstation_status) {
        $map = [
            'AwaitingPayment'  => ['pending', 'failed'],
            'AwaitingShipment' => ['processing'],
            'OnHold'           => ['on-hold'],
            'Shipped'          => ['completed'],
            'Cancelled'        => ['cancelled', 'refunded'],
            'PaymentCancelled' => ['cancelled', 'refunded', 'failed'],
        ];

        return $map[$shipstation_status] ?? [];
    }

    /**
     * Build a ShipStation order payload for a vendor order.
     *
     * @param WC_Order $order     WooCommerce order.
     * @param int      $vendor_id Vendor user ID.
     *
     * @return array
     */
    protected function get_order_data($order, $vendor_id) {
        $totals = $this->get_vendor_order_totals($vendor_id, $order);
        $notes  = $this->get_notes($order);
        $data   = [
            'order_id'               => $order->get_id(),
            'order_number'           => ltrim($order->get_order_number(), '#'),
            'status'                 => $this->get_shipstation_status_from_order($order->get_status()),
            'paid_date'              => $this->format_shipstation_date($order->get_date_paid()),
            'requested_fulfillments' => $this->get_requested_fulfillments($order, $vendor_id),
            'buyer'                  => $this->get_buyer($order),
            'bill_to'                => $this->get_bill_to($order),
            'currency'               => $order->get_currency(),
            'payment'                => [
                'payment_status'   => $this->get_payment_status($order->get_status()),
                'taxes'            => $totals['tax'] > 0 ? [
                    [
                        'amount'      => $totals['tax'],
                        'description' => __('Tax', 'wc-frontend-manager-ultimate'),
                    ],
                ] : [],
                'shipping_charges' => $totals['shipping'] > 0 ? [
                    [
                        'amount'      => $totals['shipping'],
                        'description' => implode(' | ', $this->get_shipping_methods($order)),
                    ],
                ] : [],
                'amount_paid'      => $totals['total'],
                'payment_method'   => $order->get_payment_method(),
            ],
            'ship_from'              => $this->get_ship_from(),
            'order_url'              => $order->get_checkout_order_received_url(),
            'notes'                  => $notes,
            'created_date_time'      => $this->format_shipstation_date($order->get_date_created()),
            'modified_date_time'     => $this->format_shipstation_date($order->get_date_modified()),
        ];

        return apply_filters('wcfmu_shipstation_v2_rest_order_data', $data, $order, $vendor_id);
    }

    /**
     * Get the vendor-scoped requested fulfillment payload.
     *
     * @param WC_Order $order     WooCommerce order.
     * @param int      $vendor_id Vendor user ID.
     *
     * @return array
     */
    protected function get_requested_fulfillments($order, $vendor_id) {
        $line_items        = shipstation_v2_valid_line_items($order->get_items('line_item'), $order->get_id(), $vendor_id);
        $fulfillment_items = [];

        foreach ($line_items as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            if (!$product || !$product->needs_shipping()) {
                continue;
            }

            $quantity = max(0, $item->get_quantity() - absint($order->get_qty_refunded_for_item($item->get_id())));

            if (0 === $quantity) {
                continue;
            }

            $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
            $taxes      = [];
            $item_taxes = $item->get_taxes();

            if (!empty($item_taxes['total']) && is_array($item_taxes['total'])) {
                foreach ($item_taxes['total'] as $tax_amount) {
                    $tax_amount = (float) $tax_amount;

                    if ($tax_amount <= 0) {
                        continue;
                    }

                    $taxes[] = [
                        'amount'      => $tax_amount,
                        'description' => __('Tax', 'wc-frontend-manager-ultimate'),
                    ];
                }
            }

            $fulfillment_items[] = array_filter(
                [
                    'line_item_id'       => $item->get_id(),
                    'description'        => $item->get_name(),
                    'product'            => [
                        'product_id'  => $product_id,
                        'name'        => $item->get_name(),
                        'description' => $product->get_description(),
                        'identifiers' => [
                            'sku' => $product->get_sku(),
                        ],
                        'weight'      => $this->get_item_weight($product),
                        'dimensions'  => $this->get_item_dimensions($product),
                        'urls'        => [
                            'image_url'     => $this->get_image_src_url($product->get_image_id(), 'full'),
                            'product_url'   => $product->get_permalink(),
                            'thumbnail_url' => $this->get_image_src_url($product->get_image_id(), 'woocommerce_thumbnail'),
                        ],
                    ],
                    'quantity'           => $quantity,
                    'unit_price'         => (float) $order->get_item_subtotal($item, false, true),
                    'taxes'              => $taxes,
                    'item_url'           => $product->get_permalink(),
                    'modified_date_time' => $this->format_shipstation_date($order->get_date_modified()),
                ],
                function ($value) {
                    return !empty($value) || 0 === $value || '0' === $value;
                }
            );
        }

        if (empty($fulfillment_items)) {
            return [];
        }

        return [
            [
                'requested_fulfillment_id' => '',
                'ship_to'                  => $this->get_ship_to($order),
                'items'                    => $fulfillment_items,
                'extensions'               => (object) $this->get_custom_fields($order),
                'shipping_preferences'     => [
                    'gift'             => false,
                    'shipping_service' => implode(' | ', $this->get_shipping_methods($order)),
                ],
            ],
        ];
    }

    /**
     * Get buyer details for ShipStation.
     *
     * @param WC_Order $order WooCommerce order.
     *
     * @return array
     */
    protected function get_buyer($order) {
        $buyer = $order->get_user();

        if ($buyer) {
            return [
                'buyer_id' => $buyer->user_login,
                'name'     => trim($buyer->user_firstname . ' ' . $buyer->user_lastname),
                'email'    => $buyer->user_email,
                'phone'    => $order->get_billing_phone(),
            ];
        }

        return [
            'name'  => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
        ];
    }

    /**
     * Get billing address payload.
     *
     * @param WC_Order $order WooCommerce order.
     *
     * @return array
     */
    protected function get_bill_to($order) {
        return [
            'email'          => $order->get_billing_email(),
            'name'           => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'phone'          => $order->get_billing_phone(),
            'company'        => $order->get_billing_company(),
            'address_line_1' => $order->get_billing_address_1(),
            'address_line_2' => $order->get_billing_address_2(),
            'city'           => $order->get_billing_city(),
            'state_province' => $order->get_billing_state(),
            'postal_code'    => $order->get_billing_postcode(),
            'country_code'   => $order->get_billing_country(),
        ];
    }

    /**
     * Get shipping destination payload.
     *
     * @param WC_Order $order WooCommerce order.
     *
     * @return array
     */
    protected function get_ship_to($order) {
        $shipping_country = $order->get_shipping_country();
        $use_billing      = empty($shipping_country);

        return [
            'name'           => trim(($use_billing ? $order->get_billing_first_name() : $order->get_shipping_first_name()) . ' ' . ($use_billing ? $order->get_billing_last_name() : $order->get_shipping_last_name())),
            'company'        => $use_billing ? $order->get_billing_company() : $order->get_shipping_company(),
            'phone'          => $order->get_billing_phone(),
            'address_line_1' => $use_billing ? $order->get_billing_address_1() : $order->get_shipping_address_1(),
            'address_line_2' => $use_billing ? $order->get_billing_address_2() : $order->get_shipping_address_2(),
            'city'           => $use_billing ? $order->get_billing_city() : $order->get_shipping_city(),
            'state_province' => $use_billing ? $order->get_billing_state() : $order->get_shipping_state(),
            'postal_code'    => $use_billing ? $order->get_billing_postcode() : $order->get_shipping_postcode(),
            'country_code'   => $use_billing ? $order->get_billing_country() : $order->get_shipping_country(),
        ];
    }

    /**
     * Get the ship-from payload using the site's base address.
     *
     * @return array
     */
    protected function get_ship_from() {
        return [
            'name'           => '',
            'company'        => '',
            'phone'          => '',
            'address_line_1' => WC()->countries->get_base_address(),
            'address_line_2' => WC()->countries->get_base_address_2(),
            'address_line_3' => '',
            'city'           => WC()->countries->get_base_city(),
            'state_province' => WC()->countries->get_base_state(),
            'postal_code'    => WC()->countries->get_base_postcode(),
            'country_code'   => WC()->countries->get_base_country(),
        ];
    }

    /**
     * Convert WooCommerce order status to ShipStation order status.
     *
     * @param string $order_status WooCommerce order status.
     *
     * @return string
     */
    protected function get_shipstation_status_from_order($order_status) {
        switch ($order_status) {
            case 'pending':
            case 'failed':
                return 'AwaitingPayment';
            case 'processing':
                return 'AwaitingShipment';
            case 'on-hold':
                return 'OnHold';
            case 'completed':
                return 'Shipped';
            case 'cancelled':
            case 'refunded':
                return 'Cancelled';
            default:
                return 'Unknown';
        }
    }

    /**
     * Convert WooCommerce order status to ShipStation payment status.
     *
     * @param string $order_status WooCommerce order status.
     *
     * @return string
     */
    protected function get_payment_status($order_status) {
        switch ($order_status) {
            case 'pending':
            case 'on-hold':
                return 'AwaitingPayment';
            case 'cancelled':
            case 'refunded':
                return 'PaymentCancelled';
            case 'failed':
                return 'PaymentFailed';
            default:
                return 'Paid';
        }
    }

    /**
     * Get vendor-scoped order totals using commission data when available.
     *
     * @param int      $vendor_id Vendor user ID.
     * @param WC_Order $order     WooCommerce order.
     *
     * @return array
     */
    protected function get_vendor_order_totals($vendor_id, $order) {
        global $WCFMmp;

        $totals = [
            'total'    => (float) $order->get_total(),
            'tax'      => (float) $order->get_total_tax(),
            'shipping' => (float) $order->get_shipping_total(),
        ];

        if (!isset($WCFMmp->wcfmmp_commission) || !is_object($WCFMmp->wcfmmp_commission)) {
            return $totals;
        }

        $commission_ids = wcfmu_v2_get_order_commission_ids_by_vendor($vendor_id, $order->get_id());

        if (empty($commission_ids)) {
            return $totals;
        }

        $totals['total']    = (float) $WCFMmp->wcfmmp_commission->wcfmmp_get_commission_meta_sum($commission_ids, 'gross_total');
        $totals['tax']      = (float) $WCFMmp->wcfmmp_commission->wcfmmp_get_commission_meta_sum($commission_ids, 'gross_tax_cost');
        $totals['shipping'] = (float) $WCFMmp->wcfmmp_commission->wcfmmp_get_commission_meta_sum($commission_ids, 'gross_shipping_cost');

        return $totals;
    }

    /**
     * Mark a vendor order as exported.
     *
     * @param WC_Order $order     WooCommerce order.
     * @param int      $vendor_id Vendor user ID.
     *
     * @return void
     */
    protected function mark_order_exported($order, $vendor_id) {
        if ('yes' === $order->get_meta('_shipstation_exported_' . $vendor_id, true)) {
            return;
        }

        $comment_id = $order->add_order_note(
            sprintf(
                __('Order has been exported to Shipstation for store %s', 'wc-frontend-manager-ultimate'),
                wcfm_get_vendor_store_name($vendor_id)
            )
        );
        add_comment_meta($comment_id, '_vendor_id', $vendor_id);

        $order->update_meta_data('_shipstation_exported_' . $vendor_id, 'yes');
        $order->save();
    }

    /**
     * Get order notes in ShipStation's note format.
     *
     * @param WC_Order $order WooCommerce order.
     *
     * @return array
     */
    protected function get_notes($order) {
        $notes = [];

        if (!empty($order->get_customer_note())) {
            $notes[] = [
                'type' => 'NotesFromBuyer',
                'text' => $order->get_customer_note(),
            ];
        }

        return $notes;
    }

    /**
     * Get ShipStation custom fulfillment fields as an object-compatible map.
     *
     * @param WC_Order $order WooCommerce order.
     *
     * @return array
     */
    protected function get_custom_fields($order) {
        $custom_fields = [];

        // ShipStation reserves custom_field_1 for coupon codes.
        $custom_fields['custom_field_1'] = implode(' | ', $order->get_coupon_codes());

        $meta_key = apply_filters('wcfmu_shipstation_export_custom_field_2', '');
        if ($meta_key) {
            $custom_fields['custom_field_2'] = apply_filters(
                'wcfmu_shipstation_export_custom_field_2_value',
                $order->get_meta($meta_key, true),
                $order->get_id()
            );
        }

        $meta_key = apply_filters('wcfmu_shipstation_export_custom_field_3', '');
        if ($meta_key) {
            $custom_fields['custom_field_3'] = apply_filters(
                'wcfmu_shipstation_export_custom_field_3_value',
                $order->get_meta($meta_key, true),
                $order->get_id()
            );
        }

        return $custom_fields;
    }

    /**
     * Get order shipping method labels.
     *
     * @param WC_Order $order WooCommerce order.
     *
     * @return array
     */
    protected function get_shipping_methods($order) {
        $shipping_methods      = $order->get_shipping_methods();
        $shipping_method_names = [];

        foreach ($shipping_methods as $shipping_method) {
            $shipping_method_names[] = preg_replace('/[^A-Za-z0-9 \-\.\_,]/', '', $shipping_method['name']);
        }

        return array_filter($shipping_method_names);
    }

    /**
     * Format a WooCommerce date for ShipStation.
     *
     * @param WC_DateTime|null $date Date object.
     *
     * @return string
     */
    protected function format_shipstation_date($date) {
        if (!$date instanceof WC_DateTime) {
            return '';
        }

        $utc_date = clone $date;
        $utc_date->setTimezone(new DateTimeZone('UTC'));

        return $utc_date->format('Y-m-d\TH:i:s') . '.000Z';
    }

    /**
     * Get normalized product weight for ShipStation.
     *
     * @param WC_Product $product WooCommerce product.
     *
     * @return array
     */
    protected function get_item_weight($product) {
        $weight_unit    = strtolower(get_option('woocommerce_weight_unit'));
        $shipstation_unit = 'Kilogram';

        switch ($weight_unit) {
            case 'g':
                $shipstation_unit = 'Gram';
                break;
            case 'lbs':
                $shipstation_unit = 'Pound';
                break;
            case 'oz':
                $shipstation_unit = 'Ounce';
                break;
            default:
                $weight_unit      = 'kg';
                $shipstation_unit = 'Kilogram';
                break;
        }

        return [
            'unit'  => $shipstation_unit,
            'value' => wc_get_weight((float) $product->get_weight(), $weight_unit),
        ];
    }

    /**
     * Get normalized product dimensions for ShipStation.
     *
     * @param WC_Product $product WooCommerce product.
     *
     * @return array
     */
    protected function get_item_dimensions($product) {
        $dimension_unit    = strtolower(get_option('woocommerce_dimension_unit'));
        $shipstation_unit = 'Centimeter';

        if ('in' === $dimension_unit) {
            $shipstation_unit = 'Inch';
        } else {
            $dimension_unit = 'cm';
        }

        return [
            'length' => wc_get_dimension((float) $product->get_length(), $dimension_unit),
            'width'  => wc_get_dimension((float) $product->get_width(), $dimension_unit),
            'height' => wc_get_dimension((float) $product->get_height(), $dimension_unit),
            'unit'   => $shipstation_unit,
        ];
    }

    /**
     * Resolve an attachment image URL.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $size          Image size.
     *
     * @return string
     */
    protected function get_image_src_url($attachment_id, $size) {
        if (!$attachment_id) {
            return '';
        }

        $image = wp_get_attachment_image_src($attachment_id, $size);

        return is_array($image) && !empty($image[0]) ? $image[0] : '';
    }

    /**
     * Determine whether the order belongs to the vendor.
     *
     * @param int $vendor_id Vendor user ID.
     * @param int $order_id  WooCommerce order ID.
     *
     * @return bool
     */
    protected function vendor_has_order($vendor_id, $order_id) {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE vendor_id = %d AND order_id = %d",
                $vendor_id,
                $order_id
            )
        );

        return (bool) $count;
    }

    /**
     * Convert the received ShipStation order number into a local order ID.
     *
     * @param string $order_number ShipStation order number.
     *
     * @return int
     */
    protected function get_order_id($order_number) {
        preg_match('/\((.*?)\)/', $order_number, $matches);

        if (is_array($matches) && isset($matches[1])) {
            $order_id = $matches[1];
        } elseif (function_exists('wc_sequential_order_numbers')) {
            $order_id = wc_sequential_order_numbers()->find_order_by_order_number($order_number);
        } elseif (function_exists('wc_seq_order_number_pro')) {
            $order_id = wc_seq_order_number_pro()->find_order_by_order_number($order_number);
        } else {
            $order_id = $order_number;
        }

        if (0 === (int) $order_id) {
            $order_id = $order_number;
        }

        return absint(apply_filters('wcfmu_shipstation_get_order_id', $order_id));
    }
}
