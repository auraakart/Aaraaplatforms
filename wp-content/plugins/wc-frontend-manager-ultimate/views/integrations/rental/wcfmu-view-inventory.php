<?php
global $WCFM, $wp_query;

$wcfm_is_allow_manage_rnb_inventory = apply_filters('wcfm_is_allow_manage_rnb_inventory', true);
if (!$wcfm_is_allow_manage_rnb_inventory) {
    wcfm_restriction_message_show("RnB Inventory");
    return;
}

$wcfmu_products_menus = apply_filters('wcfmu_products_menus', array(
    'any'       => __('All', 'wc-frontend-manager'),
    'publish'   => __('Published', 'wc-frontend-manager'),
    'draft'     => __('Draft', 'wc-frontend-manager'),
));

$product_status = !empty($_GET['status']) ? sanitize_text_field($_GET['status']) : 'any';
$vendor = !empty($_GET['vendor']) ? sanitize_text_field($_GET['vendor']) : '';

$current_user_id = apply_filters('wcfm_current_vendor_id', get_current_user_id());
if (!wcfm_is_vendor()) $current_user_id = 0;
$count_inventories = array();
$count_inventories['publish'] = wcfm_get_user_posts_count($current_user_id, 'inventory', 'publish');
$count_inventories['draft']   = wcfm_get_user_posts_count($current_user_id, 'inventory', 'draft');
$count_inventories['private'] = wcfm_get_user_posts_count($current_user_id, 'inventory', 'private');
$count_inventories = apply_filters('wcfmu_inventories_menus_count', $count_inventories, $current_user_id);
$count_inventories['any'] = 0;

foreach ($count_inventories as $count_inventory) {
    $count_inventories['any']  += $count_inventory;
}

$taxonomy_args = rnb_get_inventory_taxonomies();

?>

<div class="collapse wcfm-collapse" id="wcfm_products_listing">

    <div class="wcfm-page-headig">
        <span class="wcfmfa fa-warehouse"></span>
        <span class="wcfm-page-heading-text"><?php _e('Inventory', 'wc-frontend-manager'); ?></span>
        <?php do_action('wcfm_page_heading'); ?>
    </div>
    <div class="wcfm-collapse-content">
        <div id="wcfm_page_load"></div>
        <?php do_action('before_wcfm_rnb_inventory'); ?>

        <div class="wcfm-container wcfm-top-element-container">
            <ul class="wcfm_products_menus">
                <?php
                $is_first = true;
                foreach ($wcfmu_products_menus as $wcfmu_products_menu_key => $wcfmu_products_menu) {
                ?>
                    <li class="wcfm_products_menu_item">
                        <?php
                        if ($is_first) $is_first = false;
                        else echo " | ";
                        ?>
                        <a class="<?php echo ($wcfmu_products_menu_key == $product_status) ? 'active' : ''; ?>" href="<?php echo esc_url(get_wcfm_rnb_inventory_url($wcfmu_products_menu_key)); ?>"><?php echo esc_html($wcfmu_products_menu . ' (' . $count_inventories[$wcfmu_products_menu_key] . ')'); ?></a>
                    </li>
                <?php
                }
                ?>
            </ul>

            <?php
            if ($allow_wp_admin_view = apply_filters('wcfm_allow_wp_admin_view', true)) {
                ?>
                <a target="_blank" class="wcfm_wp_admin_view text_tip" href="<?php echo admin_url('edit.php?post_type=inventory'); ?>" data-tip="<?php _e('WP Admin View', 'wc-frontend-manager'); ?>"><span class="fab fa-wordpress fa-wordpress-simple"></span></a>
                <?php
            }

            if ($has_new = apply_filters('wcfm_add_new_product_sub_menu', true)) {
                echo '<a id="add_new_product_dashboard" class="add_new_wcfm_ele_dashboard text_tip" href="' . get_wcfm_edit_rnb_inventory_url() . '" data-tip="' . __('Add New Inventory', 'wc-frontend-manager') . '"><span class="wcfmfa fa-warehouse"></span><span class="text">' . __('Add New', 'wc-frontend-manager') . '</span></a>';
            }
            ?>

            <div class="wcfm-clearfix"></div>
        </div>
        <div class="wcfm-clearfix"></div><br />

        <div class="wcfm_products_filter_wrap wcfm_filters_wrap">
            <?php
            if (apply_filters('wcfm_is_inventory_vendor_filter', true)) {
                $is_marketplace = wcfm_is_marketplace();
                if ($is_marketplace) {
                    if (!wcfm_is_vendor()) {
                        $vendor_arr = array(); 
                        if ($vendor) $vendor_arr = array($vendor => wcfm_get_vendor_store_name($vendor));
                        $WCFM->wcfm_fields->wcfm_generate_form_field(array(
                            "dropdown_vendor"   => array(
                                'type'          => 'select', 
                                'options'       => $vendor_arr, 
                                'value'         => $vendor, 
                                'attributes'    => array(
                                    'style'     => 'width: 150px;'
                                )
                            )
                        ));
                    }
                }
            }
            ?>
        </div>

        <div class="wcfm-container">
            <div id="wcfm_products_listing_expander" class="wcfm-content">
                <table id="wcfm-rnb-inventory" class="display" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th style="max-width: 250px;"><?php _e('Name', 'wc-frontend-manager'); ?></th>
                            <?php foreach( $taxonomy_args as $taxonomy_arg ) {
                                ?><th><?php echo $taxonomy_arg['label']; ?></th><?php
                            } ?>
                            <th><?php _e('Date', 'wc-frontend-manager'); ?></th>
                            <?php if ( ! wcfm_is_vendor() ) { ?>
                                <th><?php echo apply_filters('wcfm_sold_by_label', '', __('Store', 'wc-frontend-manager')); ?></th>
                            <?php } ?>
                            <th><?php _e('Actions', 'wc-frontend-manager'); ?></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th style="max-width: 250px;"><?php _e('Name', 'wc-frontend-manager'); ?></th>
                            <?php foreach( $taxonomy_args as $taxonomy_arg ) {
                                ?><th><?php echo $taxonomy_arg['label']; ?></th><?php
                            } ?>
                            <th><?php _e('Date', 'wc-frontend-manager'); ?></th>
                            <?php if ( ! wcfm_is_vendor() ) { ?>
                                <th><?php echo apply_filters('wcfm_sold_by_label', '', __('Store', 'wc-frontend-manager')); ?></th>
                            <?php } ?>
                            <th><?php _e('Actions', 'wc-frontend-manager'); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <div class="wcfm-clearfix"></div>
            </div>
        </div>
        <?php
        do_action('after_wcfm_rnb_inventory');
        ?>
    </div>
</div>