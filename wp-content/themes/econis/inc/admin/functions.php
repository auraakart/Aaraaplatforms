<?php
function econis_check_theme_options() {
    // check default options
    $econis_settings = econis_global_settings();
    ob_start();
    $options = ob_get_clean();
    $econis_default_settings = json_decode($options, true);
    foreach ($econis_default_settings as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $key1 => $value1) {
                if ($key1 != 'google' && (!isset($econis_settings[$key][$key1]) || !$econis_settings[$key][$key1])) {
                    $econis_settings[$key][$key1] = $econis_default_settings[$key][$key1];
                }
            }
        } else {
            if (!isset($econis_settings[$key])) {
                $econis_settings[$key] = $econis_default_settings[$key];
            }
        }
    }
    return $econis_settings;
}
function econis_options_sidebars() {
    return array(
        'wide-left-sidebar',
        'wide-right-sidebar',
        'left-sidebar',
        'right-sidebar'
    );
}
function econis_options_layouts() {
    return array(
        "full" => array('alt' => esc_html__("Without Sidebar", 'econis'), 'img' => get_template_directory_uri().'/inc/admin/theme_options/layouts/page_full.jpg'),
        "left" => array('alt' => esc_html__("Left Sidebar", 'econis'), 'img' => get_template_directory_uri().'/inc/admin/theme_options/layouts/page_full_left.jpg'),
        "right" => array('alt' => esc_html__("Right Sidebar", 'econis'), 'img' => get_template_directory_uri().'/inc/admin/theme_options/layouts/page_full_right.jpg')
    );
}
if(!function_exists('econis_options_header_types')) :
	function econis_options_header_types() {
		$path = get_template_directory().'/templates/headers/';
		$files = array_diff(scandir($path), array('..', '.'));
		if(count($files)>0){
			foreach ($files as  $file) {
				$name_file = str_replace( '.php', '', basename($file) );
				$value = str_replace( 'header-', '',$name_file);
				$name =  str_replace( '-', ' ', ucwords($name_file) );
				$header[$value] = array('title' => $name, 'img' => get_template_directory_uri().'/inc/admin/theme_options/headers/'.esc_attr($name_file).'.jpg');
			}
		}	
		return $header;	
	}
endif;
function econis_options_banners_effect() {
	$banners_effects = array();
	for ($i = 1; $i <= 12; $i++) {
		$banners_effects['banners-effect-'.$i] =  array('alt' => esc_html__("Banner Effect", 'econis'), 'img' => get_template_directory_uri().'/inc/admin/theme_options/effects/banner-effect.png');
	}
    return $banners_effects;
}
if(!function_exists('econis_get_footers')) :
	function econis_get_footers() {
		$footer = array();
		$footers = get_posts( array('posts_per_page'=>-1,
							'post_type'=>'bwp_footer',
							'orderby'          => 'name',
							'order'            => 'ASC'
					) );
		foreach ($footers as  $key=>$value) {
			$footer[$value->ID] = array('title' => $value->post_title, 'img' => get_template_directory_uri().'/inc/admin/theme_options/footers/'.$value->post_name.'.jpg');
		}
		return $footer;
	}
endif;
// Function for Content Type, ReducxFramework
function econis_ct_related_product_columns() {
    return array(
        "2" => "2",
        "3" => "3",
        "4" => "4",
        "5" => "5",
        "6" => "6"
    );
}
function econis_ct_category_view_mode() {
    return array(
        "grid" => esc_html__("Grid", 'econis'),
		"short" => esc_html__("Short", 'econis'),
		"list" => esc_html__("List", 'econis')
    );
}