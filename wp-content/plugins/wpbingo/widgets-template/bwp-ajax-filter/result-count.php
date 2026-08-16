<?php if($loadmore == 1): ?>
<div class="woocommerce-result-count hidden-xs">
	<?php
	$first  = 1;
	$last   = min( $total, $default_posts_per_page * $paged );
	if ( $last >= $total ) {
		printf( __( 'Showing all %d results', 'wpbingo' ), $total );
	}else{
		printf( _x( 'Showing %1$d&ndash;%2$d of %3$d item(s)', '%1$d = first, %2$d = last, %3$d = total', 'wpbingo' ), $first, $last, $total );
	}
	?>
</div>
<?php else: ?>
<div class="woocommerce-result-count hidden-xs">
	<?php
	$paged    = max( 1, $wp_query->get( 'paged' ) );
	$per_page = $wp_query->get( 'posts_per_page' );
	$total    = $wp_query->found_posts;
	$first    = ( $per_page * $paged ) - $per_page + 1;
	$last     = min( $total, $wp_query->get( 'posts_per_page' ) * $paged );
	if ( 1 === $total ) {
		_e( 'Showing the single result', 'wpbingo' );
	} elseif ( $total <= $per_page || -1 === $per_page ) {
		printf( __( 'Showing all %d results', 'wpbingo' ), $total );
	} else {
		printf( _x( 'Showing %1$d&ndash;%2$d of %3$d item(s)', '%1$d = first, %2$d = last, %3$d = total', 'wpbingo' ), $first, $last, $total );
	}
	?>
</div>
<?php endif; ?>