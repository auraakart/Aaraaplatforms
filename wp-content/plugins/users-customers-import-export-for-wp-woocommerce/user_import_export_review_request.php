<?php

/**
 * Review request
 *  
 *
 * @package  User_import_export_Review_Request
 */
if (!defined('ABSPATH')) {
    exit;
}

class User_import_export_Review_Request
{
    /**
     * config options 
     */
    private $new_review_banner_title = "";
    private $plugin_title                 =   "Import Export WordPress Users and WooCommerce Customers";
    private $review_url                   =   '';
    private $plugin_prefix                =   "wt_u_iew_basic"; /* must be unique name */
    private $activation_hook              =   "wt_u_iew_basic_activate"; /* hook for activation, to store activated date */
    private $deactivation_hook            =   "wt_u_iew_basic_deactivate"; /* hook for deactivation, to delete activated date */
    private $days_to_show_banner          =   7; /* when did the banner to show */
    private $remind_days                  =   5; /* remind interval in days */
    private $webtoffee_logo_url           =   WT_U_IEW_PLUGIN_URL . 'assets/images/webtoffee-logo_small.png';
    private $review_request_bg            =   WT_U_IEW_PLUGIN_URL . 'assets/images/wbtf_review_banner_bg.png';




    private $start_date                   =   0; /* banner to show count start date. plugin installed date, remind me later added date */
    private $current_banner_state         =   2; /* 1: active, 2: waiting to show(first after installation), 3: closed by user/not interested to review, 4: user done the review, 5:remind me later */
    private $banner_state_option_name     =   ''; /* WP option name to save banner state */
    private $start_date_option_name       =   ''; /* WP option name to save start date */
    private $banner_css_class             =   ''; /* CSS class name for Banner HTML element. */
    private $banner_message               =   ''; /* Banner message. */
    private $new_review_banner_message    =   ''; /* New banner message. */
    private $later_btn_text               =   ''; /* Remind me later button text */
    private $later_btn_new_text           =   ''; /* New remind me later button text */
    private $already_did_btn_new_text     =   ''; /* New never review button text */
    private $never_btn_text               =   ''; /* Never review button text. */
    private $review_btn_new_text          =   ''; /* New review now button text */
    private $review_btn_text              =   ''; /* Review now button text. */
    private $ajax_action_name             =   ''; /* Name of ajax action to save banner state. */
    private $current_post_type            =   ''; /* Current post type being processed */
    private $dismissal_count_option       =   ''; /* Option name for dismissal count */
    private $last_dismissal_option        =   ''; /* Option name for last dismissal date */
    private $jobs_since_dismissal_option  =   ''; /* Option name for jobs since last dismissal */
    private $allowed_action_type_arr      = array(
        'later', /* remind me later */
        'never', /* never */
        'review', /* review now */
        'closed', /* not interested */
    );
    private $plugins_array = array( );

    /** Avoid registering review-banner hooks twice if current_screen runs more than once. */
    private $review_banner_hooks_registered = false;

    public function __construct()
    {
        global $wt_iew_review_banner_shown;
        
        //Set config vars
        $this->set_vars();
        $this->maybe_backfill_review_request_closed_at();

        add_action('wp_ajax_' . $this->ajax_action_name, array($this, 'process_user_action'));

        add_action($this->activation_hook, array($this, 'on_activate'));
        add_action($this->deactivation_hook, array($this, 'on_deactivate'));
        add_action('admin_notices', array($this, 'show_banner_cta'));

        /*
         * get_current_screen() is not loaded until wp-admin/includes/screen.php (after plugins load).
         * Register review hooks on current_screen so the screen id and helper exist.
         */
        add_action('current_screen', array($this, 'register_review_banner_hooks_for_screen'), 10, 1);

        // Register WooCommerce Pages Banner
        add_action('admin_notices', array($this, 'show_wc_pages_banner'));
        add_action('wp_ajax_wt_iew_dismiss_wc_pages_banner', array($this, 'dismiss_wc_pages_banner_ajax'));
    }

    /**
     * Runs once the admin screen is set; wires common vs type-specific review banners.
     *
     * @since 2.7.3
     *
     * @param WP_Screen|null $screen Current admin screen.
     */
    public function register_review_banner_hooks_for_screen( $screen )
    {
        if ($this->review_banner_hooks_registered) {
            return;
        }

        global $wt_iew_review_banner_shown;

        $page_kind = $this->check_current_page($screen);
        if ( 'common_allowed_page' === $page_kind ) {
            /*
             * Global banner: DB-driven winner post type. Type-specific eligibility runs in show_banner_in_type_specific_pages().
             */
            if ( $this->check_condition() ) {

                $post_type = $this->current_post_type;

                foreach ($this->plugins_array as $plugin) {
                    if (in_array($post_type, $plugin['post_types'], true)) {
                        $this->review_url = $plugin['url'];
                        break;
                    }
                }

                $wt_iew_review_banner_shown = true;

                $this->configure_review_banner_copy();

                add_action('admin_notices', array($this, 'show_banner'));
                add_action('admin_print_footer_scripts', array($this, 'add_banner_scripts'));
                $this->review_banner_hooks_registered = true;
            }
        } elseif ( 'type_specific_allowed_page' === $page_kind ) {
            if ( isset( $wt_iew_review_banner_shown ) && true === $wt_iew_review_banner_shown ) {
                return;
            }
            /*
             * Contextual banners: eligibility per screen-derived item_type in show_banner_in_type_specific_pages().
             */
            add_action('admin_notices', array($this, 'show_banner_in_type_specific_pages'), 5);
            add_action('admin_print_footer_scripts', array($this, 'add_banner_scripts'));
            $this->review_banner_hooks_registered = true;
        }
    }

    /**
     * item_type (history / banner family) → screen ids where the contextual review banner may run.
     *
     * WooCommerce → Reports (`woocommerce_page_wc-reports`) is listed for both order-family and user-family
     * types; {@see self::get_item_type_from_screen()} picks `order` vs `user` from the active Reports tab.
     *
     * @since 2.7.3
     *
     * @return array<string, string[]>
     */
    private function get_type_specific_pages_map()
    {
        $order_family = array(
            'edit-shop_order',
            'shop_order',
            'woocommerce_page_wc-orders',
            'edit-shop_coupon',
            'edit-shop_subscription',
            'shop_subscription',
            'woocommerce_page_wc-reports',
        );
        $product_pages = array('edit-product', 'product');
        $user_screens   = array('users', 'woocommerce_page_wc-reports');

        return array(
            'order'              => $order_family,
            'coupon'             => $order_family,
            'subscription'       => $order_family,
            'product'            => $product_pages,
            'product_review'     => $product_pages,
            'product_categories' => $product_pages,
            'product_tags'       => $product_pages,
            'user'               => $user_screens,
        );
    }

    /**
     * Active tab on WooCommerce → Reports (used to map one screen id to order vs. user review context).
     *
     * @since 2.7.3
     *
     * @return string Sanitized tab slug (e.g. orders, customers).
     */
    private function get_wc_reports_tab_slug()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI context.
        return isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'orders';
    }

    /**
     * Classify the admin screen for review-banner routing (common vs type-specific).
     *
     * @since 2.7.3
     *
     * @param WP_Screen|null $screen Current admin screen (from current_screen hook).
     * @return string|null 'common_allowed_page'|'type_specific_allowed_page'|null
     */
    private function check_current_page( $screen )
    {
        if (!$screen || !isset($screen->id)) {
            return null;
        }

        $plugin_pages = array('toplevel_page_wt_import_export_for_woo_basic_export', 
            'webtoffee-import-export-basic_page_wt_import_export_for_woo_basic_import', 
            'webtoffee-import-export-basic_page_wt_iew_scheduled_job',
            'webtoffee-import-export-basic_page_wt_import_export_for_woo_basic', 
            'toplevel_page_wt_import_export_for_woo_export', 
            'webtoffee-import-export-pro_page_wt_import_export_for_woo_import', 
            'webtoffee-import-export-pro_page_wt_import_export_for_woo_history', 
            'webtoffee-import-export-pro_page_wt_import_export_for_woo_history_log', 
            'webtoffee-import-export-pro_page_wt_import_export_for_woo_cron', 
            'webtoffee-import-export-pro_page_wt_import_export_for_woo'
        );

        // Common pages for all types (WooCommerce → Reports is type_specific only: tab maps order vs user).
        $allowed_pages = array_merge($plugin_pages, array('dashboard', 'plugins', 'woocommerce_page_wc-admin'));

        if (in_array($screen->id, $allowed_pages, true)) {
            return 'common_allowed_page';
        } 

        $type_specific_pages = $this->get_type_specific_pages_map();

        $item_type = $this->get_item_type_from_screen($screen);
        if ($item_type && isset($type_specific_pages[ $item_type ]) && in_array($screen->id, $type_specific_pages[ $item_type ], true)) {
            return 'type_specific_allowed_page';
        }

        return null;
    }

    /**
     *	Set config vars
     */
    public function set_vars()
    {
        $this->ajax_action_name             =   $this->plugin_prefix . '_process_user_review_action';
        $this->banner_state_option_name     =   $this->plugin_prefix . "_review_request";
        $this->start_date_option_name       =   $this->plugin_prefix . "_start_date";
        $this->banner_css_class             =   $this->plugin_prefix . "_review_request";

        $this->start_date                   =   absint(get_option($this->start_date_option_name));
        $banner_state                       =   absint(get_option($this->banner_state_option_name));
        $this->current_banner_state         =   ($banner_state == 0 ? $this->current_banner_state : $banner_state);

        $this->dismissal_count_option       =   'wt_iew_basic_dismiss_count';
        $this->last_dismissal_option        =   'wt_iew_basic_last_dismiss_date';
        $this->jobs_since_dismissal_option  =   'wt_iew_basic_jobs_since_dismiss';

        $this->plugins_array                = array(
            'order' => array(
                'base_name' => 'order-import-export-for-woocommerce/order-import-export-for-woocommerce.php',
                'prefix' => 'wt_o_iew_basic',
                'post_types' => array('order', 'coupon', 'subscription'),
                'url' => 'https://wordpress.org/support/plugin/order-import-export-for-woocommerce/reviews/#new-post'
            ),
            'products' => array(
                'base_name' => 'product-import-export-for-woo/product-import-export-for-woo.php',
                'prefix' => 'wt_p_iew_basic',
                'post_types' => array('product', 'product_review', 'product_categories', 'product_tags'),
                'url' => 'https://wordpress.org/support/plugin/product-import-export-for-woo/reviews/#new-post'
            ),
            'users' => array(
                'base_name' => 'users-customers-import-export-for-wp-woocommerce/users-customers-import-export-for-wp-woocommerce.php',
                'prefix' => 'wt_u_iew_basic',
                'post_types' => array('user'),
                'url' => 'https://wordpress.org/support/plugin/users-customers-import-export-for-wp-woocommerce/reviews/#new-post'
            ),
        );
    }

    /**
     * Which Import/Export plugin the current job context maps to (array key from plugins_array).
     *
     * @since 2.7.3
     *
     * @return string 'order'|'products'|'users'
     */
    private function get_review_banner_plugin_key()
    {
        foreach ($this->plugins_array as $key => $plugin) {
            if (in_array($this->current_post_type, $plugin['post_types'], true)) {
                return $key;
            }
        }
        return 'users';
    }

    /**
     * CSS modifier slug for banner theming.
     *
     * @since 2.7.3
     *
     * @return string 'order'|'product'|'user'
     */
    private function get_review_banner_theme_slug()
    {
        $key = $this->get_review_banner_plugin_key();
        if ('products' === $key) {
            return 'product';
        }
        if ('users' === $key) {
            return 'user';
        }
        return 'order';
    }

    /**
     * Right-rail illustration per basic plugin (SVG intrinsic size from asset).
     *
     * @since 2.7.3
     *
     * @return array{url:string,width?:int,height?:int}
     */
    private function get_review_banner_illustration_assets()
    {
        $key = $this->get_review_banner_plugin_key();
        $base = WT_U_IEW_PLUGIN_URL . 'assets/images/';
        $map  = array(
            'order'    => array(
                'url'    => $base . 'wt_order_review_banner_logo.svg',
                'width'  => 158,
                'height' => 155,
            ),
            'products' => array(
                'url'    => $base . 'wt_product_review_banner_logo.svg',
                'width'  => 158,
                'height' => 161,
            ),
            'users'    => array(
                'url'    => $base . 'wt_user_review_banner_logo.svg',
                'width'  => 164,
                'height' => 162,
            ),
        );
        if (isset($map[ $key ])) {
            return $map[ $key ];
        }
        return array( 'url' => $this->review_request_bg );
    }

    /**
     * Titles, body copy, and primary CTA label per plugin.
     *
     * @since 2.7.3
     */
    private function configure_review_banner_copy()
    {
        $key = $this->get_review_banner_plugin_key();

        $this->later_btn_new_text       = __('Nope, maybe later', 'users-customers-import-export-for-wp-woocommerce');
        $this->already_did_btn_new_text = __('I already did', 'users-customers-import-export-for-wp-woocommerce');
        $this->later_btn_text           = __('Remind me later', 'users-customers-import-export-for-wp-woocommerce');
        $this->never_btn_text           = __('Not interested', 'users-customers-import-export-for-wp-woocommerce');
        $this->review_btn_text          = __('Review now', 'users-customers-import-export-for-wp-woocommerce');

        $this->banner_message = sprintf(
            /* translators: 1: bold open, 2: bold close */
            __('Hey, we at %1$sWebToffee%2$s would like to thank you for using our plugin. We would really appreciate if you could take a moment to drop a quick review that will inspire us to keep going.', 'users-customers-import-export-for-wp-woocommerce'),
            '<b>',
            '</b>'
        );

        $para2 = '<p class="wbtf-review-banner__para wbtf-review-banner__para--cta">'
            . '<span class="wbtf-review-banner__cta-regular">' . esc_html__(
                'If you found the plugin helpful, please leave us a quick ',
                'users-customers-import-export-for-wp-woocommerce'
            ) . '</span>'
            . '<span class="wbtf-review-banner__cta-strong">' . esc_html__(
                '5-star review',
                'users-customers-import-export-for-wp-woocommerce'
            ) . '</span>'
            . '<span class="wbtf-review-banner__cta-regular">' . esc_html__(
                '. It would mean the world to us.',
                'users-customers-import-export-for-wp-woocommerce'
            ) . '</span>'
            . '</p>';

        switch ($key) {
            case 'products':
                $this->new_review_banner_title = __('Updating products easily with WebToffee Import Export?', 'users-customers-import-export-for-wp-woocommerce');
                $this->new_review_banner_message = '<p class="wbtf-review-banner__para">' . esc_html__('Hi there,', 'users-customers-import-export-for-wp-woocommerce') . '<br />' . esc_html__(
                    'we are thrilled to see you making great use of our WooCommerce product import export plugin. It\'s our mission to make data management as efficient as possible for you.',
                    'users-customers-import-export-for-wp-woocommerce'
                ) . '</p>' . $para2;
                $this->review_btn_new_text = __('Review Product Import Export', 'users-customers-import-export-for-wp-woocommerce');
                break;

            case 'users':
                $this->new_review_banner_title = __('Handling user data smoothly with WebToffee Import Export?', 'users-customers-import-export-for-wp-woocommerce');
                $this->new_review_banner_message = '<p class="wbtf-review-banner__para">' . esc_html__('Hi there,', 'users-customers-import-export-for-wp-woocommerce') . '<br />' . esc_html__(
                    'we are thrilled to see you making great use of our WooCommerce user import export plugin. It\'s our mission to make data management as efficient as possible for you.',
                    'users-customers-import-export-for-wp-woocommerce'
                ) . '</p>' . $para2;
                $this->review_btn_new_text = __('Review User Import Export', 'users-customers-import-export-for-wp-woocommerce');
                break;

            case 'order':
            default:
                $this->new_review_banner_title = __('Managing orders smoothly with WebToffee Import Export?', 'users-customers-import-export-for-wp-woocommerce');
                $this->new_review_banner_message = '<p class="wbtf-review-banner__para">' . esc_html__('Hi there,', 'users-customers-import-export-for-wp-woocommerce') . '<br />' . esc_html__(
                    'we are thrilled to see you making great use of our WooCommerce order import export plugin. It\'s our mission to make data management as efficient as possible for you.',
                    'users-customers-import-export-for-wp-woocommerce'
                ) . '</p>' . $para2;
                $this->review_btn_new_text = __('Review Order Import Export', 'users-customers-import-export-for-wp-woocommerce');
                break;
        }
    }

    /**
     *	Actions on plugin activation
     *	Saves activation date
     */
    public function on_activate()
    {
        $this->reset_start_date();
    }

    /**
     *	Actions on plugin deactivation
     *	Removes activation date
     */
    public function on_deactivate()
    {
        delete_option($this->start_date_option_name);
    }

    /**
     *	Reset the start date. 
     */
    private function reset_start_date()
    {
        update_option($this->start_date_option_name, time());
    }

    /**
     * On admin-ajax.php, current_post_type is not set from the screen that rendered the banner.
     * Read a whitelisted item_type from POST (set from the banner data attribute) so the correct option prefix updates.
     */
    private function wt_iew_apply_review_item_type_from_request()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- POST only on admin-ajax; process_user_action() calls check_ajax_referer first.
        if ( ! isset( $_POST['wt_review_item_type'] ) ) {
            return;
        }
        $posted = sanitize_text_field( wp_unslash( $_POST['wt_review_item_type'] ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( '' === $posted ) {
            return;
        }
        foreach ( $this->plugins_array as $plugin ) {
            if ( ! empty( $plugin['post_types'] ) && in_array( $posted, $plugin['post_types'], true ) ) {
                $this->current_post_type = $posted;
                return;
            }
        }
    }

    /**
     *	Update the banner state 
     */
    private function update_banner_state($val)
    {
        $post_type = $this->current_post_type;
        $prefix    = $this->plugin_prefix;
        foreach ($this->plugins_array as $plugin) {
            if (in_array($post_type, $plugin['post_types'], true)) {
                $prefix = isset($plugin['prefix']) ? $plugin['prefix'] : $prefix;
                break;
            }
        }

        update_option($prefix . '_review_request', $val);

        /*
         * State 3 = closed / not interested / never (incl. third "later"); state 4 = user chose "Review".
         * Same timestamp drives the ~60-day common-banner rotation for the next plugin family.
         */
        if ( in_array((int) $val, array(3, 4), true) ) {
            update_option($prefix . '_review_request_closed_at', time());
        }
    }

    /**
     * Pre-update installs: for each IE plugin in plugins_array, if state is 3 (closed) or 4 (reviewed) but
     * _review_request_closed_at is missing, set it once to current time (older versions).
     * Called from __construct() after set_vars() so plugins_array and options exist.
     *
     * @since 2.7.3
     */
    private function maybe_backfill_review_request_closed_at()
    {
        foreach ($this->plugins_array as $plugin) {
            if (empty($plugin['prefix'])) {
                continue;
            }
            $prefix = $plugin['prefix'];
            $state  = absint(get_option($prefix . '_review_request', 0));
            if (!in_array($state, array(3, 4), true)) {
                continue;
            }
            if (absint(get_option($prefix . '_review_request_closed_at', 0)) > 0) {
                continue;
            }
            update_option($prefix . '_review_request_closed_at', time());
        }
    }

    /**
     * Eligibility for contextual (type-specific admin) review banners.
     *
     * Returns false if {prefix}_review_request is not in the same allowed set as check_condition()
     * (closed / reviewed, etc.). Otherwise true when either: (1) at least 5 days since install
     * ({prefix}_start_date), or (2) at least 10 successful jobs for this item_type in wt_iew_action_history.
     *
     * @since 2.7.3
     *
     * @param string|null $item_type Screen-derived type, e.g. order, coupon, subscription, product, user.
     * @return bool
     */
    public function check_condition_for_item_specific_pages( $item_type )
    {
        if (!$item_type || !is_string($item_type)) {
            return false;
        }

        $prefix = null;
        foreach ($this->plugins_array as $plugin) {
            if (!empty($plugin['post_types']) && in_array($item_type, $plugin['post_types'], true)) {
                $prefix = isset($plugin['prefix']) ? $plugin['prefix'] : null;
                break;
            }
        }
        if (!$prefix) {
            return false;
        }

        $banner_state = absint(get_option($prefix . '_review_request', 0));
        if (!in_array($banner_state, array(0, 1, 2, 5), true)) {
            return false;
        }

        $installed = absint(get_option($prefix . '_start_date', 0));
        if ($installed && floor((time() - $installed) / DAY_IN_SECONDS) >= 5) {
            return true;
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count for banner rule.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wt_iew_action_history WHERE status = %d AND item_type = %s",
                1,
                $item_type
            )
        );
        // phpcs:enable

        return $count >= 10;
    }

    /**
     * Contextual review banner: order / product / user screens map to an item_type, then eligibility runs for that type only.
     *
     * Differs from show_banner(), which uses check_condition()'s single global "winner" item_type for dashboard,
     * Import/Export screens, Plugins, and WooCommerce Home.
     *
     * @since 2.7.3
     */
    public function show_banner_in_type_specific_pages()
    {
        global $wt_iew_review_banner_shown;
        if ( isset( $wt_iew_review_banner_shown ) && true === $wt_iew_review_banner_shown ) {
            return;
        }

        $currentScreen = get_current_screen();
        $type_specific_pages = $this->get_type_specific_pages_map();

        $item_type = $this->get_item_type_from_screen($currentScreen);
        if (!$item_type || !isset($type_specific_pages[ $item_type ]) || !in_array($currentScreen->id, $type_specific_pages[ $item_type ], true)) {
            return;
        }

        if (!$this->check_condition_for_item_specific_pages($item_type)) {
            return;
        }

        $this->current_post_type = $item_type;
        foreach ($this->plugins_array as $plugin) {
            if (in_array($item_type, $plugin['post_types'], true)) {
                $this->review_url = $plugin['url'];
                break;
            }
        }
        $this->configure_review_banner_copy();

        $this->print_review_request_banner_markup();
    }

    /**
     * Map admin screen to wt_iew_action_history item_type, or null.
     *
     * On WooCommerce → Reports, the screen id alone is ambiguous: `orders` tab → order, `customers` → user;
     * other tabs return null so no IE review banner targets them.
     *
     * @since 2.7.3
     *
     * @param WP_Screen|null $screen Screen object.
     * @return string|null
     */
    private function get_item_type_from_screen($screen)
    {
        if (!$screen || !isset($screen->id)) {
            return null;
        }
        $id = $screen->id;
        if ('woocommerce_page_wc-reports' === $id) {
            $tab = $this->get_wc_reports_tab_slug();
            if ('orders' === $tab) {
                return 'order';
            }
            if ('customers' === $tab) {
                return 'user';
            }
            return null;
        }
        if ('edit-shop_coupon' === $id) {
            return 'coupon';
        }
        if ('edit-shop_subscription' === $id || 'shop_subscription' === $id) {
            return 'subscription';
        }
        if (in_array($id, array('edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders'), true)) {
            return 'order';
        }
        if (in_array($id, array('edit-product', 'product', 'edit-product_cat', 'edit-product_tag'), true)) {
            return 'product';
        }
        if ('users' === $id) {
            return 'user';
        }
        return null;
    }

    /**
     * Shared HTML for global and contextual review banners.
     *
     * @since 2.7.3
     */
    private function print_review_request_banner_markup()
    {
        global $wt_iew_review_banner_shown;

        $wt_iew_review_banner_shown = true;

        $theme_slug   = $this->get_review_banner_theme_slug();
        $illustration = $this->get_review_banner_illustration_assets();

        ?>
        <div class="<?php echo esc_attr($this->banner_css_class); ?> notice notice-info is-dismissible wbtf-review-banner wbtf-review-banner--<?php echo esc_attr($theme_slug); ?>" data-wt-review-item-type="<?php echo esc_attr( (string) $this->current_post_type ); ?>">
            <div class="wbtf-review-banner__box">
                <span class="wbtf-review-banner__accent" aria-hidden="true"></span>
                <div class="wbtf-review-banner__main">
                    <h3 class="wbtf-review-banner__title"><?php echo esc_html($this->new_review_banner_title); ?></h3>
                    <div class="wbtf-review-banner__text">
                        <?php echo wp_kses_post($this->new_review_banner_message); ?>
                    </div>
                    <div class="wbtf-review-banner__actions wbtf-btns-wrap">
                        <a href="#" class="button wbtf-button-primary" data-type="review"><?php echo esc_html($this->review_btn_new_text); ?></a>
                        <a href="#" class="button wbtf-button-secondary" data-type="later"><?php echo esc_html($this->later_btn_new_text); ?></a>
                        <a href="#" class="wbtf-review-link" data-type="never"><?php echo esc_html($this->already_did_btn_new_text); ?></a>
                    </div>
                </div>
                <div class="wbtf-review-banner__media" aria-hidden="true">
                    <?php if (isset($illustration['width'], $illustration['height'])) : ?>
                        <img class="wbtf-review-banner__img" src="<?php echo esc_url($illustration['url']); ?>" width="<?php echo esc_attr((string) $illustration['width']); ?>" height="<?php echo esc_attr((string) $illustration['height']); ?>" alt="">
                    <?php else : ?>
                        <img class="wbtf-review-banner__img wbtf-review-banner__img--legacy" src="<?php echo esc_url($illustration['url']); ?>" alt="">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Prints the common (global winner) review banner. WooCommerce → Reports is not a common screen;
     * Reports uses {@see self::show_banner_in_type_specific_pages()} only (no duplicate tab logic here).
     */
    public function show_banner()
    {
        $this->print_review_request_banner_markup();
    }

    /**
     *	Ajax hook to process user action on the banner
     */
    public function process_user_action()
    {
        check_ajax_referer($this->plugin_prefix);
        $this->wt_iew_apply_review_item_type_from_request();
        if (isset($_POST['wt_review_action_type'])) {
            $action_type = isset($_POST['wt_review_action_type']) ? sanitize_text_field(wp_unslash($_POST['wt_review_action_type'])) : '';

            /* current action is in allowed action list */
            if (in_array($action_type, $this->allowed_action_type_arr)) {
                if ($action_type == 'never' || $action_type == 'closed') {
                    $this->update_banner_state(3);
                } elseif ($action_type == 'review') {
                    $this->update_banner_state(4);
                } elseif ($action_type == 'later') {
                    // Get current dismissal count
                    $dismissal_count = get_option($this->dismissal_count_option, 0);
                    $dismissal_count++;
                    
                    // Update dismissal tracking
                    update_option($this->dismissal_count_option, $dismissal_count);
                    update_option($this->last_dismissal_option, time());
                    update_option($this->jobs_since_dismissal_option, 0);
                    
                    if ($dismissal_count >= 3) {
                        $this->update_banner_state(3); // Never show again
                    } else {
                        $this->update_banner_state(5); ; // Remind later
                    }
                }
            }
        }
        exit();
    }

    /**
     *	Add banner JS to admin footer
     */
    public function add_banner_scripts()
    {
        if ( ! empty( $GLOBALS['wt_iew_review_banner_footer_printed'] ) ) {
            return;
        }
        $GLOBALS['wt_iew_review_banner_footer_printed'] = true;

        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce($this->plugin_prefix);
    ?>
        <style type="text/css">
            /* Scope: must match $this->banner_css_class (wt_u_iew_basic + _review_request). */
            .wt_u_iew_basic_review_request.wbtf-review-banner.wbtf-review-banner--order {
                --wbtf-review-accent: #1B89E1;
                --wbtf-review-primary: #1B89E1;
                --wbtf-review-primary-hover: #187AC8;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner.wbtf-review-banner--product {
                --wbtf-review-accent: #0B7CB7;
                --wbtf-review-primary: #0E9FDE;
                --wbtf-review-primary-hover: #0c87bd;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner.wbtf-review-banner--user {
                --wbtf-review-accent: #5B2C9B;
                --wbtf-review-primary: #7B4DC8;
                --wbtf-review-primary-hover: #6a3fb0;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner.notice {
                position: relative;
                padding: 12px 8px;
                margin: 12px 0;
                border: none;
                box-shadow: none;
                background: transparent;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .notice-dismiss {
                text-decoration: none;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__box {
                display: flex;
                flex-wrap: nowrap;
                align-items: stretch;
                background: #fff;
                border: 1px solid #E5E7EB;
                border-radius: 8px;
                overflow: hidden;
                padding-right: 42px;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__accent {
                width: 4px;
                flex-shrink: 0;
                background: var(--wbtf-review-accent, #153F95);
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__main {
                flex: 1 1 auto;
                min-width: 0;
                padding: 20px 8px 20px 20px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__kind {
                margin: 0 0 6px;
                font-size: 13px;
                font-weight: 600;
                color: var(--wbtf-review-primary, #2860F4);
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__title {
                margin: 0 0 10px;
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700;
                color: #0f172a;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__text {
                max-width: 52rem;
                color: #64748b;
            }
            /* Body copy: system UI stack (WP admin-like), 14px / regular / line-height 18px */
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__text .wbtf-review-banner__para:not(.wbtf-review-banner__para--cta) {
                margin: 0 0 10px;
                font-size: 14px;
                font-weight: 400;
                font-style: normal;
                line-height: 18px;
                letter-spacing: 0;
                text-indent: 0;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__text .wbtf-review-banner__para--cta {
                margin: 0 0 10px;
                font-size: 14px;
                font-weight: 400;
                font-style: normal;
                letter-spacing: 0;
                text-indent: 0;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__cta-regular {
                display: inline;
                font-size: 14px;
                font-weight: 400;
                font-style: normal;
                line-height: 100%;
                letter-spacing: 0;
                text-indent: 0;
                vertical-align: baseline;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__cta-strong {
                display: inline;
                font-size: 14px;
                font-weight: 500;
                font-style: normal;
                line-height: 17px;
                letter-spacing: 0;
                text-indent: 0;
                vertical-align: baseline;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__para:last-child {
                margin-bottom: 0;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px 14px;
                margin-top: 18px;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-button-primary.button {
                background: var(--wbtf-review-primary, #2860F4) !important;
                color: #fff !important;
                border: 1px solid var(--wbtf-review-primary, #2860F4) !important;
                border-radius: 6px !important;
                padding: 6px 14px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                line-height: 1.4 !important;
                box-shadow: none !important;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-button-primary.button:hover,
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-button-primary.button:focus {
                background: var(--wbtf-review-primary-hover, #1d52d6) !important;
                border-color: var(--wbtf-review-primary-hover, #1d52d6) !important;
                color: #fff !important;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-button-secondary.button {
                background: #fff !important;
                color: var(--wbtf-review-primary, #2860F4) !important;
                border: 1px solid var(--wbtf-review-primary, #2860F4) !important;
                border-radius: 6px !important;
                padding: 6px 14px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                line-height: 1.4 !important;
                box-shadow: none !important;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-button-secondary.button:hover,
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-button-secondary.button:focus {
                background: #f8fafc !important;
                color: var(--wbtf-review-primary-hover, #1d52d6) !important;
                border-color: var(--wbtf-review-primary-hover, #1d52d6) !important;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-link {
                margin: 0;
                padding: 0 2px;
                font-size: 13px;
                font-weight: 600;
                color: var(--wbtf-review-primary, #2860F4);
                text-decoration: none;
                background: none;
                border: none;
                cursor: pointer;
                box-shadow: none;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-link:hover,
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-link:focus {
                color: var(--wbtf-review-primary-hover, #1d52d6);
                text-decoration: underline;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__media {
                flex: 0 0 auto;
                display: flex;
                justify-content: center;
                padding: 8px 20px 12px 12px;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__img {
                display: block;
                width: auto;
                max-width: 100%;
                max-height: 170px;
                height: auto;
            }
            .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__img--legacy {
                width: auto;
                max-width: 200px;
                max-height: 130px;
                object-fit: contain;
            }
            @media (max-width: 782px) {
                .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__box {
                    flex-wrap: wrap;
                }
                .wt_u_iew_basic_review_request.wbtf-review-banner .wbtf-review-banner__media {
                    width: 100%;
                    order: -1;
                    padding-top: 16px;
                }
            }
        </style>
        <script type="text/javascript">
            (function($) {
                "use strict";

                var data_obj = {
                    _wpnonce: '<?php echo esc_js($nonce); ?>',
                    action: '<?php echo esc_js($this->ajax_action_name); ?>',
                    wt_review_action_type: ''
                };

                var bannerSel = '.<?php echo esc_js($this->banner_css_class); ?>';

                $(document).on('click', bannerSel + ' a.button,' + bannerSel + ' a.wbtf-review-link', function(e) {
                    e.preventDefault();
                    var elm = $(this);
                    var btn_type = elm.attr('data-type');
                    var $banner = elm.closest(bannerSel);
                    if (btn_type == 'review') {
                        window.open('<?php echo esc_url($this->review_url); ?>');
                    }
                    $banner.hide();

                    data_obj['wt_review_action_type'] = btn_type;
                    data_obj['wt_review_item_type'] = $banner.attr('data-wt-review-item-type') || '';
                    $.ajax({
                        url: '<?php echo esc_url($ajax_url); ?>',
                        data: data_obj,
                        type: 'POST'
                    });

                }).on('click', bannerSel + ' .notice-dismiss', function(e) {
                    e.preventDefault();
                    var $banner = $(this).closest(bannerSel);
                    data_obj['wt_review_action_type'] = 'closed';
                    data_obj['wt_review_item_type'] = $banner.attr('data-wt-review-item-type') || '';
                    $.ajax({
                        url: '<?php echo esc_url($ajax_url); ?>',
                        data: data_obj,
                        type: 'POST',
                    });

                });

            })(jQuery)
        </script>
        <?php
    }

    /**
     *	Checks the condition to show the banner
     */
    public function check_condition()
    { 
        global $wt_iew_review_banner_shown;
        if ( isset( $wt_iew_review_banner_shown ) && true === $wt_iew_review_banner_shown ) {
            return false;
        } 

        // Collect banner states; 3/4 = closed for that family — try >5 jobs for remaining plugins only.
        $new_start_date = get_option($this->last_dismissal_option, 0);
        $dismissal_count = get_option($this->dismissal_count_option, 0);
        $latest_start_date = 0;
        $eligible_prefixes = array();
        $saw_closed_plugin = false;

        foreach ($this->plugins_array as $plugin) {
            $plugin_prefix = $plugin['prefix'];
            $banner_state = absint(get_option($plugin_prefix . '_review_request', 0));

            if (in_array($banner_state, array(0, 1, 2, 5), true)) {
                $eligible_prefixes[] = $plugin_prefix;

                if (5 === $banner_state && 0 === (int) $new_start_date) {
                    $plugin_start_date = absint(get_option($plugin['prefix'] . '_start_date', 0));
                    if ($plugin_start_date > $latest_start_date) {
                        $latest_start_date = $plugin_start_date;
                    }
                    $dismissal_count = 1;
                    update_option($this->dismissal_count_option, $dismissal_count);
                }
            } elseif (in_array($banner_state, array(3, 4), true)) {
                $saw_closed_plugin = true;
                continue;
            } else {
                return false;
            }
        }

        if (0 === (int) $latest_start_date) {
            $latest_start_date = $new_start_date;
        }

        /*
         * If any family is fully closed (3/4), run rotation/cooldown before the global "remind later"
         * counters. Otherwise a prior wt_iew_basic_dismiss_count from "Later" can re-show the banner
         * even though {prefix}_review_request was saved as closed on dismiss.
         */
        if ($saw_closed_plugin) {
            return $this->is_review_prompt_allowed_by_job_count_after_close_cooldown(
                array_values(array_unique($eligible_prefixes))
            );
        }

        // Handle "remind later" state if any plugin has it
        if ( $dismissal_count > 0 && $dismissal_count < 3 ) { 
            // dismissed condition
            return $this->handle_dissmissed($dismissal_count, $latest_start_date);
         }            
 

        // Check never dismissed condition
        return $this->handle_never_dissmissed();
    }

    private function handle_never_dissmissed() {
        global $wpdb, $wt_iew_review_banner_shown;

        // Get first successful job date.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This is a valid use of direct database query.
        $start_date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM {$wpdb->prefix}wt_iew_action_history 
                WHERE status = %d ORDER BY created_at ASC LIMIT 1",
                1
            )
        ); 
        // phpcs:enable
        
        if (!$start_date) {
            return false;
        } 


        $days_since_start = floor((time() - $start_date) / 86400);
        // If less than 30 days from start
        if ($days_since_start > 5 && $days_since_start <= 30) {
            // Get successful jobs on distinct dates after 5 days
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This is a valid use of direct database query.
            $success_jobs = $wpdb->get_row($wpdb->prepare(
                "SELECT h.item_type, 
                    COUNT(DISTINCT DATE(FROM_UNIXTIME(h.created_at))) as date_count,
                    MAX(h.created_at) as last_success
                FROM {$wpdb->prefix}wt_iew_action_history h
                WHERE h.status = %d 
                AND h.created_at >= %d
                GROUP BY h.item_type
                HAVING COUNT(DISTINCT DATE(FROM_UNIXTIME(h.created_at))) >= 2
                ORDER BY date_count DESC, last_success DESC 
                LIMIT 1",
                1, $start_date
            )); 
            // phpcs:enable

            if ($success_jobs && $success_jobs->date_count >= 2) { 
                $this->current_post_type = $success_jobs->item_type; 
                $wt_iew_review_banner_shown = true;
                return true;
            }
        } 
        
        if ($days_since_start > 30) {
            // After 30 days, check last job (regardless of success)

            // First get the last job regardless of post type
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This is a valid use of direct database query.
            $last_job = $wpdb->get_row(
                "SELECT item_type, status, created_at 
                FROM {$wpdb->prefix}wt_iew_action_history 
                ORDER BY created_at DESC 
                LIMIT 1"
            );
            // phpcs:enable

            if ( $last_job &&  1 === (int) $last_job->status ) {
                $this->current_post_type = $last_job->item_type;
                $wt_iew_review_banner_shown = true;
                return true;
            }
        }
        return false;
    }

    private function handle_dissmissed($dismissal_count, $last_dismissal) {
        
        global $wt_iew_review_banner_shown;

        $days_since_dismissal = floor((time() - $last_dismissal) / (60 * 60 * 24));
        $jobs_since_dismissal = $this->get_jobs_since_dismissal($last_dismissal);

        
        if ($dismissal_count > 0) {

            if ($dismissal_count == 1) {
                // First dismissal: 15 jobs OR 50 days
                if ($jobs_since_dismissal >= 15 || $days_since_dismissal >= 50) { 
                    $wt_iew_review_banner_shown = true;
                    return true;
                }
            } elseif ($dismissal_count == 2) {
                // Second dismissal: 30 jobs OR 90 days
                if ($jobs_since_dismissal >= 30 || $days_since_dismissal >= 90) {
                    $wt_iew_review_banner_shown = true;
                    return true;
                }
            } 
        }
        return false;
    }

    /**
     * Minimum time after the most recent banner close (state 3/4) before we may use the job-count rule (~2 months).
     *
     * @since 2.7.3
     *
     * @return int Seconds.
     */
    private function get_review_banner_close_cooldown_seconds()
    {
        return 60 * DAY_IN_SECONDS;
    }

    /**
     * True when the newest `{prefix}_review_request_closed_at` among plugins in state 3 or 4 exists
     * and is at least get_review_banner_close_cooldown_seconds() ago (so another plugin may be prompted).
     *
     * @since 2.7.3
     *
     * @return bool
     */
    private function is_last_review_close_old_enough_for_rotation()
    {
        $latest_close = 0;
        foreach ($this->plugins_array as $plugin) {
            if (empty($plugin['prefix'])) {
                continue;
            }
            $state = absint(get_option($plugin['prefix'] . '_review_request', 0));
            if (!in_array($state, array(3, 4), true)) {
                continue;
            }
            $closed_at = absint(get_option($plugin['prefix'] . '_review_request_closed_at', 0));
            if ($closed_at > $latest_close) {
                $latest_close = $closed_at;
            }
        }
        if (0 === $latest_close) {
            return false;
        }

        return (time() - $latest_close) >= $this->get_review_banner_close_cooldown_seconds();
    }

    /**
     * After at least one IE plugin banner was closed: allow prompting another family only if the
     * most recent close was at least ~2 months ago AND one of the given prefixes has more than five
     * successful jobs (status=1) across its post_types in wt_iew_action_history.
     *
     * @since 2.7.3
     *
     * @param string[] $eligible_prefixes Prefixes still on banner state 0/1/2/5 to consider.
     * @return bool
     */
    private function is_review_prompt_allowed_by_job_count_after_close_cooldown( $eligible_prefixes )
    {
        global $wpdb, $wt_iew_review_banner_shown;

        if (!$this->is_last_review_close_old_enough_for_rotation()) {
            return false;
        }

        if (empty($eligible_prefixes) || !is_array($eligible_prefixes)) {
            return false;
        }

        $eligible = array_values(array_unique(array_filter(array_map('strval', $eligible_prefixes))));
        if (array() === $eligible) {
            return false;
        }

        foreach ($this->plugins_array as $plugin) {
            $prefix = isset($plugin['prefix']) ? $plugin['prefix'] : '';
            if ('' === $prefix || !in_array($prefix, $eligible, true)) {
                continue;
            }
            if (empty($plugin['post_types']) || !is_array($plugin['post_types'])) {
                continue;
            }

            $types = array_values(array_filter(array_map('strval', $plugin['post_types'])));
            if (array() === $types) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($types), '%s'));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- IN list placeholders match $types; merged args passed to prepare().
            $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}wt_iew_action_history WHERE status = %d AND item_type IN ({$placeholders})";
            $args = array_merge(array(1), $types);
            $count = (int) $wpdb->get_var($wpdb->prepare($sql, $args));
            // phpcs:enable

            if ($count > 5) {
                $this->current_post_type = $types[0];
                $wt_iew_review_banner_shown = true;
                return true;
            }
        }

        return false;
    }

    private function get_jobs_since_dismissal($last_dismissal) {
        
        global $wpdb;
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This is a valid use of direct database query.
        $results = $wpdb->get_row($wpdb->prepare(
            "SELECT h.item_type, 
                    COUNT(*) as success_count,
                    MAX(h.created_at) as last_success
             FROM {$wpdb->prefix}wt_iew_action_history h
             WHERE h.status = %d 
             AND h.created_at >= %s
             GROUP BY h.item_type
             ORDER BY success_count DESC, last_success DESC
             LIMIT 1",
            1, $last_dismissal
        ));
        // phpcs:enable
        
        // If we have results, get the highest count (with latest success date if tied)
        if ($results) {
            $this->current_post_type = $results->item_type;
            return $results->success_count;
        }
        return 0;
    }

    public function show_banner_cta()
    {
        global $wt_iew_review_banner_shown;
        if ( isset( $wt_iew_review_banner_shown ) && true === $wt_iew_review_banner_shown ) {
            return;
        }
        
        if (is_plugin_active('users-customers-import-export-for-wp-woocommerce/users-customers-import-export-for-wp-woocommerce.php')) {

            if (!is_plugin_active('order-import-export-for-woocommerce/order-import-export-for-woocommerce.php') && !is_plugin_active('product-import-export-for-woo/product-import-export-for-woo.php')) {

                $screen = get_current_screen();

                if ($screen->id == 'woocommerce_page_wc-reports') {
                // Set 'orders' as default tab if no 'tab' is set.
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not required.
                $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'orders';
    
                // Define content and plugin URL based on the current tab
                $content = '';
                $plugin_url = '';
                $title = esc_html__('Did You Know?', 'users-customers-import-export-for-wp-woocommerce');
                $cookie_name = ''; // We'll set this based on the current tab
    
                switch ($current_tab) {
                    case 'orders':
                        // Check if the 'orders' banner has been hidden
                        $cookie_name = 'hide_cta_orders';
                        if (isset($_COOKIE[$cookie_name]) && sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) == 'true') {
                            return; // Don't show the banner if the cookie is set
                        }
    
                        $content = '<span style="color: #212121;">' . esc_html__('You can now export WooCommerce order', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #5454A5; font-weight: bold;">' . esc_html__('data with custom filters, custom metadata, FTP export, and scheduling options.', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #212121;">' . esc_html__('Bulk edit or update orders using CSV, XML, Excel, or TSV files in one go.', 'users-customers-import-export-for-wp-woocommerce') . '</span>';
                        $plugin_url = 'https://www.webtoffee.com/product/order-import-export-plugin-for-woocommerce/?utm_source=free_plugin_report&utm_medium=basic_revamp&utm_campaign=Order_Import_Export';
                        break;
    
                    case 'customers':
                        // Check if the 'customers' banner has been hidden
                        $cookie_name = 'hide_cta_customers';
                        if (isset($_COOKIE[$cookie_name]) && sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) == 'true') {
                            return; // Don't show the banner if the cookie is set
                        }
    
                        $content = '<span style="color: #212121;">' . esc_html__('You can easily bulk export your customers', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #5454A5; font-weight: bold;">' . esc_html__('data to CSV, XML, Excel, or TSV files in just a few clicks.', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #212121;">' . esc_html__('Export custom user metadata of third-party plugins seamlessly.', 'users-customers-import-export-for-wp-woocommerce') . '</span>';
                        $plugin_url = 'https://www.webtoffee.com/product/wordpress-users-woocommerce-customers-import-export/?utm_source=free_plugin_report&utm_medium=basic_revamp&utm_campaign=User_Import_Export';
                        break;
    
                    case 'stock':
                        // Check if the 'stock' banner has been hidden
                        $cookie_name = 'hide_cta_stock';
                        if (isset($_COOKIE[$cookie_name]) && sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) == 'true') {
                            return; // Don't show the banner if the cookie is set
                        }
    
                        $content = '<span style="color: #212121;">' . esc_html__('Get your store products', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #5454A5; font-weight: bold;">' . esc_html__('bulk exported for hassle-free migration, inventory management, and bookkeeping.', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #212121;">' . esc_html__('Import/export WooCommerce products with reviews, images, and custom metadata.', 'users-customers-import-export-for-wp-woocommerce') . '</span>';
                        $plugin_url = 'https://www.webtoffee.com/product/product-import-export-woocommerce/?utm_source=free_plugin_report&utm_medium=basic_revamp&utm_campaign=Product_Import_Export';
                        break;
    
                    case 'subscriptions':
                        // Check if the 'subscriptions' banner has been hidden
                        $cookie_name = 'hide_cta_subscriptions';
                        if (isset($_COOKIE[$cookie_name]) && sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) == 'true') {
                            return; // Don't show the banner if the cookie is set
                        }
    
                        $content = '<span style="color: #212121;">' . esc_html__('Get your subscription orders exported to a', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #5454A5; font-weight: bold;">' . esc_html__('CSV, XML, Excel, or TSV file.', 'users-customers-import-export-for-wp-woocommerce') . '</span> <span style="color: #212121;">' . esc_html__('Featuring scheduled exports, advanced filters, custom metadata, and more.', 'users-customers-import-export-for-wp-woocommerce') . '</span>';
                        $plugin_url = 'https://www.webtoffee.com/product/order-import-export-plugin-for-woocommerce/?utm_source=free_plugin_report&utm_medium=basic_revamp&utm_campaign=Order_Import_Export';
                        break;
    
                    default:
                        return; // Exit if not on a recognized tab
                }
    
                // HTML for the banner remains unchanged
                ?>
                <div id="cta-banner" class="notice notice-info" style="position: relative; padding: 15px; background-color: #f3f0ff; border-left: 4px solid #5454A5; display: flex; justify-content: space-between; align-items: center; border-radius: 1px;">
                    <div style="flex: 1; margin-right: 10px;">
                        <div style="display: flex; align-items: center; margin-bottom: 5px;">
                            <img src="<?php echo esc_url(WT_U_IEW_PLUGIN_URL . 'assets/images/idea_bulb_purple.svg'); ?>" style="width: 25px; margin-right: 10px;">
                            <h2 style="margin: 0; font-size: 16px; color: #2d2d77; font-weight: 600;"><?php echo esc_html($title); ?></h2>
                        </div>
                        <p style="margin: 0; font-size: 14px; color: #6f6f6f; line-height: 1.4;"><?php echo wp_kses_post($content); ?></p>
                    </div>
    
                    <div style="display: flex; gap: 10px;">
                        <a href="<?php echo esc_url($plugin_url); ?>" target="_blank" class="button-primary" style="background: #5454A5; color: white; border: none; padding: 8px 15px; border-radius: 4px; text-decoration: none; display: flex; align-items: center; justify-content: center; font-size: 14px;"><?php esc_html_e('Check out plugin ➔', 'users-customers-import-export-for-wp-woocommerce'); ?></a>
                        <button id="maybe-later" class="button-secondary" style="background-color: #f3f0ff; color: #4a42a3; padding: 8px 15px; border: 1px solid #5454A5; border-radius: 4px; font-size: 14px;"><?php esc_html_e('Maybe later', 'users-customers-import-export-for-wp-woocommerce'); ?></button>
                    </div>
                </div>
    
                <script type="text/javascript">
                    (function($) {
                        $('#maybe-later').on('click', function(e) {
                            e.preventDefault();
                            // Set a cookie to hide the banner for 30 days for this specific tab
                            document.cookie = "<?php echo esc_js($cookie_name); ?>=true; path=/; max-age=" + (30*24*60*60) + ";";
                            $('#cta-banner').remove();
                        });
                    })(jQuery);
                </script>
                <?php
                }
            }
        }
    }

    /**
     * Show WooCommerce Pages Banner
     * Displays promotional banners on WooCommerce pages (orders, products, users)
     */
    public function show_wc_pages_banner()
    {
        global $wt_iew_review_banner_shown;
        global $wt_iew_wc_pages_banner_shown;

        // Check if another plugin is already showing a WC pages banner
        if (isset($wt_iew_wc_pages_banner_shown) && $wt_iew_wc_pages_banner_shown) {
            return;
        }
        
        $screen = get_current_screen();
        $wc_pages_banners = array(
            'woocommerce_page_wc-orders' => array(
                'option_name' => 'wt_iew_hide_did_you_know_wc_orders_banner_2026',
                'cookie_name' => 'hide_cta_wc_orders',
                'content' => '<span style="color: #212121;">' . esc_html__('There\'s a faster way to manage orders. Import, export, and update orders in bulk using CSV, XML, or Excel with the Order Import Export Plugin.', 'users-customers-import-export-for-wp-woocommerce') . '</span>',
                'plugin_url' => 'https://www.webtoffee.com/product/order-import-export-plugin-for-woocommerce/?utm_source=free_plugin&utm_medium=woocommerce_orders&utm_campaign=Order_import_export',
                'plugin_check' => 'order-import-export-for-woocommerce/order-import-export-for-woocommerce.php',
                'banner_color' => '#4750CB',
                'banner_image' => 'assets/images/idea_bulb_blue.svg',
                'premium_plugin' => 'wt-import-export-for-woo-order/wt-import-export-for-woo-order.php'
            ),
            'edit-product' => array(
                'option_name' => 'wt_iew_hide_did_you_know_wc_products_banner_2026',
                'cookie_name' => 'hide_cta_wc_products',
                'content' => '<span style="color: #212121;">' . esc_html__('You can now easily import and export WooCommerce products with images using CSV, XML, or Excel files.', 'users-customers-import-export-for-wp-woocommerce') . '</span>' ,
                'plugin_url' => 'https://www.webtoffee.com/product/product-import-export-woocommerce/?utm_source=free_plugin_cross_promotion&utm_medium=all_products_tab&utm_campaign=Product_import_export',
                'plugin_check' => 'product-import-export-for-woo/product-import-export-for-woo.php',
                'banner_color' => '#7B54E0',
                'banner_image' => 'assets/images/idea_bulb_gloomy_purple.svg',
                'premium_plugin' => 'wt-import-export-for-woo-product/wt-import-export-for-woo-product.php'
            ),
            'users' => array(
                'option_name' => 'wt_iew_hide_did_you_know_wc_customers_banner_2026',
                'cookie_name' => 'hide_cta_wc_customers',
                'content' => '<span style="color: #212121;">' . esc_html__('Easily import and export WordPress users & WooCommerce customers to CSV, XML, or Excel for seamless data management.', 'users-customers-import-export-for-wp-woocommerce') . '</span>',
                'plugin_url' => 'https://www.webtoffee.com/product/wordpress-users-woocommerce-customers-import-export/?utm_source=free_plugin_cross_promotion&utm_medium=woocommerce_customers&utm_campaign=User_import_export',
                'plugin_check' => 'users-customers-import-export-for-wp-woocommerce/users-customers-import-export-for-wp-woocommerce.php',
                'banner_color' => '#9D47CB',
                'banner_image' => 'assets/images/idea_bulb_morado_purple.svg',
                'premium_plugin' => 'wt-import-export-for-woo-user/wt-import-export-for-woo-user.php'
            )
        );

        if (!isset($wc_pages_banners[$screen->id])) {
            return;
        }

        $banner_data = $wc_pages_banners[$screen->id];

        // Check if premium plugin is active - if so, don't show the banner
        if (isset($banner_data['premium_plugin']) && is_plugin_active($banner_data['premium_plugin'])) {
            return;
        }

        // Check if banner is hidden via database option (close button) or review banner is shown
        if ( ( isset( $wt_iew_review_banner_shown ) && true === $wt_iew_review_banner_shown ) || true === (bool) get_option($banner_data['option_name'], false)) {
            return;
        }

        // Check if banner is temporarily hidden via cookie (maybe later button)
        if (isset($_COOKIE[$banner_data['cookie_name']]) && 'true' === sanitize_text_field(wp_unslash($_COOKIE[$banner_data['cookie_name']]))) {
            return;
        }

        // Mark that a banner is being shown
        $wt_iew_wc_pages_banner_shown = true;

        $title = esc_html__('Did You Know?', 'users-customers-import-export-for-wp-woocommerce');
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('wt_iew_wc_pages_banner');
        ?>
        <div id="wt-iew-cta-banner" class="notice notice-info" style="position: relative; padding: 15px; height: 38px; background-color: #fff; border-left: 4px solid <?php echo esc_attr($banner_data['banner_color']); ?>; display: flex; justify-content: space-between; align-items: center; border-radius: 1px; margin: 10px 0px 10px 0;">
            <button type="button" class="wt-iew-notice-dismiss" data-option-name="<?php echo esc_attr($banner_data['option_name']); ?>" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); border: none; margin: 0; padding: 0; background: none; color: #6E6E6E; cursor: pointer; font-size: 20px; line-height: 1; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">×</button>
            <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                <div style="display: flex; align-items: center; ">
                    <img src="<?php echo esc_url(WT_U_IEW_PLUGIN_URL . $banner_data['banner_image']); ?>" style="width: 25px; margin-right: 10px; color: <?php echo esc_attr($banner_data['banner_color']); ?>;">
                    <h2 style="color: <?php echo esc_attr($banner_data['banner_color']); ?>; font-weight: 500; font-size:15px;"><?php echo esc_html($title); ?></h2>
                    <span style="margin: 0 6px; font-size: 13px; color: #212121; line-height: 1.4;"><?php echo wp_kses_post($banner_data['content']); ?></span>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; ">
                    <a href="<?php echo esc_url($banner_data['plugin_url']); ?>" target="_blank" class="button-primary" style="background: <?php echo esc_attr($banner_data['banner_color']); ?>; color: white; border: none; padding: 8px 15px; border-radius: 4px; text-decoration: none; display: flex; align-items: center; justify-content: center; font-size: 13px; height: 32px; line-height: 1;"><?php esc_html_e('Check out plugin →', 'users-customers-import-export-for-wp-woocommerce'); ?></a>
                    <button class="wt-iew-maybe-later button-secondary" data-cookie-name="<?php echo esc_attr($banner_data['cookie_name']); ?>" style="background-color: #fff; color: #64594D; border: 1px solid #FFF; border-radius: 4px; font-size: 13px; display: flex; align-items: center; justify-content: center; height: 32px; line-height: 1;"><?php esc_html_e('Maybe later', 'users-customers-import-export-for-wp-woocommerce'); ?></button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            (function($) {
                // Maybe later button - uses cookie (temporary, 30 days)
                $('.wt-iew-maybe-later').on('click', function(e) {
                    e.preventDefault();
                    var cookieName = $(this).data('cookie-name');
                    document.cookie = cookieName + "=true; path=/; max-age=" + (30 * 24 * 60 * 60) + ";";
                    $(this).closest('#wt-iew-cta-banner').remove();
                });

                // Close button - saves to database (permanent)
                $('.wt-iew-notice-dismiss').on('click', function(e) {
                    e.preventDefault();
                    var optionName = $(this).data('option-name');
                    var banner = $(this).closest('#wt-iew-cta-banner');
                    
                    $.ajax({
                        url: '<?php echo esc_url($ajax_url); ?>',
                        type: 'POST',
                        data: {
                            action: 'wt_iew_dismiss_wc_pages_banner',
                            option_name: optionName,
                            nonce: '<?php echo esc_js($nonce); ?>'
                        },
                        success: function(response) {
                            banner.remove();
                        }
                    });
                });
            })(jQuery);
        </script>
        <?php
    }

    /**
     * AJAX handler for dismissing WooCommerce Pages Banner (close button)
     * Saves to database option permanently
     */
    public function dismiss_wc_pages_banner_ajax()
    {
        check_ajax_referer('wt_iew_wc_pages_banner', 'nonce');

        if ( ! isset( $_POST['option_name'] ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Missing option name.', 'users-customers-import-export-for-wp-woocommerce' ) ),
                400
            );
        }

        $option_name = sanitize_text_field( wp_unslash( $_POST['option_name'] ) );

        // Allowlist: only the banner-dismiss options the plugin itself defines (see show_wc_pages_banner()).
        $allowed_options = array(
            'wt_iew_hide_did_you_know_wc_orders_banner_2026',
            'wt_iew_hide_did_you_know_wc_products_banner_2026',
            'wt_iew_hide_did_you_know_wc_customers_banner_2026',
        );

        if ( ! in_array( $option_name, $allowed_options, true ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Invalid option.', 'users-customers-import-export-for-wp-woocommerce' ) ),
                400
            );
        }

        update_option( $option_name, true );
        wp_send_json_success();
    }
    
}
new User_import_export_Review_Request();
