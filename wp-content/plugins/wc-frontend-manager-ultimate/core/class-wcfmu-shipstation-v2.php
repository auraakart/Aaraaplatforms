<?php

/**
 * WCFM plugin core
 *
 * Shipstation v2 core
 *
 * @author  WC Lovers
 * @package wcfmu/core
 * @version 5.1.5
 */

class WCFMu_Shipstation_v2 {

    public function __construct() {
        // Define constant for export limit backward compatibility
        if (! defined('WCFMu_SHIPSTATION_EXPORT_LIMIT')) {
            define('WCFMu_SHIPSTATION_EXPORT_LIMIT', 100);
        }

        // WCFM Shipstation Setting
        add_action('end_wcfm_vendor_settings', [&$this, 'wcfm_shipstation_v2_setting']);

        // WCFM Shipstation Setting Save
        add_action('wcfm_vendor_settings_update', [&$this, 'wcfm_shipstation_v2_setting_save'], 150, 2);

        // REST API loader for modern ShipStation connections.
        $this->load_rest_api();

        // AJAX Handler for generating new authentication data
        add_action('wp_ajax_wcfm_shipstation_generate_keys', [&$this, 'ajax_generate_keys']);
    }

    /**
     * Generate read-only auth key for ShipStation
     *
     * @param integer $user_id
     *
     * @return string
     */
    public function generate_key($user_id) {
        $to_hash = $user_id . date('U') . mt_rand();
        return apply_filters('wcfm_shipstation_auth_key', 'MARKETPLACESS-' . hash_hmac('md5', $to_hash, wp_hash($to_hash)), $user_id);
    }

    /**
     * Retrieve existing API key info for a vendor (truncated key, last access, key ID).
     *
     * @param int $user_id Vendor user ID.
     *
     * @return array|null Associative array with key_id, truncated_key, last_access, or null if no key exists.
     */
    protected function get_existing_api_key_info($user_id) {
        global $wpdb;

        $key_id = get_user_meta($user_id, '_wcfm_shipstation_api_key_id', true);

        if (empty($key_id)) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT key_id, truncated_key, last_access FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d",
                $key_id
            ),
            ARRAY_A
        );

        if (empty($row)) {
            // Key ID stored in meta but row no longer exists in DB — clean up stale meta.
            delete_user_meta($user_id, '_wcfm_shipstation_api_key_id');
            return null;
        }

        return $row;
    }

    /**
     * Shipstation Admin Setting
     */
    public function wcfm_shipstation_v2_setting($user_id) {
        global $WCFM;

        $wcfm_shipstation_setting = get_user_meta($user_id, 'wcfm_shipstation_setting', true);
        $wcfm_shipstation_setting = $wcfm_shipstation_setting ? $wcfm_shipstation_setting : [];

        $statuses = wcfmu_shipstation_get_order_status();

        $export_statuses = ! empty($wcfm_shipstation_setting['export_statuses']) ? $wcfm_shipstation_setting['export_statuses'] : [];
        $auth_key = get_user_meta($user_id, 'shipstation_auth_key', true);

        // Generate an auth key immediately if it's missing (same as v1)
        if (empty($auth_key)) {
            $auth_key = $this->generate_key($user_id);
            update_user_meta($user_id, 'shipstation_auth_key', $auth_key);
        }

        $site_url    = home_url();
        $existing_key = $this->get_existing_api_key_info($user_id);
        $has_keys     = !empty($existing_key);

        // Determine connection status
        $status_label = '';
        $status_color = '';
        $last_access_text = '';

        if ($has_keys) {
            $last_access = $existing_key['last_access'];

            if (!empty($last_access) && $last_access !== '0000-00-00 00:00:00') {
                $status_label = __('Connected', 'wc-frontend-manager-ultimate');
                $status_color = '#00a32a'; // green
                $last_access_text = sprintf(
                    __('Last synced: %s', 'wc-frontend-manager-ultimate'),
                    human_time_diff(strtotime($last_access), current_time('timestamp')) . ' ' . __('ago', 'wc-frontend-manager-ultimate')
                );
            } else {
                $status_label = __('Keys Generated — Awaiting First Sync', 'wc-frontend-manager-ultimate');
                $status_color = '#dba617'; // amber
                $last_access_text = __('ShipStation has not connected yet using these keys.', 'wc-frontend-manager-ultimate');
            }
        }

?>
        <!-- collapsible -->
        <div class="page_collapsible" id="wcfm_settings_form_shipstation_v2_head">
            <label class="wcfmfa fa-ship"></label>
            <?php _e('ShipStation V2', 'wc-frontend-manager-ultimate'); ?><span></span>
        </div>
        <div class="wcfm-container">
            <div id="wcfm_settings_form_shipstation_v2_expander" class="wcfm-content">
                <h2><?php _e('ShipStation Authentication (V2)', 'wc-frontend-manager-ultimate'); ?></h2>
                <div class="wcfm_clearfix"></div>

                <p>
                    <?php _e('Connect your ShipStation account to your store using your public store URL, the Authentication Key below, and consumer Key-Secret pair.', 'wc-frontend-manager-ultimate'); ?>
                </p>

                <div class="wcfm_shipstation_v2_auth_block" style="background:#f1f1f1; padding:20px; border-radius:4px; margin-bottom:20px;">

                    <?php if ($has_keys) : ?>
                        <!-- Connection Status Badge -->
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:15px; padding:10px 14px; background:#fff; border-left:4px solid <?php echo esc_attr($status_color); ?>; border-radius:2px;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?php echo esc_attr($status_color); ?>;"></span>
                            <strong style="color:<?php echo esc_attr($status_color); ?>;"><?php echo esc_html($status_label); ?></strong>
                        </div>

                        <p><strong><?php _e('API Key:', 'wc-frontend-manager-ultimate'); ?></strong>
                            <code>&hellip;<?php echo esc_html($existing_key['truncated_key']); ?></code>
                        </p>
                        <?php if ($last_access_text) : ?>
                            <p class="description"><?php echo esc_html($last_access_text); ?></p>
                        <?php endif; ?>
                        <div class="wcfm_clearfix" style="margin-bottom:15px;"></div>
                    <?php endif; ?>

                    <p><strong><?php _e('URL:', 'wc-frontend-manager-ultimate'); ?></strong> <br />
                        <code><?php echo esc_url($site_url); ?></code>
                    </p>
                    <p><strong><?php _e('Authentication Key:', 'wc-frontend-manager-ultimate'); ?></strong> <br />
                        <code><?php echo esc_html($auth_key); ?></code>
                    </p>
                    <p class="description">
                        <?php _e('Copy and paste this Authentication Key into ShipStation during setup.', 'wc-frontend-manager-ultimate'); ?>
                    </p>
                    <hr>

                    <?php if ($has_keys) : ?>
                        <p style="color:#d63638; margin-bottom:10px;">
                            <span class="wcfmfa fa-exclamation-triangle"></span>
                            <?php _e('API credentials already exist. Regenerating will immediately break any active ShipStation connection using the current keys. You will need to reconnect your store in ShipStation with the new credentials.', 'wc-frontend-manager-ultimate'); ?>
                        </p>
                        <button type="button" class="wcfm_submit_button" id="wcfm_shipstation_v2_generate_btn" style="background:#d63638; border-color:#d63638;">
                            <?php _e('Regenerate API Credentials', 'wc-frontend-manager-ultimate'); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="wcfm_submit_button" id="wcfm_shipstation_v2_generate_btn">
                            <?php _e('Generate API Credentials', 'wc-frontend-manager-ultimate'); ?>
                        </button>
                    <?php endif; ?>

                    <div id="wcfm_shipstation_v2_auth_details" style="display:none; margin-top:20px;">
                        <p style="color:#d63638;"><?php _e('IMPORTANT: Please copy your Consumer Key and Secret now. They will not be completely visible again once you leave this page.', 'wc-frontend-manager-ultimate'); ?></p>
                        <p><strong><?php _e('Consumer Key:', 'wc-frontend-manager-ultimate'); ?></strong> <br />
                            <code id="wcfm_shipstation_v2_ck"></code>
                        </p>
                        <p><strong><?php _e('Consumer Secret:', 'wc-frontend-manager-ultimate'); ?></strong> <br />
                            <code id="wcfm_shipstation_v2_cs"></code>
                        </p>
                    </div>
                </div>

                <?php
                $WCFM->wcfm_fields->wcfm_generate_form_field(
                    apply_filters(
                        'wcfm_settings_fields_shipstation_v2',
                        [
                            'wcfm_shipstation_setting_export_statuses' => [
                                'label'             => __('Export Order Statuses', 'wc-frontend-manager-ultimate'),
                                'name'              => 'wcfm_shipstation_setting[export_statuses]',
                                'type'              => 'select',
                                'options'           => $statuses,
                                'class'             => 'wcfm-select wcfm_ele',
                                'value'             => $export_statuses,
                                'label_class'       => 'wcfm_title',
                                'attributes'        => ['multiple' => 'multiple'],
                                'custom_attributes' => ['required' => 'required'],
                                'hints'             => __('Define the order statuses that should be exported to ShipStation via the REST API.', 'wc-frontend-manager-ultimate'),
                            ],
                        ]
                    )
                );
                ?>
            </div>
        </div>
        <div class="wcfm_clearfix"></div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var hasExistingKeys = <?php echo $has_keys ? 'true' : 'false'; ?>;

                $('#wcfm_shipstation_v2_generate_btn').on('click', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var originalText = $btn.text();
                    var confirmMsg = hasExistingKeys ?
                        '<?php echo esc_js(__('WARNING: This will revoke your current API credentials and break any active ShipStation connection. You will need to reconnect your store in ShipStation with the new keys. Are you sure?', 'wc-frontend-manager-ultimate')); ?>' :
                        '<?php echo esc_js(__('This will generate new WooCommerce API credentials for ShipStation. Continue?', 'wc-frontend-manager-ultimate')); ?>';

                    if (!confirm(confirmMsg)) {
                        return;
                    }

                    $btn.text('<?php echo esc_js(__('Generating...', 'wc-frontend-manager-ultimate')); ?>').prop('disabled', true);

                    $.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            action: 'wcfm_shipstation_generate_keys',
                            nonce: '<?php echo wp_create_nonce('wcfm_shipstation_generate'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                $('#wcfm_shipstation_v2_auth_details').slideDown();
                                $('#wcfm_shipstation_v2_ck').text(response.data.consumer_key);
                                $('#wcfm_shipstation_v2_cs').text(response.data.consumer_secret);
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Failed to generate keys.', 'wc-frontend-manager-ultimate')); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('An error occurred. Please try again.', 'wc-frontend-manager-ultimate')); ?>');
                        },
                        complete: function() {
                            $btn.text(originalText).prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <!-- end collapsible -->
<?php
    }

    /**
     * Save settings 
     */
    public function wcfm_shipstation_v2_setting_save($user_id, $wcfm_settings_form) {
        if (isset($wcfm_settings_form['wcfm_shipstation_setting'])) {
            unset($wcfm_settings_form['wcfm_shipstation_setting']['auth_key']);
            // Keep existing keys if they aren't explicitly passed
            $existing = get_user_meta($user_id, 'wcfm_shipstation_setting', true);
            $new = $wcfm_settings_form['wcfm_shipstation_setting'];

            if (!empty($existing) && is_array($existing)) {
                $new = wp_parse_args($new, $existing);
            }

            update_user_meta($user_id, 'wcfm_shipstation_setting', $new);
        }
    }

    /**
     * Generate the Consumer Key and Secret within WooCommerce manually.
     */
    public function ajax_generate_keys() {
        check_ajax_referer('wcfm_shipstation_generate', 'nonce');

        $vendor_id = get_current_user_id();

        if (! wcfm_is_vendor()) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'wc-frontend-manager-ultimate')]);
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'woocommerce_api_keys';

        // Revoke any existing keys generated for this vendor BEFORE creating new ones.
        $existing_key_id = get_user_meta($vendor_id, '_wcfm_shipstation_api_key_id', true);
        if ($existing_key_id) {
            $wpdb->delete(
                $table_name,
                ['key_id' => $existing_key_id],
                ['%d']
            );
            delete_user_meta($vendor_id, '_wcfm_shipstation_api_key_id');
        }

        // Create new keys
        $consumer_key    = 'ck_' . wc_rand_hash();
        $consumer_secret = 'cs_' . wc_rand_hash();

        $result = $wpdb->insert(
            $table_name,
            [
                'user_id'         => $vendor_id,
                'description'     => __('ShipStation Integration (WCFM V2)', 'wc-frontend-manager-ultimate'),
                'permissions'     => 'read_write',
                'consumer_key'    => wc_api_hash($consumer_key),
                'consumer_secret' => $consumer_secret,
                'truncated_key'   => substr($consumer_key, -7),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (false === $result || $wpdb->insert_id <= 0) {
            wp_send_json_error(['message' => __('Failed to insert API key into database.', 'wc-frontend-manager-ultimate')]);
        }

        update_user_meta($vendor_id, '_wcfm_shipstation_api_key_id', $wpdb->insert_id);

        wp_send_json_success([
            'consumer_key'    => $consumer_key,
            'consumer_secret' => $consumer_secret,
        ]);
    }

    /**
     * Bootstrap REST routes used by ShipStation's modern store connection flow.
     */
    public function load_rest_api() {
        global $WCFMu;

        include_once $WCFMu->plugin_path . 'includes/shipstation_v2/functions.php';
        include_once $WCFMu->plugin_path . 'includes/shipstation_v2/class-wcfmu-shipstation-rest-api-loader.php';

        $rest_api_loader = new WCFMu_ShipStation_v2_REST_API_Loader();
        $rest_api_loader->init();
    }
}
