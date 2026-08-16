<?php

$sections = array(

	/* General TAB Sections */
	array(
		'title' => 'Main',
		'id' 	=> 'ph_main',
		'tab' 	=> 'phone',
	),

	array(
		'title' => 'Country code',
		'id' 	=> 'ph_cc',
		'tab' 	=> 'phone',
	),

	array(
		'title' => 'OTP',
		'id' 	=> 'ph_otp',
		'tab' 	=> 'phone',
	),

	array(
		'title' => 'Register',
		'id' 	=> 'ph_reg',
		'tab' 	=> 'phone',
	),

	array(
		'title' => 'Login',
		'id' 	=> 'ph_login',
		'tab' 	=> 'phone',
	),


	array(
		'id' 	=> 'woocommerce',
		'title' => 'WooCommerce Checkout Settings',
		'tab' 	=> 'phone',
		'pro' 	=> 'yes'
	),


	array(
		'title' => 'Login with Email OTP',
		'id' 	=> 'ph_emlogin',
		'tab' 	=> 'phone',
		'pro' 	=> 'yes',
	),

	array(
		'id' 	=> 'ph_popup',
		'title' => 'Popup Settings',
		'tab' 	=> 'phone',
		'desc' 	=> !function_exists('xoo_el') ? '<b>PRO: </b> Integrate OTP login with our Login/Register popup plugin to gain better control over your design. It comes packed with a ton of features.<br>The popup plugin is <b>FREE</b>, you can download and play with it.' : "<b>**NOTE**</b> You will need the pro version to integrate it with our login/signup popup plugin. The free version works well with the woocommerce's login/register form"
	),


	array(
		'title' => 'Whatsapp Settings',
		'id' 	=> 'sv_whatsapp',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/whatsapp/" target="_blank">Documentation</a>'
	),


	array(
		'title' => 'Firebase Settings',
		'id' 	=> 'sv_firebase',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/google-firebase/" target="_blank">Documentation</a>'
	),


	array(
		'title' => 'Amazon SNS Settings',
		'id' 	=> 'sv_aws',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/amazon-sns/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Twilio Settings',
		'id' 	=> 'sv_twilio',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/twilio/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Bulk SMS Settings',
		'id' 	=> 'sv_bulksms',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/bulksms/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Bulk(s) SMS Settings',
		'id' 	=> 'sv_bulkssms',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/bulkssms/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Net GSM Settings',
		'id' 	=> 'sv_netgsm',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/netgsm/" target="_blank">Documentation</a>'
	),


	array(
		'title' => 'OurSMS Settings',
		'id' 	=> 'sv_oursms',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/oursms/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'SMS Alert Settings',
		'id' 	=> 'sv_smsalert',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/smsalert/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Unifonic Settings',
		'id' 	=> 'sv_unifonic',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/unifonic/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Msg91 Settings',
		'id' 	=> 'sv_msg91',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/unifonic/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'TextLocal Settings',
		'id' 	=> 'sv_txlocal',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/textlocal/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'SMSLane Settings',
		'id' 	=> 'sv_smslane',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/smslane/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Semaphore',
		'id' 	=> 'sv_semaphore',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/semaphore/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Msegat',
		'id' 	=> 'sv_msegat',
		'tab' 	=> 'services',
		'desc' 	=> '<a href="https://docs.xootix.com/mobile-login-for-woocommerce/msegat/" target="_blank">Documentation</a>'
	),

	array(
		'title' => 'Custom',
		'id' 	=> 'sv_custom',
		'tab' 	=> 'services',
		'desc' 	=> "If your service operator isn't listed, you can manually include it. Please refer to your operator documentation<br>Enable debug under gemeral tab to see the response from SMS operator.",
	),

);

return apply_filters( 'xoo_ml_admin_settings_sections', $sections );