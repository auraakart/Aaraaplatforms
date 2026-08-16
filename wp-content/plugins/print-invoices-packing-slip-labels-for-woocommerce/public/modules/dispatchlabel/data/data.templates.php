<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$template_arr=array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	array(
		'id'=>'template1',
		'title'=>__('Dispatch Label 1', 'print-invoices-packing-slip-labels-for-woocommerce'),
		'preview_img'=>'template1.png',
	),
);
$template_arr = apply_filters("wt_pklist_add_pro_templates",$template_arr,$this->to_customize); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound