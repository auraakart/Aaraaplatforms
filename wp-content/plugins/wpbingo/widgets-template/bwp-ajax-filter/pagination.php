<?php
if ( $wp_query->max_num_pages <= 1 ) {
	return;
}
?>
<?php if( $shop_paging == "shop-loadmore" ): ?>
<nav class="woocommerce-pagination <?php echo esc_attr($shop_paging); ?>">
	<div class="woocommerce-load-more" data-paged="<?php echo esc_attr($paged); ?>">
		<?php echo esc_html__("Load more","wpbingo"); ?>
	</div>
</nav>
<?php elseif ($shop_paging == "shop-infinity"): ?>
<nav class="woocommerce-pagination <?php echo esc_attr($shop_paging); ?>">
	<div class="woocommerce-load-more" data-paged="<?php echo esc_attr($paged); ?>">
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
			'prev_text'    => esc_html__('Previous','wpbingo'),
			'next_text'    => esc_html__('Next','wpbingo'),
			'type'         => 'list',
			'end_size'     => 3,
			'mid_size'     => 3
		) ) );
	?>
</nav>
<?php endif; ?>