<?php
/**
 * Pagination - Show numbered pagination for catalog pages
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/pagination.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you (the theme developer).
 * will need to copy the new files to your theme to maintain compatibility. We try to do this.
 * as little as possible, but it does happen. When this occurs the version of the template file will.
 * be bumped and the readme will list any important changes.
 *
 * @see 	    http://docs.woothemes.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     9.3.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
global $wp_query;
if ( $wp_query->max_num_pages <= 1 ) {
	return;
}
$shop_paging	= econis_get_config('shop_paging','shop-pagination');
?>
<?php if( $shop_paging == "shop-loadmore" ): ?>
<nav class="woocommerce-pagination <?php echo esc_attr($shop_paging); ?>">
	<div class="woocommerce-load-more" data-paged="1">
		<?php echo esc_html__("Load more","econis"); ?>
	</div>
</nav>
<?php elseif ($shop_paging == "shop-infinity"): ?>
<nav class="woocommerce-pagination <?php echo esc_attr($shop_paging); ?>">
	<div class="woocommerce-load-more" data-paged="1">
		<div class="loading-filter"></div>
	</div>
</nav>
<?php else: ?>
<nav class="woocommerce-pagination">
	<?php
		echo paginate_links( apply_filters( 'woocommerce_pagination_args', array(
			'base'         => esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) ),
			'format'       => '',
			'add_args'     => false,
			'current'      => max( 1, get_query_var( 'paged' ) ),
			'total'        => $wp_query->max_num_pages,
			'prev_text'    => esc_html__('Previous','econis'),
			'next_text'    => esc_html__('Next','econis'),
			'type'         => 'list',
			'end_size'     => 3,
			'mid_size'     => 3
		) ) );
	?>
</nav>
<?php endif; ?>