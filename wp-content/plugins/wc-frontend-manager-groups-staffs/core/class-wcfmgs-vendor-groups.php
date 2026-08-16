<?php

/**
 * WCFM Groups & Staffs Vendor Groups Class
 *
 * @version		1.0.0
 * @package		wcfmgs/core
 * @author 		WC Lovers
 */
class WCFMgs_Vendor_Groups {
	
 	public function __construct() {
 		
 		// WCFM Shop Managrs End Points
 		add_filter( 'wcfm_query_vars', array( &$this, 'wcfmgs_vg_wcfm_query_vars' ), 90 );
		add_filter( 'wcfm_endpoint_title', array( &$this, 'wcfmgs_vg_endpoint_title' ), 90, 2 );
		add_action( 'init', array( &$this, 'wcfmgs_vg_init' ), 90 );
		
		// WCFM Appointments Endpoint Edit
		add_filter( 'wcfm_endpoints_slug', array( $this, 'wcfmgs_vg_endpoints_slug' ) );
		
		// WCFM Menu Filter
		add_filter( 'wcfm_menus', array( &$this, 'wcfmgs_vg_menus' ), 200 );
		add_filter( 'wcfm_menu_dependancy_map', array( &$this, 'wcfmgs_vg_menu_dependancy_map' ) );
		
		// Group Delete
		add_action( 'wp_ajax_delete_wcfm_group', array( &$this, 'delete_wcfm_group' ) );

		//duplicate group
		add_action( 'wp_ajax_wcfmgs_duplicate_group', array( &$this, 'duplicate_wcfm_group' ) );
		
 	}
 	
 	/**
   * WCFM Groups Query Var
   */
  function wcfmgs_vg_wcfm_query_vars( $query_vars ) {
  	$wcfm_modified_endpoints = (array) get_option( 'wcfm_endpoints' );
  	
		$query_groups_vars = array(
			'wcfm-groups'          => ! empty( $wcfm_modified_endpoints['wcfm-groups'] ) ? $wcfm_modified_endpoints['wcfm-groups'] : 'groups',
			'wcfm-groups-manage'   => ! empty( $wcfm_modified_endpoints['wcfm-groups-manage'] ) ? $wcfm_modified_endpoints['wcfm-groups-manage'] : 'groups-manage',
		);
		
		$query_vars = array_merge( $query_vars, $query_groups_vars );
		
		return $query_vars;
  }
  
  /**
   * WCFM Groups End Point Title
   */
  function wcfmgs_vg_endpoint_title( $title, $endpoint ) {
  	
  	switch ( $endpoint ) {
			case 'wcfm-groups' :
				$title = __( 'Vendor Groups', 'wc-frontend-manager-groups-staffs' );
			break;
			case 'wcfm-groups-manage' :
				$title = __( 'Vendor Groups Manage', 'wc-frontend-manager-groups-staffs' );
			break;
  	}
  	
  	return $title;
  }
  
  /**
   * WCFM Groups Endpoint Intialize
   */
  function wcfmgs_vg_init() {
  	global $WCFM_Query;
	
		// Intialize WCFM End points
		$WCFM_Query->init_query_vars();
		$WCFM_Query->add_endpoints();
		
		if( !get_option( 'wcfm_updated_end_point_wcfmgs_groups' ) ) {
			// Flush rules after endpoint update
			flush_rewrite_rules();
			update_option( 'wcfm_updated_end_point_wcfmgs_groups', 1 );
		}
  }
  
  /**
	 * WCFM Groups Endpoiint Edit
	 */
	function wcfmgs_vg_endpoints_slug( $endpoints ) {
		
		$wcfmgs_groups_endpoints = array(
													'wcfm-groups'           => 'groups',
													'wcfm-groups-manage'    => 'groups-manage',
													);
		
		$endpoints = array_merge( $endpoints, $wcfmgs_groups_endpoints );
		
		return $endpoints;
	}
  
  /**
   * WCFM Groups Menu
   */
  function wcfmgs_vg_menus( $menus ) {
  	global $WCFM;
  	
		$menus = array_slice($menus, 0, 5, true) +
												array( 'wcfm-groups' => array(   'label'  => __( 'Groups', 'wc-frontend-manager-groups-staffs'),
																										 'url'     => get_wcfm_groups_dashboard_url(),
																										 'icon'    => 'users',
																										 'has_new'    => 'yes',
																										 'new_class'  => 'wcfm_sub_menu_items_vendor_groups_manage',
																										 'new_url'    => get_wcfm_groups_manage_url(),
																										 'priority'   => 60
																										) )	 +
													array_slice($menus, 5, count($menus) - 5, true) ;
		
  	return $menus;
  }
  
  /**
   * WCFM Groups Menu Dependency
   */
  function wcfmgs_vg_menu_dependancy_map( $menu_dependency_mapping ) {
  	$menu_dependency_mapping['wcfm-groups-manage'] = 'wcfm-groups';
  	return $menu_dependency_mapping;
  }
  
  /**
   * Handle Group Delete
   */
  public function delete_wcfm_group() {
  	global $WCFM, $WCFMu;
  	
  	$groupid = $_POST['groupid'];
		
		if( $groupid ) {
			// Remove Group from existing Vendors
			$is_marketplace = wcfm_is_marketplace();
			if( $is_marketplace ) {
				$old_group_vendors = (array) get_post_meta( $groupid, '_group_vendors', true );
				if( !empty( $old_group_vendors ) ) {
					foreach( $old_group_vendors as $old_group_vendor ) {
						$wcfm_vendor_groups = (array) get_user_meta( $old_group_vendor, '_wcfm_vendor_group', true );
						if( !empty( $wcfm_vendor_groups ) ) {
							if( ( $key = array_search( $groupid, $wcfm_vendor_groups ) ) !== false ) {
								unset( $wcfm_vendor_groups[$key] );
							}
							update_user_meta( $old_group_vendor, '_wcfm_vendor_group', $wcfm_vendor_groups );
							update_user_meta( $old_group_vendor, '_wcfm_vendor_group_list', implode( ",", array_unique( $wcfm_vendor_groups ) ) );
						}
					}
				}
			}
			
			// Remove Group from existing Managers
			$old_group_managers = (array) get_post_meta( $groupid, '_group_managers', true );
			if( !empty( $old_group_managers ) ) {
				foreach( $old_group_managers as $old_group_manager ) {
					$wcfm_vendor_groups = (array) get_user_meta( $old_group_manager, '_wcfm_vendor_group', true );
					if( !empty( $wcfm_vendor_groups ) ) {
						if( ( $key = array_search( $groupid, $wcfm_vendor_groups ) ) !== false ) {
							unset( $wcfm_vendor_groups[$key] );
						}
						update_user_meta( $old_group_manager, '_wcfm_vendor_group', $wcfm_vendor_groups );
						update_user_meta( $old_group_manager, '_wcfm_vendor_group_list', implode( ",", array_unique( $wcfm_vendor_groups ) ) );
					}
				}
			}
					
			if(wp_delete_post($groupid)) {
				echo 'success';
				die;
			}
			die;
		}
  }

  public function duplicate_wcfm_group(){
	global $wpdb;
	
    if ( empty( $_POST['groupid'] ) ) {
        echo '{"status": false, "message": "' .  __( 'No group to copy' ) . '"}';
    }
    $group_id = isset( $_POST['groupid'] ) ? absint( $_POST['groupid'] ) : '';
    $post = get_post( $group_id );
    
    if (isset( $post ) && $post != null) {
    	
        $args = array(
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => $post->post_author,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_name'      => $post->post_name,
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_status'    => 'draft',
            'post_title'     => $post->post_title . " (Copy)",
            'post_type'      => $post->post_type,
            'to_ping'        => $post->to_ping,
            'menu_order'     => $post->menu_order
        );

        $new_post_id = wp_insert_post( $args );
    	
        $post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$group_id");
        if (count($post_meta_infos)!=0) {
        
            $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
            foreach ($post_meta_infos as $meta_info) {
                $meta_key = $meta_info->meta_key;
                if( $meta_key == '_wp_old_slug' ) continue;
                $meta_value = addslashes($meta_info->meta_value);
                $sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
            }
            $sql_query.= implode(" UNION ALL ", $sql_query_sel);
            
            $wpdb->query($sql_query);
        }

        echo '{"status": true, "redirect": "' . get_wcfm_groups_manage_url( $new_post_id ) . '", "id": "' . $new_post_id . '"}';
    }
    die;
	}
}