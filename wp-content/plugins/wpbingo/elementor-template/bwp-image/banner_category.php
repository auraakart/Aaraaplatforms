<div class="bwp-widget-banner <?php echo esc_html( $layout ); ?>">
	<?php  if($image): ?>	
	<div class="bg-banner">
		<div class="bwp-image">
			<img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr__("Banner Category","wpbingo"); ?>">
		</div>
		<?php if(isset($category) && $category) : ?>
			<?php $term = get_term_by('slug', $category, 'product_cat'); ?>
			<?php if($term) : ?>
				<div class="item-content">
					<h3 class="iten-name"><a href="<?php echo get_term_link( $term->term_id, 'product_cat' ); ?>"><?php echo esc_html($term->name); ?></a></h3>
					<?php if ($show_count) { ?>
					<div class="item-count">
						<?php if($term->count == 1){?>
							<?php echo esc_attr($term->count) .'<span>'. esc_html__(' products', 'wpbingo').'</span>'; ?>
						<?php }else{ ?>
							<?php echo esc_attr($term->count) .'<span>'. esc_html__(' products', 'wpbingo').'</span>'; ?>
						<?php } ?>
					</div>
					<?php } ?>
				</div>
			<?php endif; ?>
		<?php endif;?>
		<?php if( $title1) : ?>
			<div class="title-banner"><h3 class="title-style"><a href="<?php echo get_term_link( $term->term_id, 'product_cat' ); ?>"><i class="arrow_up"></i><?php echo esc_html( $title1 ); ?></a></h3></div>
		<?php endif;?>
	</div>
	<?php endif;?>
</div>
