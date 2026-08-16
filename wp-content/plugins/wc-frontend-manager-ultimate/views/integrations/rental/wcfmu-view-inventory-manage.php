<?php
/**
 * WCFM plugin view
 *
 * WCFM Inventory Manage view
 * This template can be overridden by copying it to yourtheme/wcfm/integrations/rental/
 *
 * @author 		WC Lovers
 * @package		wcfm/views/integrations/rental
 * @version   	1.0.0
 */
 
global $wp, $wpdb, $WCFM;

if (apply_filters('wcfm_is_pref_restriction_check', true)) {
	$wcfm_is_allow_manage_rnb_inventory = apply_filters('wcfm_is_allow_manage_rnb_inventory', true);
	if (!$wcfm_is_allow_manage_rnb_inventory) {
		wcfm_restriction_message_show("RnB Inventory Manage");
		return;
	}
}

if (isset($wp->query_vars['wcfm-rnb-inventory-manage'])) {
	if (empty($wp->query_vars['wcfm-rnb-inventory-manage'])) {
		if (!apply_filters('wcfm_is_allow_add_rnb_inventory', true)) {
			wcfm_restriction_message_show("Add RnB Inventory");
			return;
		}

		if (!apply_filters('wcfm_is_allow_space_limit', true)) {
			wcfm_restriction_message_show("Space Limit Reached");
			return;
		}
	} else {
		$rnb_inventory = get_post($wp->query_vars['wcfm-rnb-inventory-manage']);

		if ($rnb_inventory->post_status == 'publish') {
			if (!apply_filters('wcfm_is_allow_edit_products', true)) {
				wcfm_restriction_message_show("Edit RnB Inventory");
				return;
			}
		}

		if (!apply_filters('wcfm_is_allow_edit_specific_products', true, $rnb_inventory->ID)) {
			wcfm_restriction_message_show("Edit RnB Inventory");
			return;
		}

		if (wcfm_is_vendor()) {
			$is_rnb_inventory_from_vendor = (absint($rnb_inventory->post_author) === get_current_user_id());

			if (!$is_rnb_inventory_from_vendor) {
				if (apply_filters('wcfm_is_show_rnb_inventory_restrict_message', true, $rnb_inventory->ID)) {
					wcfm_restriction_message_show("Restricted Inventory");
				} else {
					echo apply_filters('wcfm_show_custom_rnb_inventory_restrict_message', '', $rnb_inventory->ID);
				}
				return;
			}
		}
	}
}

$inventory_id = 0;
$post_type = apply_filters( 'wcfm_default_rnb_inventory_type', 'inventory' );

$featured_img = '';
$categories = array();
$attributes = array();
$default_attributes = '';
$attributes_select_type = array();

$currency = get_woocommerce_currency_symbol();
$title = '';
$quantity = '';
$distance_unit_type = '';
$perkilo_price = '';
$pricing_type = '';
$general_price = '';

$days = ['friday', 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
$daily_pricing = '';

$months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
$monthly_pricing = '';

$days_range = '';

$hourly_pricing_type = '';
$hourly_price = '';
$hourly_ranges = '';

$price_type_options = [
	'general_pricing' => __('General Pricing', 'redq-rental'),
	'daily_pricing'   => __('Daily Pricing', 'redq-rental'),
	'monthly_pricing' => __('Monthly Pricing', 'redq-rental'),
	'days_range'      => __('Days Range Pricing', 'redq-rental'),
	'flat_hours'      => __('Flat Hour Pricing', 'redq-rental'),
];

if( isset( $wp->query_vars['wcfm-rnb-inventory-manage'] ) && !empty( $wp->query_vars['wcfm-rnb-inventory-manage'] ) ) {
	
	$inventory_id = absint($wp->query_vars['wcfm-rnb-inventory-manage']);
	$rnb_inventory = get_post($inventory_id);

	if( 'inventory' !== get_post_type( $inventory_id ) ) {
		wcfm_restriction_message_show( "Invalid Inventory" );
		return;
	}
	
	$featured_img = get_post_thumbnail_id($inventory_id);

	$title =  $rnb_inventory->post_title;

	$quantity 			= get_post_meta($inventory_id, 'quantity', true);
	$distance_unit_type	= get_post_meta($inventory_id, 'distance_unit_type', true);
	$perkilo_price 		= get_post_meta($inventory_id, 'perkilo_price', true);
	$pricing_type 		= get_post_meta($inventory_id, 'pricing_type', true);
	$general_price 		= get_post_meta($inventory_id, 'general_price', true);

	$daily_pricing		= get_post_meta($inventory_id, 'redq_daily_pricing', true);
	$monthly_pricing 	= get_post_meta($inventory_id, 'redq_monthly_pricing', true);
	$days_range 		= get_post_meta($inventory_id, 'redq_day_ranges_cost', true);

	$hourly_pricing_type 	= get_post_meta($inventory_id, 'hourly_pricing_type', true);
	$hourly_price 			= get_post_meta($inventory_id, 'hourly_price', true);

	$hourly_ranges 		= get_post_meta($inventory_id, 'redq_hourly_ranges_cost', true);
}

$daily_pricing		= $daily_pricing ? $daily_pricing : [];
$monthly_pricing 	= $monthly_pricing ? $monthly_pricing : [];
$days_range 		= $days_range ? $days_range : [];
$hourly_ranges 		= $hourly_ranges ? $hourly_ranges : [];

$attributes_min = $pricing_type === 'flat_hours' ? array('step' => 1, 'min' => 1,) : array('step' => 1, 'min' => 1, 'max' => 24);
$attributes_max = $pricing_type === 'flat_hours' ? array('step' => 1, 'min' => 1,) : array('step' => 1, 'min' => 1);

$current_user_id = apply_filters( 'wcfm_current_vendor_id', get_current_user_id() );

$catlimit = apply_filters( 'wcfm_catlimit', -1 );

$taxonomy_args = rnb_get_inventory_taxonomies();

?>

<div class="collapse wcfm-collapse" id="wcfm_rnb_inventory_manage">
  <div class="wcfm-page-headig">
		<span class="wcfmfa fa-warehouse"></span>
		<span class="wcfm-page-heading-text"><?php _e( 'Manage RnB Inventory', 'wc-frontend-manager-ultimate' ); ?></span>
		<?php do_action( 'wcfm_page_heading' ); ?>
	</div>
	<div class="wcfm-collapse-content">
		<div id="wcfm_page_load"></div>
		<?php do_action( 'before_wcfm_rnb_inventory_simple' ); ?>
		
		<div class="wcfm-container wcfm-top-element-container">
			<?php do_action( 'before_wcfm_rnb_inventory_manage_title' ); ?>
			<h2><?php echo $inventory_id ? __('Edit RnB Inventory', 'wc-frontend-manager-ultimate' ) : __('Add RnB Inventory', 'wc-frontend-manager-ultimate' ); ?></h2>
			<?php do_action( 'after_wcfm_rnb_inventory_manage_title' ); ?>
			
			<?php
			if( $inventory_id ) {
				?>
				<span class="inventory-status inventory-status-<?php echo $rnb_inventory->post_status; ?>"><?php if( $rnb_inventory->post_status == 'publish' ) { _e( 'Published', 'wc-frontend-manager-ultimate' ); } else { _e( ucfirst( $rnb_inventory->post_status ), 'wc-frontend-manager-ultimate' ); } ?></span>
				<?php
			}
			
			do_action( 'before_wcfm_rnb_inventory_manage_action' );
			
			if( $allow_wp_admin_view = apply_filters( 'wcfm_allow_wp_admin_view', true ) ) {
				if( $inventory_id ) {
					?>
					<a target="_blank" class="wcfm_wp_admin_view text_tip" href="<?php echo admin_url('post.php?post='.$inventory_id.'&action=edit'); ?>" data-tip="<?php _e( 'WP Admin View', 'wc-frontend-manager-ultimate' ); ?>"><span class="fab fa-wordpress fa-wordpress-simple"></span></a>
					<?php
				} else {
					?>
					<a target="_blank" class="wcfm_wp_admin_view text_tip" href="<?php echo admin_url('post-new.php?post_type=inventory'); ?>" data-tip="<?php _e( 'WP Admin View', 'wc-frontend-manager-ultimate' ); ?>"><span class="fab fa-wordpress fa-wordpress-simple"></span></a>
					<?php
				}
			}
			
			if( $has_new = apply_filters( 'wcfm_add_new_rnb_inventory_sub_menu', true ) ) {
				echo '<a id="add_new_rnb_inventory_dashboard" class="add_new_wcfm_ele_dashboard text_tip" href="'.get_wcfm_edit_rnb_inventory_url().'" data-tip="' . __('Add New Inventory', 'wc-frontend-manager-ultimate') . '"><span class="wcfmfa fa-warehouse"></span><span class="text">' . __( 'Add New', 'wc-frontend-manager-ultimate') . '</span></a>';
			}
			
			do_action( 'after_wcfm_rnb_inventory_manage_action' );
			?>
			<div class="wcfm-clearfix"></div>
		</div>
		<div class="wcfm-clearfix"></div><br />
		
		<form id="wcfm_rnb_inventory_manage_form" class="wcfm">
		
			<?php do_action( 'begin_wcfm_rnb_inventory_manage_form' ); ?>
			
			<!-- collapsible -->
			<div class="wcfm-container wcfm-tabWrap">
				<div id="wcfm_rnb_inventory_manage_form_general_expander" class="wcfm-content">
				  <div class="wcfm_rnb_inventory_manager_general_fields">
				    <?php do_action( 'wcfm_rnb_inventory_manager_left_panel_before', $inventory_id ); ?>
				    
						<?php
							$general_fields = [
								"title" => array( 
									'placeholder' => __('Inventory Title', 'wc-frontend-manager') , 
									'type' => 'text', 
									'class' => 'wcfm-text wcfm_ele wcfm_product_title wcfm_full_ele', 
									'value' => $title,
								),
								'inventory_id' => array(
									'type' => 'hidden',
									'value' => $inventory_id
								)
							];

							$general_fields += array(
								"quantity"	=> array(
									'type' 				=> 'number', 
									'label' 			=> __('Set Quantity', 'redq-rental'), 
									'placeholder' 		=> __('Add inventory quantity', 'redq-rental'), 
									'label_class' 		=> 'wcfm_title', 
									'class' 			=> 'wcfm-text', 
									'hints' 			=> sprintf(__('Minimum 1 is required for each invenotry to work with.', 'redq-rental')),
									'attributes'		=> array( 
										'step' 	=> '1',
										'min'	=> '1'
									),
									'custom_attributes' => array( 'required' => 1 ),
									'value'				=> $quantity,
								),
								"distance_unit_type"	=> array(
									'type' 				=> 'select', 
									'label' 			=> __('Distance Unit', 'redq-rental'), 
									'placeholder' 		=> __('Set Location Distance Unit', 'redq-rental'),
									'label_class' 		=> 'wcfm_title', 
									'class' 			=> 'wcfm-select', 
									'hints' 			=> sprintf(__('If you select booking layout two then for location unit it will be applied', 'redq-rental')),
									'options'     		=> array(
										'kilometer'	=> __('Kilometer', 'redq-rental'),
										'mile'      => __('Mile', 'redq-rental'),
									),
									'value'				=> $distance_unit_type,
								),
								"perkilo_price"	=> array(
									'type' 				=> 'number', 
									'label' 			=> sprintf(__('Distance Unit Price ( %s )', 'redq-rental'), $currency),
									'placeholder' 		=> __('Per Distance Unit Price', 'redq-rental'),
									'label_class' 		=> 'wcfm_title', 
									'class' 			=> 'wcfm-text', 
									'hints' 			=> sprintf(__('If you select booking layout two then for location price it will be applied', 'redq-rental')),
									'attributes'		=> array( 
										'step' => '0.01',
                    					'min'  => '0'
									),
									'value'				=> $perkilo_price,
								),

								"configure_day_pricing_plans" => array(
									'type' 	=> 'html', 
									'value'	=> __('Configure Day Pricing Plans', 'redq-rental'),
									'class'	=> 'wcfm-input-block'
								),
								"pricing_type"	=> array(
									'type' 			=> 'select', 
									'label' 		=> __('Set Price Type', 'redq-rental'),
									'label_class' 	=> 'wcfm_title', 
									'class' 		=> 'wcfm-select', 
									'desc' 			=> sprintf(__('Choose a price type - this controls the <a href = "%s">Details</a>.', 'redq-rental'), 'https: //rnb-doc.vercel.app/price-calculation'),
									'desc_class' 	=> 'wcfm_full_ele description wcfm-description',
									'options'     	=> apply_filters('rnb_pricing_types', $price_type_options),
									'value'			=> $pricing_type,
								),

								"set_general_pricing_plan" => array(
									'type' 	=> 'html', 
									'value'	=> __('Set general pricing plan', 'redq-rental'),
									'class'	=> 'wcfm-input-block show_if_general_pricing pricing_type_dependency'
								),
								"general_price"	=> array(
									'type' 			=> 'number', 
									'label' 		=> sprintf(__('General Price ( %s )', 'redq-rental'), $currency),
									'placeholder' 	=> __('Enter price here', 'redq-rental'),
									'label_class' 	=> 'wcfm_title show_if_general_pricing pricing_type_dependency', 
									'class' 		=> 'wcfm-text show_if_general_pricing pricing_type_dependency', 
									'attributes'	=> array( 
										'step' => '0.01',
                    					'min'  => '0'
									),
									'value'			=> $general_price,
								),
							);

							$general_fields["set_daily_pricing_plan"] = array(
								'type' 	=> 'html', 
								'value'	=> __('Set daily pricing Plan', 'redq-rental'),
								'class'	=> 'wcfm-input-block show_if_daily_pricing pricing_type_dependency'
							);

							foreach ($days as $key => $day) {
								$general_fields[$day . '_price'] = array(
									'type' 				=> 'number', 
									'label' 			=> __(ucfirst($day) . ' ( ' . $currency . ' )', 'redq-rental'),
									'placeholder' 		=> __('Enter price here', 'redq-rental'),
									'label_class' 		=> 'wcfm_title show_if_daily_pricing pricing_type_dependency', 
									'class' 			=> 'wcfm-text show_if_daily_pricing pricing_type_dependency', 
									'attributes'		=> array( 
										'step' => '0.01',
										'min'  => '0'
									),
									'value'				=> isset($daily_pricing[$days[$key]]) ? $daily_pricing[$days[$key]] : 0,
								);
							}

							$general_fields["set_monthly_pricing_plan"] = array(
								'type' 	=> 'html', 
								'value'	=> __('Set monthly pricing plan', 'redq-rental'),
								'class'	=> 'wcfm-input-block show_if_monthly_pricing pricing_type_dependency'
							);

							foreach ($months as $key => $month) {
								$general_fields[$month . '_price'] = array(
									'type' 				=> 'number', 
									'label' 			=> __(ucfirst($month) . ' ( ' . $currency . ' )', 'redq-rental'),
									'placeholder' 		=> __('Enter price here', 'redq-rental'),
									'label_class' 		=> 'wcfm_title show_if_monthly_pricing pricing_type_dependency', 
									'class' 			=> 'wcfm-text show_if_monthly_pricing pricing_type_dependency', 
									'attributes'		=> array( 
										'step' => '0.01',
										'min'  => '0'
									),
									'value'				=> isset($monthly_pricing[$months[$key]]) ? $monthly_pricing[$months[$key]] : 0,
								);
							}

							$general_fields["set_days_range_pricing_plan"] = array(
								'type' 	=> 'html', 
								'value'	=> __('Set day ranges pricing plans', 'redq-rental'),
								'class'	=> 'wcfm-input-block show_if_days_range pricing_type_dependency'
							);

							$general_fields['days_range_field'] = array(
								'type' 		=> 'multiinput', 
								'class' 	=> 'wcfm-text show_if_days_range pricing_type_dependency', 
								'value'		=> $days_range,
								'options'	=> array(
									'min_days' => array(
										'type' 			=> 'number', 
										'label' 		=> __('Min Days', 'redq-rental'),
										'placeholder' 	=> __('Days', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text', 
										'attributes'	=> array( 
											'step' => '1',
											'min'  => '1'
										),
										'custom_attributes' => array( 
											'required' => 1 
										),
									),
									'max_days' => array(
										'type' 			=> 'number', 
										'label' 		=> __('Max Days', 'redq-rental'),
										'placeholder' 	=> __('days', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text', 
										'attributes'	=> array( 
											'step' => '1',
											'min'  => '1'
										),
										'custom_attributes' => array( 
											'required' => 1 
										),
									),
									'range_cost' => array(
										'type' 			=> 'number', 
										'label' 		=> __('Days Range Cost ( ' . $currency . ' )', 'redq-rental'),
										'placeholder' 	=> __('Cost', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text', 
										'attributes'	=> array( 
											'step' => '0.01',
											'min'  => '0'
										),
										'custom_attributes' => array( 
											'required' => 1 
										),
									),
									"cost_applicable"	=> array(
										'type' 			=> 'select', 
										'label' 		=> __('Applicable', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-select', 
										'desc' 			=> sprintf(__('This will be applicable during booking cost calculation', 'redq-rental'), 'redq-rental'),
										'desc_class' 	=> 'wcfm_full_ele description wcfm-description',
										'options'     	=> array(
											''        => __('Select Type', 'redq-rental'),
											'per_day' => __('Per Day', 'redq-rental'),
											'fixed'   => __('Fixed', 'redq-rental'),
										),
									),
								),
							);

							$general_fields["configure_hourly_pricing_plans"] = array(
								'type' 	=> 'html', 
								'value'	=> __('Configure Hourly Pricing Plans', 'redq-rental'),
								'class'	=> 'wcfm-input-block'
							);

							$general_fields["hourly_pricing_type"]	= array(
								'type' 			=> 'select', 
								'label' 		=> __('Set Hourly Price Type', 'redq-rental'),
								'label_class' 	=> 'wcfm_title', 
								'class' 		=> 'wcfm-select', 
								'desc' 			=> sprintf(__('Choose a price type - this controls the <a href = "%s">Details</a>.', 'redq-rental'), 'https: //rnb-doc.vercel.app/price-calculation'),
								'desc_class' 	=> 'wcfm_full_ele description wcfm-description',
								'options'     	=> array(
									'hourly_general' => __('General Hourly Pricing', 'redq-rental'),
									'hourly_range'   => __('Hourly Range Pricing', 'redq-rental'),
								),
								'value'			=> $hourly_pricing_type,
							);

							$general_fields["hourly_price"]	= array(
								'type' 			=> 'number', 
								'label' 		=> sprintf(__('Hourly Price ( %s )', 'redq-rental'), $currency),
								'placeholder' 	=> __('Enter price here', 'redq-rental'),
								'label_class' 	=> 'wcfm_title show_if_hourly_general hourly_pricing_type_dependency', 
								'class' 		=> 'wcfm-text show_if_hourly_general hourly_pricing_type_dependency', 
								'hints'       	=> sprintf(__(
									'Hourly price will be applicable if booking or rental days min 1day',
									'redq-rental'
								)),
								'attributes'	=> array( 
									'step' => '0.01',
									'min'  => '0'
								),
								'value'			=> $hourly_price,
							);

							$general_fields["set_hourly_range_pricing_plan"] = array(
								'type' 	=> 'html', 
								'value'	=> __('Set hourly ranges pricing plans', 'redq-rental'),
								'class'	=> 'wcfm-input-block show_if_hourly_range hourly_pricing_type_dependency'
							);

							$general_fields['hourly_range_field'] = array(
								'type' 		=> 'multiinput', 
								'class' 	=> 'wcfm-text show_if_hourly_range hourly_pricing_type_dependency', 
								'value'		=> $hourly_ranges,
								'options'	=> array(
									'min_hours' => array(
										'type' 			=> 'number', 
										'label' 		=> __('Min Hours', 'redq-rental'),
										'placeholder' 	=> __('Hours', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text', 
										'attributes'	=> $attributes_min,
										'custom_attributes' => array( 
											'required' => 1 
										),
									),
									'max_hours' => array(
										'type' 			=> 'number', 
										'label' 		=> __('Max Hours', 'redq-rental'),
										'placeholder' 	=> __('Hours', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text', 
										'attributes'	=> $attributes_max,
										'custom_attributes' => array( 
											'required' => 1 
										),
									),
									'range_cost' => array(
										'type' 			=> 'number', 
										'label' 		=> __('Hourly Range Cost ( ' . $currency . ' )', 'redq-rental'),
										'placeholder' 	=> __('Cost', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text', 
										'attributes'	=> array( 
											'step' => '0.01',
											'min'  => '0'
										),
										'custom_attributes' => array( 
											'required' => 1 
										),
									),
									"cost_applicable"	=> array(
										'type' 			=> 'select', 
										'label' 		=> __('Applicable', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-select', 
										'desc' 			=> sprintf(__('This will be applicable during booking cost calculation', 'redq-rental'), 'redq-rental'),
										'desc_class' 	=> 'wcfm_full_ele description wcfm-description',
										'options'     	=> array(
											''         => __('Select Type', 'redq-rental'),
											'per_hour' => __('Per Hour', 'redq-rental'),
											'fixed'    => __('Fixed', 'redq-rental'),
										),
									),
								),
							);

							$WCFM->wcfm_fields->wcfm_generate_form_field(apply_filters('wcfm_rnb_inventory_manage_fields_general', $general_fields, $inventory_id, $post_type));


							
							$availabilities = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT * FROM {$wpdb->prefix}rnb_availability WHERE inventory_id = %d AND block_by = %s",
									$inventory_id,
									'CUSTOM'
								),
								ARRAY_A
							);

							$availability_field_values = [];

							foreach ( $availabilities as $availability ) {
								$availability_field_values[] = [
									'block_by'			=> $availability['block_by'],
									'pickup_datetime'	=> date("Y-m-d H:i", strtotime($availability['pickup_datetime'])), // F j, Y g:i a
									'return_datetime'	=> date("Y-m-d H:i", strtotime($availability['return_datetime'])), // F j, Y g:i a
									'row_id'			=> $availability['id'],
								];
							}

							$availability_fields["product_date_availabilities"] = array(
								'type' 	=> 'html', 
								'value'	=> __('Product Date Availabilities', 'redq-rental'),
								'class'	=> 'wcfm-input-block'
							);

							$availability_fields['product_date_availabilities_fields'] = array(
								'type' 		=> 'multiinput', 
								'class' 	=> 'wcfm-text wcfm_non_sortable', 
								'value'		=> $availability_field_values,
								'options'	=> array(
									"block_by"	=> array(
										'type' 			=> 'select', 
										'label' 		=> __('Block type', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-select', 
										'options'     	=> array(
											'CUSTOM'	=> __('Custom Block', 'redq-rental'),
										),
									),
									'pickup_datetime' => array(
										'type' 			=> 'text', 
										'label' 		=> __('Pickup Datetime', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text wcfm_datetimepicker', 
										'custom_attributes' => array( 
											'autocomplete' 	=> 'off',
											'date_format'	=> 'yy-mm-dd'
										),
									),
									'return_datetime' => array(
										'type' 			=> 'text', 
										'label' 		=> __('Dropoff Datetime', 'redq-rental'),
										'label_class' 	=> 'wcfm_title', 
										'class' 		=> 'wcfm-text wcfm_datetimepicker', 
										'custom_attributes' => array( 
											'autocomplete' => 'off',
											'date_format'	=> 'yy-mm-dd'
										),
									),
									'row_id' => array(
										'type' 			=> 'hidden',
									),
								),
							);
							
							$WCFM->wcfm_fields->wcfm_generate_form_field(apply_filters('wcfm_rnb_inventory_manage_fields_availability', $availability_fields, $inventory_id, $post_type));		
							
							
						?>
						<div class="wcfm_clearfix"></div>
					</div>
					<div class="wcfm_rnb_inventory_manager_gallery_fields">
						<div class="wcfm_rnb_inventory_manager_cats_checklist_fields">
							<p class="wcfm_title wcfm_full_ele"><strong><?php _e( 'Attached Products', 'wc-frontend-manager-ultimate' ); ?></strong></p><label class="screen-reader-text" for="_attached_products"></label>
							<ul id="_attached_products" class="rnb_inventory_taxonomy_checklist">
								<?php
								$response = rnb_get_products_by_inventory($inventory_id);
								?>

								<?php if (empty($response['success'])) : ?>
									<p><?php echo esc_attr($response['message']); ?></p>
								<?php endif; ?>

								<?php if (!empty($response['success'])) : ?>
									<?php $product_ids = $response['product_ids']; ?>
									<div class="product-list">
										<ul class="list">
											<?php foreach ($product_ids as $product_id) : ?>
												<li>
													<a href="<?php echo esc_url(get_wcfm_edit_product_url($product_id)); ?>"><?php echo get_the_title($product_id); ?> </a>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>
							</ul>
						</div>
						<?php if( apply_filters( 'wcfm_is_category_checklist', true ) ) { ?>
							<?php 
							if( apply_filters( 'wcfm_is_allow_category', true ) && apply_filters( 'wcfm_is_allow_pm_category', true ) ) {
								if( apply_filters( 'wcfm_is_allow_rnb_inventory_category', true ) ) {
									?>
									<div class="wcfm_clearfix"></div>

									<?php foreach( $taxonomy_args as $taxonomy ) { ?>
										<div class="wcfm_rnb_inventory_manager_cats_checklist_fields">
											<p class="wcfm_title wcfm_full_ele"><strong><?php echo $taxonomy['label'] ?></strong></p><label class="screen-reader-text" for="<?php echo $taxonomy['taxonomy']; ?>"><?php echo apply_filters( 'wcfm_taxonomy_custom_label', __( 'Categories', 'wc-frontend-manager-ultimate' ), 'product_cat' ); ?></label>
											<ul id="<?php echo $taxonomy['taxonomy']; ?>" class="rnb_inventory_taxonomy_checklist rnb_inventory_taxonomy_checklist_<?php echo $taxonomy['taxonomy']; ?> wcfm_ele simple variable external grouped booking" data-catlimit="<?php echo $catlimit; ?>">
												<?php
													$terms	= get_terms( [
														'taxonomy' 		=> $taxonomy['taxonomy'],
														'orderby' 		=> 'name',
														'hide_empty'	=> false,
														'childless'		=> true
													] );
													$inventory_terms 	= get_the_terms( $inventory_id, $taxonomy['taxonomy'] );
													$selected_terms 	= !empty($inventory_terms) ? wp_list_pluck($inventory_terms, 'term_id') : [];

													if ( $terms ) {
														$WCFM->library->generateTaxonomyHTML( $taxonomy['taxonomy'], $terms, $selected_terms, '', true, true );
													}
												?>
											</ul>
										</div>
										<div class="wcfm_clearfix"></div>
										<?php if( apply_filters( 'wcfm_is_allow_add_taxonomy', true ) && apply_filters( 'wcfm_is_allow_rnb_inventory_add_taxonomy', true, $taxonomy['taxonomy'] ) && apply_filters( 'wcfm_is_allow_add_'.$taxonomy['taxonomy'], true ) ) {
											?>
											<div class="wcfm_add_new_category_box wcfm_add_new_taxonomy_box">
												<p class="description wcfm_full_ele wcfm_side_add_new_category wcfm_add_new_category wcfm_add_new_taxonomy">+<?php echo __( 'Add new', 'wc-frontend-manager-ultimate' ) . ' ' . apply_filters( 'wcfm_taxonomy_custom_label', __( $taxonomy['label'], 'wc-frontend-manager-ultimate' ), $taxonomy['taxonomy'] ); ?></p>
												<div class="wcfm_add_new_taxonomy_form wcfm_add_new_taxonomy_form_hide">
													<?php 
													$WCFM->wcfm_fields->wcfm_generate_form_field( array( "wcfm_new_".$taxonomy['taxonomy'] => array( 'placeholder' =>  apply_filters( 'wcfm_add_taxonomy_custom_label', __( $taxonomy['label'], 'wc-frontend-manager-ultimate' ), $taxonomy['taxonomy'] ) . ' ' . __( 'Name', 'wc-frontend-manager-ultimate' ), 'type' => 'text', 'class' => 'wcfm-text wcfm_new_tax_ele wcfm_full_ele' ) ) );
													if( apply_filters( 'wcfm_is_allow_add_new_'.$taxonomy['taxonomy'].'_parent', true ) ) {
														$args = apply_filters( 'wcfm_wp_dropdown_'.$taxonomy['taxonomy'].'_args', array(
															'show_option_all'    => '',
															'show_option_none'   => __( '-- Parent taxonomy --', 'wc-frontend-manager-ultimate' ),
															'option_none_value'  => '0',
															'hide_empty'         => 0,
															'hierarchical'       => 1,
															'name'               => 'wcfm_new_parent_'.$taxonomy['taxonomy'],
															'class'              => 'wcfm-select wcfm_new_parent_taxt_ele wcfm_full_ele',
															'taxonomy'           => $taxonomy['taxonomy'],
														), $taxonomy['taxonomy'] );
														wp_dropdown_categories( $args );
													}
													?>
													<button type="button" data-taxonomy="<?php echo $taxonomy['taxonomy']; ?>" class="button wcfm_add_category_bt wcfm_add_taxonomy_bt"><?php _e( 'Add', 'wc-frontend-manager-ultimate' ); ?></button>
													<div class="wcfm_clearfix"></div>
												</div>
											</div>
											<div class="wcfm_clearfix"></div>
											<?php
										} 
									} ?>

									<div class="wcfm_clearfix"></div>
								  	<?php
								}
							}
							?>
							
							<?php do_action( 'after_wcfm_rnb_inventory_manage_taxonomies', $inventory_id ); ?>
						<?php } ?>

						<?php
					  	if( $wcfm_is_allow_featured = apply_filters( 'wcfm_is_allow_featured', true ) ) {
							$WCFM->wcfm_fields->wcfm_generate_form_field(apply_filters('wcfm_rnb_inventory_manage_fields_images', array(
								"featured_img" => array(
									'type' => 'upload', 
									'class' => 'wcfm-product-feature-upload wcfm_ele simple variable external grouped booking', 
									'label_class' => 'wcfm_title', 
									'prwidth' => 250, 
									'value' => $featured_img
								),
							)));
						}
						?>
						
						<?php do_action( 'wcfm_rnb_inventory_manager_right_panel_after', $inventory_id ); ?>
					</div>
				</div>
			</div>
			<!-- end collapsible -->
			<div class="wcfm_clearfix"></div><br />
			
			<div id="wcfm_rnb_inventory_submit" class="wcfm_form_simple_submit_wrapper">
			  	<div class="wcfm-message" tabindex="-1"></div>
				<?php if( $inventory_id && ( $rnb_inventory->post_status == 'publish' ) ) { ?>
					<input type="submit" name="submit-data" value="<?php _e( 'Submit', 'wc-frontend-manager-ultimate' ); ?>" id="wcfm_rnb_inventory_submit_button" class="wcfm_submit_button" />
				<?php } else { ?>
					<?php if( apply_filters( 'wcfm_is_allow_rnb_inventory_limit', true ) && apply_filters( 'wcfm_is_allow_space_limit', true ) ) { ?>
						<input type="submit" name="submit-data" value="<?php _e( 'Submit', 'wc-frontend-manager-ultimate' ); ?>" id="wcfm_rnb_inventory_submit_button" class="wcfm_submit_button" />
					<?php } ?>
				<?php } ?>
				<?php if( apply_filters( 'wcfm_is_allow_draft_published_rnb_inventory', true ) && apply_filters( 'wcfm_is_allow_add_rnb_inventory', true ) ) { ?>
					<input type="submit" name="draft-data" value="<?php _e( 'Draft', 'wc-frontend-manager-ultimate' ); ?>" id="wcfm_rnb_inventory_draft_button" class="wcfm_submit_button" />
				<?php } ?>
				<input type="hidden" name="wcfm_nonce" value="<?php echo wp_create_nonce( 'wcfm_rnb_inventory_manage' ); ?>" />
			</div>
		</form>
		<?php
		do_action( 'after_wcfm_rnb_inventory_manage' );
		?>
	</div>
</div>