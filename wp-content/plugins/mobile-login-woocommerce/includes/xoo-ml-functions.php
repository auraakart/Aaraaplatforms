<?php

//Add notice
function xoo_ml_add_notice( $message, $notice_type = 'error' ){

	$classes = $notice_type === 'error' ? 'xoo-ml-notice-error' : 'xoo-ml-notice-success';
	
	$html = '<div class="'.$classes.'">'.$message.'</div>';
	
	return apply_filters('xoo_ml_notice_html',$html,$message,$notice_type);
}

//Phone input field
function xoo_ml_get_phone_input_field( $args = array(), $return = false ){

	$settings = xoo_ml_helper()->get_phone_option();

	$args = wp_parse_args( $args, array(
		'label' 			=> __( 'Phone', 'mobile-login-woocommerce' ),
		'input_class'		=> array(),
		'cont_class'		=> array(),
		'label_class' 		=> array(),
		'show_phone' 		=> $settings['r-phone-field'],
		'cc_show' 			=> $settings['r-enable-cc-field'],
		'cc_type'	 		=> $settings['m-show-country-code-as'],
		'default_phone' 	=> '', 
		'default_cc' 		=> $settings['r-default-country-code-type'] === 'geolocation' ? Xoo_Ml_Geolocation::get_phone_code() : $settings['r-default-country-code'],
		'form_token' 		=> mt_rand( 1000, 9999 ),
		'form_type' 		=> 'register_user',
		'otp_display' 		=> $settings['m-otp-form-type'],
		'is_easylogin_form' => false,
		'merge_cc' 			=> xoo_ml_helper()->mergeCC()
	) );


	$args = apply_filters( 'xoo_ml_phone_input_field_args', $args );

	return xoo_ml_helper()->get_template( 'xoo-ml-phone-input.php', array( 'args' => $args ), '', $return );
}



//OTP login form
function xoo_ml_get_login_with_otp_form( $args = array(), $return = false ){

	$settings = xoo_ml_helper()->get_phone_option();

	$args = wp_parse_args( $args, array(
		'label' 			=> __( 'Phone', 'mobile-login-woocommerce' ),
		'button_class' 		=> array(
			'button', 'btn'
		),
		'input_class' 		=> array(),
		'cont_class'		=> array(),
		'label_class' 		=> array(),
		'form_type' 		=> 'login_with_otp',
		'redirect' 			=> trim( $settings['l-redirect'] ) ? esc_attr( $settings['l-redirect'] ) : $_SERVER['REQUEST_URI'],
		'is_easylogin_form' => false,
		'login_first' 		=> $settings['l-login-display'],
		'otp_display' 		=> $settings['m-otp-form-type'],
	) );

	return xoo_ml_helper()->get_template( 'xoo-ml-otp-login-form.php', array( 'args' => $args ), '', $return );
}


//Get user phone number
function xoo_ml_get_user_phone( $user_id, $code_or_phone = '' ){

	$code 	= esc_attr( get_user_meta( $user_id, 'xoo_ml_phone_code', true ) );
	$number = esc_attr( get_user_meta( $user_id, 'xoo_ml_phone_no', true ) );

	if( $code_or_phone === 'number' ){
		return $number;
	}else if( $code_or_phone === 'code' ){
		return $code;
	}

	return array(
		'code' 		=> $code,
		'number' 	=> $number
	);
}


/*
 * Search user by phone number
*/
function xoo_ml_get_user_by_phone( $phone_no, $phone_code = '' ){

	$meta_query_args = array(
		array(
			'key' 		=> 'xoo_ml_phone_no',
			'value' 	=> $phone_no,
			'compare' 	=> '='
		)
	);

	if( $phone_code ){
		$meta_query_args['relation'] = 'AND';
		$meta_query_args[] = array(
			'key' 		=> 'xoo_ml_phone_code',
			'value' 	=> $phone_code,
			'compare' 	=> '='
		);
	}
	else{
		$meta_query_args['relation'] = 'OR';
		$meta_query_args[] = array(
			'key' 		=> 'xoo_ml_phone_display',
			'value' 	=> $phone_no,
			'compare' 	=> 'IN'
		);
	}

	$args = array(
		'meta_query' => $meta_query_args
	);

	$user_query = new WP_User_Query( $args );

	$phone_users = $user_query->get_results();

	//In case there are more than one user registered with the same mobile number but different phone code ( Highly Unlikely ).
	//Get current user's location phone code
	if( count( $phone_users ) > 1 ){
		$phone_code = !$phone_code ? Xoo_Ml_Geolocation::get_phone_code() : $phone_code;
		foreach ( $phone_users as $phone_user ) {
			if( xoo_ml_get_user_phone( $phone_user->ID, 'code', true ) !== $phone_code ) continue;
			return $phone_user;
		}
	}
	elseif ( count( $phone_users ) === 1 ){
		return $phone_users[0];
	}
	else{
		return false;
	}

}


//Operator info
function xoo_ml_operator_data(){
	return xoo_ml_services()->operators;
}


function xoo_ml_get_country_codes(){

	$allowed 	= xoo_ml_helper()->get_phone_option('r-countries');
	$allowed 	= !is_array( $allowed ) ? array() : $allowed;

 	$all = xoo_ml_country_codes_list();

 	$return = array();

 	if( $allowed && !empty( $allowed ) ){
 		foreach ($all as $cc => $phone_code ) {
 			if( in_array( $phone_code , $allowed ) ){
 				$return[ $cc ] = $phone_code;
 			}
 		}
 	}
 	else{
 		$return = $all;
 	}

 	return apply_filters( 'xoo_ml_country_codes', $return );

}

function xoo_ml_country_codes_list(){
	return apply_filters( 'xoo_ml_country_codes_list', include XOO_ML_PATH.'/countries/phone.php' );	
}

function xoo_ml_get_default_phone_code(){

	if( xoo_ml_helper()->get_phone_option('r-default-country-code-type') === 'geolocation' && Xoo_Ml_Geolocation::get_phone_code() ){
		$default_cc = Xoo_Ml_Geolocation::get_phone_code();
	}else{
		$default_cc = xoo_ml_helper()->get_phone_option('r-default-country-code');
	}

	return $default_cc;
}

?>