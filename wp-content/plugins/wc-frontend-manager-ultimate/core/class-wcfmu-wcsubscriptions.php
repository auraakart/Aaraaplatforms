<?php

/**
 * WCFMu plugin core
 *
 * WC Subscriptions Support
 *
 * @author  WC Lovers
 * @package wcfmu/core
 * @version 2.2.2
 */

class WCFMu_WCSubscriptions
{

    /**
     * Billing fields.
     *
     * @var array
     */
    protected static $billing_fields = [];

    /**
     * Shipping fields.
     *
     * @var array
     */
    protected static $shipping_fields = [];


    public function __construct()
    {
        global $WCFM, $WCFMu;

        if (wcfm_is_subscription()) {
            // WC Subscriptions Query Var Filter
            add_filter('wcfm_query_vars', [ &$this, 'wcs_wcfm_query_vars' ], 20);
            add_filter('wcfm_endpoint_title', [ &$this, 'wcs_wcfm_endpoint_title' ], 20, 2);
            add_action('init', [ &$this, 'wcs_wcfm_init' ], 20);

            // Subscriptions Endpoint Edit
            add_filter('wcfm_endpoints_slug', [ $this, 'wcs_wcfm_endpoints_slug' ]);

            // WC Subscriptions Menu Filter
            add_filter('wcfm_menus', [ &$this, 'wcs_wcfm_menus' ], 20);

            // Subscriptions Product Type
            add_filter('wcfm_product_types', [ &$this, 'wcs_product_types' ], 40);

            // Subscriptions Load WCFMu Scripts
            add_action('wcfm_load_scripts', [ &$this, 'wcs_load_scripts' ], 30);

            // Subscriptions Load WCFMu Styles
            add_action('wcfm_load_styles', [ &$this, 'wcs_load_styles' ], 30);

            // Subscriptions Load WCFMu views
            add_action('wcfm_load_views', [ &$this, 'wcs_load_views' ], 30);

            // Subscriptions Ajax Controllers
            add_action('after_wcfm_ajax_controller', [ &$this, 'wcs_ajax_controller' ]);

            // Subscriptions Product options
            add_filter('wcfm_product_manage_fields_general', [ &$this, 'wcs_product_manage_fields_general' ], 40, 5);
            add_filter('wcfm_product_manage_fields_shipping', [ &$this, 'wcs_product_manage_fields_shipping' ], 40, 2);
            add_filter('wcfm_product_manage_fields_advanced', [ &$this, 'wcs_product_manage_fields_advanced' ], 40, 2);
            add_filter('wcfm_product_manage_fields_variations', [ &$this, 'wcs_product_manage_fields_variations' ], 40, 4);

            // Subscriptions Product Meta Data Save
            add_action('after_wcfm_products_manage_meta_save', [ &$this, 'wcs_wcfm_product_meta_save' ], 40, 2);
            add_action('after_wcfm_product_variation_meta_save', [ &$this, 'wcs_product_variation_save' ], 40, 4);

            // Subscription Product Date Edit
            add_filter('wcfm_variation_edit_data', [ &$this, 'wcs_product_data_variations' ], 40, 3);

            // Subscription Status Update
            add_action('wp_ajax_wcfm_modify_subscription_status', [ &$this, 'wcfm_modify_subscription_status' ]);

            // Pause / Resume
            add_action('init', [ $this, 'register_pause_subscription_status' ]);
            add_filter('woocommerce_subscriptions_registered_statuses', [ $this, 'add_pause_to_wcs_statuses' ]);
            add_filter('wc_subscription_statuses', [ $this, 'add_pause_label' ]);
            add_action('wp_ajax_wcfm_pause_subscription',  [ $this, 'wcfm_pause_subscription' ]);
            add_action('wp_ajax_wcfm_resume_subscription', [ $this, 'wcfm_resume_subscription' ]);
            add_action('wcfmu_begin_pause_subscription',   [ $this, 'begin_pause_subscription' ]);
            add_action('wcfmu_auto_resume_subscription',   [ $this, 'auto_resume_subscription' ]);
            // Date-scoped pause: fired at each pause date and each day-after so the
            // status matches TODAY's membership (paused only on chosen dates).
            add_action('wcfmu_sync_pause_subscription',    [ $this, 'sync_pause_status' ]);
            // Self-healing: reconcile pause/resume from the saved dates on load,
            // so the status is correct for the day even if a WP-Cron tick was
            // missed (e.g. DISABLE_WP_CRON or a low-traffic morning).
            add_action('init', [ $this, 'reconcile_pause_states' ], 99);

            // Delivery Dates
            add_action('wp_ajax_wcfm_save_sub_delivery_dates', [ $this, 'wcfm_save_sub_delivery_dates' ]);

            // Delivery Schedule Update
            add_action('wp_ajax_wcfm_update_subscription_schedule', [ $this, 'wcfm_update_subscription_schedule' ]);

            // Subscription Item Management
            add_action('wp_ajax_wcfm_sub_save_item',   [ $this, 'wcfm_sub_save_item' ]);
            add_action('wp_ajax_wcfm_sub_remove_item', [ $this, 'wcfm_sub_remove_item' ]);
            add_action('wp_ajax_wcfm_sub_add_item',    [ $this, 'wcfm_sub_add_item' ]);
        }//end if

    }//end __construct()


    /**
     * WC Subscriptions Query Var
     */
    function wcs_wcfm_query_vars($query_vars)
    {
        $wcfm_modified_endpoints = wcfm_get_option('wcfm_endpoints', []);

        // WC 3.6 FIX
        if (isset($wcfm_modified_endpoints['wcfm-subscriptions']) && ! empty($wcfm_modified_endpoints['wcfm-subscriptions']) && $wcfm_modified_endpoints['wcfm-subscriptions'] == 'subscriptions') {
            $wcfm_modified_endpoints['wcfm-subscriptions'] = 'subscriptionslist';
        }

        $query_subscriptions_vars = [
            'wcfm-subscriptions'        => ! empty($wcfm_modified_endpoints['wcfm-subscriptions']) ? $wcfm_modified_endpoints['wcfm-subscriptions'] : 'subscriptionslist',
            'wcfm-subscriptions-manage' => ! empty($wcfm_modified_endpoints['wcfm-subscriptions-manage']) ? $wcfm_modified_endpoints['wcfm-subscriptions-manage'] : 'subscriptions-manage',
        ];

        $query_vars = array_merge($query_vars, $query_subscriptions_vars);

        return $query_vars;

    }//end wcs_wcfm_query_vars()


    /**
     * WC Subscriptions End Point Title
     */
    function wcs_wcfm_endpoint_title($title, $endpoint)
    {
        global $wp;
        switch ($endpoint) {
            case 'wcfm-subscriptions':
                $title = __('Subscriptions List', 'wc-frontend-manager-ultimate');
                break;

            case 'wcfm-subscriptions-manage':
                // translators: 1) subscriptions id
                $title = sprintf(__('Subscription Manage #%s', 'wc-frontend-manager-ultimate'), $wp->query_vars['wcfm-subscriptions-manage']);
                break;
        }

        return $title;

    }//end wcs_wcfm_endpoint_title()


    /**
     * WC Subscriptions Endpoint Intialize
     */
    function wcs_wcfm_init()
    {
        global $WCFM_Query;

        // Intialize WCFM End points
        $WCFM_Query->init_query_vars();
        $WCFM_Query->add_endpoints();

        if (! get_option('wcfm_updated_end_point_wc_subscriptions')) {
            // Flush rules after endpoint update
            flush_rewrite_rules();
            update_option('wcfm_updated_end_point_wc_subscriptions', 1);
        }

    }//end wcs_wcfm_init()


    /**
     * WC Subscriptions Menu
     */
    function wcs_wcfm_menus($menus)
    {
        global $WCFM;

        if (apply_filters('wcfm_is_allow_subscriptions', true) && apply_filters('wcfm_is_allow_subscription_list', true)) {
            $menus = (array_slice($menus, 0, 3, true) + [
                'wcfm-subscriptions' => [
                    'label'    => __('Subscriptions', 'woocommerce-subscriptions'),
                    'url'      => get_wcfm_subscriptions_url(),
                    'icon'     => 'money-bill-alt',
                    'priority' => 21,
                ],
            ] + array_slice($menus, 3, (count($menus) - 3), true));
        }

        return $menus;

    }//end wcs_wcfm_menus()


    /**
     * Subscriptions Endpoiint Edit
     */
    function wcs_wcfm_endpoints_slug($endpoints)
    {
        $subscriptions_endpoints = [
            'wcfm-subscriptions'        => 'subscriptionslist',
            'wcfm-subscriptions-manage' => 'subscriptions-manage',
        ];

        $endpoints = array_merge($endpoints, $subscriptions_endpoints);

        return $endpoints;

    }//end wcs_wcfm_endpoints_slug()


    /**
     * WC Subscriptions Product Type
     */
    function wcs_product_types($pro_types)
    {
        global $WCFM, $WCFMu;

        $pro_types['variable-subscription'] = __('Variable subscription', 'woocommerce-subscriptions');

        return $pro_types;

    }//end wcs_product_types()


    /**
     * WC Subscription Scripts
     */
    public function wcs_load_scripts($end_point)
    {
        global $WCFM, $WCFMu;

        switch ($end_point) {
            case 'wcfm-subscriptions':
                $WCFM->library->load_datatable_lib();
                $WCFM->library->load_daterangepicker_lib();
                $WCFM->library->load_select2_lib();
                wp_enqueue_script('wcfm_subscriptions_js', $WCFMu->library->js_lib_url.'wc_subscriptions/wcfmu-script-wcsubscriptions.js', [ 'jquery', 'dataTables_js' ], time(), true);

                // Screen manager
                $wcfm_screen_manager      = (array) get_option('wcfm_screen_manager');
                $wcfm_screen_manager_data = [];
                if (isset($wcfm_screen_manager['subscription'])) {
                    $wcfm_screen_manager_data = $wcfm_screen_manager['subscription'];
                }

                if (! isset($wcfm_screen_manager_data['admin'])) {
                    $wcfm_screen_manager_data['admin']  = $wcfm_screen_manager_data;
                    $wcfm_screen_manager_data['vendor'] = $wcfm_screen_manager_data;
                }

                if (wcfm_is_vendor()) {
                    $wcfm_screen_manager_data = $wcfm_screen_manager_data['vendor'];
                } else {
                    $wcfm_screen_manager_data = $wcfm_screen_manager_data['admin'];
                }

                if (apply_filters('wcfm_subscriptions_additonal_data_hidden', true)) {
                    $wcfm_screen_manager_data[10] = 'yes';
                }

                wp_localize_script('wcfm_subscriptions_js', 'wcfm_subscriptions_screen_manage', $wcfm_screen_manager_data);
                break;

            case 'wcfm-subscriptions-manage':
                $WCFM->library->load_datepicker_lib();
                $WCFM->library->load_select2_lib();
                wp_register_script('wcfm_jstz', plugin_dir_url(WC_Subscriptions::$plugin_file).'assets/js/admin/jstz.min.js');
                wp_register_script('wcfm_momentjs', plugin_dir_url(WC_Subscriptions::$plugin_file).'assets/js/admin/moment.min.js');
                wp_register_script('wcfmu_flatpickr_js', $WCFMu->library->js_lib_url.'wc_subscriptions/lib/flatpickr.min.js', [], '4.6.13', true);
                wp_enqueue_script('wcfm_subscriptions_manage_js', $WCFMu->library->js_lib_url.'wc_subscriptions/wcfmu-script-wcsubscriptions-manage.js', [ 'jquery', 'wcfm_jstz', 'wcfm_momentjs', 'wcfmu_flatpickr_js', 'select2_js' ], $WCFMu->version, true);

                wp_localize_script(
                    'wcfm_subscriptions_manage_js',
                    'wcs_admin_meta_boxes',
                    apply_filters(
                        'wcfm_subscriptions_admin_meta_boxes_script_parameters',
                        [
                            'i18n_start_date_notice'         => __('Please enter a start date in the past.', 'woocommerce-subscriptions'),
                            'i18n_past_date_notice'          => __('Please enter a date at least one hour into the future.', 'woocommerce-subscriptions'),
                            'i18n_next_payment_start_notice' => __('Please enter a date after the trial end.', 'woocommerce-subscriptions'),
                            'i18n_next_payment_trial_notice' => __('Please enter a date after the start date.', 'woocommerce-subscriptions'),
                            'i18n_trial_end_start_notice'    => __('Please enter a date after the start date.', 'woocommerce-subscriptions'),
                            'i18n_trial_end_next_notice'     => __('Please enter a date before the next payment.', 'woocommerce-subscriptions'),
                            'i18n_end_date_notice'           => __('Please enter a date after the next payment.', 'woocommerce-subscriptions'),
                            'process_renewal_action_warning' => __("Are you sure you want to process a renewal?\n\nThis will charge the customer and email them the renewal order (if emails are enabled).", 'woocommerce-subscriptions'),
                        // 'payment_method'                 => wcs_get_subscription( $post )->get_payment_method(),
                        // 'search_customers_nonce'         => wp_create_nonce( 'search-customers' ),
                        ]
                    )
                );
                break;
        }//end switch

    }//end wcs_load_scripts()


    /**
     * WC Subscription Styles
     */
    public function wcs_load_styles($end_point)
    {
        global $WCFM, $WCFMu;

        switch ($end_point) {
            case 'wcfm-subscriptions':
                wp_enqueue_style('wcfm_subscriptions_css', $WCFMu->library->css_lib_url.'wc_subscriptions/wcfmu-style-wcsubscriptions.css', [], $WCFMu->version);
                break;

            case 'wcfm-subscriptions-manage':
                wp_enqueue_style('collapsible_css', $WCFM->library->css_lib_url.'wcfm-style-collapsible.css', [], $WCFMu->version);
                wp_enqueue_style('wcfmu_flatpickr_css', $WCFMu->library->css_lib_url.'wc_subscriptions/lib/flatpickr.min.css', [], '4.6.13');
                wp_enqueue_style('wcfm_subscriptions_manage_css', $WCFMu->library->css_lib_url.'wc_subscriptions/wcfmu-style-wcsubscriptions-manage.css', [], $WCFMu->version);
                break;
        }

    }//end wcs_load_styles()


    /**
     * WC Subscription Views
     */
    public function wcs_load_views($end_point)
    {
        global $WCFM, $WCFMu;

        switch ($end_point) {
            case 'wcfm-subscriptions':
                $WCFMu->template->get_template('wc_subscriptions/wcfmu-view-wcsubscriptions.php');
                break;

            case 'wcfm-subscriptions-manage':
                $WCFMu->template->get_template('wc_subscriptions/wcfmu-view-wcsubscriptions-manage.php');
                break;
        }

    }//end wcs_load_views()


    /**
     * WC Subscription Ajax Controllers
     */
    public function wcs_ajax_controller()
    {
        global $WCFM, $WCFMu;

        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
			wp_send_json_error( __( 'Invalid nonce! Refresh your page and try again.', 'wc-frontend-manager-ultimate' ) );
			wp_die();
		}

        $controllers_path = $WCFMu->plugin_path.'controllers/wc_subscriptions/';

        $controller = '';
        if (isset($_POST['controller'])) {
            $controller = $_POST['controller'];

            switch ($controller) {
                case 'wcfm-subscriptions':
                    include_once $controllers_path.'wcfmu-controller-wcsubscriptions.php';
                    new WCFMu_WCSubscriptions_Controller();
                    break;

                case 'wcfm-subscriptions-manage':
                    include_once $controllers_path.'wcfmu-controller-wcsubscriptions-manage.php';
                    new WCFMu_WCSubscriptions_Manage_Controller();
                    break;
            }
        }

    }//end wcs_ajax_controller()


    /**
     * WC Subscriptions Product General options
     */
    function wcs_product_manage_fields_general($general_fields, $product_id, $product_type, $wcfm_is_translated_product=false, $wcfm_wpml_edit_disable_element='')
    {
        global $WCFM, $WCFMu;

        $sign_up_fee         = '';
        $chosen_trial_length = 0;
        $chosen_trial_period = '';

        if ($product_id) {
            $sign_up_fee         = get_post_meta($product_id, '_subscription_sign_up_fee', true);
            $chosen_trial_length = WC_Subscriptions_Product::get_trial_length($product_id);
            $chosen_trial_period = WC_Subscriptions_Product::get_trial_period($product_id);
        }

        $general_fields = (array_slice($general_fields, 0, 12, true) + [
            '_subscription_sign_up_fee'  => [
                'label'       => sprintf(esc_html__('Sign-up fee (%s)', 'woocommerce-subscriptions'), esc_html(get_woocommerce_currency_symbol())),
                'type'        => 'text',
                'placeholder' => 'e.g. 9.90',
                'class'       => 'wcfm-text wcfm_ele wcfm_ele_hide subscription'.' '.$wcfm_wpml_edit_disable_element,
                'label_class' => 'wcfm_title wcfm_ele wcfm_ele_hide subscription'.' '.$wcfm_wpml_edit_disable_element,
                'hints'       => __('Optionally include an amount to be charged at the outset of the subscription. The sign-up fee will be charged immediately, even if the product has a free trial or the payment dates are synced.', 'woocommerce-subscriptions'),
                'value'       => $sign_up_fee,
            ],
            '_subscription_trial_length' => [
                'label'       => esc_html__('Free Trial', 'woocommerce-subscriptions'),
                'type'        => 'number',
                'class'       => 'wcfm-text wcfm_ele wcfm_ele_hide subscription_price_ele subscription'.' '.$wcfm_wpml_edit_disable_element,
                'label_class' => 'wcfm_title wcfm_ele wcfm_ele_hide subscription'.' '.$wcfm_wpml_edit_disable_element,
                'hints'       => __('An optional period of time to wait before charging the first recurring payment. Any sign up fee will still be charged at the outset of the subscription.', 'woocommerce-subscriptions'),
                'value'       => $chosen_trial_length,
            ],
            '_subscription_trial_period' => [
                'type'        => 'select',
                'options'     => wcs_get_available_time_periods(),
                'class'       => 'wcfm-select wcfm_ele wcfm_ele_hide subscription_price_ele subscription'.' '.$wcfm_wpml_edit_disable_element,
                'label_class' => 'wcfm_title wcfm_ele wcfm_ele_hide subscription'.' '.$wcfm_wpml_edit_disable_element,
                'value'       => $chosen_trial_period,
            ],
        ] + array_slice($general_fields, 12, (count($general_fields) - 1), true));
        return $general_fields;

    }//end wcs_product_manage_fields_general()


    /**
     * WC Subscriptions Product Shipping options
     */
    function wcs_product_manage_fields_shipping($shipping_fields, $product_id)
    {
        global $WCFM, $WCFMu;

        $one_time_shipping = 'no';

        if ($product_id) {
            $one_time_shipping = get_post_meta($product_id, '_subscription_one_time_shipping', true) ? get_post_meta($product_id, '_subscription_one_time_shipping', true) : 'no';
        }

        $shipping_fields = (array_slice($shipping_fields, 0, 5, true) + [
            '_subscription_one_time_shipping' => [
                'label'       => esc_html__('One time shipping', 'woocommerce-subscriptions'),
                'type'        => 'checkbox',
                'class'       => 'wcfm-checkbox wcfm_ele subscription variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele subscription variable-subscription',
                'hints'       => __('Shipping for subscription products is normally charged on the initial order and all renewal orders. Enable this to only charge shipping once on the initial order. Note: for this setting to be enabled the subscription must not have a free trial or a synced renewal date.', 'woocommerce-subscriptions'),
                'value'       => 'yes',
                'dfvalue'     => $one_time_shipping,
            ],
        ] + array_slice($shipping_fields, 5, (count($shipping_fields) - 1), true));
        return $shipping_fields;

    }//end wcs_product_manage_fields_shipping()


    /**
     * WC Subscriptions Product Advanced options
     */
    function wcs_product_manage_fields_advanced($advanced_fields, $product_id)
    {
        global $WCFM, $WCFMu;

        $subscription_limit = '';

        if ($product_id) {
            $subscription_limit = get_post_meta($product_id, '_subscription_limit', true);
        }

        $advanced_fields = (array_slice($advanced_fields, 0, 3, true) + [
            '_subscription_limit' => [
                'label'       => esc_html__('Limit subscription', 'woocommerce-subscriptions'),
                'type'        => 'select',
                'options'     => [
                    'no'     => __('Do not limit', 'woocommerce-subscriptions'),
                    'active' => __('Limit to one active subscription', 'woocommerce-subscriptions'),
                    'any'    => __('Limit to one of any status', 'woocommerce-subscriptions'),
                ],
                'class'       => 'wcfm-select wcfm_ele subscription variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele subscription variable-subscription',
                'hints'       => __('Only allow a customer to have one subscription to this product.', 'woocommerce-subscriptions'),
                'value'       => $subscription_limit,
            ],
        ] + array_slice($advanced_fields, 3, (count($advanced_fields) - 1), true));
        return $advanced_fields;

    }//end wcs_product_manage_fields_advanced()


    /**
     * WC Subscriptions Variation aditional options
     */
    function wcs_product_manage_fields_variations($variation_fileds, $variations, $variation_shipping_option_array, $variation_tax_classes_options)
    {
        global $WCFM, $WCFMu;

        $variation_fileds = (array_slice($variation_fileds, 0, 6, true) + [
            '_subscription_price'           => [
                'label'       => sprintf(esc_html__('Subscription price (%s)', 'woocommerce-subscriptions'), esc_html(get_woocommerce_currency_symbol())),
                'type'        => 'text',
                'class'       => 'wcfm-text wcfm_ele subscription_price_ele variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription',
                'hints'       => __('Choose the subscription price, billing interval and period.', 'woocommerce-subscriptions'),
            ],
            '_subscription_period_interval' => [
                'type'        => 'select',
                'options'     => wcs_get_subscription_period_interval_strings(),
                'class'       => 'wcfm-select wcfm_ele subscription_price_ele variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription',
            ],
            '_subscription_period'          => [
                'type'        => 'select',
                'options'     => wcs_get_subscription_period_strings(),
                'class'       => 'wcfm-select wcfm_ele subscription_price_ele variable-subscription_period variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription',
            ],
            '_subscription_length_day'      => [
                'label'       => __('Subscription length', 'woocommerce-subscriptions'),
                'type'        => 'select',
                'options'     => wcs_get_subscription_ranges('day'),
                'class'       => 'wcfm-select wcfm_ele variable-subscription_length_ele variable-subscription_length_day variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription_length_ele variable-subscription_length_day variable-subscription',
                'hints'       => __('Automatically expire the subscription after this length of time. This length is in addition to any free trial or amount of time provided before a synchronised first renewal date.', 'woocommerce-subscriptions'),
            ],
            '_subscription_length_week'     => [
                'label'       => __('Subscription length', 'woocommerce-subscriptions'),
                'type'        => 'select',
                'options'     => wcs_get_subscription_ranges('week'),
                'class'       => 'wcfm-select wcfm_ele variable-subscription_length_ele variable-subscription_length_week variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription_length_ele variable-subscription_length_week variable-subscription',
                'hints'       => __('Automatically expire the subscription after this length of time. This length is in addition to any free trial or amount of time provided before a synchronised first renewal date.', 'woocommerce-subscriptions'),
            ],
            '_subscription_length_month'    => [
                'label'       => __('Subscription length', 'woocommerce-subscriptions'),
                'type'        => 'select',
                'options'     => wcs_get_subscription_ranges('month'),
                'class'       => 'wcfm-select wcfm_ele variable-subscription_length_ele variable-subscription_length_month variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription_length_ele variable-subscription_length_month variable-subscription',
                'hints'       => __('Automatically expire the subscription after this length of time. This length is in addition to any free trial or amount of time provided before a synchronised first renewal date.', 'woocommerce-subscriptions'),
            ],
            '_subscription_length_year'     => [
                'label'       => __('Subscription length', 'woocommerce-subscriptions'),
                'type'        => 'select',
                'options'     => wcs_get_subscription_ranges('year'),
                'class'       => 'wcfm-select wcfm_ele variable-subscription_length_ele variable-subscription_length_year variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription_length_ele variable-subscription_length_year variable-subscription',
                'hints'       => __('Automatically expire the subscription after this length of time. This length is in addition to any free trial or amount of time provided before a synchronised first renewal date.', 'woocommerce-subscriptions'),
            ],
            '_subscription_sign_up_fee'     => [
                'label'       => sprintf(esc_html__('Sign-up fee (%s)', 'woocommerce-subscriptions'), esc_html(get_woocommerce_currency_symbol())),
                'type'        => 'text',
                'placeholder' => 'e.g. 9.90',
                'class'       => 'wcfm-text wcfm_ele variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription',
                'hints'       => __('Optionally include an amount to be charged at the outset of the subscription. The sign-up fee will be charged immediately, even if the product has a free trial or the payment dates are synced.', 'woocommerce-subscriptions'),
            ],
            '_subscription_trial_length'    => [
                'label'       => esc_html__('Free Trial', 'woocommerce-subscriptions'),
                'type'        => 'number',
                'class'       => 'wcfm-text wcfm_ele subscription_trial_ele variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription',
                'hints'       => __('An optional period of time to wait before charging the first recurring payment. Any sign up fee will still be charged at the outset of the subscription.', 'woocommerce-subscriptions'),
            ],
            '_subscription_trial_period'    => [
                'type'        => 'select',
                'options'     => wcs_get_available_time_periods(),
                'class'       => 'wcfm-select wcfm_ele subscription_trial_ele variable-subscription',
                'label_class' => 'wcfm_title wcfm_ele variable-subscription',
            ],
        ] + array_slice($variation_fileds, 6, (count($variation_fileds) - 1), true));

        return $variation_fileds;

    }//end wcs_product_manage_fields_variations()


    /**
     * WC Subscriptions Product Meta data save
     */
    function wcs_wcfm_product_meta_save($new_product_id, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $WCFMu, $_POST;

        if ($wcfm_products_manage_form_data['product_type'] == 'subscription') {
            // Make sure trial period is within allowable range
            $subscription_ranges = wcs_get_subscription_ranges();

            $max_trial_length = (count($subscription_ranges[$wcfm_products_manage_form_data['_subscription_trial_period']]) - 1);

            $wcfm_products_manage_form_data['_subscription_trial_length'] = absint($wcfm_products_manage_form_data['_subscription_trial_length']);

            if ($wcfm_products_manage_form_data['_subscription_trial_length'] > $max_trial_length) {
                $wcfm_products_manage_form_data['_subscription_trial_length'] = $max_trial_length;
            }

            update_post_meta($new_product_id, '_subscription_trial_length', $wcfm_products_manage_form_data['_subscription_trial_length']);

            $wcfm_products_manage_form_data['_subscription_sign_up_fee']       = wc_format_decimal($wcfm_products_manage_form_data['_subscription_sign_up_fee']);
            $wcfm_products_manage_form_data['_subscription_one_time_shipping'] = isset($wcfm_products_manage_form_data['_subscription_one_time_shipping']) ? 'yes' : 'no';

            $subscription_fields = [
                '_subscription_sign_up_fee',
                '_subscription_trial_period',
                '_subscription_limit',
                '_subscription_one_time_shipping',
            ];

            foreach ($subscription_fields as $field_name) {
                if (isset($wcfm_products_manage_form_data[$field_name])) {
                    update_post_meta($new_product_id, $field_name, stripslashes($wcfm_products_manage_form_data[$field_name]));
                }
            }
        }//end if

    }//end wcs_wcfm_product_meta_save()


    /**
     * WC Subscriptions Variation Data Save
     */
    function wcs_product_variation_save($new_product_id, $variation_id, $variations, $wcfm_products_manage_form_data)
    {
        global $wpdb, $WCFM, $WCFMu;

        if (WC_Subscriptions_Product::is_subscription($new_product_id)) {
            $subscription_price = isset($variations['_subscription_price']) ? wc_format_decimal($variations['_subscription_price']) : '';
            update_post_meta($variation_id, '_subscription_price', $subscription_price);
            update_post_meta($variation_id, '_regular_price', $subscription_price);
            update_post_meta($new_product_id, '_price', $subscription_price);
            update_post_meta($variation_id, '_price', $subscription_price);

            $subscription_fields = [
                '_subscription_period',
                '_subscription_period_interval',
                '_subscription_sign_up_fee',
                '_subscription_trial_period',
                '_subscription_trial_length',
            ];

            foreach ($subscription_fields as $field_name) {
                if (isset($variations[$field_name])) {
                    update_post_meta($variation_id, $field_name, stripslashes($variations[$field_name]));
                }
            }

            update_post_meta($variation_id, '_subscription_length', stripslashes($variations['_subscription_length_'.$variations['_subscription_period']]));

            if (WC_Subscriptions::is_woocommerce_pre('3.0')) {
                $variable_subscription = wc_get_product($new_product_id);
                $variable_subscription->variable_product_sync();
            } else {
                WC_Product_Variable::sync($new_product_id);
            }
        }//end if

    }//end wcs_product_variation_save()


    /**
     * WC Subscriptions Variaton edit data
     */
    function wcs_product_data_variations($variations, $variation_id, $variation_id_key)
    {
        global $WCFM, $WCFMu;

        if ($variation_id) {
            $variations[$variation_id_key]['_subscription_price']           = get_post_meta($variation_id, '_subscription_price', true);
            $variations[$variation_id_key]['_subscription_period']          = get_post_meta($variation_id, '_subscription_period', true);
            $variations[$variation_id_key]['_subscription_period_interval'] = get_post_meta($variation_id, '_subscription_period_interval', true);
            $variations[$variation_id_key]['_subscription_sign_up_fee']     = get_post_meta($variation_id, '_subscription_sign_up_fee', true);
            $variations[$variation_id_key]['_subscription_trial_period']    = get_post_meta($variation_id, '_subscription_trial_period', true);
            $variations[$variation_id_key]['_subscription_trial_length']    = get_post_meta($variation_id, '_subscription_trial_length', true);
            $variations[$variation_id_key]['_subscription_length_day']      = get_post_meta($variation_id, '_subscription_length', true);
            $variations[$variation_id_key]['_subscription_length_week']     = get_post_meta($variation_id, '_subscription_length', true);
            $variations[$variation_id_key]['_subscription_length_month']    = get_post_meta($variation_id, '_subscription_length', true);
            $variations[$variation_id_key]['_subscription_length_year']     = get_post_meta($variation_id, '_subscription_length', true);
        }

        return $variations;

    }//end wcs_product_data_variations()


    /**
     * Handle Subscriptions Details Status Update
     */
    public function wcfm_modify_subscription_status()
    {
        global $WCFM, $WCFMu;

        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( __( 'Invalid nonce! Refresh your page and try again.', 'wc-frontend-manager-ultimate' ) );
			wp_die();
		}

        $subscription_id     = $_POST['subscription_id'];
        $subscription_status = $_POST['subscription_status'];

        $subscription = wcs_get_subscription($subscription_id);
        $subscription->update_status($subscription_status);

        // Status Update Notification
        $user_id   = apply_filters('wcfm_current_vendor_id', get_current_user_id());
        $shop_name = get_user_by('ID', $user_id)->display_name;
        if (wcfm_is_vendor()) {
            $shop_name = $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_by_vendor(absint($user_id));
        }

        // translators: 1) subscriptions manage url 2) subscription status 3) shop name
        $wcfm_messages = sprintf(__('<b>%1$s</b> subscription status updated to <b>%2$s</b> by <b>%3$s</b>', 'wc-frontend-manager-ultimate'), '#<a target="_blank" class="wcfm_dashboard_item_title" href="'.get_wcfm_subscriptions_manage_url($subscription_id).'">'.$subscription_id.'</a>', ucfirst($subscription_status), $shop_name);

        $raw_message = [
            'l10n'	=> [
                'text' 		=> '<b>%1$s</b> subscription status updated to <b>%2$s</b> by <b>%3$s</b>',
                'domain'    => 'wc-frontend-manager-ultimate',
                'wrapper'	=> [
                    'function' 	=> 'sprintf',
                    'args' 		=> [
                        '#<a target="_blank" class="wcfm_dashboard_item_title" href="'.get_wcfm_subscriptions_manage_url($subscription_id).'">'.$subscription_id.'</a>', 
                        ucfirst($subscription_status), 
                        $shop_name
                    ]
                ]
            ]
        ];

        $WCFM->wcfm_notification->wcfm_send_direct_message(-2, 0, 1, 0, $wcfm_messages, 'status-update', true, $raw_message);

        echo '{"status": true, "message": "'.__('Subscription status updated.', 'wc-frontend-manager-ultimate').'"}';

        die;

    } //end wcfm_modify_subscription_status()


    /* ── Pause custom status ─────────────────────────────────────── */

    public function register_pause_subscription_status() {
        register_post_status('wc-pause', [
            'label'                     => _x('Paused', 'subscription status', 'wc-frontend-manager-ultimate'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Paused <span class="count">(%s)</span>',
                'Paused <span class="count">(%s)</span>'
            ),
        ]);
    }

    public function add_pause_to_wcs_statuses( $statuses ) {
        $statuses[] = 'wc-pause';
        return $statuses;
    }

    public function add_pause_label( $statuses ) {
        $statuses['wc-pause'] = _x('Paused', 'subscription status', 'wc-frontend-manager-ultimate');
        return $statuses;
    }

    /* ── AJAX: Pause ─────────────────────────────────────────────── */

    public function wcfm_pause_subscription() {
        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        $sub_id = absint( $_POST['subscription_id'] ?? 0 );
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            wp_send_json_error( 'Invalid subscription.' );
        }

        $user_id = $subscription->get_user_id();
        if ( ! current_user_can('manage_woocommerce') && get_current_user_id() !== $user_id ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $raw_dates  = isset( $_POST['pause_dates'] ) ? (array) $_POST['pause_dates'] : [];
        $today      = current_time('Y-m-d');
        $valid      = [];
        foreach ( $raw_dates as $d ) {
            $d = sanitize_text_field( $d );
            if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d >= $today ) {
                $valid[] = $d;
            }
        }

        if ( empty($valid) ) {
            wp_send_json_error( 'No valid future dates provided.' );
        }

        sort($valid);
        $first_date  = reset($valid);
        $last_date   = end($valid);
        $resume_date = date('Y-m-d', strtotime($last_date . ' +1 day'));

        update_post_meta( $sub_id, '_wcfmu_pause_dates',  wp_json_encode($valid) );
        update_post_meta( $sub_id, '_wcfmu_pause_resume', $resume_date );

        // Date-scoped: pause only on each chosen date, deliver on the gaps.
        // Arm a sync event on every date boundary and apply today's state now.
        $this->schedule_pause_sync( $sub_id, $valid );
        $this->sync_pause_status( $sub_id );
        $status = str_replace('wc-', '', $subscription->get_status());

        // Log the trigger as a subscription note (per-block resume dates).
        $subscription->add_order_note(
            sprintf( 'Pause set from admin — paused only on: %s.', $this->describe_pause_blocks( $valid ) )
        );

        wp_send_json_success([
            'status'       => $status,
            'pause_dates'  => $valid,
            'pause_starts' => $first_date,
            'resume_date'  => $resume_date,
        ]);
    }

    /* ── AJAX: Resume (manual) ───────────────────────────────────── */

    public function wcfm_resume_subscription() {
        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        $sub_id = absint( $_POST['subscription_id'] ?? 0 );
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            wp_send_json_error( 'Invalid subscription.' );
        }

        $user_id = $subscription->get_user_id();
        if ( ! current_user_can('manage_woocommerce') && get_current_user_id() !== $user_id ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        wp_clear_scheduled_hook('wcfmu_auto_resume_subscription', [ $sub_id ]);
        $result = $this->auto_resume_subscription( $sub_id );
        wp_send_json_success( $result );
    }

    /* ── Shared: auto-resume (called by cron + manual AJAX) ─────── */

    /**
     * Flip the subscription to `pause` when its pause window actually begins.
     *
     * Scheduled at 00:01 UTC of the first pause date so the subscription stays
     * active until that day arrives (it is not paused the moment the request is
     * made). Guarded so a subscription that was cancelled/expired, resumed, or
     * had its pause cleared in the meantime is left untouched.
     *
     * @param int $sub_id Subscription ID.
     * @return void
     */
    /**
     * UTC timestamp for the start (00:00) of a calendar date in the site's
     * timezone — so pause/resume land on local day boundaries.
     *
     * @param string $date Y-m-d.
     * @return int
     */
    private function local_day_start_ts( $date ) {
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone( 'UTC' );
            $dt = new DateTime( $date . ' 00:00:00', $tz );
            return $dt->getTimestamp();
        } catch ( Exception $e ) {
            return strtotime( $date . ' 00:00:00 UTC' );
        }
    }

    /**
     * Reconcile every subscription that has a pending/active pause window with
     * what its status should be today. Runs on `init`, throttled to once every
     * few minutes so it is cheap, and is independent of WP-Cron: it flips a
     * subscription to `pause` once its window starts and resumes it once the
     * window ends, even if the scheduled cron events never fired.
     *
     * The candidate set is small — only subscriptions with `_wcfmu_pause_resume`
     * meta, which is deleted the moment a subscription is resumed.
     *
     * @return void
     */
    public function reconcile_pause_states() {
        if ( ! function_exists( 'wcs_get_subscription' ) ) {
            return;
        }
        // Throttle: run at most once every 5 minutes.
        if ( get_transient( 'aaraa_pause_reconcile_lock' ) ) {
            return;
        }
        set_transient( 'aaraa_pause_reconcile_lock', 1, 5 * MINUTE_IN_SECONDS );

        global $wpdb;
        $ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wcfmu_pause_resume' LIMIT 500"
        );
        if ( empty( $ids ) ) {
            return;
        }

        $today = current_time( 'Y-m-d' );
        foreach ( $ids as $sub_id ) {
            $this->reconcile_one_pause( (int) $sub_id, $today );
        }
    }

    /**
     * Reconcile a single subscription's status against its saved pause dates.
     *
     * @param int    $sub_id Subscription ID.
     * @param string $today  Site-local date (Y-m-d) — unused; sync recomputes it.
     * @return void
     */
    private function reconcile_one_pause( $sub_id, $today ) {
        $this->sync_pause_status( $sub_id );
    }

    /**
     * Set the subscription's status to match TODAY's pause membership.
     *
     * Date-scoped pause: the subscription is paused only on the exact chosen
     * dates and active on every other day, so non-consecutive selections
     * (e.g. the 5th and the 11th) deliver normally on the days in between.
     *
     *   - today is one of the chosen dates  → status `pause`
     *   - today is a gap day (still within the window, not chosen) → active
     *   - today is past the last date        → resume + clear meta (final)
     *
     * Idempotent and safe to call repeatedly (from the scheduled per-date
     * events, the init reconcile safety net, or immediately on pause).
     *
     * @param int $sub_id Subscription ID.
     * @return void
     */
    public function sync_pause_status( $sub_id ) {
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            return;
        }

        $dates = $this->read_pause_dates( $sub_id );
        if ( empty( $dates ) ) {
            return;
        }

        sort( $dates );
        $last   = end( $dates );
        $today  = current_time( 'Y-m-d' );
        $status = str_replace( 'wc-', '', $subscription->get_status() );

        if ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) {
            return;
        }

        if ( $today > $last ) {
            // The whole window has passed — resume for good. Keep any still-upcoming
            // pause dates (retain helper drops only today+past), and drop the resume
            // marker so the reconciler stops scanning this subscription.
            if ( 'pause' === $status ) {
                $this->auto_resume_subscription( $sub_id );
            } else {
                if ( function_exists( '\\Aaraa\\Admin\\aaraa_retain_future_pause_dates' ) ) {
                    \Aaraa\Admin\aaraa_retain_future_pause_dates( $sub_id );
                } else {
                    delete_post_meta( $sub_id, '_wcfmu_pause_dates' );
                }
                delete_post_meta( $sub_id, '_wcfmu_pause_resume' );
            }
            return;
        }

        if ( in_array( $today, $dates, true ) ) {
            // A chosen date — must be paused.
            if ( 'pause' !== $status ) {
                $subscription->add_order_note( sprintf( 'Subscription paused for %s (date-scoped).', $today ) );
                $subscription->update_status( 'pause' );
            }
        } else {
            // A gap day (or a day before the window starts) — must be active so
            // that day's delivery goes ahead. Keep the pause meta so the next
            // chosen date re-pauses it.
            if ( 'pause' === $status ) {
                $this->gap_resume_subscription( $sub_id );
            }
        }
    }

    /**
     * Reactivate a subscription for a gap day WITHOUT clearing the pause meta,
     * so the next chosen pause date still takes effect. Wallet-aware, mirroring
     * auto_resume_subscription()'s active/on-hold decision.
     *
     * @param int $sub_id Subscription ID.
     * @return void
     */
    private function gap_resume_subscription( $sub_id ) {
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            return;
        }

        $user_id     = $subscription->get_user_id();
        $wallet_bal  = (float) get_user_meta( $user_id, 'wps_wallet', true );
        $renewal_amt = (float) $subscription->get_total();
        $new_status  = ( $renewal_amt > 0 && $wallet_bal >= $renewal_amt ) ? 'active' : 'on-hold';

        $subscription->add_order_note(
            'active' === $new_status
                ? 'Deliveries resumed for a non-paused day — status set to active.'
                : sprintf( 'Deliveries resumed for a non-paused day — placed on hold (wallet ₹%s does not cover renewal ₹%s).', number_format_i18n( $wallet_bal, 2 ), number_format_i18n( $renewal_amt, 2 ) )
        );

        $subscription->update_status( $new_status );
    }

    /**
     * (Re)schedule the per-date sync events for a pause selection.
     *
     * Clears any previous pause events, then arms a `wcfmu_sync_pause_subscription`
     * event at the local-midnight start of every chosen date and every
     * day-after, so the status flips exactly on those boundaries even without a
     * page load. The init reconcile is the safety net if a tick is missed.
     *
     * @param int      $sub_id Subscription ID.
     * @param string[] $dates  Chosen Y-m-d dates.
     * @return void
     */
    public function schedule_pause_sync( $sub_id, array $dates ) {
        wp_clear_scheduled_hook( 'wcfmu_begin_pause_subscription', [ $sub_id ] );
        wp_clear_scheduled_hook( 'wcfmu_auto_resume_subscription', [ $sub_id ] );
        wp_clear_scheduled_hook( 'wcfmu_sync_pause_subscription',  [ $sub_id ] );

        $today     = current_time( 'Y-m-d' );
        $boundaries = [];
        foreach ( $dates as $d ) {
            $boundaries[ $d ]                                              = true; // pause on this day.
            $boundaries[ date( 'Y-m-d', strtotime( $d . ' +1 day' ) ) ] = true; // re-evaluate the next day.
        }

        foreach ( array_keys( $boundaries ) as $d ) {
            if ( $d < $today ) {
                continue;
            }
            $ts = $this->local_day_start_ts( $d );
            if ( $ts > time() ) {
                wp_schedule_single_event( $ts, 'wcfmu_sync_pause_subscription', [ $sub_id ] );
            }
        }
    }

    public function begin_pause_subscription( $sub_id ) {
        // Legacy cron entry point — now delegates to the date-scoped sync.
        $this->sync_pause_status( $sub_id );
    }

    /**
     * Read the stored pause dates as a clean Y-m-d list, whatever shape they
     * were saved in. get_post_meta() auto-unserializes, so a value stored as a
     * PHP array returns an array (json_decode on which yields null); handle
     * arrays, JSON strings and comma strings alike.
     *
     * @param int $sub_id Subscription ID.
     * @return string[]
     */
    private function read_pause_dates( $sub_id ) {
        $raw = get_post_meta( $sub_id, '_wcfmu_pause_dates', true );
        if ( empty( $raw ) ) {
            return array();
        }
        if ( is_array( $raw ) ) {
            $list = $raw;
        } else {
            $decoded = json_decode( (string) $raw, true );
            $list    = is_array( $decoded ) ? $decoded : explode( ',', (string) $raw );
        }
        $out = array();
        foreach ( $list as $d ) {
            $d = trim( (string) $d );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
                $out[] = $d;
            }
        }
        return array_values( array_unique( $out ) );
    }

    /**
     * Describe pause dates as contiguous blocks each with its own resume date,
     * e.g. "2026-08-05 (resumes 2026-08-06); 2026-08-11 (resumes 2026-08-12)".
     *
     * @param string[] $dates Y-m-d dates.
     * @return string
     */
    private function describe_pause_blocks( $dates ) {
        $dates = array_values( array_unique( array_filter( (array) $dates ) ) );
        sort( $dates );
        if ( empty( $dates ) ) {
            return '';
        }

        $blocks = array();
        $start  = $dates[0];
        $prev   = $dates[0];
        for ( $i = 1, $n = count( $dates ); $i < $n; $i++ ) {
            $expected = date( 'Y-m-d', strtotime( $prev . ' +1 day' ) );
            if ( $dates[ $i ] !== $expected ) {
                $blocks[] = array( $start, $prev );
                $start    = $dates[ $i ];
            }
            $prev = $dates[ $i ];
        }
        $blocks[] = array( $start, $prev );

        $parts = array();
        foreach ( $blocks as $block ) {
            $label   = ( $block[0] === $block[1] ) ? $block[0] : ( $block[0] . ' to ' . $block[1] );
            $resume  = date( 'Y-m-d', strtotime( $block[1] . ' +1 day' ) );
            $parts[] = sprintf( '%s (resumes %s)', $label, $resume );
        }

        return implode( '; ', $parts );
    }

    public function auto_resume_subscription( $sub_id ) {
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            return [ 'status' => 'error', 'message' => 'Subscription not found.' ];
        }

        $user_id     = $subscription->get_user_id();
        $wallet_bal  = (float) get_user_meta( $user_id, 'wps_wallet', true );
        $renewal_amt = (float) $subscription->get_total();
        $new_status  = ( $renewal_amt > 0 && $wallet_bal >= $renewal_amt ) ? 'active' : 'on-hold';

        $subscription->add_order_note(
            'active' === $new_status
                ? 'Subscription resumed — status set to active.'
                : sprintf( 'Subscription resumed — placed on hold (wallet ₹%s does not cover renewal ₹%s).', number_format_i18n( $wallet_bal, 2 ), number_format_i18n( $renewal_amt, 2 ) )
        );

        $subscription->update_status( $new_status );

        // End the current pause but KEEP any upcoming pause dates (helper drops
        // only today+past). Drop the resume marker. Falls back to a full delete
        // if the helper is unavailable.
        if ( function_exists( '\\Aaraa\\Admin\\aaraa_retain_future_pause_dates' ) ) {
            \Aaraa\Admin\aaraa_retain_future_pause_dates( $sub_id );
        } else {
            delete_post_meta( $sub_id, '_wcfmu_pause_dates' );
        }
        delete_post_meta( $sub_id, '_wcfmu_pause_resume' );

        return [
            'status'  => $new_status,
            'message' => $new_status === 'active'
                ? __('Subscription resumed successfully.', 'wc-frontend-manager-ultimate')
                : __('Subscription placed on hold — wallet balance insufficient.', 'wc-frontend-manager-ultimate'),
        ];
    }

    /* ── AJAX: Save delivery dates ───────────────────────────────── */

    public function wcfm_save_sub_delivery_dates() {
        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        $sub_id = absint( $_POST['subscription_id'] ?? 0 );
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            wp_send_json_error( 'Invalid subscription.' );
        }

        $user_id = $subscription->get_user_id();
        if ( ! current_user_can('manage_woocommerce') && get_current_user_id() !== $user_id ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $raw_dates = isset( $_POST['delivery_dates'] ) ? (array) $_POST['delivery_dates'] : [];
        $today     = date('Y-m-d');
        $valid     = [];
        foreach ( $raw_dates as $d ) {
            $d = sanitize_text_field($d);
            if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d >= $today ) {
                $valid[] = $d;
            }
        }
        $valid = array_unique($valid);
        sort($valid);

        update_post_meta( $sub_id, '_wcfm_sub_delivery_dates', wp_json_encode($valid) );

        if ( ! empty($valid) && $subscription->can_date_be_updated('next_payment') ) {
            $subscription->update_dates([ 'next_payment' => $valid[0] . ' 00:00:00' ]);
        }

        wp_send_json_success([ 'delivery_dates' => $valid ]);
    }


    public function wcfm_update_subscription_schedule() {
        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }

        $sub_id   = absint( $_POST['subscription_id'] ?? 0 );
        $schedule = sanitize_text_field( $_POST['schedule_type'] ?? '' );
        $valid_schedules = [ 'daily', 'alternate', 'weekend', 'custom' ];

        if ( ! $sub_id || ! in_array( $schedule, $valid_schedules, true ) ) {
            wp_send_json_error( 'Invalid parameters.' );
        }

        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            wp_send_json_error( 'Subscription not found.' );
        }

        if ( ! current_user_can('manage_woocommerce') && (int) $subscription->get_user_id() !== get_current_user_id() ) {
            wp_send_json_error( 'Permission denied.' );
        }

        if ( $schedule === 'weekend' ) {
            $custom_days = [ 0, 6 ];
        } elseif ( $schedule === 'custom' ) {
            $custom_days = array_values( array_unique(
                array_filter( array_map( 'absint', (array) ( $_POST['custom_days'] ?? [] ) ), function($d) {
                    return $d >= 0 && $d <= 6;
                })
            ) );
            if ( empty( $custom_days ) ) {
                wp_send_json_error( 'Please select at least one day for custom schedule.' );
            }
        } else {
            $custom_days = [];
        }

        update_post_meta( $sub_id, '_wcfm_delivery_schedule', $schedule );

        if ( ! empty( $custom_days ) ) {
            update_post_meta( $sub_id, '_wcfm_delivery_days', $custom_days );
        } else {
            delete_post_meta( $sub_id, '_wcfm_delivery_days' );
        }

        wp_send_json_success( [ 'message' => __( 'Delivery schedule updated.', 'wc-frontend-manager-ultimate' ) ] );
    }


    public function wcfm_sub_save_item() {
        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }
        $sub_id  = absint( $_POST['subscription_id'] ?? 0 );
        $item_id = absint( $_POST['item_id'] ?? 0 );
        $qty     = max( 1, absint( $_POST['qty'] ?? 1 ) );
        $total   = wc_format_decimal( sanitize_text_field( $_POST['total'] ?? '0' ) );

        if ( ! $sub_id || ! $item_id ) {
            wp_send_json_error( 'Invalid parameters.' );
        }
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            wp_send_json_error( 'Subscription not found.' );
        }
        if ( ! current_user_can('manage_woocommerce') && ! wcfm_is_vendor() ) {
            wp_send_json_error( 'Permission denied.' );
            return;
        }
        $item = new WC_Order_Item_Product( $item_id );
        if ( (int) $item->get_order_id() !== $sub_id ) {
            wp_send_json_error( 'Item not found or does not belong to this subscription.' );
        }
        $item->set_quantity( $qty );
        $item->set_total( $total );
        $item->set_subtotal( $total );
        $item->save();
        $subscription->calculate_totals();
        wp_send_json_success( [
            'message'   => __( 'Item updated.', 'wc-frontend-manager-ultimate' ),
            'new_qty'   => $qty,
            'new_total' => wc_price( $total, [ 'currency' => $subscription->get_currency() ] ),
        ] );
    }


    public function wcfm_sub_remove_item() {
        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }
        $sub_id  = absint( $_POST['subscription_id'] ?? 0 );
        $item_id = absint( $_POST['item_id'] ?? 0 );

        if ( ! $sub_id || ! $item_id ) {
            wp_send_json_error( 'Invalid parameters.' );
        }
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            wp_send_json_error( 'Subscription not found.' );
        }
        if ( ! current_user_can('manage_woocommerce') && ! wcfm_is_vendor() ) {
            wp_send_json_error( 'Permission denied.' );
            return;
        }
        $item = WC_Order_Factory::get_order_item( $item_id );
        if ( ! $item || (int) $item->get_order_id() !== $sub_id ) {
            wp_send_json_error( 'Item not found or does not belong to this subscription.' );
        }
        wc_delete_order_item( $item_id );
        $subscription->calculate_totals();
        wp_send_json_success( [ 'message' => __( 'Item removed.', 'wc-frontend-manager-ultimate' ) ] );
    }


    public function wcfm_sub_add_item() {
        if ( ! check_ajax_referer( 'wcfm_ajax_nonce', 'wcfm_ajax_nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }
        $sub_id     = absint( $_POST['subscription_id'] ?? 0 );
        $product_id = absint( $_POST['product_id'] ?? 0 );
        $qty        = max( 1, absint( $_POST['qty'] ?? 1 ) );
        $price      = sanitize_text_field( $_POST['price'] ?? '' );

        if ( ! $sub_id || ! $product_id ) {
            wp_send_json_error( 'Invalid parameters.' );
        }
        $subscription = wcs_get_subscription( $sub_id );
        if ( ! $subscription ) {
            wp_send_json_error( 'Subscription not found.' );
        }
        if ( ! current_user_can('manage_woocommerce') && ! wcfm_is_vendor() ) {
            wp_send_json_error( 'Permission denied.' );
            return;
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found.' );
        }
        if ( $price !== '' && is_numeric( $price ) ) {
            $line_total = wc_format_decimal( (float) $price * $qty );
        } else {
            $line_total = wc_format_decimal( (float) $product->get_price() * $qty );
        }
        $item = new WC_Order_Item_Product();
        $item->set_props( [
            'product'  => $product,
            'quantity' => $qty,
            'subtotal' => $line_total,
            'total'    => $line_total,
        ] );
        $item->set_order_id( $sub_id );
        $item->save();
        $subscription->calculate_totals();
        wp_send_json_success( [
            'message'      => __( 'Item added.', 'wc-frontend-manager-ultimate' ),
            'item_id'      => $item->get_id(),
            'product_name' => $product->get_name(),
            'qty'          => $qty,
            'total'        => wc_price( $line_total, [ 'currency' => $subscription->get_currency() ] ),
        ] );
    }


    public static function init_address_fields($order = false, $context = 'edit') {
        /**
		 * Provides an opportunity to modify the list of order billing fields displayed on the admin.
		 *
		 * @since 6.7.5
		 * 
		 * @param array Billing fields.
		 * @param WC_Order|false $order Order object.
		 * @param string $context Context of fields (view or edit).
		 */
        self::$billing_fields = apply_filters(
            'woocommerce_admin_billing_fields',
            [
                'first_name' => [
                    'label' => __('First Name', 'woocommerce'),
                    'show'  => false,
                ],
                'last_name'  => [
                    'label' => __('Last Name', 'woocommerce'),
                    'show'  => false,
                ],
                'company'    => [
                    'label' => __('Company', 'woocommerce'),
                    'show'  => false,
                ],
                'address_1'  => [
                    'label' => __('Address 1', 'woocommerce'),
                    'show'  => false,
                ],
                'address_2'  => [
                    'label' => __('Address 2', 'woocommerce'),
                    'show'  => false,
                ],
                'city'       => [
                    'label' => __('City', 'woocommerce'),
                    'show'  => false,
                ],
                'postcode'   => [
                    'label' => __('Postcode', 'woocommerce'),
                    'show'  => false,
                ],
                'country'    => [
                    'label'   => __('Country', 'woocommerce'),
                    'show'    => false,
                    'class'   => 'js_field-country select short',
                    'type'    => 'select',
                    'options' => (['' => __('Select a country&hellip;', 'woocommerce')] + WC()->countries->get_allowed_countries()),
                ],
                'state'      => [
                    'label' => __('State/County', 'woocommerce'),
                    'class' => 'js_field-state select short',
                    'show'  => false,
                ],
                'email'      => [
                    'label' => __('Email', 'woocommerce'),
                ],
                'phone'      => [
                    'label' => __('Phone', 'woocommerce'),
                ],
            ],
            $order,
			$context
        );

        /**
		 * Provides an opportunity to modify the list of order shipping fields displayed on the admin.
		 *
		 * @since 6.7.5
		 *
		 * @param array Shipping fields.
		 * @param WC_Order|false $order Order object.
		 * @param string $context Context of fields (view or edit).
		 */
        self::$shipping_fields = apply_filters(
            'woocommerce_admin_shipping_fields',
            [
                'first_name' => [
                    'label' => __('First Name', 'woocommerce'),
                    'show'  => false,
                ],
                'last_name'  => [
                    'label' => __('Last Name', 'woocommerce'),
                    'show'  => false,
                ],
                'company'    => [
                    'label' => __('Company', 'woocommerce'),
                    'show'  => false,
                ],
                'address_1'  => [
                    'label' => __('Address 1', 'woocommerce'),
                    'show'  => false,
                ],
                'address_2'  => [
                    'label' => __('Address 2', 'woocommerce'),
                    'show'  => false,
                ],
                'city'       => [
                    'label' => __('City', 'woocommerce'),
                    'show'  => false,
                ],
                'postcode'   => [
                    'label' => __('Postcode', 'woocommerce'),
                    'show'  => false,
                ],
                'country'    => [
                    'label'   => __('Country', 'woocommerce'),
                    'show'    => false,
                    'type'    => 'select',
                    'class'   => 'js_field-country select short',
                    'options' => (['' => __('Select a country&hellip;', 'woocommerce')] + WC()->countries->get_shipping_countries()),
                ],
                'state'      => [
                    'label' => __('State/County', 'woocommerce'),
                    'class' => 'js_field-state select short',
                    'show'  => false,
                ],
            ],
            $order,
			$context
        );
    }//end init_address_fields()


}//end class
