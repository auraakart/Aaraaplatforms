<?php set_query_var( 'layout_shop', $layout_shop ); ?>
<?php if($loadmore == 1): ?>
	<?php if ( $wp_query->have_posts() ) : ?>
		<?php while ( $wp_query->have_posts() ) : $wp_query->the_post(); ?>
			<li>
			<?php wc_get_template_part( 'content-'.esc_attr($category_view_mode), 'product' ); ?> 
			</li>
		<?php endwhile; // end of the loop. ?>
	<?php else : ?>
		<?php wc_get_template( 'loop/no-products-found.php' ); ?>
	<?php endif; ?>
<?php else : ?>
	<?php if ( $wp_query->have_posts() ) : ?>
		<?php woocommerce_product_loop_start(); ?>
		<?php while ( $wp_query->have_posts() ) : $wp_query->the_post(); ?>
			<li>
			<?php wc_get_template_part( 'content-'.esc_attr($category_view_mode), 'product' ); ?>
			</li>
		<?php endwhile; // end of the loop. ?>
		<?php woocommerce_product_loop_end(); ?>
	<?php else : ?>
		<?php wc_get_template( 'loop/no-products-found.php' ); ?>
	<?php endif; ?>
<?php endif; ?>