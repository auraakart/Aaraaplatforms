<?php

class WCFM_RnB_Inventory_Controller {
    public function __construct() {
        $this->processing();
    }

    public function processing() {
        global $WCFM;

        $length = sanitize_text_field( $_POST['length'] );
		$offset = sanitize_text_field( $_POST['start'] );

        $args = array(
            'post_type'      => 'inventory',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'post_status'    => 'any',
            'posts_per_page' => $length,
			'offset'         => $offset,
        );

        if( isset( $_POST['search'] ) && !empty( $_POST['search']['value'] )) {
			$args['s'] = sanitize_text_field($_POST['search']['value']);
		}

        if ( isset($_POST['status']) && !empty($_POST['status']) && ( $_POST['status'] != 'any' ) ) $args['post_status'] = sanitize_text_field( $_POST['status'] );
        if ( isset($_POST['vendor']) && !empty($_POST['vendor']) ) $args['author'] = absint( $_POST['vendor'] );

        if (wcfm_is_vendor()) {
            if ( 'wcfmmarketplace' === $WCFM->is_marketplace ) {
                $args['author'] = $WCFM->wcfm_marketplace->vendor_id;
            }
        }

        $inventories = new WP_Query( apply_filters( 'wcfm_inventory_args', $args ) );

        $order_count = $filtered_order_count = $inventories->found_posts;

        $taxonomy_args = rnb_get_inventory_taxonomies();

        foreach ($inventories->posts as $index => $inventory) {
            foreach ($taxonomy_args as $taxonomy_arg) {
                $taxonomy = $taxonomy_arg['taxonomy'];
                $inventories->posts[$index]->$taxonomy = wp_get_post_terms(
                    $inventory->ID, $taxonomy, 
                    array(
                        'order' => 'DESC', 
                        'orderby' => 'menu_order'
                    )
                );
            }
        }

        wp_reset_postdata();

        // Generate Orders JSON
        $datatable_json = [
            'draw'              => (int) wc_clean($_POST['draw']),
            'recordsTotal'      => (int) $order_count,
            'recordsFiltered'   => (int) $filtered_order_count,
            'data'              => []
        ];

        for ($i = 0; $i < count($inventories->posts); $i++) {
            // Title
            $datatable_json['data'][$i][] = !empty($inventories->posts[$i]->post_title) ? '<a class="wcfm_product_title" href="' . get_wcfm_edit_rnb_inventory_url($inventories->posts[$i]->ID) . '">' . $inventories->posts[$i]->post_title . '</a>' : '-';

            foreach ($taxonomy_args as $taxonomy_arg) {
                // Taxonomy
                $taxonomy_list = wp_list_pluck($inventories->posts[$i]->{$taxonomy_arg['taxonomy']}, 'name');
                $datatable_json['data'][$i][] = count($taxonomy_list) ? implode(', ', $taxonomy_list) : '-';
            }

            // Date
            $datatable_json['data'][$i][] = __(sprintf('Published <br/> %s', wp_date("Y/m/d \a\\t g:i a", get_post_timestamp($inventories->posts[$i]->post_date))), 'wc-frontend-manager-ultimate');

            // Shop
            if ( ! wcfm_is_vendor() ) {
                $vendor_name = '&ndash;';

                if ( wcfm_is_vendor( $inventories->posts[$i]->post_author ) ) {
                    $vendor_name = wcfm_get_vendor_store( $inventories->posts[$i]->post_author );
                }

                $datatable_json['data'][$i][] = $vendor_name;
            }

            // Actions
            $actions = [];

            if ((apply_filters('wcfm_is_allow_edit_rnb_inventory', true) && apply_filters('wcfm_is_allow_edit_specific_rnb_inventory', true, $inventories->posts[$i]->ID))) {
                $actions['edit'] = '<a class="wcfm-action-icon" href="' . get_wcfm_edit_rnb_inventory_url($inventories->posts[$i]->ID) . '"><span class="wcfmfa fa-edit text_tip" data-tip="' . esc_attr__('Edit', 'wc-frontend-manager-ultimate') . '"></span></a>';
            }

            if (apply_filters('wcfm_is_allow_delete_rnb_inventory', true) && apply_filters('wcfm_is_allow_delete_specific_rnb_inventory', true, $inventories->posts[$i]->ID)) {
                $actions['delete'] = '<a class="wcfm-action-icon wcfm_rnb_inventory_delete" href="#" data-inventory_id="' . $inventories->posts[$i]->ID . '"><span class="wcfmfa fa-trash-alt text_tip" data-tip="' . esc_attr__('Delete', 'wc-frontend-manager-ultimate') . '"></span></a>';
            }

            $datatable_json['data'][$i][] = implode('<br/>', apply_filters('wcfm_rnb_inventory_actions', $actions, $inventories->posts[$i]));
        }

        wp_send_json($datatable_json);
    }
}
