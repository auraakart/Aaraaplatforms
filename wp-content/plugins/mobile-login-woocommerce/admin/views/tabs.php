<?php

$tabs = array(
	'phone' => array(
		'title'			=> 'Phone',
		'id' 			=> 'phone',
		'option_key' 	=> 'xoo-ml-phone-options'
	),

	'services' => array(
		'title'			=> 'Services',
		'id' 			=> 'services',
		'option_key' 	=> 'xoo-ml-services-options'
	),

	'pro' => array(
		'title'			=> 'PRO',
		'id' 			=> 'pro',
		'option_key' 	=> '',
	),



);

return apply_filters( 'xoo_ml_admin_settings_tabs', $tabs );