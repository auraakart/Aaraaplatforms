<?php
global $post,$wp_query;
if(in_array("search-no-results",get_body_class())){ ?>
   <div class="breadcrumb" class="col-sm-12">
     <a href="<?php esc_url( home_url( '/' )); ?>"><?php echo esc_html__('Home', 'econis'); ?></a>
   <span class="delimiter"></span>
   <span class="current"><?php echo esc_html__('Search results for : ', 'econis') .'"' . esc_html(get_search_query()) . '"'; ?></span> </div>
<?php
    }else{
    	$delimiter = '<span class="delimiter"></span>';
        $before = '<span class="current">';
        $after = '</span> ';
        echo '<div id="breadcrumb" class="breadcrumb">';
			$homeLink = home_url( '/' );
			echo '<div class="bwp-breadcrumb">';
			echo '<a href="' . esc_url( $homeLink ). '">' . esc_html__('Home', 'econis') . '</a> ' . wp_kses($delimiter,'social') . ' ';
			if ( is_category() ) {
				$cat_obj = $wp_query->get_queried_object();
				$thisCat = $cat_obj->term_id;
				$thisCat = get_category($thisCat);
				$parentCat = get_category($thisCat->parent);
				if ($thisCat->parent != 0) 
					echo(get_category_parents($parentCat, TRUE, ' ' . wp_kses($delimiter,'social') . ' '));
				echo wp_kses($before,'social') . '' . esc_html(single_cat_title('', false)) . '' . wp_kses($after,'social');
				echo '</div>';
			}elseif ( class_exists("WCV_Vendors") && WCV_Vendors::is_vendor_page() ){
				$vendor_shop 		= urldecode( get_query_var( 'vendor_shop' ) );
				$vendor_id   		= WCV_Vendors::get_vendor_id( $vendor_shop );
				$shop_name 			= WCV_Vendors::get_vendor_shop_name( stripslashes( $vendor_id ) );
				echo esc_html($shop_name);
				echo '</div>';
			}elseif( class_exists("WeDevs_Dokan") && dokan()->vendor->get( get_query_var( 'author' ) )->data ){
				$store_user = dokan()->vendor->get( get_query_var( 'author' ) );
				$shop_name 	= $store_user->get_shop_name();
				echo esc_html($shop_name);
				echo '</div>';
			}elseif ( is_day() ) {
				echo '<a href="' . esc_url(get_year_link(get_the_time('Y'))) . '">' . esc_html(get_the_time('Y')) . '</a> ' . wp_kses($delimiter,'social') . ' ';
				echo '<a href="' . esc_url(get_month_link(get_the_time('Y'),get_the_time('m'))) . '">' . esc_html(get_the_time('F')) . '</a> ' . wp_kses($delimiter,'social') . ' ';
				echo wp_kses($before,'social') . esc_html__('Archive by date ','econis') .'"' . esc_html(get_the_time('d')) .'"' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_month() ) {
				echo '<a href="' . esc_url(get_year_link(get_the_time('Y'))) . '">' . esc_html(get_the_time('Y')) . '</a> ' . wp_kses($delimiter,'social') . ' ';
				echo wp_kses($before,'social') . esc_html__('Archive by month ','econis') .'"' . esc_html(get_the_time('F')) .'"' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_year() ) {
				echo wp_kses($before,'social') . esc_html__('Archive by year ', 'econis') .'"' . esc_html(get_the_time('Y')) . '"' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_single() && !is_attachment() ) {
				if ( get_post_type() != 'post' ) {
					$post_type = get_post_type_object(get_post_type());
					$slug = $post_type->rewrite;
					echo '<a href="' . esc_url($homeLink) . '/' . esc_attr($slug['slug']) . '/">' . esc_html($post_type->labels->singular_name) . '</a>' . wp_kses($delimiter,'social') . ' ';
					echo wp_kses($before,'social') . esc_html(get_the_title()) . wp_kses($after,'social');
					echo wp_kses($before,'social') . '<span class="breadcrumb-title">' . esc_html(get_the_title()) . '</span>' . wp_kses($after,'social');
				} else {
					$cat = get_the_category();
					if(!empty($cat)){
						$cat = $cat[0];
						echo ' ' . get_category_parents($cat, TRUE, ' ' . wp_kses($delimiter,'social') . ' ') . ' ';
					}	
					echo wp_kses($before,'social') . '' . get_the_title() . '' . wp_kses($after,'social');
				}
				echo '</div>';
			} elseif ( !is_single() && !is_page() && get_post_type() != 'post' && !is_404() ) {
				$post_type = get_post_type_object(get_post_type());
				echo wp_kses($before,'social') . esc_html($post_type->labels->singular_name) . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_attachment() ) {
				$parent_id  = $post->post_parent;
				$breadcrumbs = array();
				while ($parent_id) {
					$page = get_page($parent_id);
					$breadcrumbs[] = '<a href="' . esc_url(get_permalink($page->ID)) . '">' . esc_html(get_the_title($page->ID)) . '</a>';
					$parent_id    = $page->post_parent;
				}
				$breadcrumbs = array_reverse($breadcrumbs);
				foreach ($breadcrumbs as $crumb) echo ' ' . wp_kses($crumb,'social') . ' ' . wp_kses($delimiter,'social') . ' ';
				echo wp_kses($before,'social') . '' . esc_html(get_the_title()) . '' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_page() && !$post->post_parent ) {
				echo wp_kses($before,'social') . '' . esc_html(get_the_title()) . '' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_page() && $post->post_parent ) {
				$parent_id  = $post->post_parent;
				$breadcrumbs = array();
				while ($parent_id) {
					$page = get_page($parent_id);
					$breadcrumbs[] = '<a href="' . esc_url(get_permalink($page->ID)) . '">' . esc_html(get_the_title($page->ID)) . '</a>';
					$parent_id    = $page->post_parent;
				}
				$breadcrumbs = array_reverse($breadcrumbs);
				foreach ($breadcrumbs as $crumb) echo ' ' . wp_kses($crumb,'social') . ' ' . wp_kses($delimiter,'social') . ' ';
				echo wp_kses($before,'social') . '' . esc_html(get_the_title()) . '' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_search()) {
				 echo wp_kses($before,'social') . esc_html__('Search results for : ','econis') .'"' . esc_html(get_search_query()) . '"' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_tag() ) {
				echo wp_kses($before,'social') . esc_html__('Archive by tag ','econis') .'"' . esc_html(single_tag_title('', false)) . '"' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_author() ) {
				global $author;
				$userdata = get_userdata($author);
				echo wp_kses($before,'social') . esc_html__(' Articles posted by ','econis') .'"' . esc_html($userdata->display_name) . '"' . wp_kses($after,'social');
				echo '</div>';
			} elseif ( is_404() ) {
				echo wp_kses($before,'social') . esc_html__('You got it ','econis') .'"' . esc_html__(' Error 404 not Found ','econis') . '"&nbsp;' . wp_kses($after,'social');
				echo '</div>';
			}else{
				echo wp_kses($before,'social') . esc_html__('Blog','econis') . wp_kses($after,'social');
				echo '</div>';
			}	
			if ( get_query_var('paged') ) {
				if ( is_category() || is_day() || is_month() || is_year() || is_search() || is_tag() || is_author() ) echo '';
			}
			echo '</div>';
    }
?>