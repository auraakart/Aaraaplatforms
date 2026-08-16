<?php

$country_phone_codes = (array) xoo_ml_country_codes_list();

$phone_codes = array();

foreach ( $country_phone_codes as $cc => $pc ) {
	if( !$pc || is_array( $pc ) ) continue;
	$phone_codes[ $pc ] = $cc . $pc;
}

$enableCCDesc = 'A valid phone number needs a country code. If disabled, the default one selected below is set as country code.';
if( function_exists('xoo_el') ){
	$enableCCDesc .= ' * Please visit "Fields" page after updating and saving this setting to refresh Country code field.';
}


$smsOperators = array(
	'twilio' 	=> 'Twilio',
	'firebase' 	=> 'Google Firebase',
	'aws'  		=> 'Amazon',
	'blksms' 	=> 'Bulk SMS',
	'bulksms' 	=> 'Bulk(s) SMS',
	'netgsm' 	=> 'Net GSM (Turkey)',
	'oursms' 	=> 'OurSMS',
	'smsalert' 	=> 'SMS Alert',
	'unifonic' 	=> 'Unifonic',
	'msg91' 	=> 'MSG91',
	'textlocal' => 'TextLocal',
	'smslane' 	=> 'SMSLane',
	'semaphore' => 'Semaphore (Phillipines)',
	'msegat' 	=> 'Msegat',
	'custom' 	=> 'Custom (None of the above)',
);	


$settings = array(

	array(
		'callback' 		=> 'radio',
		'section_id' 	=> 'ph_main',
		'id' 			=> 'm-sms-channels',
		'title' 		=> 'Send OTP via',
		'args' 			=> array(
			'options' 	=> array(
				'sms' 					=> 'SMS',
				'whatsapp' 				=> 'Whatsapp',
				'both' 					=> 'SMS & Whatsapp'
			),
			'toggleSettings' => array( //hide elements if their value is
				'xoo-ml-phone-options[m-operator]' 			=> array( 'whatsapp' ),
				'xoo-ml-phone-options[m-opsec-enable]' 		=> array( 'whatsapp' ),
				'xoo-ml-phone-options[m-opsec-countries]' 	=> array( 'whatsapp' ),
				'xoo-ml-phone-options[m-opsec-service]' 	=> array( 'whatsapp' ),
			),
		),
		'default' 		=> 'sms',
		'desc' 			=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce" target="_blank" style="margin-top: -15px; display: table;">Setup SMS/Whatsapp</a>'
	),


	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_main',
		'id'			=> 'm-operator',
		'title' 		=> 'SMS Service Operator',
		'default' 		=> 'firebase',
		'args'			=> array(
			'options' => $smsOperators
		)
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_main',
		'id' 			=> 'm-del-0',
		'title' 		=> 'Remove 0 from phone number',
		'default' 		=> 'yes',
		'desc' 			=> 'Removes 0 from the starting'
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_main',
		'id' 			=> 'm-en-debug',
		'title' 		=> 'Enable debug',
		'default' 		=> 'no',
		'desc' 			=> "Shows actual response from the SMS operator website. This will stop the plugin flow and will only show the response, so disable it once you're done debugging."
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_cc',
		'id' 			=> 'r-enable-cc-field',
		'title' 		=> 'Enable Country Code Field',
		'default' 		=> 'yes',
		'desc' 			=> $enableCCDesc
	),


	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_cc',
		'id'			=> 'r-countries',
		'title' 		=> 'Allowed Countries',
		'default' 		=> 'geolocation',
		'desc' 			=> 'Leave empty to allow all countries',
		'args'			=> array(
			'options' 		=> $phone_codes,
			'select2box' 	=> true,
			'multiple' 		=> true
		)
	),

	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_cc',
		'id'			=> 'r-default-country-code-type',
		'title' 		=> 'Default Country Code',
		'default' 		=> 'geolocation',
		'desc' 			=> 'Geolocation is auto detect user location.',
		'args'			=> array(
			'options' => array(
				'geolocation'  	=> 'Geolocation',
				'custom'   		=> 'Custom',
			)	
		)
	),


	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_cc',
		'id'			=> 'r-default-country-code',
		'title' 		=> 'Country Code',
		'default' 		=> '+1',
		'args'			=> array(
			'options' => $phone_codes,
			'select2box' 	=> true,
		)
	),

	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_cc',
		'id'			=> 'm-show-country-code-as',
		'title' 		=> 'Field Style',
		'default' 		=> 'selectbox',
		'desc' 			=> '',
		'args'			=> array(
			'options' => array(
				'selectbox' => 'Select Box',
				'input'   	=> 'Input Text',
			)	
		)
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_cc',
		'id' 			=> 'm-cc-merge',
		'title' 		=> 'Merge with Phone Number',
		'default' 		=> 'no',
		'desc' 			=> 'Works with "select box" field style'
	),



	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'ph_otp',
		'id'			=> 'otp-digits',
		'title' 		=> 'OTP Digits',
		'default' 		=> '4',
		'args' 			=> array(
			'custom_attributes' => array(
				'autocomplete' => 'disableit'
			)
		)
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'ph_otp',
		'id'			=> 'otp-resend-limit',
		'title' 		=> 'Resend OTP Limit',
		'default' 		=> '8',
		'desc' 			=> 'Maximum number of times OTP can be resent'
	),

	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'ph_otp',
		'id'			=> 'otp-resend-wait',
		'title' 		=> 'Resend OTP Wait Time',
		'default' 		=> '30',
		'desc'			=> 'Waiting time to resend a new OTP (In seconds) '
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'ph_otp',
		'id'			=> 'otp-incorrect-limit',
		'title' 		=> 'Incorrect OTP Limit',
		'default' 		=> '10',
		'desc' 			=> 'Maximum number of tries allowed before user gets banned'
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'ph_otp',
		'id'			=> 'otp-ban-time',
		'title' 		=> 'Ban Time',
		'default' 		=> '600',
		'desc' 			=> 'Time in seconds'
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'ph_otp',
		'id'			=> 'otp-expiry',
		'title' 		=> 'OTP Expiry',
		'default' 		=> '120',
		'desc' 			=> 'In Seconds. OTP expiration time'
	),	


	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_otp',
		'id'			=> 'm-otp-form-type',
		'title' 		=> 'OTP Form Type',
		'default' 		=> 'inline_input',
		'args'			=> array(
			'options' => array(
				'inline_input' 		=> 'Inline Verify Button',
				'external_form'   	=> 'External Form',
			)	
		),
	),


	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'ph_otp',
		'id' 			=> 'r-sms-txt',
		'title' 		=> 'SMS Text',
		'desc' 			=> 'Shortcodes: [otp]"',
		'default' 		=> 'Your OTP for logging in to the Madras Milk application is [otp]',
	),



	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_reg',
		'id' 			=> 'r-enable-phone',
		'title' 		=> 'Enable Phone Verification',
		'default' 		=> 'yes',
		'desc' 			=> ''
	),


	

	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_reg',
		'id' 			=> 'r-phone-field',
		'title' 		=> 'Phone Field',
		'default' 		=> 'required',
		'args'			=> array(
			'options' => array(
				'show_optional' 	=> 'Show as optional',
				'required' 			=> 'Required',
			)
		)
	),

	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_reg',
		'id' 			=> 'r-auto-submit',
		'title' 		=> 'Auto submit form',
		'desc' 			=> 'Auto submit registration form on otp verification.',
		'default' 		=> 'yes'
	),

	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_login',
		'id' 			=> 'l-enable-login-with-otp',
		'title' 		=> 'Login with Phone OTP',
		'default' 		=> 'yes',
		'desc' 			=> ''
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_login',
		'id' 			=> 'l-login-display',
		'title' 		=> 'Display OTP login form first',
		'default' 		=> 'yes',
		'desc' 			=> ''
	),

	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'ph_login',
		'id' 			=> 'l-redirect',
		'title' 		=> 'Login with OTP Redirect',
		'desc' 			=> 'Leave empty to redirect on the same page',
		'default' 		=> '',
	),

	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'ph_emlogin',
		'id' 			=> 'm-email-otp',
		'title' 		=> 'Login with email OTP',
		'default' 		=> 'yes',
		'pro' 			=> 'yes'
	),


	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'ph_emlogin',
		'id' 			=> 'm-em-txt',
		'title' 		=> 'Email OTP Text',
		'desc' 			=> 'Shortcodes: [otp]',
		'default' 		=> '[otp] is your One Time Password (OTP) to login at '.get_bloginfo( 'name' ),
		'pro' 			=> 'yes'
	),

	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'ph_emlogin',
		'id' 			=> 'm-em-subj',
		'title' 		=> 'Email OTP Subject',
		'default' 		=> __( 'One time password', 'mobile-login-woocommerce' ),
		'pro' 			=> 'yes'
	),


	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'ph_emlogin',
		'id' 			=> 'm-em-sender-name',
		'title' 		=> 'From - Sender Name',
		'desc' 			=> 'How the sender name appears in outgoing emails.',
		'default' 		=> get_bloginfo( 'name' ),
		'pro' 			=> 'yes'
	),

	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'ph_emlogin',
		'id' 			=> 'm-em-sender-em',
		'title' 		=> 'From - Sender Email',
		'desc' 			=> 'How the sender email appears in outgoing emails.',
		'args' 			=> array(
			'custom_attributes' => array(
				'autocomplete' => 'disableit'
			)
		),
		'pro' 			=> 'yes'
	),

);


if( class_exists( 'woocommerce' ) ){

	$settings[] = array(
		'callback' 		=> 'select',
		'section_id' 	=> 'ph_emlogin',
		'id' 			=> 'm-email-temp',
		'title' 		=> 'Email Template',
		'args' 			=> array(
			'options' => array(
				'plugin' 		=> 'Plugin',
				'woocommerce' 	=> 'Woocommerce'
			),
		),
		'default' 		=> 'woocommerce',
		'pro' 			=> 'yes'
	);


	$settings[] = array(
		'callback' 		=> 'text',
		'section_id' 	=> 'ph_emlogin',
		'id' 			=> 'm-email-wctitle',
		'title' 		=> 'WC Email Title',
		'default' 		=> 'One Time Password for {site_title}',
		'desc' 			=> '{site_title}',
		'pro' 			=> 'yes'
	);


	$settings[] = array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'woocommerce',
		'id' 			=> 'wc-en-chk',
		'title' 		=> 'Enable OTP verification',
		'default' 		=> 'yes',
		'pro' 			=> 'yes'
	);


	$settings[] = array(
		'callback' 		=> 'number',
		'section_id' 	=> 'woocommerce',
		'id'			=> 'wc-chk-priority',
		'title' 		=> 'Phone Field Location',
		'default' 		=> 200,
		'desc' 			=> 'Field positioning in checkout form, woocommerce default billing phone value is 100',
		'pro' 			=> 'yes'
	);


	$settings[] = array(
		'callback' 		=> 'select',
		'section_id' 	=> 'woocommerce',
		'id'			=> 'wc-chk-bphone',
		'title' 		=> 'Woocommerce Billing Phone field',
		'default' 		=> 'disable_merge',
		'args'			=> array(
			'options' => array(
				'disable' 			=> 'Disable',
				'disable_merge'  	=> 'Disable & merge with OTP field',
				'nothing' 			=> 'Do nothing'
			)	
		),
		'desc' 			=> 'option to treat default woocommerce billing phone field',
		'pro' 			=> 'yes'
	);


	$settings[] = array(
		'callback' 		=> 'select',
		'section_id' 	=> 'woocommerce',
		'id'			=> 'wc-chk-otp-form-type',
		'title' 		=> 'OTP Form Type',
		'default' 		=> 'inline_input',
		'args'			=> array(
			'options' => array(
				'inline_input' 		=> 'Inline Input',
				'external_form'   	=> 'External Form',
			)	
		),
		'pro' 			=> 'yes'
	);

}


$popupLinks = array();
if( function_exists('xoo_el') ){
	$popupLinks[admin_url('admin.php?page=xoo-el-fields')] = 'Fields';
	$popupLinks[admin_url('admin.php?page=easy-login-woocommerce-settings')] = 'Settings';
}
else{
	$popupLinks['https://wordpress.org/plugins/easy-login-woocommerce/'] = 'Download';
	$popupLinks['https://demo.xootix.com/mobile-login-for-woocommerce/#login'] = 'Demo';
}


$settings[] = array(
	'callback' 		=> 'links',
	'section_id' 	=> 'ph_popup',
	'id'			=> 'fake',
	'title' 		=> 'Login/Signup popup',
	'args'			=> array(
		'options' => $popupLinks
	)
); 


$settings[] = array(
	'callback' 		=> 'select',
	'section_id' 	=> 'ph_popup',
	'id'			=> 'el-otp-form-type',
	'title' 		=> 'OTP Form Type',
	'default' 		=> 'external_form',
	'args'			=> array(
		'options' => array(
			'inline_input' 		=> 'Inline Input',
			'external_form'   	=> 'External Form',
		)	
	)
); 

return apply_filters( 'xoo_ml_admin_settings', $settings, 'phone' );


?>