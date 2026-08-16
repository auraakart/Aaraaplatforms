<?php if ( $wp_query->have_posts() ) : ?>
		<?php while ( $wp_query->have_posts() ) : $wp_query->the_post(); ?>
			<div class="item-product <?php echo esc_attr($attributes); ?>">
			<?php
				if($content_product)
					include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product'.esc_attr($content_product).'.php'); 
				else
					include(WPBINGO_ELEMENTOR_TEMPLATE_PATH.'content-product.php');
			?>
			</div>
		<?php endwhile;
endif; ?>	
