<?php
/**
 * WCFMd plugin core
 *
 * WCfMd Delivery Time
 *
 * @author 		WC Lovers
 * @package 	wcfmmp/core
 * @version   1.2.8
 */
class WCFMd_Delivery_Time {
	public function __construct() {
		// Store Hours Default Settings
		add_action( 'wcfmd_delivery_settings_after', array( &$this, 'wcfm_delivery_time_global_settings' ), 17 );
		add_action( 'wcfm_settings_update', array( &$this, 'wcfm_delivery_time_global_settings_update' ), 17 );
		
		if( wcfm_is_vendor() && apply_filters( 'wcfm_is_allow_delivery_time', true ) ) {
			add_action( 'wcfm_vendor_settings_after_shipping', array( &$this, 'wcfm_delivery_time_vendor_settings' ), 5 );
		}
		
		// Vendor Details Page - Delivery Time Setting
		add_action( 'begin_wcfm_vendors_new_form', array( &$this, 'wcfm_delivery_time_vendor_settings' ), 12 );
		add_action( 'end_wcfm_vendors_manage_form', array( &$this, 'wcfm_delivery_time_vendor_settings' ), 12 );
		
		// Store Hours Setting Update
		add_action( 'wcfm_vendor_settings_update', array( &$this, 'wcfm_delivery_time_vendor_settings_update' ), 5, 2 );
		
		// Delivery Time Field at Checkout
		add_filter( 'woocommerce_checkout_fields', array( &$this, 'wcfmd_checkout_delivery_time_field' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( &$this, 'wcfmd_checkout_delivery_time_field_save' ), 50, 2 );
		add_action( 'wcfm_order_details_after_address', array( &$this, 'wcfmd_order_details_delivery_time_show' ), 60 );
		//add_action( 'woocommerce_admin_order_data_after_shipping_address', array( &$this, 'wcfmd_order_details_delivery_time_show' ), 60 );
		add_filter( 'wcfm_orderlist_shipping_address', array( &$this, 'wcfmd_order_list_delivery_time_show' ), 60, 2 );
		
		add_action( 'woocommerce_order_details_after_order_table', array( &$this, 'wcfmd_customer_order_delivery_time_show' ), 12, 5  );
		add_action( 'woocommerce_email_order_meta', array( &$this, 'wcfmd_customer_order_delivery_time_show' ), 12, 5  );
		add_action( 'wcfm_after_store_invoice_order_details', array( &$this, 'wcfmd_store_invoice_delivery_time_show' ), 12, 3  );
		
	}
	
	function wcfm_delivery_time_global_settings( $wcfm_options ) {
		global $WCFM;
		
		$wcfm_store_hours = get_option( 'wcfm_store_hours_options', array() );
		
		$wcfm_delivery_time = get_option( 'wcfm_delivery_time_options', array() );
		
		if( !$wcfm_delivery_time ) $wcfm_delivery_time = $wcfm_store_hours;
		
		$wcfm_delivery_time_off_days = isset( $wcfm_delivery_time['off_days'] ) ? $wcfm_delivery_time['off_days'] : array();
		$wcfm_delivery_time_start_from      = isset( $wcfm_delivery_time['start_from'] ) ? $wcfm_delivery_time['start_from'] : '';
		$wcfm_delivery_time_end_at          = isset( $wcfm_delivery_time['end_at'] ) ? $wcfm_delivery_time['end_at'] : '';
		$wcfm_delivery_time_slots_duration  = isset( $wcfm_delivery_time['slots_duration'] ) ? $wcfm_delivery_time['slots_duration'] : '';
		$wcfm_delivery_time_display_format  = isset( $wcfm_delivery_time['display_format'] ) ? $wcfm_delivery_time['display_format'] : 'date_time';
		$wcfm_delivery_time_hide_field = isset( $wcfm_delivery_time['hide_field'] ) ? $wcfm_delivery_time['hide_field'] : 'no';
		
		$wcfm_delivery_time_day_times = isset( $wcfm_delivery_time['day_times'] ) ? $wcfm_delivery_time['day_times'] : array();
		
		$wcfm_delivery_time_mon_times = isset( $wcfm_delivery_time_day_times[0] ) ? $wcfm_delivery_time_day_times[0] : array();
		$wcfm_delivery_time_tue_times = isset( $wcfm_delivery_time_day_times[1] ) ? $wcfm_delivery_time_day_times[1] : array();
		$wcfm_delivery_time_wed_times = isset( $wcfm_delivery_time_day_times[2] ) ? $wcfm_delivery_time_day_times[2] : array();
		$wcfm_delivery_time_thu_times = isset( $wcfm_delivery_time_day_times[3] ) ? $wcfm_delivery_time_day_times[3] : array();
		$wcfm_delivery_time_fri_times = isset( $wcfm_delivery_time_day_times[4] ) ? $wcfm_delivery_time_day_times[4] : array();
		$wcfm_delivery_time_sat_times = isset( $wcfm_delivery_time_day_times[5] ) ? $wcfm_delivery_time_day_times[5] : array();
		$wcfm_delivery_time_sun_times = isset( $wcfm_delivery_time_day_times[6] ) ? $wcfm_delivery_time_day_times[6] : array();
		
		$wcfm_delivery_time_start_from_options = get_wcfm_start_from_delivery_times(); 
		
		$wcfm_delivery_time_end_at_options = get_wcfm_end_at_delivery_times();
		
		$wcfm_delivery_time_slots_duration_options = get_wcfm_slots_duration_delivery_times();
		
		$wcfm_delivery_time_display_format_options = apply_filters( 'wcfm_delivery_time_display_format', array( 'date_time' => __( 'Date and Time', 'wc-frontend-manager-delivery' ), 'date' => __( 'Only Date', 'wc-frontend-manager-delivery' ), 'time' => __( 'Only Time', 'wc-frontend-manager-delivery' ) ) );

		?>

		<div class="wcfm_clearfix"></div>
		<h2><?php echo __('Delivery Time Setting', 'wc-frontend-manager-delivery'); ?></h2>
		<div class="wcfm_clearfix"></div>
		<div class="store_address">
			<?php
			$WCFM->wcfm_fields->wcfm_generate_form_field( apply_filters( 'wcfm_settings_fields_delivery_time', array(
				"wcfm_default_delivery_time_off_days" => array( 'label' => __( 'Set Week Day OFF', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[off_days]', 'attributes' => array( 'multiple' => 'multiple', 'style' => 'width: 60%;' ), 'options' => array( 0 => __( 'Monday', 'wc-frontend-manager-delivery' ), 1 => __( 'Tuesday', 'wc-frontend-manager-delivery' ), 2 => __( 'Wednesday', 'wc-frontend-manager-delivery' ), 3 => __( 'Thursday', 'wc-frontend-manager-delivery' ), 4 => __( 'Friday', 'wc-frontend-manager-delivery' ), 5 => __( 'Saturday', 'wc-frontend-manager-delivery' ), 6 => __( 'Sunday', 'wc-frontend-manager-delivery') ), 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_off_days ),
				"wcfm_delivery_time_break_1" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
				"wcfm_delivery_time_start_from" => array( 'label' => __( 'Start From', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[start_from]', 'attributes' => array( 'style' => 'width: 20%;' ), 'options' => $wcfm_delivery_time_start_from_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_start_from, 'desc' => __( 'after order time', 'wc-frontend-manager-delivery' ), 'hints' => __( 'Set this to show first time slot available for delivery. E.g. if you set this `30 minutes` then customers will have first time slot after `30 minutes` from current time.', 'wc-frontend-manager-delivery' ) ),
				"wcfm_delivery_time_break_2" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
				"wcfm_delivery_time_end_at" => array( 'label' => __( 'Show upto', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[end_at]', 'attributes' => array( 'style' => 'width: 20%;' ), 'options' => $wcfm_delivery_time_end_at_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_end_at, 'desc' => __( 'schedule times', 'wc-frontend-manager-delivery' ), 'hints' => __( 'Set this to show maximum time slots available for delivery. E.g. if you set this `2 days` then customers will able to choose time slots upto `2 days from start time`.', 'wc-frontend-manager-delivery' ) ),
				"wcfm_delivery_time_break_3" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
				"wcfm_delivery_time_slots_duration" => array( 'label' => __( 'Slots Duration', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[slots_duration]', 'attributes' => array( 'style' => 'width: 20%;' ), 'options' => $wcfm_delivery_time_slots_duration_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_slots_duration ),
				"wcfm_delivery_time_break_4" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
				"wcfm_delivery_time_display_format" => array( 'label' => __( 'Slots Display Format', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[display_format]', 'attributes' => array( 'style' => 'width: 50%;' ), 'options' => $wcfm_delivery_time_display_format_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_display_format ),
				"wcfm_delivery_time_break_4" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
				"wcfm_delivery_time_hide_field" => array( 'label' => __( 'Hide when local pickup is chosen', 'wc-frontend-manager-delivery'), 'type' => 'checkbox', 'name' => 'wcfm_delivery_time[hide_field]', 'class' => 'wcfm-checkbox wcfm_ele', 'label_class' => 'wcfm_title checkbox_title', 'value' => 'yes', 'dfvalue' => $wcfm_delivery_time_hide_field ),
				"wcfm_delivery_time_break_4" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
			) ) );
			?>
		</div>
		
		<div class="wcfm_clearfix"></div><br />
		<h2 class=""><?php _e( 'Daily Basis Delivery Time Slots', 'wc-frontend-manager-delivery' ); ?></h2>
		<div class="wcfm_clearfix"></div>
		<div class="store_address">
			<?php
			$WCFM->wcfm_fields->wcfm_generate_form_field( apply_filters( 'wcfm_settings_fields_delivery_time_slots', array( 
					"wcfm_delivery_time_mon_times" => array( 'label' => __('Monday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][0]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_0', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_0', 'value' => $wcfm_delivery_time_mon_times, 'options' => array(
						"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
						"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
					) ),
					
					"wcfm_delivery_time_tue_times" => array( 'label' => __('Tuesday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][1]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_1', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_1', 'value' => $wcfm_delivery_time_tue_times, 'options' => array(
						"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
						"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
					) ),
					
					"wcfm_delivery_time_wed_times" => array( 'label' => __('Wednesday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][2]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_2', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_2', 'value' => $wcfm_delivery_time_wed_times, 'options' => array(
						"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
						"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
					) ),
					
					"wcfm_delivery_time_thu_times" => array( 'label' => __('Thursday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][3]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_3', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_3', 'value' => $wcfm_delivery_time_thu_times, 'options' => array(
						"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
						"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
					) ),
					
					"wcfm_delivery_time_fri_times" => array( 'label' => __('Friday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][4]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_4', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_4', 'value' => $wcfm_delivery_time_fri_times, 'options' => array(
						"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
						"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
					) ),
					
					"wcfm_delivery_time_sat_times" => array( 'label' => __('Saturday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][5]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_5', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_5', 'value' => $wcfm_delivery_time_sat_times, 'options' => array(
						"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
						"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
					) ),
					
					"wcfm_delivery_time_sun_times" => array( 'label' => __('Sunday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][6]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_6', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_6', 'value' => $wcfm_delivery_time_sun_times, 'options' => array(
						"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
						"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
					) ),
				) ) );
			?>
		</div>
		<div class="wcfm_clearfix"></div>
		
		<?php
		
	}
	
	function wcfm_delivery_time_global_settings_update( $wcfm_settings_form ) {
		if( isset( $wcfm_settings_form['wcfm_delivery_time'] ) ) {
			$wcfm_delivery_time_options = $wcfm_settings_form['wcfm_delivery_time'];
			update_option( 'wcfm_delivery_time_options',  $wcfm_delivery_time_options );
		}
	}
	
	function wcfm_delivery_time_vendor_settings( $vendor_id ) {
		global $WCFM;
		
		if( !apply_filters( 'wcfm_is_allow_delivery_time', true ) || !apply_filters( 'wcfm_is_allow_delivery_time_settings', true ) ) return;
		
		$disable_vendor = get_user_meta( $vendor_id, '_disable_vendor', true );
		if( $disable_vendor ) return;
		
		$wcfm_store_hours = get_option( 'wcfm_store_hours_options', array() );
		
		// Global Setting
		$wcfm_delivery_time = get_option( 'wcfm_delivery_time_options', array() );
		if( !$wcfm_delivery_time ) $wcfm_delivery_time = $wcfm_store_hours ;
		
		$wcfm_global_delivery_time_off_days        = isset( $wcfm_delivery_time['off_days'] ) ? $wcfm_delivery_time['off_days'] : array();
		$wcfm_global_delivery_time_start_from      = isset( $wcfm_delivery_time['start_from'] ) ? $wcfm_delivery_time['start_from'] : '';
		$wcfm_global_delivery_time_end_at          = isset( $wcfm_delivery_time['end_at'] ) ? $wcfm_delivery_time['end_at'] : '';
		$wcfm_global_delivery_time_slots_duration  = isset( $wcfm_delivery_time['slots_duration'] ) ? $wcfm_delivery_time['slots_duration'] : '';
		$wcfm_global_delivery_time_display_format  = isset( $wcfm_delivery_time['display_format'] ) ? $wcfm_delivery_time['display_format'] : 'date_time';
		$wcfm_global_delivery_time_day_times       = isset( $wcfm_delivery_time['day_times'] ) ? $wcfm_delivery_time['day_times'] : array();
		
		// Vendor wise Setting
		$wcfm_vendor_delivery_time = array();
		if( $vendor_id != 99999 ) {
			$wcfm_vendor_delivery_time = get_user_meta( $vendor_id, 'wcfm_vendor_delivery_time', true );
			if( !$wcfm_vendor_delivery_time ) $wcfm_vendor_delivery_time = array();
		}
		
		$wcfm_delivery_time_enable          = isset( $wcfm_vendor_delivery_time['enable'] ) ? 'yes' : 'no';
		$wcfm_delivery_time_off_days        = isset( $wcfm_vendor_delivery_time['off_days'] ) ? $wcfm_vendor_delivery_time['off_days'] : $wcfm_global_delivery_time_off_days;
		$wcfm_delivery_time_start_from      = isset( $wcfm_vendor_delivery_time['start_from'] ) ? $wcfm_vendor_delivery_time['start_from'] : $wcfm_global_delivery_time_start_from;
		$wcfm_delivery_time_end_at          = isset( $wcfm_vendor_delivery_time['end_at'] ) ? $wcfm_vendor_delivery_time['end_at'] : $wcfm_global_delivery_time_end_at;
		$wcfm_delivery_time_slots_duration  = isset( $wcfm_vendor_delivery_time['slots_duration'] ) ? $wcfm_vendor_delivery_time['slots_duration'] : $wcfm_global_delivery_time_slots_duration;
		$wcfm_delivery_time_display_format  = isset( $wcfm_vendor_delivery_time['display_format'] ) ? $wcfm_vendor_delivery_time['display_format'] : $wcfm_global_delivery_time_display_format;
		
		$wcfm_delivery_time_day_times  = isset( $wcfm_vendor_delivery_time['day_times'] ) ? $wcfm_vendor_delivery_time['day_times'] : $wcfm_global_delivery_time_day_times;
		
		$wcfm_delivery_time_mon_times = isset( $wcfm_delivery_time_day_times[0] ) ? $wcfm_delivery_time_day_times[0] : array();
		$wcfm_delivery_time_tue_times = isset( $wcfm_delivery_time_day_times[1] ) ? $wcfm_delivery_time_day_times[1] : array();
		$wcfm_delivery_time_wed_times = isset( $wcfm_delivery_time_day_times[2] ) ? $wcfm_delivery_time_day_times[2] : array();
		$wcfm_delivery_time_thu_times = isset( $wcfm_delivery_time_day_times[3] ) ? $wcfm_delivery_time_day_times[3] : array();
		$wcfm_delivery_time_fri_times = isset( $wcfm_delivery_time_day_times[4] ) ? $wcfm_delivery_time_day_times[4] : array();
		$wcfm_delivery_time_sat_times = isset( $wcfm_delivery_time_day_times[5] ) ? $wcfm_delivery_time_day_times[5] : array();
		$wcfm_delivery_time_sun_times = isset( $wcfm_delivery_time_day_times[6] ) ? $wcfm_delivery_time_day_times[6] : array();
		
		$wcfm_delivery_time_start_from_options = get_wcfm_start_from_delivery_times(); 
		
		$wcfm_delivery_time_end_at_options = get_wcfm_end_at_delivery_times();
		
		$wcfm_delivery_time_slots_duration_options = get_wcfm_slots_duration_delivery_times();
		
		$wcfm_delivery_time_display_format_options = apply_filters( 'wcfm_delivery_time_display_format', array( 'date_time' => __( 'Date and Time', 'wc-frontend-manager-delivery' ), 'date' => __( 'Only Date', 'wc-frontend-manager-delivery' ), 'time' => __( 'Only Time', 'wc-frontend-manager-delivery' ) ) );
				
		?>
		<!-- collapsible -->
		<div class="page_collapsible" id="wcfm_settings_form_delivery_time_head">
			<label class="wcfmfa fa-shipping-fast"></label>
			<?php _e('Delivery Times', 'wc-frontend-manager-delivery'); ?><span></span>
		</div>
		<div class="wcfm-container">
			<div id="wcfm_settings_form_delivery_time_expander" class="wcfm-content">
			  <div class="wcfm_clearfix"></div>
			  <?php if( !wcfm_is_vendor() && ( $vendor_id != 99999 ) ) { ?>
				<form id="wcfm_vendor_manage_delivery_time_setting_form" class="wcfm">
				<?php } ?>
					<h2><?php _e('Delivery Time Setting', 'wc-frontend-manager-delivery'); ?></h2>
					<div class="wcfm_clearfix"></div>
					<div class="store_address">
					
						<?php
							$WCFM->wcfm_fields->wcfm_generate_form_field( apply_filters( 'wcfm_vendors_settings_fields_delivery_time', array(
								"wcfm_delivery_time" => array( 'label' => __( 'Enable Delivery Time', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[enable]', 'type' => 'checkbox', 'class' => 'wcfm-checkbox wcfm_ele', 'label_class' => 'wcfm_title checkbox_title wcfm_ele', 'value' => 'yes', 'dfvalue' => $wcfm_delivery_time_enable ),
								"wcfm_delivery_time_off_days" => array( 'label' => __( 'Set Week Day OFF', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[off_days]', 'attributes' => array( 'multiple' => 'multiple', 'style' => 'width: 60%;' ), 'options' => array( 0 => __( 'Monday', 'wc-frontend-manager-delivery' ), 1 => __( 'Tuesday', 'wc-frontend-manager-delivery' ), 2 => __( 'Wednesday', 'wc-frontend-manager-delivery' ), 3 => __( 'Thursday', 'wc-frontend-manager-delivery' ), 4 => __( 'Friday', 'wc-frontend-manager-delivery' ), 5 => __( 'Saturday', 'wc-frontend-manager-delivery' ), 6 => __( 'Sunday', 'wc-frontend-manager-delivery') ), 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_off_days ),
								"wcfm_delivery_time_break_1" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
								"wcfm_delivery_time_start_from" => array( 'label' => __( 'Start From', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[start_from]', 'attributes' => array( 'style' => 'width: 20%;' ), 'options' => $wcfm_delivery_time_start_from_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_start_from, 'desc' => __( 'after order time', 'wc-frontend-manager-delivery' ), 'hints' => __( 'Set this to show first time slot available for delivery. E.g. if you set this `30 minutes` then customers will have first time slot after `30 minutes` from current time.', 'wc-frontend-manager-delivery' ) ),
								"wcfm_delivery_time_break_2" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
								"wcfm_delivery_time_end_at" => array( 'label' => __( 'Show upto', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[end_at]', 'attributes' => array( 'style' => 'width: 20%;' ), 'options' => $wcfm_delivery_time_end_at_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_end_at, 'desc' => __( 'schedule times', 'wc-frontend-manager-delivery' ), 'hints' => __( 'Set this to show maximum time slots available for delivery. E.g. if you set this `2 days` then customers will able to choose time slots upto `2 days from start time`.', 'wc-frontend-manager-delivery' ) ),
								"wcfm_delivery_time_break_3" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
								"wcfm_delivery_time_slots_duration" => array( 'label' => __( 'Slots Duration', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[slots_duration]', 'attributes' => array( 'style' => 'width: 20%;' ), 'options' => $wcfm_delivery_time_slots_duration_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_slots_duration ),
								"wcfm_delivery_time_break_4" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
								"wcfm_delivery_time_display_format" => array( 'label' => __( 'Slots Display Format', 'wc-frontend-manager-delivery'), 'type' => 'select', 'name' => 'wcfm_delivery_time[display_format]', 'attributes' => array( 'style' => 'width: 50%;' ), 'options' => $wcfm_delivery_time_display_format_options, 'class' => 'wcfm-select wcfm_ele', 'label_class' => 'wcfm_title', 'value' => $wcfm_delivery_time_display_format ),
								"wcfm_delivery_time_break_4" => array( 'type' => 'html', 'value' => '<div class="wcfm-clearfix"></div><br />' ),
							), $vendor_id ) );
						?>
					</div>
					
					<div class="wcfm_clearfix"></div><br />
					<h2 class=""><?php _e( 'Daily Basis Delivery Time Slots', 'wc-frontend-manager-delivery' ); ?></h2>
					<div class="wcfm_clearfix"></div>
					<div class="store_address">
						<?php
						$WCFM->wcfm_fields->wcfm_generate_form_field( apply_filters( 'wcfm_vendors_settings_fields_delivery_time_slots', array( 
								"wcfm_delivery_time_mon_times" => array( 'label' => __('Monday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][0]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_0', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_0', 'value' => $wcfm_delivery_time_mon_times, 'options' => array(
									"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
									"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
								) ),
								
								"wcfm_delivery_time_tue_times" => array( 'label' => __('Tuesday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][1]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_1', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_1', 'value' => $wcfm_delivery_time_tue_times, 'options' => array(
									"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
									"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
								) ),
								
								"wcfm_delivery_time_wed_times" => array( 'label' => __('Wednesday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][2]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_2', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_2', 'value' => $wcfm_delivery_time_wed_times, 'options' => array(
									"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
									"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
								) ),
								
								"wcfm_delivery_time_thu_times" => array( 'label' => __('Thursday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][3]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_3', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_3', 'value' => $wcfm_delivery_time_thu_times, 'options' => array(
									"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
									"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
								) ),
								
								"wcfm_delivery_time_fri_times" => array( 'label' => __('Friday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][4]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_4', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_4', 'value' => $wcfm_delivery_time_fri_times, 'options' => array(
									"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
									"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
								) ),
								
								"wcfm_delivery_time_sat_times" => array( 'label' => __('Saturday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][5]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_5', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_5', 'value' => $wcfm_delivery_time_sat_times, 'options' => array(
									"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
									"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
								) ),
								
								"wcfm_delivery_time_sun_times" => array( 'label' => __('Sunday Time Slots', 'wc-frontend-manager-delivery'), 'name' => 'wcfm_delivery_time[day_times][6]', 'type' => 'multiinput', 'class' => 'wcfm_delivery_time_fields wcfm_delivery_time_fields_6', 'label_class' => 'wcfm_title wcfm_delivery_time_fields wcfm_delivery_time_fields_6', 'value' => $wcfm_delivery_time_sun_times, 'options' => array(
									"start" => array( 'label' => __('Opening', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
									"end" => array( 'label' => __('Closing', 'wc-frontend-manager-delivery'), 'type' => 'time', 'class' => 'wcfm-text wcfm_delivery_time_field', 'label_class' => 'wcfm_title wcfm_delivery_time_label' ),
								) ),
							), $vendor_id ) );
						?>
					</div>
					
					<?php if( !wcfm_is_vendor() && ( $vendor_id != 99999 ) ) { ?>
						<div class="wcfm-clearfix"></div>
						<div class="wcfm-message" tabindex="-1"></div>
						<div class="wcfm-clearfix"></div>
						<div class="wcfm_messages_submit">
							<input type="submit" name="save-data" value="<?php _e( 'Update', 'wc-frontend-manager' ); ?>" id="wcfm_delivery_time_setting_save_button" class="wcfm_submit_button" />
						</div>
						<div class="wcfm-clearfix"></div>
					<?php } ?>
					
					<?php if( !wcfm_is_vendor() && ( $vendor_id != 99999 ) ) { ?>
					<input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>" />
				</form>
				<?php } ?>
		  </div>
		</div>
		<div class="wcfm_clearfix"></div>
		<?php if(!wcfm_is_vendor() && ( $vendor_id != 99999 ) ) { ?>
			<br />
		<?php } ?>
		<?php
	}
	
	function wcfm_delivery_time_vendor_settings_update( $vendor_id, $wcfm_settings_form ) {
		if( !apply_filters( 'wcfm_is_allow_delivery_time', true ) || !apply_filters( 'wcfm_is_allow_delivery_time_settings', true ) ) return;
		
		if( isset( $wcfm_settings_form['wcfm_delivery_time'] ) ) {
			update_user_meta( $vendor_id, 'wcfm_vendor_delivery_time', $wcfm_settings_form['wcfm_delivery_time'] );
			update_user_meta( $vendor_id, 'wcfm_vendor_delivery_time_migrated', 'yes' );
		}
	}
	
	function wcfmd_checkout_delivery_time_field( $fields ) {
		global $WCFMmp;
		if( ( true === WC()->cart->needs_shipping() ) && apply_filters( 'wcfmmp_is_allow_checkout_delivery_time', true ) ) {
		
			$wcfm_marketplace_options = $WCFMmp->wcfmmp_marketplace_options;
		
			$disable_multivendor_checkout = isset( $wcfm_marketplace_options['disable_multivendor_checkout'] ) ? $wcfm_marketplace_options['disable_multivendor_checkout'] : 'no';
			
			$vendor_list = array();
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $cart_product_id = $cart_item['product_id'];
                $cart_product = get_post( $cart_product_id );
                $cart_product_author = $cart_product->post_author;
                if( function_exists( 'wcfm_is_vendor' ) && wcfm_is_vendor( $cart_product_author ) && ! in_array($cart_product_author, $vendor_list) ) {
                    $vendor_list[] = $cart_product_author;
                }
                if( $disable_multivendor_checkout == 'yes' ) { 
                    break;
                }
            }
            if(!empty($vendor_list)) {
				$wcfm_store_hours = get_option( 'wcfm_store_hours_options', array() );
				$wcfm_delivery_time = get_option( 'wcfm_delivery_time_options', array() );
				if( !$wcfm_delivery_time ) $wcfm_delivery_time = $wcfm_store_hours;
				$hide_on_local_pickup = isset($wcfm_delivery_time['hide_field']) ? $wcfm_delivery_time['hide_field'] : 'no';

                $current_time = current_time( 'timestamp' );
                $multivendor_checkout = $disable_multivendor_checkout !== 'yes';
                foreach($vendor_list as $vendor_id) {
                    $fields = $this->generate_vendor_delivery_time_checkout_field( $vendor_id, $fields, $current_time, $multivendor_checkout, $hide_on_local_pickup );
				}
            }
		}
		return $fields;
	}

	function generate_vendor_delivery_time_checkout_field( $vendor_id, $fields, $current_time, $multivendor_checkout = true, $hide_on_local_pickup = 'no' ) {
		$wcfm_vendor_delivery_time = get_user_meta( $vendor_id, 'wcfm_vendor_delivery_time', true );
		if ( ! isset( $wcfm_vendor_delivery_time['enable'] ) ) return $fields;

		$wcfm_delivery_time_off_days = isset( $wcfm_vendor_delivery_time['off_days'] ) ? $wcfm_vendor_delivery_time['off_days'] : array();
		$off_days = count($wcfm_delivery_time_off_days);
		if( $off_days == 7 ) return $fields;
        $wcfm_delivery_time_start_from = isset( $wcfm_vendor_delivery_time['start_from'] ) ? $wcfm_vendor_delivery_time['start_from'] : 0;
        $wcfm_delivery_time_end_at = isset( $wcfm_vendor_delivery_time['end_at'] ) ? $wcfm_vendor_delivery_time['end_at'] : 0;
        $wcfm_delivery_time_slots_duration = isset( $wcfm_vendor_delivery_time['slots_duration'] ) ? $wcfm_vendor_delivery_time['slots_duration'] : 0;
        $wcfm_delivery_time_display_format = isset( $wcfm_vendor_delivery_time['display_format'] ) ? $wcfm_vendor_delivery_time['display_format'] : 'date_time';

        $wcfm_delivery_time_day_times = isset( $wcfm_vendor_delivery_time['day_times'] ) ? $wcfm_vendor_delivery_time['day_times'] : array();

        $wcfm_delivery_time_start_from_options = get_wcfm_start_from_delivery_times();
        $wcfm_delivery_time_end_at_options = get_wcfm_end_at_delivery_times();
        $wcfm_delivery_time_slots_duration_options = get_wcfm_slots_duration_delivery_times();

        $start_time = strtotime( '+' . $wcfm_delivery_time_start_from_options[$wcfm_delivery_time_start_from], $current_time );
        $minutes_remaining_before_end = $this->get_minutes_from_str($wcfm_delivery_time_end_at_options[$wcfm_delivery_time_end_at], $current_time);
        $interval_in_minutes = $this->get_minutes_from_str($wcfm_delivery_time_slots_duration_options[$wcfm_delivery_time_slots_duration], $current_time);
        
        $time_slots = array( '' => __( 'Choose preferred delivery time', 'wc-frontend-manager-delivery' ) );
        $next_time_slot = $start_time;
		$first_time_flag = false;
		$max_iteration_with_no_change = 8; // 7days + 1 extra to fully check the starting day
		$previous_minutes_remaining = '';
        while ( $minutes_remaining_before_end > 0 && $max_iteration_with_no_change > 0 ) {
			if($previous_minutes_remaining == $minutes_remaining_before_end) {
				$max_iteration_with_no_change--;
			} else {
				$max_iteration_with_no_change = 8;
				$previous_minutes_remaining = $minutes_remaining_before_end;
			}
			$week_date = date( 'Y-m-d', $next_time_slot );
            $weekday = date( 'N', $next_time_slot ) - 1;
            if ( ! empty( $wcfm_delivery_time_off_days ) ) {
                if ( in_array( $weekday, $wcfm_delivery_time_off_days ) ) {
                    $next_time_slot = strtotime( $week_date . ' +1 day' ); //go to next day 12:00am
                    $first_time_flag = true;
                    continue;
                }
            }
            
			$daywise_slot_rules_applied = false;
			$current_day_slot_available = false;
            if ( !empty( $wcfm_delivery_time_day_times[$weekday] ) ) {
                $tmp_remaining_minutes = $minutes_remaining_before_end;
                $wcfm_delivery_time_day_time_slots = $wcfm_delivery_time_day_times[$weekday];
                foreach ( $wcfm_delivery_time_day_time_slots as $slot => $wcfm_delivery_time_day_time_slot ) {
                    $open_hours = !empty( $wcfm_delivery_time_day_time_slot['start'] ) ? strtotime( $week_date . ' ' . $wcfm_delivery_time_day_time_slot['start'] ) : 0;
                    $close_hours = !empty( $wcfm_delivery_time_day_time_slot['end'] ) ? strtotime( $week_date . ' ' . $wcfm_delivery_time_day_time_slot['end'] ) : 0;
					if(!$open_hours || !$close_hours || $open_hours >= $close_hours ) {
                        continue;
					}
					$current_day_slot_available = true;

                    while( $close_hours>$next_time_slot && $minutes_remaining_before_end > 0 ) {
                        if($open_hours>=$next_time_slot || $interval_in_minutes >= 1440) { // 1day = 1440 min
                            $next_time_slot = $open_hours;
                        } else {
                            $time_diff_in_minute = floor(($next_time_slot - $open_hours)/60);
                            $minutes_to_be_adjusted = $time_diff_in_minute % $interval_in_minutes;
                            $next_time_slot = strtotime( "-{$minutes_to_be_adjusted} minutes", $next_time_slot );
                            
                            //only for the 1st time
                            if(!$first_time_flag && $minutes_to_be_adjusted && apply_filters('wcfm_delivery_time_restrict_getting_current_running_slot', true)) {
                                $next_time_slot = strtotime( "+{$interval_in_minutes} minutes", $next_time_slot ); //can't use a slot if it already started.
								if($next_time_slot >= $close_hours) {
									$first_time_flag = true;
									continue;
								}
							}
                        }
                        $option_val = $this->prepare_delivery_time_range( $wcfm_delivery_time_display_format, $next_time_slot, $interval_in_minutes, $close_hours );
                        $time_slots[$option_val] = $this->format_delivery_time_checkout_field($wcfm_delivery_time_display_format, $option_val);
                        $next_time_slot = strtotime( "+{$interval_in_minutes} minutes", $next_time_slot );
                        $minutes_remaining_before_end -= $interval_in_minutes;
                        
                        if($next_time_slot>$close_hours) {
                            //adjust remining time
                            $minutes_remaining_before_end += floor(($next_time_slot-$close_hours)/60);
                            $next_time_slot=$close_hours;
                            
                        }
                        $daywise_slot_rules_applied = true;
                        if(!$first_time_flag) {
                            $first_time_flag = true;
                        }
                    }
                }
            } 
            
            if($daywise_slot_rules_applied) {
                $next_time_slot = strtotime( $week_date . ' +1 day' ); //go to next day 12:00am
                $minutes_remaining_before_end = $tmp_remaining_minutes - 1440; //1 day = 1440 minutes
            } elseif($current_day_slot_available) {
				$next_time_slot = strtotime( $week_date . ' +1 day' ); //go to next day 12:00am
			}else {
                if(apply_filters('wcfm_roundoff_delivery_time_checkout_field', true) && $wcfm_delivery_time_display_format !== 'date') {
                    $minute = (int) date('i', $next_time_slot);
                    $first_time_adjustment = $interval_in_minutes;
                    if($interval_in_minutes < 60) {
                        $minute_adjustment = ($minute % $interval_in_minutes);
                        $next_time_slot = strtotime("-{$minute_adjustment} minutes", $next_time_slot );
                    } else {
                        $next_time_slot = strtotime("-{$minute} minutes", $next_time_slot );
                        $first_time_adjustment = 60;
                    }
                    //only for the 1st time
                    if(!$first_time_flag && apply_filters('wcfm_delivery_time_restrict_getting_current_running_slot', true)) {
                        $next_time_slot = strtotime( "+{$first_time_adjustment} minutes", $next_time_slot ); //can't use a slot if it already started.
                    }
                }
                if(!$first_time_flag) {
                    $first_time_flag = true;
                }
                $option_val = $this->prepare_delivery_time_range( $wcfm_delivery_time_display_format, $next_time_slot, $interval_in_minutes );
                $time_slots[$option_val] = $this->format_delivery_time_checkout_field($wcfm_delivery_time_display_format, $option_val);

                $next_time_slot = strtotime( '+' . $wcfm_delivery_time_slots_duration_options[$wcfm_delivery_time_slots_duration], $next_time_slot );
                $minutes_remaining_before_end -= $interval_in_minutes;
            }
		}
		
		if(count($time_slots) == 1 ) return $fields; //no change

        $field_id = 'wcfmd_delvery_time_' . $vendor_id;

        $field_label = __( 'Delivery Time', 'wc-frontend-manager-delivery' );
        if ( $multivendor_checkout ) {
            $field_label = wcfm_get_vendor_store_name( $vendor_id ) . ' ' . $field_label;
        }

        $delivery_time_field = array( 
			$field_id => array(
                'label'    => $field_label,
				'type'     => 'select',
				'required' => true,
                'options'  => $time_slots,
				'class'    => array( 'form-row-wide' ),
				'priority' => 1,
                'clear'    => true,
			),
		 );
		 if($hide_on_local_pickup === 'yes') {
			$delivery_time_field[$field_id]['required'] = false;
			$delivery_time_field += array(
				"_{$field_id}_is_required" => array(
					'class'     => array('wcfm_custom_hide'),
					'value'     => 'yes', 
				)
			);
		 }
			
		$position = apply_filters('wcfmd_delivery_time_field_position', 'billing');
		if( !in_array($position, array('billing', 'shipping'))) {
			$position = 'billing';
		}
        $fields[$position] = $delivery_time_field + $fields[$position];

		return $fields;
	}

	function get_minutes_from_str($duration, $current_time) {
        $new_time = strtotime( '+' . $duration, $current_time );
        return floor(($new_time - $current_time)/60);
    }
    
    function prepare_delivery_time_range($display_format, $timestamp, $interval, $closing_time = false) {
        if ( $display_format == 'date' ) {
            return $timestamp;
        }
        if(apply_filters('wcfm_delivery_time_show_in_timeslot_format', true)) {
			$minutes_to_add = apply_filters('wcfm_delivery_time_endtime_equals_next_starttime_slot', false) ? $interval : $interval - 1;
            $slot_ends_at = strtotime( '+' . $minutes_to_add . " minutes", $timestamp ); 
            if($closing_time && $slot_ends_at>$closing_time) {
                $slot_ends_at = $closing_time;
            }
            return "{$timestamp}|{$slot_ends_at}";
        }
        return $timestamp;
    }
    
    function format_delivery_time_checkout_field($display_format, $time_value ) {
		$time_field = explode('|', $time_value);
        if ( $display_format == 'date' ) {
            return date_i18n( wc_date_format(), $time_field[0] );
        }
        $format = wc_date_format() . ' ' . wc_time_format();
        if ( $display_format == 'time' ) {
			$format = wc_time_format();
        }
        $formatted_time = date_i18n( $format, $time_field[0] );
        
        if(apply_filters('wcfm_delivery_time_show_in_timeslot_format', true) && !empty($time_field[1])) {
            if(date('y-m-d', $time_field[0]) === date('y-m-d', $time_field[1])) {
                $format = wc_time_format();
            }
            $formatted_time = $formatted_time . "-" . date_i18n( $format, $time_field[1] );
        }
        return $formatted_time;
    }
	
	function wcfmd_checkout_delivery_time_field_save( $order_id, $data ) {
		$order = wc_get_order( $order_id );
    
		if( !is_a( $order , 'WC_Order' ) ) return;
		
		$order_delivery_times = array();
    
		$items = $order->get_items( 'line_item' );
    	if( !empty( $items ) ) {
			foreach( $items as $item ) {
				$line_item = new WC_Order_Item_Product( $item );
				$product_id = $line_item->get_product_id();
				
				if( $product_id ) {
					$vendor_id = wcfm_get_vendor_id_by_post( $product_id );
					
					if( $vendor_id ) {
						if ( isset( $data['wcfmd_delvery_time_'.$vendor_id] ) && ! empty( $data['wcfmd_delvery_time_'.$vendor_id] ) ) {
							$order_delivery_times[$vendor_id] = $data['wcfmd_delvery_time_'.$vendor_id];
						}
					}
				}
			}
		}
		if ( ! empty( $order_delivery_times ) ) {
			$order->update_meta_data( '_wcfmd_delvery_times', $order_delivery_times );
			$order->save();
		}
	}

	function get_display_format($vendor_id) {
        $delivery_time_settings = array();
        if(wcfm_is_vendor($vendor_id)) {
            $delivery_time_settings = (array)get_user_meta( $vendor_id, 'wcfm_vendor_delivery_time', true );
        } else {
            $delivery_time_settings = get_option( 'wcfm_delivery_time_options', array() );
        }
        $display_format = isset( $delivery_time_settings['display_format'] ) ? $delivery_time_settings['display_format'] : 'date_time';
        if(in_array($display_format, array('date', 'time', 'date_time'))) {
			return $display_format;
		}
		return 'date_time';
    }

	function get_time_format($vendor_id) {
        $display_format = $this->get_display_format($vendor_id);
        $format = wc_date_format() . ' ' . wc_time_format();
        if($display_format=='date') {
            $format = wc_date_format();
        } elseif($display_format=='time') {
            $format = wc_time_format();
        }
        return $format;
    }
	
	function wcfmd_order_details_delivery_time_show( $order ) {
		global $WCFMmp;
		if( apply_filters( 'wcfm_is_allow_delivery_time', true ) ) {
			$wcfmd_delvery_times = $order->get_meta( '_wcfmd_delvery_times', true );
			if( !empty(  $wcfmd_delvery_times ) ) {
				foreach( $wcfmd_delvery_times as $vendor_id => $wcfmd_delvery_time ) {
					if( wcfm_is_vendor() && ( $vendor_id != $WCFMmp->vendor_id ) ) continue;
					echo '<p class="wcfm_order_details_delivery_time"><i class="wcfmfa fa-clock" style="color:#ff1400"></i>&nbsp;&nbsp;<strong>';
					if( !wcfm_is_vendor() ) echo wcfm_get_vendor_store_name( $vendor_id ) . ' ';
					$time_format = $this->get_time_format($vendor_id);
					echo __( 'Delivery Time', 'wc-frontend-manager-delivery' ).':</strong> ' . $this->format_delivery_time_checkout_field($time_format, $wcfmd_delvery_time ) . '</p>';
				}
			}
		}
	}
	
	function wcfmd_order_list_delivery_time_show( $shipping_address, $order_id ) {
		global $WCFMmp;
		if( apply_filters( 'wcfm_is_allow_delivery_time', true ) ) {
			$order = wc_get_order($order_id);
			$wcfmd_delvery_times = $order->get_meta( '_wcfmd_delvery_times', true );
			if( !empty(  $wcfmd_delvery_times ) ) {
				foreach( $wcfmd_delvery_times as $vendor_id => $wcfmd_delvery_time ) {
					if( wcfm_is_vendor() && ( $vendor_id != $WCFMmp->vendor_id ) ) continue;
					$shipping_address .=  '<br/><p class="wcfm_order_list_delivery_time"><i class="wcfmfa fa-clock" style="color:#ff1400"></i>&nbsp;&nbsp;<strong>';
					if( !wcfm_is_vendor() ) $shipping_address .= wcfm_get_vendor_store_name( $vendor_id ) . ' ';
					$time_format = $this->get_time_format($vendor_id);
					$shipping_address .= __( 'Delivery Time', 'wc-frontend-manager-delivery' ).':</strong> ' . $this->format_delivery_time_checkout_field($time_format, $wcfmd_delvery_time ) . '</p>';
				}
			}
		}
		
		return $shipping_address;
	}
	
	function wcfmd_customer_order_delivery_time_show( $order, $is_plain = 0, $is_admin = 0, $email = null, $preferred_vendor = 0 ) {
		if( function_exists('is_wcfm_page') && is_wcfm_page() ) return;
		if( $email && !in_array( $email->id, apply_filters( 'wcfm_allowed_store_policies_order_emails', array( 'customer_invoice', 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order' ) ) ) ) return;
		$wcfmd_delvery_times = $order->get_meta( '_wcfmd_delvery_times', true );
		if( empty(  $wcfmd_delvery_times ) ) return; 
				
		echo "<br />";
		echo "<h2 style='font-size: 18px; color: #17a2b8; line-height: 20px;margin-top: 6px;margin-bottom: 10px;padding: 0px;text-decoration: underline;'>" . __( 'Delivery Time(s)', 'wc-frontend-manager-delivery' ) . "</h2>";
		echo "<table width='100%' style='width:100%;'><tbody>";
		foreach ( $wcfmd_delvery_times as $vendor_id => $wcfmd_delvery_time ) {
			if( $preferred_vendor && ( $preferred_vendor != $vendor_id) ) continue;
			$store_name          = wcfm_get_vendor_store_name( $vendor_id );
			$display_format = $this->get_display_format($vendor_id);
			?>
			<tr>
				<td colspan="3" style="background-color: #eeeeee;padding: 1em 1.41575em;line-height: 1.5;font-weight:600;">
					<?php 
					echo $store_name;
					?>
				</td>
				<td colspan="5" style="background-color: #f8f8f8;padding: 1em;"><?php echo  $this->format_delivery_time_checkout_field($display_format, $wcfmd_delvery_time ); ?></td>
			</tr>
			<?php
		}
		echo "</tbody></table>";
		echo "<br />";
	}
	
	function wcfmd_store_invoice_delivery_time_show( $vendor_id, $order_id, $order ) {
		$this->wcfmd_customer_order_delivery_time_show( $order, 0, 0, false, $vendor_id );	
	}
}